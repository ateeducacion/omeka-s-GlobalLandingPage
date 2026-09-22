<?php
declare(strict_types=1);

namespace GlobalLandingPageTest\Support;

trait Fixtures
{
    private function methods(array $names, string $class = \stdClass::class)
    {
        return $this->getMockBuilder($class)->addMethods($names)->getMock();
    }

    private function record(array $values, string $class = \stdClass::class)
    {
        $mock = $this->methods(array_keys($values), $class);
        foreach ($values as $name => $value) {
            $mock->method($name)->willReturn($value);
        }
        return $mock;
    }

    private function services(array $values)
    {
        $services = $this->createMock(\Laminas\ServiceManager\ServiceLocatorInterface::class);
        $services->method('has')->willReturnCallback(function ($key) use ($values) {
            return array_key_exists($key, $values);
        });
        $services->method('get')->willReturnCallback(function ($key) use ($values) {
            if (!array_key_exists($key, $values)) {
                throw new \LogicException('Unexpected service: ' . $key);
            }
            return $values[$key];
        });
        return $services;
    }

    private function response($content)
    {
        return $this->record(['getContent' => $content]);
    }

    private function settings(array $values = [])
    {
        $settings = new \Omeka\Settings\Settings();
        $settings->values = $values;
        return $settings;
    }
}
