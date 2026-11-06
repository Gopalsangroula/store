<?php

return [

    /*
    | The platform's own domain. A client store with slug "tea" is reachable at
    | tea.<domain> without any extra setup; custom domains are added per store.
    */
    'domain' => env('PLATFORM_DOMAIN', 'store.appharu.com'),

    // Name shown on the super admin panel and on platform (subscription) invoices.
    'name' => env('PLATFORM_NAME', 'Appharu MasterStore'),

    // Platform billing currency (subscription invoices are always in this currency).
    'currency' => 'NPR',

    // How long an unpaid online order holds its stock before it is released.
    'reservation_minutes' => (int) env('STOCK_RESERVATION_MINUTES', 30),

    // Days a store may run past its subscription end before it is suspended.
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    'khalti' => [
        // Khalti ePayment (KPG-2). Each store saves its own secret key in its admin;
        // these only choose sandbox or live endpoints.
        'sandbox_url' => 'https://dev.khalti.com/api/v2/',
        'live_url' => 'https://khalti.com/api/v2/',
    ],

];
