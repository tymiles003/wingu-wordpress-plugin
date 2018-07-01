<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Model\Request\Card as RequestCard;
use Wingu\Engine\SDK\Api\Exception\HttpClient\Unauthorized;
use Wingu\Engine\SDK\Model\Request\Component\CMS;
use Wingu\Engine\SDK\Model\Request\Content\PrivateContentChannels;
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

    public function name(): string
    {
        return $this->name;
    }

    public  function version(): string
    {
        return $this->version;
    }

    public function wingu_options() : void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $winguChannelApi = Wingu::$API->channel();
        try {
            $ping = Wingu::$API->wingu()->ping();
        } catch (Unauthorized $exception) {
            _e('Invalid Wingu API Key.', Wingu::name());
        }
        ?>
        <form method="POST" action="options.php">
            <?php
            settings_fields('wingu-options');
            do_settings_sections('wingu-options');
            submit_button();
            ?>
        </form>
        <h2>Triggers</h2>
        <ol>
        <?php
        try {
            $response = $winguChannelApi->myChannels();
            $response->current();
            $i = 0;
            while ($response->valid() && $i < 7) {
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
                echo '<li>' . $channelname . ': ' . $channeltype . '</li>';
                $response->next();
                $i++;
            }
            update_option(Wingu::GLOBAL_KEY_API_IS_VALID, 'true');
        } catch (Unauthorized $exception) {
            update_option(Wingu::GLOBAL_KEY_API_IS_VALID, 'false');
        }
        echo '</ol>';
//        http://wingu/api/doc#/operations/Analytics/get_api_analytics__resource_type___resource_id___interaction___aggregation__monthly
    }

    public function wingu_settings_link($links) : array
    {
        $links[] = '<a href = "' . esc_url(get_admin_url(null, 'options-general.php?page=wingu-options')) . '" >' . __('Settings') . '</a >';
        return $links;
    }

    public function add_wingu_post_meta_box($post) : void
    {
        $winguChannelApi = Wingu::$API->channel();

        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (get_option(Wingu::GLOBAL_KEY_API_IS_VALID) === 'false') {
            _e('Invalid Wingu API Key.', Wingu::name());
            return;
        }

        wp_nonce_field(plugin_basename(__FILE__), 'wingu_nonce');

        echo '<p>' . _e('Display') . '</p>';
        $displayPreference = $this->compareValues($post->ID, Wingu::POST_KEY_DISPLAY_PREFERENCE, Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
        $linkBack          = $this->compareValues($post->ID, Wingu::POST_KEY_LINK_BACK, Wingu::GLOBAL_KEY_LINK_BACK);
        ?>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference" value="content" <?php checked('content',
        $displayPreference, true); ?>>Content</label>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference" value="excerpt" <?php checked('excerpt',
        $displayPreference, true); ?>>Excerpt</label>
        <br>
        <label for="wingu_post_link_back"><input type="checkbox" name="wingu_post_link_back" value="true" <?php checked('true',
        $linkBack, true); ?>>Link back</label>
        <br>
		<select id="wingu_post_triggers" name="wingu_post_triggers[]" multiple>
            <?php
            try {
                $response = $winguChannelApi->myChannels();
                $response->current();
                $i                = 0;
                $current_triggers = get_post_meta($post->ID, Wingu::POST_KEY_TRIGGERS, true);
                while ($response->valid() && $i < 7) {
                    $channelid   = $response->current()->id();
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
                    echo '<option value="' . $channelid . '" ' . (\in_array($channelid, (array) $current_triggers,
                            true) ? 'selected' : '') . '>' . $channelname . ': ' . $channeltype . '</option><br>';
                    $response->next();
                    $i++;
                }
                update_option(Wingu::GLOBAL_KEY_API_IS_VALID, 'true');
            } catch (Unauthorized $exception) {
                update_option(Wingu::GLOBAL_KEY_API_IS_VALID, 'false');
            }
            ?>
        </select>
        <p><a href="<?php echo esc_url(get_admin_url() . 'options-general.php?page=wingu-options') ?>" target="_blank"><?php _e('Go to plugin options', Wingu::name()) ?></a></p>
        <?php
    }

    public function wingu_save_post_meta($post_id) : void
    {
        if (! isset($_POST['wingu_nonce']) || ! wp_verify_nonce($_POST['wingu_nonce'], plugin_basename(__FILE__))) {
            return;
        }

        if (! current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $new_val_disp     = $_POST['wingu_post_display_preference'];
        $new_val_link     = $_POST['wingu_post_link_back'] ?? 'false';
        $new_val_triggers = $_POST['wingu_post_triggers'] ?? [];

        $val_disp     = get_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, true);
        $val_link     = get_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, true);
        $val_triggers = get_post_meta($post_id, Wingu::POST_KEY_TRIGGERS, true);

        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE)) {
            add_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_val_disp, true);
        } elseif ($new_val_disp !== $val_disp) {
            update_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_val_disp);
        }
        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_LINK_BACK)) {
            add_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_val_link, true);
        } elseif ($new_val_link !== $val_link) {
            update_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_val_link);
        }
        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_TRIGGERS)) {
            add_post_meta($post_id, Wingu::POST_KEY_TRIGGERS, $new_val_triggers, true);
        } elseif ($new_val_triggers !== $val_triggers) {
            update_post_meta($post_id, Wingu::POST_KEY_TRIGGERS, $new_val_triggers);
        }
    }

    public function wingu_meta_box() : void
    {
        add_meta_box(
            'wingu_post_metabox', __('Wingu', Wingu::name()), [$this, 'add_wingu_post_meta_box'], ['post', 'page']
        );
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
            Wingu::GLOBAL_KEY_API_KEY,
            'API Key',
            [$this, 'wingu_settings_api_key'],
            'wingu-options',
            'wingu_settings_section',
            [Wingu::GLOBAL_KEY_API_KEY]
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE,
            'Display',
            [$this, 'wingu_settings_display_preference'],
            'wingu-options',
            'wingu_settings_section',
            [Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE]
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK,
            'Link back',
            [$this, 'wingu_settings_link_back'],
            'wingu-options',
            'wingu_settings_section',
            [Wingu::GLOBAL_KEY_LINK_BACK]
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK_TEXT,
            'Link back text',
            [$this, 'wingu_settings_link_back_text'],
            'wingu-options',
            'wingu_settings_section',
            [Wingu::GLOBAL_KEY_LINK_BACK_TEXT]
        );

        register_setting('wingu-options', Wingu::GLOBAL_KEY_API_KEY);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_LINK_BACK);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_LINK_BACK_TEXT);
    }

    public function wingu_settings_section() : void
    {
        echo '<p>' . __('Enter your API Key. Choose whether you want to connect to triggers whole posts or only excerpts. Select if links back to your site should be added at the end of your texts.') . '</p>';
    }

    public function wingu_settings_api_key($name) : void
    {
        $value = get_option($name);
        echo "<input type='text' size='50' maxlength='36' name='{$name}' value=" . ($value !== null ? esc_attr($value) : '') . '>';
    }

    public function wingu_settings_display_preference($name) : void
    {
        $value = get_option($name);
        echo
        "<label>
         <input type='radio' name='{$name}' value='content' " . checked('content',
                $value, true) . '>' . __('Content', Wingu::name()) .
        "</label>
         <label>
         <input type='radio' name='{$name}' value='excerpt' " . checked('excerpt',
                $value, true) . '>' . __('Excerpt', Wingu::name()) .
        '</label>';
    }

    public function wingu_settings_link_back($name) : void
    {
        $value = get_option($name);
        echo "<input type='checkbox' id='{$name}' name='{$name}' value='true'" . checked('true', $value, true) . '>';
    }

    public function wingu_settings_link_back_text($name) : void
    {
        $value = get_option($name);
        echo "<textarea id='{$name}' name='{$name}' rows='5' cols='50'>{$value}</textarea>";
    }

    private function compareValues($postId, $postMetaKey, $globalKey)
    {
        $dispGlobalSetting = get_option($globalKey);
        $dispPostSpecific  = get_post_meta($postId, $postMetaKey, true);
        return $dispPostSpecific ?: $dispGlobalSetting;
    }

    public function wingu_custom_posts_column($column, $postId) : void
    {
        if ($column === 'wingu') {
            $triggers = get_post_meta($postId, Wingu::POST_KEY_TRIGGERS, true);
            echo '<input type="checkbox" disabled', (!empty($triggers) ? ' checked' : ''), '/>';
        }
    }

    public function add_wingu_posts_column($columns) : array
    {
        return array_merge($columns,
            ['wingu' => __('Wingu Triggers')]);
    }

    public function api_key_notice(): void {
        if (get_option(Wingu::GLOBAL_KEY_API_IS_VALID) !== 'true') {
            echo '<div class="notice notice-error"><p>' . __('The Wingu API Key is incorrect. Enter valid key') . ' <a href="' . esc_url(get_admin_url() . 'options-general.php?page=wingu-options') .'" target="_blank">'. __('here') . '</a></div>';
        }
    }

    public function wingu_post_updated($postId, $updatedPost): void {
        $winguApi = Wingu::$API;
        $linkback = $_POST['wingu_post_link_back'] ?? 'false';
        $dispPref = $_POST['wingu_post_display_preference'];
        $text     = null;
        if ($dispPref === 'content') {
            $text = $updatedPost->post_content;
        } elseif ($dispPref === 'excerpt') {
            $text = $updatedPost->post_excerpt;
        }

        if ($linkback === 'true') {
            $text .= get_option(Wingu::GLOBAL_KEY_LINK_BACK_TEXT);
        }
        if(!isset($_POST['wingu_post_triggers']) || $text === null) {
            return;
        }

        $createdComponent = $winguApi->component()->createCmsComponent(new CMS($text, 'html'));
        $createdDeck      = $winguApi->deck()->createDeck(new \Wingu\Engine\SDK\Model\Request\Deck\Deck($updatedPost->title,
            null, null));
        $template         = $winguApi->contentTemplate()->templates()->current()->id();
        $createdContent   = $winguApi->content()->createContent(new \Wingu\Engine\SDK\Model\Request\Content\PrivateContent($template));
        $winguApi->card()->addCardToDeck(new RequestCard($createdDeck->id(), $createdComponent->id(), 0));
        $winguApi->content()->attachMyContentToChannelsExclusively($createdContent->id(),
            new PrivateContentChannels($_POST['wingu_post_triggers']));
    }
}