<?php

namespace rkistaps\Factories;

use League\Plates\Engine;
use TheApp\Interfaces\ConfigInterface;

/**
 * Class TemplateEngineFactory
 * @package rkistaps\Factories
 */
class TemplateEngineFactory
{
    /**
     * @param ConfigInterface $config
     * @return Engine
     */
    public function fromConfig(ConfigInterface $config)
    {
        return new Engine($config->get('templatePath'));
    }
}
