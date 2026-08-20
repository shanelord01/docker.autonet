$(document).ready(function () {
    function selectTab(tab) {
        $(".autonet-tab-button").removeClass("autonet-tab-active");
        $(".autonet-tab-button[data-tab='" + tab + "']").addClass("autonet-tab-active");
        $(".autonet-tab-panel").removeClass("autonet-tab-active");
        $("#autonet-tab-" + tab).addClass("autonet-tab-active");
        if (tab === "activity") {
            autonetLoadActivity();
        }
    }

    $(".autonet-tab-button").on("click", function () {
        selectTab($(this).data("tab"));
    });

    const hashTab = window.location.hash.replace("#", "");
    if (hashTab && $("#autonet-tab-" + hashTab).length) {
        selectTab(hashTab);
    }

    $(".autonet-c-enabled").on("change", function () {
        const $row = $(this).closest("td");
        const $alias = $row.find(".autonet-c-alias");
        $alias.prop("disabled", !this.checked);
    });

    $("#autonet-container-filter").on("input", function () {
        const term = $(this).val().toLowerCase();
        $(".autonet-container-row").each(function () {
            const name = $(this).data("container").toLowerCase();
            $(this).toggle(name.includes(term));
        });
    });

    $("#autonet-save-containers").on("click", autonetSaveContainers);

    $("#autonet-add-row").on("click", function () {
        $("#autonet-mappings-table tbody").append(
            '<tr><td><input type="text" name="mapping_key[]" placeholder="com.pangolin.autonet"></td>' +
            '<td><input type="text" name="mapping_network[]" placeholder="pangolin"></td>' +
            '<td><button type="button" class="autonet-remove-row">Remove</button></td></tr>'
        );
    });

    $("#autonet-mappings-table").on("click", ".autonet-remove-row", function () {
        $(this).closest("tr").remove();
    });

    $("#autonet-test-now").on("click", function () {
        const $results = $("#autonet-test-results");
        $results.html("<p>Running...</p>");
        $.post("/plugins/docker.autonet/server/service/TestReconcile.php", {}, function (data) {
            const parsed = typeof data === "string" ? JSON.parse(data) : data;
            const actions = parsed.actions || [];
            if (actions.length === 0) {
                $results.html("<p>No changes would be made - everything already matches the configured mappings.</p>");
                return;
            }
            let rows = actions.map(a =>
                `<tr><td>${a.type}</td><td>${a.container}</td><td>${a.network}</td><td>${a.alias}</td><td>${a.result}</td></tr>`
            ).join("");
            $results.html(
                '<table class="autonet-table"><thead><tr><th>Action</th><th>Container</th><th>Network</th><th>Alias</th><th>Would do</th></tr></thead><tbody>' +
                rows + '</tbody></table>'
            );
        });
    });

    $("#autonet-refresh-activity").on("click", autonetLoadActivity);
});

function autonetSaveContainers() {
    const changes = [];

    $(".autonet-container-row").each(function () {
        const container = $(this).data("container");
        const mappings = {};
        let dirty = false;

        $(this).find(".autonet-c-enabled").each(function () {
            const index = $(this).data("mapping");
            const enabled = this.checked;
            const origEnabled = $(this).data("orig") == "1";
            const $alias = $(this).closest("td").find(".autonet-c-alias");
            const alias = $alias.val().trim();
            const origAlias = String($alias.data("orig") ?? "");

            if (enabled !== origEnabled || (enabled && alias !== origAlias)) {
                dirty = true;
            }
            mappings[index] = { enabled, alias };
        });

        if (dirty) {
            changes.push({ container, mappings });
        }
    });

    const $result = $("#autonet-containers-result");
    if (changes.length === 0) {
        $result.html("<p>No changes to save.</p>");
        return;
    }

    $result.html("<p>Saving...</p>");
    $.ajax({
        url: "/plugins/docker.autonet/server/service/SaveContainerLabels.php",
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ changes }),
        success: function (data) {
            const parsed = typeof data === "string" ? JSON.parse(data) : data;
            const containers = parsed.containers || [];
            if (containers.length === 0) {
                $result.html("<p>No changes were needed.</p>");
                return;
            }
            $result.html(
                "<p>Updated: " + containers.join(", ") +
                ". These containers need to be recreated for the new labels to take effect.</p>" +
                '<button type="button" id="autonet-apply-recreate">Recreate now</button>'
            );
            $("#autonet-apply-recreate").on("click", function () {
                const names = containers.map(c => encodeURIComponent(c));
                openDocker("update_container " + names.join("*"), _("Updating " + containers.length + " Containers"), "", "loadlist");
            });
        }
    });
}

function autonetLoadActivity() {
    $.get("/plugins/docker.autonet/server/service/Activity.php", function (data) {
        const parsed = typeof data === "string" ? JSON.parse(data) : data;
        const entries = parsed.entries || [];
        const $body = $("#autonet-activity-body");
        if (entries.length === 0) {
            $body.html('<tr><td colspan="6">No activity recorded yet.</td></tr>');
            return;
        }
        $body.html(entries.map(e =>
            `<tr><td>${e.time}</td><td>${e.type}</td><td>${e.container}</td><td>${e.network}</td><td>${e.alias}</td><td>${e.result}</td></tr>`
        ).join(""));
    });
}
