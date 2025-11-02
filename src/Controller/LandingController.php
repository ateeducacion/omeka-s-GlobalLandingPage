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
