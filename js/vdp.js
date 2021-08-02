"use strict";
var vdp_busy = false;
var vdp_waiting_timer = 30;
var vdp_vars = {'resources-loaded' : false};
if (window.jQuery) {
	jQuery(document).ready(function(){
		if (typeof vdp_ajax_url != typeof undefined) {
			vdp_vars["mode"] = "local";
			vdp_vars["resources-loaded"] = true;
			vdp_vars["ajax-url"] = vdp_ajax_url;
			vdp_init();
			vdp_ready();
		} else {
			vdp_vars["mode"] = "remote";
			if (jQuery("#vdp-remote").length == 0 || !jQuery("#vdp-remote").attr("data-handler")) {
				alert('Make sure that you properly included vdp.js. Currently you did not.');
			}
			vdp_vars["ajax-url"] = jQuery("#vdp-remote").attr("data-handler");
			jQuery('head').append("<style>#vdp-ready{display:none;width:0px;height:0px;}.vdp-error{display:none;}.vdp-ready,.vdpgoal-ready,.vdptop-ready{display:none;}</style>");
			jQuery('body').append("<div id='vdp-ready'></div>");
			vdp_init();
		}
	});
} else {
	alert('vdp.js requires jQuery to be loaded. Please include jQuery library above vdp.js. Do not use "defer" or "async" option to load jQuery.');
}

function vdp_init() {
	var forms = new Array();
	var intRegex = /^\d+$/;
	jQuery(".vdp").each(function(){
		var campaign_id = jQuery(this).attr("data-id");
		if (campaign_id && intRegex.test(campaign_id)) {
			if (forms.indexOf(campaign_id) < 0) forms.push(campaign_id);
		} else jQuery(this).attr("class", "vdp-error");
	});

	jQuery.ajax({
		url		: 	vdp_vars['ajax-url'],
		data	: 	{"action" : "vdp-init", "forms" : forms.join(","), "hostname" : window.location.hostname},
		method	:	(vdp_vars["mode"] == "remote" ? "get" : "post"),
		dataType:	(vdp_vars["mode"] == "remote" ? "jsonp" : "json"),
		async	:	true,
		success	: 	function(return_data) {
			try {
				var data;
				if (typeof return_data == 'object') data = return_data;
				else data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					jQuery(".vdp").each(function(){
						var campaign_id = jQuery(this).attr("data-id");
						if (campaign_id && intRegex.test(campaign_id)) {
							if (data['forms'].hasOwnProperty(campaign_id)) {
								jQuery(this).replaceWith(data['forms'][campaign_id]);
							} else jQuery(this).attr("class", "vdp-error");
						} else jQuery(this).attr("class", "vdp-error");
					});
					if (!vdp_vars["resources-loaded"]) {
						if (data.hasOwnProperty("css") && data["css"].length > 0) {
							for (var i=0; i<data["css"].length; i++) {
								jQuery('head').append("<link href='"+data["css"][i]+"' rel='stylesheet' type='text/css' media='all' />");
							}
						}
						if (data.hasOwnProperty("js") && data["js"].length > 0) {
							for (var i=0; i<data["js"].length; i++) {
								jQuery('body').append("<script src='"+data["js"][i]+"' type='text/javascript'></script>");
							}
						}
						var counter = 50;
						var ready = function() {
							counter--;
							if (counter == 0) {
								console.log("Can't load style.css.");
								return;
							}
							var width = jQuery("#vdp-ready").width();
							if (width == 1) {
								vdp_ready();
							} else {
								setTimeout(ready, 200);
							}
						}
						ready();
						vdp_vars["resources-loaded"] = true;
					} else vdp_resize();
				}
			} catch(error) {
				console.log(error);
			}
		},
		error	: 	function(XMLHttpRequest, textStatus, errorThrown) {
			console.log(errorThrown);
		}
	});
}

function vdp_ready() {
	vdp_resize();
	jQuery(window).resize(function() {
		vdp_resize();
	});
	console.log("Verified Downloads is ready to go!");
}

function vdp_resize() {
	jQuery("div.vdp-ready").each(function(){
		var container_width = parseInt(jQuery(this).parent().width(), 10);
		if (container_width < 480) jQuery(this).addClass("vdp-collapsed");
		else jQuery(this).removeClass("vdp-collapsed");
	});
}

function vdp_continue(_object) {
	if (vdp_busy) return;
	vdp_busy = true;
	jQuery(_object).find("i").attr("class", "vdp-fa vdp-fa-spin vdp-fa-spinner");
	jQuery(_object).closest(".vdp-ready").find(".vdp-inline-error").slideUp(200);
	var form = jQuery(_object).closest(".vdp-ready");
	jQuery(form).find(".vdp-element-error").fadeOut(300, function(){
		jQuery(this).remove();
	});
	var post_data = {
		"action"		: "vdp-continue",
		"file-id"		: jQuery(form).find("[name='file-id']").val(),
		"purchase-code"	: jQuery(form).find("[name='purchase-code']").val(),
		"terms"			: jQuery(form).find("[name='terms']").is(":checked") ? "on" : "off",
		"hostname"		: window.location.hostname
	};
	jQuery.ajax({
		url		:	vdp_vars["ajax-url"], 
		data	:	post_data,
		method	:	(vdp_vars["mode"] == "remote" ? "get" : "post"),
		dataType:	(vdp_vars["mode"] == "remote" ? "jsonp" : "json"),
		async	:	true,
		success	:	function(return_data) {
			var data;
			if (typeof return_data == 'object') data = return_data;
			else data = jQuery.parseJSON(return_data);
			if (data.status == "OK") {
				try {
					var error = false;
					if (data.hasOwnProperty("action")) {
						if (data["action"] == "redirect") {
							if (data.hasOwnProperty("url")) {
								location.href = data["url"];
							}
						} else error = true;
					} else error = true;
					if (error) {
						console.log(data);
						jQuery(_object).closest(".vdp-ready").find(".vdp-warning").html("Internal Error.");
						jQuery(_object).closest(".vdp-ready").find(".vdp-warning").slideDown(200);
					}
				} catch(error) {
					jQuery(_object).closest(".vdp-ready").find(".vdp-warning").html("Internal Error.");
					jQuery(_object).closest(".vdp-ready").find(".vdp-warning").slideDown(200);
				}
			} else if (data.status == "ERROR") {
				for (var id in data["errors"]) {
					if (data["errors"].hasOwnProperty(id)) {
						jQuery(form).find(".vdp-input-"+id).append("<div class='vdp-element-error'><span>"+data["errors"][id]+"</span></div>");
					}
				}
				jQuery(form).find(".vdp-element-error").fadeIn(300);
			} else if (data.status == "WARNING") {
				jQuery(_object).closest(".vdp-ready").find(".vdp-warning").html(data.message);
				jQuery(_object).closest(".vdp-ready").find(".vdp-warning").slideDown(200);
			} else {
				jQuery(_object).closest(".vdp-ready").find(".vdp-warning").html("Internal Error.");
				jQuery(_object).closest(".vdp-ready").find(".vdp-warning").slideDown(200);
			}
			jQuery(_object).find("i").attr("class", "vdp-fa vdp-fa-right-open");
			vdp_busy = false;
		},
		error	:	function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(_object).closest(".vdp-ready").find(".vdp-warning").html(textStatus);
			jQuery(_object).closest(".vdp-ready").find(".vdp-warning").slideDown(200);
			jQuery(_object).find("i").attr("class", "vdp-fa vdp-fa-right-open");
			vdp_busy = false;
		}
	});
	return false;
}

function vdp_read_cookie(key) {
	var pairs = document.cookie.split("; ");
	for (var i = 0, pair; pair = pairs[i] && pairs[i].split("="); i++) {
		if (pair[0] === key) return pair[1] || "";
	}
	return null;
}
function vdp_write_cookie(key, value, days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	} else var expires = "";
	document.cookie = key+"="+value+expires+"; path=/";
}
function vdp_utf8encode(string) {
	string = string.replace(/\x0d\x0a/g, "\x0a");
	var output = "";
	for (var n = 0; n < string.length; n++) {
		var c = string.charCodeAt(n);
		if (c < 128) {
			output += String.fromCharCode(c);
		} else if ((c > 127) && (c < 2048)) {
			output += String.fromCharCode((c >> 6) | 192);
			output += String.fromCharCode((c & 63) | 128);
		} else {
			output += String.fromCharCode((c >> 12) | 224);
			output += String.fromCharCode(((c >> 6) & 63) | 128);
			output += String.fromCharCode((c & 63) | 128);
		}
	}
	return output;
}
function vdp_encode64(input) {
	var keyString = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	var output = "";
	var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
	var i = 0;
	input = vdp_utf8encode(input);
	while (i < input.length) {
		chr1 = input.charCodeAt(i++);
		chr2 = input.charCodeAt(i++);
		chr3 = input.charCodeAt(i++);
		enc1 = chr1 >> 2;
		enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
		enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
		enc4 = chr3 & 63;
		if (isNaN(chr2)) {
			enc3 = enc4 = 64;
		} else if (isNaN(chr3)) {
			enc4 = 64;
		}
		output = output + keyString.charAt(enc1) + keyString.charAt(enc2) + keyString.charAt(enc3) + keyString.charAt(enc4);
	}
	return output;
}
function vdp_utf8decode(input) {
	var string = "";
	var i = 0;
	var c = c1 = c2 = 0;
	while ( i < input.length ) {
		c = input.charCodeAt(i);
		if (c < 128) {
			string += String.fromCharCode(c);
			i++;
		} else if ((c > 191) && (c < 224)) {
			c2 = input.charCodeAt(i+1);
			string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
			i += 2;
		} else {
			c2 = input.charCodeAt(i+1);
			c3 = input.charCodeAt(i+2);
			string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
			i += 3;
		}
	}
	return string;
}
function vdp_decode64(input) {
	var keyString = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	var output = "";
	var chr1, chr2, chr3;
	var enc1, enc2, enc3, enc4;
	var i = 0;
	input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
	while (i < input.length) {
		enc1 = keyString.indexOf(input.charAt(i++));
		enc2 = keyString.indexOf(input.charAt(i++));
		enc3 = keyString.indexOf(input.charAt(i++));
		enc4 = keyString.indexOf(input.charAt(i++));
		chr1 = (enc1 << 2) | (enc2 >> 4);
		chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
		chr3 = ((enc3 & 3) << 6) | enc4;
		output = output + String.fromCharCode(chr1);
		if (enc3 != 64) {
			output = output + String.fromCharCode(chr2);
		}
		if (enc4 != 64) {
			output = output + String.fromCharCode(chr3);
		}
	}
	output = vdp_utf8decode(output);
	return output;
}