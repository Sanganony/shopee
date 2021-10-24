<?php
defined("DUPXABSPATH") or die("");
//-- START OF ACTION STEP 2
/** IDE HELPERS */
/* @var $GLOBALS['DUPX_AC'] DUPX_ArchiveConfig */

require_once($GLOBALS['DUPX_INIT'] . '/api/class.cpnl.ctrl.php');

//BASIC
if ($_POST['view_mode'] == 'basic') {
	$_POST['dbaction']		= isset($_POST['dbaction']) ? DUPX_U::sanitize_text_field($_POST['dbaction']) : 'create';

	if (isset($_POST['dbhost'])) {
		$post_db_host = DUPX_U::sanitize_text_field($_POST['dbhost']);
		$_POST['dbhost'] = trim($post_db_host);
	} else {
		$_POST['dbhost'] = null;
	}
	
	if (isset($_POST['dbname'])) {
		$post_db_name = DUPX_U::sanitize_text_field($_POST['dbname']);
		$_POST['dbname'] = trim($post_db_name);
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
	

	if (isset($_POST['dbhost'])) {
		$post_db_host = DUPX_U::sanitize_text_field($_POST['dbhost']);
		$_POST['dbport'] = parse_url($post_db_host, PHP_URL_PORT);
	} else {
		$_POST['dbport'] = 3306;
	}

	$_POST['dbport']		= (!empty($_POST['dbport'])) ? DUPX_U::sanitize_text_field($_POST['dbport']) : 3306;
	$_POST['dbnbsp']		= (isset($_POST['dbnbsp']) && $_POST['dbnbsp'] == '1') ? true : false;
	
	if (isset($_POST['dbcharset'])) {
		$post_db_charset = DUPX_U::sanitize_text_field($_POST['dbcharset']);
		$_POST['dbcharset'] = trim($_POST['dbcharset']);
	} else {
		$_POST['dbcharset'] = $GLOBALS['DBCHARSET_DEFAULT'];
	}
	
	$_POST['dbcollate']		= isset($_POST['dbcollate']) ? DUPX_U::sanitize_text_field(trim($_POST['dbcollate'])) : $GLOBALS['DBCOLLATE_DEFAULT'];
	$_POST['dbcollatefb']	= (isset($_POST['dbcollatefb']) && $_POST['dbcollatefb'] == '1') ? true : false;
	$_POST['dbchunk']		= (isset($_POST['dbchunk']) && $_POST['dbchunk'] == '1') ? true : false;
	$_POST['dbobj_views']	= isset($_POST['dbobj_views']) ? true : false; 
	$_POST['dbobj_procs']	= isset($_POST['dbobj_procs']) ? true : false;
}
//CPANEL
else {
	$_POST['dbaction']	= isset($_POST['cpnl-dbaction']) ? DUPX_U::sanitize_text_field($_POST['cpnl-dbaction']) : 'create';
	$_POST['dbhost']	= isset($_POST['cpnl-dbhost']) ? DUPX_U::sanitize_text_field(trim($_POST['cpnl-dbhost'])) : null;
	$_POST['dbname']	= isset($_POST['cpnl-dbname-result']) ? DUPX_U::sanitize_text_field(trim($_POST['cpnl-dbname-result'])) : null;
	$_POST['dbuser']	= isset($_POST['cpnl-dbuser-result']) ? DUPX_U::sanitize_text_field(trim($_POST['cpnl-dbuser-result'])) : null;
	$_POST['dbpass']	= isset($_POST['cpnl-dbpass']) ? trim($_POST['cpnl-dbpass']) : null;
	$_POST['dbport']	= isset($_POST['cpnl-dbhost']) ? parse_url($_POST['cpnl-dbhost'], PHP_URL_PORT) : 3306;
	$_POST['dbport']	= (!empty($_POST['cpnl-dbport'])) ? DUPX_U::sanitize_text_field($_POST['cpnl-dbport']) : 3306;
	$_POST['dbnbsp']	= (isset($_POST['cpnl-dbnbsp']) && $_POST['cpnl-dbnbsp'] == '1') ? true : false;
	$_POST['dbmysqlmode']		= DUPX_U::sanitize_text_field($_POST['cpnl-dbmysqlmode']);
	$_POST['dbmysqlmode_opts']	= DUPX_U::sanitize_text_field($_POST['cpnl-dbmysqlmode_opts']);
	$_POST['dbcharset']			= isset($_POST['cpnl-dbcharset']) ? DUPX_U::sanitize_text_field(trim($_POST['cpnl-dbcharset'])) : $GLOBALS['DBCHARSET_DEFAULT'];
	$_POST['dbcollate']			= isset($_POST['cpnl-dbcollate']) ? DUPX_U::sanitize_text_field(trim($_POST['cpnl-dbcollate'])) : $GLOBALS['DBCOLLATE_DEFAULT'];
	$_POST['dbcollatefb']		= (isset($_POST['cpnl-dbcollatefb']) && $_POST['cpnl-dbcollatefb'] == '1') ? true : false;
	$_POST['dbchunk']			= (isset($_POST['cpnl-dbchunk']) && $_POST['cpnl-dbchunk'] == '1') ? true : false;
	$_POST['dbobj_views']		= isset($_POST['cpnl-dbobj_views']) ? true : false;
	$_POST['dbobj_procs']		= isset($_POST['cpnl-dbobj_procs']) ? true : false;
}

$_POST['cpnl-dbuser-chk'] = (isset($_POST['cpnl-dbuser-chk']) && $_POST['cpnl-dbuser-chk'] == '1') ? true : false;
$_POST['cpnl-host']		  = isset($_POST['cpnl-host']) ? DUPX_U::sanitize_text_field($_POST['cpnl-host']) : '';
$_POST['cpnl-user']		  = isset($_POST['cpnl-user']) ? DUPX_U::sanitize_text_field($_POST['cpnl-user']) : '';
$_POST['cpnl-pass']		  = isset($_POST['cpnl-pass']) ? trim(DUPX_U::wp_unslash($_POST['cpnl-pass'])) : '';

$ajax2_start	 = DUPX_U::getMicrotime();
$root_path		 = $GLOBALS['DUPX_ROOT'];
$JSON			 = array();
$JSON['pass']	 = 0;

/**
JSON RESPONSE: Most sites have warnings turned off by default, but if they're turned on the warnings
cause errors in the JSON data Here we hide the status so warning level is reset at it at the end */
$ajax2_error_level = error_reporting();
error_reporting(E_ERROR);
($GLOBALS['LOG_FILE_HANDLE'] != false) or DUPX_Log::error(ERR_MAKELOG);


//===============================================
//DB TEST & ERRORS: From Postback
//===============================================
//INPUTS
$dbTestIn			 = new DUPX_DBTestIn();
$dbTestIn->mode		 = DUPX_U::sanitize_text_field($_POST['view_mode']);
$dbTestIn->dbaction	 = DUPX_U::sanitize_text_field($_POST['dbaction']);
$dbTestIn->dbhost	 = DUPX_U::sanitize_text_field($_POST['dbhost']);
$dbTestIn->dbuser	 = DUPX_U::sanitize_text_field($_POST['dbuser']);
$dbTestIn->dbpass	 = trim($_POST['dbpass']);
$dbTestIn->dbname	 = DUPX_U::sanitize_text_field($_POST['dbname']);
$dbTestIn->dbport	 = DUPX_U::sanitize_text_field($_POST['dbport']);
$dbTestIn->dbcollatefb = DUPX_U::sanitize_text_field($_POST['dbcollatefb']);
$dbTestIn->cpnlHost  = DUPX_U::sanitize_text_field($_POST['cpnl-host']);
$dbTestIn->cpnlUser  = DUPX_U::sanitize_text_field($_POST['cpnl-user']);
$dbTestIn->cpnlPass  = trim(DUPX_U::wp_unslash($_POST['cpnl-pass']));
$dbTestIn->cpnlNewUser = DUPX_U::sanitize_text_field($_POST['cpnl-dbuser-chk']);

$dbTest	= new DUPX_DBTest($dbTestIn);

//CLICKS 'Test Database'
if (isset($_GET['dbtest'])) {
	$dbTest->runMode = 'TEST';
	$dbTest->responseMode = 'JSON';
	if (!headers_sent()) {
		header('Content-Type: application/json');
	}
	die($dbTest->run());
	
//CLICKS 'Next' 
} else {

	//@todo: 
	// - Convert DUPX_DBTest to DUPX_DBSetup
	// - implement property runMode = "Test/Live"
	// - This should replace the cpnl code block below
	/*
	$dbSetup->runMode = 'LIVE';
	$dbSetup->responseMode = 'PHP';
	$dbSetupResult = $dbSetup->run();

	if (! $dbSetupResult->payload->reqsPass) {
		$errorMessage = $dbTestResult->payload->lastError;
		DUPX_Log::error(empty($errorMessage) ? 'UNKNOWN ERROR RESPONSE:  Please try the process again!' : $errorMessage);
	}*/
}

//===============================================
//CPANEL LOGIC: From Postback
//===============================================
$cpnllog = "";
if ($_POST['view_mode'] == 'cpnl') {
	try {
		$cpnllog	  ="--------------------------------------\n";
		$cpnllog	 .="CPANEL API\n";
		$cpnllog	 .="--------------------------------------\n";

		$CPNL		 = new DUPX_cPanel_Controller();

		$post_cpnl_host = DUPX_U::sanitize_text_field($_POST['cpnl-host']);
		$post_cpnl_user = DUPX_U::sanitize_text_field($_POST['cpnl-user']);
		$post_cpnl_pass = trim(DUPX_U::wp_unslash($_POST['cpnl-pass']));

		$cpnlToken	 = $CPNL->create_token($post_cpnl_host, $post_cpnl_user, $post_cpnl_pass);
		$cpnlHost	 = $CPNL->connect($cpnlToken);
		
		//CREATE DB USER: Attempt to create user should happen first in the case that the
		//user passwords requirements are not met.
		if ($_POST['cpnl-dbuser-chk']) {
			$post_db_user = DUPX_U::sanitize_text_field($_POST['dbuser']);
			$post_db_pass = trim($_POST['dbpass']);
			$result = $CPNL->create_db_user($cpnlToken, $post_db_user, $post_db_pass);
			if ($result['status'] !== true) {
				DUPX_Log::info('CPANEL API ERROR: create_db_user ' . print_r($result['cpnl_api'], true), 2);
				DUPX_Log::error(sprintf(ERR_CPNL_API, $result['status']));
			} else {
				$cpnllog .= "- A new database user was created\n";
			}
		}

		$post_db_name = DUPX_U::sanitize_text_field($_POST['dbname']);
		//CREATE NEW DB
		if ($_POST['dbaction'] == 'create') {
			$result = $CPNL->create_db($cpnlToken, $post_db_name);
			if ($result['status'] !== true) {
				DUPX_Log::info('CPANEL API ERROR: create_db '.print_r($result['cpnl_api'], true), 2);
				DUPX_Log::error(sprintf(ERR_CPNL_API, $result['status']));
			} else {
				$cpnllog .= "- A new database was created\n";
			}
		} else {
			$cpnllog .= "- Used to connect to existing database named [{$post_db_name}]\n";
		}

		$post_db_user = DUPX_U::sanitize_text_field($_POST['dbuser']);
		//ASSIGN USER TO DB IF NOT ASSIGNED
		$result = $CPNL->is_user_in_db($cpnlToken, $post_db_name, $post_db_user);
		if (!$result['status']) {
			$result		 = $CPNL->assign_db_user($cpnlToken, $post_db_name, $post_db_user);
			if ($result['status'] !== true) {
				DUPX_Log::info('CPANEL API ERROR: assign_db_user '.print_r($result['cpnl_api'], true), 2);
				DUPX_Log::error(sprintf(ERR_CPNL_API, $result['status']));
			} else {
				$cpnllog .= "- Database user was assigned to database";
			}
		}
	} catch (Exception $ex) {
		DUPX_Log::error($ex);
	}
}

$not_yet_logged = (isset($_POST['first_chunk']) && $_POST['first_chunk']) || (!isset($_POST['continue_chunking']));

if($not_yet_logged){
    $labelPadSize = 20;
    
    DUPX_Log::info("\n\n\n********************************************************************************");
    DUPX_Log::info('* DUPLICATOR PRO INSTALL-LOG');
    DUPX_Log::info('* STEP-2 START @ '.@date('h:i:s'));
    DUPX_Log::info('* NOTICE: Do NOT post to public sites or forums!!');
    DUPX_Log::info("********************************************************************************");
    DUPX_Log::info("USER INPUTS");
    DUPX_Log::info(str_pad('VIEW MODE', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['view_mode']));
    DUPX_Log::info(str_pad('DB ACTION', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbaction']));
    DUPX_Log::info(str_pad('DB HOST', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString('**OBSCURED**'));
    DUPX_Log::info(str_pad('DB NAME', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString('**OBSCURED**'));
    DUPX_Log::info(str_pad('DB PASS', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString('**OBSCURED**'));
    DUPX_Log::info(str_pad('DB PORT', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString('**OBSCURED**'));
    DUPX_Log::info(str_pad('NON-BREAKING SPACES', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbnbsp']));
    DUPX_Log::info(str_pad('MYSQL MODE', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbmysqlmode']));
    DUPX_Log::info(str_pad('MYSQL MODE OPTS', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbmysqlmode_opts']));
    DUPX_Log::info(str_pad('CHARSET', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbcharset']));
    DUPX_Log::info(str_pad('COLLATE', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbcollate']));
    DUPX_Log::info(str_pad('COLLATE FB', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbcollatefb']));
    DUPX_Log::info(str_pad('CUNKING', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbchunk']));
    DUPX_Log::info(str_pad('VIEW CREATION', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbobj_views']));
    DUPX_Log::info(str_pad('STORED PROCEDURE', $labelPadSize, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($_POST['dbobj_procs']));
    DUPX_Log::info("********************************************************************************\n");

    if (! empty($cpnllog)) {
        DUPX_Log::info($cpnllog);
    }

    $POST_LOG = $_POST;
    unset($POST_LOG['dbpass']);
    ksort($POST_LOG);
    $log = "--------------------------------------\n";
    $log .= "POST DATA\n";
    $log .= "--------------------------------------\n";
    $log .= print_r($POST_LOG, true);
    DUPX_Log::info($log, DUPX_Log::LV_DEBUG);
    DUPX_Log::flush();
}


//===============================================
//DATABASE ROUTINES
//===============================================
$dbinstall = new DUPX_DBInstall($_POST, $ajax2_start);
if ($_POST['dbaction'] != 'manual') {
    if(!isset($_POST['continue_chunking'])){
        $dbinstall->prepareDB();
    } else if(isset($_POST['first_chunk']) && $_POST['first_chunk'] == 1) {
		$dbchunk_retry = intval($_POST['dbchunk_retry']);
		if ($dbchunk_retry > 0) {
			DUPX_Log::info("## >> Last DB Chunk installation was failed, so retrying from start point. Retrying count: ".$dbchunk_retry);
		}
		
		if (file_exists($dbinstall->sql_chunk_seek_tell_log)) {
			unlink($dbinstall->sql_chunk_seek_tell_log);
		}
		
        $dbinstall->prepareDB();
    }
}
if ($not_yet_logged) {

	//Fatal Memory errors from file_get_contents is not catchable.
	//Try to warn ahead of time with a check on buffer in memory difference
	$current_php_mem = DUPX_U::returnBytes($GLOBALS['PHP_MEMORY_LIMIT']);
	$current_php_mem = is_numeric($current_php_mem) ? $current_php_mem : null;

	if ($current_php_mem != null && $dbinstall->dbFileSize > $current_php_mem) {
		$readable_size = DUPX_U::readableByteSize($dbinstall->dbFileSize);
		$msg   = "\nWARNING: The database script is '{$readable_size}' in size.  The PHP memory allocation is set\n";
		$msg  .= "at '{$GLOBALS['PHP_MEMORY_LIMIT']}'.  There is a high possibility that the installer script will fail with\n";
		$msg  .= "a memory allocation error when trying to load the database.sql file.  It is\n";
		$msg  .= "recommended to increase the 'memory_limit' setting in the php.ini config file.\n";
		$msg  .= "see: {$faq_url}#faq-trouble-056-q \n";
		DUPX_Log::info($msg);
		unset($msg);
	}

    DUPX_Log::info("--------------------------------------");
    DUPX_Log::info("DATABASE RESULTS");
    DUPX_Log::info("--------------------------------------");
}

if ($_POST['dbaction'] == 'manual') {

	DUPX_Log::info("\n** SQL EXECUTION IS IN MANUAL MODE **");
	DUPX_Log::info("- No SQL script has been executed -");
	$JSON['pass'] = 1;
} elseif(isset($_POST['continue_chunking']) && $_POST['continue_chunking'] === 'true') {
	$ret = $dbinstall->writeInChunks();
    echo json_encode($ret);
    die();
} elseif(isset($_POST['continue_chunking']) && ($_POST['continue_chunking'] === 'false' && $_POST['pass'] == 1)) {
    $rowCountMisMatchTables = $dbinstall->getRowCountMisMatchTables();
	$JSON['pass'] = 1;
	if (!empty($rowCountMisMatchTables)) {
		$nManager = DUPX_NOTICE_MANAGER::getInstance();
		$errMsg = 'ERROR: Database Table row count verification was failed for table(s): '
									.implode(', ', $rowCountMisMatchTables).'.';
		DUPX_Log::info($errMsg);
		$nManager->addNextStepNoticeMessage($errMsg, DUPX_NOTICE_ITEM::HARD_WARNING);
		$nManager->addFinalReportNotice(array(
			'shortMsg' => 'Database Table row count validation error',
			'level' => DUPX_NOTICE_ITEM::HARD_WARNING,
			'longMsg' => $errMsg,
			'sections' => 'database'
		));
		$nManager->saveNotices();
	}
} elseif(!isset($_POST['continue_chunking'])) {
	$dbinstall->writeInDB();
	$rowCountMisMatchTables = $dbinstall->getRowCountMisMatchTables();
	$JSON['pass'] = 1;
	if (!empty($rowCountMisMatchTables)) {
		$nManager = DUPX_NOTICE_MANAGER::getInstance();
		$errMsg = 'ERROR: Database Table row count verification was failed for table(s): '
									.implode(', ', $rowCountMisMatchTables).'.';
		DUPX_Log::info($errMsg);
		$nManager->addNextStepNoticeMessage($errMsg , DUPX_NOTICE_ITEM::SOFT_WARNING);
		$nManager->addFinalReportNotice(array(
			'shortMsg' => 'Database Table row count validation error',
			'level' => DUPX_NOTICE_ITEM::HARD_WARNING,
			'longMsg' => $errMsg,
			'sections' => 'database'
		));
		$nManager->saveNotices();
	}
}

$dbinstall->runCleanupRotines();

$dbinstall->profile_end = DUPX_U::getMicrotime();
$dbinstall->writeLog();
$JSON = $dbinstall->getJSON($JSON);

//FINAL RESULTS
$ajax1_sum	 = DUPX_U::elapsedTime(DUPX_U::getMicrotime(), $dbinstall->start_microtime);
DUPX_Log::info("\nINSERT DATA RUNTIME: " . DUPX_U::elapsedTime($dbinstall->profile_end, $dbinstall->profile_start));
DUPX_Log::info('STEP-2 COMPLETE @ '.@date('h:i:s')." - RUNTIME: {$ajax1_sum}");

error_reporting($ajax2_error_level);
DUPX_Log::close();
die(DupProSnapLibUtil::wp_json_encode($JSON));