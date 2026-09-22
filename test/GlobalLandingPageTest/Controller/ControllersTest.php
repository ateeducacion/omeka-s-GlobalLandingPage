<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\Controller;

use GlobalLandingPage\Controller\LandingController;
use GlobalLandingPage\Controller\SiteController;
use GlobalLandingPage\Controller\StaticPageController;
use GlobalLandingPageTest\Support\Fixtures;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\TestCase;

class ControllersTest extends TestCase
{
    use Fixtures;

    public function testLandingUsesOwnLayoutAndTemplate(): void
    {
        $layout = new ViewModel();
        $controller = $this->record(['layout' => $layout], LandingController::class);
        $view = $controller->indexAction();
        $this->assertSame('global-landing-page/layout', $layout->getTemplate());
        $this->assertSame('omeka/index/index', $view->getTemplate());
        $this->assertSame('#collections', $view->getVariables()['primaryActionUrl']);
        $withoutLayout = $this->record(['layout' => null], LandingController::class);
        $this->assertSame($view->getVariables(), $withoutLayout->indexAction()->getVariables());
    }

    /** @dataProvider searchCases */
    public function testSiteSearchNormalizesAccentsAndHandlesApiFailure(string $query, bool $failure, array $ids): void
    {
        $sites = [
            $this->record(['title' => 'Educación', 'slug' => 'canal-a', 'id' => 1]),
            $this->record(['title' => 'Science', 'slug' => 'canal-b', 'id' => 2]),
        ];
        $api = $this->methods(['search']);
        $invocation = $api->expects($this->once())->method('search')
            ->with('sites', ['sort_by' => 'title', 'sort_order' => 'asc']);
        if ($failure) {
            $invocation->willThrowException(new \RuntimeException('API unavailable'));
        } else {
            $invocation->willReturn($this->response($sites));
        }
        $controller = $this->record([
            'layout' => new ViewModel(), 'api' => $api,
            'params' => $this->record(['fromQuery' => $query]),
        ], SiteController::class);
        $view = $controller->exploreAction();
        $this->assertSame('global-landing-page/site/explore', $view->getTemplate());
        $this->assertSame($ids, array_map(function ($site) {
            return $site->id();
        }, $view->getVariables()['sites']));
        $this->assertSame($query, $view->getVariables()['query']);
    }

    public function searchCases(): array
    {
        return [['EDUCACION', false, [1]], ['canal-b', false, [2]], ['', false, [1, 2]], ['', true, []]];
    }

    /** @dataProvider staticPages */
    public function testStaticPageUsesSiteContextOrReturnsNotFound(
        string $siteSlug,
        string $pageSlug,
        bool $found
    ): void {
        $site = new \stdClass();
        $page = $this->record(['site' => $site], \Omeka\Api\Representation\SitePageRepresentation::class);
        $api = $this->methods(['search']);
        if ($siteSlug !== '' && $pageSlug !== '') {
            $api->expects($this->once())->method('search')
                ->with('site_pages', ['site' => $siteSlug, 'slug' => $pageSlug, 'limit' => 1])
                ->willReturn($this->response($found ? [$page] : []));
        } else {
            $api->expects($this->never())->method('search');
        }
        $params = $this->methods(['fromRoute']);
        $params->method('fromRoute')->willReturnMap([
            ['site-slug', '', $siteSlug], ['page-slug', '', $pageSlug],
        ]);
        $helper = $this->methods(['setSiteRepresentation']);
        $helper->expects($found ? $this->once() : $this->never())->method('setSiteRepresentation')->with($site);
        $services = $this->services(['ViewHelperManager' => $this->services(['site' => $helper])]);
        $app = $this->record(['getServiceManager' => $services]);
        $controller = $this->record([
            'layout' => new ViewModel(), 'params' => $params, 'api' => $api,
            'getEvent' => $this->record(['getApplication' => $app]), 'notFoundAction' => 'not found',
        ], StaticPageController::class);
        $view = $controller->showAction();
        if (!$found) {
            $this->assertSame('not found', $view);
            return;
        }
        $this->assertSame('global-landing-page/common/static-page', $view->getTemplate());
        $this->assertSame($page, $view->getVariables()['page']);
    }

    public function staticPages(): array
    {
        return [['site', 'page', true], ['site', 'page', false], ['', 'page', false], ['site', '', false]];
    }
}
