<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\CategoryRepository;
use App\Models\ProductRepository;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\OrderTrackingService;
use App\Services\PaymentService;
use App\Services\SearchService;
use App\Services\SeoService;
use App\Services\StripeCheckoutService;

final class StorefrontController
{
    public function home(Request $request): void
    {
        $products = (new ProductRepository())->featured(16);
        $posts = [];
        if (Database::available()) $posts = Database::connection()->query('SELECT * FROM posts WHERE status="published" ORDER BY published_at DESC LIMIT 3')->fetchAll();
        $seo = new SeoService();
        $appUrl = rtrim((string) config('app.url'), '/');
        View::render('storefront/home', ['categories' => (new CategoryRepository())->homepage(), 'products' => $products, 'posts' => $posts, 'meta' => [
            'title' => trim((string) setting('seo_title', '')) ?: 'Trusouri și lumânări de botez personalizate | SmileBaby',
            'description' => trim((string) setting('seo_description', '')) ?: 'Descoperă trusouri, lumânări, mărturii și cadouri personalizate pentru botez, pregătite cu grijă de atelierul SmileBaby din România.',
            'canonical' => $appUrl . '/',
            'schemas' => [$seo->itemListSchema($products, 'Produse recomandate SmileBaby', $appUrl . '/')],
        ]]);
    }

    public function shop(Request $request): void
    {
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $filters = ['q' => trim((string) ($request->query['q'] ?? '')), 'category' => trim((string) ($request->query['category'] ?? '')), 'min' => $request->query['min'] ?? '', 'max' => $request->query['max'] ?? '', 'stock' => $request->query['stock'] ?? '', 'sort' => $request->query['sort'] ?? ''];
        $result = (new ProductRepository())->list($filters, $page, 16);
        $shopUrl = rtrim((string) config('app.url'), '/') . '/magazin';
        $hasFilters = $page > 1 || (bool) array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== '0');
        $seo = new SeoService();
        View::render('storefront/shop', ['products' => $result['items'], 'total' => $result['total'], 'page' => $page, 'pages' => max(1, (int) ceil($result['total'] / 16)), 'filters' => $filters, 'categories' => (new CategoryRepository())->all(), 'meta' => [
            'title' => 'Magazin produse pentru botez | SmileBaby',
            'description' => 'Descoperă trusouri, lumânări, mărturii și cadouri personalizate pentru botez, realizate cu grijă în România de atelierul SmileBaby.',
            'canonical' => $shopUrl,
            'robots' => $hasFilters ? 'noindex,follow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
            'schemas' => [
                $seo->itemListSchema($result['items'], 'Magazin SmileBaby', $shopUrl),
                $seo->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Magazin', 'url' => '/magazin']]),
            ],
        ]]);
    }

    public function category(Request $request): void
    {
        $slug = $request->params['slug'];
        $category = (new CategoryRepository())->findBySlug($slug);
        if (!$category) { http_response_code(404); View::render('errors/404'); return; }
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $filters = ['q' => trim((string) ($request->query['q'] ?? '')), 'category' => $slug, 'min' => $request->query['min'] ?? '', 'max' => $request->query['max'] ?? '', 'stock' => $request->query['stock'] ?? '', 'sort' => $request->query['sort'] ?? ''];
        $result = (new ProductRepository())->list($filters, $page, 16);
        $categoryUrl = rtrim((string) config('app.url'), '/') . '/categorie/' . rawurlencode((string) $category['slug']);
        $hasFilters = $page > 1 || (bool) array_filter(array_diff_key($filters, ['category' => true]), static fn (mixed $value): bool => $value !== '' && $value !== '0');
        $seo = new SeoService();
        View::render('storefront/category', ['category' => $category, 'products' => $result['items'], 'total' => $result['total'], 'page' => $page, 'pages' => max(1, (int) ceil($result['total'] / 16)), 'filters' => $filters, 'meta' => [
            'title' => ($category['meta_title'] ?? null) ?: $category['name'] . ' | SmileBaby',
            'description' => ($category['meta_description'] ?? null) ?: strip_tags((string) ($category['short_description'] ?? '')),
            'canonical' => $categoryUrl,
            'robots' => !empty($category['indexable']) && !$hasFilters ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,follow',
            'image' => optimized_image_url($category['image_path'] ?? null, 'display'),
            'schemas' => [
                $seo->itemListSchema($result['items'], (string) $category['name'], $categoryUrl),
                $seo->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Magazin', 'url' => '/magazin'], ['name' => (string) $category['name'], 'url' => '/categorie/' . $category['slug']]]),
            ],
        ]]);
    }

    public function product(Request $request): void
    {
        $product = (new ProductRepository())->findBySlug($request->params['slug']);
        if (!$product) {
            if (Database::available()) {
                $path = '/produs/' . $request->params['slug'];
                $stmt = Database::connection()->prepare('SELECT new_path,status_code FROM redirects WHERE old_path=? LIMIT 1'); $stmt->execute([$path]); $redirect = $stmt->fetch();
                if ($redirect) Response::redirect($redirect['new_path'], (int) $redirect['status_code']);
            }
            http_response_code(404); View::render('errors/404'); return;
        }
        $related = (new ProductRepository())->list(['category' => $product['categories'][0]['slug'] ?? ''], 1, 13)['items'];
        $related = array_slice(array_values(array_filter($related, fn ($p) => $p['id'] !== $product['id'])), 0, 12);
        $seo = new SeoService();
        $category = $product['categories'][0] ?? null;
        $productUrl = rtrim((string) config('app.url'), '/') . '/produs/' . rawurlencode((string) $product['slug']);
        $generatedMeta = $seo->generateProductMetadata($product);
        $schemaImages = $product['images'] ?: [['image_path' => $product['image_path'] ?? null]];
        View::render('storefront/product', ['product' => $product, 'related' => $related, 'meta' => [
            'title' => ($product['meta_title'] ?? null) ?: $generatedMeta['title'],
            'description' => ($product['meta_description'] ?? null) ?: $generatedMeta['description'],
            'canonical' => ($product['canonical_url'] ?? null) ?: $productUrl,
            'robots' => !empty($product['indexable']) ? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1' : 'noindex,follow',
            'image' => optimized_image_url($product['image_path'] ?? null, 'display'),
            'type' => 'product',
            'price' => $product['price'],
            'schemas' => [
                $seo->productSchema($product, $schemaImages),
                $seo->breadcrumbSchema(array_values(array_filter([
                    ['name' => 'Acasă', 'url' => '/'],
                    ['name' => 'Magazin', 'url' => '/magazin'],
                    $category ? ['name' => (string) $category['name'], 'url' => '/categorie/' . $category['slug']] : null,
                    ['name' => (string) $product['name'], 'url' => '/produs/' . $product['slug']],
                ]))),
            ],
        ]]);
    }

    public function cart(Request $request): void { View::render('storefront/cart', ['items' => (new CartService())->items(), 'meta' => ['title' => 'Coșul tău — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function cartDrawer(Request $request): void { $items=(new CartService())->items(); require BASE_PATH.'/views/components/cart-drawer.php'; }
    public function addCart(Request $request): void
    {
        try {
            (new CartService())->add(
                (int) $request->input('product_id'),
                max(1, (int) $request->input('quantity', 1)),
                $request->input('variant_id') ? (int) $request->input('variant_id') : null,
                (bool) $request->input('personalization_enabled'),
                is_array($request->input('customization')) ? $request->input('customization') : [],
                is_array($request->input('addons')) ? $request->input('addons') : [],
                is_array($request->input('customization_options')) ? $request->input('customization_options') : []
            );
        } catch (\RuntimeException $error) {
            if ($request->wantsJson()) { Response::json(['ok' => false, 'message' => $error->getMessage()], 422); return; }
            Session::flash('error', $error->getMessage());
            Response::redirect($request->server['HTTP_REFERER'] ?? '/cos');
        }
        Session::flash('success', 'Produsul a fost adăugat în coș.');
        if ($request->wantsJson()) Response::json(['ok' => true, 'count' => (new CartService())->count()]);
        Response::redirect('/cos');
    }
    public function updateCart(Request $request): void
    {
        try {
            (new CartService())->update((string) $request->input('key'), (int) $request->input('quantity'));
        } catch (\RuntimeException $error) {
            Session::flash('error', $error->getMessage());
        }
        Response::redirect('/cos');
    }
    public function updateCartCustomization(Request $request): void
    {
        try {
            (new CartService())->updateCustomization(
                (string) $request->input('key'),
                (bool) $request->input('personalization_enabled'),
                is_array($request->input('customization')) ? $request->input('customization') : [],
                is_array($request->input('customization_options')) ? $request->input('customization_options') : []
            );
            Session::flash('success', $request->input('personalization_enabled') ? 'Personalizarea a fost salvată.' : 'Personalizarea a fost eliminată.');
        } catch (\RuntimeException $error) {
            Session::flash('error', $error->getMessage());
        }
        Response::redirect('/cos');
    }
    public function removeCart(Request $request): void { (new CartService())->remove((string) $request->input('key')); Response::redirect('/cos'); }
    public function syncCart(Request $request): void
    {
        $cart = new CartService();
        try {
            $cart->replace(is_array($request->input('items')) ? $request->input('items') : []);
            Response::json(['ok' => true, 'items' => $cart->snapshot(), 'count' => $cart->count()]);
        } catch (\RuntimeException $error) {
            Response::json(['ok' => false, 'message' => $error->getMessage()], 422);
        }
    }

    public function checkout(Request $request): void
    {
        $cart = new CartService(); $items = $cart->items();
        if (!$items) { Session::flash('error', 'Coșul tău este gol.'); Response::redirect('/cos'); }
        $addresses = [];
        if (Auth::check()) {
            $addressStmt = Database::connection()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');
            $addressStmt->execute([(int) Auth::user()['id']]);
            $addresses = $addressStmt->fetchAll();
        }
        $subtotal = $cart->subtotal();
        $shippingEnabled = filter_var(setting('shipping_enabled', '1'), FILTER_VALIDATE_BOOL);
        $threshold = (float) setting('free_shipping_threshold', 300);
        $shipping = !$shippingEnabled || ($threshold > 0 && $subtotal >= $threshold) ? 0 : (float) setting('standard_shipping_cost', 20);
        View::render('storefront/checkout', ['items' => $items, 'addresses' => $addresses, 'subtotal' => $subtotal, 'shipping' => $shipping, 'paymentMethods' => (new PaymentService())->active($subtotal), 'meta' => ['title' => 'Finalizare comandă — SmileBaby', 'robots' => 'noindex,nofollow']]);
    }

    public function placeOrder(Request $request): void
    {
        $customerType = (string) $request->input('customer_type') === 'company' ? 'company' : 'individual';
        $fields = ['first_name' => 'Prenumele', 'last_name' => 'Numele', 'email' => 'Emailul', 'phone' => 'Telefonul', 'county' => 'Județul', 'city' => 'Localitatea', 'address' => 'Adresa', 'payment_method' => 'Metoda de plată'];
        if ($customerType === 'company') {
            $fields += ['company_name' => 'Denumirea firmei', 'company_vat_id' => 'CUI/CIF', 'company_registration_number' => 'Numărul de la Registrul Comerțului', 'company_address' => 'Adresa sediului social'];
        }
        $errors = Validator::required($request->body, $fields);
        if (!Validator::email((string) $request->input('email'))) $errors['email'] = 'Adresa de email nu este validă.';
        if (!$request->input('terms')) $errors['terms'] = 'Trebuie să accepți termenii magazinului.';
        if ($errors) { Session::put('_old', $request->body); Session::flash('errors', $errors); Response::redirect('/checkout'); }
        try {
            $order = (new OrderService())->create([
                'first_name' => trim((string) $request->input('first_name')), 'last_name' => trim((string) $request->input('last_name')), 'email' => trim((string) $request->input('email')), 'phone' => trim((string) $request->input('phone')), 'county' => trim((string) $request->input('county')), 'city' => trim((string) $request->input('city')), 'address' => trim((string) $request->input('address')), 'postcode' => trim((string) $request->input('postcode')), 'notes' => trim((string) $request->input('notes')),
                'customer_type' => $customerType, 'company_name' => trim((string) $request->input('company_name')), 'company_vat_id' => trim((string) $request->input('company_vat_id')), 'company_registration_number' => trim((string) $request->input('company_registration_number')), 'company_address' => trim((string) $request->input('company_address')),
            ], (string) $request->input('payment_method'));
            Session::put('last_order', ['number' => $order['order_number'], 'email' => $order['email']]);
            if ($order['gateway']['redirect_url']) Response::redirect($order['gateway']['redirect_url']);
            Response::redirect('/comanda-confirmata/' . urlencode($order['order_number']));
        } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); Response::redirect('/checkout'); }
    }

    public function confirmation(Request $request): void
    {
        $number = $request->params['number']; $order = null;
        if (Database::available()) { $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? LIMIT 1'); $stmt->execute([$number]); $order = $stmt->fetch(); }
        $last = Session::get('last_order');
        if (!$order || (!$last && !Auth::isAdmin()) || ($last && $last['email'] !== $order['email'] && !Auth::isAdmin())) { http_response_code(404); View::render('errors/404'); return; }
        $itemsStmt = Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $itemsStmt->execute([(int) $order['id']]);
        View::render('storefront/confirmation', ['order' => $order, 'items' => $itemsStmt->fetchAll(), 'displayState' => 'received', 'meta' => ['title' => 'Am primit comanda ta — SmileBaby', 'robots' => 'noindex,nofollow']]);
    }

    public function paymentResult(Request $request): void
    {
        $number = trim((string) ($request->query['order'] ?? ''));
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? LIMIT 1');
        $stmt->execute([$number]);
        $order = $stmt->fetch();
        $last = Session::get('last_order');
        if (!$order || (!$last && !Auth::isAdmin()) || ($last && $last['email'] !== $order['email'] && !Auth::isAdmin())) { http_response_code(404); View::render('errors/404'); return; }
        $paymentError = null;
        $sessionId = trim((string) ($request->query['session_id'] ?? ''));
        if ($sessionId !== '') {
            try {
                (new StripeCheckoutService())->verifyReturn($sessionId, $number);
            } catch (\Throwable $error) {
                $paymentError = $error->getMessage();
            }
            $stmt->execute([$number]);
            $order = $stmt->fetch();
        }
        $displayState = ($order['status'] ?? '') === 'cancelled'
            ? 'order_cancelled'
            : (!empty($request->query['cancelled']) ? 'cancelled' : (($order['payment_status'] ?? '') === 'paid' ? 'paid' : (($order['payment_status'] ?? '') === 'failed' || $paymentError ? 'failed' : 'pending')));
        $itemsStmt = Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $itemsStmt->execute([(int) $order['id']]);
        View::render('storefront/payment-result', ['order' => $order, 'items' => $itemsStmt->fetchAll(), 'displayState' => $displayState, 'paymentError' => $paymentError, 'meta' => ['title' => 'Status plată — SmileBaby', 'robots' => 'noindex,nofollow']]);
    }

    public function retryPayment(Request $request): void
    {
        $number = (string) $request->params['number'];
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND payment_method="online_card" LIMIT 1');
        $stmt->execute([$number]);
        $order = $stmt->fetch();
        $last = Session::get('last_order');
        if (!$order || $order['payment_status'] === 'paid' || (!$last && !Auth::isAdmin()) || ($last && $last['email'] !== $order['email'] && !Auth::isAdmin())) { http_response_code(404); View::render('errors/404'); return; }
        try {
            $methodStmt = Database::connection()->prepare('SELECT * FROM payment_methods WHERE `key`="online_card" AND enabled=1 LIMIT 1');
            $methodStmt->execute(); $method = $methodStmt->fetch();
            if (!$method) throw new \RuntimeException('Plata cu cardul nu este disponibilă momentan.');
            $result = (new PaymentService())->gateway('online_card')->initializePayment($order, $method);
            Response::redirect($result['redirect_url']);
        } catch (\Throwable $error) { Session::flash('error', $error->getMessage()); Response::redirect('/plata/rezultat?order=' . urlencode($number)); }
    }

    public function wishlist(Request $request): void
    {
        $ids = Session::get('wishlist', []); $products = [];
        foreach ($ids as $id) if ($p = (new ProductRepository())->find((int) $id)) $products[] = $p;
        View::render('storefront/wishlist', ['products' => $products, 'meta' => ['title' => 'Favorite — SmileBaby', 'robots' => 'noindex,nofollow']]);
    }

    public function toggleWishlist(Request $request): void
    {
        $id = (int) $request->input('product_id'); $ids = Session::get('wishlist', []);
        $requestedState = $request->input('active');
        $active = $requestedState === null ? !in_array($id, $ids, true) : filter_var($requestedState, FILTER_VALIDATE_BOOL);
        if ($active && !in_array($id, $ids, true)) $ids[] = $id;
        if (!$active) $ids = array_values(array_diff($ids, [$id]));
        Session::put('wishlist', $ids);
        if (Auth::check() && Database::available()) {
            if (in_array($id, $ids, true)) Database::connection()->prepare('INSERT IGNORE INTO favorites (user_id,product_id) VALUES (?,?)')->execute([Auth::user()['id'], $id]);
            else Database::connection()->prepare('DELETE FROM favorites WHERE user_id=? AND product_id=?')->execute([Auth::user()['id'], $id]);
        }
        if ($request->wantsJson()) Response::json(['ok' => true, 'active' => in_array($id, $ids, true), 'count' => count($ids)]);
        Response::redirect($request->server['HTTP_REFERER'] ?? '/favorite');
    }

    public function syncWishlist(Request $request): void
    {
        $requested = is_array($request->input('items')) ? $request->input('items') : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', array_slice($requested, 0, 200)), fn (int $id) => $id > 0)));
        if ($ids && Database::available()) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::connection()->prepare("SELECT id FROM products WHERE status='active' AND id IN ($placeholders)");
            $stmt->execute($ids);
            $valid = array_map('intval', array_column($stmt->fetchAll(), 'id'));
            $ids = array_values(array_filter($ids, fn (int $id) => in_array($id, $valid, true)));
        } elseif (!Database::available()) {
            $ids = [];
        }
        Session::put('wishlist', $ids);
        if (Auth::check() && Database::available()) {
            $userId = (int) Auth::user()['id'];
            Database::transaction(function ($db) use ($userId, $ids) {
                $db->prepare('DELETE FROM favorites WHERE user_id=?')->execute([$userId]);
                $insert = $db->prepare('INSERT INTO favorites (user_id,product_id) VALUES (?,?)');
                foreach ($ids as $id) $insert->execute([$userId, $id]);
            });
        }
        Response::json(['ok' => true, 'items' => $ids, 'count' => count($ids)]);
    }

    public function newsletter(Request $request): void
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (!Validator::email($email) || !$request->input('consent')) { Session::flash('error', 'Introdu o adresă validă și confirmă acordul.'); Response::redirect('/#newsletter'); }
        Database::connection()->prepare('INSERT INTO newsletter_subscribers (email,consent,status,token) VALUES (?,1,"active",?) ON DUPLICATE KEY UPDATE consent=1,status="active",unsubscribed_at=NULL')->execute([$email, hash('sha256', $email . random_bytes(16))]);
        Session::flash('success', 'Bine ai venit în povestea SmileBaby!'); Response::redirect('/#newsletter');
    }

    public function unsubscribe(Request $request): void { if (Database::available()) Database::connection()->prepare('UPDATE newsletter_subscribers SET status="unsubscribed",unsubscribed_at=NOW() WHERE token=?')->execute([$request->params['token']]); View::render('storefront/message', ['title' => 'Abonare oprită', 'message' => 'Nu vei mai primi noutățile noastre.']); }

    public function review(Request $request): void
    {
        $rating = (int) $request->input('rating');
        $productId = (int) $request->input('product_id');
        $authorName = trim((string) $request->input('author_name'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $title = trim((string) $request->input('title'));
        $body = trim((string) $request->input('body'));
        if ($productId < 1 || $authorName === '' || $rating < 1 || $rating > 5 || $body === '' || !Validator::email($email)) {
            $message = 'Completează corect toate câmpurile recenziei.';
            if ($request->wantsJson()) Response::json(['ok' => false, 'message' => $message], 422);
            Session::flash('error', $message);
            Response::redirect($request->server['HTTP_REFERER'] ?? '/magazin');
        }

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO reviews (product_id,user_id,author_name,email,rating,title,body,status) VALUES (?,?,?,?,?,?,?,"approved")');
        $stmt->execute([$productId, Auth::user()['id'] ?? null, $authorName, $email, $rating, $title ?: null, $body]);
        $reviewId = (int) $db->lastInsertId();
        $summary = $db->prepare('SELECT COUNT(*) review_count,COALESCE(AVG(rating),0) rating FROM reviews WHERE product_id=? AND status="approved"');
        $summary->execute([$productId]);
        $summaryRow = $summary->fetch() ?: ['review_count' => 1, 'rating' => $rating];
        $message = 'Mulțumim pentru recenzia acordată!';

        if ($request->wantsJson()) {
            Response::json([
                'ok' => true,
                'message' => $message,
                'review' => [
                    'id' => $reviewId,
                    'author_name' => $authorName,
                    'rating' => $rating,
                    'title' => $title ?: 'Recenzie client',
                    'body' => $body,
                    'verified_purchase' => false,
                    'created_label' => 'acum',
                ],
                'summary' => [
                    'count' => (int) $summaryRow['review_count'],
                    'rating' => round((float) $summaryRow['rating'], 1),
                ],
            ], 201);
        }

        Session::flash('success', $message . ' Recenzia ta a fost publicată.');
        $returnTo = strtok((string) ($request->server['HTTP_REFERER'] ?? '/magazin'), '#') ?: '/magazin';
        Response::redirect($returnTo . '#recenzii');
    }

    public function track(Request $request): void { View::render('storefront/track', ['order' => null, 'meta' => ['title' => 'Urmărește comanda — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function trackSearch(Request $request): void
    {
        $orderNumber = trim((string) $request->input('order_number'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND email=? LIMIT 1');
        $stmt->execute([$orderNumber, $email]);
        $order = $stmt->fetch();
        if (!$order) {
            View::render('storefront/track', [
                'order' => false,
                'orderNumber' => $orderNumber,
                'email' => $email,
                'meta' => ['title' => 'Urmărește comanda — SmileBaby', 'robots' => 'noindex,nofollow'],
            ]);
            return;
        }

        Session::put('tracked_order_id', (int) $order['id']);
        Response::redirect('/urmareste-comanda/status');
    }

    public function trackResult(Request $request): void
    {
        $orderId = (int) Session::get('tracked_order_id', 0);
        if ($orderId < 1) {
            Response::redirect('/urmareste-comanda');
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            Session::forget('tracked_order_id');
            Response::redirect('/urmareste-comanda');
        }

        $this->renderTrackedOrder($order);
    }

    public function trackSigned(Request $request): void
    {
        $identity = (new OrderTrackingService())->verify((string) ($request->params['token'] ?? ''));
        if (!$identity) { http_response_code(404); View::render('errors/404'); return; }

        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE id=? AND order_number=? LIMIT 1');
        $stmt->execute([$identity['id'], $identity['number']]);
        $order = $stmt->fetch();
        if (!$order) { http_response_code(404); View::render('errors/404'); return; }

        $this->renderTrackedOrder($order);
    }

    private function renderTrackedOrder(array $order): void
    {
        $orderId = (int) $order['id'];
        $db = Database::connection();
        $itemsStmt = $db->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $itemsStmt->execute([$orderId]);
        $historyStmt = $db->prepare('SELECT new_status,status_history.message,status_history.created_at FROM order_status_history status_history WHERE order_id=? ORDER BY created_at,id');
        $historyStmt->execute([$orderId]);

        View::render('storefront/track-result', [
            'order' => $order,
            'items' => $itemsStmt->fetchAll(),
            'history' => $historyStmt->fetchAll(),
            'meta' => [
                'title' => 'Comanda ' . $order['order_number'] . ' — SmileBaby',
                'robots' => 'noindex,nofollow',
            ],
        ]);
    }

    public function blog(Request $request): void
    {
        $posts = Database::available() ? Database::connection()->query('SELECT * FROM posts WHERE status="published" ORDER BY published_at DESC')->fetchAll() : [];
        $seo = new SeoService();
        View::render('storefront/blog', ['posts' => $posts, 'meta' => [
            'title' => 'Ghiduri și inspirație pentru botez | SmileBaby',
            'description' => 'Idei, ghiduri și inspirație din atelierul SmileBaby pentru alegerea și personalizarea produselor de botez.',
            'canonical' => rtrim((string) config('app.url'), '/') . '/blog',
            'robots' => $posts ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,follow',
            'schemas' => [$seo->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Atelier', 'url' => '/blog']])],
        ]]);
    }
    public function post(Request $request): void
    {
        $stmt = Database::connection()->prepare('SELECT * FROM posts WHERE slug=? AND status="published" LIMIT 1'); $stmt->execute([$request->params['slug']]); $post = $stmt->fetch();
        if (!$post) { http_response_code(404); View::render('errors/404'); return; }
        $appUrl = rtrim((string) config('app.url'), '/');
        $postUrl = $appUrl . '/blog/' . rawurlencode((string) $post['slug']);
        $postImage = optimized_image_url($post['featured_image'] ?? null, 'display');
        $postImageUrl = preg_match('#^https?://#i', $postImage) ? $postImage : $appUrl . '/' . ltrim($postImage, '/');
        $seo = new SeoService();
        View::render('storefront/post', ['post' => $post, 'meta' => [
            'title' => $post['meta_title'] ?: $post['title'] . ' | SmileBaby',
            'description' => $post['meta_description'] ?: $post['excerpt'],
            'canonical' => $postUrl,
            'robots' => !empty($post['indexable']) ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,follow',
            'image' => $postImage,
            'type' => 'article',
            'schemas' => [[
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                '@id' => $postUrl . '#article',
                'mainEntityOfPage' => $postUrl,
                'headline' => (string) $post['title'],
                'description' => (string) ($post['excerpt'] ?? ''),
                'image' => $postImageUrl,
                'datePublished' => date('c', strtotime((string) ($post['published_at'] ?? $post['created_at']))),
                'dateModified' => date('c', strtotime((string) $post['updated_at'])),
                'inLanguage' => 'ro-RO',
                'author' => ['@id' => $appUrl . '/#organization'],
                'publisher' => ['@id' => $appUrl . '/#organization'],
            ], $seo->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Atelier', 'url' => '/blog'], ['name' => (string) $post['title'], 'url' => '/blog/' . $post['slug']]])],
        ]]);
    }

    public function about(Request $request): void
    {
        $seo = new SeoService();
        View::render('storefront/about', ['meta' => [
            'title' => 'Despre atelierul SmileBaby | Produse pentru botez',
            'description' => 'Descoperă atelierul SmileBaby și felul în care pregătim trusouri, lumânări și mărturii personalizate pentru botez.',
            'schemas' => [$seo->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Despre noi', 'url' => '/despre-noi']])],
        ]]);
    }
    public function page(Request $request): void
    {
        $page = (string) ($request->params['page'] ?? trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));
        $meta = match ($page) {
            'termeni-si-conditii' => ['title' => 'Termeni și condiții — SmileBaby', 'description' => 'Condițiile de comandă SmileBaby, inclusiv avansul și returul produselor personalizate.'],
            'confidentialitate' => ['title' => 'Politica de confidențialitate — SmileBaby', 'description' => 'Cum colectează, folosește și protejează SmileBaby datele tale personale.'],
            'cookies' => ['title' => 'Politica de cookies — SmileBaby', 'description' => 'Informații despre cookie-urile, stocarea locală și preferințele folosite de SmileBaby.'],
            'livrare-si-retur' => ['title' => 'Livrare și retur — SmileBaby', 'description' => 'Livrarea, avansul și condițiile de retur, inclusiv regulile pentru produsele personalizate.'],
            default => ['title' => 'Informații — SmileBaby', 'description' => 'Informații utile SmileBaby.'],
        };
        $meta['schemas'] = [(new SeoService())->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => $meta['title'], 'url' => '/' . $page]])];
        View::render('storefront/page', ['page' => $page, 'meta' => $meta]);
    }
    public function contact(Request $request): void { View::render('storefront/contact', ['meta' => ['title' => 'Contact SmileBaby | Comenzi și produse personalizate', 'description' => 'Contactează atelierul SmileBaby pentru informații despre trusouri, lumânări, mărturii, personalizare, comenzi și livrare.', 'schemas' => [(new SeoService())->breadcrumbSchema([['name' => 'Acasă', 'url' => '/'], ['name' => 'Contact', 'url' => '/contact']])]]]); }
    public function contactSend(Request $request): void { $errors = Validator::required($request->body, ['name' => 'Numele', 'email' => 'Emailul', 'message' => 'Mesajul']); if ($errors) { Session::flash('errors', $errors); Response::redirect('/contact'); } Database::connection()->prepare('INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)')->execute([trim($request->input('name')), mb_strtolower(trim($request->input('email'))), trim($request->input('phone')), trim($request->input('subject')), trim($request->input('message'))]); Session::flash('success', 'Mesajul tău a fost trimis. Îți răspundem cât mai curând.'); Response::redirect('/contact'); }

    public function search(Request $request): void
    {
        $query=trim((string)($request->query['q']??''));$result=(new SearchService())->search($query,8);
        Response::json(['query'=>$query,'count'=>$result['count'],'products'=>array_map(fn($p)=>['name'=>$p['name'],'url'=>'/produs/'.$p['slug'],'price'=>money($p['price']),'image'=>optimized_image_url($p['image_path'],'card'),'category'=>$p['category_name']??'SmileBaby','sku'=>$p['sku']??null],$result['products']),'categories'=>array_map(fn($c)=>['name'=>$c['name'],'url'=>'/categorie/'.$c['slug'],'image'=>optimized_image_url($c['image_path']??null,'card')],$result['categories'])]);
    }

    public function sitemap(Request $request): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $maps = ['sitemap-pages.xml', 'sitemap-products.xml', 'sitemap-categories.xml'];
        if (Database::available() && (int) Database::connection()->query('SELECT COUNT(*) FROM posts WHERE status="published" AND indexable=1')->fetchColumn() > 0) $maps[] = 'sitemap-posts.xml';
        $items = array_map(static fn (string $map): string => '<sitemap><loc>' . self::xml($appUrl . '/' . $map) . '</loc></sitemap>', $maps);
        Response::xml('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode('', $items) . '</sitemapindex>');
    }

    public function sitemapPages(Request $request): void
    {
        $updated = date('c', max(filemtime(BASE_PATH . '/views/storefront/home.php'), filemtime(BASE_PATH . '/views/storefront/page.php')));
        $paths = ['/', '/magazin', '/despre-noi', '/contact', '/termeni-si-conditii', '/confidentialitate', '/cookies', '/livrare-si-retur'];
        if (Database::available() && (int) Database::connection()->query('SELECT COUNT(*) FROM posts WHERE status="published" AND indexable=1')->fetchColumn() > 0) $paths[] = '/blog';
        $appUrl = rtrim((string) config('app.url'), '/');
        $rows = array_map(static fn (string $path): array => ['loc' => $appUrl . $path, 'lastmod' => $updated], $paths);
        $this->sitemapUrlset($rows);
    }

    public function sitemapProducts(Request $request): void
    {
        $rows = [];
        if (Database::available()) {
            $sql = 'SELECT p.slug,p.name,p.updated_at,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path FROM products p WHERE p.status="active" AND p.indexable=1 ORDER BY p.id';
            $appUrl = rtrim((string) config('app.url'), '/');
            foreach (Database::connection()->query($sql)->fetchAll() as $product) {
                $image = optimized_image_url($product['image_path'] ?? null, 'display');
                $rows[] = [
                    'loc' => $appUrl . '/produs/' . rawurlencode((string) $product['slug']),
                    'lastmod' => date('c', strtotime((string) $product['updated_at'])),
                    'image' => preg_match('#^https?://#i', $image) ? $image : $appUrl . $image,
                    'image_title' => (string) $product['name'],
                ];
            }
        }
        $this->sitemapUrlset($rows, true);
    }

    public function sitemapCategories(Request $request): void
    {
        $rows = [];
        if (Database::available()) {
            $appUrl = rtrim((string) config('app.url'), '/');
            foreach (Database::connection()->query('SELECT slug,updated_at FROM categories WHERE status="active" AND indexable=1 ORDER BY id')->fetchAll() as $category) $rows[] = [
                'loc' => $appUrl . '/categorie/' . rawurlencode((string) $category['slug']),
                'lastmod' => date('c', strtotime((string) $category['updated_at'])),
            ];
        }
        $this->sitemapUrlset($rows);
    }

    public function sitemapPosts(Request $request): void
    {
        $rows = [];
        if (Database::available()) {
            $appUrl = rtrim((string) config('app.url'), '/');
            foreach (Database::connection()->query('SELECT slug,updated_at FROM posts WHERE status="published" AND indexable=1 ORDER BY id')->fetchAll() as $post) $rows[] = [
                'loc' => $appUrl . '/blog/' . rawurlencode((string) $post['slug']),
                'lastmod' => date('c', strtotime((string) $post['updated_at'])),
            ];
        }
        $this->sitemapUrlset($rows);
    }

    public function llms(Request $request): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $lines = [
            '# SmileBaby',
            '',
            '> Magazin online și atelier din România pentru trusouri de botez, lumânări, mărturii, cadouri și produse personalizate.',
            '',
            'Limba principală: română (ro-RO). Moneda: RON. Produsele sunt noi și pot include opțiuni de personalizare.',
            '',
            '## Pagini principale',
            '- [Magazin](' . $appUrl . '/magazin): catalogul complet de produse',
            '- [Despre SmileBaby](' . $appUrl . '/despre-noi): informații despre atelier și modul de lucru',
            '- [Livrare și retur](' . $appUrl . '/livrare-si-retur): costuri, termene și excepțiile produselor personalizate',
            '- [Contact](' . $appUrl . '/contact): date oficiale de contact',
            '',
            '## Date structurate',
            '- [Sitemap](' . $appUrl . '/sitemap.xml)',
            '- [Catalog extins pentru sisteme AI](' . $appUrl . '/llms-full.txt)',
            '',
            'Informațiile despre preț, stoc și variante trebuie verificate pe pagina fiecărui produs, deoarece se pot modifica.',
        ];
        Response::text(implode("\n", $lines) . "\n", 'text/markdown');
    }

    public function llmsFull(Request $request): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $lines = ['# Catalog SmileBaby', '', 'Catalog public actualizat din baza de date a magazinului.', ''];
        if (Database::available()) {
            $sql = 'SELECT p.name,p.slug,p.short_description,p.regular_price,p.sale_price,p.is_customizable,GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") categories FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id WHERE p.status="active" AND p.indexable=1 GROUP BY p.id ORDER BY p.name';
            foreach (Database::connection()->query($sql)->fetchAll() as $product) {
                $price = (float) ($product['sale_price'] ?: $product['regular_price']);
                $description = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($product['short_description'] ?? ''))));
                $lines[] = '## ' . $product['name'];
                $lines[] = '- URL: ' . $appUrl . '/produs/' . rawurlencode((string) $product['slug']);
                $lines[] = '- Categorie: ' . (($product['categories'] ?? '') ?: 'Produse pentru botez');
                $lines[] = '- Preț curent: ' . number_format($price, 2, ',', '.') . ' RON';
                $lines[] = '- Personalizare: ' . (!empty($product['is_customizable']) ? 'disponibilă' : 'nu este indicată');
                if ($description !== '') $lines[] = '- Descriere: ' . mb_substr($description, 0, 320);
                $lines[] = '';
            }
        }
        Response::text(implode("\n", $lines) . "\n", 'text/markdown');
    }

    public function agents(Request $request): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        Response::text(implode("\n", [
            '# SmileBaby — informații pentru agenți și asistenți AI',
            '',
            '- Sursa oficială: ' . $appUrl . '/',
            '- Catalog public: ' . $appUrl . '/magazin',
            '- Catalog structurat: ' . $appUrl . '/llms-full.txt',
            '- Sitemap: ' . $appUrl . '/sitemap.xml',
            '- Limbă: română (ro-RO)',
            '- Monedă: RON',
            '- Arie de livrare: România',
            '',
            'Folosește paginile publice ale produselor drept sursă pentru denumire, descriere, preț, disponibilitate și opțiuni. Verifică pagina produsului înainte de a comunica prețul sau stocul, deoarece acestea se pot modifica.',
            '',
            'Nu accesa și nu cita pagini private de cont, checkout, plată, administrare sau urmărire a comenzilor.',
        ]) . "\n", 'text/markdown');
    }

    private function sitemapUrlset(array $rows, bool $withImages = false): never
    {
        $namespace = $withImages ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';
        $items = [];
        foreach ($rows as $row) {
            $entry = '<url><loc>' . self::xml((string) $row['loc']) . '</loc>';
            if (!empty($row['lastmod'])) $entry .= '<lastmod>' . self::xml((string) $row['lastmod']) . '</lastmod>';
            if ($withImages && !empty($row['image'])) {
                $entry .= '<image:image><image:loc>' . self::xml((string) $row['image']) . '</image:loc>';
                if (!empty($row['image_title'])) $entry .= '<image:title>' . self::xml((string) $row['image_title']) . '</image:title>';
                $entry .= '</image:image>';
            }
            $items[] = $entry . '</url>';
        }
        Response::xml('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $namespace . '>' . implode('', $items) . '</urlset>');
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function paymentCallback(Request $request): void
    {
        if (!Database::available()) Response::json(['ok' => false], 503);
        try {
            if ((string) ($request->params['provider'] ?? '') !== 'stripe') throw new \RuntimeException('Provider invalid.');
            $signature = (string) ($request->server['HTTP_STRIPE_SIGNATURE'] ?? '');
            $result = (new StripeCheckoutService())->handleWebhook($request->rawBody, $signature);
            Response::json(['ok' => true, 'duplicate' => $result['duplicate'] ?? false]);
        } catch (\Throwable $e) {
            error_log('Stripe webhook rejected: ' . $e->getMessage());
            Response::json(['ok' => false, 'message' => 'Callback respins.'], 400);
        }
    }
}
