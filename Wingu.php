<?php

declare(strict_types=1);

/**
 * Author: Wingu
 * Author Email: hilfe@wingu.de
 * Author URI: https://www.wingu.de
 * Description: Describe me here
 * Domain Path: /lang
 * Plugin Name: Wingu
 * Plugin URI: https://www.wingu.de/en/developer
 * Version: 1.0
 * Text Domain: wingu-wordpress-plugin
 * */

use Wingu\Plugin\Wordpress\WinguActivator;
use Wingu\Plugin\Wordpress\WinguDeactivator;
use Wingu\Plugin\Wordpress\Wingu;

if (! defined('ABSPATH')) {
    exit;
}

define('WINGU_VERSION', '1.0.0');

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo 'You need to run \'composer install\' in plugin directory first.';
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

function activate_wingu()
{
    WinguActivator::activate();
}

function deactivate_wingu()
{
    WinguDeactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wingu');
register_deactivation_hook(__FILE__, 'deactivate_wingu');


function run_wingu()
{
    Wingu::instance()->run();
}

run_wingu();