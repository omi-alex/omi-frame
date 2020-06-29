<?php

class QSqlModelInfoProperty 
{
	/**
	 * The model property
	 *
	 * @var QModelProperty
	 */
	public $property;
	/**
	 * The parent
	 *
	 * @var QSqlModelInfoType
	 */
	public $parent;
	/**
	 * The child accepted types
	 *
	 * @var QSqlModelInfoType[]
	 */
	public $children;
	
	public function setup(QSqlModelInfoType $root, QSqlStorage $storage, QSqlTable $db_table, $recursive = false)
	{
		if (strtolower($this->property) == "id")
			return;
		
		// echo "MI Property.setup: ".$this->property."<br/>\n";
		
		$types = $this->property->types;
		if (empty($types))
			throw new Exception("Invalid type for: ".$this->parent->type.".".$this->property);
		
		if (!qis_array($types))
			$types = array($types);
		$types_on_prop = 0;

		$acc_type = null;
		$refs_types = array();
		$has_interface = false;
		
		$is_multi = $this->property->isMultiType();
		$needs_ref_column = $this->property->hasReferenceType();
		
		foreach ($types as $ty)
		{
			if (is_string($ty))
			{
				$setup_pt[$ty] = $ty;
				$types_on_prop++;
				
				if (($_i_ty = QModel::GetTypeByName($ty)) instanceof QModelType)
				{
					$refs_types[$ty] = $_i_ty;
					$cc_nt = count($this->property->getAllReferenceTypes());
					if ($cc_nt > 0)
					{
						$types_on_prop += $cc_nt;
					}
					
					if ($_i_ty->is_interface)
					{
						$has_interface = true;
						// Decrease for interface 
						$types_on_prop--;
					}
				}
			}
			else if ($ty instanceof QModelAcceptedType)
			{
				// column null/not null for collection
				// $setup_pt["boolean"] = "boolean";
				
				$acc_type = $ty;
				// setup a collection
				$setup_collection[] = $ty;
				// $this->setupCollectionType($db_table, $storage);
				$has_collection = true;
			}
			else
			{
				throw new Exception("Unexpected data type pattern for property type definition");
			}
		}
		
		if ($has_interface && ($types_on_prop == 0))
		{
			// for a interface only type we should still set a column, even if it will not be used
			$types_on_prop = 1;
		}

		if ($has_collection)
			// collections only count as one type at the property level
			$types_on_prop++;
		
		if (!empty($setup_pt))
		{
			$columns_setup = $this->setupPropertyTypes($root, $recursive, $setup_pt, $db_table, $storage, $types_on_prop, $this, $needs_ref_column);
		}
		if (!empty($setup_collection))
		{
			// we need to setup collection table for these elements
			// $this->setupCollections($root, $recursive, $setup_collection, $db_table, $storage);
			// $mi_collection = $this->children["[]"];
			// $mi_collection->setup($root, $storage, $recursive, $acc_type);
		}
		if ($is_multi)
		{
			// echo "We decide to setup type column for: {$this->property->getId()}\n";
			$this->setupMoreTypesColumn($root, $db_table, $storage, $columns_setup, $this);
		}
		else
		{
			// $full_tname = $db_table->parent->name.".".$db_table->name;
			$rowid_name = $this->property->getColumnName();
			
			$orig_name = QSqlModelInfoType::GetTableNameForType($this->property->parent->class);
			
			if (!$root->_multitype[$orig_name][$rowid_name])
			{
				if (!$this->property->hasCollectionType())
				{
					$ref_type = $this->property->hasAnyInstantiableReferenceTypes() ? reset($this->property->getAllInstantiableReferenceTypes()) : null;
					
					$root->_multitype[$orig_name][$rowid_name] = $ref_type ? 
																	array($storage->getTypeIdInStorage($ref_type), $ref_type) : 
																	(is_array($this->property->types) ? $this->property->types : array($this->property->types));
				}
			}
		}
	}
	
	
	public function setupPropertyTypes(QSqlModelInfoType $root, $recursive, $types, QSqlTable $db_table, QSqlStorage $storage, $types_on_prop, $caller, $needs_ref_column)
	{
		$types_data = null;
		$types_data_no_model = null;
		$only_model_type = true;
		
		$d_types_model = array(QSqlTableColumn::TypeInt, QSqlTableColumn::TypeBigint);
		$has_ref_col = false;
		$has_scalar_col = false;
		
		$using_prop = ($caller instanceof QSqlModelInfoType) ? $caller->parent->property : $caller->property;
		$under_collection = ($caller instanceof QSqlModelInfoType);
		
		$force_type = null;
		if (!empty($using_prop->storage["type"]))
		{
			$force_type = $using_prop->storage["type"];
			// var_dump($using_prop->storage["type"]);
		}
		
		$dimensions = null;
		$__dimensions = $this->property ? $this->property->storage["dims"] : null;
		// "dims" => array( "lang", ),
		if ($__dimensions)
		{
			$dimensions = [];
			foreach ($__dimensions as $dim_key)
			{
				if (\QModel::GetDim($dim_key))
					$dimensions[] = $dim_key;
			}
		}
		
		foreach ($types as $_type)
		{
			$type = QModel::GetTypeByName($_type);
			if (!$type)
				throw new Exception("Unable to identify type: {$_type} in ".$using_prop->getId());

			if ($type instanceof QModelType)
			{
				$has_ref_col = true;
				$d_types = $d_types_model;
			}
			else if ($type instanceof QIModelTypeUnstruct)
			{
				$only_model_type = false;
				$has_scalar_col = true;
				// column for the scalar
				$d_types = $type->getSupportedSqlDataTypes();
				if (empty($d_types))
					throw new Exception("The data type `{$type->getTypeName()}` has no supported types");
			}

			if (!$types_data)
				$types_data = $d_types;
			else
				$types_data = array_intersect($types_data, $d_types);

			if (!($type instanceof QModelType))
			{
				if (!$types_data_no_model)
					$types_data_no_model = $d_types;
				else
					$types_data_no_model = array_intersect($types_data_no_model, $d_types);
			}
		}
		
		if ($under_collection)
		{
			$name = $using_prop->getCollectionValueColumn();
			if (!$name)
			{
				// var_dump($using_prop);
				throw new Exception("No column in collection: ".$using_prop);
			}
			$name_ref = $has_ref_col ? $using_prop->getCollectionForwardColumn() : null;
		}
		else
		{
			$name = $using_prop->getColumnName();
			$name_ref = $has_ref_col ? $using_prop->getRefColumnName() : null;
		}
		
		if (!$name)
		{
			var_dump($under_collection);
			throw new Exception($using_prop->getId());
		}
		
		if (empty($types_data) && empty($force_type) && empty($types_data_no_model))
			throw new Exception("At the moment only one column solution or one column and reference column are supported. See: ".$using_prop->parent->class.".".$using_prop->name);
		
		$force_type_name = null;
		$force_type_length = null;
		$force_type_values = null;
		if ($force_type)
			list($force_type_name, $force_type_length, $force_type_values) = $this->parseTypeData($force_type, $storage);
		
		$type = $force_type ? $force_type_name : (empty($types_data) ? $types_data_no_model[0] : $types_data[0]);
		$default = $using_prop->default; // property's default
		$unsigned = $only_model_type ? true : false;
		$null = true;
		$auto_increment = false;
		$comment = "Column for property value: ".$using_prop->name;

		$length = $force_type ? ($force_type_length ?: false) : self::GetDefaultColumnLength($type);
		$values = $force_type_values; // for enums / sets | TO DO in the future
		
		// if ($using_prop->name === "Message")
		// {
		// 	var_dump("Column for property value: ".$using_prop->name, $force_type, $force_type_length, $length);
		// }

		list($charset, $collation) = self::GetDefaultColumnCharsetAndCollation();
		
		$force_index_type = null;
		if ($using_prop->storage["unique"])
			$force_index_type = QSqlTableIndex::IndexUnique;
		else if ($using_prop->storage["index"])
			$force_index_type = QSqlTableIndex::IndexNormal;
		else if ($using_prop->storage["fulltext"])
			$force_index_type = QSqlTableIndex::IndexFullText;
		else if ($using_prop->storage["noindex"])
			$force_index_type = 0;
		
		$force_null = null;
		if ($using_prop->storage["notnull"])
			$force_null = $null = false;
		else if ($using_prop->storage["null"])
			$force_null = $null = true;
		$force_default = null;
		if ($using_prop->storage["default"] !== null)
		{
			$tmp_default = trim($using_prop->storage["default"]);
			if ($tmp_default === "")
				$tmp_default = true;
			else if ($tmp_default === "true")
				$tmp_default = true;
			else if ($tmp_default === "false")
				$tmp_default = true;
			else if (($tmp_default{0} === "\"") || ($tmp_default{0} === "'"))
				$tmp_default = substr($tmp_default, 1, -1);
			$force_default = $default = $tmp_default;
		}
		
		$force_unsigned = null;
		if ($using_prop->storage["unsigned"] !== null)
			$force_unsigned = $unsigned = $using_prop->storage["unsigned"];

		if ($has_ref_col)
		{
			// echo "We setup _REF_ column for: ".$using_prop->getId()." [".($caller instanceof QSqlModelInfoProperty ? "property" : "collection")."]\n";
			$rc_length = null;
			$rc_values = null;
			$rc_default = ($force_default !== null) ? $force_default : null;
			$rc_charset = null;
			$rc_collation = null;
			$rc_unsigned = ($force_unsigned !== null) ? $force_unsigned : true;
			$rc_null = ($force_null !== null) ? $force_null : true;
			$rc_auto_increment = false;
			
			self::SetupSqlColumn($force_index_type ?: QSqlTableIndex::IndexNormal, $db_table, $name_ref, QSqlTableColumn::TypeInt, 
								$rc_length, $rc_values, $rc_default, $rc_charset, $rc_collation, $rc_unsigned, $rc_null, $rc_auto_increment, 
								"Reference column for property value: ".$using_prop->name);
			// self::SetupSqlColumn(null, $db_table, $name, $type, $length, $values, $default, $charset, $collation, $unsigned, $null, $auto_increment, $comment);
			QSqlModelInfoType::SetPropertyWithRef($db_table->name.".".$using_prop->name.($under_collection ? "[]" : ""));
		}
		if ($has_scalar_col)
		{
			if ($dimensions)
			{
				$dims_count = count($dimensions);
				foreach ($dimensions as $dim_key)
				{
					$dim_vals = QModel::$DimsDef[$dim_key];
					if (!$dim_vals)
					{
						// var_dump($dimensions, $dim_key);
						// throw new Exception("Missing dim definition in:".$this->property);
						// dimension not defined
						continue;
					}
					foreach ($dim_vals as $dv)
					{
						$dim_name = $name . (($dims_count > 1) ? "_".$dim_key : "") . "_".$dv;
						$dim_comment = $comment .  " (dimension[{$dim_key}] : ".$dv.")";
						self::SetupSqlColumn($force_index_type, $db_table, $dim_name, $type, $length, $values, $default, $charset, $collation, $unsigned, $null, $auto_increment, $dim_comment);
					}
				}
			}
			else
			{
				$column = self::SetupSqlColumn($force_index_type, $db_table, $name, $type, $length, $values, $default, $charset, $collation, $unsigned, $null, $auto_increment, $comment);
			}
		}
		
		return array($name);
		
		/**
		 *		SCALARS			OBJECTS			RESOLUTION
		 *		============================================================
		 *		0				1+				Setup one column INT
		 *		1				0				Setup one column with the desired type from `getSupportedSqlDataTypes`
		 *		1				1+				?
		 *		2+				0				Intersect with the highest supported value from `getSupportedSqlDataTypes`
		 *		2+				1+				?
		 * 
		 *		After we `cook` this, we must know foreach types where it goes :(
		 */
		
	}
	
	protected function parseTypeData($data_str, QSqlStorage $storage)
	{
		$matches = null;
		$ok = preg_match_all("/([a-zA-Z0-9]+)(?:\\((.*)\\))?/", $data_str, $matches);
		if ($ok && $matches && $matches[1][0])
		{
			$type = $storage->decodeColumnType($matches[1][0]);
			
			$val = null;
			$len = null;
			
			$len_or_val = $matches[2][0];
			if ($len_or_val)
			{
				if (strpos($len_or_val, "'") === false)
					$len = (strpos($len_or_val, ",") === false) ? (integer)trim($len_or_val) : trim($len_or_val);
				else
					$val = trim($len_or_val);
			}
			
			return array($type, $len, $val);
		}
		else
			return array(null, null, null);
	}

	public static function SetupSqlColumn($index_type, QSqlTable $table, $name, $type, $length, $values, $default, $charset, $collation, $unsigned, $null, $auto_increment, $comment)
	{
		if (!is_string($name))
		{
			var_dump($name);
			throw new Exception("Missing name");
		}
		if (!$table->columns[$name])
		{
			$column = new QSqlTableColumn($table, $name);
			$table->columns->set($name, $column);
		}
		else
		{
			$column = $table->columns[$name];
			if (!$charset)
			{
				// $charset = $column->charset;
			}
			if (!$collation)
			{
				// $collation = $column->collation;
			}
			if ($length === null)
			{
				// not a good idea in case we switch types
				// $length = $column->length;
			}
			// now the default issue !
			if (($default === null) && (($column->default === QSqlNull::Get()) || ($column->default === null)))
				$default = $column->default;
		}
		
		$column->set(array(
				"type" => $type,
				"length" => $length, 
				"values" => $values,
				"default" => $default,
				"charset" => $charset,
				"collation" => $collation,
				"unsigned" => $unsigned,
				"null" => $null,
				"auto_increment" => $auto_increment,
				"comment" => $comment));

		$index_name = ($index_type == QSqlTableIndex::IndexPrimary) ? "PRIMARY" : $name;
		
		if ($index_type)
		{
			if (!$table->indexes[$index_name])
			{
				$index = new QSqlTableIndex($table, $index_type, $index_name);
				$table->indexes->set($index_name, $index);
			}
			else
			{
				$index = $table->indexes[$index_name];
				$index->set("type", $index_type);
			}
			
			if (!$index->columns[$name])
			{
				if (!$index->columns)
					$index->columns = new QModelArray();
				$index->columns->set($name, $column);
			}
		}
		else if ($index_type === 0)
		{
			unset($table->indexes[$index_name]);
		}
		
		return $column;
	}
	
	/**
	 * Setups the type column and the index needed
	 * 
	 * @param QSqlTable $db_table
	 * @param QSqlStorage $storage
	 * @param string[] $colums_setup
	 * @return QSqlTableColumn
	 */
	public function setupMoreTypesColumn(QSqlModelInfoType $root, QSqlTable $db_table, QSqlStorage $storage, $columns_setup, $caller)
	{
		$using_prop = ($caller instanceof QSqlModelInfoType) ? $caller->parent->property : $caller->property;
		$under_collection = ($caller instanceof QSqlModelInfoType);
		
		if ($under_collection && $using_prop->isOneToMany())
			throw new Exception("You have called for more types in a oneToMany collection: ".$using_prop->getId());
		
		$name = $under_collection ? $using_prop->getTypeCollectionForwardColumn() : $using_prop->getTypeColumnName();
		$comment = "type column role for property: ".$this->property;
		
		$in_default_db = ($storage->getDefaultDatabase()->name === $db_table->parent->name);
		$full_tname = ((!$in_default_db) ? $db_table->parent->name."." : "").$db_table->name;
		$rowid_name = $under_collection ? $using_prop->getCollectionForwardColumn() : $using_prop->getColumnName();
		
		$root->_multitype[$full_tname][$rowid_name] = $name;
		
		QSqlModelInfoType::SetPropertyWithType($db_table->name.".".$using_prop->name.($under_collection ? "[]" : ""));
		
		return QSqlModelInfoProperty::SetupSqlColumn(QSqlTableIndex::IndexNormal, $db_table, $name, QSqlTableColumn::TypeSmallint, null, null, null, null, null, true, true, false, $comment);
	}
	
	private static function GetDefaultColumnLength($type)
	{
		if ($type === QSqlTableColumn::TypeVarchar)
			return 255;
		else
			return null;
	}
	
	private static function GetDefaultColumnCharsetAndCollation()
	{
		// default for the table
		return null;
	}
	
}

