<?php

class QMySqlStorage extends QSqlStorage 
{
	use QMySqlStorage_GenTrait;
	
	/**
	 * Marks if type ids for this storage were included
	 *
	 * @var boolean
	 */
	private $_typeIdsIncluded = false;
	
	public $_typeIds = [];
	public $_typeIdsFlip = [];
	public $_typeIdsPath = [];
	
	/**
	 *
	 * @var mysqli
	 */
	public $connection;
	/**
	 *
	 * @var QSqlTransaction
	 */
	protected $transaction;
	
	protected static $ModelTypeIds;

	/**
	 * Connect the storage engine
	 *
	 * @param boolean $reconnect
	 */
	public function connect($reconnect = false)
	{
		if ($reconnect)
			$this->disconnect();
		
		$this->connection = new \QMySqlConnection($this->host, $this->user, $this->pass, $this->default_db, $this->port, $this->socket);
	}

	/**
	 * Disconects
	 *
	 */
	public function disconnect()
	{
		if ($this->connection)
			$this->connection->close();
	}
	
	public function escapeValue($value)
	{
		return $this->connection->real_escape_string($value);
	}
	
	/**
	 * Gets the default storage folder
	 *
	 * @return QStorageFolder
	 */
	public function getDefaultFolder()
	{
		return $this->getDefaultDatabase();
	}
	
	public function createStorageFolder($name, QStorageFolder $parent = null)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function createStorageFolderByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	/**
	 * Gets the child entries
	 *
	 * @return QIStorageEntry[]
	 */
	public function getChildEntries()
	{
		throw new Exception("Not implemented yet");
	}
	
	/**
	 * Gets the root folders of the storage
	 *
	 * @return QIStorageEntry[]
	 */
	public function getRootEntries()
	{
		// LIST DATABASES 
		throw new Exception("Not implemented yet");
	}
	
	public function getFolderByPath($path)
	{
		// we can only get databases
		throw new Exception("Not implemented yet");
	}
	
	public function getFoldersByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function getTableByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function getTablesByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function getObjectByPath($path)
	{
		return $this->getModelByPath($path);
	}
	
	public function getModelByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function getEntityByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	public function getByPath($path)
	{
		throw new Exception("Not implemented yet");
	}
	
	/*
	public function query(QStorageQuery $query)
	{
		// this will be a powerful query object
		throw new Exception("Not implemented yet");
	}
	 */
	
	/**
	 * Gets the full table information including indexes and fields, collation, charset, engine, etc ...
	 *
	 * @param string $database_name
	 * @param string $table_name
	 * @return QSqlTable
	 */
	public function getTableByName($table_name, $database = null)
	{
		if (!$database)
			$database = $this->getDefaultDatabase();
		$database_name = $database->name;
		
		$result = $this->connection->query("SHOW FULL COLUMNS FROM {$this->escapeName($database_name)}.{$this->escapeName($table_name)}");
		
		if (!$result)
			return null;
		
		$table = new QSqlTable($table_name, $database);
		$table->setTransformState(self::TransformNoAction);
		
		$fields = new QModelArray();
		$fields->setTransformState(self::TransformNoAction);
		// Field 	Type 	Collation 	Null 	Key 	Default 	Extra 	Privileges 	Comment 
		// id  	int(10) unsigned  	NULL 	NO 	PRI  	NULL 	auto_increment 	select,insert,update,references 	the pk
		// name 	varchar(255) 	utf8_romanian_ci 	NO 	UNI 	  	  	select,insert,update,references 	the name field
		// order 	int(10) unsigned 	NULL 	NO 	  	0 	  	select,insert,update,references 	horder man
		// active 	enum('Yes','No') 	utf8_romanian_ci 	NO 	  	Yes 	  	select,insert,update,references 	aactiveee man

		while ($row = $result->fetch_assoc())
		{
			$f_name = $row["Field"];
			
			// type / length / values / unsigned
			list($f_type, $f_length, $f_values, $f_unsigned) = $this->parseFieldType($row["Type"]);
			
			$f_type = $this->decodeColumnType($f_type, $f_length);
			if ($f_type == QSqlTableColumn::TypeBool)
				$f_length = null;
			else
				$f_length = (strpos($f_length, ",") === false) ? (integer)$f_length : $f_length;
			
			$f_charset = $this->getCharsetFromCollation($row["Collation"]);
			$f_collation = $row["Collation"];
			
			$f_null = ($row["Null"] == "YES");
			// mySQL will always SHOW null even if default is not null so make sure the column accepts null
			$f_default = (($row["Default"] === null) && $f_null) ? QSqlNull::Get() : $row["Default"];
			$f_auto_increment = (trim($row["Extra"]) == "auto_increment");
			$f_comment = $row["Comment"];
			
			$sql_column = new QSqlTableColumn($table, $f_name, $f_type, $f_length, $f_values, $f_default, $f_charset, $f_collation, $f_unsigned, $f_null, $f_auto_increment, $f_comment);
			$sql_column->setTransformState(self::TransformNoAction);
			$sql_column->table = $table;
			$fields[$f_name] = $sql_column;
		}

		$indexes = new QModelArray();
		$indexes->setTransformState(self::TransformNoAction);
		
		$result = $this->connection->query("SHOW INDEX FROM {$this->escapeName($database_name)}.{$this->escapeName($table_name)}");
		
		while ($row = $result->fetch_assoc())
		{
			/**
				Table 		Non_unique 	Key_name 		Seq_in_index 	Column_name 	Collation 	Cardinality 	Sub_part 	Packed 	Null 	Index_type 	Comment
				test_1 		0 			PRIMARY 		1 				id 				A 			0 				NULL 		NULL 		  	BTREE 	 
				test_1 		0 			uniq_0 			1 				name 			A 			0 				NULL 		NULL 		  	BTREE 	 
				test_1 		1 			name 			1 				name 			A 			NULL 			NULL 		NULL 		  	BTREE 	 
				test_1 		1 			name 			2 				order 			A 			NULL 			NULL 		NULL 		  	BTREE 	 
				test_1 		1 			sometetx 		1 				sometetx 		NULL 		NULL 			NULL 		NULL 		  	FULLTEXT 	 
			 */
			$i_name = $row["Key_name"];
			
			if (isset($indexes[$i_name]))
				$current_index = $indexes[$i_name];
			else 
			{
				$current_index = new QSqlTableIndex();
				$current_index->setTransformState(self::TransformNoAction);
				$current_index->table = $table;
				$indexes[$i_name] = $current_index;
				$current_index->columns = new QModelArray();
				$current_index->columns->setTransformState(self::TransformNoAction);
				$current_index->name = $i_name;
			}
			
			// establish type
			if (strtoupper(trim($i_name)) == "PRIMARY")
				$current_index->type = QSqlTableIndex::IndexPrimary;
			else 
			{
				if (strtoupper(trim($row["Index_type"])) == "FULLTEXT")
					$current_index->type = QSqlTableIndex::IndexFullText;
				else if (trim($row["Non_unique"]) == "1")
					$current_index->type = QSqlTableIndex::IndexNormal;
				else 
					$current_index->type = QSqlTableIndex::IndexUnique;
			}
			
			$current_index->columns[$row["Column_name"]] = $fields[$row["Column_name"]];
		}

		$row_info = $this->queryOneRow("SHOW TABLE STATUS IN {$this->escapeName($database_name)} LIKE '{$this->escapeString($table_name)}'");
		// "Engine" | "Collation" | "Comment"
		$engine = trim($row_info["Engine"]);
		$collation = trim($row_info["Collation"]);
		$charset = $this->getCharsetFromCollation($collation);
		$comments = trim($row_info["Comment"]);

		// fix fields (remove index), fix indexes

		// $list_fields, $list_indexes, $engine, $charset, $collation, $comments
		$table->columns = $fields;
		$table->indexes = $indexes;
		$table->engine = $engine;
		$table->charset = $charset;
		$table->collation = $collation;
		$table->comment = $comments;
		
		return $table;
	}
	
	/**
	 * Parsese the field type that is obtain from a 'SHOW FULL COLUMNS FROM ...' command
	 * ex: ENUM('Yes','No')
	 * ex: int(10) unsigned
	 * ex: varchar(64)
	 * The result is an array : array(type, length, values, unsigned)
	 *
	 * @param string $field_type
	 * @return array
	 */
	private function parseFieldType($field_type)
	{
		$f_type = null;
		$f_length = null;
		$f_values = null;
		$f_unsigned = false;
		
		$end_type = strpos($field_type, "(");
		if ($end_type !== false)
		{
			$f_type = trim(substr($field_type, 0, $end_type));
			$end_length = strpos($field_type, ")", $end_type + 1);
			if ($end_length === false)
				throw new Exception("Invalid or unknown format");
				
			if ((strtoupper($f_type) == "ENUM") || (strtoupper($f_type) == "SET"))
			{
				$f_length = null;
				$f_values = substr($field_type, $end_type + 1, $end_length - $end_type - 1);
			}
			else 
			{
				// var_dump($field_type);
				$f_length = substr($field_type, $end_type + 1, $end_length - $end_type - 1);
				$f_values = null;
			}
			
			$parts = explode(" ", trim(substr($field_type, $end_length + 1)));
			$c_parts = count($parts);
			
			for ($i = 0; $i < $c_parts; $i++)
			{
				if (strtolower(trim($parts[$i])) == "unsigned")
				{
					$f_unsigned = true;
					break;
				}
			}
		}
		else 
		{
			$end_type = strpos($field_type, " ");
			if ($end_type !== false)
			{
				// $f_type = trim(substr($field_type, 0, $end_type));
				$parts = explode(" ", $field_type);
				$f_type = $parts[0];
				
				$c_parts = count($parts);

				for ($i = 1; $i < $c_parts; $i++)
					if (strtolower(trim($parts[$i])) == "unsigned")
					{
						$f_unsigned = true;
						break;
					}
			}
			else 
			{
				// only type is specified
				$f_type = trim($field_type);
			}
		}
		/*
		if (strtolower($f_type) === "decimal")
		{
			var_dump($f_type, $f_length, $f_values, $f_unsigned);
		}
		*/
		return array($f_type, $f_length, $f_values, $f_unsigned);
	}
	
	/**
	 * Gets the default database as an object
	 *
	 * @return QSqlDatabase
	 */
	public function getDefaultDatabase()
	{
		if (!$this->default_db)
			throw new Exception("The default database name was not set");
		if (!$this->default_database)
		{
			$this->default_database = new QSqlDatabase($this->default_db);
			$this->default_database->parent = $this;
			$this->default_database->name = $this->default_db;
			// $this->default_database->name = $this->default_db;
		}
		return $this->default_database;
	}
	
	/**
	 * Gets the database as an object
	 * 
	 * @param string $db_name
	 *
	 * @return QSqlDatabase
	 */
	public function getDatabase($db_name)
	{
		if (!$this->children[$db_name])
		{
			$db = new QSqlDatabase($db_name);
			$db->parent = $this;
			$db->name = $db_name;
			$this->children[$db_name] = $db;
		}
		return $this->children[$db_name];
	}
	
	/**
	 * Escapes the name of a database, table, column, alias and so on
	 *
	 * @param string $string
	 */
	public function escapeName($string)
	{
		/* TO DO !!! Very important !!! Security issues may arise
		 *  Permitted characters in unquoted identifiers:

				ASCII: [0-9,a-z,A-Z$_] (basic Latin letters, digits 0-9, dollar, underscore)
				Extended: U+0080 .. U+FFFF 

			Permitted characters in quoted identifiers include the full Unicode Basic Multilingual Plane (BMP), except U+0000:

				ASCII: U+0001 .. U+007F
				Extended: U+0080 .. U+FFFF 
		 */
		
		// make sure there is no : null, \n, \r, \t
		
		return "`{$string}`";
	}
	
	// WORK IN PROGRESS ======================================================================================
	
	public function syncTable(QSqlTable $table)
	{
		// echo "Sync:: ".$table->name."<br/>\n";
		/*
		CREATE TABLE `qbase_test`.`blabla` (
			`sadfsad` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'comm 1',
			`faf` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'comm 2',
			`qwer` ENUM( 'y', 'n' ) NOT NULL DEFAULT 'n' COMMENT 'comm 3',
			`qwerqwe` TEXT CHARACTER SET utf8 COLLATE utf8_romanian_ci NULL COMMENT 'comm 4'
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_romanian_ci COMMENT = 'table commeeeenntssss';
			
		*/
		
		$changed = false;
		
		$database = $table->parent ? $table->parent->name : null;
		
		$table_exists = false;
		
		$table_name = $table->name;
		
		if ($table->getTransformState() === null)
			$command = "CREATE";
		else if ($table->getTransformState() == self::TransformDelete)
		{
			$command = "DROP TABLE ".($database ? $this->escapeName($database)."." : "") . $this->escapeName($table_name).";";
			echo $command."\n";
			return $command;
		}
		else
		{
			$command = "ALTER";
			$table_exists = true;
		}
		
		$query = "{$command} TABLE " . ($database ? $this->escapeName($database)."." : "") . $this->escapeName($table_name) . " ".($table_exists ? "" : "(")."\n";
		
		$pos = 0;
		$fields = $table->columns;
		
		$add_comma = false;
		
		foreach ($fields as $field)
		{
			$changed_props = ($field->getTransformState() === null) ? $field->get() : $field->getChangedProperties();
			if ((!$changed) && $changed_props)
				$changed = true;
			
			if (!$changed_props)
				continue;
			
			if ($pos > 0)
				$query .= ",\n";

			if ($table_exists)
			{
				if ($field->getTransformState() === null)
					// new field
					$query .= "ADD ".$this->escapeName($field->name)." ".$this->getFieldModelString($field, true);
				else
					// old field
					$query .= "CHANGE ".$this->escapeName($field->name)." ".$this->escapeName($field->name)." ".$this->getFieldModelString($field, true);
			}
			else
				$query .= $this->escapeName($field->name)." ".$this->getFieldModelString($field);
			
			$add_comma = true;

			$pos++;
		}
		
		if ($table->indexes)
		{
			$pos = 0;
			foreach ($table->indexes as $index)
			{
				$changed_indx = ($index->getTransformState() === null) ? $index->get() : $index->getChangedProperties();
				if ((!$changed) && $changed_indx)
					$changed = true;
				
				// now check for columns, bleah]
				$changed_cols = $index->columns->getChangedKeys();
				if ((!$changed) && $changed_cols)
					$changed = true;

				if ((!$changed_indx) && (!$changed_cols))
					continue;
				
				$keys = $index->columns->getKeys();
				$c_keys = count($keys);
				if (($c_keys == 1) && $index->columns[$keys[0]]->auto_increment)
					continue;
				
				for ($i = 0; $i < $c_keys; $i++)
					$keys[$i] = $this->escapeName($keys[$i]);
				
				$index_type_str = "INDEX";
				if ($index->type === QSqlTableIndex::IndexFullText)
					$index_type_str = "FULLTEXT";
				else if ($index->type === QSqlTableIndex::IndexUnique)
					$index_type_str = "UNIQUE";

				if ($add_comma || ($pos > 0))
					$query .= ",\n";
				if ($table_exists)
				{
					if ($index->getTransformState() !== null)
						$query .= "DROP INDEX {$this->escapeName($index->name)},\n";
					$query .= "ADD {$index_type_str} {$this->escapeName($index->name)} (".implode(",", $keys).")";
				}
				else
					$query .= " {$index_type_str} {$this->escapeName($index->name)} (".implode(",", $keys).")";

				$pos++;
				$add_comma = true;
			}
		}
		
		// TO DO: Foreign keys !
		$closed = false;
		if ((!$table_exists) || $table->wasChanged("engine"))
		{
			$changed = true;
			if (!$closed)
				$query .= " ".($add_comma ? ($table_exists ? ",\n" : ")\n") : "");
			$closed = true;
			$query .= " ENGINE = {$table->engine}";
		}

		if ((!$table_exists) || $table->wasChanged("charset"))
		{
			$changed = true;
			if (!$closed)
				$query .= " ".($add_comma ? ($table_exists ? ",\n" : ")\n") : "");
			$closed = true;
			$query .= " CHARACTER SET {$table->charset}";
		}
		if ((!$table_exists) || $table->wasChanged("collation"))
		{
			$changed = true;
			if (!$closed)
				$query .= " ".($add_comma ? ($table_exists ? ",\n" : ")\n") : "");
			$closed = true;
			$query .= " COLLATE {$table->collation}";
		}
			
		if ((!$table_exists) || $table->wasChanged("comment"))
		{
			$changed = true;
			if (!$closed)
				$query .= " ".($add_comma ? ($table_exists ? ",\n" : ")\n") : "");
			$closed = true;
			$query .= " COMMENT = '{$this->escapeString($table->comment)}'";
		}

		$query .= ";";
		// $this->debug($query);
		// $this->query($query);
		
		if ($changed)
			echo $query."\n\n";
		unset($query);
			
		return;
	}
	
	/**
	 * Escapes a string for this connection
	 *
	 * @param string $str
	 * @return string
	 */
	public function escapeString($str)
	{
		return $this->connection->real_escape_string($str);
	}
	
	private function getFieldModelString(QSqlTableColumn $field, $for_field_update = false)
	{
		$query = self::EncodeFieldType( $field->type );

		if ($field->length)
			$query .= "({$field->length})";
		else if ($field->values)
			$query .= "({$field->values})";
			
		if ($field->type && ($field->type != QSqlTableColumn::TypeBool) && $field->unsigned)
			$query .= " UNSIGNED";
			
		if ($field->charset)
			$query .= " CHARACTER SET {$field->charset}";
		if ($field->collation)
			$query .= " COLLATE {$field->collation}";
			
		if ($field->null)
			$query .= " NULL";
		else 
			$query .= " NOT NULL";
			
		if ($field->default !== null)
		{
			if ($field->default instanceof QSqlNull)
				$query .= " DEFAULT NULL";
			else if (is_bool($field->default))
				$query .= " DEFAULT ".($field->default ? "TRUE" : "FALSE");
			else if ((($field->type == QSqlTableColumn::TypeTimestamp) 
				|| ($field->type == QSqlTableColumn::TypeDate)
				|| ($field->type == QSqlTableColumn::TypeDatetime)
				|| ($field->type == QSqlTableColumn::TypeTime))
				&& (($field->default == "CURRENT_TIMESTAMP") || ($field->default == "CURRENT_DATE")))
				$query .= " DEFAULT {$field->default}";
			else 
			{
				/*
				if (is_array($field->default))
				{
					//qvardump($field);
					throw new \Exception("Field default not accepted!");
				}
				 * 
				 */
				$query .= " DEFAULT '{$this->escapeString($field->default)}'";
			}
		}
			
		// auto increment forces PK
		if ($field->auto_increment)
		{
			if ($for_field_update)
				$query .= " AUTO_INCREMENT ";
			else 
				$query .= " AUTO_INCREMENT PRIMARY KEY ";
		}
			
		if ($field->comment)
			$query .= " COMMENT '".$this->escapeString($field->comment)."'";
			
		return $query;
	}
	
	/**
	 * Casts the field type to string representing the MySQL data type
	 *
	 * @param integer $type
	 * 
	 * @return string
	 */
	public static function EncodeFieldType($type)
	{
		switch ($type)
		{
			case QSqlTableColumn::TypeInt:
				{
					return "INT";
				}
			case QSqlTableColumn::TypeSmallint:
				{
					return "SMALLINT";
				}
			case QSqlTableColumn::TypeMediumint:
				{
					return "MEDIUMINT";
				}
			case QSqlTableColumn::TypeTinyint:
				{
					return "TINYINT";
				}
			case QSqlTableColumn::TypeBigint:
				{
					return "BIGINT";
				}
			case QSqlTableColumn::TypeDecimal:
				{
					return "DECIMAL";
				}
			case QSqlTableColumn::TypeFloat:
				{
					return "FLOAT";
				}
			case QSqlTableColumn::TypeDouble:
				{
					return "DOUBLE";
				}
			case QSqlTableColumn::TypeBit:
				{
					return "BIT";
				}
			case QSqlTableColumn::TypeBool:
				{
					return "BOOL";
				}
			case QSqlTableColumn::TypeChar:
				{
					return "CHAR";
				}
			case QSqlTableColumn::TypeVarchar:
				{
					return "VARCHAR";
				}
			case QSqlTableColumn::TypeText:
				{
					return "TEXT";
				}
			case QSqlTableColumn::TypeMediumText:
				{
					return "MEDIUMTEXT";
				}
			case QSqlTableColumn::TypeLongText:
				{
					return "LONGTEXT";
				}
			case QSqlTableColumn::TypeBlob:
				{
					return "BLOB";
				}
			case QSqlTableColumn::TypeLongBlob:
				{
					return "LONGBLOB";
				}
			case QSqlTableColumn::TypeEnum:
				{
					return "ENUM";
				}	
			case QSqlTableColumn::TypeSet:
				{
					return "SET";
				}
			case QSqlTableColumn::TypeDate:
				{
					return "DATE";
				}
			case QSqlTableColumn::TypeDatetime:
				{
					return "DATETIME";
				}
			case QSqlTableColumn::TypeTimestamp:
				{
					return "TIMESTAMP";
				}
			case QSqlTableColumn::TypeTime:
				{
					return "TIME";
				}
			default:
				{
					throw new Exception("Unknown field type: {$type}");
				}
		}
	}
	/**
	 * Gets the storage containers that contain a certain type
	 * In some cases a model may be contained in more than one container (to have the full information), or different instances can be in more than one container
	 *
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 * 
	 * @return QIStorageContainer[]
	 */
	public function getStorageContainersForType($model_type)
	{
		
	}
	/**
	 * Gets the default storage container for the specified type
	 *
	 * @param QModelType|QModelProperty $model_type
	 */
	public function getDefaultStorageContainerForType($model_type)
	{
		$t_name = ($model_type instanceof QModelType) ? QSqlModelInfoType::GetTableNameForType($model_type) : (($model_type instanceof QModelProperty) ? QSqlModelInfoType::GetTableNameForCollectionProperty($model_type->parent->class, $model_type->name) : null);
		if ($t_name)
		{
			$db = $this->getDefaultDatabase();
			if ($db->children[$t_name])
				$table = $db->children[$t_name];
			else
			{
				$table = new QSqlTable($t_name, $db);
				$table->storage = $this;
				if (!$db->children)
					$db->children = new \QModelArray();
				$db->children[$t_name] = $table;
			}
			
			return $table;
		}
		else
			return null;
	}
	/**
	 * Queries the storage to get the needed data
	 *
	 * @param QModelQuery $query
	 * @param QIModel[] $instances Must be indexed by ID (UID should be forced somehow, if ot available use: '$id/$type')
	 */
	public function queryStorage(QModelQuery $query, $instances = null)
	{
		
	}
	
	public function syncStorageContainersForDataTypes($model_info = null, $data_types = null, $as_default = false, QIStorageFolder $parent = null, $containers = null, $prefix = "", $sufix = "")
	{
		// load up the older definition
		
		// we need to redefine the Model - Storage Info
		
		if (!$model_info)
			$model_info = new QSqlModelInfo();

		/*
		// ensure tables are created
		foreach ($data_types as $dt)
			$model_info->setupDataType($dt, $this, true);
		 */
		
		$traversed = array();
		// resume the setup
		foreach ($data_types as $dt)
			$model_info->setupDataType($dt, $this, false, $traversed);
		
		// cleanup !
		
		
		// this should be done in 3 steps. 
		// 1. compute differences
		// 2. Aprove / edit
		// 3. Execute / commit
	}
	
	/**
	 * Converts the string field type (ex: VARCHAR) to the defined constant (ex: QSqlTableColumn::TypeVarchar)
	 *
	 * @param string $type
	 * @return integer
	 */
	public function decodeColumnType($type, $filed_length = null)
	{
		$type = strtoupper(trim($type));
		switch ($type)
		{
			case "INT":
			{
				return QSqlTableColumn::TypeInt;
			}
			case "TINYINT":
			{
				if (($filed_length !== null) && ($filed_length == 1))
					return QSqlTableColumn::TypeBool;
				else 
					return QSqlTableColumn::TypeTinyint;
			}
			case "SMALLINT":
			{
				return QSqlTableColumn::TypeSmallint;
			}
			case "MEDIUMINT":
			{
				return QSqlTableColumn::TypeMediumint;
			}
			case "BIGINT":
			{
				return QSqlTableColumn::TypeBigint;
			}
			case "DECIMAL":
			{
				return QSqlTableColumn::TypeDecimal;
			}
			case "FLOAT":
			{
				return QSqlTableColumn::TypeFloat;
			}
			case "DOUBLE":
			{
				return QSqlTableColumn::TypeDouble;
			}
			case "BIT":
			{
				return QSqlTableColumn::TypeBit;
			}
			case "BOOL":
			{
				return QSqlTableColumn::TypeBool;
			}
			case "CHAR":
			{
				return QSqlTableColumn::TypeChar;
			}
			case "VARCHAR":
			{
				return QSqlTableColumn::TypeVarchar;
			}
			case "TEXT":
			{
				return QSqlTableColumn::TypeText;
			}
			case "MEDIUMTEXT":
			{
				return QSqlTableColumn::TypeMediumText;
			}
			case "LONGTEXT":
			{
				return QSqlTableColumn::TypeLongText;
			}
			case "BLOB":
			{
				return QSqlTableColumn::TypeBlob;
			}
			case "LONGBLOB":
			{
				return QSqlTableColumn::TypeLongBlob;
			}
			case "ENUM":
			{
				return QSqlTableColumn::TypeEnum;
			}
			case "SET":
			{
				return QSqlTableColumn::TypeSet;
			}
			case "DATE":
			{
				return QSqlTableColumn::TypeDate;
			}
			case "DATETIME":
			{
				return QSqlTableColumn::TypeDatetime;
			}
			case "TIMESTAMP":
			{
				return QSqlTableColumn::TypeTimestamp;
			}
			case "TIME":
			{
				return QSqlTableColumn::TypeTime;
			}
			default:
			{
				throw new Exception("Unknown field type: {$type}");
			}
		}
	}
	
	/**
	 * Gets the charset from the collation
	 * Since the collation is a subset of a charset we can obtain the charset from it
	 * This is necessary as there is no other way to grab the charset for a table or column
	 *
	 * @param string $collation
	 * @return string
	 */
	private function getCharsetFromCollation($collation)
	{
		$collation = strtolower($collation);
		$pos = 0;
		$this->getAcceptedCharsets();
		
		while (($sep_pos = strpos($collation, "_", $pos)) !== false)
		{
			$charset = substr($collation, 0, $sep_pos);
			if (in_array($charset, $this->accepted_charsets))
				return $charset;
			
			$pos = $sep_pos + 1;
		}
		return null;
	}
	
	/**
	 * Gets the accepted charsets by the SQL engine
	 *
	 * @param boolean $refresh If true it forces a refresh, if not the value will be taken from the cache
	 * @return array Array of strings
	 */
	public function getAcceptedCharsets($refresh = false)
	{
		if ($this->accepted_charsets && (!$refresh))
			return $this->accepted_charsets;

		$this->accepted_charsets = array();
		$result = $this->connection->query("SHOW CHARACTER SET");
		while ($row = $result->fetch_assoc())
			$this->accepted_charsets[] = strtolower($row["Charset"]);

		return $this->accepted_charsets;
	}
	
	public function queryOneRow($query)
	{
		$result = $this->connection->query($query);
		if ($result)
		{
			$ret = $result->fetch_assoc();
			$result->free();
			return $ret;
		}
	}
	
	/**
	 * Gets the type id in the storage
	 * 
	 * @param string $type_name
	 * 
	 * @return integer
	 */
	public function getTypeIdInStorage($type_name)
	{
		if (!$this->_typeIdsIncluded)
			$this->ensureTypeIdsWasIncluded();
		
		$id = $this->_typeIds[$type_name];
		if (!$id)
		{
			if ($type_name{0} === strtolower($type_name{0}))
				return QModelType::GetScalarTypeId($type_name);
			// refresh cache
			$this->ensureTypeIdsWasIncluded(true);
			$id = $this->_typeIds[$type_name];
			if (!$id)
				throw new Exception("Unable to add type `{$type_name}` to the storage's index");
		}
		return $id;
	}

	/**
	 * Gets the type name in the storage by id
	 * 
	 * @param integer $type_name
	 * 
	 * @return string
	 */
	public function getTypeNameInStorageById($type_id)
	{
		if ($type_id < Q_FRAME_MIN_ID_TYPE)
			return QModel::GetScalarNameById($type_id);
		
		if (!$this->_typeIdsIncluded)
			$this->ensureTypeIdsWasIncluded();
		
		$type_name = $this->_typeIdsFlip[$type_id];
		if (!$type_name)
		{
			// refresh cache
			$this->ensureTypeIdsWasIncluded(true);
			$type_name = $this->_typeIdsFlip[$type_id];
			if (!$type_name)
			{
				throw new Exception("Unable to find type by id `{$type_id}` in the storage's index");
			}
		}
		return $type_name;
	}
	
	public function getTypeIds($refresh = false)
	{
		if (!$this->_typeIdsIncluded)
			$this->ensureTypeIdsWasIncluded($refresh);
		return $this->_typeIds;
	}
	
	public function getTypeIdsFlip($refresh = false)
	{
		if (!$this->_typeIdsIncluded)
			$this->ensureTypeIdsWasIncluded($refresh);
		return $this->_typeIdsFlip;
	}
	
	/**
	 * Checks if the type ids were loaded and if not it will refresh things
	 * 
	 * @param boolean $refresh
	 * 
	 * @throws Exception
	 */
	public function ensureTypeIdsWasIncluded($refresh = false)
	{
		if (!$this->_typeIdsPath)
			$this->_typeIdsPath = QAutoload::GetRuntimeFolder()."temp/storage_{$this->name}_ids.php";
			
		if ($refresh || (!file_exists($this->_typeIdsPath)))
		{
			$classes = QAutoload::GetAllWatchedClasses();
			
			/**
			* This is a great way to ensure global unique IDs for types declarations
			*/
			# $encoded = "types=".urlencode(json_encode($classes));
			$curl = curl_init(Q_FRAME_GET_ID_TYPE);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, ['types' => json_encode($classes)]);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
			curl_setopt($curl, CURLOPT_POSTREDIR, 1);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($curl);
			if (!$data)
				throw new Exception("Invalid response from: ".Q_FRAME_GET_ID_TYPE."\n\n".curl_error($curl));
			
			$data = json_decode($data, true);
			if (!$data)
			{
				#echo $rawResponse;
				throw new Exception("Invalid response from: ".Q_FRAME_GET_ID_TYPE."\n\nResponse cannot be decoded");
			}
			$data_flip = array_flip($data);
			
			$s = "<?php \$_Q_FRAME_TYPE_IDS = array(";
			foreach ($data as $k => $v)
				$s .= "\"".addslashes($k)."\" => ".addslashes($v).",\n";
			$s .= ");\n\n";
			$s .= "\$_Q_FRAME_TYPE_IDS_FLIP = array(";
			foreach ($data_flip as $k => $v)
				$s .= addslashes($k)." => \"".addslashes($v)."\",\n";
			$s .= ");\n\n?>";
		
			file_put_contents($this->_typeIdsPath, $s);
			
			$this->_typeIds = $data;
			$this->_typeIdsFlip = $data_flip;
			$this->_typeIdsIncluded = true;
		}
		else
		{
			// we need to load them
			include($this->_typeIdsPath);
			$this->_typeIds = $_Q_FRAME_TYPE_IDS;
			$this->_typeIdsFlip = $_Q_FRAME_TYPE_IDS_FLIP;
			$this->_typeIdsIncluded = true;
		}
		
		self::$ModelTypeIds = $this->_typeIds;
	}
	
	public static function GetModelTypeIds()
	{
		if (self::$ModelTypeIds === null)
			QApp::GetStorage()->ensureTypeIdsWasIncluded();
		
		return self::$ModelTypeIds;
	}
	
	public function begin($nest_transaction = false)
	{
		$transaction = null;
		$mysqli = $this->connection;
		
		$t_arr = $mysqli->query("SELECT @@autocommit;")->fetch_array();
		$autocommit = reset($t_arr);
		$nested_transaction = !($autocommit == 1);

		$transaction = new QSqlTransaction();
		if (!$nested_transaction)
		{
			$mysqli->autocommit(false);
		}
		else
		{
			// we are already in a transaction
			$transaction->save_point = "SP_".uniqid();
			$transaction->exec_save_point = false;
			if ($nest_transaction)
			{
				$mysqli->query("SAVEPOINT ".$transaction->save_point.";");
				$transaction->exec_save_point = true;
			}
		}
		
		if ($this->transaction)
			$transaction->parent = $this->transaction;
		$this->transaction = $transaction;
		
		return $transaction;
	}

	public function commit()
	{
		$parent_transaction = $this->transaction->parent;
		
		if ($parent_transaction)
		{
			if ($this->transaction->exec_save_point)
				$this->connection->query("RELEASE SAVEPOINT ".$this->transaction->save_point.";");
			$this->transaction = $parent_transaction;
		}
		else if ($this->transaction)
		{
			$this->connection->commit();
			$this->connection->autocommit(true);
			$this->transaction = null;
		}
	}
	
	public function rollback()
	{
		$parent_transaction = $this->transaction->parent;
		
		if ($parent_transaction)
		{
			//$this->connection->query("ROLLBACK TO ".$this->transaction->save_point.";");
			if ($this->transaction->exec_save_point)
				$this->connection->query("ROLLBACK TO SAVEPOINT;");
			$this->transaction = $parent_transaction;
		}
		else if ($this->transaction)
		{
			$this->connection->rollback();
			$this->connection->autocommit(true);
			$this->transaction = null;
		}
	}
	
	/**
	 * 
	 * @param string $storage_model
	 * @param string $from
	 * @param string $selector
	 * @param array $parameters
	 * 
	 * @return QIModel
	 */
	public static function ApiQuery($storage_model, $from, $from_type, $selector = null, $parameters = null, $only_first = false, $id = null, $skip_security = true, $sql_filter = null)
	{
		$view_tag = null;
		if (is_array($from) && (count($from) === 2))
			list($from, $view_tag) = $from;
		
		$called_class = get_called_class();
		if (method_exists($called_class, "ApiQuery_in_"))
			$called_class::ApiQuery_in_();
		
		if ($id !== null)
			$only_first = true;
			
		if ($from_type)
		{
			if (is_array($from_type))
				// @todo - accept all types
				$from_type = reset($from_type);
			
			if (is_string($selector))
				$selector = qParseEntity($selector);
			
			if (!$only_first)
				// join it with static::GetModelEntity()
				$selector = qJoinSelectors($selector, $from_type::GetListingEntity());
			
			if ($view_tag)
				$query = $only_first ? $from_type::GetItemQuery($view_tag, $selector) : $from_type::GetListingQuery($selector, $view_tag);
			
			if (!$query)
				$query = $only_first ? $from_type::GetItemQuery($from, $selector) : $from_type::GetListingQuery($selector, $from);

			if (!$query)
				$query = $only_first ? $from_type::GetItemQuery(null, $selector) : $from_type::GetListingQuery($selector);
			
			// secure parameters (not possible atm ... needs a new implementation)
			$query = $from.".{".(($id || $only_first) ? "" : "SQL_CALC_FOUND_ROWS, ").$query."}";
			
			if ($selector !== null)
			{
				if (is_string($selector))
					$selector = qParseEntity($selector);
				$selector = [$from => $selector];
			}
		}
		else // scalars
		{
			$query = $from;
		}
		
		// @todo : $parameters : to secure (there is also other data that needs to be secured)
		if ($parameters === null)
			$parameters = [];

		if ($id)
		{
			if (is_scalar($id))
				$parameters["Id"] = $id;
			else
				$parameters = $id;
		}

		$data_block = [];
		
		// if (($sql_filter && $sql_filter[1]) || ((!$skip_security) && ($security_filter = \QSecurity::GetSQLFilter($from))))
		if ($sql_filter && $sql_filter[1])
		{
			// if ($sql_filter)
				// $security_filter = $sql_filter[1];
			$security_filter = $sql_filter[1];
			$query = rtrim($query);
			$query = ((substr($query, -1, 1) === '}') ? substr($query, 0, -1) : $query)." WHERE AND ({$security_filter}) }";
		}
		
		$return_data = QModelQuery::BindQuery($query, $parameters, null, $data_block, $skip_security, $selector);
		$return_data = $return_data ? $return_data->$from : null;
		
		if (method_exists($called_class, "ApiQuery_out_"))
			$called_class::ApiQuery_out_();
		
		return ($only_first && (qis_array($return_data))) ? ($return_data ? reset($return_data) : null) : $return_data;
	}
	
	/**
	 * 
	 * @param QIModel|array $data
	 * @param integer $state
	 * @param string $from
	 * @return mixed
	 * @throws Exception
	 */
	public static function ApiImport($storage_model, $from, $from_type, $data, $state = null, $selector = null, bool $explicit_selector = false)
	{
		foreach ($data ?: [] as $d)
		{
			if ($d instanceof \QModel)
				$d->_is_imported_item = 1;
		}
		
		$model = new $storage_model();
		$model->setId(1); // @todo : we need to do it this way atm !!!
		$model->$from = $data;
		// get the selector
		if (!$explicit_selector)
			$selector = $from_type ? [$from => static::GetSaveSelector($from, $from_type, $data, $state, $selector)] : $from;

		if ($model->$from instanceof QModelArray)
			$model->$from->setModelProperty($from, $model);

		# $use_states = QApi::SecureStates($model, $from, $state, $selector);
		
		\QApi::$DataToProcess = $model;

		//$t1 = microtime(true);
		// trigger before import
		// $model->beforeImport(true);
		if ($explicit_selector)
		{
			$use_states = null;
			$selector = [$from => is_string($selector) ? qParseEntity($selector) : $selector];
		}
		
		#	bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false
		
		$return_data = $model->save($selector, null, $use_states, true, true, false, true);

		// $model->afterImport(true);

		\QApi::$DataToProcess = null;
		
		// @todo : secure output
		return $return_data;
	}

	public static function GetSaveSelector($from, $from_type, $data = null, $state = null, $selector = null, $initialDestination = null)
	{
		if (is_array($from_type))
			// @todo - accept all types
			$from_type = reset($from_type);

		$dataCls = \QApp::GetDataClass();

		// determine type selector
		$ty_selector = $dataCls::GetFormEntity_Final($from);
		$ty_selector_gen_form = $dataCls::GetEntityForGenerateForm_Final($from);
		$ty_selector = qJoinSelectors($ty_selector, $ty_selector_gen_form);
		
		if ($initialDestination && ($initialDestination !== $from))
		{
			$id_selector = $dataCls::GetFormEntity_Final($initialDestination);
			$ty_selector = qJoinSelectors($ty_selector, $id_selector);
			$id_selector_gen_form = $dataCls::GetEntityForGenerateForm_Final($initialDestination);
			$ty_selector = qJoinSelectors($ty_selector, $id_selector_gen_form);
		}
		
		if ($ty_selector === null)
			$ty_selector = $dataCls::GetPropertyModelEntity($from, $state);
		if ($ty_selector === null)
			$ty_selector = $from_type::GetModelEntity($state);
		if (is_string($ty_selector))
			$ty_selector = qParseEntity($ty_selector);
		$selector = ($selector && qis_array($selector)) ? qIntersectSelectors($selector, $ty_selector) : $ty_selector;
	
		return $selector;
	}
	/**
	 * 
	 * Overwritten for calling before and after save
	 * 
	 * @param QIModel|array $data
	 * @param integer $state
	 * @param string $from
	 * @return mixed
	 * @throws Exception
	 */
	public static function ApiSave($storage_model, $from, $from_type, $data, $state = null, $selector = null, $initialDestination = null)
	{
		$model = new $storage_model();
		$model->setId(1); // @todo : we need to do it this way atm !!!
		$model->$from = $data;

		$selector = $from_type ? [$from => static::GetSaveSelector($from, $from_type, $data, $state, $selector, $initialDestination)] : $from;
		
		if ($model->$from instanceof QModelArray)
			$model->$from->setModelProperty($from, $model);

		$use_states = QApi::SecureStates($model, $from, $state, $selector);

		\QApi::$DataToProcess = $model;
		
		$return_data = $model->save($selector, null, $use_states, true, true, true, false);
		
		\QApi::$DataToProcess = null;

		// @todo : secure output
		return $return_data;
	}

	/**
	 * 
	 * @param string $storage_model
	 * @param string $from
	 * @param string $selector
	 * @param array $parameters
	 * 
	 * @return QIModel
	 */
	public static function ApiQuerySync($storage_model, $from, $from_type, $selector = null, $parameters = null, $only_first = false, $id = null, array $ids_list = null, array &$data_block = null, array &$used_app_selectors = null, string $query_by_data_type = null)
	{
		$called_class = get_called_class();
		if (method_exists($called_class, "ApiQuery_in_"))
			$called_class::ApiQuery_in_();

		if ($id !== null)
			$only_first = true;
		if ($from_type)
		{
			if (is_array($from_type))
				$from_type = reset($from_type);

			if (is_string($selector))
				$selector = qParseEntity($selector);
			
			$query = $only_first ? $storage_model::GetSyncPropertyItemQuery($from) : $storage_model::GetSyncPropertyListingQuery($from);
			if (!$query)
			{
				$query = $only_first ? $from_type::GetItemSyncQuery() : $from_type::GetListingSyncQuery();
				
				if ($used_app_selectors !== null)
					$used_app_selectors[$from][$from_type][] = \Omi\App::Fix_Sync_Listing_Entity($from_type, $only_first ? $from_type::GetModelSyncEntity() : $from_type::GetListingSyncEntity());
				// secure parameters (not possible atm ... needs a new implementation)
				if (!$query_by_data_type)
					$query = $from.".{".$query."}";
				if (is_array($selector))
					$selector = [$from => $selector];
				
				# qvar_dumpk($only_first ? 'GetItemSyncQuery' : 'GetListingSyncQuery', $from_type, $query);
			}
			else
			{
				if ($used_app_selectors !== null)
				{
					$used_app_selectors[$from_type][] = $query;
					throw new \Exception('Not expected. The parameter added is not a selector.');
				}
			}
		}
		else // scalars
		{
			$query = $from;
		}

		// @todo : $parameters : to secure (there is also other data that needs to be secured)
		if ($parameters === null)
			$parameters = [];

		if ($id)
		{
			if (is_scalar($id))
				$parameters["Id"] = $id;
			else
				$parameters = $id;
		}

		if ($data_block === null)
			$data_block = [];
		
		if (is_array($ids_list))
		{
			if (strpos($query, "??Id_IN?") === false)
			{
				if (\QAutoload::GetDevelopmentMode())
				{
					qvar_dumpk($query, $ids_list, $only_first, $from_type, $storage_model, $storage_model::GetSyncPropertyListingQuery($from), $from_type::GetListingSyncQuery());
				}
				throw new \Exception('Missing ??Id_IN? for `'.$from.'`');
			}
			$parameters['Id_IN'] = [$ids_list];
		}
		
		if ($query_by_data_type)
		{
			# return QModelQuery::BindQuery($query, $binds, $this ?: get_called_class(), $dataBlock, $skip_security);
			$return_data = \QModelQuery::BindQuery($query, $parameters, $query_by_data_type, $data_block, true, $selector);
		}
		else
		{
			$return_data = QModelQuery::BindQuery($query, $parameters, null, $data_block, true, $selector);
			$return_data = $return_data ? $return_data->$from : null;
		}

		if (method_exists($called_class, "ApiQuery_out_"))
			$called_class::ApiQuery_out_();

		return ($only_first && (qis_array($return_data))) ? ($return_data ? reset($return_data) : null) : $return_data;
	}
}