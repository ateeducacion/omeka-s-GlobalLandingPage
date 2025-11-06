<?php
declare(strict_types=1);

namespace Omeka\Form\Element;

/**
 * Minimal Asset element stub used for form configuration tests.
 */
class Asset
{
    private string $name;

    /** @var array<string,mixed> */
    private array $options = [];

    /** @var array<string,mixed> */
    private array $attributes = [];

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    /**
     * @param array<string,mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options + $this->options;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes + $this->attributes;
    }
}
