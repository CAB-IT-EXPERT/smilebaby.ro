INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified_at)
VALUES ('admin@smilebaby.ro', '$2y$12$uMWqbvhKNzlZxLlgq0D6puBu3n.IkShlhfmhcIpBHibLjxO7vWn2e', 'Admin', 'SmileBaby', 'admin', 'active', NOW())
ON DUPLICATE KEY UPDATE
password_hash = VALUES(password_hash),
first_name = VALUES(first_name),
last_name = VALUES(last_name),
role = 'admin',
status = 'active',
email_verified_at = COALESCE(email_verified_at, NOW());

INSERT INTO payment_methods (`key`, name, description, enabled, sort_order, fee_type, fee_value, instructions, settings_json)
VALUES
('online_card', 'Plată online cu cardul', 'Card, Apple Pay, Google Pay sau Revolut Pay, procesate securizat prin Stripe.', 1, 1, 'none', 0, '', '{"provider":"stripe","test_mode":true}'),
('cash_on_delivery', 'Plată ramburs', 'Plătești curierului la primirea coletului.', 1, 2, 'none', 0, '', '{}')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order);

INSERT INTO settings (`key`, value, type, group_name) VALUES
('site_name', 'SmileBaby', 'string', 'general'),
('site_email', 'contact@smilebaby.ro', 'string', 'general'),
('site_phone', '+40 740 002 848', 'string', 'general'),
('site_address', 'România', 'string', 'general'),
('hero_eyebrow', 'MAI MULT DECÂT OBIECTE — AMINTIRI PENTRU O VIAȚĂ', 'string', 'homepage'),
('hero_title', 'Începuturi delicate pentru povești mari.', 'string', 'homepage'),
('hero_description', 'Descoperă colecții atent alese pentru primii ani de viață, create cu grijă, din cele mai fine materiale, pentru ca fiecare zi să fie o bucurie.', 'text', 'homepage'),
('hero_image', 'assets/images/hero-smilebaby.png', 'string', 'homepage'),
('hero_cta_label', 'DESCOPERĂ COLECȚIA', 'string', 'homepage'),
('hero_cta_url', '/magazin', 'string', 'homepage'),
('about_title', 'Produse alese cu suflet, pentru începuturi frumoase.', 'string', 'homepage'),
('about_text', 'Credem în puterea detaliilor delicate, în materialele naturale și în bucuria de a oferi cadouri cu sens.', 'text', 'homepage'),
('about_image', '', 'string', 'homepage'),
('home_quote', 'Copiii nu țin minte lucrurile materiale, ci dragostea cu care au fost înconjurați.', 'text', 'homepage'),
('announcement_enabled', '1', 'boolean', 'shipping'),
('shipping_enabled', '1', 'boolean', 'shipping'),
('standard_shipping_cost', '20', 'number', 'shipping'),
('return_shipping_cost', '20', 'number', 'shipping'),
('free_shipping_threshold', '300', 'number', 'shipping'),
('estimated_delivery_text', '2–3 zile lucrătoare', 'string', 'shipping'),
('default_payment_method', 'online_card', 'string', 'payments'),
('seo_title', 'SmileBaby — produse delicate pentru începuturi frumoase', 'string', 'seo'),
('seo_description', 'Trusouri de botez, lumânări, mărturii și jucării croșetate, produse handmade de calitate realizate în România.', 'text', 'seo'),
('google_site_verification', 'aPxwrBNLU-Gga2vs-aFL2dyYByHeoW3axzyxLRaiN7g', 'string', 'seo'),
('facebook_url', 'https://www.facebook.com/SmileBabyPovesteaBotezului', 'string', 'social'),
('instagram_url', 'https://www.instagram.com/smilebaby.ro/', 'string', 'social'),
('tiktok_url', 'https://www.tiktok.com/@smilebaby.ro', 'string', 'social'),
('pinterest_url', '', 'string', 'social'),
('youtube_url', '', 'string', 'social')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO categories (name, slug, short_description, image_path, status, show_on_homepage, homepage_order, meta_title, meta_description, indexable)
VALUES
('Botez Fetițe', 'botez-fetite', 'Trusouri și accesorii delicate pentru botezul fetiței.', 'assets/images/categories/botez-fetite.png', 'active', 1, 1, 'Botez Fetițe — SmileBaby', 'Trusouri și accesorii personalizate pentru botezul fetiței.', 1),
('Botez Băieți', 'botez-baieti', 'Trusouri și accesorii speciale pentru botezul băiețelului.', 'assets/images/categories/botez-baieti.png', 'active', 1, 2, 'Botez Băieți — SmileBaby', 'Trusouri și accesorii personalizate pentru botezul băiețelului.', 1),
('Lumânări Botez', 'lumanari-botez', 'Lumânări de botez create și decorate cu grijă.', 'assets/images/categories/lumanari-botez.png', 'active', 1, 3, 'Lumânări Botez — SmileBaby', 'Lumânări de botez personalizate și decorate manual.', 1),
('Mărturii Botez', 'marturii-botez', 'Mărturii personalizate pentru o amintire de neuitat.', 'assets/images/categories/marturii-botez.png', 'active', 1, 4, 'Mărturii Botez — SmileBaby', 'Mărturii de botez personalizate, pregătite cu drag.', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), short_description=VALUES(short_description), image_path=VALUES(image_path), status='active', show_on_homepage=1, homepage_order=VALUES(homepage_order), meta_title=VALUES(meta_title), meta_description=VALUES(meta_description), indexable=1;
