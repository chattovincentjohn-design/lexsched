# LexSched

Online appointment and notarial system for a law office, built with Laravel 13, MySQL (or SQLite) and plain Blade + CSS.

## Features

- **Clients** register, book a consultation in four steps (service, lawyer and open time, case details, payment), upload a supporting document, track, **reschedule** (up to 24 hours before, twice at most) or cancel their appointments, and print an acknowledgment receipt.
- **Secretary** approves or declines requests, sees an automatic **conflict-of-interest check**, works from a **day calendar** across all lawyers, reschedules, records payments and official receipt numbers, adds walk-in clients, and runs the **notarial checklist** (personal appearance, valid ID, complete document).
- **Notarial register**: each notary public gets sequential Doc. No. / Page No. / Book No. / Series entries, exportable to CSV.
- **Lawyers** see a weekly calendar, block hours or whole days for hearings and leave, and keep private consultation notes.
- **Admin** sees office numbers, manages services, fees, notarial document types, **holidays** and staff accounts, exports reports, and reads the audit log.
- **Accounts**: forgot-password by email link, profile and password change for every role.
- **No double booking**: a unique database index on each lawyer's date and hour, plus checks for weekends, holidays, lunch break and blocked hours.
- **Online payments** through PayMongo (GCash, Maya, cards), confirmed automatically.
- **SMS and email notifications** for received, approved, declined and cancelled bookings, and a reminder the day before (SMS through Semaphore).

## Requirements

PHP 8.3+, Composer, and either SQLite (no setup) or MySQL/MariaDB (XAMPP or Laragon). Node.js is **not** required.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

For XAMPP/MySQL, create a database named `lexsched` in phpMyAdmin, then in `.env` switch `DB_CONNECTION` to `mysql` and fill in the MySQL lines. For SQLite, run `touch database/database.sqlite` instead.

```bash
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000.

## Demo accounts

All passwords are `password`.

| Role | Email |
|---|---|
| Client | client@lexsched.test |
| Secretary | secretary@lexsched.test |
| Lawyer (notary) | delacruz@lexsched.test, santos@lexsched.test |
| Lawyer | villanueva@lexsched.test |
| Admin | admin@lexsched.test |

## Reminders and SMS

Reminders are sent by `php artisan appointments:send-reminders`, scheduled hourly. On a server, add the Laravel scheduler to cron:

```
* * * * * cd /path/to/lexsched && php artisan schedule:run >> /dev/null 2>&1
```

Without `SEMAPHORE_API_KEY` in `.env`, text messages are written to `storage/logs/laravel.log` instead of being sent. Emails use `MAIL_MAILER=log` until you configure SMTP.

## Online payments (PayMongo)

Clients can pay with GCash, Maya or a debit/credit card on PayMongo's secure checkout page. Payments are confirmed automatically, both when the client returns to the site and through PayMongo's webhook. Without a PayMongo key, the booking form falls back to manual GCash/Maya transfers with a reference number that the secretary checks.

1. Create an account at https://dashboard.paymongo.com and copy the **test secret key** (`sk_test_...`) from **Developers → API keys**.
2. Add it to `.env` as `PAYMONGO_SECRET_KEY=sk_test_...`, then run `php artisan config:clear`.
3. Once the site has a public `https://` address, register the webhook. The command prints a secret to put in `.env` as `PAYMONGO_WEBHOOK_SECRET`:

```bash
php artisan paymongo:webhook https://your-domain/webhooks/paymongo
```

On `localhost` the webhook can't reach you, but payments are still confirmed when the client returns from the payment page. Test-mode payments use PayMongo's test GCash, Maya and card screens and move no real money. Switch to the live key (`sk_live_...`) only after PayMongo activates your account. Refunds for cancelled appointments are done from the PayMongo dashboard.

## Tests

```bash
php artisan test
```

## Where things are

| Area | Files |
|---|---|
| Booking rules and availability | `app/Services/AvailabilityService.php`, `app/Services/BookingService.php` |
| Status changes and notifications | `app/Services/AppointmentWorkflow.php`, `app/Notifications/` |
| Conflict-of-interest check | `app/Services/ConflictChecker.php` |
| Notarial register numbering | `app/Services/NotarialRegister.php` |
| Office hours, reschedule rules, fees, ID types | `config/lexsched.php` |
| Holidays (seed list; admins add more in the app) | `database/seeders/HolidaySeeder.php` |
| Database tables | `database/migrations/2026_11_26_000100_create_lexsched_tables.php` |
| Pages | `resources/views/`, styles in `public/css/app.css`, scripts in `public/js/app.js` |
