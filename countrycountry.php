<?php

// error reporting
error_reporting(E_ALL);

// display errors for debugging purposes
ini_set("display_errors", true);

// increase memory limit
ini_set("memory_limit", "512M");

// increase max execution time (thousands of queries takes a while)
ini_set("max_execution_time", 300);

// constants
require_once "include/constants.php";

// class autoloader
function __autoload($classname) {
	$path = "classes/$classname.class.php";
	if (dirname($path) == "classes" && file_exists($path))
		require_once $path;
}

// set up include path
ini_set("include_path", ".:" . SITEROOT_LOCAL . "include");

// common functions
require_once "include/functions.php";

// character encoding
mb_internal_encoding("UTF-8");

// default timezone
date_default_timezone_set("Europe/London");

// undo any magic quotes
unmagic();

// start sessions
session_start();

// serve the page
$page = isset($_GET["page"]) ? $_GET["page"] : "mainmenu";
switch ($page) {
	default:
		if (dirname("content/$page.php") == "content" && file_exists("content/$page.php"))
			include "content/$page.php";
		else
			notfound();
}

?>
