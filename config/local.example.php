<?php

// Copiază acest fișier ca local.php numai dacă hostingul nu permite variabile de mediu.
// Nu urca local.php în controlul versiunilor.
putenv('APP_URL=https://www.smilebaby.ro');
putenv('APP_ENV=production');
putenv('APP_KEY=GENEREAZA_O_CHEIE_LUNGA_ALEATORIE');
putenv('DB_HOST=localhost');
putenv('DB_DATABASE=smilebaby');
putenv('DB_USERNAME=smilebaby');
putenv('DB_PASSWORD=SCHIMBA_PAROLA');
putenv('SMTP_HOST=smtp.example.ro');
putenv('SMTP_PORT=587');
putenv('SMTP_USERNAME=contact@smilebaby.ro');
putenv('SMTP_PASSWORD=SCHIMBA_PAROLA');
putenv('SMTP_ENCRYPTION=tls');
