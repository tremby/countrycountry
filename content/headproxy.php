<?php

if ($_SERVER["REQUEST_METHOD"] != "HEAD")
	badrequest("only HEAD supported");

if (!isset($_GET["url"]))
	badrequest("no url parameter given");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $_GET["url"]);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$requestheaders = array();
if (isset($_SERVER["HTTP_ACCEPT"]))
	$requestheaders[] = "Accept: " . $_SERVER["HTTP_ACCEPT"];
if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
	$requestheaders[] = "Accept-Language: " . $_SERVER["HTTP_ACCEPT_LANGUAGE"];
curl_setopt($ch, CURLOPT_HTTPHEADER, $requestheaders);

if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"]))
	curl_setopt($ch, CURLOPT_USERPWD, $_SERVER["PHP_AUTH_USER"] . ":" . $_SERVER["PHP_AUTH_PW"]);

$responseheadersstring = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

var_dump(nl2br($responseheadersstring));

// narrow to the last chunk of headers (after the last redirect) and then copy 
// the headers to our output, but skipping cache control headers since we don't 
// want any 304 not modified responses
foreach (explode("\r\n", array_pop(preg_split('%^HTTP/%m', $responseheadersstring))) as $responseheader) {
	if (empty($responseheader))
		continue;
	if (preg_match('%^HTTP/%', $responseheader))
		header($responseheader, true, $info["http_code"]);
	if (preg_match('%^If-((Unm|M)odified-Since|(None-)?Match|Range):%', $responseheader))
		continue;
	header($responseheader);
}

?>
