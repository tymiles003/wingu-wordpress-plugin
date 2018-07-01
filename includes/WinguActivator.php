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
        check_admin_referer('activate_plugin_' . Wingu::name());
        flush_rewrite_rules();
        delete_option('rewrite_rules');
        if (!get_option(Wingu::GLOBAL_KEY_API_IS_VALID)) {
            update_option(Wingu::GLOBAL_KEY_API_IS_VALID, false);
        }
//        $apikey = get_option(WINGU::GLOBAL_KEY_API_KEY);
//        if (! $apikey) {
//
//            $wingu     = new Wingu();
//            $wingu_admin = new WinguAdmin($wingu->get_Wingu(), $wingu->get_version());

//            $message = sprintf(wp_kses(__('Wingu plugin needs some <a href="%s">basic setup</a>.',
//                'beautiful-taxonomy-filters'), ['a' => ['href' => []]]),
//                esc_url(admin_url() . 'options-general.php?page=taxonomy-filters&tab=basic'));

//            $btf_admin->add_admin_notice($message);
//        }
    }
}