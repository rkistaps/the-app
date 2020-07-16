<?php

namespace TheApp\Interfaces;

interface CommandHandlerInterface
{
    public function handle(array $params = []);
}