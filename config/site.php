<?php

// Everything the public website says. The pages, the structured data for search engines
// (JSON-LD) and /llms.txt for AI assistants are all written from this one file, so they
// never disagree. Keep the sentences plain and true to what the system does.
return [
    'tagline' => 'Know who owes you, and when it is due.',

    // one or two sentences that say what it is, for search results and AI answers
    'summary' => 'DueBook is an online system for freelancers, agencies and small companies that sell services paid again and again: hosting, domains, software, support, subscriptions. It keeps every client, contract and renewal date in one place, reminds you by email before each due date, and tracks loans, income and expenses in several currencies.',

    'description' => 'Track clients, contracts and yearly renewals, get reminders before every due date, and record loans, income and expenses in any currency. Free trial.',

    'steps' => [
        ['Add your clients and what they pay for', 'For each client write what it is for, the amount, how often it is paid and the next due date. You can attach the signed contract.'],
        ['Get reminded before every due date', 'One email a day lists what is coming and what is late. You choose the days: 30, 7 and 1 day before, for example. A client can get a reminder too.'],
        ['Record the payment in one tap', 'Receiving a payment writes the income and moves the contract to its next due date by itself. A part payment leaves the date where it is.'],
    ],

    'features' => [
        ['bell', 'Due and renewals', 'One list of who should pay, how much and when: late, the next 7, 30 and 90 days, with totals for each currency.'],
        ['envelope', 'Email reminders', 'A daily email to you before due dates and for late payments, and an optional reminder to the client on the days you choose.'],
        ['file-signature', 'Clients and contracts', 'Every client with their contracts: amount, currency, monthly to three-yearly cycles, a reference such as a domain, and a scan of the signed contract.'],
        ['hand-holding-dollar', 'Payments that keep the books', 'Receiving a payment records the income and sets the next due date. Deleting it puts the contract back.'],
        ['handshake', 'Loans and people', 'Money you lent and money you borrowed, person by person, with a balance that says who owes whom and a date it should be settled by.'],
        ['arrow-trend-up', 'Income and expenses', 'All other money in and out, with your own categories, filters and totals.'],
        ['coins', 'Several currencies', 'Use USD, AFN, PKR, EUR or any other currency side by side. Nothing is converted: totals are shown per currency, with a filter on every page.'],
        ['chart-column', 'Reports', 'The money to expect in each of the next 12 months, and income against expenses by month and by category.'],
        ['user-group', 'Your team', 'Invite the people you work with. Members record the daily work; the owner keeps the settings.'],
        ['wallet', 'Works like an app on your phone', 'Open it in the browser of any phone, tablet or computer. Added to the home screen, it opens full screen like an installed app.'],
    ],

    'audiences' => [
        ['hosting', 'Hosting and domain resellers', 'Dozens or hundreds of domains and hosting accounts renew on different days. DueBook lists them by due date and reminds you, so no renewal is forgotten and no invoice is sent late.'],
        ['software', 'Software companies and developers', 'Yearly licences and support contracts for the systems you built: see which client is due this month and how much to expect in the next twelve.'],
        ['agencies', 'Agencies and freelancers', 'Retainers, maintenance plans and website care packages paid monthly or yearly, with reminders to you and, if you want, to the client.'],
        ['services', 'Any business with repeat customers', 'Rent, memberships, subscriptions, service contracts: if a customer pays you again on a date, DueBook can keep that date for you.'],
    ],

    // Questions people really ask, each with a direct answer first. The placeholders in
    // braces are filled in from the live settings: {trial}, {price}, {payments}.
    'faqs' => [
        ['What is DueBook?', 'DueBook is an online system that keeps track of who should pay you and when. You record your clients and what each one pays for, and it reminds you by email before every due date. It also tracks money you lent or borrowed, and your other income and expenses.'],
        ['Who is DueBook for?', 'It is for freelancers, agencies and small companies whose customers pay again and again: hosting and domain resellers, software companies with yearly support contracts, agencies with retainers, and any business with renewals.'],
        ['How do the reminders work?', 'Once a day DueBook emails you a list of the payments that are coming up and the ones that are late. You choose how many days before a due date you want to be told, for example 30, 7 and 1. For any contract you can also switch on a reminder email to the client.'],
        ['How much does DueBook cost?', '{price} You can try everything free for {trial} days, and no card is needed to start.'],
        ['How do I pay for DueBook?', '{payments} You pay, upload a screenshot of the payment on your Subscription page, and your subscription is switched on after we check it, usually the same day.'],
        ['Can I get a refund or change my plan?', 'Payments are not refunded. You can move to a higher plan at any time: it is paid in full and starts that day. Any other change of plan is made when your paid period ends.'],
        ['Does DueBook work on a phone?', 'Yes. DueBook runs in the browser of any phone, tablet or computer and is designed for phones first. If you add it to your home screen it opens full screen like an installed app. There is nothing to download from an app store.'],
        ['Which currencies can I use?', 'Any currency. You choose the ones you work with, such as USD, AFN, PKR or EUR, and use them side by side. DueBook does not convert between them: every total is shown for each currency.'],
        ['Who can see my records?', 'Only you and the people you invite to your account. Every business has its own separate records.'],
        ['What happens if I stop paying?', 'Your account is locked, not deleted. Your records stay as they are, and when you pay again you continue where you stopped.'],
        ['Which languages does DueBook speak?', 'The system is in English today.'],
    ],
];
