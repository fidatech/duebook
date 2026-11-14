<?php

return [
    // may a new business sign up by itself on /register?
    'registration' => (bool) env('REGISTRATION_OPEN', true),

    // who is told by email when a business says it has paid (commas between several).
    // Empty: every administrator who may sign in.
    'billing_email' => env('BILLING_EMAIL'),

    // shown beside the price on the Subscription page, for people who pay in another currency
    'pay_note' => env('PAY_NOTE', 'or the equivalent in AFN'),

    // Why a payment can be rejected. The administrator picks one; the business reads "text" and "advice".
    'reject_reasons' => [
        'not_received' => [
            'label' => 'Money not received',
            'text' => 'We did not receive this payment in our account.',
            'advice' => 'Please check in your payment app that the transfer went through, then send the screenshot of the completed payment.',
        ],
        'less_amount' => [
            'label' => 'Amount is less than the price',
            'text' => 'The amount you paid is less than the price of the plan.',
            'advice' => 'Please pay the rest, then send the screenshots of both payments or of the full amount.',
        ],
        'wrong_screenshot' => [
            'label' => 'Screenshot is not a payment',
            'text' => 'The picture you sent is not a screenshot of a payment.',
            'advice' => 'Please send the screenshot of the payment itself, the one your payment app shows after the money is sent.',
        ],
        'unclear' => [
            'label' => 'Screenshot cannot be read',
            'text' => 'The screenshot is not clear enough to read the amount, the date or the number.',
            'advice' => 'Please send a clear, uncropped screenshot that shows the amount, the date and the receiving number.',
        ],
        'wrong_account' => [
            'label' => 'Paid to another number',
            'text' => 'The payment was sent to a different account or number, not to ours.',
            'advice' => 'Please pay to the number or QR code shown on the Subscription page, then send that screenshot.',
        ],
        'already_used' => [
            'label' => 'Screenshot already used',
            'text' => 'This screenshot was already used for another payment.',
            'advice' => 'Each payment needs its own screenshot. Please send the screenshot of the new payment.',
        ],
        'old_payment' => [
            'label' => 'Old payment',
            'text' => 'The payment in the screenshot is an old one and does not belong to this subscription.',
            'advice' => 'Please send the screenshot of the payment you made for this subscription.',
        ],
        'failed' => [
            'label' => 'Payment failed or was returned',
            'text' => 'The payment in the screenshot failed or was returned to you.',
            'advice' => 'Please pay again and send the screenshot of the completed payment.',
        ],
    ],

    // days a new business may use the system before it needs a paid plan
    'trial_days' => (int) env('TRIAL_DAYS', 7),

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
    'currencies' => ['USD', 'AFN', 'PKR', 'EUR', 'GBP', 'AED', 'SAR', 'INR', 'IRR', 'TRY', 'CNY', 'CAD', 'AUD'],

    // how each of them is shown where currencies are chosen: code => [name, sign]
    'currency_names' => [
        'USD' => ['US Dollar', '$'],
        'AFN' => ['Afghan Afghani', '؋'],
        'PKR' => ['Pakistani Rupee', '₨'],
        'EUR' => ['Euro', '€'],
        'GBP' => ['British Pound', '£'],
        'AED' => ['UAE Dirham', 'د.إ'],
        'SAR' => ['Saudi Riyal', '﷼'],
        'INR' => ['Indian Rupee', '₹'],
        'IRR' => ['Iranian Rial', '﷼'],
        'TRY' => ['Turkish Lira', '₺'],
        'CNY' => ['Chinese Yuan', '¥'],
        'CAD' => ['Canadian Dollar', 'C$'],
        'AUD' => ['Australian Dollar', 'A$'],
    ],

    // names and signs of the currencies above, for the tiles in Settings
    'currency_names' => [
        'USD' => ['US dollar', '$'], 'AFN' => ['Afghan afghani', '؋'], 'PKR' => ['Pakistani rupee', '₨'], 'EUR' => ['Euro', '€'],
        'GBP' => ['British pound', '£'], 'AED' => ['UAE dirham', 'د.إ'], 'SAR' => ['Saudi riyal', '﷼'], 'INR' => ['Indian rupee', '₹'],
        'IRR' => ['Iranian rial', '﷼'], 'TRY' => ['Turkish lira', '₺'], 'CNY' => ['Chinese yuan', '¥'], 'CAD' => ['Canadian dollar', '$'],
        'AUD' => ['Australian dollar', '$'],
    ],

    // the days before a due date offered as ready choices in Settings (0 = on the day itself)
    'remind_choices' => [30, 14, 7, 3, 1, 0],

    // categories every new business starts with
    'default_categories' => [
        'income' => ['Client renewals', 'Software and systems', 'Websites', 'Hosting and domains', 'Digital products', 'Other income'],
        'expense' => ['Servers and hosting', 'Domains', 'Salaries', 'Rent', 'Internet and phone', 'Marketing', 'Office', 'Other expenses'],
    ],
];
