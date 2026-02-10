<div class="panel">
    <h3>{l s='Reporting KPI journalier' mod='ps_pts_reporting'}</h3>

    <form method="get" action="{$action_url}" class="form-inline">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="controller" value="AdminPtsReporting">
        <div class="form-group">
            <label for="pts-month-from">{l s='De' mod='ps_pts_reporting'}</label>
            <select id="pts-month-from" name="month_from" class="form-control">
                {foreach from=$months item=opt}
                    <option value="{$opt.value}" {if $opt.value == $month_from} selected{/if}>{$opt.label}</option>
                {/foreach}
            </select>
            <select id="pts-year-from" name="year_from" class="form-control">
                {foreach from=$years item=opt}
                    <option value="{$opt}" {if $opt == $year_from} selected{/if}>{$opt}</option>
                {/foreach}
            </select>
        </div>
        <div class="form-group">
            <label for="pts-month-to">{l s='A' mod='ps_pts_reporting'}</label>
            <select id="pts-month-to" name="month_to" class="form-control">
                {foreach from=$months item=opt}
                    <option value="{$opt.value}" {if $opt.value == $month_to} selected{/if}>{$opt.label}</option>
                {/foreach}
            </select>
            <select id="pts-year-to" name="year_to" class="form-control">
                {foreach from=$years item=opt}
                    <option value="{$opt}" {if $opt == $year_to} selected{/if}>{$opt}</option>
                {/foreach}
            </select>
        </div>
        <button type="submit" class="btn btn-default">{l s='Appliquer' mod='ps_pts_reporting'}</button>
        <a class="btn btn-link" href="{$export_url}">{l s='Exporter le CSV' mod='ps_pts_reporting'}</a>
    </form>

    {if empty($rows)}
        <p>{l s='Aucune donnee pour la periode selectionnee.' mod='ps_pts_reporting'}</p>
    {else}
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Date commande' mod='ps_pts_reporting'}</th>
                    <th>{l s='Date facture' mod='ps_pts_reporting'}</th>
                    <th>{l s='Cumul CA HT' mod='ps_pts_reporting'}</th>
                    <th>{l s='Cumul MB HT' mod='ps_pts_reporting'}</th>
                    <th>{l s='Cumul Marge nette' mod='ps_pts_reporting'}</th>
                    <th>{l s='% MB HT' mod='ps_pts_reporting'}</th>
                    <th>{l s='% Marge nette' mod='ps_pts_reporting'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$rows item=row}
                    <tr>
                        <td>{$row.order_date}</td>
                        <td>{$row.invoice_date}</td>
                        <td>{$row.cumul_ca_ht}</td>
                        <td>{$row.cumul_mb_ht}</td>
                        <td>{$row.cumul_marge_nette}</td>
                        <td>{$row.cumul_pct_mb_ht}</td>
                        <td>{$row.cumul_pct_marge_nette}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}
</div>