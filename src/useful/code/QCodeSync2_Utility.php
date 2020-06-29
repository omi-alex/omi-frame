<?php

/**
 * Generates and patches platform standards
 */
trait QCodeSync2_Utility
{
	public function check_syntax(string $layer, array $layer_files, array &$lint_info = null)
	{
		$lint_batch_size = 32; // we start 32 processes at once!
		$lint_cache_path = $this->temp_code_dir."php_lint_cache.php";
		
		# Read from cache
		if ($lint_info === null)
		{
			if (file_exists($lint_cache_path))
			{
				$_DATA = [];
				include($lint_cache_path);
				$lint_info = $_DATA;
			}
			if (!$lint_info)
				// ensure it's at least an empty array
				$lint_info = [];
		}
		
		# Determine what files needs checking again (either modified or never checked
		{
			$not_checked_files = [];
			foreach ($layer_files as $file => $mtime)
			{
				$short_ext = substr($file, -4, 4);
				if ((($short_ext !== '.php') || (substr($file, -8, 8) === '.gen.php')) && 
						(($short_ext !== '.tpl') || (!static::$PHP_LINT_CHECK_TPL)))
					continue;
				
				$lint_mdate_size = $lint_info[$layer][$file];
				if ((!isset($lint_mdate_size)) || ($lint_mdate_size !== [$mtime, filesize($layer.$file)]))
					$not_checked_files[$file] = $mtime;
			}
			# remove no longer needed files from the cache
			$update_cache = false;
			$unset_files = [];
			foreach ($lint_info[$layer] ?: [] as $file => $lint_mdate_size)
			{
				if (!isset($layer_files[$file]))
				{
					$unset_files[] = $file;
					$update_cache = true;
				}
			}
			foreach ($unset_files as $unsetf)
				unset($lint_info[$layer][$unsetf]);
		}
		
		for ($i = 0; $i < ceil(count($not_checked_files)/$lint_batch_size); $i++)
		{
			$lint_files = array_slice($not_checked_files, ($i * $lint_batch_size), $lint_batch_size);
			$errors = $this->lint_batch(array_keys($lint_files), $layer);
			if ($errors)
			{
				// save before we throw errors
				file_put_contents($lint_cache_path, "<?php\n\n\$_DATA = ".var_export($lint_info, true).";");
				
				qvar_dumpk($errors);
				throw new \Exception('Syntax errors');
			}
			// else we will save the info 

			# Update the lint cache that these files are ok with the modified date
			{
				foreach ($lint_files as $lf_path => $lf_mdate)
					$lint_info[$layer][$lf_path] = [$lf_mdate, filesize($layer.$lf_path)];
				file_put_contents($lint_cache_path, "<?php\n\n\$_DATA = ".var_export($lint_info, true).";");
				// we no longer need to update
				$update_cache = false;
			}
		}
		
		if ($update_cache)
			file_put_contents($lint_cache_path, "<?php\n\n\$_DATA = ".var_export($lint_info, true).";");
	}
	
	public function lint_batch(array $files, string $folder = null)
	{
		$procs = [];
		$all_pipes = [];
		$descriptorspec = [
					0 => ["pipe", "r"],  // stdin is a pipe that the child will read from
					1 => ["pipe", "w"],  // stdout is a pipe that the child will write to
					2 => ["pipe", "w"],  // stdout is a pipe that the child will write to
				];
		$outputs = [];
		$errors = [];
		$ret_codes = [];
		$process_stats = [];
		
		$t1 = microtime(true);

		try
		{
			$i = 0;
			foreach ($files as $file_path)
			{
				$path = (($folder !== null) ? $folder : '').$file_path;
				$pipes = [];
				
				$procs[$i] = $p = proc_open(PHP_BINDIR."/php -l ".escapeshellarg($path), $descriptorspec, $pipes);
				$all_pipes[$i] = $pipes;
				
				if (!is_resource($p))
					throw new Exception("Unable to start process with command: "."php -l ".escapeshellarg($path));

				// $path = "gates/".$gate."/";
				$proc_stat = proc_get_status($p);
				$process_stats[$i] = $proc_stat;
				// qvar_dumpk($proc_stat);
				$i++;
			}
					
			$i = 0;
			foreach ($all_pipes as &$pipes)
			{
				$outputs[$i] = $out = stream_get_contents($pipes[1]);
				$errors[$i] = stream_get_contents($pipes[2]);
				fclose($pipes[2]);
				
				if ((!$errors[$i]) && (!preg_match("/No\\s+syntax\\s+errors\\s+detected/uis", $out)))
					$errors[$i] = $out;
				
				$i++;
			}
		}
		finally
		{
			$i = 0;
			foreach ($procs as $process)
			{
				$ret_codes[$i] = proc_close($process);
				$i++;
			}
		}
		
		$t2 = microtime(true);
		
		// qvar_dumpk($t2 - $t1, $files, $outputs, $errors, $ret_codes, $process_stats);
		$ret = [];
		$i = 0;
		foreach ($files as $f)
		{
			if (trim($errors[$i]) || ($ret_codes[$i] !== 0))
				$ret[$f] = ['proc_ret' => $ret_codes[$i], 'error' => $errors[$i], 'open_stat' => $process_stats[$i]];
			$i++;
		}
		
		return $ret;
	}
	
	public function parse_doc_comment(string $doc_comment)
	{
		$doc_comment_inner = substr(trim($doc_comment), 3, -2);
		
		// $doc_tokens = preg_split("/(\\/\\*\\*\\s+|\\s+\\*\\/|\\n\\s*\\*\\s*\\@)/usim", $doc_comment, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$doc_tokens = preg_split("/(\\r?\\n\\s*\\*\\s*\\@[^\\s]+)/us", $doc_comment_inner, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		$indexed = [];
		$indexed['#description'] = ["", $doc_tokens[0]];
		for ($i = 1; $i < (ceil(count($doc_tokens))); $i += 2)
		{
			$m = null;
			$rc = preg_match('/^[^\\@]*\\@(.*)$/us', $doc_tokens[$i], $m);
			if (!($rc && isset($m[1])))
			{
				qvar_dumpk($doc_comment);
				throw new \Exception('Doc comment parse error');
			}
			$indexed[$m[1]] = [$doc_tokens[$i], $doc_tokens[$i + 1]];
		}
		
		return $indexed;
	}
	
	
	public function upgrade_sync_doc_comment(QPHPTokenClass $class, bool $is_patch)
	{
		$doc_comment = $class->docComment ? (string)$class->docComment : "/**\n * @class.name {$class->className}\n */";
		
		$indexed = $this->parse_doc_comment($doc_comment);
		
		$indexed['class.name'] = ["\n * @class.name", " ".$class->className];
		
		if ($class->extends)
			$indexed['class.extends'] = ["\n * @class.extends", " ".$class->extends];
		else
			unset($indexed['class.extends']);
		
		if ($class->implements)
			$indexed['class.implements'] = ["\n * @class.implements", " ".implode(", ", $class->implements)];
		else
			unset($indexed['class.implements']);

		if ($class->abstract)
			$indexed['class.abstract'] = ["\n * @class.abstract", " true"];
		else
			unset($indexed['class.abstract']);
		if ($class->final)
			$indexed['class.final'] = ["\n * @class.final", " true"];
		else
			unset($indexed['class.final']);
		
		if ($is_patch)
			$indexed['class.is_patch'] = ["\n * @class.is_patch", " true"];
		else
			// make sure we unset is_patch
			unset($indexed['class.is_patch']);
		// abstract, extends, implements, final
		
		$to_str = "/**";
		foreach ($indexed as $indx)
			$to_str .= ($indx[0].$indx[1]);
		$to_str .= "\n */";
		
		return $to_str;
	}

	public function check_if_class_extends(string $class, $namespace, string $extends, array $info_by_class, string $class_extends = null)
	{
		if (!$class_extends)
			$class_extends = $namespace ? \QPHPToken::ApplyNamespaceToName($info_by_class[$class]['extends'], $namespace) : $info_by_class[$class]['extends'];
		if (!$class_extends)
		{
			// echo "Class `{$class}` - NO!<br/>\n";
			return false;
		}
		
		$loops = 0;
		$max_loops = 64;
		while ($class_extends)
		{
			if ($class_extends === $extends)
			{
				// echo "Class `{$class}` - YES!<br/>\n";
				return true;
			}
			else
				$class_extends = $info_by_class[$class_extends]['extends'] ? ($namespace ? \QPHPToken::ApplyNamespaceToName($info_by_class[$class_extends]['extends'], $namespace) : $info_by_class[$class_extends]['extends']) : null;
			$loops++;
			if ($loops > $max_loops)
			{
				qvar_dumpk('$class, $namespace, $extends, $info_by_class, $class_extends', $class, $namespace, $extends, $info_by_class, $class_extends);
				throw new \Exception('Recursive lookup');
			}
		}
		// echo "Class `{$class}` - NO!<br/>\n";
		return false;
	}
}
