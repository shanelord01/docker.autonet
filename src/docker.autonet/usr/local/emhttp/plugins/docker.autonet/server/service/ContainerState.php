<?php

namespace DockerAutonet\Service;

require_once(__DIR__ . "/../config/Config.php");
require_once(__DIR__ . "/Reconciler.php");

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
// DockerTemplates::getTemplates() reads `global $dockerManPaths`, which this
// include only populates correctly when required at file scope - requiring
// it from inside a function/method leaves that global unset and every path
// it resolves comes back null.
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");

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

        $templatedNames = self::templatedContainerNames();

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
                "has_template" => in_array(strtolower($name), $templatedNames, true),
                "mappings" => $mappings,
            ];
        }

        usort($rows, fn($a, $b) => strcasecmp($a["name"], $b["name"]));
        return $rows;
    }

    /**
     * Lowercased container names with a Docker Manager template, loaded
     * once and cached for the life of the request - avoids re-parsing
     * every template's XML once per container.
     */
    private static function templatedContainerNames(): array
    {
        static $names = null;
        if ($names !== null) {
            return $names;
        }

        $names = [];
        $dockerTemplates = new \DockerTemplates();
        foreach ($dockerTemplates->getTemplates('user') as $file) {
            $doc = new \DOMDocument('1.0', 'utf-8');
            $doc->load($file['path']);
            $foundName = $doc->getElementsByTagName('Name')->item(0)->nodeValue ?? '';
            if ($foundName !== '') {
                $names[] = strtolower($foundName);
            }
        }
        return $names;
    }
}
