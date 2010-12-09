<?php

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
require_once SITEROOT_LOCAL . "include/Graphite.php";

$errors = array();

if (!isset($_REQUEST["id"]))
	badrequest("no endpoint ID given");
if (!Endpoint::exists($_REQUEST["id"], true))
	badrequest("no endpoint with the given ID is saved");

$endpoint = Endpoint::load($_REQUEST["id"], true);

if (isset($_POST["refreshendpoint"])) {
	$endpoint->clearcache();
	if ($endpoint->probe() && $endpoint->save()) {
		flash("Endpoint capabilities successfully refreshed");
		redirect("editendpoint?id=" . $endpoint->hash());
	} else
		$errors = array_merge(array("Probing the endpoint failed. Specific messages follow."), $endpoint->errors());
}

if (isset($_POST["editendpoint"])) {
	if (isset($_POST["endpointname"])) {
		if (empty($_POST["endpointname"]))
			$errors[] = "Endpoint name can't be empty";
		if (empty($errors)) {
			$endpoint->name($_POST["endpointname"]);
			$endpoint->description($_POST["endpointdescription"]);
			if (!$endpoint->save())
				$errors[] = "Endpoint couldn't be saved";
			if (empty($errors)) {
				flash("Endpoint information saved");
				redirect("editendpoint?id=" . $endpoint->hash());
			}
		}
	}
}

$title = "Edit endpoint '" . $endpoint->name() . "'";
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

<h3>Edit endpoint information</h3>
<form action="<?php echo SITEROOT_WEB; ?>editendpoint" method="post">
	<dl>
		<dt><label for="endpointname">Name</label></dt>
		<dd><input type="text" size="64" name="endpointname" id="endpointname" value="<?php echo htmlspecialchars(isset($_REQUEST["endpointname"]) ? $_REQUEST["endpointname"] : $endpoint->name()); ?>"></dd>

		<dt>URL</dt>
		<dd>
			<code><?php echo htmlspecialchars($endpoint->url()); ?></code>
			<p class="hint">This can't be changed</p>
		</dd>

		<dt><label for="endpointdescription">Description</label></dt>
		<dd><textarea cols="64" rows="4" name="endpointdescription"><?php echo htmlspecialchars(isset($_REQUEST["endpointdescription"]) ? $_REQUEST["endpointdescription"] : $endpoint->description()); ?></textarea></dd>

		<dt>Update</dt>
		<dd>
			<input type="hidden" name="id" value="<?php echo $endpoint->hash(); ?>">
			<input type="submit" name="editendpoint" value="Save">
		</dd>
	</dl>
</form>

<h3>Actions</h3>
<ul>
	<li>
		<form action="<?php echo SITEROOT_WEB; ?>editendpoint" method="post">
			<input type="hidden" name="id" value="<?php echo $endpoint->hash(); ?>">
			<input type="submit" name="refreshendpoint" value="Refresh capabilities">
			<span class="hint">Clear the endpoint's cache and refresh its capabilities (shown below)</span>
		</form>
	</li>
	<li>
		<form action="<?php echo SITEROOT_WEB; ?>deleteendpoint" method="post">
			<input type="hidden" name="id" value="<?php echo $endpoint->hash(); ?>">
			<input type="submit" name="deleteendpoint" value="Delete" id="deletebutton_<?php echo $endpoint->hash(); ?>">
			<span class="hint">Remove this endpoint</span>
		</form>
	</li>
</ul>

<h3>Capabilities</h3>
<dl>
	<?php foreach ($endpoint->capabilities() as $cap) { ?>
		<dt><?php echo htmlspecialchars($cap->name()); ?></dt>
		<dd>
			<p><?php echo htmlspecialchars($cap->description()); ?></p>
			<?php if (count($cap->triples())) { ?>
				<p>This was determined by finding the following example triples.</p>
				<?php
				$graph = new Graphite($GLOBALS["ns"]);
				$graph->addTriples($cap->triples());
				echo $graph->dump();
				?>
			<?php } ?>
		</dd>
	<?php } ?>
</dl>

<?php
include "htmlfooter.php";
?>
