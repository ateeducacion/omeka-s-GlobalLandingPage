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
            'headline' => 'Welcome to Omeka S', // @translate
            'lead' => 'Use this module to ship a bespoke landing page without depending on a site theme.', // @translate
            'primaryActionLabel' => 'Explore Collections', // @translate
            'primaryActionUrl' => '#collections',
        ]);

        $viewModel->setTemplate('omeka/index/index');

        return $viewModel;
    }
}
