<?php
/**
 * ⚙️ Settings Configuration - EMS Admin
 * Default system settings and validation rules
 */

return [
    'general' => [
        'site_name' => [
            'default' => 'EMS - Event Management System',
            'type' => 'string',
            'required' => true,
            'max_length' => 100
        ],
        'contact_email' => [
            'default' => 'admin@ems.com',
            'type' => 'email',
            'required' => true
        ],
        'timezone' => [
            'default' => 'Africa/Blantyre',
            'type' => 'string',
            'required' => true,
            'options' => [
                'Africa/Blantyre',
                'UTC',
                'America/New_York',
                'Europe/London',
                'Asia/Tokyo'
            ]
        ],
        'maintenance_mode' => [
            'default' => false,
            'type' => 'boolean'
        ],
        'registration_enabled' => [
            'default' => true,
            'type' => 'boolean'
        ],
        'site_description' => [
            'default' => 'Professional Event Management System for University of Livingstonia',
            'type' => 'text',
            'max_length' => 500
        ]
    ],
    
    'email' => [
        'smtp_host' => [
            'default' => 'smtp.gmail.com',
            'type' => 'string',
            'required' => true
        ],
        'smtp_port' => [
            'default' => 587,
            'type' => 'integer',
            'required' => true,
            'min' => 1,
            'max' => 65535
        ],
        'smtp_username' => [
            'default' => '',
            'type' => 'string',
            'required' => true
        ],
        'smtp_password' => [
            'default' => '',
            'type' => 'password',
            'required' => false
        ],
        'smtp_encryption' => [
            'default' => 'tls',
            'type' => 'string',
            'required' => true,
            'options' => ['tls', 'ssl', 'none']
        ],
        'from_name' => [
            'default' => 'EMS System',
            'type' => 'string',
            'required' => true,
            'max_length' => 100
        ]
    ],
    
    'payment' => [
        'payment_gateway' => [
            'default' => 'stripe',
            'type' => 'string',
            'required' => true,
            'options' => ['stripe', 'paypal', 'flutterwave', 'paystack']
        ],
        'currency' => [
            'default' => 'MWK',
            'type' => 'string',
            'required' => true,
            'options' => ['MWK', 'USD', 'EUR', 'GBP']
        ],
        'payment_api_key' => [
            'default' => '',
            'type' => 'string',
            'required' => true
        ],
        'payment_secret_key' => [
            'default' => '',
            'type' => 'password',
            'required' => true
        ],
        'payment_enabled' => [
            'default' => false,
            'type' => 'boolean'
        ]
    ],
    
    'security' => [
        'session_timeout' => [
            'default' => 60,
            'type' => 'integer',
            'required' => true,
            'min' => 5,
            'max' => 1440
        ],
        'max_login_attempts' => [
            'default' => 5,
            'type' => 'integer',
            'required' => true,
            'min' => 3,
            'max' => 10
        ],
        'password_min_length' => [
            'default' => 8,
            'type' => 'integer',
            'required' => true,
            'min' => 6,
            'max' => 20
        ],
        'two_factor_enabled' => [
            'default' => false,
            'type' => 'boolean'
        ],
        'ip_whitelist_enabled' => [
            'default' => false,
            'type' => 'boolean'
        ],
        'allowed_ips' => [
            'default' => '',
            'type' => 'text'
        ]
    ]
];
?>