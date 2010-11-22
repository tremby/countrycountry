<?php

define("MYEXPERIMENT_DOMAIN", "sandbox.myexperiment.org");

$title = "Log in";

if (isset($_SESSION["cc_myexp_user"])) {
	include "htmlheader.php";
	?>
	<h1><?php echo htmlspecialchars($title); ?></h1>
	<p>You're already logged in as <?php echo htmlspecialchars($_SESSION["cc_myexp_user"]["name"]); ?>.</p>
	<ul><li><a href="<?php echo SITEROOT_WEB; ?>logout">Log out</a></li></ul>
	<?php
	include "htmlfooter.php";
	exit;
}

if (isset($_POST["username"])) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	require_once "Graphite.php";

	// make a Reader object and set it to use our credentials with HTTP basic
	$reader = ARC2::getComponent("Reader", array("arc_reader_credentials" => array(MYEXPERIMENT_DOMAIN => $_POST["username"] . ":" . $_POST["password"])));

	// make a Parser object and set it to use the Reader above
	$parser = ARC2::getRDFParser();
	$parser->setReader($reader);

	// fetch the myexperiment whoami RDF
	$parser->parse("http://" . MYEXPERIMENT_DOMAIN . "/whoami.rdf");

	// continue if there are no errors
	$errors = $reader->getErrors();
	if (empty($errors)) {
		// put the triples in a Graphite graph
		$graph = new Graphite($GLOBALS["ns"]);
		$graph->addTriples($parser->getTriples());

		// store user info in session data
		$user = $graph->allOfType("mebase:User")->current();

		$_SESSION["cc_myexp_user"] = array(
			"uri" => (string) $user->uri,
			"homepage" => (string) $user->get("foaf:homepage"),
			"name" => (string) $user->get("sioc:name"),
			"avatar" => (string) $user->get("sioc:avatar"),
		);

		flash("You have logged in as " . htmlspecialchars($user->get("sioc:name")));
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

?>

<?php
include "htmlfooter.php";
?>
