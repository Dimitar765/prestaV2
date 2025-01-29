<?php

class EcomZoneCronTask extends ModuleGrid
{
    public function __construct()
    {
        parent::__construct();
        $this->name = 'ecomzone_cron';
        $this->title = $this->l('EcomZone Product Sync');
        $this->cron_frequency = 3600 * 2; // Run every hour
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        Configuration::updateValue('ECOMZONE_LAST_CRON_RUN', '');
        return true;
    }

    public function run()
    {
        try {
            EcomZoneLogger::log('Starting scheduled product sync');
            
            $productSync = new EcomZoneProductSync();
            $result = $productSync->importProducts();
            
            Configuration::updateValue('ECOMZONE_LAST_CRON_RUN', date('Y-m-d H:i:s'));
            EcomZoneLogger::log('Scheduled product sync completed', 'INFO', $result);
            
            return true;
        } catch (Exception $e) {
            EcomZoneLogger::log('Scheduled product sync failed', 'ERROR', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 
