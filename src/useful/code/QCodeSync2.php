<?php

/**
 * Generates and patches platform standards
 */
class QCodeSync2
{
	use QCodeSync2_Upgrade, QCodeSync2_Utility, QCodeSync2_Generate;
	
	public static $PHP_LINT_CHECK = true;
	public static $PHP_LINT_CHECK_TPL = true;
	
	public $upgrage_mode = null;
	public $full_sync = true;
	
	protected $temp_code_dir;
	
	/**
	 * Resyncs the code
	 * 
	 * @param array $files List with all the files
	 * @param array $changed_or_added List with the changed or added files
	 * @param array $removed_files List with the removed files
	 */
	public function resync(&$files, $changed_or_added, $removed_files, $new_files)
	{
		if ($this->upgrage_mode === null)
		{
			if (defined('Q_RUN_CODE_UPGRADE_TO_TRAIT') && Q_RUN_CODE_UPGRADE_TO_TRAIT)
				$this->upgrage_mode = true;
		}
		
		if ($this->upgrage_mode)
		{
			$this->full_sync = true;
			$this->run_upgrade($files, $changed_or_added, $removed_files, $new_files);
			// exit after upgrade
			return;
		}
		
		if (defined('Q_RUN_CODE_NEW_AS_TRAITS') && Q_RUN_CODE_NEW_AS_TRAITS)
		{
			$this->sync_code($files, $changed_or_added, $removed_files, $new_files);
		}
	}
	
	/**
	 * Resyncs the code
	 * 
	 * @param array $files List with all the files
	 * @param array $changed_or_added List with the changed or added files
	 * @param array $removed_files List with the removed files
	 */
	public function sync_code(&$files, $changed_or_added, $removed_files, $new_files)
	{
		$t0 = microtime(true);
		
		$this->temp_code_dir = "temp/code/";
		if (!is_dir($this->temp_code_dir))
			qmkdir($this->temp_code_dir);
		
		// new steps ... 
		
		$watch_folders_tags = array_flip(\QAutoload::GetWatchFoldersByTags());
		
		# STAGE 1 - Collect information
		$grouped_data = $this->sync_code__collect_info($files, $watch_folders_tags);
		
		# STAGE 2 - Group information by class
		$info_by_class = $this->sync_code__group_by_full_class_name($grouped_data);
		
		# STAGE 3 - PRE Compile - we make sure that we can boot up PHP classes so that we can use native reflection
		$tasks_to_run = $this->sync_code__pre_compile($info_by_class, $watch_folders_tags);
		
		# STAGE 4 - create the required traits for : model (getters/setters & misc), views (templates/resources), url controllers
		$this->sync_code__compile($tasks_to_run);
		
		$t2 = microtime(true);
		die("hop! ".($t2  - $t0));
	}
	
	function sync_code__collect_info(array $files, array $watch_folders_tags)
	{
		$lint_info = null;
		
		$grouped_data = [];
		# STAGE 1 - Collect information from files
		foreach ($files as $layer => $layer_files)
		{
			$layer_tag = $watch_folders_tags[$layer];
			if (!$layer_tag)
				throw new \Exception('Missing tag for code folder: '.$layer);
			if (static::$PHP_LINT_CHECK)
				$this->check_syntax($layer, $layer_files, $lint_info);
			
			foreach ($layer_files as $file => $mtime)
			{
				$is_php_ext = (substr($file, -4, 4) === '.php');
				$is_tpl_ext = (substr($file, -4, 4) === '.tpl');
				list ($short_file_name, $full_ext) = explode(".", basename($file), 2);
				$full_ext = ".".$full_ext;
				
				if (!(strtolower($short_file_name{0}) !== $short_file_name{0}))
				{
					// this is to fix a bug for files like: 01-mvvm.js
					continue;
				}
				
				if ($is_php_ext && ((substr($file, -8, 8) === '.gen.php') || substr($file, -8, 8) === '.dyn.php'))
					// just skip!
					continue;
				else if (!($is_php_ext || $is_tpl_ext))
				{
					// css, js ... 
					$short_class_name = $short_file_name;
					if (($full_ext === '.js') || ($full_ext === '.css'))
					{
						$key = '99-';
						$grouped_data[$layer][dirname($file)."/"][$short_class_name][$key.basename($file)] = [
								"class" => $short_class_name, 'type' => 'resource', 'res_type' => trim($full_ext, '.'), 'file' => $file,
								"final_class" => $short_class_name];
					}
					else if (($full_ext === '.min.js') || ($full_ext === '.min.css'))
					{
						// skip these
					}
					else 
						throw new \Exception('Unexpected resource type: `'.$full_ext.'` in: '.$layer.$file);
				}
				else
				{
					// echo "Evaluating: ".$layer.$file."<br/>\n";
					// plain PHP ... set it in the autoload
					$header_inf = \QPHPToken::ParseHeaderOnly($layer.$file, false);
					if (!isset($header_inf["class"]))
						throw new \Exception('Unable to identify short class name in: '.$layer.$file);
					$header_inf['is_tpl'] = $is_tpl_ext;
					$header_inf['is_url'] = $is_php_ext && (substr($file, -8, 8) === '.url.php');
					$header_inf['is_php'] = $is_php_ext && (!$header_inf['is_url']);
					$header_inf['is_patch'] = $is_php_ext && ((!$header_inf['is_url']) && ($full_ext !== '.php'));
					$header_inf['file'] = $file;
					
					if ($header_inf['class.abstract'])
					{
						qvar_dumpk($layer, $file, $header_inf);
						die;
					}
					
					// @TODO - set changed only if it happen
					$header_inf['changed'] = true;
					
					$short_class_name = (($p = strrpos($header_inf["class"], "\\")) !== false) ? substr($header_inf["class"], $p + 1) : $header_inf["class"];
					
					$final_class_name = $short_class_name;
					if ($header_inf['is_patch'])
						$final_class_name = $short_file_name;
					else if ($header_inf['is_php'])	
					{
						if (isset($header_inf['doc_comment']) && strpos($header_inf['doc_comment'], "@class.name") && 
								($parsed_dc = $this->parse_doc_comment($header_inf['doc_comment'])) && $parsed_dc['class.name'])
						{
							$final_class_name = trim(trim(trim($parsed_dc['class.name'][1]), "* \t\n"));
						}
						else if (substr($short_class_name, -strlen("_".$layer_tag."_")) === "_".$layer_tag."_")
						{
							$final_class_name = substr($short_class_name, 0, -strlen("_".$layer_tag."_"));
							qvar_dumpk('@TODO - this code is not tested ! $final_class_name #02', $final_class_name, $short_class_name, $layer_tag, $layer, $header_inf);
							die;
						}
						if ($final_class_name !== $short_class_name)
							$header_inf['is_patch'] = true;
					}
					$header_inf['final_class'] = $final_class_name;
					
					if ($header_inf['is_patch'] && ($header_inf['class'] === $final_class_name))
						throw new \Exception('Can not compile: `'.$layer.$file.'` because the name of class will conflict with the compiled class\'s name');
					
					$key = $header_inf['is_php'] ? '01-' : ($header_inf['is_url'] ? '02-' : '03-');
					$grouped_data[$layer][dirname($file)."/"][$final_class_name][$key.basename($file)] = $header_inf;
				}
			}
		}
		
		return $grouped_data;
	}
	
	function sync_code__group_by_full_class_name(array $grouped_data)
	{
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
						
						if ($header_inf['is_tpl'])
							$has_tpl = true;
						if ($header_inf['is_url'])
							$has_url = true;
						if ($header_inf['is_patch'])
							$is_patch = true;
						
						// if ($header_inf['is_patch'] || $header_inf['is_php'])
							// only php / patch are to be converted if needed
						$locations[$header_inf['file']] = $header_inf;
					}
					
					// test if it extends 
					$full_class_name = \QPHPToken::ApplyNamespaceToName($class, $namespace);
					
					if ($extends && (!$info_by_class[$full_class_name]['extends']))
						$info_by_class[$full_class_name]['extends'] = \QPHPToken::ApplyNamespaceToName($extends, $namespace);
					if ($has_tpl)
						$info_by_class[$full_class_name]['has_tpl'] = true;
					if ($has_url)
						$info_by_class[$full_class_name]['has_url'] = true;
					if ($is_patch)
						$info_by_class[$full_class_name]['is_patch'] = true;
					foreach ($locations as $loc => $h_info)
						$info_by_class[$full_class_name]['files'][$gd_layer][$loc] = $h_info;
				}
			}
		}

		return $info_by_class;
	}
	
	function sync_code__pre_compile(array $info_by_class, array $watch_folders_tags)
	{
		$toks_cache_methods = [];
		
		$autoload = [];
		$js_mirrors = [];
		$css_mirrors = [];
		$class_architecture = [];
		$classes_parents_per_layer = [];
		$implements = [];
		
		$tasks_to_run = [];
				
		foreach ($info_by_class as $full_class_name => $info)
		{
			$generate_from_class = null;
			$generate_from_class_ns = null;

			$render_methods = [];
			$resources = [];
			$url_files = [];
			
			$is_patch = false;
			$is_model = null;
			$is_view = false;
			$is_controller = false;
			$has_plain_class = false;
			$last_layer = null;
			$first_file_in_last_layer = null;
			
			$patch_extends_info = null;
			$patch_extends = null;
			
			$classes_files = [];
			
			foreach ($info['files'] ?: [] as $layer => $files_list)
			{
				$last_layer = $layer;
				$first_file_in_last_layer = null;
				foreach ($files_list as $file => $header_inf)
				{
					if (!$first_file_in_last_layer)
						$first_file_in_last_layer = $header_inf['file'];
					if ($header_inf['is_tpl'])
					{
						$is_view = true;
						$render_methods[basename($header_inf['file'])] = $header_inf;
						if (!$patch_extends_info)
							$patch_extends_info = $header_inf;
						if (!$patch_extends)
							$patch_extends = \QPHPToken::ApplyNamespaceToName($header_inf['class'], $header_inf['namespace']);
					}
					else if ($header_inf['is_url'])
					{
						$is_controller = true;
						// logic for the URL controller
						$url_files[] = $header_inf;
						if (!$patch_extends_info)
							$patch_extends_info = $header_inf;
						if (!$patch_extends)
							$patch_extends = \QPHPToken::ApplyNamespaceToName($header_inf['class'], $header_inf['namespace']);
					}
					else if ($header_inf['res_type'])
					{
						$resources[$header_inf['res_type']] = $header_inf;
					}
					else if ($header_inf['is_php'])
					{
						if ($header_inf['is_patch'])
						{
							$is_patch = true;
							$is_model_tmp = ($header_inf['extends'] && $this->check_if_class_extends($full_class_name, $header_inf['namespace'], 'QModel', $info_by_class, $header_inf['extends']));
							if (($is_model !== null) && ($is_model !== $is_model_tmp))
								throw new \Exception('A class was a model in a previous layer and now it is not: '.$layer.$header_inf['file']);
							else
								$is_model = $is_model_tmp;
							$patch_extends_info = $header_inf;
							$patch_extends = \QPHPToken::ApplyNamespaceToName($header_inf['class'], $header_inf['namespace']);
							
							$autoload[$patch_extends] = realpath($layer.$header_inf['file']);
						}
						else
						{
							// no op
							$has_plain_class = $layer.$header_inf["file"];
							$classes_files[] = $layer.$header_inf["file"];
						}
					}
					else
					{
						qvar_dumpk($full_class_name, $layer, $file, $header_inf);
						throw new \Exception('Not expected');
					}
				}
				// at the very end we create a `compiled` version
			}
			
			if ($is_patch || ($resources && (!$has_plain_class)) || $url_files || $render_methods)
			{
				# ($resources && (!$has_plain_class)) => we will allow resources for plain classes!
				if ($has_plain_class)
					throw new \Exception("Can not compile class `{$full_class_name}` because the definition in file "
											. "	`{$has_plain_class}` already uses the desired compile name.");
				
				$gens_path = dirname($first_file_in_last_layer)."/~gens/";
				$short_class_name = end(explode("\\", $full_class_name));
				
				$save_to_dir = $last_layer.$gens_path;
				if (!is_dir($save_to_dir))
					qmkdir($save_to_dir);
				
				$class_path_full = $this->ensure_class($full_class_name, $short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info);
				$autoload[$full_class_name] = $class_path_full;
				
				$element_tasks = [];

				if ($is_model && (!($is_view || $is_controller)))
					$element_tasks['model'] = true;
				if ($is_view || $resources)
				{
					if (!$is_view)
					{
						// @TODO - resources only !
					}
					else
					{
						$element_tasks['view'] = [$render_methods, $resources];
					}
				}
				if ($is_controller)
					$element_tasks['url'] = $url_files;
				
				if ($element_tasks)
					$tasks_to_run[$full_class_name] = [$short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info, $element_tasks];
			}
			else if ($has_plain_class)
			{
				if (count($classes_files) !== 1)
					throw new \Exception('Too many definitions in files for the same class: '.$full_class_name." | ".implode("; ", $classes_files));
				$class_path_full = realpath(reset($classes_files));
				$autoload[$full_class_name] = $class_path_full;
			}
		}
		
		\QAutoload::SetAutoloadArray($autoload);
		
		return $tasks_to_run;
	}
	
	function sync_code__compile($tasks_to_run)
	{
		// qvar_dumpk('sync_code__compile()', class_exists('Omi\VF\View\Customers'), $tasks_to_run);
		// die;
		foreach ($tasks_to_run as $full_class_name => $data)
		{
			$add_traits = [];
		
			list($short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info, $element_tasks) = $data;
			foreach ($element_tasks as $task_name => $task_info)
			{
				if ($task_name === 'model')
				{
					$m_trait = $this->compile_model($full_class_name, $short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info);
					if ($m_trait)
					 	$add_traits[$m_trait] = $m_trait;
				}
				else if ($task_name === 'view')
				{
					$v_trait = $this->compile_view($full_class_name, $short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info, $task_info[0], $task_info[1]);
					if ($v_trait)
						$add_traits[$v_trait] = $v_trait;
					$complile_class_needed = true;
				}
				else if ($task_name === 'url')
				{
					$v_trait = $this->compile_view($full_class_name, $short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info, $task_info);
					if ($v_trait)
						$add_traits[$v_trait] = $v_trait;
					$complile_class_needed = true;
				}
				else 
					throw new \Exception('Unknown task: '.$task_name);
			}
			
			if ($add_traits)
			{
				// qvar_dumpk('$add_traits', $add_traits);
				
				$this->compile_class($full_class_name, $short_class_name, $last_layer, $gens_path, $patch_extends, $patch_extends_info, $add_traits);
			}
		}
	}
	
	function ensure_class(string $full_class_name, string $short_class_name, string $layer, string $path, string $extend_class, array $extends_info)
	{
		$gen_path = $layer.$path.$short_class_name.".gen.php";
		$expected_content = $this->compile_setup_class($full_class_name, $short_class_name, $extend_class, $extends_info['namespace'], $extends_info['doc_comment']);
		
		# in case the file does not exist, or the begining is not what we expect, reset it
		if ((!file_exists($gen_path)) || (substr(file_get_contents($gen_path, 0, strlen($expected_content[0]))) !== $expected_content[0]))
		{
			file_put_contents($gen_path, implode("", $expected_content));
			opcache_invalidate($gen_path, true);
		}
		if (!file_exists($gen_path))
			throw new \Exception('Unable to setup class file: '.$gen_path);
		return realpath($gen_path);
	}
	
	function compile_class(string $full_class_name, tring $short_class_name, string $layer, string $path, string $extend_class, array $extends_info, array $add_traits)
	{
		$gen_path = $layer.$path.$short_class_name.".gen.php";
		echo "compile_class: ",$gen_path,"<br/>\n"; 
		// the class itself, extends, pull from the last class the doc comment so it's not lost ! & namespace 
		// getters/setters
		// (deprecated) api methods | I think this is deprecated !
		$class_parts = $this->compile_setup_class($full_class_name, $short_class_name, $extend_class, $extends_info['namespace'], $extends_info['doc_comment']);
		$class_str = $class_parts[0];
		if ($add_traits)
			$class_str .= "\tuse ".implode(", ", $add_traits).";\n\n";
		$class_str .= $class_parts[1];
		
		file_put_contents($gen_path, $class_str);
		opcache_invalidate($gen_path, true);
		
		return $class_str;
	}
	
	function compile_model(string $full_class_name, string $short_class_name, string $layer, string $path, string $extend_class, array $extends_info)
	{
		$setter_methods = $this->generate_model_methods(new ReflectionClass($full_class_name));
		
		if ($setter_methods) # later add || $security_methods ... and so on
		{
			$tait_name = $short_class_name."_GenModel_";
			list ($trait_start_str, $trait_end_str) = $this->compile_setup_trait($tait_name, $extends_info['namespace']);

			foreach ($setter_methods as $method_str)
				$trait_start_str .= $method_str;

			$trait_body_str = "\t# trait body here";
			file_put_contents($layer.$path.$short_class_name.".model.gen.php", $trait_start_str.$trait_body_str.$trait_end_str);

			return $tait_name;
		}
		else
			return null;
	}
	
	function compile_view(string $full_class_name, string $short_class_name, string $layer, string $path, string $extend_class, array $extends_info)
	{
		qvar_dumpk('compile_view', $full_class_name, class_exists($full_class_name));
		die;
		
		$tait_name = $short_class_name."_GenView_";
		list ($trait_start_str, $trait_end_str) = $this->compile_setup_trait($tait_name, $extends_info['namespace']);
		
		$trait_body_str = "\t# trait body here";
		file_put_contents($layer.$path.$short_class_name.".view.gen.php", $trait_start_str.$trait_body_str.$trait_end_str);
		
		return $tait_name;
	}
	
	function compile_controller(string $full_class_name, string $short_class_name, string $layer, string $path, string $extend_class, array $extends_info)
	{
		qvar_dumpk('compile_controller', $full_class_name, class_exists($full_class_name));
		die;
		
		$tait_name = $short_class_name."_GenCtrl_";
		list ($trait_start_str, $trait_end_str) = $this->compile_setup_trait($tait_name, $extends_info['namespace']);
		
		$trait_body_str = "\t# trait body here";
		file_put_contents($layer.$path.$short_class_name.".url.gen.php", $trait_start_str.$trait_body_str.$trait_end_str);
		
		return $tait_name;
	}
	
	function compile_setup_trait(string $short_name, string $namespace = null)
	{
		$class_str = "<?php\n\n";
		if ($namespace)
			$class_str .= "namespace {$namespace};\n\n";
		$class_str .= "trait {$short_name}";
		$class_str .= "\n{\n\n";
		
		return [$class_str, "\n}\n\n"];
	}
	
	function compile_setup_class(string $full_class_name, string $short_name, string $extend_class, string $namespace = null, string $doc_comment = null)
	{
		$class_str = "<?php\n\n";
		if ($namespace)
			$class_str .= "namespace {$namespace};\n\n";
		if ($doc_comment)
			$class_str .= trim($doc_comment)."\n";
		$short_extends = ($namespace && substr($extend_class, 0, strlen($namespace) + 1) === $namespace."\\") ? substr($extend_class, strlen($namespace) + 1) : 
				($namespace ? "\\".$extend_class : $extend_class);
		
		$class_str .= "class {$short_name}".(($full_class_name !== $extend_class) ? " extends {$short_extends}" : '');
		$class_str .= "\n{\n\n";
				
		return [$class_str, "\n}\n\n"];
	}
}
