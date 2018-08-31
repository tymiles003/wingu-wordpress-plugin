<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguI18n
{
    /** @var string */
    private $domain;

    public function loadPluginTextDomain() : void
    {
        load_plugin_textdomain(
            $this->domain,
            false,
            \dirname(plugin_basename(__FILE__), 2) . '/languages/'
        );
    }

    public function setDomain($domain) : void
    {
        $this->domain = $domain;
    }

    public function domain() : string
    {
        return $this->domain;
    }
}