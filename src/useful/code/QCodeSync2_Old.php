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
	
}
