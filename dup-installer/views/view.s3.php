<?php
defined("DUPXABSPATH") or die("");

require_once($GLOBALS['DUPX_INIT'].'/classes/class.s3.func.php');

/** IDE HELPERS */
/* @var $GLOBALS['DUPX_AC'] DUPX_ArchiveConfig */

	//-- START OF VIEW STEP 3
	$_POST['dbaction']		= isset($_POST['dbaction']) ? DUPX_U::sanitize_text_field($_POST['dbaction']) : 'create';
	
	if (isset($_POST['dbhost'])) {
		$post_db_host = DUPX_U::sanitize_text_field($_POST['dbhost']);
		$_POST['dbhost'] = trim($post_db_host);
	} else {
		$_POST['dbhost'] = null;
	}
	
	if (isset($_POST['dbname'])) {
		$post_db_name = DUPX_U::sanitize_text_field($_POST['dbname']);
		$_POST['dbname'] = isset($_POST['dbname']) ? trim($post_db_name) : null;
	} else {
		$_POST['dbname'] = null;
	}
	
	if (isset($_POST['dbuser'])) {
		$post_db_user = DUPX_U::sanitize_text_field($_POST['dbuser']);
		$_POST['dbuser'] = trim($post_db_user);
	} else {
		$_POST['dbuser'] = null;
	}
	
	if (isset($_POST['dbpass'])) {
		$_POST['dbpass'] = trim($_POST['dbpass']);
	} else {
		$_POST['dbpass'] = null;
	}
	
	$_POST['dbport']		= isset($_POST['dbhost']) ? parse_url($_POST['dbhost'], PHP_URL_PORT) : 3306;
	$_POST['dbport']		= (! empty($_POST['dbport'])) ? DUPX_U::sanitize_text_field($_POST['dbport']) : 3306;

	$_POST['subsite_id']	= isset($_POST['subsite_id']) ? intval($_POST['subsite_id']) : -1;
    $_POST['remove_redundant'] = (isset($_POST['remove_redundant'])) ? DUPX_U::sanitize_text_field($_POST['remove_redundant']) : 0;
	$_POST['exe_safe_mode']	= isset($_POST['exe_safe_mode']) ? DUPX_U::sanitize_text_field($_POST['exe_safe_mode']) : 0;

	$dbh = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass'], $_POST['dbname'], $_POST['dbport']);

	$subsite_id = intval($_POST['subsite_id']);
    if ($subsite_id > 0) {
		foreach ($GLOBALS['DUPX_AC']->subsites as $ac_subsite) {
			if ($subsite_id == $ac_subsite->id) {
				$subsite = $ac_subsite;
				break;
			}
		}
        if (property_exists($subsite, 'blogname')) {
            $blogname = $subsite->blogname;
        } else {
            $blogname = $GLOBALS['DUPX_AC']->blogname;
        }
    } else {
        $blogname = $GLOBALS['DUPX_AC']->blogname;
    }

	$all_tables     = DUPX_DB::getTables($dbh);
	$active_plugins = DUPX_U::getActivePlugins($dbh, $subsite_id);
	$old_path = $GLOBALS['DUPX_AC']->wproot;

	// RSR TODO: need to do the path too?
	$new_path = $GLOBALS['DUPX_ROOT'];
	$new_path = ((strrpos($old_path, '/') + 1) == strlen($old_path)) ? DUPX_U::addSlash($new_path) : $new_path;
	$empty_schedule_display = (DUPX_U::$on_php_53_plus) ? 'table-row' : 'none';
    $is_network_install = $subsite_id < 1 && $GLOBALS['DUPX_AC']->mu_mode > 0;
    $is_subdomain = $GLOBALS['DUPX_AC']->mu_mode === 1;
    if($is_network_install){
        $subsites = $GLOBALS['DUPX_AC']->subsites;
        if(!$is_subdomain){
            $subsites = DUPX_U::urlForSubdirectoryMode($subsites,$GLOBALS['DUPX_AC']->url_old);
        }
        $subsites = DUPX_U::appendProtocol($subsites);
        $main_url = $subsites[0]->name;
    }
?>

<!-- =========================================
VIEW: STEP 3- INPUT -->
<form id='s3-input-form' method="post" class="content-form">

	<div class="logfile-link">
		<?php DUPX_View_Funcs::installerLogLink(); ?>
	</div>
	<div class="hdr-main">
		Step <span class="step">3</span> of 4: Update Data
	</div>

	<?php
		if ($_POST['dbaction'] == 'manual') {
			echo '<div class="dupx-notice s3-manaual-msg">Manual SQL execution is enabled</div>';
		}

        $actionParams = array(
            'ctrl_action' => 'ctrl-step3',
            'ctrl_csrf_token' => DUPX_CSRF::generate('ctrl-step3'),
            'view' => 'step3',
            'csrf_token' => DUPX_CSRF::generate('step3'),
            'secure-pass' => $_POST['secure-pass'],
            'bootloader' => $GLOBALS['BOOTLOADER_NAME'],
            'archive' => $GLOBALS['FW_PACKAGE_PATH'],
            'logging' => $_POST['logging']
        );
	?>

	<!--  POST PARAMS -->
	<div class="dupx-debug">
		<i>Step 3 - Page Load</i>
		<input type="hidden" name="ctrl_action"	    value="<?php echo DUPX_U::esc_attr($actionParams['ctrl_action']); ?>" />
		<input type="hidden" name="ctrl_csrf_token" value="<?php echo DUPX_U::esc_attr($actionParams['ctrl_csrf_token']); ?>">
		<input type="hidden" name="view"		    value="<?php echo DUPX_U::esc_attr($actionParams['view']); ?>" />
		<input type="hidden" name="csrf_token"      value="<?php echo DUPX_U::esc_attr($actionParams['csrf_token']); ?>">
		<input type="hidden" name="secure-pass"     value="<?php echo DUPX_U::esc_attr($actionParams['secure-pass']); ?>" />
		<input type="hidden" name="bootloader"      value="<?php echo DUPX_U::esc_attr($actionParams['bootloader']); ?>" />
		<input type="hidden" name="archive"         value="<?php echo DUPX_U::esc_attr($actionParams['archive']); ?>" />
		<input type="hidden" name="logging"		    value="<?php echo DUPX_U::esc_attr($actionParams['logging']); ?>" />
		<input type="hidden" name="dbhost"		  value="<?php echo DUPX_U::esc_attr($_POST['dbhost']); ?>" />
		<input type="hidden" name="dbuser" 		  value="<?php echo DUPX_U::esc_attr($_POST['dbuser']); ?>" />
		<input type="hidden" name="dbpass" 		  value="<?php echo DUPX_U::esc_attr($_POST['dbpass']); ?>" />
		<input type="hidden" name="dbname" 		  value="<?php echo DUPX_U::esc_attr($_POST['dbname']); ?>" />
		<input type="hidden" name="dbcharset" 	  value="<?php echo DUPX_U::esc_attr($_POST['dbcharset']); ?>" />
		<input type="hidden" name="dbcollate" 	  value="<?php echo DUPX_U::esc_attr($_POST['dbcollate']); ?>" />
		<input type="hidden" name="retain_config" value="<?php echo DUPX_U::esc_attr($_POST['retain_config']); ?>" />
		<input type="hidden" name="exe_safe_mode" id="exe-safe-mode" value="<?php echo DUPX_U::esc_attr($_POST['exe_safe_mode']); ?>" />
		<input type="hidden" name="subsite_id"    id="subsite-id" value="<?php echo intval($_POST['subsite_id']); ?>" />
        <input type="hidden" name="remove_redundant" id="remove-redundant" value="<?php echo DUPX_U::esc_attr($_POST['remove_redundant']); ?>" />
        <input type="hidden" name="json"		  value="<?php echo DUPX_U::esc_attr($_POST['json']); ?>" />
	</div>

	<div class="hdr-sub1 toggle-hdr" data-type="toggle" data-target="#s3-new-settings">
        <a href="javascript:void(0)"><i class="fa fa-minus-square"></i>New Settings</a>
    </div>
    <div id="s3-new-settings">
        <table class="s3-opts">
            <tr id="new-url-container">
                <td>URL:</td>
                <td>
                    <input type="text" name="url_new" id="url_new" class="sync_url_new" value="" />
                    <a href="javascript:DUPX.getNewURL('url_new')" style="font-size:12px">get</a>
                </td>
            </tr>
            <tr>
                <td>Path:</td>
                <td><input type="text" name="path_new" id="path_new" value="<?php echo DUPX_U::esc_attr($new_path); ?>" /></td>
            </tr>
            <tr>
                <td>Title:</td>
                <td><input type="text" name="blogname" id="blogname" value="<?php echo DUPX_U::esc_attr($blogname); ?>" /></td>
            </tr>
            <?php if($is_network_install):?>
            <tr>
                <td>Replace Mode</td>
                <td>
                    <input type="radio" id="replace-legacy-mode" name="replace_mode" value="legacy" checked="checked">
                    <label for="replace-legacy-mode">Standard</label>
                    <input type="radio" id="replace-mapping-mode" name="replace_mode" value="mapping">
                    <label for="replace-mapping-mode">Mapping</label>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <br/><br/>
    <?php if($is_network_install):?>
    <div id="subsite-map-container" style="display: none;">
        <div class="hdr-sub1 toggle-hdr" data-type="toggle" data-target="#s3-subsite-mapping">
            <a href="javascript:void(0)"><i class="fa fa-minus-square"></i>Subsite Mapping</a>
        </div>
        <div id="s3-subsite-mapping">
            <table class="s3-opts">
                <tr>
                    <td>URLs:</td>
                    <td>
                        <?php
						foreach ($subsites as $subsite):
							$isMainSite = ($subsite->id == $GLOBALS['DUPX_AC']->main_site_id);
                            $title = ($isMainSite ? 'Main' : 'Sub').' site: '.$subsite->blogname;
                        ?>
                            <div class="site-item <?php echo $isMainSite ? 'main_site' : 'sub_site'; ?>" title="<?php echo DUPX_U::esc_attr($title); ?>" >
                                <input style="width: 42%!important;"
                                       type="text"
                                       name="mu_search[<?php echo intval($subsite->id); ?>]"
                                       id="url_old_<?php echo intval($subsite->id); ?>"
                                       value="<?php echo $subsite->name ?>"
                                       readonly="readonly"
                                       class="mu_search readonly"
                                       />
                                to
								<?php
								$url_new = DUPX_U::getDefaultURL($subsite->name,$main_url,$is_subdomain);
								?>
                                <input style="width: 42%!important;" type="text" 
                                    name="mu_replace[<?php echo intval($subsite->id); ?>]"
                                    id="url_new_<?php echo intval($subsite->id); ?>"
									value="<?php echo DUPX_U::esc_attr($url_new); ?>"
                                    class="mu_replace <?php echo $isMainSite ? ' sync_url_new' : ''; ?>"
                                />
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>
        <br/><br/>
    </div>
    <?php endif; ?>

    <!-- =========================
    SEARCH AND REPLACE -->
    <div class="hdr-sub1 toggle-hdr" data-type="toggle" data-target="#s3-custom-replace">
        <a href="javascript:void(0)"><i class="fa fa-plus-square"></i>Replace</a>
    </div>

    <div id="s3-custom-replace" style="display:none;">
        <div class="help-target">
            <?php DUPX_View_Funcs::helpIconLink('step3'); ?>
        </div><br/>

        <table class="s3-opts" id="search-replace-table">
            <tr valign="top" id="search-0">
                <td>Search:</td>
                <td><input type="text" name="search[]" style="margin-right:5px"></td>
            </tr>
            <tr valign="top" id="replace-0"><td>Replace:</td><td><input type="text" name="replace[]"></td></tr>
        </table>
        <button type="button" onclick="DUPX.addSearchReplace();return false;" style="font-size:12px;display: block; margin: 10px 0 0 0; " class="default-btn">Add More</button>
    </div>

    <br/><br/>

	<!-- ==========================
    OPTIONS -->
	<div class="hdr-sub1 toggle-hdr" data-type="toggle" data-target="#s3-adv-opts">
		<a href="javascript:void(0)"><i class="fa fa-plus-square"></i>Options</a>
	</div>
	<!-- START TABS -->
    <div id="s3-adv-opts" style="display:none;">
	<div id="tabs">
		<ul>
			<li><a href="#tabs-admin-account">Admin Account</a></li>
			<li><a href="#tabs-scan-options">Scan Options</a></li>
			<li><a href="#tabs-wp-config-file">WP-Config File</a></li>
		</ul>

		<!-- =====================
		ADMIN TAB -->
		<div id="tabs-admin-account">
			<div class="help-target">
				<?php DUPX_View_Funcs::helpIconLink('step3'); ?>
			</div><br/>

			<!-- NEW ADMIN ACCOUNT -->
			<div class="hdr-sub3">New Admin Account</div>
			<div style="text-align: center">
				<i style="color:gray;font-size: 11px">This feature is optional.  If the username already exists the account will NOT be created or updated.</i>
				<?php
					if($GLOBALS['DUPX_AC']->mu_mode > 0 && $subsite_id == -1){
						echo '<br><i style="color:gray;font-size: 11px">You will create Network Administrator account</i>';
					}
				?>
			</div>

			<table class="s3-opts" style="margin-top:7px">
				<tr>
					<td>Username:</td>
					<td><input type="text" name="wp_username" id="wp_username" value="" title="4 characters minimum" placeholder="(4 or more characters)" /></td>
				</tr>
				<tr>
					<td>Password:</td>
					<td><input type="text" name="wp_password" id="wp_password" value="" title="6 characters minimum"  placeholder="(6 or more characters)" /></td>
				</tr>
				<tr>
					<td>Mail:</td>
					<td><input type="text" name="wp_mail" id="wp_mail" value="" title=""  placeholder="" /></td>
				</tr>
				<tr>
					<td>Nickname:</td>
					<td><input type="text" name="wp_nickname" id="wp_nickname" value="" title="if username is empty"  placeholder="(if username is empty)" /></td>
				</tr>
				<tr>
					<td>First name:</td>
					<td><input type="text" name="wp_first_name" id="wp_first_name" value="" title="optional"  placeholder="(optional)" /></td>
				</tr>
				<tr>
					<td>Last name:</td>
					<td><input type="text" name="wp_last_name" id="wp_last_name" value="" title="optional"  placeholder="(optional)" /></td>
				</tr>
			</table>
		</div>

		<!-- =====================
		SCAN TAB -->
		<div id="tabs-scan-options">
			<div class="help-target">
				<?php DUPX_View_Funcs::helpIconLink('step3'); ?>
			</div><br/>
			<div class="hdr-sub3">Database Scan Options</div>
			<table  class="s3-opts">
                <tr>
					<td><label for="mode_chunking" style="font-weight: bold">Replace Mode:</label></td>
					<td>
                        <select id="mode_chunking" name="mode_chunking" size="2">
                            <option value="<?php echo DUPX_S3_Funcs::MODE_NORMAL; ?>" selected>Normal</option>
                            <option value="<?php echo DUPX_S3_Funcs::MODE_CHUNK; ?>">Chunking mode</option>
                            <?php 
                                // Prepared but not yet implemented
                                // <option value="<?php echo DUPX_S3_Funcs::MODE_SKIP; ? >">Skip mode</option>
                            ?>
                        </select>
					</td>
				</tr>
				<tr style="display: <?php echo $empty_schedule_display; ?>">
					<td>Cleanup:</td>
					<td>
						<input type="checkbox" name="empty_schedule_storage" id="empty_schedule_storage" value="1" checked />
						<label for="empty_schedule_storage" style="font-weight: normal">Remove schedules and storage endpoints</label>
					</td>
				</tr>
				<tr>
					<td style="width:105px">Site URL:</td>
					<td style="white-space: nowrap">
						<input type="text" name="siteurl" id="siteurl" value="" />
						<a href="javascript:DUPX.getNewURL('siteurl')" style="font-size:12px">get</a><br/>
					</td>
				</tr>
				<tr valign="top">
					<td style="width:80px">Old URL:</td>
					<td>
						<input type="text" name="url_old" id="url_old" value="<?php echo DUPX_U::esc_attr($GLOBALS['DUPX_AC']->url_old); ?>" readonly="readonly"  class="readonly" />
						<a href="javascript:DUPX.editOldURL()" id="edit_url_old" style="font-size:12px">edit</a>
					</td>
				</tr>
				<tr valign="top">
					<td>Old Path:</td>
					<td>
						<input type="text" name="path_old" id="path_old" value="<?php echo DUPX_U::esc_attr($old_path); ?>" readonly="readonly"  class="readonly" />
						<a href="javascript:DUPX.editOldPath()" id="edit_path_old" style="font-size:12px">edit</a>
					</td>
				</tr>
			</table><br/>

			<table>
				<tr>
					<td style="padding-right:10px">
						<b>Scan Tables:</b>
						<div class="s3-allnonelinks">
							<a href="javascript:void(0)" onclick="$('#tables option').prop('selected',true);">[All]</a>
							<a href="javascript:void(0)" onclick="$('#tables option').prop('selected',false);">[None]</a>
						</div><br style="clear:both" />
						<select id="tables" name="tables[]" multiple="multiple" style="width:315px; height:100px">
							<?php
							$need_to_check_scan_table = false;
							if ($GLOBALS['DUPX_AC']->mu_generation > 0
									&& $GLOBALS['DUPX_AC']->mu_mode > 0
									&& $subsite_id > 0) {
								$subsite_table_prefix = $GLOBALS['DUPX_AC']->wp_tableprefix.$subsite_id.'_';
								$prefix_len = strlen($GLOBALS['DUPX_AC']->wp_tableprefix);
								
								$need_to_check_scan_table = true;
							}
							foreach( $all_tables as $table ) {
								if ($need_to_check_scan_table) {
									$table_len = strlen($table);
									$table_without_base_prefix = substr($table, $prefix_len, $table_len);
									$table_subsite_id = intval($table_without_base_prefix);
									if (($subsite_id != $GLOBALS['DUPX_AC']->main_site_id && 0 === stripos($table, $subsite_table_prefix)) 
										|| 
										0 === $table_subsite_id) {
										echo '<option selected="selected" value="' . DUPX_U::esc_attr($table) . '">' . DUPX_U::esc_html($table) . '</option>';
									}
								} else {
									echo '<option selected="selected" value="' . DUPX_U::esc_attr($table) . '">' . DUPX_U::esc_html($table) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td valign="top">
						<b>Activate<?php echo ((($GLOBALS['DUPX_AC']->mu_mode > 0) && ($subsite_id == -1)) ? ' Network ' : ' ')?>Plugins:</b>
						<?php echo ($_POST['exe_safe_mode'] > 0) ? '<small class="s3-warn">Safe Mode Enabled</small>' : '' ; ?>
						<div class="s3-allnonelinks" style="<?php echo ($_POST['exe_safe_mode']>0)? 'display:none':''; ?>">
							<a href="javascript:void(0)" onclick="$('#plugins option').prop('selected',true);">[All]</a>
							<a href="javascript:void(0)" onclick="$('#plugins option').prop('selected',false);">[None]</a>
						</div><br style="clear:both" />
						<select id="plugins" name="plugins[]" multiple="multiple" style="width:315px; height:100px" <?php echo ($_POST['exe_safe_mode'] > 0) ? 'disabled="true"' : ''; ?>>
							<?php
							$exclude_plugins = array(
								'really-simple-ssl/rlrsssl-really-simple-ssl.php',
								'simple-google-recaptcha/simple-google-recaptcha.php',
							);
							$selected_string = ($_POST['exe_safe_mode'] > 0 || $subsite_id > 0) ? '' : 'selected="selected"';
							foreach ($active_plugins as $plugin) {
								$label = dirname($plugin) == '.' ? $plugin : dirname($plugin);
								if (in_array($plugin, $exclude_plugins)) {
									echo "<option value='".DUPX_U::esc_attr($plugin)."'>".DUPX_U::esc_html($label).'</option>';
								} else {
									echo "<option {$selected_string} value='".DUPX_U::esc_attr($plugin)."'>".DUPX_U::esc_html($label).'</option>';
								}								
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<br>
			<input type="checkbox" name="search_replace_email_domain" id="search_replace_email_domain" value="1" /> <label for="search_replace_email_domain">Update email domains</label><br/>
			<input type="checkbox" name="fullsearch" id="fullsearch" value="1" /> <label for="fullsearch">Use Database Full Search Mode</label><br/>
			<input type="checkbox" name="postguid" id="postguid" value="1" /> <label for="postguid">Keep Post GUID Unchanged</label><br/>
            <?php
            if ($is_network_install) {
                $checked = (count($subsites) <= MAX_SITES_TO_DEFAULT_ENABLE_CORSS_SEARCH) ? 'checked="checked"' : '';
                ?>
                <input type="checkbox" name="cross_search" id="cross_search" value="1" <?php echo $checked; ?> />
                <label for="cross_search" style="font-weight: normal">Cross-search between the sites of the network.</label><br/>
                <?php
            }
            ?>
			<br/><br/>
		</div>

		<!-- =====================
		WP-CONFIG TAB -->
		<div id="tabs-wp-config-file">
			<div class="help-target">
				<?php DUPX_View_Funcs::helpIconLink('step3'); ?>
			</div><br/>

			<?php
				require_once($GLOBALS['DUPX_INIT'].'/lib/config/class.wp.config.tranformer.php');
				$root_path		= $GLOBALS['DUPX_ROOT'];
				$wpconfig_ark_path	= "{$root_path}/dup-wp-config-arc__{$GLOBALS['DUPX_AC']->package_hash}.txt";
                if (file_exists($wpconfig_ark_path)) {
    				$config_transformer = new WPConfigTransformer($wpconfig_ark_path);
                } else {
                    $config_transformer = null;
                }
			?>
			<table class="dupx-opts dupx-advopts">
                <?php
                if (file_exists($wpconfig_ark_path) && !is_null($config_transformer)) { ?>
				<tr><td colspan="2"><div class="hdr-sub3">Posts/Pages</div></td></tr>
				<tr>
					<td>Editor:</td>
					<td>
					<?php
						$disallow_file_edit_val = false;
						if ($config_transformer->exists('constant', 'DISALLOW_FILE_EDIT')) {
							$disallow_file_edit_val = $config_transformer->get_value('constant', 'DISALLOW_FILE_EDIT');
						}
						?>
						<input type="checkbox" id="disallow_file_edit" name="disallow_file_edit" <?php DupProSnapLibUIU::echoChecked($disallow_file_edit_val);?> value="1">
						<label for="disallow_file_edit"><?php echo DUPX_U::esc_attr('Disable the Plugin/Theme Editor'); ?></label>
					</td>
				</tr>
				<tr>
					<?php
					$autosave_interval_val = '';
                    if ($config_transformer->exists('constant', 'AUTOSAVE_INTERVAL')) {
						$autosave_interval_val = $config_transformer->get_value('constant', 'AUTOSAVE_INTERVAL');;
                    }
					?>
					<td>AutoSave Interval:</td>
					<td>
						<input type="number" name="autosave_interval" id="autosave_interval" value="<?php echo DUPX_U::esc_attr($autosave_interval_val); ?>" />
						<br>
						<small class="info">Auto-save interval in seconds (default:60) </small>
					</td>
				</tr>
				<tr>
					<?php
					$wp_post_revisions_val = '';
                    if ($config_transformer->exists('constant', 'WP_POST_REVISIONS')) {
						$wp_post_revisions_const_val = $config_transformer->get_value('constant', 'WP_POST_REVISIONS');
						if ($wp_post_revisions_const_val) {
							$wp_post_revisions_val = 'true';
						} else {
							$wp_post_revisions_val = 'false';
						}
                    }
					?>
					<td>Post Revisions:</td>
					<td>
						<select name="wp_post_revisions" id="wp_post_revisions">
							<option value="">Choose...</option>
							<option value="true" <?php echo 'true' == $wp_post_revisions_val ? 'selected ' : '';?>>Yes</option>
							<option value="false" <?php echo 'false' == $wp_post_revisions_val ? 'selected ' : '';?>>No</option>
						</select>
					</td>
				</tr>
				<tr><td colspan="2"><div class="hdr-sub3"><br/>Security</div></td></tr>
				<tr>
					<td>SSL:</td>
					<?php
					$force_ssl_admin_val = false;
					if ($config_transformer->exists('constant', 'FORCE_SSL_ADMIN')) {
						$force_ssl_admin_val = $config_transformer->get_value('constant', 'FORCE_SSL_ADMIN');
					}
					?>
					<td>
						<input type="checkbox" name="ssl_admin" id="ssl_admin" <?php DupProSnapLibUIU::echoChecked($force_ssl_admin_val);?> /> <label for="ssl_admin">Enforce on Admin</label>
					</td>
				</tr>
				<?php
				$licence_type = $GLOBALS['DUPX_AC']->getLicenseType();
				if ($licence_type >= DUPX_LicenseType::Freelancer) : ?>
					<tr>
						<td>Auth Keys:</td>
						<td>
							<input type="checkbox" name="auth_keys_and_salts" id="auth_keys_and_salts" />
							<label for="auth_keys_and_salts">Generate New Unique Authentication Keys and Salts</label>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<td>Auth Keys:</td>
						<td>
							<i>Available only in Freelancer and above</i>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td>Core Auto-Updates:</td>
					<td>
					<?php
						$wp_auto_update_core_val = false;
						if ($config_transformer->exists('constant', 'WP_AUTO_UPDATE_CORE')) {
							$wp_auto_update_core_val = $config_transformer->get_value('constant', 'WP_AUTO_UPDATE_CORE');
						}
						?>
						<select name="wp_auto_update_core" id="wp_auto_update_core">
							<option value="">Choose...</option>
							<option value="false" <?php echo 'false' == $wp_auto_update_core_val ? 'selected ' : '';?>>Disable all core updates</option>
							<option value="true" <?php echo 'true' == $wp_auto_update_core_val ? 'selected ' : '';?>>Enable all core updates</option>
							<option value="minor" <?php echo 'minor' == $wp_auto_update_core_val ? 'selected ' : '';?>>Enable only core minor updates - Default</option>
						</select>
					</td>
				</tr>					
				<tr><td colspan="2"><div class="hdr-sub3"><br/>System/General</div></td></tr>
				<tr>
					<td>Cache:</td>
					<td>
						<?php
						$wp_cache_val = false;
						if ($config_transformer->exists('constant', 'WP_CACHE')) {
							$wp_cache_val = $config_transformer->get_value('constant', 'WP_CACHE');
						}
						?>
						<input type="checkbox" name="cache_wp" id="cache_wp" <?php DupProSnapLibUIU::echoChecked($wp_cache_val);?> /> <label for="cache_wp">Keep Enabled</label>
						<br>
						<?php
						$wpcachehome_val = '';
						if ($config_transformer->exists('constant', 'WPCACHEHOME')) {
							$wpcachehome_val = $config_transformer->get_value('constant', 'WPCACHEHOME');
						}
						?>
						<input type="checkbox" name="cache_path" id="cache_path" <?php DupProSnapLibUIU::echoChecked($wpcachehome_val);?> /> <label for="cache_path">Keep Home Path</label>
					</td>
				</tr>
				<tr id="wp_post_revisions_no_cont">
					<?php
					$wp_post_revisions_no = '';
                    if (isset($wp_post_revisions_const_val) && intval($wp_post_revisions_const_val) > 0) {
						$wp_post_revisions_no = intval($wp_post_revisions_const_val);
                    }
					?>
					<td>Limit Revisions	<br />	Number:</td>
					<td><input type="number" name="wp_post_revisions_no" id="wp_post_revisions_no" value="<?php echo DUPX_U::esc_attr($wp_post_revisions_no);?>" /></td>
				</tr>
				<?php
				/* DEPRECATED
				<tr>
					<?php
					$media_trash_val = '';
                    if ($config_transformer->exists('constant', 'MEDIA_TRASH')) {
						$media_trash_val = $config_transformer->get_value('constant', 'MEDIA_TRASH');
                    }
					?>
					<td>Media Trash</td>
					<td>
						<input type="checkbox" name="media_trash" id="media_trash" <?php DupProSnapLibUIU::echoChecked($media_trash_val);?> /> <label for="media_trash">Enable trash for media</label>
					</td>
				</tr>
				*/
				?>
				<tr>
					<td>WP Debug:</td>
					<td>
						<?php
						$wp_debug_val = false;
						if ($config_transformer->exists('constant', 'WP_DEBUG')) {
							$wp_debug_val = $config_transformer->get_value('constant', 'WP_DEBUG');
						}
						?>
						<input type="checkbox" id="wp-debug" name="wp_debug" <?php DupProSnapLibUIU::echoChecked($wp_debug_val);?> value="1">
						<label for="wp-debug">Display errors and warnings</label>
					</td>
				</tr>
				<tr>
					<td>WP Debug Log:</td>
					<td>
					<?php
						$wp_debug_log_val = false;
						if ($config_transformer->exists('constant', 'WP_DEBUG_LOG')) {
							$wp_debug_log_val = $config_transformer->get_value('constant', 'WP_DEBUG_LOG');
						}
						?>
						<input type="checkbox" id="wp-debug-log" name="wp_debug_log" <?php DupProSnapLibUIU::echoChecked($wp_debug_log_val);?> value="1">
						<label for="wp-debug-log">Log errors and warnings</label>
					</td>
				</tr>
				<tr>
					<td>WP Debug Display:</td>
					<td>
					<?php
						$wp_debug_display_val = false;
						if ($config_transformer->exists('constant', 'WP_DEBUG_DISPLAY')) {
							$wp_debug_display_val = $config_transformer->get_value('constant', 'WP_DEBUG_DISPLAY');
						}
						?>
						<input type="checkbox" id="wp_debug_display" name="wp_debug_display" <?php DupProSnapLibUIU::echoChecked($wp_debug_display_val);?> value="1">
						<label for="wp_debug_display">Display errors and warnings</label>
					</td>
				</tr>
				<tr>
					<td>Script Debug:</td>
					<td>
					<?php
						$script_debug_val = false;
						if ($config_transformer->exists('constant', 'SCRIPT_DEBUG')) {
							$script_debug_val = $config_transformer->get_value('constant', 'SCRIPT_DEBUG');
						}
						?>
						<input type="checkbox" id="script_debug" name="script_debug" <?php DupProSnapLibUIU::echoChecked($script_debug_val);?> value="1">
						<label for="script_debug">JavaScript or CSS errors</label>
					</td>
				</tr>
				<tr>
					<td>Save Queries:</td>
					<td>
					<?php
						$savequeries_val = false;
						if ($config_transformer->exists('constant', 'SAVEQUERIES')) {
							$savequeries_val = $config_transformer->get_value('constant', 'SAVEQUERIES');
						}
						?>
						<input type="checkbox" id="savequeries" name="savequeries" <?php DupProSnapLibUIU::echoChecked($savequeries_val);?> value="1">
						<label for="savequeries"><?php echo DUPX_U::esc_attr('Save database queries in an array ($wpdb->queries)'); ?></label>
					</td>
				</tr>
				<tr>
					<td>Cookie Domain:</td>
					<?php
					$cookie_domain_val = '';
					if ($config_transformer->exists('constant', 'COOKIE_DOMAIN')) {
						$cookie_domain_val = $config_transformer->get_value('constant', 'COOKIE_DOMAIN');
						if (0 === strpos($cookie_domain_val, '$')) {
							$cookie_domain_val = '';
						} elseif (!empty($cookie_domain_val)) {
							$parsedUrlOld = parse_url($GLOBALS['DUPX_AC']->url_old);
							$oldDomain = $parsedUrlOld['host'];
							$newDomain = $_SERVER['HTTP_HOST'];
							$cookie_domain_val = str_ireplace($oldDomain, $newDomain, $cookie_domain_val);
						}
					}
					?>
					<td>
						<input type="text" name="cookie_domain" id="cookie_domain" value="<?php echo DUPX_U::esc_attr($cookie_domain_val); ?>" />
						<br>
						<small class="info">
							Set <a href="http://www.askapache.com/htaccess/apache-speed-subdomains.html" target="_blank">different domain</a> for cookies.subdomain.example.com
						</small>
					</td>
				</tr>
				<tr>
					<td>Memory Limit:</td>
					<td>
					<?php
						$wp_memory_limit_val = false;
						if ($config_transformer->exists('constant', 'WP_MEMORY_LIMIT')) {
							$wp_memory_limit_val = $config_transformer->get_value('constant', 'WP_MEMORY_LIMIT');
						}
						?>
						<input type="text" name="wp_memory_limit" id="wp_memory_limit" value="<?php echo DUPX_U::esc_attr($wp_memory_limit_val);?>" />
						<br>
						<small class="info">PHP memory limit (default:30M; multi-site default:64M)</small>
					</td>
				</tr>
				<tr>
					<td>Max Memory Limit:</td>
					<td>
					<?php
						$wp_max_memory_limit_val = false;
						if ($config_transformer->exists('constant', 'WP_MAX_MEMORY_LIMIT')) {
							$wp_max_memory_limit_val = $config_transformer->get_value('constant', 'WP_MAX_MEMORY_LIMIT');
						}
						?>
						<input type="text" name="wp_max_memory_limit" id="wp_max_memory_limit" value="<?php echo DUPX_U::esc_attr($wp_max_memory_limit_val);?>" />
						<br>
						<small class="info"> Maximum memory limit (default:256M)</small>
					</td>
				</tr>
            <?php } else { ?>
                <tr>
                    <td>wp-config.php not found</td>
                    <td>No action on the wp-config is possible.<br>
                        After migration, be sure to insert a properly modified wp-config for correct wordpress operation.</td>
                </tr>
            <?php } ?>
			</table>
		</div>
	</div>
	<!--  END TABS  -->
	</div>
	<br/><br/><br/><br/>

	<div class="footer-buttons">
		<button id="s3-next" type="button"  onclick="DUPX.runUpdate()" class="default-btn"> Next <i class="fa fa-caret-right"></i> </button>
	</div>
</form>

<!-- =========================================
VIEW: STEP 3 - AJAX RESULT  -->
<form id='s3-result-form' method="post" class="content-form" style="display:none">

	<div class="logfile-link"><?php DUPX_View_Funcs::installerLogLink(); ?></div>
	<div class="hdr-main">
		Step <span class="step">3</span> of 4: Update Data
	</div>

	<!--  POST PARAMS -->
	<div class="dupx-debug">
		<i>Step 3 - AJAX Response</i>
		<input type="hidden" name="view"  value="step4" />
		<input type="hidden" name="csrf_token" value="<?php echo DUPX_CSRF::generate('step4'); ?>">
		<input type="hidden" name="secure-pass" value="<?php echo DUPX_U::esc_attr($_POST['secure-pass']); ?>" />
		<input type="hidden" name="bootloader" value="<?php echo DUPX_U::esc_attr($GLOBALS['BOOTLOADER_NAME']); ?>" />
		<input type="hidden" name="archive" value="<?php echo DUPX_U::esc_attr($GLOBALS['FW_PACKAGE_PATH']); ?>" />
		<input type="hidden" name="logging" id="logging" value="<?php echo DUPX_U::esc_attr($_POST['logging']); ?>" />
		<input type="hidden" name="url_new" id="ajax-url_new"  />
		<input type="hidden" name="exe_safe_mode" id="ajax-exe-safe-mode" />
		<input type="hidden" name="subsite_id" id="ajax-subsite-id" />
        <input type="hidden" name="remove_redundant" id="ajax-remove-redundant"/>
		<input type="hidden" name="json"    id="ajax-json" />
		<input type='submit' value='manual submit'>
	</div>

	<!--  PROGRESS BAR -->
	<div id="progress-area">
		<div style="width:500px; margin:auto">
            <div class="progress-text"><i class="fas fa-circle-notch fa-spin"></i> Processing Data Replacement <span class="progress-perc">0%</span></div>
			<div id="progress-bar"></div>
			<h3> Please Wait...</h3><br/><br/>
			<i>Keep this window open during the replacement process.</i><br/>
			<i>This can take several minutes.</i>
		</div>
	</div>

	<!--  AJAX SYSTEM ERROR -->
	<div id="ajaxerr-area" style="display:none">
		<p>Please try again an issue has occurred.</p>
		<div style="padding: 0px 10px 10px 10px;">
			<div id="ajaxerr-data">
                <div class="content" >
                    An unknown issue has occurred with the update setup step.  Please see the <?php DUPX_View_Funcs::installerLogLink(); ?> file for more details.
                </div>
                <div class="troubleshooting" >
                    <b>Additional Troubleshooting Tips:</b><br/>
                    - Check the <?php DUPX_View_Funcs::installerLogLink(); ?> file for warnings or errors.<br/>
                    - Check the web server and PHP error logs. <br/>
                    - For timeout issues visit the <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-100-q" target="_blank">Timeout FAQ Section</a>
                </div>
            </div>
			<div style="text-align:center; margin:10px auto 0px auto">
			<?php
			$archive_config = DUPX_ArchiveConfig::getInstance();
			?>
				<input type="button" onclick='<?php 
				if (0 == $archive_config->mu_mode) { ?>
					DUPX.hideErrorResult2();
				<?php } else { ?>
					window.history.back();
				<?php } ?>' value="&laquo; Try Again"  class="default-btn" /><br/><br/>
				<i style='font-size:11px'>See online help for more details at <a href='https://snapcreek.com' target='_blank'>snapcreek.com</a></i>
			</div>
		</div>
	</div>
</form>

<script>
/** 
* Timeout (10000000 = 166 minutes) */
DUPX.runUpdate = function()
{
	//Validation
	var wp_username = $.trim($("#wp_username").val()).length || 0;
	var wp_password = $.trim($("#wp_password").val()).length || 0;
    var wp_mail = $.trim($("#wp_mail").val()).length || 0;

	if ( $.trim($("#url_new").val()) == "" )  {alert("The 'New URL' field is required!"); return false;}
	if ( $.trim($("#siteurl").val()) == "" )  {alert("The 'Site URL' field is required!"); return false;}

     if (wp_username >= 1) {
        if (wp_username < 4) {
            alert("The New Admin Account 'Username' must be four or more characters");
            return false;
        } else if (wp_password < 6) {
            alert("The New Admin Account 'Password' must be six or more characters");
            return false;
        } else if (wp_mail === 0) {
            alert("The New Admin Account 'mail' is required");
            return false;
        }
    }

	var nonHttp = false;
	var failureText = '';

	/* IMPORTANT - not trimming the value for good - just in the check */
	$('input[name="search[]"]').each(function() {
		var val = $(this).val();

		if(val.trim() != "") {
			if(val.length < 3) {
				failureText = "Custom search fields must be at least three characters.";
			}

			if(val.toLowerCase().indexOf('http') != 0) {
				nonHttp = true;
			}
		}
	});

	$('input[name="replace[]"]').each(function() {
		var val = $(this).val();
		if(val.trim() != "") {
			// Replace fields can be anything
			if(val.toLowerCase().indexOf('http') != 0) {
				nonHttp = true;
			}
		}
	});

	if(failureText != '') {
		alert(failureText);
		return false;
	}

	if(nonHttp) {
		if(confirm('One or more custom search and replace strings are not URLs.  Are you sure you want to continue?') == false) {
			return false;
		}
	}

    if($('input[type=radio][name=replace_mode]:checked').val() == 'mapping'){
        $("#new-url-container").remove();
    }else if($('input[type=radio][name=replace_mode]:checked').val() == 'legacy') {
        $("#subsite-map-container").remove();
    }

    DUPX.ajaxRequest();
};

var lastChunkPosition = null;

DUPX.checkDataResponseData = function(respData, textStatus , xHr) {
    try {
        var data = $.parseJSON(respData);
    } catch(err) {
        console.error(err);
        console.error('JSON parse failed for response data: ' + respData);
        var status  = "<b>Server Code:</b> "	+ xHr.status		+ "<br/>";
        status += "<b>Status:</b> "			+ xHr.statusText	+ "<br/>";
        status += "<b>Response:</b> "		+ xHr.responseText  + "<hr/>";
        status += "Json response not well formatted<br>";
        $('#ajaxerr-data .content').html(status);
        DUPX.hideProgressBar();
        return false;
    }

    if (typeof(data) != 'undefined') {
        if (data.step3.chunk == 1) {
            if (JSON.stringify(lastChunkPosition) !== JSON.stringify(data.step3.chunkPos)) {
                var lastChunkPosition =  data.step3.chunkPos;
                $('.progress-perc').text(data.step3.progress_perc + '%');
                // if chunk recover the request
                DUPX.ajaxRequest(true);
            } else {
                console.error('Chunk is stuck: ' + respData);
                var status  = "<b>Server Code:</b> "	+ xHr.status		+ "<br/>";
                status += "<b>Status:</b> "			+ xHr.statusText	+ "<br/>";
                status += "<b>Response:</b> "		+ xHr.responseText  + "<hr/>";
                status += "Chunkink is stuck<br>";
                $('#ajaxerr-data .content').html(status);
                DUPX.hideProgressBar();
                return false;
            }
        } else if (data.step3.pass == 1) {
            if ($('input[name=replace_mode]:checked').val() === 'mapping') {
                $("#ajax-url_new").val($("#url_new_1").val());
            } else {
                $("#ajax-url_new").val($("#url_new").val());
            }
            $("#ajax-subsite-id").val($("#subsite-id").val());
            $("#ajax-remove-redundant").val($("#remove-redundant").val());
            $("#ajax-exe-safe-mode").val($("#exe-safe-mode").val());
            $("#ajax-json").val(escape(JSON.stringify(data)));
            <?php if (! $GLOBALS['DUPX_DEBUG']) : ?>
                setTimeout(function(){$('#s3-result-form').submit();}, 1000);
            <?php endif; ?>
            $('#progress-area').fadeOut(1800);
        } else  {
            DUPX.hideProgressBar();
        }
    } else {
        DUPX.hideProgressBar();
    }
};

DUPX.ajaxRequest = function(chunk) {
    chunk = chunk || false;

    if (chunk) {
        var data = <?php echo DupProSnapLibUtil::wp_json_encode($actionParams); ?>;
    } else {
        var data = $('#s3-input-form').serialize();
    }

    let params = {
		type: "POST",
		timeout: 10000000,
		url: window.location.href,
		data: data,
		cache: false,
		success: function(respData, textStatus , xhr){
            DUPX.checkDataResponseData(respData, textStatus , xhr);
		},
		error: function(xhr , textStatus, errorThrown) {
            try {
                console.log('xhr', xhr);
                console.log('textStatus',textStatus);
                DUPX.checkDataResponseData(xhr.responseText, textStatus , xhr);
            } catch(err) {
                var status  = "<b>Server Code:</b> "	+ xhr.status		+ "<br/>";
                status += "<b>Status:</b> "			+ xhr.statusText	+ "<br/>";
                status += "<b>Response:</b> "		+ xhr.responseText  + "<hr/>";
                status += "Ajax response error<br>";
                $('#ajaxerr-data .content').html(status);
                DUPX.hideProgressBar();
            }
		}
	};

    if (chunk === false) {
        params.beforeSend = function() {
			DUPX.showProgressBar();
			$('#s3-input-form').hide();
			$('#s3-result-form').show();
		};
    }

    $.ajax(params);
};

/**
 * Returns the windows active url */
DUPX.getNewURL = function(id)
{
	var filename = window.location.pathname.split('/').pop() || 'main.installer.php' ;
	var newVal	 = window.location.href.split("?")[0];
	newVal = newVal.replace("/" + filename, '');
	var last_slash = newVal.lastIndexOf("/");
	newVal = newVal.substring(0, last_slash);
	$("#" + id).val(newVal).keyup();
};

/**
 * Allows user to edit the package url  */
DUPX.editOldURL = function()
{
	var msg = 'This is the URL that was generated when the package was created.\n';
	msg += 'Changing this value may cause issues with the install process.\n\n';
	msg += 'Only modify  this value if you know exactly what the value should be.\n';
	msg += 'See "General Settings" in the WordPress Administrator for more details.\n\n';
	msg += 'Are you sure you want to continue?';

	if (confirm(msg)) {
		$("#url_old").removeAttr('readonly');
		$("#url_old").removeClass('readonly');
		$('#edit_url_old').hide('slow');
	}
};

/**
 * Allows user to edit the package path  */
DUPX.editOldPath = function()
{
	var msg = 'This is the SERVER URL that was generated when the package was created.\n';
	msg += 'Changing this value may cause issues with the install process.\n\n';
	msg += 'Only modify  this value if you know exactly what the value should be.\n';
	msg += 'Are you sure you want to continue?';

	if (confirm(msg)) {
		$("#path_old").removeAttr('readonly');
		$("#path_old").removeClass('readonly');
		$('#edit_path_old').hide('slow');
	}
};

var searchReplaceIndex = 1;

/**
 * Adds a search and replace line         */
DUPX.addSearchReplace = function()
{
	$("#search-replace-table").append("<tr valign='top' id='search-" + searchReplaceIndex + "'>" +
		"<td style='width:80px;padding-top:20px'>Search:</td>" +
		"<td style='padding-top:20px'>" +
			"<input type='text' name='search[]' style='margin-right:5px' />" +
			"<a href='javascript:DUPX.removeSearchReplace(" + searchReplaceIndex + ")'><i class='fa fa-minus-circle'></i></a>" +
		"</td>" +
	  "</tr>" +
			  "<tr valign='top' id='replace-" + searchReplaceIndex + "'>" +
		"<td>Replace:</td>" +
		"<td>" +
			"<input type='text' name='replace[]' />" +
		"</td>" +
	  "</tr> ");

	searchReplaceIndex++;
};

/**
 * Removes a search and replace line      */
DUPX.removeSearchReplace = function(index)
{
	$("#search-" + index).remove();
	$("#replace-" + index).remove();
};

/**
 * Go back on AJAX result view */
DUPX.hideErrorResult2 = function()
{
	$('#s3-result-form').hide();
	$('#s3-input-form').show(200);
};

DUPX.showHideRevisionNo = function() {
	var cont = $('#wp_post_revisions_no_cont');
	if ('true' == $('#wp_post_revisions').val()) {
		cont.show();
	} else {
		cont.hide();
	}
};

//DOCUMENT LOAD
$(document).ready(function() {
	$("#tabs").tabs();
	DUPX.showHideRevisionNo();
	$('#wp_post_revisions').change(DUPX.showHideRevisionNo);
	DUPX.getNewURL('url_new');
	DUPX.getNewURL('siteurl');
	$("*[data-type='toggle']").click(DUPX.toggleClick);
	$("#wp_password").passStrength({
			shortPass: 		"top_shortPass",
			badPass:		"top_badPass",
			goodPass:		"top_goodPass",
			strongPass:		"top_strongPass",
			baseStyle:		"top_testresult",
			userid:			"#wp_username",
			messageloc:		1
	});

    $('input[type=radio][name=replace_mode]').change(function() {
        if (this.value == 'mapping') {
            $("#subsite-map-container").show();
            $("#new-url-container").hide();
        }
        else if (this.value == 'legacy') {
            $("#new-url-container").show();
            $("#subsite-map-container").hide();
        }
    });

    // Sync new urls link
    var inputs_new_urls = $(".sync_url_new");
    inputs_new_urls.keyup(function() {
          inputs_new_urls.val($(this).val());
    });
});
</script>
