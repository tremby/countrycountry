$(document).ready(function() {
	$("dl.single > dd").hide();
	$("dl.single > dt:first-child + dd").show();
	$("dl.single > dt").prepend("<a class=\"expandlink\" href=\"#\">+</a>");
	$("dl.single > dt a.expandlink").click(function(e) {
		e.preventDefault();
		$(this).parents("dl:first").children("dd").not($(this).parents("dt:first").next("dd:first")).hide();
		$(this).parents("dt:first").next("dd:first").show();
	});
});
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
