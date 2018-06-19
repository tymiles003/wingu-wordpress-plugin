<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Http\Client\Curl\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Wingu\Engine\SDK\Api\Configuration;
use Wingu\Engine\SDK\Hydrator\SymfonySerializerHydrator;

class Wingu
{
    /** @var WinguLoader */
    protected $loader;

    /** @var string */
    protected $wingu;

    /** @var string */
    protected $version;

    public static $configuration;
    public static $messageFactory;
    public static $httpClient;
    public static $hydrator;

    public function __construct()
    {
        if (\defined('WINGU_VERSION')) {
            $this->version = WINGU_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->wingu  = 'wingu-wordpress-plugin';
        $this->loader = new WinguLoader();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        self::$messageFactory = new GuzzleMessageFactory();
        self::$configuration  = new Configuration((string) get_option('wingu_setting_api_key'), 'http://wingu');
        self::$httpClient     = new Client(self::$messageFactory);
        self::$hydrator       = new SymfonySerializerHydrator();
    }

    private function set_locale() : void
    {
        $wingu_i18n = new WinguI18n();
        $this->loader->add_action('plugins_loaded', $wingu_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() : void
    {
        $plugin_name = $this->wingu . '/' . basename(__FILE__);
        $wingu_admin = new WinguAdmin($this->get_Wingu(), $this->get_version());
        $this->loader->add_action('admin_menu', $wingu_admin, 'wingu_menu');
        $this->loader->add_action('admin_init', $wingu_admin, 'wingu_settings_init');
        $this->loader->add_filter('plugin_action_links_' . $plugin_name, $wingu_admin, 'wingu_settings_link');
        $this->loader->add_action('add_meta_boxes', $wingu_admin, 'my_meta_box');
        $this->loader->add_action('admin_enqueue_scripts', $wingu_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $wingu_admin, 'enqueue_scripts');

        if (get_option('wingu_setting_link_back')) {
            if (get_option('wingu_setting_display_preference') === 'content') {
                $this->loader->add_filter('the_content', $wingu_admin, 'link_back_content_excerpt');
            } elseif (get_option('wingu_setting_display_preference') === 'excerpt') {
                $this->loader->add_filter('the_excerpt', $wingu_admin, 'link_back_content_excerpt');
            }
        }
    }

    private function define_public_hooks() : void
    {
        $wingu_public = new WinguPublic($this->get_Wingu(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $wingu_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $wingu_public, 'enqueue_scripts');
    }

    public function run() : void
    {
        $this->loader->run();
    }

    public function get_Wingu() : string
    {
        return $this->wingu;
    }

    public function get_loader() : WinguLoader
    {
        return $this->loader;
    }

    public function get_version() : string
    {
        return $this->version;
    }
}