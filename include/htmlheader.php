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

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/fancybox/jquery.fancybox-1.3.1.pack.js"></script>
	<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/fancybox/jquery.fancybox-1.3.1.css" media="screen" type="text/css">

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.pie.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/flot/jquery.flot.orderBars.js"></script>

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.metadata.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.tablesorter.min.js"></script>

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.scrollTo.js"></script>

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.jplayer/jquery.jplayer.js"></script>
	<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/jquery.jplayer/jplayer.blue.monday.css" type="text/css">

	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/countrycountry.js.php"></script>
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
	<script type="text/javascript">
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', 'UA-18639058-1']);
		_gaq.push(['_trackPageview']);
		(function() {
			var ga = document.createElement('script');
			ga.type = 'text/javascript';
			ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0];
			s.parentNode.insertBefore(ga, s);
		})();
	</script>
</head>
<body>
<div id="header">
	<h1>
		<a href="<?php echo SITEROOT_WEB; ?>">NEMA: Country/country</a>
		<sub><small>beta</small></sub>
	</h1>
</div>
<div id="body">
	<a class="fancybox" id="showfeedbackform" href="#feedbackform">Send feedback</a>
	<!--[if IE]>
		<div id="iewarning"><img src="<?php echo SITEROOT_WEB; ?>images/exclamation.png" alt="!"> This demo isn't fully functional in Internet Explorer. Please try in a Gecko or Webkit based browser.</div>
	<![endif]-->
