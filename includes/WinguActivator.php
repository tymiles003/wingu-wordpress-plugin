<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguActivator
{

    public static function activate() : void
    {
        //flush rewrite rules. Just to make sure our rewrite rules from an earlier activation are applied again!
        flush_rewrite_rules();
        //would want to use flush_rewrite_rules only but that does not work for some reason??
        delete_option('rewrite_rules');

        //Find if there's already some post types enabled
        $apikey = get_option('wingu_api_key');
        //If option does not exist or is empty. Show a message to help them along
//        if (! $apikey) {
//
//            $wingu     = new Wingu();
//            $wingu_admin = new WinguAdmin($wingu->get_Wingu(), $wingu->get_version());

//            $message = sprintf(wp_kses(__('Beautiful Taxonomy Filters needs some <a href="%s">basic setup</a>.',
//                'beautiful-taxonomy-filters'), ['a' => ['href' => []]]),
//                esc_url(admin_url() . 'options-general.php?page=taxonomy-filters&tab=basic'));

//            $btf_admin->add_admin_notice($message);

//        }
    }
}