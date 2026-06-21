<?php
/**
	Aktywność adminów na plwiki.
*/
define('NO_HACKING', 1);
//header("Content-type: text/plain; charset=utf-8");
require('./_top.php');

//
// Params
//
$action = isset($_GET['action']) ? $_GET['action'] : '';
$username = isset($_GET['username']) ? $_GET['username'] : '';

//
// DB config
//
/**/
$arrMyCnf = parse_ini_file("../../.my.script.cnf", true)
	or die("ERROR: unable to read cnf");
$arrSrcDb = $arrMyCnf['plwikidb'];

$dbConfig = [];
// $arrSrcDb['host'], $arrSrcDb['dbname']
// https://wikitech.wikimedia.org/wiki/Help:Wiki_Replicas#Connecting_to_the_database_replicas
// https://db-names.toolforge.org/
$project = "plwiki";
$dbConfig['host'] = !empty($arrSrcDb['tunnel_host']) ? $arrSrcDb['tunnel_host'] : $project.".web.db.svc.wikimedia.cloud";
$dbConfig['database'] = $project."_p";
$dbConfig['user'] = $arrSrcDb['user'];
$dbConfig['password'] = $arrSrcDb['password'];
/**/

//
// Page config
//
$strPageTitle = L('AdminActivity:title');
$contentHtml = '';

//
// Process
//
require_once './AdminActivity.php';
require_once './ReviewStats.php';

$oTicks->pf_insTick('adminActivity');
$adminActivity = new AdminActivity($dbConfig);
$adminActivity->oTicks = $oTicks;
$dataType = 'all';
// single user
if ($action == 'details' && !empty($username)) {
	$dataType = 'details';
	$months = [1,3,4,6,9,11,12,18,24];
	$contentHtml .= '<bcrumbs>&lt; <a href="?">'.L('Main table').'</a></bcrumbs>';
	$contentHtml .= '<p><a href="https://pl.wikipedia.org/wiki/User:'.rawurlencode($username).'" rel="noopener noreferrer" target="_blank">'.htmlspecialchars($username, ENT_QUOTES, 'UTF-8').'</a>, aktywność w ostatnich miesiącach</p>';
	$data = $adminActivity->getSingleAdminStats($username, $months);
// all admins
} else {
	$days = 365;
	$contentHtml .= '<p>'.L('Data for the last').": $days ".L('days')
		.' ('.L('data may be cached for up to').": 24 ".L('hours').")"
		."</p>"
	;
	$data = $adminActivity->getAdminStats($days);
}
$contentHtml .= $adminActivity->renderTable($data, $dataType);
$oTicks->pf_endTick('adminActivity');

if ($action == 'details' && !empty($username)) {
	$oTicks->pf_insTick('ReviewStats');

	$reviewStats = new ReviewStats($dbConfig);
	$reviewData = $reviewStats->getStats($username);
	$reviewHtml = $reviewStats->renderStatsTable($reviewData);

	if (!empty($reviewData)) {
		$contentHtml .= "<h2>Wersje przejrzane</h2>";
		$contentHtml .= $reviewHtml;
	}
	
	$oTicks->pf_endTick('ReviewStats');
}

//
// Form ticks
//
if (!empty($oTicks))
{
	$arrTicks = $oTicks->pf_getDurations();
}

//
// Output
//
include('./view/_header.tpl.php');
echo $contentHtml;
include('./view/_footer.tpl.php');