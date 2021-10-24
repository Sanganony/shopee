<?php
defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Class used to update and edit web server configuration files
 * for both Apache and IIS files .htaccess and web.config
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\WPConfig
 *
 */
class DUPX_WPConfig
{
	/**
	 * Updates the standard WordPress config file settings
	 *
	 * @return null
	 */
	public static function updateVars(&$patterns, &$replace)
	{
		//$root_path		=  $GLOBALS['DUPX_ROOT'];
		//$wpconfig_path	= "{$root_path}/wp-config.php";
		$wpconfig_arkpath	= "{$GLOBALS['DUPX_ROOT']}/dup-wp-config-arc__{$GLOBALS['DUPX_AC']->package_hash}.txt";
		$wpconfig		= @file_get_contents($wpconfig_arkpath, true);

		//SSL CHECKS
		if ($_POST['ssl_admin']) {
			if (!strstr($wpconfig, 'FORCE_SSL_ADMIN')) {
				$wpconfig = $wpconfig . PHP_EOL . "define('FORCE_SSL_ADMIN', true);";
			}
		} else {
			array_push($patterns, "/'FORCE_SSL_ADMIN',\s*true/");
			array_push($replace, "'FORCE_SSL_ADMIN', false");
		}

		//CACHE CHECKS
		if ($_POST['cache_wp']) {
			if (!strstr($wpconfig, 'WP_CACHE')) {
				$wpconfig = $wpconfig . PHP_EOL . "define('WP_CACHE', true);";
			}
		} else {
			array_push($patterns, "/'WP_CACHE',\s*true/");
			array_push($replace, "'WP_CACHE', false");
		}
		if (!$_POST['cache_path']) {
			array_push($patterns, "/'WPCACHEHOME',\s*'.*?'/");
			array_push($replace, "'WPCACHEHOME', ''");
		}

		//--------------------
		//NEW TOKEN PARSER LOGIC:
		//$count checks for dynamic variable types such as:  define('WP_TEMP_DIR',	'D:/' . $var . 'somepath/');
		//which should not be updated.  Goal is to evenaly move all var checks into tokenParser
		$defines  = self::parseDefines($wpconfig_arkpath);

		//WP_CONTENT_DIR
		if (isset($defines['WP_CONTENT_DIR'])) {
			$new_path = str_replace($_POST['path_old'], $_POST['path_new'], DUPX_U::setSafePath($defines['WP_CONTENT_DIR']), $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_CONTENT_DIR.*?\)\s*;/");
				array_push($replace, "'WP_CONTENT_DIR', '{$new_path}');");
			}
		}

		//WP_CONTENT_URL
		// '/' added to prevent word boundary with domains that have the same root path
		if (isset($defines['WP_CONTENT_URL'])) {
			$new_path = str_replace($_POST['url_old'] . '/', $_POST['url_new'] . '/', $defines['WP_CONTENT_URL'], $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_CONTENT_URL.*?\)\s*;/");
				array_push($replace, "'WP_CONTENT_URL', '{$new_path}');");
			}
		}

		//WP_TEMP_DIR
		if (isset($defines['WP_TEMP_DIR'])) {
			$new_path = str_replace($_POST['path_old'], $_POST['path_new'], DUPX_U::setSafePath($defines['WP_TEMP_DIR']) , $count);
			if ($count > 0) {
				array_push($patterns, "/('|\")WP_TEMP_DIR.*?\)\s*;/");
				array_push($replace, "'WP_TEMP_DIR', '{$new_path}');");
			}
		}

		// This is all redundant - all this is happening on the caller.  Really should move the outside logic into here
//		if (!is_writable($wpconfig_path)) {
//			$err_log = "\nWARNING: Unable to update file permissions and write to {$wpconfig_path}.  ";
//			$err_log .= "Check that the wp-config.php is in the archive.zip and check with your host or administrator to enable PHP to write to the wp-config.php file.  ";
//			$err_log .= "If performing a 'Manual Extraction' please be sure to select the 'Manual Archive Extraction' option on step 1 under options.";
//			chmod($wpconfig_path, 0644) ? DUPX_Log::info("File Permission Update: {$wpconfig_path} set to 0644") : DUPX_Log::error("{$err_log}");
//		}

		//$wpconfig = preg_replace($patterns, $replace, $wpconfig);
		//$wpconfig_updated = file_put_contents($wpconfig_path, $wpconfig);

		//if ($wpconfig_updated === false) {
		//	DUPX_Log::error("\nWARNING: Unable to udpate {$wpconfig_path} file.  Be sure the file is present in your archive and PHP has permissions to update the file.");
		//}
		//$wpconfig = null;
	}

	/**
	 * Used to parse the wp-config PHP statements
	 *
	 * @param string	$wpconfigPath The full path to the wp-config.php file
	 *
	 * @return array	Returns and array of defines with the names
	 *					as the key and the value as the value.
	 */
	public static function parseDefines($wpconfigPath) {

		$defines = array();
		$wpconfig_file = @file_get_contents($wpconfigPath);
		
		if (!function_exists('token_get_all')) {
			DUPX_Log::info("\nNOTICE: PHP function 'token_get_all' does not exist so skipping WP_CONTENT_DIR and WP_CONTENT_URL processing.");
			return $defines;
		}

		if ($wpconfig_file === false) {
			return $defines;
		}

		$defines = array();
		$tokens	 = token_get_all($wpconfig_file);
		$token	 = reset($tokens);
        $state   = 0;
		while ($token) {
			if (is_array($token)) {
				if ($token[0] == T_WHITESPACE || $token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
					// do nothing
				} else if ($token[0] == T_STRING && strtolower($token[1]) == 'define') {
					$state = 1;
				} else if ($state == 2 && self::isConstant($token[0])) {
					$key	 = $token[1];
					$state	 = 3;
				} else if ($state == 4 && self::isConstant($token[0])) {
					$value	 = $token[1];
					$state	 = 5;
				}
			} else {
				$symbol = trim($token);
				if ($symbol == '(' && $state == 1) {
					$state = 2;
				} else if ($symbol == ',' && $state == 3) {
					$state = 4;
				} else if ($symbol == ')' && $state == 5) {
					$defines[self::tokenStrip($key)] = self::tokenStrip($value);
					$state = 0;
				}
			}
			$token = next($tokens);
		}

		return $defines;

	}

	/**
	 * Strips a value from from its location
	 *
	 * @return string	The stripped token value
	 */
	private static function tokenStrip($value)
	{
		return preg_replace('!^([\'"])(.*)\1$!', '$2', $value);
	}

	/**
	 * Is the value a constant
	 *
	 * @return bool	Returns string if the value is a constant
	 */
	private static function isConstant($token)
	{
		return $token == T_CONSTANT_ENCAPSED_STRING || $token == T_STRING || $token == T_LNUMBER || $token == T_DNUMBER;
	}

	/**
	 * Generates a random password drawn from the defined set of characters.
	 * Copy of the wp_generate_password() function from wp-includes/pluggable.php with minor tweaks
	 *
	 * @since 2.5.0
	 *
	 * @param int  $length              Optional. The length of password to generate. Default 12.
	 * @param bool $special_chars       Optional. Whether to include standard special characters.
	 *                                  Default true.
	 * @param bool $extra_special_chars Optional. Whether to include other special characters.
	 *                                  Used when generating secret keys and salts. Default false.
	 * @return string The random password.
	 */
	public static function generatePassword($length = 12, $special_chars = true, $extra_special_chars = false)
	{
		$chars	 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ($special_chars) $chars	 .= '!@#$%^&*()';
		if ($extra_special_chars) $chars	 .= '-_ []{}<>~`+=,.;:/?|';

		$password = '';
		for ($i = 0; $i < $length; $i++) {
			$password .= substr($chars, self::rand(0, strlen($chars) - 1), 1);
		}

		return $password;
	}

	/**
	 * Generates a random number
	 * * Copy of the wp_rand() function from wp-includes/pluggable.php with minor tweaks
	 *
	 * @since 2.6.2
	 * @since 4.4.0 Uses PHP7 random_int() or the random_compat library if available.
	 *
	 * @global string $rnd_value
	 * @staticvar string $seed
	 * @staticvar bool $external_rand_source_available
	 *
	 * @param int $min Lower limit for the generated number
	 * @param int $max Upper limit for the generated number
	 * @return int A random number between min and max
	 */
	private static function rand($min = 0, $max = 0)
	{
		global $rnd_value;

		// Some misconfigured 32bit environments (Entropy PHP, for example) truncate integers larger than PHP_INT_MAX to PHP_INT_MAX rather than overflowing them to floats.
		$max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff
		// We only handle Ints, floats are truncated to their integer value.
		$min = (int) $min;
		$max = (int) $max;

		// Use PHP's CSPRNG, or a compatible method
		static $use_random_int_functionality = true;
		if ($use_random_int_functionality) {
			try {
				$_max	 = ( 0 != $max ) ? $max : $max_random_number;
				// rand() can accept arguments in either order, PHP cannot.
				$_max	 = max($min, $_max);
				$_min	 = min($min, $_max);
				// mt_rand() is for PHP 5.2
				$val	 = function_exists('random_int') ? random_int($_min, $_max) : mt_rand($_min, $_max);
				if (false !== $val) {
					return abs(intval($val));
				} else {
					$use_random_int_functionality = false;
				}
			} catch (Error $e) {
				$use_random_int_functionality = false;
			} catch (Exception $e) {
				$use_random_int_functionality = false;
			}
		}

		// Reset $rnd_value after 14 uses
		// 32(md5) + 40(sha1) + 40(sha1) / 8 = 14 random numbers from $rnd_value
		if (strlen($rnd_value) < 8) {
			static $seed = '';

			$rnd_value	 = md5(uniqid(microtime().mt_rand(), true).$seed);
			$rnd_value	 .= sha1($rnd_value);
			$rnd_value	 .= sha1($rnd_value.$seed);
			$seed		 = md5($seed.$rnd_value);
		}

		// Take the first 8 digits for our value
		$value = substr($rnd_value, 0, 8);

		// Strip the first eight, leaving the remainder for the next call to rand().
		$rnd_value = substr($rnd_value, 8);

		$value = abs(hexdec($value));

		// Reduce the value to be within the min - max range
		if ($max != 0) $value = $min + ( $max - $min + 1 ) * $value / ( $max_random_number + 1 );

		return abs(intval($value));
	}
}
