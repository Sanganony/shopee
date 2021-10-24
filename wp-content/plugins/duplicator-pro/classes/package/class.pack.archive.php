<?php
defined("ABSPATH") or die("");
if (!defined('DUPLICATOR_PRO_VERSION')) exit; // Exit if accessed directly

require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/package/class.pack.archive.filters.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/package/class.pack.archive.zip.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/package/duparchive/class.pack.archive.duparchive.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/package/class.pack.archive.shellzip.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/class.exceptions.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'classes/class.io.php');
require_once (DUPLICATOR_PRO_PLUGIN_PATH . 'lib/forceutf8/src/Encoding.php');
//require_once(DUPLICATOR_PRO_PLUGIN_PATH  . 'lib/snaplib/class.snaplib.u.util.php');

/**
 * Class for handling archive setup and build process
 *
 * Standard: PSR-2 (almost)
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package DUP_PRO
 * @subpackage classes/package
 * @copyright (c) 2017, Snapcreek LLC
 * @license	https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since 1.0.0
 *
 * @notes: Trace process time
 *	$timer01 = DUP_PRO_U::getMicrotime();
 *	DUP_PRO_LOG::trace("SCAN TIME-B = " . DUP_PRO_U::elapsedTime(DUP_PRO_U::getMicrotime(), $timer01));
 *
 */
class DUP_PRO_Archive
{
//del    const ScanStatusComplete = 'complete';
//    const ScanStatusRunning = 'running';
//    const ScanStatusFirst = 'first';
    //PUBLIC
    //Includes only the dirs set on the package
    public $ExportOnlyDB;
    public $FilterDirs;
    public $FilterExts;
    public $FilterFiles;
    //Includes all FilterInfo except warnings
    public $FilterDirsAll  = array();
    public $FilterExtsAll  = array();
    public $FilterFilesAll = array();
    public $FilterOn;
    public $File;
    public $Format;
    public $PackDir;
    public $Size  = 0;
    public $Dirs  = array();
    public $DirCount = 0;
    public $RecursiveLinks  = array();
    public $Files = array();
    public $FileCount = 0;
    public $file_count = -1;
    public $FilterInfo;
    public $ListDelimiter = ";\n";
//del    public $ScanTimeStart;
//    public $ScanStatus = self::ScanStatusFirst;
    //PROTECTED
    protected $Package;
    private $global;
    private $tmpFilterDirsAll = array();
    private $wpCorePaths = array();
    private $wpCoreExactPaths = array();
    private $FileListHandle = null;
    private $DirListHandle = null;
//del    private $isForcedScanQuit = false;

    public function __construct($package)
    {
        $this->Package    = $package;
        $this->FilterOn   = false;
        $this->FilterInfo = new DUP_PRO_Archive_Filter_Info();
        $this->global     = DUP_PRO_Global_Entity::get_instance();
        $this->ExportOnlyDB = false;

        $rootPath = DUP_PRO_U::safePath(rtrim(DUPLICATOR_PRO_WPROOTPATH, '//'));

        $this->wpCorePaths[] = DUP_PRO_U::safePath("{$rootPath}/wp-admin");
        $this->wpCorePaths[] = DUP_PRO_U::safePath(WP_CONTENT_DIR . "/uploads");
        $this->wpCorePaths[] = DUP_PRO_U::safePath(WP_CONTENT_DIR . "/languages");
        $this->wpCorePaths[] = DUP_PRO_U::safePath(WP_PLUGIN_DIR);
        $this->wpCorePaths[] = DUP_PRO_U::safePath(get_theme_root());
        $this->wpCorePaths[] = DUP_PRO_U::safePath("{$rootPath}/wp-includes");

        $this->wpCoreExactPaths[] = DUP_PRO_U::safePath("{$rootPath}");
		$this->wpCoreExactPaths[] = DUP_PRO_U::safePath(WP_CONTENT_DIR);
    }

    /**
     * Builds the archive file
     *
     * @returns null
     */
    public function buildFile($package, $build_progress)
    {
        DUP_PRO_LOG::trace("Building archive");

        try {
            $this->Package = $package;
            if (!isset($this->PackDir) && !is_dir($this->PackDir)) throw new Exception("The 'PackDir' property must be a valid directory.");
            if (!isset($this->File)) throw new Exception("A 'File' property must be set.");

            $completed = false;

            switch ($this->Format) {
                case 'TAR': break;

                case 'DAF':
                    $completed = DUP_PRO_Dup_Archive::create($this, $build_progress);
                    $this->Package->Update();
                    break;

                default:
                    $this->Format = 'ZIP';

                    if ($build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) {
                        DUP_PRO_LOG::trace('Doing shell exec zip');
                        $completed = DUP_PRO_ShellZip::create($this, $build_progress);
                    } else {
                        $zipArchive = new DUP_PRO_ZipArchive();
                        $completed  = $zipArchive->create($this, $build_progress);
                    }

                    $this->Package->Update();
                    break;
            }

            if ($completed) {
                if ($build_progress->failed) {
                    DUP_PRO_LOG::traceError("Error building archive");
                    $this->Package->set_status(DUP_PRO_PackageStatus::ERROR);
                } else {
                    $filepath    = DUP_PRO_U::safePath("{$this->Package->StorePath}/{$this->Package->Archive->File}");
                    $this->Size	 = @filesize($filepath);
                    $this->Package->set_status(DUP_PRO_PackageStatus::ARCDONE);
                    DUP_PRO_LOG::trace("filesize of archive = {$this->Size}");
                    DUP_PRO_LOG::trace("Done building archive");
                }
            } else {
                DUP_PRO_LOG::trace("Archive chunk completed");
            }

        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    /**
     * Create filters info and generate meta data about the dirs and files needed for the build
     *
     * @link http://msdn.microsoft.com/en-us/library/aa365247%28VS.85%29.aspx Windows filename restrictions
     *
     * @returns object	Returns a copy of this object containing scanner results
     */
    public function buildScanStats()
    {
        $this->createFilterInfo();
        $this->initScanStats();
        
        $rootPath = DUP_PRO_U::safePath(rtrim(DUPLICATOR_PRO_WPROOTPATH, '//'));
        $rootPath = (trim($rootPath) == '') ? '/' : $rootPath;

        //If the root directory is a filter then skip it all
        if (in_array($this->PackDir, $this->FilterDirsAll) || $this->Package->Archive->ExportOnlyDB) {
            $this->initFileListHandles();             
            $this->closeFileListHandles();
            $this->Dirs = array();
        } else {
            $this->initFileListHandles();
            $this->addToList($this->PackDir, 'dir');
            $this->getFileLists($rootPath);
            if ($this->isOuterWPContentDir()) {
                $this->addToList($this->PackDir, 'dir');
                $this->getFileLists(WP_CONTENT_DIR);
            }

            $this->closeFileListHandles();
        }

        $this->FilterDirsAll = array_merge($this->FilterDirsAll, $this->FilterInfo->Dirs->Unreadable);
        $this->FilterFilesAll = array_merge($this->FilterFilesAll, $this->FilterInfo->Files->Unreadable);
        sort($this->FilterDirsAll);
        sort($this->FilterFilesAll);
        $this->setTreeFilters();

        return $this;
    }

    private function initScanStats() {
        $this->RecursiveLinks = array();
        
        // For dir
        $this->FilterInfo->Dirs->Warning    = array();
        $this->FilterInfo->Dirs->Unreadable = array();
        $this->FilterInfo->Dirs->AddonSites = array();

        // For file
        $this->Size                          = 0;
        $this->FilterInfo->Files->Size       = array();
        $this->FilterInfo->Files->Warning    = array();
        $this->FilterInfo->Files->Unreadable = array();

        $this->FileCount = 0;
        $this->DirCount  = 0;
    }
    
    /**
     * Get the file path to the archive file
     *
     * @return string	Returns the full file path to the archive file
     */
    public function getSafeFilePath()
    {
        return DUP_PRO_U::safePath(DUPLICATOR_PRO_SSDIR_PATH."/{$this->File}");
    }

    /**
     * Get the store URL to the archive file
     *
     * @return string	Returns the full URL path to the archive file
     */
    public function getURL()
    {
        return DUPLICATOR_PRO_SSDIR_URL."/{$this->File}";
    }

    /**
     * Parse the list of ";" separated paths to make paths/format safe
     *
     * @param string $dirs A list of dirs to parse
     *
     * @return string	Returns a cleanup up ";" separated string of dir paths
     */
    public static function parseDirectoryFilter($dirs = "")
    {
        $dirs			= str_replace(array("\n", "\t", "\r"), '', $dirs);
        $filters		= "";
        $dir_array		= array_unique(explode(";", $dirs));
        $clean_array	= array();
        foreach ($dir_array as $val) {
            if (strlen($val) >= 2) {
                $clean_array[] = DUP_PRO_U::safePath(trim(rtrim($val, "/\\"))) ;
            }
        }

        if (count($clean_array)) {
            $clean_array  = array_unique($clean_array);
            sort($clean_array);
            $filters = implode(';', $clean_array) . ';';
        }
        return $filters;
    }

    /**
     * Parse the list of ";" separated extension names to make paths/format safe
     *
     * @param string $extensions A list of file extension names to parse
     *
     * @return string	Returns a cleanup up ";" separated string of extension names
     */
    public static function parseExtensionFilter($extensions = "")
    {
        $filter_exts = "";
        if (strlen($extensions) >= 1 && $extensions != ";") {
            $filter_exts = str_replace(array(' ', '.'), '', $extensions);
            $filter_exts = str_replace(",", ";", $filter_exts);
            $filter_exts = DUP_PRO_STR::appendOnce($extensions, ";");
        }
        return $filter_exts;
    }

    /**
     * Parse the list of ";" separated paths to make paths/format safe
     *
     * @param string $files		A list of file paths to parse
     *
     * @return string	Returns a cleanup up ";" separated string of file paths
     */
    public static function parseFileFilter($files = "")
    {
        $files			= str_replace(array("\n", "\t", "\r"), '', $files);
        $filters		= "";
        $file_array		= array_unique(explode(";", $files));
        $clean_array	= array();
        foreach ($file_array as $val) {
            if (strlen($val) >= 2) {
                $clean_array[] = DUP_PRO_U::safePath(trim(rtrim($val, "/\\"))) ;
            }
        }

        if (count($clean_array)) {
            $clean_array  = array_unique($clean_array);
            sort($clean_array);
            $filters = implode(';', $clean_array) . ';';
        }
        return $filters;
    }

    /**
     * Creates all of the filter information meta stores
     *
     * @todo: Create New Section Settings > Packages > Filters
     * Two new check boxes one for directories and one for files
     * Readonly list boxes for directories and files
     *
     * @return null
     */
    private function createFilterInfo()
    {        
        DUP_PRO_LOG::traceObject('Filter files', $this->FilterFiles);
        
        $this->FilterInfo->Dirs->Core = array();
        
        //FILTER: INSTANCE ITEMS
        if ($this->FilterOn) {
            /*
            $this->FilterInfo->Dirs->Instance  = array_map('DUP_PRO_U::safePath', explode(";", $this->FilterDirs, -1));
            $this->FilterInfo->Exts->Instance  = explode(";", $this->FilterExts, -1);
            $this->FilterInfo->Files->Instance = array_map('DUP_PRO_U::safePath', explode(";", $this->FilterFiles, -1));
            */
            
            $this->FilterInfo->Dirs->Instance  = array_map('DUP_PRO_U::safePath', explode(";", $this->FilterDirs));
            // Remove blank entries
            $this->FilterInfo->Dirs->Instance  = array_filter(array_map('trim', $this->FilterInfo->Dirs->Instance));

            $this->FilterInfo->Exts->Instance  = explode(";", $this->FilterExts);
            // Remove blank entries
            $this->FilterInfo->Exts->Instance  = array_filter(array_map('trim', $this->FilterInfo->Exts->Instance));

            $this->FilterInfo->Files->Instance = array_map('DUP_PRO_U::safePath', explode(";", $this->FilterFiles));
            // Remove blank entries
            $this->FilterInfo->Files->Instance  = array_filter(array_map('trim', $this->FilterInfo->Files->Instance));
        }

        //FILTER: GLOBAL ITMES
        if ($GLOBALS['DUPLICATOR_PRO_GLOBAL_DIR_FILTERS_ON']) {
            $this->FilterInfo->Dirs->Global = $GLOBALS['DUPLICATOR_PRO_GLOBAL_DIR_FILTERS'];
        }

        $GLOBALS['DUPLICATOR_PRO_GLOBAL_FILE_FILTERS'][] = DUPLICATOR_PRO_WPROOTPATH . '.htaccess';
		$GLOBALS['DUPLICATOR_PRO_GLOBAL_FILE_FILTERS'][] = DUPLICATOR_PRO_WPROOTPATH . 'wp-config.php';

        if ($GLOBALS['DUPLICATOR_PRO_GLOBAL_FILE_FILTERS_ON']) {
            $this->FilterInfo->Files->Global = $GLOBALS['DUPLICATOR_PRO_GLOBAL_FILE_FILTERS'];
        }

        //FILTER: CORE ITMES
        //Filters Duplicator free packages & All pro local directories
        $storages = DUP_PRO_Storage_Entity::get_all();
        foreach ($storages as $storage) {
            if ($storage->storage_type == DUP_PRO_Storage_Types::Local && $storage->local_filter_protection) {
                $this->FilterInfo->Dirs->Core[] = DUP_PRO_U::safePath($storage->local_storage_folder);
            }
        }

        // Prevent adding double wp-content dir conflicts
        if ($this->isOuterWPContentDir()) {
            $default_wp_content_dir_path = DUP_PRO_U::safePath(ABSPATH.'wp-content');
            if (file_exists($default_wp_content_dir_path)) {
                if (is_dir($default_wp_content_dir_path)) {
                    $this->FilterInfo->Dirs->Core[] = $default_wp_content_dir_path;
                } else {
                    $this->FilterInfo->Files->Core[] = $default_wp_content_dir_path;
                }
            }
        }
        
        $this->FilterDirsAll  = array_merge($this->FilterInfo->Dirs->Instance, $this->FilterInfo->Dirs->Global, $this->FilterInfo->Dirs->Core, $this->Package->Multisite->getDirsToFilter());
        $this->FilterExtsAll  = array_merge($this->FilterInfo->Exts->Instance, $this->FilterInfo->Exts->Global, $this->FilterInfo->Exts->Core);
        $this->FilterFilesAll = array_merge($this->FilterInfo->Files->Instance, $this->FilterInfo->Files->Global, $this->FilterInfo->Files->Core);
        $this->tmpFilterDirsAll = $this->FilterDirsAll;

        //PHP 5 on windows decode patch
        if (! DUP_PRO_U::$PHP7_plus && DUP_PRO_U::isWindows()) {
            foreach ($this->tmpFilterDirsAll as $key => $value) {
                if ( preg_match('/[^\x20-\x7f]/', $value)) {
                    $this->tmpFilterDirsAll[$key] = utf8_decode($value);
                }
            }
        }
    }

    /**
     * Recursive function to get all directories in a wp install
     *
     * @notes:
     *	Older PHP logic which is more stable on older version of PHP
     *	NOTE RecursiveIteratorIterator is problematic on some systems issues include:
     *  - error 'too many files open' for recursion
     *  - $file->getExtension() is not reliable as it silently fails at least in php 5.2.17
     *  - issues with when a file has a permission such as 705 and trying to get info (had to fallback to path-info)
     *  - basic conclusion wait on the SPL libs until after php 5.4 is a requirments
     *  - tight recursive loop use caution for speed
     *
     * @return array	Returns an array of directories to include in the archive
     */
    private function getFileLists($path) {
        $handle = @opendir($path);
        if ($handle) {
            while (($file = readdir($handle)) !== false) {

                if ($file == '.' || $file == '..') {
                    continue;
                }

                $fullPath = str_replace("\\", '/', "{$path}/{$file}");

                if (is_dir($fullPath)) {
                    $add = true;
                    if (!is_link($fullPath)) {
                        foreach ($this->tmpFilterDirsAll as $key => $val) {
                            $trimmedFilterDir = rtrim($val, '/');
                            if ($fullPath == $trimmedFilterDir || strpos($fullPath, $trimmedFilterDir . '/') !== false) {
                                $add = false;
                                unset($this->tmpFilterDirsAll[$key]);
                                break;
                            }
                        }
                    } else {
                        //Convert relative path of link to absolute paths
                        chdir($fullPath);
                        $link_path = str_replace("\\", '/', realpath(readlink($fullPath)));
                        chdir(dirname(__FILE__));

                        $link_pos = strpos($fullPath, $link_path);
                        if($link_pos === 0 && (strlen($link_path) <  strlen($fullPath))){
                            $add = false;
                            $this->RecursiveLinks[] = $fullPath;
                            $this->FilterDirsAll[] = $fullPath;
                        } else { // For link filter
                            foreach ($this->tmpFilterDirsAll as $key => $val) {
                                $trimmedFilterDir = rtrim($val, '/');
                                if ($fullPath == $trimmedFilterDir || strpos($fullPath, $trimmedFilterDir . '/') !== false) {
                                    $add = false;
                                    unset($this->tmpFilterDirsAll[$key]);
                                    break;
                                }
                            }
                        }
                    }

                    if ($add) {
                        $this->getFileLists($fullPath);
                        $this->addToList($fullPath,'dir');

                    }
                } else {
                    // Note: The last clause is present to perform just a filename check
                    if ( ! (in_array(pathinfo($file, PATHINFO_EXTENSION) , $this->FilterExtsAll)
                        || in_array($fullPath, $this->FilterFilesAll)
                        || in_array($file, $this->FilterFilesAll))) {
                            $this->addToList($fullPath,'file');
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * Initializes the file list handles. Handles are set-up as properties for
     * performance improvement. Otherwise each handle would be opened and closed
     * with each path added.
     */
    private function initFileListHandles()
    {
        $file_list = DUPLICATOR_PRO_SSDIR_PATH_TMP."/{$this->Package->NameHash}_files.txt";
        $dir_list = DUPLICATOR_PRO_SSDIR_PATH_TMP."/{$this->Package->NameHash}_dirs.txt";

        //
        if($this->FileListHandle === null){
            $this->FileListHandle = fopen($file_list,"a+");
            ftruncate($this->FileListHandle,0);
        }

        if($this->DirListHandle === null){
            $this->DirListHandle = fopen($dir_list,"a+");
            ftruncate($this->DirListHandle,0);
        }
    }

    /**
     * Closes file and dir list handles
     */
    private function closeFileListHandles()
    {
        fclose($this->FileListHandle);
        $this->FileListHandle = null;
        fclose($this->DirListHandle);
        $this->DirListHandle = null;
    }

    /**
     * @param $path string Path to type
     * @param $type string Type of path, 'dir' or 'file'
     */
    private function addToList($path, $type) {
        $global = DUP_PRO_Secure_Global_Entity::getInstance();

        if ($type == 'file') {
            $filePath = $path;
            $fileName = basename($filePath);

            if (!is_readable($filePath)) {
                $this->FilterInfo->Files->Unreadable[] = $filePath;
                return;
            }
    
            if (!$global->skip_archive_scan) {
                //File Warnings
                $invalid_encoding = preg_match('/(\/|\*|\?|\>|\<|\:|\\|\|)/', $fileName) ||
                    preg_match('/[^\x20-\x7f]/', $fileName);

                $invalid_name = strlen($filePath) > 250
                    || trim($fileName) === ''
                    || $invalid_encoding;

                if($invalid_encoding) {
                    $filePath = Encoding::toUTF8($filePath);
                    $fileName = Encoding::toUTF8($fileName);
                }

                if ($invalid_name) {
                    if (($this->global->archive_build_mode === DUP_PRO_Archive_Build_Mode::ZipArchive)
                        || ($this->global->archive_build_mode === DUP_PRO_Archive_Build_Mode::DupArchive)) {
                        $this->FilterInfo->Files->Warning[] = array(
                            'name'	=> $fileName,
                            'dir'	=> pathinfo($filePath, PATHINFO_DIRNAME),
                            'path'	=> $filePath);
                    }
                }

                $fileSize = @filesize($filePath);
                $fileSize = empty($fileSize) ? 0 : $fileSize;
                $this->Size += $fileSize;

                if ($fileSize > DUPLICATOR_PRO_SCAN_WARNFILESIZE) {
                    $this->FilterInfo->Files->Size[] = array(
                        'ubytes' => $fileSize,
                        'bytes'  => DUP_PRO_U::byteSize($fileSize, 0),
                        'name'	 => $fileName,
                        'dir'	 => pathinfo($filePath, PATHINFO_DIRNAME),
                        'path'	 => $filePath);
                }
            }

            $this->FileCount++;
            fwrite($this->FileListHandle, $filePath.$this->ListDelimiter);
        } elseif($type == 'dir') {
            $dirPath = $path;
            $name = basename($dirPath);

            //Dir is not readble remove and flag
            if (!DupProSnapLibOSU::$isWindows && !is_readable($dirPath)) {
                $this->FilterInfo->Dirs->Unreadable[] = $dirPath;
                return;
            }

            if (!$global->skip_archive_scan) {
                //Locate invalid directories and warn
                $invalid_encoding = preg_match('/(\/|\*|\?|\>|\<|\:|\\|\|)/', $name) ||
                preg_match('/[^\x20-\x7f]/', $name);

                $invalid_name = strlen($dirPath) > PHP_MAXPATHLEN
                    || trim($name) === ''
                    || (strrpos($name, '.') == strlen($name) - 1 && substr($name, -1) === '.')
                    || $invalid_encoding;

                if($invalid_encoding) {
                    $dirPath = Encoding::toUTF8($dirPath);
                }

                if($invalid_name) {
                    if (($this->global->archive_build_mode === DUP_PRO_Archive_Build_Mode::ZipArchive)
                        || ($this->global->archive_build_mode === DUP_PRO_Archive_Build_Mode::DupArchive)) {
                        // only warnings, not removing dir from archive
                        $this->FilterInfo->Dirs->Warning[] = $dirPath;
                    }
                }

                //Check for other WordPress installs
                if ($name === 'wp-admin') {
                    $parent_dir = realpath(dirname($dirPath));
                    if ($parent_dir != realpath(DUPLICATOR_PRO_WPROOTPATH)) {
                        if (file_exists("$parent_dir/wp-includes")) {
                            if (file_exists("$parent_dir/wp-config.php")) {
                                // Ensure we aren't adding any critical directories
                                $parent_name = basename($parent_dir);
                                if (($parent_name != 'wp-includes') && ($parent_name != 'wp-content') && ($parent_name != 'wp-admin')) {
                                    $this->FilterInfo->Dirs->AddonSites[] =  str_replace("\\", '/',$parent_dir);
                                }
                            }
                        }
                    }
                }
            }

            $this->DirCount++;
            fwrite($this->DirListHandle, $dirPath.$this->ListDelimiter);
        }
    }

    /**
     *  Builds a tree for both file size warnings and name check warnings
     *  The trees are used to apply filters from the scan screen
     *
     *  @return null
     */
    private function setTreeFilters()
    {
        //-------------------------
        //SIZE TREE
        //BUILD: File Size tree
        $treeObj = new DUP_PRO_Tree_files(ABSPATH);

        foreach ($this->FilterInfo->Files->Size as $fileData) {
            $data = array(
                'is_warning' => true,
                'size' => $fileData['bytes'],
                'ubytes' => $fileData['ubytes'],
            );

            try {
                $treeObj->addElement($fileData['path'], $data);
            } catch (Exception $e) {
                DUP_PRO_Log::trace('Add filter file size error MSG: '.$e->getMessage());
            }
        }

        $treeObj->tree->uasort(array(__CLASS__, 'sortTreeByFolderWarningName'));
        $treeObj->tree->treeTraverseCallback(array($this, 'checkTreeNodesFolder'));
        

        $this->FilterInfo->TreeSize = self::treeNodeTojstreeNode($treeObj->tree, true, DUP_PRO_U::esc_html__('No large files found during this scan.'));

        //-------------------------
        //NAME TREE
        //BUILD: Warning tree for file names
        $treeObj = new DUP_PRO_Tree_files(ABSPATH);

        foreach ($this->FilterInfo->Dirs->Warning as $dir) {
            $nodeData = array(
                'is_warning' => true,
            );

            try {
                $treeObj->addElement($dir, $nodeData);
            } catch (Exception $e) {
                DUP_PRO_Log::trace('Add filter dir utf8 error MSG: '.$e->getMessage());
            }
        }

        foreach ($this->FilterInfo->Files->Warning as $fileData) {
            $nodeData = array(
                'is_warning' => true
            );
            try {
                $treeObj->addElement($fileData['path'], $nodeData);
            } catch (Exception $e) {
                DUP_PRO_Log::trace('Add filter file utf8 error MSG: '.$e->getMessage());
            }
        }

        $treeObj->tree->uasort(array(__CLASS__, 'sortTreeByFolderWarningName'));
        $treeObj->tree->treeTraverseCallback(array($this, 'checkTreeNodesFolder'));

        $this->FilterInfo->TreeWarning = self::treeNodeTojstreeNode($treeObj->tree, true, DUP_PRO_U::esc_html__('No file/directory name warnings found.'));
        return;
    }

    /**
     *
     * @param DUP_PRO_Tree_files_node $a
     * @param DUP_PRO_Tree_files_node $b
     */
    public static function sortTreeByFolderWarningName($a, $b)
    {
        // check sort by path type
        if ($a->isDir && !$b->isDir) {
            return -1;
        } else if (!$a->isDir && $b->isDir) {
            return 1;
        } else {
            // sort by warning
            if (
                (isset($a->data['is_warning']) && $a->data['is_warning'] == true) &&
                (!isset($b->data['is_warning']) || $b->data['is_warning'] == false)
            ) {
                return -1;
            } else if (
                (!isset($a->data['is_warning']) || $a->data['is_warning'] == false) &&
                (isset($b->data['is_warning']) && $b->data['is_warning'] == true)
            ) {
                return 1;
            } else {
                // sort by name
                return strcmp($a->name, $b->name);
            }
        }
    }

    /**
     *
     * @param DUP_PRO_Tree_files_node $node
     */
    public function checkTreeNodesFolder($node) {
        $node->data['is_core'] = 0;
        $node->data['is_filtered'] = 0;

        if ($node->isDir) {
            $node->data['is_core'] = (int) DupProSnapLibUtilWp::isWpCore($node->fullPath , DupProSnapLibUtilWp::PATH_FULL);
/*
            // Check root and content exact dir
            if (in_array($node->fullPath , $this->wpCoreExactPaths)) {
                $node->data['is_core'] = 1;
            }
            else {
               //Locate core paths, wp-admin, wp-includes, etc.
               foreach ($this->wpCorePaths as $core_dir) {
                   if (strpos($node->fullPath , $core_dir) !== false) {
                       $node->data['is_core'] = 1;
                       break;
                   }
               }
           }*/
           
           if (in_array($node->fullPath , $this->FilterDirsAll)) {
               $node->data['is_filtered'] = 1;
           }
        } else {
            $ext = pathinfo($node->fullPath, PATHINFO_EXTENSION);

            if (in_array($ext , $this->FilterExtsAll)) {
                $node->data['is_filtered'] = 1;
            } else if (in_array($node->fullPath , $this->FilterFilesAll)) {
                $node->data['is_filtered'] = 1;
            }

            /*
             * provision to disable the core files to be excluded.
             * 
             * $node->data['is_core'] = (int) DupProSnapLibUtilWp::isWpCore($node->fullPath , DupProSnapLibUtilWp::PATH_FULL);
             */
        }
    }

    /**
     * @param DUP_PRO_Tree_files_node $node
     * 
     * @return array
     */
    public static function treeNodeTojstreeNode($node , $root = false, $notFoundText = '') {
        $name = $root ? $node->fullPath : $node->name;

        if (isset($node->data['size'])) {
            $name .= ' <b style="color: black;">['.$node->data['size'].']</b>';
        }

        $li_classes = '';
        $a_attr = array();

        if ($root) {
            $li_classes .= ' root-node';
        }

        if (isset($node->data['is_warning']) && $node->data['is_warning']) {
            $li_classes .= ' warning-node';
        }

        if ($node->data['is_core']) {
            $li_classes .= ' core-node';
            $a_attr['title'] = DUP_PRO_U::esc_attr__('Core WordPress directories should not be filtered. Use caution when excluding files.');
        }

        if ($node->data['is_filtered']) {
            $li_classes .= ' filtered-node';
            if ($node->isDir) {
                $a_attr['title'] = DUP_PRO_U::esc_attr__('This dir is filtered.');
            } else {
                $a_attr['title'] = DUP_PRO_U::esc_attr__('This file is filtered.');
            }
        }

        $result = array(
            //'id'          => $node->id, // will be autogenerated if omitted
            'text'        => $name, // node text
            'fullPath'    => $node->fullPath,
            'type'        => $node->isDir ? 'folder' : 'file',
            'state'       => array(
                'opened'    => true,  // is the node open
                'disabled'  => false,  // is the node disabled
                'selected'  => false,  // is the node selected,
                'checked'   => false,
                'checkbox_disabled' => ($node->data['is_core'] && isset($node->data['is_warning']) && !$node->data['is_warning']) || $node->data['is_filtered']
            ),
            'children'    => array(),  // array of strings or objects
            'li_attr'     => array(
                'class' => $li_classes
            ),  // attributes for the generated LI node
            'a_attr'      => $a_attr  // attributes for the generated A node
        );

        if (count($node->childs) == 0) {
            if ($root) {
                $result['state']['disabled'] = true;
                $result['state']['opened'] = true;
                $result['li_attr']['class'] .= ' no-warnings';
                $result['children'][] = array(
                        //'id'          => 'no_child_founds',
                        'text'        => $notFoundText, // node text
                        'type'        => 'info-text',
                        'state'       => array(
                            'opened'    => false,  // is the node open
                            'disabled'  => true,  // is the node disabled
                            'selected'  => false,  // is the node selected,
                            'checked'   => false,
                            'checkbox_disabled' => true
                        )
                    );
            } else {
                $result['children'] = $node->haveChildren;
                $result['state']['opened'] = false;
            }
        } else {
            foreach ($node->childs as $child) {
                $result['children'][] = self::treeNodeTojstreeNode($child);
            }
        }
       
        return $result;
    }

    public function getWPConfigFilePath() { 
        $wpconfig_filepath = ''; 
        if (file_exists(DUPLICATOR_PRO_WPROOTPATH . 'wp-config.php')) { 
            $wpconfig_filepath = DUPLICATOR_PRO_WPROOTPATH . 'wp-config.php'; 
        } elseif (@file_exists(dirname(DUPLICATOR_PRO_WPROOTPATH) . '/wp-config.php') && !@file_exists(dirname(DUPLICATOR_PRO_WPROOTPATH) . '/wp-settings.php')) { 
            $wpconfig_filepath = dirname(DUPLICATOR_PRO_WPROOTPATH) . '/wp-config.php'; 
        } 
        return $wpconfig_filepath; 
	}
	
	public function isOuterWPContentDir() {
		if (!isset($this->isOuterWPContentDir)) {
			$abspath_normalize = wp_normalize_path(ABSPATH); 
			$wp_content_dir_normalize = wp_normalize_path(WP_CONTENT_DIR); 
			if (0 !== strpos($wp_content_dir_normalize, $abspath_normalize)) {
				$this->isOuterWPContentDir = true;
			} else {
				$this->isOuterWPContentDir = false;
			}
		}
		return $this->isOuterWPContentDir;
    }
    
	public function wpContentDirNormalizePath() {
		if (!isset($this->wpContentDirNormalizePath)) {
			$this->wpContentDirNormalizePath = trailingslashit(wp_normalize_path(WP_CONTENT_DIR));
		}
		return $this->wpContentDirNormalizePath;
    }
    
	public function getLocalDirPath($dir, $basePath = '') {
		$isOuterWPContentDir = $this->isOuterWPContentDir();
		$wpContentDirNormalizePath = $this->wpContentDirNormalizePath();
		$compressDir = rtrim(wp_normalize_path(DUP_PRO_U::safePath($this->PackDir)), '/');
			
        $dir = trailingslashit(wp_normalize_path($dir));
        if ($isOuterWPContentDir && 0 === strpos($dir, $wpContentDirNormalizePath)) {
			$newWPContentDirPath = empty($basePath) 
										? 'wp-content/' 
										: $basePath.'wp-content/';
			$emptyDir = ltrim(str_replace($wpContentDirNormalizePath, $newWPContentDirPath, $dir), '/');
        } else {
            $emptyDir = ltrim(str_replace($compressDir, $basePath, $dir), '/');
        }
        return $emptyDir;
    }

    public function getLocalFilePath($file, $basePath = '') {
		$isOuterWPContentDir = $this->isOuterWPContentDir();
		$wpContentDirNormalizePath = $this->wpContentDirNormalizePath();
		$compressDir = rtrim(wp_normalize_path(DUP_PRO_U::safePath($this->PackDir)), '/');
        $file = wp_normalize_path($file);
        if ($isOuterWPContentDir && 0 === strpos($file, $wpContentDirNormalizePath)) {
			$newWPContentDirPath = empty($basePath) 
										? 'wp-content/' 
										: $basePath.'wp-content/';
            $localFileName = ltrim(str_replace($wpContentDirNormalizePath, $newWPContentDirPath, $file), '/');
        } else {
            $localFileName = ltrim(str_replace($compressDir, $basePath, $file), '/');
        }
        return $localFileName;
    }
}
