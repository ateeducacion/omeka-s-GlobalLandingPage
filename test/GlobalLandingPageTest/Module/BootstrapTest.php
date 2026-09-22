<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\Module;

use GlobalLandingPage\Controller\LandingController;
use GlobalLandingPage\Module;
use GlobalLandingPageTest\Support\Fixtures;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Resolver\TemplateMapResolver;
use Laminas\View\Resolver\TemplatePathStack;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    use Fixtures;

    public function testInstallAndUninstallManageOnlyModuleSettings(): void
    {
        $module = new Module();
        $settings = $this->settings(['other' => 'preserved']);
        $services = $this->services(['Omeka\Settings' => $settings]);
        $module->install($services);
        $this->assertFalse($settings->get(Module::SETTING_USE_CUSTOM));
        $this->assertTrue($settings->get(Module::SETTING_SHOW_TOP_BAR));
        $this->assertCount(12, $settings->values);
        $this->assertSame('#e77f11', $settings->get(Module::SETTING_PRIMARY_COLOR));
        $this->assertArrayHasKey('router', $module->getConfig());
        $module->uninstall($services);
        $this->assertSame(['other' => 'preserved'], $settings->values);
        $module->uninstall($this->services([]));
    }

    /** @dataProvider templateStates */
    public function testBootstrapManagesOnlyItsOwnTemplates(bool $enabled, string $existing, bool $ownPath): void
    {
        $root = dirname(__DIR__, 3);
        $map = new TemplateMapResolver();
        $template = $existing === 'own' ? $root . '/view/omeka/index/index.phtml' : $existing;
        $map->setMap(['omeka/index/index' => $template]);
        $paths = new TemplatePathStack();
        $paths->setPaths($ownPath ? [$root . '/view', '/other/view'] : ['/other/view']);
        $acl = $this->methods(['hasResource', 'addResource', 'allow']);
        $acl->method('hasResource')->willReturn(false);
        $acl->expects($this->exactly(3))->method('addResource');
        $acl->expects($this->exactly(3))->method('allow');
        $manager = $this->methods(['attach']);
        $manager->expects($enabled ? $this->once() : $this->never())->method('attach');
        $services = $this->services([
            'Omeka\Settings' => $this->settings([Module::SETTING_USE_CUSTOM => $enabled]),
            'Omeka\Acl' => $acl, 'ViewTemplateMapResolver' => $map, 'ViewTemplatePathStack' => $paths,
        ]);
        $app = $this->record(['getServiceManager' => $services, 'getEventManager' => $manager]);
        (new Module())->onBootstrap($this->record(['getApplication' => $app], MvcEvent::class));
        if ($enabled) {
            $this->assertSame($root . '/view/omeka/index/index.phtml', $map->getMap()['omeka/index/index']);
            $this->assertContains($root . '/view', $paths->getPaths());
            $this->assertCount(2, $paths->getPaths());
        } else {
            $this->assertSame($existing === 'own' ? [] : ['omeka/index/index' => $existing], $map->getMap());
            $this->assertSame(['/other/view'], $paths->getPaths());
        }
    }

    public function templateStates(): array
    {
        return [[true, 'core.phtml', false], [true, 'own', true], [false, 'own', true], [false, 'core.phtml', false]];
    }

    /** @dataProvider routes */
    public function testOnlyGlobalIndexRoutesAreReplaced(
        ?string $path,
        ?string $controller,
        string $action,
        bool $replace
    ): void {
        $callback = null;
        $manager = $this->methods(['attach']);
        $manager->method('attach')->willReturnCallback(function ($event, $listener, $priority) use (&$callback) {
            $this->assertSame(MvcEvent::EVENT_ROUTE, $event);
            $this->assertSame(-100, $priority);
            $callback = $listener;
        });
        $services = $this->services(['Omeka\Settings' => $this->settings([Module::SETTING_USE_CUSTOM => true])]);
        $app = $this->record(['getServiceManager' => $services, 'getEventManager' => $manager]);
        (new Module())->onBootstrap($this->record(['getApplication' => $app], MvcEvent::class));
        $match = $this->methods(['getParam', 'setMatchedRouteName', 'setParam']);
        $match->method('getParam')->willReturnMap([['controller', '', $controller], ['action', '', $action]]);
        $match->expects($replace ? $this->once() : $this->never())
            ->method('setMatchedRouteName')->with('globallandingpage');
        $params = [];
        $match->method('setParam')->willReturnCallback(function ($key, $value) use (&$params) {
            $params[$key] = $value;
        });
        $request = $path === null ? new \stdClass() : $this->record([
            'getUri' => $this->record(['getPath' => $path]),
        ], \Laminas\Http\Request::class);
        $event = $this->methods(['getRequest', 'getRouteMatch', 'stopPropagation'], MvcEvent::class);
        $event->method('getRequest')->willReturn($request);
        $event->method('getRouteMatch')->willReturn($controller === null ? null : $match);
        $event->expects($replace ? $this->once() : $this->never())->method('stopPropagation')->with(true);
        $callback($event);
        $this->assertSame($replace ? LandingController::class : null, $params['controller'] ?? null);
    }

    public function routes(): array
    {
        return [
            ['/', 'Omeka\Controller\Index', 'index', true],
            ['', '\Omeka\Controller\IndexController', 'index', true],
            ['/', 'index', 'index', true],
            ['/s/site', 'index', 'index', false],
            ['/', 'Other\Controller', 'index', false],
            ['/', 'index', 'browse', false],
            [null, 'index', 'index', false],
            ['/', null, 'index', false],
        ];
    }

    public function testBootstrapWithoutSettingsDoesNothing(): void
    {
        $services = $this->services([]);
        $services->expects($this->never())->method('get');
        $app = $this->record(['getServiceManager' => $services]);
        (new Module())->onBootstrap($this->record(['getApplication' => $app], MvcEvent::class));
    }
}
