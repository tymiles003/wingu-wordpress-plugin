<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Api\Channel\ChannelApi;
use Wingu\Engine\SDK\Api\Exception\HttpClient\Unauthorized;
use Wingu\Engine\SDK\Model\Response\Channel\Beacon\PrivateBeacon;
use Wingu\Engine\SDK\Model\Response\Channel\Geofence\PrivateGeofence;
use Wingu\Engine\SDK\Model\Response\Channel\Nfc\PrivateNfc;
use Wingu\Engine\SDK\Model\Response\Channel\QrCode\PrivateQrCode;

class WinguAdmin
{
    /** @var string */
    private $name;

    /** @var string */
    private $version;

    public function __construct($name, $version)
    {
        $this->name    = $name;
        $this->version = $version;
    }

    public function wingu_menu() : void
    {
        add_options_page('Wingu Options', 'Wingu', 'manage_options', 'wingu-options', [$this, 'wingu_options']);
    }

    public function enqueue_styles() : void
    {
    }

    public function enqueue_scripts() : void
    {
    }

    public function wingu_options() : void
    {
        $winguChannelApi = new ChannelApi(Wingu::$configuration, Wingu::$httpClient, Wingu::$messageFactory,
            Wingu::$hydrator);

        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include('WinguOptionsTemplate.php');
        echo '<h2>Triggers</h2>';
        try {
            $response = $winguChannelApi->myChannels();
            $response->current();
            $i = 0;
            while ($response->valid() && $i < 5) {
                $channelname = $response->current()->name();
                $type        = \get_class($response->current());
                $channeltype = null;
                switch ($type) {
                    case PrivateGeofence::class:
                        $channeltype = 'Geofence';
                        break;
                    case PrivateQrCode::class:
                        $channeltype = 'QrCode';
                        break;
                    case PrivateNfc::class:
                        $channeltype = 'Nfc';
                        break;
                    case PrivateBeacon::class:
                        $channeltype = 'Beacon';
                        break;
                }
                echo $channelname . ': ' . $channeltype . '<br>';
                $response->next();
                $i++;
            }
        } catch (Unauthorized $exception) {
            echo 'The API Key is incorrect.';
        }
        echo '</select></div>';
//        http://wingu/api/doc#/operations/Analytics/get_api_analytics__resource_type___resource_id___interaction___aggregation__monthly
    }

    public function wingu_settings_link($links) : array
    {
        $links[] = '<a href = "' . esc_url(get_admin_url(null,
                'options-general.php?page=wingu-options')) . '" > Settings</a >';
        return $links;
    }
//    public function wingu_settings_link( $links, $plugin_name )
//    {
//        if ( $plugin_name !== 'wingu-wordpress-plugin/Wingu.php' )
//            return $links;
//        else
//            $url = get_admin_url(null, 'options-general.php?page=wingu-options');
//        $links []= '<a href="'.$url.'">Settings</a>';
//        return $links;
//    }
    public function add_wingu_post_box() : void
    {
        $winguChannelApi = new ChannelApi(Wingu::$configuration, Wingu::$httpClient, Wingu::$messageFactory,
            Wingu::$hydrator);

        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page . '));
        }

        // nonce for verification when saving
        wp_nonce_field(plugin_basename(__FILE__), 'wingu_noncename');

        echo '<p>No wingu stuff defined yet.</p>';
        echo '<p > Display</p> ';
        echo '<label><input type = "radio" name = "display_preference_post" value = "content" />Content</label>';
        echo '<label><input type = "radio" name = "display_preference_post" value = "excerpt" />Excerpt</label>';
//        multiselect triggers, divide input field by type
        echo '<select name="triggers" multiple>';
        try {
            $response = $winguChannelApi->myChannels();
            $response->current();
            $i = 0;
            while ($response->valid() && $i < 5) {
                $id          = $response->current()->id();
                $channelname = $response->current()->name();
                $type        = \get_class($response->current());
                $channeltype = null;
                switch ($type) {
                    case PrivateGeofence::class:
                        $channeltype = 'Geofence';
                        break;
                    case PrivateQrCode::class:
                        $channeltype = 'QrCode';
                        break;
                    case PrivateNfc::class:
                        $channeltype = 'Nfc';
                        break;
                    case PrivateBeacon::class:
                        $channeltype = 'Beacon';
                        break;
                }
                echo '<option value="' . $id . '">' . $channelname . ': ' . $channeltype . '</option><br>';
                $response->next();
                $i++;
            }
        } catch (Unauthorized $exception) {
            echo 'The API Key is incorrect.';
        }
        echo '</select><button type="submit">Save</button>';
        echo '<p><a href = "' . esc_url(get_admin_url() . 'options-general.php?page=wingu-options') . '" target = "_blank">Go options</a></p >';
    }

    public function my_meta_box() : void
    {
        $my_post_types = get_post_types();

        foreach ($my_post_types as $my_post_type) {
            add_meta_box(
                'Meta_box_ID', __('Wingu Metabox', 'textdomain'), [$this, 'add_wingu_post_box'], $my_post_type
            );
        }
    }

    public function link_back_content_excerpt($text) : string
    {
        $link_back_text = get_option('wingu_setting_link_back_text');
        if (is_single()) {
            $text .= $link_back_text;
        }
        return $text;
    }

    public function wingu_settings_init() : void
    {
        add_settings_section(
            'wingu_settings_section',
            'Wingu Settings',
            [$this, 'wingu_settings_section'],
            'wingu-options'
        );

        add_settings_field(
            'wingu_settings_api_key',
            'API Key',
            [$this, 'wingu_settings_api_key'],
            'wingu-options',
            'wingu_settings_section'
        );

        add_settings_field(
            'wingu_settings_display_preference',
            'Display',
            [$this, 'wingu_settings_display_preference'],
            'wingu-options',
            'wingu_settings_section'
        );

        add_settings_field(
            'wingu_settings_link_back',
            'Link back',
            [$this, 'wingu_settings_link_back'],
            'wingu-options',
            'wingu_settings_section'
        );

        add_settings_field(
            'wingu_settings_link_back-text',
            'Link back text',
            [$this, 'wingu_settings_link_back_text'],
            'wingu-options',
            'wingu_settings_section'
        );

        register_setting('wingu-options', 'wingu_setting_api_key');
        register_setting('wingu-options', 'wingu_setting_display_preference');
        register_setting('wingu-options', 'wingu_setting_link_back');
        register_setting('wingu-options', 'wingu_setting_link_back_text');
    }

    public function wingu_settings_section() : void
    {
        echo '<p>Enter your API Key. Choose whether you want to connect to triggers whole posts or excerpts. Select if links back to your site should be added at the end of your texts.</p>';
    }

    public function wingu_settings_api_key() : void
    {
        $setting = get_option('wingu_setting_api_key');
        ?>
        <input type="text" size="50" maxlength="36" name="wingu_setting_api_key" value="<?php echo $setting !== null ? esc_attr($setting) : ''; ?>">
        <?php
    }

    public function wingu_settings_display_preference() : void
    {
        $setting = get_option('wingu_setting_display_preference');
        ?>
        <label>
            <input type="radio" name="wingu_setting_display_preference" value="content" <?php checked('content', $setting, true); ?>>Content
        </label>
        <label>
            <input type="radio" name="wingu_setting_display_preference" value="excerpt" <?php checked('excerpt', $setting, true); ?>>Excerpt
        </label>
        <?php
    }

    public function wingu_settings_link_back() : void
    {
        $setting = get_option('wingu_setting_link_back');
        ?>
        <input type="checkbox" id="wingu_setting_link_back" name="wingu_setting_link_back" value="1" <?php if ($setting) echo 'checked="checked"'; ?>>
        <?php
    }

    public function wingu_settings_link_back_text() : void
    {
        $setting = get_option('wingu_setting_link_back_text');
        ?>
        <textarea id='wingu_setting_link_back_text' name='wingu_setting_link_back_text' rows='5' cols='50'><?php echo $setting; ?></textarea>
        <?php
    }
}