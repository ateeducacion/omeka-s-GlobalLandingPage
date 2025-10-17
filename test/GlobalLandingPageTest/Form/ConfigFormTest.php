<?php declare(strict_types=1);

namespace GlobalLandingPageTest\Form;

use GlobalLandingPage\Form\ConfigForm;
use Laminas\Form\Element\Checkbox;
use PHPUnit\Framework\TestCase;

class ConfigFormTest extends TestCase
{
    public function testFormRegistersCheckbox(): void
    {
        $form = new ConfigForm();
        $form->init();

        $this->assertTrue($form->has('globallandingpage_use_custom'));
        $element = $form->get('globallandingpage_use_custom');
        $this->assertInstanceOf(Checkbox::class, $element);
        $this->assertTrue($element->useHiddenElement());
        $this->assertSame('1', $element->getCheckedValue());
        $this->assertSame('0', $element->getUncheckedValue());
    }
}
