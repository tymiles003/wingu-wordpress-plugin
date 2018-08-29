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
    }
}