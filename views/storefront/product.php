<?php
$images=$product['images'] ?: [['image_path'=>$product['image_path']??null,'alt_text'=>$product['name']]];
$inStock=empty($product['manage_stock'])||($product['stock_status']??'in_stock')!=='out_of_stock';
if(!empty($product['variants']))$inStock=(bool)array_filter($product['variants'],fn($v)=>$v['stock_quantity']===null||$v['stock_status']!=='out_of_stock');
$baseRegularPrice=(float)$product['regular_price'];
$baseCurrentPrice=(float)($product['sale_price']?:$product['regular_price']);
$customizationPrice=max(0,(float)($product['customization_price']??0));
?>
<nav class="breadcrumb shell"><a href="/">Acasă</a><span>›</span><a href="/magazin">Magazin</a><?php if(!empty($product['categories'][0])):?><span>›</span><a href="/categorie/<?=e($product['categories'][0]['slug'])?>"><?=e($product['categories'][0]['name'])?></a><?php endif?><span>›</span><?=e($product['name'])?></nav>
<article class="product-page shell" data-product-price-configurator data-base-price="<?=e((string)$baseCurrentPrice)?>" data-base-regular-price="<?=e((string)$baseRegularPrice)?>" data-customization-price="<?=e((string)$customizationPrice)?>">
    <div class="gallery" data-gallery>
        <div class="gallery-thumbs" aria-label="Fotografiile produsului"><?php foreach($images as $i=>$image):?><button type="button" class="<?= $i===0?'active':'' ?>" data-gallery-thumb="<?=e(upload_url($image['image_path']))?>" data-gallery-alt="<?=e($image['alt_text']?:$product['name'])?>" aria-label="Arată imaginea <?= $i+1 ?>" aria-current="<?= $i===0?'true':'false' ?>"><img src="<?=e(upload_url($image['image_path']))?>" alt=""></button><?php endforeach?></div>
        <div class="gallery-stage" data-gallery-stage>
            <button class="gallery-main" type="button" data-gallery-main aria-label="Mărește imaginea"><img src="<?=e(upload_url($images[0]['image_path']))?>" alt="<?=e($images[0]['alt_text']?:$product['name'])?>" draggable="false"></button>
            <?php if(count($images)>1):?>
                <button class="gallery-arrow gallery-prev" type="button" data-gallery-prev aria-label="Imaginea anterioară"><span aria-hidden="true">‹</span></button>
                <button class="gallery-arrow gallery-next" type="button" data-gallery-next aria-label="Imaginea următoare"><span aria-hidden="true">›</span></button>
                <span class="gallery-counter" aria-live="polite"><b data-gallery-current>1</b> / <?=count($images)?></span>
                <span class="gallery-swipe-hint" aria-hidden="true">Trage pentru a naviga</span>
            <?php endif?>
        </div>
    </div>
    <div class="product-summary"><?php if(!empty($product['categories'][0])):?><a class="eyebrow" href="/categorie/<?=e($product['categories'][0]['slug'])?>"><?=e($product['categories'][0]['name'])?></a><?php endif?><h1><?=e($product['name'])?></h1><?php if((int)($product['review_count']??0)>0):?><div class="rating"><span><?=str_repeat('★',(int)round($product['rating'])).str_repeat('☆',5-(int)round($product['rating']))?></span><a href="#recenzii"><?= (int)$product['review_count'] ?> <?= (int)$product['review_count']===1?'recenzie':'recenzii' ?></a></div><?php endif?><div class="product-page-price" aria-live="polite"><del data-product-price-regular <?=$baseRegularPrice>$baseCurrentPrice?'':'hidden'?>><?=money($baseRegularPrice)?></del><strong data-product-price-current><?=money($baseCurrentPrice)?></strong></div><div class="product-short"><?= $product['short_description'] ?: '<p>Un produs ales cu grijă pentru cele mai frumoase începuturi.</p>' ?></div>
        <?php if($product['variants']):?><label>Alege varianta<select form="add-to-cart" name="variant_id" required data-product-variant><option value="">Selectează</option><?php foreach($product['variants'] as $variant):$variantAvailable=$variant['stock_quantity']===null||$variant['stock_status']!=='out_of_stock';$variantRegular=(float)($variant['regular_price']?:$baseRegularPrice);$variantCurrent=(float)($variant['sale_price']?:$variant['regular_price']?:$baseCurrentPrice);?><option value="<?=$variant['id']?>" data-price="<?=e((string)$variantCurrent)?>" data-regular-price="<?=e((string)$variantRegular)?>" <?=!$variantAvailable?'disabled':''?>><?=e($variant['variant_name']?:$variant['sku'])?> — <?=money($variantCurrent)?><?=!$variantAvailable?' · indisponibil':''?></option><?php endforeach?></select></label><?php endif?>
        <?php if(!empty($product['is_customizable']) && !empty($product['customization_fields'])): ?>
            <section class="storefront-customization" data-storefront-customization>
                <label class="storefront-customization-toggle">
                    <input type="checkbox" name="personalization_enabled" value="1" form="add-to-cart" autocomplete="off" data-personalization-toggle>
                    <span><i>✦</i><b><small><?= e($product['badge_text'] ?: 'PERSONALIZARE') ?></small><strong>Vreau să personalizez produsul</strong><em>Adaugă nume, dată sau detaliile tale</em></b><u><?= (float)($product['customization_price'] ?? 0) > 0 ? '+' . money($product['customization_price']) : 'GRATUIT' ?></u></span>
                </label>
                <div class="storefront-customization-fields" data-personalization-fields hidden>
                    <header><strong>Detaliile personalizării</strong><small>Completează câmpurile marcate cu *</small></header>
                    <?php foreach($product['customization_fields'] as $field): ?>
                        <label><?= e($field['label']) ?><?= !empty($field['is_required']) ? ' *' : '' ?><input form="add-to-cart" type="<?= $field['field_type'] === 'date' ? 'date' : 'text' ?>" name="customization[<?= (int)$field['id'] ?>]" value="" placeholder="<?= e($field['placeholder'] ?? '') ?>" maxlength="250" data-personalization-input data-personalization-required="<?= !empty($field['is_required']) ? '1' : '0' ?>" disabled></label>
                    <?php endforeach ?>
                    <p><span>✓</span> Verificăm toate detaliile înainte ca produsul să intre în coș.</p>
                </div>
            </section>
        <?php endif ?>
        <?php if(!empty($product['addons'])): ?>
            <section class="storefront-addons" data-storefront-addons>
                <header class="storefront-addons-heading">
                    <span aria-hidden="true">＋</span>
                    <div><small>OPȚIUNI PENTRU SET</small><strong>Completează setul cu</strong><p>Alege unul sau mai multe produse și cantitatea dorită.</p></div>
                </header>
                <div class="storefront-addon-picker" data-storefront-addon-picker>
                    <button class="storefront-addon-trigger" type="button" aria-expanded="false" data-storefront-addon-open>
                        <span><small>PRODUSE DISPONIBILE</small><strong data-storefront-addon-summary>Alege produsele suplimentare</strong></span>
                        <b data-storefront-addon-count>0</b><i aria-hidden="true">⌄</i>
                    </button>
                    <div class="storefront-addon-dropdown" data-storefront-addon-dropdown hidden>
                        <label class="storefront-addon-search"><?=icon('search')?><input type="search" placeholder="Caută tolerant la greșeli după nume, cod sau categorie…" autocomplete="off" data-storefront-addon-search><kbd>Esc</kbd></label>
                        <div class="storefront-addon-options" data-storefront-addon-options>
                            <?php foreach($product['addons'] as $addon): $addonId=(int)$addon['product_id']; $searchTerms=trim($addon['name'].' '.($addon['slug']??'').' '.($addon['sku']??'').' '.($addon['categories']??'').' '.($addon['price']??'')); ?>
                                <article class="storefront-addon-option" data-storefront-addon-option data-addon-id="<?=$addonId?>" data-addon-price="<?=e((string)$addon['price'])?>" data-addon-catalog-price="<?=e((string)$addon['catalog_price'])?>" data-addon-name="<?=e($addon['name'])?>" data-addon-search-terms="<?=e($searchTerms)?>">
                                    <label class="storefront-addon-check" aria-label="Adaugă <?=e($addon['name'])?>"><input form="add-to-cart" type="checkbox" name="addons[<?=$addonId?>][selected]" value="1" data-storefront-addon-check><i></i></label>
                                    <button class="storefront-addon-image" type="button" data-storefront-addon-image data-image="<?=e(upload_url($addon['image_path']))?>" data-alt="<?=e($addon['name'])?>" aria-label="Mărește imaginea pentru <?=e($addon['name'])?>"><img src="<?=e(upload_url($addon['image_path']))?>" alt=""></button>
                                    <div class="storefront-addon-copy"><strong><?=e($addon['name'])?></strong><small><?=e(($addon['categories']?:'Produs SmileBaby').($addon['sku']?' · '.$addon['sku']:''))?></small><span><?php if((float)$addon['catalog_price']!==(float)$addon['price']):?><del><?=money($addon['catalog_price'])?></del><?php endif?><b><?=money($addon['price'])?></b></span></div>
                                    <div class="storefront-addon-quantity" aria-label="Cantitate <?=e($addon['name'])?>"><button type="button" data-storefront-addon-minus aria-label="Scade cantitatea">−</button><input form="add-to-cart" type="number" name="addons[<?=$addonId?>][quantity]" value="1" min="1" max="99" inputmode="numeric" aria-label="Cantitate" data-storefront-addon-quantity disabled><button type="button" data-storefront-addon-plus aria-label="Crește cantitatea">＋</button></div>
                                </article>
                            <?php endforeach ?>
                            <p class="storefront-addon-search-empty" data-storefront-addon-empty hidden>Nu am găsit produse. Încearcă o denumire apropiată.</p>
                        </div>
                        <footer><span><b data-storefront-addon-count>0</b> produse alese</span><button type="button" data-storefront-addon-done>Gata</button></footer>
                    </div>
                </div>
                <div class="storefront-addon-selection" data-storefront-addon-selection hidden>
                    <div data-storefront-addon-chips></div>
                    <p><span>Produse suplimentare</span><strong data-storefront-addon-total><?=money(0)?></strong></p>
                </div>
                <dialog class="storefront-addon-image-dialog" data-storefront-addon-image-dialog><button type="button" data-storefront-addon-image-close aria-label="Închide">×</button><img src="" alt="" data-storefront-addon-image-large><div><strong data-storefront-addon-image-title></strong><small>Previzualizare produs suplimentar</small></div></dialog>
            </section>
        <?php endif ?>
        <div class="stock <?= $inStock?'in-stock':'out-stock' ?>"><?= $inStock?'În stoc':'INDISPONIBIL' ?><?php if($inStock && $product['manage_stock'] && $product['stock_quantity']<=($product['low_stock_threshold']??3)):?> · Doar <?= (int)$product['stock_quantity'] ?> rămase<?php endif?></div>
        <div class="product-actions"><form id="add-to-cart" action="/cos/adauga" method="post" data-cart-form><?=csrf_field()?><input type="hidden" name="product_id" value="<?=$product['id']?>"><div class="quantity"><button type="button" data-qty-minus><?=icon('minus')?></button><input type="number" name="quantity" value="1" min="1" max="99" aria-label="Cantitate"><button type="button" data-qty-plus><?=icon('plus')?></button></div><button class="button add-button" type="submit" <?=!$inStock?'disabled':''?>><?= $inStock?'ADAUGĂ ÎN COȘ':'INDISPONIBIL' ?></button></form><form action="/favorite" method="post" data-wishlist-form><?=csrf_field()?><input type="hidden" name="product_id" value="<?=$product['id']?>"><button class="wishlist-large" type="submit" aria-label="Adaugă la favorite"><?=icon('heart')?></button></form></div>
        <div class="product-benefits"><span><?=icon('truck')?>Livrare în <?=e(setting('estimated_delivery_text','2–3 zile'))?></span><span><?=icon('return')?>Retur în 14 zile</span><span><?=icon('card')?>Plată securizată</span></div>
    </div>
</article>
<section class="product-details shell">
    <div class="tabs" role="tablist"><button class="active" data-tab="descriere">Descriere</button><button data-tab="specificatii">Specificații</button><button data-tab="recenzii">Recenzii</button></div>
    <div class="tab-panel active" id="descriere"><?= $product['description'] ?: '<p>Detaliile produsului vor fi completate în curând.</p>' ?></div>
    <div class="tab-panel" id="specificatii"><dl><dt>Cod produs</dt><dd><?=e($product['sku']?:'—')?></dd><dt>Brand</dt><dd><?=e($product['brand']?:'SmileBaby')?></dd><dt>Disponibilitate</dt><dd><?=$inStock?'În stoc':'Indisponibil'?></dd></dl></div>
    <div class="tab-panel" id="recenzii">
        <header class="product-reviews-heading">
            <div class="product-reviews-heading-copy">
                <span>PĂRERILE CLIENȚILOR</span>
                <h2>Experiențe împărtășite cu drag</h2>
                <p>Descoperă părerile celor care au ales deja acest produs sau povestește-ne experiența ta.</p>
            </div>
            <div class="product-reviews-summary" aria-label="Ratingul produsului">
                <strong data-review-average><?= number_format((float)($product['rating'] ?? 0), 1, ',', '') ?></strong>
                <div>
                    <span class="product-reviews-summary-stars" aria-hidden="true" data-review-summary-stars><?= str_repeat('★',(int)round($product['rating'] ?? 0)).str_repeat('☆',5-(int)round($product['rating'] ?? 0)) ?></span>
                    <small data-review-summary-count><?= (int)($product['review_count'] ?? 0) ?> <?= (int)($product['review_count'] ?? 0) === 1 ? 'recenzie' : 'recenzii' ?></small>
                </div>
            </div>
            <button class="product-review-open" type="button" data-review-open>
                <span aria-hidden="true">✦</span>
                <b>Scrie o recenzie</b>
                <small>Durează mai puțin de un minut</small>
            </button>
        </header>
        <div class="reviews" data-product-reviews>
            <?php foreach($product['reviews'] as $review): ?>
                <article class="product-review-card">
                    <div class="rating-stars"><?= str_repeat('★',(int)$review['rating']) ?><i><?= str_repeat('★',5-(int)$review['rating']) ?></i></div>
                    <h3><?= e($review['title'] ?: 'Recenzie client') ?></h3>
                    <p><?= nl2br(e($review['body'])) ?></p>
                    <small><?= e($review['author_name']) ?><?= $review['verified_purchase'] ? ' · Achiziție verificată' : '' ?></small>
                    <?php if (!empty($review['admin_reply'])): ?>
                        <div class="review-store-response">
                            <div><span aria-hidden="true"><img src="<?=asset('images/favicon-owl.png')?>" alt=""></span><p><strong>Răspuns SmileBaby</strong><small><?= !empty($review['replied_at']) ? date('d.m.Y', strtotime($review['replied_at'])) : '' ?></small></p></div>
                            <blockquote><?= nl2br(e($review['admin_reply'])) ?></blockquote>
                        </div>
                    <?php endif ?>
                </article>
            <?php endforeach ?>
            <?php if (!$product['reviews']): ?><div class="product-reviews-empty" data-product-reviews-empty><strong>Fii primul care scrie o recenzie</strong><p>Împărtășește experiența ta cu acest produs.</p></div><?php endif ?>
        </div>
        <dialog class="review-compose-dialog" data-review-dialog>
            <form class="review-form" action="/recenzie" method="post" data-product-review-form>
                <?=csrf_field()?><input type="hidden" name="product_id" value="<?=$product['id']?>">
                <header class="review-compose-header">
                    <div class="review-compose-icon" aria-hidden="true"><img src="<?=asset('images/favicon-owl.png')?>" alt=""></div>
                    <div><span>SPUNE-NE PĂREREA TA</span><h3>Scrie o recenzie</h3><p>Experiența ta poate ajuta un alt părinte să aleagă cu încredere.</p></div>
                    <button type="button" class="review-compose-close" aria-label="Închide" data-review-close>×</button>
                </header>
                <div class="review-compose-body">
                    <p class="review-form-feedback" role="alert" data-review-form-feedback hidden></p>
                    <fieldset class="review-star-picker" data-review-star-picker>
                        <legend>Cum ți s-a părut produsul?</legend>
                        <div role="radiogroup" aria-label="Alege ratingul">
                            <?php foreach([1=>'Slab',2=>'Acceptabil',3=>'Bun',4=>'Foarte bun',5=>'Excelent'] as $score=>$label): ?>
                                <input type="radio" id="review-rating-<?=$score?>" name="rating" value="<?=$score?>" required>
                                <label for="review-rating-<?=$score?>" data-review-star="<?=$score?>" aria-label="<?=$score?> stele — <?=e($label)?>">★</label>
                            <?php endforeach ?>
                        </div>
                        <p data-review-rating-label>Alege numărul de stele</p>
                    </fieldset>
                    <div class="review-compose-grid">
                        <label>Numele tău<input name="author_name" value="<?=e(user()['first_name']??'')?>" autocomplete="name" placeholder="Cum te numești?" required></label>
                        <label>Adresa de email<input type="email" name="email" value="<?=e(user()['email']??'')?>" autocomplete="email" placeholder="nume@exemplu.ro" required></label>
                    </div>
                    <label>Titlul recenziei <small>opțional</small><input name="title" placeholder="Pe scurt, cum a fost experiența?"></label>
                    <label>Recenzia ta<textarea name="body" rows="5" placeholder="Spune-ne ce ți-a plăcut la acest produs…" required></textarea></label>
                </div>
                <footer class="review-compose-footer"><button type="button" data-review-close>Renunță</button><button class="button" type="submit">PUBLICĂ RECENZIA <span aria-hidden="true">→</span></button></footer>
            </form>
        </dialog>
        <dialog class="review-thank-you-dialog" data-review-thank-you-dialog>
            <button type="button" class="review-thank-you-close" aria-label="Închide" data-review-thank-you-close>×</button>
            <div class="review-thank-you-icon" aria-hidden="true"><img src="<?=asset('images/favicon-owl.png')?>" alt=""></div>
            <span>RECENZIE PUBLICATĂ</span>
            <h3>Mulțumim pentru recenzia acordată!</h3>
            <p>Experiența ta îi ajută și pe ceilalți părinți să aleagă cu mai multă încredere.</p>
            <button class="button" type="button" data-review-thank-you-close>CU DRAG</button>
        </dialog>
    </div>
</section>
<?php if($related):?><section class="section shell products-section related-products" id="recomandari" data-related-carousel><div class="section-heading"><div><span class="eyebrow">ALESE PENTRU TINE</span><h2>S-ar putea să îți placă</h2></div><a class="text-button" href="/magazin">VEZI MAGAZINUL <?=icon('arrow')?></a></div><div class="home-products-carousel"><?php if(count($related)>1):?><button class="home-product-arrow home-product-prev" type="button" aria-label="Recomandarea anterioară" data-related-prev><?=icon('chevron')?></button><?php endif?><div class="home-products-viewport" data-related-viewport><div class="home-products-track" data-related-track><?php foreach($related as $relatedProduct)(static function($product){require BASE_PATH.'/views/components/product-card.php';})($relatedProduct);?></div></div><?php if(count($related)>1):?><button class="home-product-arrow home-product-next" type="button" aria-label="Recomandarea următoare" data-related-next><?=icon('chevron')?></button><?php endif?></div><?php if(count($related)>1):?><div class="home-product-dots related-product-dots" aria-label="Poziția în recomandări"><?php foreach($related as $index=>$relatedProduct):?><button class="<?=$index===0?'active':''?>" type="button" aria-label="Recomandarea <?=$index+1?>" data-related-dot="<?=$index?>"></button><?php endforeach?></div><p class="related-swipe-hint"><?=icon('arrow')?> Glisează pentru mai multe</p><?php endif?></section><?php endif?>
<dialog class="lightbox" data-lightbox><button type="button" data-lightbox-close aria-label="Închide"><?=icon('close')?></button><img src="" alt="Imagine produs mărită"></dialog>
<script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'image'=>array_map(fn($i)=>config('app.url').upload_url($i['image_path']),$images),'description'=>strip_tags($product['short_description']?:$product['description']),'sku'=>$product['sku']?:null,'brand'=>['@type'=>'Brand','name'=>$product['brand']?:'SmileBaby'],'offers'=>['@type'=>'Offer','priceCurrency'=>'RON','price'=>$product['sale_price']?:$product['regular_price'],'availability'=>$inStock?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>config('app.url').'/produs/'.$product['slug']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script>
