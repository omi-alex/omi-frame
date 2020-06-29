<?php

final class QFunc
{
	/**
	 * @var curl
	 */
	protected static $CurlCachedHandle;
	/**
	 * 
	 * @param string $class
	 * @param string $namespace
	 * @return string
	 */
	public static function GetFullClassName(string $class, string $namespace = null)
	{
		return ($class{0} === "\\") ? substr($class, 1) : (($namespace === null) ? $class : $namespace."\\".$class);
	}
	
	/**
	 * 
	 * @param string[] $classes
	 * @param string $namespace
	 * @return string
	 */
	public static function GetFullClassNames(array $classes = null, string $namespace = null, $index_by_name = false)
	{
		if ($classes === null)
			return null;
		$ret = [];
		foreach ($classes as $k => $class)
		{
			$cnf = ($class{0} === "\\") ? substr($class, 1) : (($namespace === null) ? $class : $namespace."\\".$class);
			if ($index_by_name || is_string($k))
				$ret[$cnf] = $cnf;
			else
				$ret[] = $cnf;
		}
		return $ret ?: null;
	}
	
	/**
	 * 
	 * @param string $command
	 * @param string $stdin
	 * @param string $cwd
	 * @param array $env
	 * @return array
	 */
	public static function Exec(string $command, string $stdin = null, string $cwd = null, array $env = null)
	{
		if ($stdin === null)
		{
			// simple exec
			$return_var = null;
			$output_arr = null;
			exec($command, $output_arr, $return_var);
			$output = $output_arr ? implode("\n", $output) : null;
		}
		else
		{
			$descriptorspec = [	0 => ["pipe", "r"],	// stdin is a pipe that the child will read from
								1 => ["pipe", "w"],	// stdout is a pipe that the child will write to
								2 => ["pipe", "a"]	// stderr is a file to write to
								];
			$pipes = [];

			$process = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

			if (is_resource($process))
			{
				// $pipes now looks like this:
				// 0 => writeable handle connected to child stdin
				// 1 => readable handle connected to child stdout
				// Any error output will be appended to /tmp/error-output.txt

				fwrite($pipes[0], $stdin);
				fclose($pipes[0]);

				$output = stream_get_contents($pipes[1]);
				fclose($pipes[1]);

				// It is important that you close any pipes before calling
				// proc_close in order to avoid a deadlock
				$return_var = proc_close($process);
			}
			else
			{
				// we have an error
				$return_var = -1;
				$output = "Unable to run process: ".$command;
			}
		}

		return [$return_var, $output, ($return_var !== 0) ? new \Exception(($output ? $output."\n" : "")."Error code: {$return_var}.") : null];
	}
	
	/**
	 * Replaces the \ in class name (if any) with - for filesystem compatibility
	 * 
	 * @param string $class
	 * @return string
	 */
	public static function ClassToPath($class)
	{
		return str_replace("\\", "-", $class);
	}
	
	/**
	 * Replaces the \ in class name (if any) with _ for php var name compatibility
	 * 
	 * @param string $class
	 * @return string
	 */
	public static function ClassToVar($class)
	{
		return str_replace("\\", "_", $class);
	}

	/**
	 * Transforms a PHP array to the PHP code required to setup that array
	 * 
	 * @param array $array
	 * @param string $var_name
	 * @param boolean $add_php_tags
	 * @param resource $stream
	 * @param integer $depth
	 * @return string
	 * @throws Exception
	 */
	public static function ArrayToCode($array, $var_name = null, $add_php_tags = true)
	{
		if ($var_name && ($var_name{0} === "\$"))
			$var_name = substr($var_name, 1);

		$str = "";
		if ($add_php_tags)
			$str .= "<?php\n";
		if ($var_name)
			$str .= "\$".$var_name." = ";
		$str .= var_export($array, true);
		$str .= ";\n";
		return $str;
	}

	/**
	 * Outputs a PHP array into a file as the PHP code required to setup that array
	 * 
	 * @param array $array
	 * @param string $var_name
	 * @param string $file_path
	 * 
	 * @return boolean
	 */
	public static function ArrayToCodeFile($array, $var_name, $file_path)
	{
		file_put_contents($file_path, self::ArrayToCode($array, $var_name, true));
	}
	
	/**
	 * Creates a directory using the result of umask() for permissions
	 * 
	 * @param string $path
	 * @param boolean $recursive
	 * @param integer $umask
	 * @return boolean
	 */
	public static function MkDir($path, $recursive = true, $umask = null)
	{
		return empty($path) ? false : (is_dir($path) ? true : (mkdir($path, ($umask === null) ? (0777 & ~umask()) : $umask, $recursive)));
	}
	
	/**
	 * Parses a string into a associative array that would describe an entity
	 * ex: Orders.*,Orders.Items.{Date,Product,Quantity},Orders.DeliveryAddresses.*
	 * The {} can be used to nest properties relative to the parent
	 * 
	 * @param string $str
	 * @param boolean $mark
	 * 
	 * @return array
	 */
	function ParseEntity($str, $mark = false)
	{
		$tokens = preg_split("/(\s+|\,|\.|\:|\{|\})/us", $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		$entity = array();

		$ctx_ent = &$entity;
		$ctx_prev = null;
		$ctx_sel = &$entity;
		$selected = null;

		// . => go deeper
		// , => reset to last `level` in this context
		// { => start a new context
		// } => exit current context

		foreach ($tokens as $tok)
		{
			$frts = $tok{0};
			switch ($frts)
			{
				case " ":
				case "\t":
				case "\n":
				case "\r":
				case "\v":
					break;
				case ".":
				{
					// there is nothing to do tbh
					break;
				}
				case ",":
				{
					$ctx_sel = &$ctx_ent;
					if ($selected !== null)
					{
						$selected[] = true;
						// make sure you unset and not assign to null as it is a reference
						unset($selected);
					}
					break;
				}
				case "{":
				{
					// creates a new context
					$ctx_prev = array(&$ctx_ent, $ctx_prev);
					$ctx_ent = &$ctx_sel;
					break;
				}
				case "}":
				{
					// closes the current context
					$ctx_ent = &$ctx_prev[0];
					$ctx_prev = &$ctx_prev[1];
					if ($selected !== null)
					{
						$selected[] = true;
						// make sure you unset and not assign to null as it is a reference
						unset($selected);
					}
					break;
				}
				default:
				{
					// identifier
					($ctx_sel[$tok] !== null) ? null : ($ctx_sel[$tok] = array());
					$ctx_sel = &$ctx_sel[$tok];
					$mark ? ($selected = &$ctx_sel) : null;
					break;
				}
			}
		}

		if ($selected !== null)
		{
			$selected[] = true;
			// make sure you unset and not assign to null as it is a reference
			unset($selected);
		}

		return $entity;
	}
	
	/**
	 * Intersects two arrays by key
	 * 
	 * @param array $array_1
	 * @param array $array_2
	 * @return array
	 */
	public static function ArrayIntersectRecursive(array $array_1, array $array_2)
	{
		$result = array_intersect_key($array_1, $array_2);
		foreach ($result as $k => $v_1)
		{
			if (is_array($v_1) && is_array($v_2 = $array_2[$k]))
				$result[$k] = self::ArrayIntersectRecursive($v_1, $v_2);
		}
		return $result;
	}
	
	public static function HttpRequest($url, $get = null, $post = null, $cookies = null, $files = null, &$response_header = null, &$response_code = null, $ignore_errors = true, $curl = null)
	{
		$t0 = microtime(true);
		$curl = $curl ?: static::$CurlCachedHandle;
		if ($curl)
			curl_reset($curl);
		else
			$curl = static::$CurlCachedHandle = curl_init();
		
		// curl_setopt($curl, CURLOPT_FRESH_CONNECT, false);
		// curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
		
		$curl_header = [];
		
		$cookie_str = "";
		if ($cookies)
		{
			// TODO : fix !!!!: Cookie: a=1; b=2
			foreach ($cookies as $k => $cookie)
				$curl_header[] = "Cookie: {$k}={$cookie}"; // $k."={$cookie}; ";
		}
		if ($files)
		{
			curl_setopt($curl, CURLOPT_POST, true);
			if (!is_array($files))
				$files = [$files];
			
			$mp_boundry = '--------------------------'.microtime(true).uniqid();
			
			$content = "";
			
			if ($post)
			{
				// add some POST fields to the request too: $_POST['foo'] = 'bar'
				foreach ($post as $k => $v)
				{
					if (!is_scalar($v))
						throw new \Exception("@todo only scalars implemented at the moment");
					$content .= "--{$mp_boundry}\r\n".
								"Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n".
								"{$v}\r\n";
				}
			}
			
			foreach ($files as $_file)
			{
				if ($_file instanceof IO\File)
				{
					$file = $_file->path;
					$file_content = $_file->contents ?: file_get_contents($file);
				}
				else
				{
					$file = $_file;
					$file_content = file_get_contents($file);
				}
				$file_size = strlen($file_content);
				
				$file_tag = pathinfo($file, PATHINFO_FILENAME);
				$content .=  "--{$mp_boundry}\r\n".
							"Content-Disposition: form-data; name=\"{$file_tag}\"; filename=\"".basename($file)."\"\r\n".
							"Content-Length: {$file_size}\r\n".
							"Content-Type: application/octet-stream\r\n\r\n".
							$file_content."\r\n";
			}
			
			// signal end of request (note the trailing "--")
			$content .= "--{$mp_boundry}--\r\n";
			
			$curl_header[] = "Content-Type: multipart/form-data; boundary={$mp_boundry}";
			$curl_header[] = 'Content-Length: ' . strlen($content);
			
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		}
		else if ($post && ($postdata = http_build_query($post)))
		{
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
			$curl_header[] = "Content-type: application/x-www-form-urlencoded";
		}
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_header);
		
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		
		// curl_setopt($curl, CURLOPT_HEADER, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		
		$req_url = $url.($get ? "?".http_build_query($get) : "");
		
		curl_setopt($curl, CURLOPT_URL, $req_url);
		
		$http_content = $response = curl_exec($curl);
		// $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		// list($response_header, $http_content) = explode("\r\n\r\n", $response, 2);
		
		// var_dump(((microtime(true) - $t0)*1000)." ms", $response_header);
		// var_dump($response_code, $response_header, $http_content);
		
		if ($resp_code >= 400)
		{
			var_dump($response_code, $http_content);
			// error
			return false;
		}
		// else - unable to get head info
		// die;
		return $http_content;
	}
	
	public static function ZipFiles($arch_path, $folder = null, $files_list = null, $common_path = null)
	{
		// create object
		$zip = new \ZipArchive();
		// open archive 
		if ($zip->open($arch_path, \ZIPARCHIVE::CREATE) !== true)
			return false;		
		if (!$files_list)
		{
			if (!is_dir($folder))
				return false;
			// initialize an iterator
			// pass it the directory to be processed
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder));

			// iterate over the directory
			// add each file found to the archive
			foreach ($iterator as $key => $value)
				$zip->addFile(realpath($key), $key);
		}
		else
		{
			if ($folder && (substr($folder, -1, 1) !== '/'))
				$folder = $folder."/";
			foreach ($files_list as $key => $value)
			{
				if ((!$folder) && is_array($value))
				{
					if (substr($key, -1, 1) !== '/')
						$key = $key."/";
					$in_arch_key = $key;
					if ($common_path)
						$in_arch_key = substr($in_arch_key, strlen($common_path));
					if (substr($in_arch_key, 0, 1) === '/')
						$in_arch_key = substr($in_arch_key, 1);
					foreach ($value as $val_obj)
						$zip->addFile($key.(($val_obj instanceof \Omi\IO\File) ? $val_obj->path : $val_obj), $in_arch_key.(($val_obj instanceof \Omi\IO\File) ? $val_obj->path : $val_obj));
				}
				else
					$zip->addFile(realpath($folder ? $folder.$key : $key), $key);
			}
		}
		// close and save archive
		$zip->close();
		return $zip;
	}
	
	public static function GetCommonPaths($paths, $apply_realpath = false)
	{
		if (!$paths)
			return false;
	
		$common = [];
		$max_depth = PHP_INT_MAX;
		
		$split_p = [];
		$min_parts = null;
		$min_parts_index = -1;
		$index = 0;
		
		foreach ($paths as $path)
		{
			$p_parts = explode("/", $apply_realpath ? realpath($path) : $path);
			$c_parts = count($p_parts);
			$split_p[] = $p_parts;
			if ($max_depth > $c_parts)
			{
				$min_parts = $p_parts;
				$max_depth = $c_parts;
				$min_parts_index = $index;
			}
			$index++;
		}
		
		for ($i = 0; $i < $max_depth; $i++)
		{
			$part = $min_parts[$i];
			$break = false;
			foreach ($split_p as $k => $sp)
			{
				if (($k !== $min_parts_index) && ($part !== $sp[$i]))
				{
					$break = true;
					break;
				}
			}
			if ($break)
				break;
			else
				$common[] = $part;
		}
		
		return implode("/", $common);
	}
	
	public static function GetPhpConfig($path, $var_name = "__DATA__")
	{
		${$var_name} = null;
		require($path);
		return ${$var_name};
	}
	
	public static function VarDump()
	{
		$args = func_get_args();
		
		$css_class = "_dbg_".uniqid();

		?><div class="<?= $css_class ?>" id="<?= $css_class ?>">
			<script type="text/javascript">
				if (!window._dbgFuncToggleNext)
				{
					window._dbgFuncToggleNext = function(dom_elem, $force_mode)
					{
						var next = dom_elem ? dom_elem.nextSibling : null;
						// skip until dom element
						while (next && (next.nodeType !== 1))
							next = next.nextSibling;
						if (!next)
							return;

						if ($force_mode)
							next.style.display = $force_mode;
						else if ((next.offsetWidth > 0) || (next.offsetHeight > 0))
							next.style.display = 'none';
						else
							next.style.display = 'block';
					};
					window._dbgFuncToggle_fullTree = function(dom_element)
					{
						var $mode = (dom_element.dataset.expanded === 'block') ? 'none' : 'block';
						dom_element.dataset.expanded = $mode;
						
						var all_expands = dom_element.parentNode.querySelectorAll("._dbg_expand");
						// alert(all_expands.length);
						for (var $i = 0; $i < all_expands.length; $i++)
						{
							window._dbgFuncToggleNext(all_expands[$i], $mode);
						}
						// document.getElementById("<?= $css_class ?>");
						// el.querySelectorAll(selector);
					};
				}
			</script>
		<style type="text/css">

			div.<?= $css_class ?> {
				font-family: monospace;
				font-size: 12px;
				padding: 10px;
				margin: 10px;
				border: 2px dotted gray;
				position: relative;
			}

			div.<?= $css_class ?> h4 {
				font-size: 15px;
				margin: 5px 0px 5px 0px;
			}

			div.<?= $css_class ?> table {
				border-collapse: collapse;
				border: 1px solid black;
				padding: 3px;
			}

			div.<?= $css_class ?> table tr:first-child th {
				background-color: blue;
				color: white;
			}

			div.<?= $css_class ?> table th, div.<?= $css_class ?> table td {
				text-align: left;
				padding: 3px;
				border: 1px solid black;
				vertical-align: top;
			}

			div.<?= $css_class ?> table td {

			}

			div.<?= $css_class ?> ._dbg_params {
				cursor: pointer;
				color: blue;
			}

			div.<?= $css_class ?> pre div > div {
				display: none;
			}

			div.<?= $css_class ?> span._dbg_expand {
				cursor: pointer;
				color: blue;
			}
			
			div.<?= $css_class ?> span._dbg_toggle {
				position: absolute;
				right: 0;
				display: block;
			}

			div.<?= $css_class ?> pre span._dbg_s {
				color: green;
			}

			div.<?= $css_class ?> pre span._dbg_nl {
				color: red;
			}

			div.<?= $css_class ?> pre span._dbg_bl {
				color: orange;
			}

		</style>
		<!-- <h4>Variables</h4> -->
		<span class='_dbg_expand _dbg_toggle' onclick='_dbgFuncToggle_fullTree(this);'>[+toggle]</span>
		<?php
		foreach ($args as $arg)
			static::VarDumpInner($arg);
		?></div><?php
	}
	
	protected static function VarDumpInner($var, $max_depth = 32, &$bag = null, $depth = 0)
	{
		$ty = gettype($var);

		if (!$bag)
			$bag = array();

		if ($depth === 0)
			echo "<pre style='tab-size: 4; -moz-tab-size: 4; -o-tab-size: 4;'>\n";

		$pad = str_repeat("\t", $depth);

		switch ($ty)
		{
			case "string":
			{
				echo "[string]: ";
				echo "<span class='_dbg_s'>";
				// wordwrap ( string $str [, int $width = 75 [, string $break = "\n" [, bool $cut = false ]]] )
				echo htmlspecialchars(wordwrap("\""./*str_replace(["\n", "\r"], ["\\n\n", "\\r\r"], */$var/*)*/."\"", 160, "\n\t\t".$pad, true));
				echo "</span>";
				break;
			}
			case "NULL":
			{
				echo ": <span class='_dbg_nl'>[null]</span>";
				break;
			}
			case "integer":
			{
				echo "[int]: ";
				echo $var;
				break;
			}
			case "double":
			{
				echo "[float]: ";
				echo $var;
				break;
			}
			case "boolean":
			{
				echo "[bool]: <span class='_dbg_bl'>";
				echo $var ? "true" : "false";
				echo "</span>";
				break;
			}
			case "array":
			{
				$is_empty = !$var;
				if ($is_empty)
					echo "[array(0)]: <i>empty array</i>";
				else
				{
					echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[array(".count($var).")]:</span>";
					echo "<div>";
					end($var);
					$last_key = key($var);
					foreach ($var as $k => $v)
					{
						echo $pad."\t<b>{$k}</b>";
						if ($max_depth)
							static::VarDumpInner($v, $max_depth - 1, $bag, $depth + 1);
						else
							echo "<span class='_dbg_nl'>*** too deep</span>";
						if ($k !== $last_key)
							echo "\n";
					}
					echo "</div>";
				}
				break;
			}
			case "object":
			{
				$obj_class = get_class($var);
				
				$ref_id = array_search($var, $bag, true);
				if ($ref_id === false)
				{
					end($bag);
					$ref_id = key($bag);
					$ref_id = ($ref_id === null) ? 0 : $ref_id + 1;

					$ref_id++;

					$bag[] = $var;
				}
				else
				{
					$ref_id++;

					echo "[{$obj_class}#{$ref_id}".($var->_id ? "; id:".$var->_id : ($var->Id ? "; Id:".$var->Id : ""))."]: <span>#ref</span>";
					return;
				}

				// we need to do this to access protected & private
				$var_arr = (array)$var;

				echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[{$obj_class}".(
						$var instanceof QIModelArray ? "(".$var->count().")" : "")."#{$ref_id}".($var->_id ? "; id:".$var->_id : ($var->Id ? "; Id:".$var->Id : ""))."]:</span>";
				echo "<div>";
				foreach ($var_arr as $k => $v)
				{
					if ($k{0} === "\x00")
					{
						$name = substr($k, strrpos($k, "\x00") + 1);
						if ($k{1} === "*")
							echo $pad."\t<b>{$name} [protected]</b>";
						else
							echo $pad."\t<b>{$name} [private]</b>";
					}
					else
					{
						echo $pad."\t<b>{$k}</b>";
					}
					if ($max_depth)
						static::VarDumpInner($v, $max_depth - 1, $bag, $depth + 1);
					else
						echo "<span class='_dbg_nl'>*** too deep</span>";
					echo "\n";
				}
				echo "</div>";
				break;
			}
			case "resource":
			{
				echo get_resource_type($var)." #".intval($var);
				break;
			}
			default:
			{
				// unknown type
				break;
			}
		}

		if ($depth === 0)
			echo "</pre>";
	}
	
	public static function ElapsedMs($round = 4)
	{
		return ($round !== false) ? round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])*1000, $round) : (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])*1000;
	}
	
	/**
	 * Lock utility
	 * 
	 * @param type $file
	 * @param type $callback
	 * @param type $callback_params
	 * @param type $max_wait
	 * @param type $mode
	 * 
	 * @return \Omi\IO\FileLock
	 */
	public static function Lock($file, $callback = null, $callback_params = [], $max_wait = null, $mode = null)
	{
		$lock = \Omi\IO\FileLock::LockFile($file, $max_wait, $mode);
		if (!$lock)
			return null;
		if ($callback)
		{
			try
			{
				call_user_func_array($callback, $callback_params);
			}
			finally
			{
				$lock->unlock();
			}
		}
		return $lock;
	}
	
}
