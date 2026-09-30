<?php

namespace App\Controllers;

use App\Core\ApiAuth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\CategoryRepository;
use App\Models\ProductRepository;
use App\Services\ApiCartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\GoogleOAuthService;
use PDOException;
use RuntimeException;

final class ApiController
{
    public function health(Request $request): void
    {
        $ok = Database::available();
        Response::json(['data' => ['service' => 'SmileBaby API', 'status' => $ok ? 'ok' : 'unavailable', 'version' => '1.0.0', 'time' => date(DATE_ATOM)]], $ok ? 200 : 503);
    }

    public function documentation(Request $request): void
    {
        Response::json(['data' => ['name' => 'SmileBaby API', 'version' => 'v1', 'authentication' => 'Bearer token', 'endpoints' => [
            'GET /api/v1/health', 'GET /api/v1/config', 'GET /api/v1/categories', 'GET /api/v1/categories/{slug}', 'GET /api/v1/products', 'GET /api/v1/products/{slug}',
            'POST /api/v1/auth/register', 'POST /api/v1/auth/login', 'POST /api/v1/auth/google', 'POST /api/v1/auth/logout', 'GET /api/v1/auth/me', 'PATCH /api/v1/auth/me',
            'GET|POST /api/v1/addresses', 'PATCH|DELETE /api/v1/addresses/{id}', 'GET|POST|DELETE /api/v1/wishlist/{product_id?}',
            'GET|POST /api/v1/cart', 'PATCH|DELETE /api/v1/cart/items/{id}', 'GET|POST /api/v1/checkout',
            'GET /api/v1/orders', 'GET /api/v1/orders/{number}', 'POST /api/v1/orders/track', 'POST /api/v1/reviews', 'POST /api/v1/newsletter', 'POST /api/v1/contact',
        ]]]);
    }

    public function config(Request $request): void
    {
        Response::json(['data' => [
            'store_name' => setting('site_name', 'SmileBaby'), 'currency' => config('app.currency', 'RON'),
            'announcement_enabled' => filter_var(setting('announcement_enabled', '1'), FILTER_VALIDATE_BOOL),
            'free_shipping_threshold' => (float) setting('free_shipping_threshold', 300),
            'standard_shipping_cost' => (float) setting('standard_shipping_cost', 20),
            'return_shipping_cost' => (float) setting('return_shipping_cost', 20),
            'estimated_delivery' => setting('estimated_delivery_text', '2–3 zile lucrătoare'),
        ]]);
    }

    public function categories(Request $request): void
    {
        Response::json(['data' => array_map(fn (array $category) => $this->categoryResource($category), (new CategoryRepository())->all())]);
    }

    public function category(Request $request): void
    {
        $category = (new CategoryRepository())->findBySlug((string) $request->params['slug']);
        if (!$category) $this->error('not_found', 'Categoria nu a fost găsită.', 404);
        Response::json(['data' => $this->categoryResource($category)]);
    }

    public function products(Request $request): void
    {
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = ['q' => trim((string) ($request->query['q'] ?? '')), 'category' => trim((string) ($request->query['category'] ?? '')), 'min' => $request->query['min_price'] ?? '', 'max' => $request->query['max_price'] ?? '', 'stock' => $request->query['in_stock'] ?? '', 'featured' => $request->query['featured'] ?? '', 'customizable' => $request->query['customizable'] ?? '', 'sort' => $request->query['sort'] ?? ''];
        $result = (new ProductRepository())->list($filters, $page, $perPage);
        Response::json(['data' => array_map(fn (array $product) => $this->productResource($product), $result['items']), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total'], 'last_page' => max(1, (int) ceil($result['total'] / $perPage))]]);
    }

    public function product(Request $request): void
    {
        $product = (new ProductRepository())->findBySlug((string) $request->params['slug']);
        if (!$product) $this->error('not_found', 'Produsul nu a fost găsit.', 404);
        Response::json(['data' => $this->productResource($product, true)]);
    }

    public function register(Request $request): void
    {
        if (!RateLimiter::allow('api_register_' . sha1($request->server['REMOTE_ADDR'] ?? ''), 5, 900)) $this->error('rate_limited', 'Prea multe încercări. Reîncearcă mai târziu.', 429);
        $errors = Validator::required($request->body, ['first_name' => 'Prenumele', 'last_name' => 'Numele', 'email' => 'Emailul', 'password' => 'Parola']);
        if (!Validator::email((string) $request->input('email'))) $errors['email'] = 'Adresa de email nu este validă.';
        if (strlen((string) $request->input('password')) < 10) $errors['password'] = 'Parola trebuie să aibă minimum 10 caractere.';
        if ($errors) Response::json(['error' => ['code' => 'validation_error', 'message' => 'Verifică datele introduse.', 'fields' => $errors]], 422);
        try {
            $stmt=Database::connection()->prepare('INSERT INTO users (email,password_hash,first_name,last_name,phone) VALUES (?,?,?,?,?)');
            $stmt->execute([mb_strtolower(trim((string)$request->input('email'))),password_hash((string)$request->input('password'),PASSWORD_DEFAULT),trim((string)$request->input('first_name')),trim((string)$request->input('last_name')),trim((string)$request->input('phone'))?:null]);
            $id=(int)Database::connection()->lastInsertId(); $token=ApiAuth::issue($id,(string)$request->input('device_name','storefront'));
            $user=$this->findUser($id); Response::json(['data'=>['user'=>$user]+$token],201);
        } catch (PDOException) { $this->error('email_taken','Există deja un cont cu această adresă de email.',409); }
    }

    public function login(Request $request): void
    {
        if (!RateLimiter::allow('api_login_' . sha1($request->server['REMOTE_ADDR'] ?? ''), 8, 300)) $this->error('rate_limited','Prea multe încercări. Reîncearcă în câteva minute.',429);
        $stmt=Database::connection()->prepare('SELECT * FROM users WHERE email=? AND status="active" LIMIT 1');$stmt->execute([mb_strtolower(trim((string)$request->input('email')))]);$user=$stmt->fetch();
        if(!$user||!password_verify((string)$request->input('password'),$user['password_hash']))$this->error('invalid_credentials','Emailul sau parola nu sunt corecte.',401);
        $token=ApiAuth::issue((int)$user['id'],(string)$request->input('device_name','storefront'));
        Response::json(['data'=>['user'=>$this->userResource($user)]+$token]);
    }

    public function googleLogin(Request $request): void
    {
        if (!RateLimiter::allow('api_google_' . sha1($request->server['REMOTE_ADDR'] ?? ''), 12, 300)) $this->error('rate_limited', 'Prea multe încercări. Reîncearcă în câteva minute.', 429);
        try {
            $service = new GoogleOAuthService();
            $profile = $service->profileFromIdToken(trim((string) $request->input('id_token')));
            $user = $service->findOrCreateUser($profile);
            $token = ApiAuth::issue((int) $user['id'], (string) $request->input('device_name', 'google'));
            Response::json(['data' => ['user' => $this->userResource($user)] + $token]);
        } catch (\Throwable $error) {
            $this->error('invalid_google_token', 'Autentificarea Google nu a putut fi verificată.', 401);
        }
    }

    public function logout(Request $request): void { ApiAuth::requireUser($request); ApiAuth::revoke($request); Response::json(['data'=>['message'=>'Sesiunea a fost închisă.']]); }
    public function me(Request $request): void { Response::json(['data'=>$this->userResource(ApiAuth::requireUser($request))]); }

    public function updateMe(Request $request): void
    {
        $user=ApiAuth::requireUser($request);$first=trim((string)$request->input('first_name',$user['first_name']));$last=trim((string)$request->input('last_name',$user['last_name']));
        if($first===''||$last==='')$this->error('validation_error','Prenumele și numele sunt obligatorii.',422);
        $customerType=(string)$request->input('customer_type',$user['customer_type']??'individual')==='company'?'company':'individual';
        $companyData=['company_name'=>trim((string)$request->input('company_name',$user['company_name']??'')),'company_vat_id'=>trim((string)$request->input('company_vat_id',$user['company_vat_id']??'')),'company_registration_number'=>trim((string)$request->input('company_registration_number',$user['company_registration_number']??'')),'company_address'=>trim((string)$request->input('company_address',$user['company_address']??''))];
        if($customerType==='company'){$required=Validator::required($companyData,['company_name'=>'Denumirea firmei','company_vat_id'=>'CUI/CIF','company_registration_number'=>'Numărul de la Registrul Comerțului','company_address'=>'Adresa sediului social']);if($required)Response::json(['error'=>['code'=>'validation_error','message'=>'Verifică datele firmei.','fields'=>$required]],422);}
        Database::connection()->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,customer_type=?,company_name=?,company_vat_id=?,company_registration_number=?,company_address=? WHERE id=?')->execute([$first,$last,trim((string)$request->input('phone',$user['phone']))?:null,$customerType,$customerType==='company'?$companyData['company_name']:null,$customerType==='company'?$companyData['company_vat_id']:null,$customerType==='company'?$companyData['company_registration_number']:null,$customerType==='company'?$companyData['company_address']:null,$user['id']]);
        Response::json(['data'=>$this->findUser((int)$user['id'])]);
    }

    public function addresses(Request $request): void
    {
        $user=ApiAuth::requireUser($request);$stmt=Database::connection()->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$stmt->execute([$user['id']]);Response::json(['data'=>$stmt->fetchAll()]);
    }

    public function createAddress(Request $request): void
    {
        $user=ApiAuth::requireUser($request);$errors=Validator::required($request->body,['first_name'=>'Prenumele','last_name'=>'Numele','county'=>'Județul','city'=>'Localitatea','address'=>'Adresa']);if($errors)Response::json(['error'=>['code'=>'validation_error','message'=>'Verifică adresa.','fields'=>$errors]],422);
        $db=Database::connection();if($request->input('is_default'))$db->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
        $db->prepare('INSERT INTO user_addresses (user_id,label,first_name,last_name,phone,county,city,address,postcode,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$user['id'],trim((string)$request->input('label'))?:'Acasă',trim((string)$request->input('first_name')),trim((string)$request->input('last_name')),trim((string)$request->input('phone'))?:null,trim((string)$request->input('county')),trim((string)$request->input('city')),trim((string)$request->input('address')),trim((string)$request->input('postcode'))?:null,(int)(bool)$request->input('is_default')]);
        Response::json(['data'=>['id'=>(int)$db->lastInsertId()]],201);
    }

    public function updateAddress(Request $request): void
    {
        $user=ApiAuth::requireUser($request);$id=(int)$request->params['id'];$stmt=Database::connection()->prepare('SELECT * FROM user_addresses WHERE id=? AND user_id=?');$stmt->execute([$id,$user['id']]);$old=$stmt->fetch();if(!$old)$this->error('not_found','Adresa nu a fost găsită.',404);
        if($request->input('is_default'))Database::connection()->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
        Database::connection()->prepare('UPDATE user_addresses SET label=?,first_name=?,last_name=?,phone=?,county=?,city=?,address=?,postcode=?,is_default=? WHERE id=? AND user_id=?')->execute([trim((string)$request->input('label',$old['label'])),trim((string)$request->input('first_name',$old['first_name'])),trim((string)$request->input('last_name',$old['last_name'])),trim((string)$request->input('phone',$old['phone']))?:null,trim((string)$request->input('county',$old['county'])),trim((string)$request->input('city',$old['city'])),trim((string)$request->input('address',$old['address'])),trim((string)$request->input('postcode',$old['postcode']))?:null,(int)(bool)$request->input('is_default',$old['is_default']),$id,$user['id']]);
        Response::json(['data'=>['id'=>$id]]);
    }

    public function deleteAddress(Request $request): void { $user=ApiAuth::requireUser($request);Database::connection()->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')->execute([(int)$request->params['id'],$user['id']]);Response::json(['data'=>['deleted'=>true]]); }

    public function wishlist(Request $request): void
    {
        $user=ApiAuth::requireUser($request);$stmt=Database::connection()->prepare('SELECT p.*,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,COALESCE(p.sale_price,p.regular_price) price FROM favorites f JOIN products p ON p.id=f.product_id WHERE f.user_id=? AND p.status="active" ORDER BY f.created_at DESC');$stmt->execute([$user['id']]);Response::json(['data'=>array_map(fn($p)=>$this->productResource($p),$stmt->fetchAll())]);
    }
    public function addWishlist(Request $request): void { $user=ApiAuth::requireUser($request);$id=(int)$request->params['product_id'];Database::connection()->prepare('INSERT IGNORE INTO favorites (user_id,product_id) VALUES (?,?)')->execute([$user['id'],$id]);Response::json(['data'=>['added'=>true]],201); }
    public function removeWishlist(Request $request): void { $user=ApiAuth::requireUser($request);Database::connection()->prepare('DELETE FROM favorites WHERE user_id=? AND product_id=?')->execute([$user['id'],(int)$request->params['product_id']]);Response::json(['data'=>['deleted'=>true]]); }

    public function cart(Request $request): void { Response::json(['data'=>$this->cartFor($request)->summary()]); }
    public function addCart(Request $request): void { try{Response::json(['data'=>$this->cartFor($request)->add((int)$request->input('product_id'),(int)$request->input('quantity',1),$request->input('variant_id')?(int)$request->input('variant_id'):null)],201);}catch(RuntimeException $e){$this->error('cart_error',$e->getMessage(),422);} }
    public function updateCart(Request $request): void { Response::json(['data'=>$this->cartFor($request)->update((int)$request->params['id'],(int)$request->input('quantity'))]); }
    public function removeCart(Request $request): void { Response::json(['data'=>$this->cartFor($request)->remove((int)$request->params['id'])]); }

    public function checkoutOptions(Request $request): void
    {
        $cart=$this->cartFor($request);$summary=$cart->summary();$subtotal=(float)$summary['subtotal'];$threshold=(float)setting('free_shipping_threshold',300);$shipping=filter_var(setting('shipping_enabled','1'),FILTER_VALIDATE_BOOL)?(float)setting('standard_shipping_cost',20):0;if($threshold>0&&$subtotal>=$threshold)$shipping=0;
        $methods=[];foreach((new PaymentService())->active($subtotal) as $method){$methods[]=['key'=>$method['key'],'name'=>$method['name'],'description'=>$method['description'],'fee'=>(new PaymentService())->fee($method,$subtotal),'instructions'=>$method['instructions']];}
        Response::json(['data'=>['cart'=>$summary,'shipping'=>$shipping,'total'=>round($subtotal+$shipping,2),'payment_methods'=>$methods,'currency'=>'RON']]);
    }

    public function checkout(Request $request): void
    {
        $customerType=(string)$request->input('customer_type')==='company'?'company':'individual';$required=['first_name'=>'Prenumele','last_name'=>'Numele','email'=>'Emailul','phone'=>'Telefonul','county'=>'Județul','city'=>'Localitatea','address'=>'Adresa','payment_method'=>'Metoda de plată'];if($customerType==='company')$required+=['company_name'=>'Denumirea firmei','company_vat_id'=>'CUI/CIF','company_registration_number'=>'Numărul de la Registrul Comerțului','company_address'=>'Adresa sediului social'];$errors=Validator::required($request->body,$required);if(!Validator::email((string)$request->input('email')))$errors['email']='Adresa de email nu este validă.';if($errors)Response::json(['error'=>['code'=>'validation_error','message'=>'Verifică datele comenzii.','fields'=>$errors]],422);
        $apiUser=ApiAuth::user($request);$cart=$this->cartFor($request);$lines=$cart->sessionLines();if(!$lines)$this->error('empty_cart','Coșul este gol.',422);Session::put('cart',$lines);if($apiUser)Session::put('user_id',(int)$apiUser['id']);else Session::forget('user_id');
        try{$order=(new OrderService())->create(['first_name'=>trim((string)$request->input('first_name')),'last_name'=>trim((string)$request->input('last_name')),'email'=>trim((string)$request->input('email')),'phone'=>trim((string)$request->input('phone')),'county'=>trim((string)$request->input('county')),'city'=>trim((string)$request->input('city')),'address'=>trim((string)$request->input('address')),'postcode'=>trim((string)$request->input('postcode')),'notes'=>trim((string)$request->input('notes')),'customer_type'=>$customerType,'company_name'=>trim((string)$request->input('company_name')),'company_vat_id'=>trim((string)$request->input('company_vat_id')),'company_registration_number'=>trim((string)$request->input('company_registration_number')),'company_address'=>trim((string)$request->input('company_address'))],(string)$request->input('payment_method'));$stmt=Database::connection()->prepare('SELECT * FROM orders WHERE id=?');$stmt->execute([(int)$order['id']]);$savedOrder=$stmt->fetch()?:$order;$cart->markOrdered();Response::json(['data'=>['order'=>$this->orderResource($savedOrder),'payment'=>$order['gateway']]],201);}catch(\Throwable $e){$this->error('checkout_error',$e->getMessage(),422);}
    }

    public function orders(Request $request): void { $user=ApiAuth::requireUser($request);$stmt=Database::connection()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');$stmt->execute([$user['id']]);Response::json(['data'=>array_map(fn($o)=>$this->orderResource($o),$stmt->fetchAll())]); }
    public function order(Request $request): void { $user=ApiAuth::requireUser($request);$stmt=Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND user_id=? LIMIT 1');$stmt->execute([$request->params['number'],$user['id']]);$order=$stmt->fetch();if(!$order)$this->error('not_found','Comanda nu a fost găsită.',404);Response::json(['data'=>$this->fullOrder($order)]); }
    public function trackOrder(Request $request): void { $stmt=Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? AND email=? LIMIT 1');$stmt->execute([trim((string)$request->input('order_number')),mb_strtolower(trim((string)$request->input('email')))]);$order=$stmt->fetch();if(!$order)$this->error('not_found','Comanda nu a fost găsită.',404);Response::json(['data'=>$this->fullOrder($order)]); }

    public function review(Request $request): void
    {
        $rating=(int)$request->input('rating');$errors=Validator::required($request->body,['product_id'=>'Produsul','author_name'=>'Numele','email'=>'Emailul','body'=>'Recenzia']);if($rating<1||$rating>5)$errors['rating']='Ratingul trebuie să fie între 1 și 5.';if(!Validator::email((string)$request->input('email')))$errors['email']='Adresa de email nu este validă.';if($errors)Response::json(['error'=>['code'=>'validation_error','message'=>'Verifică recenzia.','fields'=>$errors]],422);$user=ApiAuth::user($request);
        Database::connection()->prepare('INSERT INTO reviews (product_id,user_id,author_name,email,rating,title,body,status) VALUES (?,?,?,?,?,?,?,"approved")')->execute([(int)$request->input('product_id'),$user['id']??null,trim((string)$request->input('author_name')),mb_strtolower(trim((string)$request->input('email'))),$rating,trim((string)$request->input('title'))?:null,trim((string)$request->input('body'))]);Response::json(['data'=>['message'=>'Mulțumim pentru recenzia acordată! Recenzia a fost publicată.']],201);
    }

    public function newsletter(Request $request): void { $email=mb_strtolower(trim((string)$request->input('email')));if(!Validator::email($email)||!$request->input('consent'))$this->error('validation_error','Adresa validă și acordul sunt obligatorii.',422);Database::connection()->prepare('INSERT INTO newsletter_subscribers (email,consent,status,token) VALUES (?,1,"active",?) ON DUPLICATE KEY UPDATE consent=1,status="active",unsubscribed_at=NULL')->execute([$email,hash('sha256',$email.random_bytes(16))]);Response::json(['data'=>['message'=>'Abonarea a fost înregistrată.']],201); }
    public function contact(Request $request): void { $errors=Validator::required($request->body,['name'=>'Numele','email'=>'Emailul','message'=>'Mesajul']);if(!Validator::email((string)$request->input('email')))$errors['email']='Adresa de email nu este validă.';if($errors)Response::json(['error'=>['code'=>'validation_error','message'=>'Verifică mesajul.','fields'=>$errors]],422);Database::connection()->prepare('INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)')->execute([trim((string)$request->input('name')),mb_strtolower(trim((string)$request->input('email'))),trim((string)$request->input('phone'))?:null,trim((string)$request->input('subject'))?:null,trim((string)$request->input('message'))]);Response::json(['data'=>['message'=>'Mesajul a fost trimis.']],201); }

    private function cartFor(Request $request): ApiCartService { $user=ApiAuth::user($request);$token=trim((string)($request->server['HTTP_X_CART_TOKEN']??$request->input('cart_token','')));return new ApiCartService($token,$user?(int)$user['id']:null); }
    private function findUser(int $id): array { $stmt=Database::connection()->prepare('SELECT id,email,first_name,last_name,phone,customer_type,company_name,company_vat_id,company_registration_number,company_address,role,status,created_at FROM users WHERE id=?');$stmt->execute([$id]);return $this->userResource($stmt->fetch()); }
    private function userResource(array $user): array { return ['id'=>(int)$user['id'],'email'=>$user['email'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'phone'=>$user['phone']??null,'customer_type'=>$user['customer_type']??'individual','company_name'=>$user['company_name']??null,'company_vat_id'=>$user['company_vat_id']??null,'company_registration_number'=>$user['company_registration_number']??null,'company_address'=>$user['company_address']??null,'avatar_url'=>$user['avatar_url']??null,'auth_provider'=>$user['auth_provider']??'password','role'=>$user['role'],'status'=>$user['status']]; }
    private function categoryResource(array $category): array
    {
        $image = $category['image_path'] ?? null;
        $normalizedImage = str_replace('\\', '/', (string) $image);
        if ($image && str_starts_with($normalizedImage, 'assets/images/categories/')) {
            $image = 'api/media/categories/' . basename($normalizedImage);
        }

        return [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['short_description'] ?? null,
            'image_url' => $this->mediaUrl($image),
            'product_count' => (int) ($category['product_count'] ?? 0),
        ];
    }
    private function productResource(array $product,bool $full=false): array { $now=time();$saleActive=!empty($product['sale_price'])&&(empty($product['sale_start'])||strtotime((string)$product['sale_start'])<=$now)&&(empty($product['sale_end'])||strtotime((string)$product['sale_end'])>=$now);$labels=[];if(!empty($product['badge_text']))$labels[]=['key'=>'custom','label'=>$product['badge_text']];if(!empty($product['is_customizable']))$labels[]=['key'=>'customizable','label'=>'Personalizabil'];if($saleActive)$labels[]=['key'=>'sale','label'=>'Reducere'];$data=['id'=>(int)$product['id'],'name'=>$product['name'],'slug'=>$product['slug'],'sku'=>$product['sku']??null,'short_description'=>$product['short_description']??null,'price'=>(float)($saleActive?$product['sale_price']:$product['regular_price']),'regular_price'=>(float)$product['regular_price'],'sale_price'=>$saleActive?(float)$product['sale_price']:null,'stock_status'=>empty($product['manage_stock'])?'in_stock':$product['stock_status'],'featured'=>(bool)($product['featured']??false),'is_customizable'=>(bool)($product['is_customizable']??false),'labels'=>$labels,'image_url'=>$this->mediaUrl($product['image_path']??null),'rating'=>isset($product['rating'])?(float)$product['rating']:null,'review_count'=>(int)($product['review_count']??0)];if($full){$data+=['description'=>$product['description']??null,'images'=>array_map(fn($i)=>['id'=>(int)$i['id'],'url'=>$this->mediaUrl($i['image_path']),'alt'=>$i['alt_text']],$product['images']??[]),'categories'=>array_map(fn($c)=>$this->categoryResource($c),$product['categories']??[]),'variants'=>array_map(fn($v)=>['id'=>(int)$v['id'],'name'=>$v['variant_name']?:$v['label'],'sku'=>$v['sku'],'price'=>(float)($v['sale_price']?:$v['regular_price']?:$product['price']),'stock_status'=>$v['stock_quantity']===null?'in_stock':$v['stock_status'],'image_url'=>$this->mediaUrl($v['image_path'])],$product['variants']??[]),'reviews'=>array_map(fn($r)=>['id'=>(int)$r['id'],'author'=>$r['author_name'],'rating'=>(int)$r['rating'],'title'=>$r['title'],'body'=>$r['body'],'created_at'=>$r['created_at'],'store_reply'=>$r['admin_reply']??null,'replied_at'=>$r['replied_at']??null],$product['reviews']??[])];}return $data; }
    private function orderResource(array $order): array { return ['id'=>(int)$order['id'],'number'=>$order['order_number'],'status'=>$order['status'],'payment_status'=>$order['payment_status'],'payment_method'=>$order['payment_method'],'subtotal'=>(float)($order['subtotal']??0),'shipping'=>(float)($order['shipping_total']??0),'payment_fee'=>(float)($order['payment_fee']??0),'total'=>(float)($order['total']??0),'currency'=>'RON','created_at'=>$order['created_at']??date('Y-m-d H:i:s'),'tracking'=>['courier'=>$order['courier']??null,'awb'=>$order['awb']??null,'url'=>$order['tracking_url']??null]]; }
    private function fullOrder(array $order): array { $stmt=Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=?');$stmt->execute([$order['id']]);$items=$stmt->fetchAll();$stmt=Database::connection()->prepare('SELECT new_status status,message,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at');$stmt->execute([$order['id']]);return $this->orderResource($order)+['customer'=>['first_name'=>$order['first_name'],'last_name'=>$order['last_name'],'email'=>$order['email'],'phone'=>$order['phone']],'shipping_address'=>['address'=>$order['shipping_address'],'city'=>$order['shipping_city'],'county'=>$order['shipping_county'],'postcode'=>$order['shipping_postcode']],'items'=>$items,'history'=>$stmt->fetchAll()]; }
    private function mediaUrl(?string $path): ?string { if(!$path)return null;if(preg_match('#^https?://#i',$path))return $path;return rtrim((string)config('app.url'),'\/').'/'.ltrim(str_replace('\\','/',$path),'/'); }
    private function error(string $code,string $message,int $status): never { Response::json(['error'=>['code'=>$code,'message'=>$message]],$status); }
}
