<?php
// API Wrapper برای اتصال به صرافی
class ExchangeAPI {
    private $apiUrl = "https://api.nobitex.ir";
    private $apiKey;
    private $lastRequestTime = 0;
    private $rateLimit = 0.5; // حداقل ۰.۵ ثانیه بین درخواست‌ها فاصله باشه (یعنی حداکثر ۲ درخواست در ثانیه)
    private $cache = [];

    public function __construct($api_Key = null) {
        $this->apiKey = $api_Key;
    }

    // ارسال درخواست به API
    public function sendRequest($endpoint, $params = [], $method = "GET") {
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
    
        $url = $this->apiUrl . $endpoint;
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"]
        ];
    
        // احراز هویت API
        if ($this->apiKey) {
            $options[CURLOPT_HTTPHEADER][] = "Authorization: Token " . $this->apiKey;
        }
    
        if ($method === "POST") {
            $options[CURLOPT_POST] = true;
            if (empty($params)) {
                $options[CURLOPT_POSTFIELDS] = json_encode(new \stdClass()); // ارسال یک شیء خالی
            } else {
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            }
        } else {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }
    
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $data = json_decode($response, true);
    
        // چاپ اطلاعات برای دیباگ کردن
        // echo "Request URL: $url\n";
        // echo "HTTP Code: $httpCode\n";
        // echo "Raw Response: $response\n";
    
        // ذخیره در کش
        if ((isset($data['status']) && $data['status'] === "ok") || (isset($data['s']) && $data['s'] === "ok")) {
            $this->cache[$cacheKey] = $data;
        }
    
        return $data;
    }    

    // گرفتن موجودی حساب
    public function getBalance($currency = "usdt") {
        $endpoint = "/users/wallets/balance";
        
        // درخواست موجودی از صرافی برای ارز خاص
        $response = $this->sendRequest($endpoint, ["currency" => $currency], "POST");
    
        if (!$response || !isset($response['status']) || $response['status'] !== 'ok') {
            // دیباگ کردن پاسخ
            echo "API Response: " . print_r($response, true) . PHP_EOL;
            return ['error' => 'خطا در دریافت موجودی کیف پول'];
        }
    
        return $response['balance']; // تمام موجودی‌ها را برمی‌گرداند
    }

    // دریافت قیمت بازار
    // دریافت لیست کل جفت ارزها
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
        $ichimoku = Indicators::calculateIchimokuCloud($highs, $lows, $closes);
        $adx = Indicators::calculateADX($highs, $lows, $closes);
        $superTrend = Indicators::calculateSuperTrend($highs, $lows, $closes);
        $parabolicSAR = Indicators::calculateParabolicSAR($highs, $lows);
    
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

        // if ($ichimoku && end($closes) > end($ichimoku['cloudTop'])) {
        //     $conditions[] = "قیمت بالای ابر ایچیموکو است (روند صعودی)";
        // }

        if ($ichimoku && is_array($ichimoku)) {
            $cloudTop = max($ichimoku['span_a'], $ichimoku['span_b']);
            $cloudBottom = min($ichimoku['span_a'], $ichimoku['span_b']);
        
            if (end($closes) > $cloudTop) {
                $conditions[] = "قیمت بالای ابر ایچیموکو است (روند صعودی)";
            } elseif (end($closes) < $cloudBottom) {
                $conditions[] = "قیمت زیر ابر ایچیموکو است (روند نزولی)";
            } else {
                $conditions[] = "قیمت داخل ابر ایچیموکو است (ناحیه رنج)";
            }
        } else {
            echo "خطا: مقدار ایچیموکو معتبر نیست!\n";
        }      

        // بررسی روند قوی و صعودی با ADX و MACD
        if ($adx && end($adx) > 25) {
            // MACD باید بالاتر از خط سیگنال باشد و EMA باید پایین‌تر از قیمت باشد (برای تایید روند صعودی)
            if ($macd && end($macd['macd']) > end($macd['signal']) && end($closes) > end($ema)) {
                $conditions[] = "ADX و MACD نشان‌دهنده‌ی روند صعودی قوی هستند";
            }
        }

        if ($superTrend && end($superTrend) === 'buy') {
            $conditions[] = "SuperTrend سیگنال خرید داده است";
        }
        
        if ($parabolicSAR && end($parabolicSAR) < end($closes)) {
            $conditions[] = "Parabolic SAR تایید روند صعودی داده است";
        }
    
        // بررسی نتیجه‌ی نهایی
        return !empty($conditions) 
            ? ['bestPair' => $bestPair, 'score' => $bestScore, 'signals' => implode(", ", $conditions)] 
            : ['error' => 'هیچ جفت ارز مناسبی از نظر اندیکاتورها پیدا نشد.'];
    }
    
}
