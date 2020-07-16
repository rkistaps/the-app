<?php

namespace TheApp\Apps;

use samejack\PHP\ArgvParser;
use TheApp\Components\CommandRunner;

class ConsoleApp
{
    private CommandRunner $commandRunner;
    private ArgvParser $argvParser;

    public function __construct(
        CommandRunner $commandRunner,
        ArgvParser $argvParser
    ) {
        $this->commandRunner = $commandRunner;
        $this->argvParser = $argvParser;
    }

    /**
     * @param array|string $argv
     */
    public function run($argv)
    {
        $params = $this->argvParser->parseConfigs($argv);
        $commandName = $params['command'] ?? null;
        $command = $this->commandRunner->findCommandByName($commandName);
        if (!$command) {
            echo 'Command not found' . PHP_EOL;
            return;
        }

        unset($params['command']);

        $this->commandRunner->runCommand($command, $params);
    }
}
