<?php
declare(strict_types=1);

namespace Laminas\Form\Element;

/**
 * Lightweight Select element stand-in used for unit testing without Laminas.
 */
class Select
{
    private string $name;

    /** @var array<string,mixed> */
    private array $options = [];

    /** @var array<string,mixed> */
    private array $attributes = [];

    /** @var array<mixed> */
    private array $valueOptions = [];

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

        if (isset($options['value_options']) && is_array($options['value_options'])) {
            $this->valueOptions = $options['value_options'];
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes + $this->attributes;
    }

    /**
     * @param array<mixed> $valueOptions
     */
    public function setValueOptions(array $valueOptions): void
    {
        $this->valueOptions = $valueOptions;
    }

    /**
     * @return array<mixed>
     */
    public function getValueOptions(): array
    {
        return $this->valueOptions;
    }
}
