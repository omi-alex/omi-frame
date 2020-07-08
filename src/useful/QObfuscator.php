<?php

class QObfuscator
{
	public static function ObfuscateDir($destination = null, $skip_tags = null)
	{
		if ($destination === null)
			$destination = "_obfuscated";
		$destination = rtrim($destination, "\\/");
		
		// $wf = QAutoload::GetWatchFolders();
		// $classes = QAutoload::GetAutoloadData();
		$tags = QAutoload::GetWatchFoldersByTags();		
		
		foreach ($tags as $tag => $_folder)
		{
			if ($skip_tags && in_array($tag, $skip_tags))
				continue;

			$folder = realpath($_folder)."/";

			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
			foreach($objects as $name => $object)
			{
				/* @var $object SplFileInfo */
				if ($object->isFile())
				{
					$obfusc_path = rtrim($destination, "\\/")."/".trim($tag, "\\/")."/".ltrim(substr($name, strlen($folder)), "\\/");

					$bn = $object->getBasename();
					$full_ext = (($p = strpos($bn, '.')) !== false) ? substr($bn, $p) : null;
					$last_ext = (($p = strrpos($bn, '.')) !== false) ? substr($bn, $p) : null;

					if ((strtolower($bn{0}) !== $bn{0}) && (($full_ext === '.php') || ($full_ext === '.gen.php') || ($full_ext === '.patch.php')))
					{
						//echo "<div style='color: green;'>Obfuscate {$name} :: {$obfusc_path}</div>";
						static::Obfuscate($name, $obfusc_path);
					}
					else
					{
						switch ($full_ext)
						{
							case ".php":
							case ".js":
							case ".min.js":
							case ".css":
							case ".min.css":
							{
								$dest_dir = dirname($obfusc_path);
								if (!is_dir($dest_dir))
									qmkdir($dest_dir, true);
								copy($name, $obfusc_path);
								//echo "<div style='color: blue;'>Just copy {$name} :: {$obfusc_path}</div>";
								break;
							}
							default:
							{
								switch ($last_ext)
								{
									case ".js":
									case ".css":
									case ".eot":
									case ".svg":
									case ".ttf":
									case ".woff":
									case ".woff2":
									case ".woff2":
									case ".png": 
									case ".jpg": 
									case ".jpeg": 
									case ".scss": 
									{
										$dest_dir = dirname($obfusc_path);
										if (!is_dir($dest_dir))
											qmkdir($dest_dir, true);
										copy($name, $obfusc_path);
										//echo "<div style='color: blue;'>Just copy {$name} :: {$obfusc_path}</div>";
										break;
									}
									default:
									{
										echo "<div style='color: red;'>Skip [on lp check] {$name} :: {$obfusc_path}</div>";
										break;
									}
								}
								break;
							}
						}
						// var_dump("skip: ".$bn);
					}
					// echo "<br/>\n";
					/*
					if (in_array($name, $classes))
						static::Obfuscate($name, $obfusc_path);
					else
					{
						$dest_dir = dirname($obfusc_path);
						if (!is_dir($dest_dir))
							qmkdir($dest_dir, true);
						copy($name, $obfusc_path);
					}*/
				}
			}	
		}
	}
	
	public static function Obfuscate($src, $destination)
	{
		//var_dump($src, $destination);
		//echo "<br/>";

		$dest_dir = dirname($destination);
		if (!is_dir($dest_dir))
			qmkdir($dest_dir, true);
		
		// var_dump($src, $destination);
		// copy($src, $destination);
		$content = file_get_contents($src);
		
		$tokens = token_get_all($content);
		
		// isolate functions
		// within function obfuscate
		// strip comments within functions
		
		$pos = 0;
		$output = [];
		try
		{
			$len = count($tokens);
			for ($pos = 0; $pos < $len; $pos++)
			{
				$tok = $tokens[$pos];
				
				if (is_array($tok))
				{
					$ty = $tok[0];
					$str = $tok[1];
				}
				else
				{
					$ty = null;
					$str = $tok;
				}

				if ($ty === T_FUNCTION)
				{
					$start_pos = $pos;

					$func = new QPHPTokenFunction();
					$func->parse($tokens, $tok, $pos);
					
					$end_pos = $pos;
					static::ObfuscateFunction($func, $tokens, $start_pos, $end_pos, $output);
				}
				else
					$output[] = $str;
			}
		}
		catch (Exception $ex)
		{
			var_dump($src);
			throw $ex;
		}
		
		file_put_contents($destination, implode("", $output));
		// nested functions ?! 
	}
	
	protected static function ObfuscateFunction(QPHPTokenFunction $func, $tokens, $start_pos, $end_pos, &$_out)
	{
		$vars = [];
		$vars_index = 0;
		
		$out = [];
		
		$failed = false;
		$prev = null;
		for ($i = $start_pos; $i < $end_pos; $i++)
		{
			$tok = $tokens[$i];
			if (is_array($tok))
			{
				$ty = $tok[0];
				$str = $tok[1];
				
				switch ($ty)
				{
					case T_VARIABLE: // 	$foo 	variables
					{
						// var_dump($tok);
						// throw new Exception("T_STRING_VARNAME");
						$out[] = static::HandleVar($vars, $vars_index, $tok[1], $prev);
						break;
					}
					/*case T_CURLY_OPEN: // T_CURLY_OPEN 	{$ 	complex variable parsed syntax
					{
						//var_dump($tok, $tokens[$i+1], token_name(312));
						throw new Exception("T_CURLY_OPEN");
						break;
					}*/
					// please DO NOT remove doc comments / T_DOC_COMMENT !!!
					case T_COMMENT: 
					case T_WHITESPACE: 
					{
						// do not add it
						$out[] = " ";
						break;
					}
					
					/*case T_ENCAPSED_AND_WHITESPACE: // 	" $a" 	constant part of string with variables
					{
						var_dump($tok);
						throw new Exception("T_ENCAPSED_AND_WHITESPACE");
						break;
					}*/
					case T_DOLLAR_OPEN_CURLY_BRACES: // ${ 	complex variable parsed syntax
					case T_GLOBAL: // 	global 	variable scope
					case T_STRING_VARNAME: // 	"${a 	complex variable parsed syntax
					{
						// var_dump($tok);
						// throw new Exception(token_name($ty));
						//echo "<div style='color: red;'>ISSUE ON " . token_name($ty) . "</div>";
						$failed = true;
						break;
					}
					
					default:
					{
						$out[] = $str;
						break;
					}
				}
			}
			else
				$out[] = $tok;
			
			$prev = $tok;
		}
		
		if ($failed)
		{
			//echo "<div style='color: red;'>Failed [{$func->name}]</div>";
			for ($i = $start_pos; $i < $end_pos; $i++)
			{
				$tok = $tokens[$i];
				$out[] = is_array($tok) ? $tok[1] : $tok;
			}
		}
		else
		{
			//echo "<div style='color: green;'>Obfuscated [{$func->name}]</div>";
			foreach ($out as $o)
				$_out[] = $o;
		}
	}
	
	private static function HandleVar(&$vars, &$vars_index, $var_name, $prev)
	{
		if (($var_name === "\$this") || (substr($var_name, 0, 2) === "\$Q") || 
				((substr($var_name, 0, 2) === "\$_") && ($var_name{3} && (strtoupper($var_name{3}) === $var_name{3}))) || 
				($prev && is_array($prev) && ($prev[0] === T_DOUBLE_COLON)))
			return $var_name;
		return ($v = $vars[$var_name]) ?: ($vars[$var_name] = "\$x".$vars_index++);
	}
}
