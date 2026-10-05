# Student SkillBridge

A free peer-to-peer skill-learning platform for university and college
students. Students list the skills they can teach and the skills they
want to learn. The site matches them with compatible peers and explains
every match, then lets them arrange, complete and review learning
sessions.

MIT122 Interactive Web Design and Development, Assessment 2.
Harikrushna Patel (985703).

Repository: https://github.com/hariztek/MIT122-assessment

---

## Contents

1. [Features](#features)
2. [Technology](#technology)
3. [Requirements](#requirements)
4. [Setup on Windows (XAMPP)](#setup-on-windows-xampp)
5. [Setup on macOS (MAMP)](#setup-on-macos-mamp)
6. [Demo accounts](#demo-accounts)
7. [Using the system](#using-the-system)
8. [How matching works](#how-matching-works)
9. [Security](#security)
10. [Project structure](#project-structure)
11. [Resetting the demo data](#resetting-the-demo-data)
12. [Troubleshooting](#troubleshooting)

---

## Features

| Req | Feature | Page |
|---|---|---|
| FR-01 | Create an account, with server-side validation and hashed passwords | `register.php` |
| FR-02 | Log in and stay logged in with a PHP session | `login.php` |
| FR-03 | Record skills offered and wanted, with level, mode, availability and description | `profile.php` |
| FR-04 | Edit or remove your own skill entries | `profile.php` |
| FR-05 | Keyword and category search across skills | `search.php` |
| FR-06 | Deterministic, explained match scores (no AI/ML) | `matches.php` |
| FR-07 | Send a session request with a goal and proposed time | `dashboard.php` |
| FR-08 | Accept or decline a pending request; cancel or complete an accepted one | `dashboard.php` |
| FR-09 | Leave a review only after a shared completed session | `dashboard.php` |
| FR-10 | Admins moderate accounts, skill listings and reviews | `admin.php` |

`index.php` is the public landing page. There are 8 pages in total.

## Technology

| Layer | Technology | Responsibility |
|---|---|---|
| Browser | HTML5, CSS3, plain JavaScript (no framework) | Layout, responsive design, navigation, instant form feedback |
| Server | PHP 8 (server-rendered, one script per page) | Authentication, authorisation, validation, match scoring, request state changes |
| Database | MySQL (or MariaDB) via PDO | Six related tables with foreign keys |

The JavaScript checks in `assets/js/validation.js` are a convenience only.
PHP repeats every check on the server, so the site stays correct even
with JavaScript switched off.

## Requirements

- **PHP 8.0 or newer** with the `pdo_mysql` extension (both XAMPP and
  MAMP include it).
- **MySQL 5.7+ or MariaDB 10.4+.**
- A modern browser (Chrome, Edge, Firefox or Safari).

> **Important:** the site uses links that start from the web root
> (for example `/pages/login.php`). It must be served from the root of
> a web server, such as `http://localhost:8000/`. Placing it in a
> subfolder such as `http://localhost/skillbridge/` will break the
> links and styles. Both setups below avoid this.

---

## Setup on Windows (XAMPP)

### 1. Install XAMPP

Download XAMPP for Windows from https://www.apachefriends.org (any
version with PHP 8) and install it to the default folder, `C:\xampp`.

### 2. Start MySQL and Apache

Open the **XAMPP Control Panel** and click **Start** next to **Apache**
and **MySQL**. Both should turn green. Apache is needed here only for
phpMyAdmin.

### 3. Get the project files

Either clone the repository:

```
git clone https://github.com/hariztek/MIT122-assessment.git
```

or download the ZIP and extract it, for example to
`C:\Users\<you>\Documents\MIT122-assessment`.

### 4. Point the site at XAMPP's database

Open `includes\db.php` in a text editor and change the connection
settings to XAMPP's defaults:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');        // XAMPP uses 3306 (MAMP uses 8889)
define('DB_NAME', 'skillbridge');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP's root password is empty
```

### 5. Create the database

1. Go to http://localhost/phpmyadmin.
2. Click **New** in the left sidebar.
3. Enter the database name `skillbridge`, choose the collation
   `utf8mb4_unicode_ci`, and click **Create**.

### 6. Import the three SQL files, in this order

With `skillbridge` selected in the sidebar, open the **Import** tab and
import each file from the project's `database` folder, one at a time:

1. `schema.sql` creates the six tables.
2. `seed_skills.sql` adds the skill catalogue.
3. `seed_demo.sql` adds demo students, an admin, skills, and sample
   sessions.

The order matters: each file depends on the one before it.

<details>
<summary>Command-line alternative (Command Prompt or PowerShell)</summary>

Run these from the project folder:

```
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE skillbridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe -u root skillbridge -e "source database/schema.sql"
C:\xampp\mysql\bin\mysql.exe -u root skillbridge -e "source database/seed_skills.sql"
C:\xampp\mysql\bin\mysql.exe -u root skillbridge -e "source database/seed_demo.sql"
```

</details>

### 7. Run the site

Choose **one** option.

**Option A: PHP's built-in server (recommended, no Apache config)**

Open Command Prompt or PowerShell **in the project folder** and run:

```
C:\xampp\php\php.exe -S localhost:8000
```

Then open **http://localhost:8000** in your browser. Leave the window
open while you use the site, and press `Ctrl + C` to stop it.

**Option B: Apache, using XAMPP's `htdocs` folder**

1. Move the existing contents of `C:\xampp\htdocs` into a backup folder.
2. Copy the **contents** of the project folder (not the folder itself)
   into `C:\xampp\htdocs`, so `C:\xampp\htdocs\index.php` exists.
3. Open **http://localhost**.

phpMyAdmin keeps working at http://localhost/phpmyadmin.

---

## Setup on macOS (MAMP)

1. Install MAMP from https://www.mamp.info and open it.
2. In **MAMP > Preferences > Web Server** (or **Server > Document
   root** in newer versions), set the document root to the project
   folder.
3. Click **Start**. MAMP runs Apache on port 8888 and MySQL on port
   8889, with user `root` and password `root`. Those are the values
   already in `includes/db.php`, so no edit is needed.
4. Open phpMyAdmin from the MAMP start page, then create the
   `skillbridge` database and import the three SQL files exactly as in
   Windows steps 5 and 6.
5. Open **http://localhost:8888**.

To use PHP's built-in server instead, run `php -S localhost:8000` from
the project folder and change `DB_HOST` to `127.0.0.1` (see
[Troubleshooting](#troubleshooting)).

---

## Demo accounts

`seed_demo.sql` creates these accounts. They all use the same password:

**Password for every demo account:** `YGUpkCHBmRc`

This is a placeholder for local testing only and is not used anywhere
else.

| Email | Role | Useful for |
|---|---|---|
| `admin@example.test` | Admin | The admin page: accounts, skill listings, reviews |
| `priya@example.test` | Student | Teaches Python and SQL. Has a past session to mark completed and one to review |
| `marco@example.test` | Student | Wants Python. Has an accepted session with Priya |
| `lena@example.test` | Student | Teaches design and video. Has a completed session with Priya |
| `aiko@example.test` | Student | Teaches Mandarin and Python |
| `sam@example.test` | Student | Teaches cooking and public speaking |

You can also register a new account at `/pages/register.php`. New
accounts are always students. Admins are created only in the database.

## Using the system

**As a student**

1. **Register** or **log in**.
2. On **Profile**, add skills you offer and skills you want, each with a
   level, a mode (online, in person, or both), availability and a
   description. Edit or remove them at any time.
3. **Search** by keyword or category to browse other students' skills.
4. **Matches** ranks students who can teach what you want, with a
   points breakdown explaining each score.
5. Click **Request session** on a match or search result, then state
   your goal and a proposed time.
6. On the **Dashboard**:
   - **Incoming:** accept or decline requests sent to you.
   - **Sent by you:** follow your outgoing requests.
   - For an accepted session, **cancel** it, or **mark it completed**
     once its time has passed.
   - After a completed session, **leave a review** (1 to 5 stars and an
     optional comment). There is one review per person per session.

**As an admin** (log in as `admin@example.test` and open **Admin**)

- **Accounts:** search, then suspend or reactivate students. A suspended
  student is logged out at once and hidden from search.
- **Skills:** hide or restore individual skill listings. Hidden listings
  drop out of search and matches. The owner sees them marked "Hidden by
  an admin".
- **Reviews:** hide or restore reviews.

Nothing is deleted by moderation, so every action can be undone.

**Suggested demo path:** log in as Priya, mark the past session with
Marco completed, and review the completed session with Lena.
Log in as Marco to see matches and send a new request. Then log in as
the admin to moderate.

## How matching works

Matching is a fixed point score calculated in PHP
(`includes/matching.php`). It uses no AI or machine learning, and every
point is explained on the page.

| Points | Rule |
|---|---|
| +50 | **Skill:** they offer a skill you want to learn |
| +25 | **Mode:** you can meet the same way, or one of you is happy with either |
| +15 | **Availability:** your availability notes share a day or time of day |
| +10 | **Experience:** their level is above your current level |

The maximum is 100. Results are sorted by score.

## Security

- Passwords are stored with `password_hash()` and checked with
  `password_verify()`.
- Every database query is a PDO prepared statement with bound
  parameters, which prevents SQL injection.
- Every form that changes data (register, login, profile, skills,
  requests, reviews, admin actions) checks a CSRF (cross-site request
  forgery) token, so another website can't submit it on a user's behalf.
- Every request re-checks login, account status and role on the server.
  Users can only edit their own data, and only admins can reach
  `admin.php`.
- All output is HTML-escaped to prevent cross-site scripting (XSS).
- Request, review and moderation rules are enforced in PHP. Hidden form
  fields are never trusted.

## Project structure

```
index.php              Landing page
pages/
  register.php         Create an account
  login.php            Log in and log out
  profile.php          Profile details and skill entries
  search.php           Keyword and category search
  matches.php          Ranked, explained matches
  dashboard.php        Requests, sessions and reviews
  admin.php            Moderation (admins only)
includes/
  db.php               Database connection settings (edit for your setup)
  auth.php             Login, role and session checks
  functions.php        Shared validation and helper functions
  matching.php         Match scoring rules
  header.php, footer.php, auth_aside.php   Shared layout
assets/
  css/style.css        All styles (responsive)
  js/main.js           Navigation, account menu, confirmations, motion
  js/validation.js     Client-side form checks
  img/                 Logo
database/
  schema.sql           Tables: users, skills, user_skills,
                       session_requests, sessions, reviews
  seed_skills.sql      Skill catalogue
  seed_demo.sql        Demo accounts and sample data
docs/
  kanban.md            Project backlog and Kanban board
```

## Resetting the demo data

`schema.sql` drops and recreates every table. To return to a clean demo
state, import `schema.sql`, `seed_skills.sql` and `seed_demo.sql` again,
in that order. The sample sessions are dated relative to the moment you
import, so they always have something to act on.

## Troubleshooting

| Problem | Fix |
|---|---|
| "A database error occurred." | MySQL isn't running, or the settings in `includes/db.php` don't match your setup (port 3306 with an empty password for XAMPP; 8889 with `root` for MAMP). Check that the `skillbridge` database exists. |
| Page has no styling, or links give "Not Found" | The site is in a subfolder. Serve it from the web root (setup Option A or B). |
| Skill dropdown on Profile is empty | `seed_skills.sql` wasn't imported. Import it, then `seed_demo.sql`. |
| No search results or matches | `seed_demo.sql` wasn't imported. |
| XAMPP MySQL won't start (port 3306 in use) | Another MySQL is running. Stop it, or change XAMPP's MySQL port and set `DB_PORT` to match. |
| `'php' is not recognized` on Windows | Use the full path `C:\xampp\php\php.exe`, as shown in setup Option A. |
| On macOS with `php -S`: "Access denied for user 'root'" | `localhost` connects through a socket that may reach a different MySQL. Set `DB_HOST` to `127.0.0.1` so the port is used. |
| Logged out unexpectedly | An admin suspended the account, or the PHP session expired. Log in again. |
