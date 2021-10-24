<?php
defined("DUPXABSPATH") or die("");

/**
 * DUPX_cPanel  
 * Wrapper Class for cPanel API  */
class DUPX_Server
{
	/**
	 * Returns true if safe mode is enabled
	 */
	public static $php_safe_mode_on = false;

	/**
	 * The servers current PHP version
	 */
	public static $php_version = 0;

	/**
	 * The minimum PHP version the installer will support
	 */
	public static $php_version_min = "5.2.7";

	/**
	 * Is the current servers version of PHP safe to use with the installer
	 */
	public static $php_version_safe = false;

	/**
     * Is PHP 5.3 or better running
     */
    public static $php_version_53_plus;

	/**
	 * A list of the core WordPress directories
	 */
	public static $wpCoreDirsList = "wp-admin,wp-includes,wp-content";

	public static function _init()
	{
		self::$php_safe_mode_on	 = in_array(strtolower(@ini_get('safe_mode')), array('on', 'yes', 'true', 1, "1"));
		self::$php_version		 = phpversion();
		self::$php_version_safe	 = (version_compare(phpversion(), self::$php_version_min) >= 0);
		self::$php_version_53_plus	= version_compare(PHP_VERSION, '5.3.0') >= 0;
	}

	/**
	 *  Display human readable byte sizes
	 *  @param string $size		The size in bytes
	 */
	public static function is_dir_writable($path)
	{
		if (!@is_writeable($path)) return false;

		$ret = true;

		if (is_dir($path)) {
			if ($dh = @opendir($path)) {
				closedir($dh);
			} else {
				$ret = false;
			}
		}

		if ($ret && $GLOBALS['DUPX_STATE']->mode === DUPX_InstallerMode::OverwriteInstall) {
			$setFilePermission = self::setFilePermission($path);
			if (!$setFilePermission['ret']) {
				$ret = false;
			}
		}

		return array(
					'ret' => $ret,
					'failedObjects' => isset($setFilePermission['failedObjects']) ? $setFilePermission['failedObjects'] : array(),
		);
	}

	public static function setFilePermission($path)
    {
		$set_file_perms = true;
		$set_dir_perms = true;
		$file_perms_value = 0644;
		$dir_perms_value = 0755;

		$objects = new RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($path),
			RecursiveIteratorIterator::SELF_FIRST);
		
		$ignore_paths = array(
			$path.DIRECTORY_SEPARATOR.'installer.php',
		);
		

		$ignore_path_prefixes = array(
			$path.DIRECTORY_SEPARATOR.'dup_installer',
			$path.DIRECTORY_SEPARATOR.'.', // any special directory
			$GLOBALS['FW_PACKAGE_PATH'],
		);

		$ret = true;
		$failedObjects = array();
		foreach ($objects as $name => $object) {

			if (in_array($name, $ignore_paths))  continue;

			$last_char_of_path = substr($name, -1);
			if ('.' == $last_char_of_path)  continue;

			$is_continue = false;
			foreach($ignore_path_prefixes as $ignore_path_prefix) {
				if (0 === stripos($name, $ignore_path_prefix)) {
					$is_continue = true;
				}
			}

			if ($is_continue)  continue;
            
			if ($set_file_perms && is_file($name) && !is_dir($name)) {
				$retVal = @chmod($name, $file_perms_value);
				if (!$retVal) {
					$failedObjects[] = $name;
					if ($ret) {
						$ret = false;
					}
					$failedObjectsCount = count($failedObjects);
					if ($failedObjectsCount > $GLOBALS['DISPLAY_MAX_OBJECTS_FAILED_TO_SET_PERM']) {
						break;
					}
				}
			} else {
				if ($set_dir_perms && is_dir($name)) {
					$retVal = @chmod($name, $dir_perms_value);
					if (!$retVal) {
						$failedObjects[] = $name;
                        if ($ret) {
                            $ret = false;
						}
						$failedObjectsCount = count($failedObjects);
						if ($failedObjectsCount > $GLOBALS['DISPLAY_MAX_OBJECTS_FAILED_TO_SET_PERM']) {
							break;
						}
					}
				}
			}			        			
		}

		return array(
					'ret' => $ret,
					'failedObjects' => $failedObjects,
		);
    }

	/**
	 *  Can this server process in shell_exec mode
	 *  @return bool
	 */
	public static function is_shell_exec_available()
	{
		if (array_intersect(array('shell_exec', 'escapeshellarg', 'escapeshellcmd', 'extension_loaded'), array_map('trim', explode(',', @ini_get('disable_functions'))))) return false;

		//Suhosin: http://www.hardened-php.net/suhosin/
		//Will cause PHP to silently fail.
		if (extension_loaded('suhosin')) return false;

		// Can we issue a simple echo command?
		if (!@shell_exec('echo duplicator')) return false;

		return true;
	}

	/**
	 *  Returns the path this this server where the zip command can be called
	 *  @return string	The path to where the zip command can be processed
	 */
	public static function get_unzip_filepath()
	{
		$filepath = null;
		if (self::is_shell_exec_available()) {
			if (shell_exec('hash unzip 2>&1') == NULL) {
				$filepath = 'unzip';
			} else {
				$possible_paths = array('/usr/bin/unzip', '/opt/local/bin/unzip');
				foreach ($possible_paths as $path) {
					if (file_exists($path)) {
						$filepath = $path;
						break;
					}
				}
			}
		}
		return $filepath;
	}

	/**
	* Does the site look to be a WordPress site
	*
	* @return bool		Returns true if the site looks like a WP site
	*/
	public static function isWordPress()
	{
		$search_list  = explode(',', self::$wpCoreDirsList);
		$root_files   = scandir($GLOBALS['DUPX_ROOT']);
		$search_count = count($search_list);
		$file_count   = 0;
		foreach ($root_files as $file) {
			if (in_array($file, $search_list)) {
				$file_count++;
			}
		}
		return ($search_count == $file_count);
	}

}

//INIT Class Properties
DUPX_Server::_init();
