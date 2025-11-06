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
                    'route' => '/',
                    'defaults' => [
                        'controller' => Controller\LandingController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
            ],
            'globallandingpage-sites' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route' => '/sites',
                    'defaults' => [
                        'controller' => Controller\SiteController::class,
                        'action' => 'explore',
                    ],
                ],
                'may_terminate' => true,
            ],
            'globallandingpage-static' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/global-page/:site-slug/:page-slug',
                    'defaults' => [
                        'controller' => Controller\StaticPageController::class,
                        'action' => 'show',
                    ],
                ],
                'may_terminate' => true,
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\LandingController::class => InvokableFactory::class,
            Controller\SiteController::class => InvokableFactory::class,
            Controller\StaticPageController::class => InvokableFactory::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            View\Helper\ShadeColor::class => InvokableFactory::class,
            View\Helper\ContrastColor::class => InvokableFactory::class,
            View\Helper\ResourceTags::class => InvokableFactory::class,
            View\Helper\LandingPageConfig::class => InvokableFactory::class,
        ],
        'aliases' => [
            'shadeColor' => View\Helper\ShadeColor::class,
            'ShadeColor' => View\Helper\ShadeColor::class,
            'contrastColor' => View\Helper\ContrastColor::class,
            'ContrastColor' => View\Helper\ContrastColor::class,
            'resourceTags' => View\Helper\ResourceTags::class,
            'ResourceTags' => View\Helper\ResourceTags::class,
            'landingPageConfig' => View\Helper\LandingPageConfig::class,
            'LandingPageConfig' => View\Helper\LandingPageConfig::class,
        ],
    ],
    'view_manager' => [
        'template_map' => [
            'global-landing-page/config-form' => dirname(__DIR__) . '/config/config_form.php',
            'global-landing-page/layout' => dirname(__DIR__) . '/view/layout/layout.phtml',
            'global-landing-page/common/header' => dirname(__DIR__) . '/view/common/header.phtml',
            'global-landing-page/common/footer' => dirname(__DIR__) . '/view/common/footer.phtml',
            'global-landing-page/common/static-page' => dirname(__DIR__) .
                                                    '/view/global-landing-page/common/static-page.phtml',
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
