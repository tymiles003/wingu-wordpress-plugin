<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

use Wingu\Engine\SDK\Api\Channel\ChannelApi;

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
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $winguApi = new ChannelApi(Wingu::$configuration, Wingu::$httpClient, Wingu::$messageFactory, Wingu::$hydrator);

        echo '<div class="wrap">';
        echo '<h2>API KEY</h2>';
        echo '<p>You have to enter Wingu API KEY here.</p>';
        echo '<h2>Preferences</h2>';
        echo '<h3>Display</h3>';
        echo '<p><label><input type="radio" name="display_preference" value="content" />Content</label></p>';
        echo '<p><label><input type="radio" name="display_preference" value="excerpt" />Excerpt</label></p>';
        echo '<h3>Link back</h3>';
        echo '<p><label><input type="radio" name="link_back" value="yes" />Yes</label></p>';
        echo '<p><label><input type="radio" name="link_back" value="no" />No</label></p>';
        echo '<h2>Triggers</h2>';
        echo $winguApi->myChannels()->current();
        echo '</div>';
    }
}