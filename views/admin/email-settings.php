<div class="admin-page-head">
    <div><span class="eyebrow">COMUNICARE</span><h1>Configurare email</h1><p>Mesajele automate ale magazinului, configurate în siguranță.</p></div>
</div>
<div class="admin-editor simple">
    <form class="admin-card" action="/admin/setari/email" method="post">
        <?= csrf_field() ?>
        <h2>Server SMTP</h2>
        <p>Parola este criptată înainte de salvare și nu este afișată niciodată în browser.</p>
        <div class="admin-form-grid">
            <label>Server SMTP<input name="smtp_host" value="<?=e($smtp['host'])?>" placeholder="smtp.example.ro" required></label>
            <label>Port<input type="number" name="smtp_port" min="1" max="65535" value="<?=e($smtp['port'])?>" required></label>
            <label>Utilizator<input name="smtp_username" value="<?=e($smtp['username'])?>" autocomplete="off"></label>
            <label>Parolă<input type="password" name="smtp_password" placeholder="<?=e($smtp['password_masked']?:'Introdu parola')?>" autocomplete="new-password"><small>Lasă gol pentru a păstra parola existentă.</small></label>
            <label>Criptare<select name="smtp_encryption"><option value="tls" <?=$smtp['encryption']==='tls'?'selected':''?>>TLS / STARTTLS</option><option value="ssl" <?=$smtp['encryption']==='ssl'?'selected':''?>>SSL</option><option value="none" <?=$smtp['encryption']==='none'?'selected':''?>>Fără criptare</option></select></label>
            <label>Nume expeditor<input name="smtp_from_name" value="<?=e($smtp['from_name'])?>" required></label>
            <label>Email expeditor<input type="email" name="smtp_from_email" value="<?=e($smtp['from_email'])?>" required></label>
        </div>
        <button class="admin-button">Salvează emailul</button>
    </form>
    <aside class="editor-side">
        <form class="admin-card" action="/admin/setari/email/test" method="post">
            <?= csrf_field() ?>
            <h2>Email de test</h2>
            <p>Salvează configurația, apoi verifică imediat livrarea.</p>
            <label>Destinatar<input type="email" name="recipient" value="<?=e($settings['site_email']??$smtp['from_email'])?>" required></label>
            <button class="admin-button secondary wide">Trimite testul</button>
        </form>
    </aside>
</div>
