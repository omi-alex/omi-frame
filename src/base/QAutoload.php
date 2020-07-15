<?php

/**
 * The QAutoload class manages object loading via the `__autoload` PHP feature
 * 
 * Once you add a folder in the watch list (using self::AddWatchFolder) any file 
 * with the .php extension that start with a uppercase letter is considered to be 
 * a PHP class with the name of the file (without the extension)
 * 
 * The folders are scaned in depth (recursive).
 */
final class QAutoload
{
	/**
	 * Enables or disables the Dev/Debug Panel.
	 * Please see [[mods/dev-panel]] for more details.
	 *
	 * @var boolean
	 */
	public static $DebugPanel = false;
	/**
	 * Used to collect debug information.
	 *
	 * @var string[]
	 */
	public static $DebugStacks = array();
	/**
	 * The list of folderes to be scaned
	 *
	 * @var string[]
	 */
	private static $WatchFolders = array();
	/**
	 * Watch folders by tag
	 *
	 * @var string[]
	 */
	private static $WatchFoldersByTag = [];
	/**
	 * The list of folderes that are included as read-only
	 *
	 * @var string[]
	 */
	public static $WatchFoldersReadOnly = [];
	/**
	 * The main code folder of the application
	 *
	 * @var string
	 */
	private static $RuntimeFolder;
	/**
	 * True if the autoload was included
	 *
	 * @var boolean
	 */
	private static $AutoloadIncluded = false;
	/**
	 * List of paths for the classes, stored as key => value, $CLASS => $PATH
	 * The paths are full paths. 
	 * 
	 * The autoload information is stored in: self::$RuntimeFolder/temp/autoload.php
	 *
	 * @var string[]
	 */
	private static $AutoloadArray;
	/**
	 * The keys for self::$AutoloadArray (extra caching)
	 *
	 * @var string[]
	 */
	private static $AutoloadArrayKeys;
	/**
	 * The list of classes with a list of classes that extend that class 
	 *
	 * @var array[]
	 */
	private static $ExtendedByList;
	/**
	 * Keeps track if the ExtendedByList was included or not
	 *
	 * @var boolean
	 */
	private static $ExtendedByIncluded = false;
	/**
	 * The PHP to JS mapping
	 * We need to know what JS class to use/load for a PHP class
	 *
	 * @var string[]
	 */
	private static $PHPClassToJSClass;
	/**
	 * The JS classes list with their location
	 * The information is stored in: self::$RuntimeFolder/temp/autoload_js.php
	 *
	 * @var string[]
	 */
	private static $JSClasses = null;
	/**
	 * The CSS classes list with their location
	 * The information is stored in: self::$RuntimeFolder/temp/autoload_css.php
	 *
	 * @var string[]
	 */
	private static $CssClasses = null;
	/**
	 * Keeps track if the self::$ClassParents property was loaded
	 *
	 * @var boolean
	 */
	private static $ClassParentsIncluded;
	/**
	 * Keeps track of class parents per watch folder
	 * The information is stored in: self::$RuntimeFolder/temp/classes_parents.php
	 * 
	 * @var string[][]
	 */
	private static $ClassParents = null;
	/**
	 * We need to make sure that while in Sync no classes are autoloaded
	 *
	 * @var boolean
	 */
	private static $LockAutoload = false;
	/**
	 * Keep track if this is a intranet request. 
	 * Please see self::IsIntranetRequest()
	 * 
	 * @var boolean
	 */
	private static $IsIntranetRequest = null;
	/**
	 * True if we are in development mode.
	 * The development mode will activate self::ScanForChanges() and certain tools.
	 * Please see [[mods/dev-panel]] for more details.
	 *
	 * @var boolean
	 */
	private static $DevelopmentMode = false;
	protected static $DevelopmentModeAuthentications;
	/**
	 * You may not add watch folders after ScanForChanges was called
	 *
	 * @var boolean
	 */
	private static $LockAddWatchFolders = false;
	/**
	 * Data Validators
	 * 
	 * @var string[]
	 */
	private static $DataValidators;
	/**
	 * Data fixers
	 *
	 * @var string[]
	 */
	private static $DataFixers;
	/**
	 * Data fixers
	 *
	 * @var string[]
	 */
	private static $DataEncoders;
	/**
	 * Gets the web path to the main folder
	 *
	 * @var string
	 */
	private static $MainFolderWebPath;
	
	public static $HasChanges;

	/**
	 * Adds a folder to the list of folders that are scaned for classes
	 * 
	 * @param string $path The path to be added
	 * 
	 */
	public static function AddMainFolder($path, $tag = null)
	{
		return self::AddWatchFolder($path, true, $tag);
	}
	
	/**
	 * Includes a folder to the list of folders that are scaned for classes
	 * The include is Read-only
	 * 
	 * @param string $path
	 * @param string $tag
	 * @return type
	 */
	public static function IncludeFolder($path, $tag = null)
	{
		return self::AddWatchFolder($path, false, $tag, true);
	}
	
	/**
	 * Adds a folder to the list of folders that are scaned for classes
	 * 
	 * @param string $path The path to be added
	 * @param boolean $set_as_runtime If true the path becomes the default (runtime) folder of the application
	 */
	public static function AddWatchFolder($path, $set_as_runtime = false, $tag = null, $read_only = false)
	{
		if (self::$LockAddWatchFolders)
			throw new Exception("You may not call QAutoload::AddWatchFolder() or QAutoload::LoadModule() after QAutoload::ScanForChanges() or QAutoload::EnableDevelopmentMode() was called.");
		if (!is_dir($path))
			throw new Exception("Invalid directory: ".$path);
		$path = realpath($path)."/";
		self::$WatchFolders[$path] = $path;
		self::$WatchFoldersReadOnly[$path] = $read_only;
		
		if ((!$tag) && defined('Q_RUN_CODE_UPGRADE_TO_TRAIT') && Q_RUN_CODE_UPGRADE_TO_TRAIT)
			throw new \Exception('Code folder tags are mandatory in upgrade mode: '.$path);
		else if (defined('Q_RUN_CODE_UPGRADE_TO_TRAIT') && Q_RUN_CODE_UPGRADE_TO_TRAIT && isset(self::$WatchFoldersByTag[$tag]))
			throw new \Exception('Code folder tags must be unique in upgrade mode: '.$path);
		
		if ($tag !== false)
		{
			if (!$tag)
			{
				$b_path = $path;
				do
				{
					$tag = basename($b_path);
					$b_path = dirname($b_path);
				}
				while (($bt_path = self::$WatchFoldersByTag[$tag]) && ($bt_path !== $path));
			}
			self::$WatchFoldersByTag[$tag] = $path;
		}
		
		if ($set_as_runtime)
		{
			self::SetRuntimeFolder($path);
			self::$MainFolderWebPath = "/".ltrim(substr($path, strlen($_SERVER["DOCUMENT_ROOT"])), "/");
		}
	}
	
	/**
	 * Checks if a class exists in the cache
	 * 
	 * @param string $class_name The name of the class
	 * @param boolean $force_reload If true a rescan is done
	 * @return boolean
	 */
	public static function ClassExists($class_name, $force_reload = false)
	{
		if (!$force_reload)
			return isset(self::$AutoloadArray[$class_name]);
		else
			return class_exists($class_name);
	}
    
	/**
	 * Gets the folders beeing watched
	 * 
	 * @return string[]
	 */
	public static function GetWatchFolders()
	{
		return self::$WatchFolders;
	}
	
	/**
	 * Gets the path for the specifed class name
	 * 
	 * @param string $class_name
	 * @return string
	 */
	public static function GetClassFileName($class_name)
	{
		if (!self::$AutoloadIncluded)
			self::EnsureAutoloadWasIncluded($class_name);
		return ($fn = self::$AutoloadArray[$class_name]) ? (is_string($fn) ? $fn : reset($fn)) : null;
	}
	
	/**
	 * Gets the self::$AutoloadArray stored as key => value, $CLASS => $PATH
	 * 
	 * @return string[]
	 */
	public static function GetAutoloadData()
	{
		return self::$AutoloadArray;
	}
	
	/**
	 * Gets called by the PHP engine when a class is not found or included (via __autoload)
	 * 
	 * @param string $class_name
	 */
	public static function AutoloadClass($class_name)
	{
		if (($qp = self::$AutoloadArray[$class_name]))
		{
			if (is_string($qp))
				require_once($qp);
			else
			{
				foreach ($qp as $_qp)
					require_once($_qp);
			}
		}
		else
		{
			// in case we are in sync mode we are not allowed to load classes unless they have been already listed
			if (self::$LockAutoload)
				throw new Exception("Class `{$class_name}` was requested for load during sync.");
		
			if (!self::$AutoloadIncluded)
				self::EnsureAutoloadWasIncluded($class_name);
			
			if (($qp = self::$AutoloadArray[$class_name]))
			{
				if (is_string($qp))
					require_once($qp);
				else
				{
					foreach ($qp as $_qp)
						require_once($_qp);
				}
			}
		}
	}
	
	/**
	 * Gets the JS class that should be used for the specified PHP class that is a web component.
	 * 
	 * @param string $class
	 * @return string
	 */
	public static function GetJsClassPath($class)
	{
		if (self::$JSClasses === null)
		{
			if (file_exists(self::$RuntimeFolder."temp/autoload_js.php"))
				require(self::$RuntimeFolder."temp/autoload_js.php");
			self::$JSClasses = $_Q_FRAME_JS_LOAD_ARRAY ?: array();
		}
		return self::$JSClasses[$class];
	}
	
	/**
	 * Gets self::$JSClasses
	 * 
	 * @return string[]
	 */
	public static function GetAllJsClasses()
	{
		if (self::$JSClasses === null)
		{
			if (file_exists(self::$RuntimeFolder."temp/autoload_js.php"))
				require(self::$RuntimeFolder."temp/autoload_js.php");
			self::$JSClasses = $_Q_FRAME_JS_LOAD_ARRAY ?: array();
		}
		return self::$JSClasses;
	}
	
	/**
	 * Gets the CSS file that should be included for the specifed PHP class that is a web component.
	 * 
	 * @param string $class
	 * @return string
	 */
	public static function GetCssClassPath($class)
	{
		if (self::$CssClasses === null)
		{
			if (file_exists(self::$RuntimeFolder."temp/autoload_css.php"))
				require(self::$RuntimeFolder."temp/autoload_css.php");
			self::$CssClasses = $_Q_FRAME_CSS_LOAD_ARRAY ?: array();	
		}
		return self::$CssClasses[$class];
	}
	
	/**
	 * Gets self::$CssClasses
	 * 
	 * @return string[]
	 */
	public static function GetAllCssClasses()
	{
		if (self::$CssClasses === null)
		{
			if (file_exists(self::$RuntimeFolder."temp/autoload_css.php"))
				require(self::$RuntimeFolder."temp/autoload_css.php");
			self::$CssClasses = $_Q_FRAME_CSS_LOAD_ARRAY ?: array();	
		}
		return self::$CssClasses;
	}
	
	/**
	 * Gets the list of classes in the cache
	 * 
	 * @return string[]
	 */
	public static function GetAllWatchedClasses()
	{
		self::EnsureAutoloadWasIncluded();
		return self::$AutoloadArrayKeys ?: (self::$AutoloadArray ? (self::$AutoloadArrayKeys = array_keys(self::$AutoloadArray)) : null);
	}
	/**
	 * Gets the runtime folder
	 * 
	 * @return string
	 */
	public static function GetRuntimeFolder()
	{
		return self::$RuntimeFolder;
	}

	/**
	 * Loads a module.
	 * It is similar to self::AddWatchFolder(), but it also calls for include.php and init.php in the module if they exist.
	 * 
	 * @param string $path
	 * @param boolean $set_as_runtime
	 */
	public static function LoadModule($path, $set_as_runtime = false, $tag = null)
	{
		if (file_exists($path."include.php"))
			require_once($path."include.php");
		self::AddWatchFolder($path, $set_as_runtime, $tag, false);
		if (file_exists($path."init.php"))
			require_once($path."init.php");
	}
	
	public static function GetWatchFoldersByTags()
	{
		return self::$WatchFoldersByTag;
	}
	
	/**
	 * Makes sure we have included the script with the extended by data
	 */
	private static function EnsureExtendedByWasIncluded()
	{
		if (!self::$ExtendedByIncluded)
		{
			// if we never included the autoload file, include it now
			if (!file_exists(self::$RuntimeFolder."temp/extended_by.php"))
				throw new Exception("Missing cache file: ".self::$RuntimeFolder."temp/extended_by.php");
			include(self::$RuntimeFolder."temp/extended_by.php");
			self::$ExtendedByList = $_Q_FRAME_EXTENDED_BY;
			self::$ExtendedByIncluded = true;
		}
	}
	
	/**
	 * Makes sure we have included the script with the parent classes data
	 */
	private static function EnsureClassParentsWasIncluded()
	{
		if (!self::$ClassParentsIncluded)
		{
			// if we never included the autoload file, include it now
			if (!file_exists(self::$RuntimeFolder."temp/classes_parents.php"))
				throw new Exception("Missing cache file: ".self::$RuntimeFolder."temp/classes_parents.php");
			include(self::$RuntimeFolder."temp/classes_parents.php");
			self::$ClassParents = $Q_CLASS_PARENTS_SAVE;
			self::$ClassParentsIncluded = true;
		}
	}
	
	/**
	 * Makes sure we have included the script with the autoload data
	 */
	private static function EnsureAutoloadWasIncluded($class_name = null)
	{
		if (!self::$AutoloadIncluded)
		{
			// if we never included the autoload file, include it now
			if (!file_exists(self::$RuntimeFolder."temp/autoload.php"))
				throw new Exception("Missing cache file: ".self::$RuntimeFolder."temp/autoload.php");
			include(self::$RuntimeFolder."temp/autoload.php");
			self::$AutoloadArray = $_Q_FRAME_LOAD_ARRAY;
			self::$AutoloadArrayKeys = null;
			self::$AutoloadIncluded = true;
		}
	}
	
	/**
	 * Translates the self::$RuntimeFolder/temp folder in the web path and appends the $for parameter if inputed.
	 * 
	 * @param string $for
	 * @return string
	 */
	public static function GetTempWebPath($for = null)
	{
		if ($for)
			return substr(self::$RuntimeFolder."temp/".$for, strlen(Q_RUNNING_PATH));
		else
			return substr(self::$RuntimeFolder."temp/", strlen(Q_RUNNING_PATH));
	}
	
	/**
	 * @return string
	 */
	public static function GetTempFolder()
	{
		return self::$RuntimeFolder."temp/";
	}
	
	/**
	 * Gets the JS class that should be loaded for a PHP class that is a web control
	 * 
	 * @param string $php_class
	 * @return string
	 */
	public static function GetJSClassFor($php_class)
	{
		$was_loaded = false;
		if (self::$PHPClassToJSClass === null)
		{
			include(self::$RuntimeFolder."temp/php_js.php");
			self::$PHPClassToJSClass = $_Q_FRAME_PHP_JS_ARRAY;
			$was_loaded = true;
		}
		return self::$PHPClassToJSClass[$php_class];
	}
	
	/**
	 * Gets the list of all PHP classes that are web controls and have a JS class that should be used for them.
	 * 
	 * @return string[]
	 */
	public static function GetAllJSClassForPhpClass()
	{
		if (self::$PHPClassToJSClass === null)
		{
			include(self::$RuntimeFolder."temp/php_js.php");
			self::$PHPClassToJSClass = $_Q_FRAME_PHP_JS_ARRAY;
		}
		return self::$PHPClassToJSClass;
	}

	/**
	 * Gets the list of classes with a list of classes that extend that class 
	 * 
	 * @return array[]
	 */
	public static function GetExtendedByList()
	{
		self::EnsureExtendedByWasIncluded();
		return self::$ExtendedByList;
	}
	
	/**
	 * Gets the list of classes with their parents
	 * 
	 * @return array[]
	 */
	public static function GetClassParentsList()
	{
		self::EnsureClassParentsWasIncluded();
		return self::$ClassParents;
	}
	
	/**
	 * Gets the list of clasess/interfaces that extend the specified type
	 * 
	 * @param string $class
	 * 
	 * @return string[]
	 */
	public static function GetClassExtendedBy($class)
	{
		self::EnsureExtendedByWasIncluded();
		return self::$ExtendedByList[$class];
	}
	
	/**
	 * Sets the runtime folder to be used. self::$RuntimeFolder
	 * 
	 * @param string $path
	 */
	public static function SetRuntimeFolder($path)
	{
		if (!is_dir($path."temp/"))
			mkdir($path."temp/", (0777 & ~umask()), true);
		self::$RuntimeFolder = $path;
	}
	
	/**
	 * Scans all folders that are in self::$WatchFolders
	 * If there are changes, new files or edited files or removed files, a QCodeSync::resync will be triggered.
	 * Important notes:
	 *		Files starting with a lowercase letter will be ignored.
	 *		Folders directly inside a watch folder that start with "~" will be ignored. This is very useful 
	 *		when you need to have a watch folder inside another watch folder.
	 * 
	 * @param boolean $full_resync
	 * @param boolean $debug_mode
	 * @param string $path
	 * @param string[] $avoid_folders
	 * @param boolean $skip_on_ajax
	 * @param integer[] $info
	 * @param integer[] $files_state
	 * @param integer[] $changed
	 * @param integer[] $new
	 * @param string $root_folder
	 * @param integer[] $top_info
	 * @param integer[] $top_files_state
	 * @param integer[] $top_changed
	 * @param integer[] $top_new
	 * @return void
	 * @throws Exception
	 */
	public static function ScanForChanges($full_resync = false, $debug_mode = false, $path = null, $avoid_folders = null, 
											$skip_on_ajax = true,
											&$info = null, &$files_state = null, &$changed = null, &$new = null, $root_folder = null,
											&$top_info = null, &$top_files_state = null, &$top_changed = null, &$top_new = null)
	{
		if ($path)
		{
			$is_frame_now = ($root_folder === Q_FRAME_PATH);
			
			if (!$root_folder)
			{
				$root_folder = $path;
				$path = "";
			}
			
			$res = scandir($root_folder.$path);
			$js_change = false;
			$css_change = false;
			$js_bag = [];
			$css_bag = [];
			
			foreach ($res as $f)
			{
				$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
				
				if (($f === ".") || ($f === "..") || ($f === "_compiled") || ($f === "temp") || ($f === "tmp") || ($f === ".git") || ($f === '~gens'))
					continue;
				else if (($f === "_generated") && ($path === "") && $top_info)
				{
					// _generated is a virtual watch folder
					$gen_folder = $root_folder.$f."/";
					self::ScanForChanges($full_resync, $debug_mode, $gen_folder, null, $skip_on_ajax, $top_info[$gen_folder], $top_files_state[$gen_folder], $top_changed[$gen_folder], $top_new[$gen_folder]);
					continue;
				}

				$child = $root_folder.$path.$f;
				$rel = $path.$f;
				
				if (is_dir($child))
				{
					$af = $avoid_folders ? $avoid_folders[$f] : null;
					if (($af === true) || (($f{0} === "~") && ($path === "")))
						continue;
					self::ScanForChanges($full_resync, $debug_mode, $rel."/", $af, $skip_on_ajax, $info, $files_state, $changed, $new, $root_folder);
				}
				else if (($f_0 = $f{0}) && ($f_0 !== strtolower($f_0)))
				{
					// && ((($p = strrpos($f, ".")) !== false) ? ((($ext = substr($f, $p + 1)) === "php") || ($ext === "tpl")) : false)
					$mt = filemtime($child);
					// $ext = (($p = strrpos($f, ".")) !== false) ? substr($f, $p + 1) : null;
					if (($ext === "php") || ($ext === "tpl") || ($ext === "css") || ($ext === "js"))
					{
						$info[$rel] = $mt;
						
						// if it's a generated file we don't care if it was modified or not
						// for CSS / JS we only track their presence
						if (($ext === "php") && ($sub_ext = substr($f, - strlen($ext) - 5, 4)) && (($sub_ext === ".gen") || ($sub_ext === ".dyn")))
						{
							unset($files_state[$rel]);
						}
						else if (($fsmt = $files_state[$rel]) !== null)
						{
							// no change | only for now
							// we also make sure we don't track changes for "css" and "js"
							if (($fsmt !== $mt) && (($ext === "php") || ($ext === "tpl")))
								$changed[$rel] = $mt;
							unset($files_state[$rel]);
						}
						else
						{
							// new file
							$changed[$rel] = $mt;
							$new[$rel] = $mt;
						}
					}
				}
				else if ((($ext === "css") || ($ext === "js")) && (strtolower(pathinfo(substr($f, 0, -(strlen($ext) + 1)), PATHINFO_EXTENSION)) !== "min"))
				{
					if ($is_frame_now && ($path === "view/mvvm/"))
					{
						if ($ext === "js")
							$js_bag[] = $f;
						else if ($ext === "css")
							$css_bag[] = $f;
						$mt = filemtime($child);
						$info[$rel] = $mt;
						if (($fsmt = $files_state[$rel]) !== null)
						{
							// no change
							if ($fsmt !== $mt)
							{
								$changed[$rel] = $mt;
								if ($ext === "js")
									$js_change = true;
								else if ($ext === "css")
									$css_change = true;
							}
							unset($files_state[$rel]);
						}
						else
						{
							// new file
							$changed[$rel] = $mt;
							$new[$rel] = $mt;
							if ($ext === "js")
								$js_change = true;
							else if ($ext === "css")
								$css_change = true;
						}
					}
				}
			}
			
			$mvvm_path = $root_folder."view/js/mvvm.js";
			if ($is_frame_now && ($path === "view/mvvm/") && ($js_change || $css_change || (!file_exists($mvvm_path))))
			{
				if ($js_bag && ($js_change || (!file_exists($mvvm_path))))
				{
					sort($js_bag);
					$js_content = "";
					foreach ($js_bag as $js_f)
					{
						if (strtolower(substr($js_f, -7)) === ".min.js")
							continue;
						$full_js_f = $root_folder.$path.$js_f;
						$js_content .= "\n// FILE: {$full_js_f}\n".file_get_contents($full_js_f)."\n\n";
					}
					file_put_contents($mvvm_path, $js_content);
				}
				/*
				if ($css_bag && $css_change)
				{
					// no rule here atm	
				}
				*/
			}
		}
		else
		{
			$HTTP_X_REQUESTED_WITH = filter_input(INPUT_SERVER, "HTTP_X_REQUESTED_WITH");

			self::$LockAddWatchFolders = true;
			
			if ($skip_on_ajax && (isset($HTTP_X_REQUESTED_WITH) && (strtolower($HTTP_X_REQUESTED_WITH) === 'xmlhttprequest')))
				return;

			$lock_path = QAutoload::GetRuntimeFolder()."temp/codemonitor.txt";
			if (!file_exists($lock_path))
			{
				$lock_f = fopen($lock_path, "wt");
				fwrite($lock_f, "QCodeMonitor lock");
				fclose($lock_f);
			}

			require_once(Q_FRAME_PATH."io/QFileLock.php");
			$lock = QFileLock::lock($lock_path, 5);
			if (!$lock)
				throw new Exception("Unable to get lock for QCodeMonitor::ScanForChanges on: ".$lock_path);		
			else
			{
				try
				{
					$intial_full_resync = $full_resync;
					$full_resync_reason = $intial_full_resync ? 'requested' : null;
					
					$scan_in = QAutoload::GetWatchFolders();
					if (!$scan_in)
						return;
					
					self::$LockAutoload = true;

					$save_state_path = QAutoload::GetRuntimeFolder()."temp/files_state.php";
					$has_file_state = false;
					if (file_exists($save_state_path))
					{
						require($save_state_path);
						$files_state = $Q_FILES_STATE_SAVE;
						$has_file_state = true;
					}
					else
					{
						$files_state = array();
						$full_resync = true;
						$full_resync_reason = 'missing files_state.php';
					}
					
					if (!(	file_exists(QAutoload::GetRuntimeFolder()."temp/autoload.php") && 
							(file_exists(QAutoload::GetRuntimeFolder()."temp/classes_parents.php") || Q_RUN_CODE_NEW_AS_TRAITS) && 
							file_exists(QAutoload::GetRuntimeFolder()."temp/extended_by.php") && 
							(file_exists(QAutoload::GetRuntimeFolder()."temp/implements.php") || Q_RUN_CODE_NEW_AS_TRAITS)))
					{
						$full_resync = true;
						$full_resync_reason = 'missing some temp files (autoload or classes_parents or extended_by or implements)';
					}
					
					$changed = array();
					$new = array();
					$pos = 0;
					$avoid_frame_folders = array("view" => array("js" => true));
					$info = array();
					
					foreach ($scan_in as $folder)
					{
						// $read_only = self::$WatchFoldersReadOnly[$folder];
						//if (self::$WatchFoldersReadOnly[$folder])
						//	continue;
						
						$info[$folder] = array();
						if (!$files_state[$folder])
							$files_state[$folder] = array();
						$changed[$folder] = array();
						$new[$folder] = array();
						
						// new way of doing things under linux
						// find /etc -newer /tmp/foo/
						/*
						if ($has_file_state)
						{
							
							$exec_out = array();
							$exec_ret = null;
							exec("find {$folder} -newer ".$save_state_path, $exec_out, $exec_ret);
							 * 
							
						}
						*/

						self::ScanForChanges($full_resync, $debug_mode, $folder, (($pos === 0) ? $avoid_frame_folders : null), $skip_on_ajax,
								$info[$folder], $files_state[$folder], $changed[$folder], $new[$folder], null, $info, $files_state, $changed, $new);
						
						if (empty($files_state[$folder]))
							unset($files_state[$folder]);
						if (empty($changed[$folder]))
							unset($changed[$folder]);
						if (empty($new[$folder]))
							unset($new[$folder]);

						$pos++;
					}
					
					// we need to include all required classes 'manually' as Autoload will not be relayable while in sync
					self::IncludeClassesInFolder(Q_FRAME_PATH."model/type/", true);
					self::IncludeClassesInFolder(Q_FRAME_PATH."useful/code/", true);
					self::IncludeClassesInFolder(Q_FRAME_PATH."useful/parsers/", true);
					// self::IncludeClassesInFolder(Q_FRAME_PATH."controller/", true);

					// changed files : $changed
					// removed files : $files_state
					
					if ($full_resync || $new || $changed || $files_state)
					{
						static::$HasChanges = true;
						// based on some files dependency the code sync should be able to manage the issues 
						$Q_RUN_CODE_NEW_AS_TRAITS = defined('Q_RUN_CODE_NEW_AS_TRAITS') && Q_RUN_CODE_NEW_AS_TRAITS;
						
						$sync = ((defined('Q_RUN_CODE_UPGRADE_TO_TRAIT') && Q_RUN_CODE_UPGRADE_TO_TRAIT) || 
									(defined('Q_RUN_CODE_NEW_AS_TRAITS') && Q_RUN_CODE_NEW_AS_TRAITS))
									? new QCodeSync2() : new QCodeSync();
						$sync->fullSync = $full_resync;
						$sync->debugMode = $debug_mode;
						
						// $files_state are the files that were removed
						$removed_files = $files_state;
						
						$t0 = microtime(true);
						
						if ((!$Q_RUN_CODE_NEW_AS_TRAITS) && $removed_files)
						{
							/* if a GEN file was removed, we will need to set the others as changed
							   to trigger a rebuild */
							
							$unset_elements = array();
							
							foreach ($removed_files as $rf_wf => $rem_file_in_wf)
							{
								foreach ($rem_file_in_wf as $rem_file => $mod_time)
								{
									if (substr($rem_file, -8, 8) === ".gen.php")
									{
										$rem_class_name_with_dot = substr(basename($rem_file), 0, -7);
										$rem_class_name_with_dot_len = strlen($rem_class_name_with_dot);
										// yes a generated file was removed and we need to rebuild
										// make sure all files like this one are marked as changed
										
										if (($t_wf = $info[$rf_wf]))
										{
											foreach ($t_wf as $f_name => $ft)
											{
												$b_name = basename($f_name);
												if (substr($b_name, 0, $rem_class_name_with_dot_len) === $rem_class_name_with_dot)
												{
													$changed[$rf_wf][$f_name] = $ft;
												}
											}
										}
										$unset_elements[$rf_wf][$rem_file] = null;
									}
								}
							}
							
							if ($unset_elements)
							{
								foreach ($unset_elements as $ue_wf => $ue_elem)
								{
									foreach ($ue_elem as $ue_f => $ue_t)
										unset($removed_files[$ue_wf][$ue_f]);
									if (empty($removed_files[$ue_wf]))
										unset($removed_files[$ue_wf]);
								}
								if (empty($removed_files))
									$removed_files = null;
							}
						}
						
						if (($full_resync || $removed_files) && (!$intial_full_resync))
						{
							if ($removed_files)
							{
								if ((!defined('Q_RUN_CODE_NEW_AS_TRAITS')) || (!Q_RUN_CODE_NEW_AS_TRAITS))
								{
									qvar_dumpk($removed_files);
									throw new \Exception('Some files were removed. We will not start a full resync unless is explicit.');
								}
							}
							else
							{
								throw new \Exception('We will not start a full resync unless is explicit. Reason: '.$full_resync_reason);
							}
						}
						
						if ($full_resync || $removed_files)
						{
							// 10 mins max
							if (ini_get('max_execution_time') < (60 * 10))
								set_time_limit(60 * 10);
						}
						else // not full sync
						{
							// 1 min max
							if (ini_get('max_execution_time') < (60 * 5))
								set_time_limit(60 * 5);
						}
						
						$sync->resync($info, $changed, $removed_files, $new, $full_resync);
						
						$failed = file_put_contents($save_state_path, qArrayToCode($info, "Q_FILES_STATE_SAVE"));
						if ($failed === false)
							throw new \Exception('Unable to save files state');
						opcache_invalidate($save_state_path, true);
					}
					else
					{
						static::$HasChanges = false;
						// output warnings
						$incl_traits_inf = QCodeSync::GetTraitsWasIncluded();
						if ($incl_traits_inf)
						{
							foreach ($incl_traits_inf as $class_file => $included)
							{
								if (!$included)
								{
									$class_path = (($patch_pos = strpos($class_file, ".patch.")) !== false) ? substr($class_file, 0, $patch_pos).".php" : $class_file;
									$className = pathinfo($class_path, PATHINFO_FILENAME);
									$gen_file_path = substr($class_path, 0, -3)."gen.php";
									if (file_exists($gen_file_path))
										echo "You must include in {$class_file} the trait '{$className}_GenTrait' as generated in {$gen_file_path}.<br/>\n";
								}
							}
						}
					}
				}
				catch (Exception $ex)
				{
					// make sure we unlock
					$lock->unlock();
					self::$LockAutoload = false;
					// qvar_dumpk($ex->getTrace());
					echo \QErrorHandler::GetExceptionToHtml($ex);
					throw $ex;
				}
				finally
				{
					$lock->unlock();
					self::$LockAutoload = false;
				}
			}
		}
	}
	
	/**
	 * There are cases, like in self::ScanForChanges(), when we need to load classes without using the autoloader.
	 * This method will load the classes in a specified folder without triggering the autoloader.
	 * 
	 * @param string $folder
	 * @param boolean $recursive
	 * @param boolean $use_compiled_if_exists
	 */
	private static function IncludeClassesInFolder($folder, $recursive = false, $use_compiled_if_exists = true)
	{
		$items = scandir($folder);
		foreach ($items as $item)
		{
			if (($item === "..") || ($item === "."))
				continue;
			
			$fp = null;
			$parts = explode(".", $item);
			
			if (($item{0} !== strtolower($item{0})) && (end($parts) === "php"))
			{
				$fp = $folder.$item;
				$count_parts = count($parts);
				if ($count_parts === 2)
				{
					// php only
					self::$AutoloadArray[$parts[0]] = $fp;
					$cf = self::GetCompiledPath($fp);
					if (file_exists($cf))
						self::$AutoloadArray[$parts[0]."_GenTrait"] = $cf;
				}
				else if (($count_parts === 3) && ($parts[1] === "gen"))
				{
					if (!self::$AutoloadArray[$parts[0]])
						self::$AutoloadArray[$parts[0]] = $fp;
					else
						self::$AutoloadArray[$parts[0]."_GenTrait"] = $cf;
				}
			}
			else if ($recursive && is_dir($fp = $folder.$item))
				self::IncludeClassesInFolder($fp."/", $recursive);
		}
	}
	
	/**
	 * The $path param must be the full path
	 * 
	 * @param string $path
	 */
	public static function GetCompiledPath($path)
	{
		return substr($path, 0, -3)."gen.php";
	}
	
	/**
	 * Sets the autoload information. Used by QCodeSync.
	 * 
	 * @param string[] $array
	 * @param boolean $set_included
	 */
	public static function SetAutoloadArray($array, $set_included = true)
	{
		self::$AutoloadArray = $array;
		if ($set_included)
			self::$AutoloadIncluded = true;
	}
	
	/**
	 * Sets the extended by information. Used by QCodeSync.
	 * 
	 * @param string[] $array
	 * @param boolean $set_included
	 */
	public static function SetExtendedBy($array, $set_included = true)
	{
		self::$ExtendedByList = $array;
		if ($set_included)
			self::$ExtendedByIncluded = true;
	}
	
	/**
	 * Gets the Watch Folder where the specified file is in.
	 * If none found returns null.
	 * 
	 * @todo Cache and improve performance
	 * 
	 * @param string $path
	 * @return string
	 */
	public static function GetModulePathForPath($path)
	{
		if (!self::$WatchFolders)
			return null;
		// we need to get the max from it
		$found = null;
		$found_len = null;
		foreach (self::$WatchFolders as $wf)
		{
			$len = strlen($wf);
			if (((!$found) || ($found_len < $len)) && substr($path, 0, $len) === $wf)
			{
				$found = $wf;
				$found_len = $len;
			}
		}
		return $found;
	}
	
	/**
	 * Enables development mode based on the $restriction variable.
	 * By default requests from the intranet (see self::IsIntranetRequest) will enable DevelopmentMode
	 * 
	 * @todo we could also accept IP ranges like 192.168.1.* or 192.168.*.*
	 * 
	 * @param string $restriction
	 * @param boolean $full_resync
	 * @param boolean $debug_mode
	 * 
	 */
	public static function EnableDevelopmentMode($restriction = "default", $full_resync = false, $debug_mode = false)
	{
		// skip for AJAX
		$HTTP_X_REQUESTED_WITH = filter_input(INPUT_SERVER, "HTTP_X_REQUESTED_WITH");
		$ajax_mode = ((isset($HTTP_X_REQUESTED_WITH) && (strtolower($HTTP_X_REQUESTED_WITH) === 'xmlhttprequest')) || ($_POST["__qFastAjax__"] || $_GET["__qFastAjax__"]));

		// auth:user:pass
		if (is_string($restriction))
			$restrictions = preg_split("/[\\s\\,;]+/us", $restriction, -1, PREG_SPLIT_NO_EMPTY);
		else if (is_array($restriction))
			$restrictions = $restriction;
		else
			$restrictions = [$restriction];
		
		$scaned = false;
		
		$authentications = [];
		
		foreach ($restrictions as $restriction)
		{
			if ($restriction === true)
			{
				self::RunDevelopmenMode($full_resync, $debug_mode, $ajax_mode);
				$scaned = true;
				break;
			}
			else if (($restriction === "default") || ($restriction === null))
			{
				if (self::IsIntranetRequest())
				{
					self::RunDevelopmenMode($full_resync, $debug_mode, $ajax_mode);
					$scaned = true;
					break;
				}
			}
			else if (filter_var($restriction, FILTER_VALIDATE_IP) !== false)
			{
				if ($restriction === filter_input(INPUT_SERVER, array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) ? "HTTP_X_FORWARDED_FOR" : "REMOTE_ADDR", FILTER_VALIDATE_IP))
				{
					self::RunDevelopmenMode($full_resync, $debug_mode, $ajax_mode);
					$scaned = true;
					break;
				}
			}
			else if ($restriction === "panel-only")
			{
				// self::$DevelopmentMode = true;
				self::RunDevelopmenMode(false, $debug_mode, true);
			}
			else if ($restriction === "none")
			{
				self::RunDevelopmenMode($full_resync, $debug_mode, $ajax_mode);
				$scaned = true;
				break;
			}
			else if (substr($restriction, 0, strlen("auth:")) === "auth:")
			{
				list(, $user, $pass) = preg_split("/\\s*[\\:]+\\s*/us", $restriction, 3, PREG_SPLIT_NO_EMPTY);
				$authentications[$user] = $pass;
			}
		}
		
		/*
		// try to see if it's authenticated
		if ((!$scaned) && $authentications)
		{
			self::$DevelopmentModeAuthentications = $authentications;
			if (self::IsDevelopmentAuthenticated($authentications, true))
			{
				self::RunDevelopmenMode($full_resync, $debug_mode);
				$scaned = true;
			}
		}
		*/
		
		define("Q_DEV", self::$DevelopmentMode ? true : false);
		
		if (!$scaned)
		{
			// check that it was deployed
			$was_deployed = QAutoload::WasDeployed();
			if (!$was_deployed)
			{
				/*
				qvar_dumpk(debug_backtrace());
				throw new \Exception('wtf !?!');
				// also put a warning that deployment was not made
				echo QAutoload::GetWasNotDeployedMessage();
				*/
			}
		}
	}
	
	/**
	 * Checks if the app was deployed
	 * 
	 * @return boolean
	 */
	public static function WasDeployed()
	{
		$runtime_folder = QAutoload::GetRuntimeFolder();
		// check that it was deployed
		$was_deployed = false;
		if ($runtime_folder !== null)
		{
			// check that it was deployed
			$deploy_inf_file = $runtime_folder."temp/deploy.php";
			if (file_exists($deploy_inf_file))
			{
				$deploy_data = trim(file_get_contents($deploy_inf_file));
				if ($deploy_data === self::GetDeployString())
					$was_deployed = true;
			}
		}
		return $was_deployed;
	}
	
	/**
	 * Gets the deployment identifier based on path and server identity using php_uname with 's', 'n' and 'm'
	 * 
	 * @return string
	 */
	public static function GetDeployString()
	{
		return trim(Q_RUNNING_PATH."\n".php_uname("s")."\n".php_uname("n")."\n".php_uname("m"));
	}
	
	public static function GetWasNotDeployedMessage()
	{
		return "<p><span style='color: red;'>It looks like this application was not yet deployed.</span><br/>\n".
						"In `index.php` you will need to set \\QAutoload::EnableDevelopmentMode(\"".qaddslashes($_SERVER["REMOTE_ADDR"])."\") to deploy the application.<br/>\n".
						"\\QAutoload::EnableDevelopmentMode(\$your_ip) will execute if your remote IP matches the first parameter. There are also other options please see the specs.<br/>\n".
						"The following error message is most likeley caused by this.</p><br/>\n".
				"<p>Your IP is: ".Q_REMOTE_ADDR."</p>";
	}
	
	public static function SetDevelopmenModeLight(bool $value)
	{
		self::$DevelopmentMode = $value;
	}
	
	protected static function RunDevelopmenMode($full_resync, $debug_mode, $ajax_mode = false)
	{
		self::$DevelopmentMode = true;
		
		if (!$ajax_mode)
		{
			try
			{
				self::ScanForChanges($full_resync, $debug_mode);
			}
			catch (\Exception $ex)
			{
				qvar_dump($ex->getMessage(), $ex->getTraceAsString());
				throw $ex;
			}
		}
	}
	
	/**
	 * Returns self::$DevelopmentMode
	 * 
	 * @return boolean
	 */
	public static function GetDevelopmentMode()
	{
		return self::$DevelopmentMode;
	}
	
	/**
	 * Returns self::$DevelopmentMode
	 * 
	 * @return boolean
	 */
	public static function SetDevelopmentMode($mode = true)
	{
		return self::$DevelopmentMode = $mode;
	}
	
	/**
	 * Checks if it's an intranet request.
	 * Returns true if only the last value of the 'REMOTE_ADDR' and 'SERVER_ADDR' is different.
	 * 
	 * @return boolean
	 */
	public static function IsIntranetRequest()
	{
		if (self::$IsIntranetRequest !== null)
			return self::$IsIntranetRequest;
		// 	'REMOTE_ADDR' => string '192.168.1.2' (length=11)
		//  'SERVER_ADDR' => string '192.168.1.101' (length=13)
		$ra = filter_input(INPUT_SERVER, "REMOTE_ADDR", FILTER_VALIDATE_IP);
		$sa = filter_input(INPUT_SERVER, "SERVER_ADDR", FILTER_VALIDATE_IP);
		
		if (!(is_string($ra) && is_string($sa)))
			return (self::$IsIntranetRequest = false);
		
		$ra_dotpos = strrpos($ra, ".");
		$sa_dotpos = strrpos($sa, ".");
		
		if (!($ra_dotpos && $sa_dotpos))
			return (self::$IsIntranetRequest = false);
		
		return (self::$IsIntranetRequest = (substr($ra, 0, $sa_dotpos) === substr($sa, 0, $sa_dotpos)));
	}
	
	/**
	 * Unlocks autoload
	 */
	public static function UnlockAutoload()
	{
		self::$LockAutoload = false;
	}
	
	/**
	 * Gets the data validators
	 * 
	 * @return mixed[]
	 */
	public static function GetDataValidators()
	{
		if (self::$DataValidators !== null)
			return self::$DataValidators;
		include(__DIR__."/validators.php");
		self::$DataFixers = $_Q_Fixers;
		self::$DataEncoders = $_Q_Encoders;
		return (self::$DataValidators = $_Q_Validators);
	}
	
	/**
	 * Sets the new validators
	 * 
	 * @param mixed[] $validators
	 */
	public static function SetDataValidators($validators, $full_replace = false)
	{
		if ($full_replace)
			self::$DataValidators = $validators;
		else
		{
			$old_validators = self::$DataValidators ?: self::GetDataValidators();
			self::$DataValidators = array_merge($old_validators, $validators);
		}
	}
	
	/**
	 * Gets the data fixers
	 * 
	 * @return mixed[]
	 */
	public static function GetDataFixers()
	{
		if (self::$DataFixers !== null)
			return self::$DataFixers;
		include(__DIR__."/validators.php");
		self::$DataValidators = $_Q_Validators;
		self::$DataEncoders = $_Q_Encoders;
		return (self::$DataFixers = $_Q_Fixers);
	}
	
	public static function GetDataEncoders()
	{
		if (self::$DataEncoders !== null)
			return self::$DataEncoders;
		include(__DIR__."/validators.php");
		self::$DataValidators = $_Q_Validators;
		self::$DataFixers = $_Q_Fixers;
		return (self::$DataEncoders = $_Q_Encoders);
	}
	
	/**
	 * Sets the new fixers
	 * 
	 * @param mixed[] $fixers
	 */
	public static function SetDataEncoders($encoders, $full_replace = false)
	{
		if ($full_replace)
			self::$DataEncoders = $encoders;
		else
		{
			$old_encoders = self::$DataEncoders ?: self::GetDataEncoders();
			self::$DataFixers = array_merge($old_encoders, $encoders);
		}
	}
	
	/**
	 * Sets the new fixers
	 * 
	 * @param mixed[] $fixers
	 */
	public static function SetDataFixers($fixers, $full_replace = false)
	{
		if ($full_replace)
			self::$DataFixers = $fixers;
		else
		{
			$old_fixers = self::$DataFixers ?: self::GetDataFixers();
			self::$DataFixers = array_merge($old_fixers, $fixers);
		}
	}
	
	/*
	public static function IsDevelopmentAuthenticated($authentications = null, $test_only = false)
	{
		if ($test_only)
			return false;
		else
		{
			header('HTTP/1.1 401 Unauthorized');
			header('WWW-Authenticate: Digest realm="'.$realm.
				   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

			die('401 Unauthorized to '.$realm);
		}
		if ($authentications === null)
			$authentications = self::$DevelopmentModeAuthentications;
		
		$realm = $_SERVER["HTTP_HOST"].":".$_SERVER["SERVER_PORT"].Q_APP_REL;

		if ($authentications === null)
		{
			if ($test_only)
				return false;
			else
			{
				header('HTTP/1.1 401 Unauthorized');
				header('WWW-Authenticate: Digest realm="'.$realm.
					   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

				die('401 Unauthorized to '.$realm);
			}
		}
			
		$http_digest_parse = function ($txt)
		{
			// protect against missing data
			$needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
			$data = array();
			$keys = implode('|', array_keys($needed_parts));

			$matches = null;
			preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

			foreach ($matches as $m) {
				$data[$m[1]] = $m[3] ? $m[3] : $m[4];
				unset($needed_parts[$m[1]]);
			}

			return $needed_parts ? false : $data;
		};
		
		if (empty($_SERVER['PHP_AUTH_DIGEST']))
		{
			if ($test_only)
				return false;
			else
			{
				header('HTTP/1.1 401 Unauthorized');
				header('WWW-Authenticate: Digest realm="'.$realm.
					   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');

				die('401 Unauthorized to '.$realm);
			}
		}
		else if ((!($data = $http_digest_parse($_SERVER['PHP_AUTH_DIGEST']))) || (!isset($authentications[$data['username']])))
		{
			if ($test_only)
			{
				return false;
			}
			else
			{
				header('HTTP/1.1 401 Unauthorized');
				header('WWW-Authenticate: Digest realm="'.$realm.
					   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
				die('Wrong Credentials!');
			}
		}
		else
		{
			// generate the valid response
			$A1 = md5($data['username'] . ':' . $realm . ':' . $authentications[$data['username']]);
			$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
			$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

			if ($data['response'] != $valid_response)
			{
				if ($test_only)
				{
					return false;
				}
				else
				{
					header('HTTP/1.1 401 Unauthorized');
					header('WWW-Authenticate: Digest realm="'.$realm.
						   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
					die('Wrong Credentials!');
				}
			}
			else if ($test_only)
				return true;
		}
	}
	*/
	
	/**
	 * Gets the web path to the main folder
	 * 
	 * @return string
	 */
	public static function GetMainFolderWebPath()
	{
		return self::$MainFolderWebPath;
	}
	
	public static function SetTagForWatchFolder($folder, $tag)
	{
		self::$WatchFoldersByTag[$tag] = $folder;
	}
	
	public static function GetPathForTag($tag = null, $path = null)
	{
		return (($tag === null) ? self::$RuntimeFolder : self::$WatchFoldersByTag[$tag]).($path ?: "");
	}
	
	public static function GetWebPathForTag($tag = null, $path = null)
	{
		$path = (($tag === null) ? self::$RuntimeFolder : self::$WatchFoldersByTag[$tag]).($path ?: "");
		$dr = $_SERVER["DOCUMENT_ROOT"];
		$dr_len = strlen($dr);
		if (substr($dr, -1, 1) === "/")
			$dr_len--;
		return substr($path, $dr_len);
	}
	
	public static function DeployApp($update_files_time_inf = true, $temp_path = null)
	{
		if ($temp_path === null)
			$temp_path = static::GetRuntimeFolder()."temp/";
		
		$_Q_FRAME_DEPLOY_PATHS = null;
		include($temp_path."deploy_paths.php");
		$old_deploy_inf = $_Q_FRAME_DEPLOY_PATHS;
		
		$new_deploy_inf = ["mods" => QAutoload::GetWatchFoldersByTags(), "web" => $_SERVER["CONTEXT_DOCUMENT_ROOT"] ?: $_SERVER["DOCUMENT_ROOT"]];
		
		$transforms = [];
		// we need to put them in an order first
		foreach ($old_deploy_inf["mods"] as $old_mod_path)
			$transforms[$old_mod_path] = strlen($old_mod_path);
		// sort them, longest first
		arsort($transforms);
		
		foreach ($old_deploy_inf["mods"] as $mod => $old_mod_path)
			$transforms[$old_mod_path] = [strlen($old_mod_path), $new_deploy_inf["mods"][$mod]];
		
		$items = [
			"autoload.php",
			"autoload_css.php",
			"autoload_js.php",
			"classes_parents.php",
			"namespaces.php",
			"path_extends.php",
			"path_implements.php",
			"path_traits.php",
			"traits_included.php",
			"files_state.php"
		];
		
		$items_dirs = ["types/"];
		
		foreach ($items_dirs as $item)
		{
			$full_path = $temp_path.$item;
			if (is_dir($full_path))
			{
				$subitems = scandir($full_path);
				foreach ($subitems as $sub_item)
				{
					if (($sub_item === ".") || ($sub_item === ".."))
						continue;
					$items[] = $item.$sub_item;
				}
			}
		}
		
		foreach ($items as $item)
		{
			static::DeployAppChangePaths($temp_path.$item, $transforms);
		}
		
		// special: deploy.php, deploy_paths.php
		$deploy_data = QAutoload::GetDeployString();
		file_put_contents($temp_path."deploy.php", $deploy_data);
		
		$deploy_paths = ["mods" => QAutoload::GetWatchFoldersByTags(), "web" => $_SERVER["CONTEXT_DOCUMENT_ROOT"] ?: $_SERVER["DOCUMENT_ROOT"]];
		qArrayToCodeFile($deploy_paths, "_Q_FRAME_DEPLOY_PATHS", $temp_path."deploy_paths.php");
		
		if ($update_files_time_inf)
		{
			// files_state.php => we need to update times
			$Q_FILES_STATE_SAVE = null;
			include($temp_path."files_state.php");
			$files_state = $Q_FILES_STATE_SAVE;

			foreach ($files_state as $mod_path => $fs_data)
			{
				foreach ($fs_data as $rel_path => $mtime)
					$files_state[$mod_path][$rel_path] = filemtime($mod_path.$rel_path);
			}
			file_put_contents($temp_path."files_state.php", qArrayToCode($files_state, "Q_FILES_STATE_SAVE"));
		}
		
		$web_transforms = [];
		$old_web_path = $old_deploy_inf["web"];
		$new_web_path = $deploy_paths["web"];
		foreach ($transforms as $old_path => $transf_inf)
		{
			$old_web_p = "/".substr($old_path, strlen($old_web_path));
			$web_transforms[$old_web_p] = [strlen($old_web_p), "/".substr($transf_inf[1], strlen($new_web_path))];
		}
		
		// "js_paths.js" : this is a special case
		$js_paths_path = $temp_path."js_paths.js";
		$js_paths_str = file_get_contents($js_paths_path);
		$new_js_paths_str = preg_replace_callback("/\"[^\"]*\"/us", 
								function ($matches) use ($web_transforms)
								{
									$str_path = substr($matches[0], 1, -1);
									$replaced_path = static::DeployAppReplacePath($str_path, $web_transforms);
									if ($replaced_path !== null)
									{
										return $matches[0]{0}.$replaced_path.$matches[0]{0};
									}
									else
										return $matches[0];
								}, $js_paths_str);
		file_put_contents($js_paths_path, $new_js_paths_str);
	}
	
	protected static function DeployAppChangePaths($full_path, $transforms)
	{
		$tokens = token_get_all(file_get_contents($full_path));
		$has_changes = false;
		
		$new = "";
		foreach ($tokens as $tok)
		{
			if (is_array($tok))
			{
				$ty = $tok[0];
				$str = $tok[1];
			}
			else
			{
				$ty = null;
				$str = $tok;
			}
			if (($ty === T_CONSTANT_ENCAPSED_STRING) && ($str{1} === "/"))
			{
				$str_path = stripslashes(substr($str, 1, -1));
				$replaced_path = static::DeployAppReplacePath($str_path, $transforms);
				if ($replaced_path !== null)
				{
					$new_str = $str{0}.$replaced_path.$str{0};
					if ($new_str !== $str)
					{
						$has_changes = true;
						$str = $new_str;
					}
				}
			}
			$new .= $str;
		}
		
		if ($has_changes)
			file_put_contents($full_path, $str);
	}
	
	protected static function DeployAppReplacePath($str_path, $transforms)
	{
		foreach ($transforms as $old_path => $v)
		{
			list($old_path_len, $new_path) = $v;
			if (substr($str_path, 0, $old_path_len) === $old_path)
				return addslashes($new_path).substr($str_path, $old_path_len);
		}
		return null;
	}
}
