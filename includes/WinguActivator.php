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

        if (!get_option(Wingu::GLOBAL_KEY_API_IS_VALID)) {
            update_option(Wingu::GLOBAL_KEY_API_IS_VALID, false);
        }
    }
}