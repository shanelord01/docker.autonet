$(document).ready(function () {
    $(".autonet-tab-button").on("click", function () {
        const tab = $(this).data("tab");
        $(".autonet-tab-button").removeClass("autonet-tab-active");
        $(this).addClass("autonet-tab-active");
        $(".autonet-tab-panel").removeClass("autonet-tab-active");
        $("#autonet-tab-" + tab).addClass("autonet-tab-active");
        if (tab === "activity") {
            autonetLoadActivity();
        }
    });

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
