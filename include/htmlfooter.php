	<div class="hidden" id="feedbackform">
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
			</dl>
		</form>
	</div>
</div>
<div id="footer">
	<a href="http://www.nema.ecs.soton.ac.uk">NEMA project</a>
	<br>
	A collaboration between
	<a href="http://www.ecs.soton.ac.uk">the School of Electronics and Computer Science, University of Southampton</a>,
	<a href="http://www.oerc.ox.ac.uk">Oxford e-Research Centre, University of Oxford</a>
	and
	<a href="http://www.gold.ac.uk/computing">Department of Computing, Goldsmiths University of London</a>
</div>
</body>
</html>
