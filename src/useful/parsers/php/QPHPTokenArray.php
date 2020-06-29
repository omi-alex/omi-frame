<?php

/**
 * Description of QPHPTokenArray
 *
 * @author Alex
 */
class QPHPTokenArray extends QPHPToken
{
	protected static $Obj_To_Code_Props = ["children" => null, 'docComment' => null, 'items' => null, 'pullDocComment' => null];
	
	public function parse($tokens, $tok, &$pos, $expand_output = false, $expand_in_methods = true, $expand_arrays = false)
	{
		// can start with "[" or T_ARRAY
		$this->children = array();
		
		$end_tok = ($tok === "[") ? "]" : ")";
		
		// go to the next position
		$this->children[] = $tok;
		$pos++;
		$tok = $tokens[$pos];
		
		$val = array();
		$key = null;
		
		while ($tok)
		{
			$is_arr = is_array($tok);
			$type = $is_arr ? $tok[0] : $tok;
			$increment = true;
			$set_in_val = true;
			
			if ($type === T_DOUBLE_ARROW)
			{
				$key = $val;
				$val = array();
				$set_in_val = false;
			}
			else if ($type === ",")
			{
				$this->items[] = array($key, $val);
				$val = array();
				$key = null;
				$set_in_val = false;
			}
			else if ($type === T_FUNCTION)
			{
				$child = new QPHPTokenFunction($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
				
				$increment = false;
				$set_in_val = false;
				$val[] = $child;
			}
			else if (($tok === "[") || ($type === T_ARRAY))
			{
				// an array with it
				$child = new QPHPTokenArray($this);
				$child->parse($tokens, $tok, $pos, $expand_output, $expand_in_methods, $expand_arrays);
				$child->setParent($this, true);
				
				$increment = false;
				$set_in_val = false;
				$val[] = $child;
			}
			else if ($type === $end_tok)
			{
				$this->children[] = $tok;
				$pos++;
				break;
			}
			
			if ($set_in_val)
				$val[] = $tok;
			
			if ($increment)
			{
				$this->children[] = $tok;
				$pos++;
			}
			
			$tok = $tokens[$pos];
		}
		
		if ($key || $val)
			$this->items[] = array($key, $val);
		
		// if (!($this->parent instanceof QPHPTokenArray))
		// echo "=======================================================<br/>";
		// $this->dumpItems();
	}
	
	public function dumpItems()
	{
		foreach ($this->items as $itm)
		{
			list($key, $val) = $itm;
			if ($key)
				var_dump($this->toString($key));
			if ($val)
				var_dump($this->toString($val));
			echo "<hr/>";
		}
	}
}
