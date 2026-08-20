# Docker Autonet

An Unraid plugin that connects Docker containers to Docker networks automatically, based on container labels.

## What it does

Configure one or more label key to network mappings (for example, `com.pangolin.autonet` to `pangolin`). Any container carrying that label with a truthy value (`true`, `1`, `yes`, `on`) gets connected to the mapped network. Remove the label, or set it to `false`, and the container is disconnected again (when auto-disconnect is enabled).

Reconciliation runs on a cron schedule and checks every container on the host, not just ones managed through this plugin's UI, so it also picks up containers defined purely in `docker-compose.yml` files.

A **Add Labels** button on the Docker page lets you tag existing containers without hand-editing the template XML.

## Install

Community Applications: search for "Docker Autonet" (awaiting approval).

Or install directly from the plugin URL:

```
https://raw.githubusercontent.com/shanelord01/docker.autonet/main/docker.autonet.plg
```

## Configure

Open **Settings -> Docker Autonet**.

1. Under the **Mappings** tab, add a row for each label key you want watched and the network it should connect to.
2. Under **Advanced**, set the alias label key (used for the container's DNS name on the network), whether removing a label should disconnect the container, and the rescan interval.
3. Use **Test now** to see what the current configuration would change, without changing anything.
4. **Save settings**.

Reconciliation runs every minute at most; the rescan interval setting controls how often it actually acts, not the cron schedule itself.

## Add labels to a container

On the **Docker** tab, click **Add Labels**. Pick one or more containers and one or more labels, then confirm. This writes the labels into the container's template and offers to update (recreate) the container so the labels take effect. Docker labels can only be set when a container is created, so a container that already has the desired label will not need recreating, but a newly labelled one will.

Label syntax in the picker:

- `KEY=VALUE` sets a label named `KEY` to `VALUE`.
- `NAME=KEY=VALUE` sets a label named `KEY` to `VALUE`, displayed as `NAME` in the picker.
- An empty value removes the label.
- `${CONTAINER_NAME}` in a key or value is replaced with the container's name.

## Activity and dashboard

The **Activity** tab in settings lists recent connect/disconnect actions. A dashboard widget shows current network membership per configured mapping, with a status dot for connected versus pending containers.

## Uninstall

Community Applications, or remove via the Plugins page. This removes the plugin's files, its cron entry, and its configuration.

## Migrating from docker.labelInjector and pangolin-autonet-watcher

Docker Autonet replaces both. Install it, recreate your label to network mappings in **Settings -> Docker Autonet**, confirm containers reconcile correctly, then remove the old watcher container and the labelInjector plugin. Existing `com.pangolin.autonet` / `com.pangolin.autonet.alias` labels on your containers do not need to change.
