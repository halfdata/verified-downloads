<?php
/*
Plugin Name: Verified Downloads
Plugin URI: https://codecanyon.net/item/verified-downloads/3890294?ref=halfdata
Description: The plugin easily allows you to distribute digital content among your Envato customers.
Version: 2.03
Author: Halfdata, Inc.
Author URI: https://codecanyon.net/user/halfdata?ref=halfdata
*/
define('VDP_RECORDS_PER_PAGE', '50');
define('VDP_VERSION', 2.03);
define('VDP_WEBFONTS_VERSION', 3);
define('VDP_UPLOADS_DIR', 'verified-downloads');
define('VDP_LOG_STATUS_ENABLED', '1');
define('VDP_LOG_STATUS_DISABLED', '2');

register_activation_hook(__FILE__, array("vdp_class", "install"));
register_deactivation_hook(__FILE__, array("vdp_class", "uninstall"));

class vdp_class {
	var $demo_mode = false;
	var $options = array();
	var $options_checkboxes = array();
	var $default_file_options = array();
	var $gmt_offset = 0;
	var $error_message = "";
	var $success_message = "";
	function __construct() {
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('vdp', false, dirname(plugin_basename(__FILE__)).'/languages/');
		}
		$this->gmt_offset = get_option('gmt_offset', 0);
		$this->options = array(
			"version" => VDP_VERSION,
			"envato-api-token" => '',
			"xsendfile" => "off",
			"link-lifetime" => "2",
			"gdpr-enable" => "on",
			"gdpr-title" => esc_html__('I agree with the {Terms and Conditions}', 'vdp'),
			"gdpr-error-label" => esc_html__('You must agree with the Terms and Conditions.', 'vdp'),
			"terms" => "",
			"cross-domain-enable" => "off"
		);
		foreach($this->options as $key => $value) {
			if ($value == 'on' || $value == 'off') $this->options_checkboxes[] = $key;
		}

		$this->get_options();
		
		add_action('init', array(&$this, 'handle_demo_mode'), 0);
		if (function_exists('register_block_type')) {
			add_action('init', array(&$this, 'register_block'));
		}
		add_action('widgets_init', array(&$this, 'widgets_init'));
		if (is_admin()) {
			add_action('wpmu_new_blog', array(&$this, 'install_new_blog'), 10, 6);
			add_action('delete_blog', array(&$this, 'uninstall_blog'), 10, 2);
			add_action('wp_ajax_vdp-update-settings', array(&$this, "save_settings"));
			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('admin_head', array(&$this, 'admin_header'), 15);
			add_action('wp_ajax_vdp-delete-file', array(&$this, "admin_delete_file"));
			add_action('wp_ajax_vdp-save-file', array(&$this, "admin_save_file"));
			add_action('wp_ajax_vdp-file-upload', array(&$this, "admin_upload"));
			add_action('wp_ajax_vdp-delete-log', array(&$this, "admin_delete_log"));
			add_action('wp_ajax_vdp-toggle-log', array(&$this, "admin_toggle_log"));
			add_action('wp_ajax_vdp-file-status-toggle', array(&$this, "admin_file_status_toggle"));
			add_action('wp_ajax_vdp-using', array(&$this, "admin_file_using"));
			add_action('wp_ajax_vdp-add-blackitem', array(&$this, "admin_add_blackitem"));
			add_action('wp_ajax_vdp-delete-blackitem', array(&$this, "admin_delete_blackitem"));

			add_action('wp_ajax_vdp-init', array(&$this, "remote_init"));
			add_action('wp_ajax_nopriv_vdp-init', array(&$this, "remote_init"));
			add_action('wp_ajax_vdp-continue', array(&$this, "front_continue"));
			add_action('wp_ajax_nopriv_vdp-continue', array(&$this, "front_continue"));
		} else {
			add_action("wp_head", array(&$this, "front_header"));
			add_action("wp_footer", array(&$this, "front_footer"));
			add_shortcode('vdp', array(&$this, "shortcode_handler"));
		}
		add_action("init", array(&$this, "init"), 0);
	}

	function admin_enqueue_scripts() {
		if (array_key_exists('page', $_GET)) {
			if (in_array($_GET['page'], array('vdp-settings', 'vdp', 'vdp-add', 'vdp-log', 'vdp-blacklist', 'vdp-using'))) {
				wp_enqueue_script("jquery");
				wp_enqueue_script('vdp', plugins_url('/js/admin.js', __FILE__), array(), VDP_VERSION);
				wp_enqueue_media();
			}
		}
		wp_enqueue_style('vdp', plugins_url('/css/admin.css', __FILE__), array(), VDP_VERSION);
	}

	function front_enqueue_scripts() {
		wp_enqueue_script("jquery");
		wp_enqueue_script('vdp', plugins_url('/js/vdp.js', __FILE__), array('jquery'), VDP_VERSION);
		wp_enqueue_style('vdp', plugins_url('/css/style.css', __FILE__), array(), VDP_VERSION);
	}

	static function install($_networkwide = null) {
		global $wpdb;
		if (function_exists('is_multisite') && is_multisite()) {
			if ($_networkwide) {
				$old_blog = $wpdb->blogid;
				$blog_ids = $wpdb->get_col('SELECT blog_id FROM '.esc_sql($wpdb->blogs));
				foreach ($blog_ids as $blog_id) {
					switch_to_blog($blog_id);
					self::activate();
				}
				switch_to_blog($old_blog);
				return;
			}
		}
		self::activate();
	}

	function install_new_blog($_blog_id, $_user_id, $_domain, $_path, $_site_id, $_meta) {
		if (is_plugin_active_for_network(basename(dirname(__FILE__)).'/' ).basename(__FILE__)) {
			switch_to_blog($_blog_id);
			self::activate();
			restore_current_blog();
		}
	}

	static function activate () {
		global $wpdb;
		$table_name = $wpdb->prefix."vdp_files";
		if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
			$sql = "CREATE TABLE ".$table_name." (
				id int(11) NOT NULL auto_increment,
				title varchar(255) collate utf8_unicode_ci NULL,
				source varchar(32) collate latin1_general_cs NULL,
				filename varchar(255) collate utf8_unicode_ci NULL,
				filename_string varchar(255) collate utf8_unicode_ci NULL,
				item_id varchar(31) collate utf8_unicode_ci NULL,
				item_title varchar(255) collate utf8_unicode_ci NULL,
				options longtext collate utf8_unicode_ci NULL,
				downloads int(11) NULL default '0',
				active int(11) NULL default '1',
				created int(11) NULL,
				deleted int(11) NULL default '0',
				UNIQUE KEY  id (id)
			);";
			$wpdb->query($sql);
		}
		$table_name = $wpdb->prefix."vdp_log";
		if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
			$sql = "CREATE TABLE ".$table_name." (
				id int(11) NOT NULL auto_increment,
				file_id int(11) NULL,
				download_key varchar(255) collate utf8_unicode_ci NULL,
				purchase_code varchar(255) collate utf8_unicode_ci NULL,
				buyer varchar(255) collate utf8_unicode_ci NULL,
				ip varchar(31) collate utf8_unicode_ci NULL,
				details text collate utf8_unicode_ci NULL,
				downloads int(11) NULL default '0',
				status int(11) NULL,
				created int(11) NULL,
				deleted int(11) NULL default '0',
				UNIQUE KEY  id (id)
			);";
			$wpdb->query($sql);
		}
		$table_name = $wpdb->prefix."vdp_blacklist";
		if($wpdb->get_var("SHOW TABLES LIKE '".esc_sql($table_name)."'") != $table_name) {
			$sql = "CREATE TABLE ".esc_sql($table_name)." (
				id int(11) NOT NULL auto_increment,
				username varchar(255) collate utf8_unicode_ci NULL,
				purchase_code varchar(255) collate utf8_unicode_ci NULL,
				created int(11) NULL,
				deleted int(11) NULL default '0',
				UNIQUE KEY  id (id)
			);";
			$wpdb->query($sql);
		}
		$upload_dir = wp_upload_dir();
		if (!file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR)) {
			wp_mkdir_p($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR);
			if (!file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.'index.html')) {
				file_put_contents($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.'index.html', 'Silence is the gold!');
			}
			if (!file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.'.htaccess')) {
				file_put_contents($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.'.htaccess', 'deny from all');
			}
		}
		update_option('vdp-version', VDP_VERSION);
	}

	static function uninstall() {
		global $wpdb;
		if (function_exists('is_multisite') && is_multisite()) {
			$old_blog = $wpdb->blogid;
			$blog_ids = $wpdb->get_col('SELECT blog_id FROM '.esc_sql($wpdb->blogs));
			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				self::deactivate(false);
			}
			switch_to_blog($old_blog);
		} else {
			self::deactivate(false);
		}
	}

	function uninstall_blog($_blog_id, $_drop) {
		if (is_plugin_active_for_network(basename(dirname(__FILE__)).'/'.basename(__FILE__)) && $_drop) {
			switch_to_blog($_blog_id);
			self::deactivate(true);
			restore_current_blog();
		}
	}
	
	static function deactivate($_force_delete = false) {
		global $wpdb;
		$clean_database = get_option('vdp-clean-database', 'off');
		if ($clean_database == 'on' || $_force_delete) {
			$wpdb->query("DELETE FROM ".$wpdb->prefix."options WHERE option_name LIKE 'vdp-%' AND option_name != 'vdp-clean-database'");
			$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."vdp_files");
			$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."vdp_log");
			$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."vdp_blacklist");
		}
	}

	function get_options() {
		$options = json_decode(get_option('vdp-options', '[]'), true);
		if (is_array($options) && array_key_exists('version', $options)) {
			$this->options = array_merge($this->options, $options);
			$this->options['version'] = get_option('vdp-version', VDP_VERSION);
		}
	}

	function update_options() {
		update_option('vdp-options', json_encode($this->options));
		update_option('vdp-version', $this->options['version']);
		do_action("vdp_update_options");
	}

	function populate_options() {
		foreach ($this->options as $key => $value) {
			if (array_key_exists('vdp-'.$key, $_REQUEST)) {
				$this->options[$key] = stripslashes($_REQUEST['vdp-'.$key]);
			} else if (in_array($key, $this->options_checkboxes)) $this->options[$key] = "off";
		}
		do_action("vdp_populate_options");
	}

	function check_options($_check_api) {
		$errors = array();
		if (intval($this->options['link-lifetime']) != $this->options['link-lifetime'] || intval($this->options['link-lifetime']) < 1 || intval($this->options['link-lifetime']) > 7200) $errors[] = esc_html__('Download link lifetime must be a valid integer value in range [1...7200].', 'vdp');
		if (!empty($this->options['envato-api-token'])) {
			if ($_check_api) {
				$result = $this->connect($this->options['envato-api-token'], 'v1/market/private/user/username.json');
				if (!empty($result) && is_array($result)) {
					if (array_key_exists('username', $result)) {
						
					} else if (array_key_exists('error', $result)) {
						$errors[] = $result['error'];
					} else {
						$errors[] = esc_html__('Envato API server response is invalid.', 'vdp');
					}
				} else $errors[] = esc_html__('Envato API server response is invalid.', 'vdp');
			}
		} else {
			$errors[] = esc_html__('Envato API Token can not be empty.', 'vdp');
		}
		return $errors;
	}

	function admin_menu() {
		if ($this->demo_mode) {
			$cap = "read";
		} else $cap = "manage_options";
		add_menu_page(
			"Verified Downloads"
			, "Verified Downloads"
			, $cap
			, "vdp"
			, array(&$this, 'admin_files')
			, 'none'
			, 56
		);
		add_submenu_page(
			"vdp"
			, esc_html__('Files', 'vdp')
			, esc_html__('Files', 'vdp')
			, $cap
			, "vdp"
			, array(&$this, 'admin_files')
		);
		add_submenu_page(
			"vdp"
			, esc_html__('Add File', 'vdp')
			, esc_html__('Add File', 'vdp')
			, $cap
			, "vdp-add"
			, array(&$this, 'admin_add_file')
		);
		add_submenu_page(
			"vdp"
			, esc_html__('Log', 'vdp')
			, esc_html__('Log', 'vdp')
			, $cap
			, "vdp-log"
			, array(&$this, 'admin_log')
		);
		add_submenu_page(
			"vdp"
			, esc_html__('Blacklist', 'vdp')
			, esc_html__('Blacklist', 'vdp')
			, $cap
			, "vdp-blacklist"
			, array(&$this, 'admin_blacklist')
		);
		add_submenu_page(
			"vdp"
			, esc_html__('Settings', 'vdp')
			, esc_html__('Settings', 'vdp')
			, $cap
			, "vdp-settings"
			, array(&$this, 'admin_settings')
		);
		if (defined('UAP_CORE')) {
			add_submenu_page(
				"vdp"
				, esc_html__('How To Use', 'vdp')
				, esc_html__('How To Use', 'vdp')
				, $cap
				, "vdp-using"
				, array(&$this, 'admin_using')
			);
		}
	}

	function register_block() {
		wp_register_script('vdp-file', plugins_url('js/block.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-i18n'));
		register_block_type('vdp/file', array('editor_script' => 'vdp-file'));
	}

	function admin_settings() {
		global $wpdb;
		$settings_tabs = apply_filters("vdp_settings_tabs_pre", array());
		echo '
		<div class="wrap vdp-admin vdp">
			<h2>'.esc_html__('Verified Downloads - Settings', 'vdp').'</h2>
			<form class="vdp-form" enctype="multipart/form-data" method="post" style="margin: 0px" action="'.admin_url('admin.php').'">
			<div class="vdp-properties-container">
				<h3>'.esc_html__('General Mailing Parameters', 'vdp').'</h3>
				<div class="vdp-properties-box">
					<table class="vdp-useroptions">
						<tr>
							<th>'.esc_html__('Envato API Token', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<input type="text" id="vdp-envato-api-token" name="vdp-envato-api-token" value="'.esc_html($this->options['envato-api-token']).'" placeholder="...">
										<label>'.sprintf(esc_html__('Please enter your Envato API Token. Important! Token must have the following permissions: "View and search Envato sites", "View your Envato Account username", "View your items sales history" and "Verify purchases of your items". You can generate API Token here: %s', 'vdp'), '<a target="_blank" href="https://build.envato.com/create-token">https://build.envato.com/create-token</a>').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th>'.esc_html__('Download link lifetime', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-dime">
										<input type="text" id="vdp-link-lifetime" name="vdp-link-lifetime" value="'.esc_html($this->options['link-lifetime']).'" style="width: 80px; text-align: right;"> <span style="line-height: 36px;">'.esc_html__('hours', 'vdp').'</span>
										<label>'.esc_html__('Please enter period of download link validity.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th>'.esc_html__('GDPR-compatibility', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<div class="vdp-checkbox-toggle-container">
											<input class="vdp-checkbox-toggle" type="checkbox" value="on" id="vdp-gdpr-enable" name="vdp-gdpr-enable" '.($this->options['gdpr-enable'] == "on" ? 'checked="checked"' : '').' onchange="vdp_switch_gdpr();" /><label for="vdp-gdpr-enable"></label><span>'.esc_html__('Enable checkbox to agree with the Terms & Conditions', 'vdp').'</span>
										</div>
										<label>'.esc_html__('Please tick checkbox if you want to add checkbox to the form.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr class="vdp-gdpr-depend"'.($this->options['gdpr-enable'] == "on" ? ' style="display:table-row;"' : '').'>
							<th>'.esc_html__('Checkbox label', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<input type="text" id="vdp-gdpr-title" name="vdp-gdpr-title" value="'.esc_html($this->options['gdpr-title']).'" placeholder="...">
										<label>'.esc_html__('Enter the label for GDPR checkbox. Wrap your keyword with "{" and "}" to link it with Terms & Conditions box. HTML allowed.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr class="vdp-gdpr-depend"'.($this->options['gdpr-enable'] == "on" ? ' style="display:table-row;"' : '').'>
							<th>'.esc_html__('Terms & Conditions', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<textarea id="vdp-terms" name="vdp-terms" placeholder="...">'.esc_html($this->options['terms']).'</textarea>
										<label>'.esc_html__('Your customers must be agree with Terms & Conditions before paying. Leave this field blank if you do not need Terms & Conditions box to be shown.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr class="vdp-gdpr-depend"'.($this->options['gdpr-enable'] == "on" ? ' style="display:table-row;"' : '').'>
							<th>'.esc_html__('Error label', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<input type="text" id="vdp-gdpr-error-label" name="vdp-gdpr-error-label" value="'.esc_html($this->options['gdpr-error-label']).'" placeholder="...">
										<label>'.esc_html__('Enter the error label for GDPR checkbox. It appears when user forgot to tick GDPR checkbox.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th>'.esc_html__('Enable X-Sendfile', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<div class="vdp-checkbox-toggle-container">
											<input class="vdp-checkbox-toggle" type="checkbox" value="on" id="vdp-xsendfile" name="vdp-xsendfile" '.($this->options['xsendfile'] == "on" ? 'checked="checked"' : '').' /><label for="vdp-xsendfile"></label><span>'.esc_html__('Download files through X-Sendfile module', 'vdp').'</span>
										</div>
										<label>'.sprintf(esc_html__('Use this option to enable X-SendFile mode to download huge files. Please contact your hosting provider to make sure that %smod_xsendfile%s module installed on your server. Do not activate this option if %smod_xsendfile%s module is not installed.', 'vdp'), '<a href="https://tn123.org/mod_xsendfile/" target="_blank"><strong>', '</strong></a>', '<strong>', '</strong>').'</label>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th>'.esc_html__('Cross-domain calls', 'vdp').':</th>
							<td>
								<div class="vdp-properties-content">
									<div class="vdp-properties-content-full">
										<div class="vdp-checkbox-toggle-container">
											<input class="vdp-checkbox-toggle" type="checkbox" value="on" id="vdp-cross-domain-enable" name="vdp-cross-domain-enable" '.($this->options['cross-domain-enable'] == "on" ? 'checked="checked"' : '').' /><label for="vdp-cross-domain-enable"></label><span>'.esc_html__('Enable cross-domain calls', 'vdp').'</span>
										</div>
										<label>'.esc_html__('Enable this option if you want to use cross-domain embedding, i.e. plugin installed on domain1, and button is used on domain2.', 'vdp').'</label>
									</div>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<hr>
			<div class="vdp-button-container">
				<input type="hidden" name="action" value="vdp-update-settings" />
				<input type="hidden" name="vdp_version" value="'.VDP_VERSION.'" />
				<a class="vdp-button" onclick="return vdp_save_settings(this);"><i class="vdp-fa vdp-fa-ok"></i><label>'.esc_html__('Save Settings', 'vdp').'</label></a>
			</div>
			<div class="vdp-message"></div>
			</form>
			<div id="vdp-global-message"></div>
		</div>';
	}

	function save_settings() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			$this->populate_options();
			$errors = $this->check_options(true);
			if (!empty($errors)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = implode('<br />', $errors);
				echo json_encode($return_object);
				exit;
			}
			$this->update_options();
			
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['message'] = esc_html__('Settings successfully saved.', 'vdp');
			echo json_encode($return_object);
			exit;
		}
	}

	function admin_files() {
		global $wpdb;

		if (array_key_exists("s", $_GET)) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";

		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."vdp_files WHERE deleted = '0'".((strlen($search_query) > 0) ? " AND (filename_string LIKE '%".esc_sql($search_query)."%' OR title LIKE '%".esc_sql($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/VDP_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (array_key_exists("p", $_GET)) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(admin_url("admin.php")."?page=vdp".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."vdp_files WHERE deleted = '0'".((strlen($search_query) > 0) ? " AND (filename_string LIKE '%".esc_sql($search_query)."%' OR title LIKE '%".esc_sql($search_query)."%')" : "")." ORDER BY created DESC LIMIT ".(($page-1)*VDP_RECORDS_PER_PAGE).", ".VDP_RECORDS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);

		echo '
			<div class="wrap vdp-admin vdp">
				<h2>'.esc_html__('Verified Downloads - Files', 'vdp').'</h2>
				<form action="'.admin_url('admin.php').'" method="get" class="uap-filter-form vdp-filter-form">
				<input type="hidden" name="page" value="vdp" />
				<label>'.esc_html__('Search:', 'vdp').'</label>
				<input type="text" name="s" value="'.esc_html($search_query).'" style="width: 200px;" class="form-control">
				<input type="submit" class="button-secondary action" value="'.esc_html__('Search', 'vdp').'" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="'.esc_html__('Reset search results', 'vdp').'" onclick="window.location.href=\''.admin_url('admin.php').'?page=vdp\';" />' : '').'
				</form>
				<div class="vdp-buttons"><a class="vdp-button vdp-button-small" href="'.admin_url('admin.php').'?page=vdp-add"><i class="vdp-fa vdp-fa-plus"></i><label>'.esc_html__('Create New File', 'vdp').'</label></a></div>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
				<table class="vdp-table-list widefat">
					<tr>
						<th>'.esc_html__('File', 'vdp').'</th>
						<th>'.esc_html__('Available for', 'vdp').'</th>
						<th style="width: 240px;">'.esc_html__('Shortcode', 'vdp').'</th>
						<th style="width: 60px;">'.esc_html__('Downloads', 'vdp').'</th>
						<th style="width: 35px;"></th>
					</tr>';
		if (sizeof($rows) > 0) {
			foreach ($rows as $row) {
				$title = trim($row['title']);
				if (empty($title)) $title = basename($row['filename_string']);
				$source_string = '';
				switch ($row['source']) {
					case 'file':
						$source_string = esc_html__('File', 'opd').': ';
						break;
					case 'path':
						$source_string = esc_html__('Path', 'opd').': ';
						break;
					case 'url':
						$title = $row['filename_string'];
						$source_string = esc_html__('URL', 'opd').': ';
						break;
					case 'media-library':
						$source_string = esc_html__('Media Library', 'opd').': ';
						break;
					default:
						break;
				}
				echo '
				<tr>
					<td><a href="'.admin_url('admin.php').'?page=vdp-add&id='.esc_html($row['id']).'"><strong>'.esc_html($title).'</strong></a><span class="vdp-table-list-badge-status">'.($row['active'] < 1 ? '<span class="vdp-badge vdp-badge-danger">'.esc_html__('Inactive', 'vdp').'</span>' : '').'</span><label class="vdp-table-list-em">'.esc_html($source_string.$row['filename_string']).'</label></td>
					<td>'.(empty($row['item_id']) ? esc_html__('Any Envato Item', 'vdp') : esc_html($row['item_title']).'<label class="vdp-table-list-em">ID: '.esc_html($row['id']).'</label>').'</td>
					<td class="vdp-table-list-column-shortcode"><span class="vdp-more-using" data-id="'.esc_html($row['id']).'" title="'.esc_html__('Click the icon for more options.', 'vdp').'" onclick="vdp_more_using_open(this);"><i class="vdp-fa vdp-fa-code"></i></span><div><input type="text" placeholder="..." onclick="this.focus();this.select();" readonly="readonly" value="'.(defined('UAP_CORE') ? esc_html('<div class="vdp" data-id="'.esc_html($row['id']).'"></div>') : esc_html('[vdp id="'.esc_html($row['id']).'"]')).'"></div></td>
					<td>'.intval($row['downloads']).'</td>
					<td>
						<div class="vdp-table-list-actions">
							<span><i class="vdp-fa vdp-fa-ellipsis-vert"></i></span>
							<div class="vdp-table-list-menu">
								<ul>
									<li><a href="'.admin_url('admin.php').'?page=vdp-add&id='.esc_html($row['id']).'">'.esc_html__('Edit', 'vdp').'</a></li>
									<li><a href="#" data-status="'.($row['active'] > 0 ? 'active' : 'inactive').'" data-id="'.esc_html($row['id']).'" data-doing="'.($row['active'] > 0 ? esc_html__('Deactivating...', 'vdp') : esc_html__('Activating...', 'vdp')).'" onclick="return vdp_file_status_toggle(this);">'.($row['active'] > 0 ? esc_html__('Deactivate', 'vdp') : esc_html__('Activate', 'vdp')).'</a></li>
									<li><a href="'.admin_url('admin.php').'?page=vdp-log&fid='.esc_html($row['id']).'">'.esc_html__('Log', 'vdp').'</a></li>
									<li><a href="'.(defined('UAP_CORE') ? admin_url('admin.php').'?vdp-id='.esc_html($row['id']) : admin_url('admin.php').'?vdp-id='.esc_html($row['id'])).'">'.esc_html__('Download', 'vdp').'</a></li>
									<li class="vdp-table-list-menu-line"></li>
									<li><a href="#" data-id="'.esc_html($row['id']).'" data-doing="'.esc_html__('Deleting...', 'vdp').'" onclick="return vdp_delete_file(this);">'.esc_html__('Delete', 'vdp').'</a></li>
								</ul>
							</div>
						</div>
					</td>
				</tr>';
			}
		} else {
			echo '
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? esc_html__('No results found for', 'vdp').' "<strong>'.esc_html($search_query).'</strong>"' : esc_html__('List is empty.', 'vdp')).'</td></tr>';
		}
		echo '
				</table>
				<div class="vdp-buttons"><a class="vdp-button vdp-button-small" href="'.admin_url('admin.php').'?page=vdp-add"><i class="vdp-fa vdp-fa-plus"></i><label>'.esc_html__('Create New File', 'vdp').'</label></a></div>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
			</div>
			<div id="vdp-global-message"></div>
		<div class="vdp-admin-popup-overlay" id="vdp-more-using-overlay"></div>
		<div class="vdp-admin-popup" id="vdp-more-using">
			<div class="vdp-admin-popup-inner">
				<div class="vdp-admin-popup-title">
					<a href="#" title="'.esc_html__('Close', 'vdp').'" onclick="return vdp_more_using_close();"><i class="vdp-fa vdp-fa-cancel"></i></a>
					<h3><i class="fas fa-code"></i> '.esc_html__('How To Use', 'vdp').'<span></span></h3>
				</div>
				<div class="vdp-admin-popup-content">
					<div class="vdp-admin-popup-content-form">
					</div>
				</div>
				<div class="vdp-admin-popup-loading"><i class="vdp-fa vdp-fa-spinner vdp-fa-spin"></i></div>
			</div>
		</div>';
		echo $this->admin_modal_html();
		if (!empty($this->error_message)) {
			echo '
<script>jQuery(document).ready(function(){vdp_global_message_show("danger", "'.esc_html($this->error_message).'");});</script>';
		} else if (!empty($this->success_message)) {
			echo '
<script>jQuery(document).ready(function(){vdp_global_message_show("success", "'.esc_html($this->success_message).'");});</script>';
		}
	}

	function admin_add_file() {
		global $wpdb;

		$api_ok = false;
		$result = array();
		if (!empty($this->options['envato-api-token'])) {
			$result = $this->connect($this->options['envato-api-token'], 'v1/market/private/user/username.json');
			if (!empty($result) && is_array($result)) {
				if (array_key_exists('username', $result)) {
					$api_ok = true;
					$result = $this->connect($this->options['envato-api-token'], 'v1/discovery/search/search/item?username=halfdata&page_size=100');
				}
			}
		}
		$file_details = null;
		if (array_key_exists('id', $_REQUEST) && !empty($_REQUEST['id'])) {
			$id = intval($_REQUEST["id"]);
			$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
		}
		if (!empty($file_details) && is_array($file_details) && array_key_exists('options', $file_details) && !empty($file_details['options'])) {
			$file_options = json_decode($file_details['options'], true);
			if (!empty($file_options) && is_array($file_options)) $file_options = array_merge($this->default_file_options, $file_options);
			else $file_options = $this->default_file_options;
		} else $file_options = $this->default_file_options;

		$file = array();
		$upload_dir = wp_upload_dir();
		if (file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR) && is_dir($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR)) {
			$dircontent = scandir($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR);
			for ($i=0; $i<sizeof($dircontent); $i++) {
				if ($dircontent[$i] != "." && $dircontent[$i] != ".." && $dircontent[$i] != "index.html" && $dircontent[$i] != ".htaccess") {
					if (is_file($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$dircontent[$i])) {
						$files[] = $dircontent[$i];
					}
				}
			}
		}
		
		echo '
		<div class="wrap vdp-admin vdp">
			<h2>'.(!empty($file_details) ? esc_html__('Verified Downloads - Edit file', 'vdp') : esc_html__('Verified Downloads - Add file', 'vdp')).'</h2>
			<form class="vdp-form" enctype="multipart/form-data" method="post" style="margin: 0px" action="'.admin_url('admin.php').'">
				<div class="vdp-properties-container">
					<h3>'.(!empty($file_details) ? esc_html__('Edit file', 'vdp') : esc_html__('Add file', 'vdp')).'</h3>
					<div class="vdp-properties-box">
						<table class="vdp-useroptions">
							<tr>
								<th>'.esc_html__('Title', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<input type="text" id="vdp-title" name="title" value="'.(!empty($file_details) ? esc_html($file_details['title']) : '').'" placeholder="...">
											<label>'.esc_html__('Enter the title of file. If you leave this field blank, then original file name will be the title.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th>'.esc_html__('Source', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<div class="vdp-tiles">
												<input type="radio" name="source" id="vdp-source-file" value="file"'.((!empty($file_details) && $file_details['source'] == 'file') || empty($file_details) || empty($file_details['source']) ? ' checked="checked"' : '').' onchange="vdp_change_source();" /><label for="vdp-source-file">'.esc_html__('File', 'vdp').'</label>
												<input type="radio" name="source" id="vdp-source-path" value="path"'.(!empty($file_details) && $file_details['source'] == 'path' ? ' checked="checked"' : '').' onchange="vdp_change_source();" /><label for="vdp-source-path">'.esc_html__('Server Path', 'vdp').'</label>';
		if (!defined('UAP_CORE')) {
			echo '
												<input type="radio" name="source" id="vdp-source-media-library" value="media-library"'.(!empty($file_details) && $file_details['source'] == 'media-library' ? ' checked="checked"' : '').' onchange="vdp_change_source();" /><label for="vdp-source-media-library">'.esc_html__('Media Library', 'vdp').'</label>';
		}
		echo '
												<input type="radio" name="source" id="vdp-source-url" value="url"'.(!empty($file_details) && $file_details['source'] == 'url' ? ' checked="checked"' : '').' onchange="vdp_change_source();" /><label for="vdp-source-url">'.esc_html__('URL', 'vdp').'</label>
											</div>
											<label>'.esc_html__('Select the source of the file.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>
							<tr class="vdp-source-data vdp-source-file"'.((!empty($file_details) && $file_details['source'] == 'file') || empty($file_details) || empty($file_details['source']) ? ' style="display: table-row;"' : '').'>
								<th>'.esc_html__('File', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<select name="source-file" id="vdp-fileselector">
												<option value="">-- '.esc_html__('Select available file', 'vdp').' --</option>';
		for ($i=0; $i<sizeof($files); $i++) {
			echo '
												<option value="'.esc_html($files[$i]).'"'.(!empty($file_details) && ($file_details['source'] == 'file' || empty($file_details['source'])) && $files[$i] == $file_details['filename'] ? ' selected="selected"' : '').'>'.esc_html($files[$i]).'</option>';
		}
		echo '
											</select>
											<label>'.sprintf(esc_html__('Select any available file from folder %s or upload new file below.', 'vdp'), '<strong>'.$upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.'</strong>').'</label>
											<a class="vdp-button" id="vdp-source-file-upload-button" data-select="vdp-fileselector" data-label="'.esc_html__('Upload File', 'vdp').'" data-loading="'.esc_html__('Uploading...', 'vdp').'" onclick="jQuery(\'.vdp-form-upload input[type=file]\').click(); return false;"><i class="vdp-fa vdp-fa-upload"></i><label>'.esc_html__('Upload File', 'vdp').'<label></a>
											<label>'.esc_html__('Choose file to upload.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>
							<tr class="vdp-source-data vdp-source-path"'.(!empty($file_details) && $file_details['source'] == 'path' ? ' style="display: table-row;"' : '').'>
								<th>'.esc_html__('Path', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<input type="text" name="source-path" value="'.(!empty($file_details) && $file_details['source'] == 'path' ? esc_html($file_details['filename']) : '').'" />
											<label>'.esc_html__('Enter the absolute path to the file on your server.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>
							<tr class="vdp-source-data vdp-source-url"'.(!empty($file_details) && $file_details['source'] == 'url' ? ' style="display: table-row;"' : '').'>
								<th>'.esc_html__('URL', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<input type="text" name="source-url" value="'.(!empty($file_details) && $file_details['source'] == 'url' ? esc_html($file_details['filename']) : '').'" />
											<label>'.esc_html__('Enter the URL of the file that you want to sell. This is NOT SECURE source. Everyone who knows this URL can download file without payment.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>';
		if (!defined('UAP_CORE')) {
			echo '
							<tr class="vdp-source-data vdp-source-media-library"'.(!empty($file_details) && $file_details['source'] == 'media-library' ? ' style="display: table-row;"' : '').'>
								<th>'.esc_html__('File', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<input type="text" name="source-media-library-name" value="'.(!empty($file_details) && $file_details['source'] == 'media-library' ? esc_html($file_details['filename_string']) : '').'" class="widefat" readonly="readonly" onclick="vdp_set_media(this);" />
											<input type="hidden" name="source-media-library" value="'.(!empty($file_details) && $file_details['source'] == 'media-library' ? esc_html($file_details['filename']) : '').'" />
											<label>'.esc_html__('Select the file from Media Library. This is NOT SECURE source. Everyone who knows direct URL can download file without payment.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>';
		}
		echo '
							<tr>
								<th>'.esc_html__('Available for', 'vdp').':</th>
								<td>
									<div class="vdp-properties-content">
										<div class="vdp-properties-content-full">
											<select name="item-id" id="vdp-item-id">
												<option value="0"'.(!empty($file_details) && $file_details['item_id'] == 0 ? ' selected="selected"' : '').'>'.esc_html__('Any Envato Item', 'vdp').'</option>';
		if (is_array($result) && array_key_exists('matches', $result)) {
			foreach($result['matches'] as $item) {
				echo '
												<option value="'.$item['id'].'"'.(!empty($file_details) && $item['id'] == $file_details['item_id'] ? ' selected="selected"' : '').'>'.esc_html($item['name']).' (ID: '.$item['id'].')</option>';
			}
		}
		echo '
											</select>
											<label>'.esc_html__('You can associate the file with any Envato item. It means that this file can be downloaded only by person who purchased associated item.', 'vdp').'</label>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>
				</div>
				<hr>
				<div class="vdp-button-container">
					<input type="hidden" name="action" value="vdp-save-file" />
					'.(!empty($file_details) ? '<input type="hidden" name="vdp-id" value="'.$file_details['id'].'" />' : '').'
					<a class="vdp-button" onclick="return vdp_save_file(this);"><i class="vdp-fa vdp-fa-ok"></i><label>'.esc_html__('Submit Details', 'vdp').'</label></a>
				</div>
			</form>
			<form class="vdp-form-upload" action="'.admin_url('admin-ajax.php').'" method="POST" enctype="multipart/form-data" target="vdp-iframe-upload" data-button="vdp-source-file-upload-button" onsubmit="return vdp_uploader_start(this);" style="display: none !important; width: 0 !important; height: 0 !important;">
				<input type="hidden" name="action" value="vdp-file-upload" />
				<input type="file" name="file" onchange="jQuery(this).parent().submit();" style="display: none !important; width: 0 !important; height: 0 !important;" />
				<input type="submit" value="Upload" style="display: none !important; width: 0 !important; height: 0 !important;" />
			</form>											
			<iframe data-loading="false" data-button="vdp-source-file-upload-button" id="vdp-iframe-upload" name="vdp-iframe-upload" src="about:blank" onload="vdp_uploader_finish(this);" style="display: none !important; width: 0 !important; height: 0 !important;"></iframe>
		</div>
		<div id="vdp-global-message"></div>
		<script>
			var vdp_currencies = '.json_encode($this->currencies).';
		</script>';
	}

	function admin_upload() {
		header('Content-Type: application/json');
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (is_uploaded_file($_FILES["file"]["tmp_name"])) {
			$upload_dir = wp_upload_dir();
			$filename = $this->get_filename($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR, $_FILES["file"]["name"]);
			if (!move_uploaded_file($_FILES["file"]["tmp_name"], $upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$filename)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = esc_html__('Unable to save uploaded file on server.', 'vdp');
				echo json_encode($return_object);
				exit;
			}
			$files = array();
			if (file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR) && is_dir($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR)) {
				$dircontent = scandir($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR);
				for ($i=0; $i<sizeof($dircontent); $i++) {
					if ($dircontent[$i] != "." && $dircontent[$i] != ".." && $dircontent[$i] != "index.html" && $dircontent[$i] != ".htaccess") {
						if (is_file($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$dircontent[$i])) {
							$files[] = $dircontent[$i];
						}
					}
				}
			}
			if (empty($files)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = esc_html__('Unable to save uploaded file on server.', 'vdp');
				echo json_encode($return_object);
				exit;
			}
			$html = '<option value="">-- '.esc_html__('Select available file', 'vdp').' --</option>';
			foreach ($files as $file) {
				$html .= '<option value="'.esc_html($file).'"'.($file == $filename ? ' selected="selected"' : '').'>'.esc_html($file).'</option>';
			}
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['html'] = $html;
			$return_object['message'] = esc_html__('File successfully uploaded.', 'vdp');
			echo json_encode($return_object);
			exit;
		}
		$return_object = array();
		$return_object['status'] = 'ERROR';
		$return_object['message'] = esc_html__('File was not uploaded properly.', 'vdp');
		echo json_encode($return_object);
		exit;
	}

	function admin_save_file() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			$id = '';
			if (array_key_exists('vdp-id', $_REQUEST)) $id = stripslashes(trim($_REQUEST['vdp-id']));
			$title = '';
			if (array_key_exists('title', $_REQUEST)) $title = stripslashes(trim($_REQUEST['title']));
			$source = 'file';
			if (array_key_exists('source', $_REQUEST)) $source = stripslashes(trim($_REQUEST['source']));
			$item_id = '0';
			$item_title = '';
			if (array_key_exists('item-id', $_REQUEST)) $item_id = stripslashes(trim($_REQUEST['item-id']));

			$errors = array();
			$filename = '';
			$filename_string = '';

			$file_options = $this->default_file_options;
			foreach ($this->default_file_options as $key => $value) {
				if (array_key_exists('options-'.$key, $_REQUEST)) $file_options[$key] = stripslashes(trim($_REQUEST['options-'.$key]));
			}
			
			switch ($source) {
				case 'url':
					if (array_key_exists('source-url', $_REQUEST)) $filename = stripslashes(trim($_REQUEST['source-url']));
					if (empty($filename) || !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $filename)) $errors[] = esc_html__('Invalid URL.', 'vdp');
					else $filename_string = $filename;
					break;
				case 'media-library':
					if (array_key_exists('source-media-library', $_REQUEST)) $filename = stripslashes(trim($_REQUEST['source-media-library']));
					if (empty($filename)) $errors[] = esc_html__('Invalid Media Library file.', 'vdp');
					else {
						$attachment_id = intval($filename);
						$attachment = get_post($attachment_id, ARRAY_A);
						if (is_array($attachment) && array_key_exists('post_type', $attachment) && $attachment['post_type'] == 'attachment') {
							$file = get_attached_file($attachment_id);							
							$filename_string = basename($file);
						} else $errors[] = esc_html__('Media Library file not found.', 'vdp');
					}
					break;
				case 'file':
					if (array_key_exists('source-file', $_REQUEST)) $filename = stripslashes(trim($_REQUEST['source-file']));
					if (empty($filename)) $errors[] = esc_html__('Invalid file.', 'vdp');
					else {
						$upload_dir = wp_upload_dir();
						if (file_exists($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$filename) && is_file($upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$filename)) {
							$filename_string = $filename;
						} else $errors[] = esc_html__('File not found.', 'vdp');
					}
					break;
				case 'path':
					if (array_key_exists('source-path', $_REQUEST)) $filename = stripslashes(trim($_REQUEST['source-path']));
					if (empty($filename)) $errors[] = esc_html__('Invalid file path.', 'vdp');
					else {
						if (file_exists($filename) && is_file($filename)) {
							$filename_string = $filename;
						} else $errors[] = esc_html__('Path not found.', 'vdp');
					}
					break;
				default:
					$errors[] = esc_html__('Invalid source.', 'vdp');
					break;
			}

			if (!empty($this->options['envato-api-token']) && $item_id != 0) {
				$result = $this->connect($this->options['envato-api-token'], 'v3/market/catalog/item?id='.$item_id);
				if (!empty($result) && is_array($result) && array_key_exists('name', $result)) {
					$item_title = $result['name'];
				} else $item_id = '';
			} else $item_id = '';

			
			if (!empty($errors)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = implode('<br />', $errors);
				echo json_encode($return_object);
				exit;
			}
			
			if (!empty($id)) {
				$sql = "UPDATE ".$wpdb->prefix."vdp_files SET 
					title = '".esc_sql($title)."', 
					source = '".esc_sql($source)."', 
					filename = '".esc_sql($filename)."', 
					filename_string = '".esc_sql($filename_string)."',
					item_id = '".esc_sql($item_id)."', 
					item_title = '".esc_sql($item_title)."', 
					options = '".esc_sql(json_encode($file_options))."'
					WHERE id = '".$id."'";
			} else {
				$sql = "INSERT INTO ".$wpdb->prefix."vdp_files (
					title, source, filename, filename_string, item_id, item_title, options, downloads, active, created, deleted) VALUES (
					'".esc_sql($title)."', '".esc_sql($source)."', '".esc_sql($filename)."', '".esc_sql($filename_string)."', '".esc_sql($item_id)."', '".esc_sql($item_title)."', '".esc_sql(json_encode($file_options))."', '0', '1', '".time()."', '0')";
			}
			if ($wpdb->query($sql) === false) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = esc_html__('Service is not available', 'vdp');
				echo json_encode($return_object);
				exit;
			}
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['message'] = esc_html__('Record successfully saved.', 'vdp');
			$return_object['redirect'] = admin_url('admin.php').'?page=vdp';
			echo json_encode($return_object);
			exit;
		}
	}

	function admin_delete_file() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			if (array_key_exists('id', $_REQUEST)) $id = intval($_REQUEST["id"]);
			else $id = 0;
			$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
			if (empty($file_details)) {
				$return_object = array('status' => 'ERROR', 'message' => esc_html__('File not found.', 'vdp'));
				echo json_encode($return_object);
				exit;
			}
			$sql = "UPDATE ".$wpdb->prefix."vdp_files SET deleted = '1' WHERE id = '".esc_sql($id)."'";
			$wpdb->query($sql);
			$return_object = array('status' => 'OK', 'message' => esc_html__('File successfully removed.', 'vdp'));
			echo json_encode($return_object);
			exit;
		}
	}

	function admin_file_status_toggle() {
		global $wpdb, $vdp;
		if (current_user_can('manage_options') || $vdp->demo_mode) {
			$callback = '';
			if (array_key_exists("callback", $_REQUEST)) {
				header("Content-type: text/javascript");
				$callback = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['callback']);
			}
			$file_details = null;
			if (array_key_exists('id', $_REQUEST)) {
				$id = intval($_REQUEST['id']);
				$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
			}
			if (empty($file_details)) {
				$return_data = array('status' => 'ERROR', 'message' => esc_html__('Requested file not found.', 'vdp'));
				if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
				else echo json_encode($return_data);
				exit;
			}
			if ($_REQUEST['status'] == 'inactive') {
				$wpdb->query("UPDATE ".$wpdb->prefix."vdp_files SET active = '1' WHERE id = '".esc_sql($file_details['id'])."'");
				$return_data = array(
					'status' => 'OK',
					'message' => esc_html__('File successfully activated.', 'vdp'),
					'record_action' => esc_html__('Deactivate', 'vdp'),
					'record_action_doing' => esc_html__('Deactivating...', 'vdp'),
					'record_status' => 'active'
				);
			} else {
				$wpdb->query("UPDATE ".$wpdb->prefix."vdp_files SET active = '0' WHERE id = '".esc_sql($file_details['id'])."'");
				$return_data = array(
					'status' => 'OK',
					'message' => esc_html__('File successfully deactivated.', 'vdp'),
					'record_action' => esc_html__('Activate', 'vdp'),
					'record_action_doing' => esc_html__('Activating...', 'vdp'),
					'record_status' => 'inactive',
					'record_badge' => esc_html__('Inactive', 'vdp'),
				);
			}
			if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
			else echo json_encode($return_data);
		}
		exit;
	}

	function admin_log() {
		global $wpdb;

		if (array_key_exists("s", $_GET)) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		if (array_key_exists("fid", $_GET)) $file_id = intval(trim(stripslashes($_GET["fid"])));
		else $file_id = 0;
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."vdp_log WHERE deleted = '0'".($file_id > 0 ? " AND file_id = '".esc_sql($file_id)."'" : "").((strlen($search_query) > 0) ? " AND (purchase_code LIKE '%".esc_sql($search_query)."%' OR buyer LIKE '%".esc_sql($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/VDP_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (array_key_exists("p", $_GET)) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(admin_url("admin.php")."?page=vdp-log".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : "").($file_id > 0 ? "&fid=".$file_id : ""), $page, $totalpages);

		$sql = "SELECT t1.*, t2.deleted AS file_deleted, t2.title AS file_title, t2.filename_string AS file_filename_string, t2.source AS file_source FROM ".$wpdb->prefix."vdp_log t1 LEFT JOIN ".$wpdb->prefix."vdp_files t2 ON t1.file_id = t2.id WHERE t1.deleted = '0'".($file_id > 0 ? " AND t1.file_id = '".esc_sql($file_id)."'" : "").((strlen($search_query) > 0) ? " AND (t1.purchase_code LIKE '%".esc_sql($search_query)."%' OR t1.buyer LIKE '%".esc_sql($search_query)."%')" : "")." ORDER BY t1.created DESC LIMIT ".(($page-1)*VDP_RECORDS_PER_PAGE).", ".VDP_RECORDS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);

		echo '
			<div class="wrap vdp-admin vdp">
				<h2>'.esc_html__('Verified Downloads - Log', 'vdp').'</h2>
				<form action="'.admin_url('admin.php').'" method="get" class="uap-filter-form vdp-filter-form">
				<input type="hidden" name="page" value="vdp-log" />
				'.($file_id > 0 ? '<input type="hidden" name="fid" value="'.$file_id.'" />' : '').'
				<label>'.esc_html__('Search:', 'vdp').'</label>
				<input type="text" name="s" value="'.esc_html($search_query).'" style="width: 200px;" class="form-control">
				<input type="submit" class="button-secondary action" value="'.esc_html__('Search', 'vdp').'" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="'.esc_html__('Reset search results', 'vdp').'" onclick="window.location.href=\''.admin_url('admin.php').'?page=vdp-log'.($file_id > 0 ? '&fid='.$file_id : '').'\';" />' : '').'
				</form>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
				<table id="vdp-table-transactions" class="vdp-table-list widefat">
					<tr>
						<th>'.esc_html__('Purchase Code', 'vdp').'</th>
						<th>'.esc_html__('Buyer', 'vdp').'</th>
						<th>'.esc_html__('File', 'vdp').'</th>
						<th style="width: 120px;">'.esc_html__('Downloads', 'vdp').'</th>
						<th style="width: 130px;">'.esc_html__('Created', 'vdp').'</th>
						<th style="width: 35px;"></th>
					</tr>';
		if (sizeof($rows) > 0) {
			foreach ($rows as $row) {
				$title = trim($row['file_title']);
				if (empty($title)) $title = $row['file_filename_string'];
				$badge = '';
				$enable_disable = '';
				if ($row["created"]+3600*intval($this->options['link-lifetime']) < time()) $badge = '<span class="vdp-badge vdp-badge-info">'.esc_html__('Expired', 'vdp').'</span>';
				else {
					if ($row['status'] == VDP_LOG_STATUS_DISABLED) {
						$badge = '<span class="vdp-badge vdp-badge-danger">'.esc_html__('Disabled', 'vdp').'</span>';
						$enable_disable = '<li><a href="#" data-status="disabled" data-id="'.esc_html($row['id']).'" data-doing="'.esc_html__('Enabling...', 'vdp').'" onclick="return vdp_toggle_log(this);">'.esc_html__('Enable', 'vdp').'</a></li>';
					} else $enable_disable = '<li><a href="#" data-status="enabled" data-id="'.esc_html($row['id']).'" data-doing="'.esc_html__('Disabling...', 'vdp').'" onclick="return vdp_toggle_log(this);">'.esc_html__('Disable', 'vdp').'</a></li>';
				}
				$download_url = defined('UAP_CORE') ? admin_url('do.php').'?vdp-key='.$row['download_key'] : get_bloginfo("url").'/?vdp-key='.$row['download_key'];
				echo '
					<tr>
						<td>'.esc_html($row['purchase_code']).'<span class="vdp-table-list-badge-status">'.$badge.'</span><label class="vdp-table-list-em" onclick="window.getSelection().selectAllChildren(this);">'.(!empty($row['download_key']) ? esc_html($download_url) : '-').'</label></td>
						<td>'.(empty($row['buyer']) ? '-' : esc_html($row['buyer'])).'</td>
						<td>'.esc_html($title).'</td>
						<td>'.intval($row['downloads']).'</td>
						<td>'.esc_html($this->unixtime_string($row['created'])).'</td>
						<td>
							<div class="vdp-table-list-actions">
								<span><i class="vdp-fa vdp-fa-ellipsis-vert"></i></span>
								<div class="vdp-table-list-menu">
									<ul>
										'.$enable_disable.'
										<li><a href="#" data-id="'.esc_html($row['id']).'" data-doing="'.esc_html__('Deleting...', 'vdp').'" onclick="return vdp_delete_log(this);">'.esc_html__('Delete', 'vdp').'</a></li>
									</ul>
								</div>
							</div>
						</td>
					</tr>';
			}
		} else {
			echo '
				<tr><td colspan="6" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? esc_html__('No results found for', 'vdp').' "<strong>'.esc_html($search_query).'</strong>"' : esc_html__('List is empty.', 'vdp')).'</td></tr>';
		}
		echo '
				</table>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
			</div>
			<div class="vdp-admin-popup-overlay" id="vdp-admin-popup-overlay"></div>
			<div class="vdp-admin-popup" id="vdp-admin-popup">
				<div class="vdp-admin-popup-inner">
					<div class="vdp-admin-popup-title">
						<a href="#" title="'.esc_html__('Close', 'vdp').'" onclick="return vdp_admin_popup_close();"><i class="vdp-fa vdp-fa-cancel"></i></a>
						<h3><label></label><span></span></h3>
					</div>
					<div class="vdp-admin-popup-content">
						<div class="vdp-admin-popup-content-form">
						</div>
					</div>
					<div class="vdp-admin-popup-loading"><i class="vdp-fa vdp-fa-spinner vdp-fa-spin"></i></div>
				</div>
			</div>
			<div id="vdp-global-message"></div>';
		echo $this->admin_modal_html();
	}

	function admin_toggle_log() {
		global $wpdb, $vdp;
		if (current_user_can('manage_options') || $vdp->demo_mode) {
			$callback = '';
			if (array_key_exists("callback", $_REQUEST)) {
				header("Content-type: text/javascript");
				$callback = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['callback']);
			}
			$log_details = null;
			if (array_key_exists('id', $_REQUEST)) {
				$id = intval($_REQUEST['id']);
				$log_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_log WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
			}
			if (empty($log_details)) {
				$return_data = array('status' => 'ERROR', 'message' => esc_html__('Requested record not found.', 'vdp'));
				if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
				else echo json_encode($return_data);
				exit;
			}
			if ($_REQUEST['status'] == 'disabled') {
				$wpdb->query("UPDATE ".$wpdb->prefix."vdp_log SET status = '".VDP_LOG_STATUS_ENABLED."' WHERE deleted = '0' AND id = '".esc_sql($log_details['id'])."'");
				$return_data = array(
					'status' => 'OK',
					'message' => esc_html__('Download link successfully enabled.', 'vdp'),
					'record_action' => esc_html__('Disable', 'vdp'),
					'record_action_doing' => esc_html__('Disabling...', 'vdp'),
					'record_status' => 'enabled'
				);
			} else {
				$wpdb->query("UPDATE ".$wpdb->prefix."vdp_log SET status = '".VDP_LOG_STATUS_DISABLED."' WHERE deleted = '0' AND id = '".esc_sql($log_details['id'])."'");
				$return_data = array(
					'status' => 'OK',
					'message' => esc_html__('Download link successfully disabled.', 'vdp'),
					'record_action' => esc_html__('Enable', 'vdp'),
					'record_action_doing' => esc_html__('Enabling...', 'vdp'),
					'record_status' => 'disabled',
					'record_badge' => esc_html__('Disabled', 'vdp'),
				);
			}
			if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
			else echo json_encode($return_data);
		}
		exit;
	}

	function admin_delete_log() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			$callback = '';
			if (array_key_exists("callback", $_REQUEST)) {
				header("Content-type: text/javascript");
				$callback = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['callback']);
			}
			if (array_key_exists('id', $_REQUEST)) $id = intval($_REQUEST["id"]);
			else $id = 0;
			$log_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_log WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
			if (empty($log_details)) {
				$return_object = array('status' => 'ERROR', 'message' => esc_html__('Record not found.', 'vdp'));
				if (!empty($callback)) echo $callback.'('.json_encode($return_object).')';
				else echo json_encode($return_object);
				exit;
			}
			$sql = "UPDATE ".$wpdb->prefix."vdp_log SET deleted = '1' WHERE id = '".esc_sql($id)."'";
			$wpdb->query($sql);
			$return_object = array('status' => 'OK', 'message' => esc_html__('Record successfully removed.', 'vdp'));
			if (!empty($callback)) echo $callback.'('.json_encode($return_object).')';
			else echo json_encode($return_object);
			exit;
		}
	}

	function admin_blacklist() {
		global $wpdb;

		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."vdp_blacklist WHERE deleted = '0'", ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/VDP_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (array_key_exists("p", $_GET)) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(admin_url("admin.php")."?page=vdp-blacklist", $page, $totalpages);

		$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."vdp_blacklist WHERE deleted = '0' ORDER BY created DESC LIMIT ".(($page-1)*VDP_RECORDS_PER_PAGE).", ".VDP_RECORDS_PER_PAGE, ARRAY_A);

		echo '
			<div class="wrap vdp-admin vdp">
				<h2>'.esc_html__('Verified Downloads - Blacklist', 'vdp').'</h2>
				<div class="vdp-blacklist-form">
					<input type="text" name="blackitem" value="" style="width: 280px;" class="form-control" placeholder="'.esc_html__('Username or Item Purchase Code', 'vdp').'" />
					<a class="vdp-button" onclick="return vdp_add_blackitem(this);"><i class="vdp-fa vdp-fa-ok"></i><label>'.esc_html__('Add', 'vdp').'</label></a>
				</div>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
				<table id="vdp-table-transactions" class="vdp-table-list widefat">
					<tr>
						<th>'.esc_html__('Username', 'vdp').'</th>
						<th>'.esc_html__('Purchase Code', 'vdp').'</th>
						<th style="width: 130px;">'.esc_html__('Created', 'vdp').'</th>
						<th style="width: 35px;"></th>
					</tr>';
		if (sizeof($rows) > 0) {
			foreach ($rows as $row) {
				echo '
					<tr>
						<td>'.esc_html($row['username']).'</td>
						<td>'.(!empty($row['purchase_code']) ? esc_html($row['purchase_code']) : esc_html__('All Item Purchase Codes', 'vdp')).'</td>
						<td>'.esc_html($this->unixtime_string($row['created'])).'</td>
						<td>
							<div class="vdp-table-list-actions">
								<span><i class="vdp-fa vdp-fa-ellipsis-vert"></i></span>
								<div class="vdp-table-list-menu">
									<ul>
										<li><a href="#" data-id="'.esc_html($row['id']).'" data-doing="'.esc_html__('Deleting...', 'vdp').'" onclick="return vdp_delete_blackitem(this);">'.esc_html__('Delete', 'vdp').'</a></li>
									</ul>
								</div>
							</div>
						</td>
					</tr>';
			}
		} else {
			echo '
				<tr><td colspan="4" style="padding: 20px; text-align: center;">'.esc_html__('List is empty.', 'vdp').'</td></tr>';
		}
		echo '
				</table>
				<div class="vdp-pageswitcher">'.$switcher.'</div>
			</div>
			<div id="vdp-global-message"></div>';
		echo $this->admin_modal_html();
	}

	function admin_add_blackitem() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			if (empty($this->options['envato-api-token'])) {
				echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('Set your Envato API Token on Settings page.', 'vdp')));
				exit;
			}
			$blackitem = '';
			if (array_key_exists('blackitem', $_REQUEST)) $blackitem = stripslashes(trim($_REQUEST['blackitem']));

			if (empty($blackitem)) {
				echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('Blacklist item can not be empty.', 'vdp')));
				exit;
			}
			$errors = array();

			$username = '';
			$purchase_code = '';
			$result = $this->connect($this->options['envato-api-token'], 'v1/market/user:'.urlencode($blackitem).'.json');
			if (!empty($result) && is_array($result) && array_key_exists('user', $result)) {
				$username = $blackitem;
			} else {
				$blackitem = preg_replace('/[^a-zA-Z0-9\-]/', '', $blackitem);
				$result = $this->connect($this->options['envato-api-token'], 'v3/market/author/sale?code='.urlencode($blackitem));
				if (!empty($result) && is_array($result) && array_key_exists('item', $result)) {
					$purchase_code = $blackitem;
					$username = $result['buyer'];
				} else {
					echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('Username or Item Purchase Code not found.', 'vdp')));
					exit;
				}
			}
			if ($wpdb->query("INSERT INTO ".$wpdb->prefix."vdp_blacklist (username, purchase_code, created, deleted) VALUES ('".esc_sql($username)."', '".esc_sql($purchase_code)."', '".time()."', '0')") === false) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = esc_html__('Service is not available', 'vdp');
				echo json_encode($return_object);
				exit;
			}
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['message'] = esc_html__('Item successfully saved.', 'vdp');
			$return_object['redirect'] = admin_url('admin.php').'?page=vdp-blacklist';
			echo json_encode($return_object);
			exit;
		}
	}

	function admin_delete_blackitem() {
		global $wpdb;
		if ($this->demo_mode) {
			echo json_encode(array('status' => 'ERROR', 'message' => esc_html__('This operation disabled in DEMO mode.', 'vdp')));
			exit;
		}
		if (current_user_can('manage_options')) {
			$callback = '';
			if (array_key_exists("callback", $_REQUEST)) {
				header("Content-type: text/javascript");
				$callback = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['callback']);
			}
			if (array_key_exists('id', $_REQUEST)) $id = intval($_REQUEST["id"]);
			else $id = 0;
			$log_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_blacklist WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
			if (empty($log_details)) {
				$return_object = array('status' => 'ERROR', 'message' => esc_html__('Record not found.', 'vdp'));
				if (!empty($callback)) echo $callback.'('.json_encode($return_object).')';
				else echo json_encode($return_object);
				exit;
			}
			$sql = "UPDATE ".$wpdb->prefix."vdp_blacklist SET deleted = '1' WHERE id = '".esc_sql($id)."'";
			$wpdb->query($sql);
			$return_object = array('status' => 'OK', 'message' => esc_html__('Record successfully removed.', 'vdp'));
			if (!empty($callback)) echo $callback.'('.json_encode($return_object).')';
			else echo json_encode($return_object);
			exit;
		}
	}

	function admin_using() {
		global $wpdb;
		$remote_snippet = '<script id="vdp-remote" src="'.plugins_url('/js/vdp.js', __FILE__).'?ver='.VDP_VERSION.'" data-handler="'.admin_url('admin-ajax.php').'"></script>';
		echo '
		<div class="wrap vdp-admin vdp">
			<h2>'.esc_html__('Verified Downloads - How To Use', 'vdp').'</h2>
			<div class="vdp-postbox vdp-using-page">
				<h3>Embedding Verified Downloads into website</h3>
				<p>To embed Verified Downloads into any website you need perform the following steps:</p>
				<ol>
				<li>
					Make sure that website has DOCTYPE. If not, add the following line as a first line of HTML-document:
					<input type="text" readonly="readonly" value="&lt;!DOCTYPE html&gt;" onclick="this.focus();this.select();">
				</li>
				<li>
					Make sure that website loads jQuery version 1.9 or higher. If not, add the following line into head section of HTML-document:
					<input type="text" readonly="readonly" value="&lt;script src=&quot;https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js&quot;&gt;&lt;/script&gt;" onclick="this.focus();this.select();">
				</li>
				<li>
					Copy the following JS-snippet and paste it into your website. You need paste it at the end of <code>&lt;body&gt;</code> section (above closing <code>&lt;/body&gt;</code> tag).
					<input type="text" readonly="readonly" onclick="this.focus();this.select();" value="'.esc_html($remote_snippet).'" />
				</li>
				<li>That\'s it. Integration finished. :-)</li>
				</ol>
				<h3>Using Verified Downloads</h3>
				<p>Copy file\'s shortcode and paste it where payment button must be. Shortcodes can be found on <a href="'.admin_url('admin.php').'?page=vdp">Files</a> page.</p>
			</div>
		</div>';
	}

	function admin_file_using() {
		global $wpdb;
		if (current_user_can('manage_options') || $this->demo_mode) {
			$callback = '';
			if (isset($_REQUEST['callback'])) {
				header("Content-type: text/javascript");
				$callback = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['callback']);
			}

			$file_details = null;
			if (array_key_exists('id', $_REQUEST)) {
				$file_id = intval($_REQUEST['id']);
				$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE deleted = '0' AND id = '".esc_sql($file_id)."'", ARRAY_A);
			}
			if (empty($file_details)) {
				$return_data = array(
					'status' => 'ERROR',
					'message' => esc_html__('Requested file not found.', 'vdp')
				);
				if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
				else echo json_encode($return_data);
				exit;
			}
			if (defined('UAP_CORE')) {
				$html = '
			<div class="vdp-using-details">
				<table class="vdp-using-table">
					<tr>
						<td colspan="2">
							<span>'.sprintf(esc_html__('Important! Make sure that you properly embedded script into your website, as it is said on %sHow To Use%s page.', 'vdp'), '<a target="_blank" href="?page=vdp-using">', '</a>').'</span>
						</td>
					</tr>
					<tr>
						<th>'.esc_html__('Shortcode', 'vdp').'</th>
						<td>
							<input type="text" readonly="readonly" value="'.esc_html('<div class="vdp" data-id="'.$file_details['id'].'"></div>').'" onclick="this.focus();this.select();" />
						</td>
					</tr>
				</table>
			</div>';
			} else {
				$html = '
			<div class="vdp-using-details">
				<table class="vdp-using-table">
					<tr>
						<th>'.esc_html__('Gutenberg Block', 'vdp').'</th>
						<td>
							<span>'.esc_html__('In case of using Gutenberg content editor you can add the button as a standard Gutenberg Block. Find it under Widgets category.', 'vdp').'</span>
						</td>
					</tr>
					<tr>
						<th>'.esc_html__('Shortcode', 'vdp').'</th>
						<td>
							<input type="text" readonly="readonly" value="[vdp id=\''.esc_html($file_details['id']).'\']" onclick="this.focus();this.select();" />
						</td>
					</tr>
					<tr>
						<th>'.esc_html__('PHP', 'vdp').'</th>
						<td>
							<span>'.esc_html__('Use the following PHP-code to embed the button into theme files:', 'vdp').'</span>
							<input type="text" readonly="readonly" value="'.esc_html('<?php do_shortcode("[vdp id=\''.esc_html($file_details['id']).'\']"); ?>').'" onclick="this.focus();this.select();" />
						</td>
					</tr>
					<tr>
						<th>'.esc_html__('Widget', 'vdp').'</th>
						<td>
							<span>'.esc_html__('Go to Appearance >> Widgets and drag the Verified Downloads widget into the desired sidebar. You will be able to select this file from the dropdown options while configuring the widget.', 'vdp').'</span>
						</td>
					</tr>
					<tr>
						<th>'.esc_html__('Remote use', 'vdp').'</th>
						<td>
							<span>'.esc_html__('Use the form with any non-WordPress pages of the site or with 3rd party sites. How to do it?', 'vdp').'</span>
							<ol>
								<li>
									<span>'.sprintf(esc_html__('Make sure that non-WordPress page has %sDOCTYPE%s. If not, add the following line as a first line of HTML-document:', 'vdp'), '<code>', '</code>').'</span>
									<input type="text" readonly="readonly" value="'.esc_html('<!DOCTYPE html>').'" onclick="this.focus();this.select();" />
								</li>
								<li>
									<span>'.sprintf(esc_html__('Make sure that website loads jQuery version 1.9 or higher. If not, add the following line into %shead%s section of HTML-document:', 'vdp'), '<code>', '</code>').'</span>
									<input type="text" readonly="readonly" value="'.esc_html('<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>').'" onclick="this.focus();this.select();" />
								</li>
								<li>
									<span>'.sprintf(esc_html__('Copy the following JS-snippet and paste it into HTML-document. You need paste it at the end of %sbody%s section (above closing %s</body>%s tag).', 'vdp'), '<code>', '</code>', '<code>', '</code>').'</span>
									<input type="text" readonly="readonly" value="'.esc_html('<script id="vdp-remote" src="'.plugins_url('/js/vdp.js?ver='.VDP_VERSION, __FILE__).'" data-handler="'.admin_url('admin-ajax.php').'"></script>').'" onclick="this.focus();this.select();" />
									<span>'.esc_html__('PS: You need do it one time only, even if you use several buttons on the same page.', 'vdp').'</span>
								</li>
								<li>
									<span>'.esc_html__('Use the following HTML-code to embed the form into HTML-document as a button:', 'vdp').'</span>
									<input type="text" readonly="readonly" value="'.esc_html('<div class="vdp" data-id="'.esc_html($file_details['id']).'"></div>').'" onclick="this.focus();this.select();" />
								</li>
							</ol>
						</td>
					</tr>
				</table>
			</div>';
			}
			$return_data = array(
				'status' => 'OK',
				'html' => $html,
				'title' => empty($file_details['title']) ? $file_details['filename_string'] : esc_html($file_details['title'])
			);
			if (!empty($callback)) echo $callback.'('.json_encode($return_data).')';
			else echo json_encode($return_data);
		}
		exit;
	}

	function admin_header() {
		global $wpdb;
		$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE deleted = '0' ORDER BY id DESC", ARRAY_A);
		$files = array();
		foreach($rows as $row) {
			$files[] = array(
				'id' => $row['id'],
				'title' => (empty($row['title']) ? $row['filename_string'] : $row['title'])
			);
		}
		echo '
		<script>
			var vdp_ajax_handler = "'.admin_url('admin-ajax.php').'";
			var vdp_files_encoded = "'.base64_encode(json_encode($files)).'";
		</script>';
	}

	function init() {
		global $wpdb;
		if (array_key_exists('vdp-id', $_GET) || array_key_exists('vdp-key', $_GET)) {
			$error = '';
//			if ($this->demo_mode) {
//				$error = esc_html__('This operation disabled in DEMO mode.', 'vdp');
//			} else {
				if (array_key_exists('vdp-id', $_GET)) {
					$id = intval($_GET["vdp-id"]);
					$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".esc_sql($id)."' AND deleted = '0'", ARRAY_A);
					if (empty($file_details)) $error = empty($this->options['error-invalid-link']) ? esc_html__('Invalid download link.', 'vdp') : $this->options['error-invalid-link'];
				} else {
					$download_key = $_GET["vdp-key"];
					$download_key = preg_replace('/[^a-zA-Z0-9-]/', '', $download_key);
					if (empty($download_key)) $error = empty($this->options['error-invalid-link']) ? esc_html__('Invalid download link.', 'vdp') : $this->options['error-invalid-link'];
					else {
						$log_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_log WHERE download_key = '".esc_sql($download_key)."' AND deleted = '0' AND status != '".esc_sql(VDP_LOG_STATUS_DISABLED)."'", ARRAY_A);
						if (empty($log_details)) $error = empty($this->options['error-invalid-link']) ? esc_html__('Invalid download link.', 'vdp') : $this->options['error-invalid-link'];
						else {
							$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".esc_sql($log_details["file_id"])."' AND deleted = '0' AND active = '1'", ARRAY_A);
							if (empty($file_details)) $error = empty($this->options['error-invalid-link']) ? esc_html__('Invalid download link.', 'vdp') : $this->options['error-invalid-link'];
							else if ($log_details["created"]+3600*intval($this->options['link-lifetime']) < time()) $error = empty($this->options['error-expired-link']) ? esc_html__('Download link expired.', 'vdp') : $this->options['error-expired-link'];
						}
					}
				}
				if (!empty($error)) {
					$this->front_error($error);
					exit;
				}

				do_action("vdp_front_download", $file_details, empty($log_details) ? null : $log_details);
				switch ($file_details['source']) {
					case 'media-library':
						if (defined('UAP_CORE')) {
							$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
							exit;
						}
						$attachment_id = intval($file_details['filename']);
						$attachment = get_post($attachment_id, ARRAY_A);
						if (is_array($attachment) && array_key_exists('post_type', $attachment) && $attachment['post_type'] == 'attachment') {
							$filename = get_attached_file($attachment_id);
							if (!file_exists($filename) || !is_file($filename)) {
								$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
								exit;
							}
							$filename_original = basename($filename);
							error_reporting(0);
							ob_start();
							if(!ini_get('safe_mode')) set_time_limit(0);
							ob_end_clean();
							if ($this->options['xsendfile'] == 'on') {
								header('X-Sendfile: '.$filename);
								header('Content-type: application/octet-stream');
								header('Content-Disposition: attachment; filename="'.$filename_original.'"');
							} else {
								$length = filesize($filename);
								if (strstr($_SERVER["HTTP_USER_AGENT"],"MSIE")) {
									header("Pragma: public");
									header("Expires: 0");
									header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
									header("Content-type: application-download");
									header("Content-Length: ".$length);
									header("Content-Disposition: attachment; filename=\"".$filename_original."\"");
									header("Content-Transfer-Encoding: binary");
								} else {
									header("Content-type: application-download");
									header("Content-Length: ".$length);
									header("Content-Disposition: attachment; filename=\"".$filename_original."\"");
								}
								$handle_read = fopen($filename, "rb");
								while (!feof($handle_read) && $length > 0) {
									$content = fread($handle_read, 1024);
									echo substr($content, 0, min($length, 1024));
									flush();
									if (ob_get_level() > 0) ob_flush();
									$length = $length - strlen($content);
									if ($length < 0) $length = 0;
								}
								fclose($handle_read);
							}
							if (!empty($log_details)) $wpdb->query("UPDATE ".$wpdb->prefix."vdp_log SET downloads = downloads + 1 WHERE id = '".$log_details['id']."'");
							$wpdb->query("UPDATE ".$wpdb->prefix."vdp_files SET downloads = downloads + 1 WHERE id = '".$file_details['id']."'");
							exit;
						} else {
							$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
							exit;
						}
						break;
					case 'url':
						//if (strlen($file_details['filename']) > 0 && preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $file_details['filename'])) {
							if (!empty($log_details)) $wpdb->query("UPDATE ".$wpdb->prefix."vdp_log SET downloads = downloads + 1 WHERE id = '".$log_details['id']."'");
							$wpdb->query("UPDATE ".$wpdb->prefix."vdp_files SET downloads = downloads + 1 WHERE id = '".$file_details['id']."'");
							header('Location: '.$file_details['filename']);
							exit;
						//} else {
						//	$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
						//	exit;
						//}
						break;
					case 'file':
					case 'path':
						if ($file_details['source'] == 'file') {
							$upload_dir = wp_upload_dir();
							$filename_full = $upload_dir["basedir"].DIRECTORY_SEPARATOR.VDP_UPLOADS_DIR.DIRECTORY_SEPARATOR.$file_details['filename'];
							$filename = $file_details['filename'];
						} else {
							$filename_full = $file_details['filename'];
							$filename = basename($file_details['filename']);
						}
						if (!file_exists($filename_full) || !is_file($filename_full)) {
							$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
							exit;
						}
						error_reporting(0);
						ob_start();
						if(!ini_get('safe_mode')) set_time_limit(0);
						ob_end_clean();
						if ($this->options['xsendfile'] == 'on') {
							header('X-Sendfile: '.$filename_full);
							header('Content-type: application/octet-stream');
							header('Content-Disposition: attachment; filename="'.$filename.'"');
						} else {
							$length = filesize($filename_full);
							if (strstr($_SERVER["HTTP_USER_AGENT"],"MSIE")) {
								header("Pragma: public");
								header("Expires: 0");
								header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
								header("Content-type: application-download");
								header("Content-Length: ".$length);
								header("Content-Disposition: attachment; filename=\"".$filename."\"");
								header("Content-Transfer-Encoding: binary");
							} else {
								header("Content-type: application-download");
								header("Content-Length: ".$length);
								header("Content-Disposition: attachment; filename=\"".$filename."\"");
							}
							$handle_read = fopen($filename_full, "rb");
							while (!feof($handle_read) && $length > 0) {
								$content = fread($handle_read, 1024);
								echo substr($content, 0, min($length, 1024));
								flush();
								if (ob_get_level() > 0) ob_flush();
								$length = $length - strlen($content);
								if ($length < 0) $length = 0;
							}
							fclose($handle_read);
						}
						if (!empty($log_details)) $wpdb->query("UPDATE ".$wpdb->prefix."vdp_log SET downloads = downloads + 1 WHERE id = '".$log_details['id']."'");
						$wpdb->query("UPDATE ".$wpdb->prefix."vdp_files SET downloads = downloads + 1 WHERE id = '".$file_details['id']."'");
						exit;
						break;
					default:
						$this->front_error(empty($this->options['error-no-file']) ? esc_html__('File does not exist.', 'vdp') : $this->options['error-no-file']);
						exit;
						break;
				}
//			}
			exit;
		}
		do_action("vdp_front_init");
		add_action('wp_enqueue_scripts', array(&$this, 'front_enqueue_scripts'));
	}

	function front_error($_error) {
		echo '<!DOCTYPE html>
<html>
<head>
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>'.esc_html(get_bloginfo("name")).'</title>
	<link href="//fonts.googleapis.com/css?family=Open+Sans:400,300&subset=latin,cyrillic-ext,greek-ext,latin-ext,cyrillic,greek,vietnamese" rel="stylesheet" type="text/css">
	<style>
		body {font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif; font-weight: 100; color: #444; background-color: #fff; line-height: 1.475;font-size: 24px;}
		.front-container {position: absolute;top: 0;right: 0;bottom: 0;left: 0;min-width: 240px;height: 100%;display: table;width: 100%;}
		.front-content {max-width: 1024px;margin: 0px auto;padding: 20px 0;position: relative;display: table-cell;text-align: center;vertical-align: middle;}
	</style>
</head>
<body>
	<div class="front-container">
		<div class="front-content">
			'.$_error.'
		</div>
	</div>
</body>
</html>';
		exit;
	}

	function front_header() {
		echo '<script>var vdp_ajax_url = "'.admin_url('admin-ajax.php').'";</script>';
		do_action("vdp_front_header");
	}

	function front_footer() {
		do_action("vdp_front_footer");
	}


	function shortcode_handler($_atts) {
		global $wpdb;
		$button = '';
		$errors = $this->check_options(false);
		if (empty($errors)) {
			if (array_key_exists('xd', $_atts) && $_atts['xd'] === true && $this->options['cross-domain-enable'] != 'on') {
				return '<div class="vdp-xd-forbidden">'.esc_html__('Cross-domain calls are not allowed.', 'vdp').'</div>';
			}
			
			$file_id = intval($_atts["id"]);
			$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".$file_id."' AND deleted = '0' AND active = '1'", ARRAY_A);
			if (empty($file_details)) return "";

			$terms = '';
			$terms_label = '';
			if (!empty($this->options['gdpr-enable'] == 'on') && !empty($this->options['gdpr-title'])) {
				if (!empty($this->options['terms'])) {
					$terms = esc_html($this->options['terms']);
					$tags = array("\r", "\n");
					$vals = array("", "<br />");
					$terms = str_replace($tags, $vals, $terms);
					preg_match("'{(.*?)}'si", $this->options['gdpr-title'], $match);
					$local_terms_title = '';
					if (!empty($match) && is_array($match)) $local_terms_title = $match[1];
					if (!empty($local_terms_title)) $terms_label = str_replace('{'.$local_terms_title.'}', '<a href="#" onclick="jQuery(this).parent().find(\'.vdp-terms-container\').slideToggle(300); return false;">'.esc_html($local_terms_title).'</a>', $this->options['gdpr-title']);
					else $terms_label = '<a href="#" onclick="jQuery(this).parent().find(\'.vdp-terms-container\').slideToggle(300); return false;">'.esc_html($this->options['gdpr-title']).'</a>';
				} else {
					$terms_label = $this->options['gdpr-title'];
				}
			}
			$html = '
<div class="vdp-container">
	<div class="vdp-ready">
		<div class="vdp-form-inner">
			<div class="vdp-row vdp-element">
					<div class="vdp-elements">
						<div class="vdp-element">
							<div class="vdp-input vdp-input-purchase-code">
								<input type="text" name="purchase-code" placeholder="'.esc_html__('Item Purchase Code...', 'vdp').'" value="" onfocus="jQuery(this).closest(\'.vdp-input\').find(\'.vdp-element-error\').fadeOut(300, function(){jQuery(this).remove();});">
							</div>
							<label class="vdp-description"><a target="_blank" href="https://help.market.envato.com/hc/en-us/articles/202822600">'.esc_html__('Where is my Item Purchase Code?', 'vdp').'</a></label>
						</div>
					</div>
			</div>';
		if (!empty($terms_label)) {
			$checkbox_id = uniqid('', true);
			$html .= '
			<div class="vdp-element">
				<div class="vdp-input vdp-input-terms">
					<input class="vdp-checkbox" type="checkbox" name="terms" id="vdp-terms-agree-'.esc_html($checkbox_id).'" value="on"><label for="vdp-terms-agree-'.esc_html($checkbox_id).'" onclick="jQuery(this).closest(\'.vdp-input\').find(\'.vdp-element-error\').fadeOut(300, function(){jQuery(this).remove();});"></label>'.$terms_label.'
					'.(empty($terms) ? '' : '<div class="vdp-terms-container">'.$terms.'</div>').'
				</div>
			</div>';
		}
		$html .= '
			<div class="vdp-row vdp-element">
				<div class="vdp-element">
					<div class="vdp-input">
						<input type="hidden" name="file-id" value="'.esc_html($file_details['id']).'">
						<a class="vdp-button" href="#" onclick="return vdp_continue(this);" data-label="'.esc_html__('Download', 'vdp').'" data-loading="'.esc_html__('Sending...', 'vdp').'">
							<span>'.esc_html__('Download', 'vdp').'</span>
							<i class="vdp-icon-right vdp-fa vdp-fa-right-open"></i>
						</a>
					</div>
				</div>
			</div>
			<div class="vdp-inline-error vdp-warning"></div>
		</div>
	</div>
</div>';
		}
		return $html;
	}

	function front_continue() {
		global $wpdb;
		if (isset($_REQUEST['callback'])) {
			header("Content-type: text/javascript");
			$jsonp_callback = $_REQUEST['callback'];
		} else $jsonp_callback = null;

		$errors = array();
		$field_names = array("file-id", "purchase-code", "terms");
		foreach ($field_names as $name) {
			if (!array_key_exists($name, $_REQUEST)) {
				$errors[$name] = esc_html__('This parameter is missing.', 'vdp');
			}
		}
		
		if (!empty($errors)) {
			$return_data = array('status' => 'ERROR', 'errors' => $errors);
			if (!empty($jsonp_callback)) echo $jsonp_callback.'('.json_encode($return_data).')';
			else echo json_encode($return_data);
			exit;
		}

		$file_id = intval($_REQUEST['file-id']);
		$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_files WHERE id = '".$file_id."' AND deleted = '0' AND active = '1'", ARRAY_A);
		if (empty($file_details)) $errors['file-id'] = esc_html__('This file is not available.', 'vdp');

		$file_options = $this->default_file_options;
		if (!empty($file_details['options'])) $file_options = json_decode($file_details['options'], true);
		if (is_array($file_options)) $file_options = array_merge($this->default_file_options, $file_options);
		else $file_options = $this->default_file_options;

		$purchase_code = trim(stripslashes($_REQUEST['purchase-code']));
		$purchase_code = preg_replace('/[^a-zA-Z0-9\-]/', '', $purchase_code);
		if (empty($purchase_code)) $errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
		
		$blackitem_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_blacklist WHERE purchase_code = '".esc_sql($purchase_code)."' AND deleted = '0'", ARRAY_A);
		if (!empty($blackitem_details)) $errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
		
		$terms = trim(stripslashes($_REQUEST['terms']));
		if (!empty($this->options['gdpr-enable'] == 'on') && !empty($this->options['gdpr-title']) && $terms != 'on') $errors['terms'] = esc_html__('You must agree with the Terms & Conditions.', 'vdp');

		if (empty($errors)) {
			$result = $this->connect($this->options['envato-api-token'], 'v3/market/author/sale?code='.urlencode($purchase_code));
			if (empty($result)) {
				$errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
			} else if (array_key_exists('error', $result)) {
				$errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
			} else if (!array_key_exists('item', $result) || empty($result['item'])) {
				$errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
			} else {
				$blackitem_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."vdp_blacklist WHERE username = '".esc_sql($result['buyer'])."' AND deleted = '0'", ARRAY_A);
				if (!empty($blackitem_details)) $errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
				else {
					$buyer = $result['buyer'];
					$item_id = $result['item']['id'];
					if ($file_details['item_id'] != 0 && $file_details['item_id'] != $item_id) $errors['purchase-code'] = esc_html__('Invalid Item Purchase Code.', 'vdp');
				}
			}
		}

		if (!empty($errors)) {
			$return_data = array('status' => 'ERROR', 'errors' => $errors);
			if (!empty($jsonp_callback)) echo $jsonp_callback.'('.json_encode($return_data).')';
			else echo json_encode($return_data);
			exit;
		}


		$download_key = $this->uuid_v4();

		$sql = "INSERT INTO ".$wpdb->prefix."vdp_log (
			file_id, download_key, purchase_code, buyer, ip, details, downloads, status, created, deleted) VALUES (
			'".esc_sql($file_id)."', '".esc_sql($download_key)."', '".esc_sql($purchase_code)."', '".esc_sql($buyer)."', '".esc_sql($_SERVER['REMOTE_ADDR'])."', '".esc_sql(json_encode(array()))."', '0', '".esc_sql(VDP_LOG_STATUS_ENABLED)."', '".time()."', '0')";
		$wpdb->query($sql);
		$log_id = $wpdb->insert_id;
		
		$return_data = array(
			'status' => 'OK',
			'action' => 'redirect',
			'url' => defined('UAP_CORE') ? esc_html(admin_url('do.php').'?vdp-key='.urlencode($download_key)) : esc_html(get_bloginfo('url').'/?vdp-key='.urlencode($download_key))
		);

		if (!empty($jsonp_callback)) echo $jsonp_callback.'('.json_encode($return_data).')';
		else echo json_encode($return_data);
		exit;
	}

	function remote_init() {
		global $wpdb;
		if (isset($_REQUEST['callback'])) {
			header("Content-type: text/javascript");
			$jsonp_callback = $_REQUEST['callback'];
		} else $jsonp_callback = null;
		
		$return_data = array();
		$return_data['status'] = 'OK';
		$return_data['forms'] = array();
		$return_data['css'][] = plugins_url('/css/style.css', __FILE__).'?ver='.VDP_VERSION;

		$xd = false;
		if (array_key_exists('hostname', $_REQUEST)) {
			if (strtolower($_REQUEST['hostname']) != strtolower($_SERVER['SERVER_NAME'])) $xd = true;
			else {
				if (array_key_exists('HTTP_REFERER', $_SERVER) && !empty($_SERVER['HTTP_REFERER'])) {
					$ref_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
					if ($ref_host !== false && strtolower($ref_host) != strtolower($_SERVER['SERVER_NAME'])) $xd = true;
				}
			}
		} else $xd = true;
		
		if (array_key_exists('forms', $_REQUEST)) {
			$form_ids = explode(',', $_REQUEST['forms']);
			if (!empty($form_ids) && is_array($form_ids)) {
				$form_ids = array_unique($form_ids);
				foreach ($form_ids as $form_id) {
					$form_id = intval($form_id);
					$return_data['forms'][$form_id] = $this->shortcode_handler(array('id' => $form_id, 'xd' => $xd));
				}
			}
		}
		if (!empty($jsonp_callback)) echo $jsonp_callback.'('.json_encode($return_data).')';
		else echo json_encode($return_data);
		exit;
	}

	function wpml_parse_file_id($_file_id, $_default_all_value = '', $_current_language = '') {
		$file_id = $_file_id;
		$files = array('all' => $_default_all_value);
		$pairs = explode(',', $_file_id);
		foreach($pairs as $pair) {
			$data = explode(':', $pair);
			if (sizeof($data) != 2) $files['all'] = $data[0];
			else $files[$data[0]] = $data[1];
		}
		if (!defined('ICL_LANGUAGE_CODE')) $file_id = $files['all'];
		else {
			if (!empty($_current_language) && array_key_exists($_current_language, $files)) $file_id = $files[$_current_language];
			else if (array_key_exists(ICL_LANGUAGE_CODE, $files)) $file_id = $files[ICL_LANGUAGE_CODE];
			else $file_id = $files['all'];
		}
		return $file_id;
	}
	
	function wpml_compile_file_id($_file_id, $_old) {
		$new = $_file_id;
		if (defined('ICL_LANGUAGE_CODE')) {
			if (ICL_LANGUAGE_CODE == 'all') {
				$new = $_file_id;
			} else {
				$files = array();
				$pairs = explode(',', $_old);
				foreach($pairs as $pair) {
					$data = explode(':', $pair);
					if (sizeof($data) != 2) $files['all'] = $data[0];
					else $files[$data[0]] = $data[1];
				}
				$files[ICL_LANGUAGE_CODE] = $_file_id;
				$data = array();
				foreach ($files as $key => $value) {
					$data[] = $key.':'.$value;
				}
				$new = implode(',', $data);
			}
		}
		return $new;
	}

	function admin_modal_html() {
		return '
<div class="vdp-modal-overlay"></div>
<div class="vdp-modal">
	<div class="vdp-modal-content">
		<div class="vdp-modal-message"></div>
		<div class="vdp-modal-buttons">
			<a class="vdp-modal-button" id="vdp-modal-button-ok" href="#" onclick="return false;"><i class="vdp-fa vdp-fa-ok"></i><label></label></a>
			<a class="vdp-modal-button" id="vdp-modal-button-cancel" href="#" onclick="return false;"><i class="vdp-fa vdp-fa-cancel"></i><label></label></a>
		</div>
	</div>
</div>';
	}

	function page_switcher ($_urlbase, $_currentpage, $_totalpages) {
		$pageswitcher = "";
		if ($_totalpages > 1) {
			$pageswitcher = '<div class="vdp-table-list-pages"><span>';
			if (strpos($_urlbase, "?") !== false) $_urlbase .= "&";
			else $_urlbase .= "?";
			if ($_currentpage == 1) $pageswitcher .= "<a href='#' class='vdp-table-list-page-active' onclick='return false'>1</a> ";
			else $pageswitcher .= " <a href='".$_urlbase."p=1'>1</a> ";

			$start = max($_currentpage-3, 2);
			$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
			$start = max(min($start,$end-6), 2);
			if ($start > 2) $pageswitcher .= " <strong>...</strong> ";
			for ($i=$start; $i<=$end; $i++) {
				if ($_currentpage == $i) $pageswitcher .= " <a href='#' class='vdp-table-list-page-active' onclick='return false'>".$i."</a> ";
				else $pageswitcher .= " <a href='".$_urlbase."p=".$i."'>".$i."</a> ";
			}
			if ($end < $_totalpages-1) $pageswitcher .= " <strong>...</strong> ";

			if ($_currentpage == $_totalpages) $pageswitcher .= " <a href='#' class='vdp-table-list-page-active' onclick='return false'>".$_totalpages."</a> ";
			else $pageswitcher .= " <a href='".$_urlbase."p=".$_totalpages."'>".$_totalpages."</a> ";
			$pageswitcher .= "</span></div>";
		}
		return $pageswitcher;
	}
	
	function get_filename($_path, $_filename) {
		$filename = preg_replace('/[^a-zA-Z0-9\s\-\.\_]/', ' ', $_filename);
		$filename = preg_replace('/(\s\s)+/', ' ', $filename);
		$filename = trim($filename);
		$filename = preg_replace('/\s+/', '-', $filename);
		$filename = preg_replace('/\-+/', '-', $filename);
		if (strlen($filename) == 0) $filename = "file";
		else if ($filename[0] == ".") $filename = "file".$filename;
		while (file_exists($_path.$filename)) {
			$pos = strrpos($filename, ".");
			if ($pos !== false) {
				$ext = substr($filename, $pos);
				$filename = substr($filename, 0, $pos);
			} else {
				$ext = "";
			}
			$pos = strrpos($filename, "-");
			if ($pos !== false) {
				$suffix = substr($filename, $pos+1);
				if (ctype_digit($suffix)) {
					$suffix++;
					$filename = substr($filename, 0, $pos)."-".$suffix.$ext;
				} else {
					$filename = $filename."-1".$ext;
				}
			} else {
				$filename = $filename."-1".$ext;
			}
		}
		return $filename;
	}

	function unixtime_string($_time, $_format = "Y-m-d H:i") {
		return date($_format, $_time+3600*$this->gmt_offset);
	}
	
	function random_string($_length = 16) {
		$symbols = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$string = "";
		for ($i=0; $i<$_length; $i++) {
			$string .= $symbols[rand(0, strlen($symbols)-1)];
		}
		return $string;
	}
	
	function handle_demo_mode() {
		if (defined('HALFDATA_DEMO') && HALFDATA_DEMO === true && !defined('UAP_CORE') && is_user_logged_in() && !current_user_can('edit_posts') && is_admin()) {
			$this->demo_mode = true;
		} else if (defined('HALFDATA_DEMO') && HALFDATA_DEMO === true && defined('UAP_CORE')) {
			$this->demo_mode = true;
		}
	}

	function widgets_init() {
		include_once(dirname(__FILE__).'/widget.php');
		register_widget('vdp_widget');
	}

	function uuid_v4() {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
	}	
	
	function connect($_api_key, $_path, $_data = array(), $_method = '') {
		$headers = array(
			'Content-Type: application/json;charset=UTF-8',
			'Accept: application/json',
			'Authorization: Bearer '.$_api_key
		);
		try {
			$url = 'https://api.envato.com/'.ltrim($_path, '/');
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			if (!empty($_data)) {
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($_data));
			}
			if (!empty($_method)) {
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $_method);
			}
			curl_setopt($curl, CURLOPT_TIMEOUT, 20);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36');
			$response = curl_exec($curl);
			curl_close($curl);
			$result = json_decode($response, true);
		} catch (Exception $e) {
			$result = false;
		}
		return $result;
	}
}
global $vdp;
$vdp = new vdp_class();
?>