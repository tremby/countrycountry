<?php

setcookie("cc_author", "", time() - 3600, SITEROOT_WEB);
redirect(SITEROOT_WEB . "existingcollections");

?>
