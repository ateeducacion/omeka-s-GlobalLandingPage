<?php
declare(strict_types=1);

namespace GlobalLandingPage\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'globallandingpage_use_custom',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Use custom landing page', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'id' => 'globallandingpage_use_custom',
            ],
        ]);
    }
}
