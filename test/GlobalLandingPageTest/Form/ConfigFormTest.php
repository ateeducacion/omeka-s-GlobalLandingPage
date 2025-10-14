<?php declare(strict_types=1);

namespace GlobalLandingPageTest\Form;

use GlobalLandingPage\Form\ConfigForm;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Select;
use PHPUnit\Framework\TestCase;

class ConfigFormTest extends TestCase
{
    public function testFormRegistersExpectedElements(): void
    {
        $form = new ConfigForm();
        $form->init();

        $this->assertTrue($form->has('globallandingpage_override_enabled'));
        $this->assertInstanceOf(Checkbox::class, $form->get('globallandingpage_override_enabled'));

        $this->assertTrue($form->has('globallandingpage_theme'));
        $this->assertInstanceOf(Select::class, $form->get('globallandingpage_theme'));
    }

    public function testThemeSelectDisabledWhenNoOptions(): void
    {
        $form = new ConfigForm();
        $form->init();
        $form->setThemeOptions([]);

        /** @var Select $select */
        $select = $form->get('globallandingpage_theme');

        $this->assertTrue((bool)$select->getAttribute('disabled'));
        $this->assertSame([], $select->getValueOptions());
        $this->assertSame('No compatible themes available', $select->getEmptyOption());
    }

    public function testThemeSelectPopulatesOptions(): void
    {
        $form = new ConfigForm();
        $form->init();
        $options = [
            'theme-one' => 'Theme One',
            'theme-two' => 'Theme Two',
        ];
        $form->setThemeOptions($options);

        /** @var Select $select */
        $select = $form->get('globallandingpage_theme');

        $this->assertFalse((bool)$select->getAttribute('disabled'));
        $this->assertSame($options, $select->getValueOptions());
        $this->assertSame('Select a theme...', $select->getEmptyOption());
    }
}
