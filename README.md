# NutriPOS (KP628)

NutriPOS is a web-based nutrition and recipe management system designed for seamless integration with restaurant POS workflows. It allows users to view, add, edit, and manage custom food recipes while calculating nutritional information dynamically.

---

## 📋 Prerequisites

- **XAMPP** (recommended) — includes Apache, PHP, and MySQL
- Alternatively, any server stack that supports:
  - PHP 8.0+
  - MySQL 5.7+
  - Apache or Nginx web server

---

## ⚙️ Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/NutriPOS.git
   ```

2. **Move to your local server directory**
   - Example for XAMPP:  
     Place the entire `NUTRIPOS` folder under:
     ```
     C:\xampp\htdocs\
     ```

3. **Set up the database**
   - Open **phpMyAdmin**
   - Create a new database (e.g., `nutripos_db`)
   - Import the schema file located at:
     ```
     /NUTRIPOS/backend/db/schema.sql
     ```

4. **Configure connection settings**
   - Edit `/NUTRIPOS/backend/db/mysql_orders.php` and update the following fields with your local credentials:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "nutripos_db";
     ```

5. **Start Apache and MySQL** via XAMPP Control Panel.

---

## 🚀 Usage

### 🧾 1. Product Management
- Open:
  ```
  http://localhost/NUTRIPOS/public/products.php
  ```
- Features:
  - View all saved recipes from the database  
  - Add a new recipe  
  - Edit or delete an existing recipe  

### 🍳 2. Custom Recipe Builder
- Accessed by creating or editing a recipe:
  ```
  http://localhost/NUTRIPOS/public/custom_pos_builder.js
  ```
- Features:
  - Add, edit, or remove individual ingredients  
  - Automatically calculate nutritional data (via AFCD dataset integration)
  - Update total calories, macros, and other nutrition values in real time  

### 👩‍💼 3. Admin Management
- Login page: `/NUTRIPOS/admin/login.php`
- Admins can:
  - Manage user access
  - Review or remove recipes
  - Track submission history

---

## 🗄️ Directory Structure

```
NUTRIPOS/
│
├── admin/                 # Admin login and management
│   ├── login.php
│   ├── logout.php
│   └── admin.php
│
├── backend/               # Core server-side logic and configuration
│   ├── db/
│   │   ├── mysql_orders.php
│   │   └── schema.sql
│   └── config/
│
├── public/                # Frontend pages and assets
│   ├── products.php
│   ├── custom_pos_builder.js
│   ├── style.css
│   ├── assets/
│   └── receipt.html
│
├── vendor/                # Composer dependencies
├── .env                   # Environment variables (optional)
├── README.md
└── composer.json
```

---

## 🧠 Features Overview

- ✅ CRUD operations for recipes and ingredients  
- ✅ Nutrition data auto-calculation (AFCD integration)  
- ✅ Dynamic frontend UI with responsive design  
- ✅ Secure login/logout system for admin users  
- ✅ Simple local deployment via XAMPP  

---

## 🧑‍💻 Contributors

- **Gujun Lu (S3862761)** – Backend / UI Integration  
- **Gabriel Jones** – Frontend / Testing  
- **Vy Mulqueen** – UX & Documentation  
- **Truong Phat Dang** – API & Database  

---

## 🪄 Future Improvements

- 🔗 Integration with Square POS API  
- 📊 Nutrition analytics dashboard  
- ☁️ Firebase sync for cloud storage  
- 📱 Full mobile responsiveness  

---

## 🖼️ Screenshot (Example)

> Add your screenshot here, e.g.:
>
> ![NutriPOS Dashboard](public/assets/dashboard-example.png)

---

## 📄 License

This project is for educational purposes under the RMIT University course *COSC2629 Software Engineering Project Management*.
