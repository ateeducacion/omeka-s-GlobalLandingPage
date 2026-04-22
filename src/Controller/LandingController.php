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

        $viewModel = new ViewModel([
            'headline' => 'Servicio Mediateca', // @translate
            'lead' => 'Repositorio de medios audiovisuales educativos.', // @translate
            'primaryActionLabel' => 'Explore Collections', // @translate
            'primaryActionUrl' => '#collections',
        ]);

        $viewModel->setTemplate('omeka/index/index');

        return $viewModel;
    }
}
