<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenFunction
 *
 * @author Alex
 */
class QPHPTokenFunction extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 
										'name' => null, 'arguments' => null, 
										'static' => null, 'visibility' => null, 'abstract' => null, 'final' => null, 
										'pullDocComment' => true];
	
	/**
	 * The name of the function
	 *
	 * @var string
	 */
	public $name;
	/**
	 *
	 * @var boolean
	 */
	public $pullDocComment = true;
	/**
	 *
	 * @var mixed[]
	 */
	public $arguments;
	/**
	 *
	 * @var boolean 
	 */
	public $static;
	/**
	 * The visibility of the property: public,private,protected
	 *
	 * @var string
	 */
	public $visibility;
	/**
	 * True if the method is abstract
	 *
	 * @var boolean
	 */
	public $abstract;
	/**
	 * True if the method is final
	 *
	 * @var boolean
	 */
	public $final;
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// $type = is_array($tok) ? $tok[0] : null;
		
		// step 1. : extract all modifiers
		// step 2. : extarct parameters
		
		// $after_func = (is_array($tok) && ($tok[1] === T_FUNCTION)) ? true : false;
		$after_func = false;
		$in_params = false;
		
		while ($tok && ($tok !== '{') && ($tok !== ';'))
		{
			$is_arr = is_array($tok);
			if ($after_func)
			{
				if ($is_arr && ($tok[0] === T_STRING))
				{
					$this->name = $tok[1];
					$after_func = false;
				}
				else if (($tok === "(") || ($is_arr && ($tok[1] === "(")))
				{
					// end for anonym functions
					$after_func = false;
				}
			}
			
			if (($tok === "(") || ($is_arr && ($tok[1] === "(")))
				$in_params = true;
			else if (($tok === ")") || ($is_arr && ($tok[1] === ")")))
				$in_params = false;
			else if ($in_params)
				$this->arguments[] = $tok;
			
			if ($is_arr)
			{
				switch ($tok[0])
				{
					case T_FUNCTION:
						$after_func = true;
						break;
					case T_PUBLIC:
						$this->visibility = "public";
						break;
					case T_PRIVATE:
						$this->visibility = "private";
						break;
					case T_PROTECTED:
						$this->visibility = "protected";
						break;
					case T_ABSTRACT:
						$this->abstract = true;
						break;
					case T_FINAL:
						$this->final = true;
						break;
					case T_STATIC:
						$this->static = true;
						break;
					default:
						break;
				}
			}

			$this->children[] = $tok;
			$tok = $tokens[++$pos];
		}
		
		// '{' - is part of the code block
		if ($tok === '{')
		{
			$code = new QPHPTokenCode($this);
			$code->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
			$code->setParent($this);
		}
		else if ($tok === ';')
		{
			$this->children[] = $tok;
			$pos++;
		}
		// when the code ends we are done
	}
	
	public function rename($new_name)
	{
		return $this->setName($new_name);
	}
	
	public function setName($new_name)
	{
		if (!$this->children)
			return false;
		
		$after_func = false;
		
		foreach ($this->children as $k => $tok)
		{
			$is_arr = is_array($tok);
			if ($after_func)
			{
				if ($is_arr && ($tok[0] === T_STRING))
				{
					$this->children[$k][1] = $new_name;
					$this->name = $new_name;
					return true;
				}
			}
			if ($is_arr)
			{
				switch ($tok[0])
				{
					case T_FUNCTION:
						$after_func = true;
						break;
					default:
						break;
				}
			}
		}
		
		return false;
	}
	
	public function getAppendPos()
	{
		$code = $this->findFirst(".QPHPTokenCode");
		return $code ? $code->getAppendPos() : false;
	}
	
	public function getFunctionCode()
	{
		return $this->findFirst(".QPHPTokenCode");
	}

	public function getPrependPos()
	{
		$code = $this->findFirst(".QPHPTokenCode");
		return $code ? $code->getPrependPos() : false;
		// first { from the top
		/*
		if (!$this->children)
			return false;
		$pos = 0;
		$tok = $this->children[$pos];
		
		while ($tok)
		{
			if (($tok === "{") || (is_array($tok) && ($tok[1] === "{")))
				return $pos + 1;
			$pos++;
			$tok = $this->children[$pos];
		}
		while ($tok);
		
		return false;
		*/
	}
	
	public function getArgumentsPlain()
	{
		$ret = array();
		foreach ($this->arguments as $tok)
		{
			if ((is_array($tok) && ($tok[0] === T_VARIABLE)) || ($tok === ","))
				$ret[] = $tok;
		}
		
		return $ret;
	}
	
	public function getArgumentsName()
	{
		$ret = array();
		foreach ($this->arguments as $tok)
		{
			if (is_array($tok) && ($tok[0] === T_VARIABLE))
				$ret[] = substr($tok[1], 1);
		}
		
		return $ret;
	}
	
	public function setParent(QPHPToken $parent = null, $pullDocComment = null, $add_as_child = true)
	{
		$ret = parent::setParent($parent, $pullDocComment, $add_as_child);
		if ($parent && $this->name && ($parent instanceof QPHPTokenClass))
			$parent->setMethod($this);
		return $ret;
	}
	
	public function mergeWithMethod(QPHPTokenFunction $method)
	{
		if ($this->docComment && $method->docComment)
			$this->mergeDocComment($method->docComment);
		
		$this->static = $method->static;
		$this->visibility = $method->visibility;
		$this->abstract = $method->abstract;
		$this->final = $method->final;

		$this->children = $method->children ?: array();
		
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPToken)
				$child->setParent($this, false, false);
		}
	}
	
}
