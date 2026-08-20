<?php

require_once(__DIR__ . "/../config/Config.php");

use DockerAutonet\Config\Config;

header("Content-Type: application/json");
echo json_encode(["entries" => array_values(Config::readActivity(200))]);
