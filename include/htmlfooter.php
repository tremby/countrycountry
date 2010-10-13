	<div class="hidden" id="feedbackform">
		<h3>Send feedback</h3>
		<form action="<?php echo SITEROOT_WEB; ?>submitfeedback" method="post">
			<dl>
				<dt>Name (optional)</dt>
				<dd><input id="feedback_name" name="name" type="text" size="32"></dd>

				<dt>Email address (optional)</dt>
				<dd><input id="feedback_email" name="email" type="text" size="32"></dd>

				<dt>Subject (optional)</dt>
				<dd><input id="feedback_subject" name="subject" type="text" size="32"></dd>

				<dt>Feedback</dt>
				<dd><textarea id="feedback_feedback" name="feedback" cols="32" rows="6"></textarea></dd>

				<dt>Submit</dt>
				<dd><input id="feedback_sendfeedback" type="submit" name="sendfeedback" value="Submit feedback"></dd>

				<dt>Alternatively</dt>
				<dd>You can <a href="mailto:krp@ecs.soton.ac.uk,bjn@ecs.soton.ac.uk?subject=Country/country%20feedback">email Kevin Page and Bart Nagel</a> with your thoughts</dd>
			</dl>
		</form>
	</div>
</div>
<div id="footer">
	<p><a href="http://www.nema.ecs.soton.ac.uk">NEMA project</a></p>
	<p>
		A collaboration between
		<a href="http://www.ecs.soton.ac.uk">the School of Electronics and Computer Science, University of Southampton</a>,
		<a href="http://www.oerc.ox.ac.uk">Oxford e-Research Centre, University of Oxford</a>
		and
		<a href="http://www.gold.ac.uk/computing">Department of Computing, Goldsmiths University of London</a>
	</p>
	<p>Many thanks to Stephen Downie and his team at <a href="http://www.music-ir.org/">IMIRSEL</a>, University of Illinois, for access and source code to their "Son of Blinkie" genre classification workflow.</a>
</div>
</body>
</html>
