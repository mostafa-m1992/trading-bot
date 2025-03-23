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
        'min_balance' => 10, // حداقل سرمایه درگیر (درصد)
        'take_profit' => 5, // درصد حد سود
        'stop_loss' => 2, // درصد حد ضرر
        'trailing_stop' => true, // فعال‌سازی حد سود و ضرر متحرک
        'retry_attempts' => 3, // تعداد تلاش برای ترید در صورت شکست درخواست
        'min_trade_volume' => 100, // حداقل حجم معاملات برای انتخاب ارز
    ]
];
