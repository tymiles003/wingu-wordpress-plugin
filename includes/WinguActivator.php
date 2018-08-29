<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguActivator
{
    public static function activate() : void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, 'false');
        update_option(Wingu::GLOBAL_KEY_API_KEY, '');

        update_option(Wingu::GLOBAL_KEY_DISPLAY_PREFERENCE, 'content');
        update_option(Wingu::GLOBAL_KEY_LINK_BACK, 'false');
    }
}