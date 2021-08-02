<?php
if (!defined('UAP_CORE') && !defined('ABSPATH')) exit;
class vdp_widget extends WP_Widget {
	function __construct() {
		parent::__construct(false, esc_html__('Verified Downloads', 'vdp'));
	}

	function widget($args, $instance) {
		global $vdp, $wpdb;
		$content = '';
		$file_id = $vdp->wpml_parse_file_id($instance['file-id']);
		$html = $vdp->shortcode_handler(array('id' => $file_id));
		if (!empty($html)) {
			$content = $args['before_widget'].'<div style="clear:both; margin:'.$instance['margin-top'].'px '.$instance['margin-right'].'px '.$instance['margin-bottom'].'px '.$instance['margin-left'].'px;">'.$html.'</div>'.$args['after_widget'];
		}
		echo $content;
	}

	function update($new_instance, $old_instance) {
		global $vdp, $wpdb;
		$instance = $old_instance;
		$instance['file-id'] = $vdp->wpml_compile_file_id(strip_tags($new_instance['file-id']), $instance['file-id']);
		$instance['margin-top'] = intval($new_instance['margin-top']);
		$instance['margin-bottom'] = intval($new_instance['margin-bottom']);
		$instance['margin-left'] = intval($new_instance['margin-left']);
		$instance['margin-right'] = intval($new_instance['margin-right']);
		return $instance;
	}

	function form($instance) {
		global $vdp, $wpdb;
		$instance = wp_parse_args((array)$instance, array('file-id' => '', 'margin-top' => 0, 'margin-bottom' => 0, 'margin-left' => 0, 'margin-right' => 0));
		$file_selected = $vdp->wpml_parse_file_id(strip_tags($instance['file-id']));
		$margin_top = intval($instance['margin-top']);
		$margin_bottom = intval($instance['margin-bottom']);
		$margin_right = intval($instance['margin-right']);
		$margin_left = intval($instance['margin-left']);

		$files = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE deleted = '0' ORDER BY active DESC, id DESC", ARRAY_A);
		
		echo '
		<p>
			<label class="vdp-widget-label" for="'.$this->get_field_id('file-id').'">'.esc_html__('File', 'vdp').':</label>';
		if (sizeof($files) > 0) {
			$status = -1;
			echo '
			<select class="widefat" id="'.$this->get_field_id('file-id').'" name="'.$this->get_field_name('file-id').'">';
			foreach($files as $file) {
				if ($file['active'] != $status) {
					if ($file['active'] == 1) echo '<option disabled="disabled">--------- '.esc_html__('Active files', 'vdp').' ---------</option>';
					else echo '<option disabled="disabled">--------- '.esc_html__('Inactive files', 'vdp').' ---------</option>';
					$status = $file['active'];
				}
				if ($file_selected == $file['id']) {
					echo '
				<option value="'.$file['id'].'" selected="selected"'.($file['active'] == 1 ? '' : ' disabled="disabled"').'>'.(empty($file['title']) ? esc_html($file['filename_string']) : esc_html($file['title'])).'</option>';
				} else {
					echo '
				<option value="'.$file['id'].'"'.($file['active'] == 1 ? '' : ' disabled="disabled"').'>'.(empty($file['title']) ? esc_html($file['filename_string']) : esc_html($file['title'])).'</option>';
				}
			}
			echo '
			</select>';
		} else {
			echo esc_html__('Create at least one file.', 'vdp');
		}
		echo '
		</p>
		<p>
			<label class="vdp-widget-label" for="'.$this->get_field_id("margin-top").'">'.esc_html__('Top margin', 'vdp').':</label>
			<input class="vdp-widget-tiny-text" id="'.$this->get_field_id('margin-top').'" name="'.$this->get_field_name('margin-top').'" type="number" step="1" min="-20" value="'.$margin_top.'" size="3"> '.esc_html__('px', 'vdp').'
			<label class="vdp-widget-label" for="'.$this->get_field_id("margin-bottom").'">'.esc_html__('Bottom margin', 'vdp').':</label>
			<input class="vdp-widget-tiny-text" id="'.$this->get_field_id('margin-bottom').'" name="'.$this->get_field_name('margin-bottom').'" type="number" step="1" min="-20" value="'.$margin_bottom.'" size="3"> '.esc_html__('px', 'vdp').'
			<label class="vdp-widget-label" for="'.$this->get_field_id("margin-left").'">'.esc_html__('Left margin', 'vdp').':</label>
			<input class="vdp-widget-tiny-text" id="'.$this->get_field_id('margin-left').'" name="'.$this->get_field_name('margin-left').'" type="number" step="1" min="-20" value="'.$margin_left.'" size="3"> '.esc_html__('px', 'vdp').'
			<label class="vdp-widget-label" for="'.$this->get_field_id("margin-right").'">'.esc_html__('Right margin', 'vdp').':</label>
			<input class="vdp-widget-tiny-text" id="'.$this->get_field_id('margin-right').'" name="'.$this->get_field_name('margin-right').'" type="number" step="1" min="-20" value="'.$margin_right.'" size="3"> '.esc_html__('px', 'vdp').'
		</p>';
	}
}
?>