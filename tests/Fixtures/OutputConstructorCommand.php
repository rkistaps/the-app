<?php

declare(strict_types=1);

namespace TheApp\Tests\Fixtures;

use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Interfaces\OutputInterface;

/**
 * A command class that gets the output in its constructor, as a project's command would
 */
class OutputConstructorCommand implements CommandHandlerInterface
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function handle(array $params = []): int
    {
        $this->output->writeln((string) ($params['title'] ?? ''));

        return 0;
    }
}
