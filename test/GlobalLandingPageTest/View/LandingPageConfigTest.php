<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\View;

use GlobalLandingPage\Module;
use GlobalLandingPage\View\Helper\LandingPageConfig;
use GlobalLandingPageTest\Support\Fixtures;
use PHPUnit\Framework\TestCase;

class LandingPageConfigTest extends TestCase
{
    use Fixtures;

    private function helper(array $settings, $api): LandingPageConfig
    {
        $view = $this->methods(['plugin', 'setting', 'api', 'url', 'assetUrl']);
        $view->method('plugin')->with('translate')->willReturn(function ($text) {
            return $text;
        });
        $view->method('setting')->willReturnCallback(function ($key, $default) use ($settings) {
            return $settings[$key] ?? $default;
        });
        $view->method('api')->willReturn($api);
        $view->method('url')->willReturnCallback(function ($route, $params) {
            return '/subpath/' . $route . '/' . implode('/', $params);
        });
        $view->method('assetUrl')->willReturnCallback(function ($file, $module) {
            return '/subpath/modules/' . $module . '/asset/' . $file;
        });
        $helper = new LandingPageConfig();
        $helper->setView($view);
        return $helper;
    }

    public function testConfiguredSitesDeduplicateSortAndLimitItemsAndCacheConfig(): void
    {
        $items = [];
        foreach (range(1, 11) as $id) {
            $items[$id] = $this->record(['id' => $id, 'created' => new \DateTimeImmutable('2026-01-' . $id)]);
        }
        $api = $this->methods(['read', 'search']);
        $api->method('read')->willReturnCallback(function ($resource, $id) {
            if ($id === 99) {
                throw new \RuntimeException('Deleted site');
            }
            return $this->record(['getContent' => $this->record(['id' => $id === 3 ? 0 : $id])]);
        });
        $api->expects($this->exactly(2))->method('search')
            ->willReturnCallback(function ($resource, $params) use ($items) {
                $this->assertSame('items', $resource);
                $this->assertSame(8, $params['limit']);
                $result = $params['site_id'] === 1 ? array_slice($items, 0, 10) : [$items[10], $items[11]];
                return $this->response($result);
            });
        $helper = $this->helper([
            Module::SETTING_FEATURED_SITES => [1, -1, 2, 99, 3], Module::SETTING_BASE_SITE => 'base',
            Module::SETTING_NAV_PAGES => ['about' => ' About ', 'blank' => '', '' => 'skip', 7 => 42],
            Module::SETTING_LOGOS => null, Module::SETTING_TOP_BAR_LOGO => '/subpath/logo.svg',
        ], $api);
        $config = $helper();
        $this->assertCount(3, $config['featuredSites']);
        $this->assertSame(range(11, 4), array_map(function ($item) {
            return $item->id();
        }, $config['recentItems']));
        $this->assertSame('About', $config['navItems'][2]['label']);
        $this->assertSame('/subpath/globallandingpage-static/base/about', $config['navItems'][2]['url']);
        $this->assertSame('blank', $config['navItems'][3]['label']);
        $this->assertSame('42', $config['navItems'][4]['label']);
        $this->assertSame('/subpath/logo.svg', $config['topBar']['logo']['src']);
        $this->assertStringContainsString('/subpath/modules/GlobalLandingPage/', $config['sitesLogos'][0]['src']);
        $this->assertSame($config, $helper());
    }

    /** @dataProvider fallbackCases */
    public function testRecentItemsFallbackAndGracefulApiFailures(bool $featured, bool $fallbackFails): void
    {
        $item = $this->record(['id' => 1]);
        $api = $this->methods(['read', 'search']);
        $api->method('read')->willReturn($this->response($this->record(['id' => 1])));
        $api->method('search')->willReturnCallback(function ($resource, $query) use ($item, $fallbackFails) {
            if (isset($query['site_id']) || $fallbackFails) {
                throw new \RuntimeException('Unavailable');
            }
            $this->assertTrue($query['in_sites']);
            return $this->response([$item]);
        });
        $config = $this->helper([
            Module::SETTING_FEATURED_SITES => $featured ? [1] : 'invalid', Module::SETTING_NAV_PAGES => 'invalid',
        ], $api)();
        $this->assertSame($fallbackFails ? [] : [$item], $config['recentItems']);
        $this->assertCount(2, $config['navItems']);
        $this->assertNull($config['topBar']['logo']);
    }

    public function fallbackCases(): array
    {
        return [[true, false], [false, true]];
    }

    /** @dataProvider logoShapes */
    public function testAssetLogoCompatibility(string $shape, string $url, string $label): void
    {
        $assets = [
            'asset' => $this->record(['assetUrl' => '/a', 'altText' => 'Alt', 'displayTitle' => 'Title']),
            'original' => $this->record(['originalUrl' => '/o', 'title' => 'Title']),
            'display thumbnail' => $this->record(['thumbnailDisplayUrl' => '/d']),
            'thumbnail' => $this->record(['thumbnailUrl' => '/t']),
            'serialized' => $this->record(['jsonSerialize' => ['o:original_url' => '/j', 'o:name' => 'Name']]),
            'array' => ['o:thumbnail_urls' => ['medium' => '/m'], 'o:alt_text' => 'Array alt'],
        ];
        $api = $this->methods(['read', 'search']);
        $api->method('search')->willReturn($this->response([]));
        $api->method('read')->willReturnCallback(function ($resource, $id) use ($assets, $shape) {
            $this->assertSame('assets', $resource);
            if ($id === 99) {
                throw new \RuntimeException('Missing asset');
            }
            return $this->response($id === 1 ? $assets[$shape] : new \stdClass());
        });
        $config = $this->helper([
            Module::SETTING_LOGOS => [0, 99, 2, 1], Module::SETTING_TOP_BAR_LOGO => ['id' => 1],
            Module::SETTING_SHOW_TOP_BAR => false,
        ], $api)();
        $this->assertSame([['src' => $url, 'alt' => $label]], $config['sitesLogos']);
        $this->assertSame(['show' => false, 'logo' => ['src' => $url, 'alt' => $label]], $config['topBar']);
    }

    public function logoShapes(): array
    {
        return [
            ['asset', '/a', 'Alt'], ['original', '/o', 'Title'], ['display thumbnail', '/d', 'Servicio Mediateca'],
            ['thumbnail', '/t', 'Servicio Mediateca'], ['serialized', '/j', 'Name'], ['array', '/m', 'Array alt'],
        ];
    }

    public function testDeletedTopBarAssetLeavesLogoEmpty(): void
    {
        $api = $this->methods(['read', 'search']);
        $api->method('search')->willReturn($this->response([]));
        $api->method('read')->willThrowException(new \RuntimeException('Deleted asset'));
        $config = $this->helper([Module::SETTING_TOP_BAR_LOGO => 9], $api)();
        $this->assertNull($config['topBar']['logo']);
    }
}
