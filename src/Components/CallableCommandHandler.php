<?php

namespace TheApp\Components;

use Closure;
use DI\Container;
use ReflectionFunction;
use ReflectionNamedType;
use TheApp\Exceptions\InvalidCommandInputException;
use TheApp\Interfaces\CommandHandlerInterface;

/**
 * Runs a callable command. Options are matched to the callable's parameters by name and converted to the
 * parameter's type. Parameters without a matching option get their default value or are resolved from the container.
 */
class CallableCommandHandler implements CommandHandlerInterface
{
    private const TRUE_VALUES = ['1', 'true', 'yes', 'on'];
    private const FALSE_VALUES = ['0', 'false', 'no', 'off'];

    /** @var callable */
    private $callable;
    private Container $container;

    public function __construct(
        callable $callable,
        Container $container
    ) {
        $this->callable = $callable;
        $this->container = $container;
    }

    /**
     * @param array<string, string|true> $params
     * @throws InvalidCommandInputException When a required option is missing or a value doesn't fit its type
     */
    public function handle(array $params = [])
    {
        $this->container->call($this->callable, $this->convertParams($params));
    }

    /**
     * @param array<string, string|true> $params
     * @return array<string, mixed>
     */
    private function convertParams(array $params): array
    {
        $function = new ReflectionFunction(Closure::fromCallable($this->callable));

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $builtinType = $type instanceof ReflectionNamedType && $type->isBuiltin() ? $type->getName() : null;

            if (!array_key_exists($name, $params)) {
                // Class-typed parameters are resolved from the container, so only scalar ones are required options
                if (!$parameter->isOptional() && ($type === null || $builtinType !== null)) {
                    throw new InvalidCommandInputException(sprintf('Missing required option --%s', $name));
                }

                continue;
            }

            if ($builtinType !== null) {
                $params[$name] = $this->convertValue($name, $params[$name], $builtinType);
            }
        }

        return $params;
    }

    private function convertValue(string $name, string|bool $value, string $type): mixed
    {
        switch ($type) {
            case 'bool':
                if ($value === true || in_array(strtolower((string) $value), self::TRUE_VALUES, true)) {
                    return true;
                }
                if (in_array(strtolower((string) $value), self::FALSE_VALUES, true)) {
                    return false;
                }
                throw new InvalidCommandInputException(sprintf('Option --%s expects true or false, got "%s"', $name, $value));

            case 'int':
                if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    return (int) $value;
                }
                throw new InvalidCommandInputException(sprintf('Option --%s expects an integer', $name));

            case 'float':
                if (is_string($value) && is_numeric($value)) {
                    return (float) $value;
                }
                throw new InvalidCommandInputException(sprintf('Option --%s expects a number', $name));

            case 'string':
                if ($value === true) {
                    throw new InvalidCommandInputException(sprintf('Option --%s needs a value, as in --%s=value', $name, $name));
                }
                return $value;

            default:
                return $value;
        }
    }
}
