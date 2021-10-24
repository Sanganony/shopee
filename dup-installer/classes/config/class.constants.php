<?php
defined('ABSPATH') || defined('DUPXABSPATH') || exit;

require_once($GLOBALS['DUPX_INIT'].'/classes/class.s3.func.php');

/**
 * Class used to group all global constants
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\Constants
 *
 */
class DUPX_MultisiteMode
{
    const SingleSite  = -1;
    const Standalone   = 0;
    const Subdomain    = 1;
    const Subdirectory = 2;

}

class DUPX_Constants
{
	/**
	 * Init method used to auto initialize the global params
	 *
	 * @return null
	 */
	public static function init()
	{
		$dup_installer_dir_absolute_path = dirname(dirname(dirname(__FILE__)));
		$config_files = glob($dup_installer_dir_absolute_path.'/dup-archive__*.txt');
		$config_file_absolute_path = array_pop($config_files);
		$config_file_name = basename($config_file_absolute_path, '.txt');
		$archive_prefix_length = strlen('dup-archive__');
		$GLOBALS['PACKAGE_HASH'] = substr($config_file_name, $archive_prefix_length);

		$GLOBALS['BOOTLOADER_NAME'] = isset($_POST['bootloader'])  ? DUPX_U::sanitize_text_field($_POST['bootloader']) : 'installer.php' ;
        $GLOBALS['FW_PACKAGE_PATH'] = isset($_POST['archive'])     ? DUPX_U::sanitize_text_field($_POST['archive']) : null; // '%fwrite_package_name%';
        $GLOBALS['FW_ENCODED_PACKAGE_PATH'] = urlencode($GLOBALS['FW_PACKAGE_PATH']);
        $GLOBALS['FW_PACKAGE_NAME'] = basename($GLOBALS['FW_PACKAGE_PATH']);

		$GLOBALS['FAQ_URL'] = 'https://snapcreek.com/duplicator/docs/faqs-tech';

		//DATABASE SETUP: all time in seconds
		//max_allowed_packet: max value 1073741824 (1268MB) see my.ini
		$GLOBALS['DB_MAX_TIME'] = 5000;
		$GLOBALS['DB_MAX_PACKETS'] = 268435456;
		$GLOBALS['DBCHARSET_DEFAULT'] = 'utf8';
		$GLOBALS['DBCOLLATE_DEFAULT'] = 'utf8_general_ci';
		$GLOBALS['DB_RENAME_PREFIX'] = 'x-bak-' . @date("dHis") . '__';
		$GLOBALS['DB_INSTALL_MULTI_THREADED_MAX_RETRIES'] = 3;

        if (!defined('MAX_SITES_TO_DEFAULT_ENABLE_CORSS_SEARCH')) {
            define('MAX_SITES_TO_DEFAULT_ENABLE_CORSS_SEARCH',  10);
        }

		//UPDATE TABLE SETTINGS
		$GLOBALS['REPLACE_LIST'] = array();
		$GLOBALS['DEBUG_JS'] = false;

		//PHP INI SETUP: all time in seconds
		if (!isset($GLOBALS['DUPX_ENFORCE_PHP_INI']) || !$GLOBALS['DUPX_ENFORCE_PHP_INI']) {
			if (DupProSnapLibUtil::wp_is_ini_value_changeable('mysql.connect_timeout'))
				@ini_set('mysql.connect_timeout', '5000');
			if (DupProSnapLibUtil::wp_is_ini_value_changeable('memory_limit'))
				@ini_set('memory_limit', DUPLICATOR_PHP_MAX_MEMORY);
			if (DupProSnapLibUtil::wp_is_ini_value_changeable('max_execution_time'))
				@ini_set("max_execution_time", '5000');
			if (DupProSnapLibUtil::wp_is_ini_value_changeable('max_input_time'))
				@ini_set("max_input_time", '5000');
			if (DupProSnapLibUtil::wp_is_ini_value_changeable('default_socket_timeout'))
				@ini_set('default_socket_timeout', '5000');
			@set_time_limit(0);
		}

		//CONSTANTS
		define("DUPLICATOR_PRO_INIT", 1);
		if (!defined("DUPLICATOR_PRO_SSDIR_NAME"))  define("DUPLICATOR_PRO_SSDIR_NAME", 'wp-snapshots-dup-pro');  //This should match DUPLICATOR_PRO_SSDIR_NAME in duplicator.php

		//SHARED POST PARMS
		$_GET['debug'] = isset($_GET['debug']) ? true : false;
		$_GET['basic'] = isset($_GET['basic']) ? true : false;
		$_POST['view'] = isset($_POST['view']) ? DUPX_U::sanitize_text_field($_POST['view']) : "secure";

		//GLOBALS
        $GLOBALS["VIEW"]                 = isset($_GET["view"]) ? DUPX_U::sanitize_text_field($_GET["view"]) : DUPX_U::sanitize_text_field($_POST["view"]);
        $GLOBALS['INIT']                 = ($GLOBALS['VIEW'] === 'secure');
        $GLOBALS['LOG_FILE_NAME']        = 'dup-installer-log__'.$GLOBALS['PACKAGE_HASH'].'.txt';
        $GLOBALS['SEPERATOR1']           = str_repeat("********", 10);
        $GLOBALS['LOGGING']              = isset($_POST['logging']) ? DUPX_U::sanitize_text_field($_POST['logging']) : 1;
        $GLOBALS['CURRENT_ROOT_PATH']    = str_replace('\\', '/', realpath(dirname(__FILE__)."/../../../"));
        $GLOBALS['LOG_FILE_PATH']        = $GLOBALS['DUPX_INIT'].'/'.$GLOBALS["LOG_FILE_NAME"];
        $GLOBALS["NOTICES_FILE_NAME"]    = "dup-installer-notices__{$GLOBALS['PACKAGE_HASH']}.json";
        $GLOBALS["NOTICES_FILE_PATH"]    = $GLOBALS['DUPX_INIT'].'/'.$GLOBALS["NOTICES_FILE_NAME"];
        $GLOBALS["CHUNK_DATA_FILE_NAME"] = "dup-installer-chunk__{$GLOBALS['PACKAGE_HASH']}.json";
        $GLOBALS["CHUNK_DATA_FILE_PATH"] = $GLOBALS['DUPX_INIT'].'/'.$GLOBALS["CHUNK_DATA_FILE_NAME"];
        $GLOBALS['CHOWN_ROOT_PATH']      = @chmod("{$GLOBALS['CURRENT_ROOT_PATH']}", 0755);
        $GLOBALS['CHOWN_LOG_PATH']       = @chmod("{$GLOBALS['LOG_FILE_PATH']}", 0644);
        $GLOBALS['CHOWN_NOTICES_PATH']   = @chmod("{$GLOBALS['NOTICES_FILE_PATH']}", 0644);
        $GLOBALS['URL_SSL']              = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on') ? true : false;
        $GLOBALS['URL_PATH']             = ($GLOBALS['URL_SSL']) ? "https://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}" : "http://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}";
        $GLOBALS['PHP_MEMORY_LIMIT']     = ini_get('memory_limit') === false ? 'n/a' : ini_get('memory_limit');
        $GLOBALS['PHP_SUHOSIN_ON']       = extension_loaded('suhosin') ? 'enabled' : 'disabled';
        $GLOBALS['DISPLAY_MAX_OBJECTS_FAILED_TO_SET_PERM'] = 5;
        $GLOBALS['DATABASE_PAGE_SIZE'] = 3500;
        $GLOBALS['CHUNK_MAX_TIMEOUT_TIME'] = 122; // 2MIN + 2sec = 122sec


		// Displaying notice for slow zip chunk extraction
		$GLOBALS['ZIP_ARC_CHUNK_EXTRACT_DISP_NOTICE_AFTER'] = 5 * 60 * 60; // 5 minutes
		$GLOBALS['ZIP_ARC_CHUNK_EXTRACT_DISP_NOTICE_MIN_EXPECTED_EXTRACT_TIME'] = 10 * 60 * 60; // 10 minutes
		$GLOBALS['ZIP_ARC_CHUNK_EXTRACT_DISP_NEXT_NOTICE_INTERVAL'] = 5 * 60 * 60; // 5 minutes

		$additional_msg = ' for additional details <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-015-q" target="_blank">click here</a>.';
		$GLOBALS['ZIP_ARC_CHUNK_EXTRACT_NOTICES'] = array(
			'This server looks to be under load or throttled, the extraction process may take some time',
			'This host is currently experiencing very slow I/O. You can continue to wait or try a manual extraction.',
			'This host I/O is currently having issues. It is recommended to try a manual extraction.',
		);
		foreach($GLOBALS['ZIP_ARC_CHUNK_EXTRACT_NOTICES'] as $key => $val) {
			$GLOBALS['ZIP_ARC_CHUNK_EXTRACT_NOTICES'][$key] = $val.$additional_msg;
		}

        /**
         * Inizialize notices manager and load file
         */
        $noticesManager = DUPX_NOTICE_MANAGER::getInstance();

		//Restart log if user starts from step 1
        if($GLOBALS["VIEW"] == "step1" && !isset($_POST['archive_engine'])){
            $GLOBALS['LOG_FILE_HANDLE'] = @fopen($GLOBALS['LOG_FILE_PATH'], "w+");
            $noticesManager->resetNotices();
            DUPX_S3_Funcs::resetData();
        }else{
            $GLOBALS['LOG_FILE_HANDLE'] = @fopen($GLOBALS['LOG_FILE_PATH'], "a+");
        }

		$GLOBALS['FW_USECDN'] = false;
		$GLOBALS['HOST_NAME'] = strlen($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];

        if (!defined('MAX_STRLEN_SERIALIZED_CHECK')) { define('MAX_STRLEN_SERIALIZED_CHECK', 2000000); }
	}
}

DUPX_Constants::init();
