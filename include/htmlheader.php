<?php

header("Content-Type: text/html; charset=utf-8");
header("Content-Language: en");
header("Content-Style-Type: text/css");
header("Content-Script-Type: text/javascript");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NEMA: Country/country<?php if (isset($GLOBALS["title"])) { ?> &ndash; <?php echo $GLOBALS["title"]; ?><?php } ?></title>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.pie.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.orderBars.js"></script>

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.jplayer/jquery.jplayer.js"></script>
	<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/jquery.jplayer/jplayer.blue.monday.css" type="text/css">

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/collectionbuilder.js"></script>
	<?php if (isset($GLOBALS["headerjs"])) { ?>
		<script type="text/javascript">
			<?php echo $GLOBALS["headerjs"]; ?>
		</script>
	<?php } ?>
	<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/styles.css" type="text/css">
	<?php if (isset($GLOBALS["headercss"])) { ?>
		<style type="text/css">
			<?php echo $GLOBALS["headercss"]; ?>
		</style>
	<?php } ?>
</head>
<body>
<div id="header">
	<h1>
		<a href="<?php echo SITEROOT_WEB; ?>">NEMA: Country/country</a>
		<sub><small>beta</small></sub>
	</h1>
</div>
<div id="body">
