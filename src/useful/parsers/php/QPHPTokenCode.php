<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenCode
 *
 * @author Alex
 */
class QPHPTokenCode extends QPHPToken
{
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// we do a light parse when requested
		if ((!$expand_in_methods) && ($tok === "{"))
		{
			$brackets = 0;
			$php_tags = 0;
			$last_t_string_pos = false;
			$children_pos = 0;
			$skip_processing = false;
			
			while ($tok)
			{
				$tok_idf = is_array($tok) ? $tok[0] : $tok;
				switch ($tok_idf)
				{
					case "{":
					case T_CURLY_OPEN:
					case T_DOLLAR_OPEN_CURLY_BRACES:
					case T_STRING_VARNAME:
					{
						$brackets++;
						break;
					}
					case "}":
					{
						$brackets--;
						break;
					}
					case T_OPEN_TAG:
					case T_OPEN_TAG_WITH_ECHO:
					{
						$php_tags++;
						break;
					}
					case T_CLOSE_TAG:
					{
						$php_tags--;
						break;
					}
					case T_NS_SEPARATOR:
					{
						// join inside parent
						$full_class_idf = ($last_t_string_pos === false) ? "\\" : $this->children[$last_t_string_pos][1]."\\";
						
						while (($idf_tok = $tokens[++$pos]) && (is_array($idf_tok) && ($idf_ty = $idf_tok[0]) && 
									(($idf_ty === T_STRING) || ($idf_ty === T_NS_SEPARATOR) || ($idf_ty === T_WHITESPACE))))
						{
							if ($idf_ty !== T_WHITESPACE)
								$full_class_idf .= $idf_tok[1];
						}
						
						if ($last_t_string_pos === false)
							$this->children[$children_pos++] = [T_NS_SEPARATOR, $full_class_idf];
						else
							$this->children[$last_t_string_pos][1] = $full_class_idf;
						
						if ($this->children[$last_t_string_pos][1] === "\\")
							throw new Exception("Parse error");
						
						// fix position as we have looked ahead
						$tok = $tokens[$pos];
						$tok_idf = is_array($tok) ? $tok[0] : $tok;
						
						$skip_processing = true;
						
						$last_t_string_pos = false;
						break;
					}
					default:
						break;
				}
				
				if ($skip_processing)
				{
					$skip_processing = false;
					continue;
				}
				
				if (($tok_idf === T_STRING) || ($tok_idf === T_NS_SEPARATOR))
					$last_t_string_pos = $children_pos;
				else if (($last_t_string_pos !== false) && ($tok_idf !== T_WHITESPACE))
					$last_t_string_pos = false;
					
				$this->children[$children_pos++] = $tok;
				$pos++;
				
				if (($brackets === 0) && ($php_tags === 0))
					return;
				else if (($brackets < 0) || ($php_tags < -1))
					throw new Exception("Parse error");
				
				$tok = $tokens[$pos];
			}
			return;
		}
		
		$first_open_tag = false;
		if ($tok === "{")
		{
			$this->children[] = $tok;
			$pos++;
			$tok = $tokens[$pos];
			
			$depth = 1;
			$first_bra = true;
		}
		else if (is_array($tok) && (($tok[0] === T_OPEN_TAG) || ($tok[0] === T_OPEN_TAG_WITH_ECHO)))
		{
			$first_open_tag = true;
			$depth = 0;
			$first_bra = false;
		}
		else
		{
			$depth = 0;
			$first_bra = false;
		}
		
		$last_t_string_pos = false;
			
		while (($tok = $tokens[$pos]))
		{
			$type = is_array($tok) ? $tok[0] : null;
			
			if ($tok === "\"")
			{
				// we are in a string ... continue until it ends
				do
				{
					$this->children[] = $tok;
					$tok = $tokens[++$pos];
					if ($tok === "\"")
					{
						$this->children[] = $tok;
						$pos++;
						break;
					}
				}
				while ($tok);
			}
			// opening curly brackets
			else if ($type ? ($tok[1] === '{') : ($tok === "{"))
			{
				$prev = $tokens[$pos - 1];
				if (($prev === ';') || ($prev === ')') ||  ($prev === ':') || (is_array($prev) && ($prev[0] === T_WHITESPACE)) || ($prev[0] === T_OPEN_TAG) || ($prev[0] === T_OPEN_TAG_WITH_ECHO))
				{
					$child = new QPHPTokenCode($this);
					$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
					$child->setParent($this);
				}
				else if (($prev === '$') || ($prev === '"') || ($prev === ']') || ($prev === '}') || (is_array($prev) && 
							(($prev[0] === T_VARIABLE) || ($prev[0] === T_STRING) || ($prev[0] === T_OBJECT_OPERATOR) || ($prev[0] === T_ENCAPSED_AND_WHITESPACE))))
				{
					$depth++;
					$this->children[] = $tok;
					$pos++;
				}
				else
				{
					/*
					var_dump($prev, is_array($prev) ? token_name($prev[0]) : "str");
					// echo "<textarea>{$this->toString($tokens)}</textarea>";
					array_splice($tokens, 0, $pos - 40);
					array_splice($tokens, 80, 10000);
					// var_dump($tok, $pos);
					echo "<textarea>".$this->toString($tokens)."</textarea>";
					 * 
					 */
					//var_dump($tokens[$pos-1], $tok, $pos);
					echo "<textarea>".$this->toString($tokens)."</textarea>";
					var_dump($prev);
					throw new Exception("The character was not expected before {");
				}
			}
			// closing curly brackets
			else if ($type ? ($tok[1] === '}') : ($tok === "}"))
			{
				$depth--;
				if ($depth >= 0)
				{
					$this->children[] = $tok;
					$pos++;
				}
				if ($first_bra ? ($depth <= 0) : ($depth < 0))
				{
					break;
				}
			}
			else if ($this->isPrecededBy($tokens, $pos, [";", T_OPEN_TAG, "}"]) && ($type === T_CLASS) || ($type === T_INTERFACE) || ($type === T_TRAIT) || 
					((($type === T_ABSTRACT) || ($type === T_FINAL)) && 
						$this->isFollowedBy($tokens, $pos, [T_CLASS, T_INTERFACE, T_TRAIT])))
			{
				$child = new QPHPTokenClass($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
			}
			else if ($type === T_FUNCTION)
			{
				$child = new QPHPTokenFunction($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
			}
			else if ($type === T_DOC_COMMENT)
			{
				$child = new QPHPTokenDocComment($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this);
			}
			else if ($first_open_tag && $expand_output && ($type === T_CLOSE_TAG))
			{
				// we are done
				$this->children[] = $tok;
				$pos++;
				break;
			}
			else if ($expand_output && ($type === T_CLOSE_TAG))
			{
				$this->children[] = $tok;
				$pos++;
				$tok = $tokens[$pos];
				
				// self::ParseOutput($this, $tokens, $tok, $pos, $expand_output, false, $expand_in_methods, $expand_arrays);
				self::ParseOutput($this, $tokens, $tok, $pos, $expand_output, true, $expand_in_methods, $expand_arrays);
			}
			else if ($type === T_INLINE_HTML)
			{
				$this->children[] = $tok;
				$pos++;
			}
			// else if ($this->isTokenHtml($type))
			else if (($type === T_INLINE_HTML) || (($type >= T_QXML_MIN) && ($type <= T_QXML_MAX)))
			{
				var_dump($tok,$pos);
				throw new Exception("This should not be");
				// self::ParseOutput($this, $tokens, $tok, $pos, $expand_output);
			}
			else if ($expand_arrays && (($tok === "[") || ($type === T_ARRAY)))
			{
				$child = new QPHPTokenArray($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
			}
			else
			{
				if ($type === T_NS_SEPARATOR)
				{
					$full_class_idf = ($last_t_string_pos === false) ? "\\" : $this->children[$last_t_string_pos][1]."\\";
					
					while (($idf_tok = $tokens[++$pos]) && (is_array($idf_tok) && ($idf_ty = $idf_tok[0]) && 
								(($idf_ty === T_STRING) || ($idf_ty === T_NS_SEPARATOR) || ($idf_ty === T_WHITESPACE))))
					{
						if ($idf_ty !== T_WHITESPACE)
							$full_class_idf .= $idf_tok[1];
					}
					
					if ($last_t_string_pos === false)
						$this->children[] = [T_NS_SEPARATOR, $full_class_idf];
					else
						$this->children[$last_t_string_pos][1] = $full_class_idf;
					
					$tok = $tokens[$pos];
					$type = is_array($tok) ? $tok[0] : null;
					
					if ($this->children[$last_t_string_pos][1] === "\\")
					{
						//var_dump($pos, $this->children[$last_t_string_pos]);
						var_dump($tokens[$pos-2],$tokens[$pos-1],$tokens[$pos],$tokens[$pos+1],$tokens[$pos+2],$tokens[$pos+3]);
						throw new Exception("Parse error");
					}
					
					$last_t_string_pos = false;
				}
				else
				{
					// the token is part of this 
					$this->children[] = $tok;
					$pos++;

					if ($type === T_STRING)
					{
						end($this->children);
						$last_t_string_pos = key($this->children);
					}
					else if (($last_t_string_pos !== false) && ($type !== T_WHITESPACE))
						$last_t_string_pos = false;
				}
			}
			
			$tok = $tokens[$pos];
		}
	}

	// a token will start with <?[php|=] or with { , and it will end with a `? >` or }
	// in case of a <?[php] , we will also need the whitespace
	
	public function getAppendPos()
	{
		// first } or `? >` from the end
		if (!$this->children)
			return false;
		$end = count($this->children) - 1;
		if ($end < 0)
			return false;
		
		$tok = $this->children[$end];
		
		if (($tok === "}") || (is_array($tok) && ($tok[1] === "}")))
			return $end;
		else if (is_array($tok) && ($tok[0] === T_CLOSE_TAG))
			// we also need to skip the whitespace
			return ($end < 0) ? false : $end;
		else 
			// there is a problem, not parsed ok
			return false;
	}

	public function getPrependPos()
	{
		// first } or `? >` from the end
		if (!$this->children)
			return false;
		$pos = 0;
		
		$tok = $this->children[$pos];
		
		if (($tok === "{") || (is_array($tok) && ($tok[1] === "{")))
			// right after the opening {
			return $pos + 1;
		else if (is_array($tok) && (($tok[0] === T_OPEN_TAG) || ($tok[0] === T_OPEN_TAG_WITH_ECHO)))
		{
			// also skip the whitespace
			return $pos + 1;
		}
		else 
			// there is a problem, not parsed ok
			return false;
	}
	
	public function setCaseCode($value, $new_code)
	{
		// condition: we should be in a switch code block
		// find the case with that value
		$case_code = $this->findCaseWithValue($value);
		
		if (!$case_code)
		{
			// we need to create it
			$this->prepend("\n\t\t\tcase \"".addslashes($value)."\":\n\t\t\t{\n".$new_code."\n\t\t\t}\n\t\t");
			// $case_code = $this->findCaseWithValue($value);
		}
		else
		{
			$case_code->replace("\n\t\t\t{\n".$new_code."\n\t\t\t}\n");
		}
	}
	
	public function findCaseWithValue($value, $find_default = false)
	{
		$pos = 0;
		$next_code = false;
		
		$token_type = $find_default ? T_DEFAULT : T_CASE;
		
		foreach ($this->children as $child)
		{
			if ((!$next_code) && is_array($child) && ($child[0] === $token_type))
			{
				$case_val = $this->children[$pos + 2][1];
				if ($find_default || (stripslashes(substr($case_val, 1, -1)) === $value))
					$next_code = true;
			}
			else if ($next_code && ($child instanceof QPHPTokenCode))
			{
				return $child;
			}
			$pos++;
		}
	}
	
	public function getWithoutPhpTags()
	{
		$ret = $this->children ?: array();
		$first = reset($ret);
		if (is_array($first) && (($first[0] === T_OPEN_TAG) || ($first[0] === T_OPEN_TAG_WITH_ECHO)))
			$ret[key($ret)] = "";
		$last = end($ret);
		if (is_array($last) && ($last[0] === T_CLOSE_TAG))
			$ret[key($ret)] = "";
		return $ret;
	}
	
	public function getMatchingKey($pos, $types_index, $gen_type)
	{
		// find doc comm if possible
		$doc_comm = $this->children(".QPHPTokenDocComment");
		if ($doc_comm)
		{
			$parsed = QCodeStorage::parseDocComment($this->toString($doc_comm), true);
			if ($parsed)
			{
				$merge_mode = $parsed["merge.mode"];
				$tag = $parsed["merge.tag"] ?: parent::getMatchingKey($pos, $types_index, $gen_type);
				
				return $merge_mode ? array($tag, $merge_mode) : $tag;
			}
		}
		
		return parent::getMatchingKey($pos, $types_index, $gen_type);
		/*
		$c = get_class($this);
		$ty_index = $types_index[$c] ? $types_index[$c]++ : ($types_index[$c] = 0);
		return "[{$ty_index}]{$c}";
		*/
	}
	
	/**
	 * Looks ahead of this code block to see if there are any associated code labels
	 * Example:
	 * 
	 * CodeTag1:
	 * CodeTag2:
	 * {
	 *		// this code block
	 * }
	 * 
	 * In this case the function would return array("CodeTag1", "CodeTag2");
	 * 
	 * In case the parent is null or missing data it will thrown an exception.
	 * Note: You can not call this method until this element is attached on the parent.
	 * 
	 * @param $this_pos This is the position of this element in it's parent. If not provided it will search for it.
	 * 
	 * @return array|null
	 * @throws Exception
	 */
	public function getPrecedingCodeLabels($this_pos = false)
	{
		if ((!$this->parent) || (!$this->parent->children))
			throw new Exception("Invalid parent");
		
		$tokens = $this->parent->children;
		
		if ($this_pos === false)
		{
			foreach ($tokens as $i => $toks)
			{
				if ($toks === $this)
				{
					// stop
					$this_pos = $i;
					break;
				}
			}
		}
		
		if ($this_pos === false)
			return null;
		
		// now look for code labels: T_STRING followed by ":", with whitespace or T_COMMENT OR T_DOC_COMMENT
		$has_ddots = false;
		$labels = array();
		
		for ($i = ($this_pos - 1); $i >= 0; $i--)
		{
			$tok = $tokens[$i];
			$type = is_array($tok) ? $tok[0] : (($tok instanceof QPHPToken) ? null : $tok);
			$break_loop = false;
			
			switch ($type)
			{
				case ":":
				{
					$has_ddots = true;
					break;
				}
				case T_WHITESPACE:
				case T_COMMENT:
				case T_DOC_COMMENT:
				{
					// ignore these
					break;
				}
				case T_STRING:
				{
					if (!$has_ddots)
					{
						$break_loop = true;
						break;
					}
					$labels[] = $tok[1];
					$has_ddots = true;
					break;
				}
				default:
				{
					$break_loop = true;
					break;
				}
			}
			
			if ($break_loop)
				break;
		}
		
		return $labels ?: null;
	}
	
	public function getNamespace()
	{
		$pos = $this->findNamespacePos();
		if (($pos === false) || ($pos === null))
			return null;
		
		while (($tok = $this->children[++$pos]))
		{
			if (is_array($tok) && (($tok[0] === T_STRING) || ($tok[0] === T_NS_SEPARATOR)))
				return trim($tok[1]);
		}
		return null;
	}
	
	public function findNamespacePos()
	{
		if (!$this->children)
			return false;
		
		$tok = reset($this->children);
		while ($tok)
		{
			if ($tok === "{")
				return false;
			
			$type = is_array($tok) ? $tok[0] : null;
			switch ($type)
			{
				case T_CLASS:
				case T_FUNCTION:
				case T_IF:
				case T_FOREACH:
				case T_STRING:
				case T_VAR:
				case T_STRING_VARNAME:
				{
					return false;
				}
				case T_NAMESPACE:
				{
					// determine the namespace
					return key($this->children);
				}
				default:
				{
					break;
				}
			}
			$tok = next($this->children);
		}
		return false;
	}
	
	public function setNamespace($namespace)
	{
		// if ($namespace === null) // we need to unset the namespace
		$pos = $this->findNamespacePos();
		
		if (($pos === false) || ($pos === null))
		{
			if ($namespace)
			{
				$was_set = false;
				// we will need to add it
				if (!$this->children)
					throw new Exception("The element is empty, unable to set namespace");
				$tok = reset($this->children);
				$t_pos = 0;
				while ($tok)
				{
					$type = is_array($tok) ? $tok[0] : null;
					if (($type === T_OPEN_TAG) || ($type === T_OPEN_TAG_WITH_ECHO))
					{
						// set it
						array_splice($this->children, $t_pos + 1, 0, [[T_WHITESPACE, "\n"], [T_NAMESPACE, "namespace"], [T_WHITESPACE, " "], [T_STRING, $namespace], ";", [T_WHITESPACE, "\n"]]);
						$was_set = true;
						break;
					}

					$tok = next($this->children);
					$t_pos++;
				}

				if (!$was_set)
				{
					$this->children[] = [T_OPEN_TAG, "<?php"];
					$this->children[] = [T_WHITESPACE, "\n\t"];
					$this->children[] = [T_NAMESPACE, "namespace"];
					$this->children[] = [T_WHITESPACE, " "];
					$this->children[] = [T_STRING, $namespace];
					$this->children[] = ";";
					$this->children[] = [T_WHITESPACE, "\n"];
				}
			}
		}
		else
		{
			// update the namespace
			// $pos = position of the namespace
			// var_dump($this->children[$pos], $this->children[$pos+1], $this->children[$pos+2], $this->children[$pos+3]);

			$was_set = false;
			// find the T_STRING
			$tok = $this->children[$pos];
			
			$t_pos = $pos;

			while ($tok)
			{
				if (($tok === "{") || ($tok === ";"))
				{
					if ($namespace)
					{
						echo "<textarea>{$this}</textarea>";
						throw new Exception("Bad tokens");
					}
					else
					{
						// unset the namespace
						array_splice($this->children, $pos, $t_pos - $pos + 1);
						$was_set = true;
						
						break;
					}
				}

				$type = is_array($tok) ? $tok[0] : null;
				if ($namespace && ($type === T_STRING))
				{
					$this->children[$t_pos][1] = $namespace;
					$was_set = true;
					
					break;
				}

				$tok = $this->children[++$t_pos];
			}

			if (!$was_set)
				throw new Exception("Invalid previous namespace name");
		}
	}
	
	public function removeAllUseNamespaceStatements()
	{
		return $this->getAllUseNamespaceStatements(true);
	}
	
	public function setAllUseNamespaceStatements($use_namespace_replacements)
	{
		return $this->getAllUseNamespaceStatements(true, $use_namespace_replacements);
	}
	
	public function getAllUseNamespaceStatements($remove = false, $use_namespace_replacements = null)
	{
		if (!$this->children)
			return null;
		
		$uses = [];
		$removes = [];
		$first_use_position = false;
		$last_use_position = false;
		$last_ns_declaration = false;
		$first_code_pos = false;
		
		$tok = reset($this->children);
		while ($tok)
		{
			$tok_type = is_array($tok) ? $tok[0] : null;
			if ($tok_type === T_OPEN_TAG)
				$first_code_pos = key($this->children);
			else if (($tok_type === T_USE) || ($tok_type === T_NAMESPACE))
			{
				if ($tok_type === T_USE)
				{
					// pull all data up to ;
					$use = [$tok];
					$start = key($this->children);
					if ($first_use_position === false)
						$first_use_position = $start;
				}
				while ($tok = next($this->children))
				{
					if ($tok_type === T_USE)
						$use[] = $tok;
					if (($tok === ";") || (is_array($tok) && ($tok[1] === ";")))
						break;
				}
				$end = key($this->children);
				if ($tok_type === T_USE)
				{
					$use_identifier = $this->toString($use);
					$last_use_position = $end;
					if ($remove)
						$removes[$use_identifier] = [$start, $end, $use];
					$uses[$use_identifier] = $use;
				}
				else // ($tok_type === T_NAMESPACE)
				{
					$last_ns_declaration = $end;
				}
			}
			
			$tok = next($this->children);
		}
		
		if ($remove && $removes)
		{
			foreach ($removes as $identifier => $pos)
			{
				// splice for your life
				list($start, $end, $use) = $pos;
				if ($use_namespace_replacements && ($use_repl = $use_namespace_replacements[$identifier]) && ($use_repl === $use))
					unset($use_namespace_replacements[$identifier]);
				else
				{
					for ($k = $start; $k <= $end; $k++)
					{
						$prev = $this->children[$k - 1];
						if ($prev && is_array($prev) && ($prev[0] === T_WHITESPACE))
							$this->children[$k - 1] = [T_WHITESPACE, rtrim($prev[1], "\n")];
						// it's easier & faster to overwrite with empty
						$this->children[$k] = "";
					}
				}
			}
		}
		
		if ($use_namespace_replacements)
		{
			// apply after $last_use_position
			// or, if none found, after the last namespace declaration
			$insert_at = ($last_use_position !== false) ? ($last_use_position + 1) : 
								(($last_ns_declaration !== false) ? ($last_ns_declaration + 1) : 
									(($first_code_pos !== false) ? ($first_code_pos + 1) : null));
			if ($insert_at === null)
				throw new Exception("Unable to find at least the T_OPEN_TAG");
			
			foreach (array_reverse($use_namespace_replacements) as $use_ns)
			{
				// place a newline
				array_splice($this->children, $insert_at, 0, [[T_WHITESPACE, "\n", 0]]);
				// now insert the USE
				array_splice($this->children, $insert_at + 1, 0, $use_ns);
			}
		}
		
		return $uses;
	}
	
}

