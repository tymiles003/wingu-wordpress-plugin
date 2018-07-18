<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguTrigger
{
    /** @var string */
    private $id;

    /** @var string */
    private $name;

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }

    public function id() : string
    {
        return $this->id;
    }

    public function name() : string
    {
        return $this->name;
    }
}
