<?php
declare(strict_types=1);

namespace GlobalLandingPage\Controller;

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

        $recentItems = [];
        try {
            $response = $this->api()->search('items', [
                'sort_by' => 'created',
                'sort_order' => 'desc',
                'limit' => 8,
                'in_sites' => true
            ]);
            $recentItems = $response->getContent();
        } catch (\Exception $exception) {
            $recentItems = [];
        }

        // Fetch featured sites
        // TODO: Add a settings field to select featured sites
        $featuredSitesIds = [];
        $featuredSites = [];
        try {
            $response = $this->api()->search('sites', [
                'sort_by' => 'title',
                'sort_order' => 'asc',
            ]);
            $allSites = $response->getContent();
            
            // Filter sites that begin with 'Área'
            foreach ($allSites as $site) {
                //if (in_array($site->Id(),$featuredSitesIds)) {
                    $featuredSites[] = $site;
               // }
            }
        } catch (\Exception $exception) {
            $featuredSites = [];
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
