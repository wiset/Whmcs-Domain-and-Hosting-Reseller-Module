<div class="panel panel-default rh-service-panel">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fas fa-server"></i> {$serviceName|default:'Hosting Account'}
        </h3>
    </div>

    <div class="panel-body">
        <div class="row">

            <div class="col-md-8">

                <table class="table table-striped">
                    <tr>
                        <th width="180">Domain</th>
                        <td>
                            {if $serviceDomain}
                                <a href="https://{$serviceDomain}" target="_blank">{$serviceDomain}</a>
                            {else}
                                <span class="text-muted">Not set</span>
                            {/if}
                        </td>
                    </tr>

                    <tr>
                        <th>Username</th>
                        <td>{$serviceUsername|default:'N/A'}</td>
                    </tr>

                    <tr>
                        <th>Server</th>
                        <td>{$serverHostname|default:'N/A'}</td>
                    </tr>

                    <tr>
                        <th>IP Address</th>
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

                {if $ns1 || $ns2}
                <table class="table table-striped" style="margin-top: 15px;">
                    {if $ns1}<tr><th width="180">Nameserver 1</th><td>{$ns1}</td></tr>{/if}
                    {if $ns2}<tr><th>Nameserver 2</th><td>{$ns2}</td></tr>{/if}
                </table>
                {/if}

            </div>

            <div class="col-md-4 text-right">

                {if $accountActive}

                    {if $cpanelUrl}
                    <form action="{$cpanelUrl}" method="post" target="_blank" style="display:block; margin-bottom: 8px;">
                        <input type="hidden" name="user" value="{$serviceUsername}">
                        <input type="hidden" name="pass" value="{$servicePassword}">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt"></i> Login to cPanel
                        </button>
                    </form>
                    {/if}

                    {if $isReseller && $whmUrl}
                    <form action="{$whmUrl}" method="post" target="_blank" style="display:block; margin-bottom: 8px;">
                        <input type="hidden" name="user" value="{$serviceUsername}">
                        <input type="hidden" name="pass" value="{$servicePassword}">
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-server"></i> Login to WHM
                        </button>
                    </form>
                    {/if}

                    {if $serviceDomain}
                    <a href="https://{$serviceDomain}" target="_blank" class="btn btn-default btn-block">
                        <i class="fas fa-globe"></i> Visit Website
                    </a>
                    {/if}

                {else}

                    <div class="alert alert-danger">
                        This hosting account is currently suspended.
                    </div>

                {/if}

            </div>

        </div>
    </div>
</div>
