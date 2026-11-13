<?php

// The sidebar of a business. 'owner' => true: only the owner of the business sees it.
// 'active' is the route pattern that opens the entry; 'params' tell apart entries that share a route.
return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['heading' => 'Clients'],
    ['label' => 'Due & Renewals', 'icon' => 'bell', 'route' => 'dues.index', 'badge' => 'dues'],
    ['label' => 'Clients', 'icon' => 'users', 'route' => 'clients.index', 'active' => 'clients.*'],
    ['label' => 'Contracts', 'icon' => 'file-signature', 'route' => 'contracts.index', 'active' => 'contracts.*'],
    ['label' => 'Services & Products', 'icon' => 'box', 'route' => 'services.index', 'active' => 'services.*'],

    ['heading' => 'Money'],
    ['label' => 'Income', 'icon' => 'arrow-trend-up', 'route' => 'money.index', 'params' => ['type' => 'income'], 'active' => 'money.*'],
    ['label' => 'Expenses', 'icon' => 'receipt', 'route' => 'money.index', 'params' => ['type' => 'expense'], 'active' => 'money.*'],
    ['label' => 'People & Loans', 'icon' => 'handshake', 'route' => 'people.index', 'active' => 'people.*'],
    ['label' => 'Categories', 'icon' => 'folder-tree', 'children' => [
        ['label' => 'Income categories', 'route' => 'categories.index', 'params' => ['type' => 'income'], 'active' => 'categories.*'],
        ['label' => 'Expense categories', 'route' => 'categories.index', 'params' => ['type' => 'expense'], 'active' => 'categories.*'],
    ]],
    ['label' => 'Reports', 'icon' => 'chart-column', 'route' => 'reports.index'],

    ['heading' => 'Account'],
    ['label' => 'Team', 'icon' => 'user-group', 'route' => 'team.index', 'active' => 'team.*', 'owner' => true],
    ['label' => 'Settings', 'icon' => 'gear', 'route' => 'settings.edit', 'owner' => true],
];
