<?php

declare(strict_types=1);

namespace TheApp\Components\Output;

use RuntimeException;
use TheApp\Interfaces\OutputInterface;

/**
 * Writes to streams, by default standard output and standard error. ConsoleApp uses it unless it's given another.
 */
class StreamOutput implements OutputInterface
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout Defaults to php://output, the channel echo writes to, so this output and echo
     *     keep their order, and output buffering applies to both
     * @param resource|null $stderr Defaults to php://stderr
     */
    public function __construct(mixed $stdout = null, mixed $stderr = null)
    {
        $this->stdout = $stdout ?? self::open('php://output');
        $this->stderr = $stderr ?? self::open('php://stderr');
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function writeln(string $line = ''): void
    {
        fwrite($this->stdout, $line . PHP_EOL);
    }

    public function error(string $line): void
    {
        fwrite($this->stderr, $line . PHP_EOL);
    }

    /**
     * @return resource
     */
    private static function open(string $path)
    {
        $stream = fopen($path, 'w');
        if ($stream === false) {
            throw new RuntimeException('Cannot open ' . $path);
        }

        return $stream;
    }
}
