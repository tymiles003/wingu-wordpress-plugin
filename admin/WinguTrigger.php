<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguTrigger
{
    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var string */
    private $content;

    public function __construct($id, $name, $content)
    {
        $this->id = $id;
        $this->name = $name;
        $this->content = $content;
    }

    public function id() : string
    {
        return $this->id;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function content() : string
    {
        return $this->content;
    }
}
