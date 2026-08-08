<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Hosting Account Management</h3>
    </div>

    <div class="panel-body">

        <div class="row">

            <div class="col-md-8">

                <table class="table table-striped">
                    <tr>
                        <th width="180">Domain</th>
                        <td>
                            <a href="https://{$serviceDomain}" target="_blank">
                                {$serviceDomain}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <th>Username</th>
                        <td>{$serviceUsername}</td>
                    </tr>

                    <tr>
                        <th>Server</th>
                        <td>{$serverHostname}</td>
                    </tr>

                    <tr>
                        <th>IP Address</th>
                        <td>{$serverIp}</td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="label {if $parentService.status eq 'Active'}label-success{else}label-danger{/if}">
                                {$parentService.status}
                            </span>
                        </td>
                    </tr>

                </table>

            </div>

            <div class="col-md-4 text-right">

                {if $parentService.status eq 'Active'}

                   
                    {if $parentService.status eq 'Active'}
    <!-- Direct cPanel Login Form -->
    <form action="https://{$parentService.serverhostname}:2083/login/" method="post" target="_blank" style="display:inline;">
        <input type="hidden" name="user" value="{$parentService.username}">
        <input type="hidden" name="pass" value="{$parentService.password}">
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-sign-in-alt"></i> Login to cPanel
        </button>
    </form>

    {if $parentService.hostingtype eq "reseller" or $parentService.groupname|lower|strpos:"reseller" !== false}
        <br>
        <!-- Direct WHM Login Form -->
        <form action="https://{$parentService.serverhostname}:2087/login/" method="post" target="_blank" style="display:inline;">
            <input type="hidden" name="user" value="{$parentService.username}">
            <input type="hidden" name="pass" value="{$parentService.password}">
            <button type="submit" class="btn btn-warning btn-block">
                <i class="fas fa-server"></i> Login to WHM
            </button>
        </form>
    {/if}
{/if}

                    <br>

                    <a href="https://{$serviceDomain}"
                       target="_blank"
                       class="btn btn-default btn-block">

                        <i class="fas fa-globe"></i>

                        Visit Website

                    </a>

                {else}

                    <div class="alert alert-danger">

                        This hosting account is currently suspended.

                    </div>

                {/if}

            </div>

        </div>

    </div>

</div>