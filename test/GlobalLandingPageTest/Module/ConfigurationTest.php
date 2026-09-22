<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\Module;

use GlobalLandingPage\Module;
use GlobalLandingPageTest\Support\Fixtures;
use Omeka\Api\Manager;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    use Fixtures;

    protected function tearDown(): void
    {
        \Laminas\Form\Form::$valid = true;
    }

    private function api()
    {
        $api = $this->methods(['search', 'read'], Manager::class);
        $site = $this->record(['id' => 7, 'title' => 'Site seven', 'slug' => 'seven']);
        $page = $this->record(['slug' => 'about', 'title' => 'About']);
        $api->method('search')->willReturnCallback(function ($resource, $query) use ($site, $page) {
            return $this->response($resource === 'sites' ? [$site] : [$page]);
        });
        $api->method('read')->willReturnCallback(function ($resource, $id) use ($site) {
            if ($resource === 'sites') {
                return $this->response($site);
            }
            return $this->response(['o:original_url' => '/asset/' . $id, 'o:name' => 'Logo', 'o:alt_text' => 'Alt']);
        });
        return $api;
    }

    /** @dataProvider submissions */
    public function testSaveNormalizesConfigurationAndPurifiesFooter(array $input, array $expected): void
    {
        $settings = $this->settings();
        $purifier = $this->methods(['purify']);
        $purifier->method('purify')->willReturnCallback(function ($html) {
            return strip_tags($html, '<p>');
        });
        $services = $this->services([
            'Omeka\Settings' => $settings, 'Omeka\ApiManager' => $this->api(), 'Omeka\HtmlPurifier' => $purifier,
        ]);
        $module = new Module();
        $module->setServiceLocator($services);
        $messages = $this->methods(['addSuccess']);
        $messages->expects($this->once())->method('addSuccess');
        $controller = $this->record([
            'params' => $this->record(['fromPost' => $input, 'fromFiles' => []]), 'messenger' => $messages,
        ], \Laminas\Mvc\Controller\AbstractController::class);
        $this->assertTrue($module->handleConfigForm($controller));
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $settings->get($key), $key);
        }
    }

    public function submissions(): array
    {
        return [
            'configured' => [[
                Module::SETTING_USE_CUSTOM => ['0', 'YES'], Module::SETTING_FEATURED_SITES => [7, [8], 7, -1],
                Module::SETTING_BASE_SITE => 'seven', Module::SETTING_NAV_PAGES => ['about', ['extra'], '', 5, 'about'],
                Module::SETTING_FOOTER_HTML => '<p>Footer</p><img src=x>', Module::SETTING_PRIMARY_COLOR => ' #ABC ',
                Module::SETTING_SHOW_TOP_BAR => true, Module::SETTING_TOP_BAR_LOGO => ['asset' => ['id' => 2]],
                'globallandingpage_logo_1' => ['o:id' => 2], 'globallandingpage_logo_2' => 2,
                'globallandingpage_logo_3' => ['id' => 3],
            ], [
                Module::SETTING_USE_CUSTOM => true, Module::SETTING_FEATURED_SITES => [7, 8],
                Module::SETTING_BASE_SITE => 'seven',
                Module::SETTING_NAV_PAGES => ['about' => 'About', 'extra' => 'extra'],
                Module::SETTING_FOOTER_HTML => '<p>Footer</p>', Module::SETTING_PRIMARY_COLOR => '#abc',
                Module::SETTING_LOGOS => [2, 3], Module::SETTING_TOP_BAR_LOGO => 2,
            ]],
            'defaults' => [[], [Module::SETTING_USE_CUSTOM => false, Module::SETTING_LOGOS => [],
                Module::SETTING_FEATURED_SITES => [], Module::SETTING_BASE_SITE => '',
                Module::SETTING_NAV_PAGES => []]],
            'numeric site' => [[Module::SETTING_USE_CUSTOM => 1, Module::SETTING_BASE_SITE => 7],
                [Module::SETTING_USE_CUSTOM => true, Module::SETTING_BASE_SITE => 'seven']],
            'invalid settings shapes' => [[
                Module::SETTING_USE_CUSTOM => new \stdClass(), Module::SETTING_FEATURED_SITES => 'invalid',
                Module::SETTING_NAV_PAGES => 'invalid', Module::SETTING_BASE_SITE => -1,
                Module::SETTING_PRIMARY_COLOR => [], Module::SETTING_SECONDARY_COLOR => '',
                Module::SETTING_ACCENT_COLOR => 'invalid', 'globallandingpage_logo_1' => ['id' => -1],
                'globallandingpage_logo_2' => 'invalid',
            ], [Module::SETTING_USE_CUSTOM => false, Module::SETTING_FEATURED_SITES => [],
                Module::SETTING_BASE_SITE => '',
                Module::SETTING_PRIMARY_COLOR => '#e77f11', Module::SETTING_LOGOS => []]],
        ];
    }

    public function testRejectedFormDoesNotSaveSettings(): void
    {
        \Laminas\Form\Form::$valid = false;
        $settings = $this->settings();
        $module = new Module();
        $module->setServiceLocator($this->services([
            'Omeka\Settings' => $settings, 'Omeka\ApiManager' => $this->api(), 'Omeka\HtmlPurifier' => new \stdClass(),
        ]));
        $messages = $this->methods(['addError']);
        $messages->expects($this->once())->method('addError');
        $controller = $this->record([
            'params' => $this->record(['fromPost' => [], 'fromFiles' => []]), 'messenger' => $messages,
        ], \Laminas\Mvc\Controller\AbstractController::class);
        $this->assertFalse($module->handleConfigForm($controller));
        $this->assertSame([], $settings->values);
    }

    /** @dataProvider storedConfiguration */
    public function testConfigFormRestoresSelectionsAndLogoFallback(array $values, bool $defaultLogo): void
    {
        $settings = $this->settings($values);
        $module = new Module();
        $module->setServiceLocator($this->services([
            'Omeka\Settings' => $settings, 'Omeka\ApiManager' => $this->api(),
        ]));
        $assets = $this->methods(['appendStylesheet', 'appendFile']);
        $assets->expects($this->exactly(2))->method('appendStylesheet');
        $assets->expects($this->exactly(2))->method('appendFile');
        $view = $this->methods(
            ['url', 'translate', 'headLink', 'headScript', 'assetUrl', 'render'],
            \Laminas\View\Renderer\PhpRenderer::class
        );
        $view->method('url')->willReturn('/subpath/api/site_pages');
        $view->method('translate')->willReturnArgument(0);
        $view->method('headLink')->willReturn($assets);
        $view->method('headScript')->willReturn($assets);
        $view->method('assetUrl')->willReturnCallback(function ($file) {
            return '/subpath/modules/GlobalLandingPage/asset/' . $file;
        });
        $view->method('render')->willReturnCallback(function ($template, $data) use ($defaultLogo) {
            $this->assertSame('global-landing-page/config-form', $template);
            $this->assertSame($defaultLogo, $data['selectedLogos'][0]['is_default']);
            $this->assertSame('/subpath/api/site_pages', $data['form']->get(Module::SETTING_NAV_PAGES)
                ->getAttribute('data-api-endpoint'));
            return '<form>configured</form>';
        });
        $this->assertSame('<form>configured</form>', $module->getConfigForm($view));
    }

    public function storedConfiguration(): array
    {
        return [
            [[], true],
            [[Module::SETTING_BASE_SITE => 'seven', Module::SETTING_NAV_PAGES => ['about' => 'About'],
                Module::SETTING_LOGOS => [1, 2], Module::SETTING_TOP_BAR_LOGO => ['o:id' => 2]], false],
            [[Module::SETTING_NAV_PAGES => 'invalid', Module::SETTING_LOGOS => 'invalid'], true],
        ];
    }
}
