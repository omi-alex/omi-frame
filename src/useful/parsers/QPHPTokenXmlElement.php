<?php

/**
 * Description of QPHPTokenXmlElement
 *
 * @author Alex
 */
class QPHPTokenXmlElement extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 'tag' => null, 'attrs' => null, 'classes' => null, 
						'pullDocComment' => null];
	
	/**
	 * Classes 
	 *
	 * @var string[]
	 */
	public $classes;
	
	public $_generated = false;
	
	/**
	 * Tests if this instance has a certain class
	 * 
	 * @param string $class
	 * 
	 * @return boolean
	 */
	public function hasClass($class)
	{
		return ($this->classes && ($this->classes[$class] !== null)) || (get_class($this) === $class);
	}

	public function generate(QGeneratePatchInfo $gen_info, $bind_path = null, $rel_parent = null, $rel_parent_depth = 0)
	{
		if ($this->_generated)
			return;

		$this->_generated = true;
		$do_soft_unwrap = false;

		if (strtolower($this->tag{0}) === "q")
		{
			$q_matches = null;
			$q_selector = null;
			$lc_tag = strtolower($this->tag);
			// var_dump($lc_tag);
			switch ($lc_tag)
			{
				case "qparent":
				case "qafter":
				case "qbefore":
				case "qappend":
				case "qprepend":
				case "qremove":
				case "qreplace":
				case "qinner":
				case "qwrap":
				case "qattr":
				case "qtag":
				case "qroot":
				{
					$q_matches = null;
					$q_selector = trim($this->getAttribute("q"));
	
					$re_generated_elements = [];
					//var_dump($lc_tag, $q_selector);
					if (($lc_tag === "qroot") || ($lc_tag === "qparent"))
					{
						if ($gen_info->__tpl_parent || ($gen_info->__tpl_parent_cb && ($item_to_patch = ($gen_info->__tpl_parent_cb)($gen_info, $this))))
						{
							$sync_item = $gen_info->__tpl_parent ?: $item_to_patch;
							$q_start_xml = $gen_info->__tpl_parent ? $sync_item->node->getReadOnlyTokens($sync_item->mode, $sync_item->tag) : 
												$sync_item->xml_tokens;
							
							if (!$q_start_xml)
							{
								qvar_dumpk($gen_info, $sync_item);
								throw new \Exception('No parent to patch');
							}
							
							if ($lc_tag === "qroot")
								$q_matches = $q_start_xml->children();
							else
								$q_matches = $q_selector ? ($q_start_xml ? $q_start_xml->query($q_selector) : []) : $q_start_xml->children();
							
							if ($q_matches)
							{
								foreach ($q_matches as $test_qm)
								{
									if (($test_qm instanceof QPHPToken) && (!$test_qm->_generated))
									{
										$x_gen_info = new QGeneratePatchInfo($gen_info->__tpl_parent ? $sync_item->node->getTemplatePath($sync_item->tag)
																					: $sync_item->tpl_path);
										if ($gen_info->__tpl_parent && isset($sync_item->patch))
										{
											$x_gen_info->__tpl_parent = $sync_item->patch;
											$x_gen_info->__tpl_mode = $sync_item->mode;
											$x_gen_info->__tpl_tag = $sync_item->tag;
										}
										else if ($gen_info->__tpl_parent_cb)
										{
											$x_gen_info->__tpl_parent_cb = $gen_info->__tpl_parent_cb;
											$x_gen_info->__tpl_header_info = $sync_item->__tpl_header_info;
										}
										
										$q_start_xml->generate($x_gen_info);
										$re_generated_elements[] = [$q_start_xml, $x_gen_info];
										
										if ($lc_tag === "qroot")
											$q_matches = $q_start_xml->children();
										else
											$q_matches = $q_selector ? ($q_start_xml ? $q_start_xml->query($q_selector) : []) : $q_start_xml->children();
										
										if ($q_matches)
										{
											foreach ($q_matches as $test_qm)
											{
												if (($test_qm instanceof QPHPToken) && (!$test_qm->_generated))
												{
													throw new \Exception('The template was not generated');
												}
											}
										}
									}
								}
								
							}
						}
						else
						{
							// throw new \Exception('NOTHING TO PATCH !');
						}
					}
					else
					{
						$findings = [];
						$q_selector_parsed = $q_selector ? $this->parseCssSelector($q_selector) : null;
						
						foreach ($this->parent->children as $c)
						{
							if ($c instanceof QPHPToken)
							{
								if (!(in_array(strtolower($c->tag), ["qparent", "qafter", "qbefore", "qappend", "qprepend", 
																	"qremove", "qreplace", "qinner", "qwrap", "qattr", "qtag"])))
								{
									if ($q_selector_parsed)
										$c->query($q_selector_parsed, $findings);
									else
										$findings[] = $c;
								}
							}
							/*
							else if ($q_selector === "*")
								$findings[] = $c;
							*/
						}
						$q_matches = $findings;
					}
					
					// foreach ($q_matches as $q_match)
					//	var_dump($q_match->toString());
					if (empty($q_matches))
					{
						$this->emptyNode(true);
						break;
					}

					if (($lc_tag === "qparent") || ($lc_tag === "qroot"))
					{
						// @todo : merge attributes, change tag, prepend inner
						$str_tp_tmp = trim($this->toString($q_matches));
						$parsed_code = QPHPToken::ParseTemplate($str_tp_tmp);

						$this->append($parsed_code);
						$do_soft_unwrap = true;
						// $this->softUnwrap();

						$_tofind = null;
						if (is_array($parsed_code))
						{
							foreach ($parsed_code as $pc__)
							{
								if ($pc__ instanceof QPHPTokenXmlElement)
								{
									$_tofind = $pc__;
									break;
								}
							}
						}
						else
							$_tofind = $parsed_code;
						// $_tofind = is_array($parsed_code) ? reset($parsed_code) : $parsed_code;
						if (!$_tofind)
						{
							// qvar_dump($___pc, $this->toString($q_matches));
							// qvar_dump($q_matches);
							qvar_dumpk([
										# '$this' => $this, 
										'$str_tp_tmp' => $str_tp_tmp, 
										'$gen_info' => $gen_info, 
										'$sync_item' => $sync_item, 
										'$q_start_xml' => $q_start_xml, 
										'$q_matches' => $q_matches, 
										'$parsed_code' => $parsed_code, 
										'$q_selector' => $q_selector,
										'$re_generated_elements' => $re_generated_elements
									]);
							throw new \Exception("Bad format!");
						}

						$q_match = $_tofind->findFirst(".QPHPTokenXmlElement");
						if (($q_match instanceof QPHPTokenXmlElement) && $q_match->attrs)
						{
							foreach ($q_match->attrs as $name => $value)
							{
								//   $this->attrs[$name]
								if (!$this->attrs[$name])
								{
									// $this->attrs[$name] = $value;
									$this->setAttribute($name, $value, false);
								}
							}
							// $q_match->setTag($this->tag);
						}
					}
					else 
					{
						// insert after each of the matches
						$str_code = ($this->toString($this->inner()));

						foreach ($q_matches as $q_match)
						{
							$parsed_code = QPHPToken::ParseTemplate($str_code);
							if ($q_match instanceof QPHPToken)
							{
								if ($lc_tag === "qafter")
									$q_match->after($parsed_code);
								else if ($lc_tag === "qbefore")
									$q_match->before($parsed_code);
								else if ($lc_tag === "qappend")
									$q_match->append($parsed_code);
								else if ($lc_tag === "qprepend")
									$q_match->prepend($parsed_code);
								else if ($lc_tag === "qremove")
									$q_match->emptyNode(true);
								else if ($lc_tag === "qreplace")
									$q_match->replace($parsed_code);
								else if ($lc_tag === "qinner")
									$q_match->inner($parsed_code);
								else if ($lc_tag === "qwrap")
								{
									$wrap_start = $this->getAttribute("start") ?? $this->findFirstChildWithTag('start');
									$wrap_end = $this->getAttribute("end") ?? $this->findFirstChildWithTag('end');
									
									if ($wrap_start instanceof \QPHPTokenXmlElement)
										$wrap_start = $wrap_start->innerAsString();
									if ($wrap_end instanceof \QPHPTokenXmlElement)
										$wrap_end = $wrap_end->innerAsString();
									
									if ($wrap_start && $wrap_end)
										$q_match->wrap($wrap_start, $wrap_end);
								}
								else if ($lc_tag === "qattr")
								{
									if ($q_match instanceof QPHPTokenXmlElement)
									{
										foreach ($this->attrs as $name => $value)
											$q_match->setAttribute($name, $value, false);
									}
								}
								else if ($lc_tag === "qtag")
								{
									if ($q_match instanceof QPHPTokenXmlElement)
										$q_match->setTag($this->tag);
								}
							}
						}
						$this->emptyNode(true);
					}
					
					break;
				}
			}
		}		

		// template generate
		$element_bind = null;
		if ($gen_info->isTemplate())
		{
			$start_from_this = false;
			$b_path = null;
			
			// here we setup control/page info
			if (strtolower($this->tag) === "html")
			{
				$this->setAttribute("qCtrl", "\"<?=get_class(\$this)?>\"", false);
				// $this->setClass("QWebPage");
			}
			else if (($this->parent instanceof QPHPTokenFile) && ($this->parent->extension === "tpl") && 
						// only main renders atm
						($this->parent->fileParts[1] === "tpl") && 
						$this->tag && ($this->tag{0} !== "!"))
			{
				// $f_parts = $this->parent->fileParts;
				// $this->setAttribute("qCtrl", "\"<?=\$this->name.\"({$gen_info->class_name})\";? > \"", false);
							// \$this->name.\"(\".get_class(\$this).\")
				$this->setAttribute("qCtrl", "\"<?= \$this->getQCtrl_Attr() ?>\"", false);
				$this->setClass("QWebControl");
				
				$this->setAttribute("q-dyn-parent", "\"<?= get_class(\$this->parent) ?>\"", false);
				$this->setAttribute("q-dyn-inst", "\"<?= \$this->dynamic_name ?>\"", false);
			}
		
			// done control/page info
			
			if ($this->attrs["jsFunc"])
			{
				// $parsed = QCodeStorage::parseDocComment($this->getatt$doc_comm, true);
				$attr_jsFunc = $this->getAttribute("jsFunc");
				$ret_parse = QPHPTokenDocComment::ParseJsFuncArguments($attr_jsFunc);
				
				// $this->setAttribute("jsFunc", "");
				$inside_js_ctrl = $this->parent && ((strtolower($this->parent->tag) === "qctrl"));
				
				$data_block = "<?php {\n?>";
				$do = false;
				
				if ($ret_parse["meta"] === ":start")
				{
					$do = true;
					$include = false;
					foreach ($this->parent->children as $p_child)
					{
						if ($p_child === $this)
							$include = true;
						
						if ($include)
							$data_block .= $this->toString($p_child);
						
						if (($p_child instanceof QPHPTokenXmlElement) && $p_child->attrs["jsFunc"])
						{
							$child_jsFunc = $p_child->getAttribute("jsFunc");
							$child_parse = QPHPTokenDocComment::ParseJsFuncArguments($child_jsFunc);
							if (($child_parse["name"] === $ret_parse["name"]) && ($child_parse["meta"] === ":end"))
							{
								// var_dump(" we stop at: ".$p_child);
								break;
							}
						}
					}
				}
				else if (!$ret_parse["meta"])
				{
					$do = true;
					$data_block .= $inside_js_ctrl ? $this->toString($this->inner()) : $this->toString();
				}

				if ($do)
				{
					$data_block .= "<?php\n} ?>";

					$class_path = $this->getRoot()->filename;

					$code_clone = QPHPToken::ParsePHPString($data_block, true)->findCode();

					if ($inside_js_ctrl)
						$class_path = dirname($class_path)."/".$this->parent->wrapping_data["dyn_class_name"].".php";

					// $attr_jsFunc["path"] = $this->getRoot()->filename;
					$class_path = [$class_path, $this->getRoot()->filename];

					list($func_info, $func) = QPHPTokenDocComment::CreateJsFunc($code_clone, $attr_jsFunc, $class_path);
					// var_dump($func_info, $this->toString($func));

					// $inside_js_ctrl
					$this->getRoot()->jsFunc[$func_info["tag"]] = $func;
					

					// $func_str = implode("", $js_func);
					// var_dump($func_str);
				}
			}

			if ($this->tag === "q_ctrl")
			{
				if (!$this->parent)
					throw new \Exception('Missing parent');
				
				$this->transform_new_qctrl($gen_info);
			}
		}
		else if ($gen_info->type === "url")
		{
			if ($this->tag === "urls")
			{
				// fork here : one for getUrl and one for load from url URL
				if (isset($this->parent->parent) && ($this->parent->parent instanceof QPHPTokenFile))
				{
					// echo "generateURLs: ".$gen_info->file_name."<br/>";
					$this->generateURLs($gen_info);
				}
				else
				{
					// throw new Exception("Parse error");
				}
			}
		}

		parent::generate($gen_info, $bind_path, $rel_parent, $rel_parent_depth);
		
		if ($do_soft_unwrap)
			$this->softUnwrap();
	}
	
	public function getAttribute($name, $escape = true)
	{
		// $attr_val = $escape ? "\"".addslashes($value)."\"" : $value;
		// $_cls_list = stripslashes(substr($attr_val, 1, -1));
		$attr = $this->attrs[$name];
		// htmlspecialchars_decode()
		return $attr ? ($escape ? htmlspecialchars_decode(substr($attr, 1, -1)) : $attr) : null;
	}

	public function setAttribute($name, $value, $escape = true)
	{
		$attr_val = $escape ? "\"".htmlspecialchars($value)."\"" : $value;

		if (($av = $this->attrs[$name]) !== null)
		{
			// update it
			$val = $av{0}.htmlspecialchars($value).$av{0};
			if ($val !== $av)
			{
				$setup_ok = false;
				// T_QXML_ATTR_NAME | T_QXML_ATTR_VALUE
				$prev_name = null;
				foreach ($this->children as $k => $child)
				{
					$type = is_array($child) ? $child[0] : null;
					if ($type === T_QXML_ATTR_NAME)
						$prev_name = $child[1];
					else if (($type === T_QXML_ATTR_VALUE) && ($prev_name === $name))
					{
						// bingo
						$frst_ch = $child[1]{0};
						if ((($sl = strlen($child[1])) > 1) && ($frst_ch === substr($child[1], -1)))
						{
							// one value
							$this->children[$k][1] = $value;
							$pos = $k;
						}
						else
						{
							// we need to get them all and splice
							$pos = $k + 1;
							$tok = $this->children[$pos];
							$found = false;
							do
							{
								$typ = is_array($tok) ? $tok[0] : null;
								
								if (($typ === T_QXML_ATTR_VALUE) && ($frst_ch === substr($tok[1], -1)))
								{
									// found it
									$found = $pos;
									break;
								}
								
								$pos++;
								$tok = $this->children[$pos];
							}
							while (($typ !== T_QXML_TAG_CLOSE) && ($typ !== T_QXML_TAG_SHORT_CLOSE));
							
							if ($found === false)
								throw new Exception("Bad parsing");
						}
							
						// $this->children->splice($k, ($pos - $k + 1), array(array(T_QXML_ATTR_VALUE, $attr_val)));
						array_splice($this->children, $k, ($pos - $k + 1), array(array(T_QXML_ATTR_VALUE, $attr_val)));
						$this->attrs[$name] = $attr_val;
						$setup_ok = true;
						
						break;
					}
					else if (($type === T_QXML_TAG_CLOSE) || ($type === T_QXML_TAG_SHORT_CLOSE))
						break;
				}
				
				if (!$setup_ok)
				{
					var_dump($this->toString(), $this->attrs);
					throw new Exception("Bad parsing, unable to replace attribute value: {$name} = {$value}");
				}
			}
			else 
				return;
		}
		else
		{
			// append it
			// T_QXML_TAG_CLOSE | T_QXML_TAG_SHORT_CLOSE
			$setup_ok = false;
			foreach ($this->children as $k => $child)
			{
				$type = is_array($child) ? $child[0] : null;
				if (($type === T_QXML_TAG_CLOSE) || ($type === T_QXML_TAG_SHORT_CLOSE))
				{
					/*$this->children->splice($k, 0, array(
														array(T_QXML_ATTR_SPACE, " "), 
														array(T_QXML_ATTR_NAME, $name), 
														array(T_QXML_ATTR_EQUAL, "="),
														array(T_QXML_ATTR_VALUE, $attr_val)));*/
					array_splice($this->children, $k, 0, array(
														array(T_QXML_ATTR_SPACE, " "), 
														array(T_QXML_ATTR_NAME, $name), 
														array(T_QXML_ATTR_EQUAL, "="),
														array(T_QXML_ATTR_VALUE, $attr_val)));
					$this->attrs[$name] = $attr_val;
					$setup_ok = true;
					
					break;
				}
			}
			
			if (!$setup_ok)
				throw new Exception("Bad parsing, unable to append attribute");
		}
		
		if ($setup_ok && (strtolower($name) === "class"))
		{
			$_cls_list = stripslashes(substr($attr_val, 1, -1));
			$this->classes = array_flip(preg_split("/\\s+/us", $_cls_list));
		}
	}
	
	public function removeAttribute($name)
	{
		if (!$this->children)
			return false;
		
		$name_pos = false;
		foreach ($this->children as $k => $child)
		{
			$type = is_array($child) ? $child[0] : null;
			if ($type === T_QXML_ATTR_NAME)
			{
				$prev_name = $child[1];
				$name_pos = $k;
			}
			else if (($type === T_QXML_ATTR_VALUE) && ($prev_name === $name))
			{
				// bingo
				$frst_ch = $child[1]{0};
				if ((($sl = strlen($child[1])) > 1) && ($frst_ch === substr($child[1], -1)))
				{
					// one value
					$this->children[$k][1] = $value;
					$pos = $k;
				}
				else
				{
					// we need to get them all and splice
					$pos = $k + 1;
					$tok = $this->children[$pos];
					$found = false;
					do
					{
						$typ = is_array($tok) ? $tok[0] : null;

						if (($typ === T_QXML_ATTR_VALUE) && ($frst_ch === substr($tok[1], -1)))
						{
							// found it
							$found = $pos;
							break;
						}

						$pos++;
						$tok = $this->children[$pos];
					}
					while (($typ !== T_QXML_TAG_CLOSE) && ($typ !== T_QXML_TAG_SHORT_CLOSE));

					if ($found === false)
						throw new Exception("Bad parsing");
				}

				// $this->children->splice($name_pos, ($pos - $name_pos + 1));
				array_splice($this->children, $name_pos, ($pos - $name_pos + 1));
				return true;
			}
			else if (($type === T_QXML_TAG_CLOSE) || ($type === T_QXML_TAG_SHORT_CLOSE))
			{
				if (($prev_name === $name))
				{
					// only attribute name 
					// $this->children->splice($name_pos, 1);
					array_splice($this->children, $name_pos, 1);
					return true;
				}
				else
					return false;
			}
		}
		return false;
	}
	
	public function setClass($class_name)
	{
		if ($this->classes[$class_name] !== null)
			// we already have it
			return;
		else if ($this->attrs["class"])
		{
			// update it
			$classes = stripslashes(substr($this->attrs["class"], 1, -1));
			$this->setAttribute("class", $this->attrs["class"]{0}.(empty($classes) ? $class_name : ($classes." ".$class_name)).$this->attrs["class"]{0}, false);
		}
		else
			$this->setAttribute("class", $class_name);
		$this->classes[$class_name] = $class_name;
	}
	
	protected function transformQLink()
	{
		throw new Exception("QLINK: TO DO");
		// $tag = $this->attr("to");
		/**
		$class = $this->attr("class");
		if (!$class)
			$class = "QWebControl";
		*/
		// $this->replaceWith("<a href='#{$tag}'>Link</a>");
	}
	
	public function getDynamicClassName($class_name)
	{
		$name = $this->getAttribute("name");
		// $class = $this->getAttribute("qCtrl");
		$tag = $this->getAttribute("tag");
		
		return $class_name."_".($tag ?: $name); //."_".$class;
	}
	
	protected function transform_new_qctrl(QGeneratePatchInfo $gen_info = null)
	{
		// set_time_limit(10);
		// static::RenderS_Ctrl($props = null, $init_cb = null, $parent = null, $method = "");
		
		// init_cb
		$init_cb = $this->findChildWithTag('init_cb');
		
		// setup all the methods to be run at parent via their redefinition
		// _inst_{$instid}_methodName(...args...)
		$dynamic_name = $this->getAttribute('q-name');
		$class_name = $this->getAttribute('q-class');
		
		$init_str = "";
		$meths_str = "";
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPTokenCode)
			{
				$init_str .= $this->toString($child->inner()).";\n";
			}
			else if (($child instanceof QPHPTokenXmlElement) && $child->tag)
			{
				$lc_tag = strtolower($child->tag);
				if (($lc_tag === 'init') || ($lc_tag === 'init_cb'))
				{
					$init_str .= $this->toString($child->findCode()->inner()).";\n";
				}
				else 
				{
					$m_visibility = trim($child->getAttribute('q-visibility') ?: 'public')." ";
					$m_static = (($child->attrs['q-static'] !== null) || array_key_exists('q-static', $child->attrs)) ? 'static ' : '';
					$q_args = $child->getAttribute('q-args');
					$is_render = (substr($lc_tag, 0, 6) === 'render');
					
					if ($is_render)
					{
						$api_enable = "@api.enable";
						// we need to somehow compile the template inside
						$m_body = $child->compileQCtrlRenderMethod($class_name, $child->tag);
					}
					else
					{
						$api_enable = ($v = $child->getAttribute('q-api')) && ($v !== false) ? "@api.enable" : "";
						$m_body = $this->toString($child->findCode()->inner()).";\n";
					}
					
					$meths_str .= 
"				/**
				 * {$api_enable}
				 */
				{$m_visibility}{$m_static}function {$child->tag}({$q_args})
				{
					{$m_body}
				}
";
				}
			}
		}
		
		$dyn_build_meth = 
"	public function GetDynamic_Ctrl_{$dynamic_name}()
	{
		\$ctrl = new class() extends \\{$class_name}
			{
				public function init(\$recursive = true)
				{
					\$ctrl = \$this;
					\$this->is_dynamic_ctrl = true;
					\$this->dynamic_name = '{$dynamic_name}';
					{$init_str}
				}
				
				{$meths_str}
			};
		return \$ctrl;
	}
";
		// "GetDynamic_Ctrl_{$dynamic_name}";
		// we need to inprint it on THIS 
		$root = $this->getRoot();
		$root->setup_methods["GetDynamic_Ctrl_{$dynamic_name}"] = $dyn_build_meth;
		
		$this->children = ["<?php \\{$class_name}::RenderS_Ctrl('{$dynamic_name}', \$this, '', get_defined_vars()); ?>"];
	}
	
	protected function transformQControl(QGeneratePatchInfo $gen_info)
	{
		$name = stripslashes(substr($this->attr("name"), 1, -1));
		/**
		$class = $this->attr("class");
		if (!$class)
			$class = "QWebControl";
		*/
		if (!$name)
			throw new Exception("Controls without name are not supported at the moment");
		
		$this->replaceWith("<?php \$this->{$name}->callRender(); ?>");
		
		// append in generatedInit();
		$class = $this->attr("class");
		if ($class)
		{
			$class = stripslashes(substr($class, 1, -1));
			$gen_info->_generatedInit .= "\t\tif (!\$this->{$name})\n\t\t\t\$this->addControlIfNotExists(\$this->{$name} = new {$class}(), {$this->attrs["name"]});\n";
		}
	}
	
	public function attr($name, $value = null)
	{
		if ($value)
		{
			$this->attrs[$name] = $value;
			return $value;
		}
		else
			return $this->attrs ? $this->attrs[$name] : null;
	}
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// always true
		$expand_output = true;
		
		$type = $tok[0];
		$last_attr = null;
		if (!($type === T_QXML_TAG_OPEN))
			throw new Exception("Parse error");
		
		$this->children[] = $tok;
		$pos++;
		$tok = $tokens[$pos];
		$type = $tok[0];
		
		if (!($type === T_QXML_TAG_NAME))
			throw new Exception("Parse error");
		
		$this->tag = $tok[1];
		
		// echo "Starting: {$this->tag}<br/>\n";
		
		$this->children[] = $tok;
		$pos++;
		$tok = $tokens[$pos];
		$type = $tok[0];
		
		// parse up to T_QXML_TAG_CLOSE
		while ($tok)
		{
			if (($type === T_OPEN_TAG) || ($type === T_OPEN_TAG_WITH_ECHO))
			{
				$child = new QPHPTokenCode($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this);
				
				$tok = $tokens[$pos];
				$type = $tok[0];
				
				continue;
			}
			
			// var_dump($tok);
			
			$this->children[] = $tok;

			if ($type === T_QXML_TAG_CLOSE)
			{
				if (strtoupper(substr($this->tag, 0, 8)) === "!DOCTYPE")
				{
					$pos++;
					return;
				}
				else
				{
					$pos++;
					$tok = $tokens[$pos];
					$type = $tok[0];
					break;
				}
			}
			else if ($type === T_QXML_ATTR_NAME)
			{
				$last_attr = $tok[1];
				$this->attrs[$last_attr] = true;
			}
			else if ($type === T_QXML_ATTR_VALUE)
			{
				// we need to do some very smart look ahead here
				$frst_ch = $tok[1]{0};
				$last_ch = null;
				$fst = true;
				$val = "";
				
				do
				{
					if ($tok === null)
					{
						$tmp_err_root = $this->getRoot();
						if ($tmp_err_root instanceof QPHPTokenFile)
							qvar_dumpk($tmp_err_root->filename, $tmp_err_root->toString());
						throw new Exception("parsing error unclosed attribute");
					}
					$v = is_string($tok) ? $tok : (is_array($tok) ? $tok[1] : $tok->__toString());
					$last_ch = (is_array($tok) && ($tok[0] === T_QXML_ATTR_VALUE)) ? substr($v, -1, 1) : null;
					if ($fst && ($last_ch === $v))
						$last_ch = null;
					$val .= $v;
					
					if (!$fst)
						$this->children[] = $tok;
					
					$pos++;
					$tok = $tokens[$pos];
					
					$fst = false;
				}
				while ($last_ch !== $frst_ch);
				
				$pos--;
				$tok = $tokens[$pos];
				
				// $val = htmlspecialchars_decode(substr($val, 1, -1), ENT_HTML5);
				$this->attrs[$last_attr] = $val;
				if (strtolower($last_attr) === "id")
					$this->id = $val;
				if (strtolower($last_attr) === "class")
				{
					$_cls_list = substr($this->attrs[$last_attr], 1, -1);
					$this->classes = array_flip(preg_split("/\\s+/us", $_cls_list));
					// var_dump($this->classes);
				}
				// TO DO: in case of an Id OR class, index it on the root
			}
			else if ($type === T_QXML_TAG_SHORT_CLOSE)
			{
				//echo "Ending: {$this->tag} {$pos}<br/>\n";
				$pos++;
				return;
			}

			// increment
			$pos++;
			$tok = $tokens[$pos];
			$type = $tok[0];

			/*
			 * T_QXML_COMMENT

				T_QXML_TAG_OPEN
				T_QXML_TAG_CLOSE
				T_QXML_TAG_END
				T_QXML_TAG_NAME
				T_QXML_ATTR_EQUAL
				T_QXML_ATTR_VALUE
				T_QXML_ATTR_SPACE
				T_QXML_ATTR_NAME
				T_QXML_TEXT
			 */
		}
		
		/**
		 * loop until: 
		 * 
		 *		T_QXML_TAG_OPEN : new element
		 *		<? or <?php		: PHP code/QPHPTokenCode
		 *		T_QXML_TAG_END  : ending this element
		 * 
		 */
		
		// var_dump($this->shortDebugPrint());
		
		while ($tok)
		{
			if ($type === T_QXML_TAG_OPEN)
			{
				$child = new QPHPTokenXmlElement($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this);
			}
			else if (($type === T_OPEN_TAG) || ($type === T_OPEN_TAG_WITH_ECHO))
			{
				$child = new QPHPTokenCode($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this);
				
				if ($tokens[$pos] === "}")
				{
					// in case the PHP code is not not 'aligned' with the XML/XHTML
					/* detailed example:
						public function render()
						{
							?>
							<div>
								<div>

							</div>
							<?php
						}
					*/
					// in this case we need to go back to the parent QPHPTokenCode that opened the "{"
					return;
				}
			}
			else if ($tok === "}")
			{
				// in this case we need to go back to the parent QPHPTokenCode that opened the "{"
				return;
			}
			else if ($type === T_QXML_TAG_END)
			{
				// consume until we hit : T_QXML_TAG_CLOSE
				$this->children[] = $tok;
				$pos++;
				$tok = $tokens[$pos];
				$close_name = $tok[1];
				
				while ($tok)
				{
					$this->children[] = $tok;
					// var_dump($tok);
					// increment
					$pos++;
					if ($type === T_QXML_TAG_CLOSE)
					{
						break;
					}
					$tok = $tokens[$pos];
					$type = $tok[0];
				}
				// echo "Ending: {$this->tag} {$close_name}<br/>\n";
				if (strtolower($close_name) !== strtolower($this->tag))
				{
					echo "<textarea>{$this}</textarea>";
					throw new Exception("A `".$this->tag."` is ending as: `".$close_name."` in ".$this->getRoot()->filename);
				}
				break;
			}
			else
			{
				$this->children[] = $tok;
				// increment
				$pos++;
			}
				
			$tok = $tokens[$pos];
			$type = $tok[0];
		}
		
		/*if ($expand_output && (strtolower($this->tag) === "body"))
			echo "<textarea>{$this}</textarea>";*/
	}
	
	public function generateURLs(QGeneratePatchInfo $gen_info)
	{
		$urls = $this->find("url");
		
		if (empty($urls))
			return;
		
		$get_code = "";
		$get_code .= "\t\tswitch (\$tag)\n\t\t{\n";
				
		foreach ($urls as $url)
		{
			// $attrs = $url->attrs;
			
			$get_code .= "\t\t\tcase \"".$url->attrs["tag"]."\":\n\t\t\t{\n";

			$obj_ref = $url->children("ref");
			$obj_get = $url->children("get");
			$obj_load = $url->children("load");

			if (empty($obj_get))
				throw new Exception("Invalid get specifications");

			$getter = $obj_get[0];
			$loader = $obj_load ? $obj_load[0] : null;
			$reffer = $obj_ref ? $obj_get[0] : null;

			// manage getter
			$get_parts = $getter->find("part");
			if ($get_parts)
			{
				foreach ($get_parts as $get_part)
				{
					if ($get_part->attrs["translate"])
					{
						// call for a translate
					}
					else 
					{
						$gp_code = $get_part->findFirst(".QPHPTokenCode");
						if ($gp_code)
						{
							// strip first & last
							$replacement = null;
							if (isset($gp_code->children[0]) && is_array($gp_code->children[0]) && ($gp_code->children[0][0] === T_OPEN_TAG_WITH_ECHO))
							{
								// $gp_code->children->splice(0, 1);
								array_splice($gp_code->children, 0, 1);
								// $gp_code->children->splice((count($gp_code->children) -1 ), 1);
								array_splice($gp_code->children, (count($gp_code->children) -1 ), 1);
							}
							else
								throw new Exception("We only support echoed data. If you need more complex code use: call_user_func(function() { /** your code */ return \$ret; });");

							$get_code .= "\t\t\t\t\$append_var = {$gp_code};\n".
										"\t\t\t\t\$url->append(\$append_var);\n";
							
						}
						else
						{
							// static text 
							// var_dump("return ".$get_part->children[0]);

						}
					}
				}
			
			}
			
			$get_code .= "\t\t\t\tbreak;\n\t\t\t}\n";
		}
		$get_code .= "\t\t\tdefault:\n\t\t\t\tthrow new Exception('Unknown tag');".
				"\n\t\t}\n";
		
		$root = $this->parent->parent;

		$str  = "<?php\n\nclass {$gen_info->getClassName()}\n{\n\n\tpublic function getURL(\$tag)\n\t{\n";
		$str .= $get_code;
		$str .= "\n\t}\n\n\tpublic function loadFromURL(QUrl \$url)\n\t{\n";
		$str .= "";
		$str .= "\n\t}\n\n}\n\n?>";

		$root->emptyNode(true);
		
		$code = new QPHPTokenCode($root);
		$pos = 0;
		$new_tokens = qtoken_get_all($str);
		$code->parse($new_tokens, $new_tokens[0], $pos, $gen_info->expandOutput(), true);
		$code->setParent($root);
		
		/*
		$code_1 = "<?php\n\nclass {$gen_info->getClassName()}\n{\n\n\tpublic function getURL(\$tag)\n\t{\n";
		$new_tokens = qtoken_get_all($code_1);

		$new_tokens[] = $this;

		$code_2 = "\n\t}\n\n\tpublic function loadFromURL(QUrl \$url)\n\t{\n";

		$new_tokens = array_merge($new_tokens, qtoken_get_all($code_2));

		// TO DO: clone
		$new_tokens[] = $this;

		$code_3 = "\n\t}\n\n}\n\n?>";
		$new_tokens = array_merge($new_tokens, qtoken_get_all($code_3));

		$root->emptyNode();

		$code = new QPHPTokenCode($root);
		$pos = 0;
		$code->parse($new_tokens, $new_tokens[0], $pos, $gen_info->expandOutput());
		$code->setParent($root);
		 */
	}
	
	public function getAppendPos()
	{
		// if it's short close we can not append/prepend until fixed !
		
		// T_QXML_TAG_END : </ : found
		// T_QXML_TAG_SHORT_CLOSE : /> : false
		
		// first </ from the end
		if (!$this->children)
			return false;
		$end = count($this->children) - 1;
		
		do
		{
			$tok = $this->children[$end];
			if (is_array($tok))
			{
				if ($tok[0] === T_QXML_TAG_END)
					return $end;
				else if ($tok[0] === T_QXML_TAG_SHORT_CLOSE)
					return false;
			}
			$end--;
		}
		while ($end > 0);
		return false;
	}

	public function getPrependPos()
	{
		// first > from the top
		if (!$this->children)
			return false;
		$pos = 0;
		$tok = $this->children[$pos];
		
		// T_QXML_TAG_CLOSE : > : found
		// T_QXML_TAG_SHORT_CLOSE : /> : false
		
		while ($tok)
		{
			if (is_array($tok))
			{
				if ($tok[0] === T_QXML_TAG_CLOSE)
					return $pos + 1;
				else if ($tok[0] === T_QXML_TAG_SHORT_CLOSE)
					return false;
			}
			$pos++;
			$tok = $this->children[$pos];
		}
		while ($tok);
		
		return false;
	}
	
	public function generateUrlController(QGeneratePatchInfo $gen_info, QPHPTokenCode $code = null, $return_str = false, $prefix = null, $sufix = null, $args_offset = 0)
	{
		// public function getUrlForTag($tag, QUrl $url = null)
		// public function loadFromUrl(QUrl $url, $parent = null)
		$is_urls = false;
		$continue_in_children = true;
		$global_unload_code = null;
		
		$lctag = strtolower($this->tag);
		
		$return_args_offset = null;
		
		switch ($lctag)
		{
			case "qparent" :
			{
				$is_qparent = true;
				break;
			}
			case "get":
			{
				$u_tag = stripslashes(substr($this->parent->attrs["tag"], 1, -1));
				$class = $gen_info->getClassName();
				
				// echo "<textarea>{$code}</textarea>";
				$switch_code = $code->findPHPClass()->findMethod("GetUrl_")->findSwitchCode();
				
				if ($this->attrs["translate"])
					$get_code = "qTranslate(\"".addslashes(substr($this->attrs["translate"], 1, -1))."\");";
				else
				{
					// we need to replace all $this in code with $_this
					$code_obj = $this->findCode();
					// replace all T_VARIABLE, where value = '$this' with '$_this' - will be easy
					$code_obj->replaceTokenRecursive([T_VARIABLE, '$this'], [T_VARIABLE, '$_this']);
					$get_code = $this->toString($code_obj->inner());
				}
				
				$used_params = $args_offset;
				
				// var_dump("{$u_tag} start: ".$args_offset);
				
				$call_parent = "";
				$has_parent = false;
				if ($this->parent->parent->tag === "url")
				{
					// stacking:
					// $this->getUrlForTag($parent_tag, $url, ???);
					$has_parent = true;
					$parent_tag = $this->parent->parent->attrs["tag"];
					//	have a rule oh how we pass arguments to it's parent
					//			the solution is that if this instance consumes 2 params, we skip 2 params
					$call_parent .= "self::GetUrl_(\$_this, \"".addslashes(substr($parent_tag, 1, -1))."\", \$url";
					for ($parent_params_index = 0; $parent_params_index < $args_offset; $parent_params_index++)
							$call_parent .= ", \$_arg{$parent_params_index}";
					$call_parent .= ");\n";
				}
				
				// ref index : TO DO later
				$get_code = "\$url[] = \$url_part = ".rtrim($get_code, "\n\t ;").";\n";
				
				$set_prefix = $this->attrs["noprefix"] ? false : true;
				$set_sufix = $this->attrs["nosufix"] ? false : true;
				
				$has_child = $this->parent->children("url") ? true : false;
				
				$params_naming = "";
				// param.x
				if ($this->attrs)
				{
					foreach ($this->attrs as $k => $v)
					{
						// var_dump($k);
						if (substr($k, 0, 6) === "param.")
						{
							$pi = substr($k, 6);
							if (is_numeric($pi) && ($pi >=0) && ($pi < 16))
							{
								$params_naming .= "\$".addslashes(substr($v, 1, -1))." = \$_arg".($args_offset).";\n";
								$used_params = max($used_params, (int)$pi);
								$args_offset++;
							}
							else
								throw new Exception("Error in params index in file: ".$gen_info->path);
						}
					}
				}
				
				// $args_offset += $used_params + 1;
				
				// var_dump("{$u_tag} jump: ".$args_offset);
				
				$include_str = $this->parent->generateUrlControllerIncludes(null, false, $prefix, true, false, $args_offset);
				if ($include_str)
				{
					$include_str = trim(str_replace("\n\t\t\t\t", "\n", $include_str));
					// make sure we shift on tag
					$include_str = "if ((!\$_shift) && (\$_shift = true))\n\tarray_shift(\$_tag_parts);\n".str_replace("\t\t\t\t\t\t\t\t", "\t\t\t\t", $include_str);
					
				}
				
				$case_code_str = 
							"\$_called = (\$url === null);\n".
							"\$url = (\$url !== null) ? \$url : array();\n".
							((!$has_parent) ? 
							"if (\$_this && (\$_this->parentPrefixUrl !== null))\n".
								"\t\$url[] = \$_this->parentPrefixUrl;\n" : "").
							(($prefix && $set_prefix && (!$has_parent)) ? "if (\$_prefix)\n".
														"\t"."\$url[] = \$_prefix;\n" : "").
							$call_parent.
							$params_naming.
							$get_code.
							(($sufix && $set_sufix && (!$has_child)) ? "if (\$_sufix)\n".
														"\t"."\$url[] = \$_sufix;\n" : "").
							"if (!next(\$_tag_parts))\n".
								"\treturn \$_called ? implode(\"/\", \$url) : \$url;\n".
							($include_str ? $include_str : "");
				
				$case_code_str = rtrim($case_code_str)."\nbreak;";
				
				$ccs_parts = preg_split("/\\r*\\n/us", $case_code_str);
				$case_code = "";
				foreach ($ccs_parts as $ccp)
					$case_code .= "\t\t\t\t".$ccp."\n";
				
				$switch_code->setCaseCode($u_tag, $case_code);
				
				$return_args_offset = $args_offset;
				
				break;
			}
			case "url":
			{
				if ($this->parent && $this->parent->parent)
				{
					if ($this->parent->parent->_ifscount === null)
						$this->parent->parent->_ifscount = 1;
					else
						$this->parent->parent->_ifscount++;
				}
				break;
			}
			case "load":
			{
				if (strtolower($this->parent->tag) === "urls") // top level LOAD
					return;
				
				if ((!$return_str) && $this->parent->parent && (strtolower($this->parent->parent->tag) === "url"))
				{
					// avoid duplication in <load> elements
					// var_dump("avoid duplication in <load> elements");
					return;
				}
				
				if ($this->parent && $this->parent->parent)
				{
					if ($this->parent->parent->_ifscount === null)
						$this->parent->parent->_ifscount = 1;
					else
						$this->parent->parent->_ifscount++;
				}

				/*
				if ($this->parent->parent->_ifscount === null)
					$this->parent->parent->_ifscount = 1;
				else
					$this->parent->parent->_ifscount++;
				*/

				$continue_in_children = false;
				
				$u_tag = stripslashes(substr($this->parent->attrs["tag"], 1, -1));

				$is_first_url = ($this->parent->parent->_ifscount === 1);

				$extra_xd_tab = "\t";
				// fix made by Mihai
				if ($this->parent->parent->tag == "urls")
				{
					// now we always have something above
					if ($is_first_url)
					{
						if ($gen_info->first_setted)
							$is_first_url = false;
						else
						{
							$gen_info->first_setted = true;
							$extra_xd_tab = "";
						}
					}
					$is_first_url = false;
				}

				$is_not_found = ($this->parent->tag === "notfound");
				$test_str = (($is_first_url || $is_not_found) ? "\t" : $extra_xd_tab."\t")."if ((!\$_rv) && (\$testResult = (";
				$has_translate = false;
				
				if (empty($u_tag))
				{
					if ($this->parent->tag === "index")
					{
						$test_str .= "\$url->isIndex()";
					}
					else if ($is_not_found)
					{
						// always true if we get here
						$test_str .= "true";
					}
					else // <load> or <unload>
					{
						// throw new Exception("Parse error. The url's has no tag");
					}
				}
				else 
				{
					$test_node = $this->parent->findChildWithTag("test");
					if (!$test_node)
					{
						$get_node = $this->parent->findChildWithTag("get");
						if ($get_node && $get_node->attrs["translate"])
						{
							$test_str .= "\$url->current() == qTranslate(\"".addslashes(substr($get_node->attrs["translate"], 1, -1))."\")";
							$has_translate = true;
						}
						else
						{
							// throw new Exception("Missing test condition for load");
							$test_str .= "false";
						}
					}
					else
						$test_str .= $this->toString( $this->rightTrimCodeSemicolon($test_node->findCode()->inner()) );
				}
				
				$test_str .= ")))\n";
				
				// START
				$load_str = "";
				// what to do from here
				// $load_str .= "\t\t\$self = isset(\$this) ? \$this : null;\n\n";
				
				// manage render attribute
				if ($this->attrs["render"])
				{
					throw new Exception("no longer supported");
					// $load_str .= "\$self = QApp::SetWebPageCallback(\"".substr($this->attrs["render"], 1, -1)."\")->Object;\n";
				}

				// property ?! : name class(or scalar) value
				// code inside property, assignment inside property
				$load_str .= $this->generateUrlControllerProps("self");
				
				// code inside load
				$load_code = $this->findCode();
				if ($load_code)
				{
					// TO DO: append this in the right order
					$load_str .= $this->toString($load_code->inner());
				}
				
				// no longer autoadvance, let the code determine
				// $load_str .= "\$url->next();\n";
				
				// child url(s)
				$child_urls = 0;
				foreach ($this->parent->children as $p_child)
				{
					if (($p_child instanceof QPHPTokenXmlElement) && ((($p_child_lc = strtolower($p_child->tag)) === "url") || ($p_child_lc === "index") || ($p_child_lc === "include")))
					{
						$child_load_block = $p_child->findChildWithTag("load");
						if ($child_load_block)
						{
							$load_str .= $child_load_block->generateUrlController($gen_info, $code, true);
							$child_urls++;
						}
					}
				}
				
				// END
				$meth_name_tag = (strtolower($this->parent->tag) === "url") ? $this->parent->getAttribute("tag") : $this->parent->tag;
				$meth_name = "loadUrl" . $this->tagToFuncName($meth_name_tag);
				
				$include_str = $this->generateUrlControllerIncludesForLoad($this->parent->children("include"));
				
				$unload_element = $this->parent->findChildWithTag("unload");
				$unload_str = $unload_element && ($unload_code = $unload_element->findCode()) ? $this->toString($unload_code->inner()) : null;
				
				// should check if there is a return the right way
				
				$load_str = "\n\t/**\n".
							"\t * Generated.loadUrl method\n".
							"\t * {$meth_name}\n".
							"\t */\n".
							"\tpublic function {$meth_name}(\QUrl \$url, \$testResult)\n".
							"\t{\n".
							($has_translate ? "\t\t\$url->next();\n" : "").
							"\t\t\$_rv = null;\n".
									($load_str ? rtrim($load_str)."\n" : "").
									($include_str ? rtrim($include_str)."\n" : "").
									($unload_str ? rtrim($unload_str)."\n" : "").
									(true ? "\t\treturn \$_rv;\n" : ""). //$child_urls
							"\t}\n";

				$exist_meth = $code->findPHPClass()->findMethod($meth_name);
				
				if ($exist_meth)
				{
					$exist_meth->emptyNode();
					$exist_meth->append($load_str);
				}
				else
					$code->findPHPClass()->append($load_str);

				$test_str .= "\t\t\t\$_rv = \$this->{$meth_name}(\$url, \$testResult);\n";
									// "\t\t\t\treturn \$_rv;\n";
				
				// $test_str .= "\t\t}\n";
				
				if ($return_str)
					return $test_str;
				else 
				{
					$load_meth_code = $code->findPHPClass()->findMethod("loadFromUrl")->findCode();
					if (!$load_meth_code)
						throw new Exception("Unable to find load method");

					$load_meth_code->append($test_str);
					break;
				}
				
				break;
			}
			case "urls":
			{
				$class = $gen_info->getClassName();
				$extends = ($_ext = $this->attr("extends")) ? substr($_ext, 1, -1) : null;
				$implements = $this->getAttribute("implements");
				
				$global_load_code_obj = $this->findFirstCodeElement();
				if (!$global_load_code_obj)
				{
					$global_load_code_obj = $this->findFirstChildWithTag("load");
					if ($global_load_code_obj)
						$global_load_code_obj = $global_load_code_obj->findFirstCodeElement();
				}
				$global_load_code = $global_load_code_obj ? $this->toString($global_load_code_obj->inner()) : null;
				
				$global_unload_obj = $this->findLastCodeElement();
				if ($global_unload_obj === $global_load_code_obj)
					$global_unload_obj = null;
				if (!$global_unload_obj)
				{
					$global_unload_obj = $this->findFirstChildWithTag("unload");
					if ($global_unload_obj)
						$global_unload_obj = $global_unload_obj->findFirstCodeElement();
				}
				$global_unload_code = $global_unload_obj ? $this->toString($global_unload_obj->inner()) : null;
				
				$prefix = $this->findChildWithTag("prefix");
				$prefix = $prefix ? $prefix->findCode() : null;
				$prefix = $prefix ? $this->toString($prefix->inner()) : null;
				$sufix = $this->findChildWithTag("sufix");
				$sufix = $sufix ? $sufix->findCode() : null;
				$sufix = $sufix ? $this->toString($sufix->inner()) : null;
				
				// var_dump($prefix ? $this->toString($prefix) : $prefix, $sufix ? $this->toString($sufix) : $sufix);
				$code = $this->setupClassFileContext($class, $gen_info->namespace, $extends, $implements, $global_load_code, $prefix, $sufix);
				
				$this->generateUrlControllerIncludes($code->findPHPClass()->findMethod("GetUrl_")->findSwitchCode()->findCaseWithValue(null, true), true, $prefix, null, false);
				
				$is_urls = true;
				
				break;
			}
			default:
				break;
		}
		
		if ($continue_in_children && $this->children)
		{
			// call index first, call notfound last
			// changed: we don't force index first as we may need includes like LOGIN to process before INDEX
			if ($lctag === "urls")
			{
				$urls_list = array();
				$not_found_child = null;

				foreach ($this->children as $child)
				{
					if ($child instanceof QPHPToken)
					{
						// call them in an order
						$child_tag = strtolower($child->tag);
						/*if ($child_tag === "index")
							array_unshift($urls_list, $child);
						else 
						 * 
						 */
						if ($child_tag === "notfound")
							$not_found_child = $child;
						else
							$urls_list[] = $child;
					}
				}
				
				if ($not_found_child)
					$urls_list[] = $not_found_child;
				
				$_ccode = $code;
				$load_meth_code = null;
				
				foreach ($urls_list as $child)
				{
					if ($child instanceof QPHPToken)
					{
						if (strtolower($child->tag) === "include")
						{
							$include_load_str = $this->generateUrlControllerIncludesForLoad($child);
							if (!$load_meth_code)
								$load_meth_code = $code->findPHPClass()->findMethod("loadFromUrl")->findCode();
							
							$load_meth_code->append($include_load_str."\n");
						}
						// var_dump("param A: ".$args_offset);
						$ret = $child->generateUrlController($gen_info, $_ccode, false, $prefix, $sufix, $args_offset);
						$_ccode = $_ccode ?: $ret;
					}
				}
			}
			else
			{
				foreach ($this->children as $child)
				{
					if ($child instanceof QPHPToken)
					{
						// var_dump("param B: ".$args_offset);
						$possible_args_offset = $child->generateUrlController($gen_info, $code, false, $prefix, $sufix, $args_offset);
						if ((strtolower($child->tag) === "get") && $possible_args_offset && is_int($possible_args_offset))
						{
							$args_offset = $possible_args_offset;
						}
					}
				}
				// $possible_args_offset = parent::generateUrlController($gen_info, $code, false, $prefix, $sufix, $args_offset);
			}
		}
		
		if ($is_urls)
		{
			if (!$global_unload_code)
				$global_unload_code = "";
			
			$global_unload_code .= "\n\t\treturn \$_rv;\n\t";
				
			// var_dump($global_unload_code);
			$load_meth_code = $code->findPHPClass()->findMethod("loadFromUrl")->findCode();
			if (!$load_meth_code)
				throw new Exception("Unable to find load method");

			$load_meth_code->append($global_unload_code);
			// echo "<textarea>{$global_unload_code}</textarea><br/>\n";
		}
		
		return ($return_args_offset !== null) ? $return_args_offset : $code;
	}
	
	public function generateUrlControllerIncludes(\QPHPTokenCode $code = null, $in_default = false, $prefix = null, $set_prefix = null, $has_parent = null, $args_offset = 0)
	{
		if ($set_prefix === null)
			$set_prefix = $this->attrs["noprefix"] ? false : true;
		
		$include_blocks = $this->findChildrenWithTag("include");
		if (!$include_blocks)
			return;
		
		// @todo : set sufix !!! (($sufix && $set_sufix && (!$has_child)) ? "if (\$_sufix)\n".
		
		$code_str = "";
		if ($in_default)
			$code_str .= "\t\$_called = (\$url === null);\n".
					"\t\t\t\t\$url = (\$url !== null) ? \$url : array();\n".
							((!$has_parent) ?
							"\t\t\t\tif (\$_this && \$_this->parentPrefixUrl)\n".
								"\t\t\t\t\t\$url[] = \$_this->parentPrefixUrl;\n" : "").
					(($prefix && $set_prefix && (!$has_parent)) ? "\t\t\t\t\tif (\$_prefix)\n".
																		"\t\t\t\t\t\t"."\$url[] = \$_prefix;\n" : "").
					"\t\t\t\t// we put includes that are in <urls> here\n";
		foreach ($include_blocks as $incl_block)
		{
			$incl_class = $incl_block->getAttribute("class");
			$code_str .= "\t\t\t\tif ((\$return = {$incl_class}::GetUrl_(null, \$_tag_parts, \$url";
			
			for ($params_index = $args_offset; $params_index < 16; $params_index++)
					$code_str .= ", \$_arg{$params_index}";
					
			$code_str .= ")))\n".	
						"\t\t\t\t\treturn \$_called ? implode(\"/\", \$url) : \$url;\n";
		}
		
		if ($in_default)
			$code_str .= "\t\t\t\tbreak;\n\t\t\t";
		
		if ($code)
			$code->append($code_str);
		
		return $code_str;
	}

	/**
	 * 
	 * @param string $class
	 * @param string $namespace
	 * @param string $extends
	 * @param string[] $implements
	 * @param string $global_load_code
	 * @param string $prefix
	 * @param string $sufix
	 * @return \QPHPTokenCode
	 */
	public function setupClassFileContext($class, $namespace = null, $extends = null, $implements = null, $global_load_code = null, $prefix = null, $sufix = null)
	{
		// public function getUrlForTag($tag, QUrl $url = null)
		// public function loadFromUrl(QUrl $url, $parent = null)
		$global_load_code = $global_load_code ?: "";
		
		$frameNs = __NAMESPACE__."\\";
		
		// var_dump($namespace);
		if (is_array($implements))
			$implements = implode(", ", $implements);
		else if ($implements === null)
			$implements = "{$frameNs}QIUrlController";
			
		// ".($extends ? trim(" extends {$extends}") : "").($implements ? " implements {$implements}" : "")."
		
		$tokens = qtoken_get_all($s = "<?php

".($namespace ? "namespace {$namespace};\n\n" : "").
"trait {$class}_GenTrait
{
	public static function GetUrl_(\$_this, \$tag = \"\", &\$url = null, \$_arg0 = null, \$_arg1 = null, \$_arg2 = null, \$_arg3 = null, \$_arg4 = null, \$_arg5 = null, \$_arg6 = null, \$_arg7 = null, \$_arg8 = null, \$_arg9 = null, \$_arg10 = null, \$_arg11 = null, \$_arg12 = null, \$_arg13 = null, \$_arg14 = null, \$_arg15 = null)
	{
		\$_tag_parts = null;
		\$tag = is_string(\$tag) ? reset(\$_tag_parts = explode(\"/\", \$tag)) : reset(\$_tag_parts = \$tag);
		\$_shift = false;\n".
		($prefix ? "\t\t\$_prefix = {$prefix};\n" : "").
		($sufix ? "\t\t\$_sufix = {$sufix};\n" : "").
"		switch (\$tag)
		{
			default:
			{
				if (empty(\$tag))
				{
					\$_called = (\$url === null);
					\$url = (\$url !== null) ? \$url : array();
					if (\$_this && (\$_this->parentPrefixUrl !== null))
						\$url[] = \$_this->parentPrefixUrl;
					if (\$_prefix)
						\$url[] = \$_prefix;
					if (!next(\$_tag_parts))
						return \$_called ? implode(\"/\", \$url) : \$url;
				}
				else
					throw new \\Exception(\"Unknown tag `{\$tag}`\");
			}
		}
	}
	
	public static function GetUrl(\$tag = \"\", \$_arg0 = null, \$_arg1 = null, \$_arg2 = null, \$_arg3 = null, \$_arg4 = null, \$_arg5 = null, \$_arg6 = null, \$_arg7 = null, \$_arg8 = null, \$_arg9 = null, \$_arg10 = null, \$_arg11 = null, \$_arg12 = null, \$_arg13 = null, \$_arg14 = null, \$_arg15 = null)
	{
		\$url = null;
		return self::GetUrl_(null, \$tag, \$url, \$_arg0, \$_arg1, \$_arg2, \$_arg3, \$_arg4, \$_arg5, \$_arg6, \$_arg7, \$_arg8, \$_arg9, \$_arg10, \$_arg11, \$_arg12, \$_arg13, \$_arg14, \$_arg15);
	}
	
	public function getUrlForTag_(\$tag = \"\", &\$url = null, \$_arg0 = null, \$_arg1 = null, \$_arg2 = null, \$_arg3 = null, \$_arg4 = null, \$_arg5 = null, \$_arg6 = null, \$_arg7 = null, \$_arg8 = null, \$_arg9 = null, \$_arg10 = null, \$_arg11 = null, \$_arg12 = null, \$_arg13 = null, \$_arg14 = null, \$_arg15 = null)
	{
		return self::GetUrl_(\$this, \$tag, \$url, \$_arg0, \$_arg1, \$_arg2, \$_arg3, \$_arg4, \$_arg5, \$_arg6, \$_arg7, \$_arg8, \$_arg9, \$_arg10, \$_arg11, \$_arg12, \$_arg13, \$_arg14, \$_arg15);
	}
	
	public function getUrlForTag(\$tag = \"\", \$_arg0 = null, \$_arg1 = null, \$_arg2 = null, \$_arg3 = null, \$_arg4 = null, \$_arg5 = null, \$_arg6 = null, \$_arg7 = null, \$_arg8 = null, \$_arg9 = null, \$_arg10 = null, \$_arg11 = null, \$_arg12 = null, \$_arg13 = null, \$_arg14 = null, \$_arg15 = null)
	{
		\$url = null;
		return self::GetUrl_(\$this, \$tag, \$url, \$_arg0, \$_arg1, \$_arg2, \$_arg3, \$_arg4, \$_arg5, \$_arg6, \$_arg7, \$_arg8, \$_arg9, \$_arg10, \$_arg11, \$_arg12, \$_arg13, \$_arg14, \$_arg15);
	}
	
	public function getUrlSelf(\$tag = \"\", \$_arg0 = null, \$_arg1 = null, \$_arg2 = null, \$_arg3 = null, \$_arg4 = null, \$_arg5 = null, \$_arg6 = null, \$_arg7 = null, \$_arg8 = null, \$_arg9 = null, \$_arg10 = null, \$_arg11 = null, \$_arg12 = null, \$_arg13 = null, \$_arg14 = null, \$_arg15 = null)
	{
		\$url = null;
		\$saved = \$this->parentPrefixUrl;
		\$this->parentPrefixUrl = null;
		\$return = self::GetUrl_(\$this, \$tag, \$url, \$_arg0, \$_arg1, \$_arg2, \$_arg3, \$_arg4, \$_arg5, \$_arg6, \$_arg7, \$_arg8, \$_arg9, \$_arg10, \$_arg11, \$_arg12, \$_arg13, \$_arg14, \$_arg15);
		\$this->parentPrefixUrl = \$saved;
		return \$return;
	}
	
	public function loadFromUrl({$frameNs}QUrl \$url, \$parent = null)
	{
		\$this->parentUrl = \$parent;
		\$this->parentPrefixUrl = \$url->getConsumedAsString() ?: null;
		// we should change this in the future, needed by admin module atm
		\$init_return = \$this->initController(\$url, \$parent);
		if (\$init_return !== null)
			\$_rv = \$init_return;
		else if ({$frameNs}QWebRequest::IsFastAjax())
			\$_rv = true;
	}
	
	public function initController({$frameNs}QUrl \$url = null, \$parent = null)
	{
		{$global_load_code}
	}
}

");

		$code = new QPHPTokenCode();
		$pos = 0;
		$code->parse($tokens, $tokens[0], $pos, false, true);
		
		return $code;
	}
	
	protected function generateUrlControllerProps($this_var = null)
	{
		// property ?! : name class(or scalar) value
		// code inside property, assignment inside property
		if ($this->children)
		{
			$ret = "";
			foreach ($this->children as $child)
			{
				if (($child instanceof QPHPTokenXmlElement) && (strtolower($child->tag) === "property"))
				{
					// name, class/scalar, value
					// code inside property
					// assignment inside property
					$name = $child->attrs["name"];
					$name = $name ? substr($name, 1, -1) : null;
					if (empty($name))
						throw new Exception("Missing name for property");
					
					$class = $child->attrs["class"];
					$is_scalar = false;
					if ($class)
					{
						$class = substr($class, 1, -1);
						// $typ = QModel::GetTypeByName($class);
						if (strtolower($class{0}) === $class{0})
						{
							// handle scalar
							$is_scalar = true;
						}
						else
							$ret .= "\${$this_var}->{$name} = new {$class}();\n";
					}
					
					$value = $child->attrs["value"];
					if ($value !== null)
						$ret .= "\${$this_var}->{$name} = {$value};\n";
						
					$code = $child->findCode();
					
					if ($code && $code->children && (count($code->children) > 0))
					{
						$frst = $code->children[0];
						if (is_array($frst))
						{
							if ($frst[0] === T_OPEN_TAG_WITH_ECHO)
							{
								// assignment inside property
								$ret .= "\${$this_var}->{$name} = ".$this->toString($this->rightTrimCodeSemicolon($code->inner())).";\n";
							}
							else if ($frst[0] === T_OPEN_TAG)
							{
								// code inside property
								$f_name = "func_".uniqid();
								$ret .= "\${$f_name} = function (\$self) use(\$testResult) {".$this->toString($code->inner())."};\n".
										"\${$f_name}(\${$this_var}->{$name});\n";
							}
						}
					}
					
					// now recurse
					$ret .= $child->generateUrlControllerProps("{$this_var}->{$name}");
				}
			}
			
			return $ret;
		}
		else
			return "";
	}
	
	public function generateEventMethod(QGeneratePatchInfo $gen_info, QPHPTokenCode $code = null, $return_str = false)
	{
		// public function getUrlForTag($tag, QUrl $url = null)
		// public function loadFromUrl(QUrl $url, $parent = null)
		
		// $is_urls = false;
		/**
			<events>
				<!-- event name='transform' -->
				<ontransform />
				<oncreate />
				<onmerge />
				<ondelete />
				<onupdate />
				<onappend />
				<onfix />
				<onpropertychange name="abcd"><?php
				?></onpropertychange>
			</events>
			 */
		
		$lctag = strtolower($this->tag);
		
		if ($lctag === "events")
		{
			$code = $this->setupClassFileContextForEvent($gen_info->class_name);
			
			$switch = array();
			$switch_code = $code->findFirst(".QPHPTokenClass")->findMethod("triggerEvent")->findSwitchCode();
			
			//echo "<textarea>{$switch_code}</textarea>";
				
			foreach ($this->children as $child_ev)
			{
				if (!($child_ev instanceof QPHPTokenXmlElement))
					continue;
				
				$ch_tagg = strtolower($child_ev->tag);
				$ev_name = (substr($ch_tagg, 0, 2) === "on") ? substr($ch_tagg, 2) : $ch_tagg;
				
				$flag = null;
				$ev = "transform";
				$prop_name = null;
				
				switch ($ev_name)
				{
					case "transform":
					{
						$flag = null;
						$ev = "transform";
						break;
					}
					case "create":
					{
						$flag = 'QModel::TransformCreate';
						$ev = "transform";
						break;
					}
					case "merge":
					{
						$flag = 'QModel::TransformMerge';
						$ev = "transform";
						break;
					}
					case "delete":
					{
						$flag = 'QModel::TransformDelete';
						$ev = "transform";
						break;
					}
					case "update":
					{
						$flag = 'QModel::TransformUpdate';
						$ev = "transform";
						break;
					}
					case "append":
					{
						$flag = 'QModel::TransformAppend';
						$ev = "transform";
						break;
					}
					case "fix":
					{
						$flag = 'QModel::TransformAppend';
						$ev = "transform";
						break;
					}
					case "propertychange":
					{
						$ev = "transform";
						$prop_name = substr($child_ev->attr["name"], 1, -1);
						break;
					}
					default:
					{
						$ev = $ev_name;
						break;
					}
				}
				
				$add_bra = (((($flag !== null) ? 1 : 0) + ($prop_name ? 1 : 0)) > 1);
				
				if ($flag || $prop_name)
				{
					$switch[$ev] .= "\t\t\t\tif (";

					if ($flag)
						$switch[$ev] .= ($add_bra ? "(" : "") . "\$params['actualAction'] & {$flag}" . ($add_bra ? ") && " : "");
					else if ($prop_name)
						$switch[$ev] .= ($add_bra ? "(" : "") . "\$this->wasChanged(".json_encode($prop_name).")" . ($add_bra ? ")" : "");

					$switch[$ev] .= ")\n\t\t\t\t{\n".$this->toString($child_ev->findCode()->getWithoutPhpTags())."\n\t\t\t\t}\n";
				}
				else
					$switch[$ev] .= "\n".$this->toString($child_ev->findCode()->getWithoutPhpTags())."\n";
			}
			
			foreach ($switch as $key => $sw_code)
			{
				// var_dump($key, $sw_code);
				$switch_code->setCaseCode($key, $sw_code."\n\t\t\t\tbreak;\n");
			}
		}
		
		return $code;
	}

	public function setupClassFileContextForEvent($class)
	{
		// public function getUrlForTag($tag, QUrl $url = null)
		// public function loadFromUrl(QUrl $url, $parent = null)
		
		$tokens = qtoken_get_all("<?php
	
class {$class}
{
	public function triggerEvent(\$name_or_method, \$params, \$recursive = false)
	{
		switch (\$name_or_method)
		{
			default:
			{
				break;
			}
		}
	}
}

?>");
		
		$code = new QPHPTokenCode();
		$pos = 0;
		$code->parse($tokens, $tokens[0], $pos, false, true);
		
		return $code;
	}
	
	public function testAndSkipJsFunc(&$tokens, &$ret, $current_name = null, $str_escape = false)
	{
		if ($this->attrs["jsFunc"])
		{
			// var_dump($this."");
			
			$parsed_jsFunc = $this->getAttribute("jsFunc");
			$ret_p = QPHPTokenDocComment::ParseJsFuncArguments($parsed_jsFunc);
			
			if ($ret_p && $ret_p["name"])
			{
				// var_dump($current_name);
				if ($current_name === $ret_p["name"])
					return false;

				if ($ret_p["meta"] && ($ret_p["meta"] === ":start"))
				{
					// we should determine from where to where to skip
					throw new Exception("not implemented yet");
				}
				else
				{
					if ($str_escape)
						$ret[] = "\" + ";
					
					$ret[] = " this.".$ret_p["name"]."(".($ret_p["params"] ? implode(",", $ret_p["params"]) : "").");";
					
					if ($str_escape)
						$ret[] = " + \"";
					
					return true;
				}
			}
		}
		return false;
	}
	
	
	public function inheritFrom(QPHPToken $patch_token, QPHPTokenFile $patch_file = null, QPHPTokenFile $root_file = null, QGeneratePatchInfo $gen_info = null)
	{
		// var_dump($this->getAttribute("qMerge"));
		// skip inherit if flagged
		$attr_qmerge = $this->getAttribute("qMerge");
		/*if (($patch_file && ($patch_file->extension === "tpl")) || ($gen_info && $gen_info->type === "tpl") || ($root_file && $root_file->extension === "tpl"))
		{
			if ((!$attr_qmerge) || (strtolower($attr_qmerge) !== "true"))
				return;
		}
		else*/
		if ($attr_qmerge && (strtolower($attr_qmerge) === "false"))
			return;
		
		// setup attributes merge
		if ($patch_token->attrs)
		{
			foreach ($patch_token->attrs as $name => $attr)
			{
				if (!$this->attrs[$name])
					$this->setAttribute($name, $attr, false);
			}
		}
		
		// handle in tag PHP code
		$pp_pos = $this->getPrependPos();
		$this_pp_code_count = 0;
		for ($i = 0; $i < $pp_pos; $i++)
		{
			$element = $this->children[$i];
			if ($element instanceof QPHPTokenCode)
				$this_pp_code_count++;
		}
		if ($this_pp_code_count === 0)
		{
			$pp_p_pos = $patch_token->getPrependPos();
			for ($i = 0; $i < $pp_p_pos; $i++)
			{
				$element = $patch_token->children[$i];
				if ($element instanceof QPHPTokenCode)
				{
					// $this->children->splice($pp_pos - 1, 0, array($element));
					array_splice($this->children, $pp_pos - 1, 0, array($element));
					$element->parent = $this;
				}
			}
		}
		
		parent::inheritFrom($patch_token, $patch_file, $root_file, $gen_info);
	}
	
	public function getMatchingKey($pos, $types_index, $gen_type)
	{
		if (!$this->parent)
			return "[0]";
		else if ($this->parent instanceof QPHPTokenFile)
		{
			if (count($this->parent->children(".QPHPTokenXmlElement")) === 1)
				return "[0]";
			else
				return $this->tag."[".$this->className."]";
		}
		
		$merge_mode = $this->getAttribute("mergeMode");
		$merge_tag = $this->getAttribute("mergeTag");
		
		if ($merge_tag)
			return $merge_mode ? array($merge_tag, $merge_mode) : $merge_tag;
		
		$use_index = true;
		$tag_lc = strtolower($this->tag);
		
		if (($gen_type === "url") && ($tag_lc === "url"))
		{
			$c = $this->getAttribute("tag");
			$use_index = false;
		}
		/*
		else if (($gen_type === "url") && (($tag_lc === "get") || ($tag_lc === "test") || ($tag_lc === "load")))
		{
			// full rewrite, no merge
			return false;
		}
		*/
		else if (in_array($tag_lc, array("html", "head", "body")))
		{
			$c = $this->tag;
			$use_index = false;
		}
		else if ($tag_lc === "meta")
		{
			$c = "meta/".$this->getAttribute("name");
			$use_index = false;
		}
		else if ($tag_lc === "script")
		{
			$c = "script/".$this->getAttribute("src");
			$use_index = false;
		}
		else if ($tag_lc === "link")
		{
			$c = "script/".$this->getAttribute("href");
			$use_index = false;
		}
		else
			// the default
			$c = ($id = $this->getAttribute("id")) ? $id : ($this->tag.(($classes = $this->getAttribute("class")) ? ":".$classes : ""));
		
		$ty_index = $types_index[$c] ? $types_index[$c]++ : ($types_index[$c] = 0);
		$ret_val = ($use_index ? "[{$ty_index}]" : "").$c;
		return $merge_mode ? array($ret_val, $merge_mode) : $ret_val;
	}
	
	protected function tagToFuncName($tag)
	{
		$parts = preg_split("/\\-|\\_|\\||\\~|\\#|\\@|\\\$|\\^|\\:|\\+|\\.|\\/|\\\\/us", $tag, -1, PREG_SPLIT_NO_EMPTY);
		$ret = "";
		foreach ($parts as $p)
			$ret .= ucfirst($p);
		return $ret;
	}
	
	public function cleanupTemplate()
	{
		if ((strtolower($this->tag) === "script") && $this->attrs["jsFuncMode"])
		{
			$this->children = array();
		}
		else
			parent::cleanupTemplate();
	}
	
	public function toString($formated = false, $final = false, $data = null)
	{
		if ($final && is_bool($formated))
		{
			$lc_tag = strtolower($this->tag);
			
			/*
			$after = $before = null;
			if ($this->attrs["q-var"])
			{
				$q_rel_var = $data ? $data["q-rel-var"] : null;
				$q_var = $this->getAttribute("q-var");
				$php_var = $data["q-rel-var"] = $this->extractQVarForCode($q_var, $q_rel_var);
				
				// we should now inprint data based on the bind
				switch ($lc_tag)
				{
					case "input":
					{
						if (!$this->attrs["value"])
							$this->setAttribute("value", "\"<?= {$php_var} ?>\"", false);
						break;
					}
					// case "select": // <option value="value2" selected>Value 2</option>
					case "textarea":
					{
						if ($this->innerIsEmpty())
							$this->inner("<?= {$php_var} ?>");
						break;
					}
					default:
					{
						# if (!$this->attrs["q-val"])
						#	$this->setAttribute("q-val", "\"<?= {$php_var} ?>\"", false);
						if ($this->innerIsEmpty())
							$this->inner("<?= {$php_var} ?>");
						break;
					}
				}
			}
			if ($this->attrs["q-each"])
			{
				$q_rel_var = $data ? $data["q-rel-var"] : null;
				
				$var_q_each = $this->getAttribute("q-each");
				$var_patt = '/[\w\$\\\\\.\(\)\[\]]+|\bin\b|\bas\b/us';
				// var_dump($var_patt);
				$matches = null;
				$ok = preg_match_all($var_patt, $var_q_each, $matches);
				if (!$ok)
					// throw new Exception("Parse error in q-each: ".$var_q_each);
					return null;
				list($p_1, $p_2, $p_3) = $matches[0];
				if (!($p_1 && $p_2 && $p_3))
					// throw new Exception("Parse error in q-each: ".$var_q_each);
					return null;
				$each_collection = $this->extractQVarForCode( ($p_2 === "as") ? $p_1 : $p_3, $q_rel_var);
				$each_item = $this->extractQVarForCode( ($p_2 === "as") ? $p_3 : $p_1, $q_rel_var);
				
				$data["q-rel-var"] = $each_item;
				
				// var q_each_parts = q_each.match(/[\w\$\\\.\(\)\[\]]+|\bin\b|\bas\b/g);
				//var_dump($each_collection, $each_item);
				$before = "<!-- OMI-MARK: <div q-each={$this->attrs["q-each"]}></div> -->\n".
							"<?php if ({$each_collection}) { foreach ({$each_collection} as {$each_item}) { ?>\n";
				$after = $this->attrs["q-start"] ? "" : "\n<?php } } ?>\n";
			}
			if ($this->attrs["q-end"])
			{
				$before = "";
				$after = "\n<?php } } ?>\n";
			}
			
			if ($after || $before)
				return $before.parent::toString(($lc_tag === "virtual") ? $this->inner() : $formated, $final, $data).$after;
			else
				*/
			return parent::toString(($lc_tag === "virtual") ? $this->inner() : $formated, $final, $data);
		}
		return parent::toString($formated, $final, $data);
	}
	
	public function generateUrlControllerIncludesForLoad($includes_to_load)
	{
		if (!is_array($includes_to_load))
			$includes_to_load = array($includes_to_load);
		
		if (!$includes_to_load)
			return null;
		
		$include_str = "";
		$inclds_done = 0;
		foreach ($includes_to_load as $incl)
		{
			// var_dump($incl."");
			$incl_obj_class = $incl->getAttribute("class");
			$incl_obj_var = $incl->getAttribute("var");
			$incl_obj_noload = $incl->getAttribute("noload");
			if ($incl_obj_noload && (strtolower($incl_obj_noload) !== "false"))
				continue;

			$include_str .= "\t\t".($inclds_done ? "else " : "")."if ((!\$_rv) && ";
			
			// if ((!$_rv) && ($_tctrl = new View\Pages()) && ($_rv_tmp = $_tctrl->loadFromUrl($url, $this)))
			// (\$_rv_tmp = (
			if ($incl_obj_var)
				$include_str .= "({$incl_obj_var} = new {$incl_obj_class}()) && (\$_rv_tmp = {$incl_obj_var}->loadFromUrl(\$url, \$this))";
			else if ($incl_obj_class)
				$include_str .= "(\$_rv_tmp = (new {$incl_obj_class}())->loadFromUrl(\$url, \$this))";
			else
				throw new Exception("No rule for include statement");
			
			$include_str .= ")\n\t\t\t\t\$_rv = \$_rv_tmp;\n";

			$inclds_done++;
		}
		
		return $include_str;
	}
	
	public function extractQVarForCode($var, $q_rel_var = null)
	{
		$matches = null;
		$var = trim($var);
		$ok = preg_match_all('/\([\w\\\$]+\)|\[[\w\\\$]*\]|[\w\$]+|\.|\\-\\>/us', $var, $matches);
		if (!$ok)
			return null;
		$parts = $matches[0];
		if (!$parts)
			return null;
		
		$php_var = ($parts[0] === ".") ? $q_rel_var :  "";
		$last = null;
		foreach ($parts as $p)
		{
			if (($p === ".") || ($p === "->"))
			{
				$php_var .= "->";
				$last = ".";
			}
			else if (!(($p{0} === "(") || ($p{0} === "[")))
			{
				$php_var .= $p;
				$last = $p;
			}
		}
		return ($last === ".") ? substr($php_var, 0, -2) : $php_var;
	}
	
	public function query($css_selector, &$findings = null)
	{
		if ($findings === null)
			$findings = [];
		
		$css_selector_list = null;
		if (is_string($css_selector))
		{
			$css_selector_list = $this->parseCssSelector($css_selector);
			if (!$css_selector_list)
				return null;
		}
		else
			$css_selector_list = $css_selector;
		
		$child_selectors = [];
		
		//var_dump($css_selector_list);
		
		foreach ($css_selector_list as $sel_key => $css_selector_inf)
		{
			list ($css_selector, $s_pos, $must_match) = $css_selector_inf;
			if (!$must_match)
				$child_selectors[$sel_key] = $css_selector_inf;
			// now consume
			$len = count($css_selector);
			while (($part = $css_selector[$s_pos]) !== null)
			{
				$part = trim($part);
				$is_last = ($len === ($s_pos + 1));

				if (($part === "") || ($part === ">")) // space
				{
					// loop children
					$s_pos++;
					if ($s_pos > $len)
					{
						qvar_dump($css_selector, $s_pos, $must_match);
						throw new \Exception('Algo failed');
					}
					$sub_sel_key = $s_pos."|".(($part === ">") ? "0" : "1");
					$child_selectors[$sub_sel_key] = [$css_selector, $s_pos, ($part === ">")];
					// parent::query($css_selector, $s_pos, ($part === ">"), $findings);
					break;
				}
				else 
				{
					// we handle conditions
					$first_ch = $part{0};
					$is_matching = false;
					switch ($first_ch)
					{
						case "#":
						{
							$is_matching = ($this->getAttribute("id") === substr($part, 1));
							break;
						}
						case ".":
						{
							$is_matching = $this->hasClass(substr($part, 1));
							if (!$is_matching)
							{
								$classes = $this->getAttribute("class");
								if ($classes)
								{
									$class_matches = null;
									$c_ok = preg_match_all("/[a-zA-Z0-9\\_\\-]+/us", $classes, $class_matches);
									if ($c_ok && $class_matches && $class_matches[0])
										$is_matching = in_array(substr($part, 1), $class_matches[0]);
								}
							}
							break;
						}
						case "*": // any
						{
							$is_matching = $this->tag ? true : false;
							break;
						}
						case "[":
						{
							// this one it's a bit bigger
							$attr_matches = null;
							$at_ok = preg_match("/".
									// attribute : *= includes, ^= begins, $= ends
									"\\[([a-zA-Z0-9\\_\\-]+)((\\*|\\^|\\$)?\\=([a-zA-Z0-9\\_\\-]+".

										"|'(?:(?:\\\\.|[^\\\\'])*)'|". # string with '
										"|\"(?:(?:\\\\.|[^\\\\\"])*)\"|". # string with "

										")?)?\\]".

									"/us", trim($part), $attr_matches);
							if ($at_ok && $attr_matches)
							{
								list(, $attr_name, $attr_equals, $attr_match_mode, $attr_match_value) = $attr_matches;
								if (($attr_match_value{0} === "\"") || ($attr_match_value{0} === "'"))
									$attr_match_value = substr($attr_match_value, 1, -1);
								$attr_match_value = strtolower($attr_match_value);
								if ($this->attrs[$attr_name] !== null)
								{
									if (!$attr_equals)
										$is_matching = true;
									else
									{
										$attr_value = strtolower($this->getAttribute($attr_name));
										if ($attr_match_mode === "")
											$is_matching = ($attr_value === $attr_match_value);
										else if ($attr_match_mode === "^")
											$is_matching = (substr($attr_value, 0, strlen($attr_match_value)) === $attr_match_value);
										else if ($attr_match_mode === "$")
											$is_matching = (substr($attr_value, -strlen($attr_match_value)) === $attr_match_value);
										else if ($attr_match_mode === "*")
											$is_matching = (strpos($attr_value, $attr_match_value) !== false);
									}
								}
							}
							break;
						}
						default:
						{
							// tag name
							$is_matching = (strtolower(trim($this->tag)) === strtolower($part));
							break;
						}
					}

					if ($is_matching)
					{
						if ($is_last)
						{
							if (!in_array($this, $findings, true))
							{
								// var_dump("found: ", $css_selector, $sel_key);
								$findings[] = $this;
							}
							break; // will break anway
						}
						// else allow it to continue
					}
					else
					{
						// not matching
						
						break;
						
						// else let it continue
					}
					// if all are true: allow it to continue, or if last add it to findings
					// if not true: if $must_match break, else let it continue

					$s_pos++;
				}	
			}
		}
		
		if (!empty($child_selectors))
			parent::query($child_selectors, $findings);
		
		return $findings;
	}
	
	public function setTag($tag)
	{
		if (!$this->children)
			return false;
		
		foreach ($this->children as $pos => $child)
		{
			if (is_array($child) && ($child[0] === T_QXML_TAG_NAME))
			{
				$this->children[$pos][1] = $tag;
				$this->tag = $tag;
				return true;
			}
		}
		return false;
	}
	
	/**
	 * We unwrap by setting all the elements to empty strings
	 */
	public function softUnwrap()
	{
		$pp_pos = $this->getPrependPos();
		for ($i = 0; $i < $pp_pos; $i++)
			$this->children[$i] = "";
		$app_pos = $this->getAppendPos();
		if ($app_pos === false)
		{
			qvar_dump($this->toString(), $this);
			throw new \Exception('We do not support short close tag for "qParent" or "qRoot"');
		}
		$len = count($this->children);
		// var_dump($app_pos);
		for ($i = $app_pos; $i < $len; $i++)
			$this->children[$i] = "";
	}
	
	public function compileQCtrlRenderMethod($class_name, $method_name)
	{
		$str = "<virtual";
		// copy attributes
		foreach ($this->attrs ?: [] as $k => $v)
		{
			if (($k === 'q-visibility') || ($k === 'q-static') || ($k === 'q-api'))
				continue;
			$str .= ' '.$k.($v ? '='.$v : '');
		}
		$str .= ">\n";
		$inner = $this->inner();
		foreach ($inner ?: [] as $child)
			$str .= is_array($child) ? $child[1] : (string)$child;
		$str .= "</virtual>";
		
		$parsed_tpl = static::ParseTemplate($str);
		
		$sh_meth = lcfirst(substr($method_name, strlen('render')));
		$virtual_path = qClassWithoutNs($class_name).($sh_meth ? ".".$sh_meth : "").".tpl";
		
		$gen_info = new QGeneratePatchInfo($virtual_path);
		
		// $gen_info->class_name; # not - used
		// $gen_info->is_patch; # not - used
		// $gen_info->path; # not - used
		// $gen_info->file_name; # not - used
		
		# $gen_info->__tpl_parent; #used
		$gen_info->type = 'tpl'; #used
		
		$parsed_tpl[0]->generate($gen_info);
		
		return "?>".$parsed_tpl[0]->toString(false, true)."<?php";
	}
}

