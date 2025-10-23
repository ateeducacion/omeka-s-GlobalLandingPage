<?php
declare(strict_types=1);

namespace Laminas\Form\Element;

/**
 * Minimal Laminas\Form\Element\Checkbox stand-in for unit testing.
 */
class Checkbox
{
    private string $name;

    /** @var array<string,mixed> */
    private array $options = [];

    /** @var array<string,mixed> */
    private array $attributes = [];

    private bool $useHiddenElement = false;

    /** @var string|int|float|bool|null */
    private $checkedValue = '1';

    /** @var string|int|float|bool|null */
    private $uncheckedValue = '0';

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

        if (array_key_exists('use_hidden_element', $options)) {
            $this->useHiddenElement = (bool) $options['use_hidden_element'];
        }

        if (array_key_exists('checked_value', $options)) {
            $this->checkedValue = $options['checked_value'];
        }

        if (array_key_exists('unchecked_value', $options)) {
            $this->uncheckedValue = $options['unchecked_value'];
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes + $this->attributes;
    }

    public function useHiddenElement(): bool
    {
        return $this->useHiddenElement;
    }

    /** @return string|int|float|bool|null */
    public function getCheckedValue()
    {
        return $this->checkedValue;
    }

    /** @return string|int|float|bool|null */
    public function getUncheckedValue()
    {
        return $this->uncheckedValue;
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
