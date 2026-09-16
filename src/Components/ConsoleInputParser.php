<?php

namespace TheApp\Components;

use TheApp\Exceptions\InvalidCommandInputException;
use TheApp\Structures\ConsoleInput;

/**
 * Parses command-line arguments, such as: console.php user/greet --name=World --verbose
 *
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class ConsoleInputParser
{
    /**
     * @param string[] $argv Arguments as in PHP's $argv, where the first element is the script name
     * @throws InvalidCommandInputException When there is more than one positional argument
     */
    public function parse(array $argv): ConsoleInput
    {
        $input = new ConsoleInput();
        $commandOption = null;

        foreach (array_slice(array_values($argv), 1) as $argument) {
            if (preg_match('/^--?([^=\s-][^=\s]*)(?:=(.*))?$/s', $argument, $matches) === 1) {
                $value = array_key_exists(2, $matches) ? $matches[2] : true;
                if ($matches[1] === 'command') {
                    $commandOption = $value;
                } else {
                    $input->options[$matches[1]] = $value;
                }

                continue;
            }

            if ($input->command !== null) {
                throw new InvalidCommandInputException(sprintf('Unexpected argument "%s". Pass options as --name=value', $argument));
            }

            $input->command = $argument;
        }

        // --command=name is supported for backwards compatibility. A valueless --command selects no command.
        if ($commandOption !== null) {
            if ($input->command !== null) {
                throw new InvalidCommandInputException('Pass the command either as the first argument or with --command, not both');
            }

            $input->command = is_string($commandOption) && $commandOption !== '' ? $commandOption : null;
        }

        return $input;
    }
}
