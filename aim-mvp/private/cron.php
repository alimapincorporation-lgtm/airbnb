<?php
// Cron (every 5 minutes):  */5 * * * * php /var/www/aim-airbnb/private/cron.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/lib.php';
sweep();
q('DELETE FROM hits WHERE t < ?', [time() - 86400]);
