import { h2, h3, ui, c, fig, steps, bullets, tip, note, warn, security, table, pre } from './lib.mjs';

export default {
  id: 'ch-database',
  title: 'Database configuration',
  subtitle: 'Local MySQL ya hosted (Aiven) — connection, TLS, data dekhna, backup aur restore.',
  render: () => `
${h2('db-options', '3.1 Do raaste: Local ya Hosted')}
${table(['', 'Option A — Hosted (Aiven)', 'Option B — Local MySQL 8.4'], [
  ['Kuch install karna?', 'Nahi', 'MySQL Community Server (≈ 300 MB)'],
  ['Offline chalta hai?', 'Nahi', 'Haan'],
  ['Kharcha', 'Free tier (1 GB) / paid', 'Free'],
  ['TLS (encryption)', 'Lazmi', 'Optional (localhost)'],
  ['Kis ke liye behtar', 'Demo kahin se bhi, team ka shared DB', 'Roz ki development, tests'],
], { caption: 'Database options' })}
<p>Dono soorton mein configuration sirf ${c('backend\\.env')} mein hoti hai. Local setup Chapter 2.4 mein ho chuka hai; neeche hosted option aur baqi operational kaam hain.</p>

${h2('db-aiven', '3.2 Hosted MySQL (Aiven) TLS ke sath')}
${note('Aiven ka free MySQL plan (1 node, 1 GB storage) development/demo ke liye theek hai, production ke liye nahi — free services lambi inactivity par band ho jati hain aur free tier par automatic backups nahi hote. Shuru karne se pehle Aiven ki current terms check kar lein.')}
${steps([
  `<a href="https://console.aiven.io">console.aiven.io</a> par sign up karein → <b>Create service → MySQL → Free plan</b>, qareeb tareen region chunein. Status <b>Running</b> hone ka intezar karein (2–4 minute).`,
  `App ko ${c('avnadmin')} superuser se <b>na chalayein</b>. Alag database aur user banayein (Users tab ya MySQL shell se) — neeche SQL dekhein.`,
  `Overview tab → <b>CA certificate → Download</b>. File ko ${c('backend\\storage\\certs\\aiven-ca.pem')} par save karein (yeh folder git mein nahi jata).`,
  `${c('backend\\.env')} mein host, port, user, password aur ${c('DB_SSL_CA')} set karein.`,
  `<b>Network access band karein:</b> Overview → <b>Allowed IP addresses</b> mein ${c('0.0.0.0/0')} hata kar sirf apna public IP likhein. IP badle to yahan update karein.`,
  `Verify: ${c('php artisan db:check')} (TLS cipher nazar aana chahiye), ${c('php artisan migrate --force')}, ${c('php artisan db:selftest')}.`,
])}
${pre(`CREATE DATABASE erp CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'erp_app'@'%' IDENTIFIED BY '<strong-random-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      TRIGGER ON erp.* TO 'erp_app'@'%';
FLUSH PRIVILEGES;`, 'Aiven — dedicated database & user')}
${pre(`DB_CONNECTION=mysql
DB_HOST=mysql-xxxx-yyyy.aivencloud.com
DB_PORT=12345
DB_DATABASE=erp
DB_USERNAME=erp_app
DB_PASSWORD=<strong-random-password>
DB_SSL_CA=storage/certs/aiven-ca.pem
DB_SSL_VERIFY=true`, 'backend\\.env (hosted)')}
<p>Health check: ${c('GET http://localhost:8000/api/v1/health/db')} jawab mein sirf status, driver, version, ${c('tls')} aur latency deta hai — host, user ya password <b>kabhi nahi</b>.</p>
${security(`Production mein runtime app user se ${c('UPDATE, DELETE')} on ${c('audit_logs')} revoke karein (Chapter 6) aur reporting ke liye alag SELECT-only user banayein.`)}

${h2('db-view', '3.3 Tables dekhna: phpMyAdmin / DBeaver / System page')}
<p>Kabhi kabhi data ko direct dekhna zaroori hota hai (maslan support ke liye). Teen tareeqe:</p>
${h3('A) DBeaver (recommended)')}
${steps([
  `Install: ${c('winget install dbeaver.dbeaver')}`,
  `<b>New connection → MySQL</b>. Host ${c('127.0.0.1')}, port ${c('3306')}, database ${c('erp')}, user ${c('erp_app')} aur password (${c('.env')} wala).`,
  `Hosted DB ke liye <b>SSL</b> tab mein <b>Use SSL</b> tick karein aur CA certificate = ${c('aiven-ca.pem')}.`,
])}
${h3('B) XAMPP ka phpMyAdmin')}
${steps([
  `XAMPP ki MariaDB ko port ${c('3307')} par shift karein (${c('C:\\xampp\\mysql\\bin\\my.ini')}) taake ERP ka MySQL ${c('3306')} par rahe.`,
  `${c('C:\\xampp\\phpMyAdmin\\config.inc.php')} mein host ${c('127.0.0.1')}, port ${c('3306')} aur ${c('auth_type = cookie')} set karein.`,
  `XAMPP Control Panel se sirf <b>Apache</b> start karein aur ${c('http://localhost/phpmyadmin')} kholein; ${c('erp_app')} se login karein.`,
])}
${h3('C) In-app System page (platform admins)')}
<p>${ui('Platform')} → ${ui('System')} page database tables ki approximate row counts aur size dikhata hai — read-only, koi SQL console nahi, koi credentials nahi.</p>
${fig('platform-system', 'Platform → System: companies, users, database size, free disk aur har table ki row count (read-only).')}
${warn(`<b>Read-only rahein.</b> phpMyAdmin/DBeaver se tables mein direct edit/delete <b>na karein</b>. ERP ke andar har posting ke sath journal entries, stock movements aur audit log jure hote hain — direct SQL se data ki consistency toot jati hai aur audit trail mein record nahi banta. Ghalti theek karni ho to app ke andar wala tareeqa (reverse, void, adjustment) use karein. phpMyAdmin ko kabhi production server par public na karein.`)}

${h2('db-backup', '3.4 Backups aur restore')}
<p>Backup ka matlab hai database ki mukammal copy (SQL file) jo kisi masle mein wapas laayi ja sake. Sath mein ${c('backend\\storage\\app\\private')} folder (uploaded documents, receipts, contracts) ka backup bhi zaroori hai — yeh files database mein nahi hotin.</p>
${h3('Backup lena (Windows, local MySQL)')}
${pre(`$dump = "C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe"
& $dump --single-transaction --routines --triggers -h 127.0.0.1 -P 3306 -u erp_app -p --result-file="D:\\Backups\\erp-$(Get-Date -Format yyyy-MM-dd).sql" erp`, 'PowerShell (password prompt aayega)')}
${h3('Backup lena (production Linux server, nightly)')}
${pre(`mysqldump --single-transaction --routines --triggers --ssl-ca=/srv/erp/backend/storage/certs/ca.pem \\
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_BACKUP_USER" -p"$DB_BACKUP_PASSWORD" erp \\
  | gzip > /backups/erp-$(date +%F).sql.gz`, 'Linux cron job')}
${bullets([
  'Kam az kam <b>14 din</b> ke backups rakhein, aik copy <b>off-site</b> (dusri jagah) rakhein, aur encrypt karein.',
  'Local par Windows <b>Task Scheduler</b> se roz chala sakte hain.',
  'Hosted Aiven free tier par automatic backup nahi hota — khud lein.',
])}
${h3('Restore karna')}
${steps([
  `Restore hamesha pehle aik <b>scratch (test) database</b> mein karke check karein.`,
  `Restore: ${c('mysql -h 127.0.0.1 -u erp_app -p erp_restore < erp-2026-10-15.sql')} (hosted ke liye ${c('--ssl-ca=…')} bhi dein).`,
  `Staging ${c('.env')} ko is database par point karein, phir ${c('php artisan migrate --force')} (agar backup ke baad nayi migrations aayi hon) aur ${c('php artisan db:check')}.`,
  'Sab theek ho to hi production par restore ka faisla karein.',
])}
${tip('Har teen mahine (quarterly) aik <b>restore drill</b> karein — jo backup kabhi restore karke na dekha ho, us par bharosa na karein.')}
${warn(`<b>Production database ko kabhi reset na karein.</b> ${c('php artisan migrate:fresh')}, ${c('migrate:reset')}, ${c('db:wipe')} ya rollback production par <b>sab data mita dete hain</b>. Migration mein masla ho to aik nayi corrective migration likhwayein. Demo seeder bhi production par refuse karta hai — aur aisa hi hona chahiye.`, 'Dhyan dein — kabhi nahi')}
`,
};
