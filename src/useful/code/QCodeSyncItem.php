<?php

class QCodeSyncItem 
{
	/**
	 * @var string[]
	 */
	public static $PathToNamespace = array();
	/**
	 *
	 * @var string[]
	 */
	public static $PathToExtends = array();
	/**
	 *
	 * @var string[]
	 */
	public static $PathToImplements = array();
	/**
	 *
	 * @var string[]
	 */
	public static $PathToTraits = array();
	
	public static $ExtractCache = array();
	/**
	 *
	 * @var QCodeSyncNode
	 */
	public $node;
	/**
	 *
	 * @var string
	 */
	public $mode;
	/**
	 *
	 * @var string
	 */
	public $class;
	/**
	 *
	 * @var boolean
	 */
	public $changed;
	/**
	 *
	 * @var string
	 */
	public $path;
	/**
	 *
	 * @var integer
	 */
	public $modif_date;
	/**
	 * The element to be patched
	 * 
	 * @var QCodeSyncItem
	 */
	public $patch;
	/**
	 * True if the parents of the patch need to be setup in the '_patches' folder
	 *
	 * @var boolean
	 */
	public $patch_parents;
	/**
	 *
	 * @var The tag of the template, ex: QWebGrid.add.tpl
	 */
	public $tag;
	
	public function __construct(QCodeSyncNode $node, $mode, $class, $changed, $path, $modif_date, $tag = "")
	{
		$this->node = $node;
		$this->mode = $mode;
		$this->class = $class;
		$this->changed = $changed;
		$this->path = $path;
		$this->modif_date = $modif_date;
		$this->tag = $tag ?: "";
	 }
	
	public function readFile()
	{
		// $this->parsed_data = QPHPToken::ParsePHPFile($this->node->watchFolder.$this->path);
	}
	
	public static function ExtractInfo($path, $watch_folder, $index_by_path)
	{
		// cache path => namespace
		$p_slash = strrpos($path, "/");
		$filename = ($p_slash === false) ? $path : substr($path, $p_slash + 1);
		$p_dot = strpos($filename, ".");
		if ($p_dot === false)
			return array(null, false);
		$class = substr($filename, 0, $p_dot);
		$classPath = (($p_slash === false) ? "" : substr($path, 0, $p_slash + 1)).$class.".php";
		
		$parts = explode(".", substr($filename, $p_dot + 1));
		$last = end($parts);
		$before_last = $last ? prev($parts) : null;
		
		$mode = null;
		$tag = "";
		
		if ($last === "php")
		{
			if ((!$before_last) || ($before_last === "dyn"))
			{
				$mode = "php";
				if ($before_last === "dyn")
				{
					$classPath = (($p_slash === false) ? "" : substr($path, 0, $p_slash + 1)).$class.".dyn.php";
					$before_last = null;
				}
			}
			else
			{
				$parts_error = prev($parts);
				if ((!$parts_error) && (($before_last === "url") || ($before_last === "patch") || ($before_last === "gen")))
				{
					if ($before_last === "gen")
						throw new Exception("Gen should not be scaned");
					$mode = $before_last;
				}
				else
					$mode = false;
			}
		}
		else if ($last === "tpl")
		{
			$mode = "tpl";
			$tag = $before_last ?: "";
		}
		else
			// to be ignored
			$mode = false;
		
		$header_data = null;
		
		if (($header_data = self::$ExtractCache[$index_by_path]) === null)
		{
			$php_path = dirname($watch_folder.$path)."/{$class}.php";
			$php_dyn_path = dirname($watch_folder.$path)."/{$class}.dyn.php";
			$patch_path = dirname($watch_folder.$path)."/{$class}.patch.php";
			$mode_path = $mode ? dirname($watch_folder.$path)."/{$class}.{$mode}".(($mode !== "tpl") ? ".php" : "") : null;

			$readonly_tokens = null;

			$use_path = null;
			$use_mode = null;

			if (file_exists($php_path))
			{
				$use_path = $php_path;
				$use_mode = "php";
			}
			else if (file_exists($php_dyn_path))
			{
				$use_path = $php_dyn_path;
				$use_mode = "php";
			}
			else if (file_exists($patch_path))
			{
				$use_path = $patch_path;
				$use_mode = "patch";
			}
			/*
			else if (($last === "php") && ($before_last === "gen") && file_exists($watch_folder.$path))
			{
				$use_path = $watch_folder.$path;
				$use_mode = "gen";
			}
			*/
			else if ($mode_path && file_exists($mode_path))
			{
				$use_path = $mode_path;
				$use_mode = $mode;
			}
			else if (file_exists($watch_folder.$path))
			{
				// $header_data = QPHPToken::ParseHeaderOnly($watch_folder.$path, true, $readonly_tokens);
				$use_path = $watch_folder.$path;
				$use_mode = $mode;
			}

			if ($use_path)
			{
				if (($namespace = self::$PathToNamespace[$use_path][$use_mode]) !== null)
				{
					$header_data = self::$ExtractCache[$index_by_path] = [
									"class" => QPHPToken::ApplyNamespaceToName($class, $namespace),
									"namespace" => $namespace,
									"extends" => self::$PathToExtends[$use_path][$use_mode],
									"implements" => self::$PathToImplements[$use_path][$use_mode],
									"traits" => self::$PathToTraits[$use_path][$use_mode]
								];
				}
				else
				{
					// var_dump("THE LONG WAY FOR: |{$use_path}| {$use_mode}");

					$header_data = self::$ExtractCache[$index_by_path] = QPHPToken::ParseHeaderOnly($use_path, true, $readonly_tokens);

					self::$PathToNamespace[$use_path][$use_mode] = $header_data["namespace"] ?: false;
					self::$PathToExtends[$use_path][$use_mode] = $header_data["extends"] ?: false;
					self::$PathToImplements[$use_path][$use_mode] = $header_data["implements"] ?: false;
					self::$PathToTraits[$use_path][$use_mode] = $header_data["traits"] ?: false;
				}
			}
			else
				throw new Exception("No file: $watch_folder/$path");
		}
		
		$class = $header_data["class"];
		$namespace = $header_data["namespace"];

		return array($class, $mode, $classPath, $tag, $namespace, $readonly_tokens, $use_path);
	}
}

