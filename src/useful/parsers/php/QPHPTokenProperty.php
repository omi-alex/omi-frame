<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenProperty
 *
 * @author Alex
 */
class QPHPTokenProperty extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 
											'name' => null, 'static' => null, 'visibility' => null, 
											'pullDocComment' => true];
	/**
	 *
	 * @var boolean
	 */
	public $pullDocComment = true;
	/**
	 * The name of the property
	 *
	 * @var string
	 */
	public $name;
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
	
	// static, (public,private,protected)
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// extract comment, name and properties
		$tok = $tokens[$pos];
		while ($tok)
		{
			if ($tok === ';')
			{
				$tok = $tokens[$pos];
				$this->children[] = $tok;
				$pos++;
				break;
			}
			else if (is_array($tok))
			{
				switch ($tok[0])
				{
					case T_VARIABLE:
						$this->name = substr($tok[1], 1);
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
					case T_STATIC:
						$this->static = true;
						break;
					default:
						break;
				}
			}
			
			$this->children[] = $tok;
			$pos++;
			$tok = $tokens[$pos];
		}
	}
	
	public function setParent(QPHPToken $parent = null, $pullDocComment = null, $add_as_child = true)
	{
		$ret = parent::setParent($parent, $pullDocComment, $add_as_child);
		if ($parent && $this->name)
			$this->closest(".QPHPTokenClass")->setProperty($this);
		return $ret;
	}
	
	public function mergeWithProperty(QPHPTokenProperty $property)
	{
		if ($this->docComment && $property->docComment)
			$this->mergeDocComment($property->docComment);
		
		$this->static = $property->static;
		$this->visibility = $property->visibility;

		$this->children = $property->children ?: array();
		
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPToken)
				$child->setParent($this, false, false);
		}
	}
	
	public function rename($new_name)
	{
		return $this->setName($new_name);
	}
	
	public function setName($new_name)
	{
		if (!$this->children)
			return false;
		
		foreach ($this->children as $k => $tok)
		{
			if (is_array($tok) && ($tok[0] === T_VARIABLE))
			{
				$this->children[$k][1] = ($new_name{0} === "\$") ? $new_name : "\\$".$new_name;
				$this->name = $new_name;
				return true;
			}
		}
		return false;
	}
}
