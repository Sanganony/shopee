<?php
/**
 * redirect to installer.php if exists
 */
$API['BaseRootPath'] = rtrim(str_ireplace('dup-installer', '', dirname(__FILE__)),'\\/');
$API['BaseRootURL']  = '//'.$_SERVER['HTTP_HOST'].str_ireplace('dup-installer', '', dirname($_SERVER['PHP_SELF']));

if (file_exists($API['BaseRootPath'].DIRECTORY_SEPARATOR.'installer.php')) {
    header("Location: {$API['BaseRootURL']}/installer.php");
    die;
}

echo "Please browse to the 'installer.php' from your web browser to proceed with your install!";
die;
