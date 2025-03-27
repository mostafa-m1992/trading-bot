<?php

// سفارشات خرید و فروش، مدیریت حد سود و ضرر

class Order {

    private $api;

    public function __construct(ExchangeAPI $api) {
        $this->api = $api;
    }

    // ثبت سفارش خرید/فروش
    // public function placeOrder($symbol, $type, $tradedAmount, $price = null, $execution = 'market') {
    //     // اعتبارسنجی نوع سفارش (buy/sell)
    //     if (!in_array($type, ['buy', 'sell'])) {
    //         return 'نوع سفارش نامعتبر است. باید "buy" یا "sell" باشد.';
    //     }

    //     // $tradedAmount = round($tradedAmount, 2); // مثال: 26.22

    //     // اعتبارسنجی مقدار سفارش
    //     // if ($tradedAmount <= 2) {
    //     //     var_dump($tradedAmount);
    //     //     return 'موجودی حساب کمتر از 2 تتر است';
    //     // }

    //     if (isset($tradedAmount)) {
    //         return $tradedAmount;
    //     }
        

    //     // تبدیل مقدار به رشته
    //     // $amountStr = (string)$tradedAmount;


    //     // استخراج ارزها از نماد (مثلاً BTCUSDT → BTC و USDT)
    //     // بدون خط تیره
    //     preg_match('/^([A-Z]+)(USDT|IRT|BTC)$/', $symbol, $matches);
    //     // استفاده از خط تیره
    //     // preg_match('/([A-Z]+)-([A-Z]+)/', $symbol, $matches); 
    //     if (count($matches) < 1) {
    //         return 'فرمت نماد ارز نامعتبر است.';
    //     }

    //     $srcCurrency = strtolower($matches[1]); // مثل btc
    //     $dstCurrency = strtolower($matches[2]); // مثل usdt

    //     print_r($srcCurrency);
    //     print_r($dstCurrency);

    //     // تنظیم داده‌های سفارش
    //     $orderData = [
    //         'type' => $type,
    //         'srcCurrency' => $srcCurrency,
    //         'dstCurrency' => $dstCurrency,
    //         'amount' => $tradedAmount,
    //         'price' => ($execution === 'limit') ? (string)$price : "market",
    //         'execution' => strtolower($execution)
    //     ];

    //     var_dump($orderData);

    //     // اگر سفارش از نوع Limit باشد، قیمت را اضافه کنیم
    //     // if ($execution === 'limit') {
    //     //     $orderData['price'] = $price;
    //     // }

    //     error_log("Order Data: " . print_r($orderData, true));
    //     // ارسال درخواست به API
    //     $response = $this->api->sendRequest("/market/orders/add", $orderData, "POST");
    //     error_log(print_r($response, true)); // ذخیره در error_log سرور
    //     print_r($response);

    //     // بررسی پاسخ API
    //     if (!isset($response['status']) || $response['status'] !== 'ok') {
    //         return ['error' => 'خطا در ثبت سفارش: ' . ($response['message'] ?? 'نامشخص')];
    //     }

    //     return [
    //         'success' => true,
    //         'orderId' => $response['order']['id'] ?? null,
    //         'message' => 'سفارش با موفقیت ثبت شد.',
    //         'order_data' => $response['order'] ?? []
    //     ];
    // }

    public function placeOrder($symbol, $type, $tradedAmount, $price = null, $execution = 'market') {
        // اعتبارسنجی نوع سفارش (buy/sell)
        if (!in_array($type, ['buy', 'sell'])) {
            return ['error' => 'نوع سفارش نامعتبر است. باید "buy" یا "sell" باشد.'];
        }
    
        // اعتبارسنجی مقدار سفارش
        if (!isset($tradedAmount) || $tradedAmount <= 1) {
            return ['error' => 'مقدار سفارش نامعتبر است.'];
        }
    
        // استخراج ارزها از نماد
        $symbol = strtoupper($symbol); // تبدیل به حروف بزرگ برای یکپارچگی
        $matches = [];
        
        // ابتدا حالت با خط تیره را بررسی می‌کنیم
        if (preg_match('/^([A-Z]+)-([A-Z]+)$/', $symbol, $matches)) {
            $srcCurrency = strtolower($matches[1]);
            $dstCurrency = strtolower($matches[2]);
        }
        // سپس حالت بدون خط تیره را بررسی می‌کنیم
        elseif (preg_match('/^([A-Z]+)(USDT|IRT|BTC)$/', $symbol, $matches)) {
            $srcCurrency = strtolower($matches[1]);
            $dstCurrency = strtolower($matches[2]);
        }
        else {
            return ['error' => 'فرمت نماد ارز نامعتبر است. مثال‌های صحیح: BTC-USDT یا BTCUSDT'];
        }
    
        // تبدیل مقدار به رشته (مطابق نیاز API نوبیتکس)
        $amountStr = (string)$tradedAmount;
        $priceStr = (string)$price;
    
        // تنظیم داده‌های سفارش
        $orderData = [
            'type' => $type,
            'srcCurrency' => $srcCurrency,
            'dstCurrency' => $dstCurrency,
            'amount' => $amountStr,
            'price' => $priceStr,
            'execution' => strtolower($execution)
        ];
    
        if ($execution === 'limit') {
            $orderData['price'] = $price;
        }

        // لاگ برای دیباگ
        error_log("Order Data: " . print_r($orderData, true));
    
        // ارسال درخواست به API
        $response = $this->api->sendRequest("/market/orders/add", $orderData, "POST");
        
        // لاگ پاسخ
        error_log("API Response: " . print_r($response, true));
    
        // بررسی پاسخ API
        if (!isset($response['status']) || $response['status'] !== 'ok') {
            return ['error' => 'خطا در ثبت سفارش: ' . ($response['message'] ?? 'نامشخص')];
        }
    
        return [
            'success' => true,
            'orderId' => $response['order']['id'] ?? null,
            'message' => 'سفارش با موفقیت ثبت شد.',
            'order_data' => $response['order'] ?? []
        ];
    }

    // به دست آوردن قیمت خریداری شده جفت ارز
    public function getLastBuyPrice($symbol) {
        $parts = explode("USDT", $symbol);
        if (count($parts) < 2) {
            return ['error' => 'نام نماد نامعتبر است.'];
        }
        $srcCurrency = strtolower($parts[0]);
        $dstCurrency = "usdt";
    
        $endpoint = "/market/orders/list";
        $params = [
            'srcCurrency' => $srcCurrency,
            'dstCurrency' => $dstCurrency,
            'type' => 'buy',
            'status' => 'done', // فقط سفارشات انجام‌شده
            'details' => 2,
            'order' => '-created_at' // دریافت آخرین سفارش انجام‌شده
        ];
    
        $response = $this->api->sendRequest($endpoint, $params, 'GET');
    
        if (!isset($response['orders']) || empty($response['orders'])) {
            return ['error' => 'سفارش خرید معتبری یافت نشد.'];
        }
    
        return floatval($response['orders'][0]['price']); // آخرین قیمت خرید انجام‌شده
    }
    

    // محاسبه حد سود و ضرر متحرک
    public function manageTrailingStop($symbol, $orderId, $initialPrice, $amount, $trailingStopPercentage) {
        // دریافت قیمت بازار
        $marketData = $this->api->getMarketPrice();
        if (!isset($marketData[$symbol]['lastUpdate'])) {
            return ['error' => 'قیمت فعلی بازار دریافت نشد.'];
        }

        $currentPrice = floatval($marketData[$symbol]['lastUpdate']);

        // اعتبارسنجی قیمت
        if ($currentPrice <= 0) {
            return ['error' => 'قیمت نامعتبر دریافت شد.'];
        }

        // حد ضرر اولیه (بر اساس درصد تعیین‌شده)
        $stopLossPrice = $initialPrice * (1 - $trailingStopPercentage / 100);

        // بررسی افزایش قیمت و تنظیم حد ضرر جدید
        if ($currentPrice > $initialPrice) {
            $newStopLossPrice = max($stopLossPrice, $currentPrice * (1 - $trailingStopPercentage / 100));

            // ابتدا سفارش فروش جدید را ثبت کنیم
            $newOrder = $this->placeOrder($symbol, 'sell', $amount);

            // اگر سفارش جدید ثبت شد، سفارش قبلی لغو شود
            if (isset($newOrder['success'])) {
                $this->api->sendRequest("/market/orders/cancel", ['orderId' => $orderId], 'POST');
            }

            return [
                'success' => 'حد ضرر متحرک بروزرسانی شد.',
                'new_stop_loss' => $newStopLossPrice,
                'order_response' => $newOrder
            ];
        }
        // بررسی رسیدن قیمت به حد ضرر
        if ($currentPrice <= $stopLossPrice) {
            $response = $this->placeOrder($symbol, 'sell', $amount, null, 'market');
            return [
                'error' => 'حد ضرر فعال شد و سفارش فروش ثبت شد.',
                'order_response' => $response
            ];
        }
        return ['success' => 'شرایط برای بروزرسانی حد ضرر مناسب نیست.'];
    }

    // تحلیل جفت ارز موجود برای فروش
    public function checkSellConditions($symbol, $buyPrice) {
        // دریافت داده‌های کندل برای تحلیل تکنیکال
        $timeNow = time();
        $ohlc = $this->api->getOHLC($symbol, "15", $timeNow, $timeNow - (86400 * 7));

        if (!isset($ohlc['c'], $ohlc['h'], $ohlc['l'], $ohlc['v'])) {
            return ['error' => 'داده‌های OHLC برای تحلیل تکنیکال در دسترس نیستند.'];
        }

        $closes = $ohlc['c']; // قیمت‌های بسته شدن
        $highs = $ohlc['h']; // بالاترین قیمت‌ها
        $lows = $ohlc['l']; // پایین‌ترین قیمت‌ها
        $volumes = $ohlc['v']; // حجم معاملات

        // اجرای تحلیل تکنیکال
        $rsi = Indicators::calculateRSI($closes);
        $stochRSI = Indicators::calculateStochRSI($closes);
        $ema = Indicators::calculateEMA($closes, 50);
        $macd = Indicators::calculateMACD($closes);
        $bollinger = Indicators::calculateBollingerBands($closes);
        $atr = Indicators::calculateATR($highs, $lows, $closes);
        $averageVolume = Indicators::calculateAverageVolume($volumes);
        $ichimoku = Indicators::calculateIchimokuCloud($highs, $lows, $closes);
        $adx = Indicators::calculateADX($highs, $lows, $closes);
        $superTrend = Indicators::calculateSuperTrend($highs, $lows, $closes);
        $parabolicSAR = Indicators::calculateParabolicSAR($highs, $lows);

        // شرط‌های بررسی وضعیت بازار برای فروش
        $conditions = [];

        // RSI بالای ۷۰ یعنی اشباع خرید (سیگنال فروش)
        if ($rsi && end($rsi) > 70) {
            $conditions[] = "RSI سیگنال فروش می‌دهد (اشباع خرید)";
        }

        // Stoch RSI بالای ۸۰ یعنی احتمال اصلاح قیمت
        if ($stochRSI && end($stochRSI) > 80) {
            $conditions[] = "Stochastic RSI احتمال اصلاح قیمت را نشان می‌دهد";
        }

        // قیمت پایین‌تر از EMA 50 یعنی روند نزولی است
        if ($ema && end($closes) < end($ema)) {
            $conditions[] = "قیمت پایین‌تر از EMA است (روند نزولی)";
        }

        // MACD پایین‌تر از خط سیگنال یعنی روند نزولی
        if ($macd && end($macd['macd']) < end($macd['signal'])) {
            $conditions[] = "MACD سیگنال نزولی می‌دهد";
        }

        // قیمت به باند بالایی بولینگر رسیده (احتمال اصلاح قیمت)
        if ($bollinger && end($closes) > end($bollinger['upper'])) {
            $conditions[] = "قیمت در باند بالای بولینگر است (احتمال اصلاح)";
        }

        if ($atr && end($atr) < 0) { // ATR مقدار پایینی داشته باشد یعنی نوسانات پایین هستند
            $conditions[] = "نوسانات بازار مناسب است (ATR بالا)";
        }

        if ($averageVolume && end($volumes) < $averageVolume) { // حجم پایین تر از میانگین یعنی عدم تأیید روند
            $conditions[] = "حجم معاملات بالاتر از میانگین است (تأیید روند)";
        }

        if ($ichimoku && end($closes) < end($ichimoku['cloudBottom'])) {
            $conditions[] = "قیمت زیر ابر ایچیموکو است (روند نزولی)";
        }

        // بررسی روند قوی و نزولی با ADX و MACD
        if ($adx && end($adx) > 25) {
            // MACD باید پایین‌تر از خط سیگنال باشد (برای تایید روند نزولی)
            if ($macd && end($macd['macd']) < end($macd['signal'])) {
                $conditions[] = "ADX و MACD نشان‌دهنده‌ی روند نزولی قوی هستند";
            }
        }

        if ($superTrend && end($superTrend) === 'sell') {
            $conditions[] = "SuperTrend سیگنال فروش داده است";
        }

        if ($parabolicSAR && end($parabolicSAR) > end($closes)) {
            $conditions[] = "Parabolic SAR تایید روند نزولی داده است";
        }

        // اگر قیمت فعلی کمتر از قیمت خرید باشد، فروش انجام نشود (حد ضرر متحرک مسئول فروش در ضرر است)
        $currentPrice = end($closes);
        if ($currentPrice < $buyPrice) {
            return ['warning' => 'قیمت فعلی پایین‌تر از قیمت خرید است، نیازی به فروش نیست.'];
        }

        // بررسی نتیجه‌ی نهایی
        return !empty($conditions)
            ? ['sell_signal' => true, 'signals' => implode(", ", $conditions)]
            : ['sell_signal' => false, 'message' => 'شرایط فروش تأیید نشده است.'];
    }

    // ارسال دستور عملیات فروش به تابع مربوطه
    public function checkAndSell($symbol, $buyPrice, $amount) {
        // بررسی شرایط فروش
        $sellCheck = $this->checkSellConditions($symbol, $buyPrice);

        if (isset($sellCheck['error'])) {
            echo "خطا در بررسی شرایط فروش: " . $sellCheck['error'] . PHP_EOL;
            return;
        }

        if ($sellCheck['sell_signal']) {
            echo "سیگنال فروش دریافت شد: " . $sellCheck['signals'] . PHP_EOL;
            $this->placeOrder($symbol, 'sell', $amount);
        } else {
            echo "هنوز شرایط فروش تأیید نشده است. " . PHP_EOL;
        }
    }    
}