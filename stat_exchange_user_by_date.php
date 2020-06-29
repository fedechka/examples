<?php
/**
 * $Id$
 */

$user_id = \Da\Std::grabInt('id');

$user = Da_User::get($user_id);
if (empty($user) || !$user['is_exchanger']) {
    // Пользователь не найден
    $tpl->assign('error', 'No such user');
    $tpl->assign('content', 'stat_exchange_user_by_date');
    return;
};

if (!Da_User::isAllowed($Admin, $user_id)) {
    trigger_error("Admin permission denied for ".$Admin->login." to access ".$pagename."");
    exit;
}

// Фильтр по периоду
require_once('stats_filters/report_period.php');

$Da_Subsystem = new \Da\System\Subsystem();
$tpl->assign('da_subsystem', $Da_Subsystem->get($user['subsystem_id']));

$stat   = new Da\Stats\Admin\ExchangeUserByDate($Admin);
$report = $stat->setUserId($user_id)
               ->report($report_period);

$available_months = $stat->getAvailMonths();
if (!$available_months) {
    $available_months = array(array('period' => date("Y-m-01")));
}
$tpl->assign('available_months', $available_months);

$tpl->assign('report', $report);
$prev_report = $stat->stats_prev;
$tpl->assign('prev_report', $prev_report);
$total = \Da\Std::arraySumByKeys($report);
$tpl->assign('total', $total);
$tpl->assign('user', $user);

// XLSX
if ("xlsx" == \Da\Std::grab('mode')) {
    $filename = 'stat_exchange_user_by_date-adm'.$Admin->id.'-'.date('d-m-Y-H-i-s');
    $title    = "User exchange by days";

    $columns = array(
        "baselevel" =>
            array(
                'date'                => array('header' => 'Date', 'format' => 'text'),
                'src_shows'           => array('header' => 'Source Shows', 'format' => 'int'),
                'src_clicks'          => array('header' => 'Source Clicks', 'format' => 'int'),
                'src_ctr'             => array('header' => 'Source CTR', 'format' => 'ctrRaw'),
                'buy_shows'           => array('header' => 'Advertisers Shows', 'format' => 'int'),
                'buy_clicks'          => array('header' => 'Advertisers Clicks', 'format' => 'int'),
                'buy_ctr'             => array('header' => 'Advertisers CTR', 'format' => 'ctrRaw'),
                'tier_ratio'          => array('header' => 'TP ratio', 'format' => 'float'),
                'in_shows'            => array('header' => 'Ads Shows', 'format' => 'int'),
                'in_clicks'           => array('header' => 'Ads Clicks', 'format' => 'int'),
                'in_ctr'              => array('header' => 'Ads CTR', 'format' => 'ctrRaw'),
                'debt'                => array('header' => 'Debt', 'format' => 'int'),
                'exchange_delta'      => array('header' => 'Exchange delta', 'format' => 'int'),
                'calc_exchange_ratio' => array('header' => 'Exchange ratio', 'format' => 'float'),
            ),
    );

    foreach ($report as $key => $row) {
        $report[$key]['date']           = \Da\Helper\Format::date($row['date']);
        $report[$key]['src_ctr']        = $stat->ctr($row['src_shows'], $row['src_clicks']);
        $report[$key]['buy_ctr']        = $stat->ctr($row['buy_shows'], $row['buy_clicks']);
        $report[$key]['in_ctr']         = $stat->ctr($row['in_shows'], $row['in_clicks']);
        $report[$key]['exchange_delta'] = $row['in_clicks'] - $row['debt'];
    }

    $total['date']                = 'Total';
    $total['src_ctr']             = $stat->ctr($total['src_shows'], $total['src_clicks']);
    $total['buy_ctr']             = $stat->ctr($total['buy_shows'], $total['buy_clicks']);
    $total['tier_ratio']          = ($total['src_clicks'] > 0) ? $total['buy_clicks'] / $total['src_clicks'] : 0;
    $total['in_ctr']              = $stat->ctr($total['in_shows'], $total['in_clicks']);
    $total['exchange_delta']      = $total['in_clicks'] - $total['debt'];
    $total['calc_exchange_ratio'] = ($total['src_clicks'] > 0) ? $total['debt'] / $total['src_clicks'] : 0;

    $csv = Da\Stats\Report\Report::generateReport($columns, $report, $title, array(), $total);

    Da\Stats\Report\Report::outputXLS($csv, $filename);

    exit();
}

$tpl->assign('xls_options', array(
    'pages'       => false,
    'show_deltas' => false,
));

$tpl->assign('content', 'stat_exchange_user_by_date');
