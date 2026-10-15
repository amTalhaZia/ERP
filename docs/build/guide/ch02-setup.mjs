import { h2, h3, ui, c, steps, bullets, tip, note, warn, security, table, pre } from './lib.mjs';

export default {
  id: 'ch-setup',
  title: 'System requirements & Setup (Windows)',
  subtitle: 'Naye Windows computer par ERP ko zero se chalane ka mukammal tareeqa — step-by-step.',
  render: () => `
<p class="lead">Yeh chapter technical hai aur us shakhs ke liye hai jo ERP install karega. Har step order mein karein. Aakhir mein aap ke paas teen cheezein chal rahi hongi: <b>MySQL database</b> (port 3306), <b>Laravel API</b> (${c('http://localhost:8000')}) aur <b>Next.js frontend</b> (${c('http://localhost:3000')}).</p>

${h2('setup-arch', '2.1 System ke hisse (architecture)')}
${table(['Hissa', 'Technology', 'Folder', 'Address (development)'], [
  ['Frontend (jo browser mein nazar aata hai)', 'Next.js (React, TypeScript)', c('frontend\\'), c('http://localhost:3000')],
  ['Backend API (business logic, security)', 'Laravel (PHP 8.3)', c('backend\\'), c('http://localhost:8000/api/v1')],
  ['Database', 'MySQL 8.4', c('database\\local\\'), c('127.0.0.1:3306')],
  ['Documentation', '—', c('docs\\'), '—'],
  ['Production templates', 'nginx, supervisor, cron', c('deployment\\'), '—'],
], { caption: 'Project ke folders' })}
<p>Browser sirf frontend se baat karta hai, frontend sirf API se, aur sirf API database se. Database ka password kabhi browser tak nahi jata.</p>

${h2('setup-req', '2.2 System requirements')}
${table(['Cheez', 'Kam az kam', 'Mashwara'], [
  ['Operating system', 'Windows 10 / 11 (64-bit)', 'Windows 11, latest updates'],
  ['RAM', '8 GB', '16 GB (frontend dev server + MySQL + browser)'],
  ['Disk', '5 GB free', 'SSD, 20 GB free'],
  ['Browser', 'Microsoft Edge / Chrome / Firefox (latest)', 'Edge ya Chrome'],
  ['Internet', 'Installation ke waqt zaroori', 'Map tiles (OpenStreetMap) ke liye bhi'],
], { caption: 'Hardware aur software' })}
${table(['Software', 'Version', 'Install command (PowerShell)', 'Check'], [
  ['PHP (x64)', '8.3+', c('winget install PHP.PHP.8.3'), c('php -v')],
  ['Composer', '2.x', c('winget install Composer.Composer'), c('composer -V')],
  ['MySQL Server', '8.4 LTS', c('winget install Oracle.MySQL'), c('mysqld --version')],
  ['Node.js', '20+', c('winget install OpenJS.NodeJS.LTS'), c('node -v')],
], { caption: 'Runtimes — XAMPP ki zaroorat nahi' })}
${note(`<b>XAMPP pehle se installed hai?</b> Us ka bundled PHP 8.0 aur MariaDB 10.4 is ERP ke liye purane hain. Aap XAMPP ka sirf <b>phpMyAdmin</b> database dekhne ke liye use kar sakte hain (Chapter 3), aur XAMPP ki MariaDB ko port <b>3307</b> par shift kar dein (${c('C:\\xampp\\mysql\\bin\\my.ini')}) taake dono servers aapas mein na takrayein.`)}

${h2('setup-install', '2.3 Runtimes install karna')}
${steps([
  `<b>PowerShell</b> kholein (Start menu → "PowerShell"). Normal user kaafi hai.`,
  `Har software ke liye upar wali table ka ${c('winget install …')} command chalayein. Har install ke baad PowerShell window <b>band karke dobara kholein</b> taake PATH update ho jaye.`,
  `Check commands chala kar versions confirm karein: ${c('php -v')}, ${c('composer -V')}, ${c('node -v')}. MySQL ke binaries ${c('C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin')} mein hote hain.`,
  `<b>PHP extensions on karein.</b> ${c('php.exe')} wale folder mein ${c('php.ini-development')} ko copy karke ${c('php.ini')} naam rakhein, phir neeche wali lines set karein (shuru ka ${c(';')} hata dein).`,
  `${c('php -m')} chala kar confirm karein ke saari extensions list mein hain — khaas taur par ${c('pdo_mysql')}.`,
])}
${pre(`extension_dir = "C:/path/to/php/ext"     ; absolute path, forward slashes
extension=bcmath
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 25M
date.timezone = UTC`, 'php.ini')}
${warn(`Agar baad mein ${c('db:check')} "could not find driver" bataye to iska matlab hai ${c('pdo_mysql')} extension on nahi hui. ${c('php --ini')} se dekhein ke PHP kaunsi ${c('php.ini')} file parh raha hai.`)}

${h2('setup-db', '2.4 Local MySQL database banana')}
<p>Project apne andar aik chhota, alag MySQL data folder use karta hai (${c('database\\local\\data')}) — koi Windows service nahi, admin rights nahi. Yeh sirf <b>development</b> ke liye hai. Project ke root folder (${c('C:\\Users\\…\\ERP')}) mein PowerShell kholein:</p>
${steps([
  `<b>Pehli dafa initialise karein:</b> ${c('.\\database\\local\\init.ps1')} — data directory banti hai, aur ${c('root@localhost')} account (khaali password, sirf localhost) ban jata hai.`,
  `<b>Server start karein:</b> ${c('.\\database\\local\\start.ps1 -Background')} — MySQL background mein ${c('127.0.0.1:3306')} par chalne lagta hai. (${c('-Background')} ke baghair chalayein to window khuli rakhni hogi; ${c('Ctrl+C')} se band.)`,
  `<b>App database aur user banayein:</b> ${c('.\\database\\local\\create-app-db.ps1')} — databases ${c('erp')} aur ${c('erp_test')} aur least-privilege user ${c('erp_app@localhost')} (random password ke sath) banta hai, aur ${c('.env')} mein paste karne wali lines print hoti hain.`,
  `Print hone wali ${c('DB_*')} lines kahin copy kar lein — agle step mein chahiye hongi.`,
  `Server band karna ho to: ${c('.\\database\\local\\stop.ps1')}.`,
])}
${pre(`PS> .\\database\\local\\create-app-db.ps1

Database 'erp' and 'erp_test' ready. Paste into backend/.env:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp_app
DB_PASSWORD=<24 characters, randomly generated>
DB_SSL_CA=`, 'Expected output')}
${tip(`${c('create-app-db.ps1')} dobara chalana safe hai (idempotent) — lekin har dafa user ka <b>naya password</b> set karta hai, is liye phir ${c('.env')} bhi update karein.`)}

${h2('setup-backend', '2.5 Backend (Laravel API) configure karna')}
${steps([
  `${c('cd backend')}`,
  `Dependencies install karein: ${c('composer install')}`,
  `Environment file banayein: ${c('copy .env.example .env')}`,
  `${c('.env')} ko Notepad/VS Code mein kholein aur ${c('DB_*')} lines (pichle step wali) paste karein.`,
  `Application key generate karein: ${c('php artisan key:generate')} — yeh ${c('APP_KEY')} set karta hai (encryption ke liye; kabhi share na karein).`,
  `Connection check karein: ${c('php artisan db:check')}`,
  `Database schema aur permission catalogue banayein: ${c('php artisan migrate --seed')}`,
  `Database self-test chalayein: ${c('php artisan db:selftest')}`,
  `<i>(Sirf testing ke liye, optional)</i> demo companies: ${c('php artisan db:seed --class=DemoSeeder')} — Chapter 4.5 dekhein.`,
  `API start karein: ${c('php artisan serve')} → ${c('http://localhost:8000')}`,
])}
${h3('Important .env variables')}
${table(['Variable', 'Matlab', 'Development', 'Production'], [
  [c('APP_ENV'), 'Mahol (environment)', c('local'), c('production')],
  [c('APP_DEBUG'), 'Errors mein technical detail dikhana', c('true'), `<b>${c('false')}</b> (lazmi)`],
  [c('APP_KEY'), 'Encryption key', c('key:generate'), 'Unique, secret'],
  [c('APP_URL'), 'API ka address', c('http://localhost:8000'), c('https://api.example.com')],
  [c('FRONTEND_URL'), 'Frontend ka address (reset links, CORS)', c('http://localhost:3000'), c('https://erp.example.com')],
  [c('CORS_ALLOWED_ORIGINS'), 'Kaun se origins API call kar sakte hain (comma list, wildcard nahi)', c('http://localhost:3000'), c('https://erp.example.com')],
  [c('SANCTUM_STATEFUL_DOMAINS'), 'Kin hosts ko cookie login milta hai', c('localhost:3000,127.0.0.1:3000'), c('erp.example.com')],
  [c('SESSION_DOMAIN'), 'Cookie ka domain (frontend + API dono ka)', c('localhost'), c('.example.com')],
  [c('SESSION_SECURE_COOKIE'), 'Cookie sirf HTTPS par', c('false'), `<b>${c('true')}</b>`],
  [c('SESSION_LIFETIME'), 'Kitne minute idle rehne par session khatam', c('120'), c('60–120')],
  [c('AUTH_PASSWORD_TIMEOUT'), 'Password re-confirm kitni der valid (seconds)', c('900'), c('900')],
  [`${c('DB_HOST')} … ${c('DB_PASSWORD')}`, 'Database connection', 'create-app-db.ps1 se', 'Hosted DB ke credentials'],
  [c('DB_SSL_CA'), 'Hosted DB ka TLS CA certificate path', '(khaali)', c('storage/certs/…pem')],
  [c('MAIL_MAILER'), 'Emails (password reset, invitations)', c('log'), 'Real SMTP / API'],
  [c('QUEUE_CONNECTION'), 'Background jobs (exports, emails)', c('database'), c('database') + ' / ' + c('redis') + ' + worker'],
  [c('ERP_ALLOW_ADMIN_BOOTSTRAP'), 'Production mein pehla admin banane ki ijazat', c('false'), `${c('true')} sirf aik dafa`],
], { caption: 'backend\\.env ke aham variables', compact: true })}
${note(`${c('MAIL_MAILER=log')} hone par password-reset aur invitation emails bheji nahi jatin — balke ${c('backend\\storage\\logs\\laravel.log')} mein likhi jati hain. Development mein link wahin se copy karein.`)}
${h3('db:check aur db:selftest ka expected output')}
${pre(`PS> php artisan db:check

 Connection ............................................. mysql
 Host ........................................... 127.0.0.1:3306
 Database ................................................. erp
 User ................................................. erp_app
 TLS CA ........................................ not configured
 Server version ......................................... 8.4.x
 TLS cipher ................................. none (plaintext)
 Latency ................................................ 5.6 ms
 Tables ................................................... 104

 INFO  Database connection OK.`, 'php artisan db:check')}
${pre(`PS> php artisan db:selftest

 1. Schema: tables created with foreign key ................ PASS
 2. Create: inserted row #1 ................................ PASS
 3. Read: row read back with exact decimal (1234.5678) ..... PASS
 4. Update: value changed .................................. PASS
 5. Foreign key: orphan insert rejected .................... PASS
 6. Transaction: rollback restored previous value .......... PASS
 7. Delete: cascade removed child rows ..................... PASS
 8. Strict SQL mode active (...) ........................... PASS

 INFO  All database self-tests passed.`, 'php artisan db:selftest')}
<p>${c('db:selftest')} sirf temporary tables use karta hai — asli data ko haath nahi lagata. Koi bhi line <b>FAIL</b> ho to aage na barhein; troubleshooting table (2.8) dekhein.</p>

${h2('setup-frontend', '2.6 Frontend (Next.js) configure karna')}
${steps([
  `Nayi PowerShell window kholein aur ${c('cd frontend')}`,
  `Packages install karein: ${c('npm install')}`,
  `Environment file: ${c('copy .env.example .env.local')}`,
  `${c('.env.local')} mein ${c('NEXT_PUBLIC_API_URL=http://localhost:8000')} confirm karein.`,
  `Dev server start karein: ${c('npm run dev')} → browser mein ${c('http://localhost:3000')} kholein. Login page nazar aana chahiye.`,
])}
${table(['Variable', 'Matlab'], [
  [c('NEXT_PUBLIC_API_URL'), `API ka origin, maslan ${c('http://localhost:8000')}`],
  [c('NEXT_PUBLIC_MAP_TILE_URL'), 'Optional — apna map tile server (default OpenStreetMap, sirf halka use, attribution zaroori)'],
  [c('NEXT_PUBLIC_MAP_ATTRIBUTION'), 'Custom tile server ki attribution HTML'],
], { caption: 'frontend\\.env.local' })}
${security(`${c('NEXT_PUBLIC_*')} wali har value <b>browser tak pohanchti hai</b>. In mein kabhi password, API key ya koi secret na rakhein.`)}

${h2('setup-daily', '2.7 Roz ka start: start-dev.ps1')}
<p>Pehli dafa setup ke baad, har roz sirf aik command kaafi hai. Project root mein:</p>
${pre(`PS> .\\start-dev.ps1
Starting…
  API       http://localhost:8000/api/v1/health
  Frontend  http://localhost:3000
  DB GUI    http://localhost/phpmyadmin (XAMPP Apache) or DBeaver → 127.0.0.1:3306`, 'Project root')}
${bullets([
  'MySQL background mein start hota hai (agar pehle se chal raha ho to skip).',
  'Do nayi PowerShell windows khulti hain: aik mein API (port 8000), dusri mein frontend (port 3000).',
  `Band karna: dono windows band karein, phir ${c('.\\database\\local\\stop.ps1')}.`,
])}
${h3('Background workers (exports, emails, scheduled jobs)')}
${pre(`php artisan queue:work --tries=3     # exports, emails, notifications
php artisan schedule:work            # dev; production mein cron:  * * * * * php artisan schedule:run`, 'backend')}
<p>Scheduler roz ${c('inventory:check-reorder')} (low-stock alerts) aur ${c('erp:prune')} (purana operational data retention ke mutabiq saaf) chalata hai. Bari reports ki export queue ke zariye banti hai — worker na chal raha ho to export "queued" hi reh jayega.</p>

${h2('setup-trouble', '2.8 Setup troubleshooting')}
${table(['Error / symptom', 'Wajah', 'Hal'], [
  [c('SQLSTATE[HY000] [2002]') + ' connection refused', 'MySQL nahi chal raha, ya ghalat host/port', `${c('.\\database\\local\\start.ps1 -Background')} chalayein; ${c('DB_HOST')}/${c('DB_PORT')} check karein.`],
  [c('[1045] Access denied'), 'Ghalat DB_USERNAME / DB_PASSWORD', `${c('create-app-db.ps1')} dobara chala kar nayi lines ${c('.env')} mein paste karein, phir ${c('php artisan config:clear')}.`],
  [c('[1044] Access denied … to database'), 'User ko is DB par privileges nahi', 'GRANT dobara chalayein (create-app-db.ps1).'],
  [c('[1049] Unknown database'), 'Database bana hi nahi', `${c('create-app-db.ps1')} ya ${c('CREATE DATABASE erp')}.`],
  [c('[2026] SSL connection error'), 'Hosted provider TLS maangta hai', `${c('DB_SSL_CA')} mein downloaded CA file ka path dein (Chapter 3).`],
  [c('could not find driver'), c('pdo_mysql') + ' extension off', `${c('php.ini')} mein ${c('extension=pdo_mysql')} on karein; ${c('php -m')} se confirm.`],
  ['Login page khulta hai lekin sign in par error / 419', 'CORS / cookie domain mismatch', `${c('FRONTEND_URL')}, ${c('SANCTUM_STATEFUL_DOMAINS')}, ${c('SESSION_DOMAIN')} check karein; browser mein ${c('localhost')} hi use karein (127.0.0.1 aur localhost mix na karein).`],
  ['"Too many attempts" login par', 'Rate limit (5 tries/minute)', '1 minute intezar karein.'],
  ['Port 3000 / 8000 already in use', 'Pichla server abhi chal raha hai', 'Purani window band karein ya process end karein.'],
], { caption: 'Aam masail aur un ka hal', compact: true })}
${tip(`Kisi bhi DB masle mein sab se pehle ${c('php artisan db:check')} chalayein — yeh error ke sath <b>Hint</b> line bhi print karta hai, aur password kabhi print nahi karta.`)}
`,
};
