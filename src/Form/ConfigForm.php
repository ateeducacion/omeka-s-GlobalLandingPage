<?php
declare(strict_types=1);

namespace GlobalLandingPage\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Color;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Regex as RegexValidator;
use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element\SiteSelect;
use Omeka\Settings\Settings;

class ConfigForm extends Form
{
    private const COLOR_REGEX = '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/';

    private ?ApiManager $apiManager = null;
    private ?Settings $settings = null;

    public function setApiManager(ApiManager $apiManager): void
    {
        $this->apiManager = $apiManager;
    }

    public function setSettings(Settings $settings): void
    {
        $this->settings = $settings;
    }

    public function init(): void
    {

        $this->resolveApiManager();
        $featuredSiteOptions = $this->buildFeaturedSiteOptions();

        if (method_exists($this, 'setName')) {
            $this->setName('globallandingpage-config');
        }
        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute('id', 'globallandingpage-config');
            $this->setAttribute('method', 'post');
            $this->setAttribute('enctype', 'multipart/form-data');
        }

        $this->add([
            'name' => 'globallandingpage_use_custom',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Use custom landing page', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'id' => 'globallandingpage_use_custom',
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_featured_sites',
            'type' => Select::class,
            'options' => [
                'label' => 'Featured sites', // @translate
                'info' => 'Select the sites displayed in the featured section.', // @translate
                'value_options' => $featuredSiteOptions,
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'id' => 'globallandingpage_featured_sites',
                'multiple' => true,
                'required' => false,
                'class' => 'select2-field',
                'data.theme' => 'classic',
                'data-placeholder' => 'Select one or more sites...', // @translate
                'style' => 'width:100%; min-height:200px;',
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_base_site',
            'type' => SiteSelect::class,
            'options' => [
                'label' => 'Base site for navigation', // @translate
                'info' => 'Choose the site that will provide the pages for the global header navigation.', // @translate
                'empty_option' => 'Select a site', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_base_site',
                'required' => false,
            ],
        ]);

        $this->configureSiteSelectElement('globallandingpage_base_site');

        $this->add([
            'name' => 'globallandingpage_nav_pages',
            'type' => Select::class,
            'options' => [
                'label' => 'Navigation pages', // @translate
                'info' => 'Pick the pages from the selected site that should '.
                        'appear in the global header navigation.', // @translate
                'empty_option' => '',
                'value_options' => [],
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'id' => 'globallandingpage_nav_pages',
                'multiple' => true,
                'required' => false,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select pages', // @translate
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_footer_html',
            'type' => Textarea::class,
            'options' => [
                'label' => 'Footer Copyright HTML', // @translate
                'info' => 'Custom HTML to render inside the footer.', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_footer_html',
                'rows' => 6,
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_show_top_bar',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Display top bar', // @translate
                'info' => 'Toggle the top bar shown above the header on the global landing page.', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'id' => 'globallandingpage_show_top_bar',
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_top_bar_logo',
            'type' => 'Omeka\Form\Element\Asset',
            'options' => [
                'label' => 'Top bar logo', // @translate
                'info' => 'Optional asset displayed inside the top bar. Leave blank to omit.', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_top_bar_logo',
                'value' => $this->getStoredTopBarLogoId() ?? '',
            ],
        ]);

        $this->addColorElement('globallandingpage_primary_color', 'Primary color');
        $this->addColorElement('globallandingpage_secondary_color', 'Secondary color');
        $this->addColorElement('globallandingpage_accent_color', 'Accent color');

        $storedLogoIds = $this->getStoredLogoIds();
        for ($index = 0; $index < 3; ++$index) {
            $fieldName = sprintf('globallandingpage_logo_%d', $index + 1);
            $this->add([
                'name' => $fieldName,
                'type' => 'Omeka\Form\Element\Asset',
                'options' => [
                    'label' => $index === 0
                        ? 'Header logo' // @translate
                        : sprintf('Header logo %d', $index + 1), // @translate
                    'info' => $index === 0
                        ? 'Choose an asset to display in the global header.' // @translate
                        : 'Optional additional logo to display alongside the first one.' // @translate
                ],
                'attributes' => [
                    'id' => $fieldName,
                    'value' => $storedLogoIds[$index] ?? '',
                ],
            ]);
        }

        if (class_exists(InputFilter::class) && method_exists($this, 'setInputFilter')) {
            $this->setInputFilter($this->buildInputFilter());
        }
        $this->populateNavigationPagesOptions($this->getStoredBaseSiteId());
    }

    /**
     * Build the option list for the featured sites selector.
     *
     * @return array<int,string>
     */
    private function buildFeaturedSiteOptions(): array
    {
        $apiManager = $this->resolveApiManager();
        if (!$apiManager) {
            return [];
        }

        try {
            $response = $apiManager->search(
                'sites',
                [
                    'sort_by' => 'title',
                    'sort_order' => 'asc',
                ]
            );
            $sites = $response->getContent();
        } catch (\Exception $exception) {
            return [];
        }

        $options = [];
        foreach ($sites as $site) {
            $id = method_exists($site, 'id')
                ? (int) $site->id()
                : (int) ($site['o:id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $title = method_exists($site, 'title')
                ? (string) $site->title()
                : (string) ($site['o:title'] ?? '');
            $title = trim($title) !== '' ? $title : sprintf('Site #%d', $id);

            $options[$id] = $title;
        }

        if ($options !== []) {
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $options;
    }

    /**
     * Populate navigation page options based on the selected base site.
     */
    private function populateNavigationPagesOptions(?int $siteId): void
    {
        if (!$this->has('globallandingpage_nav_pages')) {
            return;
        }

        $options = [];

        $apiManager = $this->resolveApiManager();

        if ($siteId !== null && $siteId > 0 && $apiManager) {
            try {
                $response = $apiManager->search(
                    'site_pages',
                    [
                        'site_id' => $siteId,
                        'sort_by' => 'position',
                        'sort_order' => 'asc',
                        'per_page' => 0,
                    ]
                );
                $pages = $response->getContent();
                foreach ($pages as $pageRepresentation) {
                    $slug = method_exists($pageRepresentation, 'slug')
                        ? $pageRepresentation->slug()
                        : ($pageRepresentation['o:slug'] ?? null);
                    $title = method_exists($pageRepresentation, 'title')
                        ? $pageRepresentation->title()
                        : ($pageRepresentation['o:title'] ?? $slug);
                    if (!is_string($slug) || $slug === '') {
                        continue;
                    }
                    if (!is_string($title) || $title === '') {
                        $title = $slug;
                    }
                    $options[$slug] = $title;
                }
            } catch (\Exception $exception) {
                // Leave options empty if the API call fails.
            }
        }

        $this->get('globallandingpage_nav_pages')->setValueOptions($options);
    }

    /**
     * Override setData to ensure navigation options track the selected base site.
     *
     * @param array<string,mixed>|Traversable $data
     */
    public function setData($data)
    {
        $baseSiteId = $this->extractBaseSiteId($data);
        $this->populateNavigationPagesOptions($baseSiteId);

        return parent::setData($data);
    }

    private function extractBaseSiteId($data): ?int
    {
        if (is_array($data) && isset($data['globallandingpage_base_site'])) {
            $value = $data['globallandingpage_base_site'];
            if (is_array($value)) {
                $value = array_shift($value);
            }
            return $this->normalizeSiteIdentifier($value);
        }

        $stored = $this->settings
            ? $this->settings->get('globallandingpage_base_site')
            : null;

        return $this->normalizeSiteIdentifier($stored);
    }

    private function getStoredBaseSiteId(): ?int
    {
        if (!$this->settings) {
            return null;
        }

        $stored = $this->settings->get('globallandingpage_base_site');
        return $this->normalizeSiteIdentifier($stored);
    }

    private function getStoredTopBarLogoId(): ?int
    {
        if (!$this->settings) {
            return null;
        }

        $stored = $this->settings->get('globallandingpage_top_bar_logo');
        if (is_numeric($stored)) {
            $id = (int) $stored;
            return $id > 0 ? $id : null;
        }

        if (is_array($stored)) {
            foreach (['o:id', 'id'] as $key) {
                if (isset($stored[$key]) && is_numeric($stored[$key])) {
                    $id = (int) $stored[$key];
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return int[]
     */
    private function getStoredLogoIds(): array
    {
        if (!$this->settings) {
            return [];
        }

        $stored = $this->settings->get('globallandingpage_logos', []);
        if (!is_array($stored)) {
            return [];
        }

        $ids = [];
        foreach ($stored as $value) {
            if (is_numeric($value)) {
                $value = (int) $value;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $identifier
     */
    private function normalizeSiteIdentifier($identifier): ?int
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        if (is_numeric($identifier)) {
            $id = (int) $identifier;
            return $id > 0 ? $id : null;
        }

        $apiManager = $this->resolveApiManager();
        if (!is_string($identifier) || !$apiManager) {
            return null;
        }

        $slug = trim($identifier);
        if ($slug === '') {
            return null;
        }

        try {
            $response = $apiManager->search(
                'sites',
                [
                    'slug' => $slug,
                    'limit' => 1,
                ]
            );
            $sites = $response->getContent();
            $site = $sites[0] ?? null;
            if ($site === null) {
                return null;
            }
            return method_exists($site, 'id') ? (int) $site->id() : (int) ($site['o:id'] ?? 0);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function addColorElement(string $name, string $label): void
    {
        $this->add([
            'name' => $name,
            'type' => Color::class,
            'options' => [
                'label' => $label, // @translate
            ],
            'attributes' => [
                'id' => $name,
                'required' => false,
            ],
        ]);
    }

    private function buildInputFilter(): InputFilter
    {
        $inputFilter = new InputFilter();

        $inputFilter->add([
            'name' => 'globallandingpage_use_custom',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'globallandingpage_featured_sites',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'globallandingpage_base_site',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'globallandingpage_nav_pages',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'globallandingpage_footer_html',
            'required' => false,
            'filters' => [
                ['name' => 'Laminas\Filter\StringTrim'],
            ],
        ]);

        foreach ([
            'globallandingpage_primary_color',
            'globallandingpage_secondary_color',
            'globallandingpage_accent_color',
        ] as $colorField) {
            $inputFilter->add([
                'name' => $colorField,
                'required' => false,
                'validators' => [
                    [
                        'name' => RegexValidator::class,
                        'options' => [
                            'pattern' => self::COLOR_REGEX,
                            'messages' => [
                                RegexValidator::NOT_MATCH => 'Please provide a valid '.
                                                            'hex color (e.g. #004488).', // @translate
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $topBarFilter = new InputFilter();
        $topBarFilter->add([
            'name' => 'globallandingpage_show_top_bar',
            'required' => false,
        ]);
        $topBarFilter->add([
            'name' => 'globallandingpage_top_bar_logo',
            'required' => false,
        ]);
        $inputFilter->add($topBarFilter, 'globallandingpage_top_bar_section');

        for ($index = 0; $index < 3; ++$index) {
            $inputFilter->add([
                'name' => sprintf('globallandingpage_logo_%d', $index + 1),
                'required' => false,
            ]);
        }

        return $inputFilter;
    }

    private function resolveApiManager(): ?ApiManager
    {
        if ($this->apiManager instanceof ApiManager) {
            return $this->apiManager;
        }

        if (method_exists($this, 'getOption')) {
            $option = $this->getOption('api_manager');
            if ($option instanceof ApiManager) {
                $this->apiManager = $option;
                return $this->apiManager;
            }
        }

        return null;
    }

    private function configureSiteSelectElement(string $name): void
    {
        if (!$this->has($name)) {
            return;
        }

        $element = $this->get($name);
        if (!$element instanceof SiteSelect) {
            return;
        }

        $apiManager = $this->resolveApiManager();
        if ($apiManager && method_exists($element, 'setApiManager')) {
            $element->setApiManager($apiManager);
        }
    }
}
