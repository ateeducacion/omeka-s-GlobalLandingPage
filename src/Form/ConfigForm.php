<?php
declare(strict_types=1);

namespace GlobalLandingPage\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Color;
use Laminas\Form\Element\File;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Textarea;
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
        parent::init();

        $this->setName('globallandingpage-config');
        $this->setAttribute('id', 'globallandingpage-config');
        $this->setAttribute('method', 'post');
        $this->setAttribute('enctype', 'multipart/form-data');

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
            'type' => SiteSelect::class,
            'options' => [
                'label' => 'Featured sites', // @translate
                'info' => 'Select the sites displayed in the featured section.', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_featured_sites',
                'multiple' => true,
                'required' => false,
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

        $this->configureSiteSelectElement('globallandingpage_featured_sites');
        $this->configureSiteSelectElement('globallandingpage_base_site');

        $this->add([
            'name' => 'globallandingpage_nav_pages',
            'type' => Select::class,
            'options' => [
                'label' => 'Navigation pages', // @translate
                'info' => 'Pick the pages from the selected site that should appear in the global header navigation.', // @translate
                'value_options' => [],
            ],
            'attributes' => [
                'id' => 'globallandingpage_nav_pages',
                'multiple' => true,
                'required' => false,
                'size' => 10,
            ],
        ]);

        $this->add([
            'name' => 'globallandingpage_footer_html',
            'type' => Textarea::class,
            'options' => [
                'label' => 'Footer HTML', // @translate
                'info' => 'Custom HTML to render inside the global footer.', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_footer_html',
                'rows' => 6,
                'required' => false,
            ],
        ]);

        $this->addColorElement('globallandingpage_primary_color', 'Primary color');
        $this->addColorElement('globallandingpage_secondary_color', 'Secondary color');
        $this->addColorElement('globallandingpage_accent_color', 'Accent color');

        $this->add([
            'name' => 'globallandingpage_logos',
            'type' => File::class,
            'options' => [
                'label' => 'Header logos', // @translate
                'info' => 'Upload up to three logos. Accepted formats: SVG, PNG, JPG, GIF.', // @translate
            ],
            'attributes' => [
                'id' => 'globallandingpage_logos',
                'multiple' => true,
                'accept' => 'image/*',
            ],
        ]);

        $this->setInputFilter($this->buildInputFilter());
        $this->populateNavigationPagesOptions($this->getStoredBaseSiteId());
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

        if ($siteId !== null && $siteId > 0 && $this->apiManager) {
            try {
                $response = $this->apiManager->search(
                    'site_pages',
                    [
                        'site_id' => $siteId,
                        'sort_by' => 'position',
                        'sort_order' => 'asc',
                        'limit' => 0,
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

        if (!is_string($identifier) || !$this->apiManager) {
            return null;
        }

        $slug = trim($identifier);
        if ($slug === '') {
            return null;
        }

        try {
            $response = $this->apiManager->search(
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
                                RegexValidator::NOT_MATCH => 'Please provide a valid hex color (e.g. #004488).', // @translate
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $inputFilter->add([
            'name' => 'globallandingpage_logos',
            'required' => false,
            'type' => 'Laminas\InputFilter\FileInput',
            'validators' => [
                [
                    'name' => 'Laminas\Validator\File\Count',
                    'options' => [
                        'max' => 3,
                    ],
                ],
                [
                    'name' => 'Laminas\Validator\File\Extension',
                    'options' => [
                        'extension' => ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'],
                        'case' => false,
                    ],
                ],
                [
                    'name' => 'Laminas\Validator\File\Size',
                    'options' => [
                        'max' => '4MB',
                    ],
                ],
            ],
        ]);

        return $inputFilter;
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

        if ($this->apiManager && method_exists($element, 'setApiManager')) {
            $element->setApiManager($this->apiManager);
        }
    }
}
