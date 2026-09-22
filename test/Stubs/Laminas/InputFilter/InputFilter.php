<?php
declare(strict_types=1);

namespace Laminas\InputFilter;

class InputFilter
{
    public $inputs = [];
    public function add($input, $name = null)
    {
        $this->inputs[$name ?? $input['name']] = $input;
    }
}
