<?php

require_once(__DIR__ . "/../config/Config.php");

use DockerAutonet\Config\Config;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");

function getUserTemplateInsensitive(string $containerName)
{
    $dockerTemplates = new DockerTemplates();
    foreach ($dockerTemplates->getTemplates('user') as $file) {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->load($file['path']);
        $foundName = $doc->getElementsByTagName('Name')->item(0)->nodeValue ?? '';
        if (strtolower($foundName) === strtolower($containerName)) {
            return $file['path'];
        }
    }
    return false;
}

/**
 * Set or remove one label on a container's template. Returns true if the
 * template was actually changed. Mirrors the XML shape AddLabels.php has
 * always written, so labels stay compatible either way they were set.
 */
function applyLabel(SimpleXMLElement $templateXml, string $key, string $value): bool
{
    $existing = $templateXml->xpath("//Config[@Type='Label'][@Target='$key']");

    if ($existing) {
        if ($value === "") {
            $dom = dom_import_simplexml($existing[0]);
            $dom->parentNode->removeChild($dom);
            return true;
        }
        if ((string)$existing[0][0] !== $value || (string)$existing[0]['Name'] !== $key) {
            $existing[0][0] = $value;
            $existing[0]['Name'] = $key;
            return true;
        }
        return false;
    }

    if ($value === "") {
        return false;
    }

    $newElement = $templateXml->addChild('Config');
    $newElement->addAttribute('Name', $key);
    $newElement->addAttribute('Target', $key);
    $newElement->addAttribute('Default', "");
    $newElement->addAttribute('Mode', "");
    $newElement->addAttribute('Description', "");
    $newElement->addAttribute('Type', 'Label');
    $newElement->addAttribute('Display', 'always');
    $newElement->addAttribute('Required', 'false');
    $newElement->addAttribute('Mask', 'false');
    $newElement[0] = $value;
    return true;
}

$config = Config::load();
$data = json_decode(file_get_contents("php://input"), true);
$changes = $data["changes"] ?? [];

$updatedContainerNames = [];

foreach ($changes as $change) {
    $containerName = $change["container"] ?? "";
    $mappingChanges = $change["mappings"] ?? [];
    if ($containerName === "" || empty($mappingChanges)) {
        continue;
    }

    $templatePath = getUserTemplateInsensitive($containerName);
    if (!$templatePath) {
        continue;
    }

    $templateXml = simplexml_load_file($templatePath);
    if (!$templateXml) {
        continue;
    }

    $oldXml = $templateXml->asXML();
    $changed = false;

    foreach ($mappingChanges as $index => $mappingChange) {
        if (!isset($config["mappings"][$index])) {
            continue;
        }
        $mapping = $config["mappings"][$index];
        $enabled = !empty($mappingChange["enabled"]);
        $alias = trim($mappingChange["alias"] ?? "");

        if (applyLabel($templateXml, $mapping["key"], $enabled ? "true" : "")) {
            $changed = true;
        }
        if (applyLabel($templateXml, $config["alias_label"], $enabled ? $alias : "")) {
            $changed = true;
        }
    }

    if ($changed) {
        file_put_contents($templatePath . "." . (new DateTime())->format('Y.m.d.H.I.s') . ".bak", $oldXml);
        file_put_contents($templatePath, $templateXml->asXML());
        $updatedContainerNames[] = $containerName;
    }
}

header("Content-Type: application/json");
echo json_encode(["containers" => $updatedContainerNames]);
