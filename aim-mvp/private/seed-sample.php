<?php
// Optional: adds sample properties, an offer and reviews flagged as demo. Remove later via Admin > Settings > Clear sample data.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI only.\n"); }
require __DIR__ . '/lib.php';
$rules = ['No smoking indoors', 'No parties or events', 'Quiet hours 10pm to 7am', 'Visitors must be registered at check-in'];
$mk = function (int $n, string $name, string $type, string $loc, int $beds, int $baths, int $guests, int $price, int $cleaning, string $mode, string $pol, array $am, string $desc, array $extra = []) use ($rules) {
    return array_merge([
        'id' => 'sample' . $n, 'name' => $name, 'type' => $type, 'location' => $loc, 'beds' => $beds, 'baths' => $baths, 'guests' => $guests, 'price' => $price, 'cleaning' => $cleaning,
        'bookingMode' => $mode, 'cancellation' => $pol, 'amenities' => $am, 'description' => $desc, 'rules' => $rules, 'status' => 'published', 'featured' => $n < 3, 'demo' => true,
        'images' => ['/assets/images/sample-' . $n . '.svg', '/assets/images/sample-' . (($n % 6) + 1) . '.svg'], 'minStay' => 1, 'maxStay' => 30, 'checkIn' => '14:00', 'checkOut' => '11:00',
    ], $extra);
};
$list = [
    $mk(1, 'Kololo Skyline Apartment', 'Luxury apartment', 'Kololo, Kampala', 2, 2, 4, 380000, 50000, 'instant', 'moderate', ['Wi-Fi', 'Air conditioning', 'Kitchen', 'TV', 'Secure compound', 'Generator backup', 'Workspace', 'Balcony'], 'A bright two-bedroom apartment on a high floor, with a balcony, a fully equipped kitchen and reliable backup power.', ['weekendPrice' => 420000, 'weeklyPct' => 10]),
    $mk(2, 'Bugolobi Garden Cottage', 'Premium short stay', 'Bugolobi, Kampala', 1, 1, 2, 190000, 30000, 'instant', 'flexible', ['Wi-Fi', 'Garden', 'Kitchen', 'Hot water', 'Free parking', 'Secure compound'], 'A private cottage in a shaded garden, a few minutes from restaurants and the lakeside.'),
    $mk(3, 'Muyenga Family Villa', 'Family stay', 'Muyenga, Kampala', 4, 3, 8, 650000, 80000, 'request', 'strict', ['Wi-Fi', 'Garden', 'Kitchen', 'Washing machine', 'Free parking', 'Secure compound', 'Generator backup', 'TV'], 'A spacious four-bedroom villa with a lawn and a big kitchen. The host approves each request.', ['minStay' => 2, 'weeklyPct' => 12]),
    $mk(4, 'Entebbe Lakeview Holiday Home', 'Holiday home', 'Entebbe', 3, 2, 6, 520000, 70000, 'request', 'moderate', ['Wi-Fi', 'Garden', 'Breakfast available', 'Airport pickup', 'Free parking', 'Hot water'], 'A relaxed holiday home close to the lake and a short drive from the airport.'),
    $mk(5, 'Ntinda Studio Retreat', 'Premium short stay', 'Ntinda, Kampala', 1, 1, 2, 120000, 20000, 'instant', 'flexible', ['Wi-Fi', 'Kitchen', 'Hot water', 'Workspace', 'TV'], 'A compact, well-designed studio for solo travellers and short work trips.'),
    $mk(6, 'Naguru Townhouse', 'Family stay', 'Naguru, Kampala', 3, 3, 6, 450000, 60000, 'instant', 'moderate', ['Wi-Fi', 'Air conditioning', 'Kitchen', 'Free parking', 'Secure compound', 'Washing machine'], 'A modern three-bedroom townhouse in a secure development, minutes from the city centre.'),
];
foreach ($list as $in) {
    if (q('SELECT 1 FROM properties WHERE id = ?', [$in['id']])->fetch()) continue;
    $in['slug'] = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $in['name']), '-'));
    $p = $in; $p['blocked'] = []; $p['seasons'] = []; $p['createdAt'] = now_iso(); $p['host'] = ''; $p['address'] = ''; $p['weekendPrice'] = $in['weekendPrice'] ?? ''; $p['weeklyPct'] = $in['weeklyPct'] ?? 0;
    q('INSERT INTO properties (id, slug, status, created_at, data) VALUES (?,?,?,?,?)', [$p['id'], $p['slug'], $p['status'], gmdate('Y-m-d H:i:s'), jenc($p)]);
}
if (!q("SELECT 1 FROM offers WHERE code = 'WELCOME10'")->fetch()) {
    $o = ['id' => 'offer1', 'title' => 'Welcome offer', 'code' => 'WELCOME10', 'type' => 'pct', 'value' => 10, 'minNights' => 2, 'validFrom' => '', 'validTo' => '', 'propertyId' => '', 'active' => true, 'description' => '10% off stays of 2 nights or more.'];
    q('INSERT INTO offers (id, code, data) VALUES (?,?,?)', [$o['id'], $o['code'], jenc($o)]);
}
$rv = [['sample1', 'Sample Guest', 5, 'Clean, quiet and exactly as described. Check-in was easy.'], ['sample3', 'Sample Family', 5, 'Plenty of space for the whole family and a garden the children loved.'], ['sample2', 'Sample Traveller', 4, 'A peaceful cottage close to everything.']];
foreach ($rv as $i => $r) {
    $id = 'samplerv' . $i;
    if (q('SELECT 1 FROM reviews WHERE id = ?', [$id])->fetch()) continue;
    $d = ['id' => $id, 'propertyId' => $r[0], 'name' => $r[1], 'rating' => $r[2], 'text' => $r[3], 'date' => today(), 'status' => 'approved', 'demo' => true];
    q('INSERT INTO reviews (id, property_id, booking_ref, status, created_at, data) VALUES (?,?,?,?,?,?)', [$id, $r[0], null, 'approved', gmdate('Y-m-d H:i:s'), jenc($d)]);
}
echo "Sample data added.\n";
