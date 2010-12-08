<?php
$title = "Endpoint administration";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<h3>Existing endpoints</h3>
<?php
$endpoints = Endpoint::loadall();
if (empty($endpoints)) { ?>
	<p>No endpoints have yet been saved.</p>
<?php } else { ?>
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
			<?php foreach (Endpoint::loadall() as $endpoint) { ?>
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
