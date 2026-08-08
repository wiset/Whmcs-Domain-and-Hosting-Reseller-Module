<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-server"></i> Service Overview</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-6">
                <p><strong>Product Name:</strong> {$parentService.name|default:'Managed Service'}</p>
                <p><strong>Associated Domain:</strong> <a href="http://{$serviceDomain}" target="_blank">{$serviceDomain}</a></p>
                <p><strong>Billing Cycle:</strong> {$parentService.billingcycle|ucfirst|default:'Monthly'}</p>
            </div>
            <div class="col-sm-6">
                <p><strong>Registration Date:</strong> {$parentService.regdate|default:'N/A'}</p>
                <p><strong>Next Due Date:</strong> {$parentService.nextduedate|default:'N/A'}</p>
                <p><strong>Status:</strong> <span class="label {if $parentService.status eq 'Active'}label-success{else}label-warning{/if}">{$parentService.status}</span></p>
            </div>
        </div>
        
        {if !empty($parentService.notes)}
            <hr>
            <p><strong>Instructions / Notes:</strong><br>{$parentService.notes|nl2br}</p>
        {/if}
    </div>
</div>