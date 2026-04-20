# Salon Management System

A professional Salon Management Website built with PHP, MySQL, pure CSS, and JavaScript.

This project manages:
- Customer booking
- Services and stylist profiles
- Staff scheduling
- Appointment calendar
- Client history
- Inventory alerts
- Payments and printable invoices
- Role-based access for admin, receptionist, stylist, and client

## Tech Stack

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- XAMPP

## Main Features

### Customer Side
- Luxury homepage with hero banner and salon visuals
- Dedicated service menu page
- Stylist profile page with specialization and experience
- Calendar-style appointment booking flow
- Available slot display
- Double-booking prevention for stylists
- Testimonials loaded from database
- Customer appointment history
- Simulated notifications for confirmations and reminders

### Admin / Staff Side
- Dashboard with operational summary
- Smart daily appointment view
- Staff shift and availability management
- Commission tracking
- Inventory low-stock alerts
- Client CRM with service history
- Printable invoice generation

## Roles

- `Admin`: full access
- `Receptionist`: operational access
- `Stylist`: access to own schedule and bookings
- `Client`: booking and profile access

## Important Pages

- `index.php` - homepage
- `services.php` - all salon services
- `stylists.php` - stylist profiles
- `appointments.php` - booking and appointment history
- `calendar.php` - weekly appointment calendar
- `clients.php` - client management and history
- `staff.php` - staff profile, services, shifts, availability
- `payments.php` - checkout and invoice printing
- `admin/dashboard.php` - admin/receptionist dashboard
- `stylist/dashboard.php` - stylist dashboard
- `user/dashboard.php` - client dashboard

## Setup Instructions

1. Place the project inside your XAMPP `htdocs` folder.
2. Start `Apache` and `MySQL` from XAMPP.
3. Create a database named `elegance_salon` or import `database.sql`.
4. Open [includes/db.php](/c:/xampp/htdocs/salon-management/includes/db.php) and confirm your database credentials:

```php
$host = 'localhost';
$dbname = 'elegance_salon';
$username = 'root';
$password = '';
```

5. Open the project in your browser:

```txt
http://localhost/salon-management/
```

## Email Configuration

### 1. Localhost (XAMPP)

1. Open your XAMPP `php.ini` file.
2. Find the `[mail function]` section.
3. Set:
   - `SMTP=smtp.gmail.com`
   - `smtp_port=587`
   - `sendmail_from = your_email@gmail.com`
   - `sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"`
4. Open `C:\xampp\sendmail\sendmail.ini`.
5. Configure `sendmail.ini` with your Gmail credentials.

### 2. Live Server (cPanel)

1. On cPanel hosting, emails are sent automatically through the server `sendmail` binary.
2. No code changes are needed.

### 3. Troubleshooting

1. If emails land in Spam, verify your domain SPF and DKIM DNS records.

## Database Notes

The project includes:
- `database.sql` for initial schema
- automatic schema sync in `includes/db.php`

Runtime schema sync adds missing structures safely for:
- `reviews`
- `commissions`
- `notifications`
- `staff_availability`
- extra service/staff/user/appointment columns

## Default Admin Login

Use the seeded admin account from the SQL file:

- Email: `admin@elegance.local`
- Password: `admin123`

## Assets

Custom visual assets were added in:
- `assets/images/hero-salon-main.svg`
- `assets/images/hero-stylist.svg`
- `assets/images/hero-beauty.svg`
- `assets/images/stylist-default.svg`

## UI Rules Followed

- No Bootstrap
- No jQuery
- Pure CSS and JavaScript
- Responsive layout
- Modular includes structure maintained

## Notes for Development

- Shared helpers live in `includes/salon_helpers.php`
- Authentication helpers live in `includes/auth.php`
- Database connection and schema sync live in `includes/db.php`
- Global styling is in `assets/css/style.css`
- Global interactivity is in `assets/js/script.js`

## Verification

PHP syntax was checked with:

```txt
C:\xampp\php\php.exe -l
```

on the updated PHP files with no syntax errors reported.
