<?php
$p = $product ?? [];
$action = $product ? '/admin/produse/' . $p['id'] . '/salvare' : '/admin/produse/salvare';
$editorTitle = $product ? 'Editează produsul' : 'Adaugă un produs';
$previewImage = $images[0]['image_path'] ?? null;
$customizationFields = $customizationFields ?? [];
$productAddons = $productAddons ?? [];
$addonCatalog = $addonCatalog ?? [];
$configuredAddons = [];
foreach ($productAddons as $addon) $configuredAddons[(int) $addon['addon_product_id']] = $addon;
?>
<section class="product-editor-context" aria-hidden="true"><span class="eyebrow">CATALOG / PRODUSE</span><h1><?= e($editorTitle) ?></h1><p>Editor ghidat pentru catalogul SmileBaby.</p></section>

<dialog class="product-editor-dialog" data-product-editor aria-labelledby="product-editor-title">
    <header class="product-editor-head">
        <div><span class="eyebrow">EDITOR GHIDAT · 7 PAȘI</span><h2 id="product-editor-title"><?= e($editorTitle) ?></h2><p>Completează doar informațiile relevante. Explicațiile îți arată unde apare fiecare câmp.</p></div>
        <a href="/admin/produse" aria-label="Închide editorul" data-product-editor-close>×</a>
    </header>
    <form action="<?= e($action) ?>" method="post" enctype="multipart/form-data" data-product-editor-form data-product-id="<?= (int) ($p['id'] ?? 0) ?>">
        <?= csrf_field() ?>
        <div class="product-editor-content">
            <aside class="product-editor-progress" aria-label="Pașii editorului">
                <button class="active" type="button" data-product-step-target="0"><i>1</i><span><b>Identitate</b><small>Nume, adresă și descrieri</small></span></button>
                <button type="button" data-product-step-target="1"><i>2</i><span><b>Preț & stoc</b><small>Vânzare și disponibilitate</small></span></button>
                <button type="button" data-product-step-target="2"><i>3</i><span><b>Imagini & categorii</b><small>Cum este găsit în magazin</small></span></button>
                <button type="button" data-product-step-target="3"><i>4</i><span><b>Variante</b><small>Mărimi, modele sau seturi</small></span></button>
                <button type="button" data-product-step-target="4"><i>5</i><span><b>Personalizare</b><small>Câmpuri și preț</small></span></button>
                <button type="button" data-product-step-target="5"><i>6</i><span><b>Produse suplimentare</b><small>Completează setul</small></span></button>
                <button type="button" data-product-step-target="6"><i>7</i><span><b>Publicare & Google</b><small>Vizibilitate și SEO</small></span></button>
                <section class="product-editor-live-summary">
                    <span>PREVIZUALIZARE RAPIDĂ</span>
                    <div class="product-summary-image"><?php if ($previewImage): ?><img src="<?= e(upload_url($previewImage)) ?>" alt=""><?php else: ?><?= icon('gift') ?><?php endif ?></div>
                    <strong data-product-summary-name><?= e($p['name'] ?? 'Produs fără nume') ?></strong>
                    <small data-product-summary-price><?= money($p['regular_price'] ?? 0) ?></small>
                    <em data-product-summary-status><?= e(($p['status'] ?? 'draft') === 'active' ? 'Activ' : 'Draft') ?></em>
                </section>
            </aside>
            <main class="product-editor-panels">
                <section class="product-editor-step active" data-product-step="0">
                    <div class="product-step-number">1</div><div class="product-step-body">
                        <div class="product-step-title"><span>IDENTITATE</span><h3>Cum recunoaște clientul produsul</h3><p>Numele și textele sunt primele informații văzute în magazin și la căutare.</p></div>
                        <label class="product-check-field" data-availability-field="name">Numele produsului<input name="name" value="<?= e($p['name'] ?? '') ?>" placeholder="Ex.: Trusou de botez Iris" required autocomplete="off" data-product-name data-check-availability="name"><span class="product-availability-message" data-availability-message aria-live="polite"></span><small>Folosește o denumire clară, scurtă și diferită de celelalte produse.</small></label>
                        <div class="product-form-grid two">
                            <label class="product-check-field" data-availability-field="slug">Slug<input name="slug" value="<?= e($p['slug'] ?? '') ?>" placeholder="generat-automat" autocomplete="off" data-product-slug data-check-availability="slug"><span class="product-availability-message" data-availability-message aria-live="polite"></span><small>Adresa paginii. Se generează din nume, dar o poți ajusta.</small></label>
                            <label class="product-check-field" data-availability-field="sku">Cod produs (SKU)<input name="sku" value="<?= e($p['sku'] ?? '') ?>" placeholder="Se generează automat" autocomplete="off" data-product-sku data-check-availability="sku"><span class="product-availability-message" data-availability-message aria-live="polite"></span><small>Poți introduce manual un cod unic sau îl poți lăsa liber pentru generare automată.</small></label>
                            <label class="product-check-field" data-availability-field="gtin">EAN / GTIN <em>opțional</em><input name="gtin" value="<?= e($p['gtin'] ?? '') ?>" placeholder="Ex.: 5941234567890" inputmode="numeric" autocomplete="off" data-check-availability="gtin"><span class="product-availability-message" data-availability-message aria-live="polite"></span><small>Completează numai dacă produsul are un cod de bare oficial.</small></label>
                            <label>Brand<input name="brand" value="<?= e($p['brand'] ?? 'SmileBaby') ?>"><small>Numele mărcii afișate în datele produsului.</small></label>
                        </div>
                        <label>Descriere scurtă<textarea name="short_description" rows="3" maxlength="500" data-product-count="short"><?= e(strip_tags($p['short_description'] ?? '')) ?></textarea><small>Rezumatul din listări. Recomandat: 120–160 caractere. <b data-product-count-output="short">0 / 500</b></small></label>
                        <div class="product-rich-field" data-rich-editor>
                            <div class="product-rich-label"><span>Descriere completă</span><small>Formatează vizual textul, fără să introduci cod HTML.</small></div>
                            <div class="product-rich-editor">
                                <div class="product-rich-toolbar" role="toolbar" aria-label="Formatarea descrierii">
                                    <button type="button" data-rich-command="undo" title="Anulează ultima modificare" aria-label="Anulează">↶</button>
                                    <button type="button" data-rich-command="redo" title="Refă modificarea" aria-label="Refă">↷</button>
                                    <span aria-hidden="true"></span>
                                    <button type="button" data-rich-command="bold" title="Text îngroșat"><b>B</b></button>
                                    <button type="button" data-rich-command="italic" title="Text cursiv"><i>I</i></button>
                                    <button type="button" data-rich-command="underline" title="Text subliniat"><u>U</u></button>
                                    <label>Stil<span class="sr-only">Stil paragraf</span><select data-rich-format aria-label="Stil paragraf"><option value="p">Text normal</option><option value="h2">Titlu mare</option><option value="h3">Subtitlu</option><option value="blockquote">Citat / notă</option></select></label>
                                    <span aria-hidden="true"></span>
                                    <button type="button" data-rich-command="insertUnorderedList" title="Listă cu puncte" aria-label="Listă cu puncte">• Listă</button>
                                    <button type="button" data-rich-command="insertOrderedList" title="Listă numerotată" aria-label="Listă numerotată">1. Listă</button>
                                    <button type="button" data-rich-command="createLink" title="Adaugă link" aria-label="Adaugă link">🔗</button>
                                    <button type="button" data-rich-command="unlink" title="Elimină linkul" aria-label="Elimină linkul">⛓</button>
                                    <button type="button" data-rich-command="removeFormat" title="Șterge formatarea" aria-label="Șterge formatarea">Tx</button>
                                </div>
                                <div class="product-rich-canvas" contenteditable="true" role="textbox" aria-multiline="true" data-rich-canvas data-placeholder="Scrie aici descrierea completă a produsului…"><?= sanitize_rich_html((string) ($p['description'] ?? '')) ?></div>
                                <textarea name="description" data-rich-textarea hidden><?= e(sanitize_rich_html((string) ($p['description'] ?? ''))) ?></textarea>
                                <footer><span>Textul este salvat exact așa cum îl vezi.</span><b data-product-count-output="long">0 caractere</b></footer>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="1" hidden>
                    <div class="product-step-number">2</div><div class="product-step-body">
                        <div class="product-step-title"><span>VÂNZARE</span><h3>Prețul și disponibilitatea</h3><p>Stabilește prețul afișat și dacă magazinul trebuie să urmărească o cantitate exactă.</p></div>
                        <div class="product-form-grid two">
                            <label>Preț normal (lei)<input type="number" step="0.01" min="0" name="regular_price" value="<?= e($p['regular_price'] ?? 0) ?>" required data-product-price><small>Prețul de bază, cu toate taxele incluse.</small></label>
                            <label>Preț redus (lei)<input type="number" step="0.01" min="0" name="sale_price" value="<?= e($p['sale_price'] ?? '') ?>"><small>Opțional. Va fi evidențiat ca ofertă.</small></label>
                        </div>
                        <div class="product-form-grid two">
                            <label>Reducere de la<input type="datetime-local" name="sale_start" value="<?= !empty($p['sale_start']) ? e(date('Y-m-d\TH:i', strtotime($p['sale_start']))) : '' ?>"><small>Lasă liber pentru a porni imediat.</small></label>
                            <label>Reducere până la<input type="datetime-local" name="sale_end" value="<?= !empty($p['sale_end']) ? e(date('Y-m-d\TH:i', strtotime($p['sale_end']))) : '' ?>"><small>Lasă liber dacă oferta nu are termen.</small></label>
                        </div>
                        <label class="product-switch"><span><strong>Gestionează stocul exact</strong><small>Activează dacă vrei să scazi automat fiecare bucată vândută.</small></span><input type="checkbox" name="manage_stock" value="1" <?= !empty($p['manage_stock']) ? 'checked' : '' ?> role="switch" data-product-manage-stock><i></i></label>
                        <div class="product-stock-fields" data-product-stock-fields>
                            <div class="product-form-grid three">
                                <label>Cantitate disponibilă<input type="number" min="0" name="stock_quantity" value="<?= e($p['stock_quantity'] ?? '') ?>"><small>Numărul real de bucăți ce pot fi comandate.</small></label>
                                <label>Prag stoc redus<input type="number" min="0" name="low_stock_threshold" value="<?= e($p['low_stock_threshold'] ?? 3) ?>"><small>Primești atenționare când ajunge la acest nivel.</small></label>
                                <label>Disponibilitate<select name="stock_status"><option value="in_stock">În stoc</option><option value="out_of_stock" <?= ($p['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Indisponibil</option><option value="on_backorder" <?= ($p['stock_status'] ?? '') === 'on_backorder' ? 'selected' : '' ?>>Precomandă</option></select><small>Mesajul arătat clientului.</small></label>
                            </div>
                            <label class="product-switch compact"><span><strong>Permite precomenzi</strong><small>Clientul poate comanda chiar dacă stocul a ajuns la zero.</small></span><input type="checkbox" name="allow_backorders" value="1" <?= !empty($p['allow_backorders']) ? 'checked' : '' ?> role="switch"><i></i></label>
                        </div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="2" hidden>
                    <div class="product-step-number">3</div><div class="product-step-body">
                        <div class="product-step-title"><span>PREZENTARE</span><h3>Imaginile și locul din catalog</h3><p>Fotografiile vând produsul, iar categoriile îl ajută pe client să îl găsească ușor.</p></div>
                        <section class="product-media-manager" data-product-media-manager>
                            <header>
                                <div><span>MEDIA PRODUS</span><h4>Fotografiile produsului</h4><p>Prima fotografie este afișată în catalog. Trage cardurile pentru a schimba ordinea sau folosește săgețile.</p></div>
                                <p><strong data-product-image-count><?= count($images) ?></strong>/12<small>JPG, PNG sau WebP · max. 12 MB</small></p>
                            </header>
                            <div class="product-media-gallery" data-product-image-gallery>
                                <?php foreach ($images as $image): ?>
                                    <article class="product-media-card<?= $image['is_featured'] ? ' is-cover' : '' ?>" draggable="true" data-image-key="existing:<?= (int) $image['id'] ?>" data-image-kind="existing" data-image-id="<?= (int) $image['id'] ?>">
                                        <img src="<?= e(upload_url($image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?? '') ?>">
                                        <button class="product-media-drag" type="button" aria-label="Trage pentru reordonare" title="Trage pentru reordonare"><i></i><i></i><i></i><i></i><i></i><i></i></button>
                                        <button class="product-media-remove" type="button" data-media-action="remove" aria-label="Elimină fotografia" title="Elimină fotografia">×</button>
                                        <span class="product-media-cover-label">Copertă</span>
                                        <div class="product-media-actions"><button type="button" data-media-action="left" aria-label="Mută fotografia la stânga">←</button><button type="button" data-media-action="cover" aria-label="Alege fotografia drept copertă" title="Alege drept copertă">★</button><button type="button" data-media-action="right" aria-label="Mută fotografia la dreapta">→</button></div>
                                    </article>
                                <?php endforeach ?>
                                <label class="product-media-add" data-product-media-add>
                                    <span><?= icon('media') ?></span><strong>Adaugă fotografii</strong><small>Selectează mai multe imagini<br>sau trage-le aici</small>
                                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-product-images>
                                </label>
                            </div>
                            <footer><span aria-hidden="true">↕</span><p>Reordonează fotografiile prin glisare. Steaua marchează fotografia principală.</p></footer>
                            <input type="hidden" name="image_order" value="[]" data-product-image-order>
                            <input type="hidden" name="cover_image" value="<?= !empty($images[0]) ? 'existing:' . (int) $images[0]['id'] : '' ?>" data-product-cover-image>
                            <div data-product-removed-images></div>
                        </section>
                        <div class="product-category-select" data-category-multiselect>
                            <label class="product-category-label">Categorii <small>Alege una sau mai multe zone în care produsul trebuie să apară.</small></label>
                            <button class="product-category-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" data-category-toggle><span><small>CATEGORII SELECTATE</small><strong data-category-summary>Alege categoriile produsului</strong></span><b data-category-selection-count>0</b><?= icon('chevron') ?></button>
                            <div class="product-category-dropdown" data-category-dropdown hidden>
                                <label class="product-category-search"><?= icon('search') ?><input type="search" placeholder="Caută o categorie..." autocomplete="off" data-category-search><kbd>Esc</kbd></label>
                                <div class="product-category-options" role="listbox" aria-multiselectable="true" data-category-options><?php foreach ($categories as $category): ?><label role="option"><input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $selected, true) ? 'checked' : '' ?>><i></i><span><?= e($category['name']) ?></span></label><?php endforeach ?><p data-category-empty hidden>Nu am găsit nicio categorie.</p></div>
                                <footer><span><b data-category-selection-count>0</b> selectate</span><button type="button" data-category-done>Gata</button></footer>
                            </div>
                            <div class="product-category-chips" data-category-chips></div>
                        </div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="3" hidden>
                    <div class="product-step-number">4</div><div class="product-step-body">
                        <div class="product-step-title"><span>OPȚIUNI</span><h3>Are produsul mai multe variante?</h3><p>Folosește variante numai dacă același produs se vinde în mărimi, culori, modele sau seturi diferite.</p></div>
                        <div class="product-variant-intro"><span><?= icon('gift') ?></span><div><strong>Exemple potrivite</strong><small>„Roz / 0–3 luni”, „Model Sofia”, „Set 10 piese”. Fiecare variantă poate avea preț și stoc propriu.</small></div><button class="admin-button secondary" type="button" data-add-variant>+ Adaugă variantă</button></div>
                        <div class="variant-editor product-variant-editor" data-variant-editor><?php foreach ($variants as $variant): ?><div class="variant-row"><?php require BASE_PATH . '/views/admin/variant-row.php'; ?></div><?php endforeach ?></div>
                        <template data-variant-template><div class="variant-row"><?php $variant = []; require BASE_PATH . '/views/admin/variant-row.php'; ?></div></template>
                        <div class="product-variant-empty" data-product-variant-empty><span>◇</span><strong>Nicio variantă adăugată</strong><small>Este în regulă dacă produsul se vinde într-o singură formă.</small></div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="4" hidden>
                    <div class="product-step-number">5</div><div class="product-step-body">
                        <div class="product-step-title"><span>PERSONALIZARE</span><h3>Transformă produsul într-un dar unic</h3><p>Clientul poate alege personalizarea, apoi completează exact câmpurile definite de tine.</p></div>
                        <div class="product-customization-admin" data-customization-settings>
                            <label class="product-switch product-customization-master"><span><strong>Personalizare activă</strong><small>Când este activă, opțiunea apare pe pagina produsului și în coș.</small></span><input type="checkbox" name="is_customizable" value="1" <?= !empty($p['is_customizable']) ? 'checked' : '' ?> role="switch" data-customization-toggle><i></i></label>
                            <div class="product-customization-config" data-customization-content>
                                <div class="product-form-grid two product-customization-pricing">
                                    <label>Preț suplimentar (lei)<input type="number" name="customization_price" min="0" step="0.01" value="<?= e($p['customization_price'] ?? 0) ?>"><small>Se adaugă o singură dată pentru fiecare bucată personalizată.</small></label>
                                    <label>Etichetă în magazin<input name="badge_text" maxlength="80" value="<?= e($p['badge_text'] ?? '') ?>" placeholder="Ex.: Se poate personaliza"><small>Apare pe cardul produsului și lângă opțiunea de personalizare.</small></label>
                                </div>
                                <div class="product-customization-note"><span>✦</span><div><strong>Cum funcționează?</strong><p>Adaugi câmpurile de care ai nevoie. Dacă un câmp este obligatoriu, clientul nu poate confirma personalizarea până nu îl completează.</p></div></div>
                                <header class="product-customization-fields-head"><div><span>CÂMPURILE CLIENTULUI</span><h4>Ce informații trebuie să completeze?</h4><p>Exemple: Nume copil, Data botezului, Data nașterii.</p></div><button class="admin-button secondary" type="button" data-add-customization-field>+ Adaugă câmp</button></header>
                                <div class="product-customization-fields" data-customization-fields>
                                    <?php foreach ($customizationFields as $field): ?>
                                        <div class="product-customization-field" data-customization-field>
                                            <input type="hidden" name="customization_field_id[]" value="<?= (int) $field['id'] ?>">
                                            <button class="product-customization-drag" type="button" aria-label="Reordonează câmpul" title="Trage pentru reordonare">⋮⋮</button>
                                            <label>Denumire<input name="customization_field_label[]" maxlength="150" value="<?= e($field['label']) ?>" placeholder="Ex.: Numele copilului" required></label>
                                            <label>Tip<select name="customization_field_type[]"><option value="text" <?= $field['field_type'] === 'text' ? 'selected' : '' ?>>Text</option><option value="date" <?= $field['field_type'] === 'date' ? 'selected' : '' ?>>Dată</option></select></label>
                                            <label>Exemplu / ajutor<input name="customization_field_placeholder[]" maxlength="190" value="<?= e($field['placeholder'] ?? '') ?>" placeholder="Ex.: Maria"></label>
                                            <label>Completare<select name="customization_field_required[]"><option value="1" <?= !empty($field['is_required']) ? 'selected' : '' ?>>Obligatoriu</option><option value="0" <?= empty($field['is_required']) ? 'selected' : '' ?>>Opțional</option></select></label>
                                            <div class="product-customization-order"><button type="button" data-customization-move="up" aria-label="Mută mai sus">↑</button><button type="button" data-customization-move="down" aria-label="Mută mai jos">↓</button><button type="button" data-remove-customization-field aria-label="Șterge câmpul">×</button></div>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                                <div class="product-customization-empty" data-customization-empty><span>＋</span><strong>Adaugă primul câmp</strong><small>Personalizarea activă are nevoie de cel puțin un câmp pentru client.</small></div>
                                <template data-customization-template><div class="product-customization-field" data-customization-field><input type="hidden" name="customization_field_id[]" value="0"><button class="product-customization-drag" type="button" aria-label="Reordonează câmpul" title="Trage pentru reordonare">⋮⋮</button><label>Denumire<input name="customization_field_label[]" maxlength="150" placeholder="Ex.: Numele copilului" required></label><label>Tip<select name="customization_field_type[]"><option value="text">Text</option><option value="date">Dată</option></select></label><label>Exemplu / ajutor<input name="customization_field_placeholder[]" maxlength="190" placeholder="Ex.: Maria"></label><label>Completare<select name="customization_field_required[]"><option value="1">Obligatoriu</option><option value="0">Opțional</option></select></label><div class="product-customization-order"><button type="button" data-customization-move="up" aria-label="Mută mai sus">↑</button><button type="button" data-customization-move="down" aria-label="Mută mai jos">↓</button><button type="button" data-remove-customization-field aria-label="Șterge câmpul">×</button></div></div></template>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="5" hidden>
                    <div class="product-step-number">6</div><div class="product-step-body">
                        <div class="product-step-title"><span>COMPLETEAZĂ SETUL</span><h3>Produse suplimentare disponibile</h3><p>Alege produsele pe care clientul le poate adăuga direct lângă acest produs și stabilește prețul special pentru fiecare.</p></div>
                        <div class="product-addon-admin" data-addon-settings>
                            <label class="product-switch product-addon-master"><span><strong>Adăugare produse suplimentare</strong><small>Activează secțiunea „Completează setul cu” pe pagina produsului.</small></span><input type="checkbox" name="addons_enabled" value="1" <?= !empty($p['addons_enabled']) ? 'checked' : '' ?> role="switch" data-addon-toggle><i></i></label>
                            <div class="product-addon-config" data-addon-content>
                                <div class="product-addon-explainer"><span>＋</span><div><strong>Preț independent pentru fiecare produs</strong><p>Poți oferi, de exemplu, o lumânare de 300 lei la numai 50 lei atunci când este cumpărată împreună cu acest trusou.</p></div></div>
                                <div class="product-addon-picker" data-addon-picker>
                                    <button class="product-addon-trigger" type="button" aria-expanded="false" data-addon-open><span><small>PRODUSE ELIGIBILE</small><strong data-addon-summary>Alege produse sau o categorie întreagă</strong></span><b data-addon-count><?= count($configuredAddons) ?></b><?= icon('chevron') ?></button>
                                    <div class="product-addon-dropdown" data-addon-dropdown hidden>
                                        <label class="product-addon-search"><?= icon('search') ?><input type="search" placeholder="Caută după nume, SKU, slug sau categorie…" autocomplete="off" data-addon-search><kbd>Esc</kbd></label>
                                        <section class="product-addon-category-tools"><header><span>CATEGORII</span><small>Selectează rapid toate produsele dintr-o categorie</small></header><div><?php foreach($categories as $category): $categoryCount = count(array_filter($addonCatalog, static fn(array $item): bool => in_array((int)$category['id'], $item['category_ids'] ?? [], true))); if(!$categoryCount) continue; ?><label><input type="checkbox" value="<?= (int)$category['id'] ?>" data-addon-category><i></i><span><?= e($category['name']) ?></span><b><?= $categoryCount ?></b></label><?php endforeach ?></div></section>
                                        <div class="product-addon-options" data-addon-options>
                                            <?php foreach($addonCatalog as $addon): $addonId=(int)$addon['id']; $isSelected=isset($configuredAddons[$addonId]); $searchTerms=trim($addon['name'].' '.($addon['slug']??'').' '.($addon['sku']??'').' '.($addon['categories']??'').' '.($addon['price']??'')); ?>
                                                <article class="product-addon-option" data-addon-option data-addon-id="<?= $addonId ?>" data-addon-name="<?= e($addon['name']) ?>" data-addon-image="<?= e(upload_url($addon['image_path'])) ?>" data-addon-price="<?= e((string)$addon['price']) ?>" data-addon-sku="<?= e($addon['sku']??'') ?>" data-addon-categories="<?= e($addon['categories']??'') ?>" data-addon-category-ids="<?= e(implode(',', $addon['category_ids'] ?? [])) ?>" data-addon-search-terms="<?= e($searchTerms) ?>">
                                                    <label aria-label="Selectează <?= e($addon['name']) ?>"><input type="checkbox" name="addon_product_ids[]" value="<?= $addonId ?>" <?= $isSelected?'checked':'' ?>><i></i></label>
                                                    <button type="button" data-addon-image-preview aria-label="Mărește imaginea <?= e($addon['name']) ?>"><img src="<?= e(upload_url($addon['image_path'])) ?>" alt=""><span><?= icon('plus') ?></span></button>
                                                    <div><strong><?= e($addon['name']) ?></strong><small><?= e(($addon['categories']?:'Fără categorie').' · '.($addon['sku']?:'Fără SKU')) ?></small></div>
                                                    <em><?= money($addon['price']) ?></em>
                                                </article>
                                            <?php endforeach ?>
                                            <p class="product-addon-search-empty" data-addon-search-empty hidden>Nu am găsit produse. Încearcă o denumire apropiată sau o categorie.</p>
                                        </div>
                                        <footer><span><b data-addon-count><?= count($configuredAddons) ?></b> produse selectate</span><button type="button" data-addon-done>Gata</button></footer>
                                    </div>
                                </div>
                                <section class="product-addon-selected-section"><header><div><span>PREȚURI SPECIALE</span><h4>Produsele alese</h4><p>Prețul introdus aici se aplică numai când produsul este ales ca opțiune suplimentară.</p></div></header><div class="product-addon-selected" data-addon-selected-list>
                                    <?php foreach($productAddons as $addon): ?><article data-addon-selected="<?= (int)$addon['addon_product_id'] ?>"><button type="button" data-addon-selected-image data-image="<?= e(upload_url($addon['image_path'])) ?>" data-alt="<?= e($addon['name']) ?>"><img src="<?= e(upload_url($addon['image_path'])) ?>" alt=""></button><div><strong><?= e($addon['name']) ?></strong><small><?= e(($addon['categories']?:'Fără categorie').' · '.($addon['sku']?:'Fără SKU')) ?></small><em>Preț catalog: <?= money($addon['catalog_price']) ?></em></div><label>Preț în set (lei)<input type="number" name="addon_prices[<?= (int)$addon['addon_product_id'] ?>]" min="0" step="0.01" value="<?= e($addon['custom_price']) ?>" required></label><button type="button" data-addon-remove aria-label="Elimină produsul">×</button></article><?php endforeach ?>
                                </div><div class="product-addon-empty" data-addon-empty><span>＋</span><strong>Niciun produs selectat</strong><small>Deschide lista și alege produse individual sau o categorie întreagă.</small></div></section>
                            </div>
                            <dialog class="product-addon-image-dialog" data-addon-image-dialog><button type="button" aria-label="Închide" data-addon-image-close>×</button><img src="" alt="" data-addon-image-large><div><strong data-addon-image-title></strong><small>Previzualizare produs suplimentar</small></div></dialog>
                        </div>
                    </div>
                </section>

                <section class="product-editor-step" data-product-step="6" hidden>
                    <div class="product-step-number">7</div><div class="product-step-body">
                        <div class="product-step-title"><span>PUBLICARE & GOOGLE</span><h3>Ultimele detalii înainte de salvare</h3><p>Alege cine poate vedea produsul și cum va fi descris în rezultatele Google.</p></div>
                        <div class="product-publish-card">
                            <label>Statusul produsului<select name="status" data-product-status><?php foreach (['draft'=>'Draft — doar în administrare','active'=>'Activ — vizibil în magazin','hidden'=>'Ascuns — accesibil doar prin link','archived'=>'Arhivat — scos din catalog'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($p['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><small>Pentru un produs nou recomandăm Draft până termini toate verificările.</small></label>
                            <div class="product-form-grid two"><label class="product-switch"><span><strong>Apare pe prima pagină</strong><small>Intră în selecția de produse recomandate.</small></span><input type="checkbox" name="featured" value="1" <?= !empty($p['featured']) ? 'checked' : '' ?> role="switch"><i></i></label><label>Ordine pe prima pagină<input type="number" name="featured_order" min="0" value="<?= e($p['featured_order'] ?? 0) ?>"><small>Numerele mici apar primele.</small></label></div>
                        </div>
                        <div class="product-seo-card"><div class="product-seo-preview"><span>PREVIZUALIZARE GOOGLE</span><strong data-seo-preview-title><?= e(($p['meta_title'] ?? '') ?: ($p['name'] ?? 'Numele produsului')) ?></strong><em data-seo-preview-url>smilebaby.ro/produs/<?= e($p['slug'] ?? 'adresa-produsului') ?></em><p data-seo-preview-description><?= e($p['meta_description'] ?? 'Descrierea produsului va apărea aici și îi va ajuta pe clienți să înțeleagă rapid ce oferi.') ?></p></div><label>Titlu SEO<input name="meta_title" value="<?= e($p['meta_title'] ?? '') ?>" maxlength="255" data-seo-title><small>Dacă rămâne liber, se folosește numele produsului.</small></label><label>Descriere SEO<textarea name="meta_description" maxlength="320" rows="3" data-seo-description><?= e($p['meta_description'] ?? '') ?></textarea><small>Recomandat: o propoziție clară de aproximativ 150 caractere.</small></label><details class="product-advanced-fields"><summary>Setări SEO avansate</summary><label>Adresă canonical<input type="url" name="canonical_url" value="<?= e($p['canonical_url'] ?? '') ?>"><small>Completează numai dacă pagina principală a produsului este la alt URL.</small></label><label class="product-switch compact"><span><strong>Permite indexarea</strong><small>Google poate include pagina în rezultate.</small></span><input type="checkbox" name="indexable" value="1" <?= !isset($p['indexable']) || $p['indexable'] ? 'checked' : '' ?> role="switch"><i></i></label></details></div>
                    </div>
                </section>
            </main>
        </div>
        <footer class="product-editor-footer">
            <?php if ($product): ?><button class="product-archive-button" type="submit" formaction="/admin/produse/<?= (int) $p['id'] ?>/arhivare" data-product-archive>Arhivează produsul</button><?php else: ?><span class="product-editor-safe"><i>✓</i> Produsul nu se salvează până nu apeși butonul final.</span><?php endif ?>
            <b data-product-step-label>Pasul 1 din 7</b><a href="/admin/produse" data-product-editor-close>Renunță</a><button type="button" data-product-step-prev hidden>← Înapoi</button><button class="product-editor-next" type="button" data-product-step-next>Continuă →</button><button class="admin-button product-editor-submit" type="submit" data-product-submit hidden><?= $product ? 'SALVEAZĂ MODIFICĂRILE' : 'ADAUGĂ PRODUSUL' ?></button>
        </footer>
    </form>
</dialog>
