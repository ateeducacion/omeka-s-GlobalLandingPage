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
    public const DEFAULT_LOGOS = [
        'logo-mediateca.svg',
        'ate_logo.png',
        'logo-cauce.png',
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
        $settings->set(self::SETTING_LOGOS, self::DEFAULT_LOGOS);
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
        $form->setApiManager($apiManager);
        $form->setSettings($settings);
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

        return $renderer->render('global-landing-page/config-form', [
            'form' => $form,
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
        $form->setApiManager($apiManager);
        $form->setSettings($settings);
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

        $existingLogos = $settings->get(self::SETTING_LOGOS, []);
        if (!is_array($existingLogos)) {
            $existingLogos = [];
        }
        $uploadedLogos = $this->storeUploadedLogos($fileData[self::SETTING_LOGOS] ?? null);
        if ($uploadedLogos !== []) {
            $existingLogos = array_values(array_unique(array_merge($existingLogos, $uploadedLogos)));
            $existingLogos = array_slice($existingLogos, 0, self::MAX_LOGO_FILES);
        }

        $settings->set(self::SETTING_USE_CUSTOM, $useCustom);
        $settings->set(self::SETTING_FEATURED_SITES, $featuredSites);
        $settings->set(self::SETTING_BASE_SITE, $baseSiteSlug);
        $settings->set(self::SETTING_NAV_PAGES, $navPages);
        $settings->set(self::SETTING_FOOTER_HTML, $footerHtml);
        $settings->set(self::SETTING_PRIMARY_COLOR, $primaryColor);
        $settings->set(self::SETTING_SECONDARY_COLOR, $secondaryColor);
        $settings->set(self::SETTING_ACCENT_COLOR, $accentColor);
        $settings->set(self::SETTING_LOGOS, array_values($existingLogos));

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
    private function storeUploadedLogos(?array $files): array
    {
        if ($files === null) {
            return [];
        }

        $uploads = $this->normalizeUploadedFiles($files);
        if ($uploads === []) {
            return [];
        }

        $targetDir = $this->getAssetPath('img');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $stored = [];
        foreach ($uploads as $upload) {
            $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $upload['tmp_name'] ?? '';
            if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
                continue;
            }

            $filename = $this->generateLogoFilename($upload['name'] ?? '', $targetDir);
            if ($filename === null) {
                continue;
            }

            $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
            if (!@move_uploaded_file($tmpName, $destination)) {
                continue;
            }

            $stored[] = $filename;
            if (count($stored) >= self::MAX_LOGO_FILES) {
                break;
            }
        }

        return $stored;
    }

    /**
     * @param array<string,mixed> $files
     * @return array<int,array<string,mixed>>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? null,
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? null,
            ];
        }

        return $normalized;
    }

    private function generateLogoFilename(?string $original, string $targetDir): ?string
    {
        if ($original === null || $original === '') {
            return null;
        }

        $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        $basename = (string) pathinfo($original, PATHINFO_FILENAME);
        $basename = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $basename) ?? '');
        $basename = trim($basename, '-');
        if ($basename === '') {
            $basename = 'logo';
        }

        $candidate = $basename;
        $suffix = 1;
        do {
            $filename = sprintf('%s.%s', $candidate, $extension);
            $path = $targetDir . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($path)) {
                return $filename;
            }
            $candidate = sprintf('%s-%d', $basename, $suffix);
            ++$suffix;
        } while ($suffix < 100);

        return sprintf('%s-%s.%s', $basename, uniqid('', true), $extension);
    }

    private function getAssetPath(string $subDir = ''): string
    {
        $base = __DIR__ . '/asset';
        if ($subDir !== '') {
            $base .= '/' . trim($subDir, '/');
        }

        return $base;
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
