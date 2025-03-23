<?php

class Indicators {
    
    // محاسبه RSI با استفاده از کتابخانه php-trader
    public static function calculateRSI($prices, $period = 14) {
        if (count($prices) < $period) {
            return null; // مقدار پیش‌فرض برای داده‌های ناکافی
        }
        
        return trader_rsi($prices, $period);
    }

    // محاسبه Stochastic RSI با استفاده از کتابخانه php-trader
    public static function calculateStochRSI($prices, $rsiPeriod = 14, $stochPeriod = 14) {
        if (count($prices) < $rsiPeriod) {
            return null; // داده کافی نداریم
        }

        // محاسبه RSI با استفاده از php-trader
        $rsiValues = self::calculateRSI($prices, $rsiPeriod);
        
        if (count($rsiValues) < $stochPeriod) {
            return null; // داده کافی نداریم
        }
        
        // محاسبه Stochastic RSI
        $stochRSI = trader_stochrsi($rsiValues, $rsiPeriod, $stochPeriod);
        
        return $stochRSI;
    }

    // تابع محاسبه EMA با استفاده از کتابخانه php-trader
    public static function calculateEMA($prices, $period) {
        if (count($prices) < $period) {
            return null; // داده کافی نداریم
        }
        
        return trader_ema($prices, $period);
    }

    // تابع محاسبه MACD با استفاده از کتابخانه php-trader
    public static function calculateMACD($prices, $shortPeriod = 12, $longPeriod = 26, $signalPeriod = 9) {
        if (count($prices) < $longPeriod) {
            return null; // داده کافی نداریم
        }

        // محاسبه MACD با استفاده از php-trader
        list($macd, $signal, $histogram) = trader_macd($prices, $shortPeriod, $longPeriod, $signalPeriod);

        return ['macd' => $macd, 'signal' => $signal, 'histogram' => $histogram];
    }

    // محاسبه Bollinger Bands با استفاده از کتابخانه php-trader
    public static function calculateBollingerBands($prices, $period = 20, $stdDevMultiplier = 2) {
        if (count($prices) < $period) {
            return null; // داده کافی نداریم
        }

        // محاسبه Bollinger Bands با استفاده از php-trader
        list($upperBand, $middleBand, $lowerBand) = trader_bbands($prices, $period, $stdDevMultiplier, 0, 0);

        return ['upper' => $upperBand, 'lower' => $lowerBand];
    }

    // محاسبه ATR با استفاده از کتابخانه php-trader
    public static function calculateATR($highs, $lows, $closes, $period = 14) {
        if (count($highs) < $period || count($lows) < $period || count($closes) < $period) {
            return null; // داده کافی نداریم
        }

        // محاسبه ATR با استفاده از php-trader
        return trader_atr($highs, $lows, $closes, $period);
    }

    // محاسبه Fear Zone
    public static function calculateFearZone($prices) {
        $rsi = self::calculateRSI($prices);
        return $rsi < 30; // زمانی که RSI کمتر از ۳۰ باشد، منطقه ترس در نظر گرفته می‌شود.
    }

    // محاسبه میانگین حجم معاملات
    public static function calculateAverageVolume($volumes, $period = 20) {
        return array_sum(array_slice($volumes, -$period)) / $period;
    }

    // اضافه کردن اندیکاتور Ichimoku Cloud
    public static function calculateIchimokuCloud($highs, $lows, $closes, $conversionPeriod = 9, $basePeriod = 26, $spanPeriod = 52) {
        if (count($highs) < $spanPeriod || count($lows) < $spanPeriod || count($closes) < $spanPeriod) {
            return null; // داده کافی نداریم
        }
    
        // محاسبه Tenkan-sen (خط تبدیل)
        $tenkan_sen = (max(array_slice($highs, -$conversionPeriod)) + min(array_slice($lows, -$conversionPeriod))) / 2;
    
        // محاسبه Kijun-sen (خط پایه)
        $kijun_sen = (max(array_slice($highs, -$basePeriod)) + min(array_slice($lows, -$basePeriod))) / 2;
    
        // محاسبه Senkou Span A (چشم‌انداز پیشرفته A)
        $span_a = ($tenkan_sen + $kijun_sen) / 2;
    
        // محاسبه Senkou Span B (چشم‌انداز پیشرفته B)
        $span_b = (max(array_slice($highs, -$spanPeriod)) + min(array_slice($lows, -$spanPeriod))) / 2;
    
        return [
            'tenkan_sen' => $tenkan_sen,
            'kijun_sen' => $kijun_sen,
            'span_a' => $span_a,
            'span_b' => $span_b
        ];
    }
    

    // اضافه کردن اندیکاتور ADX (Average Directional Index)
    public static function calculateADX($highs, $lows, $closes, $period = 14) {
        if (count($highs) < $period || count($lows) < $period || count($closes) < $period) {
            return null; // داده کافی نداریم
        }

        // محاسبه ADX با استفاده از php-trader
        return trader_adx($highs, $lows, $closes, $period);
    }

    // اضافه کردن اندیکاتور SuperTrend
    public static function calculateSuperTrend($highs, $lows, $closes, $atrPeriod = 14, $multiplier = 3) {
        if (count($highs) < $atrPeriod || count($lows) < $atrPeriod || count($closes) < $atrPeriod) {
            return null; // داده کافی نداریم
        }
    
        // ATR را برای چند کندل آخر محاسبه می‌کنیم
        $atrValues = trader_atr($highs, $lows, $closes, $atrPeriod);
        $atr = end($atrValues);
    
        if ($atr === false) {
            return null; // خطا در ATR
        }
    
        $latestClose = end($closes);
    
        // محاسبه روندهای صعودی و نزولی
        $upwardTrend = $latestClose - ($multiplier * $atr);
        $downwardTrend = $latestClose + ($multiplier * $atr);
    
        return [
            'upwardTrend' => $upwardTrend,
            'downwardTrend' => $downwardTrend
        ];
    }
    

    // اضافه کردن اندیکاتور Parabolic SAR
    public static function calculateParabolicSAR($highs, $lows, $accelerationFactor = 0.02, $maxAcceleration = 0.2) {
        if (count($highs) < 2 || count($lows) < 2) {
            return null; // داده کافی نداریم
        }

        // محاسبه Parabolic SAR
        return trader_sar($highs, $lows, $accelerationFactor, $maxAcceleration);
    }

}
