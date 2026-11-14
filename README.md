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
- **Several currencies** (USD, AFN, PKR ... ticked in Settings): amounts are never converted, every total is
  shown per currency, and currency chips or a currency filter on the dashboard, dues, contracts, income,
  expenses, people, services and reports show one currency at a time.
- Team (owner and members), time zone.
- **Subscription**: every plan on offer with the current one marked, how to pay, and an "I have paid" form
  with the screenshot. A business chooses a plan on its trial and after the end. A paid period is never
  refunded or changed: in its last 30 days (a setting) the business may renew or choose another plan,
  which starts when the period ends. A higher plan can be taken at any time: it is paid in full and starts
  that day. The page stays open after the trial or the subscription has ended. The dashboard shows the
  plan, the running period with a bar, and the days left.

**For the platform owner (`/admin`, separate sign-in)**

- Businesses: open, edit, set plan, trial and paid-until dates, suspend, delete.
- Plans with limits (clients, users), subscriptions, and the financial accounts the money is received in.
  The details of an account in use are shown to businesses as the way to pay.
- A business that says it has paid appears on the admin dashboard; pressing **Confirm** and saving
  (with "Paid on" filled in) makes it active until the end date. Nothing is charged automatically.
  **Reject** asks for a reason from a list (`reject_reasons` in `config/duebook.php`); the business gets
  an email, sees the reason on its Subscription page and can send a new screenshot.
- Payment for now is by **HesabPay**: the seed adds the account with its number and QR code. A business
  scans it, pays, and uploads the screenshot; you get an email with the screenshot attached
  (to `BILLING_EMAIL`, or to every administrator when that is empty).
- Each financial account has a statement: every payment received in it, who paid, when, for what, with
  the screenshot. A confirmed payment must say which account it came into.
- **Expenses** of the platform itself and a **profit report** (received, spent, profit by month; by plan and
  by category; how many trials became paying). An account statement shows its balance too.
- **Receipts**: every confirmed payment gets a number (DB-00001 ...) and a print-ready receipt page for
  the business and for you ("Print or save as PDF" in the browser).
- **Emails to businesses**, sent by themselves: trial ending, trial ended, subscription ending,
  subscription ended, payment confirmed (`duebook:lifecycle`, every hour by the scheduler).
- **Settings** page: trial days, sign-up on or off, words beside the price, who is told about payments,
  receipt prefix and company name, support contact, warning days. Saved values win over `.env`.
- **Activity log**: sign-ins, businesses opened, changed, suspended or deleted, payments confirmed or
  rejected, and every record added, changed or deleted in the admin panel.
- Administrators with **roles** (super, manager, viewer), and each one's own account page.

All businesses share one database; every row carries a `tenant_id` and a model with the
`BelongsToTenant` trait only ever returns the rows of the signed-in user's business.

## The public website

`/` is the website people find: what DueBook is, how it is used, who it is for, pricing, questions,
contact, privacy and terms. Its words are in `config/site.php`; prices, the trial and the ways to pay
come from the live plans, settings and financial accounts. For search engines and AI assistants it has
a title, description and canonical address on every page, Open Graph and Twitter tags, structured data
(Organization, WebSite, SoftwareApplication with offers, FAQPage, BreadcrumbList), `/sitemap.xml`,
`/robots.txt` and `/llms.txt`. The application itself stays `noindex`.

Set `APP_URL` to the real address (https://getduebook.com) on the server: every address the site gives
to search engines is built from it.

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
php artisan migrate --seed          # the seed adds the plan: 199 USD a year
php artisan app:create-admin        # your sign-in for /admin
php artisan serve
```

- A new business gets 7 free days (`TRIAL_DAYS`), then pays 199 USD a year (change it under `/admin` > Plans).
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

## Putting it online (Hostinger, getduebook.com)

Once, on the server (hPanel > Advanced > SSH Access gives the `ssh` command; PHP must be 8.3 or newer
under hPanel > Advanced > PHP Configuration):

```
cd ~/domains/getduebook.com
git clone -b main https://github.com/fidatech/duebook.git duebook
cd duebook
composer2 install --no-dev --optimize-autoloader
cp .env.example .env            # then fill it in: see below
php artisan key:generate
php artisan migrate --force --seed
php artisan app:create-admin
php artisan config:cache && php artisan route:cache && php artisan view:cache

# the web server shows public_html: make it the public folder of the project
cd ~/domains/getduebook.com
mv public_html public_html.old
ln -s duebook/public public_html
```

In `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://getduebook.com`, the
`DB_*` lines of the MySQL database made in hPanel, and the `MAIL_*` lines of a mailbox such as
`no-reply@getduebook.com` (`MAIL_MAILER=smtp`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`,
`MAIL_SCHEME=smtps`).

Cron job (hPanel > Advanced > Cron Jobs), every minute, or at least every hour on minute 0:

```
/usr/bin/php /home/USERNAME/domains/getduebook.com/duebook/artisan schedule:run
```

**Updates.** `bash deploy.sh` on the server brings it up to date with the `main` branch. The workflow
`.github/workflows/deploy.yml` runs it over SSH after every push to `main`, once the five secrets named
at the top of that file are added to the repository.

## Tests

```
php artisan test
```
