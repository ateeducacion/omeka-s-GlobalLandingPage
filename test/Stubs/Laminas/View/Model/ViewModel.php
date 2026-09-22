<?php
declare(strict_types=1);

namespace Laminas\View\Model;

class ViewModel
{
    private $variables;
    private $template;
    public function __construct(array $variables = [])
    {
        $this->variables = $variables;
    }
    public function setTemplate($template)
    {
        $this->template = $template;
    }
    public function getTemplate()
    {
        return $this->template;
    }
    public function getVariables()
    {
        return $this->variables;
    }
}
