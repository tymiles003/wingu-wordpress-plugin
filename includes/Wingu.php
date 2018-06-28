<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Http\Client\Curl\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Wingu\Engine\SDK\Api\Configuration;
use Wingu\Engine\SDK\Api\WinguApi;
use Wingu\Engine\SDK\Hydrator\SymfonySerializerHydrator;

class Wingu
{
    /** @var self */
    private static $instance;

    public const GLOBAL_KEY_API_KEY            = 'wingu_setting_api_key';
    public const GLOBAL_KEY_API_IS_VALID       = 'wingu_setting_api_key_is_valid';
    public const GLOBAL_KEY_DISPLAY_PREFERENCE = 'wingu_setting_display_preference';
    public const GLOBAL_KEY_LINK_BACK          = 'wingu_setting_link_back';
    public const GLOBAL_KEY_LINK_BACK_TEXT     = 'wingu_setting_link_back_text';

    public const POST_KEY_DISPLAY_PREFERENCE = '_wingu_post_display_preference';
    public const POST_KEY_LINK_BACK          = '_wingu_post_link_back';
    public const POST_KEY_TRIGGERS           = '_wingu_post_triggers';

    /** @var WinguLoader */
    protected $loader;

    /** @var string */
    protected $name;

    /** @var string */
    protected $version;

    public static $API;

    public function __construct()
    {
        if (\defined('WINGU_VERSION')) {
            $this->version = WINGU_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->name   = 'wingu-wordpress-plugin';
        $this->loader = new WinguLoader();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $messageFactory = new GuzzleMessageFactory();
        self::$API = new WinguApi(
            new Configuration((string) get_option(self::GLOBAL_KEY_API_KEY), 'http://wingu'),
            new Client($messageFactory),
            $messageFactory,
            new SymfonySerializerHydrator());
    }

    public static function instance() : Wingu
    {
        if ( self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function set_locale() : void
    {
        $wingu_i18n = new WinguI18n();
        $wingu_i18n->set_domain($this->name());
        $this->loader->add_action('plugins_loaded', $wingu_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() : void
    {
        $plugin_name = $this->name . '/' . basename(__FILE__);
        $wingu_admin = new WinguAdmin($this->name(), $this->version());
        $this->loader->add_action('admin_menu', $wingu_admin, 'wingu_menu');
        $this->loader->add_action('admin_notices', $wingu_admin, 'api_key_notice' );
        $this->loader->add_action('admin_init', $wingu_admin, 'wingu_settings_init');
        $this->loader->add_filter('plugin_action_links_' . $plugin_name, $wingu_admin, 'wingu_settings_link');
        $this->loader->add_action('manage_posts_custom_column' , $wingu_admin, 'wingu_custom_posts_column', 10, 2);
        $this->loader->add_filter('manage_posts_columns' , $wingu_admin, 'add_wingu_posts_column');
        $this->loader->add_action('add_meta_boxes', $wingu_admin, 'wingu_meta_box');
        $this->loader->add_action('post_updated', $wingu_admin, 'wingu_post_updated', 50, 2);
        $this->loader->add_action('save_post', $wingu_admin, 'wingu_save_post_meta', 100);


        $this->loader->add_action('admin_enqueue_scripts', $wingu_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $wingu_admin, 'enqueue_scripts');
    }

    private function define_public_hooks() : void
    {
        $wingu_public = new WinguPublic($this->name(), $this->version());
        $this->loader->add_action('wp_enqueue_scripts', $wingu_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $wingu_public, 'enqueue_scripts');
    }

    public function run() : void
    {
        $this->loader->run();
    }

    public function name() : string
    {
        return $this->name;
    }

    public function loader() : WinguLoader
    {
        return $this->loader;
    }

    public function version() : string
    {
        return $this->version;
    }
}

Wingu::instance();