#!/usr/bin/php
<?php

require_once(__DIR__ . "/../config/Config.php");
require_once(__DIR__ . "/Reconciler.php");

use DockerAutonet\Config\Config;
use DockerAutonet\Service\Reconciler;

// Cron fires every minute regardless of the configured rescan interval - this
// throttle is what actually makes "rescan every N seconds" true, without
// needing to regenerate the crontab whenever the setting changes.
$lastRunPath = Config::CONFIG_DIR . "/last_run";
$now = time();
$lastRun = file_exists($lastRunPath) ? (int)file_get_contents($lastRunPath) : 0;

$config = Config::load();
if ($now - $lastRun < $config["rescan_seconds"]) {
    exit;
}

if (!is_dir(Config::CONFIG_DIR)) {
    mkdir(Config::CONFIG_DIR, 0755, true);
}
file_put_contents($lastRunPath, (string)$now);

$reconciler = new Reconciler($config, dryRun: false);
$reconciler->run();
