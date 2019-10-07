<?php

declare(strict_types=1);

/**
 * Plugin Name: Wingu
 * Plugin URI: https://www.wingu.de/en/developer
 * Description: Allows you to seamlessly link content you have created on Wordpress Platform to Triggers you manage through Wingu Proximity Platform.
 * Version: 1.0.0
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
    _e('run_composer_install_first', Wingu::name());
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

function wingu_activate()
{
    WinguActivator::activate();
}

function wingu_deactivate()
{
    WinguDeactivator::deactivate();
}

function wingu_uninstall()
{
    delete_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID);
    delete_option(Wingu::GLOBAL_KEY_API_KEY);
    delete_option(Wingu::GLOBAL_KEY_LINK_BACK);
    delete_option(Wingu::GLOBAL_KEY_LINK_BACK_TEXT);
    delete_option(Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
    delete_post_meta_by_key(Wingu::POST_KEY_LINK_BACK);
    delete_post_meta_by_key(Wingu::POST_KEY_DISPLAY_PREFERENCE);
    delete_post_meta_by_key(Wingu::POST_KEY_CONTENT);
    delete_post_meta_by_key(Wingu::POST_KEY_COMPONENT);
}

register_activation_hook(__FILE__, 'wingu_activate');
register_deactivation_hook(__FILE__, 'wingu_deactivate');
register_uninstall_hook(__FILE__, 'wingu_uninstall');

Wingu::instance()->run();
