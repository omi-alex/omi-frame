<?php

class QSqlModelInfoType 
{
	
	/**
	 *
	 * @var QModelType|QIModelTypeUnstruct
	 */
	public $type;
	/**
	 * The root starting point
	 *
	 * @var QSqlModelInfoType
	 */
	public $root;
	/**
	 *
	 * @var QSqlModelInfoType|QSqlModelInfoProperty
	 */
	public $parent;
	/**
	 * Array types (QIModelArray) have as children QSqlModelInfoType elements
	 *
	 * @var QSqlModelInfoType[]|QSqlModelInfoProperty[]
	 */
	public $children;
	
	private static $TableTypeList;
	private static $TablePropertyList;
	private static $TablePropertyTypesList;
	private static $TablePropertyTypesList_Reverse;
	private static $PropertiesWithTypes;
	private static $PropertiesWithRefs;
	private static $TableToTypes;
	
	private static $ColumnsTypeInfo;
	
	public $db_table = null;
	
	/**
	 * 
	 * @param QSqlStorage $storage
	 * @return QSqlTable
	 * @throws Exception
	 */
	public function setupTable(QSqlStorage $storage)
	{
		$for_collection = $this->type->isCollection();
		$one_to_many = $for_collection && $this->parent->property->isOneToMany();
		
		$parent_prop = $this->parent ? (($this->parent instanceof QSqlModelInfoProperty) ? $this->parent->property : $this->parent->parent->property) : null;
		if ($this->parent && (!$parent_prop))
		{
			var_dump($this->type." / ".get_class($this->parent));
			throw new Exception("This should not happen");
		}
		
		if ($for_collection)
			$t_name = self::GetTableNameForCollectionProperty($this->parent->parent->type->class, $this->parent->property->name);
		else
			$t_name = self::GetTableNameForType($this->type->class);
		
		if (!$t_name)
		{
			if ($for_collection)
			{
				// var_dump(self::$TablePropertyList);
				throw new Exception("Unable to determine table name for: ".$this->parent->property);
			}
			else
				throw new Exception("Unable to determine table name for: ".$this->type."/".($this->parent->property ?: ""));
		}
		
		$orig_tname = $t_name;
		
		if (($tmpt_pos = strpos($t_name, ".")) !== false)
		{
			$db_name = substr($t_name, 0, $tmpt_pos);
			$t_name = substr($t_name, $tmpt_pos + 1);
			$db = $storage->getDatabase($db_name);
			// var_dump("$db_name.$t_name");
		}
		else
		{
			$db = $storage->getDefaultDatabase();
			$db_name = $db->name;
		}
		
		$root = $this->getEntityRoot();
		
		$root->_dbs_list[$db->name] = $db;
		
		$full_tname = $db->name.".".$t_name;
		
		if (!$db->children)
			$db->children = new QModelArray();
		
		$db_table = $db->children[$t_name] ?: (($exist_tab = $storage->getTableByName($t_name, $db)) ? $exist_tab : new QSqlTable($t_name));
		if (!$db->children)
			$db->children = new QModelArray();
		
		$db->children->set($t_name, $db_table);
		
		$db_table->set("charset", "utf8");
		$db_table->set("collation", "utf8_unicode_ci");
		$db_table->set("comment", ($for_collection && (!$one_to_many)) ? 
									"Storage for collection: ".$this->parent->parent->type->class.".".$this->parent->property->name : 
									"Storage for class: ".$this->type);
		$db_table->set("engine", "InnoDB");
		$db_table->parent = $db;
		
		if (!$db_table->columns)
			$db_table->set("columns", new QModelArray());
		if (!$db_table->indexes)
			$db_table->set("indexes", new QModelArray());
		
		// setup rowid & column id
		$rowid_name = $for_collection ? $parent_prop->getCollectionTableRowId() : $this->type->getRowIdColumnName();
		$rowid_col_obj = QSqlModelInfoProperty::SetupSqlColumn(QSqlTableIndex::IndexPrimary, $db_table, $rowid_name, QSqlTableColumn::TypeInt, null, null, null, null, null, true, false, true, "Id/RowId column role");
		
		if (!$for_collection)
		{
			// setup type column (only if required)
			if ($root->_table_types && $root->_table_types[$orig_tname])
			{
				$typ_col_name = $this->type->getTypeColumnName();
				
				$root->_multitype[$orig_tname][$rowid_name] = $typ_col_name;
				
				QSqlModelInfoProperty::SetupSqlColumn(QSqlTableIndex::IndexNormal, $db_table, $typ_col_name, QSqlTableColumn::TypeSmallint, null, null, null, null, null, true, true, false, "Type column for table entry role");
			}
			else if (!$root->_multitype[$orig_tname][$rowid_name])
			{
				$root->_multitype[$orig_tname][$rowid_name] = array((int)$storage->getTypeIdInStorage($this->type->class), $this->type->class);
			}
			
			// next one will trigger multitype
			$root->_table_types[$orig_tname][$this->type->class] = $this->type->class;
		}
		
		if ($for_collection)
		{
			// setup backreference column
			$bk_ref_name = $parent_prop->getCollectionBackrefColumn(); // $one_to_many ? "_id_".$this->parent->parent->db_table->name.".".$this->parent->property->name : "_id_".$this->parent->parent->db_table->name;
			
			// $parent_prop_type = $this->parent->parent;
			
			$coll_bk_ref_default = null;
			$coll_bk_ref_null = true;
			
			/** we don't default to 1 any more
			if (($parent_prop_type->parent === null) && ($parent_prop_type->type->class === QApp::GetDataClass()))
			{
				// $coll_bk_ref_default = QData()->getId() ?: 1;
				// $coll_bk_ref_null = false;
			}
			*/
			
			$comment = "Reference to `{$full_tname}`.`{$this->parent->property->name}` column role";
			//						SetupSqlColumn($index_type,				QSqlTable $table, $name,					$type,	$length,$values, $default,    $charset, $collation, $unsigned, $null, $auto_increment, $comment)
			$bk_ref_col_obj = QSqlModelInfoProperty::SetupSqlColumn(QSqlTableIndex::IndexNormal, $db_table, $bk_ref_name, QSqlTableColumn::TypeInt, null, null, $coll_bk_ref_default, null, null, true, $coll_bk_ref_null, false, $comment);
		}
		
		$this->db_table = $db_table;
		$this->getEntityRoot()->_used_tables[$full_tname] = $db_table;
		
		return $db_table;
	}
	
	/**
	 * Setups/syncs the data structure
	 * 
	 * @param QSqlStorage $storage
	 * @param boolean $recursive
	 * 
	 */
	public function setup(QSqlModelInfoType $root, QSqlStorage $storage, $recursive = false, QModelAcceptedType $collection_types = null)
	{
		if ($this->_setup_done)
			return;
		$this->_setup_done = true;
		
		// create/sync table & rowid|id columns
		$db_table = $this->setupTable($storage);
		
		echo "Setup type: ".$this->type." | {$db_table->parent->name}.{$db_table->name}<br/>\n";
		
		if ($this->type->isCollection())
		{
			$collection_types = $this->type;
			
			$parent_property = $this->parent->property;
			$one_to_many = $parent_property->isOneToMany();
			
			$is_multi = $collection_types ? $collection_types->isMultiType() : false;
			$needs_ref_column = $collection_types ? $collection_types->hasReferenceType() : false;
			
			// for collection we have different rules
			if (!$one_to_many)
			{
				$columns_setup = $this->parent->setupPropertyTypes($root, $recursive, $collection_types->options, $db_table, $storage, $types_on_prop, $this, $needs_ref_column);
				
				// Setup unique index for many to many collections with reference type(s)
				// here is where we have to act : // $find_by_uniqueness 
				if ($this->type->hasAnyInstantiableReferenceTypes())
				{
					// $find_by_uniqueness 
					/*
					$index_name = "uniqueness";
					if (!$db_table->indexes[$index_name])
					{
						$index = new QSqlTableIndex($db_table, QSqlTableIndex::IndexUnique, $index_name);
						$db_table->indexes->set($index_name, $index);
					}
					else
					{
						$index = $db_table->indexes[$index_name];
						$index->set("type", QSqlTableIndex::IndexUnique);
					}
					 */
					
					$bk_ref_name = $this->parent->property->getCollectionBackrefColumn();
					$rowid_name = $this->parent->property->getCollectionForwardColumn();
					
					$bk_ref_col_obj = $db_table->columns[$bk_ref_name];
					$rowid_col_obj = $db_table->columns[$rowid_col_obj];

					/*
					if (!$index->columns[$bk_ref_name])
					{
						if (!$index->columns)
							$index->columns = new QModelArray();
						$index->columns->set($bk_ref_name, $bk_ref_col_obj);
					}
					if (!$index->columns[$rowid_name])
					{
						if (!$index->columns)
							$index->columns = new QModelArray();
						$index->columns->set($rowid_name, $rowid_col_obj);
					}
					*/
				}
			}
			
			// safe one to many 
			$count_possible_types = 0;
			$possible_tables = null;
			
			// var_dump();
			$full_collection_opts = $collection_types->getAllReferenceTypes();
			
			foreach ($full_collection_opts as $c_type)
			{
				$count_possible_types++;
				
				if (($_i_ty = QModel::GetTypeByName($c_type)) instanceof QModelType)
				{
					// safe one to many 
					if ($one_to_many)
					{
						$possib_table_name = self::GetTableNameForType($c_type);
						
						// echo "oneToMany test for: ".$this->parent->property." => [{$possib_table_name}] : [{$possible_tables}]\n";
						if ($possible_tables && ($possible_tables != $possib_table_name))
						{
							// we don't add the type
							// throw new Exception("You can not have multiple tables in a oneToMany collection in ".$this->parent->property.".\nTables: {$possible_tables} != {$possib_table_name}");
							$count_possible_types--;
						}
						else
							$possible_tables = $possib_table_name;
					}
					
					if ($_i_ty->is_interface || $_i_ty->is_abstract)
						$count_possible_types--;
				}
			}
			
			// echo "Possible types for: ".$this->parent->property->parent->class.".".$this->parent->property->name." : {$count_possible_types}\n";
			
			if ($is_multi && (!$one_to_many))
			{
				// echo "2. We decide to setup type column for: {$this->parent->property->getId()}\n";
				$this->parent->setupMoreTypesColumn($root, $db_table, $storage, $columns_setup, $this);
			}
			else
			{
				if (!$one_to_many)
				{
					$full_tname = $parent_property->getCollectionTableName(); // $db_table->parent->name.".".$db_table->name;
					$rowid_name = $parent_property->getCollectionForwardColumn();
					
					if (!$root->_multitype[$full_tname][$rowid_name])
					{
						$coll_type = $parent_property->getCollectionType();
						$ref_type = $coll_type->hasAnyInstantiableReferenceTypes() ? reset($coll_type->getAllInstantiableReferenceTypes()) : null;
						// $ref_type ? array($storage->getTypeIdInStorage($ref_type), $ref_type) : $coll_type->options;
						$root->_multitype[$full_tname][$rowid_name] = $ref_type ? array($storage->getTypeIdInStorage($ref_type), $ref_type) : array_values($coll_type->options);
					}
				}
			}
			
			if ($this->children)
			{
				foreach ($this->children as $accty_child)
					$accty_child->setup($root, $storage, $recursive);
			}
		}
		else
		{
			$ty = $this->type;
			
			
			foreach ($ty->properties as $property)
			{
				// cheat, make Id props use the _id column atm
				if (strtolower($property->name) == "id")
					continue;
				
				// @storage.query does not need a table
				if ($property->storage["query"] || $property->storage["none"])
					continue;
				
				//var_dump("Setup property: ".$property);
				
				$mi_prop = $this->setEntityProperty($property);
				$mi_prop->setup($root, $storage, $db_table, $recursive);
				
				if ($mi_prop->children)
				{
					foreach ($mi_prop->children as $child)
					{
						$child->setup($root, $storage, $recursive);
					}
				}
			}
		}
	}
	
	public function setEntityProperty(QModelProperty $property)
	{
		if ($property->storage["none"])
			return null;
		
		$prop = $this->children[$property->name];
		if (!$prop)
		{
			$prop = new QSqlModelInfoProperty();
			$prop->property = $property;
			$prop->parent = $this;
			$this->children[$property->name] = $prop;
			
			// var_dump("".$property);
			
			$types = $property->types;
			
			if (is_string($types))
			{
				if ($types{0} !== strtolower($types{0}))
					$this->setEntityType_under($types, $prop);
			}
			else if (qis_array($types))
			{
				foreach ($types as $type)
				{
					if ($type instanceof QModelAcceptedType)
						$this->setAcceptedType($type, $prop);
					else if ($type{0} !== strtolower($type{0}))
						$this->setEntityType_under($type, $prop);
				}
			}
			else if ($types instanceof QModelAcceptedType)
			{
				$this->setAcceptedType($types, $prop);
			}
		}
		return $prop;
	}
	
	public function setAcceptedType(QModelAcceptedType $acc_type, QSqlModelInfoProperty $prop)
	{
		$acc_ty = new QSqlModelInfoType();
		$acc_ty->type = $acc_type;
		$acc_ty->parent = $prop;
		$prop->children["[]"] = $acc_ty;
		
		// var_dump("set the acc type");
		
		foreach ($acc_type->options as $opt)
		{
			if ($opt{0} !== strtolower($opt{0}))
				$this->setEntityType_under($opt, $acc_ty);
		}
	}
	
	public function setEntityType_under($type, $parent)
	{
		$root = $this->getEntityRoot();
		$elem_ty = $root->_cache[$type];
		if (!$elem_ty)
		{
			$posib_types = self::PossibleTypes($type);
			if ($posib_types)
			{
				foreach ($posib_types as $p_k => $pty)
				{
					$child = $root->_cache[$pty->class];
					if (!$child)
					{
						$child = new QSqlModelInfoType();
						if (is_array($pty))
						{
							var_dump($p_k, self::PossibleTypes($type )[$p_k]);
							die("soon of a chese");
						}
						$child->type = $pty;
						if ($parent)
						{
							$child->parent = $parent;
							$parent->children[$pty->class] = $child;
						}
						
						$root->_cache[$pty->class] = $child;
						
						if ($pty->properties)
						{
							foreach ($pty->properties as $prop)
							{
								$child->setEntityProperty($prop);
							}
						}
					}
				}
			}
		}
	}
	
	public function init($recursive = false)
	{
		parent::init($recursive);
		
		if (!($this->type instanceof QModelAcceptedType))
		{
			/*if (!$this->getEntityRoot())
				throw new Exception("darm");*/
			// echo "setting: ".$this->type ." = ".$this->type."<br/>\n";
			$this->getEntityRoot()->_all_types[(string)$this->type] = $this;
		}
	}
	
	public static function CreateRoot($child_type = null, $child_class = "QSqlModelInfoType")
	{
		$root = new QSqlModelInfoType();
		$root->type = QModel::GetTypeByName($child_type);
		$root->root = $root;
		$root->_traversed = array();
		$root->_used_tables = array();
		$root->_tableTypeList = array();
		$root->_all_types = array();
		$root->_dbs_list = array();
		
		$root->_cache[$root->type->class] = $root;
		
		foreach ($root->type->properties as $prop)
		{
			// var_dump("".$prop);
			$root->setEntityProperty($prop);
		}
		
		return $root;
	}
	
	public static function PossibleTypes($type)
	{
		$ret = array();
		$ext = QAutoload::GetClassExtendedBy($type);
		if ($ext === null)
			$ext = array($type);
		array_unshift($ext, $type);
		foreach ($ext as $class)
		{
			$ty = QModel::GetTypeByName($class);
			if (!($ty->is_abstract || $ty->is_interface))
				$ret[$class] = $ty;
		}
		
		return $ret;
	}
	
	/**
	 * Gets the root entity
	 * 
	 * @return QSqlModelInfoType
	 */
	public function getEntityRoot()
	{
		if (!$this->root)
		{
			$parent = $this;
			while ($parent->parent)
				$parent = $parent->parent;
			$this->root = $parent;
		}
		return $this->root;
	}
	
	/**
	 * Tests if this is the root entity
	 * 
	 * @return boolean
	 */
	public function isRootEntity()
	{
		return $this->root === $this;
	}
	
	/**
	 * Calls for a resync of the data structure
	 * 
	 */
	public static function ResyncDataStructure($storage = null)
	{
		$storage = $storage ?: QApp::GetStorage();
		# if (!$storage)
		#	throw new Exception("Unable to find default storage");
		$data_class = QApp::GetDataClass();
		$Data = new $data_class;
		if (!$Data)
			throw new Exception("Unable to find startup data");
		
		$storage->ensureTypeIdsWasIncluded(true);
		
		// refresh the mapping

		QSqlModelInfoType::RefreshTypeTableList();
		self::$PropertiesWithTypes = array();
		self::$PropertiesWithRefs = array();
		
		// start with the properties in the $Data and continue
		
		$mi = QSqlModelInfoType::CreateRoot(get_class($Data), "QSqlModelInfoType");		
		$mi->setup($mi, $storage, true);
		
		// $db = $storage->getDefaultDatabase();
		echo "<pre>";
		
		ob_start();
		foreach ($mi->_dbs_list as $db)
		{
			if (isset($db->children))
			{
				foreach ($db->children as $table)
				{
					// echo $table->name."<br/>";
					$storage->syncTable($table);
				}
			}
		}
		$sql_statements = ob_get_clean();
		echo $sql_statements;
		
		echo "</pre>";
		
		$str = qArrayToCode($mi->_multitype, "Q_MultiType_", true);
		file_put_contents(QAutoload::GetRuntimeFolder()."temp/sql_colstype_info.php", $str);
		
		self::SaveSqlInfo(self::$TablePropertyTypesList);
		
		return $sql_statements;
	}
	
	public static function GetColumnsTypeInfo()
	{
		if (self::$ColumnsTypeInfo === null)
		{
			include(QAutoload::GetRuntimeFolder()."temp/sql_colstype_info.php");
			self::$ColumnsTypeInfo = $Q_MultiType_;
		}
		
		return self::$ColumnsTypeInfo;
	}
	
	/**
	 * Gets the table name for a certain data type.
	 * 
	 * @param string $type
	 * 
	 * @return string
	 */
	public static function GetTableNameForType($type)
	{
		self::LoadTableTypeCache();
		return self::$TableTypeList[(string)$type];
	}
	
	/**
	 * Gets the table name for a property's collection
	 * 
	 * @param string $type_name
	 * @param string $propery_name
	 * 
	 * @return string
	 */
	public static function GetTableNameForCollectionProperty($type_name, $propery_name)
	{
		self::LoadTableTypeCache();
		return self::$TablePropertyList[$type_name.".".$propery_name];
	}
	
	/**
	 * Gets the table name for a property's collection
	 * 
	 * @param string $identifier
	 * 
	 * @return array[]
	 */
	public static function GetTablesForPropertyTypes($identifier)
	{
		self::LoadTableTypeCache();
		return self::$TablePropertyTypesList[$identifier];
	}
	
	public static function GetReverseTablesForPropertyTypes(string $table_name)
	{
		self::LoadTableTypeCache();
		if (self::$TablePropertyTypesList_Reverse === null)
		{
			foreach (self::$TablePropertyTypesList ?: [] as $class_name_prop => $table_to_types)
			{
				list($class_n, $prop_n) = explode(".", $class_name_prop);
				$prop_n = rtrim($prop_n, '[]');
				foreach ($table_to_types as $t => $types)
				{
					foreach ($types as $ty)
						self::$TablePropertyTypesList_Reverse[$t][$class_n][$prop_n][$ty] = $ty;
				}
			}
		}
		return self::$TablePropertyTypesList_Reverse[$table_name];
	}

	/**
	 * Refreshed the self::$TypeTableList and self::$TablePropertyList
	 * 
	 * @param QModelType $m_type
	 * @param array[] $extend_by
	 * 
	 * @throws Exception
	 */
	public static function RefreshTypeTableList(QModelType $m_type = null, &$extend_by = null, &$types_done = null)
	{
		$root_q = $m_type ? false : true;

		if (!$extend_by)
			$extend_by = QAutoload::GetExtendedByList();
		
		if (!$m_type)
		{
			// QAutoload::ScanForChanges();
			$data = QApp::Data();
			if (!$data)
				throw new Exception("Unable to find the start data object QApp::Data()");
			$m_type = $data->getModelType();
			self::$TableTypeList = array();
			self::$TablePropertyList = array();
			self::$PropertiesWithTypes = array();
			self::$PropertiesWithRefs = array();
			
			if (isset($m_type->storage["table"]) && is_string($m_type->storage["table"]) && (strlen(trim($m_type->storage["table"])) > 0))
				self::$TableTypeList[$m_type->class] = trim($m_type->storage["table"]);
			
			// no more auto tables !!!
			// else
			//	self::$TableTypeList[$m_type->class] = $m_type->class;
			
			if (!$types_done)
				$types_done = array();
		}
		
		if ($types_done[$m_type->class])
			return;
		$types_done[$m_type->class] = true;
		
		foreach ($m_type->properties as $prop)
		{
			if ($prop->storage["none"])
				continue;
			
			$has_collection = false;
			// first identify the type(s)
			$types = array();
			// $coll_types = array();
			if (is_string($prop->types))
			{
				try
				{
					$tmp_type = QModel::GetTypeByName($prop->types);
				}
				catch (Exception $ex)
				{
					var_dump($prop->getId());
					throw new Exception("Unable to get type by name: ".$prop->types);
				}
				if ($tmp_type instanceof QModelType)
					$types[$prop->types] = $tmp_type;
			}
			else if ($prop->types instanceof QModelAcceptedType)
			{
				$has_collection = true;
				foreach ($prop->types->options as $p_type)
				{
					$tmp_type = QModel::GetTypeByName($p_type);
					if ($tmp_type instanceof QModelType)
						$types[$p_type] = $tmp_type;
				}
			}
			else if (qis_array($prop->types))
			{
				foreach ($prop->types as $ty)
				{
					if (is_string($ty))
					{
						try
						{
							$tmp_type = QModel::GetTypeByName($ty);
						}
						catch (Exception $ex)
						{
							var_dump("Error for: ".$m_type->class."::".$prop->name);
							throw $ex;
						}
						if ($tmp_type instanceof QModelType)
							$types[$ty] = $tmp_type;
					}
					else if ($ty instanceof QModelAcceptedType)
					{
						$has_collection = true;
						foreach ($ty->options as $p_type)
						{
							$tmp_type = QModel::GetTypeByName($p_type);
							if ($tmp_type instanceof QModelType)
								$types[$p_type] = $tmp_type;
						}
					}
				}
			}
			
			$recurse_for = array();
			// all types are forced to that table
			foreach ($types as $class_name => $type)
			{
				$specified = false;
				if (!$type)
					throw new Exception("Missing type for class: ".$class_name);
				
				if ($type->isCollection())
					// this should not happen but just to be sure
					continue;

				$type_extended_by = $extend_by[$class_name];
				$type_extended_by[$class_name] = $class_name;
				
				foreach ($type_extended_by as $ty_name)
				{
					if (isset(self::$TableTypeList[$ty_name]))
						continue;
					// echo "{$class_name} is Extendedby: ".$ty_name."\n";
					$ty_ex = QModel::GetTypeByName($ty_name);
					if ($ty_ex->is_interface)
						continue;
					
					$f_table_name = null;
					if ($root_q)
					{
						// only for roots will we set this
						if (isset($prop->storage["table:".$ty_name]) && is_string($prop->storage["table:".$ty_name]) && (strlen(trim($prop->storage["table:".$ty_name])) > 0))
							$f_table_name = trim($prop->storage["table:".$ty_name]);
						else if (isset($prop->storage["table"]) && is_string($prop->storage["table"]) && (strlen(trim($prop->storage["table"])) > 0))
							$f_table_name = trim($prop->storage["table"]);
					}

					if ($f_table_name)
						$table_name = $f_table_name;
					else
					{
						if (isset($ty_ex->storage["table"]) && is_string($ty_ex->storage["table"]) && (strlen(trim($ty_ex->storage["table"])) > 0))
						{
							$table_name = trim($ty_ex->storage["table"]);
							$specified = true;
						}
						else
						{
							// if it's neither forced or specified we assume that there is no need to create a table for that type
							continue;
						}
					}

					if ($specified && isset(self::$TableTypeList[$class_name]) && (self::$TableTypeList[$class_name] != $table_name))
					{
						throw new Exception("Different table name specified in diferent locations for type: ".$class_name." [".self::$TableTypeList[$class_name]." != {$table_name}]");
					}
					
					// echo "SET TABLE {$table_name} FOR {$ty_name}\n";
					self::$TableTypeList[$ty_name] = $table_name;
					$recurse_for[$ty_name] = $ty_ex;
				}
			}
			
			if ($recurse_for)
			{
				foreach ($recurse_for as $r_type)
					self::RefreshTypeTableList($r_type, $extend_by, $types_done);
			}
		}
		
		if ($root_q)
		{
			self::$TablePropertyList = array();
			// now we need to get data for collections
			foreach (self::$TableTypeList as $class_name => $table)
			{
				$c_type = QModel::GetTypeByName($class_name);
				foreach ($c_type->properties as $prop)
				{
					if ($prop->storage["none"])
						continue;
					
					$acc_type = $prop->getCollectionType();
					if (!$acc_type)
						// not a collection
						continue;

					$key = $c_type->class.".".$prop->name;
					if ($prop->isOneToMany())
					{
						// echo "prop: ".$prop."<br/>";
						// get the one to many table(s)
						// throw error if more than one target tables
						$t_name = null;
						foreach ($acc_type->options as $opt)
						{
							$ext_list = QAutoload::GetClassExtendedBy($opt);
							if ($ext_list)
								array_unshift($ext_list, $opt);
							else
								$ext_list = array($opt);

							foreach ($ext_list as $sc_name)
							{
								$st_name = self::GetTableNameForType($sc_name);
								
								// var_dump($sc_name, $st_name, $t_name, $prop->parent->getTableName(), self::GetTableNameForType($sc_name));
								// echo "<hr/>";
								
								if (!$t_name)
									$t_name = $st_name;
								else if ($st_name && ($st_name !== $t_name))
								{
									// if the CLASSES have the same table
									/*
									if ($prop->parent->getTableName() === self::GetTableNameForType($sc_name))
									{
										// var_dump($key, $ext_list, self::GetTableNameForType("QCategory"), self::GetTableNameForType($sc_name));
										throw new Exception("When oneToMany is specified in a property all types must point to the same table. See in: ".$class_name.".".$prop->name.". Compared to `{$sc_name}`");
									}
									 */
									// TO DO : we should throw this error on INSERT/UPDATE also
								}
							}
						}
						if (!$t_name)
							throw new Exception("Unable to find table for oneToMany collection in: ".$class_name.".".$prop->name);
					}
					else
					{
						$psc = $prop->storage["collection"];
						if (!empty($psc))
						{
							if (is_string($psc))
								$psc = $prop->storage["collection"] = explode(",", $psc);
							$t_name = $psc[0];
						}
						else
							$t_name = $table."_".$prop->name;
					}
					
					self::$TablePropertyList[$key] = $t_name;
				}
			}
			
			// self::SaveSqlInfo($types_done);
			self::$TablePropertyTypesList = $types_done;
			// include(self::$RuntimeFolder."temp/autoload.php");
			// self::$AutoloadArray = $_Q_FRAME_LOAD_ARRAY;
			// self::$AutoloadArrayKeys = null;
			// self::$AutoloadIncluded = true;
		}
	}
	
	public static function GetTableTypes($table_name)
	{
		self::LoadTableTypeCache();
		return self::$TableToTypes[$table_name];
	}

	public static function CacheSqlData($className, $cache_path = null)
	{
		if (!$cache_path)
			$cache_path = QAutoload::GetRuntimeFolder()."temp/sql/".qClassToPath($className).".type.php";
		else if (strpos($cache_path, "\\") !== false)
			throw new Exception("Not converted ok");
		
		$dir = dirname($cache_path);
		if (!is_dir($dir))
			mkdir($dir, 0755, true);
		
		$array = array();
		$type = QModel::GetTypeByName($className);
		if (!$type)
		{
			if (qIsA($className, "QIModel"))
				throw new Exception("Invalid type for {$className}");
			return null;
		}
		
		if (($err_message = $type->checkDataModelIntegrity()) !== true)
			throw new Exception($err_message);
		
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();
		$cols_inf = $cols_type_inf[QSqlModelInfoType::GetTableNameForType($className)];
		// var_dump($cols_inf);
		
		// prepare tables -> types
		$ext_by = QAutoload::GetClassExtendedBy($className);
		if ($ext_by)
			array_unshift($ext_by, $className);
		else
			$ext_by = array($className);
		
		// $storage = QApp::GetStorage();

		// $possib_type_ids = array();
		$tables = array();
		foreach ($ext_by as $c)
		{
			if (interface_exists($c))
				continue;
			
			$table = QSqlModelInfoType::GetTableNameForType($c);
			if ($table)
			{
				$ext_cols_type_inf = $cols_type_inf[$table];
				
				$tables[$table]["#"][$c] = $c;
				$tables[$table]["id"] = $type->getIdCn();
				$tables[$table]["ty"] = $ext_cols_type_inf ? $ext_cols_type_inf[$type->getIdCn()] : null;
				// $possib_type_ids[] = $storage->getTypeIdInStorage($);
			}
		}
		
		$array["#%tables"] = $tables;
		$array["#%table"] = QSqlModelInfoType::GetTableNameForType($className);
		
		$array["#%id"] = array($type->getIdCn(), $cols_inf ? $cols_inf[$type->getIdCn()] : null);
		
		$array["#%misc"]["model"] = $type->model;
		if ($type->storage["mergeBy"])
			$array["#%misc"]["mergeBy"] = $type->storage["mergeBy"];
		
		if ($type->properties)
		{
			foreach ($type->properties as $prop_name => $prop)
			{
				if ($prop->storage["none"])
					continue;
				
				$array[$prop_name] = array();
				
				if ($prop->storage["dims"])
					$array[$prop_name]["dims"] = $prop->storage["dims"];
				
				if ($prop->hasReferenceType())
				{
					$array[$prop_name]["rc"] = $prop->getRefColumnName();
					if ($cols_inf && ($rcty = $cols_inf[$prop->getColumnName()]))
						$array[$prop_name]["rc_t"] = $rcty;
				}
				if ($prop->hasScalarType())
				{
					$array[$prop_name]["vc"] = $prop->getColumnName();
					if ($cols_inf && ($rcty = $cols_inf[$prop->getColumnName()]))
						$array[$prop_name]["rc_t"] = $rcty;
				}
				if ($prop->hasCollectionType())
				{
					$array[$prop_name]["o2m"] = $prop->isOneToMany();
					if (!$prop->isOneToMany())
						$array[$prop_name]["cid"] = $prop->getCollectionTableRowId();
				}
				
				$use_types = qis_array($prop->types) ? $prop->types : array($prop->types);
				
				foreach ($use_types as $ty)
				{
					if ($ty instanceof QModelAcceptedType)
					{
						$opt_types = array();
						$opt_joins = array();
						$first_join_table = null;
						foreach ($ty->options as $opt)
						{
							if (strtolower($opt{0}) !== $opt{0})
							{
								$ext_by = $ty->strict ? null : QAutoload::GetClassExtendedBy($opt);
								$ext_by ? array_unshift($ext_by, $opt) : ($ext_by = array($opt));
								foreach ($ext_by as $e)
								{
									if (interface_exists($e))
										continue;
									$opt_mty = QModel::GetTypeByName($e);
									if ($opt_mty->is_abstract || (!$opt_mty->storage["table"]))
										continue;
									
									$join_table = QSqlModelInfoType::GetTableNameForType($e);
									if ($prop->isOneToMany())
									{
										// in a one to many only include types that go to the same table
										if ((!$first_join_table) || ($first_join_table === $join_table))
										{
											$first_join_table = $join_table;
											$opt_types[$e] = $e;
										}
									}
									else
									{
										$opt_types[$e] = $e;
										$opt_joins[$join_table][$e] = $e;
									}
								}
								
								$array[$prop_name]["[]"]["#"][$opt] = $opt;
							}
							else
								$array[$prop_name]["[]"]["\$"][$opt] = $opt;
						}
						
						if ($opt_types)
							$array[$prop_name]["[]"]["refs"] = $opt_types;
						if ($opt_joins)
							$array[$prop_name]["[]"]["j"] = $opt_joins;
						
						$coll_tab = $prop->getCollectionTableName();
						$coll_bkref = $prop->getCollectionBackrefColumn();
						
						// var_dump($cols_type_inf[$coll_tab]);
						
						$array[$prop_name]["cb"] = $coll_bkref; //array($coll_bkref, $cols_type_inf[$coll_tab][$coll_bkref]);
						if ($ty->hasReferenceType())
						{
							$coll_fwd = $prop->isOneToMany() ? $prop->parent->getIdCn() : $prop->getCollectionForwardColumn();
							$array[$prop_name]["cf"] = array($coll_fwd, $cols_type_inf[$coll_tab][$coll_fwd]);
						}
						if ($ty->hasScalarType())
						{
							$coll_val = $prop->getCollectionValueColumn();
							$array[$prop_name]["cv"] = array($coll_val, $cols_type_inf[$coll_tab][$coll_val]);
						}
						
						$array[$prop_name]["ct"] = $coll_tab;
					}
					else
					{
						if (strtolower($ty{0}) !== $ty{0})
						{
							$ext_by = $prop->strict ? null : QAutoload::GetClassExtendedBy($ty);
							$ext_by ? array_unshift($ext_by, $ty) : ($ext_by = array($ty));
							foreach ($ext_by as $e)
							{
								if (interface_exists($e))
									continue;
								$opt_mty = QModel::GetTypeByName($e);
								if ($opt_mty->is_abstract || (!$opt_mty->storage["table"]))
									continue;
								
								$array[$prop_name]["refs"][$e] = $e;
								
								$join_table = QSqlModelInfoType::GetTableNameForType($e);
								$array[$prop_name]["j"][$join_table][$e] = $e;
							}
							
							$array[$prop_name]["#"][$ty] = $ty;
						}
						else
							$array[$prop_name]["\$"][$ty] = $ty;
					}
				}
			}
		}
		
		qArrayToCodeFile($array, "\$Q_SQL_TYPE_CACHE", $cache_path);
	}
	
	
	protected static function SaveSqlInfo($types_done)
	{
		$s = "<?php\n\n";
		$s .= "\$_Q_FRAME_TABLE_TYPE = array(";
		
		self::$TableToTypes = array();
		
		foreach (self::$TableTypeList as $k => $v)
		{
			$s .= "\"".preg_replace("/\\$/", "\\\\$", addslashes ($k))."\" => \"".preg_replace("/\\$/", "\\\\$", addslashes ($v))."\",\n";
			self::$TableToTypes[$v][] = $k;
		}
		$s .= ");\n\n";
		
		$s .= qArrayToCode(self::$TableToTypes, "_Q_FRAME_TABLE_TO_TYPES", false);
		
		$s .= "\n\n";
		
		$s .= "\$_Q_FRAME_TABLE_PROPERTY = array(";
		foreach (self::$TablePropertyList as $k => $v)
			$s .= "\"".preg_replace("/\\$/", "\\\\$", addslashes ($k))."\" => \"".preg_replace("/\\$/", "\\\\$", addslashes ($v))."\",\n";
		$s .= ");\n";

		$s .= self::RefreshPropertiesTableInfo($types_done ? array_keys($types_done) : null);
		
		$s .= "\$_Q_FRAME_PROPERTIES_TYPES = array(";
		foreach (self::$PropertiesWithTypes as $k => $v)
			$s .= "\"".preg_replace("/\\$/", "\\\\$", addslashes ($k))."\" => ".($v ? "true" : "false").",\n";
		$s .= ");\n";
		
		$s .= "\$_Q_FRAME_PROPERTIES_REFS = array(";
		foreach (self::$PropertiesWithRefs as $k => $v)
			$s .= "\"".preg_replace("/\\$/", "\\\\$", addslashes ($k))."\" => ".($v ? "true" : "false").",\n";
		$s .= ");\n";

		$s .= "\n?>";
		
		// $root->_multitype

		if (!QAutoload::GetRuntimeFolder())
			throw new Exception("There is no runtime folder set and no watch folders defined");

		file_put_contents(QAutoload::GetRuntimeFolder()."temp/sql_model_info.php", $s);
		
		
		// CacheSqlData($className, $cache_path = null)
		// var_dump(self::$TableTypeList);
		foreach (self::$TableTypeList as $className => $table)
		{
			self::CacheSqlData($className);
		}

	}

	public static function GetTableTypeList()
	{
		self::LoadTableTypeCache();
		return self::$TableTypeList;
	}
	
	public static function GetTableToTypesList()
	{
		self::LoadTableTypeCache();
		return self::$TableToTypes;
	}
	
	public static function GetTablePropertyList()
	{
		self::LoadTableTypeCache();
		return self::$TablePropertyList;
	}
	
	private static function LoadTableTypeCache($forced = false)
	{
		if ($forced || is_null(self::$TablePropertyList))
		{
			include(QAutoload::GetRuntimeFolder()."temp/sql_model_info.php");
			self::$TableTypeList = $_Q_FRAME_TABLE_TYPE;
			self::$TableToTypes = $_Q_FRAME_TABLE_TO_TYPES;
			self::$TablePropertyList = $_Q_FRAME_TABLE_PROPERTY;
			self::$TablePropertyTypesList = $_Q_FRAME_PROPS_TYPES_TABLES;
			self::$PropertiesWithTypes = $_Q_FRAME_PROPERTIES_TYPES;
			self::$PropertiesWithRefs = $_Q_FRAME_PROPERTIES_REFS;
		}
	}
	
	public static function RefreshPropertiesTableInfo($classes)
	{
		if ($classes)
		{
			$cache = array();
			foreach ($classes as $class)
			{
				$type = QModel::GetTypeByName($class);
				if ((!$type) || ($type instanceof QIModelTypeUnstruct) || $type->is_interface || $type->isCollection())
					continue;

				// echo "RefreshPropertiesTableInfo:: {$class}\n";
				foreach ($type->properties as $prop)
				{
					if ($prop->storage["none"])
						continue;
					
					$types = $prop->types;
					if (!$types)
						continue;
					if (!qis_array($types))
						$types = array($types);

					$p_id = $prop->getId();
					$ref_types = array();
					$ref_types_coll = array();
					foreach ($types as $ty)
					{
						if ($ty instanceof QModelAcceptedType)
						{
							foreach ($ty->options as $acc_ty)
							{
								if (strtolower($acc_ty{0}) == $acc_ty{0})
									continue;
								$ref_types_coll[] = $acc_ty;
							}
						}
						else if (is_string($ty))
						{
							if (strtolower($ty{0}) == $ty{0})
								continue;
							$ref_types[] = $ty;
						}
						else
							throw new Exception("Invalid type for: ".$p_id);
					}

					if (!empty($ref_types))
					{
						$ret = self::ExtractPossibleStorageContainersForTypes($ref_types);
						if (!empty($ret))
							$cache[$p_id] = $ret;
					}
					if (!empty($ref_types_coll))
					{
						$ret = self::ExtractPossibleStorageContainersForTypes($ref_types_coll);
						if (!empty($ret))
							$cache[$p_id."[]"] = $ret;
					}
				}
			}
			self::$TablePropertyTypesList = $cache;
		}
		else
			$cache = self::$TablePropertyTypesList;
		
		$s = "\$_Q_FRAME_PROPS_TYPES_TABLES = array(\n";
		$p1 = 0;
		foreach ($cache as $prop_key => $data)
		{
			if ($p1 > 0)
				$s .= ",\n";
			$s .= "\"". preg_replace("/\\$/", "\\\\$", addslashes($prop_key))."\" => array(";
			$p2 = 0;
			foreach ($data as $table => $types)
			{
				if ($p2 > 0)
					$s .= ",";
				$p3 = 0;
				$s .= "\"". preg_replace("/\\$/", "\\\\$", addslashes($table))."\" => array(";
				foreach ($types as $class)
				{
					if ($p3 > 0)
						$s .= ",";
					$s .= "\"". preg_replace("/\\$/", "\\\\$", addslashes($class))."\"";
					$p3++;
				}
				$s .= ")";
				$p2++;
			}
			$s .= ")";
			$p1++;
		}
		$s .= ");\n\n";

		return $s;
	}
	
	/**
	 * Based on a list of provided types (interfaces or classes), the method returns a list 
	 * of possible tables (containers) with a main type stored there and a list of 'other types 
	 * stored there' foreach table
	 * The list of 'other types stored there' only contains elements listed in $types
	 * 
	 * @param string[] $types
	 * @return array[]
	 */
	public static function ExtractPossibleStorageContainersForTypes($types)
	{
		if (!$types)
			return null;
		$tables = array();
		foreach ($types as $class)
		{
			$type = QModel::GetTypeByName($class);
			if ((!$type) || ($type instanceof QIModelTypeUnstruct) || $type->isCollection())
				continue;
			
			$ex_types = QAutoload::GetClassExtendedBy($class);
			if (!$type->is_interface)
			{
				if ($ex_types)
					array_unshift($ex_types, $class);
				else
					$ex_types = array($class);
			}
			if ($ex_types)
			{
				$one_set = array();
				foreach ($ex_types as $ex_class)
				{
					$tab = QSqlModelInfoType::GetTableNameForType($ex_class);
					if ($tab)
					{
						if ((!$one_set[$tab]) || $types[$ex_class])
						{
							$tables[$tab][$ex_class] = $ex_class;
							$one_set[$tab] = true;
						}
					}
				}
			}
		}
		
		return empty($tables) ? null : $tables;
	}

	public static function SetPropertyWithType($tag)
	{
		self::$PropertiesWithTypes[$tag] = true;
	}
	
	public static function SetPropertyWithRef($tag)
	{
		self::$PropertiesWithRefs[$tag] = true;
	}
	
	public static function GetPropertyWithType($tag)
	{
		return self::$PropertiesWithTypes[$tag];
	}
	
	public static function GetPropertyWithRef($tag)
	{
		return self::$PropertiesWithRefs[$tag];
	}	
}
