<?php
/**
	Aktywność adminów na plwiki.

	Kiedy dany admin/user:
	- wykonał ostatnią edycję w przestrzeni głównej (ns:0).
	- wykonał ostatnią edycję w przestrzeni MediaWiki: (ns:8).
	- wykonał ostatnią logowaną akcję administracyjną (last log action).
	- wykonał ostatnią edycję zablokowanej strony (o ile da się to sprawdził).
*/
/**/
$arrMyCnf = parse_ini_file("../../.my.script.cnf", true);
$arrSrcDb = $arrMyCnf['plwikidb'];

$dbConfig = [];
// $arrSrcDb['host'], $arrSrcDb['dbname']
$dbConfig['host'] = "plwiki.labsdb";
$dbConfig['database'] = "plwiki_p";
$dbConfig['user'] = $arrSrcDb['user'];
$dbConfig['password'] = $arrSrcDb['password'];
/**/

require_once './AdminActivity.php';

$adminActivity = new AdminActivity($dbConfig);
$adminActivity->displayTable();
