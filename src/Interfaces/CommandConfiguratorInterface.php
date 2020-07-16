<?php

namespace TheApp\Interfaces;

use TheApp\Components\CommandRunner;

interface CommandConfiguratorInterface
{
    public function configureCommands(CommandRunner $commandRunner);
}
