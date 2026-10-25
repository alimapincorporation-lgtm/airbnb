<?php
declare(strict_types=1);
require __DIR__ . '/../private/lib.php';
$u = site_url();
$urls = [['/', 'weekly'], ['/properties.html', 'daily'], ['/destinations.html', 'monthly'], ['/how-it-works.html', 'monthly'], ['/offers.html', 'weekly'], ['/reviews.html', 'weekly'], ['/about.html', 'yearly'], ['/faq.html', 'monthly'], ['/contact.html', 'yearly'], ['/host.html', 'yearly']];
foreach (props_all() as $p) {
    if ($p['status'] === 'published') {
        $urls[] = ['/stays/' . rawurlencode((string)$p['slug']), 'weekly'];
    }
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as $x) {
    echo '<url><loc>' . h($u . $x[0]) . '</loc><changefreq>' . $x[1] . '</changefreq></url>';
}
echo '</urlset>';
