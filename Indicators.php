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

}
