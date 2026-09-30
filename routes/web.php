<?php

use App\Controllers\StorefrontController;

$router->get('/', [StorefrontController::class, 'home']);
$router->get('/magazin', [StorefrontController::class, 'shop']);
$router->get('/colectii', [StorefrontController::class, 'shop']);
$router->get('/categorie/{slug}', [StorefrontController::class, 'category']);
$router->get('/produs/{slug}', [StorefrontController::class, 'product']);
$router->get('/cauta', [StorefrontController::class, 'search']);
$router->get('/cos', [StorefrontController::class, 'cart']);
$router->get('/cos/mini', [StorefrontController::class, 'cartDrawer']);
$router->post('/cos/adauga', [StorefrontController::class, 'addCart'], ['csrf']);
$router->post('/cos/actualizeaza', [StorefrontController::class, 'updateCart'], ['csrf']);
$router->post('/cos/personalizare', [StorefrontController::class, 'updateCartCustomization'], ['csrf']);
$router->post('/cos/elimina', [StorefrontController::class, 'removeCart'], ['csrf']);
$router->post('/cos/sincronizeaza', [StorefrontController::class, 'syncCart'], ['csrf']);
$router->get('/checkout', [StorefrontController::class, 'checkout']);
$router->post('/checkout', [StorefrontController::class, 'placeOrder'], ['csrf']);
$router->get('/comanda-confirmata/{number}', [StorefrontController::class, 'confirmation']);
$router->get('/plata/rezultat', [StorefrontController::class, 'paymentResult']);
$router->post('/plata/reincearca/{number}', [StorefrontController::class, 'retryPayment'], ['csrf']);
$router->get('/favorite', [StorefrontController::class, 'wishlist']);
$router->post('/favorite', [StorefrontController::class, 'toggleWishlist'], ['csrf']);
$router->post('/favorite/sincronizeaza', [StorefrontController::class, 'syncWishlist'], ['csrf']);
$router->post('/newsletter', [StorefrontController::class, 'newsletter'], ['csrf']);
$router->get('/newsletter/dezabonare/{token}', [StorefrontController::class, 'unsubscribe']);
$router->post('/recenzie', [StorefrontController::class, 'review'], ['csrf']);
$router->get('/urmareste-comanda', [StorefrontController::class, 'track']);
$router->post('/urmareste-comanda', [StorefrontController::class, 'trackSearch'], ['csrf']);
$router->get('/urmareste-comanda/status', [StorefrontController::class, 'trackResult']);
$router->get('/urmareste-comanda/acces/{token}', [StorefrontController::class, 'trackSigned']);
$router->get('/blog', [StorefrontController::class, 'blog']);
$router->get('/blog/{slug}', [StorefrontController::class, 'post']);
$router->get('/contact', [StorefrontController::class, 'contact']);
$router->post('/contact', [StorefrontController::class, 'contactSend'], ['csrf']);
$router->get('/despre-noi', [StorefrontController::class, 'about']);
$router->get('/sitemap.xml', [StorefrontController::class, 'sitemap']);
$router->post('/plati/callback/{provider}', [StorefrontController::class, 'paymentCallback']);
foreach (['termeni-si-conditii','confidentialitate','cookies','livrare-si-retur'] as $page) {
    $router->get('/' . $page, [StorefrontController::class, 'page']);
}
