<?php
	define('NO_HACKING', 1);
	require_once('./_.php');
	
	// return JS
	header("Content-type: text/javascript; charset=utf-8");
?>

// user language
var lang = '<?=$strUserLangCode?>';