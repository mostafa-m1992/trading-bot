<?php

return [
    // اطلاعات نوبیتکس
    'nobitex' => [
        // API key in account settings 
        'api_key' => '9ca2d7df0b3a5411ffdb59b3a20f2c41c409729a',
    ],

    // اطلاعات تلگرام
    'telegram' => [
        'bot_token' => '8173486530:AAFL2GEtNI23hP5nlV14X0HpAYf7Qi93Hng',
        'chat_id' => '140169313',
    ],

    // تنظیمات سرور
    'server' => [
        'host' => '37.230.48.100',
        'username' => 'root',
        'password' => 'sL2tF#^T@vps',
    ],

    // تنظیمات ترید
    'trade' => [
        'trailing_stop' => true, // فعال‌سازی حد سود و ضرر متحرک
        'retry_attempts' => 3, // تعداد تلاش برای ترید در صورت شکست درخواست
    ]
];
