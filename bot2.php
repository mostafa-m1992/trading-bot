<?php

require '/root/tradeBot/config.php';
require_once '/root/tradeBot/NobitexAPI.php';
require_once '/root/tradeBot/Indicators.php';
require_once '/root/tradeBot/TelegramNotifier.php';

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
$min_trade_volume = $config['trade']['min_trade_volume'];

// ساخت نمونه از کلاس NobitexAPI
$api = new NobitexAPI($api_key);
// ایجاد شی از کلاس TelegramNotifier
$telegramNotifier = new TelegramNotifier($bot_token, $chat_id);

while (true) {
    try {
        $marketData = $nobitexAPI->getMarketPrice();
        if (!$marketData) {
            $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات بازار!");
            sleep(10);
            continue;
        }

        $bestPairData = $nobitexAPI->findBestPair();
        if (isset($bestPairData['error'])) {
            $telegram->sendMessage("❌ " . $bestPairData['error']);
            sleep(10);
            continue;
        }

        $symbol = $bestPairData['bestPair'];
        $price = $marketData[$bestPair]['bids'][0][0] ?? null; 
        if (!$marketPrice) {
            $telegram->sendMessage("⚠️ خطا در دریافت قیمت $symbol!");
            sleep(10);
            continue;
        }

        echo "✅ جفت ارز مناسب: " . $symbol . "\n";
        $telegram->sendMessage("✅ جفت ارز: $symbol | قیمت: $price");

        // تلاش برای خرید
        $tradeResult = $nobitexAPI->placeOrder($symbol, 'buy', $min_trade_volume, $price, $orderType);
        if (!isset($tradeResult['status']) || $tradeResult['status'] !== 'ok') {
            $telegram->sendMessage("❌ خرید ناموفق برای $symbol!");
            continue;
        }

        $telegram->sendMessage("🎉 خرید انجام شد! | جفت ارز: $symbol | قیمت خرید: $price");

        sleep(15);



        // مدیریت حد سود و ضرر متحرک
        $trailingStopResult = $api->manageTrailingStop($symbol, $orderId, $price, $amount, $trailing_stop);
        if (isset($trailingStopResult['error'])) {
            $telegramNotifier->sendMessage('خطا در مدیریت حد ضرر: ' . $trailingStopResult['error']);
        } else {
            $telegramNotifier->sendMessage('حد ضرر متحرک بروزرسانی شد. جزئیات: ' . print_r($trailingStopResult, true));
        }

        // بررسی و ثبت سفارش فروش
        $api->checkAndSell($symbol, $price, $amount);
    } catch (Exception $e) {
        $telegram->sendMessage("🚨 خطای غیرمنتظره: " . $e->getMessage());
        sleep(10);
    }
}