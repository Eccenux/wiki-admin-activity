<?php
/**
	Aktywność adminów na plwiki.
*/
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

require_once './AdminActivity.php';

$adminActivity = new AdminActivity($dbConfig);
$data = $adminActivity->getAdminStats();
$adminActivity->displayTable($data);
