<?php

/**
 * Generates and patches platform standards
 */
trait QCodeSync2_Upgrade
{
	protected $upgrage_dir;
	protected $upgrade_inside_dir;

	public function run_upgrade(array &$files, array $changed_or_added, array $removed_files, array $new_files)
	{
	    $this->upgrade_inside_dir = getcwd()."/";
		$this->upgrage_dir = dirname(getcwd())."/".basename(getcwd())."@upgrade/";
		$this->temp_code_dir = $this->upgrage_dir."temp/code/";
		
		if (!is_dir($this->temp_code_dir))
			qmkdir($this->temp_code_dir);
		if (!is_dir($this->upgrage_dir))
			throw new \Exception('Please create the upgrade dir: '.$this->upgrage_dir);
		
		// new steps ... 
		
		$lint_info = null;
		$grouped_data = [];
		
		foreach ($files as $layer => $layer_files)
		{
			if (substr($layer, 0, strlen($this->upgrade_inside_dir)) !== $this->upgrade_inside_dir)
				throw new \Exception('Code folder `'.$layer.'` is outside the upgrade directory `'.$this->upgrade_inside_dir.'`');
			
			if (static::$PHP_LINT_CHECK)
				$this->check_syntax($layer, $layer_files, $lint_info);
			
			foreach ($layer_files as $file => $mtime)
			{
				$is_php_ext = (substr($file, -4, 4) === '.php');
				$is_tpl_ext = (substr($file, -4, 4) === '.tpl');
				if ($is_php_ext && ((substr($file, -8, 8) === '.gen.php') || substr($file, -8, 8) === '.dyn.php'))
					// just skip!
					continue;
				else if (!($is_php_ext || $is_tpl_ext))
				{
					$this->upgrade_copy_file($layer, $file);
				}
				else
				{
					// echo "Evaluating: ".$layer.$file."<br/>\n";
					// plain PHP ... set it in the autoload
					$header_inf = \QPHPToken::ParseHeaderOnly($layer.$file, false);
					if (!isset($header_inf["class"]))
						throw new \Exception('Unable to identify short class name in: '.$layer.$file);
					$short_class_name = (($p = strrpos($header_inf["class"], "\\")) !== false) ? substr($header_inf["class"], $p + 1) : $header_inf["class"];

					$header_inf['is_tpl'] = $is_tpl_ext;
					$header_inf['is_url'] = $is_php_ext && (substr($file, -8, 8) === '.url.php');
					$header_inf['is_php'] = $is_php_ext && (!$header_inf['is_url']);
					$header_inf['is_patch'] = $is_php_ext && (substr($file, -10, 10) === '.patch.php');
					$header_inf['file'] = $file;
					
					$key = $header_inf['is_php'] ? '01-' : ($header_inf['is_url'] ? '02-' : '03-');

					$grouped_data[$layer][dirname($file)."/"][$short_class_name][$key.basename($file)] = $header_inf;
					
					if ($header_inf['is_url'] || $header_inf['is_tpl'])
						$this->upgrade_copy_file($layer, $file);
				}
			}
		}
		
		// from here on we only do PHP classes
		$info_by_class = [];
		
		foreach ($grouped_data as $gd_layer => $gd_dirs)
		{
			foreach ($gd_dirs as $gd_dir_name => $gd_classes_list)
			{
				foreach ($gd_classes_list as $gd_class_short => $gd_class_files)
				{
					// sort by (1.php, 2.url, 3.tpl) to get the best info
					ksort($gd_class_files);
					
					$namespace = null;
					$class = null;
					$extends = null;
					$implements = null;
					
					$has_tpl = null;
					$has_url = null;
					$is_patch = null;
					
					$locations = [];
					
					foreach ($gd_class_files as $gd_file_name => $header_inf)
					{
						if ($header_inf['namespace'] && ((!$namespace) || ($namespace === $header_inf['namespace'])))
							$namespace = $header_inf['namespace'];
						//else if ($header_inf['namespace'] && ($header_inf['namespace'] !== $namespace))
						//	throw new \Exception('Namespace mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['class'] && ((!$class) || ($class === $header_inf['class'])))
							$class = $header_inf['class'];
						//else if ($header_inf['class'] && ($header_inf['class'] !== $class))
						//	throw new \Exception('Class mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['extends'] && ((!$extends) || ($extends === $header_inf['extends'])))
							$extends = $header_inf['extends'];
						//else if ($header_inf['extends'] && ($header_inf['extends'] !== $extends))
						//	throw new \Exception('Extends mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['implements'] && ((!$implements) || ($implements === $header_inf['implements'])))
							$implements = $header_inf['implements'];
						//else if ($header_inf['implements'] && ($header_inf['implements'] !== $implements))
						//	throw new \Exception('Implements mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['is_tpl'])
							$has_tpl = true;
						if ($header_inf['is_url'])
							$has_tpl = true;
						if ($header_inf['is_patch'])
							$is_patch = true;
						
						if ($header_inf['is_patch'] || $header_inf['is_php'])
							// only php / patch are to be converted if needed
							$locations[] = $header_inf['file'];
					}
					
					// test if it extends 
					$full_class_name = \QPHPToken::ApplyNamespaceToName($class, $namespace);
					
					if ($extends)
						$info_by_class[$full_class_name]['extends'] = \QPHPToken::ApplyNamespaceToName($extends, $namespace);
					if ($has_tpl)
						$info_by_class[$full_class_name]['has_tpl'] = true;
					if ($has_url)
						$info_by_class[$full_class_name]['has_url'] = true;
					if ($is_patch)
						$info_by_class[$full_class_name]['is_patch'] = true;
					foreach ($locations as $loc)
						$info_by_class[$full_class_name]['files'][$gd_layer][$loc] = true;
				}
			}
		}
		
		$watch_folders_tags = array_flip(\QAutoload::GetWatchFoldersByTags());
		$toks_cache_methods = [];
		
		foreach ($info_by_class as $class => $info)
		{
			$class_ns = (($p = strrpos($class, "\\")) !== false) ? substr($class, 0, $p) : null;
			if (($info['has_tpl']) || ($info['has_url']) || ($info['is_patch']) || 
						($info['extends'] && $this->check_if_class_extends($class, $class_ns, 'QModel', $info_by_class, $info['extends'])) || 
						($info['implements'] && $this->check_if_class_implements($class, $class_ns, 'QIModel', $info_by_class, $info['implements'])))
			{
				// do the upgrade
				$prev_layer_class_name = null;
				foreach ($info['files'] ?: [] as $layer => $files_list)
				{
					foreach ($files_list as $file => $tmp)
					{
						// do the `upgrade` then push it
						// we need the layer name !!!
						$prev_layer_class_name = $this->upgrade_class_file($class, $layer, $file, $watch_folders_tags[$layer], $toks_cache_methods, $prev_layer_class_name);
					}
				}
			}
			else
			{
				// transfer them
				foreach ($info['files'] ?: [] as $layer => $files_list)
				{
					foreach ($files_list as $file => $tmp)
					{
						$this->upgrade_copy_file($layer, $file);
					}
				}
			}
		}
		
		/**
		 * STEPS: 
		 *			1. if a class is patched `later` | or has tpl/url (now or later) | or QModel/QIModel (for getters/setters), rename it to CLASS_$layer_
		 *				ELSE JUST COPY IT !
		 *			2. inside it set ** @class.name CLASS *
		 *			3. patching classes must ` extend ` prev_patched class name
		 */
	}
	
	public function upgrade_class_file(string $full_class_name, string $layer, string $file, string $layer_tag, array &$toks_cache_methods, string $prev_layer_class_name = null)
	{
		$file_tok = \QPHPToken::ParsePHPFile($layer.$file, false, false);
		if (!$file_tok)
			throw new \Exception('Unable to parse: '.$layer.$file);
		$class_tok = $file_tok->findFirstPHPTokenClass();
		if (!$class_tok)
			throw new \Exception('Unable to parse and find class in: '.$layer.$file);
		
		$prev_toks_methods = $toks_cache_methods[$full_class_name];
		
		$new_class_name = $class_tok->className.'_'.$layer_tag.'_';
		$force_extends = $prev_layer_class_name ?: false;
		
		$after_class_stmt = false;
		$inside_extends = false;
		$class_name_was_set = false;
		$extends_was_set = false;
		$class_name_pos = false;
		$doc_comment_pos_before = false;
		$inside_use_stmt = false;
		
		$patch_renames = [];
		
		if ($class_tok->final)
			throw new \Exception("Can not upgrade class `{$full_class_name}` because it's `final` and it needs patching");
		
		foreach ($class_tok->children ?: [] as $t_pos => $child)
		{
			if (($child instanceof \QPHPTokenDocComment) && ($child === $class_tok->docComment))
			{
				$parsed_dc = $this->parse_doc_comment((string)$child);
				if (isset($parsed_dc["class.name"]))
					$parsed_dc["class.name"][1] = " ".$class_tok->className;
				else
					$parsed_dc["class.name"] = ["\n * @class.name", " ".$class_tok->className."\n "];
				
				if ($class_tok->abstract)
				{
					if (isset($parsed_dc["class.abstract"]))
						$parsed_dc["class.abstract"][1] = " true";
					else
						$parsed_dc["class.abstract"] = ["\n * @class.abstract", " true\n "];
				}
				if ($class_tok->final)
				{
					if (isset($parsed_dc["class.final"]))
						$parsed_dc["class.final"][1] = " true";
					else
						$parsed_dc["class.final"] = ["\n * @class.final", " true\n "];
				}
				
				if ($parsed_dc['patch.rename'])
				{
					$pr_lines = explode("\n", $parsed_dc['patch.rename'][1]);
					foreach ($pr_lines as $pr_line_)
					{
						$pr_line = trim(trim(trim($pr_line_), '*'));
						if (!empty($pr_line))
						{
							list($pr_key, $pr_val) = preg_split("/(\s+)/uis", $pr_line, 2);
							if ($pr_key && $pr_val)
								$patch_renames[$pr_key] = $pr_val;
						}
					}
				}
				
				$dc_str = "";
				foreach ($parsed_dc as $pdc_item)
					$dc_str .= $pdc_item[0].rtrim($pdc_item[1]);
				$child->children[0][1] = "/**{$dc_str}\n */";
			}
			else if ($child instanceof \QPHPToken)
				continue;
			
			// $done = false;
			$type = is_array($child) ? $child[0] : $child;
			switch ($type)
			{
				case T_ABSTRACT:
				case T_FINAL:
				{
					if ($doc_comment_pos_before === false)
						$doc_comment_pos_before = $t_pos;
					break;
				}
				case T_CLASS:
				{
					if ($doc_comment_pos_before === false)
						$doc_comment_pos_before = $t_pos;
					$after_class_stmt = true;
					break;
				}
				case T_STRING:
				case T_NS_SEPARATOR:
				{
					if ($inside_extends)
					{
						if ($force_extends)
						{
							$class_tok->children[$t_pos][1] = $force_extends;
							$extends_was_set = true;
						}
						$inside_extends = false;
					}
					else if ($after_class_stmt && (!$class_name_was_set))
					{
						$class_tok->children[$t_pos][1] = $new_class_name;
						$class_name_was_set = true;
						$class_name_pos = $t_pos;
					}
					else if ($inside_use_stmt && ($child[1] === $class_tok->className.'_GenTrait'))
					{
						$class_tok->children[$t_pos][1] = $new_class_name.'_GenTrait';
						// qvar_dumpk('$inside_use_stmt: '. $child[1]);
					}
					break;
				}
				case T_EXTENDS:
				{
					$inside_extends = true;
					break;
				}
				case T_IMPLEMENTS:
				{
					$inside_extends = false;
					break;
				}
				case "{":
				{
					//if ($after_class_stmt)
					//	$done = true;
					$inside_use_stmt = false;
					break;
				}
				case ";":
				{
					$inside_use_stmt = false;
					break;
				}
				case T_USE:
				{
					$inside_use_stmt = true;
					break;
				}
				default:
					break;
			}
			//if ($done)
			//	break;
		}
		
		if ($force_extends && (!$extends_was_set))
		{
			// the order for splice is important !!!
			if ($class_name_pos === false)
				throw new \Exception('Class name was not found in tokens: '.$layer.$file);
			array_splice($class_tok->children, $class_name_pos + 1, 0, 
						[
							[T_WHITESPACE, ' '], 
							[T_EXTENDS, 'extends'],
							[T_WHITESPACE, ' '],
							[T_STRING, $force_extends],
							]);
		}
		
		if (!$class_tok->abstract)
		{
			$insert_abstract_def = [
							[T_ABSTRACT, 'abstract'],
							[T_WHITESPACE, " "],];
			array_splice($class_tok->children, $doc_comment_pos_before, 0, $insert_abstract_def);
		}
		
		if (!$class_tok->docComment)
		{
			// the order for splice is important !!!
			if ($doc_comment_pos_before === false)
				throw new \Exception('Class definition start was not found in tokens: '.$layer.$file);
			$doc_comm = "/**\n * @class.name {$class_tok->className}\n ";
			
			if ($class_tok->abstract)
				$doc_comm .= "* @class.abstract true\n ";
			if ($class_tok->final)
				$doc_comm .= "* @class.final true\n ";
			
			$doc_comm .= '*/';
			$insert_doc_comm_array = [
							[T_WHITESPACE, "\n"], 
							[T_DOC_COMMENT, $doc_comm],
							[T_WHITESPACE, "\n"],
							];
			array_splice($class_tok->children, $doc_comment_pos_before, 0, $insert_doc_comm_array);
		}
		
		$dump_str = "";
		$break_out = false;
		$file_tok->walk(function ($element, $pos, $parent) use (&$dump_str, &$break_out) {
			if (($element === '{') && ($parent instanceof \QPHPTokenClass))
			{
				$break_out = true;
				$dump_str .= (is_array($element) ? $element[1] : $element);
				return false;
			}
			else if ((!($element instanceof \QPHPToken)) && (!$break_out))
				$dump_str .= (is_array($element) ? $element[1] : $element);
		});
		
		if ($patch_renames)
		{
			foreach ($patch_renames as $pr_from => $pr_to)
			{
				$method_name_was_set = false;
				$method_str = "\n\t/**\n\t * @##upgraded_patch_rename {$pr_from} => {$pr_to}\n\t */\n\t";
				$prev_class_meth = $prev_toks_methods[$pr_from];
				if (!$prev_class_meth)
					throw new \Exception("Can not find method `{$pr_from}()` to patch in ".$layer.$file);
				foreach ($prev_class_meth->children as $child)
				{
					if ($child instanceof \QPHPTokenCode)
						break;
					else if (($child instanceof \QPHPTokenDocComment) || (is_array($child) && ($child[0] === T_DOC_COMMENT)))
					{
						// skip it
					}
					else if ((!$method_name_was_set) && is_array($child) && ($child[0] === T_STRING))
					{
						// function name
						$method_str .= $pr_to;
						$method_name_was_set = true;
					}
					else
						$method_str .= is_array($child) ? $child[1] : $child;
				}
				$method_str .= "{\n\t\treturn parent::{$pr_from}(...func_get_args());\n\t}\n";
				
				$class_tok->setMethodFromString($pr_to, $method_str, false);
			}
			
			// echo "<textarea style='height: 300px; width: 1200px; -moz-tab-size : 4;tab-size : 4;' wrap='off'>".htmlspecialchars($method_str)."</textarea>";
			// echo "<textarea style='height: 300px; width: 1200px; -moz-tab-size : 4;tab-size : 4;' wrap='off'>".htmlspecialchars((string)$class_tok)."</textarea>";
			
		}
		
		// all ok, save it !
		$write_to_file_name = null;
		$full_ext = substr(basename($file), strpos(basename($file), "."));
		if ($full_ext === '.php')
			$write_to_file_name = substr($file, 0, -4).".class.php";
		else if ($full_ext === '.patch.php')
			$write_to_file_name = substr($file, 0, -strlen($full_ext)).".class.php";
		else
		{
			qvar_dumpk($file, $full_ext, $write_to_file_name);
			throw new \Exception('Unexpected extension: '.$full_ext.' in: '.$layer.$file);
		}
		
		$this->upgrade_copy_file($layer, $write_to_file_name, (string)$file_tok);
		/**
		 *	1. rename it to CLASS_$layer_
		 *	2. inside it set ** @class.name CLASS *
		 *	3. patching classes must ` extend ` prev_patched class name if not first
		 */
		
		// update the cache for the next go
		foreach ($class_tok->methods ?: [] as $m_name => $c_meth)
			$toks_cache_methods[$full_class_name][$m_name] = $c_meth;

		return $new_class_name;
	}
	
	public function upgrade_copy_file(string $layer, string $file, string $content = null)
	{
		$upgrade_path = $this->upgrage_dir . (substr($layer, strlen($this->upgrade_inside_dir))).$file;
		
		if (!is_dir(dirname($upgrade_path)))
			qmkdir(dirname($upgrade_path));
		
		if ($content !== null)
			file_put_contents($upgrade_path, $content);
		else
			copy($layer.$file, $upgrade_path);
		
		touch($upgrade_path, filemtime($layer.$file));
	}
}
