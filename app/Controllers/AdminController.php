<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ImageService;
use App\Services\OrderService;
use App\Services\ProductAddonService;
use App\Services\ProductCustomizationService;
use App\Services\SmtpClient;
use App\Services\SmartProductSearchService;
use App\Services\StripeCatalogService;
use App\Services\StripeClient;
use PDO;

final class AdminController
{
    private const PRODUCT_LIMIT = 350;
    private const PRODUCT_LIMIT_MESSAGE = 'Limita de 350 de produse a fost atinsa, contacteaza dezvoltatorul pentru un upgrade al planului';

    public function dashboard(Request $request): void
    {
        $db = Database::connection();
        $period = (string) ($request->query['period'] ?? '7d');
        $periodLabels = ['7d'=>'Ultimele 7 zile','week'=>'Săptămâna curentă','month'=>'Ultimele 30 de zile','3m'=>'Ultimele 3 luni','6m'=>'Ultimele 6 luni','1y'=>'Ultimul an','all'=>'Tot timpul'];
        if (!isset($periodLabels[$period])) $period = '7d';
        $today = new \DateTimeImmutable('today');
        $bucket = 'day';
        $start = match ($period) {
            'week' => $today->modify('monday this week'),
            'month' => $today->modify('-29 days'),
            '3m' => $today->modify('monday this week')->modify('-12 weeks'),
            '6m' => $today->modify('first day of this month')->modify('-5 months'),
            '1y' => $today->modify('first day of this month')->modify('-11 months'),
            'all' => new \DateTimeImmutable((string) ($db->query('SELECT COALESCE(DATE_FORMAT(MIN(created_at),"%Y-%m-01"),DATE_FORMAT(CURDATE(),"%Y-%m-01")) FROM orders')->fetchColumn() ?: 'today')),
            default => $today->modify('-6 days'),
        };
        if ($period === '3m') $bucket = 'week';
        if (in_array($period, ['6m','1y','all'], true)) $bucket = 'month';
        $end = $today->modify('+1 day');
        $periodParams = [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 00:00:00')];
        $metric = static function (PDO $db, string $sql, array $params): float {
            $stmt = $db->prepare($sql); $stmt->execute($params); return (float) $stmt->fetchColumn();
        };
        $stats = [
            'orders_period' => (int) $metric($db, 'SELECT COUNT(*) FROM orders WHERE created_at>=? AND created_at<?', $periodParams),
            'sales_period' => $metric($db, 'SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at>=? AND created_at<? AND status NOT IN ("cancelled","returned")', $periodParams),
            'active_orders' => (int) $db->query('SELECT COUNT(*) FROM orders WHERE status IN ("received","confirmed","processing","prepared","shipped")')->fetchColumn(),
            'low_stock' => (int) $db->query('SELECT COUNT(*) FROM products WHERE manage_stock=1 AND stock_status="in_stock" AND stock_quantity<=low_stock_threshold AND status="active"')->fetchColumn(),
            'new_customers_period' => (int) $metric($db, 'SELECT COUNT(*) FROM users WHERE role="customer" AND created_at>=? AND created_at<?', $periodParams),
        ];
        $orders = $db->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();
        $bucketSql = match ($bucket) {
            'week' => 'DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))',
            'month' => 'DATE_FORMAT(created_at,"%Y-%m-01")',
            default => 'DATE(created_at)',
        };
        $seriesStmt = $db->prepare('SELECT ' . $bucketSql . ' bucket_date,COALESCE(SUM(total),0) total,COUNT(*) order_count FROM orders WHERE created_at>=? AND created_at<? AND status NOT IN ("cancelled","returned") GROUP BY bucket_date ORDER BY bucket_date');
        $seriesStmt->execute($periodParams);
        $seriesValues = [];
        foreach ($seriesStmt->fetchAll() as $row) $seriesValues[(string) $row['bucket_date']] = ['total'=>(float) $row['total'],'orders'=>(int) $row['order_count']];
        $salesSeries = [];
        $cursor = $start;
        while ($cursor <= $today) {
            $key = $cursor->format('Y-m-d');
            $label = $bucket === 'month' ? $cursor->format('m.Y') : ($bucket === 'week' ? $cursor->format('d.m') : $cursor->format('d.m'));
            $salesSeries[] = ['date'=>$key,'label'=>$label,'total'=>$seriesValues[$key]['total'] ?? 0.0,'orders'=>$seriesValues[$key]['orders'] ?? 0];
            $cursor = $cursor->modify($bucket === 'month' ? '+1 month' : ($bucket === 'week' ? '+1 week' : '+1 day'));
        }
        View::render('admin/dashboard', compact('stats', 'orders','salesSeries','period','periodLabels'), 'layouts/admin');
    }

    public function products(Request $request): void
    {
        $db = Database::connection();
        (new StripeCatalogService())->ensureSchema($db);
        $catalogTotal = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $q = trim((string) ($request->query['q'] ?? ''));
        $status = trim((string) ($request->query['status'] ?? ''));
        $perPage = (int) ($request->query['per_page'] ?? 20);
        if (!in_array($perPage, [20, 50, 100], true)) $perPage = 20;
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $where = ['1=1'];
        $params = [];
        $searchOrder = '';
        if ($q !== '') {
            $ids = (new SmartProductSearchService())->rankedIds($q);
            if ($ids) {
                $idList = implode(',', array_map('intval', $ids));
                $where[] = 'p.id IN (' . $idList . ')';
                $searchOrder = 'FIELD(p.id,' . $idList . ')';
            } else $where[] = '0=1';
        }
        if (in_array($status, ['active','draft','hidden','archived'], true)) { $where[] = 'p.status=?'; $params[] = $status; }
        $from = ' FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id WHERE ' . implode(' AND ', $where);
        $countStmt = $db->prepare('SELECT COUNT(DISTINCT p.id)' . $from);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT p.*,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order LIMIT 1) image_path,GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") categories,(SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id=p.id AND pv.status="active") variant_count,(SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id=p.id AND pv.status="active" AND pv.stock_quantity IS NOT NULL) variant_limited_count,(SELECT COALESCE(SUM(pv.stock_quantity),0) FROM product_variants pv WHERE pv.product_id=p.id AND pv.status="active" AND pv.stock_quantity IS NOT NULL) variant_stock' . $from . ' GROUP BY p.id ORDER BY ' . ($searchOrder ?: 'p.updated_at DESC') . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        View::render('admin/products', ['products' => $stmt->fetchAll(), 'q' => $q, 'status' => $status, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage, 'catalogTotal' => $catalogTotal, 'productLimit' => self::PRODUCT_LIMIT, 'productLimitMessage' => self::PRODUCT_LIMIT_MESSAGE, 'productSaved' => Session::pullFlash('product_saved')], 'layouts/admin');
    }

    public function productForm(Request $request): void
    {
        $db = Database::connection();
        $customization = new ProductCustomizationService();
        $customization->ensureSchema($db);
        $addons = new ProductAddonService();
        $addons->ensureSchema($db);
        $id = (int) ($request->params['id'] ?? 0); $product = null; $selected = []; $images = []; $variants = []; $customizationFields = []; $productAddons = [];
        if (!$id && (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() >= self::PRODUCT_LIMIT) {
            Session::flash('error', self::PRODUCT_LIMIT_MESSAGE);
            Response::redirect('/admin/produse');
        }
        if ($id) { $stmt = Database::connection()->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]); $product = $stmt->fetch(); if (!$product) { http_response_code(404); View::render('errors/404'); return; } $stmt = Database::connection()->prepare('SELECT category_id FROM product_categories WHERE product_id=?'); $stmt->execute([$id]); $selected = array_map('intval', array_column($stmt->fetchAll(), 'category_id')); $stmt = Database::connection()->prepare('SELECT * FROM product_images WHERE product_id=? ORDER BY is_featured DESC,sort_order'); $stmt->execute([$id]); $images = $stmt->fetchAll(); $stmt = Database::connection()->prepare('SELECT * FROM product_variants WHERE product_id=? ORDER BY id'); $stmt->execute([$id]); $variants = $stmt->fetchAll(); }
        if ($id) $customizationFields = $customization->fields($id, $db);
        if ($id) $productAddons = $addons->configured($id, $db);
        $addonCatalog = $addons->catalog($id, $db);
        $categories = Database::connection()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
        View::render('admin/product-form', compact('product','selected','images','categories','variants','customizationFields','productAddons','addonCatalog'), 'layouts/admin');
    }

    public function checkProductAvailability(Request $request): void
    {
        $field = trim((string) $request->input('field'));
        $value = trim((string) $request->input('value'));
        $productId = max(0, (int) $request->input('id', 0));
        $fields = [
            'name' => ['column' => 'name', 'available' => 'Numele produsului este disponibil.', 'taken' => 'Există deja un produs cu acest nume.'],
            'slug' => ['column' => 'slug', 'available' => 'Adresa produsului este disponibilă.', 'taken' => 'Această adresă este deja folosită de alt produs.'],
            'sku' => ['column' => 'sku', 'available' => 'Codul SKU este disponibil.', 'taken' => 'Acest cod SKU există deja. Alege alt cod.'],
            'gtin' => ['column' => 'gtin', 'available' => 'Codul EAN / GTIN este disponibil.', 'taken' => 'Acest cod EAN / GTIN există deja.'],
            'variant_sku' => ['column' => 'sku', 'available' => 'SKU-ul variantei este disponibil.', 'taken' => 'Acest SKU este deja folosit de un produs sau de altă variantă.'],
        ];

        if (!isset($fields[$field])) {
            Response::json(['available' => false, 'state' => 'error', 'message' => 'Câmpul nu poate fi verificat.'], 422);
            return;
        }

        if ($value === '') {
            $message = match ($field) {
                'sku' => 'Lasă câmpul liber și SKU-ul va fi generat automat la salvare.',
                'variant_sku' => 'Lasă câmpul liber și SKU-ul variantei va fi generat automat la salvare.',
                'gtin' => 'Opțional — completează numai dacă produsul are un cod de bare.',
                'slug' => 'Adresa se generează automat din numele produsului.',
                default => 'Introdu numele produsului pentru verificare.',
            };
            Response::json(['available' => true, 'state' => 'empty', 'message' => $message]);
            return;
        }

        $definition = $fields[$field];
        $db = Database::connection();
        if ($field === 'sku') {
            $stmt = $db->prepare('SELECT 1 FROM products WHERE LOWER(TRIM(sku))=LOWER(TRIM(?)) AND id<>? UNION ALL SELECT 1 FROM product_variants WHERE LOWER(TRIM(sku))=LOWER(TRIM(?)) LIMIT 1');
            $stmt->execute([$value, $productId, $value]);
        } elseif ($field === 'variant_sku') {
            $variantId = max(0, (int) $request->input('variant_id', 0));
            $stmt = $db->prepare('SELECT 1 FROM products WHERE LOWER(TRIM(sku))=LOWER(TRIM(?)) UNION ALL SELECT 1 FROM product_variants WHERE LOWER(TRIM(sku))=LOWER(TRIM(?)) AND id<>? LIMIT 1');
            $stmt->execute([$value, $value, $variantId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM products WHERE LOWER(TRIM(' . $definition['column'] . '))=LOWER(TRIM(?)) AND id<>? LIMIT 1');
            $stmt->execute([$value, $productId]);
        }
        $match = $stmt->fetchColumn();
        Response::json([
            'available' => !$match,
            'state' => $match ? 'taken' : 'available',
            'message' => $match ? $definition['taken'] : $definition['available'],
        ]);
    }

    public function saveProduct(Request $request): void
    {
        $id = (int) ($request->params['id'] ?? 0); $isCreate = $id === 0; $db = Database::connection(); $oldSlug = null;
        if (!$id && (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() >= self::PRODUCT_LIMIT) {
            Session::flash('error', self::PRODUCT_LIMIT_MESSAGE);
            Response::redirect('/admin/produse');
        }
        $customization = new ProductCustomizationService();
        $customization->ensureSchema($db);
        $addons = new ProductAddonService();
        $addons->ensureSchema($db);
        if ($id) { $stmt = $db->prepare('SELECT slug FROM products WHERE id=?'); $stmt->execute([$id]); $oldSlug = $stmt->fetchColumn(); }
        $slug = slugify(trim((string) $request->input('slug')) ?: trim((string) $request->input('name')));
        $manageStock = (int) (bool) $request->input('manage_stock');
        $requestedStockStatus = (string) $request->input('stock_status', 'in_stock');
        $stockStatus = $manageStock && in_array($requestedStockStatus, ['in_stock','out_of_stock','on_backorder'], true) ? $requestedStockStatus : 'in_stock';
        $stockInput = $request->input('stock_quantity');
        $stockQuantity = $manageStock && $stockInput !== null && $stockInput !== '' ? (int) $stockInput : null;
        $lowStockInput = $request->input('low_stock_threshold');
        $lowStockThreshold = $lowStockInput !== null && $lowStockInput !== '' ? (int) $lowStockInput : 3;
        $allowBackorders = $manageStock ? (int) (bool) $request->input('allow_backorders') : 0;
        $customizationLabels = array_filter(array_map(fn ($label) => trim((string) $label), (array) $request->input('customization_field_label', [])));
        if ($request->input('is_customizable') && !$customizationLabels) {
            Session::flash('error', 'Adaugă cel puțin un câmp înainte să activezi personalizarea.');
            Response::redirect($id ? '/admin/produse/' . $id . '/editare?step=5' : '/admin/produse/creare?step=5');
        }
        $addonProductIds = array_values(array_filter(array_map('intval', (array) $request->input('addon_product_ids', []))));
        if ($request->input('addons_enabled') && !$addonProductIds) {
            Session::flash('error', 'Selectează cel puțin un produs suplimentar înainte să activezi această opțiune.');
            Response::redirect($id ? '/admin/produse/' . $id . '/editare?step=6' : '/admin/produse/creare?step=6');
        }
        $data = [trim((string) $request->input('name')), $slug, trim((string) $request->input('sku')) ?: null, trim((string) $request->input('gtin')) ?: null, (string) $request->input('short_description'), sanitize_rich_html((string) $request->input('description')), (float) $request->input('regular_price'), $request->input('sale_price') !== '' ? (float) $request->input('sale_price') : null, $manageStock, $stockQuantity, $lowStockThreshold, $stockStatus, $allowBackorders, (int) (bool) $request->input('featured'), max(0, (int) $request->input('featured_order')), (int) (bool) $request->input('is_customizable'), max(0, (float) $request->input('customization_price', 0)), mb_substr(trim((string) $request->input('badge_text')), 0, 80) ?: null, trim((string) $request->input('brand')) ?: null, (string) $request->input('status', 'draft'), trim((string) $request->input('meta_title')) ?: null, trim((string) $request->input('meta_description')) ?: null, trim((string) $request->input('canonical_url')) ?: null, (int) (bool) $request->input('indexable')];
        Database::transaction(function (PDO $db) use (&$id, $data, $request, $oldSlug, $customization, $addons, $addonProductIds) {
            if ($id) { $stmt = $db->prepare('UPDATE products SET name=?,slug=?,sku=?,gtin=?,short_description=?,description=?,regular_price=?,sale_price=?,manage_stock=?,stock_quantity=?,low_stock_threshold=?,stock_status=?,allow_backorders=?,featured=?,featured_order=?,is_customizable=?,customization_price=?,badge_text=?,brand=?,status=?,meta_title=?,meta_description=?,canonical_url=?,indexable=? WHERE id=?'); $stmt->execute([...$data, $id]); }
            else { $stmt = $db->prepare('INSERT INTO products (name,slug,sku,gtin,short_description,description,regular_price,sale_price,manage_stock,stock_quantity,low_stock_threshold,stock_status,allow_backorders,featured,featured_order,is_customizable,customization_price,badge_text,brand,status,meta_title,meta_description,canonical_url,indexable) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'); $stmt->execute($data); $id = (int) $db->lastInsertId(); }
            if ($data[2] === null) {
                $base = 'SB-P' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                $sku = $base; $suffix = 2;
                $checkSku = $db->prepare('SELECT COUNT(*) FROM products WHERE sku=? AND id<>?');
                do { $checkSku->execute([$sku, $id]); if (!(int) $checkSku->fetchColumn()) break; $sku = $base . '-' . $suffix++; } while (true);
                $db->prepare('UPDATE products SET sku=? WHERE id=?')->execute([$sku, $id]);
            }
            if ($oldSlug && $oldSlug !== $data[1]) $db->prepare('INSERT INTO redirects (old_path,new_path,status_code) VALUES (?,?,301) ON DUPLICATE KEY UPDATE new_path=VALUES(new_path)')->execute(['/produs/' . $oldSlug, '/produs/' . $data[1]]);
            $saleStart = trim((string) $request->input('sale_start'));
            $saleEnd = trim((string) $request->input('sale_end'));
            $db->prepare('UPDATE products SET sale_start=?,sale_end=? WHERE id=?')->execute([$saleStart !== '' ? date('Y-m-d H:i:s', strtotime($saleStart)) : null, $saleEnd !== '' ? date('Y-m-d H:i:s', strtotime($saleEnd)) : null, $id]);
            $db->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$id]);
            foreach ((array) $request->input('categories', []) as $categoryId) $db->prepare('INSERT IGNORE INTO product_categories (product_id,category_id) VALUES (?,?)')->execute([$id, (int) $categoryId]);
            $db->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$id]);
            $labels = (array) $request->input('variant_label', []);
            $skus = (array) $request->input('variant_sku', []);
            $regular = (array) $request->input('variant_regular_price', []);
            $sale = (array) $request->input('variant_sale_price', []);
            $stock = (array) $request->input('variant_stock_quantity', []);
            $stockStatus = (array) $request->input('variant_stock_status', []);
            $variantStatus = (array) $request->input('variant_status', []);
            $insertVariant = $db->prepare('INSERT INTO product_variants (product_id,label,sku,regular_price,sale_price,stock_quantity,stock_status,status) VALUES (?,?,?,?,?,?,?,?)');
            $productSkuStmt = $db->prepare('SELECT sku FROM products WHERE id=?');
            $productSkuStmt->execute([$id]);
            $productSku = trim((string) $productSkuStmt->fetchColumn()) ?: ('SB-P' . str_pad((string) $id, 6, '0', STR_PAD_LEFT));
            $skuUsedByProduct = $db->prepare('SELECT COUNT(*) FROM products WHERE LOWER(TRIM(sku))=LOWER(TRIM(?))');
            $skuUsedByVariant = $db->prepare('SELECT COUNT(*) FROM product_variants WHERE LOWER(TRIM(sku))=LOWER(TRIM(?))');
            $usedVariantSkus = [];
            foreach ($labels as $index => $label) {
                $label = trim((string) $label);
                if ($label === '') continue;
                $variantSku = trim((string) ($skus[$index] ?? ''));
                if ($variantSku === '') {
                    $sequence = $index + 1;
                    do {
                        $variantSku = $productSku . '-V' . str_pad((string) $sequence++, 3, '0', STR_PAD_LEFT);
                        $skuUsedByProduct->execute([$variantSku]);
                        $skuUsedByVariant->execute([$variantSku]);
                    } while ((int) $skuUsedByProduct->fetchColumn() || (int) $skuUsedByVariant->fetchColumn() || isset($usedVariantSkus[mb_strtolower($variantSku)]));
                } else {
                    $skuUsedByProduct->execute([$variantSku]);
                    $skuUsedByVariant->execute([$variantSku]);
                    if ((int) $skuUsedByProduct->fetchColumn() || (int) $skuUsedByVariant->fetchColumn() || isset($usedVariantSkus[mb_strtolower($variantSku)])) {
                        throw new \RuntimeException('SKU-ul variantei „' . $variantSku . '” este deja folosit.');
                    }
                }
                $usedVariantSkus[mb_strtolower($variantSku)] = true;
                $variantQuantity = ($stock[$index] ?? '') !== '' ? (int) $stock[$index] : null;
                $variantAvailability = $variantQuantity === null ? 'in_stock' : (in_array($stockStatus[$index] ?? '', ['in_stock','out_of_stock','on_backorder'], true) ? $stockStatus[$index] : 'in_stock');
                $insertVariant->execute([$id, $label, $variantSku, ($regular[$index] ?? '') !== '' ? (float) $regular[$index] : null, ($sale[$index] ?? '') !== '' ? (float) $sale[$index] : null, $variantQuantity, $variantAvailability, ($variantStatus[$index] ?? 'active') === 'inactive' ? 'inactive' : 'active']);
            }
            $customization->saveFields($db, $id, [
                'ids' => $request->input('customization_field_id', []),
                'labels' => $request->input('customization_field_label', []),
                'types' => $request->input('customization_field_type', []),
                'placeholders' => $request->input('customization_field_placeholder', []),
                'required' => $request->input('customization_field_required', []),
            ]);
            $addons->save(
                $db,
                $id,
                (bool) $request->input('addons_enabled'),
                $addonProductIds,
                (array) $request->input('addon_prices', [])
            );
        });
        try {
            $this->persistProductMedia($id, $request, trim((string) $request->input('name')) . ' - SmileBaby');
        } catch (\RuntimeException $error) {
            Session::flash('error', $error->getMessage());
            Response::redirect('/admin/produse/' . $id . '/editare?step=3');
        }
        try {
            (new StripeCatalogService())->sync($id);
        } catch (\Throwable $error) {
            Session::flash('error', 'Produsul a fost salvat local, dar sincronizarea Stripe trebuie reîncercată: ' . $error->getMessage());
        }
        Session::flash('product_saved', [
            'mode' => $isCreate ? 'created' : 'updated',
            'id' => $id,
            'name' => trim((string) $request->input('name')),
            'slug' => $slug,
        ]);
        Response::redirect('/admin/produse');
    }

    public function archiveProduct(Request $request): void
    {
        $id = (int) $request->params['id'];
        Database::connection()->prepare('UPDATE products SET status="archived" WHERE id=?')->execute([$id]);
        try { (new StripeCatalogService())->sync($id); }
        catch (\Throwable $error) { Session::flash('error', 'Produsul a fost arhivat local, dar Stripe nu a putut fi actualizat: ' . $error->getMessage()); }
        Session::flash('success', 'Produsul a fost arhivat.');
        Response::redirect('/admin/produse');
    }

    public function syncStripeCatalog(Request $request): void
    {
        try {
            $result = (new StripeCatalogService())->syncAll();
            $message = $result['success'] . ' produse sincronizate cu Stripe.';
            if ($result['failed']) $message .= ' ' . $result['failed'] . ' produse au nevoie de reverificare.';
            Session::flash($result['failed'] ? 'error' : 'success', $message);
        } catch (\Throwable $error) {
            Session::flash('error', 'Catalogul Stripe nu a putut fi sincronizat: ' . $error->getMessage());
        }
        Response::redirect('/admin/produse');
    }
    public function generateMissingProductSkus(Request $request): void
    {
        $db = Database::connection();
        $products = $db->query('SELECT id FROM products WHERE sku IS NULL OR TRIM(sku)="" ORDER BY id')->fetchAll();
        $check = $db->prepare('SELECT COUNT(*) FROM products WHERE sku=? AND id<>?');
        $update = $db->prepare('UPDATE products SET sku=? WHERE id=?');
        $generated = 0;
        Database::transaction(function () use ($products, $check, $update, &$generated) {
            foreach ($products as $product) {
                $id = (int) $product['id'];
                $base = 'SB-P' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                $sku = $base;
                $suffix = 2;
                do {
                    $check->execute([$sku, $id]);
                    if (!(int) $check->fetchColumn()) break;
                    $sku = $base . '-' . $suffix++;
                } while (true);
                $update->execute([$sku, $id]);
                $generated++;
            }
        });
        Session::flash('success', $generated ? "Au fost generate $generated coduri de produs unice." : 'Toate produsele aveau deja cod SKU.');
        Response::redirect('/admin/produse');
    }
    public function deleteProduct(Request $request): void
    {
        $id = (int) $request->params['id'];
        $db = Database::connection();
        $stmt = $db->prepare('SELECT image_path FROM product_images WHERE product_id=?');
        $stmt->execute([$id]);
        $imagePaths = array_column($stmt->fetchAll(), 'image_path');
        $stmt = $db->prepare('SELECT name FROM products WHERE id=?');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        if (!$name) { Session::flash('error', 'Produsul nu a fost găsit.'); Response::redirect('/admin/produse'); }
        try { (new StripeCatalogService())->deactivate($id); }
        catch (\Throwable $error) { Session::flash('error', 'Produsul va fi șters local, dar dezactivarea din Stripe trebuie verificată: ' . $error->getMessage()); }
        Database::transaction(function (PDO $transaction) use ($id) { $transaction->prepare('DELETE FROM products WHERE id=?')->execute([$id]); });
        foreach ($imagePaths as $path) if ($path && str_starts_with($path, 'uploads/products/') && is_file(BASE_PATH . '/' . $path)) @unlink(BASE_PATH . '/' . $path);
        Session::flash('success', 'Produsul „' . $name . '” a fost șters definitiv.');
        Response::redirect('/admin/produse');
    }
    public function deleteImage(Request $request): void { $stmt = Database::connection()->prepare('SELECT image_path FROM product_images WHERE id=?'); $stmt->execute([(int) $request->params['id']]); $path = $stmt->fetchColumn(); Database::connection()->prepare('DELETE FROM product_images WHERE id=?')->execute([(int) $request->params['id']]); if ($path && str_starts_with($path, 'uploads/products/') && is_file(BASE_PATH . '/' . $path)) unlink(BASE_PATH . '/' . $path); Response::redirect($request->server['HTTP_REFERER'] ?? '/admin/produse'); }

    public function orderExperiencePreview(Request $request): void
    {
        $allowed = ['received','paid','pending','failed','cancelled','order_cancelled'];
        $state = in_array((string) ($request->params['state'] ?? ''), $allowed, true) ? (string) $request->params['state'] : 'received';
        [$order, $items] = $this->previewOrder((int) ($request->query['order_id'] ?? 0));
        if ($state === 'received') {
            $order['payment_method'] = 'cash_on_delivery'; $order['payment_method_label'] = 'Plată ramburs'; $order['payment_status'] = 'unpaid';
        } else {
            $order['payment_method'] = 'online_card'; $order['payment_method_label'] = 'Plată online cu cardul';
            $order['payment_status'] = $state === 'paid' ? 'paid' : ($state === 'failed' ? 'failed' : 'pending');
        }
        if ($state === 'order_cancelled') $order['status'] = 'cancelled';
        View::render('storefront/payment-result', ['order' => $order, 'items' => $items, 'displayState' => $state, 'meta' => ['title' => 'Previzualizare experiență comandă', 'robots' => 'noindex,nofollow']], 'layouts/storefront');
    }

    public function orderEmailPreview(Request $request): void
    {
        $type = (string) ($request->params['type'] ?? 'received');
        [$order, $items] = $this->previewOrder((int) ($request->query['order_id'] ?? 0));
        $customer = $order;
        if ($type === 'internal') $template = 'order-internal';
        elseif ($type === 'received') $template = 'order';
        else {
            $statuses = ['confirmed','processing','prepared','shipped','delivered','cancelled','returned'];
            $order['status'] = in_array($type, $statuses, true) ? $type : 'confirmed';
            $template = 'status';
        }
        header('Content-Type: text/html; charset=utf-8');
        require BASE_PATH . '/views/emails/' . $template . '.php';
    }

    private function previewOrder(int $orderId = 0): array
    {
        $db = Database::connection();
        if ($orderId > 0) { $stmt = $db->prepare('SELECT * FROM orders WHERE id=? LIMIT 1'); $stmt->execute([$orderId]); }
        else $stmt = $db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1');
        $order = $stmt->fetch();
        if (!$order) throw new \RuntimeException('Nu există încă o comandă pentru previzualizare.');
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $items->execute([(int) $order['id']]);
        return [$order, $items->fetchAll()];
    }

    public function categories(Request $request): void { $categories = Database::connection()->query('SELECT c.*,(SELECT COUNT(*) FROM product_categories WHERE category_id=c.id) product_count,(SELECT name FROM categories p WHERE p.id=c.parent_id) parent_name FROM categories c ORDER BY c.homepage_order,c.name')->fetchAll(); View::render('admin/categories', compact('categories'), 'layouts/admin'); }
    public function categoryForm(Request $request): void { $id = (int) ($request->params['id'] ?? 0); Response::redirect('/admin/categorii?' . ($id ? 'edit=' . $id : 'create=1')); }
    public function saveCategory(Request $request): void
    {
        $id=(int)($request->params['id']??0); $slug=slugify(trim((string)$request->input('slug'))?:trim((string)$request->input('name'))); $image=null; if (!empty($request->files['image']['name'])) $image=(new ImageService())->store($request->files['image'],'categories');
        $params=[(int)$request->input('parent_id')?:null,trim($request->input('name')),$slug,(string)$request->input('short_description'),(string)$request->input('description'),(string)$request->input('status','active'),(int)(bool)$request->input('show_on_homepage'),(int)$request->input('homepage_order'),trim($request->input('meta_title'))?:null,trim($request->input('meta_description'))?:null,(int)(bool)$request->input('indexable')];
        if($id){$sql='UPDATE categories SET parent_id=?,name=?,slug=?,short_description=?,description=?,status=?,show_on_homepage=?,homepage_order=?,meta_title=?,meta_description=?,indexable=?'.($image?',image_path=?':'').' WHERE id=?'; if($image)$params[]=$image; $params[]=$id; Database::connection()->prepare($sql)->execute($params);} else {$params[]=$image; Database::connection()->prepare('INSERT INTO categories (parent_id,name,slug,short_description,description,status,show_on_homepage,homepage_order,meta_title,meta_description,indexable,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute($params);$id=(int)Database::connection()->lastInsertId();}
        Session::flash('success','Categoria a fost salvată.');Response::redirect('/admin/categorii?edit='.$id);
    }

    public function deleteCategory(Request $request): void
    {
        $id = (int) $request->params['id'];
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM product_categories WHERE category_id=?'); $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) { Session::flash('error', "Categoria nu poate fi ștearsă cât timp are $count produse. Mută produsele într-o altă categorie, apoi reîncearcă."); Response::redirect('/admin/categorii'); }
        Database::connection()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        Session::flash('success', 'Categoria a fost ștearsă.'); Response::redirect('/admin/categorii');
    }
    public function toggleCategoryVisibility(Request $request): void
    {
        $id = (int) $request->params['id'];
        $stmt = Database::connection()->prepare('UPDATE categories SET status=IF(status="active","inactive","active") WHERE id=?');
        $stmt->execute([$id]);
        Session::flash('success', 'Vizibilitatea categoriei a fost actualizată.');
        Response::redirect('/admin/categorii');
    }

    public function orders(Request $request): void
    {
        $perPage = (int) ($request->query['per_page'] ?? 20);
        $page = max(1, (int) ($request->query['page'] ?? 1));
        if (!in_array($perPage, [10, 20, 50, 100], true)) $perPage = 20;
        $total = $this->filteredOrderCount($request);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        View::render('admin/orders', [
            'orders' => $this->filteredOrders($request, $perPage, $offset),
            'filters' => $request->query,
            'perPage' => $perPage,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ], 'layouts/admin');
    }

    public function ordersCsv(Request $request): never
    {
        $statusLabels = ['received'=>'Nouă','confirmed'=>'Confirmată','processing'=>'În pregătire','prepared'=>'Pregătită pentru curier','shipped'=>'Expediată','delivered'=>'Livrată','cancelled'=>'Anulată','returned'=>'Returnată'];
        $paymentLabels = ['unpaid'=>'Neplătită','pending'=>'În așteptare','paid'=>'Plătită','failed'=>'Expirată','refunded'=>'Rambursată'];
        $orders = $this->filteredOrders($request, 5000);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="comenzi-smilebaby-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Comandă','Client','Email','Telefon','Mesaj client','Tip plată','Stare plată','Status','Data','Total (lei)'], ';', '"', '\\');
        foreach ($orders as $order) {
            fputcsv($out, [
                $order['order_number'],
                trim($order['first_name'] . ' ' . $order['last_name']),
                $order['email'],
                $order['phone'],
                trim(str_replace('[COMANDĂ TEST]', '', (string) ($order['notes'] ?? ''))),
                $order['payment_method_label'],
                $paymentLabels[$order['payment_status']] ?? $order['payment_status'],
                $statusLabels[$order['status']] ?? $order['status'],
                date('d.m.Y H:i', strtotime($order['created_at'])),
                number_format((float) $order['total'], 2, ',', ''),
            ], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function ordersPdf(Request $request): never
    {
        $statusLabels = ['received'=>'Noua','confirmed'=>'Confirmata','processing'=>'In pregatire','prepared'=>'Pregatita pentru curier','shipped'=>'Expediata','delivered'=>'Livrata','cancelled'=>'Anulata','returned'=>'Returnata'];
        $paymentLabels = ['unpaid'=>'Neplatita','pending'=>'In asteptare','paid'=>'Platita','failed'=>'Expirata','refunded'=>'Rambursata'];
        $lines = ['COMANDA              CLIENT / EMAIL                         PLATA        STATUS                    DATA             TOTAL'];
        foreach ($this->filteredOrders($request, 5000) as $order) {
            $client = trim($order['first_name'] . ' ' . $order['last_name']);
            $client = mb_substr($client !== '' ? $client : $order['email'], 0, 34);
            $lines[] = sprintf(
                '%-20s %-34s %-12s %-25s %-16s %9s lei',
                mb_substr($order['order_number'], 0, 20),
                $client,
                mb_substr($paymentLabels[$order['payment_status']] ?? $order['payment_status'], 0, 12),
                mb_substr($statusLabels[$order['status']] ?? $order['status'], 0, 25),
                date('d.m.Y H:i', strtotime($order['created_at'])),
                number_format((float) $order['total'], 2, ',', '')
            );
        }
        $pdf = $this->buildOrdersPdf($lines, count($lines) - 1);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="comenzi-smilebaby-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    public function order(Request $request): void
    {
        $id=(int)$request->params['id'];$db=Database::connection();$stmt=$db->prepare('SELECT * FROM orders WHERE id=?');$stmt->execute([$id]);$order=$stmt->fetch();if(!$order){http_response_code(404);View::render('errors/404');return;} $items=$db->prepare('SELECT oi.*,p.slug product_slug,COALESCE(NULLIF(oi.sku,""),p.sku) display_sku FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');$items->execute([$id]);$history=$db->prepare('SELECT h.*,CONCAT(u.first_name," ",u.last_name) changed_by_name FROM order_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE order_id=? ORDER BY h.created_at DESC,h.id DESC');$history->execute([$id]);$payments=$db->prepare('SELECT * FROM payments WHERE order_id=? ORDER BY created_at DESC');$payments->execute([$id]);$notes=$db->prepare('SELECT n.*,CONCAT(u.first_name," ",u.last_name) author_name FROM order_notes n LEFT JOIN users u ON u.id=n.user_id WHERE order_id=? ORDER BY n.created_at DESC,n.id DESC');$notes->execute([$id]);View::render('admin/order',['order'=>$order,'items'=>$items->fetchAll(),'history'=>$history->fetchAll(),'payments'=>$payments->fetchAll(),'notes'=>$notes->fetchAll()],'layouts/admin');
    }
    public function updateOrder(Request $request): void
    {
        $id=(int)$request->params['id'];
        try{
            $db=Database::connection();$stmt=$db->prepare('SELECT courier,awb,tracking_url FROM orders WHERE id=?');$stmt->execute([$id]);$current=$stmt->fetch();if(!$current)throw new \RuntimeException('Comanda nu există.');
            $notify=(bool)$request->input('notify');
            $emailAccepted=(new OrderService())->changeStatus($id,(string)$request->input('status'),$notify,['courier'=>$current['courier']??'','awb'=>$current['awb']??'','tracking_url'=>$current['tracking_url']??'','message'=>trim((string)$request->input('message'))]);
            $internalNote=trim((string)$request->input('internal_note'));
            if($internalNote!=='')$db->prepare('INSERT INTO order_notes (order_id,user_id,note,visible_to_customer) VALUES (?,?,?,0)')->execute([$id,Auth::user()['id']??null,$internalNote]);
            if($notify&&$emailAccepted===false)Session::flash('error','Statusul a fost actualizat, dar notificarea nu a putut fi predată serverului de email. Verifică jurnalul emailurilor.');
            else Session::flash('success',$notify?'Statusul a fost actualizat, iar notificarea a fost predată serverului de email.':'Statusul comenzii a fost actualizat.');
        }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
        Response::redirect('/admin/comenzi/'.$id);
    }

    public function updateOrderPayment(Request $request): void
    {
        $id=(int)$request->params['id'];
        try{
            $db=Database::connection();$stmt=$db->prepare('SELECT payment_method FROM orders WHERE id=?');$stmt->execute([$id]);$order=$stmt->fetch();if(!$order)throw new \RuntimeException('Comanda nu există.');if($order['payment_method']!=='cash_on_delivery')throw new \RuntimeException('Starea poate fi modificată manual doar pentru plata ramburs.');
            $status=$request->input('paid')?'paid':'unpaid';
            Database::transaction(function($db)use($id,$status){$db->prepare('UPDATE orders SET payment_status=? WHERE id=?')->execute([$status,$id]);$paymentStatus=$status==='paid'?'paid':'pending';$db->prepare('UPDATE payments SET status=? WHERE order_id=?')->execute([$paymentStatus,$id]);});
            Session::flash('success',$status==='paid'?'Plata a fost marcată ca încasată.':'Plata a fost marcată ca neîncasată.');
        }catch(\Throwable $e){Session::flash('error',$e->getMessage());}
        Response::redirect('/admin/comenzi/'.$id);
    }

    public function updateOrderCustomer(Request $request): void
    {
        $id = (int) $request->params['id'];
        try {
            $db = Database::connection();
            $exists = $db->prepare('SELECT id FROM orders WHERE id=? LIMIT 1');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) throw new \RuntimeException('Comanda nu există.');

            $firstName = trim((string) $request->input('first_name'));
            $lastName = trim((string) $request->input('last_name'));
            $email = trim((string) $request->input('email'));
            $phone = trim((string) $request->input('phone'));
            $shippingAddress = trim((string) $request->input('shipping_address'));
            $shippingCity = trim((string) $request->input('shipping_city'));
            $shippingCounty = trim((string) $request->input('shipping_county'));
            if ($firstName === '' || $lastName === '' || $phone === '' || $shippingAddress === '' || $shippingCity === '' || $shippingCounty === '') {
                throw new \RuntimeException('Completează numele, telefonul și toate datele obligatorii de livrare.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Adresa de email nu este validă.');

            $customerType = in_array($request->input('customer_type'), ['individual', 'company'], true) ? (string) $request->input('customer_type') : 'individual';
            $paymentMethod = in_array($request->input('payment_method'), ['cash_on_delivery', 'online_card'], true) ? (string) $request->input('payment_method') : 'cash_on_delivery';
            $paymentStatus = in_array($request->input('payment_status'), ['unpaid', 'pending', 'paid', 'failed', 'refunded'], true) ? (string) $request->input('payment_status') : 'unpaid';
            $paymentLabel = $paymentMethod === 'online_card' ? 'Plată online cu cardul' : 'Plată ramburs';

            $values = [
                $firstName, $lastName, $email, $phone, $customerType,
                trim((string) $request->input('company_name')) ?: null,
                trim((string) $request->input('company_vat_id')) ?: null,
                trim((string) $request->input('company_registration_number')) ?: null,
                trim((string) $request->input('company_address')) ?: null,
                $shippingAddress, $shippingCity, $shippingCounty,
                trim((string) $request->input('shipping_postcode')) ?: null,
                $paymentMethod, $paymentLabel, $paymentStatus,
                trim((string) $request->input('courier')) ?: null,
                trim((string) $request->input('awb')) ?: null,
                trim((string) $request->input('tracking_url')) ?: null,
                $id,
            ];

            Database::transaction(function ($db) use ($values, $id, $paymentStatus): void {
                $db->prepare('UPDATE orders SET first_name=?,last_name=?,email=?,phone=?,customer_type=?,company_name=?,company_vat_id=?,company_registration_number=?,company_address=?,shipping_address=?,shipping_city=?,shipping_county=?,shipping_postcode=?,payment_method=?,payment_method_label=?,payment_status=?,courier=?,awb=?,tracking_url=?,updated_at=NOW() WHERE id=?')->execute($values);
                $paymentRowStatus = match ($paymentStatus) {
                    'paid' => 'paid', 'failed' => 'failed', 'refunded' => 'refunded', default => 'pending',
                };
                $db->prepare('UPDATE payments SET status=?,updated_at=NOW() WHERE order_id=?')->execute([$paymentRowStatus, $id]);
            });
            Session::flash('success', 'Datele clientului, livrării și plății au fost actualizate.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/comenzi/' . $id);
    }

    public function reviews(Request $request): void
    {
        $db = Database::connection();
        $this->ensureReviewModerationSchema($db);
        $query = trim((string) ($request->query['q'] ?? ''));
        $status = trim((string) ($request->query['status'] ?? ''));
        $rating = (int) ($request->query['rating'] ?? 0);
        $reply = trim((string) ($request->query['reply'] ?? ''));
        $sort = trim((string) ($request->query['sort'] ?? 'newest'));
        $perPage = (int) ($request->query['per_page'] ?? 20);
        $page = max(1, (int) ($request->query['page'] ?? 1));

        if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) $status = '';
        if ($rating < 1 || $rating > 5) $rating = 0;
        if (!in_array($reply, ['', 'answered', 'unanswered'], true)) $reply = '';
        if (!in_array($sort, ['newest', 'oldest', 'rating_desc', 'rating_asc', 'product'], true)) $sort = 'newest';
        if (!in_array($perPage, [10, 20, 50, 100], true)) $perPage = 20;

        $where = ['1=1'];
        $params = [];
        if ($status !== '') { $where[] = 'r.status=?'; $params[] = $status; }
        if ($rating) { $where[] = 'r.rating=?'; $params[] = $rating; }
        if ($reply === 'answered') $where[] = 'COALESCE(r.admin_reply,"")<>""';
        if ($reply === 'unanswered') $where[] = 'COALESCE(r.admin_reply,"")=""';
        if ($query !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach (array_slice($tokens, 0, 6) as $token) {
                $like = '%' . $token . '%';
                $where[] = '(LOWER(r.author_name) LIKE ? OR LOWER(r.email) LIKE ? OR LOWER(COALESCE(r.title,"")) LIKE ? OR LOWER(r.body) LIKE ? OR LOWER(COALESCE(r.admin_reply,"")) LIKE ? OR LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.sku,"")) LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like, $like, $like);
            }
        }
        $from = ' FROM reviews r JOIN products p ON p.id=r.product_id WHERE ' . implode(' AND ', $where);
        $countStmt = $db->prepare('SELECT COUNT(*)' . $from);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $orderBy = match ($sort) {
            'oldest' => 'r.created_at ASC, r.id ASC',
            'rating_desc' => 'r.rating DESC, r.created_at DESC',
            'rating_asc' => 'r.rating ASC, r.created_at DESC',
            'product' => 'p.name ASC, r.created_at DESC',
            default => 'r.created_at DESC, r.id DESC',
        };
        $reviewsStmt = $db->prepare('SELECT r.*,p.name product_name,p.slug product_slug,p.sku product_sku,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) product_image' . $from . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        $reviewsStmt->execute($params);
        $reviews = $reviewsStmt->fetchAll();
        $stats = $db->query('SELECT COUNT(*) total,COALESCE(SUM(status="pending"),0) pending,COALESCE(SUM(status="approved"),0) approved,COALESCE(SUM(COALESCE(admin_reply,"")<>""),0) answered FROM reviews')->fetch();
        View::render('admin/reviews', compact('reviews', 'query', 'status', 'rating', 'reply', 'sort', 'perPage', 'page', 'pages', 'total', 'stats'), 'layouts/admin');
    }

    public function updateReview(Request $request): void
    {
        $db = Database::connection();
        $this->ensureReviewModerationSchema($db);
        $id = (int) ($request->params['id'] ?? 0);
        $stmt = $db->prepare('SELECT id,status,admin_reply FROM reviews WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $review = $stmt->fetch();
        if (!$review) { Session::flash('error', 'Recenzia nu mai există.'); Response::redirect($this->reviewReturnTo($request)); }

        if ((string) $request->input('action') === 'reply') {
            $reply = trim((string) $request->input('admin_reply'));
            if (mb_strlen($reply) > 3000) { Session::flash('error', 'Răspunsul poate avea maximum 3.000 de caractere.'); Response::redirect($this->reviewReturnTo($request)); }
            if ($reply === '') {
                $db->prepare('UPDATE reviews SET admin_reply=NULL,replied_at=NULL WHERE id=?')->execute([$id]);
                Session::flash('success', 'Răspunsul magazinului a fost eliminat.');
            } else {
                $db->prepare('UPDATE reviews SET admin_reply=?,replied_at=NOW(),status="approved" WHERE id=?')->execute([$reply, $id]);
                Session::flash('success', 'Răspunsul a fost publicat, iar recenzia este acum vizibilă pe site.');
            }
            Response::redirect($this->reviewReturnTo($request));
        }

        $status = (string) $request->input('status');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) { Session::flash('error', 'Statusul ales nu este valid.'); Response::redirect($this->reviewReturnTo($request)); }
        $db->prepare('UPDATE reviews SET status=? WHERE id=?')->execute([$status, $id]);
        $labels = ['pending'=>'în așteptare', 'approved'=>'publicată', 'rejected'=>'ascunsă'];
        Session::flash('success', 'Recenzia a fost marcată ca ' . $labels[$status] . '.');
        Response::redirect($this->reviewReturnTo($request));
    }

    public function deleteReview(Request $request): void
    {
        $id = (int) ($request->params['id'] ?? 0);
        $stmt = Database::connection()->prepare('DELETE FROM reviews WHERE id=?');
        $stmt->execute([$id]);
        Session::flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Recenzia a fost ștearsă definitiv.' : 'Recenzia nu mai există.');
        Response::redirect('/admin/recenzii');
    }
    public function customers(Request $request): void
    {
        $query = trim((string) ($request->query['q'] ?? ''));
        $status = trim((string) ($request->query['status'] ?? ''));
        $activity = trim((string) ($request->query['activity'] ?? ''));
        $sort = trim((string) ($request->query['sort'] ?? 'newest'));
        $perPage = (int) ($request->query['per_page'] ?? 20);
        $page = max(1, (int) ($request->query['page'] ?? 1));

        if (!in_array($status, ['', 'active', 'disabled'], true)) $status = '';
        if (!in_array($activity, ['', 'with_orders', 'repeat', 'no_orders'], true)) $activity = '';
        if (!in_array($sort, ['newest', 'oldest', 'name', 'orders_desc', 'spent_desc'], true)) $sort = 'newest';
        if (!in_array($perPage, [10, 20, 50, 100], true)) $perPage = 20;

        $where = ['u.role="customer"'];
        $params = [];
        if ($status !== '') { $where[] = 'u.status=?'; $params[] = $status; }
        if ($query !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach (array_slice($tokens, 0, 6) as $token) {
                $like = '%' . $token . '%';
                $where[] = '(LOWER(CONCAT_WS(" ",u.first_name,u.last_name)) LIKE ? OR LOWER(u.email) LIKE ? OR LOWER(COALESCE(u.phone,"")) LIKE ? OR LOWER(COALESCE(u.company_name,"")) LIKE ?)';
                array_push($params, $like, $like, $like, $like);
            }
        }

        $baseSql = 'SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) orders_count,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id=u.id AND o.status NOT IN ("cancelled","returned")) spent,
            (SELECT MAX(o.created_at) FROM orders o WHERE o.user_id=u.id) last_order_at
            FROM users u WHERE ' . implode(' AND ', $where);
        $activityWhere = match ($activity) {
            'with_orders' => ' WHERE customer_directory.orders_count > 0',
            'repeat' => ' WHERE customer_directory.orders_count >= 2',
            'no_orders' => ' WHERE customer_directory.orders_count = 0',
            default => '',
        };
        $orderBy = match ($sort) {
            'oldest' => 'customer_directory.created_at ASC, customer_directory.id ASC',
            'name' => 'customer_directory.last_name ASC, customer_directory.first_name ASC, customer_directory.id ASC',
            'orders_desc' => 'customer_directory.orders_count DESC, customer_directory.created_at DESC',
            'spent_desc' => 'customer_directory.spent DESC, customer_directory.created_at DESC',
            default => 'customer_directory.created_at DESC, customer_directory.id DESC',
        };

        $db = Database::connection();
        $countStmt = $db->prepare('SELECT COUNT(*) FROM (' . $baseSql . ') customer_directory' . $activityWhere);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $customersStmt = $db->prepare('SELECT * FROM (' . $baseSql . ') customer_directory' . $activityWhere . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        $customersStmt->execute($params);
        $customers = $customersStmt->fetchAll();

        $stats = $db->query('SELECT COUNT(*) total,
            COALESCE(SUM(u.status="active"),0) active,
            COALESCE(SUM(EXISTS(SELECT 1 FROM orders o WHERE o.user_id=u.id)),0) buyers
            FROM users u WHERE u.role="customer"')->fetch();

        View::render('admin/customers', compact('customers', 'query', 'status', 'activity', 'sort', 'perPage', 'page', 'pages', 'total', 'stats'), 'layouts/admin');
    }

    public function customer(Request $request): void
    {
        $id = (int) ($request->params['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM users WHERE id=? AND role="customer" LIMIT 1');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer) { http_response_code(404); View::render('errors/404'); return; }
        $ordersStmt = $db->prepare('SELECT o.*,(SELECT GROUP_CONCAT(CONCAT(oi.product_name," × ",oi.quantity) ORDER BY oi.id SEPARATOR " | ") FROM order_items oi WHERE oi.order_id=o.id) products_summary FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC');
        $ordersStmt->execute([$id]);
        $orders = $ordersStmt->fetchAll();
        $spent = array_sum(array_map(static fn(array $order): float => in_array($order['status'], ['cancelled','returned'], true) ? 0.0 : (float) $order['total'], $orders));
        View::render('admin/customer', compact('customer','orders','spent'), 'layouts/admin');
    }
    public function posts(Request $request): void { $posts=Database::connection()->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll();View::render('admin/posts',compact('posts'),'layouts/admin'); }
    public function postForm(Request $request): void { $id=(int)($request->params['id']??0);$post=null;if($id){$stmt=Database::connection()->prepare('SELECT * FROM posts WHERE id=?');$stmt->execute([$id]);$post=$stmt->fetch();}View::render('admin/post-form',compact('post'),'layouts/admin'); }
    public function savePost(Request $request): void { $id=(int)($request->params['id']??0);$image=null;if(!empty($request->files['featured_image']['name']))$image=(new ImageService())->store($request->files['featured_image'],'blog');$data=[Auth::user()['id'],trim($request->input('title')),slugify(trim($request->input('slug'))?:trim($request->input('title'))),(string)$request->input('excerpt'),(string)$request->input('content'),(string)$request->input('status','draft'),trim($request->input('meta_title'))?:null,trim($request->input('meta_description'))?:null,(int)(bool)$request->input('indexable'),$request->input('status')==='published'?date('Y-m-d H:i:s'):null];if($id){$sql='UPDATE posts SET author_id=?,title=?,slug=?,excerpt=?,content=?,status=?,meta_title=?,meta_description=?,indexable=?,published_at=?'.($image?',featured_image=?':'').' WHERE id=?';if($image)$data[]=$image;$data[]=$id;Database::connection()->prepare($sql)->execute($data);}else{$data[]=$image;Database::connection()->prepare('INSERT INTO posts (author_id,title,slug,excerpt,content,status,meta_title,meta_description,indexable,published_at,featured_image) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($data);$id=(int)Database::connection()->lastInsertId();}Session::flash('success','Articolul a fost salvat.');Response::redirect('/admin/articole/'.$id.'/editare'); }
    public function newsletter(Request $request): void { $subscribers=Database::connection()->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC')->fetchAll();View::render('admin/newsletter',compact('subscribers'),'layouts/admin'); }
    public function newsletterCsv(Request $request): never { header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="newsletter-smilebaby.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Email','Status','Data'],',');foreach(Database::connection()->query('SELECT email,status,subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC') as $row)fputcsv($out,$row,',');fclose($out);exit; }
    public function media(Request $request): void { $media=Database::connection()->query('SELECT * FROM media ORDER BY created_at DESC LIMIT 200')->fetchAll();View::render('admin/media',compact('media'),'layouts/admin'); }
    public function uploadMedia(Request $request): void { $path=(new ImageService())->store($request->files['image'],'media');$info=getimagesize(BASE_PATH.'/'.$path);Database::connection()->prepare('INSERT INTO media (user_id,path,filename,mime_type,size_bytes,width,height,alt_text,title) VALUES (?,?,?,?,?,?,?,?,?)')->execute([Auth::user()['id'],$path,basename($path),$info['mime'],filesize(BASE_PATH.'/'.$path),$info[0],$info[1],trim($request->input('alt_text')),trim($request->input('title'))]);Session::flash('success','Imaginea a fost încărcată.');Response::redirect('/admin/media'); }

    public function settings(Request $request): void { $rows=Database::connection()->query('SELECT * FROM settings ORDER BY group_name,`key`')->fetchAll();$settings=[];foreach($rows as $r)$settings[$r['key']]=$r['value'];$methods=Database::connection()->query('SELECT * FROM payment_methods WHERE `key` IN ("cash_on_delivery","online_card") ORDER BY sort_order')->fetchAll();foreach($methods as &$m){$s=json_decode($m['settings_json']?:'{}',true)?:[];$s['secret_masked']=Crypto::mask(Crypto::decrypt($s['webhook_secret_encrypted']??''));$m['settings']=$s;}$smtp=['host'=>$settings['smtp_host']??config('mail.host'),'port'=>$settings['smtp_port']??config('mail.port'),'username'=>$settings['smtp_username']??config('mail.username'),'password_masked'=>Crypto::mask(Crypto::decrypt($settings['smtp_password_encrypted']??'')),'encryption'=>$settings['smtp_encryption']??config('mail.encryption'),'from_email'=>$settings['smtp_from_email']??config('mail.from_email'),'from_name'=>$settings['smtp_from_name']??config('mail.from_name')];View::render('admin/settings',compact('settings','methods','smtp'),'layouts/admin'); }
    public function emailSettings(Request $request): void
    {
        $rows=Database::connection()->query('SELECT `key`,value FROM settings WHERE group_name="email" OR `key`="site_email"')->fetchAll();$settings=[];foreach($rows as $row)$settings[$row['key']]=$row['value'];
        $smtp=['host'=>$settings['smtp_host']??config('mail.host'),'port'=>$settings['smtp_port']??config('mail.port'),'username'=>$settings['smtp_username']??config('mail.username'),'password_masked'=>Crypto::mask(Crypto::decrypt($settings['smtp_password_encrypted']??'')),'encryption'=>$settings['smtp_encryption']??config('mail.encryption'),'from_email'=>$settings['smtp_from_email']??config('mail.from_email'),'from_name'=>$settings['smtp_from_name']??config('mail.from_name')];
        View::render('admin/email-settings',compact('settings','smtp'),'layouts/admin');
    }
    public function saveSettings(Request $request): void { $allowed=['site_name','site_email','site_phone','site_address','announcement_enabled','shipping_enabled','standard_shipping_cost','return_shipping_cost','free_shipping_threshold','estimated_delivery_text','default_payment_method','seo_title','seo_description','google_site_verification','facebook_url','instagram_url','tiktok_url','pinterest_url','youtube_url'];foreach($allowed as $key)if(array_key_exists($key,$request->body))Database::connection()->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,? ,"string","general") ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute([$key,is_array($request->body[$key])?json_encode($request->body[$key]):$request->body[$key]]);Session::flash('success','Setările au fost salvate.');Response::redirect('/admin/setari#livrare'); }
    public function saveEmailSettings(Request $request): void
    {
        $email = trim((string) $request->input('smtp_from_email'));
        $port = (int) $request->input('smtp_port', 587);
        $encryption = (string) $request->input('smtp_encryption', 'tls');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $port < 1 || $port > 65535 || !in_array($encryption, ['tls','ssl','none'], true)) { Session::flash('error', 'Verifică adresa expeditorului, portul și criptarea SMTP.'); Response::redirect('/admin/setari#email'); }
        $values = ['smtp_host'=>trim((string)$request->input('smtp_host')),'smtp_port'=>(string)$port,'smtp_username'=>trim((string)$request->input('smtp_username')),'smtp_encryption'=>$encryption,'smtp_from_email'=>$email,'smtp_from_name'=>trim((string)$request->input('smtp_from_name'))?:'SmileBaby'];
        $stmt = Database::connection()->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,?,"string","email") ON DUPLICATE KEY UPDATE value=VALUES(value),type=VALUES(type),group_name=VALUES(group_name)');
        foreach ($values as $key=>$value) $stmt->execute([$key,$value]);
        $password = (string) $request->input('smtp_password');
        if ($password !== '') Database::connection()->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,?,"secret","email") ON DUPLICATE KEY UPDATE value=VALUES(value),type="secret",group_name="email"')->execute(['smtp_password_encrypted',Crypto::encrypt($password)]);
        Session::flash('success', 'Setările de email au fost salvate în siguranță.'); Response::redirect('/admin/setari#email');
    }

    public function testEmail(Request $request): void
    {
        $recipient = trim((string) $request->input('recipient'));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { Session::flash('error', 'Introdu o adresă validă pentru test.'); Response::redirect('/admin/setari#email'); }
        try { (new SmtpClient())->send($recipient, 'Test email SmileBaby', '<div style="font-family:Arial,sans-serif;padding:32px"><h1>Configurarea funcționează</h1><p>Acesta este un mesaj de test trimis din panoul SmileBaby.</p></div>'); Session::flash('success', 'Emailul de test a fost trimis către '.$recipient.'.'); }
        catch (\Throwable $error) { Session::flash('error', 'Testul SMTP a eșuat: '.mb_substr($error->getMessage(),0,220)); }
        Response::redirect('/admin/setari#email');
    }
    public function savePayment(Request $request): void
    {
        $key=$request->params['key'];$stmt=Database::connection()->prepare('SELECT * FROM payment_methods WHERE `key`=?');$stmt->execute([$key]);$method=$stmt->fetch();if(!$method){http_response_code(404);return;}$settings=json_decode($method['settings_json']?:'{}',true)?:[];
        if($key==='online_card'){$settings['provider']='stripe';$settings['test_mode']=str_starts_with((string)config('payments.stripe.secret_key',''),'sk_test_');}
        $enabled=(int)(bool)$request->input('enabled');if($key==='online_card'&&$enabled&&!str_starts_with((string)config('payments.stripe.secret_key',''),'sk_')){Session::flash('error','Cheia secretă Stripe nu este configurată pe server.');Response::redirect('/admin/setari#plati');}
        Database::connection()->prepare('UPDATE payment_methods SET name=?,description=?,enabled=?,sort_order=?,fee_type=?,fee_value=?,minimum_order=?,maximum_order=?,instructions=?,settings_json=? WHERE `key`=?')->execute([trim($request->input('name')),$request->input('description'),$enabled,(int)$request->input('sort_order'),$request->input('fee_type','none'),(float)$request->input('fee_value'),$request->input('minimum_order')!==''?$request->input('minimum_order'):null,$request->input('maximum_order')!==''?$request->input('maximum_order'):null,$method['instructions'],json_encode($settings,JSON_UNESCAPED_UNICODE),$key]);Session::flash('success','Metoda de plată a fost '.($enabled?'activată și salvată.':'dezactivată și salvată.'));Response::redirect('/admin/setari#plati');
    }

    public function configureStripeWebhook(Request $request): void
    {
        try {
            $endpoint = (new StripeClient())->post('/v1/webhook_endpoints', [
                'url' => (string) config('payments.stripe.webhook_url'),
                'description' => 'SmileBaby — confirmare automată plăți și comenzi',
                'enabled_events' => ['checkout.session.completed','checkout.session.async_payment_succeeded','checkout.session.async_payment_failed','checkout.session.expired'],
                'metadata' => ['integration' => 'smilebaby'],
            ], 'smilebaby-webhook-' . bin2hex(random_bytes(8)));
            $secret = (string) ($endpoint['secret'] ?? '');
            if (!str_starts_with($secret, 'whsec_')) throw new \RuntimeException('Stripe nu a returnat secretul endpointului.');
            $stmt = Database::connection()->prepare('SELECT settings_json FROM payment_methods WHERE `key`="online_card" LIMIT 1');
            $stmt->execute();
            $settings = json_decode((string) $stmt->fetchColumn(), true) ?: [];
            $settings['provider'] = 'stripe';
            $settings['test_mode'] = str_starts_with((string) config('payments.stripe.secret_key'), 'sk_test_');
            $settings['stripe_webhook_endpoint_id'] = (string) ($endpoint['id'] ?? '');
            $settings['stripe_webhook_secret_encrypted'] = Crypto::encrypt($secret);
            Database::connection()->prepare('UPDATE payment_methods SET enabled=1,settings_json=? WHERE `key`="online_card"')->execute([json_encode($settings, JSON_UNESCAPED_SLASHES)]);
            Session::flash('success', 'Webhookul Stripe a fost creat și secretul a fost salvat criptat.');
        } catch (\Throwable $error) {
            Session::flash('error', 'Webhookul Stripe nu a putut fi configurat: ' . mb_substr($error->getMessage(), 0, 260));
        }
        Response::redirect('/admin/setari#plati');
    }

    private function orderFilterParts(Request $request): array
    {
        $where = ['1=1'];
        $params = [];
        $allowed = [
            'status' => ['received','confirmed','processing','prepared','shipped','delivered','cancelled','returned'],
            'payment_status' => ['unpaid','pending','paid','failed','refunded'],
            'payment_method' => ['cash_on_delivery','online_card'],
        ];
        foreach ($allowed as $field => $values) {
            $value = trim((string) ($request->query[$field] ?? ''));
            if (in_array($value, $values, true)) {
                $where[] = "$field=?";
                $params[] = $value;
            }
        }
        $search = trim((string) ($request->query['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(order_number LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name," ",last_name) LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    private function filteredOrderCount(Request $request): int
    {
        [$where, $params] = $this->orderFilterParts($request);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM orders WHERE ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function filteredOrders(Request $request, int $limit, int $offset = 0): array
    {
        [$where, $params] = $this->orderFilterParts($request);
        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE ' . $where . ' ORDER BY created_at DESC,id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function buildOrdersPdf(array $lines, int $orderCount): string
    {
        $toAscii = static function (string $value): string {
            $value = strtr($value, ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t','Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T','—'=>'-','–'=>'-','„'=>'"','”'=>'"','’'=>"'"]);
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            return $converted === false ? $value : $converted;
        };
        $escape = static function (string $value) use ($toAscii): string {
            return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)','',' '], $toAscii($value));
        };

        $chunks = array_chunk($lines, 27);
        if (!$chunks) $chunks = [[]];
        $pageCount = count($chunks);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $kids = [];
        foreach ($chunks as $pageIndex => $chunk) {
            $pageId = 4 + ($pageIndex * 2);
            $contentId = $pageId + 1;
            $kids[] = $pageId . ' 0 R';
            $commands = [
                'BT',
                '/F1 17 Tf',
                '1 0 0 1 40 555 Tm (SmileBaby - Raport comenzi) Tj',
                '/F1 9 Tf',
                '1 0 0 1 40 535 Tm (' . $escape($orderCount . ' comenzi  |  Generat la ' . date('d.m.Y H:i')) . ') Tj',
                '/F1 7.2 Tf',
            ];
            $y = 505;
            foreach ($chunk as $line) {
                $commands[] = '1 0 0 1 28 ' . $y . ' Tm (' . $escape((string) $line) . ') Tj';
                $y -= 16;
            }
            $commands[] = '/F1 8 Tf';
            $commands[] = '1 0 0 1 748 22 Tm (' . $escape('Pagina ' . ($pageIndex + 1) . ' / ' . $pageCount) . ') Tj';
            $commands[] = 'ET';
            $stream = implode("\n", $commands);
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count ' . $pageCount . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        $pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }

    private function persistProductMedia(int $productId, Request $request, string $altText): void
    {
        $db = Database::connection();
        $removedIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('removed_images', [])), fn (int $id) => $id > 0)));
        $removedNewIndexes = array_values(array_unique(array_map('intval', (array) $request->input('removed_new_images', []))));
        $newFiles = isset($request->files['images']) ? array_filter($this->normalizeFiles($request->files['images']), fn (array $file, int $index) => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && !in_array($index, $removedNewIndexes, true), ARRAY_FILTER_USE_BOTH) : [];

        $countStmt = $db->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=?' . ($removedIds ? ' AND id NOT IN (' . implode(',', array_fill(0, count($removedIds), '?')) . ')' : ''));
        $countStmt->execute([$productId, ...$removedIds]);
        if ((int) $countStmt->fetchColumn() + count($newFiles) > 12) throw new \RuntimeException('Poți păstra cel mult 12 fotografii pentru un produs.');

        $stored = [];
        try {
            foreach ($newFiles as $index => $file) $stored[$index] = (new ImageService())->store($file, 'products');
        } catch (\Throwable $error) {
            foreach ($stored as $path) if (is_file(BASE_PATH . '/' . $path)) @unlink(BASE_PATH . '/' . $path);
            throw $error;
        }

        $pathsToDelete = [];
        try {
            Database::transaction(function (PDO $transaction) use ($productId, $removedIds, $stored, $request, $altText, &$pathsToDelete) {
                if ($removedIds) {
                    $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
                    $select = $transaction->prepare('SELECT id,image_path FROM product_images WHERE product_id=? AND id IN (' . $placeholders . ')');
                    $select->execute([$productId, ...$removedIds]);
                    $removedRows = $select->fetchAll();
                    $validRemovedIds = array_map('intval', array_column($removedRows, 'id'));
                    $pathsToDelete = array_column($removedRows, 'image_path');
                    if ($validRemovedIds) {
                        $delete = $transaction->prepare('DELETE FROM product_images WHERE product_id=? AND id IN (' . implode(',', array_fill(0, count($validRemovedIds), '?')) . ')');
                        $delete->execute([$productId, ...$validRemovedIds]);
                    }
                }

                $tokenToId = [];
                $existing = $transaction->prepare('SELECT id FROM product_images WHERE product_id=? ORDER BY is_featured DESC,sort_order,id');
                $existing->execute([$productId]);
                foreach ($existing->fetchAll() as $row) $tokenToId['existing:' . (int) $row['id']] = (int) $row['id'];
                $insert = $transaction->prepare('INSERT INTO product_images (product_id,image_path,alt_text,sort_order,is_featured) VALUES (?,?,?,?,0)');
                foreach ($stored as $index => $path) {
                    $insert->execute([$productId, $path, $altText, 100 + $index]);
                    $tokenToId['new:' . $index] = (int) $transaction->lastInsertId();
                }

                $requestedOrder = json_decode((string) $request->input('image_order', '[]'), true);
                if (!is_array($requestedOrder)) $requestedOrder = [];
                $orderedTokens = [];
                foreach ($requestedOrder as $token) {
                    $token = (string) $token;
                    if (isset($tokenToId[$token]) && !in_array($token, $orderedTokens, true)) $orderedTokens[] = $token;
                }
                foreach (array_keys($tokenToId) as $token) if (!in_array($token, $orderedTokens, true)) $orderedTokens[] = $token;

                $coverToken = (string) $request->input('cover_image', '');
                if (!isset($tokenToId[$coverToken])) $coverToken = $orderedTokens[0] ?? '';
                $transaction->prepare('UPDATE product_images SET is_featured=0 WHERE product_id=?')->execute([$productId]);
                $update = $transaction->prepare('UPDATE product_images SET sort_order=?,is_featured=? WHERE id=? AND product_id=?');
                foreach ($orderedTokens as $position => $token) $update->execute([$position, $token === $coverToken ? 1 : 0, $tokenToId[$token], $productId]);
            });
        } catch (\Throwable $error) {
            foreach ($stored as $path) if (is_file(BASE_PATH . '/' . $path)) @unlink(BASE_PATH . '/' . $path);
            throw $error;
        }
        foreach ($pathsToDelete as $path) if ($path && str_starts_with($path, 'uploads/products/') && is_file(BASE_PATH . '/' . $path)) @unlink(BASE_PATH . '/' . $path);
    }

    private function ensureReviewModerationSchema(PDO $db): void
    {
        static $ready = false;
        if ($ready) return;
        $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="reviews" AND COLUMN_NAME=?');
        foreach ([
            'admin_reply' => 'ALTER TABLE reviews ADD COLUMN admin_reply TEXT NULL AFTER status',
            'replied_at' => 'ALTER TABLE reviews ADD COLUMN replied_at DATETIME NULL AFTER admin_reply',
        ] as $column => $sql) {
            $exists->execute([$column]);
            if (!(int) $exists->fetchColumn()) $db->exec($sql);
        }
        $ready = true;
    }

    private function reviewReturnTo(Request $request): string
    {
        $returnTo = (string) $request->input('return_to', '/admin/recenzii');
        return str_starts_with($returnTo, '/admin/recenzii') && !str_contains($returnTo, "\n") && !str_contains($returnTo, "\r") ? $returnTo : '/admin/recenzii';
    }

    private function normalizeFiles(array $files): array { $result=[];foreach($files['name'] as $i=>$name)$result[]=['name'=>$name,'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];return $result; }
}
