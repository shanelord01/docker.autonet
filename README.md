# Docker Autonet

An Unraid plugin that connects Docker containers to Docker networks automatically, based on container labels.

[Support thread on the Unraid forums](https://forums.unraid.net/topic/200314-plugin-docker-autonet/)

## What it does

Configure one or more label key to network mappings (for example, `com.pangolin.autonet` to `pangolin`). Any container carrying that label with a truthy value (`true`, `1`, `yes`, `on`) gets connected to the mapped network, using an alias label (`com.autonet.alias` by default) for its DNS name on that network. Remove the label, or set it to `false`, and the container is disconnected again (when auto-disconnect is enabled).

Reconciliation runs on a cron schedule and checks every container on the host, not just ones managed through this plugin's UI, so it also picks up containers defined purely in `docker-compose.yml` files.

## Install

Community Applications: search for "Docker Autonet" (awaiting approval).

Or install directly from the plugin URL:

```
https://raw.githubusercontent.com/shanelord01/docker.autonet/main/docker.autonet.plg
```

## Configure

Open **Settings -> Docker Autonet**.

### Mappings

Add a row for each label key you want watched and the network it should connect to. Pick an existing network from the dropdown, or choose **+ Create new network...** and type a name - saving the mapping creates that network if it doesn't already exist.

### Containers

Turn a container's connection to a mapped network on or off with a toggle, and set its alias. Saving always writes the trigger label and alias together, so a container can't end up half-configured (missing one label but not the other). Containers need to be recreated for a label change to take effect; saving offers to do that for you.

Containers not managed by Docker Manager (pure `docker-compose` deployments) are shown for visibility but can't be edited here - edit their compose file directly.

A **Manage Autonet Labels** button on the Docker page jumps straight to this tab.

### Advanced

Set the alias label key, whether removing a label should disconnect the container, the rescan interval (minimum 60 seconds), and debug logging.

### Activity and dashboard

The **Activity** tab lists recent connect/disconnect actions. A dashboard widget shows current network membership per configured mapping, with a status dot for connected versus pending containers.

Use **Test now** on the settings page to see what the current configuration would change, without changing anything.

## Uninstall

Community Applications, or remove via the Plugins page. This removes the plugin's files, its cron entry, and its configuration.

## Migrating from docker.labelInjector and pangolin-autonet-watcher

Docker Autonet replaces both. Install it, recreate your label to network mappings in **Settings -> Docker Autonet**, confirm containers reconcile correctly, then remove the old watcher container and the labelInjector plugin. Existing `com.pangolin.autonet` labels on your containers do not need to change.
