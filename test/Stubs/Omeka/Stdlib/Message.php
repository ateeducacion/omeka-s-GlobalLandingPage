<?php
declare(strict_types=1);

namespace Omeka\Stdlib;

class Message
{
    public $message;
    public function __construct($message)
    {
        $this->message = $message;
    }
}
