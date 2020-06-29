<?php

/**
 * QCodeSyncNode
 *
 * @author Alex
 */
class QCodeSyncNode
{
	/**
	 * The name of the class
	 *
	 * @var string
	 */
	public $className;
	/**
	 * @var string
	 */
	public $namespace;
	/**
	 *
	 * @var string
	 */
	public $classNameWithoutNs;
	/**
	 * The name of the parent class
	 *
	 * @var string
	 */
	public $parentClass;
	/**
	 *
	 * @var QCodeSyncNode
	 */
	public $parent;
	/**
	 *
	 * @var QCodeSyncNode[]
	 */
	public $children = array();
	/**
	 *
	 * @var QCodeSync
	 */
	public $root;
	/**
	 * The path of the class
	 *
	 * @var string
	 */
	public $classPath;
	/**
	 * The path where the class is stored in it's final state (not in _patches)
	 *
	 * @var string
	 */
	public $compilePath;
	/**
	 * The watch folder this instance is associated with
	 *
	 * @var string
	 */
	public $watchFolder;
	/**
	 * The processing modes
	 *
	 * @var string[]
	 */
	public $modes;
	/**
	 * The data on the modes 
	 * 
	 * @var QCodeSyncItem[]
	 */
	public $data = array();
	/**
	 * On outside patch is what we create in the `_patches` folder
	 *
	 * @var boolean
	 */
	public $is_outside_patch;
	public $securityFilterIsRequired = null;
	
	public $requiresCompiled = false;
	
	public $fullRebuild = null;
	/**
	 * In case we have a virtual node
	 *
	 * @var QCodeSyncNode
	 */
	public $relatedNode;
	
	/**
	 * The constructor
	 * 
	 * @param string $classPath
	 */
	public function __construct($classPath, $class_name, $watchFolder, QCodeSync $root, QCodeSyncNode $parent = null)
	{
		// url, patch, tpl
		$this->classPath = $classPath;
		$this->className = $class_name;
		$this->watchFolder = $watchFolder;
		$this->root = $root;
		if ($parent)
			$this->parent = $parent;
	}
	
	public function setNamespace($namespace)
	{
		if ($namespace)
		{
			$this->namespace = $namespace;
			$this->classNameWithoutNs = qClassWithoutNs($this->className);
			$this->className = rtrim($namespace, "\\")."\\".$this->classNameWithoutNs;
		}
		else
		{
			$this->namespace = null;
			$this->className = $this->classNameWithoutNs = qClassWithoutNs($this->className);
		}
	}
	
	/**
	 * Determines the parent for this class
	 * 
	 * @param string[] $classParents
	 * @return string
	 */
	public function readParent($classParents)
	{
		/*
		if (!$this->parentClass)
		{
			// is it possible that the parent had been changed ?
			// yes if: patch & upper clone was changed
			//         (tpl,url,event) & upper clone was changed
			$old_parent = $classParents[$this->watchFolder][$this->className];
			if (!$old_parent)
				$old_parent = false;
		}
		$this->parentClass = $this->determineParent($classParents);
		*/
		return $this->determineParent($classParents);
	}
	
	/**
	 * Does the processing of all the elements recursive
	 * 
	 * @param string[] $changed_modes
	 * @param boolean $is_repatch
	 * @param string $repatch_in_folder
	 */
	public function processNode($changed_modes = null, $classParents = null, $full_sync = false, $pn_depth = 0)
	{
		if ($this->root->debugMode)
			echo "<div><b>processNode: {$this->className}</b> - [";
			
		$gen_file_path = substr($this->classPath, 0, -3)."gen.php";
		if (file_exists($gen_file_path) && (file_exists($this->classPath) || file_exists($this->getPatchPath())))
		{
			// we need to test if trait was included
			if (!QCodeSync::TraitWasIncluded($this))
			{
				// if (file_exists($this->classPath))
				$php_class_path = file_exists($this->classPath) ? $this->classPath : $this->getPatchPath();
				echo "You must include in {$php_class_path} the trait '{$this->className}_GenTrait' as generated in {$gen_file_path}.<br/>\n";
			}
		}
		// warn if trait not included when it should be included
			
		if (!$changed_modes)
			$changed_modes = array();
		else
		{
			foreach ($changed_modes as $mode => $val)
			{
				//var_dump($this->className." :: ".$mode);
				if ($val && $this->data[$mode])
				{
					foreach ($this->data[$mode] as $d_tag => $dmd)
					{
						// var_dump("Enter:: ".$this->className." :: ".$mode." :: ".$d_tag);
						
						if (!isset($this->root->classesMap[$this->className][$mode][$d_tag]))
							$this->root->classesMap[$this->className][$mode][$d_tag] = $this;
						$dmd->parent_changed = is_array($val) ? $val[$d_tag] : $val;
						
						if ($dmd->parent_changed)// && (($mode === "url") || ($mode === "patch") || ($mode === "tpl")))
						{
							$dmd->changed = true;
							if ($this->data["php"])
								$this->data["php"][""]->changed = true;
							if ($this->data["patch"])
								$this->data["patch"][""]->changed = true;
						}
					}
				}
			}
		}
		
		if ($this->relatedNode)
		{
			foreach ($this->relatedNode->data as $md_tag => $md_data_v)
			{
				foreach ($md_data_v as $md_subtag => $md_subtag_item)
				{
					if ($md_subtag_item->changed)
					{
						if (isset($this->data[$md_tag][$md_subtag]))
						{
							$this->data[$md_tag][$md_subtag]->related_changed = true;
						}
						if ($this->data["php"])
							$this->data["php"][""]->changed = true;
						if ($this->data["patch"])
							$this->data["patch"][""]->changed = true;
					}
				}
			}
		}
		
		// should we setup relatedNode now ?
		/*
		$ch_dump = ($this->data["php"][""]->changed ? "php," : "").($this->data["patch"][""]->changed ? "patch," : "").($this->data["url"][""]->changed ? "url," : "");
		if ($this->data["tpl"])
		{
			// $ch_dump .= "tpls:";
			foreach ($this->data["tpl"] as $ch_tag => $ch_elem)
				$ch_dump .= ($ch_elem->changed ? $ch_tag."," : "");
		}
		echo str_repeat("-", $pn_depth * 4)."processNode: {$this->watchFolder}/{$this->className} :: {$ch_dump}<br/>";
		*/
		// order to process modes
		$php_to_change = false;
		
		$php_has_changed = $full_sync || $this->data["php"][""]->changed || 
								($this->data["patch"][""] && ($this->data["patch"][""]->changed || $this->data["patch"][""]->related_changed || $this->data["php"][""]->related_changed));
		
		// if ($php_has_changed)
		// var_dump($this->classPath, $php_has_changed);
		
		$sync_generated = false;
		
		$this->fullRebuild = $php_has_changed;
		$compiled_path = $this->classPath ? QAutoload::GetCompiledPath($this->classPath, $this->watchFolder) : null;
		$this->compilePath = $compiled_path;
		
		$read_only = QAutoload::$WatchFoldersReadOnly[QAutoload::GetModulePathForPath($compiled_path)];
		
		/*
		if ((!$php_has_changed) && $compiled_path && (file_exists($compiled_path)))
			// or if it does not need compilation
			$this->fullRebuild = false;
		
		// in case the compiled file is missing trigger a full rebuild
		// some don't have a compile file/don't need one either
		if ($this->fullRebuild)
			$php_has_changed = true;
		 */

		if ($php_has_changed)// || $this->hasAnyChange())
		{
			//echo "Php changed: ".$this->classPath." | ".$this->className."<br/>";
			if ((!$read_only) && file_exists($compiled_path))
			{
				unlink($compiled_path);
			}
		}
		
		if ($this->parentClass === null)
		{
			throw new Exception("\$this->parentClass should not be null at this point: ".$this->classPath);
			// $this->determineParent($classParents);
		}
		
		// First manage generators
		if ($this->modes["gen.struct"] && $this->data["gen.struct"][""]->changed)
		{
			var_dump("gen.struct changed", $this->classPath, $this->watchFolder);
			
			$this->root->resyncGenStruct($this);
		}
		if ($this->modes["gen.config"])
		{
			var_dump("gen.config changed", $this->classPath, $this->watchFolder);
			
			$this->root->resyncGenConfig($this);
		}
		
		// we may eat a bit more memory with this, we will have to track things
		// init tokens cache
		// we will have these keys: compiled, php, patch, url, tpl[tplKey]
		$this->_tokCache = array();
		
		if ($this->modes["php"] && ($this->data["php"][""]->changed || $this->data["php"][""]->related_changed || $php_has_changed))
		{
			// related_changed does not affect here
			// var_dump("resyncPHPClass: ".$this->classPath." | ".$this->parentClass);
			// does nothing atm
			// $this->root->resyncPHPClass($this);
			
			$this->data["php"][""]->changed = true;
			
			$php_to_change = true;
			$changed_modes["php"][""] = true;
			$changed_modes["patch"][""] = true;
			
			if (!$read_only)
				$this->root->resyncPHPClass($this);
			
			if ($this->root->debugMode && $this->classPath)
				echo "php,";
		}
		if ($this->modes["patch"] && ($this->data["patch"][""]->changed || $this->data["patch"][""]->related_changed || $php_has_changed))
		{
			// if related_changed then we need a full resync
			$this->data["patch"][""]->changed = true;
			
			if (!$read_only)
				$this->root->resyncPatch($this);
			
			$php_to_change = true;
			$changed_modes["patch"][""] = true;
			$changed_modes["php"][""] = true;
			
			if ($this->root->debugMode && $this->classPath)
				echo "patch,";
			
			$sync_generated = true;
		}
		if ($this->modes["tpl"])
		{
			foreach ($this->data["tpl"] as $tpl_tag => $mode_tpl)
			{
				// handle $mode_tpl->parent_changed
				if (!($mode_tpl->changed || $php_has_changed || $mode_tpl->parent_changed || $mode_tpl->related_changed))
					continue;
				
				if (!$read_only)
				{
					if ((!($mode_tpl->changed || $php_has_changed)) && ($mode_tpl->parent_changed || $mode_tpl->related_changed))
					{
						// change only if in merge mode
						$tpl_test_tokens = $this->getReadOnlyTokens("tpl", $tpl_tag);
						if (($first_DOM_Element = $tpl_test_tokens->findFirst(".QPHPTokenXmlElement")))
						{
							$q_merge_val = $first_DOM_Element->getAttribute("qMerge");
							if ((!$q_merge_val) || (strtolower($q_merge_val) === "false"))
								continue;
						}
					}

					$mode_tpl->changed = true;
					
					if ($this->data["patch"])
						$this->data["patch"][""]->changed = true;
					if ($this->data["php"])
						$this->data["php"][""]->changed = true;

					$this->root->resyncTemplate($this, $tpl_tag, $classParents);
				}
				
				$php_to_change = true;
				$changed_modes["tpl"][$tpl_tag] = true;
				
				$changed_modes["patch"][""] = true;
				$changed_modes["php"][""] = true;
				
				$sync_generated = true;

				if ($this->root->debugMode && $this->classPath)
					echo "tpl[{$tpl_tag}],";
			}
		}
		if ($this->modes["url"] && ($this->data["url"][""]->changed || $php_has_changed))
		{
			$this->data["url"][""]->changed = true;
			
			if (!$read_only)
				$this->root->resyncUrlController($this);
			
			$php_to_change = true;
			
			if ($this->data["patch"])
				$this->data["patch"][""]->changed = true;
			if ($this->data["php"])
				$this->data["php"][""]->changed = true;
			
			$changed_modes["url"][""] = true;
			$changed_modes["patch"][""] = true;
			$changed_modes["php"][""] = true;
			
			$sync_generated = true;
			
			if ($this->root->debugMode && $this->classPath)
				echo "url,";
		}
		/*
		if ($this->modes["event"] && ($this->data["event"][""]->changed || $php_has_changed))
		{
			$this->data["event"][""]->changed = true;
			
			$this->root->resyncEventMethod($this);
			$php_to_change = true;
			$changed_modes["event"] = true;
			
			// $sync_generated = true; : to do in resyncEventMethod !!!!
			
			if ($this->root->debugMode && $this->classPath)
				echo "event,";
		}
		*/
		
		if ((!$read_only) && $this->requiresCompiled)
		{
			// var_dump("requiresCompiled: ".$this->className);
			$this->writeFinalClass();
		}
		
		/*
		if (($php_has_changed || $this->hasAnyChange()) && (!$sync_generated))
		{
			// we need to update the compiled if exists
			$compiled_path = QAutoload::GetCompiledPath($this->classPath);
			if (file_exists($this->classPath) && file_exists($compiled_path))
			{
				// we need to sync to the compiled path
				$this->root->syncCompiledCode($this->classPath, QPHPToken::ParsePHPFile($this->classPath));
			}
		}
		*/
		
		/*
		if ($php_to_change || ($this->modes["php"] && $this->data["php"]->changed))
		{
			if ($this->data["php"])
				$this->data["php"]->changed = true;
			$changed_modes["php"] = true;
			// cache Type Info on QIModel elements
			
			echo "Create type cache for: {$this->classPath}<br/>";
			
			if ($this->classPath)
				echo "type";
		}
		*/
		
		if ($this->root->debugMode)
		{
			echo "]</div>";
			echo "<div style='padding-left: 20px; color: red;'>";
			foreach ($this->data as $z_mode => $z_sync_item)
			{
				foreach ($z_sync_item as $iz_sync_item)
				{
					if ($iz_sync_item->changed)
					{
						if ($iz_sync_item->patch)
							echo "<b>{$z_mode}/patch</b> : ".$iz_sync_item->patch->path."<br/>\n";
						/*
						if ($z_sync_item->class_merge_node)
							echo "<b>{$z_mode}/class</b> : ".$iz_sync_item->class_merge_node->classPath."<br/>\n";*/
					}
				}
			}
			echo "</div>";

			echo "<div style='padding-left: 40px;'>";
		}
		
		// vor virtual nodes we will have to setup changed from the relatedNode
		if ($this->relatedNode)
		{
			foreach ($this->relatedNode->data as $md_tag => $md_data_v)
			{
				foreach ($md_data_v as $md_subtag => $md_subtag_item)
				{
					if ($md_subtag_item->changed && (!$changed_modes[$md_tag][$md_subtag]))
						$changed_modes[$md_tag][$md_subtag] = true;
				}
			}
		}
		
		foreach ($this->children as $child)
		{
			$child->processNode($changed_modes, $classParents, $full_sync, $pn_depth + 1);
		}
		
		if ($php_has_changed && $compiled_path && (!file_exists($compiled_path)))
		{
			if ($sync_generated)
			{
				// var_dump($compiled_path);
				throw new Exception("Sync was run but we don't have anything in the compiled folder for: ".$this->classPath." | ".$compiled_path);
			}
			
			/**
			 *  We have a problem, because QCodeStorage::parseSecurityRights() uses PHP's native ReflectionClass we can not establish 
			 *  if we will generate SecurityFilter without loading the class
			 *  and if we load the class it will trigger a chain load of classes and create complications
			 *  
			 */
			$this->securityFilterIsRequired = false;// $this->detectIfSecurityFilterIsRequired();
			if ($this->securityFilterIsRequired)
			{
				if (file_exists($this->classPath))
				{
					if (!is_dir($_t_dir = dirname($compiled_path)))
						mkdir($_t_dir, (0777 & ~umask()), true);
					copy($this->classPath, $compiled_path);
				}
				else
					throw new Exception("This should never be. We have a candidate class to be created in compiled but there is no source to create it from: ".$this->classPath);
				// $this->root->refreshSecurityFilter[] = $this->classPath;
			}
		}
		
		if ($this->root->debugMode)
			echo "</div>";
	}
	
	/**
	 *  We have a problem, because QCodeStorage::parseSecurityRights() uses PHP's native ReflectionClass we can not establish 
	 *  if we will generate SecurityFilter without loading the class
	 *  and if we load the class it will trigger a chain load of classes and create complications
	 * 
	 * 
	 * @return boolean
	 */
	public function detectIfSecurityFilterIsRequired()
	{
		if (($this->className === "QModel") || ($this->className === "QIModel") || ($this->className === "QModelArray") || ($this->className === "QIModelArray"))
			return false;
		
		if ($this->securityFilterIsRequired !== null)
			return $this->securityFilterIsRequired;
		
		if (file_exists($this->classPath))
		{
			// var_dump("detectIfSecurityFilterIsRequired:: ".$this->classPath);
			
			$code = QPHPTokenFile::ParsePHPFile($this->classPath);
			$cclass = $code ? $code->findPHPClass() : null;
			if ($cclass)
			{
				$doc_comm = $cclass ? $cclass->docComment : null;
				if ($doc_comm)
				{
					$parsed = QCodeStorage::parseDocComment($doc_comm);
					if ($parsed && $parsed["rights"])
					{
						return true;
					}
				}
				
				$props = $cclass->children(".QPHPTokenProperty");
				if ($props)
				{
					foreach ($props as $prop)
					{
						if ($prop->docComment)
						{
							$doc_comm = is_array($prop->docComment) ? $prop->docComment[1] : $prop->docComment->toString();
							$parsed = QCodeStorage::parseDocComment($doc_comm);
							if ($parsed && $parsed["rights"])
							{
								return true;
							}
						}
					}
				}
				
				$methods = $cclass->children(".QPHPTokenFunction");
				if ($methods)
				{
					foreach ($methods as $meth)
					{
						if ($meth->docComment)
						{
							$doc_comm = is_array($meth->docComment) ? $meth->docComment[1] : $meth->docComment->toString();
							$parsed = QCodeStorage::parseDocComment($doc_comm);
							if ($parsed && $parsed["rights"])
							{
								return true;
							}
						}
					}
				}
			}
		}
		
		if ($this->parent)
		{
			$ret_val = $this->parent->detectIfSecurityFilterIsRequired();
			if ($ret_val)
				return $ret_val;
		}
		
		return false;
	}
	
	/**
	 * True if any mode was changed
	 * 
	 * @return boolean
	 */
	public function hasAnyChange()
	{
		if ($this->isVirtual())
			return false;
		
		foreach ($this->data as $k => $item)
		{
			foreach ($item as $ii)
			{
				if ($ii->changed)
					return true;
			}
		}
		return false;
	}
	
	/**
	 * True if the specified mode is marked as changed
	 * 
	 * @param string $mode
	 * @return boolean
	 */
	public function hasModeChanged($mode)
	{
		if ($this->isVirtual())
			return false;
		foreach ($this->data[$mode] as $itm)
		{
			if ($itm->changed)
				return true;
		}
		return false;
	}
	
	/**
	 * Alias for hasAnyChange
	 * True if any mode was changed
	 * 
	 * @return boolean 
	 */
	public function hasChanged()
	{
		return $this->hasAnyChange();
	}
	
	/**
	 * Returns true if this node was created only to make sure we have a proper classes tree
	 * 
	 * @return boolean
	 */
	public function isVirtual()
	{
		return ($this->classPath === null);
	}

	/**
	 * Determines the current parent of this class
	 * 
	 * @param string[][] $classParents
	 * @return boolean
	 */
	protected function determineParent($classParents)
	{
		// is it possible that the parent had been changed ?
		// yes if: patch & upper clone was changed
		//         (tpl,url,event) & upper clone was changed
		
		//var_dump("determineParent :: ".$this->classPath);
		if ($this->parentClass !== null)
			return $this->parentClass;
		
		if (($posib_parent = QCodeSyncItem::$PathToExtends[$this->classPath]["php"]) !== null)
		{
			//var_dump("by php");
			return $this->parentClass = $posib_parent;
		}
		else if (($posib_parent = QCodeSyncItem::$PathToExtends[$this->getPatchPath()]["patch"]) !== null)
		{
			//var_dump("by patch");
			return $this->parentClass = $posib_parent;
		}
		/*
		else if (($posib_parent = QCodeSyncItem::$PathToExtends[$this->getGenPath()]["gen"]) !== null)
		{
			//var_dump("by gen");
			return $this->parentClass = $posib_parent;
		}
		*/
		else if (($posib_parent = QCodeSyncItem::$PathToExtends[$this->getUrlPath()]["url"]) !== null)
		{
			// var_dump("by url :: ".$this->className, $posib_parent);
			return $this->parentClass = $posib_parent;
		}
		else if (($posib_parent = QCodeSyncItem::$PathToExtends[$this->getTemplatePath()]["tpl"]) !== null)
		{
			if ($posib_parent === false)
				$posib_parent = "QWebControl";
			return $this->parentClass = $posib_parent;
		}
		{
			// TO DO: now loop parents for TPL !!!!
		}
		
		if ($this->parentClass !== null)
		{
			return $this->parentClass;
		}
		
		$parent_clone = $this->root->classesMap[$this->className]["php"][""]->node;
		if ($this->isVirtual())
		{
			if (!$parent_clone)
			{
				$this->parentClass = false;
				return false;
			}
			else
			{
				$this->parentClass = $parent_clone->parentClass;
				return $parent_clone->parentClass;
			}
		}
		
		// if we have something cache, make sure it was not changed
		if ($classParents && (($ret_parent = $classParents[$this->watchFolder][$this->className]) !== null) && (!$this->hasAnyChange()))
		{
			$this->parentClass = $ret_parent;
			return $ret_parent;
		}
		
		$info = $this->root->getClassHeadInfo($this, $this->watchFolder);
		$this->headInfo = $info;
		
		if ($info["extends"])
			$info["extends"] = QPHPToken::ApplyNamespaceToName($info["extends"],$info["namespace"]);
		
		$parent_class = $info ? $info["extends"] : null;
		
		// we can have a parent class or a dependency class in the previous watch folder (easy to evaluate)
		if ((!$parent_class) && $classParents)
		{
			// lookup in $classParents
			foreach ($classParents as $wf => $wf_data)
			{
				if ($wf === $this->watchFolder)
					break;
				if ($wf_data[$this->className])
					$parent_class = $wf_data[$this->className];
			}
		}
		
		if ($parent_class === null)
			$parent_class = false;
		
		if (($parent_class === false) && $this->modes["tpl"])
			$parent_class = "QWebControl";
		
		$this->parentClass = $parent_class;
		
		if ($parent_class === $this->className)
			throw new Exception("The class {$this->className} extend itself");
		
		return $parent_class;
	}

	/**
	 * Gets the root element
	 * 
	 * @return QCodeSyncNode
	 */
	public function getRoot()
	{
		return $this->root;
	}
	
	/**
	 * Gets the patch path
	 * 
	 * @return string
	 */
	public function getPatchPath()
	{
		return substr($this->classPath, 0, -3)."patch.php";
	}
	
	/**
	 * Gets the patch path
	 * 
	 * @return string
	 */
	public function getGenPath()
	{
		return substr($this->classPath, 0, -3)."gen.php";
	}
	
	/**
	 * Gets the template path
	 * 
	 * @return string
	 */
	public function getTemplatePath($tag = "")
	{
		return substr($this->classPath, 0, -3).($tag ? $tag."." : "")."tpl";
	}
	
	/**
	 * Gets the path for the specified mode
	 * 
	 * @return string
	 */
	public function getModePath($mode, $tag = "")
	{
		if (!$this->classPath)
			return null;
		
		switch ($mode)
		{
			case "php":
				return $this->classPath;
			case "tpl":
				return $this->getTemplatePath($tag);
			case "url":
				return $this->getUrlPath();
			case "patch":
				return $this->getPatchPath();
			case "event":
				return $this->getEventPath();
			default:
				return null;
		}
	}
	
	public function needsModelResync($mode)
	{
		if ($this->_modelresync !== null)
			return $this->_modelresync;
		
		$probe_nodes = [$this];
		while ($probe_nodes)
		{
			$new_pn = [];
			foreach ($probe_nodes as $pn)
			{
				$hi = $pn->getHeadInfo();
				$extends = QPHPToken::ApplyNamespaceToName($hi["extends"], $hi["namespace"]);
				$implements = $hi["implements"];
				if (($extends === "QModel") || ($extends === "QModelArray"))
					return ($this->_modelresync = true);
				if ($implements)
				{
					foreach ($implements as $_impl)
					{
						$impl = QPHPToken::ApplyNamespaceToName($_impl, $hi["namespace"]);
						if (($impl === "QIModel") || ($impl === "QIModelArray"))
							return ($this->_modelresync = $pn->_modelresync = true);
						if ($impl && ($new_n = ($this->root->data["_"][$impl] ?: null)))
						{
							if ($new_n->_modelresync)
								return ($this->_modelresync = $new_n->_modelresync);
							$new_pn[$impl] = $new_n;
						}
					}
				}
				if ($extends && ($new_n = ($this->root->data["_"][$extends] ?: null)))
				{
					if ($new_n->_modelresync)
						return ($this->_modelresync = $new_n->_modelresync);
					$new_pn[$extends] = $new_n;
				}
			}
			$probe_nodes = $new_pn;
		}

		return ($this->_modelresync = false);
	}
	
	public function getFilenameForMode($mode, $tag = "")
	{
		if (!$this->classPath)
			return null;
		
		switch ($mode)
		{
			case "php":
				return $this->className.".php";
			case "tpl":
				return $this->className.($tag ? ".".$tag : "").".tpl";
			case "url":
				return $this->className.".url.php";
			case "patch":
				return $this->className.".patch.php";
			case "event":
				return $this->className.".event.php";
			case "js":
				return $this->className.".js";
			default:
				return $this->className.($tag ? ".".$tag : "").".{$mode}.php";
		}
	}
	
	/**
	 * Gets the URL path
	 * 
	 * @return string
	 */
	public function getUrlPath()
	{
		return substr($this->classPath, 0, -3)."url.php";
	}
	
	/**
	 * Gets the GenStruct path
	 * 
	 * @return string
	 */
	public function getGenStructPath()
	{
		return substr($this->classPath, 0, -3)."gen.struct.php";
	}
	
	/**
	 * Gets the GenConfig path
	 * 
	 * @return string
	 */
	public function getGenConfigPath()
	{
		return substr($this->classPath, 0, -3)."gen.config.php";
	}
	
	/**
	 * Gets the Event path
	 * 
	 * @return string
	 */
	public function getEventPath()
	{
		return substr($this->classPath, 0, -3)."event.php";
	}
	
	/**
	 * Gets the sync item for the specified mode
	 * 
	 * @return QCodeSyncItem
	 */
	public function getItem($mode, $tag = "")
	{
		return $this->data[$mode][$tag];
	}
	
	/**
	 * Sets the sync item for the specified mode
	 * 
	 * @param QCodeSyncItem $item
	 * @param string $mode
	 */
	public function setItem(QCodeSyncItem $item, $mode, $tag = "")
	{
		$this->data[$mode][$tag] = $item;
	}
	
	/**
	 * Determines foreach mode, what sync item it should patch (if any) 
	 * and also for elements that are clones, it determines what PHP class file they 
	 * should overwrite
	 * 
	 * @param QCodeSyncItem[][] $classesMap
	 */
	public function establishWhatWePatchAndClassMergeWith($classesMap, $watchFolder = null)
	{
		foreach ($this->data as $mode => $sync_item_list)
		{
			foreach ($sync_item_list as $tag => $sync_item)
			{
				if (($mode === "tpl") || ($mode === "event") || ($mode === "url"))
				{
					$parent_synci = $this->parent->data[$mode][$tag];
					
					if (($patch_synci = $classesMap[$this->className][$mode][$tag]) !== null)
					{
						// var_dump("clone in a previous WatchFolder", $sync_item->path);
						$sync_item->patch = $patch_synci;
						$sync_item->patch_parents = true;
						/*if ($patch_synci->changed)
							$sync_item->changed = true;*/

						/*
						foreach ($patch_synci->node->children as $child)
						{
							if ((!($cc = $this->root->data["_"][$child->className])) || ($cc->isVirtual()))
							{
								// var_dump("setupOutsideWorkingFolderPatch", $child->path);
								$this->setupOutsideWorkingFolderPatch($child, $mode, $tag, $sync_item->modif_date, $sync_item->changed);
							}
						}
						*/
					}
					// parent in this WatchFolder
					else if ($this->parent && $parent_synci)
					{
						$sync_item->patch = $parent_synci;
						/*if ($parent_synci->changed)
							$sync_item->changed = true;*/
					}
					// parent in a previous WatchFolder
					else if (($patch_synci = $classesMap[$this->parent->className][$mode][$tag]) !== null)
					{
						$sync_item->patch = $patch_synci;
						/*if ($patch_synci->changed)
							$sync_item->changed = true;*/
					}
					
					/*
					if (!empty($classesMap[$this->className]))
						$sync_item->class_merge_node = reset($classesMap[$this->className]["php"])->node;*/
				}
				else if ($mode === "patch")
				{
					// clone in a previous WatchFolder
					if (($patch_synci = $classesMap[$this->className]["php"][$tag]) !== null)
					{
						$sync_item->patch = $patch_synci;
						/*if ($patch_synci->changed)
							$sync_item->changed = true;*/
					}
				}
			}
		}
	}
	
	public function debugPrint($tabs = "")
	{
		if ($this->parent === null)
		{
			//echo "<pre>";
		}
		
		echo $tabs.$this->className;
		foreach ($this->data as $mode => $di_grp)
		{
			if (count($di_grp) === 1)
			{
				$di = reset($di_grp);
				$c = $di->changed ? "green" : "gray";
				echo " <span style='color: {$c};'>[{$mode}]</span>";
			}
			else
			{
				foreach ($di_grp as $tag => $di)
				{
					$c = $di->changed ? "green" : "gray";
					echo " <span style='color: {$c};'>[{$mode}/{$tag}]</span>";
				}
			}
		}
		echo "\n";
		
		if ($this->children)
		{
			foreach ($this->children as $child)
				$child->debugPrint($tabs."\t");
		}
		
		if ($this->parent === null)
		{
			//echo "</pre>";
		}
	}
	
	public function getReadOnlyTokens($mode, $tag = "")
	{
		return $this->getTokens($mode, $tag, true);
	}
	
	public function getTokens($mode, $tag = "", $for_read_only = false)
	{
		$for_read_only = true;
		if ($mode === "final")
			// return $this->getFinalTokens($for_read_only);
			throw new Exception("this is not accepted");
		
		if ($for_read_only && ($ret = $this->_tokCache[$mode][$tag]))
			return $ret;
		
		// we don't edit except on the generated file, so we can cache with ease
		
		$path = $this->getModePath($mode, $tag);
		
		// var_dump("getTokens:: ".$path);
		
		// var_dump($path, $mode, $tag);
		// if we have the class in this folder
		if (file_exists($path))
		{
			// use this
			$expand_in_methods = (($mode === "php") || ($mode === "patch")) ? false : true;
			// $cached_tokens = $this->__cached_tokens ? $this->__cached_tokens[$path] : null;
			$tokens = QPHPToken::ParsePHPFile($path, $expand_in_methods, false, null, true);
			if ($for_read_only)
				$this->_tokCache[$mode][$tag] = $tokens;
			return $tokens;
		}
		return null;
	}

	public function setFinalToken(QPHPToken $tok)
	{
		$this->_tokCache["final"] = $tok;
	}
	
	/**
	 * Gets the tokens to be modified and later generated
	 * If taken from an earlier folder the FINAL tokens will be overwritten (optimization)
	 * 
	 * @return QPHPToken
	 */
	public function getFinalTokens()
	{
		if (($ret = $this->_tokCache["final"]))
			return $ret;
		
		// var_dump("getFinalTokens: ".$this->classPath);
		
		if ($this->classNameWithoutNs === null)
			$this->classNameWithoutNs = qClassWithoutNs($this->className);
		
		// if ((!$php_has_changed) && $compiled_path && (file_exists($compiled_path)))
			// $this->fullRebuild = false;
		// ensure minimum setup
		// $this->root->classPaths[$this->className] = $this->classPath;
		
		// echo "getFinalTokens/{$this->classPath}<br/>\n";
		//var_dump($this->classPath, $this->fullRebuild, file_exists($this->classPath));
		
		// if we have the class in this folder
		if (($this->fullRebuild === false) && file_exists($this->compilePath))
		{
			//echo "---- (fullRebuild = false) using compilePath<br/>\n";
			$tokens = QPHPToken::ParsePHPFile($this->compilePath, false);
			$this->_tokCache["final"] = $tokens;
		}
		// in this case we have the PHP class here
		else if (file_exists($this->classPath))
		{
			//echo "---- (file_exists(\$this->classPath)) return null<br/>\n";
			// even if a PHP class is defined at an upper level, here it is reset
			//$this->root->classPaths[$this->className] = $this->classPath;
			$this->_tokCache["final"] = $this->ensureGeneratedTraitClass();
		}
		// if we are patching or for tpl & url
		else 
		{
			// here we may have a patch, or a upper class to merge with
			//echo "---- we may have a patch<br/>\n";
			$upper_node = null;
			$upper_node_opts = [];
			// 1. find if we have a upper class
			foreach ($this->data as $data_mode => $mode_data)
			{
				// if (($data_mode !== 'php') && ($data_mode !== 'patch'))
					// continue;
				
				foreach ($mode_data as $mode_data_key => $sync_item)
				{
					// we presume the patch is the same regardless of mode !!! Big fail !
					if (($sync_item->patch) && ($sync_item->patch->node->className === $this->className))
					{
						$upper_node = $sync_item->patch->node;
						// break;
						$upper_node_opts[$data_mode][$mode_data_key] = $upper_node;
					}
				}
				// if ($upper_node)
					// break;
			}
			
			if ($upper_node_opts['patch'])
			{
				// qvar_dumpk("picking patch", array_keys($upper_node_opts['patch']));
				$upper_node = reset($upper_node_opts['patch']);
			}
			else if ($upper_node_opts['php'])
				$upper_node = reset($upper_node_opts['php']);
			else if ($upper_node_opts['tpl'])
			{
				if (($upper_node = $upper_node_opts['tpl']['']))
				{}
				else
					$upper_node = reset($upper_node_opts['tpl']);
			}
			else if ($upper_node_opts)
			{
				$upper_node = reset(reset($upper_node_opts));
			}
			else
				$upper_node = null;
			
			// qvar_dumpk(dirname($this->classPath), $this->data, $upper_node, $upper_node_opts);
			
			// we need class defined if there is no patch
			$second_class_needed = !file_exists($this->getPatchPath());
			$generate_as_trait = !$second_class_needed;
			//if ($second_class_needed)
			//	$this->root->classPaths[$this->className] = $this->compilePath;

			// in this case we have an upper class to merge with (in case of patch, url, tpl)
			if ($upper_node)
			{
				//	echo "---- found upper node: {$upper_node->classPath}<br/>\n";
				// var_dump($fe = $upper_node->getPatchPath());
				
				$upper_class_path = null;
				if (file_exists(($fe = $upper_node->classPath)))
					$upper_class_path = $fe;
				else if (file_exists(($fe = $upper_node->getPatchPath())))
					$upper_class_path = $fe;
				else if (file_exists(($fe = \QAutoload::GetCompiledPath($upper_node->classPath))))
					$upper_class_path = $fe;

				$upper_trait_tokens = $upper_node->getFinalTokens();
				
				// if PHP | Patch ... get it, extract constants, convert to trait
				if ($upper_class_path)
				{
					$upper_class_tokens = QPHPToken::ParsePHPFile($upper_class_path, false);
					$upper_class_obj = $upper_class_tokens->findFirst(".QPHPTokenClass");

					$saved_constants = null;
					// because we transform to a trait we will need to save the constants
					if ($generate_as_trait)
					{
						$saved_constants = $upper_class_obj->constants;
						// here we will also drop constants
						$upper_class_obj->transformToTrait($this->classNameWithoutNs."_GenTrait", null, true);
					}
					$upper_class_obj->removeUseTrait($this->classNameWithoutNs."_GenTrait");

					if ($upper_trait_tokens)
						// we also have a trait to join with
						$upper_class_obj->appendTrait($upper_trait_tokens->findFirst(".QPHPTokenClass"));
					
					//if ($second_class_needed || ($saved_constants && (!file_exists($this->getPatchPath()))))
					//	$this->ensureGeneratedTraitClass($upper_class_tokens, $generate_as_trait);
					
					if ($saved_constants && (!file_exists($this->getPatchPath())))
					{
						// (!file_exists($this->classPath)) is also true
						// we need to create the class & constants
						$constants_class = $upper_class_tokens->findSecondPHPTokenClass();
						// constants
						$constants_class->mergeConstants($saved_constants);
					}

					$this->_tokCache["final"] = $upper_class_tokens;
				}
				// if has trait ... get it, merge with it, there must be no colision
				// if trait and no PHP | Patch ... just copy it !
				else if ($upper_trait_tokens)
				{
					if ($second_class_needed)
						$this->ensureGeneratedTraitClass($upper_trait_tokens, $generate_as_trait);
					
					$this->_tokCache["final"] = $upper_trait_tokens;
				}
				else
				{
					throw new Exception("Algorithm error. There is no info in the patch, but it was linked to this node.");
				}
			}
			// there is no upper class to merge with
			else
			{
				//echo "---- no upper node<br/>\n";
				if ($second_class_needed)
				{
					// var_dump($trait_str);
					$this->_tokCache["final"] = $this->ensureGeneratedTraitClass(null, $generate_as_trait);
				}
				else
					$this->_tokCache["final"] = null;
			}
		}
		
		return $this->_tokCache["final"];
	}
	
	/**
	 * Makes sure that class is appended to the file
	 * 
	 * @param QPHPToken $top_level_token
	 * 
	 * @return QPHPToken
	 */
	public function ensureGeneratedTraitClass(QPHPToken $top_level_token = null, $generate_as_trait = true)
	{
		if ($generate_as_trait)
		{
			$trait_str = "<?php\n/**
  * This file was generated and it will be overwritten when it's dependencies are changed or removed.
  */\n".($this->namespace ? "namespace {$this->namespace};\n\n" : "")."trait {$this->classNameWithoutNs}_GenTrait\n{\n\n}\n\n";
		}
		else
			$trait_str = "<?php\n\n".($this->namespace ? "namespace {$this->namespace};\n\n" : "")."class {$this->classNameWithoutNs}\n{\n\n}\n\n";
		
		// "<?php\n\n".($this->namespace ? "namespace {$this->namespace};\n\n" : "")."class {$this->classNameWithoutNs}\n{\n\tuse {$this->classNameWithoutNs}_GenTrait;\n\n}\n\n";
		$new_class_file = QPHPToken::ParsePHPString($trait_str, false, false);
		
		if ($top_level_token)
		{
			$new_class = $new_class_file->findFirstPHPTokenClass();
			if (!$top_level_token->children)
				$top_level_token->children = array($new_class);
			else
				$top_level_token->children[] = $new_class;
		}
		
		return $new_class_file;
	}

	public function writeFinalClass()
	{
		$final = $this->getFinalTokens();
		if (!$final)
		{
			var_dump($this->classPath, $this->watchFolder);
			throw new Exception("the class has been marked for saving but we don't have an object");
		}
		
		$save_path = QAutoload::GetCompiledPath($this->classPath, $this->watchFolder);

		// var_dump("writeFinalClass:: {$this->classPath} => {$save_path}");
		// echo "<textarea>{$final}</textarea>";

		if (!is_dir(dirname($save_path)))
			qmkdir(dirname($save_path));
		
		// ensure namespace is right !
		$final_code = ($final instanceof QPHPTokenCode) ? $final : $final->findCode();
		$final_code->setNamespace($this->namespace);
		
		if ($this->parentClass)
		{
			$final_class = $final_code->findFirstPHPTokenClass();
			if ($final_class->type !== "trait")
				$final_class->setExtends("\\".ltrim($this->parentClass, "\\"), true);
		}
		
		// pull all use statements and copy them over
		$use_statements_tokens = file_exists($this->classPath) ? $this->getReadOnlyTokens("php") : (file_exists($this->getPatchPath()) ? $this->getReadOnlyTokens("patch") : null);
		$use_statements_code = $use_statements_tokens ? (($use_statements_tokens instanceof QPHPTokenCode) ? $use_statements_tokens : $use_statements_tokens->findCode()) : null;
		if ($use_statements_code)
		{
			$use_statements = $use_statements_code->getAllUseNamespaceStatements();
			// var_dump("xxxx", $use_statements);
			if ($use_statements)
				$final_code->setAllUseNamespaceStatements($use_statements);
		}

		QCodeSync::filePutContentsIfChanged($save_path, ($str = $final->toString()), true);
		
		// die($save_path);

		// update info on file
		$this->root->files[$this->watchFolder][substr($save_path, strlen($this->watchFolder))] = filemtime($save_path);
		
		// don't do that we need it later !!!
		// unset($this->_tokCache["final"]);
	}
	
	public function getHeadInfo()
	{
		if ($this->headInfo !== null)
			return $this->headInfo;
		$info = $this->root->getClassHeadInfo($this, $this->watchFolder);
		$this->headInfo = ($info !== null) ? $info : false;
		
		return $info;
	}
	
	
}
