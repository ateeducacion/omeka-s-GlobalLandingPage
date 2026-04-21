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
            $collected = [];
            foreach ($featuredSites as $siteId) {
                $siteId = (int) $siteId;
                if ($siteId <= 0) {
                    continue;
                }
                try {
                    $response = $this->api()->search('items', [
                        'sort_by' => 'created',
                        'sort_order' => 'desc',
                        'limit' => 8,
                        'site_id' => $siteId,
                    ]);
                    foreach ($response->getContent() as $item) {
                        $collected[$item->id()] = $item;
                    }
                } catch (\Exception $exception) {
                    error_log('[GlobalLandingPage] site_id=' . $siteId . ' failed: ' . $exception->getMessage());
                }
            }
            // Fallback for Omeka versions where site_id pool query yields nothing
            if (empty($collected)) {
                try {
                    $response = $this->api()->search('items', [
                        'sort_by' => 'created',
                        'sort_order' => 'desc',
                        'limit' => 8,
                        'in_sites' => true,
                    ]);
                    foreach ($response->getContent() as $item) {
                        $collected[$item->id()] = $item;
                    }
                } catch (\Exception $exception) {
                    error_log('[GlobalLandingPage] in_sites fallback failed: ' . $exception->getMessage());
                }
            }
            // Sort merged results by created date descending and take top 8
            usort($collected, fn($a, $b) => $b->created() <=> $a->created());
            $recentItems = array_slice(array_values($collected), 0, 8);
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
