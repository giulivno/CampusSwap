# CampusSwap

A student-only online marketplace for University of Florida and Santa Fe College students to buy, sell, and trade items locally on campus.

## Team

| Name | Role |
|------|------|
| Salena Till | Team Captain |
| Ava Vellozzi | TBD |
| Jackson Kelly | TBD |
| Belal Mansour | TBD |
| Giuliano Di Lorenzo | TBD |

---

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL (via phpMyAdmin)
- **Server:** Apache
- **Local Environment:** XAMPP
- **Version Control:** Git / GitHub

---

## Getting Started

### 1. Install XAMPP

Download and install XAMPP from https://www.apachefriends.org. Make sure **Apache** and **MySQL** are running in the XAMPP Control Panel before continuing.

### 2. Clone the repo

Open Git Bash and run:

```bash
cd /c/xampp/htdocs
git clone https://github.com/giulivno/CampusSwap.git
cd CampusSwap
```

### 3. Set up the database

1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **SQL** in the top nav
3. Run this to create the database:
```sql
CREATE DATABASE IF NOT EXISTS campusswap;
```
4. Click on **campusswap** in the left sidebar
5. Click **SQL** again and paste the contents of `sql/schema.sql`
6. Hit **Go**

You should now see 7 tables: `campuses`, `users`, `categories`, `listings`, `listing_images`, `messages`, `saved_listings`.

### 4. Configure the database connection

Open `src/db.php` and confirm the credentials match your local XAMPP setup. The defaults should work out of the box:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'campusswap');
```

If your XAMPP MySQL has a password set, update `DB_PASS` accordingly.

### 5. Run the app

Go to `http://localhost/CampusSwap/public/index.php` in your browser. You should be redirected to the login page.

---

## Folder Structure

```
CampusSwap/
├── public/             # All web-accessible pages
│   ├── index.php       # Home / listing feed
│   ├── login.php       # Login page
│   ├── register.php    # Registration page
│   ├── logout.php      # Logout handler
│   └── assets/
│       ├── css/        # Stylesheets
│       └── js/         # JavaScript files
├── src/                # PHP backend logic
│   ├── db.php          # Database connection
│   ├── auth.php        # Login, registration, session logic
│   └── helpers.php     # Shared utility functions
├── uploads/            # User-uploaded listing images (not committed)
├── sql/
│   └── schema.sql      # Full database schema
├── .htaccess           # Apache URL routing
└── README.md
```
