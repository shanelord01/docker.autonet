<?php

require_once(__DIR__ . "/../config/Config.php");
require_once(__DIR__ . "/Reconciler.php");

use DockerAutonet\Config\Config;
use DockerAutonet\Service\Reconciler;

$config = Config::load();
$reconciler = new Reconciler($config, dryRun: true);
$actions = $reconciler->run();

header("Content-Type: application/json");
echo json_encode(["actions" => $actions]);
