<?php
declare(strict_types=1);

namespace Laminas\View\Resolver;

class TemplatePathStack
{
    private $paths = [];
    public function getPaths()
    {
        return $this->paths;
    }
    public function setPaths($paths)
    {
        $this->paths = $paths;
    }
    public function addPath($path)
    {
        $this->paths[] = $path;
    }
}
