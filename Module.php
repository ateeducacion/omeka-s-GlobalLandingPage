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
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    private const SETTING_USE_CUSTOM = 'globallandingpage_use_custom';
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
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        if (!$serviceLocator->has('Omeka\Settings')) {
            return;
        }

        /** @var Settings $settings */
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete(self::SETTING_USE_CUSTOM);
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

            $acl->allow(null, Controller\LandingController::class, 'index');
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

        $form = new ConfigForm();
        $form->init();
        $form->setData([
            self::SETTING_USE_CUSTOM => $settings->get(self::SETTING_USE_CUSTOM, false) ? '1' : '0',
        ]);

        return $renderer->render('global-landing-page/config-form', [
            'form' => $form,
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        /** @var Settings $settings */
        $settings = $services->get('Omeka\Settings');

        $form = new ConfigForm();
        $form->init();
        $form->setData($controller->params()->fromPost());

        if (!$form->isValid()) {
            $controller->messenger()->addError(new Message('Unable to save settings: submitted data is invalid.')); // @translate
            return false;
        }

        $useCustom = false;
        $data = $form->getData(FormInterface::VALUES_AS_ARRAY);

        if (isset($data[self::SETTING_USE_CUSTOM])) {
            $value = $data[self::SETTING_USE_CUSTOM];
            if (is_array($value)) {
                $value = array_pop($value);
            }
            $useCustom = (string) $value === '1';
        }

        $settings->set(self::SETTING_USE_CUSTOM, $useCustom);

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
