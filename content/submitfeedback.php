<?php

header("Content-Type: text/plain");

array_map("trim", $_POST);

$errors = array();

if (!isset($_POST["feedback"]) || empty($_POST["feedback"]))
	$errors[] = "Feedback is empty";

if (isset($_POST["email"]) && !empty($_POST["email"]) && !isemail($_POST["email"]))
	$errors[] = "Email address given is not valid -- you can leave it blank if you don't require a reply";

if (!empty($errors))
	badrequest($errors);

$emailaddress = "countrycountry@nema.ecs.soton.ac.uk";
$sender = "\"Country/country script\" <$emailaddress>";
$from = null;

if (isset($_POST["email"]) && !empty($_POST["email"]))
	$from = $_POST["email"];

if (isset($_POST["name"]) && !empty($_POST["name"]))
	$from = mb_encode_mimeheader($_POST["name"], "UTF-8", "Q") . (!is_null($from) ?  " <" . $from . ">" : "<no.address.given@nema.ecs.soton.ac.uk>");

if (is_null($from))
	$from = $sender;

if (mail(
	"Bart Nagel <bjn@ecs.soton.ac.uk>, Kevin Page <krp@ecs.soton.ac.uk>",
	mb_encode_mimeheader("Country/country feedback" . (isset($_POST["subject"]) && !empty($_POST["subject"]) ? ": " . $_POST["subject"] : ""), "UTF-8", "Q"),
	quoted_printable_encode(wordwrap($_POST["feedback"], 70)),
	"Content-Type: text/plain; charset=UTF-8\r\n"
	. "Content-Transfer-Encoding: quoted-printable\r\n"
	. "From: $from\r\n"
	. "Sender: $sender"
))
	ok();

servererror("mail failed to send");

?>
