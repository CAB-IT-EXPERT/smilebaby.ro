<?php

use App\Controllers\ApiController;

$router->get('/api/v1', [ApiController::class, 'documentation']);
$router->get('/api/v1/health', [ApiController::class, 'health']);
$router->get('/api/v1/config', [ApiController::class, 'config']);
$router->get('/api/v1/categories', [ApiController::class, 'categories']);
$router->get('/api/v1/categories/{slug}', [ApiController::class, 'category']);
$router->get('/api/v1/products', [ApiController::class, 'products']);
$router->get('/api/v1/products/{slug}', [ApiController::class, 'product']);

$router->post('/api/v1/auth/register', [ApiController::class, 'register']);
$router->post('/api/v1/auth/login', [ApiController::class, 'login']);
$router->post('/api/v1/auth/google', [ApiController::class, 'googleLogin']);
$router->post('/api/v1/auth/logout', [ApiController::class, 'logout']);
$router->get('/api/v1/auth/me', [ApiController::class, 'me']);
$router->put('/api/v1/auth/me', [ApiController::class, 'updateMe']);

$router->get('/api/v1/addresses', [ApiController::class, 'addresses']);
$router->post('/api/v1/addresses', [ApiController::class, 'createAddress']);
$router->put('/api/v1/addresses/{id}', [ApiController::class, 'updateAddress']);
$router->delete('/api/v1/addresses/{id}', [ApiController::class, 'deleteAddress']);

$router->get('/api/v1/wishlist', [ApiController::class, 'wishlist']);
$router->post('/api/v1/wishlist/{product_id}', [ApiController::class, 'addWishlist']);
$router->delete('/api/v1/wishlist/{product_id}', [ApiController::class, 'removeWishlist']);

$router->get('/api/v1/cart', [ApiController::class, 'cart']);
$router->post('/api/v1/cart/items', [ApiController::class, 'addCart']);
$router->put('/api/v1/cart/items/{id}', [ApiController::class, 'updateCart']);
$router->delete('/api/v1/cart/items/{id}', [ApiController::class, 'removeCart']);
$router->get('/api/v1/checkout', [ApiController::class, 'checkoutOptions']);
$router->post('/api/v1/checkout', [ApiController::class, 'checkout']);

$router->get('/api/v1/orders', [ApiController::class, 'orders']);
$router->post('/api/v1/orders/track', [ApiController::class, 'trackOrder']);
$router->get('/api/v1/orders/{number}', [ApiController::class, 'order']);
$router->post('/api/v1/reviews', [ApiController::class, 'review']);
$router->post('/api/v1/newsletter', [ApiController::class, 'newsletter']);
$router->post('/api/v1/contact', [ApiController::class, 'contact']);
