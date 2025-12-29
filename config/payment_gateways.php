<?php

return [
    'easylink' => [
        'credentials' => [
            'url' => env('EASYLINK_URL', 'https://merchant.easylink.id/integrations'),
            'app_id' => env('EASYLINK_APP_ID'),
            'app_key' => env('EASYLINK_APP_KEY'),
            'app_secret' => env('EASYLINK_APP_SECRET'),
            'callback_topup' => env('EASYLINK_CALLBACK_TOPUP', 'https://api.ilonapay.com/ipn/easylink/topup'),
            'callback_transaction' => env('EASYLINK_CALLBACK_TRANSACTION', 'https://api.ilonapay.com/ipn/easylink/disbursment'),
        ],
    ],
    'netzme' => [
        'credentials' => [
            'url' => env('NETZME_URL', 'https://tokoapisnap.netzme.com/api'),
            'client_id' => env('NETZME_CLIENT_ID'),
            'private_key' => env('NETZME_PRIVATE_KEY'),
            'client_secret' => env('NETZME_CLIENT_SECRET'),
            'callback_token' => env('NETZME_CALLBACK_TOKEN'),
        ],
    ],
];
