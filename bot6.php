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

$telegramNotifier = new TelegramNotifier($bot_token, $chat_id);

// فراخوانی تابع findBestPair برای پیدا کردن بهترین جفت ارز
$bestPairResult = $api->findBestPair();
if (isset($bestPairResult['error'])) {
    echo "خطا: " . $bestPairResult['error'] . PHP_EOL;
    $telegramNotifier->sendMessage("خطا: " . $bestPairResult['error']);
} else {
    echo "بهترین جفت ارز: " . $bestPairResult['bestPair'] . PHP_EOL;
    echo "امتیاز: " . $bestPairResult['score'] . PHP_EOL;
    echo "سیگنال‌ها: " . $bestPairResult['signals'] . PHP_EOL;
    $telegramNotifier->sendMessage("بهترین جفت ارز: " . $bestPairResult['bestPair'] . "\nامتیاز: " . $bestPairResult['score'] . "\nسیگنال‌ها: " . $bestPairResult['signals']);
}

// فرض می‌کنیم که بهترین جفت ارز پیدا شده و قصد داریم یک سفارش خرید ثبت کنیم
$symbol = $bestPairResult['bestPair']; // جفت ارز انتخابی
$amount = 0.1; // مقدار خرید
$orderType = 'limit'; // نوع سفارش
$price = 10000; // قیمت برای سفارش محدود

$orderResult = $api->placeOrder($symbol, 'buy', $amount, $price, $orderType);
if (isset($orderResult['error'])) {
    echo "خطا در ثبت سفارش: " . $orderResult['error'] . PHP_EOL;
    $telegramNotifier->sendMessage("خطا در ثبت سفارش: " . $orderResult['error']);
} else {
    echo "سفارش با موفقیت ثبت شد. شماره سفارش: " . $orderResult['orderId'] . PHP_EOL;
    $telegramNotifier->sendMessage("سفارش خرید با موفقیت ثبت شد. شماره سفارش: " . $orderResult['orderId']);
}

// فرض می‌کنیم که سفارش خرید با موفقیت ثبت شده و حالا نیاز به مدیریت حد ضرر متحرک داریم
$orderId = $orderResult['orderId']; // شماره سفارش
$initialPrice = $price; // قیمت اولیه سفارش
$trailingStopPercentage = 1; // درصد حد ضرر متحرک

$trailingStopResult = $api->manageTrailingStop($symbol, $orderId, $initialPrice, $amount, $trailingStopPercentage);
if (isset($trailingStopResult['error'])) {
    echo "خطا در مدیریت حد ضرر: " . $trailingStopResult['error'] . PHP_EOL;
    $telegramNotifier->sendMessage("خطا در مدیریت حد ضرر: " . $trailingStopResult['error']);
} else {
    echo "حد ضرر متحرک بروزرسانی شد. حد ضرر جدید: " . $trailingStopResult['new_stop_loss'] . PHP_EOL;
    $telegramNotifier->sendMessage("حد ضرر متحرک بروزرسانی شد. حد ضرر جدید: " . $trailingStopResult['new_stop_loss']);
}

// بررسی شرایط فروش و اقدام به فروش در صورت نیاز
$buyPrice = $price; // قیمت خرید ثبت‌شده
$api->checkAndSell($symbol, $buyPrice, $amount);  // فراخوانی تابع checkAndSell

?>
