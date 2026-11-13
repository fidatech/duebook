<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands:
//   app:create-admin    a system administrator for /admin
//   duebook:remind      the daily reminder emails
//
// The server's cron runs "php artisan schedule:run" every minute. The reminders are tried
// every hour because businesses live in different time zones: each one gets its email
// once a day, at the hour it chose in Settings.
Schedule::command('duebook:remind')->hourly()->withoutOverlapping();
