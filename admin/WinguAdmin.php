<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Api\Exception\HttpClient\Unauthorized;
use Wingu\Engine\SDK\Model\Request\Card as RequestCard;
use Wingu\Engine\SDK\Model\Request\Channel\Beacon\BeaconLocation;
use Wingu\Engine\SDK\Model\Request\Channel\Beacon\PrivateBeacon as RequestBeacon;
use Wingu\Engine\SDK\Model\Request\Channel\Geofence\PrivateGeofence as RequestGeofence;
use Wingu\Engine\SDK\Model\Request\Channel\Nfc\PrivateNfc as RequestNfc;
use Wingu\Engine\SDK\Model\Request\Channel\QrCode\PrivateQrCode as RequestQrCode;
use Wingu\Engine\SDK\Model\Request\Channel\PrivateChannelsFilter;
use Wingu\Engine\SDK\Model\Request\Component\CMS;
use Wingu\Engine\SDK\Model\Request\Content\Pack as RequestPack;
use Wingu\Engine\SDK\Model\Request\Content\PrivateContentChannels;
use Wingu\Engine\SDK\Model\Request\StringValue;
use Wingu\Engine\SDK\Model\Response\Channel\Beacon\PrivateBeacon;
use Wingu\Engine\SDK\Model\Response\Channel\Geofence\PrivateGeofence;
use Wingu\Engine\SDK\Model\Response\Channel\Nfc\PrivateNfc;
use Wingu\Engine\SDK\Model\Response\Channel\QrCode\PrivateQrCode;
use Wingu\Engine\SDK\Model\Response\Content\PrivateContent;

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
        add_options_page(
            __('options_page_title', Wingu::name()),
            'Wingu',
            'manage_options',
            'wingu-options',
            [$this, 'wingu_options']
        );
    }

    public function enqueue_styles() : void
    {
        wp_enqueue_style('select2', plugins_url(Wingu::name() . '/admin/css/select2.min.css'));
        wp_enqueue_style('jquery-ui', plugins_url(Wingu::name() . '/admin/css/jquery-ui.css'));
    }

    public function enqueue_scripts() : void
    {
        wp_enqueue_script(
            'ajax-apikey-checker',
            plugins_url(Wingu::name() . '/admin/js/wingu-api-key.js'),
            ['jquery', 'jquery-ui-tabs'],
            time()
        );
        wp_enqueue_script(
            'select2',
            plugins_url(Wingu::name() . '/admin/js/select2.min.js'),
            ['jquery'],
            time()
        );
        wp_enqueue_script(
            'wingu-select2',
            plugins_url(Wingu::name() . '/admin/js/wingu-select2.js'),
            ['jquery', 'select2'],
            time()
        );
    }

    public function get_wingu_private_triggers() : void
    {
        try {
            $response = Wingu::$API->channel()->myChannels(new PrivateChannelsFilter(null, $_POST['name']));
            $response->current();
            $all_triggers = [];
            while ($response->valid()) {
                $id        = $response->current()->id();
                $name      = $response->current()->name();
                $contentid = $response->current()->content() ? $response->current()->content()->id() : 'No Content ID';

                switch (\get_class($response->current())) {
                    case PrivateGeofence::class:
                        $name .= ' (Geofence)';
                        break;
                    case PrivateQrCode::class:
                        $name .= ' (QR Code)';
                        break;
                    case PrivateNfc::class:
                        $name .= ' (NFC)';
                        break;
                    case PrivateBeacon::class:
                        $name .= ' (Beacon)';
                        break;
                }
                $all_triggers[] = (object) ['id' => $id, 'text' => $name, 'content' => $contentid];
                $response->next();
            }
            echo \json_encode(['results' => $all_triggers]);
        } catch (\Exception $exception) {
            update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
            update_option(Wingu::GLOBAL_KEY_API_KEY, '');
            _e('generic_error', Wingu::name());
        }

        wp_die();
    }

    public function get_wingu_private_contents() : void
    {
        try {
            $response     = Wingu::$API->content()->myContents();
            $all_contents = [];
            while ($response->valid()) {
                /** @var PrivateContent $current */
                $current = $response->current();
                if ($current->packs()[0] !== null) {
                    $deckId    = $current->packs()[0]->deck()->id();
                    $deckTitle = $current->packs()[0]->deck()->title();
                } else {
                    $deckId    = __('no_id', Wingu::name());
                    $deckTitle = __('no_title', Wingu::name());
                }
                $all_contents[] = (object) ['id' => $deckId, 'text' => $deckTitle];
                $response->next();
            }
            echo \json_encode(['results' => $all_contents]);
        } catch (Unauthorized $exception) {
            update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
            update_option(Wingu::GLOBAL_KEY_API_KEY, '');
            _e('generic_error', Wingu::name());
        }

        wp_die();
    }

    public function check_wingu_api_key() : void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('access_denied', Wingu::name()));
        }

        $oldKey = get_option(Wingu::GLOBAL_KEY_API_KEY);
        update_option(Wingu::GLOBAL_KEY_API_KEY, $_GET['apikey']);
        Wingu::refreshApiKey();

        try {
            Wingu::$API->wingu()->ping();
            update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'true');
        } catch (Unauthorized $exception) {
            echo 'Invalid';
            update_option(Wingu::GLOBAL_KEY_API_KEY, $oldKey);
            Wingu::refreshApiKey();
        }

        wp_die();
    }

    public function wingu_options() : void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('access_denied', Wingu::name()));
        }

        $activeTab = $_GET['tab'] ?? 'settings';

        echo '<h2 class="nav-tab-wrapper">';
        echo "<a href='?page=wingu-options&tab=settings' id='settings_tab' class='nav-tab " . ($activeTab === 'settings' ? 'nav-tab-active' : '') . "'>"
            . __('settings', Wingu::name()) . '</a>';
        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) === 'true') {
            echo "<a href='?page=wingu-options&tab=triggers' id='triggers_tab' class='nav-tab " . ($activeTab === 'triggers' ? 'nav-tab-active' : '') . "'>"
                . __('triggers', Wingu::name()) . '</a>';
            echo "<a href='?page=wingu-options&tab=link' id='link_tab' class='nav-tab " . ($activeTab === 'link' ? 'nav-tab-active' : '') . "'>"
                . __('linking_contents_triggers', Wingu::name()) . '</a>';
        }
        echo '</h2>';

        if ($activeTab === 'settings') {
            echo '<form method="POST" action="options.php">';
            settings_fields('wingu-options');
            do_settings_sections('wingu-options');
            submit_button(__('save', Wingu::name()), 'primary', 'wingu_settings_submit');
            echo '</form>';
        } elseif ($activeTab === 'triggers') {
            echo '<h2>' . __('triggers', Wingu::name()) . '</h2>';
            try {
                $winguTriggerList = new WinguListTable();
                $winguTriggerList->prepare_items();
                echo '<form id="triggers-filter" method="get">
                      <input type="hidden" name="page" value="' . $_REQUEST['page'] . '" />
                      <input type="hidden" name="tab" value="triggers" />';
                $winguTriggerList->search_box(__('search', Wingu::name()), 'search');
                $winguTriggerList->display();
                echo '</form>';
            } catch (Unauthorized $exception) {
                update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
                update_option(Wingu::GLOBAL_KEY_API_KEY, '');
                _e('generic_error', Wingu::name());
            }
        } elseif ($activeTab === 'link') {
            echo '<h2>' . __('linking_contents_triggers', Wingu::name()) . '</h2>';
            if (isset($_REQUEST['action'])) {
                if ($_REQUEST['action'] === 'unlink') {
                    if ($_REQUEST['trigger'] === null || $_REQUEST['content'] === null) {
                        return;
                    }
                    $type = strtolower($_REQUEST['type']);
                    switch ($type) {
                        case 'beacon':
                            Wingu::$API->beacon()->updateMyBeacon(
                                $_REQUEST['trigger'],
                                new RequestBeacon(new BeaconLocation(null, null), new StringValue(null))
                            );
                            break;
                        case 'geofence':
                            Wingu::$API->geofence()->updateMyGeofence(
                                $_REQUEST['trigger'],
                                new RequestGeofence(new StringValue(null))
                            );
                            break;
                        case 'nfc':
                            Wingu::$API->nfc()->updateMyNfc(
                                $_REQUEST['trigger'],
                                new RequestNfc(new StringValue(null))
                            );
                            break;
                        case 'qrcode':
                            Wingu::$API->qrcode()->updateMyQrCode(
                                $_REQUEST['trigger'],
                                new RequestQrCode(new StringValue(null))
                            );
                            break;
                    }

                    $args  = [
                        'meta_key' => Wingu::POST_KEY_CONTENT,
                        'meta_value' => $_REQUEST['contentid'],
                        'post_type' => 'any',
                        'posts_per_page' => -1,
                    ];
                    $posts = get_posts($args);
                    foreach ($posts as $post) {
                        update_post_meta($post->ID, Wingu::POST_KEY_CONTENT, '');
                    }
                    echo __('unlinked_content_named', Wingu::name())
                        . '<strong>' . $_REQUEST['content'] . '</strong> ' . __('unlinked_content_from', Wingu::name())
                        . '<strong>' . $_REQUEST['name'] . '</strong>';
                } elseif ($_REQUEST['action'] === 'link') {
                    if (! isset($_REQUEST['wingu_link_content'])) {
                        ?>
                        <form method="POST">
                            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                            <input type="hidden" name="tab" value="link"/>
                            <table class="form-table">
                                <tbody>
                                <tr>
                                    <th scope="row"><label for="wingu_link_trigger"><?php _e('trigger', Wingu::name()) ?></label>
                                    </th>
                                    <td><select id="wingu_link_trigger" name="wingu_link_trigger">
                                            <option value="<?php echo $_REQUEST['trigger']; ?>" selected><?php echo $_REQUEST['name']; ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wingu_link_content"><?php _e('content', Wingu::name()) ?></label>
                                    </th>
                                    <td><select id="wingu_link_content" name="wingu_link_content">
                                            <?php
                                            $posts = get_posts(['post_type' => ['post', 'page']]);
                                            foreach ($posts as $post) {
                                                echo "<option value='{$post->ID}'>{$post->post_title}</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <?php submit_button(__('submit_link_content_trigger', Wingu::name())); ?>
                        </form>
                        <?php
                    } else {
                        try {
                            $winguApi = Wingu::$API;
                            $dispPref = get_option(Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
                            $linkback = get_option(Wingu::GLOBAL_KEY_LINK_BACK);
                            $post     = get_post($_REQUEST['wingu_link_content']);
                            $text     = $this->parseWordpressContentText($post, $dispPref, $linkback);

                            $createdContent = $this->createNewWinguContent($post, $text);

                            $type = strtolower($_REQUEST['type']);
                            switch ($type) {
                                case 'beacon':
                                    $winguApi->beacon()->updateMyBeacon(
                                        $_REQUEST['wingu_link_trigger'],
                                        new RequestBeacon(new BeaconLocation(null, null), new StringValue($createdContent->id()))
                                    );
                                    break;
                                case 'geofence':
                                    $winguApi->geofence()->updateMyGeofence(
                                        $_REQUEST['wingu_link_trigger'],
                                        new RequestGeofence(new StringValue($createdContent->id()))
                                    );
                                    break;
                                case 'nfc':
                                    $winguApi->nfc()->updateMyNfc(
                                        $_REQUEST['wingu_link_trigger'],
                                        new RequestNfc(new StringValue($createdContent->id()))
                                    );
                                    break;
                                case 'qrcode':
                                    $winguApi->qrcode()->updateMyQrCode(
                                        $_REQUEST['wingu_link_trigger'],
                                        new RequestQrCode(new StringValue($createdContent->id()))
                                    );
                                    break;
                            }

                            echo __('trigger', Wingu::name()) . '<strong>' . $_REQUEST['name'] . '</strong> '
                                . __('linked_to', Wingu::name()) . '<strong>' . $post->post_title . '</strong>';
                        } catch (\Exception $exception) {
                            _e('generic_error', Wingu::name());
                        }

                    }
                }
            } else {
                _e('linking_contents_triggers_description', Wingu::name());
            }
        }
    }

    public function wingu_settings_link($links) : array
    {
        $links[] = '<a href = "' . esc_url(get_admin_url(null, 'options-general.php?page=wingu-options')) . '" >'
            . __('settings', Wingu::name()) . '</a>';

        return $links;
    }

    public function add_wingu_post_meta_box($post) : void
    {
        if (! current_user_can('edit_others_posts')) {
            wp_die(__('access_denied', Wingu::name()));
        }

        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) === 'false') {
            _e('invalid_api_key', Wingu::name());

            return;
        }

        wp_nonce_field(plugin_basename(__FILE__), 'wingu_nonce');

        $displayPreference = $this->compareValues($post->ID, Wingu::POST_KEY_DISPLAY_PREFERENCE,
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
        $linkBack          = $this->compareValues($post->ID, Wingu::POST_KEY_LINK_BACK, Wingu::GLOBAL_KEY_LINK_BACK);
        ?>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference"
                                                          value="content"
                <?php checked('content', $displayPreference, true); ?>><?php _e('content', Wingu::name()); ?></label>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference"
                                                          value="excerpt"
                <?php checked('excerpt', $displayPreference, true); ?>><?php _e('excerpt', Wingu::name()); ?></label>
        <br>
        <label for="wingu_post_link_back"><input type="checkbox" name="wingu_post_link_back" value="true"
                <?php checked('true', $linkBack, true); ?>><?php _e('link_back', Wingu::name()); ?></label>
        <br/><br/>
        <select id="wingu_post_choice" name="wingu_post_choice">
            <option value="do-nothing" selected><?php _e('do_nothing', Wingu::name()) ?></option>
            <option value="update-component"><?php _e('update_content', Wingu::name()); ?></option>
            <option value="new-content"><?php _e('create_new_content', Wingu::name()); ?></option>
            <option value="existing-content"><?php _e('add_to_existing_content', Wingu::name()); ?></option>
        </select>
        <div>
            <select id="wingu_post_triggers" name="wingu_post_triggers[]" multiple></select>
        </div>
        <div>
            <select id="wingu_post_content" name="wingu_post_content"></select>
        </div>
        <br/>
        <a href="<?php echo esc_url(get_admin_url() . 'options-general.php?page=wingu-options') ?>" target="_blank">
            <?php _e('plugin_options_link', Wingu::name()) ?></a>
        <?php
    }

    public function wingu_save_post_meta($post_id) : void
    {
        if (! isset($_POST['wingu_nonce']) || ! wp_verify_nonce($_POST['wingu_nonce'], plugin_basename(__FILE__))) {
            return;
        }

        if (! current_user_can('edit_posts')) {
            wp_die(__('access_denied', Wingu::name()));
        }

        $new_disp     = $_POST['wingu_post_display_preference'];
        $new_linkback = $_POST['wingu_post_link_back'] ?? 'false';
        $new_content  = $_POST['wingu_post_content'] ?? '';

        $disp     = get_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, true);
        $linkback = get_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, true);
        $content  = get_post_meta($post_id, Wingu::POST_KEY_CONTENT, true);

        if (! metadata_exists('post', $post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE)) {
            add_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_disp, true);
        } elseif ($new_disp !== $disp) {
            update_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_disp);
        }
        if (! metadata_exists('post', $post_id, Wingu::POST_KEY_LINK_BACK)) {
            add_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_linkback, true);
        } elseif ($new_linkback !== $linkback) {
            update_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_linkback);
        }
        if (! metadata_exists('post', $post_id, Wingu::POST_KEY_CONTENT)) {
            add_post_meta($post_id, Wingu::POST_KEY_CONTENT, $new_content, true);
        } elseif ($new_content !== $content) {
            update_post_meta($post_id, Wingu::POST_KEY_CONTENT, $new_content);
        }
    }

    public function wingu_meta_box() : void
    {
        add_meta_box(
            'wingu_post_metabox',
            'Wingu',
            [$this, 'add_wingu_post_meta_box'],
            ['post', 'page'],
            'advanced'
        );
    }

    public function wingu_settings_init() : void
    {
        add_settings_section(
            'wingu_settings_section',
            __('settings', Wingu::name()),
            [$this, 'wingu_settings_section'],
            'wingu-options'
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_API_KEY,
            __('api_key', Wingu::name()),
            [$this, 'wingu_settings_api_key'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_API_KEY
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE,
            __('display', Wingu::name()),
            [$this, 'wingu_settings_display_preference'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK,
            __('link_back', Wingu::name()),
            [$this, 'wingu_settings_link_back'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_LINK_BACK
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK_TEXT,
            __('link_back_text', Wingu::name()),
            [$this, 'wingu_settings_link_back_text'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_LINK_BACK_TEXT
        );

        register_setting('wingu-options', Wingu::GLOBAL_KEY_API_KEY);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_LINK_BACK);
        register_setting('wingu-options', Wingu::GLOBAL_KEY_LINK_BACK_TEXT);
    }

    public function wingu_settings_section() : void
    {
        echo '<p>' . __('wingu_settings_description', Wingu::name()) . '</p>';
    }

    public function wingu_settings_api_key($name) : void
    {
        $value = get_option($name);
        echo "<input type='text' size='50' maxlength='36' id='{$name}' name='{$name}' value=" . ($value !== null ? esc_attr($value) : '') . '>';
        echo "&nbsp;<input type='button' class='button button-secondary' id='api_key_checker' value='" . __('validate',
                Wingu::name()) . "'>";
    }

    public function wingu_settings_display_preference($name) : void
    {
        $value = get_option($name);
        echo
            "<label><input type='radio' name='{$name}' value='content' " . checked('content', $value, false) . '>'
            . __('content', Wingu::name())
            . "</label>
             <label>
             <input type='radio' name='{$name}' value='excerpt' " . checked('excerpt', $value, false) . '>'
            . __('excerpt', Wingu::name())
            . '</label>';
    }

    public function wingu_settings_link_back($name) : void
    {
        $value = get_option($name);
        echo "<input type='checkbox' id='{$name}' name='{$name}' value='true' " . checked('true', $value, false) . '>';
    }

    public function wingu_settings_link_back_text($name) : void
    {
        $value = get_option($name);

        echo "<textarea id='{$name}' name='{$name}' rows='5' cols='50'>{$value}</textarea><br>";
        _e('link_back_placeholders', Wingu::name());
        echo ' {PERMALINK}, {AUTHOR}, {DATE}, {TITLE}, {DATE_MODIFIED}, {TYPE}, {COMMENT_COUNT}.<br>';
        _e('case_insensitive_placeholders', Wingu::name());
//      todo: placement of help message above
//      todo: whether placeholders should be english only
    }

    public function wingu_custom_posts_column($column, $postId) : void
    {
        if ($column === 'wingu') {
            $content = get_post_meta($postId, Wingu::POST_KEY_CONTENT, true);
            if ($content !== '') {
                echo '<img src="' . plugins_url(Wingu::name() . '/admin/wingu.png') . '">';
            }
        }
    }

    public function add_wingu_posts_column($columns) : array
    {
        return array_merge(
            $columns,
            ['wingu' => __('triggers', Wingu::name())]
        );
    }

    public function wingu_api_key_notice() : void
    {
        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) !== 'true') {
            global $pagenow;
            $whitelistDisplayNotice = [
                'options-general.php',
                'edit.php',
                'post-new.php',
                'plugins.php',
            ];

            if (\in_array($pagenow, $whitelistDisplayNotice, true)) {
                echo '<div class="notice notice-error"><p>'
                    . __('invalid_api_key', Wingu::name()) . ' '
                    . __('enter_valid_one', Wingu::name())
                    . ' <a href="' . esc_url(get_admin_url() . 'options-general.php?page=wingu-options')
                    . '" target="_blank">' . __('here', Wingu::name()) . '</a></div>';
            }
        }
    }

    public function wingu_post_updated($postId, $updatedPost) : void
    {
        if (! isset($_POST['wingu_post_choice'])) {
            return;
        }

        $winguApi = Wingu::$API;
        $dispPref = $_POST['wingu_post_display_preference'];
        $linkback = $_POST['wingu_post_link_back'] ?? 'false';
        $text = $this->parseWordpressContentText($updatedPost, $dispPref, $linkback);

        switch ($_POST['wingu_post_choice']) {
            case 'update-component':
                $componentId = get_post_meta($postId, Wingu::POST_KEY_COMPONENT, true);
                if ($componentId !== '') {
                    $winguApi->component()->cms()->update($componentId, new CMS\Update($text, 'html'));
                } else {
                    return;
                }
                break;

            case 'new-content':
                $createdContent = $this->createNewWinguContent($updatedPost, $text);
                $winguApi->content()->attachMyContentToChannelsExclusively(
                    $createdContent->id(),
                    new PrivateContentChannels($_POST['wingu_post_triggers']));
                break;

            case 'existing-content':
                $createdComponentId = get_post_meta($postId, Wingu::POST_KEY_COMPONENT, true);
                if ($createdComponentId === '') {
                    $createdComponent   = $winguApi->component()->cms()->create(new CMS\Create($text, 'html'));
                    $createdComponentId = $createdComponent->id();
                    update_post_meta($postId, Wingu::POST_KEY_COMPONENT, $createdComponentId);
                } else {
                    $winguApi->component()->cms()->update($createdComponentId, new CMS\Update($text, 'html'));
                }
                $winguApi->card()->addCardToDeck(new RequestCard($_POST['wingu_post_content'], $createdComponentId, 0));
                break;

            case 'do-nothing':
            default:
                return;
        }

    }

    public function wingu_portal_unlink_triggers() : void
    {
        /** todo: unlinking from portal */
//        if (!isset($_POST['unlinked_triggers'])) {
//            return;
//        }
//
//        $input = file_get_contents('php://input');
//        $json = json_decode($input);
//
//        if ($json->id) {
//        }
    }

    public function _ajax_fetch_wingu_triggers_callback() : void
    {
        $winguTriggersList = new WinguListTable();
        $winguTriggersList->ajax_response();
    }

    public function ajax_wingu_trigger_pagination_script() : void
    {
        echo '<script>';
        include __DIR__ . '/js/wingu-ajax-pagination.js';
        echo '</script>';
    }

    private function compareValues($postId, $postMetaKey, $globalKey)
    {
        $globalSetting = get_option($globalKey);
        $postSpecific  = get_post_meta($postId, $postMetaKey, true);

        return $postSpecific ?: $globalSetting;
    }

    private function parseWordpressContentText($post, $dispPref, $linkback) : string
    {
        $text = null;

        if ($dispPref === 'content') {
            $text = $post->post_content;
        } elseif ($dispPref === 'excerpt') {
            $text = $post->post_excerpt;
        }

        if ($linkback === 'true') {
            $linkbackText = get_option(Wingu::GLOBAL_KEY_LINK_BACK_TEXT);

            $variables = [
                'permalink' => get_permalink($post->ID),
                'author' => get_the_author_meta('display_name', $post->post_author),
                'date' => get_the_date('d/m/Y', $post->ID),
                'title' => $post->post_title,
                'date_modified' => get_the_modified_date('d/m/Y', $post->ID),
                'type' => $post->post_type,
                'comment_count' => $post->comment_count,
            ];

            foreach ($variables as $key => $value) {
                $linkbackText = str_ireplace('{' . $key . '}', $value, $linkbackText);
            }

            $text .= ' ' . $linkbackText;
        }

        return $text;
    }

    private function createNewWinguContent($post, $text) : PrivateContent
    {
        $winguApi = Wingu::$API;

        $createdComponentId = get_post_meta($post->ID, Wingu::POST_KEY_COMPONENT, true);
        if ($createdComponentId === '') {
            $createdComponent   = $winguApi->component()->cms()->create(
                new CMS\Create($text, 'html')
            );
            $createdComponentId = $createdComponent->id();
            update_post_meta($post->ID, Wingu::POST_KEY_COMPONENT, $createdComponentId);
        } else {
            $winguApi->component()->cms()->update($createdComponentId, new CMS\Update($text, 'html'));
        }

        $createdDeck = $winguApi->deck()->createDeck(
            new \Wingu\Engine\SDK\Model\Request\Deck\Create($post->post_title, null, null)
        );
        $template    = $winguApi->contentTemplate()->templates()->current()->id();

        $createdContent = $winguApi->content()->createContent(
            new \Wingu\Engine\SDK\Model\Request\Content\PrivateContent($template)
        );
        update_post_meta($post->ID, Wingu::POST_KEY_CONTENT, $createdContent->id());

        $winguApi->card()->addCardToDeck(
            new RequestCard($createdDeck->id(), $createdComponentId, 0)
        );
        $winguApi->content()->createMyPack(
            new RequestPack\Create(
                $createdContent->id(),
                $createdDeck->id(),
                substr(get_user_locale(), 0, 2)
            )
        );

        return $createdContent;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function version() : string
    {
        return $this->version;
    }
}
