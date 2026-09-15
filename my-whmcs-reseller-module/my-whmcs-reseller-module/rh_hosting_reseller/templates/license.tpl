<div class="panel panel-default rh-service-panel">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-key"></i> {$serviceName|default:'License Management'}</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-8">
                <p><strong>Package / Edition:</strong> {$serviceName}</p>
                <p><strong>Registered Domain / IP:</strong> <code>{$serviceDomain|default:'Not Assigned'}</code></p>
                <p><strong>Billing Cycle:</strong> {$serviceBillingCycle}</p>
                <p><strong>Status:</strong>
                    <span class="label {if $accountActive}label-success{else}label-danger{/if}">{$accountStatus}</span>
                </p>

                {if $licensekey}
                    <div class="form-group" style="margin-top: 15px;">
                        <label>License Key:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{$licensekey}" readonly id="licenseKeyInput">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="rhCopyLicenseKey(this)">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="col-sm-4 text-right">
                <p class="text-muted"><small>Next Due Date:<br><strong>{$serviceNextDueDate}</strong></small></p>
            </div>
        </div>
    </div>
</div>

<script>
function rhCopyLicenseKey(btn) {
    var input = document.getElementById('licenseKeyInput');
    navigator.clipboard.writeText(input.value);
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied';
    setTimeout(function () { btn.innerHTML = original; }, 2000);
}
</script>
