<div class="panel panel-default rh-service-panel">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fas fa-desktop"></i> {$serviceName|default:'Virtual Private Server'}
        </h3>
    </div>
    <div class="panel-body">
        <div class="row">

            <div class="col-md-8">
                <table class="table table-striped">
                    <tr>
                        <th width="180">Hostname</th>
                        <td>{$serverHostname|default:'N/A'}</td>
                    </tr>
                    <tr>
                        <th>Primary IP</th>
                        <td>{$serverIp|default:'N/A'}</td>
                    </tr>
                    <tr>
                        <th>Billing Cycle</th>
                        <td>{$serviceBillingCycle}</td>
                    </tr>
                    <tr>
                        <th>Next Due Date</th>
                        <td>{$serviceNextDueDate}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="label {if $accountActive}label-success{else}label-danger{/if}">
                                {$accountStatus}
                            </span>
                        </td>
                    </tr>
                </table>

                {if $disklimit || $bwlimit}
                <div class="rh-usage">
                    {if $disklimit}
                    <label class="rh-usage-label">Disk Usage &mdash; {$diskusage} / {$disklimit} MB</label>
                    <div class="progress" style="height: 14px; margin-bottom: 15px;">
                        <div class="progress-bar {if $diskPercent >= 90}progress-bar-danger{elseif $diskPercent >= 75}progress-bar-warning{else}progress-bar-success{/if}"
                             role="progressbar" style="width: {$diskPercent}%;">
                            {$diskPercent}%
                        </div>
                    </div>
                    {/if}

                    {if $bwlimit}
                    <label class="rh-usage-label">Bandwidth Usage &mdash; {$bwusage} / {$bwlimit} MB</label>
                    <div class="progress" style="height: 14px;">
                        <div class="progress-bar {if $bwPercent >= 90}progress-bar-danger{elseif $bwPercent >= 75}progress-bar-warning{else}progress-bar-success{/if}"
                             role="progressbar" style="width: {$bwPercent}%;">
                            {$bwPercent}%
                        </div>
                    </div>
                    {/if}
                </div>
                {/if}
            </div>

            <div class="col-md-4 text-right">
                <button type="button" class="btn btn-success btn-block" disabled title="Not yet connected to the server backend">
                    <i class="fas fa-play"></i> Start
                </button>
                <button type="button" class="btn btn-warning btn-block" disabled title="Not yet connected to the server backend">
                    <i class="fas fa-redo"></i> Reboot
                </button>
                <button type="button" class="btn btn-danger btn-block" disabled title="Not yet connected to the server backend">
                    <i class="fas fa-stop"></i> Stop
                </button>
                <p class="text-muted" style="margin-top: 8px;">
                    <small>Power controls will activate once wired to an AJAX action handler.</small>
                </p>
            </div>

        </div>
    </div>
</div>
