<?php
declare(strict_types=1);

namespace GlobalLandingPage;

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'router' => [
        'routes' => [
            'globallandingpage' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route' => '/global-landing',
                    'defaults' => [
                        'controller' => Controller\LandingController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\LandingController::class => InvokableFactory::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            View\Helper\ShadeColor::class => InvokableFactory::class,
            View\Helper\ContrastColor::class => InvokableFactory::class,
            View\Helper\ResourceTags::class => InvokableFactory::class,
        ],
        'aliases' => [
            'shadeColor' => View\Helper\ShadeColor::class,
            'ShadeColor' => View\Helper\ShadeColor::class,
            'contrastColor' => View\Helper\ContrastColor::class,
            'ContrastColor' => View\Helper\ContrastColor::class,
            'resourceTags' => View\Helper\ResourceTags::class,
            'ResourceTags' => View\Helper\ResourceTags::class,
        ],
    ],
    'view_manager' => [
        'template_map' => [
            'global-landing-page/config-form' => __DIR__ . '/config_form.php',
            'global-landing-page/layout' => dirname(__DIR__) . '/view/layout/layout.phtml',
            'global-landing-page/common/header' => dirname(__DIR__) . '/view/common/header.phtml',
            'global-landing-page/common/footer' => dirname(__DIR__) . '/view/common/footer.phtml',
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
];
