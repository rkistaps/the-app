<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

use TheApp\Components\CommandRunner;

interface CommandConfiguratorInterface
{
    public function configureCommands(CommandRunner $commandRunner): void;
}
