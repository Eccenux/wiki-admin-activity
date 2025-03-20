<?php
/**
	Aktywność adminów na plwiki.
*/
define('NO_HACKING', 1);
//header("Content-type: text/plain; charset=utf-8");
require('./_top.php');

/**/
$arrMyCnf = parse_ini_file("../../.my.script.cnf", true);
$arrSrcDb = $arrMyCnf['plwikidb'];

$dbConfig = [];
// $arrSrcDb['host'], $arrSrcDb['dbname']
$project = "plwiki";
$dbConfig['host'] = $project.".web.db.svc.wikimedia.cloud";
$dbConfig['database'] = $project."_p";
$dbConfig['user'] = $arrSrcDb['user'];
$dbConfig['password'] = $arrSrcDb['password'];
/**/

$strPageTitle = L('AdminActivity:title');
$contentHtml = '';

require_once './AdminActivity.php';

$oTicks->pf_insTick('adminActivity');
$adminActivity = new AdminActivity($dbConfig);
$data = $adminActivity->getAdminStats();
$contentHtml = $adminActivity->renderTable($data);
$oTicks->pf_endTick('adminActivity');

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