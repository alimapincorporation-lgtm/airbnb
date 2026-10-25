# AIM-AirBnB (MVP)

PHP 8.1+ and MySQL 8 (or MariaDB 10.4+) on a VPS. The front end is plain HTML/CSS/JS served from `public/`.
Bookings, payments status, guests, reviews and the admin CMS live in MySQL. Email goes through Brevo or Resend.

```
public/    web root: pages, css, js, admin/, api/index.php, property.php (SEO), sitemap.php, uploads/
private/   OUTSIDE the web root in production: lib.php, config.php, schema.sql, install.php, cron.php, seed-sample.php
deploy/    nginx.conf
```

## What it does
- Guests: browse and filter (live availability), instant book or request-to-book (per property), promo codes, deposit,
  Mobile Money payment page with transaction ID, magic-link booking page, find my booking, cancel with policy-based refund, reviews.
- Server-side rules: price is recomputed on the server, double bookings are blocked with a database row lock, unpaid holds expire,
  transaction IDs cannot be reused, rate limits and a honeypot protect forms.
- Email: host alert on every new booking/request/payment/cancellation/enquiry (with WhatsApp click-to-chat button), guest emails on
  request received, approved, payment received, confirmed, cancelled, hold expired. Failures are logged in the `mail_log` table.
- Admin (`/admin/`): dashboard, bookings (approve, decline, verify payment, cancel, refund, notes, invoice, manual booking), properties
  with photo upload, availability calendar and blocks, payments, guests, reviews, offers, enquiries, site content, reports, settings,
  staff accounts (owner and staff roles), activity log, JSON export.
- SEO: `/stays/{slug}` pages with title, description, canonical, Open Graph and JSON-LD, plus `/sitemap.xml` and `robots.txt`.

## Deploy on Ubuntu 22.04/24.04 (nginx)
```bash
sudo apt update && sudo apt install -y nginx mysql-server php-fpm php-mysql php-curl php-mbstring unzip certbot python3-certbot-nginx
sudo mkdir -p /var/www/aim-airbnb && sudo unzip aim-airbnb-mvp.zip -d /tmp/aim && sudo cp -r /tmp/aim/aim-mvp/* /var/www/aim-airbnb/

# database
sudo mysql -e "CREATE DATABASE aim_airbnb CHARACTER SET utf8mb4; CREATE USER 'aim'@'localhost' IDENTIFIED BY 'USE_A_LONG_PASSWORD'; GRANT ALL ON aim_airbnb.* TO 'aim'@'localhost';"

# config
cd /var/www/aim-airbnb/private && sudo cp config.sample.php config.php && sudo nano config.php   # db password, site_url, mail provider + key
sudo chown -R www-data:www-data /var/www/aim-airbnb/public/uploads && sudo chmod 640 config.php && sudo chown root:www-data config.php

# tables + owner account (pick a long unique password)
ADMIN_PASSWORD='change-this-long-password' php install.php owner
php seed-sample.php        # optional demo content; remove later in Admin > Settings > Clear sample data

# web server
sudo cp /var/www/aim-airbnb/deploy/nginx.conf /etc/nginx/sites-available/aim-airbnb   # edit server_name and the php-fpm socket version
sudo ln -s /etc/nginx/sites-available/aim-airbnb /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# cron: expire unpaid holds, complete finished stays
( crontab -l 2>/dev/null; echo "*/5 * * * * php /var/www/aim-airbnb/private/cron.php" ) | crontab -
# nightly database backup
( crontab -l 2>/dev/null; echo "15 2 * * * mysqldump aim_airbnb | gzip > /var/backups/aim-\$(date +\%F).sql.gz" ) | crontab -
```
Then: edit `public/robots.txt` (your domain), sign in at `/admin/`, open Settings and fill in contact details,
the notification email, WhatsApp number and your MTN/Airtel numbers. Submit `/sitemap.xml` in Google Search Console.

## Email (Brevo or Resend)
Create an account, verify your sending domain or address, create an API key, and put `provider`, `api_key`, `from_email` in `private/config.php`.
Make a test booking and check the `mail_log` table if nothing arrives.

## Smoke test before launch (do all of these)
1. Home loads with no errors; Admin sign-in works; wrong password is refused.
2. Add a property with photos, publish it, open `/stays/your-slug`.
3. Book it as a guest on a phone: you get the host email and the guest email; the magic link opens the booking.
4. Pay a small test amount by Mobile Money, submit the ID, verify it in Admin: guest gets the confirmation email.
5. Try the same dates again: they are blocked. Cancel from the guest page: refund amount matches the policy.
6. Send a contact enquiry and a review; moderate it in Admin.

## Known limits
- Payment is verified by a person against the Mobile Money statement. A gateway (Flutterwave or Pesapal) is the next upgrade.
- No iCal sync with Airbnb/Booking.com yet. Do not list the same home elsewhere without blocking dates by hand.
- Photos are stored on the server disk (`public/uploads`); include that folder in backups.
- Generic data model: records are stored as JSON documents with indexed key columns. Fine for hundreds of properties; revisit at larger scale.
