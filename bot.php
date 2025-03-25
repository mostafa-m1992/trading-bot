<?php

// هسته اصلی ربات: اجرای توابع

require '/root/tradeBot/trading-bot/config.php';
require_once '/root/tradeBot/trading-bot/ExchangeAPI.php';
require_once '/root/tradeBot/trading-bot/Order.php';
require_once '/root/tradeBot/trading-bot/Indicators.php';
require_once '/root/tradeBot/trading-bot/TelegramNotifier.php';

// اطلاعات دسترسی
$config = include('config.php');
$api_key = $config['nobitex']['api_key'];
$bot_token = $config['telegram']['bot_token'];
$chat_id = $config['telegram']['chat_id'];
$min_balance = $config['trade']['min_balance'];
$take_profit = $config['trade']['take_profit'];
$stop_loss = $config['trade']['stop_loss'];
$trailing_stop = $config['trade']['trailing_stop'];
$retry_attempts = $config['trade']['retry_attempts'];

// ساخت نمونه از کلاس ExchangeAPI
$exchangeAPI = new ExchangeAPI($api_key);
// ساخت نمونه از کلاس Order
$order = new Order($exchangeAPI);
// ایجاد شی از کلاس TelegramNotifier
$telegram = new TelegramNotifier($bot_token, $chat_id);

if ($exchangeAPI) {
    echo " اعتبار سنجی موفق بود و توکن متصل شد\n";
} else {
    echo "⚠️ توکن وصل نمی شود\n";
}

// دریافت موجودی حساب
$balanceData = $exchangeAPI->getBalance();
if (!$balanceData || isset($balanceData['error'])) {
    $telegram->sendMessage("❌ خطا در دریافت موجودی حساب");
    exit;
} else {
    echo "موجودی حساب معادل: " . $balanceData . " دریافت شد\n";
    $telegram->sendMessage("✅ موجودی حساب معادل: " . $balanceData . " دریافت شد");
}

// فرض می‌کنیم ابتدا با USDT خرید خواهیم کرد
$currentCurrency = 'USDT';

while (true) {
    try {
        $marketData = $exchangeAPI->getMarketPrice();
        if (!$marketData) {
            echo "⚠️ خطا در دریافت اطلاعات بازار\n";
            $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات بازار");
            sleep(10);
            continue;
        } else {
            echo "اطلاعات کل جفت ارزهای بازار دریافت شد\n";
            $telegram->sendMessage("✅ اطلاعات کل جفت ارزهای بازار دریافت شد");
        }




        // فیلتر کردن جفت‌ارزهایی که با ارز فعلی تطابق دارند
        // $filteredPairs = array_filter(array_keys($marketData), function($pair) use ($currentCurrency) {
        //     return strpos($pair, $currentCurrency) !== false;
        // });

        // if (empty($filteredPairs)) {
        //     return ['error' => 'هیچ جفت ارزی با ارز فعلی پیدا نشد.'];
        // }


        
        // فیلتر کردن جفت ارزهایی که یکی از ارزها "USDT" باشد
        // $filteredPairs = [];
        // foreach ($marketData as $symbol => $data) {
        //     $parts = explode('_', $symbol); 
        //     $symbol_clean = end($parts); // استخراج بخش ارز دوم جفت ارز
            
        //     // بررسی اینکه یکی از ارزهای جفت ارز "USDT" باشد
        //     if ($symbol_clean == 'USDT' || strpos($symbol, 'USDT') !== false) {
        //         $filteredPairs[] = $symbol;
        //     }
        // }

        // if (empty($filteredPairs)) {
        //     echo "❌ هیچ جفت ارزی با USDT پیدا نشد!\n";
        //     $telegram->sendMessage("❌ هیچ جفت ارزی با USDT پیدا نشد!");
        //     exit;
        // }

        $bestPairData = $exchangeAPI->findBestPair();
        if (isset($bestPairData['error'])) {
            echo "بهترین جفت ارز پیدا نشد\n";
            $telegram->sendMessage("❌ " . $bestPairData['error']);
            sleep(10);
            continue;
        } else {
            echo "✅بهترین جفت ارز پیدا شد\n";
        }

        $symbol = $bestPairData['bestPair'] ?? null;

        if (!$symbol || !isset($marketData[$symbol])) {
            echo "خطا: مقدار bestPair نامعتبر است یا در marketData وجود ندارد!\n";
            $telegram->sendMessage("⚠️ مقدار bestPair نامعتبر است یا در marketData وجود ندارد!");
            sleep(10);
            continue;
        }

        $price = $marketData[$symbol]['bids'][0][0] ?? null; 
        
        if (!$price) {
            echo "خطا در دریافت قیمت\n";
            $telegram->sendMessage("⚠️ خطا در دریافت قیمت $symbol!");
            sleep(10);
            continue;
        } else {
            echo " نماد: " . $symbol . " با قیمت: " . $price . " مشخص شد\n";
            $telegram->sendMessage("✅ نماد: " . $symbol . " با قیمت: " . $price . " مشخص شد");
        }

        // echo "✅ جفت ارز مناسب: " . $symbol . "\n";
        // $telegram->sendMessage("✅ جفت ارز: $symbol | قیمت: $price");




        // اگر ارز فعلی تتر باشد، خرید تتر به ارز مقصد
        // if ($currentCurrency == 'USDT') {
        //     // خرید ارز از USDT
        //     echo "خرید با استفاده از تتر: $symbol\n";
        // } else {
        //     // در صورتی که ارز مقصد متفاوت باشد، خرید با آن ارز
        //     echo "خرید با استفاده از ارز فعلی: $symbol\n";
        // }



        // استخراج ارز دوم از جفت ارز (مثلاً BTCIRT → IRR)

        // $parts = explode('_', $symbol); 
        // $symbol_clean = end($parts); // گرفتن بخش اصلی جفت ارز

        // $commonCurrencies = ['IRT', 'BTC', 'ETH', 'ADA', 'DOGE', 'USDT']; // لیست ارزهای دوم
        // $currency = null;

        // foreach ($commonCurrencies as $currencyCode) {
        //     if (str_ends_with($symbol_clean, $currencyCode)) {
        //         $currency = $currencyCode;
        //         break;
        //     }
        // }

        // استخراج ارز اول از جفت ارز (مثلاً BTCIRT → BTC)

        $parts = explode('_', $symbol); 
        $symbol_clean = reset($parts); // گرفتن بخش اول جفت ارز

        $commonCurrencies = ['IRT', 'BTC', 'ETH', 'ADA', 'DOGE', 'USDT']; // لیست ارزهای معتبر
        $currency = null;

        foreach ($commonCurrencies as $currencyCode) {
            if (str_starts_with($symbol_clean, $currencyCode)) {
                $currency = $currencyCode;
                break;
            }
        }

        if (!$currency) {
            die("خطا: ارز دوم قابل تشخیص نیست برای نماد $symbol_clean\n");
        }

        if (!$currency) {
            echo "❌ خطا در استخراج ارز از جفت ارز!\n";
            $telegram->sendMessage("❌ خطا در استخراج ارز از جفت ارز: $symbol");
            continue;
        }





        // $currency = end($parts); // دریافت ارز دوم

        // if ($currency != $currentCurrency) {
        //     $currentCurrency = $currency; // برای خرید بعدی از ارز جدید استفاده می‌کنیم
        // }




        // دریافت موجودی این ارز
        $availableBalance = $balanceData[$currentCurrency] ?? 0;
        // $availableBalance = $balanceData[$currency] ?? 0;

        // اگر موجودی برابر با صفر است، پیام خطا ارسال کنید
        if ($availableBalance <= 0) {
            echo "❌ موجودی کافی برای معامله ندارید!\n";
            $telegram->sendMessage("❌ موجودی کافی برای معامله ندارید!");
            continue;  // از حلقه خارج می‌شود
        }

        // استفاده از تمام موجودی برای خرید
        $balanceAmount = $availableBalance;

        echo "مقدار خرید: " . $balanceAmount . "\n";
        $telegram->sendMessage("✅ مقدار خرید با تمام موجودی: " . $balanceAmount);



        // محاسبه مقدار معامله بر اساس درصدی از موجودی حساب
        // $balanceAmount = $availableBalance * ($min_balance / 100); 
        // print_r($balanceAmount);

        // if ($balanceAmount < $availableBalance ) {
        //     echo "❌ موجودی کافی برای معامله ندارید! مقدار موردنیاز: \n" . $min_balance;
        //     $telegram->sendMessage("❌ موجودی کافی برای معامله ندارید! مقدار موردنیاز: " . $min_balance);
        //     exit;
        // } else {
        //     echo "موجودی کافی برای معامله وجود دارد: " . $balanceAmount . "\n";
        //     $telegram->sendMessage("✅ موجودی کافی برای معامله وجود دارد: " . $balanceAmount);
        // }
        
        // echo "موجودی ارز دوم: $availableBalance | مقدار خرید: $balanceAmount\n";
        // $telegram->sendMessage("موجودی ارز دوم: $availableBalance | مقدار خرید: $balanceAmount");
        

        // تلاش برای خرید
        $tradeResult = $order->placeOrder($symbol, 'buy', $balanceAmount, $price, 'limit');
        if (!isset($tradeResult['status']) || $tradeResult['status'] !== 'ok') {
            echo "❌ خرید ناموفق برای $symbol. جزئیات: " . print_r($tradeResult, true) . "\n";
            $telegram->sendMessage("❌ خرید ناموفق برای $symbol. جزئیات: " . print_r($tradeResult, true));
            continue;
        }
        echo "خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $price";
        $telegram->sendMessage("🎉 خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $price");

        sleep(15);

        $tradedAmount = $tradeResult['order']['amount'] ?? null; // مقدار خریداری‌شده را دریافت کن
        $orderId = $tradeResult['order']['id'] ?? null; // شماره سفارش

        if (!$tradedAmount || !$orderId) {
            echo "خطا: مقدار دارایی خریداری‌ شده یا شماره سفارش نامعتبر است";
            $telegram->sendMessage("⚠️ خطا: مقدار دارایی خریداری‌ شده یا شماره سفارش نامعتبر است");
            continue;
        }

        echo "خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $price | مقدار: $tradedAmount";
        $telegram->sendMessage("🎉 خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $price | مقدار: $tradedAmount");

        // مدیریت حد ضرر متحرک و بررسی سفارش فروش
        try {
            $trailingStopResult = $order->manageTrailingStop($symbol, $orderId, $price, $tradedAmount, $trailing_stop);
            if (isset($trailingStopResult['error'])) {
                $telegram->sendMessage('⚠️ خطا در مدیریت حد ضرر: ' . $trailingStopResult['error']);
            } else {
                $telegram->sendMessage('✅ حد ضرر متحرک بروزرسانی شد. جزئیات: ' . print_r($trailingStopResult, true));
            }
            
            $order->checkAndSell($symbol, $price, $tradedAmount);
            // بعد از فروش، ارز جدید را به جای ارز اولیه قرار می‌دهیم
            $currentCurrency = $currency; // ارز جدیدی که به دست آمده است

        } catch (Exception $e) {
            $telegram->sendMessage("🚨 خطای غیرمنتظره در مدیریت حد ضرر و ثبت فروش: " . $e->getMessage());
            sleep(10);
        }
        
    } catch (Exception $e) {
        $telegram->sendMessage("🚨 خطای غیرمنتظره: " . $e->getMessage());
        sleep(10);
    }
}