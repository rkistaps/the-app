<?php

namespace TheApp\Exceptions;

/**
 * Thrown when a route matches the request path but not its HTTP method.
 * It extends NoRouteMatchException, so error handlers that only check for that still work.
 */
class MethodNotAllowedException extends NoRouteMatchException
{
    /**
     * @param string[] $allowedMethods
     */
    public function __construct(private array $allowedMethods)
    {
        parent::__construct('Method not allowed. Allowed methods: ' . implode(', ', $allowedMethods));
    }

    /**
     * Methods accepted for the requested path, for the Allow response header
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
