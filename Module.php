<?php
declare(strict_types=1);

namespace GlobalLandingPage;

use DirectoryIterator;
use GlobalLandingPage\Form\ConfigForm;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use Omeka\Module\AbstractModule;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;
use Throwable;
use RuntimeException;

/**
 * Module bootstrapper.
 */
class Module extends AbstractModule
{
    private const TEMPLATE_NAME = 'omeka/index/index';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $services = $this->getServiceLocator();
        if (!$services || !$services->has('ViewTemplateMapResolver')) {
            return;
        }

        $this->applyTemplateOverride($services);
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        /** @var Settings $settings */
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->set('globallandingpage_override_enabled', false);
        $settings->set('globallandingpage_theme', '');
        $settings->delete('globallandingpage_original_template');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        if (!$serviceLocator->has('Omeka\Settings') || !$serviceLocator->has('ViewTemplateMapResolver')) {
            return;
        }

        /** @var Settings $settings */
        $settings = $serviceLocator->get('Omeka\Settings');
        /** @var TemplateMapResolver $resolver */
        $resolver = $serviceLocator->get('ViewTemplateMapResolver');

        $this->restoreOriginalTemplate($settings, $resolver);
        $settings->delete('globallandingpage_override_enabled');
        $settings->delete('globallandingpage_theme');
        $settings->delete('globallandingpage_original_template');
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->locateServices($renderer);
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');

        $themes = $this->getAvailableThemesWithTemplate($services);

        $form = new ConfigForm();
        $form->init();
        $form->setThemeOptions($this->formatThemeOptions($themes));
        $form->setData([
            'globallandingpage_override_enabled' => $settings->get('globallandingpage_override_enabled', false) ? '1' : '0',
            'globallandingpage_theme' => $settings->get('globallandingpage_theme', ''),
        ]);

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->locateServices(null, $controller);
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');

        $data = $controller->params()->fromPost();
        $enabled = !empty($data['globallandingpage_override_enabled'])
            && (string)$data['globallandingpage_override_enabled'] === '1';
        $selectedTheme = isset($data['globallandingpage_theme'])
            ? (string)$data['globallandingpage_theme']
            : '';

        $themes = $this->getAvailableThemesWithTemplate($services);
        $messenger = new Messenger();

        if ($enabled && ($selectedTheme === '' || !isset($themes[$selectedTheme]))) {
            $messenger->addError(new Message('Select a theme that supplies view/omeka/index/index.phtml before enabling the override.'));
            return false;
        }

        if ($selectedTheme !== '' && !isset($themes[$selectedTheme])) {
            $selectedTheme = '';
            $enabled = false;
            $messenger->addWarning(new Message('The selected theme no longer provides view/omeka/index/index.phtml. Override disabled.'));
        }

        $settings->set('globallandingpage_override_enabled', $enabled);
        $settings->set('globallandingpage_theme', $selectedTheme);

        $this->applyTemplateOverride($services);

        $messenger->addSuccess(new Message('Global landing page configuration saved.'));
        return true;
    }

    /**
     * Build the select options with theme labels.
     *
     * @param array<string, array{label: string, template: string}> $themes
     * @return array<string, string>
     */
    private function formatThemeOptions(array $themes): array
    {
        $options = [];
        foreach ($themes as $id => $data) {
            $label = $data['label'];
            if ($label !== $id) {
                $label = sprintf('%s (%s)', $label, $id);
            }
            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * Apply the template override based on current settings.
     */
    private function applyTemplateOverride(ServiceLocatorInterface $services): void
    {
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');
        /** @var TemplateMapResolver $resolver */
        $resolver = $services->get('ViewTemplateMapResolver');

        $enabled = (bool)$settings->get('globallandingpage_override_enabled', false);
        $selectedTheme = (string)$settings->get('globallandingpage_theme', '');
        $themes = $this->getAvailableThemesWithTemplate($services);

        if ($enabled && $selectedTheme !== '' && isset($themes[$selectedTheme])) {
            $templatePath = $themes[$selectedTheme]['template'];
            if (!is_file($templatePath)) {
                $this->restoreOriginalTemplate($settings, $resolver);
                return;
            }

            $this->storeOriginalTemplate($settings, $resolver);
            $this->setTemplatePath($resolver, $templatePath);
            return;
        }

        $this->restoreOriginalTemplate($settings, $resolver);
    }

    /**
     * Persist the original template path so we can restore it later.
     */
    private function storeOriginalTemplate(Settings $settings, TemplateMapResolver $resolver): void
    {
        $originalPath = $settings->get('globallandingpage_original_template');
        if ($originalPath !== null && $originalPath !== '') {
            return;
        }

        $current = $this->getCurrentTemplatePath($resolver);
        if ($current !== null && $current !== '') {
            $settings->set('globallandingpage_original_template', $current);
        }
    }

    /**
     * Restore the original template or remove the override.
     */
    private function restoreOriginalTemplate(Settings $settings, TemplateMapResolver $resolver): void
    {
        $original = $settings->get('globallandingpage_original_template');
        if (is_string($original) && $original !== '') {
            $this->setTemplatePath($resolver, $original);
            return;
        }

        $this->clearTemplateOverride($resolver);
    }

    /**
     * Set the template map entry.
     */
    private function setTemplatePath(TemplateMapResolver $resolver, string $path): void
    {
        if (method_exists($resolver, 'add')) {
            $resolver->add(self::TEMPLATE_NAME, $path);
            return;
        }

        $map = $this->getResolverMap($resolver);
        $map[self::TEMPLATE_NAME] = $path;
        $resolver->setMap($map);
    }

    /**
     * Remove the module override.
     */
    private function clearTemplateOverride(TemplateMapResolver $resolver): void
    {
        $map = $this->getResolverMap($resolver);
        if (isset($map[self::TEMPLATE_NAME])) {
            unset($map[self::TEMPLATE_NAME]);
            $resolver->setMap($map);
        }
    }

    private function getCurrentTemplatePath(TemplateMapResolver $resolver): ?string
    {
        $map = $this->getResolverMap($resolver);
        return $map[self::TEMPLATE_NAME] ?? null;
    }

    /**
     * Retrieve the resolver map safely.
     *
     * @return array<string, string>
     */
    private function getResolverMap(TemplateMapResolver $resolver): array
    {
        return method_exists($resolver, 'getMap') ? $resolver->getMap() : [];
    }

    /**
     * Collect themes that provide the landing page template.
     *
     * @return array<string, array{label: string, template: string}>
     */
    private function getAvailableThemesWithTemplate(ServiceLocatorInterface $services): array
    {
        $themesWithTemplate = [];

        if ($services->has('Omeka\Site\ThemeManager')) {
            $themeManager = $services->get('Omeka\Site\ThemeManager');

            foreach ($themeManager->getThemes() as $theme) {
                $themeId = $this->getThemeIdentifier($theme);
                if ($themeId === '') {
                    continue;
                }

                $templatePath = $this->resolveThemeTemplatePath($theme, $themeId);
                if (!$templatePath) {
                    continue;
                }

                $themesWithTemplate[$themeId] = [
                    'label' => $this->deriveThemeLabel($theme, $themeId),
                    'template' => $templatePath,
                ];
            }
        }

        if (empty($themesWithTemplate) && defined('OMEKA_PATH')) {
            $themesDir = OMEKA_PATH . '/themes';
            if (is_dir($themesDir)) {
                foreach (new DirectoryIterator($themesDir) as $dir) {
                    if (!$dir->isDir() || $dir->isDot()) {
                        continue;
                    }

                    $themeId = $dir->getFilename();
                    $templatePath = $dir->getPathname() . '/view/omeka/index/index.phtml';
                    if (!is_file($templatePath)) {
                        continue;
                    }

                    $themesWithTemplate[$themeId] = [
                        'label' => $this->deriveThemeLabelFromDirectory($dir->getPathname(), $themeId),
                        'template' => $templatePath,
                    ];
                }
            }
        }

        uasort($themesWithTemplate, static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        });

        return $themesWithTemplate;
    }

    /**
     * Determine the theme identifier.
     *
     * @param mixed $theme
     */
    private function getThemeIdentifier($theme): string
    {
        foreach (['getId', 'getName', 'getSlug'] as $method) {
            if (method_exists($theme, $method)) {
                /** @var mixed $value */
                $value = $theme->{$method}();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Resolve the template path for a theme object.
     *
     * @param mixed $theme
     */
    private function resolveThemeTemplatePath($theme, string $themeId): ?string
    {
        $basePath = '';
        foreach (['getPath', 'getRootPath', 'getBasePath'] as $method) {
            if (method_exists($theme, $method)) {
                /** @var mixed $value */
                $value = $theme->{$method}();
                if (is_string($value) && $value !== '') {
                    $basePath = $value;
                    break;
                }
            }
        }

        if ($basePath === '' && defined('OMEKA_PATH')) {
            $basePath = OMEKA_PATH . '/themes/' . $themeId;
        }

        $templatePath = $basePath . '/view/omeka/index/index.phtml';
        return is_file($templatePath) ? $templatePath : null;
    }

    /**
     * Derive a readable label for a theme.
     *
     * @param mixed $theme
     */
    private function deriveThemeLabel($theme, string $themeId): string
    {
        foreach (['getLabel', 'getTitle', 'getDisplayName', 'getName'] as $method) {
            if (method_exists($theme, $method)) {
                try {
                    /** @var mixed $value */
                    $value = $theme->{$method}();
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                } catch (Throwable $exception) {
                    // Ignore and continue to other methods.
                }
            }
        }

        if (method_exists($theme, 'getIni')) {
            try {
                /** @var mixed $value */
                $value = $theme->getIni('label');
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (Throwable $exception) {
                // Ignore and fall back.
            }
        }

        return $themeId;
    }

    private function deriveThemeLabelFromDirectory(string $themePath, string $themeId): string
    {
        $iniPath = $themePath . '/config/theme.ini';
        if (!is_file($iniPath)) {
            return $themeId;
        }

        $ini = @parse_ini_file($iniPath);
        if (is_array($ini)) {
            foreach (['label', 'title', 'name'] as $key) {
                if (!empty($ini[$key]) && is_string($ini[$key])) {
                    return $ini[$key];
                }
            }
        }

        return $themeId;
    }

    /**
     * Retrieve the main service manager, falling back to context-specific sources.
     */
    private function locateServices(?PhpRenderer $renderer = null, ?AbstractController $controller = null): ServiceLocatorInterface
    {
        $services = $this->getServiceLocator();
        if ($services instanceof ServiceLocatorInterface) {
            return $services;
        }

        if ($renderer !== null) {
            $helpers = $renderer->getHelperPluginManager();
            if (method_exists($helpers, 'getServiceLocator')) {
                $services = $helpers->getServiceLocator();
                if ($services instanceof ServiceLocatorInterface) {
                    $this->setServiceLocator($services);
                    return $services;
                }
            }
            if (method_exists($helpers, 'getServiceManager')) {
                $services = $helpers->getServiceManager();
                if ($services instanceof ServiceLocatorInterface) {
                    $this->setServiceLocator($services);
                    return $services;
                }
            }
        }

        if ($controller !== null) {
            if (method_exists($controller, 'getServiceLocator')) {
                $services = $controller->getServiceLocator();
                if ($services instanceof ServiceLocatorInterface) {
                    $this->setServiceLocator($services);
                    return $services;
                }
            }
            if (method_exists($controller, 'getEvent')) {
                $event = $controller->getEvent();
                if ($event && method_exists($event, 'getApplication')) {
                    $application = $event->getApplication();
                    if ($application && method_exists($application, 'getServiceManager')) {
                        $services = $application->getServiceManager();
                        if ($services instanceof ServiceLocatorInterface) {
                            $this->setServiceLocator($services);
                            return $services;
                        }
                    }
                }
            }
        }

        throw new RuntimeException('Unable to access the Omeka service locator.');
    }
}
