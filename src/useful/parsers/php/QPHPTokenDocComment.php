<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenDocComment
 *
 * @author Alex
 */
class QPHPTokenDocComment extends QPHPToken
{
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		if (is_array($tok) && ($tok[0] === T_DOC_COMMENT))
		{
			$this->children[] = $tok;
			$pos++;
		}
	}
	
	public function generate(QGeneratePatchInfo $gen_info, $bind_path = null, $rel_parent = null, $rel_parent_depth = 0)
	{
		// var_dump("QPHPTokenDocComment::generate");
		// var_dump($this->children);
		if ($this->children)
		{
			$doc_comm = reset($this->children);
			$doc_comm = $doc_comm[1];
			
			$parsed = QCodeStorage::parseDocComment($doc_comm, true);
			
			//var_dump($doc_comm, $parsed);
			if ($parsed["jsFunc"])
			{
				// parse jsFunc info
				// we should get started with the first function or code block from here on
				$after_me = false;
				$code = null;
				foreach ($this->parent->children as $child)
				{
					if ($after_me)
					{
						if (($child instanceof QPHPTokenCode) || ($child instanceof QPHPTokenFunction))
						{
							$code = ($child instanceof QPHPTokenFunction) ? $child->findCode() : $child;
							//var_dump("found", $code."");
							break;
						}
					}
					else if ($child === $this)
						$after_me = true;
				}
				
				if ($code)
				{
					// clone the code 
					$code_clone = QPHPToken::ParsePHPString("<?php ".$code->toString()." ?>", true)->findCode();
					$class_path = $this->getRoot()->filename;
					list($func_info, $func) = self::CreateJsFunc($code_clone, $parsed["jsFunc"], $class_path);
					$this->getRoot()->jsFunc[$func_info["tag"]] = $func;
				}
				
			}
		}
		
		parent::generate($gen_info, $bind_path, $rel_parent, $rel_parent_depth);
	}
	
	public static function CreateJsFunc($tokens, $info, $class_path, &$ret = null, &$str_escape = false, &$out_created = false, $open_with_echo = false, $next_setup_func = false)
	{
		$setup_func = false;
		$extra_append = null;
		
		if ($class_path)
		{
			$saved_class_path = $class_path;
			if (is_array($class_path))
			{
				list($class_path, $real_path) = $class_path;
			}
		}
		
		$new_setup_out_var = $next_setup_func;
		// $new_setup_out_return = $next_setup_func;
		if ($new_setup_out_var)
			$ret []= " { var \$_QOUT = \"\"; ";
		$next_setup_func = false;
		// $next_setup_func = false;
		
		if ($ret === null)
		{
			$setup_func = true;
			
			$info = self::ParseJsFuncArguments($info);
			
			$patch_info = new QGeneratePatchInfo($class_path);
			
			$tok = $tokens;
			
			$tokens = is_array($tok) ? $tok : $tok->inner();
			
			$start_xml_elem = ($tok instanceof QPHPTokenCode) ? $tok->findFirst(".QPHPTokenXmlElement") : (($tok instanceof QPHPTokenXmlElement) ? $tok : null);
			
			// $initial_count = count($tok->children);
			// $pp_pos = $tok->getPrependPos();
			$extra_prepend = "";
			
			if ($start_xml_elem)
			{
				$possible_scripts = $start_xml_elem->children(".QPHPTokenXmlElement");
				if ($possible_scripts)
				{
					foreach ($possible_scripts as $ps_script)
					{
						if ((strtolower($ps_script->tag) === "script") && ($ps_script->getAttribute("jsFuncMode") === "append"))
						{
							$extra_append = $tok->toString($ps_script->inner());
							// $tok->append($tok->toString($ps_script->inner()));
						}
						else if ((strtolower($ps_script->tag) === "script") && ($ps_script->getAttribute("jsFuncMode") === "prepend"))
						{
							// $tok->prepend($tok->toString($ps_script->inner()));
							$extra_prepend = $tok->toString($ps_script->inner());
						}
					}
				}
			}
			
			// $tok->prepend();
			// $skip_count = count($tok->children) - $initial_count;
			
			// $tok->append("\n\t\treturn \$_QOUT;\n");
			
			$class_name = $patch_info->class_name; // pathinfo($class_path, PATHINFO_FILENAME);
			/*
			$s_args = "";
			foreach ($info["params"] as $inf_p)
			{
				$inf_k = ($inf_p{0} === "\$") ? substr($inf_p, 1) : $inf_p;
				$s_args .= "\"{$inf_k}\": $inf_p, ";
			}
			$s_args = rtrim($s_args, ", ");
			 */
			$ret = ["{$class_name}.prototype.{$info["name"]} = function", "(",  implode(", ", $info["params"]), ")", "\n", "{\n", 
						// "\tif (this.saveArgs) this.saveArgs(\"{$info["name"]}\", {{$s_args}});\n",
						"\tvar \$_QOUT = \"\";\n", $extra_prepend];
			
			// $tokens = $tok->children;
		}
		
		if (!$tokens)
			return $ret;
		
		$tok = reset($tokens);
		$prev_tok = null;
		$prev_tok_non_code = null;
		
		$dynamic_obj_key_depth = 0;
		$T_CURLY_OPEN_depth = 0;
		$found_pos = null;
		
		while ($tok)
		{
			$ty = is_array($tok) ? $tok[0] : null;
			$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
			
			/*
			if (($tok instanceof QPHPTokenXmlElement) && ($tok->attrs["q-each"]))
			{
				$ret[] = "<!-- OMI-MARK: <div q-each=".qaddslashes($tok->attrs["q-each"])." q-js-template></div> -->\\\n";
			}
			*/
			
			if ($ts !== null)
			{
				if ($ty === T_CLOSE_TAG)
				{
					// starts output
					if ($str_escape || $open_with_echo)
					{
						// we need to cleanup the ";" if present
						$last_ret = end($ret);
						while ($last_ret)
						{
							$last_ret_rtrim = rtrim($last_ret, "\t\n ");
							if (strlen($last_ret_rtrim) === 0)
								$last_ret = prev($ret);
							else if (substr($last_ret_rtrim, -1) === ";")
							{
								$ret[key($ret)] = substr($last_ret_rtrim, 0, -1);
								break;
							}
							else
								break;
						}
						// end cleanup ";"
						
						$ret[] = ($open_with_echo ? ")" : "") . " + \"";
						if ($open_with_echo)
						{
							$str_escape = true;
							$open_with_echo = false;
						}
					}
					else
					{
						$ret[] = "\$_QOUT += \"";
						$str_escape = true;
					}
				}
				else if ($ty === T_OPEN_TAG)
				{
					// end output
					$ret[] = "\";";
					/**
					 * T_OPEN_TAG 	<?php, <? or <% 	escaping from HTML
						T_OPEN_TAG_WITH_ECHO 	<?= or <%=
					 */
					// starts output
					// $ret[] = "str += \"";
					$str_escape = false;
				}
				else if ($ty === T_FUNCTION)
				{
					$next_setup_func = true;
					$ret[] = $ts;
				}
				else if ($ty === T_VARIABLE)
				{
					if ($tok[1] === "\$this")
						$ret[] = "this";
					else
						$ret[] = $tok[1];
				}
				else if ($ty === T_DOUBLE_COLON)
				{
					$ret[] = ".";
				}
				else if ($ty === T_ECHO)
				{
					$ret[] = "\$_QOUT += ";
				}
				else if ($ty === T_NS_SEPARATOR)
				{
					// \Omi\View\DropDown::RenderS
					// or Omi\View\DropDown::RenderS
					$ret[] = "window[\"".ltrim(addslashes($ts), "\\")."\"]";
					// var_dump(end($ret));
				}
				else if ($ty === T_ISSET)
				{
					$js_isset_str = "";
					$first_expr_test = true;
					$sqare_bra = 0;
					
					$last_expr_pos = null;
					
					while (($tok = next($tokens)) !== ")")
					{
						$ty = is_array($tok) ? $tok[0] : null;
						$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
						
						// $test_expr = false;
						if ($ts === "]")
							$sqare_bra--;
						else if ($ts === "[")
							$sqare_bra++;
						
						if (($ty === T_VARIABLE) || ($ty === T_STRING) || ($ty === T_LNUMBER) || ($ts === "]"))
						{
							$js_isset_str .= $ts;
							// $test_expr = true;
							if ($sqare_bra === 0)
							{
								if (!$first_expr_test)
									$ret[] = " && ";
								$ret[] = $js_isset_str;
								
								// getting the key
								end($ret);
								$last_expr_pos = key($ret);
								
								$first_expr_test = false;
							}
						}
						else if ($ty === T_OBJECT_OPERATOR)
							$js_isset_str .= ".";
						else if (($ty === T_CONSTANT_ENCAPSED_STRING) || ($ts === "[") || ($ts === "]"))
							$js_isset_str .= $ts;
						else if ($ts === "(")
							$ret[] = $ts;
					}
					
					if ($last_expr_pos !== null)
						$ret[$last_expr_pos] = "(({$ret[$last_expr_pos]} !== undefined) && ({$ret[$last_expr_pos]} !== null))";
					// fixing the position as it will be pushed
					prev($tokens);
				}
				else if ($ty === T_OPEN_TAG_WITH_ECHO)
				{
					$str_escape = false;
					$open_with_echo = true;
					$ret[] = "\" + (";
				}
				else if ($ty === T_OBJECT_OPERATOR)
				{
					$ret[] = ".";
				}
				else if ($ty === T_CONCAT_EQUAL)
				{
					// force to string
					/* T_CONCAT_EQUAL 	.=*/
					$ret[] = "+= \"\" +";
				}
				else if ($ts === ".")
				{
					// concat is a simple string .
					$ret[] = "+ \"\" +";
				}
				else if ($ty === T_CURLY_OPEN)
				{
					$ret[] = "\" + ";
					$T_CURLY_OPEN_depth++;
				}
				else if (($ty === T_STRING) && ($ts === "self"))
				{
					$ret[] = "window[\"".qaddslashes($class_name)."\"]";
				}
				else if (($ty === T_STATIC) && ($ts === "static"))
				{
					$ret[] = "this";
				}
				else if (($ty === T_STRING) && ($ts === "func_num_args"))
				{
					$ret[] = "arguments.length";
					self::jsFuncJumpAt($tokens, ")");
				}
				else if (($ty === T_STRING) && ($ts === "func_get_args"))
				{
					$ret[] = "arguments";
					self::jsFuncJumpAt($tokens, ")");
				}
				else if (($ty === T_STRING) && ($ts === "func_get_arg"))
				{
					$ret[] = "arguments";
					do
					{
						$tok = next($tokens);
						$ty = is_array($tok) ? $tok[0] : null;
						$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
						
						if ($ts === "(")
							$ret[] = "[";
						else if ($ts === ")")
						{
							$ret[] = "]";
							break;
						}
						else
							$ret[] = $ts;
					}
					while ($tok);
				}
				else if ($ty === T_STRING)
				{
					$prev_ty = ($prev_tok_non_code && is_array($prev_tok_non_code)) ? $prev_tok_non_code[0] : null;
					$prev_ts = ($prev_tok_non_code && is_array($prev_tok_non_code)) ? $prev_tok_non_code[1] : $prev_tok_non_code;
					
					$is_name_of_class = (($prev_ty === T_INSTANCEOF) || ($prev_ty === T_NEW)) || ($prev_ts && ($prev_ts{0} === "\\"));
					if (!$is_name_of_class)
					{
						// we must also lookahead for (T_DOUBLE_COLON)
						// and also for: function (Order\Blabla $bla)
						$next_non_ws = null;
						$next_probe_pos = key($tokens) + 1;
						$next_probe = $tokens[$next_probe_pos];
						while ((!$next_non_ws) && $next_probe)
						{
							$np_ty = is_array($next_probe) ? $next_probe[0] : null;
							if (($np_ty !== T_WHITESPACE) && ($np_ty !== T_DOC_COMMENT) && ($np_ty !== T_COMMENT))
							{
								$next_non_ws = $next_probe;
								break;
							}
							$next_probe = $tokens[++$next_probe_pos];
						}
						if ($next_probe)
						{
							$next_probe_ty = is_array($next_probe) ? $next_probe[0] : null;
							if ($next_probe_ty === T_DOUBLE_COLON)
								$is_name_of_class = true;
							else if (($prev_tok_non_code === "(") && ($next_probe_ty === T_VARIABLE))
								$is_name_of_class = true;
						}
					}
					if ($is_name_of_class)
					{
						$ts_namespace = static::GetNamespaceForPath($real_path ?: $class_path);
						$ts = self::ConvertPHPClassNameToJsClassName($ts, $ts_namespace);
					}
					// $ret[] = $str_escape ? str_replace("\n", "\\n\" + \n\t\t\t\"", addslashes(str_replace("\r\n", "\n", $ts))) : $ts;
					$ret[] = $str_escape ? str_replace("\n", "\\\n", addslashes(str_replace("\r\n", "\n", $ts))) : $ts;
				}
				else if (($ts === "?") && self::isFollowedBy($tokens, key($tokens), ":", $found_pos))
				{
					array_jump($tokens, $found_pos);
					$ret[] = "||";
				}
				else if (($ts === "{") && is_array($prev_tok) && ($prev_tok[0] === T_OBJECT_OPERATOR))
				{
					// remove prev ->
					array_pop($ret);
					$ret[] = "[";
					$dynamic_obj_key_depth++;
				}
				else if (($ts === "}") && $T_CURLY_OPEN_depth)
				{
					$ret[] = " + \"";
					$T_CURLY_OPEN_depth--;
				}
				else if (($ts === "}") && $dynamic_obj_key_depth)
				{
					$ret[] = "]";
					$dynamic_obj_key_depth--;
				}
				else if ($ty === T_FOREACH)
				{
					// foreach ( [list expr] as [keyname => ] [value name])
					// for (var keyname in list_expr)
					// T_DOUBLE_ARROW 	=> 	array syntax
					// T_AS
					// $fe_start = array("for");
					$fe_expr = array();
					$fe_key = null;
					$fe_value = array();
					
					// look ahead up to AS ... )
					/*$c_pos = key($tokens);*/
					$as_found = false;
					$arrow_found = false;
					$inside = false;
					
					while (($c_tok = next($tokens)))
					{
						$is_arr = is_array($c_tok);
						if ($inside)
						{
							if ($is_arr && ($c_tok[0] === T_AS))
							{
								$as_found = true;
							}
							else if ($as_found && ($is_arr && ($c_tok[0] === T_DOUBLE_ARROW)))
							{
								$arrow_found = true;
								$fe_key = $fe_value;
								$fe_value = array();
							}
							else if ($as_found && (($c_tok === ")") || ($is_arr && ($c_tok[1] === ")"))))
							{
								// done
								break;
							}
							else
							{
								if ($as_found)
									$fe_value[] = $c_tok;
								else
									$fe_expr[] = $c_tok;
							}
						}
						else if (($c_tok === "(") || ($is_arr && ($c_tok[1] === "(")))
							$inside = true;
						
					}
					
					$fe_value = trim(QPHPToken::toString($fe_value));
					if ($fe_key === null)
						$fe_key = "\$_key_".substr(preg_replace("/\\.\\-\\>\\[\\]\\{\\}\\$/us", "", $fe_value), 1);
					else
						$fe_key = trim(QPHPToken::toString($fe_key));
					
					$fe_js_expr = "\$_expr_".substr(preg_replace("/\\.\\-\\>\\[\\]\\{\\}\\$/us", "", $fe_value), 1);
					$fe_js_is_arr = "\$_isArr_".substr(preg_replace("/\\.\\-\\>\\[\\]\\{\\}\\$/us", "", $fe_value), 1);
					
					$ret[] = "var {$fe_js_expr} = ";
					self::CreateJsFunc($fe_expr, $info, $saved_class_path, $ret, $str_escape, $out_created, $open_with_echo);
					$ret[] = ";\n";
					$ret[] = "var {$fe_js_is_arr} = Array.isArray({$fe_js_expr});\n";
					$ret[] = "if (({$fe_js_expr}._ty === 'QModelArray') && ({$fe_js_expr}.__len__ === undefined))\n";
					$ret[] = "\t{$fe_js_expr} = {$fe_js_expr}._items;\n";
					
					$for_init = "\n\t\tif (({$fe_js_is_arr} && (!(({$fe_key} >=0) && ({$fe_key} < {$fe_js_expr}.length)))) || ((!{$fe_js_is_arr}) && ({$fe_key}.charAt(0) === '_')))\n".
									"\t\t\tcontinue;\n".
									"\t\t{$fe_value} = {$fe_js_expr}[{$fe_key}];\n";
									
					$ret[] = "for (var {$fe_key} in {$fe_js_expr})\n".
								"{\n";
					$ret[] = $for_init;
					
					/**
					 * 
						var $_expr_item = $items ;
						if ($_expr_item._ty && ($_expr_item._ty === 'QModelArray'))
							$_expr_item = $_expr_item._items;
						var $_isArr_item = Array.isArray($_expr_item);
						for (var $_key_item in $_expr_item)
						{
								if ($_isArr_item && (!(($_key_item >=0) && ($_key_item < $_expr_item.length))))
									continue;
								$item = $_expr_item[$_key_item];
					 */
					
					// skip whitespace
					$tok = next($tokens);
					while ($tok && is_array($tok) && ($tok[0] === T_WHITESPACE))
						$tok = next($tokens);
					
					// inject inner stuff here
					// then position after and continue
					if ($tok instanceof QPHPTokenCode)
					{
						// a code block
						// typeof n === 'number'
						$inner_tokens = $tok->inner();
						self::CreateJsFunc($inner_tokens, $info, $saved_class_path, $ret, $str_escape, $out_created, $open_with_echo);
					}
					else
					{
						// an instruction, go to ; and extract it, then wrap it in {}
						$inner_tokens = array();
						do
						{
							$inner_tokens[] = $tok;
							$tok = next($tokens);
						}
						while ($tok && ($tok !== ";"));
						
						$inner_tokens[] = $tok;
						
						// var_dump("----", $inner_tokens);
						
						self::CreateJsFunc($inner_tokens, $info, $saved_class_path, $ret, $str_escape, $out_created, $open_with_echo);
						
						$ret[] = "\n";
					}
					
					$ret[] = "}\n";
					
					// var_dump($for_init, $fe_expr, $fe_key, $fe_value, implode("", $ret));
					// die();
				}
				else
				{
					// $ret[] = $str_escape ? str_replace("\n", "\\n\" + \n\t\t\t\"", addslashes(str_replace("\r\n", "\n", $ts))) : $ts;
					$ret[] = $str_escape ? str_replace("\n", "\\\n", addslashes(str_replace("\r\n", "\n", $ts))) : $ts;
				}
			}
			else if (($tok instanceof QPHPTokenXmlElement) && ($tok->tag === "qCtrl"))
			{
				$_xml_children = $tok->children("script");
				if ($_xml_children && ($xml_children = reset($_xml_children)) && (($xml_children->attrs["jsFunc"] !== null) || $xml_children->getAttribute("type")))
				{
					$ret[] = "\";";
					$str_escape = false;
					
					$ret[] = QPHPToken::toString($xml_children->inner());
					
					$ret[] = "\$_QOUT += \"";
					$str_escape = true;
				}
				else
				{
					$qCtrl_name = $tok->getAttribute("name");
					$qCtrl_phpname_full = $tok->getAttribute("phpname") ?: $qCtrl_name;
					$qCtrl_class = $tok->getAttribute("qCtrl");
					$qCtrl_tag = $tok->getAttribute("tag");
					
					if (!$class_name)
					{
						$class_name = pathinfo($class_path, PATHINFO_FILENAME);
						$class_name = (($cnsp = strpos($class_name, ".")) !== false) ? substr($class_name, 0, $cnsp) : $class_name;
					}
					
					$qCtrl_phpname = (($pp_pos = strpos($qCtrl_phpname_full, "{")) !== false) ? substr($qCtrl_phpname_full, 0, $pp_pos) : $qCtrl_phpname_full;
					
					// $init = $this->findChildWithTag("init");
					$dyn_class_name = $tok->getDynamicClassName($class_name);
					
					$ts_namespace = static::GetNamespaceForPath($real_path ?: $class_path);
					$dyn_class_name = QPHPToken::ApplyNamespaceToName($dyn_class_name, $ts_namespace);
					
					// generate it
					$replacement = "var \${$qCtrl_phpname} = \$ctrl(\"".qaddslashes($dyn_class_name)."\");\n";
					
					$in_tpl_code = $tok->findCode();

					if ($in_tpl_code)
					{
						// we will need to generate from PHP's code
						// var_dump($in_tpl_code."");
						$new_ret = [];
						$new_out_created = false;
						$new_str_escape = false;
						// CreateJsFunc($tokens, $info, $class_path, &$ret = null, &$str_escape = false, &$out_created = false, $open_with_echo = false)
						self::CreateJsFunc($in_tpl_code->inner(), $info, $saved_class_path, $new_ret, $new_str_escape, $new_out_created, $open_with_echo);
						
						// @todo  this is a very ugly fix for $_OUT += 
						$new_ret_str = str_replace("\${$qCtrl_phpname}.render", "\$_QOUT += \${$qCtrl_phpname}.render", implode("", $new_ret));
						$replacement .= $new_ret_str;
					}
					else
					{
						$replacement .= 
								// "this.addControl(\${$qCtrl_phpname});\n". // @todo not, implemented yet
								"\${$qCtrl_phpname}.init();\n".	
								"\$_QOUT += \${$qCtrl_phpname}.render();\n";
					}
					
					$ret[] = "\";\n";
					$ret[] = $replacement;
					$ret[] = "\n\$_QOUT += \"";
					$str_escape = true;
				}
			}
			else if (($tok instanceof QPHPTokenXmlElement) && (strtolower($tok->tag) === "script") && $tok->attrs["jsFuncMode"])
			{
				// skip it !
			}
			else if (($tok instanceof QPHPToken) && $tok->children)
			{
				// var_dump($info);
				$skip_children = $tok->testAndSkipJsFunc($tokens, $ret, $info["name"], $str_escape);
				
				if ($skip_children && ($tok instanceof QPHPTokenXmlElement))
				{
					// var_dump($tok."", $out_created, $str_escape, $open_with_echo);
				}
				
				if (!$skip_children)
					self::CreateJsFunc($tok->children, $info, $saved_class_path, $ret, $str_escape, $out_created, $open_with_echo, 
											($tok instanceof QPHPTokenCode) ? $next_setup_func : false);
			}
			
			$prev_tok = current($tokens);
			if (($ty !== T_WHITESPACE) && ($ty !== T_DOC_COMMENT) && ($ty !== T_COMMENT))
				$prev_tok_non_code = $prev_tok;
			$tok = next($tokens);
		}
		
		if ($setup_func)
		{
			$ret[] = "\n\t\treturn \$_QOUT;\n};\n";
			// var_dump(array($info, implode("",$ret)));
			// die();
		}
		
		if ($new_setup_out_var)
			$ret []= " return \$_QOUT; } ";
		
		$info["tag"] = $class_name."#".$info["name"];
		
		return array($info, $ret);
	}
	
	public static function ParseJsFuncArguments($args_str)
	{
		$ret = array();
		$params = array();
		
		// renderOrderItem($orderItem=.items[].(EcmOrderItem)):start
		
		$matches = null;
		preg_match("/(\w+)\s*(\([^\)]*\))?(\:\w+)?/us", $args_str, $matches);
		
		$func_name = $matches[1];
		$func_args = substr($matches[2], 1, -1);
		
		$parts = explode(",", $func_args);
		
		// ... $param = ...
		foreach ($parts as $p)
		{
			$mat = null;
			preg_match("/(?=\w+\s+)?(\\$\w+)(?=\s*=\s*.*)?/us", $p, $mat);
			// ?<
			if ($mat && $mat[1])
				$params[] = $mat[1];
		}
		$ret["params"] = $params;
		$ret["name"] = $func_name;
		$ret["meta"] = $matches[3];
		
		// var_dump($args_str, $ret);
		
		return $ret;
	}
	
	public function testAndSkipJsFunc(&$tokens, &$ret_out, $current_name = null)
	{
		$doc_comm = reset($this->children);
		$doc_comm = $doc_comm[1];
		$parsed = QCodeStorage::parseDocComment($doc_comm, true);
		if ($parsed["jsFunc"])
		{
			$ret = QPHPTokenDocComment::ParseJsFuncArguments($parsed["jsFunc"]);
			if ($ret["name"])
			{
				if ($current_name === $ret["name"])
					return false;
				
				$after_me = false;
				
				foreach ($tokens as $tk => $child)
				{
					if ($after_me)
					{
						if ($child instanceof QPHPTokenCode)
						{
							//var_dump($tokens[$tk]."");
							$tokens[$tk] = " this.".$ret["name"]."(".($ret["params"] ? (implode(",", $ret["params"])) : "").");";
							return false;
						}
						else if ($child instanceof QPHPTokenFunction)
						{
							//var_dump($tokens[$tk]."");
							$tokens[$tk] = " this.".$ret["name"].";";
							return false;
						}
					}
					else if ($child === $this)
						$after_me = true;
				}
			}
		}
		return false;
	}
	
	public function jsFuncSkip(&$tokens, $opts, $whitespace = true)
	{
		if (!is_array($opts))
			$opts = array($opts);
		
		do
		{
			$tok = next($tokens);
			if (!$tok)
				break;
			$ty = is_array($tok) ? $tok[0] : null;
			$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
		}
		while ($tok && (($whitespace && ($ty === T_WHITESPACE)) || in_array($ts, $opts, true) || in_array($ty, $opts, true)));
	}
	
	public function jsFuncJumpAfter(&$tokens, $opt)
	{
		do
		{
			$tok = next($tokens);
			if (!$tok)
				break;
			$ty = is_array($tok) ? $tok[0] : null;
			$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
			
			if (($opt === $ts) || ($opt === $ty))
			{
				next($tokens);
				break;
			}
		}
		while ($tok);
	}
	
	public function jsFuncJumpAt(&$tokens, $opt)
	{
		do
		{
			$tok = next($tokens);
			if (!$tok)
				break;
			$ty = is_array($tok) ? $tok[0] : null;
			$ts = is_string($tok) ? $tok : (($ty !== null) ? $tok[1] : null);
			
			if (($opt === $ts) || ($opt === $ty))
			{
				break;
			}
		}
		while ($tok);
	}
	
	
	public static function ConvertPHPClassNameToJsClassName($class, $namespace)
	{
		if ($namespace)
			$class = QPHPToken::ApplyNamespaceToName($class, $namespace);
		
		$parts = preg_split("/\\\\/us", $class, -1, PREG_SPLIT_NO_EMPTY);
		return next($parts) ? implode(".", $parts) : reset($parts);
	}
}
