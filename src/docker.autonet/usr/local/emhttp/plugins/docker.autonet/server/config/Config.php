<?php

namespace DockerAutonet\Config;

class Config
{
    public const CONFIG_DIR = "/boot/config/plugins/docker.autonet";
    public const CONFIG_PATH = self::CONFIG_DIR . "/config.json";
    public const LOG_PATH = "/var/log/docker.autonet.log";
    public const ACTIVITY_PATH = self::CONFIG_DIR . "/activity.jsonl";
    public const ACTIVITY_MAX_LINES = 500;

    public const DEFAULTS = [
        "mappings" => [
            ["key" => "com.pangolin.autonet", "network" => "pangolin"],
        ],
        "alias_label" => "com.autonet.alias",
        "auto_disconnect" => true,
        "rescan_seconds" => 60,
        "debug" => false,
    ];

    static function load(): array
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return self::DEFAULTS;
        }
        $json = file_get_contents(self::CONFIG_PATH);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return self::DEFAULTS;
        }
        return array_merge(self::DEFAULTS, $decoded);
    }

    static function save(array $config): void
    {
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0755, true);
        }
        file_put_contents(self::CONFIG_PATH, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Handle a settings-page POST. Returns a message to display, or null if this wasn't a POST.
     */
    static function formSubmit(): ?string
    {
        if ($_SERVER["REQUEST_METHOD"] != "POST") {
            return null;
        }

        $keys = $_POST["mapping_key"] ?? [];
        $nets = $_POST["mapping_network"] ?? [];
        $mappings = [];
        foreach ($keys as $i => $key) {
            $key = self::stripLabelValue(trim($key));
            $net = trim($nets[$i] ?? "");
            if ($key !== "" && $net !== "") {
                $mappings[] = ["key" => $key, "network" => $net];
            }
        }

        $config = [
            "mappings" => $mappings,
            "alias_label" => trim($_POST["alias_label"] ?? self::DEFAULTS["alias_label"]) ?: self::DEFAULTS["alias_label"],
            "auto_disconnect" => isset($_POST["auto_disconnect"]),
            "rescan_seconds" => max(60, (int)($_POST["rescan_seconds"] ?? self::DEFAULTS["rescan_seconds"])),
            "debug" => isset($_POST["debug"]),
        ];

        self::save($config);
        return "Settings saved.";
    }

    /**
     * A mapping's label key is always applied with an implicit "=true" - if
     * someone pastes or types "key=true" (or any other "=value") into the
     * key field, keep only the part before the "=" instead of storing a
     * key that already contains one. Without this, the key ends up baked
     * into containers as "key=true" itself with "=true" appended again.
     */
    private static function stripLabelValue(string $key): string
    {
        return trim(explode("=", $key, 2)[0]);
    }

    /**
     * Suggested label keys for the Add Labels dropdown on the Docker page,
     * derived from the configured mappings instead of a separate hardcoded list.
     */
    static function suggestedLabelKeys(): array
    {
        return array_map(fn($m) => $m["key"], self::load()["mappings"]);
    }

    static function appendActivity(array $entry): void
    {
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0755, true);
        }
        $entry["time"] = date("c");
        file_put_contents(self::ACTIVITY_PATH, json_encode($entry) . "\n", FILE_APPEND);

        $lines = file(self::ACTIVITY_PATH, FILE_IGNORE_NEW_LINES);
        if ($lines && count($lines) > self::ACTIVITY_MAX_LINES) {
            $lines = array_slice($lines, -self::ACTIVITY_MAX_LINES);
            file_put_contents(self::ACTIVITY_PATH, implode("\n", $lines) . "\n");
        }
    }

    static function readActivity(int $limit = 100): array
    {
        if (!file_exists(self::ACTIVITY_PATH)) {
            return [];
        }
        $lines = file(self::ACTIVITY_PATH, FILE_IGNORE_NEW_LINES) ?: [];
        $lines = array_slice($lines, -$limit);
        $entries = array_map(fn($l) => json_decode($l, true), array_reverse($lines));
        return array_filter($entries, fn($e) => is_array($e));
    }
}
