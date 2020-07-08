<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of QPHPTokenClassConst
 *
 * @author Alex
 */
class QPHPTokenClassConst extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 'name' => null, 'pullDocComment' => true];
	
	/**
	 *
	 * @var boolean
	 */
	public $pullDocComment = true;
	/**
	 * The name of the constant
	 *
	 * @var string
	 */
	public $name;
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		while ($tok)
		{
			$this->children[] = $tok;
			
			if ((!$this->name) && is_array($tok) && ($tok[0] === T_STRING))
				$this->name = $tok[1];
			
			$pos++;
			if ($tok === ';')
			{
				$tok = $tokens[$pos];
				break;
			}
			$tok = $tokens[$pos];
		}
	}
	
	public function setParent(QPHPToken $parent = null, $pullDocComment = null, $add_as_child = true)
	{
		$ret = parent::setParent($parent, $pullDocComment, $add_as_child);
		if ($parent && $this->name)
			$this->closest(".QPHPTokenClass")->setConstant($this);
		return $ret;
	}
	
	public function mergeWithConstant(QPHPTokenClassConst $constant)
	{
		if ($this->docComment && $constant->docComment)
			$this->mergeDocComment($constant->docComment);
		
		$this->children = $constant->children ?: array();
		
		foreach ($this->children as $child)
		{
			if ($child instanceof QPHPToken)
				$child->setParent($this, false, false);
		}
	}
}
