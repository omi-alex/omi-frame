<?php

/**
 * The QModelType holds data type information for QIModel objects and interfaces
 */
final class QModelType 
{
	// define scalar types
	const ModelTypeVoid = 0;
	const ModelTypeInteger = 1;
	const ModelTypeString = 2;
	const ModelTypeFloat = 3;
	const ModelTypeBoolean = 4;
	const ModelTypeNull = 5;
	const ModelTypeArray = 6;
	const ModelTypeResource = 7;
	const ModelTypeMixed = 8;
	const ModelTypeCallback = 9;
	const ModelTypeScalar = 10;
	const ModelTypeScalarOrArray = 11;
	const ModelTypeDate = 12;
	const ModelTypeDateTime = 13;
	const ModelTypeTime = 14;
	const ModelTypeTimestamp = 15;
	const ModelTypeObject = 16;
	const ModelTypeFile = 17;

	/**
	 * The name of the class
	 * 
	 * @var string
	 */
	public $class;
	/**
	 * If it's final
	 *
	 * @var boolean
	 */
	public $is_final;
	/**
	 * If it's abstract
	 *
	 * @var boolean
	 */
	public $is_abstract;
	/**
	 * If it's an interface
	 *
	 * @var boolean
	 */
	public $is_interface;
	/**
	 * The parent class if it's the case
	 * We consider the QIModel implementers to have no parent
	 * 
	 * @var string
	 */
	public $parent;
	/**
	 * The list with the type's properties
	 *
	 * @var QModelProperty[]
	 */
	public $properties;
	/**
	 * The list of methods
	 *
	 * @var QModelMethod[]
	 */
	public $methods;
	/**
	 * The path to the file as a string
	 *
	 * @var string
	 */
	public $path;
	/**
	 * True if it's a instanceof QIModelArray
	 *
	 * @var boolean
	 */
	public $is_collection;
	/**
	 * The list of interfaces that this object implements (one level)
	 *
	 * @var string[]
	 */
	public $implements;
	/**
	 *
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
	 * @var string[]
	 */
	public $storage = null;
	/**
	 * @var string[]
	 */
	public $cfg = null;
	/**
	 * @var string[]
	 */
	public $model = null;
	
	/**
	 * True if this type implmenents QIModelArray
	 * 
	 * @return boolean
	 */
	public function isCollection()
	{
		return is_bool($this->is_collection) ? $this->is_collection : ($this->is_collection = qIsA($this->class, "QIModelArray"));
	}
	
	/**
	 * True if this object is a instance of the specified type
	 * 
	 * @param string $type
	 * @return boolean
	 */
	public function isInstanceOf($type)
	{
		return qIsA($this->class, $type);
	}
	
	/**
	 * Gets the ID of the model or class
	 * 
	 * @return string
	 */
	public function getId()
	{
		return $this->class;
	}
	
	/**
	 * Gets the numeric id as created for it.
	 * 
	 * @return integer
	 */
	public function getIntId()
	{
		return $this->id ?: ($this->id = QApp::GetStorage()->getTypeIdInStorage($this->class));
	}
	
	/**
	 * The __toString magic method
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->class;
	}
	
	/**
	 * Gets the name of the type
	 * 
	 * @return string
	 */
	public function getTypeName()
	{
		return $this->class;
	}
	
	/**
	 * Gets the name of the default storage table
	 * 
	 * @return string
	 */
	public function getTableName()
	{
		return $this->_tname ?: ($this->_tname = QSqlModelInfoType::GetTableNameForType($this->class));
	}
	
	/**
	 * Gets the name of the rowid column
	 * 
	 * @return string
	 */
	public function getRowIdColumnName()
	{
		// storage.idCol 
		return $this->_idCol ?: ($this->_idCol = (($this->storage && $this->storage["idCol"]) ? $this->storage["idCol"] : QORM_IDCOL));
	}
	/**
	 * Gets the name of the rowid column
	 * 
	 * @return string
	 */
	public function getIdCn()
	{
		// storage.idCol 
		return $this->_idCol ?: ($this->_idCol = (($this->storage && $this->storage["idCol"]) ? $this->storage["idCol"] : QORM_IDCOL));
	}
	
	/**
	 * Gets the name of the rowid column
	 * 
	 * @return string
	 */
	public function getTypeColumnName()
	{
		// storage.typeCol
		// return QORM_TYCOL;
		return $this->_typeCol ?: ($this->_typeCol = (($this->storage && $this->storage["typeCol"]) ? $this->storage["typeCol"] : QORM_TYCOL));
	}
	
	/**
	 * Gets the reflection class
	 * 
	 * @return ReflectionClass
	 */
	public function getReflectionClass()
	{
		return $this->__refl ?: ($this->__refl = new ReflectionClass($this->class));
	}

	/**
	 * Gets the QModelType for the specified $class
	 * 
	 * @param string $class
	 * @return QModelType
	 */
	public static final function GetModelTypeByClass($class)
	{
		if (!$class)
			return null;
		return QModel::GetTypeByName($class);
	}

	/**
	 * Returns true if 
	 * 
	 * @param string $method_name
	 * @return boolean
	 */
	public function methodHasApiAccess($method_name)
	{
		return isset($this->methods[$method_name]->api["enable"]);
	}
	
	/**
	 * Gets the name of the class without it's namespace.
	 * 
	 * @return string
	 */
	public function getClassNameWithoutNs()
	{
		return (($nsp = strrpos($this->class, "\\")) === false) ? $this->class : substr($this->class, $nsp + 1);
	}
	
	/**
	 * Gets the name of the properties
	 * 
	 * @return string[]
	 */
	public function getPropertiesNames()
	{
		return $this->_ppkeys ?: ($this->_ppkeys = $this->properties->getKeys());
	}
	
	/**
	 * Gets the QModelType instance based on the numeric ID of the class
	 * 
	 * @param integer $id
	 * @return QModelType
	 */
	public static function GetModelTypeById($id)
	{
		return self::GetModelTypeByClass(QApp::GetStorage()->getTypeNameInStorageById($id));
	}
	
	/**
	 * Gets all the classes that extend this type, including itself.
	 * 
	 * @return string[]
	 */
	public function getPossibleTypes()
	{
		if ($this->_posty !== null)
			return $this->_posty;
		else
		{
			// should be a string at this point
			$ext = QAutoload::GetClassExtendedBy($this->class);
			if ($ext)
			{
				$ext[$this->class] = $this->class;
				return $this->_posty = $ext;
			}
			else
				return $this->_posty = array($this->class => $this->class);
		}
	}
	
	/**
	 * @todo 
	 * 
	 * Checks if all declared types exists.
	 * 
	 * @return boolean|string Returns true if all ok or an error message string if there are issues.
	 */
	public function checkDataModelIntegrity()
	{
		$ret = true;
		// TO DO
		return $ret;
	}
	
	/**
	 * Gets the numeric id of a scalar type
	 * 
	 * @param string $scalar_type
	 * @return integer
	 * @throws Exception
	 */
	public static function GetScalarTypeId($scalar_type)
	{
		switch ($scalar_type)
		{
			case "integer":
			case "int":
				return QModelType::ModelTypeInteger;
			case "string":
				return QModelType::ModelTypeString;
			case "float":
			case "double":
				return QModelType::ModelTypeFloat;
			case "boolean":
			case "bool":
				return QModelType::ModelTypeBoolean;
			case "null":
				return QModelType::ModelTypeNull;
			case "array":
				return QModelType::ModelTypeArray;
			case "date":
				return QModelType::ModelTypeDate;
			case "datetime":
				return QModelType::ModelTypeDateTime;
			case "time":
				return QModelType::ModelTypeTime;
			case "timestamp":
				return QModelType::ModelTypeTimestamp;
			case "resource":
				return QModelType::ModelTypeResource;
			case "mixed":
				return QModelType::ModelTypeMixed;
			case "callback":
				return QModelType::ModelTypeCallback;
			case "scalar":
				return QModelType::ModelTypeScalar;
			case "scalarorarray":
				return QModelType::ModelTypeScalarOrArray;
			case "object":
				return QModelType::ModelTypeObject;
			case "file":
				return QModelType::ModelTypeFile;
			case "void":
				return QModelType::ModelTypeVoid;
			default:
			{
				throw new Exception("Unregistered scalar type: {$scalar_type}");
			}
		}
	}
	
	public static function IsInstantiable($class_name)
	{
		$ty = QModel::GetTypeByName($class_name);
		if (!$ty)
			return false;
		return (!$ty->is_abstract) && (!$ty->is_interface) && (!$ty->is_trait);
	}
	
	public function extractDisplayableProps()
	{
		$props = $this->properties;
		$show_props = [];
		$show_p_count = 0;
		foreach ($props as $p_name => $p)
		{
			if (in_array($p_name, ['Owner', 'Gid', 'Id', 'Del__', 'CreatedBy', 'MTime', 'SuppliedBy', 'ChangedTime', 'InSyncProcess', 'ToBeSynced', 'LastSyncedAt']))
				continue;
			$show_props[$p_name] = $p;
			$show_p_count++;
		}
		
		$inside_collection = false;

		if (($show_p_count === 1) && (is_object($props[key($show_props)]->types)) && $props[key($show_props)]->types->isCollection())
		{
			$inside_collection = $props[key($show_props)];
			// qvar_dumpk('expand the collection!');
			$inst_ref_type = $props[key($show_props)]->types->getAllInstantiableReferenceTypes();
			$inst_ref_type = is_array($inst_ref_type) ? reset($inst_ref_type) : $inst_ref_type;
			
			$type = \QModel::GetTypeByName($inst_ref_type);
			list($show_props,) = $type->extractDisplayableProps();
		}
		
		return [$show_props, $inside_collection];
	}
}
