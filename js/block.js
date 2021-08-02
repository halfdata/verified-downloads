"use strict";
var vdp_block_files = null;
function vdp_block_utf8decode(input) {
	var string = "";
	var i = 0;
	var c = 0, c1 = 0, c2 = 0;
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
function vdp_block_decode64(input) {
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
	output = vdp_block_utf8decode(output);
	return output;
}
jQuery(document).ready(function(){
	vdp_block_files = JSON.parse(vdp_block_decode64(vdp_files_encoded));
	wp.blocks.registerBlockType( 'vdp/file', {
		title: 'Verified Downloads',
		description: 'Insert file button driven by Green Downloads plugin.',
		icon: 'custom-icon',
		category: 'widgets',
		keywords: ['file', 'download' ],
		supports: {
			customClassName: false,
			className: false,
			html: false
		},
		attributes: {
			id: {
				type: 'string',
				default: ''
			},
			title: {
				type: 'string',
				default: ''
			}
		},
		edit: function(prop) {
			var icon = wp.element.createElement("div", {className: "vdp-block-file-label-icon"}, "");
			var options = new Array(wp.element.createElement("option", {value: ""}, "Select the file..."));
			if (typeof vdp_block_files === typeof new Array()) {
				for (var i=0; i<vdp_block_files.length; i++) {
					options.push(wp.element.createElement("option", {value: vdp_block_files[i]["id"]}, vdp_block_files[i]["title"]));
				}
			}
			return wp.element.createElement("div", {className: "vdp-block-file"}, 
				icon, 
				wp.element.createElement("label", {className: "vdp-block-file-label"}, "Verified Downloads"),
				wp.element.createElement("select", {
					className: "vdp-block-file-value", 
					value: prop.attributes.id,
					onChange: function(event) {
						const selected = event.target.querySelector('option:checked');
						prop.setAttributes({id : event.target.value, title : (selected.innerHTML).replace(/["'\]\[]/g, "")});
					}},
					options
				)
			);
		},

		save: function(prop) {
			return "[vdp id='"+prop.attributes.id+"']";
		},
	});
});