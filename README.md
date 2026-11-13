# DueBook

Know who should pay you, and when. A multi-tenant SaaS by Fida Technologies for people who sell
services that are paid again and again (hosting, domains, software support, subscriptions) and
want one place for their clients, renewals, loans, income and expenses.

Built on the same template as `fida-sarafi`: on a phone or tablet it looks and works like an
installed app (bottom bar, cards instead of tables, "Add to Home Screen").

## What is in it

**For a business (tenant)**

- **Due & Renewals**: late, next 7, 30 and 90 days, with totals per currency, a Receive button
  and a WhatsApp reminder button.
- **Clients** and their **contracts**: what it is for, a reference (domain, cPanel user ...),
  amount, currency, how often it is paid, next due date, and a scan of the signed contract.
- **Receiving a payment** writes the income by itself and moves the contract to its next due date.
  A part payment leaves the due date where it is.
- **Services & Products**: what you sell, including digital products, each with its income category.
- **Income** and **Expenses** with categories, filters and totals.
- **People & Loans**: for each person, "I gave" and "I received" entries and a balance that says
  who owes whom. An entry may have a date it should be settled by.
- **Reports**: money to expect in each of the next 12 months, income and expenses by month and by category.
- **Reminder emails**: one email a day to the business (what is coming, what is late), and, for
  contracts where it is switched on, an email to the client. Days and hour are set in Settings.
- Team (owner and members), several currencies (never converted, totals are per currency), time zone.

**For the platform owner (`/admin`, separate sign-in)**

- Businesses: open, edit, set plan, trial and paid-until dates, suspend, delete.
- Plans with limits (clients, users), subscriptions, and the financial accounts the money is received in.
- Administrators.

All businesses share one database; every row carries a `tenant_id` and a model with the
`BelongsToTenant` trait only ever returns the rows of the signed-in user's business.

## Stack

Laravel 13, PHP 8.3 or newer, MySQL 8 (SQLite for tests), Blade with a little JavaScript.
No Node is needed: `php tools/build-assets.php` rebuilds the CSS, JavaScript and icons after
views change (it removes unused CSS, so run it when you use a class or icon for the first time).

## Set up

```
composer install
cp .env.example .env
php artisan key:generate
# create the database named in .env (DB_DATABASE), then:
php artisan migrate --seed          # the seed adds three example plans
php artisan app:create-admin        # your sign-in for /admin
php artisan serve
```

- Businesses sign up at `/register` (switch off with `REGISTRATION_OPEN=false`) or are opened by an
  administrator under `/admin` > Businesses.
- For your own business: open it in `/admin` with status **Active** and **Paid until** empty; it never ends.

## Email and the daily reminders

Set the `MAIL_*` lines in `.env` to a real SMTP account (with `MAIL_MAILER=log` emails are only
written to `storage/logs`). Then add the Laravel scheduler to the server's cron:

```
* * * * * cd /path/to/duebook && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `duebook:remind` every hour; each business gets its email once a day at the hour
it chose. To try it now: `php artisan duebook:remind --force`.

## Tests

```
php artisan test
```
