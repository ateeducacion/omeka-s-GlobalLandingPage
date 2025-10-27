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

            $response = $this->api()->search('sites', $searchParams);
            $sites = $response->getContent();

            if ($query !== '') {
                $lowerQuery = mb_strtolower($query);
                $sites = array_values(array_filter($sites, static function ($site) use ($lowerQuery) {
                    $title = $site !== null ? mb_strtolower((string) $site->title()) : '';
                    $slug = $site !== null ? mb_strtolower((string) $site->slug()) : '';
                    return mb_strpos($title, $lowerQuery) !== false || mb_strpos($slug, $lowerQuery) !== false;
                }));
            }
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
