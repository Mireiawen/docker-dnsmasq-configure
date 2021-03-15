<?php
declare(strict_types = 1);

use Mireiawen\dnsconfig\Application;

require_once('vendor/autoload.php');

$filename = 'files/dnsmasq.yml';
$config = Application::LoadConfig($filename);
$app = new Application($config);
$app->Run();