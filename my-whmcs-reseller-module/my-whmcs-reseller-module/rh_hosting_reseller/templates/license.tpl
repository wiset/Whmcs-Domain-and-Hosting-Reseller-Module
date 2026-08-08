<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-key"></i> License Management</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-8">
                <p><strong>Package / Edition:</strong> {$parentService.name|default:'Standard License'}</p>
                <p><strong>Registered Domain / IP:</strong> <code>{$serviceDomain|default:'Not Assigned'}</code></p>
                <p><strong>Status:</strong> <span class="label {if $parentService.status eq 'Active'}label-success{else}label-danger{/if}">{$parentService.status}</span></p>
                
                {if !empty($parentService.licensekey)}
                    <div class="form-group" style="margin-top: 15px;">
                        <label>License Key:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{$parentService.licensekey}" readonly id="licenseKeyInput">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(document.getElementById('licenseKeyInput').value); alert('License key copied to clipboard!');">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="col-sm-4 text-right">
                <p class="text-muted"><small>Next Due Date:<br><strong>{$parentService.nextduedate|default:'N/A'}</strong></small></p>
            </div>
        </div>
    </div>
</div>