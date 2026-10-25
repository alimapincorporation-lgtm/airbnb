<?php
declare(strict_types=1);
require __DIR__ . '/../../private/lib.php';

set_exception_handler(function (Throwable $e) {
    error_log('[aim] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    fail(500, cfg('dev') ? $e->getMessage() : 'Something went wrong. Please try again.');
});
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$pos = strpos($uri, '/api/');
$route = trim($pos === false ? '' : substr($uri, $pos + 5), '/');
$seg = $route === '' ? [] : explode('/', $route);
$b = body();
maybe_sweep();

/* ---------- session helpers ---------- */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string)(cfg('session_name') ?: 'aimadmin'));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
    if (isset($_SESSION['last']) && time() - (int)$_SESSION['last'] > 28800) {
        $_SESSION = [];
    }
    $_SESSION['last'] = time();
}

function admin_user(): ?array
{
    session_boot();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $r = q('SELECT id, username, role FROM admins WHERE id = ?', [$_SESSION['uid']])->fetch();
    if (!$r) {
        return null;
    }
    $_SESSION['uname'] = $r['username'];
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $r;
}

function admin_guard(bool $owner = false): array
{
    $u = admin_user();
    if (!$u) {
        fail(401, 'Please sign in.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        $tok = $_SERVER['HTTP_X_CSRF'] ?? '';
        if (!is_string($tok) || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $tok)) {
            fail(403, 'Session check failed. Reload the page and try again.');
        }
    }
    if ($owner && $u['role'] !== 'owner') {
        fail(403, 'Only the owner can do that.');
    }
    return $u;
}

function guest_booking_row(string $ref, string $tok): array
{
    $row = booking_find($ref);
    if (!$row || $tok === '' || !hash_equals((string)$row['token'], $tok)) {
        throttle('badtok', 30, 900);
        fail(404, 'Booking not found. Check your link or use Find my booking.');
    }
    return $row;
}

function doc_rows(string $table, string $order = 'created_at DESC', int $limit = 3000): array
{
    $out = [];
    foreach (q("SELECT * FROM $table ORDER BY $order LIMIT $limit")->fetchAll() as $r) {
        $d = json_decode($r['data'], true) ?: [];
        $d['id'] = $r['id'];
        if (isset($r['status'])) {
            $d['status'] = $r['status'];
        }
        $out[] = $d;
    }
    return $out;
}

/* ---------- collection savers (admin) ---------- */
function clean_prop(array $in, ?array $old): ?array
{
    $name = clean($in['name'] ?? '', 120);
    $id = clean($in['id'] ?? '', 32);
    if ($name === '' || $id === '') {
        return null;
    }
    $n = function ($k, $d = 0) use ($in) { return (isset($in[$k]) && is_numeric($in[$k])) ? $in[$k] + 0 : $d; };
    $list = function ($k, $max, $len) use ($in) {
        $o = [];
        foreach (array_slice(is_array($in[$k] ?? null) ? $in[$k] : [], 0, $max) as $v) {
            $s = clean($v, $len);
            if ($s !== '') $o[] = $s;
        }
        return $o;
    };
    $imgs = [];
    foreach (array_slice(is_array($in['images'] ?? null) ? $in['images'] : [], 0, 20) as $u) {
        $u = clean($u, 500);
        if (preg_match('#^(/uploads/|/assets/images/|https://)#', $u)) $imgs[] = $u;
    }
    if (!$imgs) $imgs = ['/assets/images/placeholder.svg'];
    $blocked = [];
    foreach (array_slice(is_array($in['blocked'] ?? null) ? $in['blocked'] : [], 0, 300) as $x) {
        if (is_array($x) && is_date($x['from'] ?? '') && is_date($x['to'] ?? '') && $x['to'] >= $x['from']) {
            $blocked[] = ['from' => $x['from'], 'to' => $x['to'], 'note' => clean($x['note'] ?? '', 120)];
        }
    }
    $seasons = [];
    foreach (array_slice(is_array($in['seasons'] ?? null) ? $in['seasons'] : [], 0, 30) as $x) {
        if (is_array($x) && is_date($x['from'] ?? '') && is_date($x['to'] ?? '') && is_numeric($x['price'] ?? null) && $x['price'] > 0) {
            $seasons[] = ['name' => clean($x['name'] ?? '', 60), 'from' => $x['from'], 'to' => $x['to'], 'price' => $x['price'] + 0];
        }
    }
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', clean($in['slug'] ?? $name, 160)), '-'));
    if ($slug === '') $slug = $id;
    if (q('SELECT 1 FROM properties WHERE slug = ? AND id <> ?', [$slug, $id])->fetch()) {
        $slug .= '-' . substr($id, -4);
    }
    $time = function ($k, $d) use ($in) {
        $v = clean($in[$k] ?? '', 5);
        return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : $d;
    };
    $wk = (isset($in['weekendPrice']) && is_numeric($in['weekendPrice']) && $in['weekendPrice'] > 0) ? $in['weekendPrice'] + 0 : '';
    return [
        'id' => $id, 'slug' => $slug, 'name' => $name, 'type' => clean($in['type'] ?? 'Premium short stay', 60) ?: 'Premium short stay',
        'location' => clean($in['location'] ?? '', 120), 'address' => clean($in['address'] ?? '', 200), 'description' => clean($in['description'] ?? '', 4000),
        'beds' => max(0, (int)$n('beds', 1)), 'baths' => max(0, (int)$n('baths', 1)), 'guests' => max(1, (int)$n('guests', 2)),
        'price' => max(0, $n('price')), 'weekendPrice' => $wk, 'cleaning' => max(0, $n('cleaning')), 'weeklyPct' => max(0, min(60, $n('weeklyPct'))),
        'minStay' => max(1, (int)$n('minStay', 1)), 'maxStay' => max(1, (int)$n('maxStay', 30)), 'checkIn' => $time('checkIn', '14:00'), 'checkOut' => $time('checkOut', '11:00'),
        'bookingMode' => (($in['bookingMode'] ?? '') === 'request') ? 'request' : 'instant',
        'cancellation' => in_array($in['cancellation'] ?? '', ['flexible', 'moderate', 'strict'], true) ? $in['cancellation'] : 'moderate',
        'status' => (($in['status'] ?? '') === 'published') ? 'published' : 'draft', 'featured' => !empty($in['featured']),
        'amenities' => $list('amenities', 40, 60), 'rules' => $list('rules', 30, 200), 'images' => $imgs, 'blocked' => $blocked, 'seasons' => $seasons,
        'host' => clean($in['host'] ?? '', 80), 'demo' => !empty($old['demo']), 'createdAt' => $old['createdAt'] ?? now_iso(),
    ];
}

function save_properties(array $items, array $removed): void
{
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $id = clean($it['id'] ?? '', 32);
        $row = $id !== '' ? q('SELECT * FROM properties WHERE id = ?', [$id])->fetch() : null;
        $old = $row ? prop_decode($row) : null;
        $p = clean_prop($it, $old);
        if (!$p) fail(422, 'Each property needs a name.');
        $data = jenc($p);
        if ($row) {
            q('UPDATE properties SET slug = ?, status = ?, data = ? WHERE id = ?', [$p['slug'], $p['status'], $data, $id]);
        } else {
            q('INSERT INTO properties (id, slug, status, created_at, data) VALUES (?,?,?,?,?)', [$id, $p['slug'], $p['status'], gmdate('Y-m-d H:i:s'), $data]);
        }
    }
    foreach ($removed as $rid) {
        $rid = clean($rid, 32);
        $n = (int)q("SELECT COUNT(*) c FROM bookings WHERE property_id = ? AND status IN ('pending','awaiting_payment','payment_submitted','confirmed')", [$rid])->fetch()['c'];
        if ($n > 0) fail(422, 'That property has active bookings. Cancel them first, or unpublish instead.');
        q('DELETE FROM properties WHERE id = ?', [$rid]);
    }
}

function save_reviews(array $items, array $removed): void
{
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $id = clean($it['id'] ?? '', 32);
        if ($id === '') continue;
        $st = in_array($it['status'] ?? '', ['pending', 'approved', 'hidden'], true) ? $it['status'] : 'pending';
        $row = q('SELECT * FROM reviews WHERE id = ?', [$id])->fetch();
        $d = $row ? (json_decode($row['data'], true) ?: []) : [];
        $d = array_merge($d, [
            'id' => $id, 'propertyId' => clean($it['propertyId'] ?? ($d['propertyId'] ?? ''), 32), 'name' => clean($it['name'] ?? ($d['name'] ?? ''), 60),
            'rating' => max(1, min(5, (int)($it['rating'] ?? ($d['rating'] ?? 5)))), 'text' => clean($it['text'] ?? ($d['text'] ?? ''), 1200),
            'date' => is_date($it['date'] ?? '') ? $it['date'] : ($d['date'] ?? today()), 'status' => $st, 'reply' => clean($it['reply'] ?? ($d['reply'] ?? ''), 600),
        ]);
        if ($row) {
            q('UPDATE reviews SET property_id = ?, status = ?, data = ? WHERE id = ?', [$d['propertyId'], $st, jenc($d), $id]);
        } else {
            q('INSERT INTO reviews (id, property_id, booking_ref, status, created_at, data) VALUES (?,?,?,?,?,?)', [$id, $d['propertyId'], null, $st, gmdate('Y-m-d H:i:s'), jenc($d)]);
        }
    }
    foreach ($removed as $rid) {
        q('DELETE FROM reviews WHERE id = ?', [clean($rid, 32)]);
    }
}

function save_offers(array $items, array $removed): void
{
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $id = clean($it['id'] ?? '', 32);
        $code = strtoupper(substr((string)preg_replace('/[^A-Za-z0-9]/', '', clean($it['code'] ?? '', 40)), 0, 20));
        if ($id === '' || $code === '' || clean($it['title'] ?? '', 100) === '') fail(422, 'Each offer needs a title and a code.');
        if (q('SELECT 1 FROM offers WHERE code = ? AND id <> ?', [$code, $id])->fetch()) fail(422, 'That code already exists.');
        $type = (($it['type'] ?? '') === 'fixed') ? 'fixed' : 'pct';
        $val = max(0, (float)($it['value'] ?? 0));
        if ($type === 'pct') $val = min(90, $val);
        $d = [
            'id' => $id, 'title' => clean($it['title'], 100), 'code' => $code, 'type' => $type, 'value' => $val + 0, 'minNights' => max(0, (int)($it['minNights'] ?? 0)),
            'validFrom' => is_date($it['validFrom'] ?? '') ? $it['validFrom'] : '', 'validTo' => is_date($it['validTo'] ?? '') ? $it['validTo'] : '',
            'propertyId' => clean($it['propertyId'] ?? '', 32), 'active' => !empty($it['active']), 'description' => clean($it['description'] ?? '', 200),
        ];
        if (q('SELECT 1 FROM offers WHERE id = ?', [$id])->fetch()) {
            q('UPDATE offers SET code = ?, data = ? WHERE id = ?', [$code, jenc($d), $id]);
        } else {
            q('INSERT INTO offers (id, code, data) VALUES (?,?,?)', [$id, $code, jenc($d)]);
        }
    }
    foreach ($removed as $rid) {
        q('DELETE FROM offers WHERE id = ?', [clean($rid, 32)]);
    }
}

function save_enquiries(array $items, array $removed): void
{
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $id = clean($it['id'] ?? '', 32);
        $row = q('SELECT * FROM enquiries WHERE id = ?', [$id])->fetch();
        if (!$row) continue;
        $st = in_array($it['status'] ?? '', ['new', 'replied', 'closed'], true) ? $it['status'] : $row['status'];
        $d = json_decode($row['data'], true) ?: [];
        $d['status'] = $st;
        q('UPDATE enquiries SET status = ?, data = ? WHERE id = ?', [$st, jenc($d), $id]);
    }
    foreach ($removed as $rid) {
        q('DELETE FROM enquiries WHERE id = ?', [clean($rid, 32)]);
    }
}

function save_notes($map): void
{
    if (!is_array($map)) return;
    foreach ($map as $k => $v) {
        $k = clean((string)$k, 190);
        $v = clean($v, 1000);
        if ($k === '') continue;
        if ($v === '') {
            q('DELETE FROM guest_notes WHERE k = ?', [$k]);
        } else {
            q('INSERT INTO guest_notes (k, note) VALUES (?,?) ON DUPLICATE KEY UPDATE note = VALUES(note)', [$k, $v]);
        }
    }
}

function admin_dump(array $u): array
{
    $bookings = [];
    foreach (q('SELECT * FROM bookings ORDER BY created_at DESC LIMIT 3000')->fetchAll() as $r) {
        $bookings[] = booking_decode($r);
    }
    $activity = [];
    foreach (q('SELECT t, actor, text FROM activity ORDER BY id DESC LIMIT 300')->fetchAll() as $r) {
        $activity[] = ['t' => str_replace(' ', 'T', $r['t']) . 'Z', 'by' => $r['actor'], 'text' => $r['text']];
    }
    $notes = [];
    foreach (q('SELECT k, note FROM guest_notes')->fetchAll() as $r) {
        $notes[$r['k']] = $r['note'];
    }
    $users = $u['role'] === 'owner' ? q('SELECT id, username, role FROM admins ORDER BY id')->fetchAll() : [];
    return [
        'properties' => props_all(), 'bookings' => $bookings, 'reviews' => doc_rows('reviews'), 'offers' => doc_rows('offers', 'id'), 'enquiries' => doc_rows('enquiries'),
        'activity' => $activity, 'guestNotes' => (object)$notes, 'users' => $users, 'settings' => settings(),
    ];
}

/* =====================================================================
   PUBLIC ROUTES
   ===================================================================== */
$s0 = $seg[0] ?? '';

if ($s0 === 'bootstrap' && $method === 'GET') {
    header('Cache-Control: no-cache');
    $props = array_values(array_filter(props_all(), function ($p) { return $p['status'] === 'published'; }));
    $busy = [];
    foreach ($props as $p) {
        $busy[$p['id']] = [];
    }
    $rows = q("SELECT property_id, check_in, check_out FROM bookings WHERE status IN ('awaiting_payment','payment_submitted','confirmed') AND check_out >= ?", [today()])->fetchAll();
    foreach ($rows as $r) {
        if (isset($busy[$r['property_id']])) {
            $busy[$r['property_id']][] = [$r['check_in'], $r['check_out']];
        }
    }
    $offers = [];
    foreach (doc_rows('offers', 'id') as $o) {
        if (!empty($o['active'])) $offers[] = $o;
    }
    $reviews = [];
    foreach (doc_rows('reviews', 'created_at DESC', 300) as $r) {
        if (($r['status'] ?? '') === 'approved') {
            unset($r['bookingRef']);
            $reviews[] = $r;
        }
    }
    out(['settings' => public_settings(), 'properties' => array_map('prop_public', $props), 'offers' => $offers, 'reviews' => $reviews, 'busy' => (object)$busy, 'seo' => (bool)cfg('pretty_urls')]);
}

if ($s0 === 'bookings') {
    if ($method === 'POST' && count($seg) === 1) {
        throttle('book', 15, 3600);
        if (!empty($b['website'])) {
            fail(422, 'Could not complete your booking.');
        }
        $r = booking_create($b, false);
        if (isset($r['error'])) fail(422, $r['error']);
        out(['booking' => guest_view($r['booking']), 'token' => $r['token']], 201);
    }
    if ($method === 'POST' && ($seg[1] ?? '') === 'lookup') {
        if (throttle_count('lookup', 900) >= 10) fail(429, 'Too many attempts. Please wait a while and try again.');
        $row = booking_find(clean($b['ref'] ?? '', 20));
        $who = strtolower(clean($b['who'] ?? '', 120));
        $ok = false;
        if ($row && $who !== '') {
            $bk = booking_decode($row);
            $dg = function ($v) { return (string)preg_replace('/\D/', '', (string)$v); };
            $ok = strtolower((string)$bk['guest']['email']) === $who || (strlen($dg($who)) > 8 && substr($dg($bk['guest']['phone']), -9) === substr($dg($who), -9));
        }
        if (!$ok) {
            throttle_hit('lookup');
            fail(404, 'We could not match those details. Check your reference and contact details.');
        }
        out(['booking' => guest_view($bk), 'token' => $row['token']]);
    }
    if (count($seg) === 2 && $method === 'GET') {
        $row = guest_booking_row($seg[1], clean($_GET['t'] ?? '', 64));
        out(['booking' => guest_view(booking_decode($row))]);
    }
    if (count($seg) === 3 && $method === 'POST' && in_array($seg[2], ['payment', 'cancel'], true)) {
        $row = guest_booking_row($seg[1], clean($b['t'] ?? '', 64));
        throttle($seg[2] === 'payment' ? 'pay' : 'gcancel', 10, 3600);
        $r = $seg[2] === 'payment' ? booking_act($row['id'], 'submitPayment', $b, 'guest', true) : booking_act($row['id'], 'cancel', $b, 'guest', true);
        if (isset($r['error'])) fail(422, $r['error']);
        out(['booking' => guest_view($r['booking'])]);
    }
    fail(404, 'Not found.');
}

if ($s0 === 'enquiries' && $method === 'POST') {
    throttle('enq', 6, 3600);
    if (!empty($b['website'])) {
        out(['ok' => true]);
    }
    $name = clean($b['name'] ?? '', 80);
    $email = clean($b['email'] ?? '', 120);
    $msg = clean($b['message'] ?? '', 2000);
    if ($name === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail(422, 'Please enter your name, a valid email and a message.');
    }
    $e = ['id' => uid(), 'kind' => (($b['kind'] ?? '') === 'host') ? 'host' : 'contact', 'name' => $name, 'phone' => clean($b['phone'] ?? '', 30), 'email' => $email,
        'propertyId' => clean($b['propertyId'] ?? '', 32), 'location' => clean($b['location'] ?? '', 120), 'message' => $msg, 'status' => 'new', 'createdAt' => now_iso()];
    q('INSERT INTO enquiries (id, status, created_at, data) VALUES (?,?,?,?)', [$e['id'], 'new', gmdate('Y-m-d H:i:s'), jenc($e)]);
    log_act('Enquiry from ' . $name, 'guest');
    notify_enquiry($e);
    out(['ok' => true], 201);
}

if ($s0 === 'reviews' && $method === 'POST') {
    throttle('review', 5, 3600);
    $row = guest_booking_row(clean($b['ref'] ?? '', 20), clean($b['t'] ?? '', 64));
    $bk = booking_decode($row);
    if (!in_array($bk['status'], ['confirmed', 'completed'], true) || $bk['in'] > today()) {
        fail(422, 'You can review your stay once it has started.');
    }
    if (q('SELECT 1 FROM reviews WHERE booking_ref = ?', [$bk['ref']])->fetch()) {
        fail(422, 'You have already reviewed this stay.');
    }
    $text = clean($b['text'] ?? '', 1200);
    if ($text === '') fail(422, 'Write a few words about your stay.');
    $first = explode(' ', trim((string)$bk['guest']['name']))[0];
    $d = ['id' => uid(), 'propertyId' => $bk['propertyId'], 'bookingRef' => $bk['ref'], 'name' => mb_substr($first, 0, 40), 'rating' => max(1, min(5, (int)($b['rating'] ?? 5))), 'text' => $text, 'date' => today(), 'status' => 'pending'];
    q('INSERT INTO reviews (id, property_id, booking_ref, status, created_at, data) VALUES (?,?,?,?,?,?)', [$d['id'], $d['propertyId'], $bk['ref'], 'pending', gmdate('Y-m-d H:i:s'), jenc($d)]);
    log_act('Review submitted for ' . $bk['propertyName'], 'guest');
    out(['ok' => true], 201);
}

/* =====================================================================
   ADMIN ROUTES
   ===================================================================== */
if ($s0 === 'admin') {
    $a = $seg[1] ?? '';

    if ($a === 'login' && $method === 'POST') {
        if (throttle_count('login', 900) >= 8) fail(429, 'Too many sign-in attempts. Try again in 15 minutes.');
        $un = clean($b['username'] ?? '', 80);
        $pw = is_string($b['password'] ?? null) ? $b['password'] : '';
        $r = q('SELECT * FROM admins WHERE username = ?', [$un])->fetch();
        usleep(300000);
        if (!$r || !password_verify($pw, $r['pass_hash'])) {
            throttle_hit('login');
            log_act('Failed admin sign-in for "' . $un . '"', 'system');
            fail(401, 'Incorrect username or password.');
        }
        session_boot();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$r['id'];
        $_SESSION['uname'] = $r['username'];
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        log_act('Signed in', $r['username']);
        out(['user' => ['id' => (int)$r['id'], 'username' => $r['username'], 'role' => $r['role']], 'csrf' => $_SESSION['csrf']]);
    }
    if ($a === 'logout' && $method === 'POST') {
        session_boot();
        $_SESSION = [];
        session_destroy();
        out(['ok' => true]);
    }
    if ($a === 'me' && $method === 'GET') {
        $u = admin_user();
        if (!$u) fail(401, 'Not signed in.');
        out(['user' => ['id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role']], 'csrf' => $_SESSION['csrf']]);
    }
    if ($a === 'data' && $method === 'GET') {
        $u = admin_guard();
        out(admin_dump($u));
    }
    if ($a === 'settings' && $method === 'POST') {
        admin_guard(true);
        save_settings(is_array($b['patch'] ?? null) ? $b['patch'] : []);
        out(['settings' => settings()]);
    }
    if ($a === 'save' && $method === 'POST' && isset($seg[2])) {
        admin_guard();
        $items = $b['items'] ?? [];
        $removed = is_array($b['removed'] ?? null) ? $b['removed'] : [];
        switch ($seg[2]) {
            case 'properties': save_properties(is_array($items) ? $items : [], $removed); break;
            case 'reviews': save_reviews(is_array($items) ? $items : [], $removed); break;
            case 'offers': save_offers(is_array($items) ? $items : [], $removed); break;
            case 'enquiries': save_enquiries(is_array($items) ? $items : [], $removed); break;
            case 'guestNotes': save_notes($items); break;
            default: fail(404, 'Unknown collection.');
        }
        log_act('Saved ' . $seg[2], actor());
        out(['ok' => true]);
    }
    if ($a === 'bookings' && $method === 'POST') {
        $u = admin_guard();
        if (count($seg) === 2) {
            $r = booking_create($b, true);
            if (isset($r['error'])) fail(422, $r['error']);
            out(['booking' => $r['booking']], 201);
        }
        if (count($seg) === 4 && $seg[3] === 'act') {
            $r = booking_act($seg[2], clean($b['action'] ?? '', 30), is_array($b['payload'] ?? null) ? $b['payload'] : [], $u['username'], false);
            if (isset($r['error'])) fail(422, $r['error']);
            out(['booking' => $r['booking']]);
        }
    }
    if ($a === 'upload' && $method === 'POST') {
        admin_guard();
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) fail(422, 'Upload failed. The photo may be too large.');
        $f = $_FILES['file'];
        if ($f['size'] > 6 * 1024 * 1024) fail(422, 'Photo is too large (max 6 MB).');
        $info = @getimagesize($f['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) fail(422, 'Use a JPG, PNG or WebP photo.');
        $dir = rtrim((string)cfg('upload_dir'), '/');
        if (!is_dir($dir) || !is_writable($dir)) fail(500, 'The uploads folder is not writable on the server.');
        $name = bin2hex(random_bytes(10)) . '.' . $types[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) fail(500, 'Could not save the photo.');
        out(['url' => rtrim((string)cfg('upload_url'), '/') . '/' . $name]);
    }
    if ($a === 'password' && $method === 'POST') {
        $u = admin_guard();
        $r = q('SELECT * FROM admins WHERE id = ?', [$u['id']])->fetch();
        if (!password_verify((string)($b['current'] ?? ''), $r['pass_hash'])) fail(422, 'Current password is incorrect.');
        $pw = (string)($b['password'] ?? '');
        if (strlen($pw) < 10) fail(422, 'Use at least 10 characters.');
        if (strtolower($pw) === strtolower($u['username'])) fail(422, 'Password must not match your username.');
        q('UPDATE admins SET pass_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        log_act('Password changed', $u['username']);
        out(['ok' => true]);
    }
    if ($a === 'users' && $method === 'POST') {
        $u = admin_guard(true);
        $act = (string)($b['action'] ?? '');
        if ($act === 'add') {
            $un = clean($b['username'] ?? '', 40);
            $pw = (string)($b['password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $un)) fail(422, 'Username: 3 to 40 letters, numbers, dots, dashes.');
            if (strlen($pw) < 10) fail(422, 'Password needs at least 10 characters.');
            if (q('SELECT 1 FROM admins WHERE username = ?', [$un])->fetch()) fail(422, 'That username is taken.');
            q('INSERT INTO admins (username, pass_hash, role, created_at) VALUES (?,?,?,?)', [$un, password_hash($pw, PASSWORD_DEFAULT), 'staff', gmdate('Y-m-d H:i:s')]);
            log_act('Staff account added: ' . $un, $u['username']);
            out(['ok' => true]);
        }
        $target = q('SELECT * FROM admins WHERE id = ?', [(int)($b['id'] ?? 0)])->fetch();
        if (!$target || $target['role'] === 'owner') fail(422, 'Account not found.');
        if ($act === 'reset') {
            $pw = (string)($b['password'] ?? '');
            if (strlen($pw) < 10) fail(422, 'Password needs at least 10 characters.');
            q('UPDATE admins SET pass_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $target['id']]);
            log_act('Password reset for ' . $target['username'], $u['username']);
            out(['ok' => true]);
        }
        if ($act === 'remove') {
            q('DELETE FROM admins WHERE id = ?', [$target['id']]);
            log_act('Staff account removed: ' . $target['username'], $u['username']);
            out(['ok' => true]);
        }
        fail(422, 'Unknown action.');
    }
    if ($a === 'export' && $method === 'GET') {
        $u = admin_guard(true);
        $d = admin_dump($u);
        unset($d['users']);
        $d['exportedAt'] = now_iso();
        out($d);
    }
    if ($a === 'cleardemo' && $method === 'POST') {
        admin_guard(true);
        foreach (['bookings' => 'id', 'reviews' => 'id', 'enquiries' => 'id', 'properties' => 'id'] as $t => $col) {
            foreach (q("SELECT id, data FROM $t")->fetchAll() as $r) {
                $d = json_decode($r['data'], true);
                if (is_array($d) && !empty($d['demo'])) q("DELETE FROM $t WHERE id = ?", [$r['id']]);
            }
        }
        log_act('Sample data cleared', actor());
        out(['ok' => true]);
    }
    fail(404, 'Not found.');
}

fail(404, 'Not found.');
