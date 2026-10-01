<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

final class ProductCustomizationService
{
    private static bool $schemaReady = false;

    public function ensureSchema(?PDO $db = null): void
    {
        if (self::$schemaReady || !Database::available()) return;
        $db ??= Database::connection();

        try {
            $db->query('SELECT customization_price FROM products LIMIT 0');
            $db->query('SELECT customization_help_text FROM products LIMIT 0');
            $db->query('SELECT id FROM product_customization_fields LIMIT 0');
            $db->query('SELECT id FROM product_customization_options LIMIT 0');
            $db->query('SELECT customization_json,customization_price FROM order_items LIMIT 0');
            $this->backfillExistingCustomizableProducts($db);
            $this->backfillPersonalizedMessageField($db);
            $this->backfillTrusouOptions($db);
            self::$schemaReady = true;
            return;
        } catch (PDOException) {
            // The first request after deployment applies the small, idempotent schema upgrade below.
        }

        if (!$this->columnExists($db, 'products', 'customization_price')) {
            $db->exec('ALTER TABLE products ADD customization_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER is_customizable');
        }
        if (!$this->columnExists($db, 'products', 'customization_help_text')) {
            $db->exec('ALTER TABLE products ADD customization_help_text VARCHAR(500) NULL AFTER customization_price');
        }
        $db->exec('CREATE TABLE IF NOT EXISTS product_customization_fields (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(150) NOT NULL,
            field_type ENUM("text","date") NOT NULL DEFAULT "text",
            placeholder VARCHAR(190) NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customization_product (product_id, sort_order, id),
            CONSTRAINT fk_customization_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->exec('CREATE TABLE IF NOT EXISTS product_customization_options (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(150) NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_customization_option_label (product_id, label),
            INDEX idx_customization_options_product (product_id, sort_order, id),
            CONSTRAINT fk_customization_options_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if (!$this->columnExists($db, 'order_items', 'customization_json')) {
            $db->exec('ALTER TABLE order_items ADD customization_json TEXT NULL AFTER variant_name');
        }
        if (!$this->columnExists($db, 'order_items', 'customization_price')) {
            $db->exec('ALTER TABLE order_items ADD customization_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER customization_json');
        }
        $this->backfillExistingCustomizableProducts($db);
        $this->backfillPersonalizedMessageField($db);
        $this->backfillTrusouOptions($db);
        self::$schemaReady = true;
    }

    public function fields(int $productId, ?PDO $db = null): array
    {
        if ($productId < 1 || !Database::available()) return [];
        $db ??= Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT id,product_id,label,field_type,placeholder,is_required,sort_order FROM product_customization_fields WHERE product_id=? ORDER BY sort_order,id');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function options(int $productId, ?PDO $db = null): array
    {
        if ($productId < 1 || !Database::available()) return [];
        $db ??= Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT id,product_id,label,price,sort_order FROM product_customization_options WHERE product_id=? ORDER BY sort_order,id');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function saveOptions(PDO $db, int $productId, array $input): void
    {
        $this->ensureSchema($db);
        $ids = array_values((array) ($input['ids'] ?? []));
        $labels = array_values((array) ($input['labels'] ?? []));
        $prices = array_values((array) ($input['prices'] ?? []));
        $keep = [];
        $seenLabels = [];
        $update = $db->prepare('UPDATE product_customization_options SET label=?,price=?,sort_order=? WHERE id=? AND product_id=?');
        $insert = $db->prepare('INSERT INTO product_customization_options (product_id,label,price,sort_order) VALUES (?,?,?,?)');
        foreach ($labels as $index => $rawLabel) {
            $label = mb_substr(trim((string) $rawLabel), 0, 150);
            if ($label === '') continue;
            $normalizedLabel = mb_strtolower($label);
            if (isset($seenLabels[$normalizedLabel])) throw new RuntimeException('Personalizarea „' . $label . '” este introdusă de două ori.');
            $seenLabels[$normalizedLabel] = true;
            $rawPrice = str_replace(',', '.', trim((string) ($prices[$index] ?? '0')));
            if (!is_numeric($rawPrice) || !is_finite((float) $rawPrice) || (float) $rawPrice < 0 || (float) $rawPrice > 999999.99) throw new RuntimeException('Introdu un preț valid pentru „' . $label . '”.');
            $price = round((float) $rawPrice, 2);
            $optionId = (int) ($ids[$index] ?? 0);
            if ($optionId > 0) {
                if (!$this->optionBelongsToProduct($db, $optionId, $productId)) throw new RuntimeException('O opțiune de personalizare nu aparține acestui produs. Reîncarcă pagina și încearcă din nou.');
                $update->execute([$label, $price, $index, $optionId, $productId]);
                $keep[] = $optionId;
            } else {
                $insert->execute([$productId, $label, $price, $index]);
                $keep[] = (int) $db->lastInsertId();
            }
        }
        if ($keep) {
            $marks = implode(',', array_fill(0, count($keep), '?'));
            $db->prepare("DELETE FROM product_customization_options WHERE product_id=? AND id NOT IN ($marks)")->execute([$productId, ...$keep]);
        } else {
            $db->prepare('DELETE FROM product_customization_options WHERE product_id=?')->execute([$productId]);
        }
    }

    public function validateOptions(int $productId, array $selected, ?PDO $db = null): array
    {
        $options = $this->options($productId, $db);
        if (!$options) {
            if ($selected) throw new RuntimeException('Opțiunile de personalizare nu mai sunt disponibile.');
            return [];
        }
        $selectedIds = array_values(array_unique(array_filter(array_map(static fn ($entry): int => (int) (is_array($entry) ? ($entry['option_id'] ?? $entry['id'] ?? 0) : $entry), $selected))));
        if (!$selectedIds) throw new RuntimeException('Alege cel puțin o parte a produsului pe care vrei să o personalizezi.');
        $byId = array_column($options, null, 'id');
        $validated = [];
        foreach ($selectedIds as $id) {
            if (!isset($byId[$id])) throw new RuntimeException('O opțiune de personalizare nu mai este disponibilă.');
            $option = $byId[$id];
            $validated[] = ['option_id' => (int) $id, 'label' => (string) $option['label'], 'price' => round((float) $option['price'], 2)];
        }
        return $validated;
    }

    public function saveFields(PDO $db, int $productId, array $input): void
    {
        $this->ensureSchema($db);
        $ids = array_values((array) ($input['ids'] ?? []));
        $labels = array_values((array) ($input['labels'] ?? []));
        $types = array_values((array) ($input['types'] ?? []));
        $placeholders = array_values((array) ($input['placeholders'] ?? []));
        $required = array_values((array) ($input['required'] ?? []));
        $keep = [];
        $update = $db->prepare('UPDATE product_customization_fields SET label=?,field_type=?,placeholder=?,is_required=?,sort_order=? WHERE id=? AND product_id=?');
        $insert = $db->prepare('INSERT INTO product_customization_fields (product_id,label,field_type,placeholder,is_required,sort_order) VALUES (?,?,?,?,?,?)');

        foreach ($labels as $index => $rawLabel) {
            $label = mb_substr(trim((string) $rawLabel), 0, 150);
            if ($label === '') continue;
            $type = in_array(($types[$index] ?? 'text'), ['text', 'date'], true) ? $types[$index] : 'text';
            $placeholder = mb_substr(trim((string) ($placeholders[$index] ?? '')), 0, 190) ?: null;
            $isRequired = (int) (($required[$index] ?? '1') === '1');
            $fieldId = (int) ($ids[$index] ?? 0);
            if ($fieldId > 0) {
                $update->execute([$label, $type, $placeholder, $isRequired, $index, $fieldId, $productId]);
                if ($update->rowCount() || $this->fieldBelongsToProduct($db, $fieldId, $productId)) $keep[] = $fieldId;
            } else {
                $insert->execute([$productId, $label, $type, $placeholder, $isRequired, $index]);
                $keep[] = (int) $db->lastInsertId();
            }
        }

        if ($keep) {
            $marks = implode(',', array_fill(0, count($keep), '?'));
            $db->prepare("DELETE FROM product_customization_fields WHERE product_id=? AND id NOT IN ($marks)")->execute([$productId, ...$keep]);
        } else {
            $db->prepare('DELETE FROM product_customization_fields WHERE product_id=?')->execute([$productId]);
        }
    }

    public function validateValues(int $productId, array $values, ?PDO $db = null): array
    {
        $fields = $this->fields($productId, $db);
        if (!$fields) throw new RuntimeException('Personalizarea acestui produs nu este configurată încă.');
        $normalized = [];
        foreach ($fields as $field) {
            $value = trim((string) ($values[(string) $field['id']] ?? $values[(int) $field['id']] ?? ''));
            if ($value === '' && (int) $field['is_required']) {
                throw new RuntimeException('Completează câmpul „' . $field['label'] . '” pentru personalizare.');
            }
            if ($value === '') continue;
            if ($field['field_type'] === 'date') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Alege o dată validă pentru „' . $field['label'] . '”.');
            }
            $normalized[] = [
                'field_id' => (int) $field['id'],
                'label' => $field['label'],
                'type' => $field['field_type'],
                'value' => mb_substr($value, 0, 250),
            ];
        }
        return $normalized;
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function fieldBelongsToProduct(PDO $db, int $fieldId, int $productId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM product_customization_fields WHERE id=? AND product_id=?');
        $stmt->execute([$fieldId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    private function optionBelongsToProduct(PDO $db, int $optionId, int $productId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM product_customization_options WHERE id=? AND product_id=?');
        $stmt->execute([$optionId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    private function backfillTrusouOptions(PDO $db): void
    {
        $marker = 'product_trusou_customization_options_v1';
        $check = $db->prepare('SELECT value FROM settings WHERE `key`=? LIMIT 1');
        $check->execute([$marker]);
        if ((string) $check->fetchColumn() === '1') return;
        $defaults = [
            ['Capac cufăr', 50],
            ['Prosoape (prosop mare + prosop mic)', 100],
            ['Fașă de botez', 25],
            ['Pânză de mir', 25],
            ['Lumânare', 20],
        ];
        $findProducts = $db->prepare('SELECT id FROM products WHERE is_customizable=1 AND LOWER(name) LIKE ? ORDER BY id');
        $findProducts->execute(['%trusou%']);
        $products = $findProducts->fetchAll(PDO::FETCH_COLUMN);
        $existing = $db->prepare('SELECT COUNT(*) FROM product_customization_options WHERE product_id=?');
        $insert = $db->prepare('INSERT IGNORE INTO product_customization_options (product_id,label,price,sort_order) VALUES (?,?,?,?)');
        $db->beginTransaction();
        try {
            foreach ($products as $productId) {
                $existing->execute([(int) $productId]);
                if ((int) $existing->fetchColumn() > 0) continue;
                foreach ($defaults as $order => [$label, $price]) $insert->execute([(int) $productId, $label, $price, $order]);
            }
            $db->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,"1","boolean","system") ON DUPLICATE KEY UPDATE value="1",type="boolean",group_name="system"')->execute([$marker]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    /**
     * Existing products already marked as customizable receive the two useful
     * defaults once. The marker deliberately prevents deleted fields from being
     * recreated later if the shop administrator chooses a different setup.
     */
    private function backfillExistingCustomizableProducts(PDO $db): void
    {
        $marker = 'product_customization_defaults_v1';
        $check = $db->prepare('SELECT value FROM settings WHERE `key`=? LIMIT 1');
        $check->execute([$marker]);
        if ((string) $check->fetchColumn() === '1') return;

        $insert = $db->prepare('INSERT INTO product_customization_fields
            (product_id,label,field_type,placeholder,is_required,sort_order)
            SELECT p.id,?,?,?,?,COALESCE((
                SELECT MAX(existing.sort_order) + 1
                FROM product_customization_fields existing
                WHERE existing.product_id=p.id
            ),0)
            FROM products p
            WHERE p.is_customizable=1
              AND NOT EXISTS (
                SELECT 1 FROM product_customization_fields duplicate
                WHERE duplicate.product_id=p.id AND LOWER(TRIM(duplicate.label))=LOWER(?)
              )');

        $db->beginTransaction();
        try {
            $insert->execute(['Numele copilului', 'text', 'Ex.: Maria', 1, 'Numele copilului']);
            $insert->execute(['Data botezului/nașterii', 'date', null, 1, 'Data botezului/nașterii']);
            $db->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,"1","boolean","system")
                ON DUPLICATE KEY UPDATE value="1",type="boolean",group_name="system"')->execute([$marker]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    private function backfillPersonalizedMessageField(PDO $db): void
    {
        $marker = 'product_customization_message_default_v1';
        $check = $db->prepare('SELECT value FROM settings WHERE `key`=? LIMIT 1');
        $check->execute([$marker]);
        if ((string) $check->fetchColumn() === '1') return;

        $db->beginTransaction();
        try {
            $insert = $db->prepare('INSERT INTO product_customization_fields
                (product_id,label,field_type,placeholder,is_required,sort_order)
                SELECT p.id,"Mesaj personalizat","text","Scrie mesajul dorit",0,COALESCE((
                    SELECT MAX(existing.sort_order) + 1
                    FROM product_customization_fields existing
                    WHERE existing.product_id=p.id
                ),0)
                FROM products p
                WHERE p.is_customizable=1
                  AND NOT EXISTS (
                    SELECT 1 FROM product_customization_fields duplicate
                    WHERE duplicate.product_id=p.id AND LOWER(TRIM(duplicate.label))=LOWER("Mesaj personalizat")
                  )');
            $insert->execute();
            $db->prepare('INSERT INTO settings (`key`,value,type,group_name) VALUES (?,"1","boolean","system")
                ON DUPLICATE KEY UPDATE value="1",type="boolean",group_name="system"')->execute([$marker]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }
}
