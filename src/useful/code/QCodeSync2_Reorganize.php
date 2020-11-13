<?php

trait QCodeSync2_Reorganize
{
	protected $reorganize_ns_to_path = [];
	
	public static function Reorganize(string $code_folder = 'code/', string $target_dir = null, string $frame_layer_tag = 'frame', string $backend_tag = 'backend')
	{
		$code_sync = new \QCodeSync2();
		$code_sync->reorganize_do();
	}
	
	protected function reorganize_do(string $code_folder = 'code/', string $target_dir = null, string $frame_layer_tag = 'frame', string $backend_tag = 'backend')
	{
		$this->init();
		$this->boot_info_by_class();
		
		$this->reorganize_ns_to_path = [];
		
		if ($target_dir === null)
			$target_dir = '../'.basename(getcwd()).'~reorganized~/';
		if (!is_dir($target_dir))
			qmkdir($target_dir);
		$target_dir = realpath($target_dir).'/';
		$this->reorganize_dir = $target_dir;
		# reset the log
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "START: ".date('Y-m-d H:i:s')."\n\n");
		
		$frame_full_path = $this->tags_to_watch_folders[$frame_layer_tag];
		if (!is_dir($frame_full_path))
			throw new \Exception('Unable to identify frame\'s folder.');
		$code_full_path = realpath($code_folder).'/';
		if (!is_dir($code_full_path))
			throw new \Exception('Unable to identify code\'s folder.');
		$backend_full_path = $this->tags_to_watch_folders[$backend_tag];
		if (!is_dir($backend_full_path))
			throw new \Exception('Unable to identify backend\'s folder.');
		
		$classes_path = $target_dir.'~includes/classes/';
		$model_path = $target_dir.'~includes/model/';
		$ui_path = $target_dir.'~includes/view/';
		$controllers_path = $target_dir.'~includes/controller/';
		$backend_path = $target_dir.'~includes/generated-ui/';
		if (!is_dir($classes_path))
			qmkdir($classes_path);
		if (!is_dir($model_path))
			qmkdir($model_path);
		if (!is_dir($ui_path))
			qmkdir($ui_path);
		if (!is_dir($controllers_path))
			qmkdir($controllers_path);
		if (!is_dir($backend_path))
			qmkdir($backend_path);
		
		foreach ($this->info_by_class as $full_class_name => $info)
		{
			$p = strrpos($full_class_name, "\\");
			$namespace = ($p !== false) ? substr($full_class_name, 0, $p) : null;
			$short_class_name = ($p !== false) ? substr($full_class_name, $p + 1) : $short_class_name;
			
			$layers_count = count($info['files']);
			$is_model = $info['is_model'];
			$is_patch = $info['is_patch'];
			$has_php = $info['has_php'];
			$has_tpl = $info['has_tpl'];
			$has_url = $info['has_url'];
			
			$is_view = ($has_tpl || ((!$has_url) && $this->check_if_qview_base($full_class_name)));
			
			$files_by_role = [];
			$files_by_role_frame = [];
			$files_by_role_backend = [];
			
			foreach ($info['files'] as $layer_tag => $layer_files)
			{
				if ($frame_layer_tag === $layer_tag)
					continue;
				
				$layer_path = $this->tags_to_watch_folders[$layer_tag];
				if (!$layer_path)
					throw new \Exception('Missing layer path for tag: '.$layer_tag);
				
				foreach ($layer_files as $file_tag => $header_inf)
				{
					$full_path = $layer_path.$header_inf['file'];
					$header_inf['layer_path'] = $layer_path;
					if (substr($full_path, 0, strlen($frame_full_path)) === $frame_full_path)
					{
						$files_by_role_frame[$file_tag][$layer_tag] = $header_inf;
					}
					else if (substr($full_path, 0, strlen($backend_full_path)) === $backend_full_path)
					{
						# in code ... do the logic for it here
						$files_by_role_backend[$file_tag][$layer_tag] = $header_inf;
					}
					else if (substr($full_path, 0, strlen($code_full_path)) === $code_full_path)
					{
						# in code ... do the logic for it here
					}
					else
					{
						$files_by_role[$file_tag][$layer_tag] = $header_inf;
					}
				}
			}
			
			$copy_elements = [];
			$save_path = $is_view ? $ui_path : ($has_url ? $controllers_path : ($is_model ? $model_path : $classes_path));
			$namespace_rel_path = ($namespace ? $this->reorganize_get_path_for_namespace($namespace) : "");
			$save_path .= ($namespace_rel_path . ($is_view ? $short_class_name."/" : ''));
			$new_layer_tag = $is_view ? 'view' : ($has_url ? 'controller' : ($is_model ? 'model' : 'classes'));
			
			foreach ($files_by_role_backend as $file_tag => $layer_files)
			{
				foreach ($layer_files as $header_inf)
					$copy_elements[] = [$backend_path.$short_class_name."/", $header_inf];
			}
			
			foreach ($files_by_role as $file_tag => $layer_files)
			{
				if ($files_by_role_frame[$file_tag])
				{
					qvar_dumpk($full_class_name, $file_tag, $layer_files);
					throw new \Exception('Files from the frame must not be modified!');
				}
				
				$short_tag = (($p = strpos($file_tag, '@')) !== false) ? substr($file_tag, 0, $p) : $file_tag;

				if (count($layer_files) === 1)
				{
					$first_header_inf = reset($layer_files);
					# just as it is
					if (($short_tag === 'php') && ($first_header_inf['class'] !== $first_header_inf['final_class']))
					{
						# we need to set the class name
						$copy_elements[$file_tag] = [$save_path, $this->reorganize_join_php($full_class_name, $layer_files, $save_path, $new_layer_tag, $is_model)];
					}
					else
						$copy_elements[$file_tag] = [$save_path, reset($layer_files)];
				}
				else
				{
					$t_size = [];
					$t_starts = [];
					foreach ($layer_files as $lf)
					{
						$t_size[] = filesize($lf['layer_path'].$lf['file']);
						$t_str = trim(substr(file_get_contents($lf['layer_path'].$lf['file']), 0, 12));
						if (($p = strpos($t_str, "\n")) !== false)
							$t_str = trim(substr($t_str, 0, $p));
						$t_starts[] = $t_str;
					}
					$this->overlapping[$short_tag][] = 'count='.count($layer_files).' | '.'size='.implode('+', $t_size).' | '.$full_class_name.' # '.$file_tag." [".implode("; ", $t_starts)."] => ".substr($save_path, strlen($target_dir));
					
					if ($short_tag === 'tpl')
						// join the tpls into one
						$copy_elements[$file_tag] = [$save_path, $this->reorganize_join_tpl($full_class_name, $layer_files, $save_path)];
					else if ($short_tag === 'php')
					{
						if (!$is_patch)
						{
							qvar_dumpk($full_class_name, $layer_files, $layer_tag, $is_model);
							throw new \Exception('Not patched ?! check!');
						}
						// join the classes into one
						$copy_elements[$file_tag] = [$save_path, $this->reorganize_join_php($full_class_name, $layer_files, $save_path, $new_layer_tag, $is_model)];
					}
					else if ($short_tag === 'url')
						// join the urls into one
						$copy_elements[$file_tag] = [$save_path, $this->reorganize_join_url($full_class_name, $layer_files, $save_path)];
					else
						// join the resources into one
						$copy_elements[$file_tag] = [$save_path, $this->reorganize_join_resources($full_class_name, $layer_files, $save_path)];
				}
			}
			
			foreach ($copy_elements as $file_tag => $copy_info)
			{
				list ($dest_dir, $header_inf) = $copy_info;
				$src_content = $header_inf['src'];
				$src = ($src_content === null) ? $header_inf['layer_path'].$header_inf['file'] : null;
				if (($src !== null) && (!file_exists($src)))
				{
					qvar_dumpk($src, $header_inf);
					throw new \Exception('Src missing file');
				}
				
				if (!is_dir($dest_dir))
					qmkdir($dest_dir);
				$dest = $dest_dir.basename($header_inf['file']);
				
				if ($src_content !== null)
				{
					file_put_contents($dest, $src_content);
					echo "MERGE: {$header_inf['layer']}@{$header_inf['tag']} => {$dest}<br/>\n";
				}
				else
				{
					copy($src, $dest);
					if (substr($src, 0, strlen('/home/alex/public_html/vf-merge/vf-base-new/~backend/')) !== '/home/alex/public_html/vf-merge/vf-base-new/~backend/')
						echo "COPY: {$src} => {$dest}<br/>\n";
				}
			}
		}
		
		qvar_dumpk($this->overlapping);
		
		die("yooooupy!");
	}

	protected function reorganize_get_path_for_namespace(string $namespace)
	{
		if (($return = $this->reorganize_ns_to_path[$namespace]) !== null)
			return $return;
	
		$ns_saved = $namespace;
		if ($namespace === 'Omi\VF\View')
			return ($this->reorganize_ns_to_path[$ns_saved] = 'vf/');
		else if ($namespace === 'Omi')
			return ($this->reorganize_ns_to_path[$ns_saved] = '');
		else if (substr($namespace, 0, strlen('Omi\VF\View\\')) === 'Omi\VF\View\\')
			$namespace = "vf\\".substr($namespace, strlen('Omi\VF\View\\'));
		else if (substr($namespace, 0, strlen('Omi\\')) === 'Omi\\')
			$namespace = substr($namespace, strlen('Omi\\'));
		
		$namespace_parts = explode("\\", $namespace);
		$path = [];
		foreach ($namespace_parts as $ns)
		{
			$tmp_itm = preg_replace_callback('/(?<!\b)[A-Z][a-z]+|(?<=[a-z])[A-Z]/', function($match) {
				return '_'. $match[0];
			}, $ns);
			$tmp_itm = preg_replace('/[\\_\\s]+/', '_', $tmp_itm);
			
			$path[] = strtolower($tmp_itm);
		}
		
		$path_str = $path ? implode("/", $path).'/' : '';
		if (in_array($path_str, $this->reorganize_ns_to_path))
		{
			qvar_dumpk($path_str, $this->reorganize_ns_to_path, $ns_saved, $namespace);
			throw new \Exception('The namespace path is already in the list');
		}
		
		// echo "NS :: {$ns_saved} => {$path_str} <br/>\n";
		
		return ($this->reorganize_ns_to_path[$ns_saved] = $path_str);
	}
	
	protected function reorganize_join_php(string $full_class_name, array $layer_files, string $save_path, string $new_layer_tag = null, bool $is_model = false)
	{
		$final_tokens = null;
		
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE PHP START\n" , FILE_APPEND);
		foreach ($layer_files as $header_inf)
		{
			$fp = $header_inf['layer_path'].$header_inf['file'];
			if (!file_exists($fp))
				throw new \Exception('Missing src file');
			$tokens = \QPHPToken::ParsePHPFile($fp, false);
			
			if (!$final_tokens)
				$final_tokens = $tokens;
			else
			{
				$this->reorganize_join_php_classes($final_tokens, $tokens);
			}
			file_put_contents($this->reorganize_dir."reorganize_log.txt", "		REORGANIZE PHP {$header_inf['layer_path']}{$header_inf['file']} => {$save_path}\n" , FILE_APPEND);
		}
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE PHP DONE\n" , FILE_APPEND);
		
		# rename $final_tokens class name using the new layer tag
		$first_header_inf = reset($layer_files);
		if ($first_header_inf['class'] !== $first_header_inf['final_class'])
		{
			$new_class_name = $first_header_inf['final_class']."_".$new_layer_tag."_";
			$class = $final_tokens->findFirstPHPTokenClass();
			$class->setClassName($new_class_name);
		}
		
		$ret_header_inf = reset($layer_files);
		$ret_header_inf['src'] = $final_tokens->toString();
		
		return $ret_header_inf;
	}
	
	protected function reorganize_join_tpl(string $full_class_name, array $layer_files, string $save_path)
	{
		$final_tokens = '';
		
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE_APPENDING TPL START\n" , FILE_APPEND);
		foreach ($layer_files as $header_inf)
		{
			$fp = $header_inf['layer_path'].$header_inf['file'];
			if (!file_exists($fp))
				throw new \Exception('Missing src file');
			$final_tokens .= "\n<!-- REORGANIZE_APPENDING ".($header_inf['layer_path'].$header_inf['file'])." -->\n".
							file_get_contents($fp);
			
			file_put_contents($this->reorganize_dir."reorganize_log.txt", "		REORGANIZE_APPENDING TPL {$header_inf['layer_path']}{$header_inf['file']} => {$save_path}\n" , FILE_APPEND);
		}
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE_APPENDING TPL DONE\n" , FILE_APPEND);
		
		$ret_header_inf = reset($layer_files);
		$ret_header_inf['src'] = trim($final_tokens)."\n";
		
		return $ret_header_inf;
	}
	
	protected function reorganize_join_url(string $full_class_name, array $layer_files, string $save_path)
	{
		throw new \Exception('reorganize_join_url not implemented. There were no joins for `url`');
		/*
		$final_tokens = null;
		
		foreach ($layer_files as $header_inf)
		{
			$fp = $header_inf['layer_path'].$header_inf['file'];
			if (!file_exists($fp))
				throw new \Exception('Missing src file');
			$tokens = \QPHPToken::ParsePHPFile($fp, true);
			
			if (!$final_tokens)
				$final_tokens = $tokens;
			else
			{
				# join
				# @TODO - join these 2 $final_tokens + $tokens
			}
		}
		
		$ret_header_inf = reset($layer_files);
		$ret_header_inf['src'] = $final_tokens->toString();
		
		return $ret_header_inf;
		*/
	}
	
	protected function reorganize_join_resources(string $full_class_name, array $layer_files, string $save_path)
	{
		$final_tokens = '';
		
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE_APPENDING RES START\n" , FILE_APPEND);
		foreach ($layer_files as $header_inf)
		{
			$fp = $header_inf['layer_path'].$header_inf['file'];
			if (!file_exists($fp))
				throw new \Exception('Missing src file');
			$final_tokens .= "\n/* REORGANIZE_APPENDING ".($header_inf['layer_path'].$header_inf['file'])." */\n".
							file_get_contents($fp);
			
			file_put_contents($this->reorganize_dir."reorganize_log.txt", "		REORGANIZE_APPENDING RES {$header_inf['layer_path']}{$header_inf['file']} => {$save_path}\n" , FILE_APPEND);
		}
		file_put_contents($this->reorganize_dir."reorganize_log.txt", "REORGANIZE_APPENDING RES DONE\n" , FILE_APPEND);
		
		$ret_header_inf = reset($layer_files);
		$ret_header_inf['src'] = trim($final_tokens)."\n";
		
		return $ret_header_inf;
	}
	
	protected function reorganize_join_php_classes(QPHPTokenFile $class_file, QPHPTokenFile $extending_class_file)
	{
		$class = $class_file->findFirstPHPTokenClass();
		$extending_class = $extending_class_file->findFirstPHPTokenClass();
		
		# 1. doc comment ?
		if ($extending_class->docComment)
		{
			if (!$class->docComment)
				array_unshift($class->children, $extending_class->docComment);
			else
			{
				$ex_dc = $class->find_class_element("", get_class($extending_class->docComment));
				$class->children[$ex_dc[1]] = $extending_class->docComment;
			}
			$class->docComment = $extending_class->docComment;
			$extending_class->docComment->parent = $class;
		}
		
		# 3. Add any extra implements
		if ($extending_class->implements && ($extending_class->implements !== $class->implements))
		{
			// implements
			qvar_dumpk("BEFORE", $extending_class->implements, $class->implements, $class);

			$class->implements = ($class->implements ? array_merge($extending_class->implements, $class->implements) : $extending_class->implements);
			$class->normalizeTokensFromAttributes();

			qvar_dumpk("AFTER", $extending_class->implements, $class->implements, $class);
			throw new \Exception('@TODO');
		}
		
		$class_traits = $class->getTraits();
		# 4. Traits
		foreach ($extending_class->getTraits() ?: [] as $trait_full => $ext_trait)
		{
			if (!$class_traits[$trait_full])
			{
				if ($class_traits)
					qvar_dumpk("BEFORE", $trait_full, $ext_trait, $class);
				$class->setUseTrait($ext_trait);
				if ($class_traits)
				{
					qvar_dumpk("AFTER", $class);
					throw new \Exception('@TODO CHECK IF IT WORKS');
				}
			}
		}
		
		# 5. copy all constants / properties / methods
		foreach ($extending_class->constants ?: [] as $name => $ext_constant)
		{
			if (!$class->constants[$name])
				// we will need to add it
				$class->smartAppend($ext_constant);
			else
			{
				$ex_item = $class->find_class_element($name, get_class($ext_constant));
				$class->children[$ex_item[1]] = $ext_constant;
			}
			$class->constants[$name] = $ext_constant;
			$ext_constant->parent = $class;
		}
		
		# properties
		foreach ($extending_class->properties ?: [] as $name => $ext_property)
		{
			if (!$class->properties[$name])
				// we will need to add it
				$class->smartAppend($ext_property);
			else
			{
				$ex_item = $class->find_class_element($name, get_class($ext_property));
				$class->children[$ex_item[1]] = $ext_property;
			}
			$class->properties[$name] = $ext_property;
			$ext_property->parent = $class;
		}
		
		# first manage the method pathing, then methods merge
		// apply patch directives: @patch.rename, @patch.remove
		
		$skip_methods = [];
		
		if (($patch_metadata = ($extending_class->docComment ? (($pm = QCodeStorage::parseClassDocComment($extending_class->docComment)) ? $pm['patch'] : null) : null)))
		{
			if ($patch_metadata["rename"])
			{
				foreach ($patch_metadata["rename"] as $old_name => $new_name)
				{
					if ($class->methods[$new_name])
						# we need to remove the fake patches that we have created
						$class->removePropertyOrMethod($new_name);
					
					$class->renamePropertyOrMethod($old_name, $new_name);
					$skip_methods[$new_name] = true;
				}
			}
			if ($patch_metadata["remove"])
			{
				foreach ($patch_metadata["remove"] as $remove_item)
				{
					$class->removePropertyOrMethod($remove_item);
					$skip_methods[$new_name] = $remove_item;
				}
			}
		}
		
		# methods
		foreach ($extending_class->methods ?: [] as $name => $ext_method)
		{
			if ($skip_methods[$name])
				continue;
			
			if (!$class->methods[$name])
				// we will need to add it
				$class->smartAppend($ext_method);
			else
			{
				$ex_item = $class->find_class_element($name, get_class($ext_method));
				$class->children[$ex_item[1]] = $ext_method;
			}
			$class->methods[$name] = $ext_method;
			$ext_method->parent = $class;
		}
	}
	
}
