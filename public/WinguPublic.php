<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguPublic
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

    public function enqueue_styles() : void
    {
    }

    public function enqueue_scripts() : void
    {
    }

    public function name() : string
    {
        return $this->name;
    }

    public function version() : string
    {
        return $this->version;
    }
}
