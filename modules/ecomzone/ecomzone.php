<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class EcomZone extends Module
{
    public function __construct()
    {
        $this->name = 'ecomzone';
        $this->tab = 'market_place';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        // Register autoloader
        spl_autoload_register([$this, 'autoload']);

        $this->displayName = $this->l('EcomZone Dropshipping');
        $this->description = $this->l('Integration with EcomZone Dropshipping API');
    }

    /**
     * Autoload classes
     */
    public function autoload($className)
    {
        // Only handle our module's classes
        if (strpos($className, 'EcomZone') !== 0) {
            return;
        }

        $classPath = dirname(__FILE__) . '/classes/' . $className . '.php';
        if (file_exists($classPath)) {
            require_once $classPath;
        }
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionOrderStatusUpdate') &&
            Configuration::updateValue('ECOMZONE_API_TOKEN', '') &&
            Configuration::updateValue('ECOMZONE_API_URL', 'https://dropship.ecomzone.eu/api') &&
            Configuration::updateValue('ECOMZONE_LAST_SYNC', '') &&
            Configuration::updateValue('ECOMZONE_CRON_TOKEN', Tools::encrypt(uniqid()));
    }

    private function createLogFile()
    {
        if (!file_exists(dirname(EcomZoneLogger::LOG_FILE))) {
            mkdir(dirname(EcomZoneLogger::LOG_FILE), 0755, true);
        }
        
        if (!file_exists(EcomZoneLogger::LOG_FILE)) {
            touch(EcomZoneLogger::LOG_FILE);
            chmod(EcomZoneLogger::LOG_FILE, 0666);
        }
        
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('ECOMZONE_API_TOKEN') &&
            Configuration::deleteByName('ECOMZONE_API_URL') &&
            Configuration::deleteByName('ECOMZONE_CRON_TOKEN') &&
            Configuration::deleteByName('ECOMZONE_LAST_CRON_RUN');
    }

    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submitEcomZoneModule')) {
            Configuration::updateValue('ECOMZONE_API_TOKEN', Tools::getValue('ECOMZONE_API_TOKEN'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        
        // Handle manual product import
        if (Tools::isSubmit('importP<Down>oducts')) {
            try {
                $productSync = new EcomZoneProductSync();
                $result = $productSync->importProducts();
                $output .= $this->displayConfirmation(
                    sprintf($this->l('Imported %d products'), $result['imported'])
                );
            } catch (Exception $e) {
                $output .= $this->displayError($e->getMessage());
            }
        }
        
        // Add debug info
        $debugInfo = $this->getDebugInfo();
        
        $shopUrl = Tools::getShopDomainSsl(true);
        $shopRoot = _PS_ROOT_DIR_;

        $this->context->smarty->assign([
            'ECOMZONE_API_TOKEN' => Configuration::get('ECOMZONE_API_TOKEN'),
            'ECOMZONE_CRON_TOKEN' => Configuration::get('ECOMZONE_CRON_TOKEN'),
            'ECOMZONE_DEBUG_INFO' => $debugInfo,
            'ECOMZONE_LOGS' => $this->getRecentLogs(),
            'shop_url' => $shopUrl,
            'shop_root' => $shopRoot
        ]);
        
        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    private function getDebugInfo()
    {
        $lastCronRun = Configuration::get('ECOMZONE_LAST_CRON_RUN');
        $nextRun = !empty($lastCronRun) ? 
            date('Y-m-d H:i:s', strtotime($lastCronRun) + 3600) : 
            $this->l('Not scheduled yet');

        return [
            'php_version' => PHP_VERSION,
            'prestashop_version' => _PS_VERSION_,
            'module_version' => $this->version,
            'curl_enabled' => function_exists('curl_version'),
            'api_url' => Configuration::get('ECOMZONE_API_URL'),
            'last_sync' => Configuration::get('ECOMZONE_LAST_SYNC'),
            'last_cron_run' => $lastCronRun ?: $this->l('Never'),
            'next_cron_run' => $nextRun,
            'log_file' => EcomZoneLogger::LOG_FILE,
            'log_file_exists' => file_exists(EcomZoneLogger::LOG_FILE),
            'log_file_writable' => is_writable(EcomZoneLogger::LOG_FILE)
        ];
    }

    private function getRecentLogs($lines = 50)
    {
        if (!file_exists(EcomZoneLogger::LOG_FILE)) {
            return [];
        }
        
        $logs = array_slice(file(EcomZoneLogger::LOG_FILE), -$lines);
        return array_map('trim', $logs);
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $order = $params['order'];
        $newOrderStatus = $params['newOrderStatus'];

        // Sync order when it's paid
        if ($newOrderStatus->paid == 1) {
            try {
                $orderSync = new EcomZoneOrderSync();
                $result = $orderSync->syncOrder($order->id);
                
                // Log the result
                PrestaShopLogger::addLog(
                    'EcomZone order sync: ' . json_encode($result),
                    1,
                    null,
                    'Order',
                    $order->id,
                    true
                );
            } catch (Exception $e) {
                PrestaShopLogger::addLog(
                    'EcomZone order sync error: ' . $e->getMessage(),
                    3,
                    null,
                    'Order',
                    $order->id,
                    true
                );
            }
        }
    }

    public function hookActionCronJob($params)
    {
        $cronTask = new EcomZoneCronTask();
        
        // Check if it's time to run
        $lastRun = Configuration::get('ECOMZONE_LAST_CRON_RUN');
        if (empty($lastRun) || (strtotime($lastRun) + $cronTask->cron_frequency) <= time()) {
            return $cronTask->run();
        }
        
        return true;
    }

    public function runCronTasks()
    {
        try {
            EcomZoneLogger::log('Starting scheduled product sync');
            
            $lastRun = Configuration::get('ECOMZONE_LAST_CRON_RUN');
            $frequency = 3600; // 1 hour in seconds
            
            if (!empty($lastRun) && (strtotime($lastRun) + $frequency) > time()) {
                EcomZoneLogger::log('Skipping cron - too soon since last run');
                return false;
            }
            
            $productSync = new EcomZoneProductSync();
            $result = $productSync->importProducts();
            
            Configuration::updateValue('ECOMZONE_LAST_CRON_RUN', date('Y-m-d H:i:s'));
            EcomZoneLogger::log('Scheduled product sync completed', 'INFO', $result);
            
            return $result;
        } catch (Exception $e) {
            EcomZoneLogger::log('Scheduled product sync failed', 'ERROR', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
} 
