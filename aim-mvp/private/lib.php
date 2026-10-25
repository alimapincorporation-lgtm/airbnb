<?php
declare(strict_types=1);
/* AIM-AirBnB server library: database, validation, pricing, booking rules, email. */

const BLOCKING = ['awaiting_payment', 'payment_submitted', 'confirmed'];

function cfg(?string $k = null)
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        if (!is_file($f)) {
            fail(500, 'Server is not configured. Copy private/config.sample.php to private/config.php.');
        }
        $c = require $f;
        date_default_timezone_set($c['timezone'] ?? 'Africa/Kampala');
    }
    return $k === null ? $c : ($c[$k] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $pdo = new PDO($c['dsn'], $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $code, string $msg): void
{
    out(['error' => $msg], $code);
}

function body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $j = json_decode($raw === false ? '' : $raw, true);
        $b = is_array($j) ? $j : [];
    }
    return $b;
}

function now_iso(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
function uid(): string { return bin2hex(random_bytes(8)); }
function today(): string { cfg(); return date('Y-m-d'); }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_assoc(array $a): bool { return $a !== [] && array_keys($a) !== range(0, count($a) - 1); }
function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); }

function clean($v, int $max = 200): string
{
    if (!is_string($v) && !is_int($v) && !is_float($v)) {
        return '';
    }
    $s = trim((string)$v);
    $s = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return mb_substr($s, 0, $max);
}

function is_date($d): bool
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    $p = array_map('intval', explode('-', $d));
    return checkdate($p[1], $p[2], $p[0]);
}

function days_between(string $a, string $b): int
{
    return (int)round((strtotime($b . ' UTC') - strtotime($a . ' UTC')) / 86400);
}

function add_days(string $d, int $n): string
{
    return gmdate('Y-m-d', strtotime($d . ' UTC') + $n * 86400);
}

function later(callable $f): void
{
    static $registered = false;
    $GLOBALS['aim_after'][] = $f;
    if (!$registered) {
        $registered = true;
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            foreach ($GLOBALS['aim_after'] as $fn) {
                try {
                    $fn();
                } catch (Throwable $e) {
                    error_log('[aim] after: ' . $e->getMessage());
                }
            }
        });
    }
}

/* ---------- rate limiting ---------- */
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

function throttle_count(string $bucket, int $window): int
{
    q('DELETE FROM hits WHERE t < ?', [time() - 86400]);
    return (int)q('SELECT COUNT(*) c FROM hits WHERE ip=? AND bucket=? AND t > ?', [client_ip(), $bucket, time() - $window])->fetch()['c'];
}
function throttle_hit(string $bucket): void { q('INSERT INTO hits (ip,bucket,t) VALUES (?,?,?)', [client_ip(), $bucket, time()]); }
function throttle(string $bucket, int $max, int $window): void
{
    if (throttle_count($bucket, $window) >= $max) {
        fail(429, 'Too many attempts. Please wait a while and try again.');
    }
    throttle_hit($bucket);
}

/* ---------- settings ---------- */
function defaults(): array
{
    static $d = null;
    if ($d === null) {
        $d = json_decode((string)file_get_contents(__DIR__ . '/defaults.json'), true) ?: [];
    }
    return $d;
}

function settings(): array
{
    $row = q("SELECT v FROM settings WHERE k='main'")->fetch();
    $s = $row ? (json_decode($row['v'], true) ?: []) : [];
    foreach (defaults() as $k => $v) {
        if (!array_key_exists($k, $s)) {
            $s[$k] = $v;
        } elseif (is_array($v) && is_assoc($v) && is_array($s[$k])) {
            $s[$k] = array_merge($v, $s[$k]);
        }
    }
    return $s;
}

function public_settings(): array
{
    $s = settings();
    unset($s['notifyEmail']);
    return $s;
}

function save_settings(array $patch): void
{
    $s = settings();
    $c = [];
    foreach (['brand', 'tagline', 'phone', 'email', 'notifyEmail', 'whatsapp', 'address', 'currency'] as $k) {
        if (array_key_exists($k, $patch)) {
            $c[$k] = clean($patch[$k], 300);
        }
    }
    if (array_key_exists('about', $patch)) {
        $c['about'] = clean($patch['about'], 6000);
    }
    if (isset($c['currency'])) {
        $cur = strtoupper((string)preg_replace('/[^A-Za-z]/', '', $c['currency']));
        $c['currency'] = $cur !== '' ? substr($cur, 0, 3) : 'UGX';
    }
    if (isset($c['brand']) && $c['brand'] === '') {
        $c['brand'] = 'AIM-AirBnB';
    }
    foreach (['email', 'notifyEmail'] as $k) {
        if (!empty($c[$k]) && !filter_var($c[$k], FILTER_VALIDATE_EMAIL)) {
            fail(422, 'Enter a valid email address.');
        }
    }
    $num = ['depositPct' => [1, 100], 'holdHours' => [1, 720], 'serviceFeePct' => [0, 30], 'maxGuests' => [1, 100]];
    foreach ($num as $k => $r) {
        if (array_key_exists($k, $patch) && is_numeric($patch[$k])) {
            $c[$k] = max($r[0], min($r[1], $patch[$k] + 0));
        }
    }
    if (isset($patch['momo']) && is_array($patch['momo'])) {
        $c['momo'] = ['mtn' => clean($patch['momo']['mtn'] ?? '', 30), 'airtel' => clean($patch['momo']['airtel'] ?? '', 30), 'name' => clean($patch['momo']['name'] ?? '', 80)];
    }
    if (isset($patch['hero']) && is_array($patch['hero'])) {
        $c['hero'] = ['eyebrow' => clean($patch['hero']['eyebrow'] ?? '', 120), 'title' => clean($patch['hero']['title'] ?? '', 160), 'text' => clean($patch['hero']['text'] ?? '', 500)];
    }
    if (isset($patch['policies']) && is_array($patch['policies'])) {
        $c['policies'] = [];
        foreach (['privacy', 'terms', 'cancellation'] as $k) {
            $c['policies'][$k] = clean($patch['policies'][$k] ?? '', 12000);
        }
    }
    if (isset($patch['theme']) && is_array($patch['theme'])) {
        $c['theme'] = [];
        foreach (['light', 'dark'] as $k) {
            $v = (string)($patch['theme'][$k] ?? '');
            $c['theme'][$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '';
        }
    }
    if (isset($patch['faq']) && is_array($patch['faq'])) {
        $c['faq'] = [];
        foreach (array_slice($patch['faq'], 0, 60) as $row) {
            if (is_array($row) && isset($row[0], $row[1]) && clean($row[0], 300) !== '') {
                $c['faq'][] = [clean($row[0], 300), clean($row[1], 2000)];
            }
        }
    }
    if (isset($patch['destinations']) && is_array($patch['destinations'])) {
        $c['destinations'] = [];
        foreach (array_slice($patch['destinations'], 0, 40) as $row) {
            if (is_array($row) && clean($row['name'] ?? '', 80) !== '') {
                $c['destinations'][] = ['name' => clean($row['name'], 80), 'blurb' => clean($row['blurb'] ?? '', 300)];
            }
        }
    }
    $s = array_merge($s, $c);
    q("INSERT INTO settings (k, v) VALUES ('main', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [jenc($s)]);
    log_act('Settings updated: ' . implode(', ', array_keys($c)), actor());
}

function actor(): string { return $_SESSION['uname'] ?? 'system'; }
function log_act(string $text, string $by = 'system'): void
{
    try {
        q('INSERT INTO activity (t, actor, text) VALUES (?,?,?)', [gmdate('Y-m-d H:i:s'), mb_substr($by, 0, 60), mb_substr($text, 0, 400)]);
    } catch (Throwable $e) {
        error_log('[aim] activity: ' . $e->getMessage());
    }
}

/* ---------- properties ---------- */
function prop_decode(array $r): array
{
    $p = json_decode($r['data'], true) ?: [];
    $p['id'] = $r['id'];
    $p['slug'] = $r['slug'];
    $p['status'] = $r['status'];
    return $p;
}
function props_all(): array { return array_map('prop_decode', q('SELECT * FROM properties ORDER BY created_at DESC')->fetchAll()); }
function prop_get(string $key, bool $publishedOnly = false): ?array
{
    $r = q('SELECT * FROM properties WHERE id = ? OR slug = ? LIMIT 1', [$key, $key])->fetch();
    if (!$r) {
        return null;
    }
    $p = prop_decode($r);
    return ($publishedOnly && $p['status'] !== 'published') ? null : $p;
}
function prop_public(array $p): array
{
    unset($p['address']);
    $p['blocked'] = array_map(function ($b) { return ['from' => $b['from'], 'to' => $b['to']]; }, $p['blocked'] ?? []);
    return $p;
}

/* ---------- pricing and availability (mirror of js/store.js; the server is authoritative) ---------- */
function night_price(array $p, string $d): float
{
    foreach (($p['seasons'] ?? []) as $s) {
        if (!empty($s['from']) && !empty($s['to']) && $d >= $s['from'] && $d <= $s['to'] && !empty($s['price'])) {
            return (float)$s['price'];
        }
    }
    $w = (int)gmdate('w', strtotime($d . ' UTC'));
    if (!empty($p['weekendPrice']) && ($w === 5 || $w === 6)) {
        return (float)$p['weekendPrice'];
    }
    return (float)($p['price'] ?? 0);
}

function find_promo(string $code, array $p, int $n): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $r = q('SELECT data FROM offers WHERE code = ?', [$code])->fetch();
    $o = $r ? json_decode($r['data'], true) : null;
    if (!$o || empty($o['active'])) {
        return ['error' => 'That code is not valid.'];
    }
    $t = today();
    if ((!empty($o['validFrom']) && $t < $o['validFrom']) || (!empty($o['validTo']) && $t > $o['validTo'])) {
        return ['error' => 'That code has expired.'];
    }
    if (!empty($o['propertyId']) && $o['propertyId'] !== $p['id']) {
        return ['error' => 'That code does not apply to this stay.'];
    }
    if (!empty($o['minNights']) && $n < (int)$o['minNights']) {
        return ['error' => 'That code needs at least ' . (int)$o['minNights'] . ' nights.'];
    }
    return $o;
}

function make_quote(array $p, string $a, string $b, string $code = ''): array
{
    $s = settings();
    $n = days_between($a, $b);
    $q = ['nights' => $n, 'nightly' => [], 'sub' => 0, 'weekly' => 0, 'promo' => null, 'promoAmt' => 0, 'cleaning' => (int)($p['cleaning'] ?? 0), 'fee' => 0, 'total' => 0, 'deposit' => 0, 'error' => ''];
    if ($n <= 0) {
        $q['error'] = 'Choose a check-out after check-in.';
        return $q;
    }
    for ($i = 0; $i < $n; $i++) {
        $pr = (int)round(night_price($p, add_days($a, $i)));
        $q['nightly'][] = $pr;
        $q['sub'] += $pr;
    }
    $wp = (float)($p['weeklyPct'] ?? 0);
    if ($wp > 0 && $n >= 7) {
        $q['weekly'] = (int)round($q['sub'] * $wp / 100);
    }
    $pm = find_promo($code, $p, $n);
    if ($pm && !isset($pm['error'])) {
        $q['promo'] = $pm['code'];
        $base0 = $q['sub'] - $q['weekly'];
        $q['promoAmt'] = ($pm['type'] ?? 'pct') === 'fixed' ? (int)min((float)$pm['value'], $base0) : (int)round($base0 * (float)$pm['value'] / 100);
    }
    $base = $q['sub'] - $q['weekly'] - $q['promoAmt'] + $q['cleaning'];
    $q['fee'] = (int)round($base * (float)$s['serviceFeePct'] / 100);
    $q['total'] = $base + $q['fee'];
    $dp = max(1.0, (float)$s['depositPct']);
    $q['deposit'] = (int)min($q['total'], ceil($q['total'] * $dp / 100 / 1000) * 1000);
    return $q;
}

function stay_conflicts(array $p, string $a, string $b, ?string $ignore = null, bool $lock = false): bool
{
    foreach (($p['blocked'] ?? []) as $x) {
        if ($a <= $x['to'] && $b > $x['from']) {
            return true;
        }
    }
    $sql = "SELECT 1 FROM bookings WHERE property_id = ? AND status IN ('awaiting_payment','payment_submitted','confirmed') AND check_in < ? AND check_out > ? AND id <> ? LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    return (bool)q($sql, [$p['id'], $b, $a, $ignore ?? ''])->fetch();
}

function validate_stay(array $p, string $a, string $b, int $g, ?string $ignore = null, bool $lock = false): string
{
    $n = days_between($a, $b);
    if ($n <= 0) return 'Choose a check-out after check-in.';
    if ($a < today()) return 'Check-in cannot be in the past.';
    $min = (int)($p['minStay'] ?? 1);
    $max = (int)($p['maxStay'] ?? 0);
    if ($min > 0 && $n < $min) return 'Minimum stay is ' . $min . ' night' . ($min > 1 ? 's' : '') . '.';
    if ($max > 0 && $n > $max) return 'Maximum stay is ' . $max . ' nights.';
    if ($g > (int)($p['guests'] ?? 1)) return 'This home sleeps up to ' . (int)$p['guests'] . ' guests.';
    if (stay_conflicts($p, $a, $b, $ignore, $lock)) return 'Those dates are not available. Try different dates.';
    return '';
}

/* ---------- bookings ---------- */
function booking_decode(array $r): array
{
    $b = json_decode($r['data'], true) ?: [];
    $b['id'] = $r['id'];
    $b['ref'] = $r['ref'];
    $b['status'] = $r['status'];
    return $b;
}

function booking_find(string $key): ?array
{
    $key = trim($key);
    $r = q('SELECT * FROM bookings WHERE ref = ? OR id = ? LIMIT 1', [strtoupper($key), $key])->fetch();
    return $r ? $r : null;
}

function booking_save(array $b): void
{
    $hold = ($b['status'] === 'awaiting_payment' && !empty($b['holdExpires'])) ? gmdate('Y-m-d H:i:s', (int)strtotime($b['holdExpires'])) : null;
    q('UPDATE bookings SET status = ?, check_in = ?, check_out = ?, hold_until = ?, data = ? WHERE id = ?', [$b['status'], $b['in'], $b['out'], $hold, jenc($b), $b['id']]);
}

function guest_view(array $b): array
{
    unset($b['adminNote'], $b['token']);
    return $b;
}

function new_ref(): string
{
    $A = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $r = 'AIM-';
        for ($i = 0; $i < 6; $i++) {
            $r .= $A[random_int(0, strlen($A) - 1)];
        }
    } while (q('SELECT 1 FROM bookings WHERE ref = ?', [$r])->fetch());
    return $r;
}

function hold_until_iso(): string { return gmdate('Y-m-d\TH:i:s\Z', time() + (int)settings()['holdHours'] * 3600); }

function booking_create(array $d, bool $admin = false): array
{
    $p = prop_get((string)($d['propertyId'] ?? ''), !$admin);
    if (!$p) {
        return ['error' => 'Property not found.'];
    }
    $a = $d['in'] ?? '';
    $b = $d['out'] ?? '';
    if (!is_date($a) || !is_date($b)) {
        return ['error' => 'Choose valid dates.'];
    }
    $g = max(1, min(50, (int)($d['guests'] ?? 1)));
    $gd = is_array($d['guest'] ?? null) ? $d['guest'] : [];
    $name = clean($gd['name'] ?? '', 80);
    $phone = clean($gd['phone'] ?? '', 30);
    $email = clean($gd['email'] ?? '', 120);
    $notes = clean($gd['notes'] ?? '', 500);
    if ($name === '' || strlen((string)preg_replace('/\D/', '', $phone)) < 9) {
        return ['error' => 'Enter your name and a valid phone number.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Enter a valid email address.'];
    }
    if (!$admin && $email === '') {
        return ['error' => 'Enter your email address.'];
    }
    $method = (($d['method'] ?? 'mtn') === 'airtel') ? 'airtel' : 'mtn';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM properties WHERE id = ? FOR UPDATE', [$p['id']]);
        $err = validate_stay($p, $a, $b, $g, null, true);
        if ($err !== '') {
            $pdo->rollBack();
            return ['error' => $err];
        }
        $quote = make_quote($p, $a, $b, (string)($d['promo'] ?? ''));
        $instant = ($p['bookingMode'] ?? 'instant') === 'instant' || $admin;
        $status = $admin ? 'confirmed' : ($instant ? 'awaiting_payment' : 'pending');
        $now = now_iso();
        $paid = $admin ? max(0, (int)($d['paid'] ?? 0)) : 0;
        $id = uid();
        $ref = new_ref();
        $token = bin2hex(random_bytes(16));
        $holdIso = ($status === 'awaiting_payment') ? hold_until_iso() : '';
        $text = $admin ? 'Booking created by admin' : ($instant ? 'Booking created (instant book). Awaiting payment' : 'Booking request sent. Awaiting host approval');
        $bk = [
            'id' => $id, 'ref' => $ref, 'propertyId' => $p['id'], 'propertyName' => $p['name'], 'in' => $a, 'out' => $b,
            'nights' => $quote['nights'], 'guests' => $g, 'guest' => ['name' => $name, 'phone' => $phone, 'email' => $email, 'notes' => $notes],
            'pricing' => $quote, 'mode' => $p['bookingMode'] ?? 'instant', 'cancellation' => $p['cancellation'] ?? 'moderate', 'status' => $status,
            'payment' => ['method' => $method, 'amountDue' => $quote['deposit'], 'amountPaid' => $paid, 'txnId' => '', 'verified' => $admin],
            'holdExpires' => $holdIso, 'createdAt' => $now, 'source' => $admin ? 'admin' : 'website',
            'history' => [['t' => $now, 'text' => $text, 'by' => $admin ? 'admin' : 'guest']],
        ];
        q('INSERT INTO bookings (id, ref, property_id, check_in, check_out, status, token, hold_until, created_at, data) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $id, $ref, $p['id'], $a, $b, $status, $token, $holdIso !== '' ? gmdate('Y-m-d H:i:s', (int)strtotime($holdIso)) : null, gmdate('Y-m-d H:i:s'), jenc($bk),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    log_act('New booking ' . $ref . ' for ' . $p['name'], $admin ? actor() : 'guest');
    notify($admin ? 'admin_created' : 'created', $bk, $token);
    return ['booking' => $bk, 'token' => $token];
}

function refund_for(array $b, bool $byHost): int
{
    $paid = (int)($b['payment']['amountPaid'] ?? 0);
    if ($paid <= 0) return 0;
    if ($byHost) return $paid;
    $days = days_between(today(), $b['in']);
    $pol = $b['cancellation'] ?? 'moderate';
    if ($pol === 'flexible') {
        $first = $b['nights'] > 0 ? (int)round(($b['pricing']['sub'] ?? 0) / $b['nights']) : 0;
        return $days >= 1 ? $paid : max(0, $paid - $first);
    }
    if ($pol === 'moderate') return $days >= 5 ? $paid : ($days >= 1 ? (int)round($paid / 2) : 0);
    return $days >= 7 ? (int)round($paid / 2) : 0;
}

/* Apply one action inside a row lock. $by: admin username, 'guest' or 'system'. */
function booking_act(string $id, string $action, array $x, string $by, bool $isGuest = false): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = q('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$r) {
            $pdo->rollBack();
            return ['error' => 'Booking not found.'];
        }
        $b = booking_decode($r);
        $st = $b['status'];
        $now = now_iso();
        $note = function (string $t) use (&$b, $now, $by) { $b['history'][] = ['t' => $now, 'text' => $t, 'by' => $by]; };
        $deny = function () use ($pdo) { $pdo->rollBack(); return ['error' => 'That action is not available for this booking right now.']; };
        $event = null;
        switch ($action) {
            case 'approve':
                if ($st !== 'pending') return $deny();
                $p = prop_get($b['propertyId']);
                if (!$p || stay_conflicts($p, $b['in'], $b['out'], $b['id'], true)) { $pdo->rollBack(); return ['error' => 'Dates now conflict with another booking or block.']; }
                $b['status'] = 'awaiting_payment';
                $b['holdExpires'] = hold_until_iso();
                $note('Request approved. Awaiting payment');
                $event = 'approved';
                break;
            case 'decline':
                if ($st !== 'pending') return $deny();
                $b['status'] = 'declined';
                $b['declineReason'] = clean($x['reason'] ?? '', 300);
                $note('Request declined' . ($b['declineReason'] !== '' ? ': ' . $b['declineReason'] : ''));
                $event = 'declined';
                break;
            case 'submitPayment':
                if ($st !== 'awaiting_payment') return $deny();
                $txn = strtoupper(clean($x['txnId'] ?? '', 40));
                if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{4,39}$/', $txn)) { $pdo->rollBack(); return ['error' => 'Enter the transaction ID exactly as shown in your SMS (letters and numbers).']; }
                $dup = q("SELECT ref FROM bookings WHERE id <> ? AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.payment.txnId')) = ? LIMIT 1", [$id, $txn])->fetch();
                if ($dup) { $pdo->rollBack(); return ['error' => 'That transaction ID has already been used. Please check it or contact us.']; }
                $b['payment']['txnId'] = $txn;
                $b['payment']['method'] = (($x['method'] ?? '') === 'airtel') ? 'airtel' : 'mtn';
                $b['payment']['payerPhone'] = clean($x['phone'] ?? '', 30);
                $b['payment']['submittedAt'] = $now;
                $b['status'] = 'payment_submitted';
                $note('Payment reference submitted: ' . $txn);
                $event = 'payment_submitted';
                break;
            case 'verifyPayment':
                if (!in_array($st, ['awaiting_payment', 'payment_submitted'], true)) return $deny();
                $amt = (int)round((float)($x['amount'] ?? 0));
                if ($amt <= 0) { $pdo->rollBack(); return ['error' => 'Enter the amount received.']; }
                $b['payment']['amountPaid'] = $amt;
                $b['payment']['verified'] = true;
                $b['payment']['verifiedAt'] = $now;
                $b['status'] = 'confirmed';
                $b['holdExpires'] = '';
                $note('Payment verified (' . money($amt) . '). Booking confirmed');
                $event = 'confirmed';
                break;
            case 'rejectPayment':
                if ($st !== 'payment_submitted') return $deny();
                $b['status'] = 'awaiting_payment';
                $b['payment']['txnId'] = '';
                $b['holdExpires'] = hold_until_iso();
                $reason = clean($x['reason'] ?? '', 300);
                $note('Payment could not be verified' . ($reason !== '' ? ': ' . $reason : '') . '. Guest asked to resubmit');
                $event = 'payment_rejected';
                break;
            case 'confirm':
                if (!in_array($st, ['awaiting_payment', 'payment_submitted'], true)) return $deny();
                $b['status'] = 'confirmed';
                $b['holdExpires'] = '';
                $note('Booking confirmed by admin');
                $event = 'confirmed';
                break;
            case 'recordPayment':
                if (!in_array($st, ['confirmed', 'completed'], true)) return $deny();
                $amt = (int)round((float)($x['amount'] ?? 0));
                if ($amt <= 0) { $pdo->rollBack(); return ['error' => 'Enter the amount received.']; }
                $b['payment']['amountPaid'] = (int)($b['payment']['amountPaid'] ?? 0) + $amt;
                $b['payment']['verified'] = true;
                $note('Payment recorded: ' . money($amt) . (clean($x['note'] ?? '', 100) !== '' ? ' (' . clean($x['note'], 100) . ')' : ''));
                break;
            case 'cancel':
                if (!in_array($st, ['pending', 'awaiting_payment', 'payment_submitted', 'confirmed'], true)) return $deny();
                if ($isGuest && $b['in'] < today()) return $deny();
                $b['refundDue'] = refund_for($b, !$isGuest);
                $b['status'] = 'cancelled';
                $b['cancelledBy'] = $isGuest ? 'guest' : 'host';
                $b['cancelReason'] = clean($x['reason'] ?? '', 300);
                $b['holdExpires'] = '';
                $note('Cancelled by ' . $b['cancelledBy'] . '. Refund due: ' . money($b['refundDue']));
                $event = 'cancelled';
                break;
            case 'refund':
                if ($st !== 'cancelled') return $deny();
                $due = (int)($b['refundDue'] ?? 0);
                $amt = (int)round((float)($x['amount'] ?? 0));
                if ($amt <= 0) $amt = max(0, $due - (int)($b['refunded'] ?? 0));
                if ($amt <= 0 || (int)($b['refunded'] ?? 0) + $amt > $due) { $pdo->rollBack(); return ['error' => 'Refund amount is more than what is owed.']; }
                $b['refunded'] = (int)($b['refunded'] ?? 0) + $amt;
                $note('Refund of ' . money($amt) . ' marked as sent');
                break;
            case 'complete':
                if ($st !== 'confirmed') return $deny();
                $b['status'] = 'completed';
                $note('Marked completed');
                break;
            case 'note':
                $b['adminNote'] = clean($x['note'] ?? '', 1000);
                $note('Internal note updated');
                break;
            default:
                $pdo->rollBack();
                return ['error' => 'Unknown action.'];
        }
        booking_save($b);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    log_act($action . ' ' . $b['ref'], $by);
    if ($event !== null) {
        notify($event, $b, (string)(q('SELECT token FROM bookings WHERE id = ?', [$id])->fetch()['token'] ?? ''));
    }
    return ['booking' => $b];
}

/* Expire unpaid holds and complete finished stays. Safe to call often. */
function sweep(): void
{
    $now = gmdate('Y-m-d H:i:s');
    foreach (q("SELECT id FROM bookings WHERE status = 'awaiting_payment' AND hold_until IS NOT NULL AND hold_until < ?", [$now])->fetchAll() as $row) {
        $r = q('SELECT * FROM bookings WHERE id = ?', [$row['id']])->fetch();
        if (!$r || $r['status'] !== 'awaiting_payment') continue;
        $b = booking_decode($r);
        $b['status'] = 'expired';
        $b['holdExpires'] = '';
        $b['history'][] = ['t' => now_iso(), 'text' => 'Hold expired: no payment received', 'by' => 'system'];
        booking_save($b);
        notify('expired', $b, (string)$r['token']);
    }
    foreach (q("SELECT id FROM bookings WHERE status = 'confirmed' AND check_out < ?", [today()])->fetchAll() as $row) {
        $r = q('SELECT * FROM bookings WHERE id = ?', [$row['id']])->fetch();
        if (!$r || $r['status'] !== 'confirmed') continue;
        $b = booking_decode($r);
        $b['status'] = 'completed';
        $b['history'][] = ['t' => now_iso(), 'text' => 'Stay completed', 'by' => 'system'];
        booking_save($b);
    }
}

function maybe_sweep(): void
{
    $r = q("SELECT v FROM settings WHERE k = 'sweep'")->fetch();
    if (!$r || time() - (int)$r['v'] > 60) {
        q("INSERT INTO settings (k, v) VALUES ('sweep', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string)time()]);
        sweep();
    }
}

/* ---------- money, links, email ---------- */
function money($n): string { return settings()['currency'] . ' ' . number_format((float)$n, 0, '.', ','); }
function site_url(): string { return rtrim((string)cfg('site_url'), '/'); }
function intl_phone(string $p): string
{
    $d = (string)preg_replace('/\D/', '', $p);
    if (strlen($d) === 10 && $d[0] === '0') {
        $d = ((string)(cfg('country_code') ?: '256')) . substr($d, 1);
    }
    return $d;
}
function wa_url(string $phone, string $text): string
{
    $n = intl_phone($phone);
    return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode($text);
}
function host_email(): string { $s = settings(); return (string)($s['notifyEmail'] ?: $s['email']); }

function send_mail(string $to, string $subject, string $html): bool
{
    $m = cfg('mail') ?: [];
    $ok = false;
    $info = '';
    if (empty($m['api_key']) || empty($m['from_email'])) {
        $info = 'Mail not configured';
    } else {
        $fromName = (string)($m['from_name'] ?? settings()['brand']);
        if (($m['provider'] ?? 'brevo') === 'resend') {
            $url = 'https://api.resend.com/emails';
            $headers = ['Authorization: Bearer ' . $m['api_key'], 'Content-Type: application/json'];
            $payload = ['from' => $fromName . ' <' . $m['from_email'] . '>', 'to' => [$to], 'subject' => $subject, 'html' => $html];
        } else {
            $url = 'https://api.brevo.com/v3/smtp/email';
            $headers = ['api-key: ' . $m['api_key'], 'Content-Type: application/json', 'accept: application/json'];
            $payload = ['sender' => ['name' => $fromName, 'email' => $m['from_email']], 'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $html];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => jenc($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ok = $code >= 200 && $code < 300;
        $info = $ok ? 'ok' : 'HTTP ' . $code . ' ' . $err . ' ' . substr((string)$res, 0, 200);
    }
    try {
        q('INSERT INTO mail_log (t, to_addr, subject, ok, info) VALUES (?,?,?,?,?)', [gmdate('Y-m-d H:i:s'), mb_substr($to, 0, 150), mb_substr($subject, 0, 200), $ok ? 1 : 0, mb_substr($info, 0, 400)]);
    } catch (Throwable $e) {
        error_log('[aim] mail_log: ' . $e->getMessage());
    }
    return $ok;
}

function mail_html(string $heading, array $lines, ?array $cta = null, ?array $cta2 = null): string
{
    $s = settings();
    $btn = function (array $c, bool $primary) {
        return '<a href="' . h($c[1]) . '" style="display:inline-block;margin:6px 8px 0 0;padding:11px 18px;border-radius:7px;text-decoration:none;font-weight:600;font-size:14px;'
            . ($primary ? 'background:#245A46;color:#fff;' : 'border:1px solid #245A46;color:#245A46;') . '">' . h($c[0]) . '</a>';
    };
    $h = '<div style="background:#f3f3f1;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#20221f"><div style="max-width:560px;margin:auto;background:#fff;border-radius:12px;overflow:hidden">'
        . '<div style="background:#1C4B3B;color:#fff;padding:18px 24px;font-family:Georgia,serif;font-size:20px">' . h($s['brand']) . '</div><div style="padding:24px">'
        . '<h2 style="margin:0 0 14px;font-family:Georgia,serif;font-size:22px">' . h($heading) . '</h2>';
    foreach ($lines as $l) {
        $h .= '<p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#44463f">' . $l . '</p>';
    }
    if ($cta) $h .= $btn($cta, true);
    if ($cta2) $h .= $btn($cta2, false);
    return $h . '</div><div style="padding:14px 24px;background:#fafaf8;font-size:12px;color:#878a83">' . h($s['brand']) . ' · ' . h($s['address']) . '</div></div></div>';
}

function booking_summary_html(array $b): string
{
    return '<strong>' . h($b['propertyName']) . '</strong><br>' . h(date('D j M Y', strtotime($b['in'] . ' UTC'))) . ' to ' . h(date('D j M Y', strtotime($b['out'] . ' UTC')))
        . ' (' . (int)$b['nights'] . ' nights, ' . (int)$b['guests'] . ' guests)<br>Total ' . h(money($b['pricing']['total'])) . ' · To confirm ' . h(money($b['payment']['amountDue'])) . '<br>Reference <strong>' . h($b['ref']) . '</strong>';
}

function notify(string $event, array $b, string $token = ''): void
{
    later(function () use ($event, $b, $token) {
        $s = settings();
        $brand = $s['brand'];
        $guestEmail = (string)($b['guest']['email'] ?? '');
        $link = site_url() . '/booking-confirmation.html?ref=' . rawurlencode($b['ref']) . '&t=' . rawurlencode($token);
        $payLink = site_url() . '/payment.html?ref=' . rawurlencode($b['ref']) . '&t=' . rawurlencode($token);
        $adminLink = site_url() . '/admin/#/bookings';
        $sum = booking_summary_html($b);
        $toHost = function (string $subject, string $heading, array $lines, string $waText = '') use ($b, $adminLink) {
            $to = host_email();
            if ($to === '') return;
            $wa = $waText !== '' ? wa_url((string)$b['guest']['phone'], $waText) : '';
            $lines[] = 'Guest: <strong>' . h($b['guest']['name']) . '</strong> · ' . h($b['guest']['phone']) . ' · ' . h($b['guest']['email']);
            send_mail($to, $subject, mail_html($heading, $lines, ['Open in admin', $adminLink], $wa !== '' ? ['WhatsApp the guest', $wa] : null));
        };
        $toGuest = function (string $subject, string $heading, array $lines, ?array $cta = null) use ($guestEmail) {
            if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) return;
            send_mail($guestEmail, $subject, mail_html($heading, $lines, $cta));
        };
        $waHi = 'Hello ' . $b['guest']['name'] . ', this is ' . $brand . ' about your booking ' . $b['ref'] . '.';
        switch ($event) {
            case 'created':
                if ($b['status'] === 'pending') {
                    $toHost('New booking request ' . $b['ref'], 'New booking request', [$sum, 'Approve or decline it in the admin.'], $waHi);
                    $toGuest('We received your request ' . $b['ref'], 'Request received', ['Thank you, ' . h($b['guest']['name']) . '. The host will reply within a day. You will not pay until the request is approved.', $sum], ['View your booking', $link]);
                } else {
                    $toHost('New booking ' . $b['ref'] . ' (awaiting payment)', 'New instant booking', [$sum, 'Dates are held until payment is received.'], $waHi);
                    $toGuest('Booking ' . $b['ref'] . ': complete your payment', 'Your dates are held', ['Thank you, ' . h($b['guest']['name']) . '. Pay by Mobile Money before the hold ends to secure your stay.', $sum], ['Pay now', $payLink]);
                }
                break;
            case 'admin_created':
                $toGuest('Your booking ' . $b['ref'] . ' is confirmed', 'Booking confirmed', [$sum], ['View your booking', $link]);
                break;
            case 'approved':
                $toGuest('Request approved: pay to confirm ' . $b['ref'], 'Your request was approved', ['Good news. Pay by Mobile Money to confirm your stay before the hold ends.', $sum], ['Pay now', $payLink]);
                break;
            case 'declined':
                $toGuest('Update on your request ' . $b['ref'], 'Request not accepted', ['Sorry, the host could not accept this request.' . (!empty($b['declineReason']) ? ' Reason: ' . h($b['declineReason']) : ''), 'You are welcome to look at other dates or stays.', $sum], ['Browse stays', site_url() . '/properties.html']);
                break;
            case 'payment_submitted':
                $toHost('Verify payment for ' . $b['ref'], 'Payment to verify', [$sum, 'Transaction ID: <strong>' . h($b['payment']['txnId']) . '</strong> via ' . h(strtoupper($b['payment']['method'])) . ' from ' . h($b['payment']['payerPhone'] ?? ''), 'Check it in your Mobile Money statement, then verify in the admin.'], $waHi);
                $toGuest('We received your payment details ' . $b['ref'], 'Payment being verified', ['We have your transaction ID and are verifying it. You will get another email when your booking is confirmed.', $sum], ['View your booking', $link]);
                break;
            case 'payment_rejected':
                $toGuest('Please resubmit your payment ' . $b['ref'], 'We could not verify your payment', ['We could not match that transaction ID. Please check it and submit again, or contact us.', $sum], ['Submit again', $payLink]);
                break;
            case 'confirmed':
                $toGuest('Booking confirmed ' . $b['ref'], 'You are all set', ['Your stay is confirmed. Check-in is from ' . h((prop_get($b['propertyId'])['checkIn'] ?? '14:00')) . '. Keep your reference handy.', $sum], ['View your booking', $link]);
                break;
            case 'cancelled':
                $toHost('Booking cancelled ' . $b['ref'], 'Booking cancelled', [$sum, 'Cancelled by ' . h($b['cancelledBy'] ?? '') . '. Refund due: <strong>' . h(money($b['refundDue'] ?? 0)) . '</strong>.'], $waHi);
                $toGuest('Booking cancelled ' . $b['ref'], 'Your booking was cancelled', [$sum, 'Refund due: <strong>' . h(money($b['refundDue'] ?? 0)) . '</strong>. We will contact you to send it by Mobile Money.'], ['View booking', $link]);
                break;
            case 'expired':
                $toGuest('Your hold expired ' . $b['ref'], 'Your hold has expired', ['We did not receive payment in time, so the dates were released. You are welcome to book again.', $sum], ['Browse stays', site_url() . '/properties.html']);
                break;
        }
    });
}

function notify_enquiry(array $e): void
{
    later(function () use ($e) {
        $to = host_email();
        if ($to === '') return;
        $wa = $e['phone'] !== '' ? wa_url($e['phone'], 'Hello ' . $e['name'] . ', thanks for contacting ' . settings()['brand'] . '.') : '';
        send_mail($to, ($e['kind'] === 'host' ? 'Host enquiry from ' : 'New enquiry from ') . $e['name'], mail_html($e['kind'] === 'host' ? 'New host enquiry' : 'New enquiry', [
            '<strong>' . h($e['name']) . '</strong> · ' . h($e['email']) . ($e['phone'] !== '' ? ' · ' . h($e['phone']) : ''),
            nl2br(h($e['message'])),
        ], ['Open enquiries', site_url() . '/admin/#/enquiries'], $wa !== '' ? ['WhatsApp them', $wa] : null));
    });
}
