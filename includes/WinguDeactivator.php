<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguDeactivator
{

    public static function deactivate() : void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        if (get_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID) === 'true') {
            update_option(Wingu::GLOBAL_KEY_API_KEY_IS_VALID, false);
            update_option(Wingu::GLOBAL_KEY_API_KEY, '');
        }
    }
}