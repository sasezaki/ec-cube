<?php


require __DIR__.'/../bootstrap.php';
$kernel = new \Eccube\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();
