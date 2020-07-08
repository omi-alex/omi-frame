<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenClass
 *
 * @author Alex
 */
class QPHPTokenClass extends QPHPToken
{
	public static $ClassSubElements = array(T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true, T_FUNCTION => true, T_VAR => true, 
			T_FINAL => true, T_STATIC => true, T_ABSTRACT => true, T_CONST => true);
	
	public static $FuncModifiers = array(T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true, T_FINAL => true, T_STATIC => true, T_ABSTRACT => true, T_CONST => true);
	
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 
											'type' => 'class', 'final' => false, 'abstract' => false, 'className' => null, 
											'extends' => null, 'implements' => null, 'pullDocComment' => true];
	
	/**
	 * The type of the class: class / trait / interface
	 *
	 * @var string
	 */
	public $type = "class"; // trait / interface
	/**
	 * The name of the class
	 *
	 * @var string
	 */
	public $className;
	/**
	 * If the class is final or not
	 *
	 * @var boolean
	 */
	public $final = false;
	/**
	 * If the class is abstract or not
	 *
	 * @var boolean
	 */
	public $abstract = false;
	/**
	 * The extended class
	 *
	 * @var string
	 */
	public $extends;
	/**
	 * The list of implemented interfaces
	 *
	 * @var string[]
	 */
	public $implements;
	/**
	 * The list of methods
	 *
	 * @var QPHPTokenFunction[]
	 */
	public $methods;
	/**
	 * The list of methods
	 *
	 * @var QPHPTokenProperty[]
	 */
	public $properties;
	/**
	 * The list of methods
	 *
	 * @var QPHPTokenClassConst[]
	 */
	public $constants;
	/**
	 *
	 * @var boolean
	 */
	public $pullDocComment = true;

	public function getKeyAsChild()
	{
		return $this->className;
	}
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		$type = is_array($tok) ? $tok[0] : null;

		$in_extends = false;
		$in_implements = false;
		$class_name = false;
		
		$implements_index = 0;
		
		while ($tok)
		{
			$type = is_array($tok) ? $tok[0] : $tok;
			
			$append_only = false;
			
			if (($type === T_CLASS) || ($type === T_INTERFACE) || ($type === T_TRAIT))
			{
				$class_name = true;
				$this->type = ($type === T_CLASS) ? "class" : (($type === T_INTERFACE) ? "interface" : "trait");
			}
			else if ($type === T_FINAL)
				$this->final = true;
			else if ($type === T_ABSTRACT)
				$this->abstract = true;
			else if ($type === T_EXTENDS)
			{
				$in_extends = true;
				$in_implements = false;
			}
			else if ($type === T_IMPLEMENTS)
			{
				$in_extends = false;
				$in_implements = true;
			}
			else if (($type === T_STRING) || ($type === T_NS_SEPARATOR))
			{
				if ($class_name)
				{
					if ($type === T_NS_SEPARATOR)
						throw new Exception("Parse error. There should be no T_NS_SEPARATOR in class name definition");
					$this->className = $tok[1];
					$class_name = false;
				}
				else if ($in_extends)
				{
					if ($this->extends)
						$append_only = true;
					$this->extends .= $tok[1];
				}
				else if ($in_implements)
				{
					if ($this->implements[$implements_index])
						$append_only = true;
					$this->implements[$implements_index] .= $tok[1];
				}
			}
			else if ($in_implements && ($tok === ","))
			{
				// increment implements position
				$implements_index++;
			}
			
			if ($append_only)
			{
				end($this->children);
				$this->children[key($this->children)][1] .= $tok[1];
			}
			else
				$this->children[] = $tok;
			
			$pos++;
			
			$tok = $tokens[$pos];
			if ($tok === '{')
				break;	
		}
		
		if ($this->implements)
			// attach key->value
			$this->implements = array_combine($this->implements, $this->implements);
		
		$tok = $tokens[$pos];
		$last_t_string_pos = false;
		// 2. Property/Method/Const
		while ($tok)
		{
			$type = is_array($tok) ? $tok[0] : null;
			
			if ($tok === '}')
			{
				// we are done with the class
				$this->children[] = $tok;
				$pos++;
				break;
			}
			else if ($type === T_CONST)
			{
				// manage constant
				$child = new QPHPTokenClassConst($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
			}
			else if (self::$ClassSubElements[$type])
			{
				if ($this->inFunction($tokens, $pos))
				{
					// manage function
					$child = new QPHPTokenFunction($this);
					$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
					$child->setParent($this, true);
				}
				else 
				{
					// manage property
					$child = new QPHPTokenProperty($this);
					$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
					$child->setParent($this, true);
				}
			}
			else
			{
				// if NAMESPACED 
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
					
					// var_dump($full_class_idf, $tokens[$pos-1], $tok);
					
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
	
	public function inFunction($tokens, $pos)
	{
		$p = $pos;
		$next = $tokens[$p];
		
		// skip whitespaces & comments
		while ($next && is_array($next) && (($next[0] === T_WHITESPACE) || ($next[0] === T_COMMENT) || ($next[0] === T_DOC_COMMENT) || self::$FuncModifiers[$next[0]]))
			$next = $tokens[++$p];
		
		return T_FUNCTION === (($next === null) ? null : (is_string($next) ? $next : $next[0]));
	}
	
	/**
	 * Sets this instace to use the specified trait
	 * If $remove_duplicates_in_class then all the methods and properties that 
	 * exists in both this class and in the trait will be removed from this class
	 * 
	 * @param QPHPTokenClass $trait
	 * @param boolean $remove_duplicates_in_class
	 * 
	 */
	public function useTrait(QPHPTokenClass $trait, $remove_duplicates_in_class = false)
	{
		if ($trait->type !== "trait")
			throw new Exception("Input parameter is not a trait");
		if (!$remove_duplicates_in_class)
			throw new Exception("Not implemented at the moment");
		
		// TO DO: take extra information from trait's doc comment: @extends, @final, @abstract, @implements
		// then run normalize !
		
		$this->setUseTrait($trait->className);
		
		if ($this->docComment && $trait->docComment)
			$this->mergeDocComment($trait->docComment);
		else if ($trait->docComment)
		{
			$this->docComment = $trait->docComment;
			array_unshift($this->children, $trait->docComment);
		}
		
		// constants
		if ($trait->constants)
		{
			foreach ($trait->constants as $name => $constant)
			{
				// we will need to add it
				if (($self_const = $this->constants[$name]))
				{
					$this->removeChild($self_const);
					unset($this->constants[$name]);
				}
			}
		}
		
		// properties
		if ($trait->properties)
		{
			foreach ($trait->properties as $name => $property)
			{
				// we will need to add it
				if (($self_prop = $this->properties[$name]))
				{
					$this->removeChild($self_prop);
					unset($this->properties[$name]);
				}
			}
		}
		
		// methods
		if ($trait->methods)
		{
			foreach ($trait->methods as $name => $method)
			{
				// we will need to add it
				if (($self_meth = $this->methods[$name]))
				{
					$this->removeChild($self_meth);
					unset($this->methods[$name]);
				}
			}
		}
		
		// TO DO run normalize
		// $this->normalizeTokensFromAttributes();
	}
	
	/**
	 * $class will overwrite THIS
	 * 
	 * @param QPHPTokenClass $class
	 * @param boolean $append_mode
	 * @throws Exception
	 */
	public function mergeWithClass(QPHPTokenClass $class, $append_mode = true)
	{
		// manage doc comment
		if ($this->docComment && $class->docComment)
			$this->mergeDocComment($class->docComment);
		else if ($class->docComment)
		{
			$this->docComment = $class->docComment;
			// $this->children->prepend($class->docComment);
			array_unshift($this->children, $class->docComment);
		}
		
		if ($this->type !== $class->type)
			throw new Exception("You can not change the type from `{$this->type}` to `{$class->type}`");
			
		$this->className = $class->className;
			
		// final, abstract
		$this->final = $append_mode ? ($this->final || $class->final) : $class->final;
		$this->abstract = $append_mode ? ($this->abstract || $class->abstract) : $class->abstract;
		
		// extends
		$this->extends = $append_mode ? ($class->extends ?: $this->extends) : $class->extends;
		
		/*
		
		$this->normalizeTokensFromAttributes();
		echo "<hr/>";
		echo "<textarea>{$this}</textarea>";
		echo "<hr/>";
		*/
		
		// implements
		$this->implements = $append_mode ? ($this->implements ? ($class->implements ? array_merge($this->implements, $class->implements) : $this->implements) : $class->implements) : $class->implements;
		
		// constants
		if ($this->constants)
		{
			$matched = array();
			foreach ($this->constants as $name => $constant)
			{
				if (($mc = $class->constants[$name]) !== null)
				{
					$matched[$name] = true;
					$constant->mergeWithConstant($mc);
				}
			}
		}
		
		if ($class->constants)
		{
			foreach ($class->constants as $name => $constant)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->constants[$name] = $constant;
					$this->smartAppend($constant);
				}
			}
		}
		
		// properties
		if ($this->properties)
		{
			$matched = array();
			foreach ($this->properties as $name => $property)
			{
				if (($mc = $class->properties[$name]) !== null)
				{
					$matched[$name] = true;
					$property->mergeWithProperty($mc);
				}
			}
		}
		if ($class->properties)
		{
			foreach ($class->properties as $name => $property)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->properties[$name] = $property;
					$this->smartAppend($property);
				}
			}
		}
		
		// methods
		if ($this->methods)
		{
			$matched = array();
			foreach ($this->methods as $name => $method)
			{
				if (($mc = $class->methods[$name]) !== null)
				{
					$matched[$name] = true;
					$method->mergeWithMethod($mc);
				}
			}
		}
		
		if ($class->methods)
		{
			foreach ($class->methods as $name => $method)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->methods[$name] = $method;
					$this->smartAppend($method);
				}
			}
		}
		
		// run normalize
		$this->normalizeTokensFromAttributes();
	}
	
	public function smartAppend(QPHPToken $element)
	{
		$is_prop = ($element instanceof QPHPTokenProperty);
		$is_meth = (!$is_prop) && ($element instanceof QPHPTokenFunction);
		$is_const = (!$is_prop) && (!$is_meth) && ($element instanceof QPHPTokenClassConst);
		
		foreach ($this->children as $k => $child)
		{
			if ($is_const && ($child instanceof QPHPTokenProperty))
			{
				// $this->children->splice($k, 0, array($element, "\n\t"));
				array_splice($this->children, $k, 0, array($element, "\n\t"));
				$element->setParent($this, false, false);
				break;
			}
			else if (($is_prop || $is_const) && ($child instanceof QPHPTokenFunction))
			{
				// $this->children->splice($k, 0, array($element, "\n\t"));
				array_splice($this->children, $k, 0, array($element, "\n\t"));
				$element->setParent($this, false, false);
				break;
			}
			else if (($is_prop || $is_const || $is_meth) && (($child === "}") || (is_array($child) && ($child[1] === "}"))))
			{
				// $this->children->splice($k, 0, array("\n\t", $element, "\n"));
				array_splice($this->children, $k, 0, array("\n\t", $element, "\n"));
				$element->setParent($this, false, false);
				break;
			}
		}
	}
	
	public function normalizeTokensFromAttributes()
	{
		// call parent (will manage doc comments)
		parent::normalizeTokensFromAttributes();
		
		// className, final, abstract, extends, implements
		
		// loop up to {
		$pos = 0;
		$tok = reset($this->children);
		
		$pos_final = false;
		$pos_abstract = false;
		$pos_type = false;
		$pos_className = false;
		$pos_extends = false;
		$pos_extends_name = false;
		$pos_implements = false;
		$pos_implements_names = array();
		
		$new = array();
		
		$next_is_name = false;
		$next_is_extends = false;
		$next_is_implements = false;
		
		$extends_was_set = false;
		
		$first_iface = true;
		
		while ($tok)
		{
			$type = is_array($tok) ? $tok[0] : null;
			if (($tok === "{") || (is_array($tok) && ($tok[1] === "{")))
			{
				// if there are extra whitespaces we will need to move them a bit
				$append_to_new = array();
				while (($wsp_new = end($new)) && is_array($wsp_new) && ($wsp_new[0] === T_WHITESPACE))
				{
					$append_to_new[] = $wsp_new;
					array_pop($new);
				}
				
				// extends & implements
				if ($this->extends && ($pos_extends === false))
				{
					$new[] = array(T_WHITESPACE, " ");
					$new[] = array(T_EXTENDS, "extends");
				}
				if ($this->extends && ($pos_extends_name === false))
				{
					$new[] = array(T_WHITESPACE, " ");
					$new[] = array(T_STRING, $this->extends);
				}
				if ($this->implements)
				{
					if ($pos_implements === false)
					{
						$new[] = array(T_WHITESPACE, " ");
						$new[] = array(T_IMPLEMENTS, "implements");
						$first_iface = true;
					}
					
					foreach ($this->implements as $impl_iface)
					{
						if (!isset($pos_implements_names[$impl_iface]))
						{
							$new[] = $first_iface ? array(T_WHITESPACE, " ") : ",";
							$new[] = array(T_STRING, $impl_iface);
							if ($first_iface)
								$first_iface = false;
						}
					}
				}
				
				if ($append_to_new)
				{
					foreach ($append_to_new as $v)
					{
						$new[] = $v;
					}
				}
				
				break;
			}
			else if (($type === T_CLASS) || ($type === T_INTERFACE) || ($type === T_TRAIT))
			{
				// make sure for final && abstract
				if ($this->final && ($pos_final === false))
				{
					$new[] = array(T_FINAL, "final");
					$new[] = array(T_WHITESPACE, " ");
				}
				if ($this->abstract && ($pos_abstract === false))
				{
					$new[] = array(T_ABSTRACT, "abstract");
					$new[] = array(T_WHITESPACE, " ");
				}
				
				$new[] = array(($this->type === "class") ? T_CLASS : (($this->type === "interface") ? T_INTERFACE : T_TRAIT), $this->type);
				
				$next_is_name = true;
				$pos_type = $pos;
			}
			else if ($next_is_name && (($type === T_STRING) || ($type === T_NS_SEPARATOR)))
			{
				$pos_className = $pos;
				$next_is_name = false;
				
				$new[] = array(T_STRING, $this->className);
			}
			else if ($type === T_FINAL)
			{
				$pos_final = $pos;
				$new[] = $tok;
			}
			else if ($type === T_ABSTRACT)
			{
				if ($this->type != "trait")
				{
					$pos_abstract = $pos;
					$new[] = $tok;
				}
			}
			else if ($type === T_EXTENDS)
			{
				$pos_extends = $pos;
				$next_is_extends = true;
				if ($this->extends)
					$new[] = $tok;
			}
			else if ($next_is_extends && (($type === T_STRING) || ($type === T_NS_SEPARATOR)))
			{
				$pos_extends_name = $pos;
				$next_is_extends = false;
				if ($this->extends && (!$extends_was_set))
				{
					$new[] = array(T_STRING, $this->extends);
					$extends_was_set = true;
				}
			}
			else if ($type === T_IMPLEMENTS)
			{
				// make sure we setup extends before implements !!!
				if ($this->extends && ($extends_was_set === false))
				{
					// in case we did not had EXTENDS but we need to extend something
					$new[] = array(T_EXTENDS, "extends");
					$new[] = array(T_WHITESPACE, " ");
					$new[] = array(T_STRING, $this->extends);
					$new[] = array(T_WHITESPACE, " ");
					
					$extends_was_set = true;
					
					// make sure we don't write it again
					$pos_extends = $pos;
					$pos_extends_name = $pos;
				}
				
				$pos_implements = $pos;
				$next_is_implements = true;
				if (!(empty($this->implements)))
					$new[] = $tok;
			}
			else if ($next_is_implements && (($type === T_STRING) || ($type === T_NS_SEPARATOR)))
			{
				$pos_implements_names[$tok[1]] = $pos;
				if ($this->implements && $this->implements[$tok[1]])
				{
					$first_iface = false;
					$new[] = $tok;
				}
			}
			else
			{
				// quick fix by Mihai - to rewrite when have more time
				// if we have a comma separator between interfaces and we have a trait (no implements) then skip the comma
				$isBetweenInterfacesComma = ($next_is_implements && (is_string($tok) && ($tok === ",")));
				if (!($isBetweenInterfacesComma && !$this->implements))
					$new[] = $tok;
			}
			
			//$__ptok = $tok;
			$tok = next($this->children);
			$pos++;
		}

		// $this->children->splice(0, $pos, $new);
		array_splice($this->children, 0, $pos, $new);
	}
	
	public function setMethod(QPHPTokenFunction $method)
	{
		if (!$this->methods)
			$this->methods = array();
		$this->methods[$method->name] = $method;
	}
	
	public function setProperty(QPHPTokenProperty $property)
	{
		if (!$this->properties)
			$this->properties = array();
		$this->properties[$property->name] = $property;
	}
	
	public function setConstant(QPHPTokenClassConst $contant)
	{
		if (!$this->constants)
			$this->constants = array();
		$this->constants[$contant->name] = $contant;
	}

	public function getAppendPos()
	{
		if (!$this->children)
			return false;
		$end = count($this->children) - 1;
		
		do
		{
			$tok = $this->children[$end];
			if (($tok === "}") || (is_array($tok) && ($tok[1] === "}")))
				return $end;
			$end--;
		}
		while ($end > 0);
		return false;
	}

	public function getPrependPos()
	{
		// first { from the top
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
		
		return false;
	}
	
	public function removeUseTrait($trait_name)
	{
		return $this->setUseTrait($trait_name, true);
	}
	
	/**
	 * Transforms a class to a trait. If a QPHPTokenClass is specified, it will remove 
	 * any properties, methods or constants from itself that are present on the specified argument.
	 * This is mainly used for patching. QCodeSync::resyncPatch()
	 * 
	 * @param QPHPTokenClass $subtract_class_elements
	 */
	public function transformToTrait($new_name = null, QPHPTokenClass $subtract_class_elements = null, $remove_constants = false)
	{
		if ($this->constants)
		{
			if (!$remove_constants)
			{
				if (!$subtract_class_elements)
					throw new Exception("We can not transform to a trait a class that has constants");
				else if (array_keys($this->constants) !== array_keys($subtract_class_elements->constants))
					throw new Exception("Not all constants are redefined in the class that replaces the one thet is transformed in a trait. So contants will be lost. You must explicitly drop constants.");
			}
			// constants, there are no constants in a trait
			foreach ($this->constants as $self_const)
				$this->removeChild($self_const);
			unset($this->constants);
		}
		
		$has_changes = ($new_name !== $this->className) || ($this->type !== "trait");

		if ($has_changes)
		{
			if ($new_name !== null)
				$this->className = $new_name;
			$this->setType("trait", false);

			$this->normalizeTokensFromAttributes();
		}

		if ($subtract_class_elements)
		{
			$subtract = $subtract_class_elements;
			
			/*
			if ($subtract->constants)
			{
				foreach ($subtract->constants as $name => $constant)
				{
					// we will need to add it
					if (($self_const = $this->constants[$name]))
					{
						$this->removeChild($self_const);
						unset($this->constants[$name]);
					}
				}
			}
			 */

			// properties
			if ($subtract->properties)
			{
				foreach ($subtract->properties as $name => $property)
				{
					// we will need to add it
					if (($self_prop = $this->properties[$name]))
					{
						$this->removeChild($self_prop);
						unset($this->properties[$name]);
					}
				}
			}

			// methods
			if ($subtract->methods)
			{
				foreach ($subtract->methods as $name => $method)
				{
					// we will need to add it
					if (($self_meth = $this->methods[$name]))
					{
						$this->removeChild($self_meth);
						unset($this->methods[$name]);
					}
				}
			}
		}
	}
	
	/**
	 * Changes the type of this element. The type can be: interface, class, trait
	 * 
	 * @param string $new_type
	 * @param boolean $normalize
	 */
	public function setType($new_type, $normalize = true)
	{
		$this->type = $new_type;
		if ($new_type === "trait")
		{
			// we need to drop: extends, implements & use self
			$this->final = false;
			$this->abstract = false;
			$this->extends = null;
			$this->implements = null;

			$child = reset($this->children);
			while ($child)
			{
				$pos = key($this->children);
				if (is_array($child) && ($child[0] === T_USE))
				{
					$use_traits = 0;
					$has_own_name_as_trait = false;
					$all_use_traits = [];
					while ($child && ($child !== ";"))
					{
						if (is_array($child) && (($child[0] === T_STRING) || ($child[0] === T_NS_SEPARATOR)))
						{
							$use_trait_name = $child[1];
							$use_traits++;
							if ($use_trait_name === $this->className)
								$has_own_name_as_trait = true;
							else
								$all_use_traits[$use_trait_name] = $use_trait_name;
						}
						$child = next($this->children);
					}
					if (empty($all_use_traits))
					{
						// nothing, remove traits for good
						array_splice($this->children, $pos, (key($this->children) - $pos) + 1);
					}
					else
					{
						$new_traits = [[T_USE, "use"], [T_WHITESPACE, " "]];
						$ut_pos = 0;
						foreach ($all_use_traits as $ut)
						{
							if ($ut_pos)
								$new_traits[] = ",";
							$new_traits[] = [T_STRING, $ut];
							$ut_pos++;
						}
						$new_traits[] = ";";
						array_splice($this->children, $pos, (key($this->children) - $pos) + 1, []);
					}
					
					break;
				}
				
				$child = next($this->children);
			}
		}
		if ($normalize)
			$this->normalizeTokensFromAttributes();
	}
	
	/**
	 * Changes the extends of this element. 
	 * 
	 * @param string $class_to_extend
	 * @param boolean $overwrite
	 */
	public function setExtends($class_to_extend, $overwrite = true)
	{
		if (($overwrite || (!$this->extends)) && ($this->extends !== $class_to_extend))
		{
			$this->extends = $class_to_extend;
			$this->normalizeTokensFromAttributes();
		}
	}
	
	/**
	 * Sets the use clause in the class to include the specified trait
	 * 
	 * @param string $trait_name
	 * @throws Exception
	 */
	public function setUseTrait($trait_name, $unset = false)
	{
		$pp_pos = $prepend_pos = $this->getPrependPos();
		if (!$prepend_pos)
			throw new Exception("Bad tokens in class");
		
		$use_pos = false;
		
		// find the position where we need to apply 'use' or upadate 'use'	
		while (($tok = $this->children[++$prepend_pos]))
		{
			if (is_object($tok))
				break;
			else if (is_array($tok) && $tok[0] === T_USE)
			{
				// we already have use, we will need to make sure we have this trait
				$use_pos = $prepend_pos;
				break;
			}
		}
		
		if ($unset && ($use_pos === false))
			// there is no trait set
			return false;
		
		if ($use_pos !== false)
		{
			$trait_use_pos = $use_pos;
			$traits_incl = array();
			while (($tok = $this->children[++$use_pos]))
			{
				if (($tok === ";") || ($tok === "{"))
				{
					if (!$traits_incl[$trait_name])
					{
						if ($unset)
						{
							// we are ok, there is no trait with that name
							return false;
						}
						else
						{
							array_splice($this->children, $use_pos, 0, 
													array(
														",", 
														array(T_WHITESPACE, ' ', 4),
														array(T_STRING, $trait_name, 4)));
						}
					}
					else if ($unset)
					{
						if (next($traits_incl))
						{
							// we have more than one
							$replacement = [[T_USE, "use", 4],
											[T_WHITESPACE, ' ', 4]];
							$ti_pos = 0;
							foreach ($traits_incl as $ti)
							{
								if ($ti_pos++)
									$replacement[] = ",";
								$replacement[] = [T_STRING, $ti, 4];
							}
							array_splice($this->children, $trait_use_pos, ($use_pos - $trait_use_pos + 1), $replacement);
						}
						else
						{
							// remove the entire use trait block
							array_splice($this->children, $trait_use_pos, ($use_pos - $trait_use_pos + 1));
						}
					}
						
					break;
				}
				else if (is_array($tok) && ($tok[0] === T_STRING))
				{
					$traits_incl[$tok[1]] = $tok[1];
				}
			}
		}
		else
		{
			array_splice($this->children, $pp_pos, 0, 
				array(
						array(T_WHITESPACE, "\n\t", 4),
						array(T_USE, "use", 4),
						array(T_WHITESPACE, ' ', 4),
						array(T_STRING, $trait_name, 4),
						";",
						array(T_WHITESPACE, "\n", 4)
						));
		}
	}
	
	/**
	 * Appends the methods and properties of a trait to this object if they are not found
	 * 
	 * @param QPHPTokenClass $trait
	 */
	public function appendTrait(QPHPTokenClass $trait)
	{
		// properties
		if ($trait->properties)
		{
			foreach ($trait->properties as $name => $property)
			{
				if (!$this->properties[$name])
				{
					// we will need to add it
					$this->properties[$name] = $property;
					$this->smartAppend($property);
				}
			}
		}
		
		// methods
		if ($trait->methods)
		{
			foreach ($trait->methods as $name => $method)
			{
				if (!$this->methods[$name])
				{
					// we will need to add it
					$this->methods[$name] = $method;
					if (!$method)
					{
						# qvar_dump($this);
						//echo "<textarea>{$this}</textarea>";
						//qvardump($name, $this->className);
						throw new \Exception("Method not found [{$this->className}::{$name}]");
					}
					$this->smartAppend($method);
				}
			}
		}
	}
	
	/**
	 * Merges constants 
	 * 
	 * @param QPHPTokenClassConst[] $constants
	 * @param boolean $overwrite
	 */
	public function mergeConstants($constants, $overwrite = true)
	{
		// constants
		if ($overwrite && $this->constants)
		{
			$matched = array();
			foreach ($this->constants as $name => $constant)
			{
				if (($mc = $constants[$name]) !== null)
				{
					$matched[$name] = true;
					$constant->mergeWithConstant($mc);
				}
			}
		}
		
		if ($constants)
		{
			foreach ($constants as $name => $constant)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->constants[$name] = $constant;
					$this->smartAppend($constant);
				}
			}
		}
	}
	
	/**
	 * Merges methods 
	 * 
	 * @param QPHPTokenFunction[] $methods
	 * @param boolean $overwrite
	 */
	public function mergeMethods($methods, $overwrite = true)
	{
		// methods
		if ($overwrite && $this->methods)
		{
			$matched = array();
			foreach ($this->methods as $name => $method)
			{
				if (($mc = $methods[$name]) !== null)
				{
					$matched[$name] = true;
					$method->mergeWithMethod($mc);
				}
			}
		}
		
		if ($methods)
		{
			foreach ($methods as $name => $method)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->methods[$name] = $method;
					$this->smartAppend($method);
				}
			}
		}
	}
	
	/**
	 * Sets a method from a string
	 * 
	 * @param string $render_func_name
	 * @param string $render_code
	 * @param boolean $parse_string
	 * @return \QPHPTokenFunction
	 * @throws Exception
	 */
	public function setMethodFromString($render_func_name, $render_code, $parse_string = true)
	{
		if ($parse_string)
			throw new Exception("not impemented");
		else
		{
			$method = $this->methods[$render_func_name];
			if (!$method)
			{
				$method = new QPHPTokenFunction();
				$method->name = $render_func_name;
				$this->methods[$render_func_name] = $method;
				$this->smartAppend($method);
			}
			$method->children = [$render_code];
			
			return $method;
		}
	}
	
	public function setPropertyFromString($property_name, $property_code, $parse_string = true)
	{
		if ($parse_string)
			throw new Exception("not impemented");
		else
		{
			$property = $this->properties[$property_name];
			if (!$property)
			{
				$property = new QPHPTokenProperty();
				$property->name = $property_name;
				$this->properties[$property_name] = $property;
				$this->smartAppend($property);
			}
			$property->children = [$property_code];
			
			return $property;
		}
	}
	
	/**
	 * Merges properties 
	 * 
	 * @param QPHPTokenProperty[] $properties
	 * @param boolean $overwrite
	 */
	public function mergeProperties($properties, $overwrite = true)
	{
		// properties
		if ($overwrite && $this->properties)
		{
			$matched = array();
			foreach ($this->properties as $name => $property)
			{
				if (($mc = $properties[$name]) !== null)
				{
					$matched[$name] = true;
					$properties->mergeWithProperty($mc);
				}
			}
		}
		
		if ($properties)
		{
			foreach ($properties as $name => $property)
			{
				if (!$matched[$name])
				{
					// we will need to add it
					$this->properties[$name] = $property;
					$this->smartAppend($property);
				}
			}
		}
	}
	
	/**
	 * Sets the implementing interfaces
	 * 
	 * @param string[]|string $implements
	 */
	public function mergeImplements($implements, $exclusive = false)
	{
		if (is_array($implements))
			// make sure it's key/value
			$implements = array_combine($implements, $implements);
		else
			$implements = array($implements => $implements);
		
		if ($exclusive)
			$this->implements = $implements;
		else
		{
			foreach ($implements as $iface)
				$this->implements[$iface] = $iface;
		}
		
		$this->normalizeTokensFromAttributes();
	}
	
	public function renamePropertyOrMethod($old_name, $new_name)
	{
		if ($old_name{0} === "\$")
		{
			$prop = $this->properties[substr($old_name, 1)];
			if ($prop)
			{
				$prop->rename($new_name);
				$this->properties[$old_name] = null;
				$this->properties[$new_name] = $prop;
			}
			else 
				throw new Exception("Unable to rename property '{$old_name}' to '{$new_name}'. Invalid property in class ".$this->className);
		}
		else
		{
			// we assume method
			$meth = $this->methods[$old_name];
			if ($meth)
			{
				$meth->rename($new_name);
				$this->methods[$old_name] = null;
				$this->methods[$new_name] = $meth;
			}
			else 
			{
				# qvardump($this);
				throw new Exception("Unable to rename method '{$old_name}' to '{$new_name}'. Invalid method in class ".
							$this->className." located at ".$this->getRoot()->filename);
			}
		}
	}
	
	public function removePropertyOrMethod($item_name)
	{
		if ($item_name{0} === "\$")
		{
			$prop = $this->properties[substr($item_name, 1)];
			if ($prop)
			{
				$this->removeChild($prop);
				unset($this->properties[substr($item_name, 1)]);
			}
		}
		else
		{
			// we assume method
			$meth = $this->methods[$item_name];
			if ($meth)
			{
				$this->removeChild($meth);
				unset($this->methods[$item_name]);
			}
		}
	}
	
	/**
	 * Sets the use clause in the class to include the specified trait
	 * 
	 * @param string $trait_name
	 * @throws Exception
	 */
	public function setUseTrait_2($trait_name, bool $unset = false)
	{
		$pp_pos = $prepend_pos = $this->getPrependPos();
		if (!$prepend_pos)
			throw new Exception("Bad tokens in class");
		
		$use_pos = false;
		
		// find the position where we need to apply 'use' or upadate 'use'	
		while (($tok = $this->children[++$prepend_pos]))
		{
			if (is_object($tok))
				break;
			else if (is_array($tok) && $tok[0] === T_USE)
			{
				// we already have use, we will need to make sure we have this trait
				$use_pos = $prepend_pos;
				break;
			}
		}
		
		if ($use_pos === false)
			// there is no trait set
			return false;
		
		$i = $use_pos + 1;
		$traits = [];
		
		$inside_bra = false;
		$new_trait_name = null;
		$new_trait_toks = [];
		
		while (($token = $this->children[$i]))
		{
			if ($token === '}')
			{
				// this is the end
				$new_trait_toks[] = $token;
				$traits[$new_trait_name] = $new_trait_toks;
				$inside_bra = false;
				break;
			}
			else if ($inside_bra)
			{
				// continue
				$new_trait_toks[] = $token;
			}
			else if ($token === ';')
			{
				if ($new_trait_name)
					$traits[$new_trait_name] = $new_trait_toks;
				break;
			}
			else if ($token === ',')
			{
				if ($new_trait_name)
					$traits[$new_trait_name] = $new_trait_toks;
				$new_trait_name = null;
				$new_trait_toks = [];
			}
			else if (is_array($token) && (($token[0] === T_WHITESPACE) || ($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT)))
			{
				$new_trait_toks[] = $token;
			}
			else if ($token instanceof \QPHPToken)
			{
				qvar_dump("AAA", $token);
				throw new \Exception('Unexpected object in trait use declaration.');
			}
			else if (is_array($token))
			{
				if (($token[0] === T_STRING) || ($token[0] === T_NS_SEPARATOR))
				{
					$new_trait_name .= $token[1];
					$new_trait_toks[] = $token;
				}
				else
				{
					qvar_dumpk($token, token_name($token[0]));
					throw new \Exception('you were expecting ....');
				}
			}
			else if ($token === '{')
			{
				// expanded info on the TRAIT 
				$inside_bra = true;
				if ($new_trait_name)
				{
					$traits[$new_trait_name] = $new_trait_toks;
					$new_trait_name = "@insteadof";
					$new_trait_toks = [];
					$new_trait_toks[] = $token;
				}
			}
			else
			{
				qvar_dumpk("BBB", $this->getRoot()->filename, $token, array_slice($this->children, $use_pos, $i));
				throw new \Exception('Unexpected object in trait use declaration.');
			}
			
			$i++;
		}
		
		// if (($this->className === 'Endpoint') || ($this->className === 'Number'))
		{
			$saved = $traits;
			if ($unset)
				unset($traits[$trait_name]);
			else
				$traits[$trait_name] = $trait_name;
			
			if (empty($traits))
			{
				// no more traits
				// qvar_dumpk('NO TRAITS ! first -> last', $this->children[$use_pos], $this->children[$i], array_slice($this->children, $use_pos - 2, $i - $use_pos + 4));
				array_splice($this->children, $use_pos, $i - $use_pos + 1);
				// qvar_dumpk("AFTER SPLICE !", array_slice($this->children, $use_pos - 2, $i - $use_pos + 4));
			}
			else
			{
				// no more traits
				// qvar_dumpk('NEW SET TRAITS ! first -> last', $this->children[$use_pos], $this->children[$i], array_slice($this->children, $use_pos - 2, $i - $use_pos + 4));
				$replacement = [];
				foreach ($traits as $t_toks)
					foreach ($t_toks as $t_tok)
						$replacement[] = $t_tok;
				array_splice($this->children, $use_pos + 1, $i - $use_pos - 1, $replacement);
				// qvar_dumpk("AFTER SPLICE !", array_slice($this->children, $use_pos - 2, $i - $use_pos + 4));
			}
			
			
		}
	}
	
	public function setClassName(string $new_name)
	{
		$after_class_decl = false;
		foreach ($this->children as &$child)
		{
			if ($child instanceof \QPHPToken)
				continue;
			if (is_array($child))
			{
				if (($child[0] === T_CLASS) || ($child[0] === T_INTERFACE) || ($child[0] === T_TRAIT))
					$after_class_decl = true;
				else if (($child[0] === T_STRING) && $after_class_decl)
				{
					$this->className = $new_name;
					$child[1] = $new_name;
					return $child[1];
				}
			}
		}
	}
	
	public function getTraits()
	{
		$pp_pos = $prepend_pos = $this->getPrependPos();
		if (!$prepend_pos)
			throw new Exception("Bad tokens in class");
		
		$use_pos = false;
		
		$uses_list = [];
		$inside_use = false;
		
		$ns = $this->getRoot()->getNamespace();
		
		// find the position where we need to apply 'use' or upadate 'use'	
		while (($tok = $this->children[++$prepend_pos]))
		{
			$type = is_array($tok) ? $tok[0] : $tok;
			if (is_object($tok) || ($inside_use && (($type === '{') || ($type === ';'))))
				break;
			if ($type === T_USE)
			{
				// we already have use, we will need to make sure we have this trait
				$use_pos = $prepend_pos;
				$inside_use = true;
			}
			else if (($type === T_STRING) || ($type === T_NS_SEPARATOR))
				$uses_list[\QPHPToken::ApplyNamespaceToName($tok[1], $ns)] = $tok[1];
		}
		
		return $uses_list;
	}
	
	public function find_class_element(string $name, string $class_name)
	{
		foreach ($this->children ?: [] as $pos => $child)
		{
			if (is_object($child) && (get_class($child) === $class_name) && (($name === '') || ($child->name === $name)))
				return [$child, $pos];
		}
		return null;
	}
		
}
