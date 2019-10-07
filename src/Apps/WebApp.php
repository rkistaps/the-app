<?php

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use TheApp\Interfaces\ConfigInterface;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp
{
    /** @var ConfigInterface */
    private $config;

    /**
     * WebApp constructor.
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Run app
     */
    public function run()
    {
        dd($this->config);
    }
}
