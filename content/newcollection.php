<?php

$errors = array();
$endpoints = array();

if (isset($_POST["newcollection"])) {
	if (!isset($_POST["endpoints"]) || empty($_POST["endpoints"]))
		$errors[] = "You need to select at least one endpoint";
	else {
		$endpoints = Endpoint::load($_POST["endpoints"], true);
		foreach ($endpoints as $ep)
			if (!$ep->hascapability("relationships"))
				$errors[] = "Endpoint '" . $ep->name() . "' doesn't have relationship data and so can't be used for building a collection";
	}

	if (empty($errors)) {
		$collection = new Collection($endpoints);
		$collection->sessionStore();
		redirect(SITEROOT_WEB . "editcollection/" . $collection->id());
	}
}

$title = "New collection";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php if (!empty($errors)) { ?>
	<div class="errors">
		<h3>Errors</h3>
		<ul>
			<?php foreach ($errors as $error) { ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>

<p>First you need to choose which endpoints you want to build your collection from.</p>

<?php if (count(Endpoint::allwith("relationships")) == 0) { ?>
	<p>No endpoints with artist/record/track relationships are currently defined.</p>
<?php } else { ?>
	<form action="<?php echo SITEROOT_WEB; ?>newcollection" method="post">
		<ul id="endpointselect">
			<?php foreach (Endpoint::allwith("relationships") as $endpoint) { ?>
				<li>
					<label>
						<input type="checkbox" name="endpoints[]" id="endpoints_<?php echo $endpoint->hash(); ?>" value="<?php echo $endpoint->hash(); ?>"<?php if (!isset($_POST["newcollection"]) || isset($_POST["endpoints"]) && in_array($endpoint->hash(), $_POST["endpoints"])) { ?> checked="checked"<?php } ?>>
						<?php echo htmlspecialchars($endpoint->name()); ?>
					</label>
					<p class="hint"><?php echo htmlspecialchars($endpoint->description()); ?></p>
				</li>
			<?php } ?>
		</ul>
		<input type="submit" name="newcollection" value="Make new collection">
	</form>
	<h3>This combination provides</h3>
	<ul id="commoncapabilities">
		<?php
		if (!isset($_POST["newcollection"]))
			$endpoints = Endpoint::allwith("relationships");
		foreach (Endpoint::commoncapabilities($endpoints) as $cap) { ?>
			<li><?php echo htmlspecialchars($cap->name()); ?></li>
		<?php } ?>
	</ul>
<?php } ?>

<?php
include "htmlfooter.php";
?>
