<?php
declare(strict_types=1);

namespace GlobalLandingPage\Controller;

use GlobalLandingPage\Module;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class LandingController extends AbstractActionController
{
    public function indexAction(): ViewModel
    {
        $layout = $this->layout();
        if ($layout !== null) {
            $layout->setTemplate('global-landing-page/layout');
        }

        $settings = $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Settings');
        $featuredSites = $settings->get(Module::SETTING_FEATURED_SITES, []);

        $recentItems = [];
        if (!empty($featuredSites)) {
            try {
                $response = $this->api()->search('items', [
                    'sort_by' => 'created',
                    'sort_order' => 'desc',
                    'limit' => 8,
                    'site_id' => $featuredSites,
                ]);
                $recentItems = $response->getContent();
            } catch (\Exception $exception) {
                $recentItems = [];
            }
        }


        $viewModel = new ViewModel([
            'headline' => 'Servicio Mediateca', // @translate
            'lead' => 'Repositorio de medios audiovisuales educativos.', // @translate
            'primaryActionLabel' => 'Explore Collections', // @translate
            'primaryActionUrl' => '#collections',
            'recentItems' => $recentItems,
        ]);

        $viewModel->setTemplate('omeka/index/index');

        return $viewModel;
    }
}
