<?php
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

// Include the module class
require_once dirname(__FILE__) . '/ecomzone.php';

// Ensure module is loaded
$module = Module::getInstanceByName('ecomzone');
if (!$module) {
    die('Module not found');
}

// Security token check
$token = null;

if (php_sapi_name() === 'cli') {
    global $argv;
    foreach ($argv as $arg) {
        if (preg_match('/^--token=(.+)$/', $arg, $matches)) {
            $token = $matches[1];
            break;
        }
    }
} else {
    $token = Tools::getValue('token');
}

$configToken = Configuration::get('ECOMZONE_CRON_TOKEN');

echo "Config token: " . $configToken . "\n";
echo "Provided token: " . $token . "\n";

if (empty($token) || $token !== $configToken) {
    die('Invalid token. Provided: ' . $token . ', Expected: ' . $configToken);
}

try {
    $result = $module->runCronTasks();
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 