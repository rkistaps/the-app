<?php

use League\Plates\Template\Template;

/** @var Template $this */
/** @var string $title */

$this->layout('default');

echo 'Hello: ' . $title;

