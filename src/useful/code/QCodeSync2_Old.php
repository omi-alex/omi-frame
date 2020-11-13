<?php

trait QCodeSync2_Old
{
	function sync()
	{
		// @TODO - $removed_files - cleanup gens !

		// run the code also
		qvar_dumpk('compile fun !!!');
		// now what ?!
		// qvar_dumpk($files, $changed_or_added, $removed_files, $new_files);
		$autoload = [];
		$js_mirrors = [];
		$css_mirrors = [];
		$class_architecture = [];
		$classes_parents_per_layer = [];
		$implements = [];

		foreach ($files as $layer => $layer_files)
		{
			$resolved = [];
			foreach ($layer_files as $file => $f_mdate)
			{
				$last_ext = (($p = strrpos($file, '.')) !== false) ? substr($file, $p) : null;
				$second_ext = ($last_ext && (($p = strrpos(substr($file, 0, -strlen($last_ext)), '.')) !== false)) ? substr(substr($file, 0, -strlen($last_ext)), $p) : null;

				if (($last_ext === '.php') && ($second_ext === null))
				{
					// plain PHP ... set it in the autoload
					$header_inf = \QPHPToken::ParseHeaderOnly($layer.$file);
					if (!$header_inf["class"])
						throw new \Exception('Unable to identify class/trait/interface name in file: '.$layer.$file);
					$full_class_name = ($header_inf["namespace"] ? $header_inf["namespace"]."\\" : '').$header_inf["class"];
					$autoload[$full_class_name] = $layer.$file;
					$classes_parents_per_layer[$layer][$full_class_name] = $header_inf["extends"] ?: false;
					$implements[$full_class_name] = $header_inf["implements"] ?: false;
				}
				else if (($last_ext === '.php') && ($second_ext === '.class'))
				{
					// look for const also
					// update the file + getters & setters
					$ret = $this->sync_class_file($class_architecture, $layer, $file, $autoload);
					// for classes we don't do much
					if (is_array($ret) && ($ret[0] === 'is_class'))
					{
						$file_tokens = $ret[1];
						$class_tokens = $ret[2];
						$class_namespace = $file_tokens->getNamespace();
						$full_class_name = ($class_namespace ? $class_namespace."\\" : '').$class_tokens->className;
						$autoload[$full_class_name] = $layer.$file;
						$classes_parents_per_layer[$layer][$full_class_name] = $class_tokens->extends ?: false;
						$implements[$full_class_name] = $header_inf->implements ?: false;
					}
				}
				else if (($last_ext === '.php') && ($second_ext === '.const'))
				{
					// ignore ... used by  '.class'
				}
				else if ($last_ext === '.tpl')
				{
					// next stage for view elements
				}
				else if ($last_ext === '.js')
				{
					$js_mirrors[$layer.$file] = $layer.$file;
				}
				else if ($last_ext === '.css')
				{
					$css_mirrors[$layer.$file] = $layer.$file;
				}
				else if (($last_ext === '.php') && ($second_ext === '.url'))
				{
					// next stage for view elements
				}
				else if (($last_ext === '.php') && ($second_ext === '.dyn'))
				{
					// deprecated
				}
				else if (($last_ext === '.php') && ($second_ext === '.gen'))
				{
					// skip !
				}
				else
				{
					qvar_dumpk("resolve {$file} => {$f_mdate}", $last_ext, $second_ext, $autoload);
					die;
				}
			}
		}

		// qvar_dumpk($autoload);
		$temp_code_dir = \QAutoload::GetTempFolder();
		if (!is_dir($temp_code_dir))
			qmkdir($temp_code_dir);

		file_put_contents($temp_code_dir."autoload.php", "<?php\n\n\$_Q_FRAME_LOAD_ARRAY = ".var_export($autoload, true).";");
		file_put_contents($temp_code_dir."classes_parents.php", "<?php\n\n\$Q_CLASS_PARENTS_SAVE = ".var_export($classes_parents_per_layer, true).";");
		file_put_contents($temp_code_dir."implements.php", "<?php\n\n\$_Q_FRAME_IMPLEMENTS_ARRAY = ".var_export($implements, true).";");

		# @TODO $EXTENDED_BY

	}
	
	
	public function sync_class_file(array &$class_architecture, string $layer, string $file, array &$autoload)
	{
		// look for const also
		// update the file + getters & setters
		$file_tok = \QPHPToken::ParsePHPFile($layer.$file, false, false);
		$class_tok = $file_tok->findFirstPHPTokenClass();
		
		if ($class_tok->type === 'class')
			return ['is_class', $file_tok, $class_tok];
		
		if (!$class_tok)
			throw new \Exception('Missing trait in: '.$layer.$file);
		$namespace = $file_tok->getNamespace();
		
		$doc_comment_info = $class_tok->docComment ? $this->parse_doc_comment((string)$class_tok->docComment) : null;
		
		$class_name = null;
		if (isset($doc_comment_info['class.name'][1]))
			$class_name = trim(rtrim(trim($doc_comment_info['class.name'][1]), "*"));
		else
		{
			$m = null;
			$rc = preg_match("/^(.*)\\_Trait(?:.*)?\\_?\$/u", $class_tok->className, $m);
			if (!($rc && isset($m[1])))
				throw new \Exception('The name of the trait must be {CLASS_NAME}_Trait or {CLASS_NAME}_Trait_ or {CLASS_NAME}_Trait_Something');
			$class_name = $m[1];
		}
		
		$full_trait_name = ($namespace ? $namespace."\\" : '').$class_tok->className;
		$full_class_name = ($namespace ? $namespace."\\" : '').$class_name;
		
		// for constants
		$consts_file = substr($layer.$file, 0, -strlen('.class.php')).'.const.php';
		$iface_tok = file_exists($consts_file) ? \QPHPToken::ParsePHPFile($consts_file, false, false) : null;
		
		if (isset($class_architecture[$full_class_name][$layer]))
			throw new \Exception("Duplicate class `{$full_class_name}` in layer `{$layer}`, file: {$file}");
		else
			$class_architecture[$full_class_name][$layer] = [];
		
		$class_struct = &$class_architecture[$full_class_name][$layer];
		$class_struct['class.file'] = $layer.$file;
		$class_struct['trait.name'] = $class_tok->className;
		$class_struct['namespace'] = $namespace;
		
		if ($class_tok->docComment)
		{
			foreach ($doc_comment_info ?: [] as $k => $v)
			{
				if ((substr($k, 0, 6) === 'class.') || ($k === '#description'))
					$class_struct[$k] = trim(rtrim(trim($v[1]), "*"));
			}
		}
		
		$str_constants = [];
		
		if ($iface_tok)
		{
			$class_struct['const.file'] = $consts_file;
			
			$iface_class_tok = $iface_tok->findFirstPHPTokenClass();
			if (!$iface_class_tok)
				throw new \Exception('Unable to find interface in file: '.$consts_file);
			
			foreach ($iface_class_tok->constants ?: [] as $const_name => $constant)
			{
				$class_struct['@constants'][$const_name] = ["name" => $const_name];
				$str_constants[$const_name] = (string)$constant;
			}
		}
		
		foreach ($class_tok->properties ?: [] as $prop_name => $prop)
		{
			$class_struct['@properties'][$prop_name] = ["name" => $prop_name];
		}
		
		foreach ($class_tok->methods ?: [] as $meth_name => $method)
		{
			$class_struct['@methods'][$meth_name] = ["name" => $meth_name];
		}
		
		// save it to file for this layer
		$class_str = "<?php\n";
		if ($namespace)
			$class_str .= "\nnamespace {$namespace};\n";
		/*
				@class.name
				@class.extends
				@class.implements
				@class.abstract
				@class.final
				@class.is_patch
		 */
		$class_str .= "\n";
		if ($class_struct["#description"])
			$class_str .= "/**\n ".trim($class_struct["#description"])."\n */\n";
		if ($class_struct["class.final"])
			$class_str .= "final ";
		if ($class_struct["class.abstract"])
			$class_str .= "abstract ";
		$class_str .= "class ".$class_name;
		if ($class_struct["class.extends"])
			$class_str .= " extends ".$class_struct["class.extends"];
		if ($class_struct["class.implements"])
			$class_str .= " implements ".$class_struct["class.implements"];
		$class_str .= "\n{\n";
		
		$class_str .= "\tuse ".$class_tok->className.";\n\n";
		
		foreach ($str_constants ?: [] as $constat_str)
			// @TODO - better tabbing
			$class_str .= $constat_str."\n";
		
		$class_str .= "\n}\n";
		
		$save_path_dir = dirname($layer.$file)."/gen/";
		if (!is_dir($save_path_dir))
			qmkdir($save_path_dir);
		$save_path = $save_path_dir.substr(basename($layer.$file), 0, -strlen('.class.php')).'.gen.php';
		
		file_put_contents($save_path, $class_str);
		
		$autoload[$full_class_name] = $save_path;
		$autoload[$full_trait_name] = $layer.$file;
				
		unset($class_struct);
	}

	
	function sync_code__group_by_full_class_name()
	{
		$this->extends_map = [];
		$has_changes = $this->full_sync ? true : false;
		
		qvar_dumpk('$this->changes_by_class', $this->changes_by_class);
		die;
		
		$last_layer = null;
		foreach ($this->grouped_data as $gd_layer => $gd_dirs)
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
					$has_php = null;
					
					$resources = [];
					
					foreach ($gd_class_files as $gd_file_name => &$header_inf)
					{
						$was_added = $header_inf['added'];
						$was_removed = $header_inf['removed'];
						
						if ($was_removed)
							throw new \Exception('@TODO - $was_removed');
						
						if ($header_inf['namespace'] && ((!$namespace) || ($namespace === $header_inf['namespace'])))
							$namespace = $header_inf['namespace'];
						// else if ($header_inf['namespace'] && ($header_inf['namespace'] !== $namespace))
						//	throw new \Exception('Namespace mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['final_class'] && ((!$class) || ($class === $header_inf['final_class'])))
							$class = $header_inf['final_class'];
						else if ($header_inf['final_class'] && ($header_inf['final_class'] !== $class))
							throw new \Exception('Class mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['extends'] && ((!$extends) || ($extends === $header_inf['extends'])))
							$extends = $header_inf['extends'];
						//else if ($header_inf['extends'] && ($header_inf['extends'] !== $extends))
						//	throw new \Exception('Extends mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						
						if ($header_inf['implements'] && ((!$implements) || ($implements === $header_inf['implements'])))
							$implements = $header_inf['implements'];
						//else if ($header_inf['implements'] && ($header_inf['implements'] !== $implements))
						//	throw new \Exception('Implements mistmatching '.$gd_layer.$gd_dir_name.$gd_file_name);
						if ($header_inf['is_php'])
							$has_php = true;
					}
					
					// test if it extends
					$full_class_name = \QPHPToken::ApplyNamespaceToName($class, $namespace);

					# if no extends is present up to this layer for a TPL, we make sure it will at least extend `QWebControl`
					if ((!$extends) && $has_tpl && ((!$is_patch) || $has_php) && (!$this->info_by_class[$full_class_name]['extends']))
					{
						$extends_full = $extends = 'QWebControl';
						foreach ($gd_class_files as $gd_file_name => &$header_inf)
							$header_inf['extends'] = $header_inf['namespace'] ? '\\QWebControl' : 'QWebControl';
					}
					else
					{
						$extends_full = $extends ? \QPHPToken::ApplyNamespaceToName($extends, $namespace) : null;
					}
					
					if ($extends)
					{
						$this->extends_map[$full_class_name] = $extends_full;
						if (!$this->info_by_class[$full_class_name]['extends'])
							$this->info_by_class[$full_class_name]['extends'] = $extends_full;
					}

					foreach ($gd_class_files as $gd_file_name => &$header_inf)
					{
						// we must ensure proper namespace to all info!
						if ($namespace && (!$header_inf['namespace']))
							$header_inf['namespace'] = $namespace;
						$header_inf['class_full'] = \QPHPToken::ApplyNamespaceToName($header_inf['class'], $namespace);
						if ($extends_full)
							$header_inf['extends_full'] = $extends_full;
							
						$locations[$header_inf['file']] = $header_inf;
						
						if ($extends_full && ($class !== $header_inf['class']))
							$this->extends_map[$header_inf['class_full']] = $extends_full;
						
						if ($header_inf['type'] === 'resource')
							$this->info_by_class[$full_class_name]['res'][$header_inf['res_type']][] = $header_inf;
					}
					
					if ($has_tpl)
						$this->info_by_class[$full_class_name]['has_tpl'] = true;
					if ($has_url)
						$this->info_by_class[$full_class_name]['has_url'] = true;
					if ($is_patch)
						$this->info_by_class[$full_class_name]['is_patch'] = true;
					foreach ($locations as $loc => $h_info)
					{
						if (isset($this->info_by_class[$full_class_name]['files'][$this->watch_folders_tags[$gd_layer]][$h_info['tag']]))
						{
							qvar_dumpk($full_class_name, $gd_layer, $h_info['tag']);
							throw new \Exception('This should not duplicate');
						}
						$this->info_by_class[$full_class_name]['files'][$this->watch_folders_tags[$gd_layer]][$h_info['tag']] = $h_info;
					}
				}
			}
		
			$last_layer = $gd_layer;
		}
		
		if ($has_changes)
			file_put_contents($this->temp_code_dir."sync_cache_grouped_data.php", "<?php\n\n\$_DATA = ".var_export($this->grouped_data, true).";\n");
	}
	
}
