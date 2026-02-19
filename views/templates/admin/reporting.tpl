<div class="panel">
    <h3>{l s='Reporting KPI' mod='ps_pts_reporting'}</h3>

    <form method="get" action="{$action_url}" class="form-inline">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="controller" value="AdminPtsReporting">
        <div class="col-sm-12 col-md-6">
            {* Date départ *}
            <div class="form-group col-sm-12 col-md-6">
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
            {* Date fin *}
            <div class="form-group col-sm-12 col-md-6">
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
            {* Taux de dépannage *}
            <div class="form-group col-sm-12" style="padding-top:20px;">
                <label for="pts-depannage-rate">{l s='Taux depannage' mod='ps_pts_reporting'}</label>
                <input id="pts-depannage-rate" type="number" name="depannage_rate" value="{$depannage_rate}" min="0.01"
                    step="0.01" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn btn-default">{l s='Appliquer' mod='ps_pts_reporting'}</button>
        <a class="btn btn-link" href="{$export_url}">{l s='Exporter le CSV' mod='ps_pts_reporting'}</a>
        <a class="btn btn-link" href="{$export_monthly_url}">{l s='Rapport mensuel' mod='ps_pts_reporting'}</a>
    </form>

    {if empty($rows)}
        <p>{l s='Aucune donnee pour la periode selectionnee.' mod='ps_pts_reporting'}</p>
    {else}
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Reference commande' mod='ps_pts_reporting'}</th>
                    <th>{l s='Date commande' mod='ps_pts_reporting'}</th>
                    <th>{l s='Date facture' mod='ps_pts_reporting'}</th>
                    <th>{l s='ca' mod='ps_pts_reporting'}</th>
                    <th>{l s='depannage' mod='ps_pts_reporting'}</th>
                    <th>{l s='commandes fournisseur liees' mod='ps_pts_reporting'}</th>
                    <th>{l s='marge brute' mod='ps_pts_reporting'}</th>
                    <th>{l s='Marge nette' mod='ps_pts_reporting'}</th>
                    <th>{l s='% marge brute' mod='ps_pts_reporting'}</th>
                    <th>{l s='% marge nette' mod='ps_pts_reporting'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$rows item=row}
                    <tr>
                        <td>{$row.order_reference}</td>
                        <td>{$row.order_date}</td>
                        <td>{$row.invoice_date}</td>
                        <td>{$row.ca_ht}</td>
                        <td>{$row.depannage_ht}</td>
                        <td>{$row.supplier_order_refs}</td>
                        <td>{$row.mb_ht}</td>
                        <td>{$row.marge_nette}</td>
                        <td>{$row.pct_mb_ht}</td>
                        <td>{$row.pct_marge_nette}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}
</div>