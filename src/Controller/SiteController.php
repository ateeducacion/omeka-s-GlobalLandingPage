<?php
declare(strict_types=1);

namespace GlobalLandingPage\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class SiteController extends AbstractActionController
{
    public function exploreAction(): ViewModel
    {
        $layout = $this->layout();
        if ($layout !== null) {
            $layout->setTemplate('global-landing-page/layout');
        }

        // Get search query from request
        $query = $this->params()->fromQuery('q', '');

        // Fetch all sites
        $sites = [];
        try {
            $searchParams = [
                'sort_by' => 'title',
                'sort_order' => 'asc',
            ];

            // If search query exists, filter by title
            if ($query !== '') {
                $searchParams['title'] = $query;
            }

            $response = $this->api()->search('sites', $searchParams);
            $sites = $response->getContent();
        } catch (\Exception $exception) {
            $sites = [];
        }

        $viewModel = new ViewModel([
            'sites' => $sites,
            'query' => $query,
        ]);

        $viewModel->setTemplate('global-landing-page/site/explore');

        return $viewModel;
    }
}
