<?php

if (isset($_SESSION["cc_myexp_user"])) {
	unset($_SESSION["cc_myexp_user"]);
	flash("You have logged out");
} else
	flash("You weren't logged in anyway");

redirect(SITEROOT_WEB);

?>
