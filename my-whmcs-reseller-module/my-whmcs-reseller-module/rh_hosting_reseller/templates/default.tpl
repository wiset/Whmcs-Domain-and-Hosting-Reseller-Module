<div class="panel panel-default rh-service-panel">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-server"></i> {$serviceName|default:'Service Overview'}</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-6">
                <p><strong>Product Name:</strong> {$serviceName}</p>
                <p><strong>Associated Domain:</strong>
                    {if $serviceDomain}
                        <a href="http://{$serviceDomain}" target="_blank">{$serviceDomain}</a>
                    {else}
                        <span class="text-muted">N/A</span>
                    {/if}
                </p>
                <p><strong>Billing Cycle:</strong> {$serviceBillingCycle}</p>
            </div>
            <div class="col-sm-6">
                <p><strong>Registration Date:</strong> {$serviceRegDate}</p>
                <p><strong>Next Due Date:</strong> {$serviceNextDueDate}</p>
                <p><strong>Status:</strong>
                    <span class="label {if $accountActive}label-success{else}label-warning{/if}">{$accountStatus}</span>
                </p>
            </div>
        </div>

        {if $serviceNotes}
            <hr>
            <p><strong>Instructions / Notes:</strong><br>{$serviceNotes|nl2br}</p>
        {/if}
    </div>
</div>
