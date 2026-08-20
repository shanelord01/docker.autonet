<?php

namespace DockerAutonet\Service;

require_once(__DIR__ . "/../config/Config.php");
require_once(__DIR__ . "/Reconciler.php");

use DockerAutonet\Config\Config;

class ContainerState
{
    /**
     * One row per container on the host, with current enabled/alias/status
     * for every configured mapping, and whether it's editable here at all
     * (only Docker-Manager-templated containers are - compose-managed ones
     * have no template file for a plugin to write labels into).
     */
    public static function list(array $config): array
    {
        $reconciler = new Reconciler($config, dryRun: true);
        $attrs = $reconciler->allContainerAttrs();
        $aliasLabel = $config["alias_label"];

        $rows = [];
        foreach ($attrs as $attr) {
            $name = ltrim($attr["Name"] ?? "", "/");
            if ($name === "") {
                continue;
            }

            $netMode = $attr["HostConfig"]["NetworkMode"] ?? "";
            if ($netMode === "host" || str_starts_with($netMode, "container:")) {
                continue;
            }

            $labels = $attr["Config"]["Labels"] ?? [];
            $networks = $attr["NetworkSettings"]["Networks"] ?? [];

            $mappings = [];
            foreach ($config["mappings"] as $i => $mapping) {
                $labelValue = $labels[$mapping["key"]] ?? null;
                $enabled = $labelValue !== null && !in_array(strtolower(trim($labelValue)), ["", "0", "false", "no", "off"], true);
                $mappings[$i] = [
                    "network" => $mapping["network"],
                    "key" => $mapping["key"],
                    "enabled" => $enabled,
                    "alias" => $labels[$aliasLabel] ?? "",
                    "connected" => array_key_exists($mapping["network"], $networks),
                ];
            }

            $rows[] = [
                "name" => $name,
                "has_template" => self::hasUserTemplate($name),
                "mappings" => $mappings,
            ];
        }

        usort($rows, fn($a, $b) => strcasecmp($a["name"], $b["name"]));
        return $rows;
    }

    private static function hasUserTemplate(string $containerName): bool
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
        require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");

        $dockerTemplates = new \DockerTemplates();
        foreach ($dockerTemplates->getTemplates('user') as $file) {
            $doc = new \DOMDocument('1.0', 'utf-8');
            $doc->load($file['path']);
            $foundName = $doc->getElementsByTagName('Name')->item(0)->nodeValue ?? '';
            if (strtolower($foundName) === strtolower($containerName)) {
                return true;
            }
        }
        return false;
    }
}
