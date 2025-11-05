<?php
declare(strict_types=1);

namespace GlobalLandingPage;

use GlobalLandingPage\Form\ConfigForm;
use Laminas\Form\FormInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Omeka\Module\AbstractModule;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    public const SETTING_USE_CUSTOM = 'globallandingpage_use_custom';
    public const SETTING_FEATURED_SITES = 'globallandingpage_featured_sites';
    public const SETTING_BASE_SITE = 'globallandingpage_base_site';
    public const SETTING_NAV_PAGES = 'globallandingpage_nav_pages';
    public const SETTING_FOOTER_HTML = 'globallandingpage_footer_html';
    public const SETTING_PRIMARY_COLOR = 'globallandingpage_primary_color';
    public const SETTING_SECONDARY_COLOR = 'globallandingpage_secondary_color';
    public const SETTING_ACCENT_COLOR = 'globallandingpage_accent_color';
    public const SETTING_LOGOS = 'globallandingpage_logos';
    public const SETTING_SHOW_TOP_BAR = 'globallandingpage_show_top_bar';
    public const SETTING_TOP_BAR_LOGO = 'globallandingpage_top_bar_logo';
    public const DEFAULT_LOGOS = [
        'default_logo.svg',
    ];
    public const DEFAULT_COLORS = [
        self::SETTING_PRIMARY_COLOR => '#e77f11',
        self::SETTING_SECONDARY_COLOR => '#394f68',
        self::SETTING_ACCENT_COLOR => '#394f68',
    ];
    private const MAX_LOGO_FILES = 3;
    private const TEMPLATE_ALIAS = 'omeka/index/index';
    private const TEMPLATE_PATH = __DIR__ . '/view/omeka/index/index.phtml';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        /** @var Settings $settings */
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->set(self::SETTING_USE_CUSTOM, false);
        $settings->set(self::SETTING_FEATURED_SITES, []);
        $settings->set(self::SETTING_BASE_SITE, '');
        $settings->set(self::SETTING_NAV_PAGES, []);
        $settings->set(self::SETTING_FOOTER_HTML, '');
        foreach (self::DEFAULT_COLORS as $settingKey => $defaultValue) {
            $settings->set($settingKey, $defaultValue);
        }
        $settings->set(self::SETTING_LOGOS, []);
        $settings->set(self::SETTING_SHOW_TOP_BAR, true);
        $settings->set(self::SETTING_TOP_BAR_LOGO, '');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        if (!$serviceLocator->has('Omeka\Settings')) {
            return;
        }

        /** @var Settings $settings */
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete(self::SETTING_USE_CUSTOM);
        $settings->delete(self::SETTING_FEATURED_SITES);
        $settings->delete(self::SETTING_BASE_SITE);
        $settings->delete(self::SETTING_NAV_PAGES);
        $settings->delete(self::SETTING_FOOTER_HTML);
        $settings->delete(self::SETTING_PRIMARY_COLOR);
        $settings->delete(self::SETTING_SECONDARY_COLOR);
        $settings->delete(self::SETTING_ACCENT_COLOR);
        $settings->delete(self::SETTING_LOGOS);
        $settings->delete(self::SETTING_SHOW_TOP_BAR);
        $settings->delete(self::SETTING_TOP_BAR_LOGO);
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $application = $event->getApplication();
        $services = $application->getServiceManager();

        if (!$services->has('Omeka\Settings')) {
            return;
        }

        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');
        $enabled = (bool) $settings->get(self::SETTING_USE_CUSTOM, false);

        if ($services->has('Omeka\Acl')) {
            $acl = $services->get('Omeka\Acl');

            if (!$acl->hasResource(Controller\LandingController::class)) {
                $acl->addResource(Controller\LandingController::class);
            }

            if (!$acl->hasResource(Controller\SiteController::class)) {
                $acl->addResource(Controller\SiteController::class);
            }

            $acl->allow(
                null,
                Controller\LandingController::class,
                'index'
            );
            $acl->allow(
                null,
                Controller\SiteController::class,
                'explore'
            );
        }

        $resolver = $services->has('ViewTemplateMapResolver')
            ? $services->get('ViewTemplateMapResolver')
            : null;
        $this->configureTemplateOverride($resolver, $enabled);
        $templatePathStack = $services->has('ViewTemplatePathStack')
            ? $services->get('ViewTemplatePathStack')
            : null;
        $this->configureTemplatePathStack($templatePathStack, $enabled);

        if (!$enabled) {
            return;
        }

        $eventManager = $application->getEventManager();
        $eventManager->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $routeEvent): void {

                $request = $routeEvent->getRequest();
                if (!$request instanceof HttpRequest) {
                    return;
                }

                $path = (string) $request->getUri()->getPath();
                if ($path !== '/' && $path !== '') {
                    return;
                }

                error_log("ENTRA AL HOME CUSTOM".get_class($routeEvent));
                $routeMatch = $routeEvent->getRouteMatch();
                if ($routeMatch === null) {
                    return;
                }

                $controllerParam = (string) $routeMatch->getParam('controller', '');
                $actionParam = (string) $routeMatch->getParam('action', '');
                $normalizedController = ltrim($controllerParam, '\\');

                $isDefaultController = $normalizedController === 'Omeka\Controller\Index'
                    || $normalizedController === 'Omeka\Controller\IndexController';

                if (!$isDefaultController || $actionParam !== 'index') {
                    return;
                }

                $routeMatch->setMatchedRouteName('globallandingpage');
                $routeMatch->setParam('controller', Controller\LandingController::class);
                $routeMatch->setParam('action', 'index');
                $routeMatch->setParam('__NAMESPACE__', __NAMESPACE__ . '\Controller');
                $routeMatch->setParam('module', __NAMESPACE__);
                $routeMatch->setParam('__CONTROLLER__', 'landing');
                $routeMatch->setParam('globallandingpage_active', true);

                $routeEvent->stopPropagation(true);
            },
            -100
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');
        /** @var ApiManager $apiManager */
        $apiManager = $services->get('Omeka\ApiManager');

        $form = new ConfigForm();
        $form->setSettings($settings);
        $form->setOption('api_manager', $apiManager);
        $form->init();

        $baseSiteSlug = (string) $settings->get(self::SETTING_BASE_SITE, '');
        $baseSiteId = $baseSiteSlug !== ''
            ? $this->findSiteIdBySlug($apiManager, $baseSiteSlug)
            : null;

        $navPagesSetting = $settings->get(self::SETTING_NAV_PAGES, []);
        if (!is_array($navPagesSetting)) {
            $navPagesSetting = [];
        }
        $navPageSlugs = array_keys($navPagesSetting);

        $logoIds = $settings->get(self::SETTING_LOGOS, []);
        if (!is_array($logoIds)) {
            $logoIds = [];
        }
        $logoIds = array_values($logoIds);
        $topBarLogoSetting = $settings->get(self::SETTING_TOP_BAR_LOGO, '');

        $form->setData([
            self::SETTING_USE_CUSTOM => $settings->get(self::SETTING_USE_CUSTOM, false) ? '1' : '0',
            self::SETTING_FEATURED_SITES => $settings->get(self::SETTING_FEATURED_SITES, []),
            self::SETTING_BASE_SITE => $baseSiteId,
            self::SETTING_NAV_PAGES => $navPageSlugs,
            self::SETTING_FOOTER_HTML => $settings->get(self::SETTING_FOOTER_HTML, ''),
            self::SETTING_PRIMARY_COLOR => $settings->get(
                self::SETTING_PRIMARY_COLOR,
                self::DEFAULT_COLORS[self::SETTING_PRIMARY_COLOR]
            ),
            self::SETTING_SECONDARY_COLOR => $settings->get(
                self::SETTING_SECONDARY_COLOR,
                self::DEFAULT_COLORS[self::SETTING_SECONDARY_COLOR]
            ),
            self::SETTING_ACCENT_COLOR => $settings->get(
                self::SETTING_ACCENT_COLOR,
                self::DEFAULT_COLORS[self::SETTING_ACCENT_COLOR]
            ),
            'globallandingpage_show_top_bar' => $settings->get(self::SETTING_SHOW_TOP_BAR, true) ? '1' : '0',
            'globallandingpage_top_bar_logo' => $topBarLogoSetting,
            'globallandingpage_logo_1' => $logoIds[0] ?? '',
            'globallandingpage_logo_2' => $logoIds[1] ?? '',
            'globallandingpage_logo_3' => $logoIds[2] ?? '',
        ]);
        if ($form->has(self::SETTING_NAV_PAGES)) {
            $navPagesElement = $form->get(self::SETTING_NAV_PAGES);
            $navPagesElement->setAttribute('data-selected-values', implode(',', $navPageSlugs));
            $navPagesElement->setAttribute(
                'data-api-endpoint',
                $renderer->url('api/default', ['resource' => 'site_pages'], ['force_canonical' => true])
            );
            $navPagesElement->setAttribute('data-loading-label', $renderer->translate('Loading...'));
            $navPagesElement->setAttribute(
                'data-empty-label',
                $renderer->translate('Select a site to load pages.')
            );
        }

        $renderer->headLink()->appendStylesheet(
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'
        );
        $renderer->headLink()->appendStylesheet(
            $renderer->assetUrl('css/admin.css', 'GlobalLandingPage')
        );
        $renderer->headScript()->appendFile(
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js'
        );
        $renderer->headScript()->appendFile(
            $renderer->assetUrl('js/admin-init.js', 'GlobalLandingPage')
        );

        $selectedLogos = $this->prepareLogoData(
            $settings->get(self::SETTING_LOGOS, []),
            $apiManager
        );

        if ($selectedLogos === []) {
            $selectedLogos = $this->prepareDefaultLogoData($renderer);
        }

        return $renderer->render('global-landing-page/config-form', [
            'form' => $form,
            'selectedLogos' => $selectedLogos,
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');
        /** @var ApiManager $apiManager */
        $apiManager = $services->get('Omeka\ApiManager');

        $form = new ConfigForm();
        $form->setSettings($settings);
        $form->setOption('api_manager', $apiManager);
        $form->init();

        $postData = $controller->params()->fromPost();
        $fileData = $controller->params()->fromFiles();
        $form->setData(array_replace_recursive($postData, $fileData));

        if (!$form->isValid()) {
            $controller->messenger()->addError(
                new Message('Unable to save settings: submitted data is invalid.') // @translate
            );
            return false;
        }

        $data = $form->getData(FormInterface::VALUES_AS_ARRAY);

        $useCustom = $this->normalizeCheckbox($data[self::SETTING_USE_CUSTOM] ?? null);
        $featuredSites = $this->filterPositiveIntegers($data[self::SETTING_FEATURED_SITES] ?? []);
        $baseSiteId = $this->normalizeSiteIdentifier($data[self::SETTING_BASE_SITE] ?? null, $apiManager);
        $baseSiteSlug = $baseSiteId !== null
            ? (string) $this->findSiteSlugById($apiManager, $baseSiteId)
            : '';

        $navPagesSelection = $this->normalizeStringArray($data[self::SETTING_NAV_PAGES] ?? []);
        $navOptions = $form->has(self::SETTING_NAV_PAGES)
            ? $form->get(self::SETTING_NAV_PAGES)->getValueOptions()
            : [];
        $navPages = [];
        foreach ($navPagesSelection as $slug) {
            $label = '';
            if (is_array($navOptions) && isset($navOptions[$slug])) {
                $label = (string) $navOptions[$slug];
            }
            if ($label === '') {
                $label = $slug;
            }
            $navPages[$slug] = $label;
        }

        $footerHtml = (string) ($data[self::SETTING_FOOTER_HTML] ?? '');
        $primaryColor = $this->normalizeColor(
            $data[self::SETTING_PRIMARY_COLOR] ?? null,
            self::DEFAULT_COLORS[self::SETTING_PRIMARY_COLOR]
        );
        $secondaryColor = $this->normalizeColor(
            $data[self::SETTING_SECONDARY_COLOR] ?? null,
            self::DEFAULT_COLORS[self::SETTING_SECONDARY_COLOR]
        );
        $accentColor = $this->normalizeColor(
            $data[self::SETTING_ACCENT_COLOR] ?? null,
            self::DEFAULT_COLORS[self::SETTING_ACCENT_COLOR]
        );

        $showTopBar = $this->normalizeCheckbox($data['globallandingpage_show_top_bar'] ?? null);
        $topBarLogoId = $this->normalizeAssetIdentifier($data['globallandingpage_top_bar_logo'] ?? null);

        $selectedLogos = [];
        for ($index = 1; $index <= self::MAX_LOGO_FILES; ++$index) {
            $fieldName = sprintf('globallandingpage_logo_%d', $index);
            $logoId = $this->normalizeAssetIdentifier($data[$fieldName] ?? null);
            if ($logoId !== null) {
                $selectedLogos[] = $logoId;
            }
        }
        $selectedLogos = array_values(array_unique($selectedLogos));

        $settings->set(self::SETTING_USE_CUSTOM, $useCustom);
        $settings->set(self::SETTING_FEATURED_SITES, $featuredSites);
        $settings->set(self::SETTING_BASE_SITE, $baseSiteSlug);
        $settings->set(self::SETTING_NAV_PAGES, $navPages);
        $settings->set(self::SETTING_FOOTER_HTML, $footerHtml);
        $settings->set(self::SETTING_PRIMARY_COLOR, $primaryColor);
        $settings->set(self::SETTING_SECONDARY_COLOR, $secondaryColor);
        $settings->set(self::SETTING_ACCENT_COLOR, $accentColor);
        $settings->set(self::SETTING_SHOW_TOP_BAR, $showTopBar);
        $settings->set(self::SETTING_TOP_BAR_LOGO, $topBarLogoId ?? '');
        $settings->set(self::SETTING_LOGOS, $selectedLogos);

        $resolver = $services->has('ViewTemplateMapResolver')
            ? $services->get('ViewTemplateMapResolver')
            : null;
        $this->configureTemplateOverride($resolver, $useCustom);
        $templatePathStack = $services->has('ViewTemplatePathStack')
            ? $services->get('ViewTemplatePathStack')
            : null;
        $this->configureTemplatePathStack($templatePathStack, $useCustom);

        $controller->messenger()->addSuccess(new Message('Global landing page settings saved.')); // @translate

        return true;
    }

    /**
     * Normalize checkbox-style values to a boolean.
     *
     * @param mixed $value
     */
    private function normalizeCheckbox($value): bool
    {
        if (is_array($value)) {
            $value = array_pop($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return $normalized === '1' || $normalized === 'true' || $normalized === 'yes';
        }

        return false;
    }

    /**
     * @param mixed $values
     * @return int[]
     */
    private function filterPositiveIntegers($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = array_shift($value);
            }
            $intValue = (int) $value;
            if ($intValue > 0) {
                $result[$intValue] = $intValue;
            }
        }

        return array_values($result);
    }

    /**
     * @param mixed $values
     * @return string[]
     */
    private function normalizeStringArray($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = array_shift($value);
            }
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $identifier
     */
    private function normalizeSiteIdentifier($identifier, ApiManager $apiManager): ?int
    {
        if ($identifier === null) {
            return null;
        }

        if (is_numeric($identifier)) {
            $siteId = (int) $identifier;
            return $siteId > 0 ? $siteId : null;
        }

        if (!is_string($identifier)) {
            return null;
        }

        $slug = trim($identifier);
        if ($slug === '') {
            return null;
        }

        return $this->findSiteIdBySlug($apiManager, $slug);
    }

    private function findSiteIdBySlug(ApiManager $apiManager, string $slug): ?int
    {
        try {
            $response = $apiManager->search('sites', ['slug' => $slug, 'limit' => 1]);
            $sites = $response->getContent();
            $site = $sites[0] ?? null;
            if ($site === null) {
                return null;
            }
            if (is_object($site) && method_exists($site, 'id')) {
                return (int) $site->id();
            }
            if (is_array($site) && isset($site['o:id'])) {
                return (int) $site['o:id'];
            }
        } catch (\Exception $exception) {
            return null;
        }

        return null;
    }

    private function findSiteSlugById(ApiManager $apiManager, int $siteId): ?string
    {
        if ($siteId <= 0) {
            return null;
        }

        try {
            $site = $apiManager->read('sites', $siteId)->getContent();
        } catch (\Exception $exception) {
            return null;
        }

        if (is_object($site) && method_exists($site, 'slug')) {
            return (string) $site->slug();
        }

        if (is_array($site) && isset($site['o:slug'])) {
            return (string) $site['o:slug'];
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function normalizeAssetIdentifier($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $candidates = [
            $value['o:id'] ?? null,
            $value['id'] ?? null,
            $value['asset']['o:id'] ?? null,
            $value['asset']['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $id = (int) $candidate;
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }

    private function normalizeColor($value, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return $default;
        }

        if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $candidate)) {
            return $default;
        }

        return strtolower($candidate);
    }

    /**
     * @param array<string,mixed>|null $files
     * @return string[]
     */
    private function prepareLogoData($logoIds, ApiManager $apiManager): array
    {
        $rawValues = [];
        if (is_array($logoIds)) {
            $rawValues = $logoIds;
        } elseif ($logoIds !== null && $logoIds !== '') {
            $rawValues = [$logoIds];
        }

        $ids = [];
        foreach ($rawValues as $value) {
            $normalized = $this->normalizeAssetIdentifier($value);
            if ($normalized !== null) {
                $ids[] = $normalized;
            }
        }

        if ($ids === []) {
            return [];
        }

        $prepared = [];
        foreach (array_slice(array_values(array_unique($ids)), 0, self::MAX_LOGO_FILES) as $id) {
            try {
                $asset = $apiManager->read('assets', $id)->getContent();
            } catch (\Exception $exception) {
                continue;
            }

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
                $src = $this->resolveAssetSource($src, $serialized);
                if ($alt === '' && isset($serialized['o:alt_text']) && is_string($serialized['o:alt_text'])) {
                    $alt = (string) $serialized['o:alt_text'];
                }
                if ($label === '' && isset($serialized['o:name']) && is_string($serialized['o:name'])) {
                    $label = (string) $serialized['o:name'];
                }
            }

            if ($src === '') {
                continue;
            }

            $prepared[] = [
                'id' => $id,
                'src' => $src,
                'alt' => $alt,
                'label' => $label !== '' ? $label : null,
                'is_default' => false,
            ];
        }

        return $prepared;
    }

    private function resolveAssetSource(string $current, array $asset): string
    {
        if ($current !== '') {
            return $current;
        }

        if (isset($asset['o:original_url']) && is_string($asset['o:original_url'])) {
            return $asset['o:original_url'];
        }

        if (isset($asset['o:thumbnail_urls']) && is_array($asset['o:thumbnail_urls'])) {
            foreach (['large', 'medium', 'square', 'original'] as $key) {
                if (isset($asset['o:thumbnail_urls'][$key]) && is_string($asset['o:thumbnail_urls'][$key])) {
                    return $asset['o:thumbnail_urls'][$key];
                }
            }
        }

        if (isset($asset['@id']) && is_string($asset['@id'])) {
            return $asset['@id'];
        }

        if (isset($asset['@value']) && is_string($asset['@value'])) {
            return $asset['@value'];
        }

        return '';
    }

    /**
     * @return array<int,array{id: ?int, src: string, alt: string, label: ?string, is_default: bool}>
     */
    private function prepareDefaultLogoData(PhpRenderer $renderer): array
    {
        $defaults = [];
        foreach (self::DEFAULT_LOGOS as $filename) {
            $src = $renderer->assetUrl('img/' . ltrim($filename, '/'), 'GlobalLandingPage');
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $label = ucwords(str_replace(['-', '_'], ' ', $basename));
            $defaults[] = [
                'id' => null,
                'src' => $src,
                'alt' => $label,
                'label' => $label,
                'is_default' => true,
            ];
        }

        return $defaults;
    }

    /**
     * Ensure the index template map reflects the current override state.
     *
     * @param object|null $resolver
     */
    private function configureTemplateOverride($resolver, bool $enabled): void
    {
        if (!$resolver instanceof TemplateMapResolver) {
            return;
        }

        $map = $resolver->getMap();
        if (!is_array($map)) {
            $map = [];
        }

        if ($enabled) {
            $map[self::TEMPLATE_ALIAS] = self::TEMPLATE_PATH;
        } elseif (isset($map[self::TEMPLATE_ALIAS])) {
            $currentPath = $map[self::TEMPLATE_ALIAS];
            if (is_string($currentPath) && realpath($currentPath) === realpath(self::TEMPLATE_PATH)) {
                unset($map[self::TEMPLATE_ALIAS]);
            }
        }

        $resolver->setMap($map);
    }

    /**
     * Ensure the template path stack includes the module's view path when enabled.
     *
     * @param object|null $templatePathStack
     */
    private function configureTemplatePathStack($templatePathStack, bool $enabled): void
    {
        if (!$templatePathStack instanceof TemplatePathStack) {
            return;
        }

        $moduleViewPath = realpath(__DIR__ . '/view') ?: __DIR__ . '/view';
        $normalizedTarget = realpath($moduleViewPath) ?: rtrim($moduleViewPath, DIRECTORY_SEPARATOR);
        $existingPaths = $this->collectTemplatePaths($templatePathStack);

        if ($enabled) {
            foreach ($existingPaths as $existingPath) {
                $normalizedExisting = realpath($existingPath) ?: rtrim($existingPath, DIRECTORY_SEPARATOR);
                if ($normalizedExisting === $normalizedTarget) {
                    return;
                }
            }

            $templatePathStack->addPath($moduleViewPath);
            return;
        }

        if ($existingPaths === []) {
            return;
        }

        $filteredPaths = [];
        $modified = false;

        foreach ($existingPaths as $existingPath) {
            $normalizedExisting = realpath($existingPath) ?: rtrim($existingPath, DIRECTORY_SEPARATOR);
            if ($normalizedExisting === $normalizedTarget) {
                $modified = true;
                continue;
            }
            $filteredPaths[] = $existingPath;
        }

        if (!$modified) {
            return;
        }

        if (method_exists($templatePathStack, 'setPaths')) {
            $templatePathStack->setPaths($filteredPaths);
            return;
        }

        if (method_exists($templatePathStack, 'clearPaths')) {
            $templatePathStack->clearPaths();
            foreach ($filteredPaths as $path) {
                $templatePathStack->addPath($path);
            }
        }
    }

    /**
     * @return string[]
     */
    private function collectTemplatePaths(TemplatePathStack $templatePathStack): array
    {
        if (method_exists($templatePathStack, 'getPaths')) {
            $paths = $templatePathStack->getPaths();
            return $this->normalizeTemplatePaths($paths);
        }

        if ($templatePathStack instanceof \Traversable) {
            return $this->normalizeTemplatePaths($templatePathStack);
        }

        return [];
    }

    /**
     * @param iterable<string|mixed>|array<string|mixed> $paths
     * @return string[]
     */
    private function normalizeTemplatePaths($paths): array
    {
        if (is_array($paths)) {
            $iterable = $paths;
        } elseif ($paths instanceof \Traversable) {
            $iterable = $paths;
            if ($paths instanceof \SplPriorityQueue) {
                $clone = clone $paths;
                $clone->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
                $iterable = $clone;
            }
        } else {
            return [];
        }

        $normalized = [];
        foreach ($iterable as $key => $value) {
            if (is_string($value) && $value !== '') {
                $normalized[] = $value;
                continue;
            }

            if (is_string($key) && $key !== '') {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }
}
