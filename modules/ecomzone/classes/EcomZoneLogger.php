<?php

class EcomZoneLogger
{
    const LOG_FILE = _PS_ROOT_DIR_ . '/var/logs/ecomzone.log';
    
    public static function log($message, $level = 'INFO', $context = [])
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[$date] [$level] $message $contextStr\n";
        
        file_put_contents(self::LOG_FILE, $logMessage, FILE_APPEND);
        
        // Also log to PrestaShop
        PrestaShopLogger::addLog(
            "EcomZone: $message",
            ($level === 'ERROR' ? 3 : 1),
            null,
            'EcomZone',
            null,
            true
        );
    }
} 