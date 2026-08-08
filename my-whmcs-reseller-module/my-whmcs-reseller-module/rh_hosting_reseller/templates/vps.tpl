<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Virtual Private Server Controls</h3>
    </div>
    <div class="panel-body">
        <p><strong>Primary IP:</strong> {$serverIp}</p>
        <p><strong>Status:</strong> <span class="label label-success">{$parentService.status}</span></p>
        <hr>
        <button type="button" class="btn btn-success btn-sm"><i class="fas fa-play"></i> Start</button>
        <button type="button" class="btn btn-warning btn-sm"><i class="fas fa-redo"></i> Reboot</button>
        <button type="button" class="btn btn-danger btn-sm"><i class="fas fa-stop"></i> Stop</button>
    </div>
</div>