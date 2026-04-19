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

### 5. Environment Variables (Google Maps API)

This project uses the Google Maps API, which requires a local environment variable for security.

**STEP 1: Get API Key**
Go to: [https://console.cloud.google.com/](https://console.cloud.google.com/)

Enable:
- Places API
- Maps JavaScript API

Create an API key.

**STEP 2: Set Up Environment File**
1. Navigate to the example file in the root directory: `.env.example`
2. Copy the contents of it (`GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here`)
3. In the project root directory, create a file named: `.env` and paste the content from the example file

The application automatically loads this key using src/config.php.

⚠️ Important:
- Do NOT commit the `.env` file to GitHub
- Each developer must create their own `.env` file locally
- If missing, Google Maps features will not work

### 6. Email Notifications Setup

This project uses **PHPMailer with Gmail SMTP** to send email notifications when a user receives a new message.

---

####  Local Setup

1. Navigate to the example config file:
public/email_config.example.php

2. Copy and rename it:
public/email_config.php

3. Open `email_config.php` and fill in the credentials:
- email: `campusswap.team@gmail.com`
- password: `kbgqjcaqippbweaa`

Email notifications are triggered when a message is sent and are handled using PHPMailer with Gmail SMTP authentication.

Each developer must create their own copy of this file, do not commit credentials to the repo.

### 7. Run the app

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
