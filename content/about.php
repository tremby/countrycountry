<?php
$title = "About country/country";
include "htmlheader.php";
?>
<h2><?php echo htmlspecialchars($title); ?></h2>

<ul>
	<li><a href="http://www.nema.ecs.soton.ac.uk/CountryCountry-abstract-krp-20100705-03.pdf">Download abstract as PDF</a></li>
	<li><a href="http://www.nema.ecs.soton.ac.uk/CountryCountry-poster.pdf">Download poster as PDF</a></li>
</ul>

<h1 style="text-align: center;">Semantics for signal and result collections through Linked Data:<br>How country is my country?</h1>
<p style="text-align: center;">
	Kevin R. Page <sup>1,3</sup>,
	Benjamin Fields <sup>2</sup>,
	Tim Crawford <sup>2</sup>,
	David C. De Roure <sup>1,3</sup>,
	Gianni O’Neill <sup>1</sup>,
	Bart J. Nagel <sup>1</sup>
</p>
<p style="text-align: center;">
	<sup>1</sup> School of Electronics and Computer Science, University of Southampton, UK<br>
	<sup>2</sup> Department of Computing, Goldsmiths University of London, UK<br>
	<sup>3</sup> Oxford e-Research Centre, University of Oxford, UK
</p>

<div style="float: right; width: 320px; margin: 0 0 1em 1em;">
	<a href="http://www.nema.ecs.soton.ac.uk/CountryCountry-poster.pdf"><img src="<?php echo SITEROOT_WEB; ?>images/CountryCountry-poster.jpg" alt="Country/country poster"></a>
</div>

<h2>Scope and motivation</h2>
<p>The Linked Data movement encourages a Semantic Web
built upon HTTP URIs that are published, linked, and retrieved
using RDF and SPARQL.</p>

<p>Employing existing ontologies including GeoNames and the
Music Ontology, we present a proof-of-concept system that
demonstrates the utility of Linked Data for enhancing the
application of MIR workﬂows, both when curating collections
of signal for analysis, and publishing results that can be easily
correlated to these, and other, collection sets.</p>

<p>By way of example we gather and publish metadata describing signal collections derived from the country of an
artist; genre analysis over these collections and integration of
collection and result metadata enables us to ask: “<em>how country
is my country?</em>”.</p>

<p>While the demonstrator embodies a speciﬁc analysis (genre)
and collection selection (by nation) to a simple use case, the
approach and technologies are more generic and widely applicable – the common data model of RDF extends a myriad of
possibilities for linking with data models and categorisations
within and without the MIR community.</p>

<h2>System overview</h2>
<p>
The prototype system consists of:
</p>
<ol>
<li><p>
A <strong>signal repository</strong> which serves audio ﬁles using
the standard HTTP protocol and access mechanisms.
For our demonstrator a subset copy of the Jamendo
<a href="http://www.jamendo.com/">collection</a> is used. The signal repository also publishes,
as Linked Data and using the <a href="http://musicontology.com/">Music Ontology</a>, a small
RDF sub-graph for each locally stored audio ﬁle that
describes the relationship to the track it is a recording
of and the “deﬁnitive” identiﬁer (URI) for that track
(as “minted” by the Jamendo Linked Data service at
<a href="http://dbtune.org/jamendo/">dbtune</a>).
</p></li>
<li><p>
A <strong>collection builder</strong> web application that enables a user to publish sets of tracks described using
RDF. Queries to assemble collections take advantage
of Linked Data: the Jamendo service incorporates links
to geographic locations as deﬁned by <a href="http://www.geonames.org/ontology/">GeoNames</a> and
when use the Jamendo SPARQL endpoint we can enrich
our query using the GeoNames ontology and data. For
example, we can identify all the tracks offered by
Jamendo recorded by artists from a speciﬁc country.
</p></li>
<li><p>
The <strong>analysis</strong> is performed by a NEMA genre classiﬁcation
workﬂow:
</p>
<ol>
<li><p>
We have extended the <a href="http://myexperiment.org/">myExperiment</a> collaborative environment to support the Meandre workﬂows used by NEMA.
</p></li>
<li><p>
myExperiment has been modiﬁed to accept the
collections RDF published in step 2) and pass
the target tracks contained within to the analysis
workﬂow.
</p></li>
<li><p>
A head-end workﬂow component has been written to dereference each track URI passed to the
workﬂow and, using the Linked Data published by
the signal repository, retrieve the local copy of the
audio ﬁle as well as the reference to the original
Jamendo identiﬁer. This URI is persisted through
the genre analysis workﬂow until it reaches a new
tail-end component where the analysis is published
using RDF – including links back to the Jamendo
URI.</p></li>
</ol>
</li>
<li><p>
A <strong>results viewer</strong> web application retrieves the
collections RDF from 2) and results RDF from 3),
cross-referencing them via the common identiﬁers used
throughout the system. The user can identify trends in
genre classiﬁcation within and between collections. Results can be pooled through existing and new collections
and inform the creation of new sets.
</p></li>
</ol>

<h2>Acknowledgements</h2>
<p>
Many thanks to Stephen Downie and his team at IMIRSEL,
University of Illinois, for access and source code to their
“Son of Blinkie” genre classiﬁcation workﬂow, and to Michael
Jewell for the inspiring title.
</p>
<p>
This work was carried out through the Networked Environment for Musical Analysis (NEMA) project, funded by the
Andrew W. Mellon Foundation and by the SALAMI project, funded in the UK by the JISC.
</p>

<?php include "htmlfooter.php"; ?>
