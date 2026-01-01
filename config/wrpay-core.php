<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook handling in the Wrpay Core package.
    |
    */

    'webhook' => [
        /*
        |--------------------------------------------------------------------------
        | Default Webhook URL
        |--------------------------------------------------------------------------
        |
        | The default webhook URL to use when webhooks are enabled but no
        | specific URL is configured for a merchant.
        |
        */
        'default_url' => env('WRPAY_WEBHOOK_URL', ''),

        /*
        |--------------------------------------------------------------------------
        | Default Webhook Secret
        |--------------------------------------------------------------------------
        |
        | The default webhook secret for signing webhook payloads.
        |
        */
        'default_secret' => env('WRPAY_WEBHOOK_SECRET', ''),

        /*
        |--------------------------------------------------------------------------
        | Verify SSL
        |--------------------------------------------------------------------------
        |
        | Whether to verify SSL certificates when sending webhooks.
        |
        */
        'verify_ssl' => env('WRPAY_WEBHOOK_VERIFY_SSL', true),

        /*
        |--------------------------------------------------------------------------
        | Timeout
        |--------------------------------------------------------------------------
        |
        | Webhook request timeout in seconds.
        |
        */
        'timeout' => env('WRPAY_WEBHOOK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for transaction handling.
    |
    */

    'transaction' => [
        /*
        |--------------------------------------------------------------------------
        | Default Currency
        |--------------------------------------------------------------------------
        |
        | The default currency code for transactions.
        |
        */
        'default_currency' => env('WRPAY_DEFAULT_CURRENCY', 'USD'),

        /*
        |--------------------------------------------------------------------------
        | Hold Period Days
        |--------------------------------------------------------------------------
        |
        | Number of days to hold payment transactions before release (H+1).
        |
        */
        'hold_period_days' => env('WRPAY_HOLD_PERIOD_DAYS', 1),

        /*
        |--------------------------------------------------------------------------
        | Release Time
        |--------------------------------------------------------------------------
        |
        | The time of day when transactions become ready for release.
        | This is used in the scopeReadyForRelease query scope.
        |
        */
        'release_time_hour' => env('WRPAY_RELEASE_TIME_HOUR', 23),
        'release_time_minute' => env('WRPAY_RELEASE_TIME_MINUTE', 0),

        /*
        |--------------------------------------------------------------------------
        | Fee Rates
        |--------------------------------------------------------------------------
        |
        | Fee rate configurations for transaction calculations.
        | All rates are percentages.
        |
        */
        'fee_rates' => [
            'mdr' => env('WRPAY_MDR_FEE_RATE', 0.7),
            'admin' => env('WRPAY_ADMIN_FEE_RATE', 0.1),
            'cashback' => env('WRPAY_CASHBACK_FEE_RATE', 0.125),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for wallet operations.
    |
    */

    'wallet' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Sandbox Mode
        |--------------------------------------------------------------------------
        |
        | Whether to enable sandbox mode for wallet operations.
        |
        */
        'sandbox_enabled' => env('WRPAY_WALLET_SANDBOX_ENABLED', false),
    ],

    'merchant' => [
        'features' => [
            // Payment Settings
            [
                'feature' => 'auto_process_payments',
                'description' => 'Automatically process payments without manual approval.',
                'status' => true,
                'type' => 'boolean',
                'category' => 'payment',
            ],
            [
                'feature' => 'require_customer_info',
                'description' => 'Require customer information for all transactions.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'payment',
            ],
            [
                'feature' => 'payment_timeout_minutes',
                'description' => 'Payment session timeout in minutes (1-1440).',
                'status' => 30,
                'type' => 'integer',
                'category' => 'payment',
                'validation' => 'min:1|max:1440',
            ],

            // Notification Settings
            [
                'feature' => 'email_notifications',
                'description' => 'Receive transaction notifications via email.',
                'status' => true,
                'type' => 'boolean',
                'category' => 'notification',
            ],
            [
                'feature' => 'sms_notifications',
                'description' => 'Receive transaction notifications via SMS.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'notification',
            ],
            [
                'feature' => 'whatsapp_notifications',
                'description' => 'Receive transaction notifications via WhatsApp.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'notification',
            ],
            [
                'feature' => 'telegram_notifications',
                'description' => 'Receive transaction notifications via Telegram.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'notification',
            ],

            // Webhook Settings
            [
                'feature' => 'webhooks_enabled',
                'description' => 'Enable webhook notifications to external systems.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'webhook',
            ],
            [
                'feature' => 'webhook_verify_ssl',
                'description' => 'Verify SSL certificates for webhook calls.',
                'status' => true,
                'type' => 'boolean',
                'category' => 'webhook',
            ],

            // Security Settings
            [
                'feature' => 'api_ip_whitelist_enabled',
                'description' => 'Enable IP whitelist for API access.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'security',
            ],
            [
                'feature' => 'two_factor_required',
                'description' => 'Require two-factor authentication for sensitive operations.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'security',
            ],
            [
                'feature' => 'session_timeout_minutes',
                'description' => 'User session timeout in minutes (1-1440).',
                'status' => 60,
                'type' => 'integer',
                'category' => 'security',
                'validation' => 'min:1|max:1440',
            ],

            // Display Settings
            [
                'feature' => 'show_merchant_logo',
                'description' => 'Display merchant logo on payment pages.',
                'status' => true,
                'type' => 'boolean',
                'category' => 'display',
            ],
            [
                'feature' => 'theme_color',
                'description' => 'Primary theme color for payment pages (hex format).',
                'status' => '#007bff',
                'type' => 'string',
                'category' => 'display',
                'validation' => 'regex:/^#[0-9A-Fa-f]{6}$/',
            ],

            // Transaction Settings
            [
                'feature' => 'transaction_pagination_limit',
                'description' => 'Number of transactions per page (5-100).',
                'status' => 20,
                'type' => 'integer',
                'category' => 'transaction',
                'validation' => 'min:5|max:100',
            ],
            [
                'feature' => 'auto_refund_failed',
                'description' => 'Automatically refund failed transactions.',
                'status' => false,
                'type' => 'boolean',
                'category' => 'transaction',
            ],
            [
                'feature' => 'refund_timeout_hours',
                'description' => 'Timeout for refunds in hours (1-168).',
                'status' => 24,
                'type' => 'integer',
                'category' => 'transaction',
                'validation' => 'min:1|max:168',
            ],

            // Currency Settings
            // [
            //     'feature' => 'multi_currency_enabled',
            //     'description' => 'Enable support for multiple currencies.',
            //     'status' => false,
            //     'type' => 'boolean',
            //     'category' => 'currency',
            // ],
        ],

        // Additional field configurations for non-boolean features
        'additional_fields' => [
            // 'payment_methods_allowed' => [
            //     'type' => 'array',
            //     'category' => 'payment',
            //     'description' => 'Allowed payment methods',
            //     'options' => [
            //         'credit_card' => 'Credit Card',
            //         'debit_card' => 'Debit Card',
            //         'bank_transfer' => 'Bank Transfer',
            //         'ewallet' => 'E-Wallet',
            //         'crypto' => 'Cryptocurrency',
            //         'qr_code' => 'QR Code',
            //         'cash_on_delivery' => 'Cash on Delivery',
            //     ],
            // ],
            // 'supported_currencies' => [
            //     'type' => 'array',
            //     'category' => 'currency',
            //     'description' => 'Supported currencies',
            //     'source' => 'currencies', // Will be populated from currencies table
            // ],
            'notification_email' => [
                'type' => 'email',
                'category' => 'notification',
                'description' => 'Email address for notifications',
                'validation' => 'nullable|email|max:255',
            ],
            'notification_phone' => [
                'type' => 'string',
                'category' => 'notification',
                'description' => 'Phone number for notifications',
                'validation' => 'nullable|string|max:20',
            ],
            'webhook_url' => [
                'type' => 'url',
                'category' => 'webhook',
                'description' => 'Webhook endpoint URL',
                'validation' => 'nullable|required_if:webhooks_enabled,1|url|max:500',
            ],
            'webhook_secret' => [
                'type' => 'string',
                'category' => 'webhook',
                'description' => 'Webhook secret for verification',
                'validation' => 'nullable|required_if:webhooks_enabled,1|string|min:16|max:255',
            ],
            'api_ip_whitelist' => [
                'type' => 'array',
                'category' => 'security',
                'description' => 'Allowed IP addresses for API access',
                'validation' => 'nullable|array',
                'item_validation' => 'ip',
            ],
            // 'custom_css' => [
            //     'type' => 'textarea',
            //     'category' => 'display',
            //     'description' => 'Custom CSS for payment pages',
            //     'validation' => 'nullable|string|max:5000',
            // ],
            // 'primary_button_color' => [
            //     'type' => 'string',
            //     'category' => 'display',
            //     'description' => 'Primary button color (hex format)',
            //     'validation' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            // ],
        ],

        // Feature categories for grouping
        'categories' => [
            'payment' => [
                'title' => 'Payment Settings',
                'description' => 'Configure payment processing behavior',
            ],
            'notification' => [
                'title' => 'Notification Settings',
                'description' => 'Configure notification preferences',
            ],
            'webhook' => [
                'title' => 'Webhook Settings',
                'description' => 'Configure external webhook notifications',
            ],
            'security' => [
                'title' => 'Security Settings',
                'description' => 'Configure security and access controls',
            ],
            'display' => [
                'title' => 'Display Settings',
                'description' => 'Customize appearance and branding',
            ],
            'transaction' => [
                'title' => 'Transaction Settings',
                'description' => 'Configure transaction processing',
            ],
            'currency' => [
                'title' => 'Currency Settings',
                'description' => 'Configure currency support',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the model classes used throughout the package.
    | This allows you to extend or replace models with your own implementations.
    |
    */

    'models' => [
        'customer' => env('WRPAY_MODEL_CUSTOMER', \Wrpay\Core\Models\Customer::class),
        'deposit_method' => env('WRPAY_MODEL_DEPOSIT_METHOD', \Wrpay\Core\Models\DepositMethod::class),
        'merchant' => env('WRPAY_MODEL_MERCHANT', \Wrpay\Core\Models\Merchant::class),
        'transaction' => env('WRPAY_MODEL_TRANSACTION', \Wrpay\Core\Models\Transaction::class),
        'wallet' => env('WRPAY_MODEL_WALLET', \Wrpay\Core\Models\Wallet::class),
        'withdraw_method' => env('WRPAY_MODEL_WITHDRAW_METHOD', \Wrpay\Core\Models\WithdrawMethod::class),
        'withdraw_account' => env('WRPAY_MODEL_WITHDRAW_ACCOUNT', \Wrpay\Core\Models\WithdrawAccount::class),
        'transaction_withdraw_account' => env('WRPAY_MODEL_TRANSACTION_WITHDRAW_ACCOUNT', \Wrpay\Core\Models\TransactionWithdrawAccount::class),
    ],

];
