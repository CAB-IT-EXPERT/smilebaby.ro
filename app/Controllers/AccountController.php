<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\MailService;
use App\Services\GoogleOAuthService;

final class AccountController
{
    public function loginForm(Request $request): void { View::render('account/login', ['googleEnabled' => (new GoogleOAuthService())->configured(), 'meta' => ['title' => 'Autentificare — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function login(Request $request): void
    {
        if (!RateLimiter::allow('login_' . sha1($request->server['REMOTE_ADDR'] ?? ''), 8, 300)) { Session::flash('error', 'Prea multe încercări. Reîncearcă peste câteva minute.'); Response::redirect('/autentificare'); }
        if (Auth::attempt((string) $request->input('email'), (string) $request->input('password'))) {
            $this->syncWishlist();
            Response::redirect(Auth::isAdmin() ? '/admin' : (string) ($request->input('next') ?: '/cont'));
        }
        Session::flash('error', 'Emailul sau parola nu sunt corecte.'); Response::redirect('/autentificare');
    }
    public function logout(Request $request): void { Auth::logout(); Response::redirect('/'); }

    public function registerForm(Request $request): void { View::render('account/register', ['googleEnabled' => (new GoogleOAuthService())->configured(), 'meta' => ['title' => 'Creează cont — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function register(Request $request): void
    {
        $errors = Validator::required($request->body, ['first_name' => 'Prenumele', 'last_name' => 'Numele', 'email' => 'Emailul', 'password' => 'Parola']);
        if (!Validator::email((string) $request->input('email'))) $errors['email'] = 'Adresa de email nu este validă.';
        if (strlen((string) $request->input('password')) < 10) $errors['password'] = 'Parola trebuie să aibă cel puțin 10 caractere.';
        if ($request->input('password') !== $request->input('password_confirmation')) $errors['password_confirmation'] = 'Parolele nu coincid.';
        if ($errors) { Session::put('_old', $request->body); Session::flash('errors', $errors); Response::redirect('/inregistrare'); }
        try {
            $stmt = Database::connection()->prepare('INSERT INTO users (email,password_hash,first_name,last_name,phone) VALUES (?,?,?,?,?)');
            $stmt->execute([mb_strtolower(trim((string) $request->input('email'))), password_hash((string) $request->input('password'), PASSWORD_DEFAULT), trim((string) $request->input('first_name')), trim((string) $request->input('last_name')), trim((string) $request->input('phone')) ?: null]);
            Auth::attempt((string) $request->input('email'), (string) $request->input('password')); $this->syncWishlist();
            Session::flash('success', 'Bine ai venit la SmileBaby!'); Response::redirect('/cont');
        } catch (\PDOException) { Session::flash('error', 'Există deja un cont cu această adresă de email.'); Response::redirect('/inregistrare'); }
    }

    public function googleRedirect(Request $request): void
    {
        try {
            $state = bin2hex(random_bytes(32));
            $next = (string) ($request->query['next'] ?? '/cont');
            if (!str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/cont';
            Session::put('google_oauth_state', $state);
            Session::put('google_oauth_next', $next);
            Response::redirect((new GoogleOAuthService())->authorizationUrl($state));
        } catch (\Throwable) {
            Session::flash('error', 'Autentificarea Google nu este disponibilă momentan.');
            Response::redirect('/autentificare');
        }
    }

    public function googleCallback(Request $request): void
    {
        $expectedState = (string) Session::get('google_oauth_state', '');
        $receivedState = (string) ($request->query['state'] ?? '');
        Session::forget('google_oauth_state');
        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState) || isset($request->query['error'])) {
            Session::flash('error', 'Autentificarea Google a fost anulată sau nu a putut fi verificată.');
            Response::redirect('/autentificare');
        }
        try {
            $service = new GoogleOAuthService();
            $profile = $service->profileFromCode((string) ($request->query['code'] ?? ''));
            $user = $service->findOrCreateUser($profile);
            Auth::loginById((int) $user['id']);
            $this->syncWishlist();
            $next = (string) Session::get('google_oauth_next', '/cont');
            Session::forget('google_oauth_next');
            Session::flash('success', 'Te-ai autentificat cu Google.');
            Response::redirect(($user['role'] ?? '') === 'admin' ? '/admin' : $next);
        } catch (\Throwable) {
            Session::forget('google_oauth_next');
            Session::flash('error', 'Contul Google nu a putut fi autentificat. Te rugăm să încerci din nou.');
            Response::redirect('/autentificare');
        }
    }

    public function forgotForm(Request $request): void { View::render('account/forgot', ['meta' => ['title' => 'Parolă uitată — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function forgot(Request $request): void
    {
        if (RateLimiter::allow('forgot_' . sha1($request->server['REMOTE_ADDR'] ?? ''), 4, 900)) {
            $stmt = Database::connection()->prepare('SELECT id,email,first_name FROM users WHERE email=? LIMIT 1'); $stmt->execute([mb_strtolower(trim((string) $request->input('email')))]); $user = $stmt->fetch();
            if ($user) { $token = bin2hex(random_bytes(32)); Database::connection()->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))')->execute([$user['id'], hash('sha256', $token)]); (new MailService())->send($user['email'], 'Resetarea parolei SmileBaby', 'emails/reset', ['resetUrl' => config('app.url') . '/resetare-parola/' . $token, 'customer' => $user]); }
        }
        Session::flash('success', 'Dacă adresa există, vei primi instrucțiunile de resetare.'); Response::redirect('/parola-uitata');
    }

    public function resetForm(Request $request): void { View::render('account/reset', ['token' => $request->params['token'], 'meta' => ['title' => 'Alege o parolă nouă — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function reset(Request $request): void
    {
        $token = (string) $request->params['token']; $password = (string) $request->input('password');
        if (strlen($password) < 10 || $password !== $request->input('password_confirmation')) { Session::flash('error', 'Parola trebuie să aibă minimum 10 caractere, iar confirmarea să coincidă.'); Response::redirect('/resetare-parola/' . $token); }
        $stmt = Database::connection()->prepare('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1'); $stmt->execute([hash('sha256', $token)]); $reset = $stmt->fetch();
        if (!$reset) { Session::flash('error', 'Linkul a expirat sau a fost deja folosit.'); Response::redirect('/parola-uitata'); }
        Database::transaction(function ($db) use ($reset, $password) { $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]); $db->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$reset['id']]); });
        Session::flash('success', 'Parola a fost schimbată. Te poți autentifica.'); Response::redirect('/autentificare');
    }

    public function dashboard(Request $request): void
    {
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5'); $stmt->execute([Auth::user()['id']]);
        View::render('account/dashboard', ['orders' => $stmt->fetchAll(), 'meta' => ['title' => 'Contul meu — SmileBaby', 'robots' => 'noindex,nofollow']]);
    }
    public function profile(Request $request): void { View::render('account/profile', ['meta' => ['title' => 'Datele mele — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function updateProfile(Request $request): void
    {
        $customerType = (string) $request->input('customer_type') === 'company' ? 'company' : 'individual';
        $fields = ['first_name' => 'Prenumele', 'last_name' => 'Numele'];
        if ($customerType === 'company') $fields += ['company_name' => 'Denumirea firmei', 'company_vat_id' => 'CUI/CIF', 'company_registration_number' => 'Numărul de la Registrul Comerțului', 'company_address' => 'Adresa sediului social'];
        $errors = Validator::required($request->body, $fields);
        if ($errors) { Session::put('_old', $request->body); Session::flash('errors', $errors); Response::redirect('/cont/profil'); }
        Database::connection()->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,customer_type=?,company_name=?,company_vat_id=?,company_registration_number=?,company_address=? WHERE id=?')->execute([
            trim((string) $request->input('first_name')), trim((string) $request->input('last_name')), trim((string) $request->input('phone')) ?: null, $customerType,
            $customerType === 'company' ? trim((string) $request->input('company_name')) : null,
            $customerType === 'company' ? trim((string) $request->input('company_vat_id')) : null,
            $customerType === 'company' ? trim((string) $request->input('company_registration_number')) : null,
            $customerType === 'company' ? trim((string) $request->input('company_address')) : null,
            Auth::user()['id'],
        ]);
        Session::forget('_old');
        Session::flash('success', 'Datele de client și facturare au fost salvate.');
        Response::redirect('/cont/profil');
    }
    public function addresses(Request $request): void { $stmt = Database::connection()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC'); $stmt->execute([Auth::user()['id']]); View::render('account/addresses', ['addresses' => $stmt->fetchAll(), 'meta' => ['title' => 'Adrese — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function saveAddress(Request $request): void { Database::connection()->prepare('INSERT INTO user_addresses (user_id,label,first_name,last_name,phone,county,city,address,postcode,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([Auth::user()['id'], trim($request->input('label')) ?: 'Acasă', trim($request->input('first_name')), trim($request->input('last_name')), trim($request->input('phone')), trim($request->input('county')), trim($request->input('city')), trim($request->input('address')), trim($request->input('postcode')), (int) (bool) $request->input('is_default')]); Session::flash('success', 'Adresa a fost salvată.'); Response::redirect('/cont/adrese'); }
    public function orders(Request $request): void { $stmt = Database::connection()->prepare('SELECT o.*,(SELECT oi.image_path FROM order_items oi WHERE oi.order_id=o.id ORDER BY oi.id LIMIT 1) preview_image,(SELECT oi.product_name FROM order_items oi WHERE oi.order_id=o.id ORDER BY oi.id LIMIT 1) preview_product,(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.order_id=o.id) item_count FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC'); $stmt->execute([Auth::user()['id']]); View::render('account/orders', ['orders' => $stmt->fetchAll(), 'meta' => ['title' => 'Comenzile mele — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function order(Request $request): void { $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND user_id=? LIMIT 1'); $stmt->execute([$request->params['number'], Auth::user()['id']]); $order = $stmt->fetch(); if (!$order) { http_response_code(404); View::render('errors/404'); return; } $items = Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=?'); $items->execute([$order['id']]); $history = Database::connection()->prepare('SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at'); $history->execute([$order['id']]); View::render('account/order', ['order' => $order, 'items' => $items->fetchAll(), 'history' => $history->fetchAll(), 'meta' => ['title' => 'Comanda ' . $order['order_number'] . ' — SmileBaby', 'robots' => 'noindex,nofollow']]); }
    public function deleteAccount(Request $request): void { $id = Auth::user()['id']; Auth::logout(); Database::connection()->prepare('UPDATE users SET email=CONCAT("deleted-",id,"@example.invalid"),password_hash="",first_name="Cont",last_name="șters",phone=NULL,status="disabled" WHERE id=?')->execute([$id]); Session::flash('success', 'Contul tău a fost dezactivat și datele de profil au fost anonimizate.'); Response::redirect('/'); }

    private function syncWishlist(): void
    {
        if (!Auth::check() || !Database::available()) return;
        $userId = Auth::user()['id'];
        foreach (Session::get('wishlist', []) as $id) Database::connection()->prepare('INSERT IGNORE INTO favorites (user_id,product_id) VALUES (?,?)')->execute([$userId, $id]);
        $stmt = Database::connection()->prepare('SELECT product_id FROM favorites WHERE user_id=?'); $stmt->execute([$userId]); Session::put('wishlist', array_map('intval', array_column($stmt->fetchAll(), 'product_id')));
    }
}
