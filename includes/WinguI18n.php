<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguI18n
{
    private $domain;

    public function load_plugin_textdomain() : void
    {
        load_plugin_textdomain(
            $this->domain,
            false,
            \dirname(plugin_basename(__FILE__), 2) . '/languages/'
        );
    }

    public function set_domain($domain) : void
    {
        $this->domain = $domain;
    }
}