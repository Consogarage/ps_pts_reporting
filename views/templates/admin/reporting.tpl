<div class="panel pts-reporting-panel">
    <h3>{l s='Reporting KPI' mod='ps_pts_reporting'}</h3>

    <ul class="nav nav-tabs" id="pts-reporting-tabs">
        <li{if $active_tab == 'tab-ca-marge'} class="active"{/if}><a href="#tab-ca-marge" data-toggle="tab">{l s='CA et marge' mod='ps_pts_reporting'} <span class="text-warning small">Les données CA et dépannage ignorent les commandes Ital Express</span></a></li>
        <li{if $active_tab == 'tab-kpi-clients'} class="active"{/if}><a href="#tab-kpi-clients" data-toggle="tab">{l s='KPI clients' mod='ps_pts_reporting'}</a></li>
    </ul>

    <div class="tab-content pts-reporting-tab-content">
    <div class="tab-pane{if $active_tab == 'tab-ca-marge'} active{/if}" id="tab-ca-marge">

    <form method="get" action="{$action_url}" class="form-inline">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="controller" value="AdminPtsReporting">
        <input type="hidden" name="active_tab" value="tab-ca-marge">

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
            <a class="btn btn-info btn-block"
                href="{$export_url}">{l s='Exporter le tableau' mod='ps_pts_reporting'}</a>
            <p class="help-block">
                {l s='Exporter le tableau ci-dessous en CSV' mod='ps_pts_reporting'}</p>
            <hr>
            <div class="row">
                <div class="col-xs-3">
                    <select name="report_monthly_month" class="form-control">
                        {foreach from=$months item=opt}
                            <option value="{$opt.value}" {if $opt.value == $report_monthly_month}selected{/if}>{$opt.label}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-xs-3">
                    <select name="report_monthly_year" class="form-control">
                        {foreach from=$years item=opt}
                            <option value="{$opt}" {if $opt == $report_monthly_year}selected{/if}>{$opt}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-xs-6">
                    <button type="submit" name="export_monthly" value="1" class="btn btn-info btn-block">
                        {* {$export_monthly_label} *}
                        Rapport mensuel (cumul)
                    </button>

                </div>
            </div>
            <p class="help-block">
                {l s='CSV avec cumul envoyé par email et exporté sur le serveur (répertoire %s)' sprintf=[$exports_directory] mod='ps_pts_reporting'}
            </p>
            <details class="pts-reporting-history">
                <summary class="btn btn-block btn-default">{l s='Historique des exports' mod='ps_pts_reporting'}
                </summary>
                {if empty($export_history)}
                    <p class="help-block">{l s='Aucun export disponible.' mod='ps_pts_reporting'}</p>
                {else}
                    <ul class="list-unstyled pts-reporting-history-list">
                        {foreach from=$export_history item=exportItem}
                            <li>
                                <a href="{$exportItem.download_url}">{$exportItem.filename}</a>
                                <span class="text-muted">({$exportItem.date})</span>
                            </li>
                        {/foreach}
                    </ul>
                {/if}
            </details>
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

    </div>{* /tab-pane #tab-ca-marge *}

    <div class="tab-pane{if $active_tab == 'tab-kpi-clients'} active{/if}" id="tab-kpi-clients">

        <form method="get" action="{$action_url}" class="form-inline pts-kpi-clients-form">
            <input type="hidden" name="token" value="{$token}">
            <input type="hidden" name="controller" value="AdminPtsReporting">
            <input type="hidden" name="active_tab" value="tab-kpi-clients">
            <div class="form-group">
                <label for="pts-kpi-month">{l s='Mois' mod='ps_pts_reporting'}</label>
                <select id="pts-kpi-month" name="kpi_month" class="form-control">
                    {foreach from=$months item=opt}
                        <option value="{$opt.value}" {if $opt.value == $kpi_month}selected{/if}>{$opt.label}</option>
                    {/foreach}
                </select>
            </div>
            <div class="form-group">
                <label for="pts-kpi-year">{l s='Année' mod='ps_pts_reporting'}</label>
                <select id="pts-kpi-year" name="kpi_year" class="form-control">
                    {foreach from=$years item=opt}
                        <option value="{$opt}" {if $opt == $kpi_year}selected{/if}>{$opt}</option>
                    {/foreach}
                </select>
            </div>
            <button type="submit" class="btn btn-default">{l s='Afficher' mod='ps_pts_reporting'}</button>
            {if !empty($customer_kpi_rows)}
            <a href="{$kpi_export_url}" class="btn btn-success">
                <i class="icon-download"></i> {l s='Exporter XLSX' mod='ps_pts_reporting'}
            </a>
            {/if}
        </form>

        {if !empty($customer_kpi_rows)}
        <div class="pts-kpi-summary">
            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-3">
                    <div class="pts-kpi-stat-block">
                        <span class="pts-kpi-stat-label">{l s='Clients actifs N' mod='ps_pts_reporting'}</span>
                        <span class="pts-kpi-stat-value">{$customer_kpi_summary.nb_actifs_n}</span>
                        <span class="pts-kpi-stat-sub">N-1 : {$customer_kpi_summary.nb_actifs_n1} &mdash; évol. : <span class="{if $customer_kpi_summary.evol_nb_clients > 0}text-success{elseif $customer_kpi_summary.evol_nb_clients < 0}text-danger{/if}">{$customer_kpi_summary.evol_nb_clients} %</span></span>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3">
                    <div class="pts-kpi-stat-block">
                        <span class="pts-kpi-stat-label">{l s='CA HT N' mod='ps_pts_reporting'}</span>
                        <span class="pts-kpi-stat-value">{$customer_kpi_summary.total_ca_n} €</span>
                        <span class="pts-kpi-stat-sub">N-1 : {$customer_kpi_summary.total_ca_n1} € &mdash; écart : <span class="{if $customer_kpi_summary.ecart_ca > 0}text-success{elseif $customer_kpi_summary.ecart_ca < 0}text-danger{/if}">{$customer_kpi_summary.ecart_ca} € ({$customer_kpi_summary.pct_ca_vs_n1} %)</span></span>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3">
                    <div class="pts-kpi-stat-block">
                        <span class="pts-kpi-stat-label">{l s='MB HT N' mod='ps_pts_reporting'}</span>
                        <span class="pts-kpi-stat-value">{$customer_kpi_summary.total_mb_n} € <small>({$customer_kpi_summary.pct_mb_n} %)</small></span>
                        <span class="pts-kpi-stat-sub">N-1 : {$customer_kpi_summary.total_mb_n1} € ({$customer_kpi_summary.pct_mb_n1} %) &mdash; vs N-1 : <span class="{if $customer_kpi_summary.pct_mb_vs_n1 > 0}text-success{elseif $customer_kpi_summary.pct_mb_vs_n1 < 0}text-danger{/if}">{$customer_kpi_summary.pct_mb_vs_n1} %</span></span>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-3">
                    <div class="pts-kpi-stat-block">
                        <span class="pts-kpi-stat-label">{l s='Devis / Cdes / Transfo' mod='ps_pts_reporting'}</span>
                        <span class="pts-kpi-stat-value">{$customer_kpi_summary.total_devis} / {$customer_kpi_summary.total_cmds} / {$customer_kpi_summary.total_transfo}</span>
                        <span class="pts-kpi-stat-sub">{l s='Taux transfo' mod='ps_pts_reporting'} : {$customer_kpi_summary.taux_transfo} % &mdash; panier : {$customer_kpi_summary.panier_moyen} € &mdash; avoirs : {$customer_kpi_summary.total_avoirs} &mdash; nouveaux : {$customer_kpi_summary.nb_nouveaux}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pts-kpi-clients-table-wrap">
        <table class="table table-bordered table-hover table-condensed pts-kpi-clients-table">
            <thead>
                <tr>
                    <th rowspan="2">#</th>
                    <th rowspan="2">{l s='Raison sociale' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Activité' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Dept' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Pays' mod='ps_pts_reporting'}</th>
                    <th colspan="4" class="text-center">{l s='CA HT' mod='ps_pts_reporting'}</th>
                    <th colspan="5" class="text-center">{l s='Marge brute' mod='ps_pts_reporting'}</th>
                    <th colspan="4" class="text-center">{l s='Devis' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Panier moyen' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Avoirs' mod='ps_pts_reporting'}</th>
                    <th rowspan="2">{l s='Nouveau' mod='ps_pts_reporting'}</th>
                </tr>
                <tr>
                    <th>{l s='N' mod='ps_pts_reporting'}</th>
                    <th>{l s='N-1' mod='ps_pts_reporting'}</th>
                    <th>{l s='Écart' mod='ps_pts_reporting'}</th>
                    <th>{l s='% vs N-1' mod='ps_pts_reporting'}</th>
                    <th>{l s='MB N' mod='ps_pts_reporting'}</th>
                    <th>{l s='% MB N' mod='ps_pts_reporting'}</th>
                    <th>{l s='MB N-1' mod='ps_pts_reporting'}</th>
                    <th>{l s='% MB N-1' mod='ps_pts_reporting'}</th>
                    <th>{l s='% MB vs N-1' mod='ps_pts_reporting'}</th>
                    <th>{l s='Nb' mod='ps_pts_reporting'}</th>
                    <th>{l s='Cdes' mod='ps_pts_reporting'}</th>
                    <th>{l s='Transformés' mod='ps_pts_reporting'}</th>
                    <th>{l s='Taux' mod='ps_pts_reporting'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$customer_kpi_rows item=row}
                <tr{if $row.is_new_customer} class="pts-new-customer"{/if}>
                    <td>{$row.customer_id}</td>
                    <td>{$row.company}</td>
                    <td>{$row.activity}</td>
                    <td>{$row.dept}</td>
                    <td>{$row.pays}</td>
                    <td class="text-right">{$row.ca_n}</td>
                    <td class="text-right text-muted">{$row.ca_n1}</td>
                    <td class="text-right {if $row.ecart_ca > 0}text-success{elseif $row.ecart_ca < 0}text-danger{/if}">{$row.ecart_ca}</td>
                    <td class="text-right {if $row.pct_ca_vs_n1 > 0}text-success{elseif $row.pct_ca_vs_n1 < 0}text-danger{/if}">{$row.pct_ca_vs_n1}&nbsp;%</td>
                    <td class="text-right">{$row.mb_n}</td>
                    <td class="text-right">{$row.pct_mb_n}&nbsp;%</td>
                    <td class="text-right text-muted">{$row.mb_n1}</td>
                    <td class="text-right text-muted">{$row.pct_mb_n1}&nbsp;%</td>
                    <td class="text-right {if $row.pct_mb_vs_n1 > 0}text-success{elseif $row.pct_mb_vs_n1 < 0}text-danger{/if}">{$row.pct_mb_vs_n1}&nbsp;%</td>
                    <td class="text-right">{$row.nb_devis}</td>
                    <td class="text-right">{$row.nb_commandes}</td>
                    <td class="text-right">{$row.nb_devis_transformed}</td>
                    <td class="text-right">{$row.taux_transfo}&nbsp;%</td>
                    <td class="text-right">{$row.panier_moyen}</td>
                    <td class="text-right">{$row.nb_avoirs}</td>
                    <td class="text-center">{if $row.is_new_customer}✓{/if}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
        </div>
        {elseif $active_tab == 'tab-kpi-clients'}
            <p class="text-muted">{l s='Aucune donnée pour la période sélectionnée.' mod='ps_pts_reporting'}</p>
        {/if}

    </div>{* /tab-pane #tab-kpi-clients *}

    </div>{* /tab-content *}
</div>