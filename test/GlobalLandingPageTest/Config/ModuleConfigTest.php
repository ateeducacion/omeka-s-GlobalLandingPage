<?php declare(strict_types=1);

namespace GlobalLandingPageTest\Config;

use GlobalLandingPage\Controller\LandingController;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require dirname(__DIR__, 3) . '/config/module.config.php';
    }

    public function testRegistersLandingRoute(): void
    {
        $this->assertArrayHasKey('router', $this->config);
        $this->assertArrayHasKey('routes', $this->config['router']);
        $this->assertArrayHasKey('globallandingpage', $this->config['router']['routes']);

        $route = $this->config['router']['routes']['globallandingpage'];
        $this->assertSame('Laminas\Router\Http\Literal', $route['type']);
        $this->assertSame('/global-landing', $route['options']['route']);
        $this->assertSame(LandingController::class, $route['options']['defaults']['controller']);
        $this->assertSame('index', $route['options']['defaults']['action']);
    }

    public function testRegistersControllerFactory(): void
    {
        $this->assertArrayHasKey('controllers', $this->config);
        $this->assertArrayHasKey('factories', $this->config['controllers']);
        $this->assertArrayHasKey(LandingController::class, $this->config['controllers']['factories']);
    }

    public function testViewManagerProvidesTemplatesAndMap(): void
    {
        $this->assertArrayHasKey('view_manager', $this->config);
        $viewManager = $this->config['view_manager'];

        $this->assertArrayHasKey('template_map', $viewManager);
        $this->assertSame(
            [
                'global-landing-page/config-form' => dirname(__DIR__, 3) . '/config/config_form.php',
                'global-landing-page/layout' => dirname(__DIR__, 3) . '/view/layout/layout.phtml',
                'global-landing-page/common/header' => dirname(__DIR__, 3) . '/view/common/header.phtml',
                'global-landing-page/common/footer' => dirname(__DIR__, 3) . '/view/common/footer.phtml',
                'global-landing-page/common/static-page' => dirname(__DIR__, 3) .
                                                        '/view/global-landing-page/common/static-page.phtml',
            ],
            $viewManager['template_map']
        );

        $this->assertArrayNotHasKey('template_path_stack', $viewManager);
    }

    public function testTranslatorPatternsRemainIntact(): void
    {
        $this->assertArrayHasKey('translator', $this->config);
        $this->assertSame(
            [
                [
                    'type' => 'gettext',
                    'base_dir' => dirname(__DIR__, 3) . '/language',
                    'pattern' => '%s.mo',
                    'text_domain' => null,
                ],
            ],
            $this->config['translator']['translation_file_patterns']
        );
    }
}
