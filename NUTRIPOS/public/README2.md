# NutriPOS – POS Order Simulation + Receipts + Admin + Email

This package adds:
- POS order simulation (no payment)
- Nutrition calc from AFCD
- Receipt page with QR payload (nutrition summary)
- Save orders & items in SQL (MySQL/Postgres via PDO)
- Admin section with login + orders list
- Optional PHPMailer email for receipt link

## Install

1) Create DB (MySQL example)
```sql
CREATE DATABASE nutripos CHARACTER SET utf8mb4;
```
Run schema:
```sh
mysql -u root -p nutripos < db/schema.sql
```

2) Configure DB & mail
- Copy `config/config.php` to `config/local.php` and set credentials.

3) Create admin user
```php
<?php
require 'NutriPOS/backend/db.php';
$pdo = pdo();
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users(email,password_hash,role) VALUES(?,?,?)")->execute(['admin@example.com',$hash,'admin']);
echo "done";
```
Run the snippet once or insert manually.

4) PHPMailer (optional):
```
cd NutriPOS/backend
composer require phpmailer/phpmailer
```

5) AFCD CSV
Put `afcd.csv` into `NutriPOS/data/afcd.csv`

6) Serve
- XAMPP: `http://localhost/NutriPOS/public/pos_simulator.html`
- Admin: `http://localhost/NutriPOS/admin/login.php`

## Endpoints
- POST `backend/orders_create.php` { customer_email?, items:[{name,afcd_code?,grams,qty}] }
- GET  `backend/orders_get.php?id=...`
- GET  `public/receipt.html?id=...`

## Notes
- QR uses a client-side library; for email we send a **link** to the hosted receipt which renders the QR.
- All nutrition values are scaled from AFCD per-100g; kJ→kcal uses 4.184 kJ per kcal.
