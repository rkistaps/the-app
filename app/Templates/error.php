<?php
/** @var Throwable $throwable */

$this->layout('default');

?>

<h1>An error happened</h1>
<pre>
    <?php var_dump($throwable->getTraceAsString()); ?>
</pre>