<?php

return [
    // may a new business sign up by itself on /register?
    'registration' => (bool) env('REGISTRATION_OPEN', true),

    // days a new business may use the system before it needs a paid plan
    'trial_days' => (int) env('TRIAL_DAYS', 14),

    // how often a contract comes due: name => months (0 = paid once)
    'cycles' => [
        'monthly' => 1,
        'quarterly' => 3,
        'half_yearly' => 6,
        'yearly' => 12,
        'two_years' => 24,
        'three_years' => 36,
        'one_time' => 0,
    ],

    'cycle_labels' => [
        'monthly' => 'Every month',
        'quarterly' => 'Every 3 months',
        'half_yearly' => 'Every 6 months',
        'yearly' => 'Every year',
        'two_years' => 'Every 2 years',
        'three_years' => 'Every 3 years',
        'one_time' => 'One time',
    ],

    // reminders are sent this many days before the due date (0 = on the day)
    'remind_days' => [30, 7, 1, 0],

    // currencies offered in Settings; a business may type any other 3-letter code
    'currencies' => ['USD', 'AFN', 'EUR', 'GBP', 'AED', 'SAR', 'PKR', 'INR', 'IRR', 'TRY', 'CAD', 'AUD'],

    // categories every new business starts with
    'default_categories' => [
        'income' => ['Client renewals', 'Software and systems', 'Websites', 'Hosting and domains', 'Digital products', 'Other income'],
        'expense' => ['Servers and hosting', 'Domains', 'Salaries', 'Rent', 'Internet and phone', 'Marketing', 'Office', 'Other expenses'],
    ],
];
