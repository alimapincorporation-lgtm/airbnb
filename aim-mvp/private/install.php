<?php
// Usage:  ADMIN_PASSWORD='a-long-unique-password' php private/install.php [owner-username]
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("Run this from the command line.\n"); }
require __DIR__ . '/lib.php';
$user = $argv[1] ?? 'owner';
$pw = (string)(getenv('ADMIN_PASSWORD') ?: '');
if (strlen($pw) < 10) { fwrite(STDERR, "Set ADMIN_PASSWORD (10+ characters) in the environment.\n"); exit(1); }
foreach (array_filter(array_map('trim', explode(";\n", (string)file_get_contents(__DIR__ . '/schema.sql')))) as $stmt) {
    if (strpos($stmt, '--') === 0 && strpos($stmt, "\n") === false) { continue; }
    db()->exec($stmt);
}
if (!q("SELECT 1 FROM admins WHERE role = 'owner' LIMIT 1")->fetch()) {
    q('INSERT INTO admins (username, pass_hash, role, created_at) VALUES (?,?,?,?)', [$user, password_hash($pw, PASSWORD_DEFAULT), 'owner', gmdate('Y-m-d H:i:s')]);
    echo "Owner account '$user' created.\n";
} else { echo "An owner account already exists; left unchanged.\n"; }
if (!q("SELECT 1 FROM settings WHERE k = 'main'")->fetch()) {
    q("INSERT INTO settings (k, v) VALUES ('main', ?)", [jenc(defaults())]);
    echo "Default settings stored.\n";
}
echo "Done. Sign in at /admin/ and fill in Settings (contact details, notification email, Mobile Money numbers).\n";
