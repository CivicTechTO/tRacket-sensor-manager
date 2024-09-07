<?php
namespace com_webcomand_noisemeter_www;

// Add autoloader to access the webCOMAND PHP API (https://www.webcomand.com/docs/api/php/)
require('/var/www/webcomand/comand.php');

/**
 * Route requests to https://manage.tracket.info/ to the controllers.
 */
\io_comand_mvc\router::route([
    'namespace' => __NAMESPACE__,
    'namespace_path'=>'../packages/' . __NAMESPACE__ . '/',
    'base_dir' => '../packages/' . __NAMESPACE__ . '/',
    'default' => 'device_manager'
]);
