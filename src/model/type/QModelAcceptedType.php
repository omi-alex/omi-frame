<?php

/**
 * This class is used to store type information for properties that have arrays
 */
final class QModelAcceptedType 
{
	/**
	 * The type of the collection as string. This should be a class name that implements QIModelArray 
	 *
	 * @var string
	 */
	public $type;
	/**
	 * In case this is an array we also need a list of sub-options
	 * Sub options should never be class names that implements QIModelArray
	 *
	 * @var string[]
	 */
	public $options;
	/**
	 * The options are strict
	 * This means that if we have a type like "QCategory" it will not accept classes that inherit QCategory
	 *
	 * @var boolean
	 */
	public $strict;
	/**
	 * Flags that no export should be don for this instance
	 *
	 * @var boolean
	 */
	public $no_export = true;
	
	/**
	 * Constructor
	 * 
	 * @param string $type
	 * @param string[] $options
	 * @param boolean $strict 
	 */
	public function __construct($type, $options, $strict = null)
	{
		$this->type = $type;
		$this->options = $options;
		$this->strict = $strict;
	}

	/**
	 * String representation for QModelAcceptedType
	 * 
	 * @return string
	 */
	public function __toString()
	{
		$has_more = $this->options && next($this->options);
		return ($this->options ? ($has_more ? "(" : "").implode("|", $this->options).($has_more ? ")" : "")."[".($this->type != "QModelArray" ? $this->type : "")."]" : (string)$this->type);
	}
	
	/**
	 * Sets the accepted types 
	 * 
	 * @param string[] $types
	 */
	public function setTypes($types)
	{
		foreach ($types as $ty)
			$this->options[$ty] = $ty;
	}
	
	/**
	 * Always returns true as it's a collection
	 * 
	 * @return boolean
	 */
	public function isCollection()
	{
		return true;
	}
	
	/**
	 * Tests if this->types will accept multiple types
	 * 
	 * @return boolean
	 */
	public function isMultiType()
	{
		if ($this->_multity !== null)
			return $this->_multity;
		else
		{
			$fst = reset($this->options);
			$cnt = next($this->options);
			if ($cnt)
				return ($this->_multity = true);
			else
				return ($this->_multity = ($this->strict ? false : (QAutoload::GetClassExtendedBy($fst) ? true : false)));
		}
	}
	
	/**
	 * Tests if this collection has scalar types defined
	 * 
	 * @return boolean
	 */
	public function hasScalarType()
	{
		if ($this->_hassc !== null)
			return $this->_hassc;
		foreach ($this->options as $o)
		{
			if (strtolower($o{0}) == $o{0})
				return ($this->_hassc = true);
		}
		return ($this->_hassc = false);
	}
	
	
	/**
	 * Checks if the types require a REFERENCE column ($), for storing the reference data
	 * 
	 * @return boolean
	 */
	public function hasReferenceType()
	{
		if ($this->_hasrt !== null)
			return $this->_hasrt;
		
		foreach ($this->options as $o)
		{
			if (strtolower($o{0}) !== $o{0})
				return ($this->_hasrt = $o);
		}
		return ($this->_hasrt = false);
	}
	
	public function hasInstantiableReferenceType()
	{
		if ($this->_hasirt !== null)
			return $this->_hasirt;
		
		foreach ($this->options as $o)
		{
			if ((strtolower($o{0}) !== $o{0}) && QModelType::IsInstantiable($o))
				return ($this->_hasirt = $o);
		}
		return ($this->_hasirt = false);
	}
	
	/**
	 * Generates the PHP code string representation required to create this object.
	 * 
	 * @return string
	 */
	public function toPhpCode($strict = null)
	{
		$str = "new QModelAcceptedType(\"".addslashes($this->type)."\", array(";
		
		foreach ($this->options as $opt)
			$str .= json_encode($opt)." => ".json_encode($opt).",";
		
		$str .= ")".(($this->strict || $strict) ? ", true" : "").")";
		
		return $str;
	}
	
	/**
	 * Convert the input value to the supported type
	 * This method is called by QModel::set
	 * 
	 * @param mixed $val
	 * @return mixed
	 */
	public function fixVal($val)
	{
		if (is_object($val) || is_array($val))
			return $val;
		
		if ($this->_sfix === null)
		{
			$frst = reset($this->options);
			
			if (($frst{0} === strtolower($frst{0})) && (next($this->options) === false))
			{
				switch ($frst)
				{
					case "integer":
					case "int":
					{
						$this->_sfix = 1;
						break;
					}
					case "boolean":
					case "bool":
					{
						$this->_sfix = 2;
						break;
					}
					case "float":
					case "double":
					{
						$this->_sfix = 3;
						break;
					}
					case "string":
					{
						$this->_sfix = 4;
						break;
					}
					default:
					{
						$this->_sfix = 0;
						break;
					}
				}
			}
			else
				$this->_sfix = 0;
		}

		switch ($this->_sfix)
		{
			case 0:
				return $val;
			case 1:
				return (int)$val;
			case 2:
				return (bool)$val;
			case 3:
				return (float)$val;
			case 4:
				return (string)$val;
		}
	}
	
	/**
	 * Applies the namespace to the types
	 * 
	 * @param string $namespace
	 */
	public function applyNamespaceToTypes($namespace)
	{
		// $this->type = QCodeStorage::ApplyNamespaceToName($this->type, $namespace);
		
		foreach ($this->options as $k => $opt)
		{
			if (($opt{0} === "\\") || (strtolower($opt{0}) !== $opt{0}))
				$this->options[$k] = QCodeStorage::ApplyNamespaceToName($opt, $namespace);
		}
	}
	
	/**
	 * Gets the data types that are objects
	 * 
	 * @return string[]
	 */
	public function getReferenceTypes()
	{
		if ($this->_retty !== null)
			return $this->_retty;
		$ret = array();
		foreach ($this->options as $ty)
		{
			if (is_string($ty) && strtolower($ty{0}) != $ty{0})
				$ret[$ty] = $ty;
		}
		return ($this->_retty = $ret);
	}
	
	public function getInstantiableReferenceTypes()
	{
		if ($this->_retity !== null)
			return $this->_retity;
		$ret = array();
		foreach ($this->options as $ty)
		{
			if (is_string($ty) && (strtolower($ty{0}) != $ty{0}) && QModelType::IsInstantiable($ty))
				$ret[] = $ty;
		}
		return ($this->_retity = $ret);
	}
	
	public function getAllInstantiableReferenceTypes()
	{
		if ($this->_allretity !== null)
			return $this->_allretity;
		
		$types = array();
		foreach ($this->options as $opt_ty)
		{
			if (strtolower($opt_ty{0}) !== $opt_ty{0})
			{
				$ext_types = $this->strict ? null : QAutoload::GetClassExtendedBy($opt_ty);
				if ((!interface_exists($opt_ty)) && QModelType::IsInstantiable($opt_ty))
					$types[$opt_ty] = $opt_ty;
				if ($ext_types)
				{
					foreach ($ext_types as $ext_ty)
					{
						if ((!interface_exists($ext_ty)) && QModelType::IsInstantiable($ext_ty))
							$types[$ext_ty] = $ext_ty;
					}
				}
			}
		}
		
		return ($this->_allretity = $types);
	}
	
	public function hasAnyInstantiableReferenceTypes()
	{
		if ($this->_hasanyity !== null)
			return $this->_hasanyity;
		
		foreach ($this->options as $opt_ty)
		{
			if (strtolower($opt_ty{0}) !== $opt_ty{0})
			{
				$ext_types = $this->strict ? null : QAutoload::GetClassExtendedBy($opt_ty);
				if ((!interface_exists($opt_ty)) && QModelType::IsInstantiable($opt_ty))
					return ($this->_hasanyity = true);
				if ($ext_types)
				{
					foreach ($ext_types as $ext_ty)
					{
						if ((!interface_exists($ext_ty)) && QModelType::IsInstantiable($ext_ty))
							return ($this->_hasanyity = true);
					}
				}
			}
		}
		
		return ($this->_hasanyity = false);
	}
	
	/**
	 * Gets all possible objects classes accepted, including classes that inherit the listed ones.
	 * 
	 * @return string[]
	 */
	public function getAllReferenceTypes()
	{
		if ($this->_allretty !== null)
			return $this->_allretty;
		
		$types = array();
		foreach ($this->options as $opt_ty)
		{
			if (strtolower($opt_ty{0}) !== $opt_ty{0})
			{
				$ext_types = $this->strict ? null : QAutoload::GetClassExtendedBy($opt_ty);
				if (!interface_exists($opt_ty))
					$types[$opt_ty] = $opt_ty;
				if ($ext_types)
				{
					foreach ($ext_types as $ext_ty)
						$types[$ext_ty] = $ext_ty;
				}
			}
		}
		
		return ($this->_allretty = $types);
	}
}
