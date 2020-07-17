<?php

/**
 * Generates and patches platform standards
 */
class QCodeSync 
{
	const JS_Patch_Append_Key = "mp93xcgyn2mfyzgnbi428o7gfnzchm2sxdbg74xz2cgh";
	
	/**
	 * If in debug mode we produce output
	 *
	 * @var boolean
	 */
	public $debugMode = false;
	/**
	 * If in fullSync mode we resync all files
	 *
	 * @var boolean
	 */
	public $fullSync = false;
	/**
	 * Here we hold the data foreach class indexed by [$WatchFolder][$Class]
	 * 
	 * @var QCodeSyncNode[][]
	 */
	public $data = array();
	/**
	 * Here we hold the state of the info cross watch folder indexed by : [$Class][$Mode]
	 *
	 * @var QCodeSyncItem[][]
	 */
	public $classesMap = array();
	/**
	 * Keep a list of written files organized per watch folder so that we can update 
	 * the QCodeMonitor files
	 *
	 * @var QCodeSyncNode[][]
	 */
	public $writtenFiles = array();
	/**
	 * The list of outside patched that are needed
	 * Grouped by watch folder and className
	 *
	 * @var QCodeSyncNode[][]
	 */
	public $outside_patches = array();
	/**
	 * Similar to writtenFiles, but only keeps new files
	 * 
	 * @var QCodeSyncNode[][]
	 */
	public $newFiles = array();
	/**
	 *
	 * @var string[]
	 */
	public $classPaths = array();
	/**
	 *
	 * @var string[]
	 */
	public $jsClassPaths = array();
	/**
	 *
	 * @var string[]
	 */
	public $cssClassPaths = array();
	/**
	 *
	 * @var QCodeSync
	 */
	public static $Instance = null;
	
	public $debugLog = null;
	
	public static $SyncDebugLog = null;
	
	public $headInfo = array();
	
	protected static $TraitIncludeInfo = null;
	
	protected static $PreparedFixers = null;
	protected static $PreparedValidators = null;
	protected static $PreparedEncoders = null;
	
	public static $_benchmark = [];
	
	/**
	 *
	 * @var string[]
	 */
	public $watchFolders;
	/**
	 *
	 * @var string[]
	 */
	public $processedWatchFolders = array();
	/**
	 *
	 * @var string[][]
	 */
	public $files;

	// removed atm
	// public $modelHasChanged = true;
	
	/**
	 * Resyncs the code
	 * 
	 * @param array $files List with all the files
	 * @param array $changed_or_added List with the changed or added files
	 * @param array $removed_files List with the removed files
	 */
	public function resync(&$files, $changed_or_added, $removed_files, $new_files)
	{
		try
		{
			static::$_benchmark = [];
			
			$__t0 = microtime(true);
			
			ini_set('memory_limit', '4096M');
			
			// just to be sure
			gc_enable();

			$this->files = &$files;

			self::$Instance = $this;

			if (!empty($removed_files))
			{
				// we have to many scenarios to consider when we have removed files
				// TO DO in the future : do not switch to fullSync, improve how we do it
				$this->fullSync = true;
			}

			$has_model_changes = false;
			$has_sql_changes = false;

			$this->setChanges($changed_or_added, $removed_files, $new_files, $this->fullSync);
			// setup log
			$this->debugLog = $this->fullSync ? array() : $this->loadDebugLog();
			if ($this->debugLog === null)
				$this->debugLog = array();

			// if in fullSync we mark all as changed (bad idea !)
			$temp_folder = QAutoload::GetRuntimeFolder()."temp/";
			if ($this->fullSync)
			{
				$changed_or_added = $files;
				// $this->modelHasChanged = true;
				if (file_exists($temp_folder."namespaces.php"))
					unlink($temp_folder."namespaces.php");
				if (file_exists($temp_folder."path_extends.php"))
					unlink($temp_folder."path_extends.php");
				if (file_exists($temp_folder."path_implements.php"))
					unlink($temp_folder."path_implements.php");
				$_Q_FRAME_NAMESPACES_ARRAY = array();
				$_Q_FRAME_PATHS_EXTENDS_ARRAY = array();
				$_Q_FRAME_PATHS_IMPLEMENTS_ARRAY = array();
				$_Q_FRAME_PATHS_TRAITS_ARRAY = array();
				// TO DO: we need to remove all .gen. files on full sync !!!

				$has_model_changes = true;
				$has_sql_changes = true;
			}
			else
			{
				self::LoadTraitWasIncluded();

				if (file_exists($temp_folder . "namespaces.php"))
					include($temp_folder . "namespaces.php");
				if (file_exists($temp_folder . "path_extends.php"))
					include($temp_folder . "path_extends.php");
				if (file_exists($temp_folder . "path_implements.php"))
					include($temp_folder . "path_implements.php");
				if (file_exists($temp_folder . "path_traits.php"))
					include($temp_folder . "path_traits.php");

				// namespaces.php
				$_Q_FRAME_NAMESPACES_ARRAY = $_Q_FRAME_NAMESPACES_ARRAY ?: array();
				$_Q_FRAME_PATHS_EXTENDS_ARRAY = $_Q_FRAME_PATHS_EXTENDS_ARRAY ?: array();
				$_Q_FRAME_PATHS_IMPLEMENTS_ARRAY = $_Q_FRAME_PATHS_IMPLEMENTS_ARRAY ?: array();
				$_Q_FRAME_PATHS_TRAITS_ARRAY = $_Q_FRAME_PATHS_TRAITS_ARRAY ?: array();

				if ($changed_or_added)
				{
					foreach ($changed_or_added as $chad_wf => $chad_list)
					{
						foreach ($chad_list as $chad_file => $ts)
						{
							unset($_Q_FRAME_NAMESPACES_ARRAY[$chad_wf.$chad_file]);
							unset($_Q_FRAME_PATHS_EXTENDS_ARRAY[$chad_wf.$chad_file]);
							unset($_Q_FRAME_PATHS_IMPLEMENTS_ARRAY[$chad_wf.$chad_file]);
							unset($_Q_FRAME_PATHS_TRAITS_ARRAY[$chad_wf.$chad_file]);
							unset(self::$TraitIncludeInfo[$chad_wf.$chad_file]);
						}
					}
				}
				QCodeSyncItem::$PathToNamespace = $_Q_FRAME_NAMESPACES_ARRAY;
				QCodeSyncItem::$PathToExtends = $_Q_FRAME_PATHS_EXTENDS_ARRAY;
				QCodeSyncItem::$PathToImplements = $_Q_FRAME_PATHS_IMPLEMENTS_ARRAY;
				QCodeSyncItem::$PathToTraits = $_Q_FRAME_PATHS_TRAITS_ARRAY;
			}

			$this->watchFolders = array_keys($files);

			// load the parents from the previous state
			$classParents = array();
			$save_parents_path = QAutoload::GetRuntimeFolder()."temp/classes_parents.php";
			if ((!$this->fullSync) && file_exists($save_parents_path))
			{
				require($save_parents_path);
				$classParents = $Q_CLASS_PARENTS_SAVE;
			}

			// save the current state as we need to compare it later
			$this->prevClassParents = $classParents;

			$possible_classes = array();

			// foreach file in there setup the data chain
			foreach ($files as $watchFolder => $wf_data)
			{
				try
				{
					$__t0_start = microtime(true);
					// we are now per watch folder
					// Load all the files && changes

					if ($this->fullSync)
					{
						/*if (is_dir($watchFolder."_compiled/"))
							// empty compiled folder
							qEmptyDir($watchFolder."_compiled/", false);
						if (is_dir($watchFolder."_dynamic/"))
							// empty _dynamic folder
							qEmptyDir($watchFolder."_dynamic/", false);*/
					}

					foreach ($wf_data as $path => $modif_date)
					{
						// ignore gen files
						if ((substr($path, -8, 8) === ".gen.php") || (substr($path, -3, 3) === ".js") || (substr($path, -4, 4) === ".css"))
							continue;

						$sync_item = $this->setCodeSyncItem("_", $watchFolder, $path, $modif_date, null, null, $changed_or_added[$watchFolder][$path] ? true : false);
						if ($sync_item->class && (!$sync_item->relatedNode))
							$sync_item->node->relatedNode = ($rnode = $this->classesMap[$sync_item->class]) ? reset(reset($this->classesMap[$sync_item->class]))->node : null;

						$possible_classes[$sync_item->class] = $sync_item->node->classPath;

						// some initial changed checks
						if (($sync_item->mode === "patch") && 
									(($tmp_sync_item_x = $this->classesMap[$sync_item->class]["patch"][""]) || ($tmp_sync_item_x = $this->classesMap[$sync_item->class]["php"][""]))
									&& $tmp_sync_item_x->changed)
						{
							$sync_item->changed = true;
						}
					}

					$possible_roots = array();
					$grouped = array();
					// group & determine parents
					$data_keys = $this->data["_"] ? array_keys($this->data["_"]) : array();
					foreach ($data_keys as $class)
					{
						$m_node = $this->data["_"][$class];
						$prev_class = $class;

						// loop to determine parents
						while ($m_node)
						{
							$p_class = $m_node->readParent($classParents);

							$classParents[$watchFolder][$prev_class] = $p_class;

							if ($p_class && $possible_classes[$p_class])
							{
								$grouped[$p_class][$m_node->className] = $m_node;
								$p_node = $this->data["_"][$p_class];
								if ((!$p_node) && $p_class)
								{
									$p_node = $this->setCodeSyncItem("_", $watchFolder, null, null, $p_class, null, false);
									$p_node->relatedNode = ($rnode = $this->classesMap[$p_class]) ? reset(reset($this->classesMap[$p_class]))->node : null;
									$p_node->readParent($classParents);
								}

								$m_node = $p_node;
								$prev_class = $p_class;
							}
							else
							{
								$possible_roots[$prev_class] = $m_node;

								// will not be included in grouped, do it now, it's a root element
								$m_node->establishWhatWePatchAndClassMergeWith($this->classesMap, $watchFolder);

								break;
							}
						}
					}

					// now we need to group them by parent
					foreach ($grouped as $p_class => $nodes)
					{
						$p_node = $this->data["_"][$p_class];
						// if (!$p_node)
							// continue;

						if ($p_node)
							$possible_roots[$p_class] = $p_node;

						foreach ($nodes as $node)
						{
							if ($p_node)
							{
								$p_node->children[$node->className] = $node;
								$node->parent = $p_node;
							}

							// who do we patch && where do we read Class code from
							$node->establishWhatWePatchAndClassMergeWith($this->classesMap, $watchFolder);
						}
					}

					$this->all_current_classes = $possible_roots;

					/*
					// print data 
					echo "<pre>WF: <b>{$watchFolder}</b>\n\n";
					foreach ($possible_roots as $p_class => $p_node)
					{
						if ($p_node->parent)
							continue;

						$p_node->debugPrint();
					}
					echo "</pre>";
					 */

					// echo "<h4>{$watchFolder}</h4>\n";
					// echo "<hr/>\n";
					
				}
				finally
				{
					$this->benchmark("resync@start", $__t0_start, func_get_args());
				}

				// NOW run for real
				foreach ($possible_roots as $p_class => $p_node)
				{
					if ($p_node->parent)
						continue;

					$p_node->processNode(null, $classParents, $this->fullSync);
				}

				// echo "{$watchFolder} ====================================================<br/>\n\n";

				// cleanup outside patches if needed 
				/*
				if (!empty($this->outside_patches[$watchFolder]))
				{
					$this->cleanupOutsidePatches($watchFolder, $this->outside_patches[$watchFolder]);
				}
				*/

				// normalize data, we move data from ["_"] to [$watchFolder]
				$wfd = $this->data[$watchFolder] = ($this->data["_"] !== null) ? $this->data["_"] : array();

				unset($this->data["_"]);
				// we setup the $this->classesMap for the next WatchFolder
				foreach ($wfd as $className => $node)
				{
					foreach ($node->data as $mode => $tmp_sync_item)
					{
						foreach ($tmp_sync_item as $_tsi => $tmp_sync_item__)
						// $classesMap[$this->className][$mode][$tag]
							$this->classesMap[$className][$mode][$_tsi] = $tmp_sync_item__;
					}
				}

				$this->processedWatchFolders[] = $watchFolder;
			}
			
			$mandatory_cache_refresh = false;
			// this is no longer required !!!
			/*
			foreach ($this->writtenFiles as $wf => $nodes)
			{
				$wf_len = strlen($wf);
				foreach ($nodes as $node)
				{
					//$compiled_path = QAutoload::GetCompiledPath($node->classPath);
					//$fp = file_exists($compiled_path) ? $compiled_path : (file_exists($node->classPath) ? $node->classPath : null);
					//if (!$fp)
					//	continue;
					// this is no longer required !!!
					// $rel_path = substr($node->classPath, $wf_len);
					// $f_time = filemtime($node->classPath);
					// if (!$files[$wf][$rel_path])
					//	$mandatory_cache_refresh = true;
					// $files[$wf][$rel_path] = $f_time;
				}
			}
			*/
			if (!$mandatory_cache_refresh)
			{
				foreach ($this->newFiles as $nodes)
				{
					if (!empty($nodes))
						$mandatory_cache_refresh = true;
				}
			}

			try
			{
				$__t0_mw = microtime(true);
				// handle method wrapping requirements for dynamic callbacks
				if ($this->requires_method_wrapping)
				{
					foreach ($this->requires_method_wrapping as $wrap_class => $wrap_meths)
						$this->wrapMethods($wrap_class, $wrap_meths, $classParents);
					$mandatory_cache_refresh = true;
				}
			}
			finally
			{
				$this->benchmark("resync@method_wrapping", $__t0_mw, func_get_args());
			}

			// ask for a types cache rebuild
			$cache_folder = QAutoload::GetRuntimeFolder()."temp/types/";
			$cf_processed = array();

			// TO DO: handle $removed_files : $removed_files[$elem->watchFolder]
			// if it's a tpl, it's children are to be affected by this change as they will need to sync the template 
			// from another source

			if (!is_dir($temp_folder))
				mkdir($temp_folder, (0777 & ~umask()), true);
			if (!is_dir($cache_folder))
				mkdir($cache_folder, (0777 & ~umask()), true);
			// for now in case of a full sync we cleanup everything
			if ($this->fullSync)
			{
				// cleanup the temp folder
				if (file_exists($temp_folder."autoload.php"))
					unlink($temp_folder."autoload.php");

				if (file_exists($temp_folder."extended_by.php"))
					unlink($temp_folder."extended_by.php");
				if (file_exists($temp_folder."implements.php"))
					unlink($temp_folder."implements.php");
				if (file_exists($temp_folder."autoload_js.php"))
					unlink($temp_folder."autoload_js.php");
				if (file_exists($temp_folder."autoload_css.php"))
					unlink($temp_folder."autoload_css.php");
				if (file_exists($temp_folder."classes_parents.php"))
					unlink($temp_folder."classes_parents.php");
				// unlink($temp_folder."files_state.php");
				if (file_exists($temp_folder."js_paths.js"))
					unlink($temp_folder."js_paths.js");
				if (file_exists($temp_folder."model_type.js"))
					unlink($temp_folder."model_type.js");
				if (file_exists($temp_folder."php_js.php"))
					unlink($temp_folder."php_js.php");

				self::EmptyDir($temp_folder."types/");
				self::EmptyDir($temp_folder."docs/");

			}
			else
			{
				if (file_exists($temp_folder."autoload.php"))
					include($temp_folder."autoload.php");
				// if ($this->modelHasChanged)
				// {
				if (file_exists($temp_folder."extended_by.php"))
					include($temp_folder."extended_by.php");
				if (file_exists($temp_folder."implements.php"))
					include($temp_folder."implements.php");
				if (file_exists($temp_folder."autoload_css.php"))
					include($temp_folder."autoload_css.php");
				if (file_exists($temp_folder."classes_parents.php"))
					include($temp_folder."classes_parents.php");

				// js_paths.js
				$_Q_FRAME_JS_PATHS = self::ExtractJson($temp_folder."js_paths.js");
				// model_type.js : NO LONGER USED
				$_Q_FRAME_JS_QModel_Types = self::ExtractJson($temp_folder."model_type.js");
				if (file_exists($temp_folder."php_js.php"))
					include($temp_folder."php_js.php");
				// }
			}

			// autoload.php
			$_Q_FRAME_LOAD_ARRAY = $_Q_FRAME_LOAD_ARRAY ?: array();
			// extended_by.php
			$_Q_FRAME_EXTENDED_BY = array();

			// implements.php
			$_Q_FRAME_IMPLEMENTS_ARRAY = $_Q_FRAME_IMPLEMENTS_ARRAY ?: array();

			// autoload_js.php
			$_Q_FRAME_JS_LOAD_ARRAY = $_Q_FRAME_JS_LOAD_ARRAY ?: array();
			// autoload_css.php
			$_Q_FRAME_CSS_LOAD_ARRAY = $_Q_FRAME_CSS_LOAD_ARRAY ?: array();
			// classes_parents.php
			// $Q_CLASS_PARENTS_SAVE = $Q_CLASS_PARENTS_SAVE ?: array();
			// files_state.php
			// no need, the sync will handle this
			// js_paths.js
			$_Q_FRAME_JS_PATHS = $_Q_FRAME_JS_PATHS ?: array();

			// model_type.js : QModel.Types
			$_Q_FRAME_JS_QModel_Types = $_Q_FRAME_JS_QModel_Types ?: array();
			// php_js.php
			$_Q_FRAME_PHP_JS_ARRAY = $_Q_FRAME_PHP_JS_ARRAY ?: array();

			$doc_root_len = strlen($_SERVER["DOCUMENT_ROOT"]);

			$_Q_FRAME_LOAD_ARRAY = array();

			// first update autoload so things will work
			foreach ($this->classPaths as $cp_class => $cp_path)
			{
				// $elem = ($e = reset($cm_data)) ? (reset($e)->node) : null;

				// this is not the best way to do it, we no longer may have a PHP there !!!
				// $elem->classPath ... may be in an upper watch folder !!!

				// if ($elem instanceof QCodeSyncNode)
				// {
					// $e_has_changed = $elem->hasChanged();

				// $class_path = file_exists($cp_path) ? $cp_path : QAutoload::GetCompiledPath($cp_path);
				$file_exists_cp_path = file_exists($cp_path);
				$has_php_class_ = false;

				// var_dump($cp_class, $cp_path);


				if ($file_exists_cp_path)
				{
					$_Q_FRAME_LOAD_ARRAY[$cp_class] = $cp_path;
					$has_php_class_ = true;
				}
				else if (($fe_class_patch = file_exists(substr($cp_path, 0, -3)."patch.php")))
				{
					$_Q_FRAME_LOAD_ARRAY[$cp_class] = substr($cp_path, 0, -3)."patch.php";
					$has_php_class_ = true;
				}

				$c_compiled_path = QAutoload::GetCompiledPath($cp_path);

				if (file_exists($c_compiled_path))
				{
					if ($has_php_class_)
						$_Q_FRAME_LOAD_ARRAY[$cp_class."_GenTrait"] = $c_compiled_path;
					else
						$_Q_FRAME_LOAD_ARRAY[$cp_class] = $c_compiled_path;
				}
			}

			QAutoload::SetAutoloadArray($_Q_FRAME_LOAD_ARRAY);
			QAutoload::UnlockAutoload();

			// if ($this->modelHasChanged)
			// {

			$_Q_FRAME_JS_PATHS = array();
			$_Q_FRAME_CSS_PATHS = array();
			$_Q_FRAME_JS_LOAD_ARRAY = array();
			$_Q_FRAME_CSS_LOAD_ARRAY = array();

			
			try
			{
				$__t0_js = microtime(true);
				if ($this->jsClassPaths)
				{
					foreach ($this->jsClassPaths as $js_class => $js_paths)
					{
						foreach ($js_paths ?: [] as $js_path)
						{
							$frame_js_path = "/".ltrim(substr($js_path, $doc_root_len), "/");

							// autoload_js.php
							if (file_exists($js_path))
							{
								// js_paths.js
								if (!$_Q_FRAME_JS_PATHS[$js_class])
									$_Q_FRAME_JS_PATHS[$js_class] = [];
								$_Q_FRAME_JS_PATHS[$js_class][$frame_js_path] = $frame_js_path;

								if (!$_Q_FRAME_JS_LOAD_ARRAY[$js_class])
									$_Q_FRAME_JS_LOAD_ARRAY[$js_class] = [];
								$_Q_FRAME_JS_LOAD_ARRAY[$js_class][$js_path] = $js_path;
							}
							else
							{
								if (isset($_Q_FRAME_JS_LOAD_ARRAY[$js_class]))
									unset($_Q_FRAME_JS_LOAD_ARRAY[$js_class][$js_path]);

								if (isset($_Q_FRAME_JS_PATHS[$js_class]))
									unset($_Q_FRAME_JS_PATHS[$js_class][$frame_js_path]);
							}
						}
					}
				}
			}
			finally
			{
				$this->benchmark("resync@js", $__t0_js, func_get_args());
			}

			// autoload_css.php
			if ($this->cssClassPaths)
			{
				foreach ($this->cssClassPaths as $css_class => $css_paths)
				{
					foreach ($css_paths ?: [] as $css_path)
					{
						// autoload_css.php
						$frame_css_path = "/".ltrim(substr($css_path, $doc_root_len), "/");
						if (file_exists($css_path))
						{
							if (!$_Q_FRAME_CSS_LOAD_ARRAY[$css_class])
								$_Q_FRAME_CSS_LOAD_ARRAY[$css_class] = [];
							$_Q_FRAME_CSS_LOAD_ARRAY[$css_class][$css_path] = $css_path;

							if (!$_Q_FRAME_CSS_PATHS[$css_class])
								$_Q_FRAME_CSS_PATHS[$css_class] = [];
							$_Q_FRAME_CSS_PATHS[$css_class][$frame_css_path] = $frame_css_path;
						}
						else
						{
							if (isset($_Q_FRAME_CSS_LOAD_ARRAY[$css_class]))
								unset($_Q_FRAME_CSS_LOAD_ARRAY[$css_class][$css_path]);

							if (isset($_Q_FRAME_CSS_PATHS[$css_class]))
								unset($_Q_FRAME_CSS_PATHS[$css_class][$frame_css_path]);
						}
					}
				}
			}

			// classes_parents.php
			// $Q_CLASS_PARENTS_SAVE = $classParents;

			// TO DO: tick creation of security filters

			foreach ($this->classesMap as $class => $cm_data)
			{
				$elem = ($e = reset($cm_data)) ? (reset($e)->node) : null;

				if ($elem instanceof QCodeSyncNode)
				{
					$e_has_changed = $elem->hasChanged();

					// php_js.php
					if ($elem->parentClass)
						$_Q_FRAME_PHP_JS_ARRAY[$elem->className] = $elem->parentClass;
					else
						unset($_Q_FRAME_PHP_JS_ARRAY[$elem->className]);

					// update: $_Q_FRAME_IMPLEMENTS_ARRAY
					if ($e_has_changed || ($_Q_FRAME_IMPLEMENTS_ARRAY[$elem->className] === null))
					{
						// $rc1 = new ReflectionClass($elem->className);
						// $ifaces = $rc1->getInterfaceNames();

						$ifaces = $this->getClassImplements($elem, $watchFolder);

						if ($ifaces)
						{
							$_Q_FRAME_IMPLEMENTS_ARRAY[$elem->className] = array();
							foreach ($ifaces as $iface)
								$_Q_FRAME_IMPLEMENTS_ARRAY[$elem->className][$iface] = 1;
						}
						else
							$_Q_FRAME_IMPLEMENTS_ARRAY[$elem->className] = false;
					}
				}
			}

			//$this->php_js_array = $_Q_FRAME_PHP_JS_ARRAY;
			// unlink($temp_folder."autoload.php");
			// setup js mapping
			// var_dump($classParents[$watchFolder], $_Q_FRAME_IMPLEMENTS_ARRAY);

			$_Q_FRAME_JS_QModel_Types = array();
			foreach ($classParents as $cp_data)
			{
				foreach ($cp_data as $php_class => $php_c_parent)
					$_Q_FRAME_JS_QModel_Types[$php_class] = $php_c_parent;
			}

			/*
			foreach ($_Q_FRAME_IMPLEMENTS_ARRAY as $php_class => $php_impl_infaces)
			{
				if (is_array($php_impl_infaces))
				{
					foreach ($php_impl_infaces as $php_c_parent => $v)
					{
						$this->propagateExtendedBy($php_class, $php_c_parent, $_Q_FRAME_EXTENDED_BY, $classParents[$watchFolder], $_Q_FRAME_IMPLEMENTS_ARRAY);
					}
				}
			}
			*/

			// var_dump($_Q_FRAME_JS_QModel_Types, $_Q_FRAME_EXTENDED_BY);

			// how do we handle for interfaces, for interfaces we need the implements, whomever implements an interface extends that interface
			// $_Q_FRAME_JS_QModel_Types = $CLASS => $PARENT_CLASS
			foreach ($_Q_FRAME_JS_QModel_Types as $php_class => $php_c_parent)
			{
				if ($php_c_parent || $_Q_FRAME_IMPLEMENTS_ARRAY[$php_class])
				{
					$this->propagateExtendedBy($php_class, $php_c_parent, $_Q_FRAME_EXTENDED_BY, $_Q_FRAME_JS_QModel_Types, $_Q_FRAME_IMPLEMENTS_ARRAY);
					/*
					$_Q_FRAME_EXTENDED_BY[$php_c_parent][$php_class] = $php_class;
					foreach ($_Q_FRAME_EXTENDED_BY as $k => $v)
					{
						if ($v[$php_c_parent])
							$_Q_FRAME_EXTENDED_BY[$k][$php_class] = $php_class;
					}
					*/
				}
			}

			QAutoload::SetExtendedBy($_Q_FRAME_EXTENDED_BY);

			// we need to do this in a different way, start from QIModel and go recursive
			$QIModel_classes = $_Q_FRAME_EXTENDED_BY["QIModel"];
			//var_dump($QIModel_classes);
			if (!$QIModel_classes["QApi"])
				$QIModel_classes["QApi"] = "QApi";
			
			try
			{
				$__t0_cache_data = microtime(true);

				foreach ($QIModel_classes as $class)
				{
					$cm_data = $this->classesMap[$class];
					if (!$cm_data)
					{
						// var_dump($class);
						// throw new Exception("Issue 1 :: $class");
						continue;
					}
					$elem = ($e = $cm_data['patch'] ?: $cm_data['php'] ?: reset($cm_data)) ? (reset($e)->node) : null;
					if ($elem instanceof QCodeSyncNode)
					{
						$e_has_changed = ($this->fullSync || $elem->hasChanged());
						$cache_path = $cache_folder.qClassToPath($elem->className).".type.php";

						if ($e_has_changed || (!file_exists($cache_path)))
						{
							// TYPES folder
							// if (qIsA($elem->className, "QIModel"))
							if ($QIModel_classes[$elem->className])
							{
								// For instances of "QIModel" we must cache data

								if (!$cf_processed[$elem->className])
								{
									list($processed_ty, $ty_changes) = QCodeStorage::CacheData($elem->className, $cache_path);
									if ($ty_changes)
									{
										$has_model_changes = true;
										if ($processed_ty->storage)
											$has_sql_changes = true;
									}
									$cf_processed[$elem->className] = true;
								}

								// now dependencies
								$extended_by = QAutoload::GetClassExtendedBy($elem->className);
								if ($extended_by)
								{
									foreach ($extended_by as $ext_by)
									{
										if (!$cf_processed[$ext_by])
										{
											// var_dump($ext_by);
											list($processed_ty, $ty_changes) = QCodeStorage::CacheData($ext_by);
											if ($ty_changes)
											{
												$has_model_changes = true;
												if ($processed_ty->storage)
													$has_sql_changes = true;
											}
											$cf_processed[$ext_by] = true;
										}
									}
								}
							}
							else if (file_exists($cache_path))
								unlink($cache_path);
						}
					}
					else
					{
						var_dump(get_class($cm_data["php"][""]));
						throw new Exception("Issue 2 :: {$class}");
					}
				}
			
			}
			finally
			{
				$this->benchmark("resync@cache_data", $__t0_cache_data, func_get_args());
			}

			if ($has_sql_changes)
				QApp::SetHasStorageChanges(true);

			$_Q_FRAME_NAMESPACES_ARRAY = QCodeSyncItem::$PathToNamespace;
			$_Q_FRAME_PATHS_EXTENDS_ARRAY = QCodeSyncItem::$PathToExtends;
			$_Q_FRAME_PATHS_IMPLEMENTS_ARRAY = QCodeSyncItem::$PathToImplements;
			$_Q_FRAME_PATHS_TRAITS_ARRAY = QCodeSyncItem::$PathToTraits;

			qArrayToCodeFile($_Q_FRAME_LOAD_ARRAY, "_Q_FRAME_LOAD_ARRAY", $temp_folder."autoload.php");
			qArrayToCodeFile($_Q_FRAME_NAMESPACES_ARRAY, "_Q_FRAME_NAMESPACES_ARRAY", $temp_folder."namespaces.php");
			qArrayToCodeFile($_Q_FRAME_PATHS_EXTENDS_ARRAY, "_Q_FRAME_PATHS_EXTENDS_ARRAY", $temp_folder."path_extends.php");
			qArrayToCodeFile($_Q_FRAME_PATHS_IMPLEMENTS_ARRAY, "_Q_FRAME_PATHS_IMPLEMENTS_ARRAY", $temp_folder."path_implements.php");
			qArrayToCodeFile($_Q_FRAME_PATHS_TRAITS_ARRAY, "_Q_FRAME_PATHS_TRAITS_ARRAY", $temp_folder."path_traits.php");
			// if ($this->modelHasChanged)
			// {
			qArrayToCodeFile($_Q_FRAME_EXTENDED_BY, "_Q_FRAME_EXTENDED_BY", $temp_folder."extended_by.php");
			qArrayToCodeFile($_Q_FRAME_IMPLEMENTS_ARRAY, "_Q_FRAME_IMPLEMENTS_ARRAY", $temp_folder."implements.php");

			qArrayToCodeFile($_Q_FRAME_JS_LOAD_ARRAY, "_Q_FRAME_JS_LOAD_ARRAY", $temp_folder."autoload_js.php");
			qArrayToCodeFile($_Q_FRAME_CSS_LOAD_ARRAY, "_Q_FRAME_CSS_LOAD_ARRAY", $temp_folder."autoload_css.php");
			qArrayToCodeFile($classParents, "Q_CLASS_PARENTS_SAVE", $temp_folder."classes_parents.php");

			// js_paths.js
			file_put_contents($temp_folder."js_paths.js", 
					"window.\$_Q_FRAME_JS_PATHS = ".json_encode($_Q_FRAME_JS_PATHS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n".
					"window.\$_Q_FRAME_CSS_PATHS = ".json_encode($_Q_FRAME_CSS_PATHS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n");

			// do a bit of cleanup
			$new_Q_FRAME_JS_QModel_Types = [];
			foreach ($_Q_FRAME_JS_QModel_Types as $js_xc => $js_c)
			{
				if ($js_c)
					$new_Q_FRAME_JS_QModel_Types[$js_xc] = $js_c;
			}

			// model_type.js : rethink it
			file_put_contents($temp_folder."model_type.js", "window.\$_Q_FRAME_JS_CLASS_PARENTS = ".json_encode($new_Q_FRAME_JS_QModel_Types, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n");

			qArrayToCodeFile($_Q_FRAME_PHP_JS_ARRAY, "_Q_FRAME_PHP_JS_ARRAY", $temp_folder."php_js.php");

			// now handle deploy information
			$deploy_data = QAutoload::GetDeployString();
			file_put_contents($temp_folder."deploy.php", $deploy_data);

			// Q_RUNNING_PATH
			$deploy_paths = ["mods" => QAutoload::GetWatchFoldersByTags(), "web" => $_SERVER["CONTEXT_DOCUMENT_ROOT"] ?: $_SERVER["DOCUMENT_ROOT"]];
			qArrayToCodeFile($deploy_paths, "_Q_FRAME_DEPLOY_PATHS", $temp_folder."deploy_paths.php");
			// qArrayToCodeFile($deploy_data, "_Q_FRAME_DEPLOY_DATA", $temp_folder."deploy.php");

			self::SaveTraitWasIncluded();
			// }
			self::$Instance = null;
			
			
			if ($this->_resolve_after)
			{
				if (($ra_security = $this->_resolve_after['security']))
				{
					foreach ($ra_security as $full_class_name => $security_element)
					{
						$security_class_str = \QSecurityGenerator::GenerateForClass($full_class_name);
						if (!$security_class_str)
							continue;
						
						$final_tokens = $security_element->getFinalTokens();
						$final_class = $final_tokens->findPHPClass();

						$parsed_gen_meths = QPHPToken::ParsePHPString($security_class_str, false, false);	
						$final_class->mergeMethods($parsed_gen_meths->findPHPClass()->methods, true);
						// $element->requiresCompiled = true;
						self::filePutContentsIfChanged($security_element->compilePath, $final_tokens."");
					}
				}
			}

		// $this->saveDebugLog();
		
		// unlink($temp_folder."classes_parents.php");
		
		// unlink($temp_folder."js_paths.js");
		// unlink($temp_folder."model_type.js");
		
		// to handle SQL !!!
		// unlink($temp_folder."sql_model_info.php");
		// unlink($temp_folder."storage_sql_ids.php");
		}
		finally
		{
			$this->benchmark("resync", $__t0, func_get_args());
			// qvar_dump($this->_benchmark);
		}
	}
	
	public static function GetDebugLog()
	{
		if (self::$SyncDebugLog !== null)
			return self::$SyncDebugLog;
		
		$temp_folder = QAutoload::GetRuntimeFolder()."temp/";
		if (file_exists($temp_folder."debug_log.php"))
		{
			include($temp_folder."debug_log.php");
			return self::$SyncDebugLog = isset($_QSYNC_DEBUG_LOG) ? $_QSYNC_DEBUG_LOG : null;
		}
		else
			return null;
	}
	
	public function loadDebugLog()
	{
		return $this->debugLog = self::GetDebugLog();
	}
	
	public function saveDebugLog()
	{
		if ($this->debugLog === null)
			return false;
		
		$temp_folder = QAutoload::GetRuntimeFolder()."temp/";
		qArrayToCodeFile($this->debugLog, "_QSYNC_DEBUG_LOG", $temp_folder."debug_log.php");
		return true;
	}
	
	public static function ExtractJson($path)
	{
		if (!file_exists($path))
			return null;
		$cont = file_get_contents($path);
		if (!$cont)
			return null;
		// find the first {
		$p1 = strpos($cont, "{");
		$p2 = strrpos($cont, "}");
		
		if (! (($p1 !== false) && ($p2 !== false) && ($p2 > $p1)))
			return null;
		return json_decode(substr($cont, $p1, $p2 - $p1 + 1), true);
	}
	
	public function getClassCurrentPath($class)
	{
		$cm_data = $this->classesMap[$class];
		
		/* @var $elem QCodeSyncNode */
		$elem = ($e = $cm_data["php"]) ? ($e[""]->node) : null;
		$i_path = ($elem && $elem->classPath && file_exists($elem->classPath)) ? $elem->classPath : QAutoload::GetClassFileName($class);
		$c_path = QAutoload::GetCompiledPath($i_path);
		
		if (file_exists($c_path))
			return $c_path;
		else
			return $i_path;
	}
	
	public function getClassCurrentTokens($class)
	{
		$cm_data = $this->classesMap[$class];
		
		/* @var $elem QCodeSyncNode */
		$elem = ($e = $cm_data["php"]) ? ($e[""]->node) : null;
		if ($elem)
			return $elem->getFinalTokens();
		else
		{
			$i_path = QAutoload::GetClassFileName($class);
			$c_path = QAutoload::GetCompiledPath($i_path);
			if (file_exists($c_path))
				return QPHPToken::ParsePHPFile($c_path, false);
			else
				return QPHPToken::ParsePHPFile($i_path, false);
		}
	}
	
	/**
	 * Creates dynamic methods via the QModel::$_fn hidden property
	 * 
	 * @param string $_dyn_class
	 * @param string[] $wrap_meths
	 */
	public function wrapMethods($_dyn_class, $wrap_meths, &$classParents)
	{
		list ($wrap_class, $dyn_class, $namespace) = explode("#", $_dyn_class);
		
		// var_dump($wrap_class, $dyn_class, $namespace);
		
		if (!$wrap_meths)
			return;
		
		$code = $this->getClassCurrentTokens($wrap_class);
		if (!$code)
			throw new Exception("Unable to locate class: ".$wrap_class);
		
		$gen_path = null;
		list($default_method, /* $var_name */, /*$ctrl_class*/, $gen_from_class, $gen_path, $method_str, /* $arguments */) = reset($wrap_meths);
		$dyn_path = dirname($gen_path)."/".$dyn_class.".dyn.php";
		
		$watchFolder = QAutoload::GetModulePathForPath($dyn_path);
		
		// it's safe to use a read only object, we don't change anything
		// $code = QPHPTokenFile::ParsePHPFile($path, false);
		
		/*if (!$code)
			throw new Exception("Unable to parse PHP class: {$path}");*/
			
		/* @var $php_class QPHPTokenClass */
		$php_class = $code->findPHPClass();
		if (!$php_class)
		{
			echo "<textarea>{$code}</textarea>";
			throw new Exception("Unable to find PHP class {$wrap_class} in {$path}");
		}
			
		$str = "<?php\n".
				"/**\n".
				" * This file was generated and it will be overwritten when it's dependencies are changed or removed.\n".
				" */\n\n".
				($namespace ? "namespace {$namespace};\n\n" : "").
				"class {$dyn_class} extends \\{$wrap_class}\n{\n";
				
		$class_with_ns = QPHPToken::ApplyNamespaceToName($dyn_class, $namespace);
		$classParents[$watchFolder][$class_with_ns] = $wrap_class;
		
		// var_dump($class_with_ns, $wrap_class);
		
		// $new_class_name = $wrap_class."_".$gen_info["class_name"]."_"
		
		$gen_from_class = null;
		foreach ($wrap_meths as $meth_info)
		{
			list($default_method, /* $var_name */, /*$ctrl_class*/, $gen_from_class, $gen_path, $method_str, /* $arguments */) = $meth_info;
			
			$meth_obj = $php_class->findMethod($default_method);
			if ($meth_obj && $meth_obj->docComment)
				$str .= "\t".QPHPToken::toString($meth_obj->docComment)."\n";
			$str .= "\t".$method_str;
		}
		
		$str .= "\n}\n\n";
		
		/*if (!is_dir($dir = dirname($dyn_path)))
			mkdir($dir, (0777 & ~umask()), true);
		file_put_contents($dyn_path, $str);*/
		self::filePutContentsIfChanged($dyn_path, $str, true);
		
		$ret = $this->setCodeSyncItem("_", $watchFolder, substr($dyn_path, strlen($watchFolder)), filemtime($dyn_path), null, "php", true);
		$ret =  ($ret instanceof QCodeSyncItem) ? $ret : null;
		if (!$ret)
			throw new Exception("Unexpected");
		
		$this->classesMap[$class_with_ns]["php"][""] = $ret;
	}
	
	/**
	 * Sets an item in the chain
	 * 
	 * @param string $key
	 * @param string $watchFolder
	 * @param string $path
	 * @param integer $modif_date
	 * @param string $class
	 * @param string $mode
	 * @param boolean $changed
	 * 
	 * @return QCodeSyncItem
	 * 
	 * @throws Exception
	 */
	public function setCodeSyncItem($key, $watchFolder, $path, $modif_date, $class, $mode, $changed, $determined_namespace = null)
	{
		if ($path && ($path{0} === "/"))
		{
			var_dump("setCodeSyncItem: ".$watchFolder." :: ".$path." :: ".$class);
			throw new Exception("should not be");
		}
		
		if (!($path || $class))
			throw new Exception("You must specify either the `class and mode` or `path`");
		
		$namespace = false;
		$readonly_tokens = null;
		
		$bn = basename($path);
		$path_to_sync_node = dirname($watchFolder.$path)."/".substr($bn, 0, strpos($bn, "."));
		
		if ($path)
		{
			list ($_class, $_mode, $classPath, $tag, $namespace, $readonly_tokens, $readonly_tokens_path) = QCodeSyncItem::ExtractInfo($path, $watchFolder, $path_to_sync_node);
			
			if (!$class)
				$class = $_class;
			$mode = $mode ?: $_mode;
			if ($mode === "dyn")
				$mode = "php";

			if ($class)
			{
				// we need to ALWAYS setup this
				$this->classPaths[$class] = $watchFolder.$classPath;
				
				$js_path = $watchFolder.substr($classPath, 0, -3)."js";
				//echo "<div style='color: darkred;'>{$class} :: {$js_path}</div>";
				if (file_exists($js_path))
				{
					//echo "<div style='color: darkred;'>{$class} :: {$js_path}</div>";
					if (!$this->jsClassPaths[$class])
						$this->jsClassPaths[$class] = [];
					$this->jsClassPaths[$class][$js_path] = $js_path;
				}
				$css_path = $watchFolder.substr($classPath, 0, -3)."css";
				if (file_exists($css_path))
				{
					if (!$this->cssClassPaths[$class])
						$this->cssClassPaths[$class] = [];
					$this->cssClassPaths[$class][$css_path] = $css_path;
				}
			}
		}
		else
		{
			$classPath = null;
		}
		
		if ($mode === "dyn")
			$mode = "php";
		
		if ((!$namespace) && $determined_namespace)
			$namespace = $determined_namespace;
		
		$sync_node = $this->data[$key][$class] ?: ($this->data[$key][$class] = new QCodeSyncNode($classPath ? $watchFolder.$classPath : null, $class, $watchFolder, $this));
		if (!$sync_node->head_info)
			$sync_node->head_info = QCodeSyncItem::$ExtractCache[$path_to_sync_node];
		if ($readonly_tokens)
			$sync_node->__cached_tokens[$readonly_tokens_path] = $readonly_tokens;
		
		$this->path_to_sync_node[$path_to_sync_node] = $sync_node;
		
		if ($path)
			$sync_node->path = $path;

		$this->pathToElement[$watchFolder.$classPath] = $sync_node;

		if ($namespace)
			$sync_node->setNamespace($namespace);
		
		if ($mode)
		{
			if (($sync_item = $sync_node->getItem($mode, $tag)) !== null)
			{
				// update the info
				$sync_item->class = $class ?: $sync_item->class;
				$sync_item->path = $path ? $sync_node->getModePath($mode, $tag) : $sync_item->path;
				$sync_item->changed = $changed ?: $sync_item->changed;
				$sync_item->modif_date = $modif_date ?: $sync_item->modif_date;
				$sync_item->tag = $tag;
				$sync_node->modes[$mode] = $mode;
			}
			else
			{
				$sync_item = new QCodeSyncItem($sync_node, $mode, $class, $changed, $sync_node->getModePath($mode, $tag), $modif_date, $tag);
				$sync_node->setItem($sync_item, $mode, $tag);
				$sync_node->modes[$mode] = $mode;
				$sync_item->changed = $changed ?: $sync_item->changed;
				$sync_item->modif_date = $modif_date ?: $sync_item->modif_date;
			}
			
			if (($mode !== "php") && (!$sync_node->modes["php"]))
			{
				$this->setCodeSyncItem($key, $watchFolder, $classPath, $modif_date, $class, "php", false, $namespace);
			}
			
			return $sync_item;
		}
		else
			return $sync_node;
	}
	
	/**
	 * Tells if a file was changed (before the Sync process has started)
	 * 
	 * @param string $full_path
	 * @return boolean|integer
	 */
	public function hasChanged($full_path)
	{
		if ($this->fullSync)
			return true;
		return $this->changedFiles[$full_path];
	}
	
	/**
	 * Writes content to a file only if the new data is different from the old data
	 * 
	 * @param string $filename
	 * @param string $data
	 * @return mixed
	 */
	public static function filePutContentsIfChanged($filename, $data, $create_dir = false)
	{
		$data = is_string($data) ? $data : (string)$data;
		if (file_exists($filename) && (filesize($filename) === strlen($data)) && (file_get_contents($filename) === $data))
			// we say that there is no change
			return true;
		else
		{
			if ($create_dir && (!is_dir($dir = dirname($filename))))
				mkdir($dir, (0777 & ~umask()), true);

			return file_put_contents($filename, $data);
		}
	}
	
	/**
	 * 
	 * @param QCodeSyncNode $element
	 * @param string $tag
	 * 
	 */
	public function resyncGenConfig(QCodeSyncNode $element, $tag = "")
	{
		throw new Exception("no longer used");
		
		/*
		// 1. read the struct
		$struct_file = $element->getGenStructPath();
		require($struct_file);
		$struct = ${$element->className};
		
		if (!is_array($struct["query"]))
			$struct["query"] = array($struct["query"]);
		
		// 2. propose config based on 
		foreach ($struct["query"] as $query)
		{
			$sql_struct = QModelQuery::QueryToStruct($query);
			$gen_config = QWebControlFromModelGen::proposeConfig($sql_struct);
		}
		
		// 3. check upper config's existance ( TO DO ) and merge it with the proposed one
		
		// TO DO
		
		// 4. merge the stored config (if exists) over the proposed config
		
		// TO DO
		
		// 5. save the obtained config
		
		$element->gen_config = $gen_config;
		
		$conf_php = "<?php ob_start(); ?>\n\n".$gen_config->exportToJs(true)."\n\n<?php\n\n\t\${$element->className} = QModel::ExtractData(ob_get_clean());\n"."\n?>";
		self::filePutContentsIfChanged($element->getGenConfigPath(), $conf_php);
		
		// TO DO : format exportToJs to be human readable
		
		// 6. generate PHP (normal or as patch if exists), TPL, URL, & misc (TO DO)
		// then make sure the rest is done normal
		
		// 6.1 PHP
		
		// 2 properties foreach query , one the Q struct and one the Q result
	
		// 6.2 TPL
		
		// already started
		$tpl = "";
		self::generateWebControls($gen_config, $tpl, "", "\t");
		
		// $tpl = "<div qBindPath='this.queryResult'>\n{$tpl}</div>\n";
		
		echo "<textarea>{$tpl}</textarea>";
		
		self::filePutContentsIfChanged($element->getTemplatePath(), $tpl);
		
		// 6.3 URL
		
		// to do 
		 * 
		 */
	}
	
	function generateWebControls($conf, &$tpl = "", $path = "", $tabs = "")
	{
		// PopUPs !!!
		
		if ($conf instanceof QModelTypeConfig)
		{
			$is_array = ($conf->Parent instanceof QModelTypeConfig);
			if ($is_array)
				$tpl .= "{$tabs}<div qBindPath='.[]'>\n";
			
			if ($conf->Children)
			{
				foreach ($conf->Children as $child)
				{
					$c_path = ($child instanceof QModelTypeConfig) ? "[]" : "";
					self::generateWebControls($child, $tpl, $c_path, $is_array ? $tabs."\t" : $tabs);
				}
			}
			
			if ($is_array)
				$tpl .= "{$tabs}</div>\n";
		}
		else if ($conf instanceof QModelPropertyConfig)
		{
			$c_path = ($conf->Parent ? "." : "").$conf->PropertyName;
			$tpl .= "{$tabs}<div qBindPath='{$c_path}'>\n";
			
			if ($conf->Children)
			{
				foreach ($conf->Children as $child)
				{
					// $c_path = ($path ? $path."." : "").$child->PropertyName);
					self::generateWebControls($child, $tpl, $c_path, $tabs."\t");
				}
			}
			
			$tpl .= "{$tabs}</div>\n";
		}
	}
	
	/**
	 * Resyncs the URL Controller
	 * 
	 * @param QCodeSyncNode $element
	 */
	public function resyncUrlController(QCodeSyncNode $element, $tag = "")
	{
		try
		{
			$__t0 = microtime(true);

			$debug_log = "";

			$sync_item = $element->data["url"][$tag];
			$full_path = $element->getUrlPath();

			$obj_parsed = $element->getTokens("url", $tag); // QPHPToken::ParsePHPFile($full_path, true);
			if (!$obj_parsed)
				throw new Exception("Unable to parse `{$full_path}` in QCodeSync::resyncUrlController");

			if ($this->debugMode)
				echo "Processing URL: {$full_path}<br/>\n";

			$render_code = $this->managePatching($sync_item, $obj_parsed, null, false, $debug_log);
			if (!$render_code)
			{
				$render_code = $obj_parsed;
			}

			$gen_info = new QGeneratePatchInfo($full_path);

			if ($element->namespace)
				$gen_info->namespace = $element->namespace;

			// echo "<textarea>{$render_code}</textarea>";
			if (isset($sync_item->patch))
			{
				$gen_info->__tpl_parent = $sync_item->patch;
				$gen_info->__tpl_mode = $sync_item->mode;
				$gen_info->__tpl_tag = $sync_item->tag;
			}

			$this->DebugGenerate($gen_info, $render_code);
			$render_code->generate($gen_info);
			//echo "<textarea>{$render_code}</textarea>";

			$url_controller = $render_code->generateUrlController($gen_info);
			if (!$url_controller)
				throw new Exception("Unable to generate controller for: ".$element->getUrlPath());
			$code = QPHPToken::ParsePHPString($url_controller->toString(), false, false);
			// we need to rebuild $code as it's not parsed ok

			if ($gen_info->class_name === "index")
			{
				if ($this->debugMode)
					var_dump("to do");
				return;
			}
			else
			{
				if (!file_exists($element->classPath))
					$this->newFiles[$element->watchFolder][] = $element;

				// echo "<textarea>{$code}</textarea>";
				// echo "<textarea>{$element->classPath}</textarea>";
				// $this->mergeWithFileClass($code, $element->classPath, $full_path, true);
				$final_class = $element->getFinalTokens();
				$element->requiresCompiled = true;

				// $this->classPaths[$first_class->className] = $full_path;
				// $patch_class->transformToTrait($first_class->className."_GenTrait", $first_class, true);

				if ($final_class)
				{
					$final_class_obj = $final_class->findFirst(".QPHPTokenClass");
					$final_class_obj->mergeMethods($code->findFirst(".QPHPTokenClass")->methods);
					// echo "<textarea>{$final_class_obj}</textarea>";
					if ($final_class_obj->type === "class")
						$final_class_obj->mergeImplements("\\QIUrlController");
					// echo "<textarea>{$final_class_obj}</textarea>";
				}
				else
				{
					// $element->setFinalToken($code);
					throw new Exception("getFinalTokens should now always return");
				}

				$this->writtenFiles[$element->watchFolder][] = $element;
			}

			/*
			if ($sync_item->class_merge_node)
			{
				throw new Exception("are we using this ?");
				$write_to_struct = QPHPToken::ParsePHPFile($element->classPath);
				$patch_code_path = QAutoload::GetCompiledPath($sync_item->class_merge_node->classPath);
				$patch_php_code = QPHPToken::ParsePHPFile($patch_code_path, false);

				$patch_php_code->findFirst(".QPHPTokenClass")->mergeWithClass($write_to_struct->findFirst(".QPHPTokenClass"), true);

				// self::filePutContentsIfChanged(QAutoload::GetCompiledPath($element->classPath), $write_to_struct."", true);
				QCodeSync::syncCompiledCode($element->classPath, $patch_php_code);
			}
			 */

			$this->debugLog[$element->watchFolder][$element->className]["url"][$tag] = array("fp" => $full_path, "log" => $debug_log);
		}
		finally
		{
			$this->benchmark("resyncUrlController", $__t0, func_get_args());
		}
	}
	
	protected $syncCompiledCode_Cache = array();
	
	function syncCompiledCode($path, QPHPToken $patch_php_code)
	{
		$compiled_path = QAutoload::GetCompiledPath($path);
		
		if (file_exists($compiled_path))
		{
			// $this->syncCompiledCode_Cache[$compiled_path] ?: 
			$compiled_php_code = QPHPToken::ParsePHPFile($compiled_path, false);
			$compiled_php_code->findFirst(".QPHPTokenClass")->mergeWithClass($patch_php_code->findFirst(".QPHPTokenClass"), true);
			
			$patch_php_code = $compiled_php_code;
			// $this->syncCompiledCode_Cache[$compiled_path] = $compiled_php_code;
		}
		
		//echo "<textarea>{$patch_php_code}</textarea>";
		self::filePutContentsIfChanged($compiled_path, $patch_php_code->toString(), true);
	}
	
	/**
	 * Resyncs the Event Method
	 * 
	 * @param QCodeSyncNode $element
	 */
	public function resyncEventMethod(QCodeSyncNode $element, $tag = "")
	{
		// ! have a look at the other syncs !!!!
		throw new Exception("this is not up to date !!!");
		
		/*
		$sync_item = $element->data["event"][$tag];
		$full_path = $element->getEventPath();
		
		$obj_parsed = QPHPToken::ParsePHPFile($full_path, true);
		
		if ($this->debugMode)
			echo "Processing Event: {$full_path}<br/>\n";
		
		$render_code = $this->managePatching($sync_item, $obj_parsed);
		if (!$render_code)
			$render_code = $obj_parsed;
		
		// if ($this->debugMode)
		//echo "<textarea>{$render_code}</textarea>";
		
		$gen_info = new QGeneratePatchInfo($full_path);
		$code = $render_code->generateEventMethod($gen_info);
		
		//echo "<textarea>{$code}</textarea>";
		
		if ($gen_info->class_name === "index")
		{
			throw new Exception("to do");
		}
		else
		{
			if (!file_exists($element->classPath))
				$this->newFiles[$element->watchFolder][] = $element;
			
			$this->mergeWithFileClass($code, $element->classPath, $full_path, true);
			
			$this->writtenFiles[$element->watchFolder][] = $element;
		}
		
		if ($sync_item->class_merge_node)
		{
			$patch_code_path = QAutoload::GetCompiledPath($sync_item->class_merge_node->classPath);
			$patch_php_code = QPHPToken::ParsePHPFile($patch_code_path, false);
			
			$write_to_struct = null;
			if (file_exists($element->classPath))
			{
				$write_to_struct = QPHPToken::ParsePHPFile($element->classPath);
				$patch_php_code->findFirst(".QPHPTokenClass")->mergeWithClass($write_to_struct->findFirst(".QPHPTokenClass"), true);

				self::filePutContentsIfChanged(QAutoload::GetCompiledPath($element->classPath), $write_to_struct."", true);
			}
			else
				self::filePutContentsIfChanged(QAutoload::GetCompiledPath($element->classPath), $patch_php_code."", true);
		}
		
		// ! have a look at the other syncs !!!!
		throw new Exception("this is not up to date !!!");
		*/
	}
	
	/**
	 * Does the patching XML <-> XML
	 * 
	 * @param QCodeSyncItem $sync_item
	 * @param QPHPTokenFile $obj_parsed
	 * @param QPHPTokenFile $patch_element
	 * 
	 * @return null
	 */
	public function managePatching(QCodeSyncItem $sync_item, QPHPToken $obj_parsed, $patch_element = null, $ignore_changed = false, &$debug_log = null)
	{
		if (!($sync_item->patch && ($sync_item->changed || $ignore_changed)))
			return null;
		
		// skip if merge/patching is not required
		if (($first_DOM_Element = $obj_parsed->findFirst(".QPHPTokenXmlElement")))
		{
			$q_merge_val = $first_DOM_Element->getAttribute("qMerge");
			if (((!$q_merge_val) && ($sync_item->mode === "tpl")) || (strtolower($q_merge_val) === "false"))
				return null;
		}
		
		$patch_element = $sync_item->patch;
		$patch_elem_path = $patch_element->node->getModePath($sync_item->mode, $sync_item->tag);

		$obj_to_patch = $sync_item->patch->node->getReadOnlyTokens($sync_item->mode, $sync_item->tag); // QPHPToken::ParsePHPFile($patch_elem_path);
		
		if ($patch_element->patch)
		{
			$new_obj_to_patch = $this->managePatching($patch_element, $obj_to_patch, null, true);
			if ($new_obj_to_patch)
				$obj_to_patch = $new_obj_to_patch;
		}
		
		// echo "PATCHING: {$patch_elem_path} with {$obj_parsed->filename}<br/>\n";
		
		$debug_log .= "managePatching with ".$patch_elem_path."\n";

		$gen_info = new QGeneratePatchInfo($patch_elem_path);
		
		//echo "<h3>Patching</h3><textarea>{$obj_to_patch}</textarea>";
		//echo "<h3>With</h3><textarea>{$obj_parsed}</textarea>";
		
		// $obj_to_patch, $obj_parsed
		// $obj_parsed->patch($obj_to_patch, $obj_to_patch, $obj_parsed, $gen_info, true);
		if ($obj_to_patch)
		{
			$obj_parsed->inheritFrom($obj_to_patch, $obj_to_patch, $obj_parsed, $gen_info);
		}
		$obj_parsed->fixBrokenParents();
		
		// echo "<h3>Result</h3><textarea>{$obj_to_patch}</textarea>";
		
		return $obj_parsed;
	}
	
	/**
	 * Resyncs the Template
	 * 
	 * @param QCodeSyncNode $element
	 */
	public function resyncTemplate(QCodeSyncNode $element, $tag = "", $classParents = null)
	{
		try
		{
			$__t0 = microtime(true);
			
			$debug_log = "";
			// throw new Exception("pff");

			$sync_item = $element->data["tpl"][$tag];

			$full_path = $element->getTemplatePath($tag);
			if (!file_exists($full_path))
				// it's virtual
				return;

			// if it's the last watch folder then we don't have to get a clone

			// PROFILER: 1.4 sec
			$obj_parsed = $element->getTokens("tpl", $tag); // QPHPToken::ParsePHPFile($full_path, true);

			if (!$obj_parsed)
				throw new Exception("Invalid file ".$full_path);

			//echo "<h3>Before patching: {$full_path}</h3><textarea>{$obj_parsed}</textarea>";

			$render_code = $this->managePatching($sync_item, $obj_parsed, null, false, $debug_log);
			if (!$render_code)
				$render_code = $obj_parsed;

			// now convert things
			$gen_info = new QGeneratePatchInfo($element->getTemplatePath($tag));
			//if ($dump)
			//echo "<textarea>{$render_code}</textarea>";
			// PROFILER: 0.55 sec
			if (isset($sync_item->patch))
			{
				$gen_info->__tpl_parent = $sync_item->patch;
				$gen_info->__tpl_mode = $sync_item->mode;
				$gen_info->__tpl_tag = $sync_item->tag;

				// echo "<div style='color: red;'>XENSURE: {$full_path}</div>\n";
				// static::EnsureTplIsGenerated($sync_item);
			}

			//echo "<div style='color: red;'>GENERATE: {$full_path}</div>\n";
			$this->DebugGenerate($gen_info, $render_code);
			$render_code->generate($gen_info);
			
			if ($render_code->jsFunc)
			{
				$js_path = substr($element->getTemplatePath(""), 0, -4).".gen.js";
				$contents = file_exists($js_path) ? file_get_contents($js_path) : "";
				foreach ($render_code->jsFunc as $func_tag => $func_code)
				{
					list($func_class, $func_name) = explode("#", $func_tag);
					// OrderCtrl_productsDropDown.prototype.renderItemCaption = function
					list($func_info) = explode("=", $func_code[0]);
					$func_info = trim($func_info);
					list($js_func_class) = explode(".", $func_info);

					$js_func_class_without_ns = $js_func_class;
					$js_func_class = QPHPToken::ApplyNamespaceToName($js_func_class, $element->namespace);

					$parent_class_for_js_class = null;

					if ($element->className !== $js_func_class)
					{
						foreach ($render_code->requires_wrapping as $rw_tag => $rw_meths)
						{
							list($rw_parent_class, $rw_child_class) = explode("#", $rw_tag);
							if ($rw_child_class === $js_func_class_without_ns)
							{
								$parent_class_for_js_class = $rw_parent_class;
								break;
							}
						}
					}
					else
						$parent_class_for_js_class = $element->parentClass;

					$code_id_tag = "/** Begin :: Generated function: {$js_func_class}::{$func_name} **/";
					$code_id_tag_end = "/** End :: Generated function: {$js_func_class}::{$func_name} **/";

					$pos_beg = strpos($contents, $code_id_tag);
					$pos_end = ($pos_beg !== false) ? strpos($contents, $code_id_tag_end, $pos_beg) : false;

					// make sure we include resources for all parents
					// $parent_class_for_js_class
					// $this->all_current_classes[$parent_class_for_js_class]->parent->className
					// if (!$parent_class_for_js_class)
					$parent_class_for_js_class = $this->getJsParentClass($js_func_class, $classParents);
					// var_dump("getJsParentClass", $js_func_class, $parent_class_for_js_class);

					$new_first_elem = "QExtendClass(\"".qaddslashes($js_func_class)."\", \"".qaddslashes($parent_class_for_js_class)."\", {\n";

					// fix $func_code for namespace : OrderCtrl.prototype.renderEditItem = function
					$new_first_elem .= "{$func_name}: function";
					$func_code[0] = $new_first_elem;

					$func_str = QPHPToken::toString($func_code);
					$func_str = rtrim($func_str, "\n\t ;")."});\n";

					if ($pos_end !== false)
						$contents = substr($contents, 0, $pos_beg + strlen($code_id_tag)) . "\n" . $func_str . "\n" . substr($contents, $pos_end);
					else
						$contents .= "\n\n".$code_id_tag."\n".$func_str."\n".$code_id_tag_end."\n\n";
				}

				$rc_jsp = self::filePutContentsIfChanged($js_path, $contents);
				
				$js_debug_path = "/".ltrim(substr($js_path, strlen($_SERVER["DOCUMENT_ROOT"])), "/");

				$debug_log .= "Generating (".count($render_code->jsFunc).") JS Functions to: {$js_debug_path}\n";
			}

			// $gen_init = "\n\tpublic function generatedInit()\n\t{\n".$gen_info->_generatedInit."\n\t}\n";

			$extends = $element->parentClass ?: "\\QWebControl";
			
			$rc_setup_methods = $render_code->setup_methods;

			$includeJsClass_extra = "";

			if ($render_code->must_include_class)
			{
				foreach ($render_code->must_include_class as $incl_class)
				{
					$includeJsClass_extra .= "\t\t\$this->includeJsClass(\"".qaddslashes($incl_class)."\");\n";
				}
			}

			$func_camel = ucfirst($tag);
			$render_func_name = "render{$func_camel}";

			$namespace = $element->namespace;
			// $class_name = qClassWithoutNs($element->className);

			// var_dump($class_name, $namespace);

			// $render_code->cleanupTemplate();
			$first_xml = ($render_code instanceof QPHPTokenXmlElement) ? $render_code : $render_code->findFirst(".QPHPTokenXmlElement");
			$arguments = $render_args = $first_xml ? ($first_xml->getAttribute("qArgs") ?: ($first_xml->getAttribute("q-args") ?: "") ) : "";
			$arguments = $arguments ? trim($arguments) : "";

			$debug_path = "/".ltrim(substr($full_path, strlen($_SERVER["DOCUMENT_ROOT"])), "/");
			// var_dump($full_path);

			// PROFILER: 0.75 sec when forcing $render_code to string
			// here we could optimize in relation with the next profiler info (PROFILER: 0.42 sec)

			// we do the var replace {{}} as a last moment parsing
			$render_code_str = $render_code->toString(false, true);

			$render_code = /*"<?php\n\n".
						($namespace ? "namespace ".$namespace.";\n\n" : "").
						"trait {$class_name}__GenTrait\n{\n".*/
						"\t/**\n".
						"\t * @api.enable\n".
						"\t */\n".
						"\tpublic function {$render_func_name}({$render_args})\n\t{\n\t\t".
										($arguments ? 
										"if ((!func_num_args()) && \$this && \$this->_rf_args && (\$_rf_args = \$this->_rf_args[\"".qaddslashes($render_func_name)."\"]))\n".
										"\t\t	list(".self::GetVarsFromArgs($arguments).") = \$_rf_args;\n"
										: "").
										"\t\t\$this->includeJsClass();\n".
											$includeJsClass_extra.
										"/* if (\QWebRequest::IsAjaxCallback())\n".
											"\t\t{\n".
											"\t\t\t\$this->renderAjaxStart();\n".
											"\t\t\tif (!\$this->changed)\n".
												"\t\t\t\treturn;\n".
											"\t\t} */\n\t\tif (\QAutoload::\$DebugPanel) \$this->renderDebugInfo(\"".qaddslashes($debug_path)."\", \"".qaddslashes($debug_log)."\");\n\t\t?>".
										$render_code_str."<?php\n".
											"\t\t/* if (\QWebRequest::IsAjaxCallback())\n".
											"\t\t\t\$this->renderAjaxEnd(); */\n".
											"\t}\n";
						// $gen_init.
						/*($render_code->dynamic_methods ? implode("\n\n", $render_code->dynamic_methods) : "")*/
						// "\n}\n\n";

			if (!file_exists($element->classPath))
				$this->newFiles[$element->watchFolder][] = $element;

			// render must overwrite, but the rest DO NOT !!!
			// PROFILER: 0.42 sec

			// $render_code = QPHPToken::ParsePHPString($render_code."", false, false);

			$element->requiresCompiled = true;
			$final_class = $element->getFinalTokens();

			// $extends

			if (!$final_class)
			{
				throw new Exception("getFinalTokens should always return (".$element->classPath.")");
			}
			else
			{
				$final_class_obj = $final_class->findFirst(".QPHPTokenClass");
				// $final_class_obj->mergeMethods($render_code->findFirst(".QPHPTokenClass")->methods);

				$method = $final_class_obj->setMethodFromString($render_func_name, $render_code, false);
				$method->visibility = "public";
				
				if ($rc_setup_methods)
				{
					foreach ($rc_setup_methods as $rc_meth_name => $rc_meth_body)
					{
						$rc_method = $final_class_obj->setMethodFromString($rc_meth_name, $rc_meth_body, false);
						$rc_method->visibility = "public";
					}
				}

				if ($extends && ($final_class_obj->type !== "trait"))
				{
					$final_class_obj->setExtends($extends, true);
				}

				if ($namespace)
				{
					if ($final_class instanceof QPHPTokenCode)
						$final_class->setNamespace($namespace);
					else
						$final_class->findCode()->setNamespace($namespace);
				}
			}

			$this->writtenFiles[$element->watchFolder][] = $element;

			$this->debugLog[$element->watchFolder][$element->className]["tpl"][$tag] = array("fp" => $full_path, "log" => $debug_log);
		}
		finally
		{
			$this->benchmark("resyncTemplate", $__t0, func_get_args());
		}
	}

	public function resyncPHPClass(QCodeSyncNode $element, $tag = "")
	{
		// echo "resyncPHPClass::{$tag}".$element->classPath."<br/>\n";
		try
		{
			$__t0 = microtime(true);
			
			if (!file_exists($element->classPath))
				return;

			// generate for API methods
			$this->generateApiMethods($element, $element->getReadOnlyTokens("php"));

			// generate _set / _check, ...

			if ($element->needsModelResync("php"))
			{
				$this->generateModelMethods($element, $element->getReadOnlyTokens("php"));
				$this->_resolve_after['security'][$element->className] = $element;
			}
		}
		finally
		{
			$this->benchmark("resyncPHPClass", $__t0, func_get_args());
		}
	}
	
	/**
	 * Resyncs based on a patch file
	 * 
	 * @param QCodeSyncNode $element
	 * @throws Exception
	 */
	public function resyncPatchJS(QCodeSyncNode $element, $tag = "", $patch_metadata = null)
	{
		$full_path = $element->getPatchPath();
		$js_path = substr($full_path, 0, -strlen(".patch.php")).".js";
		// var_dump("resyncPatchJS", $js_path, get_class($element->parent));
		// find the last JS 
		// qvar_dump($element
		$patched = isset($element->data['patch'][""]->patch->node) ? $element->data['patch'][""]->patch->node : null;
		$parent_js = null;
		$parent_js_wf = null;
		
		while ($patched)
		{
			$parent_js = substr($patched->classPath, 0, -4).".js";
			// var_dump("parent_js", $parent_js, file_exists($parent_js));
			if (file_exists($parent_js))
			{
				// this is the one
				$parent_js_wf = $patched->watchFolder;
				break;
			}
			else
				$parent_js = null;
			$patched = isset($patched->data['patch'][""]->patch->node) ? $patched->data['patch'][""]->patch->node : null;
		}
		
		if ($parent_js && file_exists($parent_js))
		{
			/*
			// $parent_js
			if (!$parent_js_wf)
				throw new \Exception('Missing wf');
			$parent_js_rel = substr($parent_js, strlen($parent_js_wf));
			
			$JS_Patch_Append_Key_Start = "/ * START PATCHED JS: {$parent_js_rel} | (key:".static::JS_Patch_Append_Key.") * /";
			$JS_Patch_Append_Key_End = "/ * END PATCHED JS: {$parent_js_rel} | (key:".static::JS_Patch_Append_Key.") * /";
			// const JS_Patch_Append_Key_Start = "/* START: mp93xcgyn2mfyzgnbi428o7gfnzchm2sxdbg74xz2cgh * /";
			// const JS_Patch_Append_Key_End = "/* END: mp93xcgyn2mfyzgnbi428o7gfnzchm2sxdbg74xz2cgh * /";
			$dest_str = file_get_contents($js_path);
			$start_pos = strpos($dest_str, $JS_Patch_Append_Key_Start);
			$end_pos = strpos($dest_str, $JS_Patch_Append_Key_End, $start_pos ?: 0);
			
			$src_str = file_get_contents($parent_js);
			
			$new_js = null;
			if ($start_pos && $end_pos)
			{
				$exp_k = $start_pos + strlen($JS_Patch_Append_Key_Start);
				$existing_patch = substr($dest_str, $exp_k, $end_pos - $exp_k);
				if (trim($existing_patch) !== trim($src_str))
				{
					$new_js = rtrim(substr($dest_str, 0, $start_pos))."\n\n".$JS_Patch_Append_Key_Start."\n\n".$src_str."\n\n".$JS_Patch_Append_Key_End.
								"\n\n".ltrim(substr($dest_str, $end_pos + strlen($JS_Patch_Append_Key_End)));
				}
			}
			else
				$new_js = rtrim($dest_str)."\n\n".$JS_Patch_Append_Key_Start."\n\n".$src_str."\n\n".$JS_Patch_Append_Key_End."\n\n";
			
			if ($new_js)
			{
				static::filePutContentsIfChanged($js_path, $new_js);
			}
			*/
		}
	}
	
	/**
	 * Resyncs based on a patch file
	 * 
	 * @param QCodeSyncNode $element
	 * @throws Exception
	 */
	public function resyncPatch(QCodeSyncNode $element, $tag = "")
	{
		try
		{
			$__t0 = microtime(true);

			$debug_log = "";

			$full_path = $element->getPatchPath();
			// $sync_item = $element->data["patch"][$tag];

			$obj_parsed = $element->getReadOnlyTokens("patch", $tag); // $this->getParsedFile($full_path);
			if (!$obj_parsed)
				throw new Exception("Unable to parse: ".$element->getModePath("patch", $tag));

			$first_class = $obj_parsed->findFirst(".QPHPTokenClass");
			if (!$first_class)
			{
				qvar_dumpk($obj_parsed);
				throw new Exception("Unable to find the main class in: {$full_path}");
			}
			if (!$first_class->className)
				throw new Exception("Missing class name in: {$full_path}");

			// $class = $element->className;
			//$patch_element = $sync_item->patch;
			//if (!$patch_element)
			//	throw new Exception("No patch for: ".$full_path);
			/*
			$tmp_cp = QAutoload::GetCompiledPath($patch_element->node->classPath);
			$class_to_patch_path = ($tmp_cp && file_exists($tmp_cp)) ? $tmp_cp : $patch_element->node->classPath;
			if (!$class_to_patch_path)
				throw new Exception("Missing class to patch in: {$full_path}");

			$debug_log .= "Class to patch path: {$class_to_patch_path}\n";

			$obj_to_patch = QPHPToken::ParsePHPFile($class_to_patch_path, false);
			if (!$obj_to_patch)
				throw new Exception("Unable to parse: {$class_to_patch_path}");
			*/

			$obj_to_patch = $element->getFinalTokens();
			
			if (!$obj_to_patch)
				throw new Exception("Unable to find a class to patch: ".$element->classPath);
			$patch_class = $obj_to_patch->findFirst(".QPHPTokenClass");

			// apply patch directives: @patch.rename, @patch.remove
			$patch_metadata = $first_class->docComment ? QCodeStorage::parseClassDocComment($first_class->docComment) : null;
			if (isset($patch_metadata['storage']['view_js_patch']) && file_exists(substr($full_path, 0, -strlen(".patch.php")).".js"))
				$this->resyncPatchJS($element, $tag, $patch_metadata);
			$patch_metadata = $patch_metadata ? $patch_metadata["patch"] : null;
			if ($patch_metadata)
			{
				if ($patch_metadata["rename"])
				{
					foreach ($patch_metadata["rename"] as $old_name => $new_name)
						$patch_class->renamePropertyOrMethod($old_name, $new_name);
				}
				if ($patch_metadata["remove"])
				{
					foreach ($patch_metadata["remove"] as $remove_item)
						$patch_class->removePropertyOrMethod($remove_item);
				}
			}

			/*
			if (!$patch_class)
				throw new Exception("Unable to find the main class in: {$class_to_patch_path}");
			if (!$patch_class->className)
				throw new Exception("Missing class name in: {$class_to_patch_path}");

			if ($patch_element->node->className !== $class)
				throw new Exception("Not matching the same class {$full_path} => {$class_to_patch_path} || {$patch_class->className} vs {$class} | ".get_class($patch_class));
			*/

			/*
			if ($first_class->type === "trait")
			{
				// make sure we set the right include
				$this->classPaths[$first_class->className] = $full_path;
				$patch_class->useTrait($first_class, true);
			}
			else
			{
				$patch_class->mergeWithClass($first_class, false);
			}
			*/

			// $this->classPaths[$first_class->className] = $full_path;
			$patch_class->transformToTrait($first_class->className."_GenTrait", $first_class, true);
			//echo "<textarea>{$patch_class}</textarea>";
			// $patch_class->useTrait($first_class, true);

			// $element->patchDependents["patch"] = $class_to_patch_path;
			// $class_to_patch_path->patchDependents["patch"][] = $element;

			if (!file_exists($element->classPath))
				$this->newFiles[$element->watchFolder][] = $element;

			// $destination = QAutoload::GetCompiledPath(dirname($full_path)."/".$class.".php");
			// self::filePutContentsIfChanged($destination, $obj_to_patch->__toString(), true);

			// QCodeSync::syncCompiledCode(dirname($full_path)."/".qClassWithoutNs($class).".php", $obj_to_patch);


			$element->requiresCompiled = true;
			$element->setFinalToken($obj_to_patch);

			$this->generateApiMethods($element, $element->getReadOnlyTokens("patch"));
			// $this->generateSecurityMethods($element, $element->getReadOnlyTokens("patch"));
			// now handle generation of model methods
			if ($element->needsModelResync("patch"))
			{
				$this->generateModelMethods($element, $element->getReadOnlyTokens("patch"));
				$this->_resolve_after['security'][$element->className] = $element;
			}

			$this->writtenFiles[$element->watchFolder][] = $element;

			$this->debugLog[$element->watchFolder][$element->className]["patch"][$tag] = array("fp" => $full_path, "log" => $debug_log);
		}
		finally
		{
			$this->benchmark("resyncPatch", $__t0, func_get_args());
		}
	}
	
	public function setChanges($changed_or_added, $removed_files, $new_files)
	{
		$this->changedFiles = array();
		
		if (!$this->fullSync)
		{
			foreach ($changed_or_added as $wf => $files)
			{
				foreach ($files as $f => $time)
					$this->changedFiles[$wf.$f] = true;
			}
			foreach ($new_files as $wf => $files)
			{
				foreach ($files as $f => $time)
					$this->changedFiles[$wf.$f] = true;
			}
		}
		
		$this->changed_or_added = $changed_or_added;
		$this->removed_files = $removed_files;
		$this->new_files = $new_files;
	}
	
	public static function FileWasChanged($path)
	{
		if (!self::$Instance)
			throw new Exception(get_called_class()." was not initialized");
		
		$sync = self::$Instance;
		if ($sync->changed_or_added)
		{
			foreach ($sync->changed_or_added as $work_folder => $data)
			{
				$wf_len = strlen($work_folder);
				if (substr($path, 0, $wf_len) === $work_folder)
				{
					$rest = substr($path, $wf_len);
					if ($data[$rest])
						return true;
				}
			}
		}
		
		return false;
	}
	
	public function getClassImplements(QCodeSyncNode $elem, $watchFolder, $cache_mode = true)
	{
		// $sync_node->head_info = QCodeSyncItem::$ExtractCache[$path_to_sync_node];
		if (!$elem->head_info)
			throw new Exception("should have been populated !");
		return $elem->head_info ? $elem->head_info["implements"] : null;
		/*
		$info = $this->getClassHeadInfo($elem, $watchFolder, $cache_mode);
		if ($info && $info["implements"])
		{
			// do some cleanup
			$new_info = [];
			foreach ($info["implements"] as $impl_name)
			{
				$i_name = QPHPToken::ApplyNamespaceToName($impl_name, $info["namespace"]);
				$new_info["implements"][$i_name] = $i_name;
			}
			$info = $new_info;
		}
		return $info ? $info["implements"] : null;
		 * 
		 */
	}
	
	public function getClassHeadInfo(QCodeSyncNode $elem, $watchFolder, $cache_mode = true)
	{
		if ($cache_mode && ($ret_cache = $this->headInfo[$watchFolder][$elem->className]))
			return $ret_cache;
		
		// also setup some cache
		$ret = array();
		if (($php = $elem->data["php"]) && file_exists($elem->classPath))
		{
			$info = QPHPToken::ParseHeaderOnly($elem->classPath);
			$this->mergeClassHeadInfo($ret, $info);
		}
		if (($php = $elem->data["patch"]) && file_exists($elem->getModePath("patch")))
		{
			$info = QPHPToken::ParseHeaderOnly($elem->getModePath("patch"));
			$this->mergeClassHeadInfo($ret, $info);
		}
		if (($php = $elem->data["tpl"]))
		{
			foreach ($elem->data["tpl"] as $tag => $tnfo)
			{
				$parse_path = $elem->getModePath("tpl", $tag);
				if ((!file_exists($parse_path)) && (!($elem->relatedNode && is_file($parse_path = $elem->relatedNode->getModePath("tpl", $tag)))))
					continue;
				if (!is_file($parse_path))
					throw new Exception("Should never be");
				
				$info = QPHPToken::ParseHeaderOnly($parse_path);
				$this->mergeClassHeadInfo($ret, $info);
			}
		}
		if (($php = $elem->data["url"]))
		{
			$info = QPHPToken::ParseHeaderOnly($elem->getModePath("url"));
			$this->mergeClassHeadInfo($ret, $info);
		}
		
		$missing_extends = !$ret["extends"];
		$missing_implements = !$ret["implements"];
		if ($missing_extends || $missing_implements)
		{
			$prev_wf = $this->getPreviousWatchFolders($watchFolder);
			$prev_watch_folders = $prev_wf ? array_reverse($prev_wf) : null;
			if ($prev_watch_folders)
			{
				foreach ($prev_watch_folders as $prev_wf)
				{
					$stack_item = $this->data[$prev_wf][$elem->className];
					if (!$stack_item)
						continue;
					$prev_head_inf = $this->getClassHeadInfo($stack_item, $stack_item->watchFolder, $cache_mode);
					if ($missing_extends && ($per_wf_extends = $prev_head_inf["extends"]))
					{
						$ret["extends"] = "\\".QPHPToken::ApplyNamespaceToName($per_wf_extends, $prev_head_inf["namespace"]);
						$missing_extends = false;
						if (!$missing_implements)
							break;
					}
					if ($missing_implements && ($per_wf_implements = $prev_head_inf["implements"]))
					{
						foreach ($per_wf_implements as $_per_wf_impls)
						{
							$per_wf_impls = "\\".QPHPToken::ApplyNamespaceToName($_per_wf_impls, $prev_head_inf["namespace"]);
							$ret["implements"][$per_wf_impls] = $per_wf_impls;
						}
						$missing_implements = false;
						if (!$missing_extends)
							break;
					}
				}
			}
		}
		
		if ((!$ret["extends"]) && $elem->data["tpl"])
		{
			// default to QWebControl for templates
			$ret["extends"] = "\\QWebControl";
		}
		if ((!$ret["implements"]) && $elem->data["url"])
		{
			// default to QIUrlController for url controllers
			$ret["implements"] = array(__NAMESPACE__."\QIUrlController" => __NAMESPACE__."\QIUrlController");
		}
		
		if ($ret && $ret["class"])
			$this->headInfo[$watchFolder][$ret["class"]] = $ret;
		
		return $ret;
	}
	
	protected function getPreviousWatchFolders($current_watch_folder)
	{
		$prev_watchFolders = array();
		foreach ($this->watchFolders as $wf)
		{
			if ($wf === $current_watch_folder)
				break;
			$prev_watchFolders[] = $wf;
		}
		
		return $prev_watchFolders;
	}
	
	protected function mergeClassHeadInfo(&$ret_info, $info_2, $throw_error = true)
	{
		if ($ret_info === null)
			return ($ret_info = $info_2);
		else if ($info_2 === null)
			return $ret_info;
		
		// we will copy into $ret_info
		if ($ret_info["class"])
		{
			if ($info_2["class"] && ($ret_info["class"] !== $info_2["class"]) && $throw_error)
				var_dump("Mismatching class name: {$ret_info["class"]} vs {$info_2["class"]}");
		}
		else
			$ret_info["class"] = $info_2["class"];
		
		if ($ret_info["extends"])
		{
			if ($info_2["extends"] && ($ret_info["extends"] !== $info_2["extends"]) && $throw_error)
				var_dump("Mismatching extends name: {$ret_info["extends"]} vs {$info_2["extends"]} for class: ".$ret_info["class"]);
		}
		else
			$ret_info["extends"] = $info_2["extends"];
		
		
		if ($info_2["implements"])
		{
			if ($ret_info["implements"])
			{
				foreach ($info_2["implements"] as $impl)
					$ret_info["implements"][$impl] = $impl;
			}
			else
				$ret_info["implements"] = $info_2["implements"];
		}
		
		if ($info_2["traits"])
		{
			if ($ret_info["traits"])
			{
				foreach ($info_2["traits"] as $impl)
					$ret_info["traits"][$impl] = $impl;
			}
			else
				$ret_info["traits"] = $info_2["traits"];
		}
		
		if ($ret_info["namespace"])
		{
			if ($info_2["namespace"] && ($ret_info["namespace"] !== $info_2["namespace"]) && $throw_error)
				var_dump("Mismatching namespace name: {$ret_info["namespace"]} vs {$info_2["namespace"]} for class: ".$ret_info["class"]);
		}
		else
			$ret_info["namespace"] = $info_2["namespace"];
		
		return $ret_info;
	}
	
	/**
	 * If a class is extended by a certain class, then the parents are also extended by that class
	 * 
	 * @param string $php_class The class that extends
	 * @param string $parent_iface_or_class The class that is extended
	 * @param string[][] $_Q_FRAME_EXTENDED_BY
	 */
	protected function propagateExtendedBy($php_class, $parent_iface_or_class, &$_Q_FRAME_EXTENDED_BY, $classParents, $_Q_FRAME_IMPLEMENTS_ARRAY)
	{
		$extended_classes = $parent_iface_or_class ? [$parent_iface_or_class => $parent_iface_or_class] : [];
		if (($implements = $_Q_FRAME_IMPLEMENTS_ARRAY[$php_class]))
		{
			foreach ($implements as $interface => $v)
			{
				if ($interface)
					$extended_classes[$interface] = $interface;
			}
		}
		$inheriting_classes = $_Q_FRAME_EXTENDED_BY[$php_class];
		if ($inheriting_classes)
			$inheriting_classes[$php_class] = $php_class;
		else
			$inheriting_classes = [$php_class => $php_class];
		
		while ($extended_classes)
		{
			$new_extended = [];
			foreach ($extended_classes as $extended_class)
			{
				// mark them all
				foreach ($inheriting_classes as $inheriting_class)
					$_Q_FRAME_EXTENDED_BY[$extended_class][$inheriting_class] = $inheriting_class;

				if (($ccp = $classParents[$extended_class]))
					$new_extended[$ccp] = $ccp;
				if (($cci = $_Q_FRAME_IMPLEMENTS_ARRAY[$extended_class]))
				{
					foreach ($cci as $_cci => $v)
					{
						if ($_cci)
							$new_extended[$_cci] = $_cci;
					}
				}
			}
			
			// $extended_classes are added to $inheriting_classes
			//$inheriting_classes = array_merge($inheriting_classes, $extended_classes);
			$extended_classes = $new_extended;
		}
	}
	
	/**
	 * Sub method for generateModelMethods
	 * 
	 * @param string|QModelAcceptedType $type_inf
	 * @return string[]
	 * @throws Exception
	 */
	protected function generateModelMethodsGetParamsForType($type_inf)
	{
		$is_ok = null;
		$cast_str = null;
		$valid_type = false;
		
		$remove_thous_sep = false;
		
		if ($type_inf instanceof QModelAcceptedType)
		{
			$is_ok = "((!__IN_PHP__) || (\$value instanceof \\QIModelArray))";
			$valid_type = true;
		}
		else if ($type_inf{0} !== strtolower($type_inf{0}))
		{
				// var_dump("A: ".$type_inf);
			// reference
			$is_ok = "((!__IN_PHP__) || (\$value instanceof \\{$type_inf}))";
			$valid_type = true;
		}
		else 
		{
			switch ($type_inf)
			{
				case "int":
				case "integer":
				{
					$cast_str = "(int)";
					$valid_type = true;
					$remove_thous_sep = true;
					break;
				}
				case "float":
				case "double":
				{
					$cast_str = "(float)";
					$remove_thous_sep = true;
					$valid_type = true;
					break;
				}
				case "string":
				{
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "bool":
				case "boolean":
				{
					$cast_str = "(bool)";
					$valid_type = true;
					break;
				}
				case "date":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "datetime":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "array":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(array)";
					$valid_type = true;
					break;
				}
				case "time":
				{
					// parse date
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "file":
				{
					// to do
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				default:
				{
					// see: QModel::GetTypeByName
					// throw new Exception("to do: ".$type_inf);
					$valid_type = false;
					break;
				}
			}
		}
		
		return array($is_ok, $cast_str, $valid_type, $remove_thous_sep);
	}
	
	
	protected function generateModelMethodsForType($assign_to, QPHPTokenProperty $m_property, $meth_name, $type_inf, $for_array = false, $for_id = false)
	{
		// fix type info a bit if possible
		if (is_array($type_inf) && (next($type_inf) === false))
			$type_inf = reset($type_inf);
		
		// now create casts for $type_inf
		$is_ok = null;
		$cast_str = null;
		$get_type = "";
		$before = "";
		$custom_assign = null;
		
		$fixval_str = null;
		$validation_str = null;

		if ($m_property->parsed_data["fixValue"])
			$fixval_str = self::GetFixValStr($m_property->parsed_data["fixValue"], $for_array);
		if ($m_property->parsed_data["encode"])
			$encode_str = self::GetEncodeValStr($m_property->parsed_data["encode"], $for_array);
		if ($m_property->parsed_data["validation"])
			$validation_str = self::GetValidationStr($m_property->parsed_data["validation"], $for_array);
		
		$acc_type = null;
		$valid_type = false;
		
		$possible_collection = false;
		$collection_only = false;

		if (is_string($type_inf))
		{
			list ($is_ok, $cast_str, $valid_type, $remove_thous_sep) = $this->generateModelMethodsGetParamsForType($type_inf);
		}
		else if ($type_inf instanceof QModelAcceptedType)
		{
			list ($is_ok, $cast_str, $valid_type, $remove_thous_sep) = $this->generateModelMethodsGetParamsForType($type_inf->type);
			$acc_type = $type_inf instanceof QModelAcceptedType ? $type_inf : null;
			$possible_collection = true;
			$collection_only = true;
		}
		else if (is_array($type_inf))
		{
			$accepted_obj_types = array();
			$accepted_scalar_types = array();
			
			// var_dump($type_inf);
			
			// all needs to be valid
			$valid_type = true;
			// now we will need to loop a bit
			// fill: $is_fail, $cast_str
			foreach ($type_inf as $_tyinf)
			{
				$use_ty = $_tyinf instanceof QModelAcceptedType ? $_tyinf->type : $_tyinf;
				$type_extracted_inf = $this->generateModelMethodsGetParamsForType($use_ty, true);

				if ($type_extracted_inf[3])
				{
					// it seems that we have this case and it is not treated
					//throw new \Exception("Thousands separator not implemented for mixed types");
				}
				
				// all needs to be valid
				$valid_type = $valid_type && $type_extracted_inf[0];
				
				if ($_tyinf instanceof QModelAcceptedType)
				{
					$accepted_obj_types[$use_ty] = $type_extracted_inf;
					$acc_type = $_tyinf;
					$possible_collection = true;
				}
				else if ($_tyinf{0} !== strtolower($_tyinf{0}))
					$accepted_obj_types[$use_ty] = $type_extracted_inf;
				else
					$accepted_scalar_types[$use_ty] = $type_extracted_inf;
			}
			
			// var_dump($accepted_obj_types);
			
			if ($valid_type)
			{
				$custom_assign = "";

				// var_dump($accepted_obj_types, $accepted_scalar_types);

				$scalar_types_count = $accepted_scalar_types ? count($accepted_scalar_types) : 0;

				$assign_scalar = null;
				// $cast_scalar = null;

				if ($accepted_scalar_types)
				{
					if ($scalar_types_count === 1)
					{
						// we will try to cast
						list(, $cast_str) = reset($accepted_scalar_types);
						$assign_scalar = "{$cast_str}\$value";
					}
					else
					{
						// to do, also populate aliases
						// exclusive or error
						$before = "\n\t\t\$posible_scalars = array('".implode("' => true, '", $this->getCompatbilePHPTypes(array_keys($accepted_scalar_types)))."' => true);";
						$assign_scalar = "\$posible_scalars[gettype(\$value)] ? \$value : (\$fail = null)";
					}
				}

				if ($accepted_obj_types)
				{
					$custom_assign .= "is_object(\$value) ? ((";
					$first = true;
					
					foreach ($accepted_obj_types as $oty => $oty_inf)
					{
						if (!$first)
							$custom_assign .= " || ";
						else
							$first = false;
						$custom_assign .= "((!__IN_PHP__) || (\$value instanceof \\{$oty}))";
					}
					$custom_assign .= ") ? \$value : (\$fail = null))";

					if (!$accepted_scalar_types)
						$custom_assign .= " : (\$fail = null)";
				}

				if ($accepted_scalar_types)
				{
					if ($accepted_obj_types)
						$custom_assign .= " : (";

					$custom_assign .= $assign_scalar;

					if ($accepted_obj_types)
						$custom_assign .= ")";
				}

				// stuff for: $custom_assign & $before

				$custom_assign = "(!\$check || (\$value === null)) ? \$value : (".$custom_assign.")";
			}
			// throw new Exception("to do");
		}
		else
		{
			var_dump($type_inf);
			throw new Exception("Bad type parsing. This should not be");
		}

		if ($valid_type)
		{
			$acc_type_meth_name = $meth_name."_Item_";
			$acc_type_str = null;
			if ($acc_type)
			{
				// to do ... create Setter/checker for it
				// set{$PropertyName}_Item_
				
				$acc_type_str = $this->generateModelMethodsForType($assign_to, $m_property, $acc_type_meth_name, $acc_type->options, true);
				if (!$acc_type_str)
					$valid_type = false;
			}
			
			$property_name = $m_property->name;
			
			// $validation_str
			if ($validation_str)
			{
				if ($collection_only)
				{
					// @todo different for collection only
				}
				else if ($possible_collection)
					$is_ok = "(((!__IN_PHP__) || (\$value instanceof QIModelArray)) ? (".($is_ok ? $is_ok." && " : "")."({$validation_str})".") : (".($is_ok ?: "")."))";
				else 
					$is_ok = ($is_ok ? $is_ok." && " : "")."({$validation_str})";
			}
			
			$str = "	public function {$meth_name}(\$value, ".($for_array ? "\$key = null, \$row_id = null, " : "")."\$check = true, \$null_on_fail = false)
	{
		\$fail = false;{$get_type}{$before}".($possible_collection ? "
		if (is_array(\$value) && (\$check !== 1))
			\$value = new \QModelArray(\$value);" : "").(($fixval_str || $remove_thous_sep) ? ("
		\$value = ".($remove_thous_sep ? "str_replace(Q_Thousands_Separator, '', " : "(").
						($fixval_str ? "{$fixval_str});\n" : "\$value);\n")) : "").
			($encode_str ? "
		\$value = {$encode_str};" : "").
				"
		\$return = ".
				($custom_assign ?: "((\$check === false) ".($validation_str ? "" : " || (\$value === null)").") ? \$value : ".
					($is_ok ? "({$is_ok} ? {$cast_str}\$value : (\$fail = null))" : "{$cast_str}\$value")).";
		if ((\$fail === null) && (!\$null_on_fail))
			throw new \Exception(\"Failed to assign value in {$meth_name}\");
		if (\$check !== 1)
		{
			{$assign_to}".($for_array ? "[\$key]" : "")." = \$return;".
			($for_array ? "
			if ((\$key !== null) && (\$row_id !== null))
				{$assign_to}->setRowIdAtIndex(\$key, \$row_id);" : "").
			($for_id ? "
			\$this->_id = (is_string(\$return) && empty(\$return)) ? null : \$return;" : "")."
			\$this->".($for_array ? "{$property_name}->_wst[\$key]" : "_wst[\"{$property_name}\"]")." = true;
		}
		return \$return;
	}\n";

			return $acc_type_str ? array($meth_name => $str, $acc_type_meth_name => $acc_type_str) : $str;
		}
		else
			return null;
	}
	
	protected function generateApiMethods(QCodeSyncNode $element, QPHPToken $source_tokens)
	{
		$src_class = $source_tokens->findPHPClass();
		if (!$src_class)
			throw new Exception("Missing source class. Possible parse error on element: ".$element->classPath);
		
		if (!$src_class->methods)
			return;
		
		$meths_to_set = [];
		$namespace = $source_tokens->getNamespace();
		
		foreach ($src_class->methods as $meth_name => $method)
		{
			$doc_comment = $method->docComment ? (is_array($method->docComment) ? $method->docComment[1] : $method->docComment) : null;
			
			if ((!$doc_comment) || (strpos($doc_comment, "@api") === false))
				continue;
			
			$extract_comment = null;
			$meth_data = QCodeStorage::parseDocComment($doc_comment, true, $namespace, $extract_comment);
			
			if ($data)
			{
				echo "<hr/>";
				var_dump($meth_name, $data, $extract_comment);
				echo "<hr/>";
			}
			$meth_data["static"] = $method->static;
			$meth_data["full_comment"] = $doc_comment;
			
			$gen_api_meth = $this->generateApiMethodFromData($meth_name, $meth_data);
			list($gen_meth_code_in, $gen_meth_code_out) = $gen_api_meth;
			// var_dump($gen_meth_code, $proposed_api_code);
			if ($gen_meth_code_in)
				$meths_to_set[$meth_name."_in_"] = $gen_meth_code_in;
			if ($gen_meth_code_out)
				$meths_to_set[$meth_name."_out_"] = $gen_meth_code_out;
		}
		
		if ($meths_to_set)
		{
			$final_tokens = $element->getFinalTokens();
			$final_class = $final_tokens->findPHPClass();
			
			$str = "<?php\n\n".$final_class->type." ".$final_class->className."\n{\n\n";
			foreach ($meths_to_set as $meth)
				$str .= $meth;
			$str .= "\n\n}\n";
			
			$parsed_gen_meths = QPHPToken::ParsePHPString($str, false, false);
			
			$final_class->mergeMethods($parsed_gen_meths->findPHPClass()->methods, true);
			$element->requiresCompiled = true;
		}
	}
	
	protected function generateModelMethods(QCodeSyncNode $element, QPHPToken $source_tokens)
	{
		// var_dump("generateModelMethods", $element->classPath);
		
		// TO DO: WE NEED THE NAMESPACE !!!
		//echo "<textarea>{$source_tokens}</textarea>";
		
		$src_class = $source_tokens->findPHPClass();
		if (!$src_class)
			throw new Exception("Missing source class. Possible parse error on element: ".$element->classPath);
		
		// qvar_dump($src_class->properties);
		
		if (!$src_class->properties)
			return;
		
		$meths_to_set = [];
		
		foreach ($src_class->properties as $prop)
		{
			// ignore statics
			if ($prop->static)
				continue;
			
			$meth_name = "set".ucfirst($prop->name)."";
			
			// if the method is user defined continue
			if ($src_class->methods[$meth_name])
				continue;
			
			$type_inf = null;
			$doc_comm = is_string($prop->docComment) ? $prop->docComment : $prop->docComment[1];
			$parsed = null;
			if ($doc_comm)
			{
				// $this::parseDocComment($prop->getDocComment(), false, $ref_class->getNamespaceName());
				$prop->parsed_data = $parsed = QCodeStorage::parseDocComment($doc_comm, false, $element->namespace);
				$type_inf = $parsed ? $parsed["types"] : null;
			}
			
			
			//var_dump($type_inf);
			
			if (!$type_inf)
				continue;
			
			// if $type_inf
			//					generateModelMethodsForType($assign_to, $meth_name, $type_inf, $for_array = false, $for_id = false)
			$meth_str = $this->generateModelMethodsForType("\$this->".$prop->name, $prop, $meth_name, $type_inf, false, (strtolower($prop->name) === "id"));
			
			// var_dump($meth_str);
			
			if ($meth_str)
			{
				if (is_array($meth_str))
				{
					foreach ($meth_str as $m_name => $meth_body)
						$meths_to_set[$m_name] = $meth_body;
				}
				else
					$meths_to_set[$meth_name] = $meth_str;
			}
			
		}
		
		if ($meths_to_set)
		{
			$final_tokens = $element->getFinalTokens();
			$final_class = $final_tokens->findPHPClass();
			
			// $str = "<?php\n\n".$final_class->type." ".$final_class->className."\n{\n\n";
			if ($meths_to_set)
			{
				foreach ($meths_to_set as $m_name => $meth)
					// $str .= $meth;
					$final_class->setMethodFromString($m_name, $meth, false);
			}
			//$str .= "\n\n}\n";
			
			// $parsed_gen_meths = QPHPToken::ParsePHPString($str, false, false);
			
			// $final_class->mergeMethods($parsed_gen_meths->findPHPClass()->methods, true);
			$element->requiresCompiled = true;
			
			// echo "<textarea>{$final_tokens}</textarea>";
		}
		
	}
	
	protected static function getCompatbilePHPTypes($types)
	{
		$ret = array();
		foreach ($types as $ty)
		{
			switch ($ty)
			{
				case "boolean":
				case "bool":
				{
					$ret["boolean"] = "boolean";
					break;
				}
				case "integer":
				case "int":
				{
					$ret["integer"] = "integer";
					break;
				}
				case "double":
				case "float":
				{
					$ret["double"] = "double";
					break;
				}
				case "string":
				{
					$ret["string"] = "string";
					break;
				}
				case "array":
				{
					$ret["array"] = "array";
					break;
				}
				case "object":
				case "resource":
				case "NULL":
				case "unknown type":
				default:
				{
					throw new Exception("Type not accepted: ".$ty);
				}
			}
		}
		return $ret;
	}
	
	public static function TraitWasIncluded(QCodeSyncNode $element, $file_with_class = null)
	{
		if (self::$TraitIncludeInfo === null)
			self::LoadTraitWasIncluded();
		
		// $this->changedFiles
		// if it was changed, then we need to review our plan
		$file_with_class = $file_with_class ?: (file_exists($element->classPath) ? $element->classPath : $element->getPatchPath());
		
		if (($ret = self::$TraitIncludeInfo[$file_with_class]) !== null)
			return $ret;
		else
		{
			$trait_was_included = false;
			
			$head_inf = $element->head_info;
			if ($head_inf["traits"])
			{
				$expected_trait = $element->className."_GenTrait";
				$trait_was_included = $head_inf["traits"][$expected_trait] ? true : false;
			}
			
			return (self::$TraitIncludeInfo[$file_with_class] = $trait_was_included);
		}
	}
	
	public static function LoadTraitWasIncluded()
	{
		if (self::$TraitIncludeInfo !== null)
			return;
		else if (QCodeSync::$Instance && QCodeSync::$Instance->fullSync)
			return (self::$TraitIncludeInfo = []);
		
		$inf_path = QAutoload::GetRuntimeFolder()."temp/traits_included.php";
		if (file_exists($inf_path))
		{
			include($inf_path);
			self::$TraitIncludeInfo = $_Q_TraitIncludeInfo;
		}
		else
			self::$TraitIncludeInfo = array();
	}
	
	public static function SaveTraitWasIncluded()
	{
		// there was no request
		if (self::$TraitIncludeInfo === null)
			return;
		
		$inf_path = QAutoload::GetRuntimeFolder()."temp/traits_included.php";
		// traits_included.php
		qArrayToCodeFile(self::$TraitIncludeInfo, "_Q_TraitIncludeInfo", $inf_path);
	}
	
	public static function GetTraitsWasIncluded()
	{
		if (self::$TraitIncludeInfo === null)
			self::LoadTraitWasIncluded();
		
		return self::$TraitIncludeInfo;
	}
	
	public function getNamespaceForPath($path)
	{
		$dir = dirname($path)."/";
		$class = pathinfo($path, PATHINFO_BASENAME);
		$class = (($p = strpos($class, ".")) !== false) ? substr($class, 0, $p) : $class;
		$sync_node = $this->pathToElement[$dir.$class.".php"];
		return $sync_node->namespace;
	}

	public static function GetFixValStr($cleanup_rule, $for_array = null, $value_replacement = null)
	{
		$cleanups = self::GetPreparedDataFixers();
		$rules = self::ParseValidationRules($cleanup_rule);
		return self::GetRuleStr($cleanups, $rules, $value_replacement);
	}

	public static function GetEncodeValStr($encode_rule, $for_array = null, $value_replacement = null)
	{
		$cleanups = self::GetPreparedDataEncoders();
		$rules = self::ParseValidationRules($encode_rule);
		return self::GetRuleStr($cleanups, $rules, $value_replacement);
	}

	public static function GetValidationStr($validation_rule, $for_array = null, $value_replacement = null)
	{
		$validators = self::GetPreparedDataValidators();
		$rules = self::ParseValidationRules($validation_rule);
		return self::GetRuleStr($validators, $rules, $value_replacement, true);
	}

	public static function GetValidationData($validation_rule, $prop_name = null)
	{
		$validators = self::GetPreparedDataValidators();
		$rules = self::ParseValidationRules($validation_rule);

		list($rulesByTag, $rulesParams) = static::GetRulesData($rules, $validators);

		$alert = "";
		$info = "";
		$alert_pos = 0;
		$info_pos = 0;
		if ($validators && (count($validators) > 0))
		{
			foreach ($validators as $validatorData)
			{
				list($v_token, $v_data, $v_params, $v_alert, $v_info) = $validatorData;
				$validator_tag = reset($v_token)[1];

				if (!$validator_tag)
					continue;

				if (isset($rulesByTag[$validator_tag]))
				{
					if ($v_alert)
					{
						$_to_replace = $v_params && (count($v_params) > 0) ? array_values($v_params) : [];
						$_to_replace_with = $rulesParams[$validator_tag];
						if (!$_to_replace)
							$_to_replace = [];
						if (!$_to_replace_with)
							$_to_replace_with = [];
						
						if ($prop_name)
						{
							$_to_replace[] = "\$property";
							$_to_replace_with[] = $prop_name;
						}

						$v_alert = str_replace($_to_replace, $_to_replace_with, $v_alert);
						$v_info = str_replace($_to_replace, $_to_replace_with, $v_info);

						$alert .= "<span class=\"qc-validation-alert\" data-tag=\"{$validator_tag}\">{$v_alert}</span>";
						$alert_pos++;
					}

					if ($v_info)
					{
						$info .= (($info_pos > 0) ? "<br/>" : "") . $v_info;
						$info_pos++;
					}
				}
			}
		}
		return [$alert, $info];
	}
	
	public static function GetRulesData($rules, $validators)
	{
		if (!$rules || (count($rules) === 0))
			return [[], []];

		$currentRule = null;
		$prevWasRule = false;
		$in_rule_method = false;
		$rulesParams = [];
		$rulesByTag = [];
		foreach ($rules as $ruleData)
		{
			if (is_string($ruleData))
			{
				if ($prevWasRule && ($ruleData === "("))
					$in_rule_method = true;
				else if ($in_rule_method && ($ruleData === ")"))
					$in_rule_method = false;
			}

			if (!is_array($ruleData) || ($ruleData[0] === T_WHITESPACE) || ($ruleData[0] === T_BOOLEAN_AND) || ($ruleData[0] === T_BOOLEAN_OR))
				continue;

			$ruleVal = $ruleData[1];
			$is_rule = (($ruleData[0] === T_STRING) && isset($validators[strtolower($ruleVal)]));

			if ($in_rule_method)
			{
				if (!$currentRule)
					throw new \Exception("no current rule found!");
				if (!isset($rulesParams[$currentRule]))
					$rulesParams[$currentRule] = [];
				$rulesParams[$currentRule][] = $ruleVal;		
			}

			if ($is_rule)
			{
				$rulesByTag[$ruleVal] = $ruleVal;
				$currentRule = $ruleVal;
			}
			$prevWasRule = $is_rule;
		}
		return[$rulesByTag, $rulesParams];
	}

	protected static function GetRuleStr($parsed_rules, $current_rule, $value_replacement = null, $is_validation = false)
	{
		// var_dump($parsed_rules, $current_rule);
		$rule = "";
		$cr = current($current_rule);
		$has_mandatory = false;
	
		while ($cr)
		{
			$is_arr = is_array($cr);
			$txt = $is_arr ? $cr[1] : $cr;
			$txt_lc = strtolower($txt);
			if ($is_arr && ($cr[0] === T_STRING) && (($parsed_r = $parsed_rules[$txt_lc])))
			{
				if ($txt_lc === "mandatory")
					$has_mandatory = true;
				$rule .= "(";
				// we have a match, try to replace 
				// var_dump($parsed_r);

				list($parsed_r_def, $parsed_r_code, $parsed_r_vars /*, $error_message */) = $parsed_r;

				if ($parsed_r_vars)
				{
					$parsed_r_vars_by_index = array_values($parsed_r_vars);
					// 1. determine the values for the variables
					// 2. replace varibales in code
					// 3. replace entire block
					$values = [];
					$val_pos = 0;
					$var_txt = "";
					$bra_depth = 0;
					
					// T_LNUMBER || T_CONSTANT_ENCAPSED_STRING | T_DNUMBER | T_STRING && false true
					while (($cr = next($current_rule)))
					{
						$cr_isarr = is_array($cr);
						// $cr_type = $cr_isarr ? $cr[0] : null;
						$cr_txt = $cr_isarr ? $cr[1] : $cr;
						
						/*
						if (($cr_type === T_LNUMBER) || ($cr_type === T_CONSTANT_ENCAPSED_STRING) || ($cr_type === T_DNUMBER) || 
								(($cr_type === T_STRING) && ((strtolower($cr_txt) === "true") || (strtolower($cr_txt) === "false"))))
						{
							$values[$parsed_r_vars_by_index[$val_pos]] = $cr_txt;
							$val_pos++;
						}
						*/
						if ($cr_txt === "(")
						{
							if ($bra_depth > 0)
								$var_txt .= $cr_txt;
							$bra_depth++;
						}
						else if ($cr_txt === ")")
						{
							$bra_depth--;
							if ($bra_depth === 0)
								break;
							else
								$var_txt .= $cr_txt;
						}
						else if (($bra_depth === 1) && ($cr_txt === ","))
						{
							$values[$parsed_r_vars_by_index[$val_pos]] = $var_txt;
							$val_pos++;
							$var_txt = "";
						}
						else
						{
							$var_txt .= $cr_txt;
						}
					}
					
					if (strlen(trim($var_txt)) > 0)
					{
						$values[$parsed_r_vars_by_index[$val_pos]] = $var_txt;
						$val_pos++;
						$var_txt = "";
					}
					
					foreach ($parsed_r_code as $pr)
					{
						$pr_isarr = is_array($pr);
						$pr_type = $pr_isarr ? $pr[0] : null;
						$pr_txt = is_array($pr) ? $pr[1] : $pr;
						if (($pr_type === T_VARIABLE) && (($replacement = $values[$pr_txt]) !== null))
							$rule .= $replacement;
						else if ($value_replacement && ($pr_type === T_VARIABLE) && ($pr_txt === "\$value"))
							$rule .= $value_replacement;
						else
							$rule .= $pr_txt;
					}
				}
				else
				{
					foreach ($parsed_r_code as $pr)
					{
						$sub_isarr = is_array($pr);
						$sub_txt = $sub_isarr ? $pr[1] : $pr;
						if ($value_replacement && $sub_isarr && ($pr[0] === T_VARIABLE) && ($sub_txt === "\$value"))
							$rule .= $value_replacement;
						else
							$rule .= $sub_txt;
					}
				}
				$rule .= ")";
			}
			else if ($value_replacement && $is_arr && ($cr[0] === T_VARIABLE) && ($txt === "\$value"))
			{
				$rule .= $value_replacement;
			}
			else
			{
				$rule .= $txt;
			}
			$cr = next($current_rule);
		}

		if ($is_validation && (!$has_mandatory))
		{
			// loose rule, accept empty
			$val_str = $value_replacement ?: "\$value";
			$rule = "(({$val_str} === null) || ({$val_str} === \"\") || {$rule})";
		}
		return $rule;
	}

	public static function ParseValidationRules($rule)
	{
		$toks = token_get_all("<?php ".trim($rule));
		array_shift($toks);
		return $toks;
	}
	
	public static function GetPreparedDataFixers()
	{
		if (self::$PreparedFixers !== null)
			return self::$PreparedFixers;
		
		return self::$PreparedFixers = self::GetPreparedData(QAutoload::GetDataFixers());
	}
	
	public static function GetPreparedDataEncoders()
	{
		if (self::$PreparedEncoders !== null)
			return self::$PreparedEncoders;
		return self::$PreparedEncoders = self::GetPreparedData(QAutoload::GetDataEncoders());
	}
	
	protected static function GetPreparedData($fixers)
	{
		$ret = [];
		foreach ($fixers as $k => $_v)
		{
			$key_parsed = self::ParseValidationRules($k);
			$index = null;
			$vars = [];
			
			$v = is_array($_v) ? reset($_v) : $_v;
			$error_message = is_array($_v) ? next($_v) : null;
			$info_message = is_array($_v) ? next($_v) : null;

			foreach ($key_parsed as $kp)
			{
				if (is_array($kp))
				{
					if ((!$index) && ($kp[0] === T_STRING))
						$index = strtolower($kp[1]);
					else if ($kp[0] === T_VARIABLE)
						$vars[$kp[1]] = $kp[1];
				}
			}
			if ($index)
				$ret[$index] = [$key_parsed, self::ParseValidationRules($v), $vars, $error_message, $info_message];
		}
		return $ret;
	}
	
	public static function GetPreparedDataValidators()
	{
		if (self::$PreparedValidators !== null)
			return self::$PreparedValidators;
		
		return self::$PreparedValidators = self::GetPreparedData(QAutoload::GetDataValidators());
	}
	
	public static function EmptyDir($dir)
	{
		if (!is_dir($dir))
			return;
		
		$path = rtrim($dir, "\\/")."/";
		$items = scandir($path);
		foreach ($items as $item)
		{
			if (($item === ".") || ($item === ".."))
				continue;
			$fp = $path.$item;
			if (is_file($fp))
				unlink($fp);
		}
	}
	
	protected function generateApiMethodFromData($name, $meth_data)
	{
		// create fixers & filters, then validators foreach param and foreach return
		$params = $meth_data["params"];
		$return = $meth_data["return"];
		$inner_body = $meth_data["body"];
		$full_comment = $meth_data["full_comment"];

		// $generated_start = "// Generated API Code for: {$name}\n";
		// $generated_end = "// END Generated API Code for: {$name}\n";

		$body = "";

		$use_called_class = false;
		// you may cancel the creation of the $_called_class_ variable with "defineCalledClass" => false
		/*if ($meth_data["static"] && ($meth_data["defineCalledClass"] !== false))
		{
			// we no longer use $_called_class_ !!!
			// $use_called_class = "\$_called_class_ = get_called_class();\n";
			// $body .= $use_called_class = "\$_called_class_ = get_called_class();\n";
		}*/

		// type, fixers, validators
		if ($params)
		{
			// 1. filter input (fixers)
			foreach ($params as $p_name => $param_data)
			{
				if (($fixer = $param_data["filter"]))
				{
					/*if (empty($body))
						$body = $generated_start;*/
					$body .= "\${$p_name} = ".QCodeSync::GetFixValStr($fixer, null, "\$".$p_name).";\n";
				}
			}
			// 2. validate input (validators)
			foreach ($params as $p_name => $param_data)
			{
				if (($validator = $param_data["validator"]))
				{
					/*if (empty($body))
						$body = $generated_start;*/
					$body .= "if (!(".QCodeSync::GetValidationStr($validator, null, "\$".$p_name)."))\n".
									"\tthrow new \Exception(\"Invalid input parameter {$p_name}\");\n";
				}
			}
		}

		/*if (!empty($body))
			$body .= $generated_end;*/

		// 3. call $inner_body
		// @todo - replace return with \$_return_ = 
		//					if non empty return
		//					here it's a bit delicate, wrap it in a lambada ?
		$args = $params ? "\$".implode(", \$", array_keys($params)) : "";
		$args_by_ref = $params ? "&\$".implode(", &\$", array_keys($params)) : "";
		
		$body_out = "";
		
		/*
		if ($return)
			$body .= "\$_return_ = ";
		else
			$body .= "// no return was defined for the method\n";
		*/
		// $body .= "\$_callback_({$args}".($use_called_class ? ($args ? ", " : "")."\$_called_class_" : "").");\n";// $inner_body."\n";
		if ($return)
		{
			//$body .= $generated_start;
			// 4. filter return
			if (($return_fixer = $return["filter"]))
			{
				$body_out .= "\$_return_ = ".QCodeSync::GetFixValStr($return_fixer, null, "\$_return_").";\n";
			}
			// 5. validate return (validators)
			if (($return_validator = $return["validator"]))
			{
				$body_out .= "if (!(".QCodeSync::GetValidationStr($return_validator, null, "\$_return_")."))\n".
									"\tthrow new \Exception(\"Invalid return\");\n";
			}
			// $body .= "return \$_return_;\n";
			if (!($return_fixer || $return_validator))
				$return = false;
		}
		
		/*$class_meth = "public ".($meth_data["static"] ? "static " : "")."function {$name}({$args})\n".
				"{\n".
				($use_called_class ? "\t".$use_called_class : "").
				"\t".($return ? "return " : "// no return was defined for the method\n\t").($use_called_class ? "static::" : "\$this->")."{$name}_wrap_({$args}".($args ? ", " : "")."function ({$args}".($use_called_class ? (($args ? ", " : "")."\$_called_class_") : "").")\n".
				"\t{\n".
					"\t\t// place the code for ::{$name}() here\n".
					"\t\t".$inner_body."\n".
				"\t}".($use_called_class ? ", \$_called_class_" : "").");\n".
				"}\n\n";
				
		$class_meth = "\t".trim($full_comment)."\n\t".str_replace("\n", "\n\t", rtrim($class_meth))."\n";*/
		$gen_meth = null;
		if (!empty(trim($body)))
		{
			$gen_meth = "protected ".($meth_data["static"] ? "static " : "")."function {$name}_in_({$args_by_ref}".
										($use_called_class ? ($args_by_ref ? ", " : "")."\$_called_class_" : "").")\n{\n";
			$gen_meth .= "\t".str_replace("\n", "\n\t", rtrim($body))."\n}\n\n";

			// "\t".trim($full_comment)."\n\t".
			$gen_meth = str_replace("\n", "\n\t", rtrim($gen_meth))."\n";
		}
		
		$gen_meth_out = null;
		if ($return && (!empty(trim($body_out))))
		{
			$gen_meth_out = "protected ".($meth_data["static"] ? "static " : "")."function {$name}_out_(&\$_return_".($args_by_ref ? ", " : "")."{$args_by_ref}".
										($use_called_class ? ($args_by_ref ? ", " : "")."\$_called_class_" : "").")\n{\n";
			$gen_meth_out .= "\t".str_replace("\n", "\n\t", rtrim($body_out))."\n}\n\n";

			// "\t".trim($full_comment)."\n\t".
			$gen_meth_out = str_replace("\n", "\n\t", rtrim($gen_meth_out))."\n";
		}
		
		// var_dump($gen_meth, $gen_meth_out);
		
		return [$gen_meth, $gen_meth_out];
	}
	/*
	protected function generateSecurityMethods(QCodeSyncNode $element, QPHPToken $source_tokens)
	{
		$final_tokens = $element->getFinalTokens();
		$final_class = $final_tokens->findPHPClass();
		
		$security_class_str = \QSecurityGenerator::GenerateFromTokens($element->className);
		
		if ($security_class_str)
		{
			$parsed_gen_meths = QPHPToken::ParsePHPString($security_class_str, false, false);	
			$final_class->mergeMethods($parsed_gen_meths->findPHPClass()->methods, true);
			$element->requiresCompiled = true;
		}
	}*/
	
	public static function GetVarsFromArgs($args_str)
	{
		if (!$args_str)
			return $args_str;
		$matches = null;
		$r = preg_match_all("/\\$[\\w+_0-9]+/us", $args_str, $matches);
		if ($r && $matches[0])
		{
			return implode(",", $matches[0]);
		}
		else
			return $args_str;
	}
	
	public function getJsParentClass($class, $classParents)
	{
		// qvar_dump($classParents, $class);
		$parent_class = $this->data["_"][$class]->readParent($classParents);
		$parent = $parent_class ? $this->data["_"][$parent_class] : null;
		
		while ($parent)
		{
			$node = $parent->isVirtual() ? $this->classesMap[$parent->className]["php"][""]->node : $parent;
			$js_path = substr($node->classPath, 0, -3)."js";
			if (file_exists($js_path))
				return $node->className;
			
			$parent_class = $parent->readParent($classParents);
			$parent = $parent_class ? $this->data["_"][$parent_class] : null;
		}
		return null;
	}
	
	public function DebugGenerate(\QGeneratePatchInfo $gen_info, \QPHPToken $render_code)
	{
		if (!$this->debugMode)
			return;
		
		$rc_root = $render_code->getRoot();
		
		echo '<div style="padding-left: 20px; color: red;">';
		echo "Begin generate: ";
		if ($rc_root && ($rc_root instanceof \QPHPTokenFile))
			echo htmlspecialchars($rc_root->filename);
		else
		{
			$tok = new QPHPTokenXmlElement();
			$rc_str = $tok->toString($render_code);
			echo "<input type='text' value='".htmlentities(substr($rc_str, 0, 256)." [...]").'\' />';
		}
		echo "</div>\n";
	}
	
	/*
	public static function EnsureTplIsGenerated(QCodeSyncItem $sync_item)
	{
		// $sync_item = $element->data["tpl"][$tag];
		$tpl_parent = $sync_item->patch;
		if ($tpl_parent && ($q_start_xml = $tpl_parent->node->getReadOnlyTokens($tpl_parent->mode, $sync_item->tag)) && ($q_matches = $q_start_xml->children(".QPHPTokenXmlElement")))
		{
			$generated = true;
			foreach ($q_matches as $qm)
			{
				if (!$qm->_generated)
				{
					$generated = false;
					break;
				}
			}
			
			if (!$generated)
			{
				$element = $tpl_parent->node;
				$tag = $tpl_parent->tag;
				
				$gen_info = new QGeneratePatchInfo($element->getTemplatePath($tag));
				if (isset($tpl_parent->patch))
				{
					$gen_info->__tpl_parent = $tpl_parent->patch;
					$gen_info->__tpl_mode = $tpl_parent->mode;
					$gen_info->__tpl_tag = $tpl_parent->tag;

					static::EnsureTplIsGenerated($tpl_parent);
				}
				
				$render_code = $tpl_parent->node->getReadOnlyTokens($tpl_parent->mode, $tpl_parent->tag);
				
				$full_path = $element->getTemplatePath($tag);
				echo "<div style='color: red;'>ensureTplIsGenerated :: GENERATE: {$full_path}</div>\n";
				$render_code->generate($gen_info);
			}
		}
	}*/

	public function benchmark(string $method, float $start_time, array $args = null)
	{
		$end_t = microtime(true);
		static::$_benchmark[$method]['count'] += 1;
		static::$_benchmark[$method]['time'] += ($end_t - $start_time);
	}
	
}
