<?php

declare(strict_types=1);

use Wingu\Plugin\Wordpress\Wingu;
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID);
delete_option(Wingu::GLOBAL_KEY_API_KEY);
delete_option(Wingu::GLOBAL_KEY_LINK_BACK);
delete_option(Wingu::GLOBAL_KEY_LINK_BACK_TEXT);
delete_option(Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE);
delete_post_meta_by_key(Wingu::POST_KEY_LINK_BACK);
delete_post_meta_by_key(Wingu::POST_KEY_DISPLAY_PREFERENCE);
delete_post_meta_by_key(Wingu::POST_KEY_CONTENT);
delete_post_meta_by_key(Wingu::POST_KEY_COMPONENT);