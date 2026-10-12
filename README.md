# ÉLAN Boutique Login & Role-Based Access System

A polished PHP + MySQL authentication demo for Visual Studio Code / XAMPP.

## Features
1. **User identification:** sign in with a unique username or email.
2. **User authentication:** password verification using PHP `password_hash()` / `password_verify()`, followed by a six-digit one-time verification code.
3. **User authorization:** three roles with different permissions:
   - **Admin:** dashboard, management summaries, and staff account creation.
   - **Manager:** dashboard and management summaries.
   - **Sales Assistant:** standard staff dashboard.
4. Session ID regeneration, CSRF protection, prepared SQL statements, one-time-code expiry, attempt limits, password hashing, and basic failed-login throttling.
5. Self-service password changes and admin password resets for staff accounts.
6. Responsive boutique-inspired design.

## Run in VS Code with XAMPP
1. Install and start **Apache** and **MySQL** in XAMPP.
2. Copy the project folder (the one containing `index.php`, `config.php`, and `assets`) into `C:\\xampp\\htdocs\\boutique_login_system\\`.
3. Open the folder in VS Code.
4. Check `config.php`. The default local XAMPP database values are `root` and a blank password.
5. For a new installation only, visit `http://localhost/boutique_login_system/setup.php` once. This creates the database, tables, and demo accounts. Do not rerun setup on an existing installation: it resets the demo account passwords.
6. Open `http://localhost/boutique_login_system/`.

**Folder check:** `index.php` must be at `C:\\xampp\\htdocs\\boutique_login_system\\index.php`, not one folder deeper. A nested copy such as `C:\\xampp\\htdocs\\boutique_login_system\\boutique_login_system\\index.php` will not load at the URL above. A 404 means Apache cannot find the project at that URL; check the folder location and that Apache is running.

## Demo accounts
- Admin: username `admin`, password `Admin123!`
- Manager: username `manager`, password `Manager123!`
- Sales Assistant: username `sales`, password `Sales123!`

After the password is accepted, enter the six-digit code shown on the verification page while running in development mode.

## Important second-factor note
This project demonstrates a second factor with a short-lived one-time code. With SMTP not configured, `APP_ENV = 'development'` shows the code on screen for classroom demonstration. **This is not suitable for a deployed/production system.** For real use:
- Set up a working SMTP mail service and configure `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, and `SMTP_FROM_NAME` in `config.php`.
- Update `send_otp_email()` to use an SMTP library such as PHPMailer (recommended; PHP's `mail()` requires server mail configuration).
- Change `APP_ENV` to `production`. In production, the application will not display a code if email delivery fails.
- Use HTTPS, secure session cookie settings, rate limiting at the web-server/application level, password reset, audit logging, and remove/restrict `setup.php`.
- Change all demo passwords. Never use the supplied demo accounts for a public deployment.

## Files
- `index.php` — username/email + password login
- `verify.php` — second-factor code verification
- `dashboard.php` — role-aware dashboard
- `change_password.php` — current user password update form
- `users.php` — Admin-only user creation, directory, and password reset tools
- `logout.php` — logout/cancel pending sign-in
- `config.php` — database, session, CSRF, and mail settings
- `schema.sql` — database schema
- `setup.php` — one-time database and demo-account setup
- `assets/style.css` — responsive styling
