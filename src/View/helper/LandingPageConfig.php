<?php
declare(strict_types=1);

namespace GlobalLandingPage\View\Helper;

use GlobalLandingPage\Module;
use Laminas\View\Helper\AbstractHelper;

class LandingPageConfig extends AbstractHelper
{
    private ?array $config = null;

    public function __invoke(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $view = $this->getView();
        $translate = $view->plugin('translate');

        $headline = $translate('Servicio Mediateca');
        $lead = $translate('Repositorio de medios audiovisuales educativos.');

        $this->config = [
            'headline' => $headline,
            'lead' => $lead,
            'featuredSites' => $this->resolveFeaturedSites(),
            'navItems' => $this->buildNavigation(),
            'sitesLogos' => $this->resolveSiteLogos($headline),
            'topBar' => $this->resolveTopBarConfig($headline),
        ];

        return $this->config;
    }

    private function resolveFeaturedSites(): array
    {
        $view = $this->getView();
        $featuredSiteIds = $view->setting(Module::SETTING_FEATURED_SITES, []);
        if (!is_array($featuredSiteIds) || $featuredSiteIds === []) {
            return [];
        }

        $api = $view->api();

        $sites = [];
        foreach ($featuredSiteIds as $siteId) {
            $siteId = (int) $siteId;
            if ($siteId <= 0) {
                continue;
            }
            try {
                $sites[] = $api->read('sites', $siteId)->getContent();
            } catch (\Throwable $exception) {
                continue;
            }
        }

        return $sites;
    }

    private function buildNavigation(): array
    {
        $view = $this->getView();
        $translate = $view->plugin('translate');

        $navItems = [
            [
                'label' => $translate('Inicio'),
                'url' => $view->url('globallandingpage', [], true),
                'slug' => 'home',
            ],
            [
                'label' => $translate('Explorar Canales'),
                'url' => $view->url('globallandingpage-sites', [], true),
                'slug' => 'explore',
            ],
        ];

        $baseSiteSlug = (string) $view->setting(Module::SETTING_BASE_SITE, '');
        $navPagesSetting = $view->setting(Module::SETTING_NAV_PAGES, []);
        if (!is_array($navPagesSetting)) {
            $navPagesSetting = [];
        }

        if ($baseSiteSlug !== '' && $navPagesSetting !== []) {
            foreach ($navPagesSetting as $slug => $title) {
                $slug = is_string($slug) ? trim($slug) : (string) $slug;
                if ($slug === '') {
                    continue;
                }

                $label = is_string($title) ? trim($title) : (string) $title;
                if ($label === '') {
                    $label = $slug;
                }

                $navItems[] = [
                    'label' => $label,
                    'url' => $view->url(
                        'site/page',
                        ['site-slug' => $baseSiteSlug, 'page-slug' => $slug],
                        ['force_canonical' => true]
                    ),
                    'slug' => 'page:' . $slug,
                ];
            }
        }

        return $navItems;
    }

    private function resolveSiteLogos(string $headline): array
    {
        $view = $this->getView();
        $logosSetting = $view->setting(Module::SETTING_LOGOS, []);
        $logos = [];

        if (is_array($logosSetting) && $logosSetting !== []) {
            $api = $view->api();
            foreach ($logosSetting as $assetId) {
                $assetId = (int) $assetId;
                if ($assetId <= 0) {
                    continue;
                }

                try {
                    $asset = $api->read('assets', $assetId)->getContent();
                } catch (\Throwable $exception) {
                    continue;
                }

                $logoData = $this->extractAssetLogo($asset, $headline);
                if ($logoData !== null) {
                    $logos[] = $logoData;
                }
            }
        }

        if ($logos !== []) {
            return $logos;
        }

        foreach (Module::DEFAULT_LOGOS as $defaultLogo) {
            $src = $view->assetUrl('img/' . ltrim($defaultLogo, '/'), 'GlobalLandingPage');
            $basename = pathinfo($defaultLogo, PATHINFO_FILENAME);
            $label = ucwords(str_replace(['-', '_'], ' ', $basename));
            $logos[] = [
                'src' => $src,
                'alt' => $label !== '' ? $label : $headline,
            ];
        }

        return $logos;
    }

    /**
     * @param mixed $asset
     */
    private function extractAssetLogo($asset, string $headline): ?array
    {
        $src = '';
        $alt = '';
        $label = '';
        $serialized = null;

        if (is_object($asset)) {
            if (method_exists($asset, 'assetUrl')) {
                $src = (string) $asset->assetUrl();
            } elseif (method_exists($asset, 'originalUrl')) {
                $src = (string) $asset->originalUrl();
            } elseif (method_exists($asset, 'thumbnailDisplayUrl')) {
                $src = (string) $asset->thumbnailDisplayUrl('large');
            } elseif (method_exists($asset, 'thumbnailUrl')) {
                $src = (string) $asset->thumbnailUrl('large');
            }

            if (method_exists($asset, 'altText')) {
                $alt = (string) $asset->altText();
            }

            if (method_exists($asset, 'displayTitle')) {
                $label = (string) $asset->displayTitle();
            } elseif (method_exists($asset, 'title')) {
                $label = (string) $asset->title();
            }

            if (method_exists($asset, 'jsonSerialize')) {
                $serialized = $asset->jsonSerialize();
            }
        } elseif (is_array($asset)) {
            $serialized = $asset;
        }

        if (is_array($serialized)) {
            if ($src === '' && isset($serialized['o:original_url']) && is_string($serialized['o:original_url'])) {
                $src = (string) $serialized['o:original_url'];
            }

            if ($src === '' && isset($serialized['o:thumbnail_urls']) && is_array($serialized['o:thumbnail_urls'])) {
                foreach (['large', 'medium', 'square', 'original'] as $thumbnailKey) {
                    if (
                        isset($serialized['o:thumbnail_urls'][$thumbnailKey])
                        && is_string($serialized['o:thumbnail_urls'][$thumbnailKey])
                    ) {
                        $src = (string) $serialized['o:thumbnail_urls'][$thumbnailKey];
                        break;
                    }
                }
            }

            if ($alt === '' && isset($serialized['o:alt_text']) && is_string($serialized['o:alt_text'])) {
                $alt = (string) $serialized['o:alt_text'];
            }

            if ($label === '' && isset($serialized['o:name']) && is_string($serialized['o:name'])) {
                $label = (string) $serialized['o:name'];
            }
        }

        if ($src === '') {
            return null;
        }

        $text = $alt !== '' ? $alt : ($label !== '' ? $label : $headline);

        return [
            'src' => $src,
            'alt' => $text,
        ];
    }

    private function resolveTopBarConfig(string $headline): array
    {
        $view = $this->getView();
        $showTopBar = (bool) $view->setting(Module::SETTING_SHOW_TOP_BAR, true);
        $logoSetting = $view->setting(Module::SETTING_TOP_BAR_LOGO, '');

        $logo = null;
        $assetId = null;
        if (is_numeric($logoSetting)) {
            $assetId = (int) $logoSetting;
        } elseif (is_array($logoSetting)) {
            foreach (['o:id', 'id'] as $key) {
                if (isset($logoSetting[$key]) && is_numeric($logoSetting[$key])) {
                    $assetId = (int) $logoSetting[$key];
                    break;
                }
            }
        }

        if ($assetId !== null && $assetId > 0) {
            try {
                $asset = $view->api()->read('assets', $assetId)->getContent();
                $logoData = $this->extractAssetLogo($asset, $headline);
                if ($logoData !== null) {
                    $logo = $logoData;
                }
            } catch (\Throwable $exception) {
                $logo = null;
            }
        } elseif (is_string($logoSetting) && $logoSetting !== '' && !is_numeric($logoSetting)) {
            $logo = [
                'src' => $logoSetting,
                'alt' => $headline,
            ];
        }

        return [
            'show' => $showTopBar,
            'logo' => $logo,
        ];
    }
}
