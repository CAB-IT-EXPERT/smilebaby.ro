<?php
$email = trim((string) setting('site_email', 'contact@smilebaby.ro')) ?: 'contact@smilebaby.ro';
$delivery = trim((string) setting('estimated_delivery_text', '2–3 zile lucrătoare')) ?: '2–3 zile lucrătoare';
$shippingEnabled = filter_var(setting('shipping_enabled', '1'), FILTER_VALIDATE_BOOL);
$shippingCost = max(0, (float) setting('standard_shipping_cost', 20));
$returnShippingCost = max(0, (float) setting('return_shipping_cost', 20));
$freeThreshold = max(0, (float) setting('free_shipping_threshold', 0));

$documents = [
    'termeni-si-conditii' => [
        'number' => '01', 'eyebrow' => 'CADRUL COMENZILOR', 'title' => 'Termeni și condiții',
        'lead' => 'Regulile simple și transparente care guvernează utilizarea magazinului SmileBaby și comenzile plasate online.',
        'summary' => 'Prin folosirea site-ului sau plasarea unei comenzi confirmi că ai citit și accepți acești termeni.',
        'sections' => [
            ['Despre magazin', '<p>SmileBaby este magazinul online prin care sunt prezentate și comercializate produse pentru botez, daruri și articole personalizabile. Datele de contact curente sunt disponibile în pagina <a href="/contact">Contact</a> și în documentele fiscale emise pentru fiecare comandă.</p>'],
            ['Produse și informații', '<p>Depunem eforturi pentru ca fotografiile, descrierile, prețurile și stocurile să fie corecte. Nuanțele pot varia ușor în funcție de ecran, lumină sau caracterul artizanal al produsului. Pentru articolele realizate manual pot exista diferențe firești, care nu reprezintă defecte.</p><p>Înainte de comandă, verifică dimensiunile, conținutul pachetului și opțiunile de personalizare afișate în pagina produsului.</p>'],
            ['Comanda și confirmarea ei', '<p>Adăugarea produselor în coș nu le rezervă. Comanda este transmisă după finalizarea checkout-ului și devine acceptată când primești confirmarea noastră pe un suport durabil. Putem solicita clarificări pentru personalizare sau datele de livrare.</p><ul><li>Ne rezervăm dreptul de a refuza o comandă în caz de eroare evidentă de preț, stoc indisponibil ori date incomplete.</li><li>Dacă un produs nu mai este disponibil, te contactăm pentru înlocuire sau rambursare.</li><li>Produsele personalizate intră în lucru după confirmarea tuturor detaliilor necesare.</li></ul>'],
            ['Prețuri și plată', '<p>Prețurile sunt afișate în lei și includ taxele aplicabile, dacă nu se indică altfel. Costul livrării și eventualele taxe ale metodei de plată sunt prezentate înainte de confirmarea comenzii. Metodele disponibile sunt cele afișate la checkout.</p>'],
            ['Contul și securitatea', '<p>Ești responsabil(ă) pentru corectitudinea datelor furnizate și pentru păstrarea confidențialității parolei. Anunță-ne dacă observi utilizarea neautorizată a contului tău.</p>'],
            ['Proprietate intelectuală', '<p>Textele, fotografiile, elementele grafice, mărcile și structura site-ului nu pot fi copiate sau utilizate comercial fără acordul titularului drepturilor.</p>'],
            ['Sesizări și soluționare', '<p>Pentru orice problemă, scrie-ne mai întâi la <a href="mailto:' . e($email) . '">' . e($email) . '</a>. Încercăm să găsim o soluție amiabilă cât mai repede. Consumatorii se pot adresa și autorităților competente ori mecanismelor legale de soluționare alternativă.</p>'],
            ['Actualizarea termenilor', '<p>Putem actualiza acești termeni atunci când se schimbă funcționalitățile sau cadrul legal. Versiunea aplicabilă unei comenzi este cea disponibilă la data plasării ei.</p>'],
        ],
    ],
    'confidentialitate' => [
        'number' => '02', 'eyebrow' => 'DATELE TALE', 'title' => 'Confidențialitate',
        'lead' => 'Îți explicăm clar ce date folosim, de ce avem nevoie de ele și ce drepturi ai asupra informațiilor tale.',
        'summary' => 'Nu vindem date personale. Folosim numai informațiile necesare pentru magazin, comunicare și obligațiile legale.',
        'sections' => [
            ['Cine prelucrează datele', '<p>Operatorul magazinului online SmileBaby prelucrează datele colectate prin acest site. Pentru orice întrebare privind confidențialitatea ne poți scrie la <a href="mailto:' . e($email) . '">' . e($email) . '</a>.</p>'],
            ['Ce date colectăm', '<ul><li><strong>Cont:</strong> nume, email, telefon și parola stocată în formă securizată.</li><li><strong>Comenzi:</strong> produse, adresă de facturare/livrare, telefon, email, metodă și stare a plății.</li><li><strong>Comunicare:</strong> mesajele trimise prin contact, solicitări și recenzii.</li><li><strong>Newsletter:</strong> adresa de email și dovada consimțământului.</li><li><strong>Date tehnice:</strong> identificatori de sesiune și preferințe cookie necesare funcționării site-ului.</li></ul>'],
            ['De ce le folosim', '<p>Prelucrăm datele pentru executarea comenzii și a contractului, gestionarea contului, suport, prevenirea fraudelor, respectarea obligațiilor fiscale și contabile și, numai cu acordul tău, transmiterea newsletterului sau activarea tehnologiilor opționale.</p>'],
            ['Cui pot fi transmise', '<p>Datele pot fi comunicate strict cât este necesar către furnizori de curierat, procesatori de plăți, servicii de găzduire și email, furnizori IT, contabilitate ori autorități publice atunci când legea o cere. Le solicităm partenerilor să protejeze datele și să le folosească doar pentru serviciul prestat.</p>'],
            ['Cât timp le păstrăm', '<p>Păstrăm datele atât cât este necesar pentru scopul colectării. Documentele comenzilor se păstrează conform termenelor fiscale și contabile, mesajele de suport cât este necesar soluționării, iar datele de newsletter până la retragerea consimțământului. Contul poate fi șters sau anonimizat la cerere, cu excepția datelor pe care legea ne obligă să le păstrăm.</p>'],
            ['Drepturile tale', '<p>În condițiile Regulamentului (UE) 2016/679, poți solicita accesul, rectificarea, ștergerea, restricționarea, portabilitatea sau opoziția la prelucrare și îți poți retrage consimțământul. Ai și dreptul de a depune o plângere la autoritatea de supraveghere.</p><p>Pentru exercitarea drepturilor, contactează-ne folosind adresa de email de mai sus. Putem solicita informații rezonabile pentru verificarea identității.</p>'],
            ['Securitate și actualizări', '<p>Aplicăm măsuri tehnice și organizatorice adecvate pentru protejarea datelor. Niciun sistem nu poate garanta risc zero, însă limităm accesul și colectăm doar ce este necesar. Politica poate fi actualizată; versiunea curentă este publicată permanent aici.</p>'],
        ],
    ],
    'cookies' => [
        'number' => '03', 'eyebrow' => 'ALEGERILE TALE', 'title' => 'Politica de cookies',
        'lead' => 'Cookie-urile și stocarea locală ajută magazinul să îți păstreze sesiunea, coșul, favoritele și preferințele.',
        'summary' => 'Tehnologiile strict necesare funcționează fără acord suplimentar. Cele opționale se activează numai după alegerea ta.',
        'sections' => [
            ['Ce sunt cookie-urile', '<p>Cookie-urile sunt fișiere mici salvate de browser. Site-ul poate utiliza și stocarea locală a browserului, care îndeplinește o funcție asemănătoare pentru preferințe și date păstrate între vizite.</p>'],
            ['Ce folosim în prezent', '<div class="legal-table-wrap"><table class="legal-table"><thead><tr><th>Element</th><th>Scop</th><th>Durată</th></tr></thead><tbody><tr><td><code>smilebaby_session</code></td><td>Sesiune, autentificare și protecția formularelor</td><td>Până la închiderea sesiunii</td></tr><tr><td><code>sb_cookie_consent</code></td><td>Memorează alegerea ta privind cookie-urile</td><td>Până la ștergerea din browser</td></tr><tr><td><code>smilebaby_cart_v1</code></td><td>Păstrează coșul de cumpărături</td><td>Până la ștergere sau finalizarea comenzii</td></tr><tr><td><code>smilebaby_wishlist_v1</code></td><td>Păstrează produsele favorite</td><td>Până la ștergerea din browser</td></tr></tbody></table></div>'],
            ['Categorii de tehnologii', '<ul><li><strong>Necesare:</strong> fac posibile sesiunea, securitatea, coșul și funcțiile cerute de tine.</li><li><strong>Preferințe:</strong> memorează alegeri precum consimțământul.</li><li><strong>Analiză și marketing:</strong> dacă vor fi configurate, se vor activa numai după acceptul tău și vor fi descrise aici.</li></ul>'],
            ['Cum îți schimbi alegerea', '<p>Poți redeschide oricând panoul de preferințe din butonul de mai jos. De asemenea, poți șterge cookie-urile și datele locale din setările browserului; în acest caz, coșul, favoritele sau autentificarea se pot reseta.</p><button class="button button-ghost legal-cookie-action" type="button" data-cookie-settings>DESCHIDE SETĂRILE COOKIE</button>'],
            ['Temei și informații suplimentare', '<p>Pentru tehnologiile neesențiale solicităm acordul în condițiile legislației aplicabile comunicațiilor electronice. Mai multe informații despre datele personale găsești în <a href="/confidentialitate">Politica de confidențialitate</a>.</p>'],
        ],
    ],
    'livrare-si-retur' => [
        'number' => '04', 'eyebrow' => 'DE LA NOI, LA TINE', 'title' => 'Livrare și retur',
        'lead' => 'Informații clare despre pregătirea coletului, costuri, recepție și returnarea produselor eligibile.',
        'summary' => 'Pregătim fiecare comandă cu grijă. Pentru produse disponibile, estimarea curentă este ' . e($delivery) . '.',
        'sections' => [
            ['Pregătirea comenzii', '<p>Produsele aflate în stoc sunt, de regulă, pregătite pentru expediere în intervalul afișat în magazin. Produsele personalizate, realizate la comandă sau care necesită confirmarea detaliilor pot avea un termen diferit, comunicat înainte de începerea lucrului.</p>'],
            ['Costul livrării', '<p>' . ($shippingEnabled ? 'Costul standard configurat este <strong>' . money($shippingCost) . '</strong>.' : 'Livrarea prin magazin este momentan dezactivată.') . ($freeThreshold > 0 ? ' Pentru comenzile eligibile de cel puțin <strong>' . money($freeThreshold) . '</strong>, livrarea este gratuită.' : '') . ' Costul final este întotdeauna afișat în checkout înainte de trimiterea comenzii.</p>'],
            ['Expediere și recepție', '<ul><li>Vei primi informații de urmărire atunci când coletul este predat curierului, dacă serviciul permite.</li><li>Verifică integritatea ambalajului la primire și semnalează imediat deteriorările vizibile.</li><li>Întârzierile cauzate de curier, vreme sau perioade aglomerate pot modifica estimarea inițială.</li><li>O adresă sau un număr de telefon incorect poate întârzia ori împiedica livrarea.</li></ul>'],
            ['Dreptul de retragere', '<p>Pentru produsele eligibile cumpărate la distanță, consumatorul poate notifica retragerea în termen de 14 zile de la intrarea în posesia fizică a bunului, fără a fi obligat să își motiveze decizia, în condițiile OUG nr. 34/2014.</p><p>Trimite solicitarea la <a href="mailto:' . e($email) . '">' . e($email) . '</a>, menționând numărul comenzii și produsele returnate. Îți vom comunica pașii și adresa de retur.</p>'],
            ['Costul returului', '<p>' . ($returnShippingCost > 0 ? 'Costul configurat pentru transportul de retur organizat prin serviciul indicat de SmileBaby este <strong>' . money($returnShippingCost) . '</strong>.' : 'Transportul de retur organizat prin serviciul indicat de SmileBaby este <strong>gratuit</strong>.') . ' Dacă alegi un alt curier, costul poate fi diferit și este comunicat de transportator. Pentru un produs greșit, deteriorat sau neconform, costurile se soluționează potrivit legii și situației concrete.</p>'],
            ['Produse care nu pot fi returnate', '<p>Conform art. 16 din OUG nr. 34/2014, dreptul de retragere nu se aplică, între altele, bunurilor confecționate după specificațiile clientului sau personalizate în mod clar și produselor sigilate care nu pot fi returnate din motive de igienă după desigilare. Aceste excepții nu afectează drepturile privind produsele neconforme.</p>'],
            ['Condiția produselor și rambursarea', '<p>Produsele eligibile trebuie returnate fără deteriorări suplimentare și, pe cât posibil, cu accesoriile și ambalajele primite. Consumatorul poate răspunde pentru diminuarea valorii rezultată din manipulări care depășesc ceea ce este necesar pentru verificarea naturii și funcționării produsului.</p><p>Rambursarea se efectuează prin metoda permisă de situație, în termenele legale, după primirea notificării și în condițiile prevăzute de lege. Putem amâna rambursarea până la recepția bunurilor sau furnizarea dovezii expedierii, după caz.</p>'],
            ['Produs greșit, deteriorat sau neconform', '<p>Contactează-ne cât mai repede și trimite numărul comenzii, descrierea problemei și fotografii clare. Vom analiza situația și îți vom propune, după caz, înlocuirea, repararea, reducerea prețului sau rambursarea conform legii.</p>'],
        ],
    ],
];

$officialSources = [
    'termeni-si-conditii' => [['OUG 34/2014 — Portal Legislativ', 'https://legislatie.just.ro/Public/DetaliiDocument/158913']],
    'confidentialitate' => [['Regulamentul (UE) 2016/679 — EUR-Lex', 'https://eur-lex.europa.eu/eli/reg/2016/679/2016-05-04?locale=ro']],
    'cookies' => [['Legea 506/2004 — Portal Legislativ', 'https://legislatie.just.ro/Public/DetaliiDocument/138941']],
    'livrare-si-retur' => [['OUG 34/2014 — Portal Legislativ', 'https://legislatie.just.ro/Public/DetaliiDocument/158913']],
];
$doc = $documents[$page] ?? $documents['termeni-si-conditii'];
?>
<article class="legal-document shell">
    <header class="legal-hero">
        <span class="legal-number"><?= e($doc['number']) ?></span>
        <div><span class="legal-eyebrow"><?= e($doc['eyebrow']) ?></span><h1><?= e($doc['title']) ?></h1><p><?= e($doc['lead']) ?></p></div>
        <small>Actualizat la <?= date('d.m.Y') ?></small>
    </header>
    <div class="legal-layout">
        <aside class="legal-toc">
            <strong>ÎN ACEASTĂ PAGINĂ</strong>
            <nav><?php foreach ($doc['sections'] as $index => $section): ?><a href="#sectiunea-<?= $index + 1 ?>"><span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><?= e($section[0]) ?></a><?php endforeach ?></nav>
            <a class="legal-help" href="/contact"><small>MAI AI ÎNTREBĂRI?</small><strong>Vorbește cu noi</strong><?= icon('arrow') ?></a>
        </aside>
        <div class="legal-content">
            <div class="legal-summary"><span>PE SCURT</span><p><?= e($doc['summary']) ?></p></div>
            <?php foreach ($doc['sections'] as $index => $section): ?>
            <section id="sectiunea-<?= $index + 1 ?>"><span class="legal-section-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><div><h2><?= e($section[0]) ?></h2><?= $section[1] ?></div></section>
            <?php endforeach ?>
            <footer class="legal-source-note"><span>Notă</span><div><p>Acest document descrie regulile magazinului și se interpretează împreună cu legislația aplicabilă. Dacă o prevedere diferă de o normă obligatorie, se aplică norma legală.</p><?php foreach ($officialSources[$page] ?? [] as [$label, $url]): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($label) ?> <?= icon('arrow') ?></a><?php endforeach ?></div></footer>
        </div>
    </div>
</article>
