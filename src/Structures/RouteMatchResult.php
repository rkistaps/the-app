<?php

namespace TheApp\Structures;

class RouteMatchResult
{
    private Route $route;
    private array $parameters = [];

    public function __construct(Route $route, $parameters = [])
    {
        $this->route = $route;
        $this->parameters = $parameters;
    }

    public function setRoute(Route $route): self
    {
        $this->route = $route;
        return $this;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
