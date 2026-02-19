<div class="panel pts-reporting-panel">
    <h3>{l s='Reporting KPI' mod='ps_pts_reporting'}</h3>

    <form method="get" action="{$action_url}" class="form-inline">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="controller" value="AdminPtsReporting">

        <div class="col-sm-12 col-md-8 pts-reporting-filters-box">
            <div class="form-group col-sm-12 col-md-6">
                <label for="pts-month-from">{l s='De' mod='ps_pts_reporting'}</label>
                <select id="pts-month-from" name="month_from" class="form-control">
                    {foreach from=$months item=opt}
                        <option value="{$opt.value}" {if $opt.value == $month_from}selected{/if}>{$opt.label}</option>
                    {/foreach}
                </select>
                <select id="pts-year-from" name="year_from" class="form-control">
                    {foreach from=$years item=opt}
                        <option value="{$opt}" {if $opt == $year_from}selected{/if}>{$opt}</option>
                    {/foreach}
                </select>
            </div>

            <div class="form-group col-sm-12 col-md-6">
                <label for="pts-month-to">{l s='A' mod='ps_pts_reporting'}</label>
                <select id="pts-month-to" name="month_to" class="form-control">
                    {foreach from=$months item=opt}
                        <option value="{$opt.value}" {if $opt.value == $month_to}selected{/if}>{$opt.label}</option>
                    {/foreach}
                </select>
                <select id="pts-year-to" name="year_to" class="form-control">
                    {foreach from=$years item=opt}
                        <option value="{$opt}" {if $opt == $year_to}selected{/if}>{$opt}</option>
                    {/foreach}
                </select>
            </div>

            <div class="form-group col-sm-12 col-md-6 pts-reporting-spaced-group">
                <label for="pts-depannage-rate">{l s='Taux depannage' mod='ps_pts_reporting'}</label>
                <input id="pts-depannage-rate" type="number" name="depannage_rate" value="{$depannage_rate}" min="0.01"
                    step="0.01" class="form-control">
            </div>

            <div class="form-group col-sm-12 col-md-6 pts-reporting-spaced-group">
                <label for="pts-report-emails">{l s='Email(s) (separes par virgule)' mod='ps_pts_reporting'}</label>
                <input id="pts-report-emails" type="text" name="report_emails" value="{$report_emails}"
                    class="form-control">
                <p class="help-block">
                    {l s='Pour l\'envoi des rapports (CRON et bouton)' mod='ps_pts_reporting'}</p>
                <p class="help-block">
                    {l s='Laisser vide pour ne pas envoyer de rapport par email' mod='ps_pts_reporting'}</p>
            </div>

            <div class="col-sm-12 pts-reporting-actions-row">
                <button type="submit"
                    class="btn btn-default btn-block">{l s='Appliquer' mod='ps_pts_reporting'}</button>
            </div>
        </div>

        <div class="col-sm-12 col-md-4 text-right pts-reporting-export-col">
            <a class="btn btn-info" href="{$export_url}">{l s='Exporter le tableau' mod='ps_pts_reporting'}</a>
            <button type="submit" name="export_monthly" value="1" class="btn btn-info">{$export_monthly_label}</button>
        </div>
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