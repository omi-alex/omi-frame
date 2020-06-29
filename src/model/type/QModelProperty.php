<?php

/**
 * Represents a class property in a QModelType
 */
final class QModelProperty 
{
	/**
	 * The name of the property
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The propery type
	 *
	 * @var string|QModelAcceptedType|(string|QModelAcceptedType)[]
	 */
	public $types;
	/**
	 * The type(s) are strict
	 * This means that if we have a type like "QCategory" it will not accept classes that inherit QCategory
	 *
	 * @var boolean
	 */
	public $strict;
	/**
	 * True if the property represents an unsigned variable
	 *
	 * @var boolean
	 */
	public $unsigned;
	/**
	 * The max length of this property, only for value properties
	 *
	 * @var integer
	 */
	public $length;
	/**
	 * True if the variable can be null
	 *
	 * @var boolean
	 */
	public $null;
	/**
	 * The default that can be used when requested
	 *
	 * @var string
	 */
	public $default;
	/**
	 * In case of a string we need to know the charser and collation
	 *
	 * @var string
	 */
	public $charset;
	/**
	 * In case of a string we need to know the charser and collation
	 *
	 * @var string
	 */
	public $collation;
	/**
	 * Information associated with the property
	 *
	 * @var string
	 */
	public $comment;
	/**
	 * True if this propery is mandatory and can not be null or empty
	 *
	 * @var boolean
	 */
	public $mandatory;
	/**
	 * Gets the parent type that holds this property
	 *
	 * @var QModelType
	 */
	public $parent;
	/**
	 * In case of an enum or set we have a list of accepted values
	 * For enums we could use something like: `var enum{a,b,c,d,e,f}`
	 *
	 * @var string[]
	 */
	public $values;
	/**
	 * Storage info
	 *
	 * @var scalar[]
	 */
	public $storage;
	/**
	 * The getter
	 *
	 * @var string
	 */
	public $getter;
	/**
	 * The setter
	 *
	 * @var string
	 */
	public $setter;
	/**
	 * @var array
	 */
	public $rights;
	/**
	 * Flags that no export should be don for this instance
	 *
	 * @var boolean
	 */
	public $no_export = true;
	/**
	 * @var string
	 */
	public $validation;
	/**
	 * @var string
	 */
	public $is_mandatory;
	
	/**
	 * Gets the id of the property
	 * 
	 * @return string
	 */
	public function getId()
	{
		return $this->parent->class.".".$this->name;
	}

	/**
	 * String casting
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->parent->class.".".$this->name."(".(is_array($this->types) ? implode("|", $this->types) : $this->types) .")";
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
			if (is_string($this->types))
			{
				switch ($this->types)
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
	 * Checks if the accepted types of the property are all collection based
	 * 
	 * @return boolean
	 */
	public function collectionOnlyTypes()
	{
		if (qis_array($this->types))
		{
			foreach ($this->types as $t)
				if (!($t instanceof QModelAcceptedType))
					return false;
			return true;
		}
		else
			return ($this->types instanceof QModelAcceptedType);
	}
	
	/**
	 * Checks if this property has collection type specified
	 * 
	 * @return boolean
	 */
	public function hasCollectionType()
	{
		return ($this->_cty !== null) ? ($this->_cty ? true : false) : ($this->getCollectionType() ? true : false);
	}
	
	/**
	 * by Mihai
	 * to be rethinked
	 * @return boolean
	 */
	public function isScalar()
	{
		if (isset($this->_issc))
			return $this->_issc;

		$_ct = ($this->types && $this->types->options && is_array($this->types->options)) ? reset($this->types->options) : null;				
		try 
		{
			$ty = \QModelType::GetScalarTypeId($_ct);
		}
		catch (\Exception $ex) 
		{
			$ty = null;
		}
		return ($this->_issc = !is_null($ty));
	}
	
	/**
	 * If this property has a QModelAcceptedType type it returns it
	 * 
	 * @return QModelAcceptedType
	 */
	public function getCollectionType()
	{
		if ($this->_cty === null)
		{
			if (qis_array($this->types))
			{
				foreach ($this->types as $t)
					if ($t instanceof QModelAcceptedType)
						return ($this->_cty = $t);
				return ($this->_cty = false);
			}
			else
				return ($this->_cty = (($this->types instanceof QModelAcceptedType) ? $this->types : false));
		}
		else
			return $this->_cty;
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
			if (is_string($this->types))
			{
				// should be a string at this point
				if (strtolower($this->types{0}) === $this->types{0})
					return ($this->_multity = false);
				else
				{
					if ($this->strict)
						return ($this->_multity = false);
					else
					{
						$ext = QAutoload::GetClassExtendedBy($this->types);
						return ($this->_multity = (interface_exists($this->types) ? (($ext && next($ext)) ? true : false) : (!empty($ext))));
					}
				}
			}
			else if ($this->types instanceof QModelAcceptedType)
				return false;
			else 
				return ($this->_multity = true);
		}
	}
	
	/**
	 * Gets all possible objects classes accepted, including classes that inherit the listed ones.
	 * 
	 * @return string[]
	 */
	public function getAllReferenceTypes()
	{
		if ($this->_allrefs !== null)
			return $this->_allrefs;
		else
		{
			if ($this->strict)
				return ($this->_allrefs = $this->getReferenceTypes());
			$types = array();
			$raws = qis_array($this->types) ? $this->types : array($this->types);
			foreach ($raws as $type)
				$this->getAllReferenceTypesRec($type, $types);
			
			return ($this->_allrefs = $types);
		}
	}
	
	/**
	 * Sub-method for getAllReferenceTypes()
	 * 
	 * @return string[]
	 */
	protected function getAllReferenceTypesRec($type, &$types)
	{
		if (is_string($type))
		{
			// should be a string at this point
			if (strtolower($type{0}) !== $type{0})
			{
				$n_types = QAutoload::GetClassExtendedBy($type);
				if (!interface_exists($type))
					$types[$type] = $type;
				if ($n_types)
				{
					foreach ($n_types as $nty)
						$types[$nty] = $nty;
				}
			}
		}
		else if ($type instanceof QModelAcceptedType)
		{
			foreach ($type->options as $opt_ty)
			{
				if (strtolower($opt_ty{0}) !== $opt_ty{0})
				{
					$ext_types = QAutoload::GetClassExtendedBy($opt_ty);
					if (!interface_exists($opt_ty))
						$types[$opt_ty] = $opt_ty;
					if ($ext_types)
					{
						foreach ($ext_types as $ext_ty)
							$types[$ext_ty] = $ext_ty;
					}
				}
			}
		}
	}
	
	/**
	 * Checks if the types require a VALUE column, for storing the scalar data
	 * 
	 * @return boolean
	 */
	public function hasScalarType()
	{
		if ($this->_hassc !== null)
			return $this->_hassc;
		else
		{
			if (is_string($this->types))
				return ($this->_hassc = (strtolower($this->types{0}) == $this->types{0}));
			else if ($this->types instanceof QModelAcceptedType)
				return ($this->_hassc = false);
			else 
			{
				foreach ($this->types as $o)
				{
					if ((is_string($o)) && (strtolower($o{0}) == $o{0}))
						return ($this->_hassc = true);
				}
				return ($this->_hassc = false);
			}
		}
	}
	
	/**
	 * Gets types require a VALUE column, for storing the scalar data
	 * 
	 * @return boolean
	 */
	public function getScalarTypes()
	{
		if ($this->_scty !== null)
			return $this->_scty;
		else
		{
			if (is_string($this->types))
				return ($this->_hassc = ((strtolower($this->types{0}) === $this->types{0}) ? array($this->types) : false));
			else if ($this->types instanceof QModelAcceptedType)
				return ($this->_scty = false);
			else 
			{
				$this->_scty = array();
				foreach ($this->types as $o)
				{
					if ((is_string($o)) && (strtolower($o{0}) === $o{0}))
						$this->_scty[] = $o;
				}
				return $this->_scty ?: ($this->_scty = false);
			}
		}
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
		else
		{
			if (is_string($this->types))
				return ($this->_hasrt = ((strtolower($this->types{0}) !== $this->types{0})));
			else if ($this->types instanceof QModelAcceptedType)
				return ($this->_hasrt = false);
			else 
			{
				foreach ($this->types as $o)
				{
					if ((is_string($o)) && (strtolower($o{0}) !== $o{0}))
						return ($this->_hasrt = true);
				}
				return ($this->_hasrt = false);
			}
		}
	}
	
	public function hasInstantiableReferenceType()
	{
		if ($this->_hasirt !== null)
			return $this->_hasirt;
		else
		{
			if (is_string($this->types))
				return ($this->_hasirt = ((strtolower($this->types{0}) !== $this->types{0}) && QModelType::IsInstantiable($this->types)));
			else if ($this->types instanceof QModelAcceptedType)
				return ($this->_hasirt = false);
			else 
			{
				foreach ($this->types as $o)
				{
					if ((is_string($o)) && (strtolower($o{0}) !== $o{0}) && QModelType::IsInstantiable($o))
						return ($this->_hasirt = true);
				}
				return ($this->_hasirt = false);
			}
		}
	}

	/**
	 * Gets the name of the column for the specified type
	 * 
	 * @return string
	 */
	public function getColumnName()
	{
		return $this->_cname ?: (($this->_cname = $this->storage["column"]) ?: ($this->_cname = ((strtolower($this->name) === "id") ? $this->parent->getIdCn() : $this->name)));
	}
	
	/**
	 * Gets the name of the column for references
	 * 
	 * @return string
	 */
	public function getRefColumnName()
	{
		return QORM_FKPREFIX.$this->getColumnName();
	}
	
	/**
	 * Gets the name of the type column
	 * 
	 * @return string
	 */
	public function getTypeColumnName()
	{
		return QORM_TYCOLPREFIX.$this->getColumnName().QORM_TYCOLSUFIX;
	}

	/**
	 * Gets the name of the collection table
	 * 
	 * @return string
	 */
	public function getCollectionTableName()
	{
		return $this->_ctable ?: ($this->_ctable = QSqlModelInfoType::GetTableNameForCollectionProperty($this->parent->class, $this->name));
	}
	/**
	 * Gets the rowid name for a collection
	 * 
	 * @return string
	 */
	public function getCollectionTableRowId()
	{
		return $this->_idCol ?: ($this->_idCol = (($this->storage && $this->storage["idCol"]) ? $this->storage["idCol"] : QORM_IDCOL));
	}
	/**
	 * Gets the rowid name for a collection
	 * 
	 * @return string
	 */
	public function getCollRId()
	{
		return $this->_idCol ?: ($this->_idCol = (($this->storage && $this->storage["idCol"]) ? $this->storage["idCol"] : QORM_IDCOL));
	}
	
	/**
	 * If the property contains a collection, then it returns the column in the collection table that 
	 * points back to the main table 
	 * 
	 * @return string
	 */
	public function getCollectionBackrefColumn()
	{
		if ($this->_bkcol)
			return $this->_bkcol;
		else
		{
			if ($this->isOneToMany())
			{
				return is_string($this->storage["oneToMany"]) ? 
						($this->_bkcol = QORM_FKPREFIX.$this->storage["oneToMany"]) : 
							($this->_bkcol = QORM_FKPREFIX.$this->parent->getTableName().QORM_FKPREFIX.$this->getColumnName());
			}
			else 
			{
				$sc = $this->storage["collection"];
				if (!empty($sc))
				{
					// if collection is specified we expect: table,backcolumn,forwardcolumn
					if (is_string($sc))
						$sc = $this->storage["collection"] = explode(",", $sc);
					return ($this->_bkcol = QORM_FKPREFIX.$sc[1]);
				}
				else
					return ($this->_bkcol = QORM_FKPREFIX.$this->parent->getTableName());
			}
		}
	}
	
	/**
	 * If the property contains a collection, then it returns the column in the collection table that 
	 * points forward to the table that contains the value of the collection
	 * In a one to many collection it will return null
	 * 
	 * @return string
	 */
	public function getCollectionForwardColumn()
	{
		// a many to many does not have a FWD column
		if ($this->_fwdcol || $this->isOneToMany())
			return $this->_fwdcol;
		else
		{
			$sc = $this->storage["collection"];
			if (!empty($sc))
			{
				// if collection is specified we expect: table,backcolumn,forwardcolumn
				if (is_string($sc))
					$sc = $this->storage["collection"] = explode(",", $sc);
				return ($this->_fwdcol = QORM_FKPREFIX.$sc[2]);
			}
			else
			{
				return ($this->_fwdcol = QORM_FKPREFIX.$this->getColumnName());
			}
		}
	}
	
	/**
	 * Gets the value (scalar) column in the collection 
	 * In a one to many collection it will return null
	 * 
	 * @return string
	 */
	public function getCollectionValueColumn()
	{
		return ($fwd = $this->getCollectionForwardColumn()) ? substr($fwd, strlen(QORM_FKPREFIX)) : null;
	}
	
	/**
	 * Gets the type column name for the forward column
	 * In a one to many collection it will return null
	 * 
	 * @return string
	 */
	public function getTypeCollectionForwardColumn()
	{
		return ($fwd = $this->getCollectionForwardColumn()) ? $fwd.QORM_TYCOLSUFIX : null;
	}
	
	/**
	 * Checks if the collection of the property is one to many or not
	 * 
	 * @return boolean
	 */
	public function isOneToMany()
	{
		return $this->_otom ? true : ($this->_otom = ($this->storage["oneToMany"] ? true : 
					($this->storage["manyToMany"] ? false : 
					(($this->parent->class === QApp::GetDataClass()) && ($coll_ty = $this->getCollectionType()) && $coll_ty->hasReferenceType()))));
					// must also make sure it does not point to a scenario with multipe tables
	}
	/**
	 * Gets the data types that are objects in this property
	 * 
	 * @return string[]
	 */
	public function getReferenceTypes()
	{
		if (is_string($this->types))
			return (strtolower($this->types{0}) !== $this->types{0}) ? array($this->types => $this->types) : null;
		else if (qis_array($this->types))
		{
			$ret = array();
			foreach ($this->types as $ty)
			{
				if (is_string($ty) && strtolower($ty{0}) != $ty{0})
					$ret[$ty] = $ty;
			}
			if (!empty($ret))
				return $ret;
		}
		else if (($this->types instanceof \QModelAcceptedType) && ($this->types->type == "QModelArray") && $this->types->options)
		{
			foreach ($this->types->options as $ty)
			{
				if (is_string($ty) && strtolower($ty{0}) != $ty{0})
					$ret[$ty] = $ty;
			}
			if (!empty($ret))
				return $ret;
		}
		else
			return null;
	}
	
	public function getInstantiableReferenceTypes()
	{
		if (is_string($this->types))
			return ((strtolower($this->types{0}) !== $this->types{0}) && QModelType::IsInstantiable($this->types)) ? array($this->types) : null;
		else if (qis_array($this->types))
		{
			$ret = array();
			foreach ($this->types as $ty)
			{
				if (is_string($ty) && (strtolower($ty{0}) !== $ty{0}) && QModelType::IsInstantiable($ty))
					$ret[] = $ty;
			}
			if (!empty($ret))
				return $ret;
		}
		else
			return null;
	}
	
	public function getFirstInstantiableType()
	{
		$types = ($this->types instanceof QModelAcceptedType) ? 
				$this->types->getAllInstantiableReferenceTypes() :
				$this->getAllInstantiableReferenceTypes();
		return $types ? reset($types) : null;
	}
	
	public function getAllInstantiableReferenceTypes()
	{
		if ($this->_allretity !== null)
			return $this->_allretity;
		
		$options = array();
		if (is_string($this->types))
			$options = [$this->types => $this->types];
		else if (qis_array($this->types))
			$options = $this->types;
		else
			return null;
		
		foreach ($options as $opt_ty)
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
		
		$options = array();
		if (is_string($this->types))
			$options = [$this->types];
		else if (qis_array($this->types))
			$options = $this->types;
		else
			return null;
		
		foreach ($options as $opt_ty)
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
	
	public function getAllPossibleTypesAsArray($types = null, $is_collection = false, &$ret = null)
	{
		$types = $types ?: $this->types;
		if ($ret === null)
			$ret = [];
		if (is_string($types))
		{
			if (ucfirst($types{0}) === $types{0})
			{
				$inst_types = static::GetAllInstantiableTypesFor($types, $this->strict);
				foreach ($inst_types as $it)
					$ret[$it] = $it;
			}
			else
				$ret[$types] = $types;
		}
		else if ($types instanceof \QModelAcceptedType)
		{
			$ret["[]"] = [];
			$this->getAllPossibleTypesInArray($types->options, true, $ret["[]"]);
		}
		else if (is_array($types))
		{
			foreach ($types as $type)
				$this->getAllPossibleTypesInArray($type, false, $ret);
		}
		
		return $ret ?: null;
	}
	
	public static function GetAllInstantiableTypesFor($opt_ty, $strict = false)
	{
		$types = [];
		$ext_types = $strict ? null : QAutoload::GetClassExtendedBy($opt_ty);
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
		return $types;
	}
	
	/**
	 * @param string $class_name
	 * @return string
	 */
	public function getAppPropertyFor(string $class_name)
	{
		if (($app_prop = $this->_appProps[$class_name]) !== null)
			return $app_prop ?: null;

		/* @TODO
		$inst_types = $this->getAllInstantiableReferenceTypes();
		if (!$inst_types[$class_name])
			// the type is not supported by the property
			return null;
		*/
		
		// if explicit
		if (($app_prop = $this->storage["appProperty"]))
		{
			if (($app_prop !== 'none') && ($app_prop !== 'false'))
				return ($this->_appProps[$class_name] = false);
			else
				return ($this->_appProps[$class_name] = $app_prop);
		}
		else if ($this->parent->class === \QApp::GetDataClass())
		{
			// if top level property we merge on it
			return ($this->_appProps[$class_name] = $this->name);
		}
		else if (($m_type = \QModel::GetTypeByName($class_name)) && ($app_prop = $m_type->storage["appProperty"]))
		{
			if (($app_prop !== 'none') && ($app_prop !== 'false'))
				return ($this->_appProps[$class_name] = false);
			else
				return ($this->_appProps[$class_name] = $app_prop);
		}
		else
		{
			$this->_appProps[$class_name] = false;
			return null;
		}
	}
	
	// @TODO to be improved !!!!
	public function isMandatory()
	{
		if ($this->is_mandatory !== null)
			return $this->is_mandatory;
		return ($this->is_mandatory = ($this->validation && preg_match("/\\bmandatory\\b/us", $this->validation)));
	}
	
	public function getCaption()
	{
		if ($this->storage["caption"] !== null)
			return $this->storage["caption"];
		else
		{
			$tmp_display_caption = preg_replace_callback('/(?<!\b)[A-Z][a-z]+|(?<=[a-z])[A-Z]/', function($match) {
				return ' '. $match[0];
			}, $this->name);
			
			return preg_replace('/[\\_\\s]+/', " ", $tmp_display_caption);
		}
	}
}
