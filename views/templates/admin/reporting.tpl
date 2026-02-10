<div class="panel">
    <h3>{l s='Daily KPI reporting' mod='ps_pts_reporting'}</h3>

    <p>
        {l s='Month' mod='ps_pts_reporting'}: {$month} / {$year}
        - <a href="{$export_url}">{l s='Export CSV' mod='ps_pts_reporting'}</a>
    </p>

    {if empty($rows)}
        <p>{l s='No data found for the selected period.' mod='ps_pts_reporting'}</p>
    {else}
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Date' mod='ps_pts_reporting'}</th>
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
                        <td>{$row.date}</td>
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