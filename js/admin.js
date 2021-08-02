"use strict";
var vdp_keyuprefreshtimer;
function vdp_switch_gdpr() {
	if (jQuery("#vdp-gdpr-enable").is(":checked")) {
		jQuery(".vdp-gdpr-depend").fadeIn(200);
	} else {
		jQuery(".vdp-gdpr-depend").fadeOut(200);
	}
}
function vdp_default_copy(_object) {
	var state = jQuery(_object).closest("tbody").attr("data-state");
	jQuery("#vdp-section-button-default").find("input, textarea, select").each(function(){
		var id = jQuery(this).attr("id");
		if (id) {
			id = id.replace("button-", "button-"+state+"-")
			var type = jQuery(this).attr("type");
			if (type == "radio" || type == "checkbox") {
				if (jQuery(this).is(":checked")) jQuery("#"+id).prop("checked", true);
				else jQuery("#"+id).prop("checked", false);
			} else {
				if (jQuery("#"+id).hasClass("vdp-color")) jQuery("#"+id).minicolors('value', jQuery(this).val());
				else jQuery("#"+id).val(jQuery(this).val());
			}
		}
	});
	if (jQuery("#vdp-button-"+state+"-background-gradient-horizontal").is(":checked") || jQuery("#vdp-button-"+state+"-background-gradient-vertical").is(":checked") || jQuery("#vdp-button-"+state+"-background-gradient-diagonal").is(":checked")) jQuery("#vdp-content-button-"+state+"-background-color2").show();
	else jQuery("#vdp-content-button-"+state+"-background-color2").hide();
}
var vdp_sending = false;
function vdp_save_settings(_button) {
	if (vdp_sending) return false;
	vdp_sending = true;
	var button_object = _button;
	jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-spinner vdp-fa-spin");
	jQuery(button_object).addClass("vdp-button-disabled");
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: jQuery(".vdp-form").serialize(),
		success	: function(return_data) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			var data;
			try {
				var data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					vdp_global_message_show('success', data.message);
				} else if (data.status == "ERROR") {
					vdp_global_message_show("danger", data.message);
				} else {
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
				}
			} catch(error) {
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
			vdp_sending = false;
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			vdp_sending = false;
		}
	});
	return false;
}
function vdp_change_source() {
	var source = jQuery("input[name=source]:checked").val();
	jQuery(".vdp-source-data").hide();
	jQuery(".vdp-source-"+source).fadeIn(200);
}
function vdp_change_provider() {
	var options = "";
	var provider = jQuery("input[name=provider]:checked").val();
	if (provider == "free") {
		jQuery(".vdp-not-free").fadeOut(200);
	} else {
		var active_currency = jQuery("select[name=currency]").val();
		if (vdp_currencies.hasOwnProperty(provider)) {
			for (var j=0; j<vdp_currencies[provider].length; j++) {
				options += "<option"+(vdp_currencies[provider][j] == active_currency ? " selected='selected'" : "")+" value='"+vdp_escape_html(vdp_currencies[provider][j])+"'>"+vdp_escape_html(vdp_currencies[provider][j])+"</option>";
			}
			jQuery("select[name=currency]").html(options);
		}
		jQuery(".vdp-not-free").fadeIn(200);
	}
}
function vdp_set_media(_object) {
	var input = _object;
	var media_frame = wp.media({
		title: 'Select File',
		library: {
		},
		multiple: false
	});
	media_frame.on("select", function() {
		var attachment = media_frame.state().get("selection").first();
		jQuery(input).val(attachment.attributes.filename);
		jQuery(input).parent().find("input[type=hidden]").val(attachment.attributes.id);
	});
	media_frame.open();
}
var vdp_uploading = false;
function vdp_uploader_start(_object) {
	var iframe_id = jQuery(_object).attr("target");
	var button_id = jQuery(_object).attr("data-button");
	jQuery("#"+iframe_id).attr("data-loading", "true");
	jQuery("#"+button_id+" label").text(jQuery("#"+button_id).attr("data-loading"));
	jQuery("#"+button_id+" i").attr("class", "vdp-fa vdp-fa-spinner vdp-fa-spin");
}
function vdp_uploader_finish(_object) {
	if (jQuery(_object).attr("data-loading") != "true") return false;
	jQuery(_object).attr("data-loading", "false");
	var button_id = jQuery(_object).attr("data-button");
	jQuery("#"+button_id+" i").attr("class", "vdp-fa vdp-fa-upload");
	jQuery("#"+button_id+" label").text(jQuery("#"+button_id).attr("data-label"));
	var return_data = jQuery(_object).contents().find("html").text();
	try {
		var data;
		if (typeof return_data == 'object') data = return_data;
		else data = jQuery.parseJSON(return_data);
		if (data.status == "OK") {
			var select_id = jQuery("#"+button_id).attr("data-select");
			console.log(data.html);
			jQuery("#"+select_id).html(data.html);
			vdp_global_message_show("success", data.message);
		} else if (data.status == "ERROR") {
			vdp_global_message_show("danger", data.message);
		} else {
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
		}
	} catch(error) {
		vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
	}
}
function vdp_save_file(_button) {
	if (vdp_sending) return false;
	vdp_sending = true;
	var button_object = _button;
	jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-spinner vdp-fa-spin");
	jQuery(button_object).addClass("vdp-button-disabled");
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: jQuery(".vdp-form").serialize(),
		success	: function(return_data) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			var data;
			try {
				var data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					vdp_global_message_show('success', data.message);
					location.href = data.redirect;
				} else if (data.status == "ERROR") {
					vdp_global_message_show('danger', data.message);
				} else {
					vdp_global_message_show('danger', data.message);
				}
			} catch(error) {
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
			vdp_sending = false;
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			vdp_sending = false;
		}
	});
	return false;
}

function vdp_delete_file(_object) {
	if (vdp_sending) return false;
	vdp_modal_open({
		message:		'Please confirm that you want to delete the item.',
		ok_label:		'Delete',
		ok_function:	function(e) {
			vdp_modal_close();
			vdp_sending = true;
			
			var record_id = jQuery(_object).attr("data-id");
			var doing_label = jQuery(_object).attr("data-doing");
			var do_label = jQuery(_object).html();
			jQuery(_object).html("<i class='vdp-fa vdp-fa-spinner vdp-fa-spin'></i> "+doing_label);
			
			jQuery.ajax({
				type	: "POST",
				url		: vdp_ajax_handler, 
				data	: {"action" : "vdp-delete-file", "id" : record_id},
				success	: function(return_data) {
					try {
						var data;
						if (typeof return_data == 'object') data = return_data;
						else data = jQuery.parseJSON(return_data);
						if (data.status == "OK") {
							vdp_global_message_show('success', data.message);
							var row = jQuery(_object).closest("tr");
							jQuery(row).fadeOut(300, function(){
								jQuery(row).remove();
							});
						} else if (data.status == "ERROR") {
							vdp_global_message_show('danger', data.message);
						} else {
							vdp_global_message_show('danger', data.message);
						}
					} catch(error) {
						console.log();
						vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					}
					jQuery(_object).html(do_label);
					vdp_sending = false;
				},
				error	: function(XMLHttpRequest, textStatus, errorThrown) {
					jQuery(_object).html(do_label);
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					vdp_sending = false;
				}
			});
		}
	});
	return false;
}

function vdp_delete_log(_object) {
	if (vdp_sending) return false;
	vdp_modal_open({
		message:		'Please confirm that you want to delete the item.',
		ok_label:		'Delete',
		ok_function:	function(e) {
			vdp_modal_close();
			vdp_sending = true;
			
			var record_id = jQuery(_object).attr("data-id");
			var doing_label = jQuery(_object).attr("data-doing");
			var do_label = jQuery(_object).html();
			jQuery(_object).html("<i class='vdp-fa vdp-fa-spinner vdp-fa-spin'></i> "+doing_label);
			
			jQuery.ajax({
				type	: "POST",
				url		: vdp_ajax_handler, 
				data	: {"action" : "vdp-delete-log", "id" : record_id},
				success	: function(return_data) {
					try {
						var data;
						if (typeof return_data == 'object') data = return_data;
						else data = jQuery.parseJSON(return_data);
						if (data.status == "OK") {
							jQuery(_object).closest("tr").fadeOut(300, function(){
								jQuery(_object).closest("tr").remove();
							});
							vdp_global_message_show("success", data.message);
						} else if (data.status == "ERROR") {
							vdp_global_message_show("danger", data.message);
						} else {
							vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
						}
					} catch(error) {
						vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					}
					jQuery(_object).html(do_label);
					vdp_sending = false;
				},
				error	: function(XMLHttpRequest, textStatus, errorThrown) {
					jQuery(_object).html(do_label);
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					vdp_sending = false;
				}
			});
		}
	});
	return false;
}

function vdp_toggle_log(_object) {
	if (vdp_sending) return false;
	vdp_sending = true;
	var record_id = jQuery(_object).attr("data-id");
	var record_status = jQuery(_object).attr("data-status");
	var doing_label = jQuery(_object).attr("data-doing");
	var do_label = jQuery(_object).html();
	jQuery(_object).html("<i class='vdp-fa vdp-fa-spinner vdp-fa-spin'></i> "+doing_label);
	var post_data = {"action" : "vdp-toggle-log", "id" : record_id, "status" : record_status};
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: post_data,
		success	: function(return_data) {
			jQuery(_object).html(do_label);
			try {
				var data;
				if (typeof return_data == 'object') data = return_data;
				else data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					jQuery(_object).html(data.record_action);
					jQuery(_object).attr("data-status", data.record_status);
					jQuery(_object).attr("data-doing", data.record_action_doing);
					if (data.record_status == "enabled") jQuery(_object).closest("tr").find(".vdp-table-list-badge-status").html("");
					else jQuery(_object).closest("tr").find(".vdp-table-list-badge-status").html("<span class='vdp-badge vdp-badge-danger'>"+data.record_badge+"</span>");
					vdp_global_message_show("success", data.message);
				} else if (data.status == "ERROR") {
					vdp_global_message_show("danger", data.message);
				} else {
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
				}
			} catch(error) {
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
			jQuery(_object).closest("tr").find(".row-actions").removeClass("visible");
			vdp_sending = false;
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(_object).html(do_label);
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			vdp_sending = false;
		}
	});
	return false;
}

function vdp_file_status_toggle(_object) {
	if (vdp_sending) return false;
	vdp_sending = true;
	var record_id = jQuery(_object).attr("data-id");
	var record_status = jQuery(_object).attr("data-status");
	var doing_label = jQuery(_object).attr("data-doing");
	var do_label = jQuery(_object).html();
	jQuery(_object).html("<i class='vdp-fa vdp-fa-spinner vdp-fa-spin'></i> "+doing_label);
	var post_data = {"action" : "vdp-file-status-toggle", "id" : record_id, "status" : record_status};
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: post_data,
		success	: function(return_data) {
			jQuery(_object).html(do_label);
			try {
				var data;
				if (typeof return_data == 'object') data = return_data;
				else data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					jQuery(_object).html(data.record_action);
					jQuery(_object).attr("data-status", data.record_status);
					jQuery(_object).attr("data-doing", data.record_action_doing);
					if (data.record_status == "active") jQuery(_object).closest("tr").find(".vdp-table-list-badge-status").html("");
					else jQuery(_object).closest("tr").find(".vdp-table-list-badge-status").html("<span class='vdp-badge vdp-badge-danger'>"+data.record_badge+"</span>");
					vdp_global_message_show("success", data.message);
				} else if (data.status == "ERROR") {
					vdp_global_message_show("danger", data.message);
				} else {
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
				}
			} catch(error) {
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
			jQuery(_object).closest("tr").find(".row-actions").removeClass("visible");
			vdp_sending = false;
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(_object).html(do_label);
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			vdp_sending = false;
		}
	});
	return false;
}

function vdp_add_blackitem(_button) {
	if (jQuery("input[name='blackitem']").val() == "") return false;
	if (vdp_sending) return false;
	vdp_sending = true;
	var button_object = _button;
	jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-spinner vdp-fa-spin");
	jQuery(button_object).addClass("vdp-button-disabled");
	var post_data = {"action" : "vdp-add-blackitem", "blackitem" : jQuery("input[name='blackitem']").val()};
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: post_data,
		success	: function(return_data) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			var data;
			try {
				var data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					vdp_global_message_show('success', data.message);
					location.href = data.redirect;
				} else if (data.status == "ERROR") {
					vdp_global_message_show('danger', data.message);
				} else {
					vdp_global_message_show('danger', data.message);
				}
			} catch(error) {
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
			vdp_sending = false;
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			jQuery(button_object).find("i").attr("class", "vdp-fa vdp-fa-ok");
			jQuery(button_object).removeClass("vdp-button-disabled");
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			vdp_sending = false;
		}
	});
	return false;
}

function vdp_delete_blackitem(_object) {
	if (vdp_sending) return false;
	vdp_modal_open({
		message:		'Please confirm that you want to delete the item.',
		ok_label:		'Delete',
		ok_function:	function(e) {
			vdp_modal_close();
			vdp_sending = true;
			
			var record_id = jQuery(_object).attr("data-id");
			var doing_label = jQuery(_object).attr("data-doing");
			var do_label = jQuery(_object).html();
			jQuery(_object).html("<i class='vdp-fa vdp-fa-spinner vdp-fa-spin'></i> "+doing_label);
			
			jQuery.ajax({
				type	: "POST",
				url		: vdp_ajax_handler, 
				data	: {"action" : "vdp-delete-blackitem", "id" : record_id},
				success	: function(return_data) {
					try {
						var data;
						if (typeof return_data == 'object') data = return_data;
						else data = jQuery.parseJSON(return_data);
						if (data.status == "OK") {
							jQuery(_object).closest("tr").fadeOut(300, function(){
								jQuery(_object).closest("tr").remove();
							});
							vdp_global_message_show("success", data.message);
						} else if (data.status == "ERROR") {
							vdp_global_message_show("danger", data.message);
						} else {
							vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
						}
					} catch(error) {
						vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					}
					jQuery(_object).html(do_label);
					vdp_sending = false;
				},
				error	: function(XMLHttpRequest, textStatus, errorThrown) {
					jQuery(_object).html(do_label);
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
					vdp_sending = false;
				}
			});
		}
	});
	return false;
}

var vdp_global_message_timer;
function vdp_global_message_show(_type, _message) {
	clearTimeout(vdp_global_message_timer);
	jQuery("#vdp-global-message").fadeOut(300, function() {
		jQuery("#vdp-global-message").attr("class", "");
		jQuery("#vdp-global-message").addClass("vdp-global-message-"+_type).html(_message);
		jQuery("#vdp-global-message").fadeIn(300);
		vdp_global_message_timer = setTimeout(function(){jQuery("#vdp-global-message").fadeOut(300);}, 5000);
	});
}

/* Modal Popup - begin */
var vdp_modal_buttons_disable = false;
function vdp_modal_open(_settings) {
	var settings = {
		width: 				480,
		height:				180,
		ok_label:			'Yes',
		cancel_label:		'Cancel',
		message:			'Do you really want to continue?',
		ok_function:		function() {vdp_modal_close();},
		cancel_function:	function() {vdp_modal_close();}
	}
	var objects = [settings, _settings],
    settings = objects.reduce(function (r, o) {
		Object.keys(o).forEach(function (k) {
			r[k] = o[k];
		});
		return r;
    }, {});
	
	vdp_modal_buttons_disable = false;
	jQuery(".vdp-modal-message").html(settings.message);
	jQuery(".vdp-modal").width(settings.width);
	jQuery(".vdp-modal").height(settings.height);
	jQuery(".vdp-modal-content").width(settings.width);
	jQuery(".vdp-modal-content").height(settings.height);
	jQuery(".vdp-modal-button").unbind("click");
	jQuery(".vdp-modal-button").removeClass("vdp-modal-button-disabled");
	jQuery("#vdp-modal-button-ok").find("label").html(settings.ok_label);
	jQuery("#vdp-modal-button-cancel").find("label").html(settings.cancel_label);
	jQuery("#vdp-modal-button-ok").bind("click", function(e){
		e.preventDefault();
		if (!vdp_modal_buttons_disable && typeof settings.ok_function == "function") {
			settings.ok_function();
		}
	});
	jQuery("#vdp-modal-button-cancel").bind("click", function(e){
		e.preventDefault();
		if (!vdp_modal_buttons_disable && typeof settings.cancel_function == "function") {
			settings.cancel_function();
		}
	});
	jQuery(".vdp-modal-overlay").fadeIn(300);
	jQuery(".vdp-modal").css({
		'top': 					'50%',
		'transform': 			'translate(-50%, -50%) scale(1)',
		'-webkit-transform': 	'translate(-50%, -50%) scale(1)'
	});
}
function vdp_modal_close() {
	jQuery(".vdp-modal-overlay").fadeOut(300);
	jQuery(".vdp-modal").css({
		'transform': 			'translate(-50%, -50%) scale(0)',
		'-webkit-transform': 	'translate(-50%, -50%) scale(0)'
	});
	setTimeout(function(){jQuery(".vdp-modal").css("top", "-3000px")}, 300);
}
/* Modal Popup - end */
function vdp_confirm_redirect(_object, _action) {
	var message, button_label;
	if (_action == "delete") {
		message = 'Please confirm that you want to delete the item.';
		button_label = 'Delete';
	} else if (_action == "delete-all") {
		message = 'Please confirm that you want to delete all items.';
		button_label = 'Delete';
	} else {
		message = 'Please confirm that you want to perform this action.';
		button_label = 'Continue';
	}
	vdp_modal_open({
		message:		message,
		ok_label:		button_label,
		ok_function:	function(e) {
			vdp_modal_close();
			location.href = jQuery(_object).attr("href");
		}
	});
	return false;
}

function vdp_admin_popup_resize() {
	if (vdp_record_active) {
		var popup_height = 2*parseInt((jQuery(window).height() - 100)/2, 10);
		var popup_width = Math.min(Math.max(2*parseInt((jQuery(window).width() - 300)/2, 10), 640), 1080);
		jQuery("#vdp-admin-popup").height(popup_height);
		jQuery("#vdp-admin-popup").width(popup_width);
		jQuery("#vdp-admin-popup .vdp-admin-popup-inner").height(popup_height);
		jQuery("#vdp-admin-popup .vdp-admin-popup-content").height(popup_height - 52);
	}
}
function vdp_admin_popup_ready() {
	vdp_admin_popup_resize();
	jQuery(window).resize(function() {
		vdp_admin_popup_resize();
	});
}

var vdp_record_active = null;
function vdp_admin_popup_open(_object) {
	var action = jQuery(_object).attr("data-action");
	if (typeof action == typeof undefined) return false;
	jQuery("#vdp-admin-popup .vdp-admin-popup-content-form").html("");
	var window_height = 2*parseInt((jQuery(window).height() - 100)/2, 10);
	var window_width = Math.min(Math.max(2*parseInt((jQuery(window).width() - 300)/2, 10), 640), 1080);
	jQuery("#vdp-admin-popup").height(window_height);
	jQuery("#vdp-admin-popup").width(window_width);
	jQuery("#vdp-admin-popup .vdp-admin-popup-inner").height(window_height);
	jQuery("#vdp-admin-popup .vdp-admin-popup-content").height(window_height - 52);
	jQuery("#vdp-admin-popup-overlay").fadeIn(300);
	jQuery("#vdp-admin-popup").fadeIn(300);
	var title = jQuery(_object).attr("data-title");
	if (typeof title != typeof undefined) jQuery("#vdp-admin-popup .vdp-admin-popup-title h3 label").html(title);
	var subtitle = jQuery(_object).attr("data-subtitle");
	if (typeof subtitle != typeof undefined) jQuery("#vdp-admin-popup .vdp-admin-popup-title h3 span").html(subtitle);
	jQuery("#vdp-admin-popup .vdp-admin-popup-loading").show();
	vdp_record_active = jQuery(_object).attr("data-id");
	var post_data = {"action" : action, "id" : vdp_record_active};
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: post_data,
		success	: function(return_data) {
			try {
				var data;
				if (typeof return_data == 'object') data = return_data;
				else data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					jQuery("#vdp-admin-popup .vdp-admin-popup-content-form").html(data.html);
					jQuery("#vdp-admin-popup .vdp-admin-popup-loading").hide();
				} else if (data.status == "ERROR") {
					vdp_admin_popup_close();
					vdp_global_message_show("danger", data.message);
				} else {
					vdp_admin_popup_close();
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
				}
			} catch(error) {
				vdp_admin_popup_close();
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			vdp_admin_popup_close();
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
		}
	});

	return false;
}
function vdp_admin_popup_close() {
	jQuery("#vdp-admin-popup-overlay").fadeOut(300);
	jQuery("#vdp-admin-popup").fadeOut(300, function() {
		jQuery("#vdp-admin-popup .vdp-admin-popup-content-form").html("")
	});
	vdp_record_active = null;
	return false;
}
var vdp_more_active = null;
function vdp_more_using_open(_object) {
	jQuery("#vdp-more-using .vdp-admin-popup-content-form").html("");
	var window_height = 2*parseInt((jQuery(window).height() - 100)/2, 10);
	var window_width = Math.min(Math.max(2*parseInt((jQuery(window).width() - 300)/2, 10), 640), 840);
	jQuery("#vdp-more-using").height(window_height);
	jQuery("#vdp-more-using").width(window_width);
	jQuery("#vdp-more-using .vdp-admin-popup-inner").height(window_height);
	jQuery("#vdp-more-using .vdp-admin-popup-content").height(window_height - 52);
	jQuery("#vdp-more-using-overlay").fadeIn(300);
	jQuery("#vdp-more-using").fadeIn(300);
	jQuery("#vdp-more-using .vdp-admin-popup-title h3 span").html("");
	jQuery("#vdp-more-using .vdp-admin-popup-loading").show();
	vdp_more_active = jQuery(_object).attr("data-id");
	var post_data = {"action" : "vdp-using", "id" : vdp_more_active};
	jQuery.ajax({
		type	: "POST",
		url		: vdp_ajax_handler, 
		data	: post_data,
		success	: function(return_data) {
			try {
				var data;
				if (typeof return_data == 'object') data = return_data;
				else data = jQuery.parseJSON(return_data);
				if (data.status == "OK") {
					jQuery("#vdp-more-using .vdp-admin-popup-content-form").html(data.html);
					jQuery("#vdp-more-using .vdp-admin-popup-title h3 span").html(data.title);
					jQuery("#vdp-more-using .vdp-admin-popup-loading").hide();
				} else if (data.status == "ERROR") {
					vdp_more_using_close();
					vdp_global_message_show("danger", data.message);
				} else {
					vdp_more_using_close();
					vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
				}
			} catch(error) {
				vdp_more_using_close();
				vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
			}
		},
		error	: function(XMLHttpRequest, textStatus, errorThrown) {
			vdp_more_using_close();
			vdp_global_message_show("danger", vdp_esc_html__("Something went wrong. We got unexpected server response."));
		}
	});

	return false;
}

function vdp_more_using_close() {
	jQuery("#vdp-more-using-overlay").fadeOut(300);
	jQuery("#vdp-more-using").fadeOut(300);
	vdp_more_active = null;
	setTimeout(function(){jQuery("#vdp-more-using .vdp-admin-popup-content-form").html("");}, 1000);
	return false;
}

function vdp_esc_html__(_string) {
	var string;
	if (typeof vdp_translations == typeof {} && vdp_translations.hasOwnProperty(_string)) {
		string = vdp_translations[_string];
		if (string.length == 0) string = _string;
	} else string = _string;
	return vdp_escape_html(string);
}
function vdp_escape_html(_text) {
	if (!_text) return "";
	var map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};
	return _text.replace(/[&<>"']/g, function(m) { return map[m]; });
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
