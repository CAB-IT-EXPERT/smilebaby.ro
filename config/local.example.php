<?php

// Copiază acest fișier ca local.php numai dacă hostingul nu permite variabile de mediu.
// Nu urca local.php în controlul versiunilor.
putenv('APP_URL=https://smilebaby.ro');
putenv('APP_ENV=production');
putenv('APP_KEY=GENEREAZA_O_CHEIE_LUNGA_ALEATORIE');
putenv('DB_HOST=localhost');
putenv('DB_DATABASE=smilebaby');
putenv('DB_USERNAME=smilebaby');
putenv('DB_PASSWORD=SCHIMBA_PAROLA');
putenv('SMTP_HOST=mail.smilebaby.ro');
putenv('SMTP_PORT=465');
putenv('SMTP_ENCRYPTION=ssl');
putenv('SMTP_SITE_USERNAME=site@smilebaby.ro');
putenv('SMTP_SITE_PASSWORD=SCHIMBA_PAROLA');
putenv('SMTP_CONTACT_USERNAME=contact@smilebaby.ro');
putenv('SMTP_CONTACT_PASSWORD=SCHIMBA_PAROLA');
putenv('MAIL_ORDER_RECIPIENT=contact@smilebaby.ro');
putenv('STRIPE_SECRET_KEY=sk_live_SCHIMBA_CHEIA');
putenv('STRIPE_PUBLISHABLE_KEY=pk_live_SCHIMBA_CHEIA');
putenv('STRIPE_WEBHOOK_SECRET=whsec_SE_COMPLETEAZA_DUPA_CREAREA_ENDPOINTULUI');
putenv('STRIPE_WEBHOOK_URL=https://smilebaby.ro/plati/callback/stripe');
