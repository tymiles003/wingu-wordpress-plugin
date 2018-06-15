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
        self::$configuration  = new Configuration('7f094101-5348-4d4e-8356-388c794f5455', 'http://wingu');
        self::$httpClient     = new Client(self::$messageFactory);
        self::$hydrator       = new SymfonySerializerHydrator();
    }

    private function set_locale() : void
    {
        $plugin_i18n = new WinguI18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() : void
    {
        $plugin_admin = new WinguAdmin($this->get_Wingu(), $this->get_version(), $this);
        $this->loader->add_action('admin_menu', $plugin_admin, 'wingu_menu');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    private function define_public_hooks() : void
    {
        $plugin_public = new WinguPublic($this->get_Wingu(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
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