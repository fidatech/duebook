<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands:
//   app:create-admin    a system administrator for /admin
//   duebook:remind      the daily reminder emails of each business
//   duebook:lifecycle   emails from the platform to its businesses (trial ending, renewal due)
//
// The server's cron runs "php artisan schedule:run" every minute. The reminders are tried
// every hour because businesses live in different time zones: each one gets its email
// once a day, at the hour it chose in Settings.
Schedule::command('duebook:remind')->hourly()->withoutOverlapping();

// Emails from the platform to its businesses: trial ending, renewal due, ended. Also hourly, once a day each.
Schedule::command('duebook:lifecycle')->hourly()->withoutOverlapping();
