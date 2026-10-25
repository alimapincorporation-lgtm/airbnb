<?php
// Serves /stays/{slug}: the property page shell with server-rendered SEO tags (title, description, canonical, Open Graph, JSON-LD).
declare(strict_types=1);
require __DIR__ . '/../private/lib.php';

$slug = trim((string)($_GET['slug'] ?? ''), '/');
$base = '<base href="' . h(site_url()) . '/">';
$p = $slug !== '' ? prop_get($slug, true) : null;
if (!$p) {
    http_response_code(404);
    echo str_replace('<head>', '<head>' . $base, (string)file_get_contents(__DIR__ . '/404.html'));
    exit;
}
$s = settings();
$tpl = (string)file_get_contents(__DIR__ . '/property.html');
$abs = function (string $u): string { return preg_match('#^https?://#', $u) ? $u : site_url() . $u; };
$city = trim(explode(',', (string)$p['location'])[0]);
$title = $p['name'] . ' in ' . $city . ' | ' . $s['brand'];
$desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', (string)$p['description'])), 0, 155);
if ($desc === '') {
    $desc = $p['name'] . ': ' . $p['beds'] . ' bedroom stay in ' . $p['location'] . ' for up to ' . $p['guests'] . ' guests. Book online.';
}
$canon = site_url() . '/stays/' . rawurlencode((string)$p['slug']);
$img = $abs((string)($p['images'][0] ?? '/assets/images/placeholder.svg'));
$ld = [
    '@context' => 'https://schema.org', '@type' => 'LodgingBusiness', 'name' => $p['name'], 'description' => $desc, 'url' => $canon,
    'image' => array_map($abs, array_slice($p['images'] ?? [], 0, 5)),
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => $p['location'], 'addressCountry' => 'UG'],
    'priceRange' => money($p['price']) . ' per night', 'numberOfRooms' => (int)$p['beds'],
    'amenityFeature' => array_map(function ($a) { return ['@type' => 'LocationFeatureSpecification', 'name' => $a, 'value' => true]; }, $p['amenities'] ?? []),
];
$rv = q("SELECT data FROM reviews WHERE property_id = ? AND status = 'approved'", [$p['id']])->fetchAll();
if (count($rv) > 0) {
    $sum = 0;
    foreach ($rv as $r) { $sum += (int)(json_decode($r['data'], true)['rating'] ?? 0); }
    $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($sum / count($rv), 1), 'reviewCount' => count($rv)];
}
$head = $base
    . '<link rel="canonical" href="' . h($canon) . '">'
    . '<meta property="og:type" content="website"><meta property="og:site_name" content="' . h($s['brand']) . '">'
    . '<meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '">'
    . '<meta property="og:url" content="' . h($canon) . '"><meta property="og:image" content="' . h($img) . '">'
    . '<meta name="twitter:card" content="summary_large_image">'
    . '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
$tpl = preg_replace('#<title>.*?</title>#s', '<title>' . h($title) . '</title>', $tpl, 1);
$tpl = preg_replace('#<meta name="description" content="[^"]*">#', '<meta name="description" content="' . h($desc) . '">', $tpl, 1);
$tpl = str_replace('<head>', '<head>' . $head, $tpl);
$fallback = '<main id="main" tabindex="-1"><section class="section"><div class="container"><h1>' . h($p['name']) . '</h1><p>' . h($p['location']) . '</p><p>' . h($desc) . '</p></div></section></main>';
$tpl = str_replace('<main id="main" tabindex="-1"></main>', $fallback, $tpl);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo $tpl;
