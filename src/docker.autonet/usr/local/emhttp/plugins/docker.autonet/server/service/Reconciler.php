<?php

namespace DockerAutonet\Service;

require_once(__DIR__ . "/../config/Config.php");

use DockerAutonet\Config\Config;

class Reconciler
{
    private array $config;
    private bool $dryRun;
    private array $actions = [];

    public function __construct(array $config, bool $dryRun = false)
    {
        $this->config = $config;
        $this->dryRun = $dryRun;
    }

    private function exec(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command . " 2>&1", $output, $exitCode);
        return ["exitCode" => $exitCode, "output" => trim(implode("\n", $output))];
    }

    private function log(string $message): void
    {
        $line = "[" . date("Y-m-d H:i:s") . "] " . $message;
        if (!is_dir(dirname(Config::LOG_PATH))) {
            mkdir(dirname(Config::LOG_PATH), 0755, true);
        }
        file_put_contents(Config::LOG_PATH, $line . "\n", FILE_APPEND);
    }

    /**
     * Truthy label value, matching pangolin-autonet-watcher's label_truthy().
     */
    private function labelTruthy(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $value = strtolower(trim($value));
        return !in_array($value, ["", "0", "false", "no", "off"], true);
    }

    /**
     * RFC-1123 hostname validation for a network alias, matching watcher.py's sanitise_alias().
     * Falls back to the container name when the label value isn't a valid DNS label.
     */
    private function sanitiseAlias(string $value, string $fallback): string
    {
        $value = substr(trim($value), 0, 64);
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $value)) {
            return $value;
        }
        return $fallback;
    }

    /**
     * Raw `docker inspect` output for every container on the host. Used by
     * the Containers settings tab to show current state for containers
     * that aren't yet part of any mapping, not just ones already managed.
     */
    public function allContainerAttrs(): array
    {
        return $this->inspectContainers($this->listContainerIds());
    }

    private function listContainerIds(): array
    {
        $result = $this->exec("docker ps -aq --no-trunc");
        if ($result["exitCode"] !== 0 || $result["output"] === "") {
            return [];
        }
        return explode("\n", $result["output"]);
    }

    private function inspectContainers(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $args = implode(" ", array_map("escapeshellarg", $ids));
        $result = $this->exec("docker inspect " . $args);
        if ($result["exitCode"] !== 0) {
            $this->log("Error inspecting containers: " . $result["output"]);
            return [];
        }
        $decoded = json_decode($result["output"], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function recordAction(string $type, string $container, string $network, string $alias, string $result): void
    {
        $entry = [
            "type" => $type,
            "container" => $container,
            "network" => $network,
            "alias" => $alias,
            "result" => $result,
            "dry_run" => $this->dryRun,
        ];
        $this->actions[] = $entry;
        if (!$this->dryRun) {
            Config::appendActivity($entry);
        }
    }

    private function reconcileOne(array $attrs): void
    {
        $name = ltrim($attrs["Name"] ?? "", "/");
        if ($name === "") {
            return;
        }

        $netMode = $attrs["HostConfig"]["NetworkMode"] ?? "";
        if ($netMode === "host" || str_starts_with($netMode, "container:")) {
            return;
        }

        $labels = $attrs["Config"]["Labels"] ?? [];
        $networks = $attrs["NetworkSettings"]["Networks"] ?? [];
        $aliasLabel = $this->config["alias_label"];
        $autoDisconnect = $this->config["auto_disconnect"];

        foreach ($this->config["mappings"] as $mapping) {
            $labelKey = $mapping["key"];
            $netName = $mapping["network"];

            $wantsAttach = $this->labelTruthy($labels[$labelKey] ?? null);
            $isConnected = array_key_exists($netName, $networks);
            $alias = $this->sanitiseAlias($labels[$aliasLabel] ?? $name, $name);

            if ($wantsAttach && !$isConnected) {
                if ($this->dryRun) {
                    $this->recordAction("connect", $name, $netName, $alias, "would connect");
                    continue;
                }
                $result = $this->exec("docker network connect --alias " . escapeshellarg($alias) . " "
                    . escapeshellarg($netName) . " " . escapeshellarg($name));
                if ($result["exitCode"] === 0) {
                    $this->log("Connected '$name' to '$netName' with alias '$alias'");
                    $this->recordAction("connect", $name, $netName, $alias, "connected");
                } else {
                    $this->log("Failed to connect '$name' to '$netName': " . $result["output"]);
                    $this->recordAction("connect", $name, $netName, $alias, "failed: " . $result["output"]);
                }
            } elseif (!$wantsAttach && $isConnected && $autoDisconnect) {
                if ($this->dryRun) {
                    $this->recordAction("disconnect", $name, $netName, $alias, "would disconnect");
                    continue;
                }
                $result = $this->exec("docker network disconnect " . escapeshellarg($netName) . " " . escapeshellarg($name));
                if ($result["exitCode"] === 0) {
                    $this->log("Disconnected '$name' from '$netName'");
                    $this->recordAction("disconnect", $name, $netName, $alias, "disconnected");
                } else {
                    $this->log("Failed to disconnect '$name' from '$netName': " . $result["output"]);
                    $this->recordAction("disconnect", $name, $netName, $alias, "failed: " . $result["output"]);
                }
            }
        }
    }

    /**
     * Run one reconciliation pass over every container on the host. Returns the
     * list of actions taken (or, in dry-run mode, that would have been taken).
     */
    public function run(): array
    {
        if (empty($this->config["mappings"])) {
            return [];
        }
        $ids = $this->listContainerIds();
        foreach ($this->inspectContainers($ids) as $attrs) {
            $this->reconcileOne($attrs);
        }
        return $this->actions;
    }

    /**
     * Current membership per configured network, for the dashboard widget.
     * Read-only: reports desired vs. actual state without connecting/disconnecting anything.
     */
    public function status(): array
    {
        $byNetwork = [];
        foreach ($this->config["mappings"] as $mapping) {
            $byNetwork[$mapping["network"]] = [];
        }

        $ids = $this->listContainerIds();
        foreach ($this->inspectContainers($ids) as $attrs) {
            $name = ltrim($attrs["Name"] ?? "", "/");
            if ($name === "") {
                continue;
            }
            $netMode = $attrs["HostConfig"]["NetworkMode"] ?? "";
            if ($netMode === "host" || str_starts_with($netMode, "container:")) {
                continue;
            }

            $labels = $attrs["Config"]["Labels"] ?? [];
            $networks = $attrs["NetworkSettings"]["Networks"] ?? [];
            $aliasLabel = $this->config["alias_label"];

            foreach ($this->config["mappings"] as $mapping) {
                $labelKey = $mapping["key"];
                $netName = $mapping["network"];
                $wantsAttach = $this->labelTruthy($labels[$labelKey] ?? null);
                $isConnected = array_key_exists($netName, $networks);

                if (!$wantsAttach && !$isConnected) {
                    continue;
                }
                $alias = $this->sanitiseAlias($labels[$aliasLabel] ?? $name, $name);
                $byNetwork[$netName][] = [
                    "container" => $name,
                    "alias" => $alias,
                    "status" => $isConnected ? "connected" : "pending",
                ];
            }
        }

        return $byNetwork;
    }
}
