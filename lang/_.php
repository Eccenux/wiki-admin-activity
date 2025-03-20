<?php
	if (!defined('NO_HACKING'))
	{
		die ('GO AWAY!');
	}

	// some basic conf and params
	define ('FALLBACK_LANG', 'en');
	$strLangDir = dirname(__FILE__);
	$strUserLangCode = !empty($_REQUEST['user_lang']) ? $_REQUEST['user_lang'] 
		: (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2) 
		: ''
		)
	;
	
	// remove any unexpected chars
	preg_replace('#[^a-zA-Z_\-]+#', '', $strUserLangCode);
	
	// setup lang array
	require_once ("$strLangDir/".FALLBACK_LANG.".php");
	$strUserLangFile = "{$strLangDir}/{$strUserLangCode}.php";
	if (strlen($strUserLangCode)>=1 && is_file($strUserLangFile))
	{
		require_once ($strUserLangFile);
	}
	
	// shorthabnd function for reading l10n string
	function L($strKey)
	{
		global $arrLang;
		if (isset($arrLang[$strKey]))
		{
			return $arrLang[$strKey];
		}
		return $strKey;
	}
?>