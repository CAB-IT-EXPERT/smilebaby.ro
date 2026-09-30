-- Un produs fără gestiune cantitativă are stoc nelimitat și trebuie să fie comandabil.
UPDATE products
SET stock_quantity = NULL,
    stock_status = 'in_stock',
    allow_backorders = 0
WHERE manage_stock = 0;

-- La variante, cantitatea NULL are aceeași semnificație: stoc nelimitat.
UPDATE product_variants
SET stock_status = 'in_stock'
WHERE stock_quantity IS NULL;
