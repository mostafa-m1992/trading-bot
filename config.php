<?php

return [
    // اطلاعات نوبیتکس
    'nobitex' => [
        // API key in account settings 
        'api_key' => '',
    ],

    // اطلاعات تلگرام
    'telegram' => [
        'bot_token' => '',
        'chat_id' => '',
    ],

    // تنظیمات سرور
    'server' => [
        'host' => '',
        'username' => '',
        'password' => '',
    ],

    // تنظیمات ترید
    'trade' => [
        'trailing_stop' => true, // فعال‌سازی حد سود و ضرر متحرک
        'retry_attempts' => 3, // تعداد تلاش برای ترید در صورت شکست درخواست
    ]
];
