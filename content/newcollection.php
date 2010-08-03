<?php

$collection = new Collection();
$collection->sessionStore();
redirect(SITEROOT_WEB . "editcollection/" . $collection->id());

?>
