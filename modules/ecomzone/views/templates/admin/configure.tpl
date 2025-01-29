<form method="post" action="{$current|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cogs"></i> {l s='EcomZone Configuration' mod='ecomzone'}
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='API Token' mod='ecomzone'}</label>
                <div class="col-lg-9">
                    <input type="text" name="ECOMZONE_API_TOKEN" value="{$ECOMZONE_API_TOKEN}" />
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submitEcomZoneModule" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='ecomzone'}
            </button>
        </div>
    </div>
</form>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Manual Actions' mod='ecomzone'}
    </div>
    <div class="form-wrapper">
        <form method="post" class="form-horizontal">
            <div class="form-group">
                <div class="col-lg-9 col-lg-offset-3">
                    <button type="submit" name="importProducts" class="btn btn-default">
                        <i class="process-icon-download"></i> {l s='Import Products' mod='ecomzone'}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-clock"></i> {l s='Cron Setup Instructions' mod='ecomzone'}
    </div>
    <div class="form-wrapper">
        <p>{l s='Add the following command to your server crontab to run every hour:' mod='ecomzone'}</p>
        <pre>0 * * * * curl -s "{$shop_url}modules/ecomzone/cron.php?token={$ECOMZONE_CRON_TOKEN}"</pre>
        <p>{l s='Or using PHP CLI:' mod='ecomzone'}</p>
        <pre>0 * * * * php {$shop_root}modules/ecomzone/cron.php --token={$ECOMZONE_CRON_TOKEN}</pre>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-info"></i> {l s='Debug Information' mod='ecomzone'}
    </div>
    <div class="form-wrapper">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    {foreach from=$ECOMZONE_DEBUG_INFO key=key item=value}
                        <tr>
                            <td><strong>{$key|escape:'html':'UTF-8'}</strong></td>
                            <td>{$value|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                    <tr>
                        <td><strong>{l s='Last Cron Run' mod='ecomzone'}</strong></td>
                        <td>{$ECOMZONE_LAST_CRON_RUN|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Next Scheduled Run' mod='ecomzone'}</strong></td>
                        <td>{$ECOMZONE_NEXT_CRON_RUN|escape:'html':'UTF-8'}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Recent Logs' mod='ecomzone'}
    </div>
    <div class="form-wrapper">
        <div class="log-container" style="max-height: 400px; overflow-y: auto;">
            {foreach from=$ECOMZONE_LOGS item=log}
                <div class="log-line">{$log|escape:'html':'UTF-8'}</div>
            {/foreach}
        </div>
    </div>
</div> 