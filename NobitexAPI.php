<?php
// API Wrapper برای اتصال به صرافی
class NobitexAPI {
    private $apiUrl = "https://api.nobitex.ir";
    private $apiKey;
    private $lastRequestTime = 0;
    private $rateLimit = 0.5; // حداقل ۰.۵ ثانیه بین درخواست‌ها فاصله باشه (یعنی حداکثر ۲ درخواست در ثانیه)
    private $cache = [];
    private $cacheTTL = 5; // مدت زمان کش (۵ ثانیه)

    public function __construct($api_Key = null) {
        $this->apiKey = $api_Key;
    }

    // ارسال درخواست به API
    private function sendRequest($endpoint, $params = [], $method = "GET") {
        $cacheKey = md5($endpoint . json_encode($params));

        // بررسی کش برای کاهش تعداد درخواست‌ها
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // کنترل نرخ درخواست‌ها (Rate Limiting)
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - $this->lastRequestTime;
        
        if ($timeSinceLastRequest < $this->rateLimit) {
            usleep(($this->rateLimit - $timeSinceLastRequest) * 1_000_000); // تبدیل به میکروثانیه
        }

        // تنظیم زمان آخرین درخواست
        $this->lastRequestTime = microtime(true);

        $url = "https://api.nobitex.ir" . $endpoint;
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"]
        ];

        if ($method === "POST") {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
        } else {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // ذخیره در کش
        if ((isset($data['status']) && $data['status'] === "ok") || (isset($data['s']) && $data['s'] === "ok")) {
            $this->cache[$cacheKey] = $data;
        }

        return $data;
    }

    // دریافت قیمت بازار
    // دریافت لیست جفت ارزها
    public function getMarketPrice() {
        return $this->sendRequest("/v3/orderbook/all", [], "GET");
    }

    // دریافت لیست معامله جفت ارز مشخص
    //دریافت جزئیات جفت ارز مشخص
    public function getTrades($symbol) {
        $price = $this->sendRequest("/v2/trades/$symbol", [], 'GET');
        return $price['trades'] ?? null;
    }

    // دریافت اطلاعات کندل‌های جفت ارز انتخاب شده 
    // این تابع برای تحلیل جفت ارز انتخاب شده در تابع پایین استفاده می شود
    public function getOHLC($symbol, $resolution = "D", $to = 1562230967,  $from = 1562058167) {
        return $this->sendRequest("/market/udf/history", [
            "symbol" => $symbol,
            "resolution" => $resolution,
            "to" => $to,
            "from" => $from
        ], 'GET');
    }
   
    // تحلیل و انتخاب بهترین جفت ارز برای معامله
    public function findBestPair() {
        // دریافت قیمت‌های بازار
        $pairsData = $this->getMarketPrice();
        if (!isset($pairsData['status']) || $pairsData['status'] !== 'ok') {
            return ['error' => 'داده‌های بازار در دسترس نیستند.'];
        }

        // استخراج جفت‌ارزها (بدون 'status')
        $pairs = array_keys($pairsData);
        unset($pairs[array_search('status', $pairs)]);

        $bestPair = null;
        $bestScore = -INF;

        foreach ($pairs as $pair) {
            if (!isset($pairsData[$pair]['bids'][0][0], $pairsData[$pair]['asks'][0][0])) {
                continue; // اگر جفت‌ارز قیمت نداشته باشد، رد شود
            }

            // حجم معاملات ۲۴ ساعته و تغییر قیمت را بررسی کنیم
            $volume = $pairsData[$pair]['volume'] ?? 0;
            $change24h = $pairsData[$pair]['lastUpdate'] - $pairsData[$pair]['bids'][0][0];

            // محاسبه امتیاز جفت ارز
            $score = floatval($volume) * abs($change24h);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPair = $pair;
            }
        }

        if (!$bestPair) {
            return ['error' => 'جفت ارز مناسب پیدا نشد.'];
        }

        // دریافت اطلاعات معاملات برای بهترین جفت ارز
        $tradeData = $this->getTrades($bestPair);
        if (!$tradeData || !is_array($tradeData)) {
            return ['error' => 'اطلاعات معاملات برای جفت ارز انتخاب شده در دسترس نیست.'];
        }

        // دریافت داده‌های OHLC برای تحلیل تکنیکال
        $timeNow = time();
        $ohlc = $this->getOHLC($bestPair, "15", $timeNow, $timeNow - (86400 * 7));

        if (!isset($ohlc['c'], $ohlc['h'], $ohlc['l'], $ohlc['v'])) {
            return ['error' => 'داده‌های OHLC برای تحلیل تکنیکال در دسترس نیستند.'];
        }
    
        $closes = $ohlc['c']; // قیمت‌های بسته شدن
        $highs = $ohlc['h']; // بالاترین قیمت‌ها
        $lows = $ohlc['l']; // پایین‌ترین قیمت‌ها
        $volumes = $ohlc['v']; // حجم معاملات
    
        // اجرای تحلیل تکنیکال با همه‌ی اندیکاتورها
        $rsi = Indicators::calculateRSI($closes);
        $stochRSI = Indicators::calculateStochRSI($closes);
        $ema = Indicators::calculateEMA($closes, 50);
        $macd = Indicators::calculateMACD($closes);
        $bollinger = Indicators::calculateBollingerBands($closes);
        $atr = Indicators::calculateATR($highs, $lows, $closes);
        $fearZone = Indicators::calculateFearZone($closes);
        $averageVolume = Indicators::calculateAverageVolume($volumes);
    
        // شرط‌های بررسی وضعیت بازار
        $conditions = [];
    
        if ($rsi && end($rsi) < 30) { // RSI پایین ۳۰ یعنی اشباع فروش 🚀
            $conditions[] = "RSI سیگنال خرید می‌دهد";
        }
    
        if ($stochRSI && end($stochRSI) < 20) { // Stoch RSI پایین ۲۰ یعنی سیگنال قوی‌تر
            $conditions[] = "Stochastic RSI سیگنال خرید قوی‌تر می‌دهد";
        }
    
        if ($ema && end($closes) > end($ema)) { // قیمت بالاتر از EMA 50 یعنی روند صعودی
            $conditions[] = "قیمت بالاتر از EMA است (روند صعودی)";
        }
    
        if ($macd && end($macd['macd']) > end($macd['signal'])) { // MACD بالاتر از خط سیگنال یعنی روند صعودی
            $conditions[] = "MACD سیگنال صعودی می‌دهد";
        }
    
        if ($bollinger && end($closes) < end($bollinger['lower'])) { // قیمت زیر باند پایینی بولینگر یعنی احتمال رشد قیمت
            $conditions[] = "قیمت در باند پایین بولینگر است (احتمال رشد)";
        }
    
        if ($atr && end($atr) > 0) { // ATR مقدار بالایی داشته باشد یعنی نوسانات بالا هستند
            $conditions[] = "نوسانات بازار مناسب است (ATR بالا)";
        }
    
        if ($averageVolume && end($volumes) > $averageVolume) { // حجم بالاتر از میانگین یعنی تأیید روند
            $conditions[] = "حجم معاملات بالاتر از میانگین است (تأیید روند)";
        }
    
        if ($fearZone) { // اگر در منطقه ترس باشد، معامله انجام نمی‌شود
            return ['error' => 'جفت ارز در منطقه ترس قرار دارد و قابل معامله نیست.'];
        }
    
        // بررسی نتیجه‌ی نهایی
        return !empty($conditions) 
            ? ['bestPair' => $bestPair, 'score' => $bestScore, 'signals' => implode(", ", $conditions)] 
            : ['error' => 'هیچ جفت ارز مناسبی از نظر اندیکاتورها پیدا نشد.'];
    }
    
    // ثبت سفارش خرید/فروش
    public function placeOrder($symbol, $type, $amount, $price = null, $orderType = 'limit') {
        // اعتبارسنجی نوع سفارش (buy/sell)
        if (!in_array($type, ['buy', 'sell'])) {
            return ['error' => 'نوع سفارش نامعتبر است. باید "buy" یا "sell" باشد.'];
        }
        
        // اعتبارسنجی مقدار سفارش
        if (!is_numeric($amount) || $amount <= 0) {
            return ['error' => 'مقدار سفارش باید یک عدد مثبت باشد.'];
        }
    
        // بررسی سفارش بازار (Market Order)
        if ($orderType === 'market') {
            $price = null; // قیمت در سفارش بازار نیازی به تعیین ندارد
        } elseif (!is_numeric($price) || $price <= 0) {
            return ['error' => 'برای سفارش محدود (Limit) قیمت معتبر وارد کنید.'];
        }
    
        // استخراج ارزها از نماد (مثلاً BTCUSDT → BTC و USDT)
        preg_match('/([A-Z]+)(USDT|IRT|BTC)/', $symbol, $matches);
        if (count($matches) < 3) {
            return ['error' => 'فرمت نماد ارز نامعتبر است.'];
        }
    
        $srcCurrency = strtolower($matches[1]); // مثل btc
        $dstCurrency = strtolower($matches[2]); // مثل usdt
    
        // تنظیم داده‌های سفارش
        $orderData = [
            'type' => $type,
            'srcCurrency' => $srcCurrency,
            'dstCurrency' => $dstCurrency,
            'amount' => $amount,
        ];
    
        // اگر سفارش از نوع Limit باشد، قیمت را اضافه کنیم
        if ($orderType === 'limit') {
            $orderData['price'] = $price;
        }
    
        // ارسال درخواست به API
        $response = $this->sendRequest("/market/orders/add", $orderData, "POST");
    
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

    // محاسبه حد سود و ضرر متحرک
    public function manageTrailingStop($symbol, $orderId, $initialPrice, $amount, $trailingStopPercentage) {
        // دریافت قیمت بازار
        $marketData = $this->getMarketPrice();
        if (!isset($marketData[$symbol]['lastPrice'])) {
            return ['error' => 'قیمت فعلی بازار دریافت نشد.'];
        }
    
        $currentPrice = floatval($marketData[$symbol]['lastPrice']);
    
        // اعتبارسنجی قیمت
        if ($currentPrice <= 0) {
            return ['error' => 'قیمت نامعتبر دریافت شد.'];
        }
    
        // حد ضرر اولیه (بر اساس درصد تعیین‌شده)
        $stopLossPrice = $initialPrice * (2 - $trailingStopPercentage / 100);
    
        // بررسی افزایش قیمت و تنظیم حد ضرر جدید
        if ($currentPrice > $initialPrice) {
            $newStopLossPrice = max($stopLossPrice, $currentPrice * (2 - $trailingStopPercentage / 100));
    
            // ابتدا سفارش فروش جدید را ثبت کنیم
            $newOrder = $this->placeOrder($symbol, 'sell', $amount, null, 'market');
    
            // اگر سفارش جدید ثبت شد، سفارش قبلی لغو شود
            if (isset($newOrder['success'])) {
                $this->sendRequest("/market/orders/cancel", ['orderId' => $orderId], 'POST');
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
        $ohlc = $this->getOHLC($symbol, "15", $timeNow, $timeNow - (86400 * 7));
    
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




// private function sendRequest($endpoint, $params = [], $method = "GET") {
//     // نمایش درخواست در لاگ برای بررسی، بدون ارسال به نوبیتکس
//     echo "🔹 Mock API Call: $method $endpoint \n";
//     echo "📤 Params: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

//     // شبیه‌سازی پاسخ نوبیتکس برای ثبت سفارش
//     if ($endpoint == "/market/orders/add") {
//         return [
//             'status' => 'ok',
//             'order' => [
//                 'id' => rand(1000, 9999),  // عدد تصادفی به عنوان ID سفارش
//                 'price' => $params['price'],
//                 'amount' => $params['amount']
//             ]
//         ];
//     }

//     // شبیه‌سازی پاسخ برای لغو سفارش
//     if ($endpoint == "/market/orders/cancel-old") {
//         return ['status' => 'ok', 'message' => 'سفارش لغو شد'];
//     }

//     // شبیه‌سازی پاسخ برای آپدیت سفارش
//     if ($endpoint == "/market/orders/update") {
//         return ['status' => 'ok', 'message' => 'حد ضرر و سود آپدیت شد'];
//     }

//     // 🔥 اصلاح این قسمت: فقط اطلاعات جفت‌ارز موردنظر رو برمی‌گردونه
//     if ($endpoint == "/v3/orderbook" && isset($params['symbol'])) {
//         return [
//             'status' => 'ok',
//             'symbol' => $params['symbol'],
//             'bestBid' => 14990,  // بهترین قیمت خرید
//             'bestAsk' => 15010   // بهترین قیمت فروش
//         ];
//     }

//     // شبیه‌سازی پاسخ عمومی
//     return ['status' => 'ok'];
// }