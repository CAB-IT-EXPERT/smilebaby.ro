<section class="admin-category-page">
    <header class="admin-catalog-head">
        <div><span class="eyebrow">STRUCTURA MAGAZINULUI</span><h1>Categorii</h1><p>Organizează produsele și filtrele magazinului.</p></div>
        <button class="admin-button catalog-add-button" type="button" data-category-create>CATEGORIE NOUĂ</button>
    </header>
    <div class="admin-catalog-table-wrap">
        <table class="admin-catalog-table admin-category-table">
            <thead><tr><th>Categorie</th><th>Slug</th><th>Părinte</th><th>Produse</th><th>Ordine</th><th>Pe website</th><th>Acțiuni</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $category):
                $editorData = [
                    'id' => (int) $category['id'], 'name' => $category['name'], 'slug' => $category['slug'],
                    'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : '', 'short_description' => $category['short_description'],
                    'description' => $category['description'], 'status' => $category['status'], 'show_on_homepage' => (bool) $category['show_on_homepage'],
                    'homepage_order' => (int) $category['homepage_order'], 'meta_title' => $category['meta_title'],
                    'meta_description' => $category['meta_description'], 'indexable' => (bool) $category['indexable'],
                    'image_url' => optimized_image_url($category['image_path'], 'card'), 'product_count' => (int) $category['product_count'],
                ];
            ?>
                <tr>
                    <td><button class="catalog-product category-edit-trigger" type="button" data-category-edit="<?= (int) $category['id'] ?>"><img src="<?= e(optimized_image_url($category['image_path'], 'card')) ?>" alt="" loading="lazy" decoding="async"><span><strong><?= e($category['name']) ?></strong><small class="category-description"><?= e($category['short_description'] ?: 'Fără descriere scurtă') ?></small></span></button><script type="application/json" id="category-data-<?= (int) $category['id'] ?>"><?= json_encode($editorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script></td>
                    <td><code><?= e($category['slug']) ?></code></td>
                    <td><?= e($category['parent_name'] ?: '—') ?></td>
                    <td><strong><?= (int) $category['product_count'] ?></strong></td>
                    <td><span class="category-order"><?= (int) $category['homepage_order'] ?></span></td>
                    <td><form method="post" action="/admin/categorii/<?= (int) $category['id'] ?>/vizibilitate"><?= csrf_field() ?><button class="category-visibility" type="submit" aria-label="Schimbă vizibilitatea categoriei <?= e($category['name']) ?>"><input type="checkbox" <?= $category['status'] === 'active' ? 'checked' : '' ?> tabindex="-1"><i></i><span><?= $category['status'] === 'active' ? 'Vizibilă' : 'Ascunsă' ?></span></button></form></td>
                    <td><div class="catalog-actions"><button type="button" data-category-edit="<?= (int) $category['id'] ?>" aria-label="Editează <?= e($category['name']) ?>" data-tooltip="Editează categoria"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10.9-10.9a2 2 0 0 0-2.8-2.8L5.4 16.2 4 20Z"/><path d="m14.8 6.8 2.8 2.8"/></svg></button><button type="button" class="catalog-delete-button" aria-label="Șterge <?= e($category['name']) ?>" data-tooltip="<?= (int) $category['product_count'] > 0 ? 'Categoria conține produse și nu poate fi ștearsă' : 'Șterge categoria' ?>" <?= (int) $category['product_count'] > 0 ? 'disabled' : 'data-admin-delete data-delete-kind="categoria" data-delete-name="' . e($category['name']) . '" data-delete-action="/admin/categorii/' . (int) $category['id'] . '/stergere"' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 4h6l1 3H8l1-3Z"/><path d="m6 7 1 13h10l1-13M10 11v5M14 11v5"/></svg></button></div></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$categories): ?><tr><td class="catalog-empty" colspan="7"><strong>Nu există categorii.</strong><span>Adaugă prima categorie pentru a organiza magazinul.</span></td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="category-editor-dialog" data-category-editor aria-labelledby="category-editor-title">
    <header class="category-editor-head"><div><span class="eyebrow">EDITOR GHIDAT · 3 PAȘI</span><h2 id="category-editor-title" data-category-editor-title>Categorie nouă</h2><p>Completează pe rând informațiile de mai jos. Explicațiile te ajută să înțelegi unde apare fiecare câmp.</p></div><button type="button" aria-label="Închide" data-category-editor-close>×</button></header>
    <form method="post" enctype="multipart/form-data" data-category-editor-form><?= csrf_field() ?>
        <div class="category-editor-content">
            <nav class="category-editor-progress" aria-label="Pașii categoriei">
                <button type="button" class="active" data-category-step-button="0"><i>1</i><span><b>Identitate</b><small>Nume, adresă și părinte</small></span></button>
                <button type="button" data-category-step-button="1"><i>2</i><span><b>Prezentare</b><small>Descrieri și imagine</small></span></button>
                <button type="button" data-category-step-button="2"><i>3</i><span><b>Publicare & Google</b><small>Vizibilitate și SEO</small></span></button>
                <aside><span>SFAT</span><p>Poți reveni oricând la un pas anterior. Nimic nu se salvează până nu apeși butonul final.</p></aside>
            </nav>
            <div class="category-editor-panels">
            <section class="category-editor-step active" data-category-step="0"><div class="category-step-number">1</div><div class="category-step-body"><div class="category-step-title"><span>IDENTITATE</span><h3>Cum recunoaște clientul categoria</h3><p>Numele și adresa categoriei sunt baza organizării magazinului.</p></div><label>Numele categoriei<input name="name" required data-category-name><small>Este titlul afișat în meniu, în filtre și în pagina categoriei. Folosește o denumire scurtă și ușor de înțeles.</small></label><div class="admin-form-grid"><label>Slug<input name="slug" data-category-slug><small>Adresa din URL, de exemplu <b>botez-fetite</b>. Se generează automat din nume, dar o poți ajusta.</small></label><label>Categorie părinte<select name="parent_id" data-category-parent><option value="">Fără părinte</option><?php foreach ($categories as $parent): ?><option value="<?= (int) $parent['id'] ?>"><?= e($parent['name']) ?></option><?php endforeach ?></select><small>Alege un părinte doar dacă această categorie trebuie să fie o subcategorie.</small></label></div></div></section>
            <section class="category-editor-step" data-category-step="1"><div class="category-step-number">2</div><div class="category-step-body"><div class="category-step-title"><span>PREZENTARE</span><h3>Ce vede și înțelege vizitatorul</h3><p>Textele și imaginea explică rapid ce produse se află aici.</p></div><label>Descriere scurtă<textarea name="short_description" rows="3" data-category-short-description></textarea><small>Apare în carduri și în introduceri. Recomandat: o propoziție de maximum 160 de caractere.</small></label><label>Descriere completă<textarea name="description" rows="4" data-category-description></textarea><small>Folosește acest spațiu pentru detalii despre colecție, materiale, personalizare sau ocaziile potrivite.</small></label><label class="category-image-field">Imagine categorie<input type="file" name="image" accept="image/*" data-category-image><small>Imaginea reprezintă categoria în homepage și în magazin. Recomandat: format pătrat, clar, minimum 800 × 800 px.</small><span class="category-image-preview" data-category-image-preview hidden><img alt="Previzualizare categorie"><b>Imagine selectată</b></span></label></div></section>
            <section class="category-editor-step" data-category-step="2"><div class="category-step-number">3</div><div class="category-step-body"><div class="category-step-title"><span>VIZIBILITATE ȘI GOOGLE</span><h3>Unde apare categoria și cum este indexată</h3><p>Controlezi publicarea, poziția pe homepage și informațiile SEO.</p></div><div class="admin-form-grid"><label>Status<select name="status" data-category-status><option value="active">Activă — vizibilă pe website</option><option value="inactive">Inactivă — ascunsă temporar</option></select><small>O categorie inactivă nu mai este disponibilă clienților, dar datele ei rămân salvate.</small></label><label>Ordine homepage<input type="number" min="0" name="homepage_order" data-category-order><small>Numărul mai mic apare primul. Folosește 0 dacă ordinea nu este importantă.</small></label></div><label class="switch-row"><span><strong>Afișează pe homepage</strong><small>Activează doar pentru categoriile principale pe care vrei să le promovezi în prima pagină.</small></span><input type="hidden" name="show_on_homepage" value="0"><input type="checkbox" name="show_on_homepage" value="1" role="switch" data-category-homepage><i></i></label><div class="admin-form-grid"><label>Meta title<input name="meta_title" maxlength="255" data-category-meta-title><small>Titlul afișat în rezultatele Google. Ideal: 50–60 de caractere și numele SmileBaby la final.</small></label><label>Meta description<textarea name="meta_description" rows="3" maxlength="320" data-category-meta-description></textarea><small>Rezumatul din Google. Ideal: 140–160 de caractere, clar și convingător.</small></label></div><label class="switch-row"><span><strong>Permite indexarea în Google</strong><small>Dezactivează doar pentru categorii temporare sau care nu trebuie găsite în căutări.</small></span><input type="hidden" name="indexable" value="0"><input type="checkbox" name="indexable" value="1" role="switch" data-category-indexable><i></i></label></div></section>
            </div>
        </div>
        <footer class="category-editor-footer"><button class="category-editor-delete" type="button" data-category-modal-delete hidden>Șterge categoria</button><span data-category-step-label>Pasul 1 din 3</span><button type="button" data-category-editor-close>Renunță</button><button type="button" data-category-step-prev hidden>← Înapoi</button><button class="category-editor-next" type="button" data-category-step-next>Continuă →</button><button class="admin-button" type="submit" data-category-submit hidden>SALVEAZĂ CATEGORIA</button></footer>
    </form>
</dialog>
