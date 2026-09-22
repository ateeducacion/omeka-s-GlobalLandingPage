<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\View;

use GlobalLandingPage\View\Helper\ContrastColor;
use GlobalLandingPage\View\Helper\ResourceTags;
use GlobalLandingPage\View\Helper\ShadeColor;
use GlobalLandingPageTest\Support\Fixtures;
use PHPUnit\Framework\TestCase;

class ColorAndTagsTest extends TestCase
{
    use Fixtures;

    public function testColorsChooseReadableContrastAndClampShades(): void
    {
        $contrast = new ContrastColor();
        $this->assertSame('#fff', $contrast('#000', ['#123', '#fff']));
        $this->assertSame('#000', $contrast('#fff', ['#fff', '#000']));
        $this->assertSame('', $contrast('#fff', []));
        $shade = new ShadeColor();
        $this->assertSame('#ffffff', $shade('#ffffff', 50));
        $this->assertSame('#000000', $shade('#000000', -50));
        $this->assertSame('#808080', $shade('#000000', 50));
    }

    /** @dataProvider resourceTypes */
    public function testResourceTagsEscapeLabelsAndRespectConfiguredTypes(string $type, string $label): void
    {
        $view = $this->methods(['themeSetting', 'escapeHtml', 'translate']);
        $view->method('themeSetting')->willReturn(['resource_type', 'resource_class']);
        $view->method('translate')->willReturnArgument(0);
        $view->method('escapeHtml')->willReturnCallback(function ($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        });
        $resource = $this->record([
            'resourceName' => $type, 'resourceClass' => $this->record(['id' => 1]),
            'displayResourceClassLabel' => '<script>unsafe</script>',
        ]);
        $helper = new ResourceTags();
        $helper->setView($view);
        $html = $helper($resource);
        $this->assertStringContainsString($label, $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertSame('', $helper(null));
        $helper->setView($this->record(['themeSetting' => null]));
        $this->assertSame('', $helper($resource));
    }

    public function resourceTypes(): array
    {
        return [['items', 'Item'], ['item_sets', 'Item set'], ['media', 'Media']];
    }
}
