<?php
// vim: ft=javascript
require "constants.php";
?>

$(document).ready(function() {
	// slidey definition lists
	$("dl.single > dd").hide();
	$("dl.single > dt:first-child + dd").show();
	$("dl.single > dt").prepend("<a class=\"expandlink\" href=\"#\"></a>");
	$("dl.single > dt a.expandlink").click(function(e) {
		e.preventDefault();
		$(this).parents("dl:first").children("dd").not($(this).parents("dt:first").next("dd:first")).slideUp("fast");
		$(this).parents("dt:first").next("dd:first").slideDown("fast");
	});

	// logic for list of collection URIs for results comparison
	removecollectionuri = function() {
		$(this).parents("li:first").remove();
		if ($("#uris li").length == 0)
			$("#noneyet").show();
	};
	adduri = function(uri) {
		var found = false;
		$("#uris input").each(function() {
			if ($(this).val() == uri) {
				alert("This URI is already in the list");
				found = true;
				return false; // jquery.each break
			}
		});
		if (found)
			return;
		$.get("<?php echo SITEROOT_WEB; ?>collectioninfo", {"uri": uri}, function(data, textstatus, xhr) {
			$("#uris").append("<li><a class=\"deletecollectionuributton\" href=\"#\"><img src=\"<?php echo SITEROOT_WEB; ?>images/delete.png\" alt=\"Delete\" title=\"Remove this collection from the list\"></a> <input type=\"hidden\" name=\"uri[]\" value=\"" + data.uri + "\"><em>" + data.title + "</em> by " + data.creator + " (" + data.signalcount + " tracks)" + " <a href=\"" + uri + "\" title=\"URI\"><img src=\"<?php echo SITEROOT_WEB; ?>images/uri.png\" alt=\"URI\"></a></li>");
			$("#uris .deletecollectionuributton").unbind("click").click(removecollectionuri);
			$("#addcollectionuri").val("");
			$("#noneyet").hide();
		});
	};
	$("#uris .deletecollectionuributton").click(removecollectionuri);
	$("#addcollectionuributton").click(function() {
		adduri($("#addcollectionuri").val());
	});
	$("#addcollectionuridropdownbutton").click(function() {
		adduri($("#addcollectionuridropdown").val());
	});
	$("#viewcollectionresultsbutton").click(function() {
		if ($("#uris li").length == 0) {
			alert("You haven't added any collections to the list yet");
			return false;
		}
		return true;
	});

	// delete collection button
	$(".deletebutton").click(function() {
		return confirm("Are you sure you want to delete the collection '" + $(this).parents("tr:first").find("td:first").text() + "' and all its groundings?");
	});

	// stripe tables
	$("table tbody tr").filter(":even").addClass("even").end().filter(":odd").addClass("odd");

	// make tables sortable
	$("table.tablesorter").tablesorter({widgets: ["zebra"], textExtraction: "complex"});

	// set up fancyboxes
	$("a.fancybox").fancybox();

	// scroll left and right buttons
	$("#scrollleft").click(function() {
		$(this).parents(".scroll:first").scrollTo({"top": "+=0", "left": "-=410"}, 800);
	});
	$("#scrollright").click(function() {
		$(this).parents(".scroll:first").scrollTo({"top": "+=0", "left": "+=410"}, 800);
	});
	showhidescrollbuttons();
	$(window).resize(showhidescrollbuttons);

	// ajax error handler
	$(document).ajaxError(function(e, xhr, options, er) {
		alert("Error " + xhr.status + " (" + xhr.statusText + "): " + xhr.responseText);
	});

	// feedback form
	$("#feedbackform form").submit(function() {
		$.post("<?php echo SITEROOT_WEB; ?>submitfeedback", $(this).serialize(), function() {
			$("#feedback_subject, #feedback_feedback").val("");
			$.fancybox.close();
			successalert("Feedback has been sent");
		});
		return false;
	});
});
function showhidescrollbuttons() {
	$(".scroll").each(function() {
		if ($(this).children().first().width() > $(this).width() && $(this).height() > $(window).height() / 2)
			$(this).find("#scrollleft, #scrollright").show();
		else
			$(this).find("#scrollleft, #scrollright").hide();
	});
}
function plotgraph(md5sum, plotto, xmax) {
	//console.log("plotting graph " + md5sum + "!");
	var data = [];
	$.each(graphs[md5sum].series, function(k, v) {
		if ($("#framedatachart_" + md5sum + "_series_" + k).is(":checked")) {
			var subdata = [];
			if (plotto == null)
				data.push(graphs[md5sum].series[k]);
			else {
				for (var i = 0; i < graphs[md5sum].series[k].data.length; i++) {
					if (graphs[md5sum].series[k].data[i][0] < plotto)
						subdata.push(graphs[md5sum].series[k].data[i]);
				}
				data.push({label: graphs[md5sum].series[k].label,data:subdata});
			}
		} else
			data.push({label:graphs[md5sum].series[k].label,data:[]});
	});
	return $.plot($("#framedatachart_" + md5sum), data, {xaxis:{min:0,max:xmax},legend:{show:false}});
}
function successalert(message) {
	$("body").prepend("<div id=\"successalert\">" + message + "</div>");
	$("#successalert").hide().fadeIn("fast").delay(2000).fadeOut("slow", function() {
		$(this).remove();
	});
}
