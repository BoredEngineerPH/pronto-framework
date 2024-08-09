<?php
include(dirname(__DIR__).'/vendor/autoload.php');

// Config
define('CACHE_EXPIRATION', 1);
define('CACHE_PAGE', false);

$page = new Pronto\Page(dirname(__DIR__).'/resources');
$page->render();