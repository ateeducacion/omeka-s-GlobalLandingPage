<?php
declare(strict_types=1);

namespace Laminas\Form;

/**
 * Lightweight Laminas\Form\Form replacement for unit testing without the framework.
 */
class Form
{
    /** @var array<string,object> */
    private array $elements = [];

    /**
     * @param array<string,mixed> $spec
     */
    public function add(array $spec): void
    {
        if (!isset($spec['name']) || !is_string($spec['name']) || $spec['name'] === '') {
            throw new \InvalidArgumentException('Form elements must define a non-empty name.');
        }

        $name = $spec['name'];
        $type = $spec['type'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new \InvalidArgumentException(sprintf('Element "%s" must define a class type.', $name));
        }

        if (!class_exists($type)) {
            throw new \RuntimeException(sprintf('Element class "%s" cannot be autoloaded.', $type));
        }

        $element = new $type($name);

        if (isset($spec['options']) && is_array($spec['options']) && method_exists($element, 'setOptions')) {
            $element->setOptions($spec['options']);
        }

        if (isset($spec['attributes']) && is_array($spec['attributes']) && method_exists($element, 'setAttributes')) {
            $element->setAttributes($spec['attributes']);
        }

        $this->elements[$name] = $element;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->elements);
    }

    /**
     * @throws \InvalidArgumentException when the element is not found.
     */
    public function get(string $name): object
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('Element "%s" is not registered in the form.', $name));
        }

        return $this->elements[$name];
    }
}
