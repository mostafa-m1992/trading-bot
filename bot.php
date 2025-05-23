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
$trailing_stop = $config['trade']['trailing_stop'];

// ساخت نمونه از کلاس ExchangeAPI
$exchangeAPI = new ExchangeAPI($api_key);
// ساخت نمونه از کلاس Order
$order = new Order($exchangeAPI);
// ایجاد شی از کلاس TelegramNotifier
$telegram = new TelegramNotifier($bot_token, $chat_id);


while (true) {
    try {
        // اتصال به صرافی
        if ($exchangeAPI) {
            echo " اعتبار سنجی موفق بود و توکن متصل شد\n";
            $telegram->sendMessage("✅ اتصال به صرافی انجام شد");
        } else {
            echo "⚠️ توکن وصل نمی شود\n";
            $telegram->sendMessage("❌ عدم اتصال به صرافی");
        }
        
        // دریافت موجودی حساب
        $balanceData = $exchangeAPI->getBalance();
        if (!$balanceData || isset($balanceData['error'])) {
            $telegram->sendMessage("❌ خطا در دریافت موجودی حساب");
        } else {
            echo "موجودی تتری حساب معادل: " . $balanceData . " دریافت شد\n";
            $telegram->sendMessage("✅ موجودی تتری حساب معادل: " . $balanceData . " دریافت شد");
        }

        // گرفتن اطلاعات بازار
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

        // بهترین جفت ارز بر مبنای تتر پیدا می شود
        $bestPairData = $exchangeAPI->findBestPair();
        if (isset($bestPairData['error'])) {
            echo "بهترین جفت ارز پیدا نشد\n";
            $telegram->sendMessage("❌ " . $bestPairData['error']);
            sleep(10);
            continue;
        } else {
            echo "✅بهترین جفت ارز پیدا شد\n";
        }

        // نماد جفت ارز مشخص می شود
        $symbol = $bestPairData['bestPair'] ?? null;

        if (!$symbol || !isset($marketData[$symbol])) {
            echo "خطا: مقدار bestPair نامعتبر است یا در marketData وجود ندارد!\n";
            $telegram->sendMessage("⚠️ مقدار bestPair نامعتبر است یا در marketData وجود ندارد!");
            sleep(10);
            continue;
        }

        // قیمت نماد مشخص شده پیدا می شود
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


        // به دست آوردن قیمت خریداری شده جفت ارز
        $initialPrice = $order->getLastBuyPrice($symbol);
        if (isset($initialPrice['error'])) {
            return $initialPrice; // اگر خطایی بود، برگردانیم
        }

        // تلاش برای خرید
        $tradeResult = $order->placeOrder($symbol, 'buy', $balanceData, $price);
        if (!isset($tradeResult['status']) || $tradeResult['status'] !== 'ok') {
            echo "❌ خرید ناموفق برای $symbol. جزئیات: " . print_r($tradeResult, true) . "\n";
            $telegram->sendMessage("❌ خرید ناموفق برای $symbol " . "جزئیات: " . print_r($tradeResult, true));
            sleep(10);
            continue;
        } else {
            echo "خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $initialPrice";
            $telegram->sendMessage("🎉 خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $initialPrice");
        }


        $tradedAmount = $tradeResult['order']['amount'] ?? null; // مقدار خریداری‌شده را دریافت کن
        $orderId = $tradeResult['order']['id'] ?? null; // شماره سفارش

        if (!$tradedAmount || !$orderId) {
            echo "خطا: مقدار دارایی خریداری‌ شده یا شماره سفارش نامعتبر است\n";
            $telegram->sendMessage("⚠️ خطا: مقدار دارایی خریداری‌ شده یا شماره سفارش نامعتبر است");
            sleep(10);
            continue;
        } else {
            echo "خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $initialPrice | مقدار: $tradedAmount";
            $telegram->sendMessage("🎉 خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $initialPrice | مقدار: $tradedAmount");
        }

        
        // مدیریت حد ضرر متحرک و بررسی سفارش فروش
        try {
            $trailingStopResult = $order->manageTrailingStop($symbol, $orderId, $initialPrice, $tradedAmount, $trailing_stop);
            if (isset($trailingStopResult['error'])) {
                $telegram->sendMessage('⚠️ خطا در مدیریت حد ضرر: ' . print_r($trailingStopResult, true));
                sleep(10);
                continue;
            } else {
                $telegram->sendMessage('✅ حد ضرر متحرک بروزرسانی شد. جزئیات: ' . print_r($trailingStopResult, true));
            }
            
            $order->checkAndSell($symbol, $initialPrice, $tradedAmount);
            // بعد از فروش، ارز جدید را به جای ارز اولیه قرار می‌دهیم

        } catch (Exception $e) {
            $telegram->sendMessage("🚨 خطای غیرمنتظره در مدیریت حد ضرر و ثبت فروش: " . $e->getMessage());
            sleep(10);
        }
        
    } catch (Exception $e) {
        $telegram->sendMessage("🚨 خطای غیرمنتظره: " . $e->getMessage());
        sleep(10);
    }
}