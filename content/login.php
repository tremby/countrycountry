<?php

$title = "Log in";

if (user_loggedin()) {
	include "htmlheader.php";
	?>
	<h1><?php echo htmlspecialchars($title); ?></h1>
	<p>You're already logged in as <?php echo htmlspecialchars(user_name()); ?>.</p>
	<ul><li><a href="<?php echo SITEROOT_WEB; ?>logout">Log out</a></li></ul>
	<?php
	include "htmlfooter.php";
	exit;
}

if (isset($_POST["username"])) {
	$errors = array();
	if (user_login($_POST["username"], $_POST["password"], $errors)) {
		flash("You have logged in as " . htmlspecialchars(user_name()));
		redirect(SITEROOT_WEB);
		exit;
	}
	// there were errors -- show the login form again with error messages
}

include "htmlheader.php";
?>
<h1><?php echo htmlspecialchars($title); ?></h1>

<?php if (isset($errors) && !empty($errors)) { ?>
	<div class="error">
		<h2>Errors</h2>
		<ul>
			<?php foreach ($errors as $error) { ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>

<p>Log in using your <?php echo htmlspecialchars(MYEXPERIMENT_DOMAIN); ?> account details. (These aren't stored.)</p>
<form action="<?php echo SITEROOT_WEB; ?>login" method="post">
	<dl>
		<dt><label for="username">Username</label></dt>
		<dd><input type="text" name="username" id="username" size="32"<?php if (isset($_POST["username"])) { ?> value="<?php echo htmlspecialchars($_POST["username"]); ?>"<?php } ?>></dd>

		<dt><label for="password">Password</label></dt>
		<dd><input type="password" name="password" id="password" size="32"></dd>

		<dt>Submit</dt>
		<dd><input type="submit" name="login" value="Log in"></dd>
	</dl>
</form>

<?php
include "htmlfooter.php";
?>
