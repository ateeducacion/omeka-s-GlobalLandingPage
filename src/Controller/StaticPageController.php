<?php
declare(strict_types=1);

namespace GlobalLandingPage\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\SitePageRepresentation;

class StaticPageController extends AbstractActionController
{
    public function showAction()
    {
        $layout = $this->layout();
        if ($layout !== null) {
            $layout->setTemplate('global-landing-page/layout');
        }

        $siteSlug = (string) $this->params()->fromRoute('site-slug', '');
        $pageSlug = (string) $this->params()->fromRoute('page-slug', '');

        if ($siteSlug === '' || $pageSlug === '') {
            return $this->notFoundAction();
        }

        try {
            $response = $this->api()->search('site_pages', [
                'site' => $siteSlug,
                'slug' => $pageSlug,
                'limit' => 1,
            ]);
            /** @var SitePageRepresentation|null $page */
            $page = $response->getContent()[0] ?? null;
        } catch (\Exception $exception) {
            $page = null;
        }

        if (!$page instanceof SitePageRepresentation) {
            return $this->notFoundAction();
        }

        $services = $this->getEvent()->getApplication()->getServiceManager();
        /** @var \Laminas\View\HelperPluginManager $viewHelpers */
        $viewHelpers = $services->get('ViewHelperManager');
        if ($viewHelpers->has('site')) {
            $siteHelper = $viewHelpers->get('site');
            if (method_exists($siteHelper, 'setSiteRepresentation')) {
                $siteHelper->setSiteRepresentation($page->site());
            }
        }
        $viewModel = new ViewModel([
            'page' => $page,
        ]);
        $viewModel->setTemplate('global-landing-page/common/static-page');

        return $viewModel;
    }
}
