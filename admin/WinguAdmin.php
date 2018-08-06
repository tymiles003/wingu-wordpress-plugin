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
    private const GEOFENCE = 'Geofence';
    private const QRCODE = 'QR Code';
    private const NFC = 'NFC';
    private const BEACON = 'Beacon';

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
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
        wp_enqueue_style('jquery-ui','https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
    }

    public function enqueue_scripts() : void
    {
        wp_enqueue_script('ajax-apikey-checker', plugins_url(Wingu::name() . '/admin/js/checkApiKey.js'), ['jquery', 'jquery-ui-tabs']);
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', ['jquery'] );
        wp_register_script('wingu-select2', plugins_url(Wingu::name() . '/admin/js/winguSelect2.js'), [ 'jquery', 'select2' ]);
        $wingu_variables = [
                'api_url' => Wingu::$API_URL,
                ];
        wp_localize_script('wingu-select2', 'wingu', $wingu_variables);
        wp_enqueue_script('wingu-select2');
    }

    public function check_api_key() : void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $old = get_option(Wingu::GLOBAL_KEY_API_KEY);
        update_option(Wingu::GLOBAL_KEY_API_KEY, $_GET['apikey']);
        Wingu::refreshApiKey();

        try {
            Wingu::$API->wingu()->ping();
            update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'true');
        } catch (Unauthorized $exception) {
            echo 'Invalid';
            update_option(Wingu::GLOBAL_KEY_API_KEY, $old);
            Wingu::refreshApiKey();
        }

        wp_die();
    }

    public function wingu_options() : void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $activeTab = $_GET['tab'] ?? 'settings';

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=wingu-options&tab=settings" id="settings_tab" class="nav-tab' . $activeTab === 'settings' ? 'nav-tab-active' : '' . '">' . __('Settings', Wingu::name()) . '</a>';
        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) === 'true') {
            echo '<a href="?page=wingu-options&tab=triggers" id="triggers_tab" class="nav-tab' . $activeTab === 'triggers' ? 'nav-tab-active' : '' . '">' . __('Triggers', Wingu::name()) . '</a>';
            echo '<a href="?page=wingu-options&tab=link" id="link_tab" class="nav-tab' . $activeTab === 'link' ? 'nav-tab-active' : '' . '">' . __('Link Content', Wingu::name()) . '</a>';
        }
        echo '</h2>';

        if ($activeTab === 'settings') {
            echo '<form method="POST" action="options.php">';
            settings_fields('wingu-options');
            do_settings_sections('wingu-options');
            submit_button();
            echo '</form>';
        }
        elseif ($activeTab === 'triggers') {
            echo '<h2>Triggers</h2><ol>';
            try {
                $test = new WinguListTable();
                $test->prepare_items();
                echo '<form id="triggers-filter" method="get">
            <input type="hidden" name="page" value="' . $_REQUEST['page'] . '" />
            <input type="hidden" name="tab" value="triggers" />';
                $test->search_box(__('Search', Wingu::name()), 'search');
                $test->display();
                echo '</form>';
            } catch (Unauthorized $exception) {
                update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
                update_option(Wingu::GLOBAL_KEY_API_KEY, '');
            }
            echo '</ol>';
//        http://wingu/api/doc#/operations/Analytics/get_api_analytics__resource_type___resource_id___interaction___aggregation__monthly
        } elseif ($activeTab === 'link') {
            echo '<h2>' . __('Link Content to Triggers', Wingu::name()) . '</h2>';
//            todo: linking/unlinking
        }
    }

    public function wingu_settings_link($links) : array
    {
        $links[] = '<a href = "' . esc_url(get_admin_url(null, 'options-general.php?page=wingu-options')) . '" >' . __('Settings', Wingu::name()) . '</a >';
        return $links;
    }

    public function add_wingu_post_meta_box($post) : void
    {
        $winguChannelApi = Wingu::$API->channel();
        $winguContentApi = Wingu::$API->content();

        if (! current_user_can('edit_others_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) === 'false') {
            _e('Invalid Wingu API Key.', Wingu::name());
            return;
        }

        wp_nonce_field(plugin_basename(__FILE__), 'wingu_nonce');

        $displayPreference = $this->compareValues($post->ID, Wingu::POST_KEY_DISPLAY_PREFERENCE, Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
        $linkBack          = $this->compareValues($post->ID, Wingu::POST_KEY_LINK_BACK, Wingu::GLOBAL_KEY_LINK_BACK);
        ?>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference" value="content"
        <?php checked('content', $displayPreference, true); ?>><?php _e('Content', Wingu::name()); ?></label>
        <label for="wingu_post_display_preference"><input type="radio" name="wingu_post_display_preference" value="excerpt"
        <?php checked('excerpt', $displayPreference, true); ?>><?php _e('Excerpt', Wingu::name()); ?></label>
        <br>
        <label for="wingu_post_link_back"><input type="checkbox" name="wingu_post_link_back" value="true"
        <?php checked('true', $linkBack, true); ?>><?php _e('Link back', Wingu::name()); ?></label>
        <br /><br />
        <select id="wingu_post_choice" name="wingu_post_choice">
        <option value="do-nothing" selected><?php _e('Do nothing', Wingu::name()) ?></option>
        <option value="update-component"><?php _e('Update content from Wordpress', Wingu::name()); ?></option>
        <option value="new-content"><?php _e('Create new Content and link to Trigger', Wingu::name()); ?></option>
        <option value="existing-content"><?php _e('Add to existing Content', Wingu::name()); ?></option>
        </select>
        <div>
		<select id="wingu_post_triggers" name="wingu_post_triggers[]" multiple>
            <?php
            try {
                $response = $winguChannelApi->myChannels();
                $response->current();
//                $current_content = get_post_meta($post->ID, Wingu::POST_KEY_CONTENT, true);
                $all_triggers = [];
                while ($response->valid()) {
                    $id   = $response->current()->id();
                    $name = $response->current()->name();
                    $contentid = $response->current()->content()->id();
                    switch (\get_class($response->current())) {
                        case PrivateGeofence::class:
                            $all_triggers[self::GEOFENCE][] = new WinguTrigger($id, $name, $contentid);
                            break;
                        case PrivateQrCode::class:
                            $all_triggers[self::QRCODE][] = new WinguTrigger($id, $name, $contentid);
                            break;
                        case PrivateNfc::class:
                            $all_triggers[self::NFC][] = new WinguTrigger($id, $name, $contentid);
                            break;
                        case PrivateBeacon::class:
                            $all_triggers[self::BEACON][] = new WinguTrigger($id, $name, $contentid);
                            break;
                    }
                    $response->next();
                }
                ?>
                <?php foreach ($all_triggers as $type=>$triggers): ?>
                    <optgroup label="<?php echo $type; ?>">
                       <?php foreach($triggers as $trigger): ?>
                        <option value="<?php echo $trigger->id() ?>"><?php echo $trigger->name(); ?></option>
<!--                        if ($trigger->content() === $current_content) { echo 'selected'; }-->
                         <?php endforeach; ?>
                    </optgroup>
            <?php endforeach; ?>
            <?php
            } catch (Unauthorized $exception) {
                update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
                update_option(Wingu::GLOBAL_KEY_API_KEY, '');
            }
            ?>
        </select>
        </div>
        <div>
        <select id="wingu_post_content" name="wingu_post_content">
            <?php
            try {
                $response = $winguContentApi->myContents();
                $current_content = get_post_meta($post->ID, Wingu::POST_KEY_CONTENT, true);
                while ($response->valid()) {
                    $current = $response->current();
                        if ($current->packs()[0] !== null) {
                            $deckId = $current->packs()[0]->deck()->id();
                           $deckTitle = $current->packs()[0]->deck()->title();
                        } else {
                            $deckId = __('No ID', Wingu::name());
                            $deckTitle = __('No title', Wingu::name());
                        }
                        echo '<option value="' . $deckId . '" ' . (($current_content === $current->id()) ? 'selected' : '') . '>' . $deckTitle . '</option>';
                        $response->next();
                }
            } catch (Unauthorized $exception) {
                update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
                update_option(Wingu::GLOBAL_KEY_API_KEY, '');
            }
            ?>
        </select>
        </div>
        <br/>
        <a href="<?php echo esc_url(get_admin_url() . 'options-general.php?page=wingu-options') ?>" target="_blank"><?php _e('Go to plugin options', Wingu::name()) ?></a>
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

        $new_disp     = $_POST['wingu_post_display_preference'];
        $new_linkback = $_POST['wingu_post_link_back'] ?? 'false';
        $new_content  = $_POST['wingu_post_content'] ?? '';

        $disp     = get_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, true);
        $linkback = get_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, true);
        $content  = get_post_meta($post_id, Wingu::POST_KEY_CONTENT, true);

        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE)) {
            add_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_disp, true);
        } elseif ($new_disp !== $disp) {
            update_post_meta($post_id, Wingu::POST_KEY_DISPLAY_PREFERENCE, $new_disp);
        }
        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_LINK_BACK)) {
            add_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_linkback, true);
        } elseif ($new_linkback !== $linkback) {
            update_post_meta($post_id, Wingu::POST_KEY_LINK_BACK, $new_linkback);
        }
        if (!metadata_exists('post', $post_id, Wingu::POST_KEY_CONTENT)) {
            add_post_meta($post_id, Wingu::POST_KEY_CONTENT, $new_content, true);
        } elseif ($new_content !== $content) {
            update_post_meta($post_id, Wingu::POST_KEY_CONTENT, $new_content);
        }
    }

    public function wingu_meta_box() : void
    {
        add_meta_box(
            'wingu_post_metabox', 'Wingu', [$this, 'add_wingu_post_meta_box'], ['post', 'page'], 'advanced'
        );
    }

    public function wingu_settings_init() : void
    {
        add_settings_section(
            'wingu_settings_section',
            __('Wingu Settings', Wingu::name()),
            [$this, 'wingu_settings_section'],
            'wingu-options'
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_API_KEY,
            __('API Key', Wingu::name()),
            [$this, 'wingu_settings_api_key'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_API_KEY
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE,
            __('Display', Wingu::name()),
            [$this, 'wingu_settings_display_preference'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK,
            __('Link back', Wingu::name()),
            [$this, 'wingu_settings_link_back'],
            'wingu-options',
            'wingu_settings_section',
            Wingu::GLOBAL_KEY_LINK_BACK
        );

        add_settings_field(
            Wingu::GLOBAL_KEY_LINK_BACK_TEXT,
            __('Link back text', Wingu::name()),
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
        echo '<p>' . __('Enter your API Key. Choose whether you want to connect to triggers whole posts or only excerpts. Select if links back to your site should be added at the end of your texts.') . '</p>';
    }

    public function wingu_settings_api_key($name) : void
    {
        $value = get_option($name);
        echo "<input type='text' size='50' maxlength='36' id='{$name}' name='{$name}' value=" . ($value !== null ? esc_attr($value) : '') . '>';
        echo "&nbsp;<input type='button' class='button button-secondary' id='api_key_checker' value='" . __('Validate', Wingu::name()) . "'>";
    }

    public function wingu_settings_display_preference($name) : void
    {
        $value = get_option($name);
        echo
        "<label>
         <input type='radio' name='{$name}' value='content' " . checked('content', $value, false) . '>' . __('Content', Wingu::name()) .
        "</label>
         <label>
         <input type='radio' name='{$name}' value='excerpt' " . checked('excerpt', $value, false) . '>' . __('Excerpt', Wingu::name()) .
        '</label>';
    }

    public function wingu_settings_link_back($name) : void
    {
        $value = get_option($name);
        echo "<input type='checkbox' id='{$name}' name='{$name}' value='true' " . checked('true', $value, false) . '>';
    }

    public function wingu_settings_link_back_text($name) : void
    {
        $value = get_option($name);

        echo "<textarea id='{$name}' name='{$name}' rows='5' cols='50'>{$value}</textarea>";
         _e('Existing options include', Wingu::name());
        echo ' {AUTHOR}, {DATE}, {TITLE}, {DATE_MODIFIED}, {TYPE}, {COMMENT_COUNT}';
//      todo: change placement of help message above
    }

    public function wingu_custom_posts_column($column, $postId) : void
    {
        if ($column === 'wingu') {
            $content = get_post_meta($postId, Wingu::POST_KEY_CONTENT, true);
            if ($content !== '') {
//              todo: change Wingu icon
                echo '<img height="40" width="40" src="' . plugins_url(Wingu::name() . '/admin/wingu.jpg') . '">';
            }
        }
    }

    public function add_wingu_posts_column($columns) : array
    {
        return array_merge($columns,
            ['wingu' => __('Wingu Triggers')]);
    }

    public function wingu_api_key_notice(): void {
        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) !== 'true') {
            echo '<div class="notice notice-error"><p>' . __('The Wingu API Key is incorrect. Enter valid key') . ' <a href="' . esc_url(get_admin_url() . 'options-general.php?page=wingu-options') .'" target="_blank">'. __('here') . '</a></div>';
        }
    }

    public function wingu_post_updated($postId, $updatedPost): void {
        if (!isset($_POST['wingu_post_choice']) || $_POST['wingu_post_choice'] === 'do-nothing') {
            return;
        }

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
            $linkbackText = get_option(Wingu::GLOBAL_KEY_LINK_BACK_TEXT);

            $variables = [
                    'author' => $updatedPost->post_author,
                    'date' => $updatedPost->post_date_gmt,
                    'title' => $updatedPost->post_title,
                    'date_modified' => $updatedPost->post_modified_gmt,
                    'type' => $updatedPost->post_type,
                    'comment_count' => $updatedPost->comment_count
                    ];

            foreach($variables as $key => $value){
                $linkbackText = str_replace('{'.strtoupper($key).'}', $value, $linkbackText);
            }

            $text .= $linkbackText;
        }

//        todo: verify that logic is always correct
        if ($_POST['wingu_post_choice'] === 'update-component') {
            $componentId = get_post_meta($postId, Wingu::POST_KEY_COMPONENT, true);
            if ($componentId !== false) {
                $winguApi->component()->updateCmsComponent($componentId, new CMS($text, 'html'));
            } else {
                return;
            }
        } elseif ($_POST['wingu_post_choice'] === 'new-content') {
            $createdComponent = get_post_meta($postId, Wingu::POST_KEY_COMPONENT, true);
            if ($createdComponent === false) {
                $createdComponent = $winguApi->component()->createCmsComponent(new CMS($text, 'html'));
                update_post_meta($postId, Wingu::POST_KEY_COMPONENT, $createdComponent->id());
            }

            $createdDeck      = $winguApi->deck()->createDeck(new \Wingu\Engine\SDK\Model\Request\Deck\Deck($updatedPost->post_title, null, null));
            $template         = $winguApi->contentTemplate()->templates()->current()->id();
            $createdContent   = $winguApi->content()->createContent(new \Wingu\Engine\SDK\Model\Request\Content\PrivateContent($template));
            update_post_meta($postId, Wingu::POST_KEY_CONTENT, $createdContent->id());

            $winguApi->card()->addCardToDeck(new RequestCard($createdDeck->id(), $createdComponent->id(), 0));
            $winguApi->content()->attachMyContentToChannelsExclusively($createdContent->id(), new PrivateContentChannels($_POST['wingu_post_triggers']));
        } elseif ($_POST['wingu_post_choice'] === 'existing-content') {
            $createdComponent = get_post_meta($postId, Wingu::POST_KEY_COMPONENT, true);
            if ($createdComponent === false) {
                $createdComponent = $winguApi->component()->createCmsComponent(new CMS($text, 'html'));
                update_post_meta($postId, Wingu::POST_KEY_COMPONENT, $createdComponent->id());
            }

            $winguApi->card()->addCardToDeck(new RequestCard($_POST['wingu_post_content'], $createdComponent->id(), 0));
        }
    }

    public function wingu_portal_unlink_triggers(): void {
        if(!isset($_POST['unlinked_triggers'])) {
          return;
        }

        $input = file_get_contents('php://input');
        $json  = json_decode($input);

        if($json->id) {
        }
    }

    public function _ajax_fetch_wingu_triggers_callback() : void
    {
        $wingu_triggers_table = new WinguListTable();
        $wingu_triggers_table->ajax_response();
    }

    public function ajax_trigger_pagination_script() : void
    {
        echo '<script>';
        include __DIR__ . '/js/ajaxPagination.js';
        echo '</script>';
    }

    private function compareValues($postId, $postMetaKey, $globalKey)
    {
        $dispGlobalSetting = get_option($globalKey);
        $dispPostSpecific  = get_post_meta($postId, $postMetaKey, true);
        return $dispPostSpecific ?: $dispGlobalSetting;
    }

    public function name(): string
    {
        return $this->name;
    }

    public  function version(): string
    {
        return $this->version;
    }
}