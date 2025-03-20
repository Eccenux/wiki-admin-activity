<?php
/**
	Init file to be included first.
	
	This includes some libraries and sets up some generic stuff.
*/
if (!defined('NO_HACKING'))
{
	die ('GO AWAY!');
}

// set timezone
if (function_exists('date_default_timezone_set'))
{
	date_default_timezone_set('Europe/Warsaw');
}
else
{
	die ('date_default_timezone_set does not exist!');
}

// include other classes
require_once './lib/ticks.class.php';
$oTicks = new cTicks();

// include i10n stuff along with L() language function
require_once './lang/_.php';
