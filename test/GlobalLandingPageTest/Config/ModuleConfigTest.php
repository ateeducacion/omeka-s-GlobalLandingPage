<?php declare(strict_types=1);

namespace GlobalLandingPageTest\Config;

use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    public function testModuleConfigProvidesViewStack(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';

        $this->assertIsArray($config);
        $this->assertArrayHasKey('view_manager', $config);
        $this->assertArrayHasKey('template_path_stack', $config['view_manager']);
        $this->assertContains(
            dirname(__DIR__, 3) . '/view',
            $config['view_manager']['template_path_stack']
        );
    }

    public function testModuleConfigRegistersTranslatorPatterns(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/module.config.php';

        $this->assertArrayHasKey('translator', $config);
        $this->assertArrayHasKey('translation_file_patterns', $config['translator']);
        $this->assertSame(
            [
                [
                    'type' => 'gettext',
                    'base_dir' => dirname(__DIR__, 3) . '/language',
                    'pattern' => '%s.mo',
                    'text_domain' => null,
                ],
            ],
            $config['translator']['translation_file_patterns']
        );
    }
}
