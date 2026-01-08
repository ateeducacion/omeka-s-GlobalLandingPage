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
                $normalizedQuery = $this->normalizeString($query);
                $sites = array_values(array_filter($sites, function ($site) use ($normalizedQuery) {
                    $title = $site !== null ? $this->normalizeString((string) $site->title()) : '';
                    $slug = $site !== null ? $this->normalizeString((string) $site->slug()) : '';
                    return mb_strpos($title, $normalizedQuery) !== false
                        || mb_strpos($slug, $normalizedQuery) !== false;
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

    /**
     * Normalize a string by converting to lowercase and removing accents/diacritical marks
     */
    private function normalizeString(string $string): string
    {
        // Convert to lowercase
        $string = mb_strtolower($string, 'UTF-8');

        // Normalize to NFD (Canonical Decomposition)
        // This separates base characters from combining diacritical marks
        $string = \Normalizer::normalize($string, \Normalizer::NFD);

        if ($string === false) {
            return '';
        }

        // Remove combining diacritical marks (Unicode category Mn)
        $string = preg_replace('/\p{Mn}/u', '', $string);

        return $string !== null ? $string : '';
    }
}
