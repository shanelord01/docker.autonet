<?php

require_once(__DIR__ . "/../config/Config.php");
require_once(__DIR__ . "/Reconciler.php");

use DockerAutonet\Config\Config;
use DockerAutonet\Service\Reconciler;

$config = Config::load();

if (empty($config["mappings"])) {
    echo '<p align="center" style="font-style:italic;padding-top:10px;">No network mappings configured.</p>';
    exit;
}

$reconciler = new Reconciler($config, dryRun: true);
$byNetwork = $reconciler->status();

foreach ($config["mappings"] as $mapping) {
    $net = $mapping["network"];
    $entries = $byNetwork[$net] ?? [];

    echo '<div class="autonet-network-row">';
    echo '<div class="autonet-network-name">' . htmlspecialchars($net) . '</div>';

    if (empty($entries)) {
        echo '<div class="autonet-empty">No containers</div>';
    } else {
        foreach ($entries as $entry) {
            $dotClass = $entry["status"] === "connected" ? "autonet-dot-connected" : "autonet-dot-pending";
            echo '<div class="autonet-row">';
            echo '<span class="autonet-dot ' . $dotClass . '"></span>';
            echo '<span class="autonet-container">' . htmlspecialchars($entry["container"]) . '</span>';
            echo '<span class="autonet-alias">' . htmlspecialchars($entry["alias"]) . '</span>';
            echo '</div>';
        }
    }
    echo '</div>';
}
