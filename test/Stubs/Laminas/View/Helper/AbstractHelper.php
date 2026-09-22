<?php
declare(strict_types=1);

namespace Laminas\View\Helper;

abstract class AbstractHelper
{
    private $view;
    public function setView($view)
    {
        $this->view = $view;
    }
    public function getView()
    {
        return $this->view;
    }
}
