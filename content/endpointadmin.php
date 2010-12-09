<?php
$title = "Endpoint administration";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<h3>Existing endpoints</h3>
<?php
$endpoints = Endpoint::all();
if (empty($endpoints)) { ?>
	<p>No endpoints have yet been saved.</p>
<?php } else { ?>
	<p>There <?php echo plural($c = count(Endpoint::all()), "are", "is"); ?> <?php echo $c; ?> endpoint<?php echo plural($c); ?> saved, of which <?php echo $c = count(Endpoint::allwith("relationships")); ?> ha<?php echo plural($c, "ve", "s"); ?> structure information and <?php echo count(Endpoint::allwith("grounding")); ?> can be grounded against. <?php echo $c = count(Endpoint::allwith(array("grounding", "relationships"))); ?> ha<?php echo plural($c, "ve", "s"); ?> both structure information and grounding capabilties.</p>
	<table class="tablesorter {sortlist: [[0,0]]}">
		<thead>
			<tr>
				<th>Name</th>
				<th>URL</th>
				<th>Description</th>
				<th class="{sorter: false}">Capabilities</th>
				<th class="{sorter: false}">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach (Endpoint::all() as $endpoint) { ?>
				<tr id="endpoint_<?php echo $endpoint->hash(); ?>">
					<td><?php echo htmlspecialchars($endpoint->name()); ?></td>
					<td><a href="<?php echo htmlspecialchars($endpoint->url()); ?>"><?php echo htmlspecialchars(parse_url($endpoint->url(), PHP_URL_HOST)); ?>...</a></td>
					<td><?php echo htmlspecialchars($endpoint->description()); ?></td>
					<td>
						<ul>
							<?php foreach ($endpoint->capabilities() as $cap) { ?>
								<li><?php echo htmlspecialchars($cap->name()); ?></li>
							<?php } ?>
						</ul>
					</td>
					<td>
						<ul>
							<li><input type="button" class="deleteendpointbutton" value="Delete" id="deletebutton_<?php echo $endpoint->hash(); ?>"></li>
							<li><a href="<?php echo SITEROOT_WEB; ?>editendpoint?id=<?php echo $endpoint->hash(); ?>">Edit</a></li>
						</ul>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
<?php } ?>

<h3>Actions</h3>
<ul>
	<li><a href="<?php echo SITEROOT_WEB; ?>newendpoint">Add a new endpoint</a></li>
</ul>

<?php
include "htmlfooter.php";
?>
