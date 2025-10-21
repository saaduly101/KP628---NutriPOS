# NutriPOS

A lightweight, web-based nutrition and recipe management system designed for integration with restaurant Point-of-Sale (POS) workflows. NutriPOS lets staff and customers view nutrition info for menu items, create/edit recipes, and share results via email or QR on a receipt.

---

## Release Notes
### Version 1.0 – Final Integrated Build (October 2025)

NutriPOS combines all major functionalities from the **main** and **develop** branches into a single stable release.  
Key updates include:

- Implemented full POS builder with ingredient management and autocomplete.  
- Added order creation, retrieval, and listing APIs.  
- Integrated Square API with Sandbox/Production switch support.  
- Added admin panel with authentication and dashboard.  
- Developed nutrition engine using AFCD dataset with fuzzy ingredient search.  
- Added email receipt system with nutrition details (PHPMailer integration).  
- Improved UI responsiveness and styling for tablet and desktop layouts.  
- Fixed database schema errors and enhanced MySQL reliability.  
- Refined documentation, error handling, and project configuration files.  

---

## Change Log

- **Aug 16–17, 2025:** Introduced POS Builder, database schema, and order management.  
- **Aug 18–19, 2025:** Improved admin UI and merged style updates.  
- **Aug 20–23, 2025:** Added Square SDK integration and initial testing.  
- **Aug 25–26, 2025:** Implemented admin authentication and login validation.  
- **Aug 27–29, 2025:** Created order and database testing modules.  
- **Sep 1–5, 2025:** Refined POS builder logic and improved database connection handling.  
- **Sep 11, 2025:** Added timezone validation and improved webhook structure.  
- **Sep 12, 2025:** Merged and stabilized Square API integration; reverted redundant commits.  
- **Sep 20–25, 2025:** Enhanced backend error handling and fixed minor SQL bugs.  
- **Oct 4–8, 2025:** Added PHPMailer dependency; implemented email receipt functionality.  
- **Oct 9–10, 2025:** Fixed email sending errors and improved order data handling.  
- **Oct 11–12, 2025:** Merged `feature/email-receipts` and `style/square-ui` into `develop`.  
- **Oct 13, 2025:** Updated README documentation, cleaned project structure, and finalized submission.  

---

## Key Features
- **Recipe CRUD:** Create, edit, duplicate, and delete menu items (recipes) with precise grams per ingredient.
- **Nutrition Engine:** Calculates kJ/kcal, macros, sugars, sodium, etc. using the Australian Food Composition Database (AFCD).
- **Fast AFCD Search:** Fuzzy search with an in-memory cache for responsive ingredient lookup.
- **POS-Aware Orders:** Create orders from the recipe builder or ingest completed orders from Square (optional).
- **Shareable Receipts:** Email nutrition receipts and display QR codes that open a hosted receipt page.
- **Admin Pages:** Login-gated admin views to review products and order history.

---


## Architecture & Data Flow
```
Browser (public/*.php + JS)
   ↕ AJAX (JSON)
Backend (backend/*.php)
   ↔ config/*.php  (env + overrides)
   ↔ data/afcd.csv (cached in memory)
   ↔ MySQL (via PDO)
   ↔ data/*.json (local persistence for products)
   ↔ SMTP (PHPMailer) for email_receipt.php
   ↔ Square SDK (webhook) for completed orders (optional)
```

### Explanation
- **Front-end:** Simple PHP pages with JavaScript calling backend JSON endpoints.
- **Back-end:** PHP (Composer autoload), PDO for DB, Dotenv for configuration.
- **Data:** AFCD CSV is cached for faster performance; products stored as JSON, orders and snapshots in MySQL.

---

## Directory Structure
```
NUTRIPOS/
├─ public/                   # Browser-facing pages
│  ├─ products.php           # Manage saved recipes
│  ├─ custom_pos_builder.php # Build custom items + checkout
│  ├─ receipt.html           # Minimal receipt + QR view
│  └─ assets/ css/js/img
├─ backend/                  # JSON APIs & core logic
│  ├─ db.php                 # PDO connector (reads config)
│  ├─ auth.php               # Sessions & auth helpers
│  ├─ afcd_cache.php         # In-memory AFCD cache
│  ├─ afcd_search_lib.php    # Fuzzy matching utilities
│  ├─ afcd_search.php        # GET ?q=...
│  ├─ nutrition_lib.php      # Nutrient maths helpers
│  ├─ nutrition_calc.php     # POST ingredients[] grams[] → totals
│  ├─ products_list.php      # GET list
│  ├─ products_get.php       # GET one
│  ├─ products_save.php      # POST create/update
│  ├─ products_delete.php    # POST delete
│  ├─ orders_create.php      # POST create order from builder
│  ├─ orders_get.php         # GET one order (+nutrition)
│  ├─ orders_list.php        # GET recent orders
│  └─ email_receipt.php      # POST send email for order
├─ admin/                    # Login-gated admin UI
│  ├─ login.php  logout.php  admin.php  orders.php
├─ db/                       # DB artefacts and utilities
│  ├─ schema.sql             # MySQL schema
│  └─ mysql_orders.php       # Simple HTML reporting view
├─ config/
│  ├─ config.php             # Loads env, sets sane defaults
│  └─ local.php              # Optional dev/prod overrides
├─ data/
│  ├─ afcd.csv               # AFCD source data
│  ├─ products.json          # Saved recipes (JSON)
│  └─ NutriPOS_BasicRecipes.json
├─ EXTERNAL_DATABASE/
│  └─ square_webhook.php     # Square order webhook handler (optional)
├─ composer.json             # Dependencies (Dotenv, PHPMailer, Square)
└─ README.md                 # This file
```

---

## Tech Stack
- **PHP 8+**, Composer
- **MySQL/MariaDB** (PDO)
- **vlucas/phpdotenv**
- **PHPMailer**
- **Square PHP SDK (optional)**
- **Vanilla JavaScript**

---

## GitHub Repository

**Project Repository (main):**  
[https://github.com/saaduly101/KP628---NutriPOS/tree/main](https://github.com/saaduly101/KP628---NutriPOS/tree/main)

**Active Development Branch (latest updates):**  
[https://github.com/saaduly101/KP628---NutriPOS/tree/develop](https://github.com/saaduly101/KP628---NutriPOS/tree/develop)

---

## Deployed Project URL

Currently not deployed online.  
The project must be run locally using **XAMPP** or an equivalent PHP server.

---

## Quick Start
1. **Install PHP & Composer**
```bash
php -v
composer -V
```
2. **Install Dependencies**
```bash
composer install
```
3. **Create and Populate .env** (see below)
4. **Create the Database**
```bash
mysql -u root -p -e "CREATE DATABASE nutripos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p nutripos < NUTRIPOS/db/schema.sql
```
5. **Run Locally**
```bash
php -S 127.0.0.1:8080 -t NUTRIPOS/public
```
6. Visit http://127.0.0.1:8080/

---

## Configuration
NutriPOS reads environment variables from `.env`, merges defaults from `config/config.php`, and optional overrides from `config/local.php`.

```env
# App
APP_ENV=local
LOCALE=en_AU

# Database
DB_SERVERNAME=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=secret
DB_NAME=nutripos

# SMTP
SMTP_HOST=smtp-relay.example.com
SMTP_PORT=587
SMTP_USERNAME=postmaster@example.com
SMTP_PASSWORD=app-password-or-token
SMTP_FROM="NutriPOS <no-reply@example.com>"
SMTP_SECURE=tls
SMTP_ENABLED=true

# Square (optional)
SQUARE_ENVIRONMENT=SANDBOX
SQUARE_ACCESS_TOKEN=your_token
SQUARE_WEBHOOK_SIGNATURE_KEY=your_webhook_secret
SQUARE_NOTIFICATION_URL=https://your.host/EXTERNAL_DATABASE/square_webhook.php
```

---

## API Endpoints
| Category | Endpoint | Method | Description |
|-----------|-----------|--------|--------------|
| **AFCD & Nutrition** | `backend/afcd_search.php?q=` | GET | Fuzzy ingredient search |
|  | `backend/nutrition_calc.php` | POST | Calculate total nutrition |
| **Products** | `backend/products_save.php` | POST | Save or update a recipe |
|  | `backend/products_list.php` | GET | List all recipes |
| **Orders** | `backend/orders_create.php` | POST | Create a new order |
|  | `backend/orders_get.php?id=` | GET | Retrieve one order |
| **Receipts & Email** | `backend/email_receipt.php` | POST | Send order receipt |
|  | `public/receipt.html?id=` | GET | View receipt by QR |

---

## Database Schema Overview
| Table | Description |
|--------|--------------|
| **orders** | Stores order details (id, total, email, timestamp). |
| **order_line_items** | Stores each item in an order with grams and price. |
| **nutrition_snapshot** | Captures energy, protein, fat, carbs, sugars, sodium for each order. |

---

## AFCD Caching Mechanism
- `backend/afcd_cache.php` loads `data/afcd.csv` once per PHP process.
- `backend/afcd_search_lib.php` performs normalisation and fuzzy scoring.

---

## Security Notes
- Keep `.env` outside the web root.
- Secure `admin/*` pages with strong credentials.
- Validate and sanitise all user input.
- Verify Square webhook signatures.

---

## Troubleshooting
| Issue | Solution |
|--------|-----------|
| **Dotenv class not found** | Run `composer install` and include autoload. |
| **Cannot connect to database** | Check `.env` and MySQL configuration. |
| **Email not sending** | Verify SMTP credentials. |
| **AFCD search slow** | Ensure `afcd_cache.php` and `afcd.csv` are available. |
| **Webhook not firing** | Confirm HTTPS endpoint matches Square configuration. |

---

## Team Members
| Name | Student ID | Role |
|------|-------------|------|
| **Gabriel Jones** | S3957629 | Team Leader |
| **Muhammad Nauman** | S4007917 | Backend / API Developer |
| **Vy Mulqueen** | S3933172 | UI / API Developer |
| **Phat Dang Truong** | S3963893 | Frontend Developer |
| **Gujun Lu** | S3862761 | Frontend & Documentation Developer |

---

## Summary
NutriPOS combines usability and transparency for restaurant management. It streamlines recipe creation, nutritional calculation, and POS order integration using PHP, MySQL, and AFCD data. Through a modular structure, cached data, and well-defined APIs, NutriPOS demonstrates practical web development principles. Its architecture ensures performance, maintainability, and scalability while addressing real-world needs for efficiency, accuracy, and health awareness.
