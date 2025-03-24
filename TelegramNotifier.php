<?php

class TelegramNotifier {
    private $bot_token;
    private $chat_id;
    private $log_file = "telegram_log.txt"; // مسیر فایل لاگ

    public function __construct($bot_token, $chat_id) {
        $this->bot_token = $bot_token;
        $this->chat_id = $chat_id;
    }

    public function sendMessage($message, $parseMode = 'Markdown') {
        $url = "https://api.telegram.org/bot{$this->bot_token}/sendMessage";
        $data = [
            'chat_id' => $this->chat_id,
            'text' => $message,
            'parse_mode' => $parseMode // پشتیبانی از Markdown و HTML
        ];

        // استفاده از cURL برای ارسال درخواست
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // بررسی وضعیت ارسال پیام
        if ($response === false || $httpCode !== 200) {
            $errorMessage = "❌ خطا در ارسال پیام به تلگرام! وضعیت: " . $httpCode;
            error_log($errorMessage);
            $this->logMessage($message, 'Failed', $errorMessage);
        } else {
            $this->logMessage($message, 'Success', 'پیام با موفقیت ارسال شد.');
        }
    }

    private function logMessage($message, $status, $details) {
        $logEntry = date("Y-m-d H:i:s") . " | وضعیت: {$status} | پیام: {$message} | جزئیات: {$details}\n";
        file_put_contents($this->log_file, $logEntry, FILE_APPEND);
    }
}
