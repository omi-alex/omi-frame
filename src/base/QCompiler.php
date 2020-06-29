<?php

class QCompiler
{
	/**
	 * The list of classes to be compiled, if null all the classes will be compiled
	 *
	 * @var string[]
	 */
	public $classes;
	/**
	 * If true will also check for removed files and process changes 
	 *
	 * @var boolean
	 */
	public $cleanupRemoved;
	/**
	 * Number of items listed
	 *
	 * @var integer
	 */
	public $itemsCount = 0;
	/**
	 * Number of items taken in consideration
	 *
	 * @var integer
	 */
	public $itemsScaned = 0;
	/**
	 * The list of modules to be considered
	 * 
	 * @var string[]
	 */
	public $modules;
	/**
	 * The modules keys to be processed in order
	 *
	 * @var string[]
	 */
	public $modulesKeys;
	/**
	 * The current module
	 *
	 * @var string
	 */
	public $currentModuleKey;
	/**
	 * The path where the metadata is stored
	 * 
	 * @var string
	 */
	public $metaInfoPath;
	/**
	 * In case we compile only for some classes, we will setup a filter based on the list of classes
	 *
	 * @var string[][]
	 */
	public $classesFilter;
	/**
	 * Metadata collected
	 *
	 * @var array
	 */
	public $meta;
	/**
	 * Number of classes that are to be compiled. If all we set this to true
	 * 
	 * @var integer|boolean
	 */
	public $remainingClasses;
	/**
	 * Caching on disk meta inf to avoid multiple includes
	 *
	 * @var array
	 */
	private $onDiskMetaCache = [];
	/**
	 * Metadata that needs to be compiled
	 *
	 * @var array
	 */
	private $toProcess = [];
	/**
	 * Cache of classes info
	 *
	 * @var string[][]
	 */
	private $classesInfo = [];
	/**
	 * Unresolved dependencies
	 *
	 * @var string[]
	 */
	private $pendingDependencies = [];
	
	/**
	 * Init the compiler instance parameters
	 */
	public function init()
	{
		// get last sync time
		$this->modules = QAutoload::GetWatchFoldersByTags();
		$this->modulesKeys = array_reverse(array_keys($this->modules));
		$this->currentModuleKey = reset($this->modulesKeys);
		
		$this->metaInfoPath = QAutoload::GetRuntimeFolder()."temp/meta/";
		if (!is_dir($this->metaInfoPath))
			qmkdir($this->metaInfoPath);
		
		// optimize filters
		if ($this->classes)
		{
			$this->classesFilter = [];
			foreach ($this->classes as $full_class)
			{
				list($sh_class, $namespace) = qClassShortAndNamespace($full_class);
				$this->classesFilter[$sh_class][$namespace ?: 0] = $full_class;
			}
		}
		
		// Number of classes that are to be compiled. If all we set this to true
		$this->remainingClasses = $this->classes ? count($this->classes) : true;
	}
	
	/**
	 * Run the compiler
	 */
	public function run()
	{
		// LOOP all MODULES
		while ($this->remainingClasses && $this->currentModuleKey)
		{
			$this->scanCurrentModule();
			
			// @todo: handle pending dependencies
			
			$process_in_module = $this->toProcess[$this->currentModuleKey] ?: [];
			foreach ($process_in_module as $meta_key => $c_metadata)
			{
				foreach ($c_metadata as $key => $f_meta)
				{
					// extends, implements, traits
					foreach ($f_meta as $subkey => $meta)
						$this->handleDependencies($this->currentModuleKey, $meta_key, $key, $subkey, $meta);
				}
			}
			
			if ($process_in_module)
				var_dump($process_in_module);
			
			$this->currentModuleKey = next($this->modulesKeys);
		}
		
		foreach ($this->toProcess as $mod_tag => $c_mod_meta)
		{
			foreach ($c_mod_meta as $meta_key => $c_metadata)
			{
				// @TODO : process it
				
				// SAVE metadata
				$mod_path = $this->modules[$mod_tag];
				$mod_meta_path = $this->metaInfoPath.$mod_tag."/";
				$c_metadata_path = $mod_meta_path.substr($meta_key, strlen($mod_path)).".php";
				
				$touch_mt = $c_metadata["_maxmt"];

				if (!is_dir(dirname($c_metadata_path)))
					qmkdir(dirname($c_metadata_path), true);
				qArrayToCodeFile($c_metadata, "_Q_METADATA", $c_metadata_path);
				// stamp the last modified date
				touch($c_metadata_path, $touch_mt);
			}
		}
	}
	
	/**
	 * Scans the current module
	 */
	public function scanCurrentModule()
	{
		$mod_path = $this->modules[$this->currentModuleKey];
		$mod_tag = $this->currentModuleKey;
		
		$mp_len = strlen($mod_path);
		$c_mod_meta = [];

		$classes_filter = $this->classesFilter;
		$items_count = $this->itemsCount;
		
		$rec_iter = new RecursiveDirectoryIterator($mod_path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		$filter = new QFileFilter($rec_iter, function (QFileFilter $file_obj) use ($classes_filter, $mp_len, &$items_count)

				{
					$items_count++;
					/** @var SplFileInfo $file_inf */
					$file_inf = $file_obj->current();
					// var_dump($file_inf->getPathname());

					if ($file_inf->isFile())
					{
						$ext = $file_inf->getExtension();
						$fn = $file_inf->getBasename(".".$ext);
						// in case we have a class filter
						if ($classes_filter)
						{
							$short = (($dot_pos = strpos($fn, ".")) !== false) ? substr($fn, 0, $dot_pos) : $fn;
							if (!$classes_filter[$short])
								return false;
						}
						
						// avoid .min.js & .min.css & .dyn.php
						if (((($ext === "js") || ($ext === "css")) && (substr($fn, -4) === ".min")) || (($ext === "php") && (substr($fn, -4) === ".dyn")))
							return false;

						return ($fn{0} === strtoupper($fn{0})) && ((($ext === "php") && (substr($fn, -4) !== ".gen") && (substr($fn, -4) !== ".dyn")) || ($ext === "tpl") || ($ext === "js") || ($ext === "css"));
					}
					else
					{
						$fp = $file_inf->getRealPath();
						$bn = $file_inf->getBasename();
						$r_path = substr($fp, $mp_len);
						// var_dump($bn);
						return (($r_path{0} !== "~") && ($bn !== "temp"));
					}
				});

		$iterator = new RecursiveIteratorIterator($filter);

		$mod_meta_path = $this->metaInfoPath.$mod_tag."/";
		if (!is_dir($mod_meta_path))
			qmkdir($mod_meta_path);

		// FOREACH FILE IN THE MODULE (filters may apply) setup metadata
		foreach ($iterator as $file_path => $file_obj)
		{
			if (!$file_obj->isFile())
				continue;

			$ext = $file_obj->getExtension();
			if ((!$ext) || (!(($ext === "php") || ($ext === "tpl"))))
				continue;
			
			$bn = $file_obj->getBasename(".".$ext);
			$sub_elem = (($dot_pos = strrpos($bn, ".")) !== false) ? substr($bn, $dot_pos + 1) : null;

			$meta_key = substr($file_path, 0, strlen($file_path) - strlen($ext) - 1 - ($sub_elem ? (strlen($sub_elem) + 1) : 0));
			$c_metadata_path = $mod_meta_path.substr($meta_key, strlen($mod_path)).".php";
			$mt = $file_obj->getMTime();
			
			// @TODO - apply partial filtering based on file name (except namespace as we don't know it before we look inside)
			
			if ($ext === "php")
				$c_mod_meta[$meta_key][$sub_elem ?: $ext][""] = ["mt" => $mt, "path" => $file_obj->getRealPath()];
			else 
				$c_mod_meta[$meta_key][$ext][$sub_elem ?: ""] = ["mt" => $mt, "path" => $file_obj->getRealPath()];
			
			// set or update max time
			$max_mt = $c_mod_meta[$meta_key]["_maxmt"];
			if ((!$max_mt) || ($mt > $max_mt))
				$c_mod_meta[$meta_key]["_maxmt"] = $mt;
		}
		
		// SAVE & PROCESS METADATA
		foreach ($c_mod_meta as $meta_key => $c_metadata)
		{
			$c_metadata_path = $mod_meta_path.substr($meta_key, strlen($mod_path)).".php";
			
			$touch_mt = $c_metadata["_maxmt"];
			$cmp_mt = file_exists($c_metadata_path) ? filemtime($c_metadata_path) : 0;

			if ($touch_mt > $cmp_mt)
				// handle a change
				$this->handleChange($mod_tag, $meta_key, $c_metadata_path, $c_metadata);
		}
		
		// then resolve dependencies // (dependencies one way - backward)
		$this->meta[$mod_tag] = $c_mod_meta;
		
		// we also need some metadata per module, identify what files have changed ... and more
		$this->itemsCount = $items_count;
	}
	
	/**
	 * Reads meta data either from in memory cache, disk cache or parses the file
	 * 
	 * @param array $meta The meta info to be populated
	 * @param string $tag The tag (ex: php, patch, tpl, url)
	 * @param string $sub_tag Sub tag only for templates (.tpl)
	 * @param string $meta_key The meta key of the element
	 * @param string $mod_tag The module tag/key
	 * @param integer $mt The modified date of the file to be checked / updated
	 */
	protected function readMetaData(&$meta, $tag, $sub_tag, $meta_key, $mod_tag, $mt = null)
	{
		if ($mt === null)
			$mt = filemtime($meta["path"]);
		
		$mod_path = $this->modules[$mod_tag];
		$mod_meta_path = $this->metaInfoPath.$mod_tag."/";
		$c_metadata_path = $mod_meta_path.substr($meta_key, strlen($mod_path)).".php";
		
		if (file_exists($c_metadata_path) && (filemtime($c_metadata_path) >= $mt))
		{
			if (($_Q_METADATA = $this->onDiskMetaCache[$meta_key]) === null)
			{
				include($c_metadata_path);
				$this->onDiskMetaCache[$meta_key] = $_Q_METADATA;
			}
			
			$disk_meta = $_Q_METADATA[$tag][$sub_tag];
			$meta["class"] = $disk_meta["class"];
			$meta["extends"] = $disk_meta["extends"];
			$meta["implements"] = $disk_meta["implements"];
			$meta["traits"] = $disk_meta["traits"];
		}
		else
		{
			$file_path = $meta["path"];
			
			$meta = QPHPToken::ParseHeaderOnly($file_path, true);
			$this->itemsScaned++;

			$meta["mt"] = $mt;
			$meta["path"] = $file_path;
		}
		
		if ($meta["class"])
		{
			$this->classesInfo[$meta["class"]][$mod_tag] = $meta_key;
		}
	}
	
	protected function handleChange($mod_tag, $meta_key, $c_metadata_path, &$c_metadata)
	{
		// this key was already flagged for processing
		if ($this->toProcess[$mod_tag][$meta_key])
			return;
		
		$old_meta = null;
		if (file_exists($c_metadata_path))
		{
			$_Q_METADATA = null;
			include($c_metadata_path);
			$old_meta = $_Q_METADATA;
		}
		
		// order of processing: php|patch, url, tpl (we are lucky and they are in alpha order)
		ksort($c_metadata);
		// DETECT WHAT FILES HAVE CHANGED
		foreach ($c_metadata as $k => &$md)
		{
			if ($k{0} === "_")
				continue;
			$old_meta_md = $old_meta ? $old_meta[$k] : null;
		
			foreach ($md as $subkey => &$meta)
			{
				$old_meta_data = $old_meta_md ? $old_meta_md[$subkey] : null;
				
				$new_mt = $meta["mt"];
				$old_mt = $old_meta_data ? $old_meta_data["mt"] : null;
				
				if ($new_mt !== $old_mt)
				{
					// has changed
					if (!$meta["class"])
						$this->readMetaData($meta, $k, $subkey, $meta_key, $mod_tag, $new_mt);
					
					if (!$this->toProcess[$mod_tag][$meta_key])
						$this->toProcess[$mod_tag][$meta_key] = [];
					
					$this->toProcess[$mod_tag][$meta_key][$k][$subkey] = $meta;
				}
			}
		}
	}
	
	protected function handleDependencies($mod_tag, $meta_key, $mode, $submode, $meta)
	{
		// make sure extends,implements,traits are in sync
		// @todo
		$check = ["extends", "implements", "traits"];
		
		foreach ($check as $check_type)
		{
			$check_classes = $meta[$check_type];
			if (!$check_classes)
				continue;
			else if (is_string($check_classes))
				$check_classes = [$check_classes];
			
			foreach ($check_classes as $check_class)
			{
				// avoid the generated trait
				if (substr($check_class, -strlen("_GenTrait")) === "_GenTrait")
					continue;
				
				if (($class_info = $this->classesInfo[$check_class]))
				{
					// get the most recent one as this one will be run
					$class_info = reset($class_info);
					var_dump("ext info", $class_info);
					die();
					// $this->handleChange($mod_tag, $meta_key, $c_metadata_path, $c_metadata)
				}
				else
				{
					// it was not processed yet
					$this->pendingDependencies[$check_class] = true;
				}
			}
		}
		
		// handle patching
		$this->getPatchingElement($mod_tag, $meta_key, $mode, $submode, $meta);
	}
	
	protected function getPatchingElement($mod_tag, $meta_key, $mode, $submode, $meta)
	{
		// handle patching
		if (($mode === "tpl") || ($mode === "url"))
		{
			$extends = $meta["extends"];
			$found_meta_data = $this->findClassInModule($extends, $this->meta[$mod_tag], $mod_tag);
			// parent in this WatchFolder
			if ($found_meta_data)
			{
				var_dump("parent in this WatchFolder", $found_meta_data);
				die();
			}
			else
			{
				// clone in a previous WatchFolder (this is a ... if there is ...)
				// parent in a previous WatchFolder (this is a ... if there is ...)
				var_dump("clone in a previous WatchFolder | parent in a previous WatchFolder");
				die();
			}
		}
		else if ($mode === "patch")
		{
			// clone in a previous WatchFolder (this is a ... if there is ...)
			var_dump("patch : clone in a previous WatchFolder");
			die();
		}
	}
	
	protected function findClassInModule($class, $mod_data, $mod_tag)
	{
		list($class_short/*, $namespace*/) = qClassShortAndNamespace($class);
		
		foreach ($mod_data as $meta_key => &$m_data)
		{
			$b_name = trim(basename($meta_key));
			// var_dump($this->getUseMeta($m_data, $meta_key, $mod_tag));
			if ($class_short === $b_name)
			{
				// may be it but we need to check it in full
				$use_meta = $this->getUseMeta($m_data, $meta_key, $mod_tag);
				if ($use_meta && ($use_meta["class"] === $class))
				{
					return [$use_meta, $meta_key, $mod_tag, $m_data];
				}
			}
		}
		return null;
	}
	
	protected function getUseMeta(&$meta_data, $meta_key, $mod_tag)
	{
		// first make sure it is populated
		foreach ($meta_data as $tag => &$v)
		{
			if ($tag{0} === "_")
				continue;
			
			foreach ($v as $sub_tag => &$meta)
			{
				if (!$meta["class"])
					$this->readMetaData($meta, $tag, $sub_tag, $meta_key, $mod_tag, null);
			}
		}
		
		$c_use_meta = $meta_data["php"][""] ?: $meta_data["patch"][""] ?: ($meta_data["url"][""]["namespace"] ? $meta_data["url"][""] : ($c_use_meta["tpl"][""]["namespace"] ? $c_use_meta["tpl"][""] : null));
		if ($c_use_meta === null)
		{
			if ($meta_data["tpl"])
			{
				foreach ($meta_data["tpl"] as $meta)
				{
					if ($meta["namespace"])
					{
						$c_use_meta = $meta;
						break;
					}
				}
			}
			if ($c_use_meta === null)
			{
				if ($meta_data["url"][""])
					$c_use_meta = $meta_data["url"][""];
				else 
					$c_use_meta = ($meta_data["tpl"][""] ?: reset($meta_data["tpl"]));
			}
		}
		
		var_dump($c_use_meta);
		die();
		
		return $c_use_meta;
	}

	public static function xHandleDependencies($mode, $submode, $meta, $old_meta_data, $classes_filter, $full_mod_meta, $last_mod_tag, $c_metadata_path, $mod_tags_order, $c_use_meta)
	{
		var_dump("HandleDependencies::{$mode}, {$submode}", $meta);
		
		// make sure: extends, implements, traits are up to date
		
		if (($mode === "tpl") || ($mode === "url"))
		{
			/* THE PRIORITY IS: 
					parent in this WatchFolder
					clone in a previous WatchFolder
					parent in a previous WatchFolder */
		}
		else if ($mode === "patch")
		{
			// clone in a previous WatchFolder
		}
		/*
		if (($mode === "tpl") || ($mode === "event") || ($mode === "url"))
		// parent in this WatchFolder
		// clone in a previous WatchFolder
		// parent in a previous WatchFolder
		else if ($mode === "patch")
		// clone in a previous WatchFolder
		 */
	}
	
	/**
	 * Run the compiler
	 * 
	 * @param boolean $classes If null all classes will be processed, you can provide one class or a list of classes to be processed
	 * @param boolean $cleanup_removed If true it will detect remove files and will cleanup
	 * @return \QCompiler
	 */
	public static function Compile($classes = null, $cleanup_removed = true)
	{
		$compiler = new static();
		$compiler->classes = is_string($classes) ? [$classes] : $classes;
		$compiler->cleanupRemoved = $cleanup_removed;
	
		$compiler->init();
		$compiler->run();
		
		return $compiler;
	}
}
