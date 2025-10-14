<?php
declare(strict_types=1);

namespace GlobalLandingPage\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Select;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'globallandingpage_override_enabled',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Enable landing page override', // @translate
                'info' => 'Use the selected theme to render the global landing page.', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'value' => '0',
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_theme',
            'type' => Select::class,
            'options' => [
                'label' => 'Theme', // @translate
                'info' => 'Only themes that include view/omeka/index/index.phtml appear here.', // @translate
                'empty_option' => 'Select a theme...',
            ],
            'attributes' => [
                'class' => 'chosen-select',
            ],
        ]);
    }

    /**
     * Inject the available theme options.
     *
     * @param array<string, string> $options
     */
    public function setThemeOptions(array $options): void
    {
        if (!$this->has('globallandingpage_theme')) {
            return;
        }

        /** @var Select $element */
        $element = $this->get('globallandingpage_theme');
        $element->setValueOptions($options);

        if (empty($options)) {
            $element->setAttribute('disabled', true);
            $element->setEmptyOption('No compatible themes available');
            return;
        }

        $element->setAttribute('disabled', false);
    }
}
