<?php

declare(strict_types=1);

/**
 * Plugin Name: Wingu
 * Plugin URI: https://www.wingu.de/en/developer
 * Description: Describe me here
 * Version: 1.0
 * Author: Wingu
 * Author URI: https://www.wingu.de
 * Author Email: hilfe@wingu.de
 * License: GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: wingu
 * */

use Wingu\Plugin\Wordpress\Wingu;
use Wingu\Plugin\Wordpress\WinguActivator;
use Wingu\Plugin\Wordpress\WinguDeactivator;

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

Wingu::instance()->run();
