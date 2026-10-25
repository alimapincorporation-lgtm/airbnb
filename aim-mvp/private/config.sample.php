<?php
// Copy to private/config.php and fill in. Never commit config.php. Keep the private/ folder OUTSIDE the web root.
return [
    'db' => ['dsn' => 'mysql:host=127.0.0.1;dbname=aim_airbnb;charset=utf8mb4', 'user' => 'aim', 'pass' => 'CHANGE_ME'],
    'site_url' => 'https://your-domain.com',      // no trailing slash; used in emails, sitemap and SEO tags
    'pretty_urls' => true,                         // true when the nginx/Apache rewrite for /stays/slug is active
    'timezone' => 'Africa/Kampala',
    'country_code' => '256',                       // used to turn 07xx numbers into WhatsApp links
    'mail' => [
        'provider' => 'brevo',                     // 'brevo' or 'resend'
        'api_key' => '',                           // Brevo API key (v3) or Resend API key
        'from_email' => 'bookings@your-domain.com',// must be a verified sender/domain with the provider
        'from_name' => 'AIM-AirBnB',
    ],
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url' => '/uploads',
    'session_name' => 'aimadmin',
    'dev' => false,                                // true shows error details in API responses (never on production)
];
