<?php

namespace TheApp\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when command-line input can't be used: an unexpected argument, a missing required option,
 * or an option value that doesn't fit its parameter type. The message is meant for the person running the command.
 */
class InvalidCommandInputException extends InvalidArgumentException
{
}
