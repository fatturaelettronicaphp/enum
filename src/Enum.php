<?php

declare(strict_types=1);

namespace Spatie\Enum;

use TypeError;
use ReflectionClass;
use JsonSerializable;
use ReflectionMethod;

abstract class Enum implements JsonSerializable
{
    /** @var array */
    protected static $cache = [];

    /** @var array */
    protected static $map = [];

    /** @var string */
    protected $value;

    /**
     * @param string $value
     *
     * @return static
     */
    public static function from(string $value): Enum
    {
        if (method_exists(static::class, $value)) {
            return forward_static_call(static::class.'::'.$value);
        }

        return new static($value);
    }

    public function __construct(string $value = null)
    {
        if ($value === null) {
            $value = $this->resolveValueFromStaticCall();
        }

        $enumValues = self::resolve();

        if (isset($enumValues[strtolower($value)])) {
            $value = $enumValues[strtolower($value)];
        }

        if (! in_array($value, $enumValues)) {
            throw new TypeError("Value {$value} not available in enum ".static::class);
        }

        if ($value === null) {
            throw new TypeError("Value of enum can't be null");
        }

        $this->value = $value;
    }

    public static function __callStatic($name, $arguments)
    {
        if (strlen($name) > 2 && strpos($name, 'is') === 0) {
            if (! isset($arguments[0])) {
                throw new \ArgumentCountError(sprintf('Calling %s::%s() in static context requires one argument', static::class, $name));
            }

            return static::from($arguments[0])->$name();
        }

        if (! isset(self::resolve()[strtolower($name)])) {
            throw new TypeError("Method {$name} not available in enum ".static::class);
        }

        return new static($name);
    }

    public function __call($name, $arguments)
    {
        if (strlen($name) > 2 && strpos($name, 'is') === 0) {
            return $this->equals(substr($name, 2));
        }

        if (isset(self::resolve()[strtolower($name)])) {
            return static::__callStatic($name, $arguments);
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s->%s()', static::class, $name));
    }

    /**
     * @param string|\Spatie\Enum\Enum $enum
     *
     * @return bool
     */
    public function equals($enum): bool
    {
        if (is_string($enum)) {
            $enum = static::from($enum);
        }

        if (! $enum instanceof $this) {
            return false;
        }

        if ($enum->value !== $this->value) {
            return false;
        }

        return true;
    }

    /**
     * @param string[]|\Spatie\Enum\Enum[] $enums
     *
     * @return bool
     */
    public function isOneOf(array $enums): bool
    {
        foreach ($enums as $enum) {
            if ($this->equals($enum)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize()
    {
        return $this->value;
    }

    public static function toArray(): array
    {
        return self::resolve();
    }

    public static function getKeys(): array
    {
        return array_keys(self::resolve());
    }

    public static function getValues(): array
    {
        return array_values(self::resolve());
    }

    protected static function resolve(): array
    {
        $enumValues = [];

        $class = static::class;

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $staticReflection = new ReflectionClass(static::class);

        foreach (self::resolveValuesFromStaticMethods($staticReflection) as $value => $name) {
            $enumValues[$value] = $name;
        }

        foreach (self::resolveFromDocblocks($staticReflection) as $value => $name) {
            $enumValues[$value] = $name;
        }

        return self::$cache[$class] = $enumValues;
    }

    protected static function resolveValuesFromStaticMethods(ReflectionClass $staticReflection): array
    {
        $enumValues = [];
        foreach ($staticReflection->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $methodName = $method->getName();
            $enumValues[strtolower($methodName)] = static::$map[$methodName] ?? $methodName;
        }

        return $enumValues;
    }

    protected static function resolveFromDocblocks(ReflectionClass $staticReflection): array
    {
        $enumValues = [];

        $docComment = $staticReflection->getDocComment();

        if (! $docComment) {
            return $enumValues;
        }

        preg_match_all('/\@method static self ([\w]+)\(\)/', $docComment, $matches);

        foreach ($matches[1] ?? [] as $valueName) {
            $enumValues[strtolower($valueName)] = static::$map[$valueName] ?? $valueName;
        }

        return $enumValues;
    }

    protected function resolveValueFromStaticCall(): ?string
    {
        if (strpos(get_class($this), 'class@anonymous') === 0) {
            $backtrace = debug_backtrace();

            return $backtrace[2]['function'];
        }

        return null;
    }
}
