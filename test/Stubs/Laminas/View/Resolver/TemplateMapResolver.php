<?php
declare(strict_types=1);

namespace Laminas\View\Resolver;

class TemplateMapResolver
{
    private $map = [];
    public function getMap()
    {
        return $this->map;
    }
    public function setMap($map)
    {
        $this->map = $map;
    }
}
