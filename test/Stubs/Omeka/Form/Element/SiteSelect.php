<?php
declare(strict_types=1);

namespace Omeka\Form\Element;

use Laminas\Form\Element\Select;

/**
 * Minimal SiteSelect element stub that tracks the injected API manager.
 */
class SiteSelect extends Select
{
    /** @var object|null */
    private $apiManager;

    public function setApiManager(object $apiManager): void
    {
        $this->apiManager = $apiManager;
    }

    public function getApiManager(): ?object
    {
        return $this->apiManager;
    }
}
