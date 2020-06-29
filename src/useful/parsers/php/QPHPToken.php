<?php

define("T_QXML_MIN",				999000); // <!-- ... -->
define("T_QXML_MAX",				999999); // <!-- ... -->

define("T_QXML_COMMENT",			999001); // <!-- ... -->
define("T_QXML_TAG_OPEN",			999002); // ex: <
define("T_QXML_TAG_NAME",			999022); // ex: div
define("T_QXML_TAG_SHORT_CLOSE",	999023); // ex: div
define("T_QXML_TAG_CLOSE",			999003); // 
define("T_QXML_TAG_END",			999004); // </div>
define("T_QXML_TEXT",				999005); // text inside XML element
define("T_QXML_ATTR_NAME",			999006);
define("T_QXML_ATTR_VALUE",			999007);
define("T_QXML_ATTR_SPACE",			999011);
define("T_QXML_ATTR_EQUAL",			999012);

define("T_QXML_DOCTYPE",			999090); // <!-- ... -->

/**
 * Description of QPHPBlock
 *
 * @author Alex
 */
abstract class QPHPToken
{
	public static $Openers = array(T_OPEN_TAG => "QPHPTokenCode");
	public static $Closers = array(T_CLOSE_TAG => "QPHPTokenCode");
	
	public static $TokensCache = [];
	
	public static $_ClassNameShortcuts = [
		'dd' => 'Omi\View\DropDown',
		'DropDown' => 'Omi\View\DropDown',
	];

	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 'pullDocComment' => null];
	
	// T_INLINE_HTML

	/**
	 * Tag identifier
	 *
	 * @var string
	 */
	public $tag;
	/**
	 * The attributes
	 *
	 * @var string[]
	 */
	public $attrs;
	/**
	 * The attributes
	 *
	 * @var string
	 */
	public $id;
	/**
	 * The parent token
	 *
	 * @var QPHPToken
	 */
	public $parent;
	/**
	 *
	 * @var QPHPTokenFile 
	 */
	public $root;
	/**
	 * Child elements
	 *
	 * @var (QPHPToken|string|array)[]
	 */
	public $children;
	/**
	 *
	 * @var QPHPTokenDocComment
	 */
	public $docComment;
	/**
	 *
	 * @var boolean
	 */
	public $pullDocComment;
	
	public function __construct(QPHPToken $parent = null)
	{
		// only reference parent
		// the object is not ready to be linked until it is parsed
		$this->parent = $parent;
		$this->children = array();
	}
	
	/*
	public function __destruct()
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
					$child->__destruct();
			}
			unset($this->children);
		}
		
		unset($this->parent);
		unset($this->root);
	}
	*/
	
	public function setParent(QPHPToken $parent = null, $pullDocComment = null, $add_as_child = true)
	{
		$pullDocComment = ($pullDocComment !== null) ? $pullDocComment : (($this->pullDocComment !== null) ? $this->pullDocComment : false);
		
		$this->parent = $parent;
		if ($pullDocComment && $parent)
			$this->pullDocComment($parent);
		
		if ($add_as_child && $parent)
		{
			if ($this->parent->children === null)
				$this->parent->children = array();

			$key = $this->getKeyAsChild();
			if ($key)
				$this->parent->children[$key] = $this;
			else
				$this->parent->children[] = $this;
		}
	}
	
	protected function pullDocComment(QPHPToken $parent = null, $debug = false)
	{
		$parent = $parent ?: $this->parent;
		if (!$parent)
			return;
		
		if ($this instanceof QPHPTokenDocComment)
		{
			var_dump($this->pullDocComment);
			throw new Exception("not ok");
		}
		// echo "I will pull doc comment for: ".get_class($this)." :: ".$this->tag."<br/>\n";
		
		$last = end($parent->children);
		$count = 0;
		
		while ($last)
		{
			$is_arr = is_array($last);
			$is_doc_comm = ($is_arr && ($last[0] === T_DOC_COMMENT)) || ($last instanceof QPHPTokenDocComment);
			$is_whitesp = $is_arr && ($last[0] === T_WHITESPACE);
			
			if ($is_doc_comm)
			{
				$list[] = $splice_pos = key($parent->children);
				
				$splice_count = 0;
				foreach ($list as $key)
				{
					// $key = $list[$i];
					$token = $parent->children[$key];
					
					// $this->children->prepend($token);
					array_unshift($this->children, $token);
					
					$this->docComment = $token;
					
					if ($token instanceof QPHPToken)
						$token->parent = $this; //, null, false);
					$splice_count++;
				}
				
				// $parent->children->splice($splice_pos, $splice_count);
				array_splice($parent->children, $splice_pos, $splice_count);
				break;
			}
			else if ($is_whitesp)
			{
				// contiune
				$list[] = key($parent->children);
				$last = prev($parent->children);
				$count++;
			}
			else
			{
				///echo "No doc comment<br/>";
				// there is no DOC Comment associted to this element
				return;
			}
		}
	}
	
	protected function isTokenHtml($token_type)
	{
		// var_dump($token_type, T_INLINE_HTML, T_QXML_MIN, T_QXML_MAX);
		return (($token_type === T_INLINE_HTML) || (($token_type >= T_QXML_MIN) && ($token_type <= T_QXML_MAX)));
	}
	
	public function getKeyAsChild()
	{
		return null;
	}

	public function __toString()
	{
		return $this->toString();
	}
	
	public function toString($formated = false, $final = false, $data = null)
	{
		if (!is_bool($formated))
		{
			$arg = $formated;
			if (qis_array($arg))
			{
				if (is_array($arg) && is_int($arg[0]) && is_string($arg[1]))
					return $arg[1];
				$str = "";
				foreach ($arg as $child)
				{
					if (is_string($child))
						$str .= $child;
					else if (is_array($child))
						$str .= $child[1];
					else if ($child instanceof QPHPToken)
						$str .= $child->toString(false, $final, $data);
				}
				return $str;
			}
			else if (is_string($arg))
				return $arg;
			else if ($arg instanceof QPHPToken)
				return $arg->toString(false, $final, $data);
		}
		else
		{
			$str = "";
			if ($this->children)
			{
				foreach ($this->children as $child)
				{
					if (is_string($child))
						$str .= $child;
					else if (is_array($child))
						$str .= $child[1];
					else // if ($child instanceof QPHPToken)
						$str .= $child->toString($formated, $final, $data);
					/*
					else
						throw new Exception("Unexpected token type");*/
				}
			}
			return $str;
		}
	}

	public abstract function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false);
	
	public static $ParserCount = array();

	/**
	 * 
	 * @param string $filename
	 * @param type $expand_in_methods
	 * @param type $expand_arrays
	 * @param type $cached_tokens
	 * @param type $cached_tokens_needs_expand
	 * 
	 * @return \QPHPTokenFile
	 */
	public static function ParsePHPFile($filename, $expand_in_methods = true, $expand_arrays = false, $cached_tokens = null, $cached_tokens_needs_expand = false)
	{
		if (!file_exists($filename))
			return null;
		
		if (isset(self::$ParserCount[$filename]))
		{
			/*list($count_exp, $count_wo_exp) = explode(",", self::$ParserCount[$filename]);
			if ($expand_in_methods)
				$count_exp++;
			else
				$count_wo_exp++;
			self::$ParserCount[$filename] = $count_exp.",".$count_wo_exp;*/
			self::$ParserCount[$filename]++;
		}
		else
		{
			// self::$ParserCount[$filename] = $expand_in_methods ? "1,0" : "0,1";
			self::$ParserCount[$filename] = 1;
		}
		
		// echo "Parse:{$filename}<br/>";
		
		$ret = new QPHPTokenFile($filename);
		$ret->beginParse($expand_in_methods, $cached_tokens, $expand_arrays, $cached_tokens_needs_expand);

		return $ret;
	}

	public static function ParsePHPString($string, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		$ret = new QPHPTokenCode();
		// $ret->beginParse();
		$tokens = $ret->getAllTokens($string, $expand_output);
		$pos = 0;
		$ret->parse($tokens, $tokens[0], $pos, $expand_output, $expand_in_methods, $expand_arrays);
		return $ret;
	}
	
	public static function ParseTokensFromString($string, $expand_output = false)
	{
		$ret = new QPHPTokenCode();
		// $ret->beginParse();
		$tokens = $ret->getAllTokens($string, $expand_output);
		return $tokens;
	}
	
	public static function ParseHtmlString($string)
	{
		$expand_output = true;
		$ret = new QPHPTokenXmlElement();
		// $ret->beginParse();
		$tokens = $ret->getAllTokens($string, $expand_output);
		$pos = 0;
		$ret->parse($tokens, $tokens[0], $pos, $expand_output);
		return $ret;
	}
	
	public function replaceWith($string, $wrap_in_php = false, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		if ($string instanceof QPHPToken)
		{
			$code = $string;
		}
		else if (is_string($string))
		{
			$tokens = $wrap_in_php ? qtoken_get_all("<?php ".$string." ?>") : qtoken_get_all($string);
			$code = new QPHPTokenCode($this->parent);
			$pos = 0;
			$code->parse($tokens, $tokens[0], $pos, $expand_output, $expand_in_methods, $expand_arrays);
		}
		else
			throw new Exception("Not accepted input data");
		
		$key = null;
		$parent = $this->parent;
		if ($parent->children)
		{
			// var_dump(get_class($parent->children));
			foreach ($parent->children as $k => $child)
			{
				if ($child === $this)
				{
					$key = $k;
					$parent->children[$k] = $code;
					break;
				}
			}
		}
		// var_dump("Replaced at position: ".$key);
		// var_dump($code."", "has replaced", $this."", "on parent", $parent."");
		
		if ($key === null)
			throw new Exception("Error, missing key");
		$code->setParent($parent, false, false);
		$this->parent = null;
		$this->setParent(null, false, false);
		
		return $code;
	}
	
	public function fixBrokenParents()
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
				{
					$child->parent = $this;
					$child->fixBrokenParents();
				}
			}
		}
	}
	
	public function emptyNode($full = false)
	{
		if ($full === true)
		{
			foreach ($this->children as $child)
				if ($child instanceof QPHPToken)
					$child->setParent(null);
			unset($this->children);
			$this->children = array();
		}
		else
		{
			$pp_pos = $this->getPrependPos();
			$ap_pos = $this->getAppendPos();
			
			$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;
			
			for ($i = $pp_pos; $i < $ap_pos; $i++)
			{
				if ($inner_element->children[$i] instanceof QPHPToken)
					$inner_element->children[$i]->setParent(null);
			}
			// $inner_element->children->splice($pp_pos, ($ap_pos - $pp_pos));
			array_splice($inner_element->children, $pp_pos, ($ap_pos - $pp_pos));
		}
		
		return $this;
	}
	
	public function swap($element)
	{
		$elem = $this->normalizeInputElements($element);
		if ($elem && $elem[0] && ($elem[0] instanceof QPHPToken))
		{
			$this_pos = $this->getInParentPos();
			$this_parent = $this->parent;
			$elem_pos = $elem->getInParentPos();
			$elem_parent = $elem->parent;

			$this->remove();
			$elem->remove();
			
			// $this_parent->children->splice($this_pos, 0, $elem);
			array_splice($this_parent->children, $this_pos, 0, $elem);
			// $elem_parent->children->splice($elem_pos, 0, $this);
			array_splice($elem_parent->children, $elem_pos, 0, $this);
		}
	}
	
	public function wrapPHPCode($start, $end, $expand_output = false)
	{
		return $this->wrap($start, $end, true, $expand_output);
	}
	
	public function wrap($start, $end, $wrap_in_php = false, $expand_output = false)
	{
		$tokens = $wrap_in_php ? qtoken_get_all("<?php ".$start." ?>") : qtoken_get_all($start);
		//array_shift($tokens);
		
		//array_pop($tokens);

		// $tokens[] = $this;
		// $tokens = array_merge($tokens, qtoken_get_all((string)$this));
		
		$end_tok = $wrap_in_php ? qtoken_get_all("<?php ".$end." ?>") : qtoken_get_all($end);
		
		//array_shift($end_tok);
		//array_pop($end_tok);
		// $tokens = array_merge($tokens, $end_tok);
		$key = null;
		if ($this->parent->children)
		{
			foreach ($this->parent->children as $k => $child)
			{
				if ($child === $this)
				{
					$key = $k;
					// $this->parent->children[$k] = $code;
					break;
				}
			}
		}
		if ($key === null)
			throw new Exception("Error, missing key");
		
		// $this->parent->children->splice($k, 0, $tokens);
		array_splice($this->parent->children, $k, 0, $tokens);
		// $this->parent->children->splice($k + count($tokens) + 1, 0, $end_tok);
		array_splice($this->parent->children, $k + count($tokens) + 1, 0, $end_tok);
		
		return $this;
		/*
		// var_dump($tokens);
		
		// var_dump($start, $end);
		
		$code = new QPHPTokenCode($this->parent);
		$pos = 0;
		$code->parse($tokens, $tokens[0], $pos, $expand_output);
		
		//echo "<textarea>".$code."</textarea>";
		
		$key = null;
		if ($this->parent->children)
		{
			foreach ($this->parent->children as $k => $child)
			{
				if ($child === $this)
				{
					$key = $k;
					$this->parent->children[$k] = $code;
					break;
				}
			}
		}
		if ($key === null)
			throw new Exception("Error, missing key");
		$code->setParent($this->parent, false, false);
		
		$this->parent = $code;
		$this->setParent($code, false, false);
		
		return $this;
		 * 
		 */
	}
	
	public function generate(QGeneratePatchInfo $gen_info, $bind_path = null, $rel_parent = null, $rel_parent_depth = 0)
	{
		if ($this->children)
		{			
			foreach ($this->children as $k => $child)
			{
				if ($child instanceof QPHPToken)
					$child->generate($gen_info, $bind_path, $rel_parent, $rel_parent_depth);
				else if (is_array($child))
				{
					$tok_ty = $child[0];
					switch ($tok_ty)
					{
						case T_DIR:
						{
							$this->children[$k] = "\"".qaddslashes(dirname($gen_info->path))."\"";
							break;
						}
						case T_FILE:
						{
							$this->children[$k] = "\"".qaddslashes($gen_info->path)."\"";
							break;
						}
						default:
							break;
					}
				}
			}
		}
	}

	public function isFollowedBy($tokens, $pos, $type, &$found_pos = null)
	{
		$p = $pos + 1;
		$next = $tokens[$p];
		
		// skip whitespaces & comments
		while ($next && is_array($next) && (($next[0] === T_WHITESPACE) || ($next[0] === T_COMMENT) || ($next[0] === T_DOC_COMMENT)))
			$next = $tokens[++$p];
		
		$r = (($next === null) ? null : (is_string($next) ? $next : $next[0]));
		$found_pos = $p;
		return is_array($type) ? in_array($r, $type) : ($type === $r);
	}
	
	public function isPrecededBy($tokens, $pos, $type, &$found_pos = null)
	{
		$p = $pos - 1;
		$prev = $tokens[$p];
		
		// skip whitespaces & comments
		while ($prev && is_array($prev) && (($prev[0] === T_WHITESPACE) || ($prev[0] === T_COMMENT) || ($prev[0] === T_DOC_COMMENT)))
			$prev = $tokens[--$p];
		
		$r = (($prev === null) ? null : (is_string($prev) ? $prev : $prev[0]));
		$found_pos = $p;
		return is_array($type) ? in_array($r, $type) : ($type === $r);
	}
	
	/**
	 * Finds the first match by expression
	 * 
	 * @param string $expression
	 * @return QPHPToken
	 */
	public function findFirst($expression)
	{
		// only class now
		$matches = $this->find($expression, true);
		return empty($matches) ? null : $matches[0];
	}
	
	/**
	 * Finds the first QPHPTokenClass instance
	 * 
	 * @return \QPHPTokenClass
	 */
	public function findFirstPHPTokenClass()
	{
		if ($this->children)
		{
			$code = null;
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPTokenClass)
					return $child;
				else if (($child instanceof QPHPTokenCode) && ($code === null))
					$code = $child;
			}
			if ($code)
				return $code->findFirstPHPTokenClass();
		}
		return null;
	}
	
	/**
	 * Finds the first QPHPTokenClass instance
	 * 
	 * @return \QPHPTokenXmlElement
	 */
	public function findFirstXMLElement()
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPTokenXmlElement)
					return $child;
				else if ($child instanceof QPHPToken)
				{
					$found_el = $child->findFirstXMLElement();
					if ($found_el)
						return $found_el;
				}
			}
		}
		return null;
	}
	
	/**
	 * Finds the second QPHPTokenClass in the current context
	 * 
	 * @return \QPHPTokenClass
	 */
	public function findSecondPHPTokenClass()
	{
		if ($this->children)
		{
			$first = null;
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPTokenClass)
				{
					if ($first)
						return $child;
					else
						$first = $child;
				}
			}
		}
		return null;
	}
	
	/**
	 * Finds the first match by expression
	 * 
	 * @param string $expression
	 * @return QPHPToken
	 */
	public function find($expression, $only_first = false, &$list = null, $recurse = true)
	{
		if (!$expression)
			return null;

		if (!$list)
			$list = array();
		
		// only class now
		if ($expression{0} === ".")
		{
			$class = substr($expression, 1);
			if ($class && $this->hasClass($class))
			{
				if ($only_first)
					return array($this);
				else
					$list[] = $this;
			}
			
		}
		// tags by default
		else
		{
			if ($this->tag === $expression)
			{
				if ($only_first)
					return array($this);
				else
					$list[] = $this;
			}
		}
		
		if ($recurse)
		{
			foreach ($this->children as $c)
			{
				if ($c instanceof QPHPToken)
				{
					$ret = $c->find($expression, $only_first, $list);
					if ($ret && $only_first)
						return $ret;
				}
			}
		}

		return $list;
	}
	
	public function hasClass($class)
	{
		return (get_class($this) === $class);
	}
	
	public function children($expression = null)
	{
		if ($expression)
		{
			$list = array();
			if ($this->children)
			{
				foreach ($this->children as $child)
					if ($child instanceof QPHPToken)
						$child->find($expression, false, $list, false);
			}
			return $list;
		}
		else
			return $this->children;
	}
	
	public function closest($expression = null)
	{
		if ($expression)
		{
			$result = null;
			$element = $this->parent;
			while ($element)
			{
				$element->find($expression, false, $result, false);
				if (!empty($result))
					return $result[0];
				
				$element = $element->parent;
			}
			return null;
		}
		else
			return $this->parent;
	}
	
	public function parseDocComment($str)
	{
		$parts = preg_split("/(\\/\\*\\*\\s+|\\s+\\*\\/|\\n\\s*\\*\\s*\\@)/usim", $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		$cp = count($parts);
		if ($cp === 0)
			return array($str);
		
		$index = array();
		// empty
		// $index[] = $parts[0];
		// start /**
		$index[] = $parts[1];
		// description or empty
		$index[] = $parts[2];
		
		$i = 3;
		for ($i = 3; $i < ($cp - 2); $i++)
		{
			$p_i = $parts[$i];
			if (($i % 2) === 1)
				$index[] = $p_i;
			else
			{
				$sp1 = preg_split("/(\\s+)/usim", $parts[$i], 2, PREG_SPLIT_DELIM_CAPTURE);
				$c = count($sp1);
				if ($c === 0)
					$index[] = $parts[$i];
				else 
				{
					$key = $sp1[0];
					// @param 	type [$varname] description
					if ($key === "param")
					{
						$i_sp1 = preg_split("/(\\s+)/usim", $sp1[2], 3);
						if (!empty($i_sp1[1]))
							$key = $key."-".$i_sp1[1];
					}
					$index[$key] = $parts[$i];
				}
			}
		}
		
		// end */
		$index[] = $parts[$i++];
		// last empty chunk
		// $index[] = $parts[$i++];
		
		return $index;
	}
	
	public function mergeDocComment($docComment)
	{
		$str_1 = is_string($this->docComment) ? $this->docComment : ($this->docComment instanceof QPHPTokenDocComment) ? $this->docComment->toString() : $this->docComment[1];
		$str_2 = is_string($docComment) ? $docComment : ($docComment instanceof QPHPTokenDocComment) ? $docComment->toString() : $docComment[1];
		
		$index_1 = $this->parseDocComment($str_1);
		$index_2 = $this->parseDocComment($str_2);
		
		$prev = null;
		foreach ($index_2 as $key => $value)
		{
			if ($key === 1)
			{
				$index_1[$key] = $value;
			}
			if (!is_numeric($key))
			{
				if ($index_1[$key])
					$index_1[$key] = $value;
				else
				{
					// to be added 
					// $index_1[$key] = $value;
					array_splice($index_1, -1, 0, array($prev => $index_2[$prev], $key => $value));
				}
			}
			$prev = $key;
		}
		
		if (is_string($this->docComment))
		{
			$this->docComment = implode("", $index_1);
		}
		else if ($this->docComment instanceof QPHPTokenDocComment)
		{
			$this->docComment->children = array(array(T_DOC_COMMENT, implode("", $index_1), null));
		}
		else if (is_array($this->docComment))
		{
			$this->docComment[1] = implode("", $index_1);
		}
		else 
			throw new Exception("Not implemented");
	}
	
	public function normalizeTokensFromAttributes()
	{
		// only doc comments can be normalized here
		$pos = $this->findDocCommentPos();
		if ($pos === false)
		{
			if ($this->docComment)
			{
				$val = is_string($this->docComment) ? array(T_DOC_COMMENT, $this->docComment) : $this->docComment;
				// $this->children->prepend($val);
				array_unshift($this->children, $val);
			}
		}
		else 
		{
			if ($this->docComment)
			{
				$val = is_string($this->docComment) ? array(T_DOC_COMMENT, $this->docComment) : $this->docComment;
				$this->children[$pos] = $val;
			}
			else
			{
				// remove it
				// $this->children->splice($pos, 1);
				array_splice($this->children, $pos, 1);
			}
		}
	}
	
	public function findDocCommentPos()
	{
		if (!$this->children)
			return false;
		
		foreach ($this->children as $pos => $child)
		{
			if ((is_array($child) && ($child[0] === T_DOC_COMMENT)) || ($child instanceof QPHPTokenDocComment))
				return $pos;
		}
		
		return false;
	}
	
	public function getAppendPos()
	{
		return false;
	}

	public function getPrependPos()
	{
		return false;
	}
	
	public function append($elements, $xml_mode = false)
	{
		$elems = $this->normalizeInputElements($elements, $xml_mode, $this);
		
		if (!empty($elems))
		{
			$pos = $this->getAppendPos();
			$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;
			// $inner_element->children->splice($pos, 0, $elems);
			array_splice($inner_element->children, $pos, 0, $elems);
			foreach ($elems as $e)
			{
				if ($e instanceof QPHPToken)
					$e->parent = $inner_element;
			}
		}
		
		return $elems;
	}
	
	public function appendTo($target)
	{
		$target->append($this);
	}

	public function prepend($elements)
	{
		$elems = $this->normalizeInputElements($elements, $xml_mode, $this);
		
		if (!empty($elems))
		{
			$pos = $this->getPrependPos();
			$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;
			// $inner_element->children->splice($pos, 0, $elems);
			array_splice($inner_element->children, $pos, 0, $elems);
		}
	}
	
	/**
	 * Gets the inner text
	 * 
	 * @return string
	 */
	public function innerAsString()
	{
		return $this->toString($this->inner());
	}
	
	/**
	 * Gets the inner text
	 * 
	 * @return string
	 */
	public function innerText()
	{
		$elements = $this->inner();
		if (!$elements)
			return null;
		
		$str = "";
		foreach ($elements as $elem)
		{
			if ($elem instanceof \QPHPToken)
				$str .= $elem->innerText();
			else 
				$str .= is_array($elem) ? $elem[1] : $elem;
		}
		return $str;
	}
	
	public function inner($elements = null)
	{
		if (func_num_args() === 0)
		{
			$pp_pos = $this->getPrependPos();
			$ap_pos = $this->getAppendPos();
			
			$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;
			
			$ret = array();
			for ($i = $pp_pos; $i < $ap_pos; $i++)
				$ret[] = $inner_element->children[$i];
			return $ret;
		}
		else
		{
			$this->emptyNode();
			$this->append($elements);
		}
	}
	
	public function prependTo($target)
	{
		$target->prepend($this);
	}
	
	public function insertAfter($target)
	{
		$target->after($this);
	}
	
	public function after($elements)
	{
		$elems = $this->normalizeInputElements($elements, $xml_mode, $this);
		
		if (!empty($elems))
		{
			$pos = $this->getInParentPos();
			// $this->parent->children->splice($pos + 1, 0, $elems);
			array_splice($this->parent->children, $pos + 1, 0, $elems);
		}
	}
	
	public function replace($elements)
	{
		$elems = $this->normalizeInputElements($elements, $xml_mode, $this);
		
		if (!empty($elems))
		{
			$pos = $this->getInParentPos();
			// $this->parent->children->splice($pos, 1, $elems);
			array_splice($this->parent->children, $pos, 1, $elems);
		}
	}
	
	
	public function insertBefore($target)
	{
		$target->before($this);
	}
	
	public function before($elements)
	{
		$elems = $this->normalizeInputElements($elements, $xml_mode, $this);
		
		if (!empty($elems))
		{
			$pos = $this->getInParentPos();
			// $this->parent->children->splice($pos, 0, $elems);
			array_splice($this->parent->children, $pos, 0, $elems);
		}
	}
	
	public function remove()
	{
		$this->parent->removeChild($this);
	}
	
	public function removeChild(QPHPToken $element)
	{
		if ($this->children)
		{
			$pos = 0;
			foreach ($this->children as $child)
			{
				if ($child === $element)
				{
					// $this->children->splice($pos, 1);
					array_splice($this->children, $pos, 1);
					return $pos;
				}
				$pos++;
			}
		}
		return false;
	}
	
	public function getInParentPos()
	{
		if (!$this->parent)
			return false;
		if ($this->parent->children)
		{
			$pos = 0;
			foreach ($this->parent->children as $child)
			{
				if ($child === $this)
					return $pos;
				$pos++;
			}
		}
		return false;
	}
	
	protected function normalizeInputElements($input, $xml_mode = true, QPHPToken $expected_parent = null)
	{
		if ($input === null)
			return array();
		else if (is_string($input))
		{
			$len = strlen($input);
			if ($len === 1)
				return $input;
			// detect, parse, return
			$elem = $this->parseStringElement($input, $xml_mode, $expected_parent);
			return is_array($elem) ? $elem : array($elem);
		}
		else if ($input instanceof QPHPToken)
		{
			return array($input);
		}
		else if (is_array($input) || ($input instanceof QModelArray))
		{
			if ($input instanceof QModelArray)
			{
				$new_inp = array();
				foreach ($input as $k => $v)
					$new_inp[$k] = $v;
				$input = $new_inp;
			}
			
			if ($input[0] && is_int($input[0]) && $input[1] && is_string($input[1]))
				// we have a token array
				return array($input);
				
			$ret = array();
			foreach ($input as $elem)
			{
				if ($elem === null)
					continue;
				else if (is_string($elem))
					$ret[] = $this->parseStringElement($elem, $xml_mode, $expected_parent);
				else if ($elem instanceof QPHPToken)
					$ret[] = $elem;
				else if (is_array($elem) && $elem[0] && is_int($elem[0]) && $elem[1] && is_string($elem[1]))
					// array token
					$ret[] = $elem;
				else
					throw new Exception("Invalid input");
			}
			
			return $ret;
		}
		else
		{
			// var_dump(get_class($input));
			throw new Exception("Invalid input");
		}
	}
	
	protected function parseStringElement($input_str, $xml_mode = true, QPHPToken $expected_parent = null, $expand_in_methods = true, $expand_arrays = false)
	{
		if (($input_str === null) || (strlen($input_str) === 0))
			return null;
		else if (strlen($input_str) === 1)
			// we have a small token
			return $input_str;
		
		$remove_wrapping = false;
		
		$php_start = (preg_match("/^\\s*\\<\\?/us", $input_str) === 1);
		$xml_start = $php_start ? false : (preg_match("/^\\s*\\<[^\\?]/us", $input_str) === 1);
		
		if ($xml_mode || $xml_start)
		{
			return $this->getAllTokens($input_str, $xml_mode);
			
			/** TO DO
			$elem = new QPHPTokenOutput();
			$pos = 0;
			$elem->parse($tokens, $tokens[0], $pos);
			return $elem;
			 * 
			**/
		}
		else
		{
			if (!($expected_parent instanceof QPHPTokenXmlElement))
			{
				if ($php_start)
					$tokens = $this->getAllTokens($input_str, $xml_mode);
				else
				{
					$tokens = $this->getAllTokens("<?php {".$input_str."}", $xml_mode);
					array_splice($tokens, 0, 1);
				}
				
				$rc = new QPHPTokenCode();
				$pos = 0;
				$rc->parse($tokens, $tokens[0], $pos, $xml_mode, $expand_in_methods, $expand_arrays);
				
				// $ret = $rc->children->exchangeArray(array());
				$ret = $rc->children;
				$rc->children = array();
				
				if (!$php_start)
				{
					array_splice($ret, 0, 1);
					array_splice($ret, -1, 1);
				}
				
				return $ret;
			}
			else
			{
				// under a XML element
				if ($php_start)
					$tokens = $this->getAllTokens($input_str, $xml_mode);
				else
				{
					$tokens = $this->getAllTokens("<?php {".$input_str."}", $xml_mode);
					array_splice($tokens, 0, 1);
				}
				
				$rc = new QPHPTokenCode();
				$pos = 0;
				$rc->parse($tokens, $tokens[0], $pos, $xml_mode, $expand_in_methods, $expand_arrays);
				
				// $ret = $rc->children->exchangeArray(array());
				$ret = $rc->children;
				$rc->children = array();
				
				if (!$php_start)
				{
					array_splice($ret, 0, 1);
					array_splice($ret, -1, 1);
				}
				
				return $ret;
			}
			
			// for PHP code we need to make a wrap in order for the tokenizer to work ok
			if (!$php_start)
			{
				/** we need to wrap it in `<?php` and `?>` */
				$remove_wrapping = true;
				$input_str = "<?php ".$input_str." ?>";
			}
			
			$tokens = $this->getAllTokens($input_str, $xml_mode);
			
			// create code block
			
			if ($remove_wrapping)
			{
				// remove wrapping
			}
			
			
		}
		
		/*
		$p = 0;
		if ($rv === 1)
		{
			// xml element, tokenize
			
		}
		else
		{
			// may be text or PHP code
			if ($xml_mode)
			{
				// then it is text
			}
			else
			{
				// it is PHP 
			}
		}
		*/
	}
	
	protected function isValidInputTokens($tokens)
	{
		// make sure we have balanced brackets before we start to avoid most common issues
		// also balaced php openers
		// braces : "{", "}"
		// php tags: T_OPEN_TAG 	<?php, <? or <%
		// T_OPEN_TAG_WITH_ECHO 	<?= or <%=
		// T_CLOSE_TAG 	? > or % >
		
		$brackets = 0;
		$php_tags = 0;
		
		foreach ($tokens as $tok)
		{
			// $is_arr = is_array($tok);
			// $tok_ty = $is_arr ? $tok[0] : false;
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
			}
			/*
			if (($tok === "{") || ($tok_ty === T_CURLY_OPEN) || ($tok_ty === T_DOLLAR_OPEN_CURLY_BRACES) || ($tok_ty === T_STRING_VARNAME))
				$brackets++;
			else if ($tok === "}")
				$brackets--;
			else if (($tok_ty === T_OPEN_TAG) || ($tok_ty === T_OPEN_TAG_WITH_ECHO))
				$php_tags++;
			else if ($tok_ty === T_CLOSE_TAG)
				$php_tags--;
			 */
		}
		
		return ($brackets === 0) && (($php_tags === 0) || ($php_tags === 1));
	}
	
	public static $dbg_tmp_f = null;
	
	protected function getAllTokens($string, $expand_output = false, &$is_patch = null, $saved_tokens = null)
	{
		$is_patch = false;
		if (empty($string))
			return array();
		
		$is_Valid = false;
		// validate input
		if ($saved_tokens)
		{
			$tokens = $saved_tokens;
			$is_Valid = true;
		}
		else
			$tokens = qtoken_get_all($string, $is_Valid);
		
		if (!$tokens)
		{
			var_dump($string);
			throw new Exception("Invalid input code string");
		}
		
		// $is_Valid = $this->isValidInputTokens($tokens);
		if (!$is_Valid)
		{
			// var_dump($string, $tokens);
			throw new Exception("Invalid input code string.");
		}
		
		$new_toks = null;

		if ($expand_output)
		{
			$new_toks = array();
	
			$patch_tags = $patch_attrs = array("qAppend" => true, "qPrepend" => true, "qInsert" => true, "qReplace" => true, "qRemove" => true, "qWrap" => true, "qWrapInner" => true,
								"qUnwrap" => true, "qEmpty" => true, "qText" => true, "qInner" => true, "qMerge" => true, "qPatch" => true);
			
			$in_tag = false;
			$in_attr = false;
			$in_attr_quote = null;
			
			$in_doc_type = false;
			$in_doc_comm = false;
				
			foreach ($tokens as $tok)
			{
				if (is_array($tok) && ($tok[0] === T_INLINE_HTML))
				{
					$rest_to_parse = null;
					
					if ($in_attr)
					{
						// delicate issue
						// look for the ending quote
						$matches = null;
						$stat = preg_match("/\\".$in_attr_quote."/ius", $tok[1], $matches,  PREG_OFFSET_CAPTURE);
						if ($matches && $matches[0])
						{
							// split an continue
							$split_pos = $matches[0][1];
							// append part of the attribute, continue for the rest
							$new_toks[] = array(T_QXML_ATTR_VALUE, substr($tok[1], 0, $split_pos + 1), null);
							$rest_to_parse = substr($tok[1], $split_pos + 1);
							$in_attr = false;
							$in_attr_quote = null;
						}
						else
						{
							// only part of the attribute value, append and continue
							$new_toks[] = array(T_QXML_ATTR_VALUE, $tok[1], null);
							continue;
						}
					}
					
					// split $tok[1] into tokens
					$matches = null;
					
					// @todo ... in <script> elements ignore < > , what do we do when we have PHP inside <script>
					
					$stat = preg_match_all("/".

						($in_doc_comm ? "[^\\<\\>]+\-\->|" : "").
						"<\\!doctype\\s+|". # doc type
						"<\!\-\-.*?\-\->|". # XML comments full
						"<\!\-\-|". # XML comments : <!-- --> (start)
						"\-\->|". # XML comments : <!-- --> (end)
						"'(?:(?:\\\\.|[^\\\\'])*)'|". # string with '
						"\"(?:(?:\\\\.|[^\\\\\"])*)\"|". # string with "
						"[\\p{L&}\\$\\:\\@][\\w\\.\\-\\:]+|\\p{L&}+|". # attribute names (and not only)
						"\\<\\/|".
						"\\/\\>|".
						"[\\<\\>\\=]{1}|".
						"\\\"|\\\'|". # quote, double or single
						"(\\s+)|".				# whitespace
						"[^\\<\\>]+|". # text (here it is)
						"(.+)". # parse error

						"/ius", $rest_to_parse ? $rest_to_parse : $tok[1], $matches);
					
					// var_dump($rest_to_parse ? $rest_to_parse : $tok[1], $matches[0]);

					if (!$stat)
					{
						var_dump($stat, $matches, array_flip(get_defined_constants(true)['pcre'])[preg_last_error()]);
						throw new \Exception("Parse error.".($stat === false ? array_flip(get_defined_constants(true)['pcre'])[preg_last_error()] : ""));
					}

					if ($matches && (!empty($matches[0])))
					{
						// $m_comments = $matches[0];
						// $m_close_tags = $matches[2];
						// $m_tags = $matches[3];
						// $m_txt = $matches[4];
						$m_error = $matches[2];
						$m_spaces = $matches[1];

						// $count = count($matches[0]);

						// var_dump($matches);

						$p = 0;
						$text_tok = null;

						foreach ($matches[0] as $match)
						{
							if (!empty($m_error[$p]))
							{
								throw new Exception("Parse error");
							}
							else if ($in_doc_comm)
							{
								end($new_toks);
								$nt_key = key($new_toks);
								$new_toks[$nt_key][1] .= $match;
								if (substr($match, -3, 3) === "-->")
									$in_doc_comm = false;
							}
							else if ($in_doc_type)
							{
								end($new_toks);
								$nt_key = key($new_toks);
								$new_toks[$nt_key][1] .= $match;
								if ($match === ">")
									$in_doc_type = false;
							}
							else if ($match === "<")
							{
								$text_tok = null;
								if (!$in_tag)
								{
									$in_tag = true;
									// add open tag
									$new_toks[] = array(T_QXML_TAG_OPEN, "<", null);
								}
								else
								{
									// var_dump($rest_to_parse, $in_attr, $matches);
									var_dump($p, $rest_to_parse, $match, $matches);
									throw new Exception("Parse error");
								}
							}
							else if ($in_tag && ($match === "/>"))
							{
								$text_tok = null;
								$in_tag = false;
								$new_toks[] = array(T_QXML_TAG_SHORT_CLOSE, $match, null);
							}
							else if ($in_tag && ($match === ">"))
							{
								$text_tok = null;
								$in_tag = false;
								$new_toks[] = array(T_QXML_TAG_CLOSE, $match, null);
							}
							else if (substr($match, 0, 2) === "</")
							{
								$in_tag = true;
								$text_tok = null;
								$new_toks[] = array(T_QXML_TAG_END, $match, null);
							}
							else if (substr($match, 0, 4) === "<!--")
							{
								$text_tok = null;
								// comment
								$new_toks[] = array(T_QXML_COMMENT, $match, null);
								if (substr($match, -3, 3) !== "-->")
									$in_doc_comm = true;
							}
							else if ((substr($match, 0, 2) === "<!") && (strtolower(substr($match, 2, 7)) === "doctype"))
							{
								$text_tok = null;
								// doctype
								$new_toks[] = array(T_QXML_DOCTYPE, $match, null);
								$in_doc_type = true;
							}
							else if ($in_tag)
							{
								$text_tok = null;
								// tag name, attributes elements should be here: whitespace, name, equal, value
								$prev = $matches[0][$p - 1];
								if (($prev === "<") || ($prev === "</"))
								{
									if ((!$is_patch) && $patch_tags[$match])
										$is_patch = true;
									$new_toks[] = array(T_QXML_TAG_NAME, $match, null);
								}
								else if ($match === "=")
									$new_toks[] = array(T_QXML_ATTR_EQUAL, $match, null);
								else if (($match === "\"") || ($match === "'"))
								{
									$new_toks[] = array(T_QXML_ATTR_VALUE, $match, null);
									$in_attr_quote = $in_attr ? null : $match;
									// inverse $in_attr
									$in_attr = !$in_attr;
								}
								else if (($match{0} === "\"") || ($match{0} === "'"))
								{
									if ($in_attr)
										throw new Exception("Parse error");
									$len = strlen($match);
									if ($match{0} !== $match{$len-1})
									{
										$in_attr_quote = $match{0};
										$in_attr = true;
									}
									else
									{
										$in_attr_quote = null;
										$in_attr = false;
									}
									$new_toks[] = array(T_QXML_ATTR_VALUE, $match, null);
								}
								else
								{
									if ($in_attr)
									{
										$new_toks[] = array(T_QXML_ATTR_VALUE, $match, null);
									}
									// whitespace or name
									else if ($m_spaces[$p])
									{
										$new_toks[] = array(T_QXML_ATTR_SPACE, $match, null);
									}
									else
									{
										if ((!$is_patch) && $patch_attrs[$match])
											$is_patch = true;
										$new_toks[] = array(T_QXML_ATTR_NAME, $match, null);
									}
								}
							}
							else
							{
								// text element
								if ($text_tok === null)
								{
									$text_tok = count($new_toks);
									$new_toks[] = array(T_QXML_TEXT, $match, null);
								}
								else
								{
									$new_toks[$text_tok][1] .= $match;
								}
							}

							$p++;
						}
					}
					else
					{
						// just leave the token as it was
						$new_toks[] = $tok;
					}
				}
				else if ($in_doc_comm || $in_doc_type)
				{
					end($new_toks);
					$nt_key = key($new_toks);
					$new_toks[$nt_key][1] .= is_array($tok) ? $tok[1] : $tok;
					
					// var_dump($new_toks[$nt_key][1]);
				}
				else
					$new_toks[] = $tok;
			}
			
			// echo "{$this->filename} ========================================================";
			// var_dump($new_toks);
			
			return $new_toks;
		}
		else
			return $tokens;
	}
	
	public static function ParseTemplate($template_string)
	{
		$pos = 0;
		return static::ParseOutput(null, $template_string, null, $pos, true);
	}
	
	public static function ParseOutput($parent, $tokens, $tok = null, &$pos = 0, $expand_output = false, $stacked = false, $expand_in_methods = true, $expand_arrays = false)
	{
		if (is_string($tokens))
		{
			$tmp_parser = new QPHPTokenCode();
			$tokens = $tmp_parser->getAllTokens($tokens, $expand_output);
		}
		$ret = $parent ? null : array();
		while (($tok = $tokens[$pos]))
		{
			$type = is_array($tok) ? $tok[0] : null;
			if (!$type)
			{
				if ($tok instanceof QPHPToken)
				{
					// already parsed
					if ($parent)
						$parent->children[] = $tok;
					else
						$ret[] = $tok;
						
					$pos++;
					$tok = $tokens[$pos];
					continue;
				}
				else if ($tok === "}")
				{
					// in case we have: <div><div><?php } // closing method or code block
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
					// we need to return to avoid the error
					// in this case we need to go back to the parent QPHPTokenCode that opened the "{"
					return;
				}
				else
				{
					var_dump(get_class($parent));
					echo "<textarea>".$parent."</textarea>";
					echo "<textarea>".$parent->parent->parent."</textarea>";
					
					var_dump($tok);

					throw new Exception("Unexpected. Output must be typed.");
				}
			}
			// this can only happen with $expand_output
			if ($type === T_QXML_TAG_OPEN)
			{
				// opening a tag
				$child = new QPHPTokenXmlElement($parent);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				
				if ($parent)
					$child->setParent($parent);
				else
					$ret[] = $child;
				
			}
			else if (($type === T_OPEN_TAG) || ($type == T_OPEN_TAG_WITH_ECHO))
			{
				if ($stacked)
				{
					if ($parent)
						$parent->children[] = $tok;
					else
						$ret[] = $tok;
						
					$pos++;
					break;
				}

				$child = new QPHPTokenCode($parent);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				
				if ($parent)
					$child->setParent($parent);
				else
					$ret[] = $child;
			}
			else if (($type === T_INLINE_HTML) || (($type >= T_QXML_MIN) && ($type <= T_QXML_MAX)))
			{
				if ($parent)
					$parent->children[] = $tok;
				else
					$ret[] = $tok;
				$pos++;
			}
			else 
			{
				// var_dump($tok, $type, $tokens[$pos - 3], $tokens[$pos - 2], $tokens[$pos - 1], $tok, $tokens[$pos + 1], $tokens[$pos + 2], $tokens[$pos + 3], $tokens[$pos + 4], $tokens[$pos + 5]);
				throw new Exception("Unexpected");
			}
		}
		
		return $parent ? null : $ret;
	}
	
	/**
	 * 
	 * @return QPHPTokenFile
	 */
	public function getRoot()
	{
		return $this->root ?: ($this->root = ($this->parent ? $this->parent->getRoot() : $this));
	}

	public function generateUrlController(QGeneratePatchInfo $gen_info, QPHPTokenCode $code = null, $return_str = false, $prefix = null, $sufix = null, $args_offset = 0)
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
				{
					$ret = $child->generateUrlController($gen_info, $code, $return_str, $prefix, $sufix, $args_offset);
					$code = $code ?: $ret;
				}
			}
		}
		
		return $code;
	}
	
	public function generateEventMethod(QGeneratePatchInfo $gen_info, QPHPTokenCode $code = null)
	{
		// var_dump("generateEventMethod", get_class($this));
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
				{
					// var_dump("DO", get_class($child));
					$ret = $child->generateEventMethod($gen_info, $code);
					$code = $code ?: $ret;
				}
			}
		}
		
		return $code;
	}
	
	/**
	 * 
	 * @param string $class
	 * @return QPHPTokenClass
	 */
	public function findPHPClass($class = null)
	{
		foreach ($this->children as $child)
		{
			if (($child instanceof QPHPTokenClass) && ($class ? ($child->className === $class) : true))
				return $child;
			else if ($child instanceof QPHPToken)
			{
				$ret = $child->findPHPClass($class);
				if ($ret)
					return $ret;
			}
		}
	}
	
	public function findMethod($name)
	{
		if ($this instanceof QPHPTokenClass)
		{
			$elems = $this->children(".QPHPTokenFunction");
			// return $elems ? reset($elems) : null;
			if ($elems)
			{
				foreach ($elems as $e)
				{
					if ($e->name === $name)
						return $e;
				}
			}
			return null;
		}
		else
		{
			$class = $this->findFirst(".QPHPTokenClass");
			return $class ? $class->findMethod($name) : null;
		}
	}
	
	/**
	 * 
	 * @return \QPHPTokenCode
	 */
	public function findCode()
	{
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPTokenCode)
				return $child;
		}
	}
	
	public function findSwitchCode()
	{
		if ($this instanceof QPHPTokenFunction)
			return $this->findCode()->findSwitchCode();
		
		// $pos = 0;
		$after_switch = false;
		foreach ($this->children as $child)
		{
			if ($after_switch)
			{
				if ($child instanceof QPHPTokenCode)
					return $child;
			}
			else if (is_array($child) && ($child[0] === T_SWITCH))
				$after_switch = true;
		}
	}
	
	public function findChildrenWithTag($tag, $with_index = false)
	{
		if (!$this->children)
			return null;
		$ret = array();
		
		$lc = strtolower($tag);
		foreach ($this->children as $pos => $child)
		{
			if (($child instanceof QPHPToken) && $child->tag && (strtolower($child->tag) === $lc))
				$ret[$with_index ? $pos : null] = $child;
		}
		
		return $ret;
	}

	public function findChildWithTag($tag)
	{
		if ($this->children)
		{
			$lc = strtolower($tag);
			foreach ($this->children as $child)
			{
				if (($child instanceof QPHPToken) && $child->tag && (strtolower($child->tag) === $lc))
					return $child;
			}
		}
	}
	
	public function rightTrimCodeSemicolon($list = null)
	{
		$list = $list ?: $this->children;
		
		$count = count($list);
		if ($count === 0)
			return;
		
		$splice_pos = -1;
		for ($i = ($count - 1); $i >= 0; $i--)
		{
			$itm = $list[$i];
			if (is_array($itm) && (($itm[0] === T_WHITESPACE) || ($itm[0] === T_COMMENT) || ($itm[0] === T_DOC_COMMENT)))
				continue;
			else if ($itm === ";")
			{
				$splice_pos = $i;
				break;
			}
			else
				break;
		}
		if ($splice_pos > -1)
			// $list->splice($splice_pos, 1);
			array_splice($list, $splice_pos, 1);
		
		return $list;
	}
	
	public function isFirstChildWithTag()
	{
		if (!$this->tag)
			return false;
		
		if (!($this->parent && $this->parent->children))
			return false;
		
		$lct = strtolower($this->tag);
		// var_dump($lct);
		if ($lct === "url")
		{
			//var_dump($this->tagsPathAsString());
		}
		foreach ($this->parent->children as $child)
		{
			if (($child instanceof QPHPToken) && (strtolower($child->tag) === $lct))
			{
				if ($lct === "url")
				{
					//var_dump($child === $this);
				}
				return ($child === $this);
			}
		}
		if ($lct === "url")
		{
			//var_dump(false);
		}
		return false;
	}
	
	public function tagsPathAsString()
	{
		return ($this->parent && ($ptag = $this->parent->tagsPathAsString()) ? 
					($ptag . ($this->tag ? (" > " . $this->tag) : "")) : ($this->tag ?: null));
	}
	
	/**
	 * Setup how patches work
	 *
	 * @var array[]
	 */
	protected static $PatchData = array(
			"qAppend" => array(
					"append", // method
					"qFind"), // attr name to extract selector when tag is explicit

			"qInsertAfter" => array(
					"insertAfter", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qInsertBefore" => array(
					"insertBefore", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qPrepend" => array(
					"prepend", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qReplace" => array(
					"replace", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qEmpty" => array(
					"emptyNode", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qRemove" => array(
					"remove", // method
					"qFind"), // attr name to extract selector when tag is explicit
		
			"qInner" => array(
					"inner", // method
					"qFind") // attr name to extract selector when tag is explicit
		
			// TO DO: 
		
			);
	
	public function inheritFrom(QPHPToken $patch_token, QPHPTokenFile $patch_file = null, QPHPTokenFile $root_file = null, QGeneratePatchInfo $gen_info = null)
	{
		if ((!$patch_token) || (!$this->children) || (!$patch_token->children))
			return;
		
		$match_map = $this->getChildrenMatchMap($gen_info->type);
		$match_map_from = $patch_token->getChildrenMatchMap($gen_info->type);
		
		$last_match_pos = $this->getPrependPos();
		
		if ($this instanceof QPHPTokenFunction)
			throw new Exception("Invalid algorithm for QPHPTokenFunction / getPrependPos is not compatible");
		
		foreach ($match_map_from as $key => $data)
		{
			// var_dump($key);
			
			list(/*$pos*/, $element) = $data;
			
			// find a match
			// key pattern : /[typos]type/
			$mm = $match_map[$key];
			
			list(/*$new_pos*/, $new_elem, $merge_mode) = $mm ? $mm : array(null, null, null);
			
			if ($mm && $merge_mode)
			{
				if ($merge_mode === "append")
				{
					// it's the other way around , we prepend the element we inherit !
					$new_elem->prepend($element->inner());
				}
				else if ($merge_mode === "prepend")
				{
					// it's the other way around , we append the element we inherit !
					$new_elem->append($element->inner());
				}
				else
				{
					var_dump($merge_mode);
					throw new Exception("Invalid merge mode: ".$merge_mode);
				}
					
				// we are done
				continue;
			}
			
			if (($element instanceof QPHPToken) && ($mm))
			{
				
				// for not QPHPTokenXmlElement we keep the new version and we continue
				if (!($element instanceof QPHPTokenXmlElement))
					continue;
				
				$new_elem->inheritFrom($element, $patch_file, $root_file, $gen_info);
				$new_elem_pos = null;
				foreach ($this->children as $op_pos => $op_ch)
				{
					if ($op_ch === $new_elem)
					{
						$new_elem_pos = $op_pos;
						break;
					}
				}
				if ($new_elem_pos === null)
					throw new Exception("should not happen");
				$last_match_pos = $new_elem_pos + 1;
				// var_dump("reset pos at: {$last_match_pos} / {$element->tag}");
			}
			else
			{
				if ($mm)
				{
					// new version is better
					continue;
				}
				else
				{
					// does not exists, should be added
					// qMerge = false - do not merge
					$append_pos = $this->getAppendPos();
					$splice_pos = ($append_pos > $last_match_pos) ? $last_match_pos : $append_pos;
					
					$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;

					// $inner_element->children->splice($splice_pos, 0, array($element));
					array_splice($inner_element->children, $splice_pos, 0, array($element));
					if ($element instanceof QPHPToken)
						$element->parent = $inner_element;
					/*else
						var_dump($splice_pos, $this->toString($element));*/

					$last_match_pos = $splice_pos + 1;
				}
			}
		}
		
		if ($this instanceof QPHPTokenFile)
		{
			// echo "<pre>";
			// var_dump($this->filename, $this."");
			// var_dump($patch_token->filename, $patch_token."");
			// echo "============================================================================================================================";
			// echo "</pre>";
		}
	}
	
	public function getChildrenMatchMap($gen_type)
	{
		if (!$this->children)
			return null;
		
		$map = array();
		$types_index = array();
		
		$start = $this->getPrependPos();
		$end = $this->getAppendPos();
		
		$inner_element = ($this instanceof QPHPTokenFunction) ? $this->getFunctionCode() : $this;
		
		for ($k = $start; $k < $end; $k++)
		{
			$child = $inner_element->children[$k];
			if ($child instanceof QPHPToken)
			{
				$match_key = $child->getMatchingKey($k, $types_index, $gen_type);
				if ($match_key !== false)
				{
					if (is_array($match_key))
						$map[$match_key[0]] = array($k, $child, $match_key[1]);
					else
						$map[$match_key] = array($k, $child);
				}
			}
			else
			{
				if (is_array($child) && ($child[0] === T_QXML_DOCTYPE))
				{
					// var_dump($child);
					
					// $ty_index = $types_index["string"] ? $types_index["string"]++ : ($types_index["string"] = 0);
					$map["T_QXML_DOCTYPE"] = array($k, $child);
					// var_dump($map["T_QXML_DOCTYPE"]);
				}
				else
				{
					$ty_index = $types_index["string"] ? $types_index["string"]++ : ($types_index["string"] = 0);
					$map["[{$ty_index}]string/".uniqid()] = array($k, $child);
				}
			}
		}
		
		return $map;
	}
	
	public function getMatchingKey($pos, $types_index, $gen_type)
	{
		$c = get_class($this);
		$ty_index = $types_index[$c] ? $types_index[$c]++ : ($types_index[$c] = 0);
		return "[{$ty_index}]{$c}";
	}
	
	/*
	public function gatherMapIndex(&$map, $parent = "", $pos = null)
	{
		if ($this instanceof QPHPTokenFile)
			// skip the first one
			$key = "";
		else
		{
			$key = $parent."/".(($pos !== null) ? "[{$pos}]" : "").get_class($this);
			$map[$key] = $this;
		}
		if ($this->children)
		{
			foreach ($this->children as $ck => $child)
			{
				if ($child instanceof QPHPToken)
					$child->gatherMapIndex($map, $key, $ck);
				else
					$map[$key."/[{$ck}]"] = $this;
			}
		}
	}
	*/

	protected function shortDebugPrint()
	{
		$ret = "[tag:".$this->tag."]";
		if ($this->attrs)
		{
			$ret .= "(";
			foreach ($this->attrs as $k => $v)
				$ret .= "{$k}={$v};";
			$ret .= ")";
		}
		return $ret;
	}
	
	public function checkChildrenCount()
	{
		if (!$this->children)
			return true;
		$pos = 0;
		foreach ($this->children as $k => $child)
		{
			if ($k !== $pos)
			{
				throw new Exception("Broken count");
				// return false;
			}
			$pos++;
		}
		return true;
	}
	
	public function testAndSkipJsFunc(&$tokens, &$ret)
	{
		return false;
	}
	
	public function cleanupTemplate()
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
					$child->cleanupTemplate();
			}
		}
	}
	
	public static function ParseHeaderOnly($path, $apply_namespace = false, &$readonly_tokens = null, bool $with_doc_comment = true)
	{
		# echo "ParseHeaderOnly::{$path}<br/>\n";
		
		if (!file_exists($path))
			throw new Exception("Missing file: ".$path);
		
		$base = basename($path);
		$ext = pathinfo($base, PATHINFO_EXTENSION);
		$ext_2 = pathinfo(substr($base, 0, -(strlen($ext) + 1)), PATHINFO_EXTENSION);
		
		$class = substr($base, 0, strpos($base, "."));
		if ($class{0} !== strtoupper($class{0}))
			// not a class
			return null;
		
		$ret = array("class" => $class);
		$doc_comment = null;
		$doc_comment_was_set = null;
		
		if (($ext === "php") && ($ext_2 !== 'url'))
		{
			// $tokens = qtoken_get_all(file_get_contents($path));
			$tokens = QPHPToken::GetPHPTokensFromFile($path);
			
			$last_type = false;
			
			// find class definition
			while (($token = next($tokens)))
			// foreach ($tokens as $tok_p => $token)
			{
				$tok_p = key($tokens);
				$is_arr = is_array($token);
				$t_type = $is_arr ? $token[0] : null;
				
				if (($token === "{") || ($is_arr && ($token[1] === "{")))
				{
					$last_type = false;
					$tok_pos = $tok_p + 1;
					
					$token = $tokens[$tok_pos];
					$in_trait_use = false;
					$exit_loop = false;
					// find use & break
					while ($token)
					{
						$is_arr = is_array($token);
						$t_type = $is_arr ? $token[0] : null;
						
						if (($token === ";") || ($is_arr && ($token[1] === ";")))
							break;
						else if ($in_trait_use)
						{
							if ($t_type === T_STRING)
								$ret["traits"][$token[1]] = $token[1];
						}
						else if ($is_arr)
						{
							$t_type = $token[0];
							// we already have use, we will need to make sure we have this trait
							switch ($t_type)
							{
								case T_USE:
								{
									$in_trait_use = true;
									break;
								}
								case T_PRIVATE:
								case T_PUBLIC:
								case T_PROTECTED:
								case T_FUNCTION:
								case T_CONST:
								{
									$exit_loop = true;
									break;
								}
							}
						}
						
						if ($exit_loop)
							break;
						
						$token = $tokens[++$tok_pos];
					}
					
					break;
				}
				
				if (($last_type !== false) && (($t_type === T_STRING) || ($t_type === T_NS_SEPARATOR)))
				{
					// find identifer
					$identifier = [$token[1]];
					while (($idf_tok = next($tokens)) && (is_array($idf_tok) && ($idf_ty = $idf_tok[0]) && 
									(($idf_ty === T_STRING) || ($idf_ty === T_NS_SEPARATOR) || ($idf_ty === T_WHITESPACE))))
					{
						if ($idf_ty !== T_WHITESPACE)
							$identifier[] = $idf_tok[1];
					}
					// we step one back to fix position
					prev($tokens);
					$identifier = implode("", $identifier);
					
					switch ($last_type)
					{
						case T_CLASS:
						case T_INTERFACE:
						case T_TRAIT:
						{
							$ret["class"] = $identifier;
							$ret["type"] = ($last_type === T_CLASS) ? 'class' : (($last_type === T_TRAIT) ? 'trait' : 'interface');
							$last_type = false;
							break;
						}
						case T_EXTENDS:
						{
							$ret["extends"] = $identifier;
							$last_type = false;
							break;
						}
						case T_IMPLEMENTS:
						{
							$ret["implements"][$identifier] = $identifier;
							// here we don't reset $last_type as we may expect more types
							break;
						}
						case T_NAMESPACE:
						{
							$ret["namespace"] = $identifier;
							$last_type = false;
							break;
						}
						default:
							break;
					}
				}
				else
				{
					switch ($t_type)
					{
						case T_CLASS:
						case T_INTERFACE:
						case T_TRAIT:
						case T_EXTENDS:
						case T_IMPLEMENTS:
						{
							if (!$doc_comment_was_set)
							{
								$doc_comment_was_set = true;
								if ($doc_comment)
									$ret['doc_comment'] = $doc_comment;
							}
							$last_type = $t_type;
							break;
						}
						case T_NAMESPACE:
						{
							$last_type = $t_type;
							break;
						}
						case T_FINAL:
						case T_ABSTRACT:
						{
							if (!$doc_comment_was_set)
							{
								$doc_comment_was_set = true;
								if ($doc_comment)
									$ret['doc_comment'] = $doc_comment;
							}
							$ret['is_'.(($t_type === T_FINAL) ? 'final' : (($t_type === T_ABSTRACT) ? 'abstract' : null))] = true;
							break;
						}
						case T_DOC_COMMENT:
						{
							$doc_comment = $token[1];
						}
						default:
							break;
					}
				}
			}
		}
		else if (($ext === "tpl") || (($ext === "php") && ($ext_2 === "url")))
		{
			// $tokens = qtoken_get_all(file_get_contents($path));
			$readonly_tokens = $tokens = QPHPToken::GetPHPTokensFromFile($path);
			$file_without_php = "";
			
			$has_opener = false;
			$q_args = null;
			
			foreach ($tokens as $tok)
			{
				if (is_array($tok) && ($tok[0] === T_INLINE_HTML))
				{
					$file_without_php .= $tok[1];
					
					// split $tok[1] into tokens
					$matches = null;

					$stat = preg_match_all("/".
						"<\\!doctype\\s+|". # doc type
						"<\!\-\-.*?\-\->|". # XML comments full
						"<\!\-\-|". # XML comments : <!-- --> (start)
						"\-\->|". # XML comments : <!-- --> (end)
						"'(?:(?:\\\\.|[^\\\\'])*)'|". # string with '
						"\"(?:(?:\\\\.|[^\\\\\"])*)\"|". # string with "
						"[\\p{L&}\\$][\\w\\.\\-\\:]+|\\p{L&}+|". # attribute names (and not only)
						"\\<\\/|".
						"\\/\\>|".
						"[\\<\\>\\=]{1}|".
						"\\\"|\\\'|". # quote, double or single
						"(\\s+)|".				# whitespace
						"[^\\<\\>]+|". # text (here it is)
						"(.+)". # parse error

						"/ius", $file_without_php, $matches);

					if (!$stat)
						throw new Exception("Parse error");
					
					$has_opener = false;
					$has_closer = false;
					
					$extends = null;
					$implements = null;
					$namespace = null;
					
					$last_attr_name = null;
					$last_m = null;
					
					foreach ($matches[0] as $m)
					{
						// ignore whitespace
						if (ctype_space($m))
							continue;
						
						if ($m === "<")
							$has_opener = true;
						else if ($m === ">")
						{
							if ($has_opener)
							{
								$has_closer = true;
								break;
							}
						}
						else if ($m === "=")
							$last_attr_name = $last_m;
						else if (($m{0} === "\"") || ($m{0} === "'"))
						{
							$last_attr_value = substr($m, 1, -1);
							if (($last_attr_name === "extends") || ($last_attr_name === "q-extends"))
								$extends = $last_attr_value;
							else if (($last_attr_name === "implements") || ($last_attr_name === "q-implements"))
								$implements = $last_attr_value;
							else if (($last_attr_name === "q-namespace") || ($last_attr_name === "qNamespace"))
								$namespace = $last_attr_value;
							else if (($last_attr_name === "q-args") || ($last_attr_name === "qArgs"))
								$q_args = $last_attr_value;
							$last_attr_name = null;
						}
						else
						{
							$last_attr_name = null;
							$last_attr_value = null;
						}
						
						$last_m = $m;
					}

					if ($has_closer)
					{
						if ($extends)
							$ret["extends"] = trim($extends);
						if ($implements)
						{
							$implements = preg_split('/\s+/ius', $implements, -1, PREG_SPLIT_NO_EMPTY);
							foreach ($implements as $impl)
								$ret["implements"][$impl] = $impl;
						}
						if ($namespace)
							$ret["namespace"] = trim($namespace);
						if ($q_args)
							$ret["q-args"] = trim($q_args);
						
						break;
					}
				}
			}
		}
		else
			return null;
		
		if ($apply_namespace) // apply even when namespace is not present to fix possible leading "\\"
		{
			if ($ret["class"])
				$ret["class"] = self::ApplyNamespaceToName($ret["class"], $ret["namespace"]);
			if ($ret["extends"])
				$ret["extends"] = self::ApplyNamespaceToName($ret["extends"], $ret["namespace"]);
			if ($ret["implements"])
			{
				$new = [];
				foreach ($ret["implements"] as $k => $v)
				{
					$new_k = self::ApplyNamespaceToName($ret["implements"][$k], $ret["namespace"]);
					$new[$new_k] = $new_k;
				}
				$ret["implements"] = $new;
			}
			if ($ret["traits"])
			{
				$new = [];
				foreach ($ret["traits"] as $k => $v)
				{
					$new_k = self::ApplyNamespaceToName($ret["traits"][$k], $ret["namespace"]);
					$new[$new_k] = $new_k;
				}
				$ret["traits"] = $new;
			}
		}
		
		return $ret;
	}
	
	public static function ApplyNamespaceToName($class, $namespace)
	{
		if ($class{0} === "\\")
			return substr($class, 1);
		else
			return $namespace ? $namespace."\\".$class : $class;
	}
	
	/**
	 * Gets the shortname for a FULL Class name within the specified namespace
	 * 
	 * @param string|string[]|QModelAcceptedType $full_class
	 * @param string $namespace
	 */
	public static function ShortNameForNamespace($full_class, $namespace = null)
	{
		if (is_array($full_class))
		{
			$return = [];
			foreach ($full_class as $fc)
				$return[$fc] = self::ShortNameForNamespace($fc, $namespace);
			return $return;
		}
		else if ($full_class instanceof QModelAcceptedType)
		{
			$options = [];
			$type = self::ShortNameForNamespace($full_class->type, $namespace);
			foreach ($full_class->options as $opt)
			{
				$ns_short_class = self::ShortNameForNamespace($opt, $namespace);
				$options[$ns_short_class] = $ns_short_class;
			}
			$acc_type = new QModelAcceptedType($type, $options, $full_class->strict);
			$acc_type->no_export = $full_class->no_export;
			return $acc_type;
		}
		else
		{
			// if no namespace or scalar
			if ((!$namespace) || (strtolower($full_class{0}) === $full_class{0}))
				return $full_class;
			if ($full_class{0} === "\\")
				$full_class = substr($full_class, 1);
			// reduce namespace as much as possible
			$parts = explode("\\", $full_class);
			$parts_ns = explode("\\", $namespace);
			$part = reset($parts);
			$short_class = "";
			
			foreach ($parts_ns as $ns_part)
			{
				if ($part === $ns_part)
					$part = next($parts);
				else
					// not full match on namespace, prepend \ and return full class name
					return "\\".$full_class;
			}
			while ($part)
			{
				if ($short_class)
					$short_class .= "\\";
				$short_class .= $part;
				$part = next($parts);
			}
			return $short_class;
		}
	}
	
	/**
	 * Replaces all tokens that is either a string or array and match the first parameter
	 * 
	 * @param array|string $tok_to_find
	 * @param array|string $replacement
	 * @param string $ty
	 * @return void
	 */
	public function replaceTokenRecursive($tok_to_find, $replacement, $ty = null)
	{
		if (!$this->children)
			return;
		// we need it for this: // replace all T_VARIABLE, where value = '$this' with '$_this' - will be easy
		$ty = $ty ?: gettype($tok_to_find);
		foreach ($this->children as $k => $child)
		{
			if (gettype($child) === $ty)
			{
				if ($ty === "string")
				{
					if ($child === $tok_to_find)
						$this->children[$k] = $replacement;
				}
				else if ($ty === "array")
				{
					if (($child[0] === $tok_to_find[0]) && ($child[1] === $tok_to_find[1]))
						$this->children[$k] = $replacement;
				}
				else
					$child->replaceTokenRecursive($tok_to_find, $replacement, $ty);
			}
		}
	}
	
	public function findFirstCodeElement()
	{
		if (!$this->children)
			return null;
		
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPTokenCode)
				return $child;
		}
	}
	
	public function findLastCodeElement()
	{
		if (!$this->children)
			return null;
		
		$child = end($this->children);
		while ($child)
		{
			if ($child instanceof QPHPTokenCode)
			{
				reset($this->children);
				return $child;
			}
			$child = prev($this->children);
		}
		reset($this->children);
		return null;
	}
	
	public static $__cntotks = 0;
	
	public static function GetPHPTokensFromFile($file)
	{
		return token_get_all(file_get_contents($file));
	}
	
	public function getNamespace()
	{
		$code = $this->findFirstCodeElement();
		return $code ? $code->getNamespace() : null;
	}
		
	/**
	 * 
	 * @param string $template
	 * @return string
	 * @throws Exception
	 */
	public static function ParseTemplateMarkings($template, string $src_file = null)
	{
		// handle controls 
		// non-greedy search
		
		$has_ctrls = false;
		// if we nest controls this will be an epic fail !!!
		// start ctrl
		$template = preg_replace_callback('/((?:^|\n)\s*)\@ctrl\b\s*\:\s*([\w\\\\]+)\s*(?:\[\s*([\w\\\\]+)\s*\])?(\s*\r?\n)/us', 
				function ($ctrl_matches) use (&$has_ctrls)
				{
					$has_ctrls = true;
					$class_name = static::GetQCtrlFullClassName($ctrl_matches[2]);
					$ctrl_name = $ctrl_matches[3];
					return $ctrl_matches[1]."<q_ctrl q-class='{$class_name}' q-name='{$ctrl_name}'>".$ctrl_matches[4];
				},
				$template);
		// end ctrl
		$template = preg_replace_callback('/((?:^|\n)\s*)\@endctrl\b(\s*\r?\n)/us', 
				function ($ctrl_matches) use (&$has_ctrls)
				{
					$has_ctrls = true;
					return $ctrl_matches[1]."</q_ctrl>".$ctrl_matches[2];
				},
				$template);
		// bind in ctrl
		$template = preg_replace_callback('/((?:^|\n)\s*)\@bind(?:s)?\b(\s*\r?\n)(.*?)(\r?\n\s*\<\/?[\w\_]+)/us', // go all the way to \n\s*<(/|\w 
				function ($ctrl_matches) use (&$has_ctrls)
				{
					// qvar_dump($ctrl_matches[0]);
					$has_ctrls = true;
					$binds_str = static::RenderQCtrlBinds($ctrl_matches[3]);
					return $ctrl_matches[1].$binds_str.$ctrl_matches[4];
				},
				$template);
				
		if ($has_ctrls)
		{
			// qvar_dump($template);
		}
		
		// htmlspecialchars($string, ENT_HTML5 | ENT_COMPAT | ENT_SUBSTITUTE)
		$reg_exp = "/\\{\\{\\s*(\\$(?:[a-zA-Z0-9\\_\\s\\[\\]\\\"\\'\\$]|(?:\\-\\>))+)\\s*\\}\\}/us"; // matching vars & adding isset
		$template = preg_replace($reg_exp, "<?= isset(\$1) ? htmlspecialchars(\$1) : \"\" ?>", $template);
		
		$reg_exp = "/\\{\\{\\s*(.*?)\\s*\\}\\}/us"; // matching expressions
		$template = preg_replace($reg_exp, "<?= htmlspecialchars(\$1) ?>", $template);
		
		$predicates = ["@\\$", "@var", "@php", "@code", "@endcode", "@if", "@elseif", "@else", "@endif", "@endforeach", 
					"@endfor", "@endwhile", "@endswitch", "@break", "@continue", "@endeach", "@foreach", 
					"@echo",
					"@for", "@do", "@while", "@switch", "@case", "@default", "@each", "@include",
					"@end"];
		foreach ($predicates as $k => $p)
			$predicates[$k] = str_replace(["@"], ["\\@"], $p);

		$pred_exp = "(".implode("|", $predicates).")";
		$reg_exp = "/{$pred_exp}(.*?)?(\\r?\\n|\$)|".
				// "(\\{\\{\\s*(?:.*?)\\s*\\}\\})". // matching vars
				"/us";
		
		$pm_resp = preg_split($reg_exp, $template, -1, PREG_SPLIT_DELIM_CAPTURE);

		if ($pm_resp === false)
			throw new Exception("parse error");
		
		$out = [];
		$len = count($pm_resp);
		
		for ($i = 0; $i < $len; $i++)
		{
			/*switch ($x):
				case "abcd":
			endswitch;*/
			
			$chunk = $pm_resp[$i];
			switch ($chunk)
			{
				case "@var":
				case "@php":
				{
					$out[] = "<?php ".rtrim(ltrim($pm_resp[++$i]), " \t\n;")."; ?>";
					break;
				}
				case "@echo":
				{
					$out[] = "<?= ".rtrim(ltrim($pm_resp[++$i]), " \t\n;")."; ?>";
					break;
				}
				case "@end":
				{
					$out[] = "<?php } ?>";
					break;
				}
				case "@code":
				{
					$out[] = "<?php ";
					break;
				}
				case "@endcode":
				{
					$out[] = " ?>";
					break;
				}
				case "@\\$":
				case "@break":
				case "@continue":
				{
					$out[] = "<?php ".substr($chunk, 1).rtrim(ltrim($pm_resp[++$i]), " \t\n;")."; ?>";
					break;
				}
				case "@endif":
				case "@endforeach":
				case "@endfor":
				case "@endwhile":
				case "@endswitch":
				{
					/* $out[] = "<?php ".substr($chunk, 1).rtrim(ltrim($pm_resp[++$i]), " \t\n;")."; ?>"; */
					$out[] = "<?php } ?>";
					break;
				}
				case "@endeach":
				{
					$out[] = "<?php } ".rtrim(ltrim($pm_resp[++$i]), " \t\n;")."; ?>";
					break;
				}
				case "@if":
				case "@foreach":
				case "@for":
				case "@do":
				case "@while":
				case "@switch":
				{
					// @todo : @do .. @while is not implemented ok !
					$out[] = "<?php ".substr($chunk, 1).ltrim($pm_resp[++$i])." { ?>";
					break;
				}
				case "@else":
				case "@elseif":
				{
					$out[] = "<?php } ".substr($chunk, 1).ltrim($pm_resp[++$i])." { ?>";
					break;
				}
				case "@case":
				case "@default":
				{
					$out[] = "<?php ".substr($chunk, 1).ltrim($pm_resp[++$i])." ?>";
					break;
				}
				case "@each":
				{
					$out[] = "<?php foreach".ltrim($pm_resp[++$i])." { ?>";
					break;
				}
				case "@include":
				{
					// @include(\Omi\Cms\View\Menu::item, \$Data, 2, 'this is me', 4, \$var) // and a comment
					// => \Omi\Cms\View\Menu::renderItem(\$Data, 2, 'this is me', 4, \$var);
					// $str = "(\Omi\Cms\View\Menu::item, \$Data,2,'this is me',4, \$var) // and a comment";
					$out[] = self::ConvertTplIncludeToPhp($pm_resp[++$i]);
					break;
				}
				case "@ctrl":
				case "@control":
				{
					// @control \Omi\View\DropDown {
					//		// place init code here
					//		function overwriteIt()
					//		{
					//			// do something else
					//		}
					// }
					// dynamic controls in a easier manner
					// @todo
					break;
				}
				default:
				{
					// htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
					if ((substr($chunk, 0, 2) === "{{") && (substr($chunk, -2, 2) === "}}"))
						$out[] = "<?= htmlspecialchars(".trim(substr($chunk, 2, -2)).", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>";
					else
						$out[] = $chunk;
					break;
				}
			}
		}

		return implode("", $out);
	}
	
	protected static function ConvertTplIncludeToPhp($str)
	{
		// => \Omi\Cms\View\Menu::renderItem(\$Data, 2, 'this is me', 4, \$var);
		// $str = "(\Omi\Cms\View\Menu::item, \$Data,2,'this is me',4, \$var) // and a comment";
		// ($this->bla->bla or .   / T_OBJECT_OPERATOR, 
		
		$toks = token_get_all("<?php ".$str);
		$toks_len = count($toks);

		$incl_class = "";
		$incl_meth = "";
		$incl_rest = "";
		$incl_stage = 0; // 0 - class name, 1 - method, 2 - arguments, 3 - misc code
		$has_args = false;

		for ($i = 1; $i < $toks_len; $i++)
		{
			$tok = $toks[$i];
			$tok_ty = is_array($tok) ? $tok[0] : $tok;
			if ($incl_stage === 0) // class name
			{
				if (($tok_ty === T_STRING) || ($tok_ty === T_NS_SEPARATOR))
					$incl_class .= $tok[1];
				else if ($tok_ty === T_VARIABLE)
				{
					// we will need to advance up to :: or ,
					for (; $i < $toks_len; $i++)
					{
						$tok = $toks[$i];
						$tok_ty = is_array($tok) ? $tok[0] : $tok;
						
						if (($tok_ty === T_STRING) || ($tok_ty === T_VARIABLE) || ($tok_ty === T_OBJECT_OPERATOR))
							$incl_class .= $tok[1];
						else if ($tok_ty === T_DOUBLE_COLON)
						{
							$incl_stage = 1;
							break;
						}
						else if ($tok_ty === ",")
						{
							$has_args = true;
							$incl_stage = 2;
							break;
						}
					}
				}
				else if ($tok_ty === T_DOUBLE_COLON)
					$incl_stage = 1;
				else if ($tok_ty === ",")
				{
					$has_args = true;
					$incl_stage = 2;
				}
				else if ($tok_ty === ")")
				{
					$incl_stage = 3;
					$incl_rest .= is_array($tok) ? $tok[1] : $tok;
				}
			}
			else if ($incl_stage === 1) // method name
			{
				if ($tok_ty === T_STRING)
					$incl_meth = $tok[1];
				else if ($tok_ty === ",")
				{
					$has_args = true;
					$incl_stage = 2;
				}
				else if ($tok_ty === ")")
				{
					$incl_stage = 3;
					$incl_rest .= is_array($tok) ? $tok[1] : $tok;
				}
			}
			else 
			{
				$incl_rest .= is_array($tok) ? $tok[1] : $tok;
			}
		}
		
		if ($incl_class{0} === "\$")
		{
			if (substr(strtolower($incl_meth), 0, 6) !== "render")
				$incl_meth = "render".ucfirst($incl_meth);
		}
		else if ((!$incl_meth) && $incl_class && (($ch_0 = $incl_class{0}) !== "\\") && (strtolower($ch_0) === $ch_0))
		{
			// accept include of sub-template: @include(footer) or @include(::footer)
			// we have a method not a class
			$incl_meth = $incl_class;
			$incl_class = "static";
		}
		else if (!$incl_class)
			$incl_class = "static";
		
		// var_dump($incl_class, $incl_meth, $incl_rest);

		// => \Omi\Cms\View\Menu::renderItem(\$Data, 2, 'this is me', 4, \$var);
		// $str = "(\Omi\Cms\View\Menu::item, \$Data,2,'this is me',4, \$var) // and ?a comment";
		if ($incl_class{0} === "\$")
		{
			$incl_rest = trim($incl_rest);
			// variable
			return "<?php ".$incl_class."->{$incl_meth}(".($incl_rest ?: ")")." ?>";
		}
		else 
			return "<?php ".$incl_class."::RenderS(isset(\$this) ? \$this : null, \"".addslashes($incl_meth)."\"".($has_args ? ", " : "").trim(rtrim($incl_rest, " \t\n;"))."; ?>";
	}
	
	public function findFirstChildWithTag($tag)
	{
		if (!$this->children)
			return null;
		
		$lc_tag = strtolower($tag);
		foreach ($this->children as $child)
		{
			if (($child instanceof QPHPTokenXmlElement) && (strtolower($child->tag) === $lc_tag))
				return $child;
		}
		return null;
	}
	
	public function innerIsEmpty()
	{
		$inner = $this->inner();
		if (!$inner)
			return true;
		foreach ($inner as $inn)
		{
			if ($inn instanceof QPHPToken)
				return false;
			else if (is_string($inn) && (!empty(trim($inn))))
				return false;
			else if (is_array($inn) && (!empty(trim($inn[1]))))
				return false;	
		}
		return true;
	}
	
	public function query($css_selector, &$findings = null)
	{
		if ($findings === null)
			$findings = [];
		
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if ($child instanceof QPHPToken)
					$child->query($css_selector, $findings);
			}
		}
		
		return $findings;
	}
	
	public function parseCssSelector($css_selector)
	{
		// parse css selector
		$matches = null;
		$ok = preg_match_all("/".
				// attribute : *= includes, ^= begins, $= ends
				"\\[[a-zA-Z0-9\\_\\-]+(?:(?:\\*|\\^|\\$)?\\=(?:[a-zA-Z0-9\\_\\-]+".

					"|'(?:(?:\\\\.|[^\\\\'])*)'|". # string with '
					"|\"(?:(?:\\\\.|[^\\\\\"])*)\"|". # string with "

					")?)?\\]|".
				// by id #id
				"\\#[a-zA-Z0-9\\_\\-]+|".
				// by class .classname
				"\\.[a-zA-Z0-9\\_\\-]+|".
				// by tag div
				"[a-zA-Z0-9]+|".
				// by *
				"\\*|".
				// one level separator ">" (also consume the spaces)
				"\\s*\\>\\s*|".
				// deep level separator
				"\\s+".

				"/us", trim($css_selector), $matches); //, $limit, $flags)
		if ($ok)
		{
			$css_selector = $matches[0];
			$css_selector_list = ["0|0" => [$css_selector, 0, false]];
			return $css_selector_list;
		}
		else 
			return null;
	}
	
	public static function GetNamespaceForPath($path)
	{
		if ($path === null)
			return null;
		else if (QCodeSync::$Instance)
			return QCodeSync::$Instance->getNamespaceForPath($path);
		else
		{
			$head_inf = QPHPToken::ParseHeaderOnly($path);
			return $head_inf["namespace"];
		}
	}
	
	public static function RenderQCtrlBinds(string $binds_str)
	{
		/*
			$ctrl->query = 'Countries';
			$ctrl.binds.country <= $this.countries.selected,
			$ctrl.binds.country <=> $this.countries.selected,
			$ctrl.NoneCaption <= $this.countries.selected ? null : 'Please pick a country'
		 */
		
		// qvar_dump($binds_str);
		
		$tokens = token_get_all("<?php ".$binds_str);
		$len = count($tokens);
		$init_cb_str = "<init_cb><?php\n";
		$binds_str = "";
		
		$dom_str = "<div class='q-bind q-bind-ctrl'><!--  ";
		
		$on_left_side = true;
		for ($pos = 1; $pos < $len; $pos++)
		{
			$tok = $tokens[$pos];
			$ty = is_array($tok) ? $tok[0] : $tok;
			$str = is_array($tok) ? $tok[1] : $tok;
			
			// <= T_IS_SMALLER_OR_EQUAL
		// <=> T_SPACESHIP
		// => T_DOUBLE_ARROW
			if ($on_left_side && (($str === '=') || ($str === '=') || ($str === '<=') || ($str === '<=>') || ($str === '=>')))
			{
				$init_cb_str .= "=";
				$on_left_side = false;
			}
			else if ($on_left_side && ($str === ','))
			{
				$init_cb_str .= "="; // multipe assignments to the same op
			}
			else
				$init_cb_str .= $str;
		}
		
		$dom_str .= " --></div>";
		
		$init_cb_str .= "\n\t\t?></init_cb>\n";
		
		// from those binds => <init_cb></init_cb>
		// then				=> <binds></binds> | for this ... we need to know 
		// then mix it with JS FUNC
		// 
		
		return $init_cb_str.$binds_str;
		
		$definition_id = "static";
		
		// in PHP we only exec ... we don't create any binds
		
		// we do this somewhere in the QWebRequest
		$uniq_prefix = uniqid("qctrl_", true);
		
		function qBind() { }
		function qBindIf() { }
		function qBindList() { }
		function qBindTemplate() { }
		
		return;
		// $ctrl 
		
		// how do we handle binds over a collection ?!
		// must be definition based :)
		?>
		@if (($data = $misc['data']))
			If the collection exists put data here!
			@each ($data as $k => $v)
				plain text here
				<div>
					whatever
				</div>
			@end
			more data ...
		@end
		<?php
		
		// we could bind to an item if we give it an id
		// in more complex situations we need to keep track of paths
		
		// interested in arguments
		// @include => @bind-include 
		// $x = $arg[1]; => @bind-var $x = $arg[1]
		
		// all => $ctrl.binds-data , then what ?!
		//		each element will be from a "source"
		
		/*
		
		Let's define what a bind is:		
				a bind adds listeners
				
				qBind(
					q_ctrl($this ?: this),		# control
					q_ctx($_ctx_ ?: null),		# context | to do more later
					[							# list of elements listening
						event_name(s) 
							=> path_to_object,	# event_name may be multiple
												# path may include * (all props, one level) or ** (recurse infinite) or *4 (4 levels deep)
					],
					callback,					# may also be a function
					callback_ctx,
					callback_args
					);
				
					// we should always try to set the path matching as deep as possible
					q_set($obj, 'path_1', 'path_2', ...)
		 
					$ctrl.query = 'Countries',
					$ctrl.binds.country <= $this.countries.selected,
					$ctrl.binds.country <=> $this.countries.selected,
					$ctrl.NoneCaption <= $this.countries.selected ? null : 'Please pick a country'
		 
		 		IN PHP A BIND IS JUST A EQ B
				IN JS A BIND IS MORE !
		
				// some coditional rendering would also be useful later !
		 
				__IN_PHP__ ? ($ctrl.binds.country = $this.countries.selected) :
				qBind(
					q_ctrl($this ?: this),		# control
					q_ctx($_ctx_ ?: null),		# 
					[
						'onchange' => [q_ctrl($this ?: this), 'countries', 'selected']
					],
					function ($args, $sender, $value, $old_value)
					{
						q_set($value, $this, 'binds', 'country');
					},
					null,					# will default to this | current control
					[]						# no args
					
				+ listener(s)
					$ctrl.listener[] = event(s) => callback() + args
				+ 
					

		 */
		
		qBindTemplate('...');
		
		qBindIf();
		
		qBindList(
				$data,
				render_collection_item,
				function ($item, $key)
				{
					// good to filter
					return $item;
				},
				function ($data)
				{
					// use this to order
					return $data;
				}
			);
		
		qBind( ($instance_id = $uniq_prefix.'|'.\QModel::GetNextId()),
				($definition_id = '!!generated_static!!'),
				// also include control class and name/id !
			[
				[[[q_ctrl, $instance_id], 'query'], '=', ['Countries']],
				[[[q_ctrl, $instance_id], 'binds', 'country'], '<=', [[q_this, $instance_id], 'countries', 'selected']],
				[[[q_ctrl, $instance_id], 'binds', 'country'], '<=>', [[q_this, $instance_id], 'countries', 'selected']],
				[[[q_ctrl, $instance_id], 'NoneCaption'], '<=>', function () {}, [[[q_this, $instance_id], 'countries', 'selected']]],
				]);
		
		// @TODO - dom removed => binds removed !!!
		
		// <= T_IS_SMALLER_OR_EQUAL
		// <=> T_SPACESHIP
		// => T_DOUBLE_ARROW
		
		// qvar_dump(token_name(290), token_name(289), token_name(268));
		
		// break it in tokens
		$tokens = token_get_all("<?php ".$binds_str);
		
		
		
		// qvar_dump('x');
	}
	
	public static function GetQCtrlFullClassName($ctrl_short_name)
	{
		if (($sh_n = static::$_ClassNameShortcuts[$ctrl_short_name]))
			return $sh_n;
		else
			return $ctrl_short_name;
	}
	
	public function obj_to_code(bool $pretty_print = false, object $object = null, array $array = null, int $depth = 0)
	{
		$tabs = $pretty_print ? str_repeat("\t", $depth) : '';
		$loop = null;
		if ($object !== null)
		{
			if (get_class($object) !== 'stdClass')
				throw new \Exception('Only standard classes are supported!');
			echo "(object)[";
			$loop = $object;
		}
		else if ($array !== null)
		{
			echo "[";
			$loop = $array;
		}
		else
		{
			echo get_class($this), "::obj_wakeup([";
			$loop = $this::$Obj_To_Code_Props;
		}
		foreach ($loop as $k => $v)
		{
			if (($object !== null) || ($array !== null))
			{
				$prop = $k;
				$val = $v;
			}
			else
			{
				$prop = $k;
				$val = $this->$prop;
				
				if ($val === $v) // default value do not print it
					continue;
			}
			
			echo ($pretty_print ? "\n\t" : ''), $tabs, (is_int($prop) ? $prop : var_export($prop, true)) , ' => ';
			if ($val instanceof QPHPToken)
				$val->obj_to_code($pretty_print, null, null, $depth + 1);
			else if (is_array($val))
				$this->obj_to_code($pretty_print, null, $val, $depth + 1);
			else if (is_object($val))
				$this->obj_to_code($pretty_print, $val, null, $depth + 1);
			else
				var_export($val);
			echo ",";
		}
		if (($object !== null) || ($array !== null))
			echo "]";
		else
			echo "])";
	}
	
	public static function obj_wakeup(array $props)
	{
		$class_n = static::class;
		$obj = new $class_n;
		foreach ($props as $prop => $value)
			$obj->$prop = $value;
		// restore children
		foreach ($obj->children ?: [] as $child)
		{
			if ($child instanceof QPHPToken)
				$child->setParent($obj, null, false);
		}
		return $obj;
	}
	
	public function walk(callable $callback, $child_pos = 0, \QPHPToken $parent = null)
	{
		$continue = $callback($this, $child_pos, $parent);
		if ($continue === false)
			return;
		foreach ($this->children ?: [] as $pos => $child)
		{
			if ($child instanceof QPHPToken)
				$child->walk($callback, $pos, $this);
			else if (is_array($child) || is_string($child))
				$callback($child, $child_pos, $this);
		}
	}
}
