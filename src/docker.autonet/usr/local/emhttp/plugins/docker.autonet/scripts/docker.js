$(document).ready(function () {
    $("#docker_containers").after('<input type="button" onclick="autonetLabelFormPopup()" value="Add Labels" style="">')
})

function autonetLabelFormPopup() {
    swal({
        title: "Autonet Label Editor",
        text: '<form id="autonet-label-form"></form>',
        html: true,
        showCancelButton: true,
        closeOnConfirm: false,
        closeOnCancel: false,
        allowOutsideClick: true
    }, function (isConfirm) {
        $('div.spinner.fixed').show();
        $(".sweet-alert").removeClass("autonet-label-injector");
        swal.close();
        if (isConfirm) {
            setTimeout(() => {
                $('div.spinner.fixed').hide();
                autonetAddLabels();
            }, 500);
        } else {
            $('div.spinner.fixed').hide();
        }
    });
    $(".sweet-alert").addClass("autonet-label-injector")

    autonetLabelForm()
}

function autonetAddLabels() {
    const labels = $('#autonet-label-injector-labels')
        .val()
        .map(value => {
            const splits = value.split("=");
            if (splits.length >= 3) {
                return { name: splits[0], key: splits[1], value: splits[2] }
            } else {
                return { name: splits[0], key: splits[0], value: splits[1] }
            }
        });

    const containers = $('#autonet-label-injector-containers').val().filter(x => x !== 'all');

    if (labels.length > 0 && containers.length > 0) {
        $('div.spinner.fixed').show();
        $.post("/plugins/docker.autonet/server/service/AddLabels.php", { data: JSON.stringify({ labels, containers }) }, function (data) {
            $('div.spinner.fixed').hide();
            data = JSON.parse(data)
            const hasUpdates = data.containers.length > 0
            let updates = ['<pre class="autonet-label-updates">'];
            if (hasUpdates) {
                updates.push("<h3>Templates updated. Applying the container(s) now will recreate them with the new labels.</h3>")
                updates.push("<h3>Once you press okay the changes will be applied one by one</h3>")
                Object.entries(data.updates).forEach(([container, changes]) => {
                    updates.push(`<h3>${container} changes:</h3>${changes.join("")}`);
                });
            } else {
                updates.push("<h3>No containers returned any label changes, nothing to apply</h3>")
            }

            updates.push("</pre>")

            swal({
                title: "Summary of updates",
                text: updates.join(""),
                html: true,
                closeOnConfirm: false,
                allowOutsideClick: true,
                showCancelButton: true,
            }, function (isConfirm) {
                $(".sweet-alert").removeClass("autonet-label-summary");
                swal.close();
                if (isConfirm && hasUpdates) {
                    $('div.spinner.fixed').show();
                    const containersString = data.containers.map(container => encodeURIComponent(container));
                    setTimeout(() => {
                        $('div.spinner.fixed').hide();
                        openDocker('update_container ' + containersString.join("*"), _(`Updating ${data.containers.length} Containers`), '', 'loadlist');
                    }, 500);
                }
            });
            $(".sweet-alert").addClass("autonet-label-summary")
        });
    }
}

const autonetLabelInjectorNotes = `<h3>Note:</h3>
                    <ul class="list">
                        <li>Type and press enter to save a label, separate label from value via '='</li>
                        <li>When empty values are provided the label will be removed or ignored if not found</li>
                        <li>Existing tags will be replaced</li>
                        <li>If you provide 3 = i.e A=B=C, A is the name, B is the label, C is the value</li>
                        <li>If you provide 2 = i.e A=B, A is the name and the label, B is the value</li>
                        <li>To use quotes in a value, use an escaped backtick \\\` - otherwise the option fails to save</li>
                    </ul>
                    <h3>The following special values can be used to replace values or keys:</h3>
                    <ul class="list">
                        <li>\${CONTAINER_NAME} - i.e 'LABEL_A=\${CONTAINER_NAME}.domain.com' -> 'LABEL_A=container_a.domain.com'</li>
                    </ul>`
function autonetLabelForm() {
    $('#autonet-label-form').html(`
        <form id="autonet-label-form" class="autonet-label-form">
            <div class="autonet-label-form-group">
                <p>Choose containers to add labels to</p>
                <select id="autonet-label-injector-containers" name="containers" class="autonet-label-select" multiple required></select>
                <button id="autonet-remove-all-containers">Remove All</button>
            </div>
            <div class="autonet-label-form-group">
                <div class="autonet-label-injector-notes">
                    ${autonetLabelInjectorNotes}
                </div>
                <select id="autonet-label-injector-labels" name="labels" class="autonet-label-select" multiple required></select>
                <button id="autonet-remove-all-labels">Remove All</button>
            </div>
        </form>
        `)
    autonetGenerateLabelsSelect();
    autonetGenerateContainersSelect();

    $(".sa-confirm-button-container button").prop("disabled", true)
    const valueChecker = function () {
        if ($("#autonet-label-injector-containers").val() && $("#autonet-label-injector-labels").val()) {
            $(".sa-confirm-button-container button").prop("disabled", false)
        } else {
            $(".sa-confirm-button-container button").prop("disabled", true)
        }
    }
    $("#autonet-label-injector-containers").on('change', valueChecker);
    $("#autonet-label-injector-labels").on('change', valueChecker);
}

function autonetGenerateLabelsSelect() {
    generateDropdown("#autonet-label-injector-labels", {
        choices: autonetDefaultLabels.map(label => ({
            value: label,
            label: label,
            selected: true,
            disabled: false
        })),
        addItemFilter,
        customAddItemText: defaultOptions.customAddItemText,
    }, "#autonet-remove-all-labels")
}

function autonetGenerateContainersSelect() {
    generateDropdown("#autonet-label-injector-containers", {
        choices: docker.map(ct => ({
            value: ct.name,
            label: ct.name,
            selected: false,
            disabled: false
        })).concat({
            value: 'all',
            label: 'all',
            selected: false,
            disabled: false
        }),
    }, "#autonet-remove-all-containers")
}
