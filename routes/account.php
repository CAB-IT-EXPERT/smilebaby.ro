<?php

use App\Controllers\AccountController;

$router->get('/autentificare', [AccountController::class, 'loginForm']);
$router->post('/autentificare', [AccountController::class, 'login'], ['csrf']);
$router->get('/autentificare/google', [AccountController::class, 'googleRedirect']);
$router->get('/autentificare/google/callback', [AccountController::class, 'googleCallback']);
$router->post('/logout', [AccountController::class, 'logout'], ['csrf']);
$router->get('/inregistrare', [AccountController::class, 'registerForm']);
$router->post('/inregistrare', [AccountController::class, 'register'], ['csrf']);
$router->get('/parola-uitata', [AccountController::class, 'forgotForm']);
$router->post('/parola-uitata', [AccountController::class, 'forgot'], ['csrf']);
$router->get('/resetare-parola/{token}', [AccountController::class, 'resetForm']);
$router->post('/resetare-parola/{token}', [AccountController::class, 'reset'], ['csrf']);
$router->get('/cont', [AccountController::class, 'dashboard'], ['auth']);
$router->get('/cont/profil', [AccountController::class, 'profile'], ['auth']);
$router->post('/cont/profil', [AccountController::class, 'updateProfile'], ['auth','csrf']);
$router->get('/cont/adrese', [AccountController::class, 'addresses'], ['auth']);
$router->post('/cont/adrese', [AccountController::class, 'saveAddress'], ['auth','csrf']);
$router->get('/cont/comenzi', [AccountController::class, 'orders'], ['auth']);
$router->get('/cont/comenzi/{number}', [AccountController::class, 'order'], ['auth']);
$router->post('/cont/sterge', [AccountController::class, 'deleteAccount'], ['auth','csrf']);
