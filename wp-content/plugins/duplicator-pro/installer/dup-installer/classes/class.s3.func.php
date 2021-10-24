<?php
/**
 * Class used to update and edit web server configuration files
 * for .htaccess, web.config and user.ini
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\Crypt
 *
 */
defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Step 3 functions
 * Singlethon
 */
final class DUPX_S3_Funcs
{
    const MODE_NORMAL = 1;
    const MODE_CHUNK  = 2;
    const MODE_SKIP   = 3; // not implemented yet

    /**
     *
     * @var DUPX_S3_Funcs
     */
    protected static $instance = null;

    /**
     *
     * @var array
     */
    public $post = null;

    /**
     *
     * @var array
     */
    public $cTableParams = null;

    /**
     *
     * @var array
     */
    public $report = array();

    /**
     *
     * @var int
     */
    private $timeStart = null;

    /**
     *
     * @var database connection
     */
    private $dbh = null;

    /**
     *
     * @var bool
     */
    private $fullReport = false;

    private function __construct()
    {
        $this->timeStart = DUPX_U::getMicrotime();
    }

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * inizialize 3sFunc data
     */
    public function initData()
    {
        // if data file exists load saved data
        if (file_exists(self::getS3dataFilePath())) {
            DUPX_Log::info('LOAD S3 DATA FROM JSON', 2);
            if ($this->loadData() == false) {
                throw new Exception('Can\'t load s3 data');
            }
        } else {
            DUPX_Log::info('INIT S3 DATA', 2);
            // else init data from $_POST
            $this->setPostData();
            $this->setReplaceList();
            $this->initReport();
            $this->copyOriginalConfigFiles();
        }
    }

    /**
     *
     * @return string
     */
    private static function getS3dataFilePath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = $GLOBALS['DUPX_INIT'].'/dup-installer-s3data__'.$GLOBALS['PACKAGE_HASH'].'.json';
        }
        return $path;
    }

    /**
     *
     * @return boolean
     */
    public function saveData()
    {
        $data = array(
            'post' => $this->getPost(),
            'report' => $this->report,
            'cTableParams' => $this->cTableParams,
            'replaceData' => DUPX_S_R_MANAGER::getInstance()->getArrayData()
        );

        // @todo remove JSON_PRETTY_PRINT  and update snap lib with json pretty function
        if (($json = DupProSnapLibUtil::wp_json_encode($data, JSON_PRETTY_PRINT)) === false) {
            DUPX_Log::info('Can\'t encode json data');
            return false;
        }

        if (@file_put_contents(self::getS3dataFilePath(), $json) === false) {
            DUPX_Log::info('Can\'t save s3 data file');
            return false;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    private function loadData()
    {
        if (!file_exists(self::getS3dataFilePath())) {
            return false;
        }

        if (($json = @file_get_contents(self::getS3dataFilePath())) === false) {
            DUPX_Log::info('Can\'t load s3 data file');
            return false;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            DUPX_Log::info('Can\'t decode json data');
            return false;
        }

        if (array_key_exists('post', $data)) {
            $this->post = $data['post'];
        } else {
            DUPX_Log::info('S3 data not well formed: post not found.');
            return false;
        }

        if (array_key_exists('cTableParams', $data)) {
            $this->cTableParams = $data['cTableParams'];
        } else {
            DUPX_Log::info('S3 data not well formed: cTableParams not found.');
            return false;
        }

        if (array_key_exists('replaceData', $data)) {
            DUPX_S_R_MANAGER::getInstance()->setFromArrayData($data['replaceData']);
        } else {
            DUPX_Log::info('S3 data not well formed: replace not found.');
            return false;
        }

        if (array_key_exists('report', $data)) {
            $this->report = $data['report'];
        } else {
            DUPX_Log::info('S3 data not well formed: report not found.');
            return false;
        }

        return true;
    }

    /**
     *
     * @return boolean
     */
    public static function resetData()
    {
        $result = true;
        if (file_exists(self::getS3dataFilePath())) {
            if (@unlink(self::getS3dataFilePath()) === false) {
                DUPX_Log::info('Can\'t delete s3 data file');
                $result = false;
            }
        }

        if (file_exists($GLOBALS["CHUNK_DATA_FILE_PATH"])) {
            if (@unlink($GLOBALS["CHUNK_DATA_FILE_PATH"]) === false) {
                DUPX_Log::info('Can\'t delete s3 chunk file');
                $result = false;
            }
        }
        return $result;
    }

    private function initReport()
    {
        $this->report = self::getInitReport();
    }

    public static function getInitReport()
    {
        return array(
            'pass' => 0,
            'chunk' => 0,
            'chunkPos' => array(),
            'progress_perc' => 0,
            'scan_tables' => 0,
            'scan_rows' => 0,
            'scan_cells' => 0,
            'updt_tables' => 0,
            'updt_rows' => 0,
            'updt_cells' => 0,
            'errsql' => array(),
            'errser' => array(),
            'errkey' => array(),
            'errsql_sum' => 0,
            'errser_sum' => 0,
            'errkey_sum' => 0,
            'profile_start' => '',
            'profile_end' => '',
            'time' => '',
            'err_all' => 0,
            'warn_all' => 0,
            'warnlist' => array()
        );
    }

    public function getJsonReport()
    {
        $this->report['warn_all'] = empty($this->report['warnlist']) ? 0 : count($this->report['warnlist']);

        if ($this->fullReport) {
            return array(
                'step1' => json_decode(urldecode($this->post['json'])),
                'step3' => $this->report
            );
        } else {
            return array(
                'step3' => $this->report
            );
        }
    }

    private static function logSectionHeader($title, $func, $line)
    {
        $log = "\n".'===================================='."\n".
            $title;
        if ($GLOBALS["LOGGING"] > 1) {
            $log .= ' [FUNC: '.$func.' L:'.$line.']';
        }
        $log .= "\n".
            '====================================';
        DUPX_Log::info($log);
    }

    private function setPostData()
    {
        // POST PARAMS
        // SEARCH AND SEPLACE SETTINGS
        $this->post = array();

        $this->post['blogname']     = isset($_POST['blogname']) ? htmlspecialchars($_POST['blogname'], ENT_QUOTES) : 'No Blog Title Set';
        $this->post['postguid']     = filter_input(INPUT_POST, 'postguid', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['fullsearch']   = filter_input(INPUT_POST, 'fullsearch', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['replace_mode'] = DUPX_U::isset_sanitize($_POST, 'replace_mode', array('default' => 'legacy'));

        $this->post['path_old'] = DUPX_U::isset_sanitize($_POST, 'path_old', array('default' => null, 'trim' => true));
        $this->post['path_new'] = DUPX_U::isset_sanitize($_POST, 'path_new', array('default' => null, 'trim' => true));

        if ($this->isMapping() && isset($_POST['mu_replace'][1])) {
            $_POST['url_old'] = $_POST['mu_search'][1];
            // it is already synchronized in the client but I apply it for greater security
            $_POST['url_new'] = $_POST['mu_replace'][1];
        }

        $this->post['siteurl'] = DUPX_U::isset_sanitize($_POST, 'siteurl', array('default' => null, 'trim' => true));
        if (!is_null($this->post['siteurl'])) {
            $this->post['siteurl'] = rtrim($this->post['siteurl'], '/');
        }

        $this->post['url_old'] = DUPX_U::isset_sanitize($_POST, 'url_old', array('default' => null, 'trim' => true));
        if (!is_null($this->post['url_old'])) {
            $this->post['siteurl'] = rtrim($this->post['url_old'], '/');
        }

        $this->post['url_new'] = DUPX_U::isset_sanitize($_POST, 'url_new', array('default' => null, 'trim' => true));
        if (!is_null($this->post['url_new'])) {
            $this->post['siteurl'] = rtrim($this->post['url_new'], '/');
        }

        $this->post['tables']           = isset($_POST['tables']) && is_array($_POST['tables']) ? array_map('DUPX_U::sanitize_text_field', $_POST['tables']) : array();
        $this->post['cross_search']     = filter_input(INPUT_POST, 'cross_search', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['subsite_id']       = filter_input(INPUT_POST, 'subsite_id', FILTER_VALIDATE_INT, array("options" => array('default' => -1, 'min_range' => 0)));
        $this->post['remove_redundant'] = filter_input(INPUT_POST, 'remove_redundant', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['replaceMail']      = filter_input(INPUT_POST, 'search_replace_email_domain', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['search']           = filter_input(INPUT_POST, 'search', FILTER_DEFAULT,
            array(
            'options' => array(
                'default' => array()
            ),
            'flags' => FILTER_REQUIRE_ARRAY,
        ));
        $this->post['replace']          = filter_input(INPUT_POST, 'replace', FILTER_DEFAULT,
            array(
            'options' => array(
                'default' => array()
            ),
            'flags' => FILTER_REQUIRE_ARRAY,
        ));
        foreach (array_keys($this->post['search']) as $index) {
            $this->post['search'][$index]  = trim(DUPX_U::sanitize_text_field($this->post['search'][$index]));
            $this->post['replace'][$index] = DUPX_U::sanitize_text_field($this->post['replace'][$index]);
        }

        $this->post['mu_search']  = filter_input(INPUT_POST, 'mu_search', FILTER_DEFAULT,
            array(
            'options' => array(
                'default' => array()
            ),
            'flags' => FILTER_REQUIRE_ARRAY,
        ));
        $this->post['mu_replace'] = filter_input(INPUT_POST, 'mu_replace', FILTER_DEFAULT,
            array(
            'options' => array(
                'default' => array()
            ),
            'flags' => FILTER_REQUIRE_ARRAY,
        ));

        // DATABASE CONNECTION
        $this->post['dbhost']    = trim(filter_input(INPUT_POST, 'dbhost', FILTER_DEFAULT, array('options' => array('default' => ''))));
        $this->post['dbuser']    = trim(filter_input(INPUT_POST, 'dbuser', FILTER_DEFAULT, array('options' => array('default' => ''))));
        $this->post['dbname']    = trim(filter_input(INPUT_POST, 'dbname', FILTER_DEFAULT, array('options' => array('default' => ''))));
        $this->post['dbpass']    = trim(filter_input(INPUT_POST, 'dbpass', FILTER_DEFAULT, array('options' => array('default' => ''))));
        $this->post['dbcharset'] = DUPX_U::isset_sanitize($_POST, 'dbcharset', array('default' => ''));
        $this->post['dbcollate'] = DUPX_U::isset_sanitize($_POST, 'dbcollate', array('default' => ''));

        // NEW ADMIN USER
        $this->post['wp_username']   = DUPX_U::isset_sanitize($_POST, 'wp_username', array('default' => '', 'trim' => true));
        $this->post['wp_password']   = DUPX_U::isset_sanitize($_POST, 'wp_password', array('default' => '', 'trim' => true));
        $this->post['wp_mail']       = DUPX_U::isset_sanitize($_POST, 'wp_mail', array('default' => '', 'trim' => true));
        $this->post['wp_nickname']   = DUPX_U::isset_sanitize($_POST, 'wp_nickname', array('default' => '', 'trim' => true));
        $this->post['wp_first_name'] = DUPX_U::isset_sanitize($_POST, 'wp_first_name', array('default' => '', 'trim' => true));
        $this->post['wp_last_name']  = DUPX_U::isset_sanitize($_POST, 'wp_last_name', array('default' => '', 'trim' => true));

        // WP CONFIG SETTINGS
        $this->post['ssl_admin']           = filter_input(INPUT_POST, 'ssl_admin', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['auth_keys_and_salts'] = filter_input(INPUT_POST, 'auth_keys_and_salts', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['cache_wp']            = filter_input(INPUT_POST, 'cache_wp', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['cache_path']          = filter_input(INPUT_POST, 'cache_path', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['wp_debug']            = filter_input(INPUT_POST, 'wp_debug', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['wp_debug_log']        = filter_input(INPUT_POST, 'wp_debug_log', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['wp_debug_display']    = filter_input(INPUT_POST, 'wp_debug_display', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['script_debug']        = filter_input(INPUT_POST, 'script_debug', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['savequeries']         = filter_input(INPUT_POST, 'savequeries', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['wp_memory_limit']     = DUPX_U::isset_sanitize($_POST, 'wp_memory_limit', array('default' => '', 'trim' => true));
        $this->post['wp_max_memory_limit'] = DUPX_U::isset_sanitize($_POST, 'wp_max_memory_limit', array('default' => '', 'trim' => true));
        $this->post['disallow_file_edit']  = filter_input(INPUT_POST, 'disallow_file_edit', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['cookie_domain']       = DUPX_U::isset_sanitize($_POST, 'cookie_domain', array('default' => '', 'trim' => true));
        $this->post['autosave_interval']   = filter_input(INPUT_POST, 'autosave_interval', FILTER_VALIDATE_INT, array("options" => array('default' => 0, 'min_range' => 0)));

        $this->post['wp_auto_update_core'] = filter_input(INPUT_POST, 'wp_auto_update_core', FILTER_DEFAULT, array('options' => array('default' => '')));
        switch ($this->post['wp_auto_update_core']) {
            case 'false':
                $this->post['wp_auto_update_core'] = false;
                break;
            case 'true':
                $this->post['wp_auto_update_core'] = true;
                break;
            case 'minor':
                break;
            default:
                break;
        }

        if (isset($_POST['wp_post_revisions']) && !empty($_POST['wp_post_revisions'])) {
            switch ($_POST['wp_post_revisions']) {
                case 'true':
                    $this->post['wp_post_revisions'] = filter_input(INPUT_POST, 'wp_post_revisions_no', FILTER_VALIDATE_INT, array("options" => array('default' => 'true', 'min_range' => 1)));
                    break;
                case 'false':
                    $this->post['wp_post_revisions'] = 'false';
                    break;
                default:
                    $this->post['wp_post_revisions'] = 'no-action';
                    break;
            }
        } else {
            $this->post['wp_post_revisions'] = 'remove';
        }

        // OTHER
        $this->post['empty_schedule_storage'] = filter_input(INPUT_POST, 'empty_schedule_storage', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['exe_safe_mode']          = filter_input(INPUT_POST, 'exe_safe_mode', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['retain_config']          = filter_input(INPUT_POST, 'retain_config', FILTER_VALIDATE_BOOLEAN, array('options' => array('default' => false)));
        $this->post['plugins']                = filter_input(INPUT_POST, 'plugins', FILTER_SANITIZE_STRING,
            array(
            'options' => array(
                'default' => array()
            ),
            'flags' => FILTER_REQUIRE_ARRAY,
        ));

        $this->post['mode_chunking'] = filter_input(INPUT_POST, 'mode_chunking', FILTER_VALIDATE_INT,
            array('options' => array(
                'default' => self::MODE_NORMAL,
                'min_range' => 1,
                'max_range' => 3
        )));

        // MULTI SITE MODE
        if ($GLOBALS['DUPX_AC']->mu_mode == 0) {
            $this->post['action_mu_mode'] = DUPX_MultisiteMode::SingleSite;
        } else {
            $this->post['action_mu_mode'] = $this->post['subsite_id'] > 0 ? DUPX_MultisiteMode::Standalone : $GLOBALS['DUPX_AC']->mu_mode;
        }

        $this->post['json'] = filter_input(INPUT_POST, 'json', FILTER_DEFAULT, array('options' => array('default' => '{}')));
    }

    public function getPost($key = null)
    {
        if (is_null($this->post)) {
            $this->initData();
        }

        if (is_null($key)) {
            return $this->post;
        } else if (isset($this->post[$key])) {
            return $this->post[$key];
        } else {
            return null;
        }
    }

    public function isMapping()
    {
        return $this->getPost('replace_mode') === "mapping";
    }

    /**
     * add table in tables list to scan in search and replace engine if isn't already in array
     * 
     * @param string $table
     */
    public function addTable($table)
    {
        if (empty($table)) {
            return;
        }

        // make sure post data is inizialized
        $this->getPost();
        if (!in_array($table, $this->post['tables'])) {
            $this->post['tables'][] = $table;
        }
    }

    public function newSiteIsMultisite()
    {
        return $this->post['action_mu_mode'] == DUPX_MultisiteMode::Subdirectory || $this->post['action_mu_mode'] == DUPX_MultisiteMode::Subdomain;
    }

    /**
     * open db connection if is closed
     */
    private function dbConnection()
    {
        if (is_null($this->dbh)) {
            // make sure post data is inizialized
            $this->getPost();

            //MYSQL CONNECTION
            $this->dbh   = DUPX_DB::connect($this->post['dbhost'], $this->post['dbuser'], $this->post['dbpass'], $this->post['dbname']);
            $dbConnError = (mysqli_connect_error()) ? 'Error: '.mysqli_connect_error() : 'Unable to Connect';

            if (!$this->dbh) {
                $msg = "Unable to connect with the following parameters: <br/> <b>HOST:</b> {$post_db_host}<br/> <b>DATABASE:</b> {$post_db_name}<br/>";
                $msg .= "<b>Connection Error:</b> {$dbConnError}";
                DUPX_Log::error($msg);
            }

            $db_max_time = mysqli_real_escape_string($this->dbh, $GLOBALS['DB_MAX_TIME']);
            @mysqli_query($this->dbh, "SET wait_timeout = ".mysqli_real_escape_string($this->dbh, $db_max_time));

            $post_db_charset = $this->post['dbcharset'];
            $post_db_collate = $this->post['dbcollate'];
            DUPX_DB::setCharset($this->dbh, $post_db_charset, $post_db_collate);
        }
    }

    public function getDbConnection()
    {
        // make sure dbConnection is inizialized
        $this->dbConnection();
        return $this->dbh;
    }

    /**
     * close db connection if is open
     */
    public function closeDbConnection()
    {
        if (!is_null($this->dbh)) {
            mysqli_close($this->dbh);
            $this->dbh = null;
        }
    }

    public function initLog()
    {
        // make sure dbConnection is inizialized
        $this->dbConnection();

        $charsetServer = @mysqli_character_set_name($this->dbh);
        $charsetClient = @mysqli_character_set_name($this->dbh);

        //LOGGING
        $date = @date('h:i:s');
        $log  = "\n\n".
            "********************************************************************************\n".
            "DUPLICATOR PRO INSTALL-LOG\n".
            "STEP-3 START @ ".$date."\n".
            "NOTICE: Do NOT post to public sites or forums\n".
            "********************************************************************************\n".
            "CHARSET SERVER:\t".DUPX_Log::varToString($charsetServer)."\n".
            "CHARSET CLIENT:\t".DUPX_Log::varToString($charsetClient)."\n".
            "********************************************************************************\n".
            "OPTIONS:\n";

        $skipOpts = array('tables', 'plugins', 'dbpass', 'json', 'search', 'replace', 'mu_search', 'mu_replace', 'wp_password');
        foreach ($this->post as $key => $val) {
            if (in_array($key, $skipOpts)) {
                continue;
            }
            $log .= str_pad($key, 22, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($val)."\n";
        }
        $log .= "********************************************************************************\n";

        DUPX_Log::info($log);

        $POST_LOG = $this->post;
        unset($POST_LOG['tables']);
        unset($POST_LOG['plugins']);
        unset($POST_LOG['dbpass']);
        ksort($POST_LOG);

        //Detailed logging
        $log = "--------------------------------------\n";
        $log .= "POST DATA\n";
        $log .= "--------------------------------------\n";
        $log .= print_r($POST_LOG, true);
        DUPX_Log::info($log, DUPX_Log::LV_DEBUG);

        $log = "--------------------------------------\n";
        $log .= "TABLES TO SCAN\n";
        $log .= "--------------------------------------\n";
        $log .= (isset($this->post['tables']) && count($this->post['tables']) > 0) ? DUPX_Log::varToString($this->post['tables']) : 'No tables selected to update';
        $log .= "--------------------------------------\n";
        $log .= "KEEP PLUGINS ACTIVE\n";
        $log .= "--------------------------------------\n";
        $log .= (isset($this->post['plugins']) && count($this->post['plugins']) > 0) ? DUPX_Log::varToString($this->post['plugins']) : 'No plugins selected for activation';
        DUPX_Log::info($log, 2);
        DUPX_Log::flush();
    }

    public function initChunkLog($maxIteration, $timeOut, $throttling, $rowsPerPage)
    {
        $log = "********************************************************************************\n".
            "CHUNK PARAMS:\n";
        $log .= str_pad('maxIteration', 22, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($maxIteration)."\n";
        $log .= str_pad('timeOut', 22, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($timeOut)."\n";
        $log .= str_pad('throttling', 22, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($throttling)."\n";
        $log .= str_pad('rowsPerPage', 22, '_', STR_PAD_RIGHT).': '.DUPX_Log::varToString($rowsPerPage)."\n";
        $log .= "********************************************************************************\n";
        DUPX_Log::info($log);
    }

    /**
     *
     * @staticvar type $configTransformer
     * 
     * @return WPConfigTransformer
     */
    public function getWpConfigTransformer()
    {
        static $configTransformer = null;

        if (is_null($configTransformer)) {
            //@todo: integrate all logic into DUPX_WPConfig::updateVars
            if (is_writable($this->getWpconfigArkPath())) {
                $configTransformer = new WPConfigTransformer($this->getWpconfigArkPath());
            } else {
                $err_log = "\nWARNING: Unable to update file permissions and write to dup-wp-config-arc__[HASH].txt.  ";
                $err_log .= "Check that the wp-config.php is in the archive.zip and check with your host or administrator to enable PHP to write to the wp-config.php file.  ";
                $err_log .= "If performing a 'Manual Extraction' please be sure to select the 'Manual Archive Extraction' option on step 1 under options.";
                chmod($this->getWpconfigArkPath(), 0644) ? DUPX_Log::info("File Permission Update: dup-wp-config-arc__[HASH].txt set to 0644") : DUPX_Log::error("{$err_log}");
            }
        }

        return $configTransformer;
    }

    /**
     *
     * @staticvar string $path
     * @return string
     */
    public function getWpconfigArkPath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = $GLOBALS['DUPX_ROOT'].'/dup-wp-config-arc__'.$GLOBALS['DUPX_AC']->package_hash.'.txt';
        }
        return $path;
    }

    /**
     *
     * @staticvar string $path
     * @return string
     */
    public function getHtaccessArkPath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = $GLOBALS['DUPX_ROOT'].'/htaccess.orig';
        }
        return $path;
    }

    /**
     *
     * @staticvar string $path
     * @return string
     */
    public function getOrigWpConfigPath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = $GLOBALS['DUPX_INIT'].'/dup-orig-wp-config__'.$GLOBALS['DUPX_AC']->package_hash.'.txt';
        }
        return $path;
    }

    /**
     *
     * @staticvar string $path
     * @return string
     */
    public function getOrigHtaccessPath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = $GLOBALS['DUPX_INIT'].'/dup-orig-wp-config__'.$GLOBALS['DUPX_AC']->package_hash.'.txt';
        }
        return $GLOBALS['DUPX_INIT'].'/dup-orig-htaccess__'.$GLOBALS['DUPX_AC']->package_hash.'.txt';
    }

    /**
     *
     * @return string
     */
    public function copyOriginalConfigFiles()
    {
        $wpOrigPath = $this->getOrigWpConfigPath();
        $wpArkPath  = $this->getWpconfigArkPath();

        if (file_exists($wpOrigPath)) {
            if (!@unlink($wpOrigPath)) {
                DUPX_Log::info('Can\'t delete copy of WP Config orig file');
            }
        }

        if (!file_exists($wpArkPath)) {
            DUPX_Log::info('WP Config ark file don\' exists');
        }

        if (!@copy($wpArkPath, $wpOrigPath)) {
            $errors = error_get_last();
            DUPX_Log::info("COPY ERROR: ".$errors['type']."\n".$errors['message']);
        } else {
            echo DUPX_Log::info("Original WP Config file copied", 2);
        }

        $htOrigPath = $this->getOrigHtaccessPath();
        $htArkPath  = $this->getHtaccessArkPath();

        if (file_exists($htOrigPath)) {
            if (!@unlink($htOrigPath)) {
                DUPX_Log::info('Can\'t delete copy of htaccess orig file');
            }
        }

        if (!file_exists($htArkPath)) {
            DUPX_Log::info('htaccess ark file don\' exists');
        }

        if (!@copy($htArkPath, $htOrigPath)) {
            $errors = error_get_last();
            DUPX_Log::info("COPY ERROR: ".$errors['type']."\n".$errors['message']);
        } else {
            echo DUPX_Log::info("htaccess file copied", 2);
        }
    }

    /**
     * set replace list
     *
     * Auto inizialize function
     */
    public function setReplaceList()
    {
        self::logSectionHeader('SET SEARCH AND REPLACE LIST', __FUNCTION__, __LINE__);
        $this->setCustomReplaceList();
        $this->setMultisiteReplaceList();
        $this->setGlobalSearchAndReplaceList();
    }

    /**
     *
     * @return int MODE_NORAML|MODE_CHUNK|MODE_SKIP
     */
    public function getEngineMode()
    {
        return $this->getPost('mode_chunking');
    }

    /**
     *
     * @return bool 
     */
    public function isChunk()
    {
        return $this->getPost('mode_chunking') === self::MODE_CHUNK;
    }

    private function setCustomReplaceList()
    {
        // make sure post data is inizialized
        $this->getPost();

        $s_r_manager = DUPX_S_R_MANAGER::getInstance();
        //CUSTOM REPLACE -> REPLACE LIST
        foreach ($this->post['search'] as $search_index => $search_for) {
            if (strlen($search_for) > 0) {
                $replace_with = $this->post['replace'][$search_index];
                $s_r_manager->addItem($search_for, $replace_with, DUPX_S_R_ITEM::TYPE_STRING, 20);
            }
        }
    }

    private function setMultisiteReplaceList()
    {
        // make sure dbConnection is inizialized
        $this->dbConnection();

        $s_r_manager = DUPX_S_R_MANAGER::getInstance();

        DUPX_Log::info("-----------------------------------------", 2);
        DUPX_Log::info("ACTION MU MODE START :\"{$this->post['action_mu_mode']}\"", 2);

        switch ($this->post['action_mu_mode']) {
            case DUPX_MultisiteMode::Subdomain:
            case DUPX_MultisiteMode::Subdirectory:
                if ($this->post['action_mu_mode'] === DUPX_MultisiteMode::Subdomain) {
                    self::logSectionHeader('ACTION MU MODE START SUBDOMAINs', __FUNCTION__, __LINE__);
                } else {
                    self::logSectionHeader('ACTION MU MODE START SUBFOLDERS', __FUNCTION__, __LINE__);
                }
                $subsites = $GLOBALS['DUPX_AC']->subsites;

                // put the main sub site at the end
                $main_subsite = $subsites[0];
                array_shift($subsites);
                $subsites[]   = $main_subsite;

                if ($this->post['action_mu_mode'] !== DUPX_MultisiteMode::Subdomain) {
                    $subsites = DUPX_U::urlForSubdirectoryMode($subsites, $GLOBALS['DUPX_AC']->url_old);
                }

                $main_url = $main_subsite->name;

                DUPX_Log::info("MAIN URL :\"{$main_url}\"", 2);
                DUPX_Log::info(
                    '-- SUBSITES --'."\n".
                    print_r($subsites, true), 3);

                foreach ($subsites as $cSub) {
                    DUPX_Log::info('SUBSITE ID:'.$cSub->id.'NAME: '.$cSub->name, 3);

                    if ($this->isMapping() && isset($this->post['mu_search'][$cSub->id])) {
                        $search = $this->post['mu_search'][$cSub->id];
                    } else {
                        $search = $cSub->name;
                    }

                    if ($this->isMapping() && isset($this->post['mu_replace'][$cSub->id])) {
                        $replace = $this->post['mu_replace'][$cSub->id];
                    } else {
                        $replace = DUPX_U::getDefaultURL($cSub->name, $main_url, $this->post['action_mu_mode'] === DUPX_MultisiteMode::Subdomain);
                    }

                    // get table for search and replace scope for subsites
                    if ($this->post['cross_search'] == false && $cSub->id > 1) {
                        $tables = DUPX_MU::getSubsiteTables($cSub->id, $this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix);
                    } else {
                        // global scope
                        $tables = true;
                    }
                    $priority = ($cSub->id > 1) ? 5 : 10;
                    $s_r_manager->addItem($search, $replace, DUPX_S_R_ITEM::TYPE_URL, $priority, $tables);

                    // Replace email address (xyz@oldomain.com to xyz@newdomain.com).
                    if ($this->post['replaceMail']) {
                        $at_old_domain = '@'.DUPX_U::getDomain($search);
                        $at_new_domain = '@'.DUPX_U::getDomain($replace);
                        $s_r_manager->addItem($at_old_domain, $at_new_domain, DUPX_S_R_ITEM::TYPE_STRING, 20, $tables);
                    }

                    // for domain host and path priority is on main site
                    $priority = ($cSub->id > 1) ? 10 : 5;
                    $sUrlInfo = parse_url($search);
                    $sHost    = isset($sUrlInfo['host']) ? $sUrlInfo['host'] : '';
                    $sPath    = isset($sUrlInfo['path']) ? $sUrlInfo['path'] : '';
                    $rUrlInfo = parse_url($replace);
                    $rHost    = isset($rUrlInfo['host']) ? $rUrlInfo['host'] : '';
                    $rPath    = isset($rUrlInfo['path']) ? $rUrlInfo['path'] : '';

                    // add path and host scope for custom columns in database
                    $s_r_manager->addItem($sHost, $rHost, DUPX_S_R_ITEM::TYPE_URL, $priority, 'domain_host');
                    $s_r_manager->addItem($sPath, $rPath, DUPX_S_R_ITEM::TYPE_STRING, $priority, 'domain_path');
                }
                break;
            case DUPX_MultisiteMode::Standalone:
                self::logSectionHeader('ACTION MU MODE START STANDALONE', __FUNCTION__, __LINE__);

                // REPLACE URL
                foreach ($GLOBALS['DUPX_AC']->subsites as $cSub) {
                    if ($cSub->id == $this->post['subsite_id']) {
                        $standalone_obj = $cSub;
                        break;
                    }
                }
                if ($GLOBALS['DUPX_AC']->mu_mode !== DUPX_MultisiteMode::Subdomain) {
                    $subsites       = DUPX_U::urlForSubdirectoryMode(array($standalone_obj), $GLOBALS['DUPX_AC']->url_old);
                    $standalone_obj = $subsites[0];
                }
                $search  = $standalone_obj->name;
                $replace = $this->post['url_new'];
                $s_r_manager->addItem($search, $replace, DUPX_S_R_ITEM::TYPE_URL, 5);

                // CONVERSION
                if ($this->post['subsite_id'] == 1) {
                    // Since we are converting subsite to multisite consider this a standalone site
                    $GLOBALS['DUPX_AC']->mu_mode = DUPX_MultisiteMode::Standalone;
                    $post_path_new               = $this->post['path_new'];
                    $new_content_dir             = (substr($post_path_new, -1, 1) == '/') ? "{$post_path_new}{$GLOBALS['DUPX_AC']->relative_content_dir}" : "{$post_path_new}/{$GLOBALS['DUPX_AC']->relative_content_dir}";
                    try {
                        DUPX_MU::convertSubsiteToStandalone($this->post['subsite_id'], $this->dbh, $GLOBALS['DUPX_AC'], $new_content_dir, $this->post['remove_redundant']);
                    } catch (Exception $ex) {
                        DUPX_Log::error("Problem with core logic of converting subsite into a standalone site.<br/>".$ex->getMessage().'<br/>'.$ex->getTraceAsString());
                    }
                } else if ($this->post['subsite_id'] > 1) {

                    // Need to swap the subsite prefix for the main table prefix
                    $subsite_uploads_dir = "/uploads/sites/{$this->post['subsite_id']}";
                    $subsite_prefix      = "{$GLOBALS['DUPX_AC']->wp_tableprefix}{$this->post['subsite_id']}_";

                    $s_r_manager->addItem($subsite_uploads_dir, '/uploads', DUPX_S_R_ITEM::TYPE_PATH, 10);
                    $s_r_manager->addItem($subsite_prefix, $GLOBALS['DUPX_AC']->wp_tableprefix, DUPX_S_R_ITEM::TYPE_STRING, 10);

                    // REPLACE PATH
                    $post_path_new   = $this->post['path_new'];
                    $new_content_dir = (substr($post_path_new, -1, 1) == '/') ? "{$post_path_new}{$GLOBALS['DUPX_AC']->relative_content_dir}" : "{$post_path_new}/{$GLOBALS['DUPX_AC']->relative_content_dir}";

                    try {
                        DUPX_MU::convertSubsiteToStandalone($this->post['subsite_id'], $this->dbh, $GLOBALS['DUPX_AC'], $new_content_dir, $this->post['remove_redundant']);
                    } catch (Exception $ex) {
                        DUPX_Log::error("Problem with core logic of converting subsite into a standalone site.<br/>".$ex->getMessage().'<br/>'.$ex->getTraceAsString());
                    }

                    // Since we are converting subsite to multisite consider this a standalone site
                    $GLOBALS['DUPX_AC']->mu_mode = DUPX_MultisiteMode::Standalone;

                    //Replace WP 3.4.5 subsite uploads path in DB
                    if ($GLOBALS['DUPX_AC']->mu_generation === 1) {
                        $blogs_dir   = 'blogs.dir/'.$this->post['subsite_id'].'/files';
                        $uploads_dir = 'uploads';

                        $s_r_manager->addItem($blogs_dir, $uploads_dir, DUPX_S_R_ITEM::TYPE_PATH, 5);

                        $post_url_new = $this->post['url_new'];
                        $files_dir    = "{$post_url_new}/files";
                        $uploads_dir  = "{$post_url_new}/{$GLOBALS['DUPX_AC']->relative_content_dir}/uploads";

                        $s_r_manager->addItem($files_dir, $uploads_dir, DUPX_S_R_ITEM::TYPE_URL, 5);
                    }
                } else {
                    // trace error stand alone conversion with subsite id <= 0
                }

                break;
            case DUPX_MultisiteMode::SingleSite:
            default:
                // do nothing
                break;
        }
    }

    private function setGlobalSearchAndReplaceList()
    {
        $s_r_manager = DUPX_S_R_MANAGER::getInstance();

        // make sure dbConnection is inizialized
        $this->dbConnection();

        // DIRS PATHS
        $post_path_old = $this->post['path_old'];
        $post_path_new = $this->post['path_new'];
        $s_r_manager->addItem($post_path_old, $post_path_new, DUPX_S_R_ITEM::TYPE_PATH, 10);

        // URLS
        // url from _POST
        $old_urls_list = array($this->post['url_old']);
        $post_url_new  = $this->post['url_new'];
        $at_new_domain = '@'.DUPX_U::getDomain($post_url_new);

        try {
            $confTransformer = $this->getWpConfigTransformer();

            // urls from wp-config
            if (!is_null($confTransformer)) {
                if ($confTransformer->exists('constant', 'WP_HOME')) {
                    $old_urls_list[] = $confTransformer->get_value('constant', 'WP_HOME');
                }

                if ($confTransformer->exists('constant', 'WP_SITEURL')) {
                    $old_urls_list[] = $confTransformer->get_value('constant', 'WP_SITEURL');
                }
            }

            // urls from db
            $dbUrls = mysqli_query($this->dbh, 'SELECT * FROM `'.mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix).'options` where option_name IN (\'siteurl\',\'home\')');
            if ($dbUrls instanceof mysqli_result) {
                while ($row = $dbUrls->fetch_object()) {
                    $old_urls_list[] = $row->option_value;
                }
            } else {
                DUPX_Log::info('DB ERROR: '.mysqli_error($this->dbh));
            }
        } catch (Exception $e) {
            DUPX_Log::info('CONTINUE EXCEPTION: '.$exceptionError->getMessage());
            DUPX_Log::info('TRACE:');
            DUPX_Log::info($exceptionError->getTraceAsString());
        }

        $old_urls_list = array_unique($old_urls_list);
        foreach ($old_urls_list as $old_url) {
            $s_r_manager->addItem($old_url, $post_url_new, DUPX_S_R_ITEM::TYPE_URL, 10);

            // Replace email address (xyz@oldomain.com to xyz@newdomain.com).
            if ($this->post['replaceMail']) {
                $at_old_domain = '@'.DUPX_U::getDomain($old_url);
                $s_r_manager->addItem($at_old_domain, $at_new_domain, DUPX_S_R_ITEM::TYPE_STRING, 20);
            }
        }
    }

    public function runSearchAndReplace()
    {
        self::logSectionHeader('RUN SEARCH AND REPLACE', __FUNCTION__, __LINE__);

        // make sure post data is inizialized
        $this->getPost();

        DUPX_UpdateEngine::load($this->post['tables']);
        DUPX_UpdateEngine::replaceBlogsAndSiteTables();
        DUPX_UpdateEngine::logStats();
        DUPX_UpdateEngine::logErrors();
    }

    public function removeMaincenanceMode()
    {
        self::logSectionHeader('REMOVE MAINTENANCE MODE', __FUNCTION__, __LINE__);
        // make sure post data is inizialized
        $this->getPost();


        if (isset($this->post['remove_redundant']) && $this->post['remove_redundant']) {
            if ($GLOBALS['DUPX_STATE']->mode == DUPX_InstallerMode::OverwriteInstall) {
                DUPX_U::maintenanceMode(false, $GLOBALS['DUPX_ROOT']);
            }
        }
    }

    public function removeLicenseKey()
    {
        self::logSectionHeader('REMOVE LICENSE KEY', __FUNCTION__, __LINE__);
        // make sure dbConnection is inizialized
        $this->dbConnection();

        if (isset($GLOBALS['DUPX_AC']->brand) && isset($GLOBALS['DUPX_AC']->brand->enabled) && $GLOBALS['DUPX_AC']->brand->enabled) {
            $license_check = mysqli_query($this->dbh,
                "SELECT COUNT(1) AS count FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` WHERE `option_name` LIKE 'duplicator_pro_license_key' ");
            $license_row   = mysqli_fetch_row($license_check);
            $license_count = is_null($license_row) ? 0 : $license_row[0];
            if ($license_count > 0) {
                mysqli_query($this->dbh,
                    "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` SET `option_value` = '' WHERE `option_name` LIKE 'duplicator_pro_license_key'");
            }
        }
    }

    public function createNewAdminUser()
    {
        self::logSectionHeader('CREATE NEW ADMIN USER', __FUNCTION__, __LINE__);
        // make sure dbConnection is inizialized
        $this->dbConnection();

        $nManager = DUPX_NOTICE_MANAGER::getInstance();

        if (strlen($this->post['wp_username']) >= 4 && strlen($this->post['wp_password']) >= 6) {
            $wp_username   = mysqli_real_escape_string($this->dbh, $this->post['wp_username']);
            $newuser_check = mysqli_query($this->dbh,
                "SELECT COUNT(*) AS count FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."users` WHERE user_login = '{$wp_username}' ");
            $newuser_row   = mysqli_fetch_row($newuser_check);
            $newuser_count = is_null($newuser_row) ? 0 : $newuser_row[0];

            if ($newuser_count == 0) {

                $newuser_datetime = @date("Y-m-d H:i:s");
                $newuser_datetime = mysqli_real_escape_string($this->dbh, $newuser_datetime);
                $newuser_security = mysqli_real_escape_string($this->dbh, 'a:1:{s:13:"administrator";b:1;}');

                $post_wp_username = $this->post['wp_username'];
                $post_wp_password = $this->post['wp_password'];

                $post_wp_mail     = $this->post['wp_mail'];
                $post_wp_nickname = $this->post['wp_nickname'];
                if (empty($post_wp_nickname)) {
                    $post_wp_nickname = $post_wp_username;
                }
                $post_wp_first_name = $this->post['wp_first_name'];
                $post_wp_last_name  = $this->post['wp_last_name'];

                $wp_username   = mysqli_real_escape_string($this->dbh, $post_wp_username);
                $wp_password   = mysqli_real_escape_string($this->dbh, $post_wp_password);
                $wp_mail       = mysqli_real_escape_string($this->dbh, $post_wp_mail);
                $wp_nickname   = mysqli_real_escape_string($this->dbh, $post_wp_nickname);
                $wp_first_name = mysqli_real_escape_string($this->dbh, $post_wp_first_name);
                $wp_last_name  = mysqli_real_escape_string($this->dbh, $post_wp_last_name);

                $newuser1 = @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."users`
                        (`user_login`, `user_pass`, `user_nicename`, `user_email`, `user_registered`, `user_activation_key`, `user_status`, `display_name`)
                        VALUES ('{$wp_username}', MD5('{$wp_password}'), '{$wp_username}', '{$wp_mail}', '{$newuser_datetime}', '', '0', '{$wp_username}')");

                $newuser1_insert_id = intval(mysqli_insert_id($this->dbh));

                $newuser2 = @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta`
                        (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', '".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."capabilities', '{$newuser_security}')");

                $newuser3 = @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta`
                        (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', '".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."user_level', '10')");

                //Misc Meta-Data Settings:
                @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', 'rich_editing', 'true')");
                @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', 'admin_color',  'fresh')");
                @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', 'nickname', '{$wp_nickname}')");
                @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', 'first_name', '{$wp_first_name}')");
                @mysqli_query($this->dbh,
                        "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser1_insert_id}', 'last_name', '{$wp_last_name}')");

                //Add super admin permissions
                if ($this->newSiteIsMultisite()) {
                    $site_admins_query = mysqli_query($this->dbh,
                        "SELECT meta_value FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."sitemeta` WHERE meta_key = 'site_admins'");
                    $site_admins       = mysqli_fetch_row($site_admins_query);
                    $site_admins[0]    = stripslashes($site_admins[0]);
                    $site_admins_array = unserialize($site_admins[0]);

                    array_push($site_admins_array, $this->post['wp_username']);

                    $site_admins_serialized = serialize($site_admins_array);

                    @mysqli_query($this->dbh,
                            "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."sitemeta` SET meta_value = '{$site_admins_serialized}' WHERE meta_key = 'site_admins'");
                    // Adding permission for each sub-site to the newly created user
                    $admin_user_level   = 10; // For wp_2_user_level
                    $sql_values_array   = array();
                    $sql_values_array[] = "('{$newuser1_insert_id}', 'primary_blog', '{$GLOBALS['DUPX_AC']->main_site_id}')";
                    foreach ($GLOBALS['DUPX_AC']->subsites as $subsite_info) {
                        // No need to add permission for main site
                        if ($subsite_info->id == $GLOBALS['DUPX_AC']->main_site_id) {
                            continue;
                        }

                        $cap_meta_key       = $subsite_info->blog_prefix.'capabilities';
                        $sql_values_array[] = "('{$newuser1_insert_id}', '{$cap_meta_key}', '{$newuser_security}')";

                        $user_level_meta_key = $subsite_info->blog_prefix.'user_level';
                        $sql_values_array[]  = "('{$newuser1_insert_id}', '{$user_level_meta_key}', '{$admin_user_level}')";
                    }
                    $sql = "INSERT INTO ".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."usermeta (user_id, meta_key, meta_value) VALUES ".implode(', ', $sql_values_array);
                    @mysqli_query($this->dbh, $sql);
                }

                DUPX_Log::info("\nNEW WP-ADMIN USER:");
                if ($newuser1 && $newuser2 && $newuser3) {
                    DUPX_Log::info("- New username '{$this->post['wp_username']}' was created successfully allong with MU usermeta.");
                } elseif ($newuser1) {
                    DUPX_Log::info("- New username '{$this->post['wp_username']}' was created successfully.");
                } else {
                    $newuser_warnmsg            = "- Failed to create the user '{$this->post['wp_username']}' \n ";
                    $this->report['warnlist'][] = $newuser_warnmsg;

                    $nManager->addFinalReportNotice(array(
                        'shortMsg' => 'New admin user create error',
                        'level' => DUPX_NOTICE_ITEM::HARD_WARNING,
                        'longMsg' => $newuser_warnmsg,
                        'sections' => 'general'
                        ), DUPX_NOTICE_MANAGER::ADD_UNIQUE_UPDATE, 'new-user-create-error');

                    DUPX_Log::info($newuser_warnmsg);
                }
            } else {
                $newuser_warnmsg            = "\nNEW WP-ADMIN USER:\n - Username '{$this->post['wp_username']}' already exists in the database.  Unable to create new account.\n";
                $this->report['warnlist'][] = $newuser_warnmsg;

                $nManager->addFinalReportNotice(array(
                    'shortMsg' => 'New admin user create error',
                    'level' => DUPX_NOTICE_ITEM::SOFT_WARNING,
                    'longMsg' => $newuser_warnmsg,
                    'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_PRE,
                    'sections' => 'general'
                    ), DUPX_NOTICE_MANAGER::ADD_UNIQUE_UPDATE, 'new-user-create-error');

                DUPX_Log::info($newuser_warnmsg);
            }
        }
    }

    public function configurationFileUpdate()
    {
        self::logSectionHeader('CONFIGURATION FILE UPDATES', __FUNCTION__, __LINE__);
        DUPX_Log::incIndent();
        // make sure post data is inizialized
        $this->getPost();

        $nManager = DUPX_NOTICE_MANAGER::getInstance();
        try {
            if (file_exists($this->getWpconfigArkPath())) {
                $confTransformer = $this->getWpConfigTransformer();

                $mu_newDomain     = parse_url($this->post['url_new']);
                $mu_oldDomain     = parse_url($this->post['url_old']);
                $mu_newDomainHost = $mu_newDomain['host'];
                $mu_oldDomainHost = $mu_oldDomain['host'];
                $mu_newUrlPath    = parse_url($this->post['url_new'], PHP_URL_PATH);
                $mu_oldUrlPath    = parse_url($this->post['url_old'], PHP_URL_PATH);

                if (empty($mu_newUrlPath) || ($mu_newUrlPath == '/')) {
                    $mu_newUrlPath = '/';
                } else {
                    $mu_newUrlPath = rtrim($mu_newUrlPath, '/').'/';
                }

                if (empty($mu_oldUrlPath) || ($mu_oldUrlPath == '/')) {
                    $mu_oldUrlPath = '/';
                } else {
                    $mu_oldUrlPath = rtrim($mu_oldUrlPath, '/').'/';
                }

                if ($confTransformer->exists('constant', 'WP_HOME')) {
                    $confTransformer->update('constant', 'WP_HOME', $this->post['url_new'], array('normalize' => true, 'add' => false));
                    DUPX_Log::info('UPDATE WP_HOME '.DUPX_Log::varToString($this->post['url_new']));
                }
                if ($confTransformer->exists('constant', 'WP_SITEURL')) {
                    $confTransformer->update('constant', 'WP_SITEURL', $this->post['url_new'], array('normalize' => true, 'add' => false));
                    DUPX_Log::info('UPDATE WP_SITEURL '.DUPX_Log::varToString($this->post['url_new']));
                }
                if ($confTransformer->exists('constant', 'DOMAIN_CURRENT_SITE')) {
                    $confTransformer->update('constant', 'DOMAIN_CURRENT_SITE', $mu_newDomainHost, array('normalize' => true, 'add' => false));
                    DUPX_Log::info('UPDATE DOMAIN_CURRENT_SITE '.DUPX_Log::varToString($mu_newDomainHost));
                }
                if ($confTransformer->exists('constant', 'PATH_CURRENT_SITE')) {
                    $confTransformer->update('constant', 'PATH_CURRENT_SITE', $mu_newUrlPath, array('normalize' => true, 'add' => false));
                    DUPX_Log::info('UPDATE PATH_CURRENT_SITE '.DUPX_Log::varToString($mu_newUrlPath));
                }

                /**
                 * if is single site clean all mu site define
                 */
                if (!$this->newSiteIsMultisite()) {
                    if ($confTransformer->exists('constant', 'WP_ALLOW_MULTISITE')) {
                        $confTransformer->remove('constant', 'WP_ALLOW_MULTISITE');
                        DUPX_Log::info('REMOVED WP_ALLOW_MULTISITE');
                    }
                    if ($confTransformer->exists('constant', 'ALLOW_MULTISITE')) {
                        $confTransformer->update('constant', 'ALLOW_MULTISITE', 'false', array('add' => false, 'raw' => true, 'normalize' => true));
                        DUPX_Log::info('TRANSFORMER: ALLOW_MULTISITE constant value set to false in WP config file');
                    }
                    if ($confTransformer->exists('constant', 'MULTISITE')) {
                        $confTransformer->update('constant', 'MULTISITE', 'false', array('add' => false, 'raw' => true, 'normalize' => true));
                        DUPX_Log::info('TRANSFORMER: MULTISITE constant value set to false in WP config file');
                    }
                    if ($confTransformer->exists('constant', 'NOBLOGREDIRECT')) {
                        $confTransformer->update('constant', 'NOBLOGREDIRECT', 'false', array('add' => false, 'raw' => true, 'normalize' => true));
                        DUPX_Log::info('TRANSFORMER: NOBLOGREDIRECT constant value set to false in WP config file');
                    }
                    if ($confTransformer->exists('constant', 'SUBDOMAIN_INSTALL')) {
                        $confTransformer->remove('constant', 'SUBDOMAIN_INSTALL');
                        DUPX_Log::info('TRANSFORMER: SUBDOMAIN_INSTALL constant removed from WP config file');
                    }
                    if ($confTransformer->exists('constant', 'VHOST')) {
                        $confTransformer->remove('constant', 'VHOST');
                        DUPX_Log::info('TRANSFORMER: VHOST constant removed from WP config file');
                    }
                    if ($confTransformer->exists('constant', 'SUNRISE')) {
                        $confTransformer->remove('constant', 'SUNRISE');
                        DUPX_Log::info('TRANSFORMER: SUNRISE constant removed from WP config file');
                    }
                }

                $dbname = DUPX_U::getEscapedGenericString($this->post['dbname']);
                $dbuser = DUPX_U::getEscapedGenericString($this->post['dbuser']);
                $dbpass = DUPX_U::getEscapedGenericString($this->post['dbpass']);
                $dbhost = DUPX_U::getEscapedGenericString($this->post['dbhost']);

                $confTransformer->update('constant', 'DB_NAME', $dbname, array('raw' => true));
                DUPX_Log::info('UPDATE DB_NAME '.DUPX_Log::varToString($dbname));

                $confTransformer->update('constant', 'DB_USER', $dbuser, array('raw' => true));
                DUPX_Log::info('UPDATE DB_USER '.DUPX_Log::varToString($dbuser));

                $confTransformer->update('constant', 'DB_PASSWORD', $dbpass, array('raw' => true));
                DUPX_Log::info('UPDATE DB_PASSWORD '.DUPX_Log::varToString('** OBSCURED **'));

                $confTransformer->update('constant', 'DB_HOST', $dbhost, array('raw' => true));
                DUPX_Log::info('UPDATE DB_HOST '.DUPX_Log::varToString($dbhost));

                //SSL CHECKS
                if ($this->post['ssl_admin']) {
                    $confTransformer->update('constant', 'FORCE_SSL_ADMIN', 'true', array('raw' => true, 'normalize' => true));
                    DUPX_Log::info('UPDATE FORCE_SSL_ADMIN '.DUPX_Log::varToString(true));
                } else {
                    if ($confTransformer->exists('constant', 'FORCE_SSL_ADMIN')) {
                        $confTransformer->update('constant', 'FORCE_SSL_ADMIN', 'false', array('raw' => true, 'add' => false, 'normalize' => true));
                        DUPX_Log::info('UPDATE FORCE_SSL_ADMIN '.DUPX_Log::varToString(false));
                    }
                }

                if ($this->post['cache_wp']) {
                    $confTransformer->update('constant', 'WP_CACHE', 'true', array('raw' => true, 'normalize' => true));
                    DUPX_Log::info('UPDATE WP_CACHE '.DUPX_Log::varToString(true));
                } else {
                    if ($confTransformer->exists('constant', 'WP_CACHE')) {
                        $confTransformer->update('constant', 'WP_CACHE', 'false', array('raw' => true, 'add' => false, 'normalize' => true));
                        DUPX_Log::info('UPDATE WP_CACHE '.DUPX_Log::varToString(false));
                    }
                }

                // Cache: [ ] Keep Home Path
                if ($this->post['cache_path']) {
                    if ($confTransformer->exists('constant', 'WPCACHEHOME')) {
                        $wpcachehome_const_val     = $confTransformer->get_value('constant', 'WPCACHEHOME');
                        $wpcachehome_const_val     = DUPX_U::wp_normalize_path($wpcachehome_const_val);
                        $wpcachehome_new_const_val = str_replace($this->post['path_old'], $this->post['path_new'], $wpcachehome_const_val, $count);
                        if ($count > 0) {
                            $confTransformer->update('constant', 'WPCACHEHOME', $wpcachehome_new_const_val, array('normalize' => true));
                            DUPX_Log::info('UPDATE WPCACHEHOME '.DUPX_Log::varToString($wpcachehome_new_const_val));
                        }
                    }
                } else {
                    $confTransformer->remove('constant', 'WPCACHEHOME');
                    DUPX_Log::info('REMOVE WPCACHEHOME');
                }

                if ($GLOBALS['DUPX_AC']->is_outer_root_wp_content_dir) {
                    if (empty($GLOBALS['DUPX_AC']->wp_content_dir_base_name)) {
                        $ret = $confTransformer->remove('constant', 'WP_CONTENT_DIR');
                        DUPX_Log::info('REMOVE WP_CONTENT_DIR');
                        // sometimes WP_CONTENT_DIR const removal failed, so we need to update them
                        if (false === $ret) {
                            $wpContentDir = "dirname(__FILE__).'/wp-content'";
                            $confTransformer->update('constant', 'WP_CONTENT_DIR', $wpContentDir, array('raw' => true, 'normalize' => true));
                            DUPX_Log::info('UPDATE WP_CONTENT_DIR '.DUPX_Log::varToString($wpContentDir));
                        }
                    } else {
                        $wpContentDir = "dirname(__FILE__).'/".$GLOBALS['DUPX_AC']->wp_content_dir_base_name."'";
                        $confTransformer->update('constant', 'WP_CONTENT_DIR', $wpContentDir, array('raw' => true, 'normalize' => true));
                        DUPX_Log::info('UPDATE WP_CONTENT_DIR '.DUPX_Log::varToString($wpContentDir));
                    }
                } elseif ($confTransformer->exists('constant', 'WP_CONTENT_DIR')) {
                    $wp_content_dir_const_val = $confTransformer->get_value('constant', 'WP_CONTENT_DIR');
                    $wp_content_dir_const_val = DUPX_U::wp_normalize_path($wp_content_dir_const_val);
                    $new_path                 = str_replace($this->post['path_old'], $this->post['path_new'], $wp_content_dir_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WP_CONTENT_DIR', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_CONTENT_DIR '.DUPX_Log::varToString($new_path));
                    }
                }

                //WP_CONTENT_URL
                // '/' added to prevent word boundary with domains that have the same root path
                if ($GLOBALS['DUPX_AC']->is_outer_root_wp_content_dir) {
                    if (empty($GLOBALS['DUPX_AC']->wp_content_dir_base_name)) {
                        $ret = $confTransformer->remove('constant', 'WP_CONTENT_URL');
                        DUPX_Log::info('REMOVE WP_CONTENT_URL');
                        // sometimes WP_CONTENT_DIR const removal failed, so we need to update them
                        if (false === $ret) {
                            $new_url = $this->post['url_new'].'/wp-content';
                            $confTransformer->update('constant', 'WP_CONTENT_URL', $new_url, array('raw' => true, 'normalize' => true));
                            DUPX_Log::info('UPDATE WP_CONTENT_URL '.DUPX_Log::varToString($new_url));
                        }
                    } else {
                        $new_url = $this->post['url_new'].'/'.$GLOBALS['DUPX_AC']->wp_content_dir_base_name;
                        $confTransformer->update('constant', 'WP_CONTENT_URL', $new_url, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_CONTENT_URL '.DUPX_Log::varToString($new_url));
                    }
                } elseif ($confTransformer->exists('constant', 'WP_CONTENT_URL')) {
                    $wp_content_url_const_val = $confTransformer->get_value('constant', 'WP_CONTENT_URL');
                    $new_path                 = str_replace($this->post['url_old'].'/', $this->post['url_new'].'/', $wp_content_url_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WP_CONTENT_URL', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_CONTENT_URL '.DUPX_Log::varToString($new_path));
                    }
                }

                //WP_TEMP_DIR
                if ($confTransformer->exists('constant', 'WP_TEMP_DIR')) {
                    $wp_temp_dir_const_val = $confTransformer->get_value('constant', 'WP_TEMP_DIR');
                    $wp_temp_dir_const_val = DUPX_U::wp_normalize_path($wp_temp_dir_const_val);
                    $new_path              = str_replace($this->post['path_old'], $this->post['path_new'], $wp_temp_dir_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WP_TEMP_DIR', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_TEMP_DIR '.DUPX_Log::varToString($new_path));
                    }
                }

                // WP_PLUGIN_DIR
                if ($confTransformer->exists('constant', 'WP_PLUGIN_DIR')) {
                    $wp_plugin_dir_const_val = $confTransformer->get_value('constant', 'WP_PLUGIN_DIR');
                    $wp_plugin_dir_const_val = DUPX_U::wp_normalize_path($wp_plugin_dir_const_val);
                    $new_path                = str_replace($this->post['path_old'], $this->post['path_new'], $wp_plugin_dir_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WP_PLUGIN_DIR', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_PLUGIN_DIR '.DUPX_Log::varToString($new_path));
                    }
                }

                // WP_PLUGIN_URL
                if ($confTransformer->exists('constant', 'WP_PLUGIN_URL')) {
                    $wp_plugin_url_const_val = $confTransformer->get_value('constant', 'WP_PLUGIN_URL');
                    $new_path                = str_replace($this->post['url_old'].'/', $this->post['url_new'].'/', $wp_plugin_url_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WP_PLUGIN_URL', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WP_PLUGIN_URL '.DUPX_Log::varToString($new_path));
                    }
                }

                // WPMU_PLUGIN_DIR
                if ($confTransformer->exists('constant', 'WPMU_PLUGIN_DIR')) {
                    $wpmu_plugin_dir_const_val = $confTransformer->get_value('constant', 'WPMU_PLUGIN_DIR');
                    $wpmu_plugin_dir_const_val = DUPX_U::wp_normalize_path($wpmu_plugin_dir_const_val);
                    $new_path                  = str_replace($this->post['path_old'], $this->post['path_new'], $wpmu_plugin_dir_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WPMU_PLUGIN_DIR', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WPMU_PLUGIN_DIR '.DUPX_Log::varToString($new_path));
                    }
                }

                // WPMU_PLUGIN_URL
                if ($confTransformer->exists('constant', 'WPMU_PLUGIN_URL')) {
                    $wpmu_plugin_url_const_val = $confTransformer->get_value('constant', 'WPMU_PLUGIN_URL');
                    $new_path                  = str_replace($this->post['url_old'].'/', $this->post['url_new'].'/', $wpmu_plugin_url_const_val, $count);
                    if ($count > 0) {
                        $confTransformer->update('constant', 'WPMU_PLUGIN_URL', $new_path, array('normalize' => true));
                        DUPX_Log::info('UPDATE WPMU_PLUGIN_URL '.DUPX_Log::varToString($new_path));
                    }
                }

                $licence_type = $GLOBALS['DUPX_AC']->getLicenseType();
                if ($licence_type >= DUPX_LicenseType::Freelancer) {
                    if ($this->post['auth_keys_and_salts']) {
                        $need_to_change_const_keys = array(
                            'AUTH_KEY',
                            'SECURE_AUTH_KEY',
                            'LOGGED_IN_KEY',
                            'NONCE_KEY',
                            'AUTH_SALT',
                            'SECURE_AUTH_SALT',
                            'LOGGED_IN_SALT',
                            'NONCE_SALT',
                        );
                        foreach ($need_to_change_const_keys as $const_key) {
                            $is_const_key_exists = $confTransformer->exists('constant', $const_key);
                            $key                 = DUPX_WPConfig::generatePassword(64, true, true);

                            if ($is_const_key_exists) {
                                $confTransformer->update('constant', $const_key, $key);
                                DUPX_Log::info('UPDATE '.$const_key.' '.DUPX_Log::varToString('**OBSCURED**'));
                            } else {
                                $confTransformer->add('constant', $const_key, $key);
                                DUPX_Log::info('ADD '.$const_key.' '.DUPX_Log::varToString('**OBSCURED**'));
                            }
                        }
                    }
                }

                // COOKIE_DOMAIN
                if (!empty($this->post['cookie_domain'])) {
                    $confTransformer->update('constant', 'COOKIE_DOMAIN', $this->post['cookie_domain'], array('normalize' => true));
                    DUPX_Log::info('UPDATE COOKIE_DOMAIN'.DUPX_Log::varToString($this->post['cookie_domain']));
                } else {
                    if ($confTransformer->exists('constant', 'COOKIE_DOMAIN')) {
                        $confTransformer->remove('constant', 'COOKIE_DOMAIN');
                        DUPX_Log::info('REMOVE COOKIE_DOMAIN');
                    }
                }

                // AutoSave Interval
                if (!empty($this->post['autosave_interval'])) {
                    $confTransformer->update('constant', 'AUTOSAVE_INTERVAL', (string) $this->post['autosave_interval'], array('raw' => true, 'normalize' => true));
                    DUPX_Log::info('UPDATE AUTOSAVE_INTERVAL '.DUPX_Log::varToString((string) $this->post['autosave_interval']));
                } else {
                    if ($confTransformer->exists('constant', 'AUTOSAVE_INTERVAL')) {
                        $confTransformer->remove('constant', 'AUTOSAVE_INTERVAL');
                        DUPX_Log::info('REMOVE AUTOSAVE_INTERVAL');
                    }
                }

                // POST REVISIONS
                switch ($this->post['wp_post_revisions']) {
                    case 'remove':
                        if ($confTransformer->exists('constant', 'WP_POST_REVISIONS')) {
                            $confTransformer->remove('constant', 'WP_POST_REVISIONS');
                            DUPX_Log::info('REMOVE WP_POST_REVISIONS');
                        }
                        break;
                    case 'no-action':
                        break;
                    default:
                        if (is_bool($this->post['wp_post_revisions'])) {
                            $wp_rev = DUPX_U::boolToStr($this->post['wp_post_revisions']);
                        } else {
                            $wp_rev = (string) $this->post['wp_post_revisions'];
                        }
                        $confTransformer->update('constant', 'WP_POST_REVISIONS', $wp_rev, array('raw' => true, 'normalize' => true));
                        DUPX_Log::info('UPDATE WP_POST_REVISIONS '.DUPX_Log::varToString($wp_rev));
                }

                $is_wp_debug_exists = $confTransformer->exists('constant', 'WP_DEBUG');
                if ($is_wp_debug_exists || $this->post['wp_debug']) {
                    $confTransformer->update('constant', 'WP_DEBUG', DUPX_U::boolToStr($this->post['wp_debug']), array('raw' => true));
                    DUPX_Log::info('UPDATE WP_DEBUG '.DUPX_Log::varToString($this->post['wp_debug']));
                }

                $is_wp_debug_log_exists = $confTransformer->exists('constant', 'WP_DEBUG_LOG');
                if ($is_wp_debug_log_exists || $this->post['wp_debug_log']) {
                    $confTransformer->update('constant', 'WP_DEBUG_LOG', DUPX_U::boolToStr($this->post['wp_debug_log']), array('raw' => true));
                    DUPX_Log::info('UPDATE WP_DEBUG_LOG '.DUPX_Log::varToString($this->post['wp_debug_log']));
                }

                // WP_DEBUG_DISPLAY
                $is_wp_debug_display_exists = $confTransformer->exists('constant', 'WP_DEBUG_DISPLAY');
                if ($is_wp_debug_display_exists || $this->post['wp_debug_display']) {
                    $confTransformer->update('constant', 'WP_DEBUG_DISPLAY', DUPX_U::boolToStr($this->post['wp_debug_display']), array('raw' => true));
                    DUPX_Log::info('UPDATE WP_DEBUG_DISPLAY '.DUPX_Log::varToString($this->post['wp_debug_display']));
                }

                // SCRIPT_DEBUG
                $is_script_debug_exists = $confTransformer->exists('constant', 'SCRIPT_DEBUG');
                if ($is_script_debug_exists || $this->post['script_debug']) {
                    $confTransformer->update('constant', 'SCRIPT_DEBUG', DUPX_U::boolToStr($this->post['script_debug']), array('raw' => true));
                    DUPX_Log::info('UPDATE SCRIPT_DEBUG '.DUPX_Log::varToString($this->post['script_debug']));
                }

                // SAVEQUERIES  savequeries
                $is_savequeries_exists = $confTransformer->exists('constant', 'SAVEQUERIES');
                if ($is_savequeries_exists || $this->post['savequeries']) {
                    $confTransformer->update('constant', 'SAVEQUERIES', DUPX_U::boolToStr($this->post['savequeries']), array('raw' => true));
                    DUPX_Log::info('UPDATE SAVEQUERIES '.DUPX_Log::varToString($this->post['savequeries']));
                }

                // WP_MEMORY_LIMIT
                if (!empty($this->post['wp_memory_limit'])) {
                    $confTransformer->update('constant', 'WP_MEMORY_LIMIT', $this->post['wp_memory_limit'], array('normalize' => true));
                    DUPX_Log::info('UPDATE WP_MEMORY_LIMIT '.DUPX_Log::varToString($this->post['wp_memory_limit']));
                } else {
                    $confTransformer->remove('constant', 'WP_MEMORY_LIMIT');
                    DUPX_Log::info('REMOVE WP_MEMORY_LIMIT');
                }

                // WP_MAX_MEMORY_LIMIT
                if (!empty($this->post['wp_max_memory_limit'])) {
                    $confTransformer->update('constant', 'WP_MAX_MEMORY_LIMIT', $this->post['wp_max_memory_limit'], array('normalize' => true));
                    DUPX_Log::info('UPDATE WP_MAX_MEMORY_LIMIT '.DUPX_Log::varToString($this->post['wp_max_memory_limit']));
                } else {
                    $confTransformer->remove('constant', 'WP_MAX_MEMORY_LIMIT');
                    DUPX_Log::info('REMOVE WP_MAX_MEMORY_LIMIT');
                }

                // Disable File modification DISALLOW_FILE_EDIT
                if ($this->post['disallow_file_edit']) {
                    $confTransformer->update('constant', 'DISALLOW_FILE_EDIT', 'true', array('raw' => true, 'normalize' => true));
                    DUPX_Log::info('UPDATE DISALLOW_FILE_EDIT '.DUPX_Log::varToString(true));
                } else {
                    $confTransformer->remove('constant', 'DISALLOW_FILE_EDIT');
                    DUPX_Log::info('REMOVE DISALLOW_FILE_EDIT');
                }

                // WP_AUTO_UPDATE_CORE
                if (!is_null($this->post['wp_auto_update_core'])) {
                    $pass_arr = array('normalize' => true);
                    if (is_bool($this->post['wp_auto_update_core'])) {
                        $wp_auto_update_core_val = DUPX_U::boolToStr($this->post['wp_auto_update_core']);
                        $pass_arr['raw']         = true;
                    } else {
                        $wp_auto_update_core_val = $this->post['wp_auto_update_core'];
                        $pass_arr['raw']         = false;
                    }
                    $confTransformer->update('constant', 'WP_AUTO_UPDATE_CORE', $wp_auto_update_core_val, $pass_arr);
                    DUPX_Log::info('UPDATE WP_AUTO_UPDATE_CORE '.DUPX_Log::varToString($wp_auto_update_core_val));
                } else {
                    $confTransformer->remove('constant', 'WP_AUTO_UPDATE_CORE');
                    DUPX_Log::info('REMOVE WP_AUTO_UPDATE_CORE');
                }

                DUPX_Log::info("\nUPDATED WP-CONFIG ARK FILE: - 'dup-wp-config-arc__[HASH].txt'");
            } else {
                DUPX_Log::info("WP-CONFIG ARK FILE NOT FOUND");
                DUPX_Log::info("WP-CONFIG ARK FILE:\n - 'dup-wp-config-arc__[HASH].txt'");
                DUPX_Log::info("SKIP FILE UPDATES\n");

                $shortMsg = 'wp-config.php not found';
                $longMsg  = <<<LONGMSG
Error updating wp-config file.<br>
The installation is finished but check the wp-config.php file and manually update the incorrect values.
LONGMSG;
                /*    $nManager->addNextStepNotice(array(
                  'shortMsg' => $shortMsg,
                  'level' => DUPX_NOTICE_ITEM::CRITICAL,

                  ), DUPX_NOTICE_MANAGER::ADD_UNIQUE , 'wp-config-transformer-exception'); */
                $nManager->addFinalReportNotice(array(
                    'shortMsg' => $shortMsg,
                    'level' => DUPX_NOTICE_ITEM::HARD_WARNING,
                    'longMsg' => $longMsg,
                    'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_HTML,
                    'sections' => 'general'
                    ), DUPX_NOTICE_MANAGER::ADD_UNIQUE, 'wp-config-transformer-exception');
            }
        } catch (Exception $e) {
            $shortMsg = 'wp-config.php transformer:'.$e->getMessage();
            $longMsg  = <<<LONGMSG
Error updating wp-config file.<br>
The installation is finished but check the wp-config.php file and manually update the incorrect values.
LONGMSG;
            /*    $nManager->addNextStepNotice(array(
              'shortMsg' => $shortMsg,
              'level' => DUPX_NOTICE_ITEM::CRITICAL,

              ), DUPX_NOTICE_MANAGER::ADD_UNIQUE , 'wp-config-transformer-exception'); */
            $nManager->addFinalReportNotice(array(
                'shortMsg' => $shortMsg,
                'level' => DUPX_NOTICE_ITEM::CRITICAL,
                'longMsg' => $longMsg,
                'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_HTML,
                'sections' => 'general'
                ), DUPX_NOTICE_MANAGER::ADD_UNIQUE, 'wp-config-transformer-exception');

            DUPX_Log::info("WP-CONFIG TRANSFORMER EXCEPTION\n".$e->getTraceAsString());
        }
        DUPX_Log::resetIndent();
    }

    public function htaccessUpdate()
    {
        self::logSectionHeader('HTACCESS UPDATE', __FUNCTION__, __LINE__);

        // make sure dbConnection is inizialized
        $this->dbConnection();

        if ($this->post['retain_config']) {
            $new_htaccess_name = '.htaccess';
        } else {
            $new_htaccess_name = 'htaccess.orig'.rand();
        }

        if (DUPX_ServerConfig::renameHtaccess($GLOBALS['DUPX_ROOT'], $new_htaccess_name)) {
            DUPX_Log::info("\nReseted original .htaccess content from htaccess.orig");
        }

        //Web Server Config Updates
        if (!isset($this->post['url_new']) || $this->post['retain_config']) {
            DUPX_Log::info("\nNOTICE: Retaining the original .htaccess, .user.ini and web.config files may cause");
            DUPX_Log::info("issues with the initial setup of your site.  If you run into issues with your site or");
            DUPX_Log::info("during the install process please uncheck the 'Config Files' checkbox labeled:");
            DUPX_Log::info("'Retain original .htaccess, .user.ini and web.config' and re-run the installer.");
        } else {
            DUPX_ServerConfig::setup($this->post['action_mu_mode'], $GLOBALS['DUPX_AC']->mu_generation, $this->dbh, $GLOBALS['DUPX_ROOT']);
        }
    }

    public function generalUpdateAndCleanup()
    {
        self::logSectionHeader('GENERAL UPDATES & CLEANUP', __FUNCTION__, __LINE__);
        // make sure dbConnection is inizialized
        $this->dbConnection();

        $blog_name        = mysqli_real_escape_string($this->dbh, $this->post['blogname']);
        $plugin_list      = $this->post['plugins'];
        $mu_newDomain     = parse_url($this->post['url_new']);
        $mu_oldDomain     = parse_url($this->post['url_old']);
        $mu_newDomainHost = $mu_newDomain['host'];
        $mu_oldDomainHost = $mu_oldDomain['host'];

        // Force Duplicator Pro active so we the security cleanup will be available
        if ($this->newSiteIsMultisite()) {
            $multisite_plugin_list = array();
            foreach ($plugin_list as $get_plugin) {
                $multisite_plugin_list[$get_plugin] = time();
            }

            if (!array_key_exists('duplicator-pro/duplicator-pro.php', $multisite_plugin_list)) {
                $multisite_plugin_list['duplicator-pro/duplicator-pro.php'] = time();
            }

            $serial_plugin_list = @serialize($multisite_plugin_list);
        } else {
            if (!in_array('duplicator-pro/duplicator-pro.php', $plugin_list)) {
                $plugin_list[] = 'duplicator-pro/duplicator-pro.php';
            }
            $serial_plugin_list = @serialize($plugin_list);
        }

        /** FINAL UPDATES: Must happen after the global replace to prevent double pathing
          http://xyz.com/abc01 will become http://xyz.com/abc0101  with trailing data */
        mysqli_query($this->dbh,
            "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` SET option_value = '".mysqli_real_escape_string($this->dbh, $blog_name)."' WHERE option_name = 'blogname' ");
        if ($this->newSiteIsMultisite()) {
            mysqli_query($this->dbh,
                "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."sitemeta` SET meta_value = '".mysqli_real_escape_string($this->dbh, $serial_plugin_list)."'  WHERE meta_key = 'active_sitewide_plugins' ");
        } else {
            mysqli_query($this->dbh,
                "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` SET option_value = '".mysqli_real_escape_string($this->dbh, $serial_plugin_list)."'  WHERE option_name = 'active_plugins' ");
        }
        mysqli_query($this->dbh,
            "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` SET option_value = '".mysqli_real_escape_string($this->dbh, $this->post['url_new'])."'  WHERE option_name = 'home' ");
        mysqli_query($this->dbh,
            "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` SET option_value = '".mysqli_real_escape_string($this->dbh, $this->post['siteurl'])."'  WHERE option_name = 'siteurl' ");
        mysqli_query($this->dbh,
            "INSERT INTO `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` (option_value, option_name) VALUES('".mysqli_real_escape_string($this->dbh,
                $this->post['exe_safe_mode'])."','duplicator_pro_exe_safe_mode')");
        //Reset the postguid data
        if ($this->post['postguid']) {
            mysqli_query($this->dbh,
                "UPDATE `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."posts` SET guid = REPLACE(guid, '".mysqli_real_escape_string($this->dbh, $this->post['url_new'])."', '".mysqli_real_escape_string($this->dbh,
                    $this->post['url_old'])."')");
            $update_guid = @mysqli_affected_rows($this->dbh) or 0;
            DUPX_Log::info("Reverted '{$update_guid}' post guid columns back to '{$this->post['url_old']}'");
        }

        //SCHEDULE STORAGE CLEANUP
        if (($this->post['empty_schedule_storage']) == true || (DUPX_U::$on_php_53_plus == false)) {

            $dbdelete_count  = 0;
            $dbdelete_count1 = 0;
            $dbdelete_count2 = 0;

            @mysqli_query($this->dbh, "DELETE FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."duplicator_pro_entities` WHERE `type` = 'DUP_PRO_Storage_Entity'");
            $dbdelete_count1 = @mysqli_affected_rows($this->dbh);

            @mysqli_query($this->dbh, "DELETE FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."duplicator_pro_entities` WHERE `type` = 'DUP_PRO_Schedule_Entity'");
            $dbdelete_count2 = @mysqli_affected_rows($this->dbh);

            $dbdelete_count = (abs($dbdelete_count1) + abs($dbdelete_count2));
            DUPX_Log::info("- Removed '{$dbdelete_count}' schedule storage items");
        }
    }

    public function noticeTest()
    {
        self::logSectionHeader('NOTICES TEST', __FUNCTION__, __LINE__);
        // make sure dbConnection is inizialized
        $this->dbConnection();

        $nManager = DUPX_NOTICE_MANAGER::getInstance();
        if (file_exists($this->getWpconfigArkPath())) {
            $wpconfig_ark_contents = file_get_contents($this->getWpconfigArkPath());
            $config_vars           = array('WPCACHEHOME', 'COOKIE_DOMAIN', 'WP_SITEURL', 'WP_HOME', 'WP_TEMP_DIR');
            $config_found          = DUPX_U::getListValues($config_vars, $wpconfig_ark_contents);

            //Files
            if (!empty($config_found)) {
                $msg                        = "WP-CONFIG NOTICE: The wp-config.php has following values set [".implode(", ", $config_found)."].  \n";
                $msg                        .= "Please validate these values are correct by opening the file and checking the values.\n";
                $msg                        .= "See the codex link for more details: https://codex.wordpress.org/Editing_wp-config.php";
                // old system
                $this->report['warnlist'][] = $msg;
                DUPX_Log::info($msg);

                $nManager->addFinalReportNotice(array(
                    'shortMsg' => 'wp-config notice',
                    'level' => DUPX_NOTICE_ITEM::NOTICE,
                    'longMsg' => $msg,
                    'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_PRE,
                    'sections' => 'general'
                ));
            }

            //-- Finally, back up the old wp-config and rename the new one
            $wpconfig_path = "{$GLOBALS['DUPX_ROOT']}/wp-config.php";
            if (copy($this->getWpconfigArkPath(), $wpconfig_path) === false) {
                DUPX_Log::error("ERROR: Unable to copy 'dup-wp-config-arc__[HASH].txt' to 'wp-config.php'.\n".
                    "Check server permissions for more details see FAQ: https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-055-q");
            }
        } else {
            $msg                        = "WP-CONFIG NOTICE: <b>wp-config.php not found.</b><br><br>";
            $msg                        .= "No action on the wp-config was possible.<br>";
            $msg                        .= "Be sure to insert a properly modified wp-config for correct wordpress operation.";
            $this->report['warnlist'][] = $msg;

            $nManager->addFinalReportNotice(array(
                'shortMsg' => 'wp-config not found',
                'level' => DUPX_NOTICE_ITEM::HARD_WARNING,
                'longMsg' => $msg,
                'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_HTML,
                'sections' => 'general'
                ), DUPX_NOTICE_MANAGER::ADD_UNIQUE_UPDATE, 'wp-config-not-found');

            DUPX_Log::info($msg);
        }

        //Database
        $result = @mysqli_query($this->dbh,
                "SELECT option_value FROM `".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options` WHERE option_name IN ('upload_url_path','upload_path')");
        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                if (strlen($row[0])) {
                    $msg = "MEDIA SETTINGS NOTICE: The table '".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options' has at least one the following values ['upload_url_path','upload_path'] \n";
                    $msg .= "set please validate settings. These settings can be changed in the wp-admin by going to /wp-admin/options.php'";

                    $this->report['warnlist'][] = $msg;
                    DUPX_Log::info($msg);

                    $nManager->addFinalReportNotice(array(
                        'shortMsg' => 'Media settings notice',
                        'level' => DUPX_NOTICE_ITEM::SOFT_WARNING,
                        'longMsg' => $msg,
                        'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_PRE,
                        'sections' => 'general'
                        ), DUPX_NOTICE_MANAGER::ADD_UNIQUE_UPDATE, 'media-settings-notice');

                    break;
                }
            }
        }

        $sql = "INSERT into ".mysqli_real_escape_string($this->dbh, $GLOBALS['DUPX_AC']->wp_tableprefix)."options (option_name, option_value) VALUES ('duplicator_pro_migration', '1');";
        @mysqli_query($this->dbh, $sql);

        if (empty($this->report['warnlist'])) {
            DUPX_Log::info("No General Notices Found\n");
        }
    }

    public function cleanupTmpFiles()
    {
        self::logSectionHeader('CLEANUP TMP FILES', __FUNCTION__, __LINE__);
        // make sure post data is inizialized
        $this->getPost();

        //Cleanup any tmp files a developer may have forgotten about
        //Lets be proactive for the developer just in case
        $wpconfig_path_bak   = "{$GLOBALS['DUPX_ROOT']}/wp-config.bak";
        $wpconfig_path_old   = "{$GLOBALS['DUPX_ROOT']}/wp-config.old";
        $wpconfig_path_org   = "{$GLOBALS['DUPX_ROOT']}/wp-config.org";
        $wpconfig_path_orig  = "{$GLOBALS['DUPX_ROOT']}/wp-config.orig";
        $wpconfig_safe_check = array($wpconfig_path_bak, $wpconfig_path_old, $wpconfig_path_org, $wpconfig_path_orig);
        foreach ($wpconfig_safe_check as $file) {
            if (file_exists($file)) {
                $tmp_newfile = $file.uniqid('_');
                if (rename($file, $tmp_newfile) === false) {
                    DUPX_Log::info("WARNING: Unable to rename '{$file}' to '{$tmp_newfile}'");
                }
            }
        }

        if (isset($this->post['remove_redundant']) && $this->post['remove_redundant']) {
            $licence_type = $GLOBALS['DUPX_AC']->getLicenseType();
            if ($licence_type >= DUPX_LicenseType::Freelancer) {
                // Need to load if user selected redundant-data checkbox
                require_once($GLOBALS['DUPX_INIT'].'/classes/utilities/class.u.remove.redundant.data.php');

                $new_content_dir = (substr($this->post['path_new'], -1, 1) == '/') ? "{$this->post['path_new']}{$GLOBALS['DUPX_AC']->relative_content_dir}" : "{$this->post['path_new']}/{$GLOBALS['DUPX_AC']->relative_content_dir}";

                try {
                    DUPX_Log::info("#### Recursively deleting redundant plugins");
                    DUPX_RemoveRedundantData::deleteRedundantPlugins($new_content_dir, $GLOBALS['DUPX_AC'], $this->post['subsite_id']);
                } catch (Exception $ex) {
                    // Technically it can complete but this should be brought to their attention
                    DUPX_Log::error("Problem deleting redundant plugins");
                }

                try {
                    DUPX_Log::info("#### Recursively deleting redundant themes");
                    DUPX_RemoveRedundantData::deleteRedundantThemes($new_content_dir, $GLOBALS['DUPX_AC'], $this->post['subsite_id']);
                } catch (Exception $ex) {
                    // Technically it can complete but this should be brought to their attention
                    DUPX_Log::error("Problem deleting redundant themes");
                }
            }
            if ($GLOBALS['DUPX_STATE']->mode == DUPX_InstallerMode::OverwriteInstall) {
                DUPX_U::maintenanceMode(true, $GLOBALS['DUPX_ROOT']);
            }
        }
    }

    public function finalReportNotices()
    {
        self::logSectionHeader('FINAL REPORT NOTICES', __FUNCTION__, __LINE__);

        $this->wpConfigFinalReport();
        $this->htaccessFinalReport();
    }

    private function htaccessFinalReport()
    {
        $nManager = DUPX_NOTICE_MANAGER::getInstance();

        $orig = file_get_contents($this->getOrigHtaccessPath());
        $new  = file_get_contents($GLOBALS['DUPX_ROOT'].'/.htaccess');

        $lightBoxContent = '<div class="row-cols-2">'.
            '<div class="col col-1"><b>Original .htaccess</b><pre>'.htmlspecialchars($orig).'</pre></div>'.
            '<div class="col col-2"><b>New .htaccess</b><pre>'.htmlspecialchars($new).'</pre></div>'.
            '</div>';
        $longMsg         = DUPX_U_Html::getLigthBox('.htaccess changes', 'HTACCESS COMPARE', $lightBoxContent, false);

        $nManager->addFinalReportNotice(array(
            'shortMsg' => 'htaccess changes',
            'level' => DUPX_NOTICE_ITEM::INFO,
            'longMsg' => $longMsg,
            'sections' => 'changes',
            'open' => true,
            'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_HTML
            ), DUPX_NOTICE_MANAGER::ADD_UNIQUE, 'htaccess-changes');
    }

    private function wpConfigFinalReport()
    {
        $nManager = DUPX_NOTICE_MANAGER::getInstance();

        if (($orig = file_get_contents($this->getOrigWpConfigPath())) === false) {
            $orig = 'Can read origin wp-config.php file';
        } else {
            $orig = $this->obscureWpConfig($orig);
        }

        if (($new = file_get_contents($GLOBALS['DUPX_ROOT'].'/wp-config.php')) === false) {
            $new = 'Can read wp-config.php file';
        } else {
            $new = $this->obscureWpConfig($new);
        }

        $lightBoxContent = '<div class="row-cols-2">'.
            '<div class="col col-1"><b>Original wp-config.php</b><pre>'.htmlspecialchars($orig).'</pre></div>'.
            '<div class="col col-2"><b>New wp-config.php</b><pre>'.htmlspecialchars($new).'</pre></div>'.
            '</div>';
        $longMsg         = DUPX_U_Html::getLigthBox('wp-config.php changes', 'WP-CONFIG.PHP COMPARE', $lightBoxContent, false);

        $nManager->addFinalReportNotice(array(
            'shortMsg' => 'wp-config.php changes',
            'level' => DUPX_NOTICE_ITEM::INFO,
            'longMsg' => $longMsg,
            'sections' => 'changes',
            'open' => true,
            'longMsgMode' => DUPX_NOTICE_ITEM::MSG_MODE_HTML
            ), DUPX_NOTICE_MANAGER::ADD_UNIQUE, 'wp-config-changes');
    }

    private function obscureWpConfig($src)
    {
        $transformer = new WPConfigTransformerSrc($src);
        $obsKeys     = array('DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT');
        foreach ($obsKeys as $key) {
            if ($transformer->exists('constant', $key)) {
                $transformer->update('constant', $key, '**OBSCURED**');
            }
        }

        return $transformer->getSrc();
    }

    public function chunkStop($progressPerc, $position)
    {
        // make sure post data is inizialized
        $this->getPost();

        $this->closeDbConnection();

        $ajax3_sum = DUPX_U::elapsedTime(DUPX_U::getMicrotime(), $this->timeStart);
        DUPX_Log::info("\nSTEP-3 CHUNK STOP @ ".@date('h:i:s')." - RUNTIME: {$ajax3_sum} \n\n");

        $this->report['chunk']         = 1;
        $this->report['chunkPos']      = $position;
        $this->report['pass']          = 0;
        $this->report['progress_perc'] = $progressPerc;
    }

    public function complete()
    {
        // make sure post data is inizialized
        $this->getPost();
        $this->closeDbConnection();

        $ajax3_sum = DUPX_U::elapsedTime(DUPX_U::getMicrotime(), $this->timeStart);
        DUPX_Log::info("\nSTEP-3 COMPLETE @ ".@date('h:i:s')." - RUNTIME: {$ajax3_sum} \n\n");

        $this->fullReport              = true;
        $this->report['pass']          = 1;
        $this->report['chunk']         = 0;
        $this->report['chunkPos']      = null;
        $this->report['progress_perc'] = 100;
        // error_reporting($ajax3_error_level);
    }

    public function error($message)
    {
        // make sure post data is inizialized
        $this->getPost();

        $this->closeDbConnection();

        $ajax3_sum = DUPX_U::elapsedTime(DUPX_U::getMicrotime(), $this->timeStart);
        DUPX_Log::info("\nSTEP-3 ERROR @ ".@date('h:i:s')." - RUNTIME: {$ajax3_sum} \n\n");

        $this->report['pass']          = -1;
        $this->report['chunk']         = 0;
        $this->report['chunkPos']      = null;
        $this->report['error_message'] = $message;
    }

    protected function __clone()
    {

    }

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}