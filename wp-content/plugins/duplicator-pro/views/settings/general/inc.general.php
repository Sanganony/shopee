<?php
defined("ABSPATH") or die("");

$global  = DUP_PRO_Global_Entity::get_instance();

$nonce_action = 'duppro-settings-general-edit';
$action_updated = null;
$action_response = DUP_PRO_U::__("General settings updated.");

//SAVE RESULTS
if (isset($_REQUEST['action'])) {

    check_admin_referer($nonce_action);
	DUP_PRO_U::initStorageDirectory();

	switch ($_REQUEST['action']) {

		case 'save':
			$global->uninstall_settings = isset($_POST['uninstall_settings']) ? 1 : 0;
			$global->uninstall_packages= isset($_POST['uninstall_packages']) ? 1 : 0;
			$global->wpfront_integrate = isset($_REQUEST['_wpfront_integrate']) ? 1 : 0;
			$global->debug_on			= isset($_REQUEST['_debug_on']) ? 1 : 0;
			$global->trace_profiler_on  = isset($_REQUEST['_trace_profiler_on']) ? 1 : 0;
		break;
	
		case 'trace':
			$trace_direction = $_REQUEST['_logging_mode'] == 'on' ? 'on' : 'off';
			$action_response .= ' &nbsp; ' . DUP_PRO_U::__("Trace settings have been turned {$trace_direction}.");
		break;
	}

    switch ($_REQUEST['_logging_mode']) {

		case 'off':
			update_option('duplicator_pro_trace_log_enabled', false);
			update_option('duplicator_pro_send_trace_to_error_log', false);
			break;

		case 'on':
			if ((bool) get_option('duplicator_pro_trace_log_enabled') == false) {
				DUP_PRO_LOG::deleteTraceLog();
			}
			update_option('duplicator_pro_trace_log_enabled', true);
			update_option('duplicator_pro_send_trace_to_error_log', false);
			break;

		case 'enhanced':
			if (((bool) get_option('duplicator_pro_trace_log_enabled') == false) || ((bool) get_option('duplicator_pro_send_trace_to_error_log') == false)) {
				DUP_PRO_LOG::deleteTraceLog();
			}

			update_option('duplicator_pro_trace_log_enabled', true);
			update_option('duplicator_pro_send_trace_to_error_log', true);
			break;
	}

	$action_updated = $global->save();
	$global->adjust_settings_for_system();	
}

$trace_log_enabled = (bool)get_option('duplicator_pro_trace_log_enabled');
$send_trace_to_error_log = (bool)get_option('duplicator_pro_send_trace_to_error_log');

if ($trace_log_enabled) {
	$logging_mode = ($send_trace_to_error_log) ?  'enhanced' : 'on';
} else {
	$logging_mode = 'off';
}

$wpfront_ready = apply_filters('wpfront_user_role_editor_duplicator_integration_ready', false);
?>

<style>
	td.profiles p.description {margin:5px 0 20px 25px; font-size:11px}
</style>

<form id="dup-settings-form" action="<?php echo self_admin_url('admin.php?page=' . DUP_PRO_Constants::$SETTINGS_SUBMENU_SLUG); ?>" method="post" data-parsley-validate>
<?php wp_nonce_field($nonce_action); ?>
<input type="hidden" id="dup-settings-action" name="action" value="save">
<input type="hidden" name="page" value="<?php echo DUP_PRO_Constants::$SETTINGS_SUBMENU_SLUG ?>">
<input type="hidden" name="tab" value="general">
<input type="hidden" name="subtab" value="general">

<?php if ($action_updated) : ?>
	<div class="notice notice-success is-dismissible dpro-wpnotice-box"><p><?php echo $action_response; ?></p></div>
<?php endif;

$duplicator_pro_settings_message = get_transient('duplicator_pro_settings_message');
if ($duplicator_pro_settings_message) {
?>
	<div class="notice notice-success is-dismissible dpro-wpnotice-box"><p><?php echo esc_html($duplicator_pro_settings_message); ?></p></div>
<?php
	delete_transient('duplicator_pro_settings_message');
}
?>

<!-- ===============================
PLUG-IN SETTINGS -->
<h3 class="title"><?php DUP_PRO_U::esc_html_e("Plugin") ?> </h3>
<hr size="1" />
<table class="form-table">       
	<tr valign="top">
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Version"); ?></label></th>
		<td><?php echo DUPLICATOR_PRO_VERSION ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Uninstall"); ?></label></th>
		<td>
			<input type="checkbox" name="uninstall_settings" id="uninstall_settings" <?php echo DUP_PRO_UI::echoChecked($global->uninstall_settings); ?> /> 
			<label for="uninstall_settings"><?php DUP_PRO_U::esc_html_e("Delete Plugin Settings"); ?> </label><br/>

			<input type="checkbox" name="uninstall_packages" id="uninstall_packages" <?php echo DUP_PRO_UI::echoChecked($global->uninstall_packages); ?> /> 
			<label for="uninstall_packages"><?php DUP_PRO_U::esc_html_e("Delete Entire Storage Directory"); ?></label><br/>

		</td>
	</tr>
	<tr>
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Custom Roles"); ?></label></th>
		<td>
			<input type="checkbox" name="_wpfront_integrate" id="_wpfront_integrate" <?php echo DUP_PRO_UI::echoChecked($global->wpfront_integrate); ?> <?php echo $wpfront_ready ? '' : 'disabled'; ?> />
			<label for="_wpfront_integrate"><?php DUP_PRO_U::esc_html_e("Enable User Role Editor Plugin Integration"); ?></label>
			<p class="description">
				<?php
				printf('%s <a href="https://wordpress.org/plugins/wpfront-user-role-editor/" target="_blank">%s</a> %s'
						. ' <a href="https://wpfront.com/lifeinthegrid" target="_blank">%s</a> %s '
						. ' <a href="https://wpfront.com/integrations/duplicator-integration/" target="_blank">%s</a>', DUP_PRO_U::__('The User Role Editor Plugin'), DUP_PRO_U::__('Free'), DUP_PRO_U::__('or'), DUP_PRO_U::__('Professional'), DUP_PRO_U::__('must be installed to use'), DUP_PRO_U::__('this feature.')
				);
				?>
			</p>
		</td>
	</tr>
</table><br/>

<!-- ===============================
DEBUG SETTINGS -->
<h3 class="title"><?php DUP_PRO_U::esc_html_e('Debug') ?> </h3>
<hr size="1" />

<table class="form-table">	
	<tr>
		<th scope="row"><label><?php echo DUP_PRO_U::__("Trace Log"); ?></label></th>
		<td>
			<select name="_logging_mode">
				<option value="off" <?php DUP_PRO_UI::echoSelected($logging_mode == 'off'); ?> ><?php DUP_PRO_U::esc_html_e('Off');?></option>
				<option value="on"  <?php DUP_PRO_UI::echoSelected($logging_mode == 'on'); ?>><?php DUP_PRO_U::esc_html_e('On');?></option>
				<option value="enhanced"  <?php DUP_PRO_UI::echoSelected($logging_mode == 'enhanced'); ?>><?php DUP_PRO_U::esc_html_e('On (Enhanced)');?></option>
			</select>
			<p class="description">
			<?php
				DUP_PRO_U::esc_html_e("Turning on log initially clears it out. The enhanced setting writes to both trace and PHP error logs.");
				echo "<br/>";
				DUP_PRO_U::esc_html_e("WARNING: Only turn on this setting when asked to by support as tracing will impact performance.");
			?>
			</p><br/>
			<button class="button" <?php DUP_PRO_UI::echoDisabled(DUP_PRO_LOG::traceFileExists() === false); ?> onclick="DupPro.Pack.DownloadTraceLog(); return false">
				<i class="fa fa-download"></i> <?php echo DUP_PRO_U::__('Download Trace Log') . ' (' . DUP_PRO_LOG::getTraceStatus() . ')'; ?>
			</button>
		</td>
	</tr>
	<tr>
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Debugging"); ?></label></th>
		<td>
			<input type="checkbox" name="_debug_on" id="_debug_on" <?php echo DUP_PRO_UI::echoChecked($global->debug_on); ?> />
			<label for="_debug_on"><?php DUP_PRO_U::esc_html_e("Enable debug options throughout plugin"); ?></label>
			 <p class="description"><?php DUP_PRO_U::esc_html_e('Refresh page after saving to show/hide Debug menu.'); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Profiler"); ?></label></th>
		<td>
			<input type="checkbox" name="_trace_profiler_on" id="_trace_profiler_on" <?php echo DUP_PRO_UI::echoChecked($global->trace_profiler_on); ?> />
			<label for="_trace_profiler_on"><?php DUP_PRO_U::esc_html_e("Enable Performance Stats"); ?></label> <br/>

			<p class="description">
				<?php
				DUP_PRO_U::esc_html_e("Trace log must be 'On' to view profile report");
				?>
			</p>
		</td>
	</tr>
</table><br/>

<!-- ===============================
ADVANCED SETTINGS -->
<h3 class="title"><?php DUP_PRO_U::esc_html_e('Advanced') ?> </h3>
<hr size="1" />
<table class="form-table">
	<tr>
		<th scope="row"><label><?php DUP_PRO_U::esc_html_e("Settings"); ?></label></th>
		<td>
			<button class="button"  onclick="DupPro.Pack.ConfirmResetAll(); return false">
				<i class="fas fa-redo fa-sm"></i> <?php echo DUP_PRO_U::__('Reset All'); ?>
			</button>
			<p class="description">
				<?php DUP_PRO_U::esc_html_e("Reset all settings to their defaults."); ?>
				<i class="fas fa-question-circle fa-sm"
					data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("Reset Settings"); ?>"
					data-tooltip="<?php DUP_PRO_U::esc_attr_e('Resets standard settings to defaults. Does not affect license key, storage or schedules.'); ?>"></i>
			</p>
		</td>
	</tr>
</table>

<p class="submit" style="margin:5px 0px 0xp 5px;">
	<input type="submit" name="submit" id="submit" class="button-primary" value="<?php DUP_PRO_U::esc_attr_e('Save General Settings') ?>" style="display: inline-block;" />
</p>
</form>

<?php
    $confirm1 = new DUP_PRO_UI_Dialog();
    $confirm1->title			= DUP_PRO_U::__('Reset Settings?');
    $confirm1->message			= DUP_PRO_U::__('Are you sure you want to reset settings to defaults?');
    $confirm1->progressText     = DUP_PRO_U::__('Resetting settings, Please Wait...');
    $confirm1->jsCallback		= 'DupPro.Pack.ResetAll()';
    $confirm1->okText           = DUP_PRO_U::__('Yes');
    $confirm1->cancelText       = DUP_PRO_U::__('No');
    $confirm1->initConfirm();
?>

<script>
jQuery(document).ready(function ($) {

	// which: 0=installer, 1=archive, 2=sql file, 3=log
	DupPro.Pack.DownloadTraceLog = function () {
		var actionLocation = ajaxurl + '?action=duplicator_pro_get_trace_log&nonce='+'<?php echo wp_create_nonce('duplicator_pro_get_trace_log'); ?>';
		location.href = actionLocation;
	};

	DupPro.Pack.ConfirmResetAll = function ()
	{
		<?php $confirm1->showConfirm(); ?>
	};

	DupPro.Pack.ResetAll = function ()
	{
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: 'duplicator_pro_reset_user_settings',
				nonce: '<?php echo wp_create_nonce('duplicator_pro_reset_user_settings'); ?>'
			},
			success: function (respData) {
				try {
					var data = DupPro.parseJSON(respData);
				} catch(err) {
					console.error(err);
					console.error('JSON parse failed for response data: ' + respData);
					var data = respData;
				}
				DupPro.ReloadWindow(data);
			}
		});
	};

	//Init
	$( "#_trace_log_enabled" ).click(function()
	{
		$('#_send_trace_to_error_log').attr('disabled', ! $(this).is(':checked'));
	});

});
</script>
