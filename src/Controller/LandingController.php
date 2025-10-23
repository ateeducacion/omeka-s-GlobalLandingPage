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

        // Fetch 10 most recent items
        $recentItems = [];
        try {
            $response = $this->api()->search('items', [
                'sort_by' => 'created',
                'sort_order' => 'desc',
                'limit' => 8,
            ]);
            $recentItems = $response->getContent();
        } catch (\Exception $exception) {
            $recentItems = [];
        }

        // Fetch featured sites (sites with names beginning with 'Área')
        $featuredSites = [];
        try {
            $response = $this->api()->search('sites', [
                'sort_by' => 'title',
                'sort_order' => 'asc',
            ]);
            $allSites = $response->getContent();
            
            // Filter sites that begin with 'Área'
            foreach ($allSites as $site) {
                $title = method_exists($site, 'title') ? $site->title() : '';
                if (stripos($title, 'Área') === 0) {
                    $featuredSites[] = $site;
                }
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
            'featuredSites' => $featuredSites,
        ]);

        $viewModel->setTemplate('omeka/index/index');

        return $viewModel;
    }
}
