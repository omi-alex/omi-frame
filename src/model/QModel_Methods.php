<?php

trait QModel_Methods
{
		public function __construct($id = null)
	{
		if ($id !== null)
		{
			if (is_array($id))
				$this->extractFromArray($id);
			else 
				$this->setId($id);
		}
	}
	/**
	 * The init function
	 * 
	 * @param boolean $recursive
	 */
	public function init($recursive = false)
	{
		if ($this->_qini)
			return true;
		$this->_qini = true;
		
		if ($recursive)
		{
			foreach ($this as $name => $value)
			{
				if (($name{0} !== '_') && ($value instanceof QIModel))
					$value->init($recursive);
			}
		}
	}

	/**
	 * Gets the model type
	 * 
	 * @return QModelType
	 */
	public function getModelType()
	{
		return $this->_ty ?: ($this->_ty = self::GetTypeByName(get_class($this)));
	}
	/**
	 * Gets the [default] storage container that this model should be in
	 * In case his information is spread over multiple places it will return them all
	 * The role of the container is specified by $use_for
	 *
	 * @param integer $use_for
	 * 
	 * @return QIStorageContainer|QIStorageContainer[]
	 */
	public function getContainers($use_for = null)
	{
		if (!$this->_sc)
			$this->_sc = $this->getDefaultContainers();
		return ($use_for && is_array($this->_sc)) ? $this->_sc[$use_for] : $this->_sc;
	}
	
	/**
	 * Sets a container or more
	 * 
	 * @param QIStorage|QIStorage[] $container
	 * @param integer $use_for
	 */
	public function setContainers($container, $use_for = null)
	{
		if ($use_for)
			$this->_sc[$use_for] = $container;
		else
			$this->_sc = $container;
	}
	
	/**
	 * Gets the default container(s) for this instance
	 * 
	 * @return QIStorage|QIStorage[]
	 */
	protected function getDefaultContainers()
	{
		return QApp::GetStorage()->getDefaultStorageContainerForType($this->getModelType());
	}


	/**
	 * Gets the identifier of the object
	 *
	 * @return integer|string
	 */
	public function getId()
	{
		return $this->_id;
	}
	
	/**
	 * Gets the full id of this instance
	 * 
	 * @return string
	 */
	public function getFullId()
	{
		return $this->_id ? $this->_id."\$".$this->getModelType()->class : 
				("?".($this->_tmpid ?: ($this->_tmpid = QModel::GetNextId()))."\$".$this->getModelType()->class);
	}
	
	/**
	 * Sets the identifier of the object
	 * 
	 * @param integer|string $id
	 */
	public function setId($id, $check = true, $null_on_fail = false)
	{
		if (is_numeric($id))
		{
			$id = (int)$id;
			if ($id === 0)
				$id = null;
		}
		else if (is_string($id) && (($id === '') || ctype_space($id)))
			$id = null;
		$this->_id = $this->Id = $id;
	}
	
	/**
	 * Gets the transform state of the object 
	 * 
	 * @return integer|string 
	 */
	public function getTransformState()
	{
		// 	 * $_ts  This is the transform state of the object : No Action, Create / Update / ... + Custom
		return $this->_ts;
	}
	/**
	 * Sets the transform state of the object 
	 * 
	 * @param integer|string $transf_state 
	 */
	public function setTransformState($transf_state)
	{
		$this->_ts = $transf_state;
	}
	/**
	 * Gets the lock state of this object
	 * 
	 * @return bool|integer
	 */
	public function getLockState()
	{
		return $this->_lk;
	}
	/**
	 * Sets the lock state of this object
	 * 
	 * @param bool|integer $lock_state
	 */
	public function setLockState($lock_state)
	{
		$this->_lk = $lock_state;
	}
	/**
	 * Gets the property with the specified name
	 * Get also may specify the `$requester` of the data
	 * Based on the requester we may decide to return the data differently
	 * If $read_again_from === true it will read from all containers that are used by this instance
	 * If $property is null then we do a reload of this object from the storage(s)
	 *
	 * @param string|string[] $property
	 * @param boolean|QIStorageContainer|QIStorageContainer[] $query_if_unset
	 * @param mixed $requester
	 * @param mixed $reason
	 * 
	 * @return mixed
	 */
	public function get($property = null, $query_if_unset = null, $requester = null, $reason = null)
	{
		// if query if unset was called, query for the unset property/properties specified
		if ($query_if_unset)
		{
			if (is_string($property) && (!$this->wasSet($property)))
				$this->query($property);
			else 
			{
				if (!$property)
				{
					$q = array();
					foreach ($this->getModelType()->properties as $prop)
					{
						if (!$this->wasSet($prop->name))
							$q[] = $prop->name;
					}
					if (!empty($q))
						$this->query($q);
				}
				else
				{
					$q = array();
					foreach ($property as $prop)
					{
						if (!$this->wasSet($prop))
							$q[] = $prop;
					}
					if (!empty($q))
						$this->query($q);
				}
			}
		}
		
		if (is_string($property))
		{
			if ($property{0} == "_")
				return $this->$property;
			return ($get = ($this->_ty ? $this->_ty->properties[$property]->getter : $this->getModelType()->properties[$property]->getter)) ? $this->$get() : $this->$property;
		}
		else if (!$property)
		{
			// if no property was specifed get all values in an array
			$ty = $this->getModelType();
			$ret = new QModelArray();
			foreach ($ty->properties as $k => $prop)
				$ret[$k] = ($prop->getter) ? $this->{$prop->getter}() : $this->{$prop->name};
			return $ret;
		}
		else if (is_array($property))
		{
			// at this point $property should be an array of strings
			$props = $this->getModelType()->properties;
			$ret = new QModelArray();
			foreach ($property as $k)
			{
				$get = $props[$k]->getter;
				$ret[$k] = $get ? $this->$get() : $this->$k;
			}
			return $ret;
		}
		else
			throw new Exception("Unsupported type for parameter \$property");
	}
	
	/**
	 * Sets the property with the specified name
	 * Set also may specify the `$requester` 
	 * Based on the requester we may decide to set the data differently
	 * We may also specify more keys at a time. In such case we expect either an object or an associative array 
	 * and $value is expected to be null
	 * If $transform_with === true it will transform with all containers that are used by this instance
	 *
	 * @param string|string[] $property
	 * @param mixed $value
	 * @param boolean|QIStorageContainer|QIStorageContainer[] $transform_with The containers to transform with
	 * @param mixed $requester
	 * @param mixed $reason
	 * 
	 */
	public function set($property, $value = null, $transform_with = null, $requester = null, $reason = null)
	{
		if (is_string($property))
		{
			$prop = $this->getModelType()->properties[$property];
			if (!$prop)
				throw new Exception("The class ".get_class($this)." is missing the property ".$property);
			$value = $prop->fixVal($value);
			
			if (($value instanceof QFile) && ($_fstorage = $prop->storage))
				$value->_fstorage = $_fstorage;
			
			if ($property{0} === "_")
			{
				$this->$property = $value;
				return;
			}
			// manage saving the old property
			if ((!(isset($this->_ols[$property]) || ($this->_ols && array_key_exists($property, $this->_ols)))) && (($value === null) || ($this->$property !== $value)))
				$this->_ols[$property] = $this->$property;
			// end old
			$set = $this->getModelType()->properties[$property]->setter;
			if ($set)
				$this->$set($value);
			else
				$this->$property = $value;
		}
		else
		{
			// at this point $property should be an array of strings and the value null
			if ($value)
				throw new Exception("When the \$property parameter is an array the \$value parameter should be null.");
			$props = $this->getModelType()->properties;
			foreach ($property as $k => $_v)
			{
				$prop = $props[$k];
				if (!$prop)
					throw new Exception("The class ".get_class($this)." is missing the property ".$k);
				$v = $prop->fixVal($_v);
				
				if (($v instanceof QFile) && ($_fstorage = $prop->storage))
					$v->_fstorage = $_fstorage;
				
				if ($k{0} == "_")
				{
					$this->$k = $v;
					continue;
				}
				// manage saving the old property
				if ((!(isset($this->_ols[$k]) || ($this->_ols && array_key_exists($k, $this->_ols)))) && (($value === null) || ($this->$k !== $v)))
					$this->_ols[$k] = $this->$k;
				// end old
				$set = $prop->setter;
				if ($set)
					$this->$set($v);
				else
					$this->$k = $v;
			}
		}
	}
		
	/**
	 * Queries the storage to get data
	 * For QIModelArray a new instance will be created and returned
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public static function QueryAll($query = null, $binds = null, &$dataBlock = null, $skip_security = true)
	{
		if ($query === null)
			$query = static::GetListingQuery();
		return QModelQuery::BindQuery($query, $binds, get_called_class(), $dataBlock, $skip_security);
	}
	/**
	 * Queries the storage to get data
	 * For QIModelArray a new instance will be created and returned
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public static function Q($query = null, $binds = null, &$dataBlock = null, $skip_security = true)
	{
		$class = get_called_class();
		return QModelQuery::BindQuery($query ?: $class::GetListingEntity(), $binds, $class, $dataBlock, $skip_security);
	}
	
	/**
	 * Queries the storage to get data using the self::GetListingEntity() as a starting selector
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel[]
	 */
	public static function QueryList($query = null, $binds = null, &$dataBlock = null, $skip_security = true)
	{
		$class = get_called_class();
		$list_entity = $class::GetListingEntity();
		return QModelQuery::BindQuery($query ? $list_entity.", ".$query : $list_entity, $binds, $class, $dataBlock, $skip_security);
	}
	
	/**
	 * Queries the storage and returns the first element found
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public static function QueryFirst($query, $binds = null, &$dataBlock = null, $skip_security = true)
	{
		return (($res = QModelQuery::BindQuery($query, $binds, get_called_class(), $dataBlock, $skip_security)) && $res[0]) ? $res[0] : null;
	}
	
	/**
	 * Queries the storage by id
	 * 
	 * @param string|integer $id
	 * @param string $selector Optional selector. If not present self::GetModelEntity() will be used.
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public static function QueryById($id, $selector = null, &$dataBlock = null, $skip_security = true)
	{
		$query = ($selector ?: static::GetModelEntity())." WHERE Id=?";
		$binds = $id;
		return (($res = QModelQuery::BindQuery($query, $binds, get_called_class(), $dataBlock, $skip_security)) && $res[0]) ? $res[0] : null;
	}
	
	/**
	 * Queries the storage by ids
	 * 
	 * @param string[]|integer[] $ids
	 * @param string $selector Optional selector. If not present self::GetModelEntity() will be used.
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public static function QueryByIds(array $ids, $selector = null, &$dataBlock = null, $skip_security = true)
	{
		$query = ($selector ?: static::GetModelEntity())." WHERE Id IN (?)";
		return QModelQuery::BindQuery($query, [$ids], get_called_class(), $dataBlock, $skip_security);
	}
	
	/**
	 * Checks if a property was set
	 * 
	 * This is @deprecated !!!!
	 * It will always return true for now
	 * 
	 * @param string $property
	 * 
	 * @return bool 
	 */
	public function wasSet($property)
	{
		return ($this->$property !== null) || $this->_wst[$property];
		// return ($this->$property !== null) || isset($this->_ols[$property]) || ($this->_ols && array_key_exists($property, $this->_ols));
	}
	
	/**
	 * Tests if a property was changed
	 * If no property is specified tests if any property was changed
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function wasChanged($property = null)
	{
		if ($property)
		{
			// if not _ols or _ols[$property] then it was not changed 
			return ((!$this->_ols) || (!(isset($this->_ols[$property]) || array_key_exists($property, $this->_ols)))) ? false : $this->$property !== $this->_ols[$property];
		}
		else
		{
			if ($this->_ols)
			{
				foreach ($this->_ols as $k => $v)
					if ($v !== $this->$k)
						return true;
				return false;
			}
			else
				return false;
		}
	}
	
	/**
	 * Tests if a property was changed
	 * If no property is specified tests if any property was changed
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function hasChanged($property = null)
	{
		if ($property)
		{
			// if not _ols or _ols[$property] then it was not changed 
			return ((!$this->_ols) || (!(isset($this->_ols[$property]) || array_key_exists($property, $this->_ols)))) ? false : $this->$property !== $this->_ols[$property];
		}
		else
		{
			if ($this->_ols)
			{
				foreach ($this->_ols as $k => $v)
					if ($v !== $this->$k)
						return true;
				return false;
			}
			else
				return false;
		}
	}
	
	/**
	 * Checks if a property has the old value saved
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function hasOldValue($property)
	{
		return isset($this->_ols[$property]) || ($this->_ols && array_key_exists($property, $this->_ols));
	}
	
	/**
	 * Gets the old/initial value of a property
	 * 
	 * @param string $property
	 * @return mixed
	 */
	public function getOldValue($property)
	{
		return isset($this->_ols[$property]) ? $this->_ols[$property] : null;
	}
	
	/**
	 * Gets the list of changed properties
	 * 
	 * @return string[]
	 */
	public function getChangedProperties()
	{
		if ($this->_ols)
		{
			$list = array();
			foreach ($this->_ols as $k => $v)
				if ($v !== $this->$k)
					$list[] = $k;
			if (empty($list))
				return null;
			else 
				return $list;
		}
		else
			return null;
	}

	/**
	 * Default setter in case we force getters and setters with the `private` or `protected` keywords
	 * 
	 * @param string $property
	 * @param mixed $value
	 * 
	 * @return boolean
	 */
	
	/*
	public function __set($property, $value)
	{
		if ($property{0} == '_')
		{
			$this->{$property} = $value;
			return;
		}
		return $this->set($property, $value);
	}
	 * 
	 */

	/**
	 * Run a transform on a MODEL
	 * The action is one of the QIModel::ModelState*, custom actions may be used as strings
	 * Specific containers may be specified, if not the ones provided by QIModel::getContainers will be used
	 * If $recurse is set to true transform will be called foreach property that implements QIModel 
	 * The default is to recurse
	 * If $as_simulation is set to true the code should not actually do anything, it should just test and see if
	 * the actions can be executed ok. In the future we should provide output so that the user will be able to 
	 * see what can and can't be done and why. (TO DO)
	 * The operation should throw an error if it fails.
	 * In case of failure we should ROLLBACK (TO DO)
	 *
	 * @param mixed $parameters
	 * @param QIStorageContainer|QIStorageContainer[] $containers
	 * @param boolean $recurse
	 * @param QBacktrace $backtrace
	 * @param boolean $as_simulation
	 */
	public function frame_transform($parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, 
			$as_simulation = false, &$issues = null, &$root_issues = null, bool $trigger_provision = true, bool $trigger_events = true, 
			bool $trigger_save = false, bool $trigger_import = false)
	{
		// if the object is not marked for transform or a lock is already in place on this object we avoid infinte loop
		//var_dump($recurse);
		//die();

		if ($this->_lk && ($recurse === true))
			return $this;

		if ($this->_ts === null)
			$this->_ts = self::TransformMerge;
		
		if (is_string($recurse))
			$recurse = qParseEntity($recurse);

		$is_root_call = ((!$backtrace) || ($backtrace === $backtrace->root));
		if ($is_root_call)
		{
			\QAudit::BackupTransform($this, $recurse, $parameters);

			if (!$issues)
				$issues = array();
			if (!$root_issues)
			{
				$root_issues = &$issues;
				$root_issues["_count_"] = 0;
			}
		}

		// we mark the fact that the sync started on this instance
		if ($recurse === true)
			$this->_lk = true;
		
		$containers = $containers ?: $this->getContainers(self::StorageContainerForWrite);
		
		// we should wrap this one (ok)
		if (!$backtrace)
			$backtrace = new QBacktrace(null, $this, true);
		
		if ($recurse)
		{
			$type = $this->getModelType();
			
			$rec_isarr = is_array($recurse);
		
			foreach ($type->properties as $property)
			{
				$value = $this->{$property->name};
				// TO DO : We should also check the data type(s) supported by the property
				if (!($value instanceof QIModel))
					continue;
				
				$rec_child = $rec_isarr ? ($recurse[$property->name] ?: $recurse["*"]) : $recurse;

				if (!$rec_child)
					continue;

				$next_bkt = $backtrace ? $backtrace->next($value) : null;
				if (($value instanceof QIModelArray) && (!$value->getModelProperty()))
					$value->setModelProperty($property);
				$value->transform($parameters, null, $rec_child, $next_bkt, $as_simulation, ($issues[$property->name] = array()), $root_issues, $trigger_provision, $trigger_events);
			}
		}

		if ($is_root_call)
		{
			if ($root_issues["_count_"] > 0)
			{
				$error = new Exception("Issues: ".json_encode($root_issues));
				throw $error;
			}
			else
			{
				try
				{
					if (self::$SecurityCheckTransform)
					{
						$user = QUser::GetCurrentUser();
						self::SecurityCheck($this, null, null, $user, null, null);
					}
					
					$exec_transform_state = is_int($parameters) ? $parameters : (($parameters && is_int($parameters["ts"])) ? $parameters["ts"] : null);

					if (qis_array($containers))
					{
						foreach ($containers as $container)
							$container->executeTransaction($backtrace, $exec_transform_state, $recurse, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
					}
					else 
						$containers->executeTransaction($backtrace, $exec_transform_state, $recurse, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
				}
				catch (Exception $ex)
				{
					throw $ex;
				}
			}
		}
		
		// we are done with the sync & locks reset the variables
		// $this->_lk = false;
		// $this->_ts = null;
		
		// TO DO: we need to set the actual action taken in 'actualAction'!
		if ($is_root_call)
		{
			$this->cleanupLocks();
		}
		
		return $this;
	}
	
	/**
	 * Cleanup transaction/transform locks
	 */
	public function cleanupLocks()
	{
		if ($this->_lk)
		{
			$this->_lk = false;
			$type = $this->getModelType();

			foreach ($type->properties as $property)
			{
				$value = $this->{$property->name};
				if ($value instanceof QIModel)
					$value->cleanupLocks();
			}
		}
	}
	
	/**
	 * This method should be called before a model transform 'Per container'
	 *
	 * @param QIStorageContainer $container
	 * @param mixded $params
	 */
	public function beforeTransformModelByContainer(QIStorageContainer $container, $params = null)
	{
		// nothing by default
	}
	
	/**
	 * Outputs the content of the object into a string. 
	 * The function avoids recursion
	 * 
	 * @param QIModel $self
	 * 
	 * @return string
	 */
	public static function DumpIt($self)
	{
		qDSDumpVar($self);
	}
	
	/**
	 * Creates a temporary id on the object
	 * 
	 * @return integer
	 */
	public function getTemporaryId()
	{
		return $this->_tmpid ?: ($this->_tmpid = QModel::GetNextId());
	}
	
	/**
	 * Outputs the content of the object into a JSON string. 
	 * The function avoids recursion
	 * 
	 * @param string $str Description
	 * @param QIModel[] $refs
	 * 
	 * @return string
	 */
	public function exportToJs($ignore_nulls = false, &$str = null, &$refs = null)
	{
		if (!$str)
			$str = "";
		if (!$refs)
			$refs = array();
		$f_id = $this->getFullId();
		//var_dump($f_id);
		// link it as a reference
		$m_type = $this->getModelType();
		
		$str .= "{\"_ty\":\"".$m_type->class."\"";
		$id = $this->getId();
		if ($id === null)
			$str .= ",\"_tmpid\":".$this->getTemporaryId();
		else
			$str .= ",\"_id\":".json_encode($id);
		$ts = $this->getTransformState();
		if ($ts !== null)
			$str .= ",\"_ts\":".(($ts === null) ? "null" : $ts);
		if ($refs[$f_id] === $this)
		{
			$str .= ",\"_isref\": true}\n";
			return;
		}
		else if ($this->no_export && ((!is_array($ignore_nulls)) || (!$ignore_nulls[1])))
		{
			$str .= ",_noexp: true}\n";
			return;
		}

		$refs[$f_id] = $this;
		foreach ($this as $k => $v)
		{
			if (($k{0} == "_") || ($ignore_nulls && ($v === null)))
				continue;

			$str .= ",\n\"".$k."\":";
			if ($v === null)
				$str .= "null";
			else if (is_string($v))
				$str .= json_encode($v, JSON_UNESCAPED_SLASHES);
			else if (is_integer($v) || is_float($v))
				$str .= $v;
			else if (is_bool($v))
				$str .= $v ? "true" : "false";
			else if ($v instanceof QIModel)
			{
				if (($v instanceof QIModelArray) && (!$v->getModelProperty()))
				{
					//if (!$m_type->properties[$k])
					//	throw new Exception("Model type for class ".get_class($this)." has no property with name {$k}");
					$v->setModelProperty($m_type->properties[$k]);
				}
				$v->exportToJs($ignore_nulls, $str, $refs);
			}
			else if (is_array($v) || is_object($v))
	            {
				
					$str .= json_encode($v);
	            }
		}
		$str .= "}\n";
		return $str;
	}

	/**
	 * Gets the model type
	 * 
	 * @param string $type
	 * 
	 * @return QModelType|QIModelTypeUnstruct
	 */
	public static function GetTypeByName($type)
	{
		if (!$type)
			return null;
		if (isset(self::$Types[$type]))
			return self::$Types[$type];
		else if ($type{0} === strtolower($type{0}))
		{
			// we have a scalar type
			switch ($type)
			{
				case QModelType::ModelTypeInteger:
				case "integer":
				case "int":
					return self::$Types[$type] = QModelTypeInteger::GetType();
				case QModelType::ModelTypeString:
				case "string":
					return self::$Types[$type] = QModelTypeString::GetType();
				case QModelType::ModelTypeFloat:
				case "float":
				case "double":
					return self::$Types[$type] = QModelTypeFloat::GetType();
				case QModelType::ModelTypeBoolean:
				case "boolean":
				case "bool":
					return self::$Types[$type] = QModelTypeBoolean::GetType();
				case QModelType::ModelTypeNull:
				case "null":
					return self::$Types[$type] = QModelTypeNull::GetType();
				case QModelType::ModelTypeArray:
				case "array":
					return self::$Types[$type] = QModelTypeArray::GetType();
				case QModelType::ModelTypeDate:
				case "date":
					return self::$Types[$type] = QModelTypeDate::GetType();
				case QModelType::ModelTypeDateTime:
				case "datetime":
					return self::$Types[$type] = QModelTypeDateTime::GetType();
				case QModelType::ModelTypeTime:
				case "time":
					return self::$Types[$type] = QModelTypeTime::GetType();
				case QModelType::ModelTypeTimestamp:
				case "timestamp":
					return self::$Types[$type] = QModelTypeTimestamp::GetType();
				case QModelType::ModelTypeResource:
				case "resource":
					return self::$Types[$type] = QModelTypeResource::GetType();
				case QModelType::ModelTypeMixed:
				case "mixed":
					return self::$Types[$type] = QModelTypeMixed::GetType();
				case QModelType::ModelTypeCallback:
				case "callback":
					return self::$Types[$type] = QModelTypeCallback::GetType();
				case QModelType::ModelTypeScalar:
				case "scalar":
					return self::$Types[$type] = QModelTypeScalar::GetType();
				case QModelType::ModelTypeScalarOrArray:
				case "scalarorarray":
					return self::$Types[$type] = QModelTypeScalarOrArray::GetType();
				case QModelType::ModelTypeObject:
				case "object":
					return self::$Types[$type] = QModelTypeObject::GetType();
				case QModelType::ModelTypeFile:
				case "file":
					return self::$Types[$type] = QModelTypeFile::GetType();
				default:
				{
					throw new Exception("Unregistered scalar type: {$type}");
				}
			}
		}
		else
		{
			// we have a QIModel
			$cache_file = QAutoload::GetRuntimeFolder()."temp/types/".qClassToPath($type).".type.php";
			if (!file_exists($cache_file))
			{
				// throw new Exception("Missing type informatio for: {$type}. Please call QAutoload::EnableDevelopmentMode() to resolve the issue.");
				$cache_res = QCodeStorage::CacheData($type, $cache_file);
				return (self::$Types[$type] = (is_array($cache_res) ? reset($cache_res) : $cache_res));
			}
			else
			{
				// var_dump("from cache: ".$cache_file);
				// $t1 = microtime(true);
				require($cache_file);
				// ${"Q_TYPECACHE_{$type}"} = QCodeStorage::GetDefault()->storageGetModelById($type);
				// $t2 = microtime(true);
				// var_dump($t2 - $t1);
				$str_type = qClassToVar($type);
				return (self::$Types[$type] = ${"Q_TYPECACHE_{$str_type}"});
			}
		}
	}
	
	/**
	 * Gets the name of a scalar based on its id
	 * 
	 * @param integer $id_type
	 * @return string
	 * @throws Exception
	 */
	public static function GetScalarNameById($id_type)
	{
		switch ($id_type)
		{
			case QModelType::ModelTypeInteger:
				return "integer";
			case QModelType::ModelTypeString:
				return "string";
			case QModelType::ModelTypeFloat:
				return "float";
			case QModelType::ModelTypeBoolean:
				return "boolean";
			case QModelType::ModelTypeNull:
				return "null";
			case QModelType::ModelTypeArray:
				return "array";
			case QModelType::ModelTypeDate:
				return "date";
			case QModelType::ModelTypeDateTime:
				return "datetime";
			case QModelType::ModelTypeTime:
				return "time";
			case QModelType::ModelTypeTimestamp:
				return "timestamp";
			case QModelType::ModelTypeResource:
				return "resource";
			case QModelType::ModelTypeMixed:
				return "mixed";
			case QModelType::ModelTypeCallback:
				return "callback";
			case QModelType::ModelTypeScalar:
				return "scalar";
			case QModelType::ModelTypeScalarOrArray:
				return "scalarorarray";
			case QModelType::ModelTypeObject:
				return "object";
			case QModelType::ModelTypeFile:
				return "file";
			default:
			{
				throw new Exception("Unregistered scalar type: {$id_type}");
			}
		}
	}

	/**
	 * Gets all the types info that were loaded so far.
	 * 
	 * @return QModelType[]
	 */
	public static function GetAllTypes()
	{
		return self::$Types;
	}
	
	/**
	 * Gets the next unique id
	 * 
	 * @return integer
	 */
	public static function GetNextId()
	{
		return (self::$NextId === null) ? (self::$NextId = (int)(microtime(true) * 10000)) : ++self::$NextId;
	}
	
	/**
	 * Extracts the type from a variable, as an option you can also specify the accepted types for it
	 * @todo force list of accepted types
	 * 
	 * @todo force list of accepted types
	 * 
	 * @param mixed $variable
	 * @param QModelAcceptedType|QModelProperty $types
	 * @return QModelType|QIModelTypeUnstruct
	 */
	public static function GetTypeForVariable($variable, $types = null)
	{
		if ($variable === null)
		{
			// null is always accepted
			return QModelTypeNull::GetType();
		}
		else if ($variable instanceof QIModel)
			return $variable->getModelType();
		/* TO DO !!!!
		else if ($types)
			return $types->validateScalarTypeForVariable($variable);
		*/
		else if (is_string($variable))
			return QModelTypeString::GetType();
		else if (is_int($variable))
			return QModelTypeInteger::GetType();
		else if (is_array($variable))
			return QModelTypeArray::GetType();
		else if (is_bool($variable))
			return QModelTypeBoolean::GetType();
		else if (is_float($variable))
			return QModelTypeFloat::GetType();
		else 
		{
			// is_numeric()
			// is_object()
			// is_resource()
			return null;
		}
	}
	
	/**
	 * Gets the model defaults foreach property
	 * 
	 * @return QIModel[]
	 */
	public static function getModelDefaults()
	{
		return null;
	}
	/**
	 * Loads the model defaults foreach property
	 * 
	 */
	public function loadModelDefaults()
	{
		
	}
	
	/**
	 * Gets the props names
	 * 
	 * @return string[]
	 */
	public function getPropertiesNames()
	{
		// we have some redundant code for optimization
		return $this->_ty ? ($this->_ty->_ppkeys ?: ($this->_ty->_ppkeys = $this->_ty->properties->getKeys())) : $this->getModelType()->getPropertiesNames();
	}
	
	/**
	 * Alias for "save"
	 *
	 * @param string|boolean|null $selector
	 */
	public function store($selector = false, $data = null, $state = null)
	{
		return $this->save($selector, $data, $state);
	}
	/**
	 * Saves the model in the Storage, based on it's state, the default is merge
	 *
	 * @param string|boolean|null $selector
	 */
	public function save($selector = null, $data = null, $state = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if ($data)
			$this->extractFromArray($data);
		// $parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false, &$issues = null, &$root_issues = null, bool $trigger_provision = true, bool $trigger_events = true
		$issues = null;
		$root_issues = null;
		return $this->transform($state, null, $selector, null, false, $issues, $root_issues, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
	}
	/**
	 * Run a MERGE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function merge($selector = null, $data = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		$this->_ts = QModel::TransformMerge;
		return $this->save($selector, $data, array("ts" => QModel::TransformMerge), $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
	}
	/**
	 * Run a INSERT on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function insert($selector = null, $data = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		$this->_ts = QModel::TransformCreate;
		return $this->save($selector, $data, array("ts" => QModel::TransformCreate));
	}
	/**
	 * Run a UPDATE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function update($selector = null, $data = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		$this->_ts = QModel::TransformUpdate;
		return $this->save($selector, $data, array("ts" => QModel::TransformUpdate));
	}
	/**
	 * Run a DELETE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function delete($selector = null, $data = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		$this->_ts = QModel::TransformDelete;
		return $this->save($selector, $data, array("ts" => QModel::TransformDelete));
	}
	
	/**
	 * Marks the instance for deletion by applying $this->_ts = self::TransformDelete
	 */
	public function markDelete()
	{
		$this->_ts = self::TransformDelete;
	}
	
	/**
	 * Called when an upload was made and you need to do something with the file
	 * As an option the file could be handled on upload
	 * 
	 * @param string $property
	 * @param string[] $upload_info
	 */
	public function handleUpload($property, $upload_info)
	{
		return QUndefined();
	}
	
	/**
	 * @todo
	 * 
	 * @param QUser $User
	 * @param type $req_rights
	 * @param type $property
	 * @param type $extra_entities
	 * @return boolean
	 */
	public function SecurityFilter(QUser $User, $req_rights, $property = null, &$extra_entities = null)
	{
		return true;
	}
	
	/**
	 * @todo
	 * 
	 * @param QIModel $item
	 * @param QIModel $parent
	 * @param type $property
	 * @param QUser $user
	 * @param type $req_rights
	 * @param type $objects
	 */
	public function SecurityCheck(QIModel $item, QIModel $parent, $property, QUser $user, $req_rights, &$objects)
	{
		// to do: extra entities, ways to handle negative returns (we need some kind of unset bag and release all objects)
		// don't expect $objects to be ready from the start
		// how do we handle collections ?!?!
		
		$req_rights = ($req_rights === null) ? $this->_ts : $req_rights;
		
		$res = $item->SecurityFilter($user, QPermsFlagRead, null, $extra_entities);
		if (!$res)
		{
			// we need some kind of unset bag and release all objects
			// destroy object or throw an error
			if ($parent && $property)
				$parent->$property = null;
		}
		$props = $item->getPropertiesNames();
		
		foreach ($props as $prop)
		{
			$res = $item->SecurityFilter($user, QPermsFlagRead, $prop, $extra_entities);
			if (!$res)
				$item->$prop = null;
			else
				self::SecurityCheck($item->$prop, $item, $prop, $user, $req_rights, $objects);
		}
	}
	
	/**
	 * Sets the dimentions for all models
	 * 
	 * @param array[] $dims_def
	 */
	public static function SetDimsDef($dims_def)
	{
		self::$DimsDef = $dims_def;
		foreach ($dims_def as $def_key => $def_values)
			self::$Dims[$def_key] = reset($def_values);
	}
	
	/**
	 * Sets the current value of a dimention
	 * 
	 * @param string $dim_key
	 * @param scalar|scalar[] $value
	 */
	public static function SetDim($dim_key, $value)
	{
		self::$Dims[$dim_key] = $value;
	}
	
	/**
	 * Gets the current value of a dimention
	 * 
	 * @param string $dim_key
	 * @return scalar|scalar[]
	 */
	public static function GetDim($dim_key)
	{
		return self::$Dims[$dim_key];
	}

	/**
	 * Gets a model's caption
	 * 
	 * @return string
	 */
	public function getModelCaption()
	{
		return get_class($this)." #".(($id = $this->getId()) ? $id : "[new]");
	}
	
	/**
	 * Dumps the model ID data as a string
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return (string)$this->getModelCaption();
	}
	
	/**
	 * @todo This should be replaced in the future
	 * 
	 * Adapts $data if needed
	 * 
	 * @param object $data
	 */
	public function apiAdaptInput(object $data = null)
	{
		
	}

	/**
	 * In case the property is a file type, gets it's path based on the value of the property for this instance
	 * 
	 * @param string $property_name
	 * @return string
	 */
	public function getFullPath($property_name)
	{
		$path = $this->getModelType()->properties[$property_name]->storage["filePath"];
		return rtrim($path, "\\/")."/".(($this->$property_name instanceof QFile) ? $this->$property_name->Path : $this->$property_name);
	}
	
	/**
	 * In case the property is a file type, gets it's name based on the value of the property for this instance
	 * 
	 * @param string $property_name
	 * @return string
	 */
	public function getFileName($property_name)
	{
		return ($this->$property_name instanceof QFile) ? $this->$property_name->Path : $this->$property_name;
	}
	
	/**
	 * In case the property is a file type, gets it's filePath info
	 * 
	 * @param string $property_name
	 * @return string
	 */
	public function getStoragePath($property_name)
	{
		return $this->getModelType()->properties[$property_name]->storage["filePath"];
	}
	
	/**
	 * Checks if $this equals $object
	 * Two model object are equal if they have the same ID - getId() and the same class
	 * 
	 * @param QIModel $object
	 * @return boolean
	 */
	public function equals(QIModel $object)
	{
		return ($this->getId() === $object->getId()) && (get_class($this) === get_class($object));
	}

	public function check($selector = null, $throw_exception = false)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		if (!is_array($selector))
			return;
		
		$data = reset($selector);
		$prop = key($selector);
		$all_keys = ($prop === "*");
		$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;

		if ($all_keys)
		{
			// skip #%tables
			next($type_inf);
			// skip #%table
			next($type_inf);
			// skip #%id
			next($type_inf);
			// skip #%misc
			next($type_inf);

			$prop = key($type_inf);
			// we no longer loop
			$data = $selector["*"];
		}

		try
		{
			while ($prop)
			{
				$val = $this->{$prop};
				//echo "Evaluating: {$prop}<br/>";
				if ($val !== null)
				{
					//echo "Checking: {$prop}<br/>";
					//echo "set{$prop}<br/>";
					$this->{"set{$prop}"}($this->$prop);
					if ($val instanceof QIModel)
					{
						if ($val instanceof QIModelArray)
						{
							$p_meth = "set{$prop}_Item_";
							// setProp_Item_
							foreach ($val as $k => $item)
							{
								$this->$p_meth($item, $k);
								if (($item instanceof QIModel) && ($item->check($data, $throw_exception) === false))
									return false;
							}
						}
						else
						{
							// setProp
							if ($val->check($data, $throw_exception) === false)
								return false;
						}
					}
				}

				if ($all_keys)
				{
					next($type_inf);
					$prop = key($type_inf);
					$data = $selector[$prop] ?: [];
				}
				else
				{
					// here it must be an array
					$data = next($selector);
					$prop = key($selector);
				}
			}
		}
		catch (Exception $ex)
		{
			if ($throw_exception)
				throw $ex;
			else
				return false;
		}
		// if all ok
		return true;
	}
	
	public function touch($selector = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		if (!is_array($selector))
			return;
		
		$data = reset($selector);
		$prop = key($selector);
		$all_keys = ($prop === "*");
		$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;

		if ($all_keys)
		{
			// skip #%tables
			next($type_inf);
			// skip #%table
			next($type_inf);
			// skip #%id
			next($type_inf);
			// skip #%misc
			next($type_inf);

			$prop = key($type_inf);
			// we no longer loop
			$data = $selector["*"];
		}

		while ($prop)
		{
			$val = $this->{$prop};
			if ($val !== null)
			{
				// touch
				$this->_wst[$prop] = true;
				if ($val instanceof QIModel)
				{
					if ($val instanceof QIModelArray)
					{
						foreach ($val as $item)
						{
							// touch
							$this->_wst[$prop] = true;
							$item->touch($data);
						}
					}
					else
					{
						// touch
						$val->touch($data);
					}
				}
			}

			if ($all_keys)
			{
				next($type_inf);
				$prop = key($type_inf);
				$data = $selector[$prop] ?: [];
			}
			else
			{
				$data = next($selector);
				$prop = key($selector);
			}
		}
		// if all ok
		return true;
	}
	
	public function getBindValue()
	{
		return get_class($this)."|".$this->getId();
	}
	
	/**
	 * Transforms the object into a PHP array. 
	 * The function avoids recursion
	 * Also 2 objects of the same class and same id (getId() is used), will not be included twice.
	 * 
	 * @return array
	 */
	public function toArray($selector = null, $include_nonmodel_properties = false, $with_type = true, $with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
	{
		/*if ($selector === null)
			$selector = static::GetModelEntity();*/
		
		if (is_string($selector))
			$selector = qParseEntity($selector);
		/*else if (!(($selector !== null) && is_array($selector)))
			return;*/
		
		$class = get_class($this);
		
		$arr = [];
		if ($with_type)
			$arr["_ty"] = $class;
		
		$id = $this->getId();
		$was_included = false;
		if ($id !== null)
		{
			if ($with_hidden_ids)
				$arr["_id"] = $id;
			
			if ($refs === null)
				$refs = [];
			if (isset($refs[$id][$class]))
				$was_included = true;
			else
				$refs[$id][$class] = $this;
		}
		else
		{
			if ($refs_no_class === null)
				$refs_no_class = [];
			if (($refs_class = $refs_no_class[$class]) && in_array($this, $refs_class, true))
				$was_included = true;
			else
				$refs_no_class[$class][] = $this;
		}
		
		if (!$was_included)
		{
			$data = ($selector !== null) ? reset($selector) : null;
			$prop = ($selector !== null) ? key($selector) : null;
			$all_keys = ($selector === null) || ($prop === "*");
			$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;
			
			$obj_props = null;
						
			// handle all properties in the selector
			if ($all_keys)
			{
				if ($include_nonmodel_properties)
				{
					if (!$obj_props)
						$obj_props = get_object_vars($this);
					reset($obj_props);
					$prop = key($obj_props);
					// skip model reserved properties
					while($prop && (($prop === "_ty") || ($prop === "_id") || ($prop === "_sc") || ($prop === "_tmpid")))
					{
						next($obj_props);
						$prop = key($obj_props);
					}
				}
				else
				{
					// skip #%tables
					next($type_inf);
					// skip #%table
					next($type_inf);
					// skip #%id
					next($type_inf);
					// skip #%misc
					next($type_inf);

					$prop = key($type_inf);
				}
				// we no longer loop
				$data = null;
			}

			while ($prop !== null)
			{
				$val = $this->{$prop};
				$ty = gettype($val);
				
				/* "boolean" "integer" "double" "string" "array" "object" "resource" "NULL" "unknown type"*/
				switch ($ty)
				{
					case "string":
					case "array":
					case "integer":
					case "double":
					case "boolean":
					{
						$arr[$prop] = $val;
						break;
					}
					case "NULL":
					{
						if (!$ignore_nulls)
							$arr[$prop] = null;
						break;
					}
					case "object":
					{
						if ($val instanceof QIModel)
							$arr[$prop] = $val->toArray($data, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
						else
							$arr[$prop] = (array)$val;
						break;
					}
					default:
						break;
				}

				if ($all_keys)
				{
					if ($include_nonmodel_properties)
					{
						if (!$obj_props)
							throw new \Exception("We must have obj props defined by now!");
						next($obj_props);
						$prop = key($obj_props);
						// skip model reserved properties
						while($prop && (($prop === "_ty") || ($prop === "_id") || ($prop === "_sc") || ($prop === "_tmpid")))
						{
							next($obj_props);
							$prop = key($obj_props);
						}
					}
					else
					{
						next($type_inf);
						$prop = key($type_inf);
					}
					$data = ($selector !== null) ? ($selector[$prop] ?: []) : null;
				}
				else
				{
					$data = ($selector !== null) ? next($selector) : null;
					$prop = ($selector !== null) ? key($selector) : null;
				}
			}
		}
		
		return $arr;
	}
	
	public static function SaveData($data, $selector = true, $state = null, $type = "auto")
	{
		$class = get_called_class();
		$obj = $class::FromArray($data, $type, ($selector === true) ? null : $selector, false);
		if ($obj instanceof QIModel)
			return $obj->save($selector, $state);
		else
			return false;
	}
	
	public static function MergeData($data, $selector = true, $type = "auto", bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		$class = get_called_class();
		$obj = $class::FromArray($data, $type, ($selector === true) ? null : $selector, false);
		if ($obj instanceof QIModel)
			return $obj->merge($selector, null, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
		else
			return false;
	}
	
	public static function InsertData($data, $selector = true, $type = "auto")
	{
		$class = get_called_class();
		$obj = $class::FromArray($data, $type, ($selector === true) ? null : $selector, false);
		if ($obj instanceof QIModel)
			return $obj->insert($selector);
		else
			return false;
	}
	
	public static function UpdateData($data, $selector = true, $type = "auto")
	{
		$class = get_called_class();
		$obj = $class::FromArray($data, $type, ($selector === true) ? null : $selector, false);
		if ($obj instanceof QIModel)
			return $obj->update($selector);
		else
			return false;
	}
	
	public static function DeleteData($data, $selector = true, $type = "auto")
	{
		$class = get_called_class();
		$obj = $class::FromArray($data, $type, ($selector === true) ? null : $selector, false);
		if ($obj instanceof QIModel)
			return $obj->delete($selector);
		else
			return false;
	}
	
	public static function DeleteById($id, $selector = null)
	{
		$class = get_called_class();
		// for now we also need to query for it
		$obj = $class::QueryById($id, $selector);
		if ($obj)
			return $obj->delete($selector);
		else
			return false;
	}
	/**
	 * TODO - change it for bulk remove
	 * 
	 * @param type $ids
	 * @param type $selector
	 */
	public static function DeleteByIds($ids, $selector = null)
	{
		foreach ($ids ?:[] as $id)
			static::DeleteById($id, $selector);
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetListingEntity()
	{
		$class = get_called_class();
		$le = static::$ListingEntity[$class];
		if ($le !== null)
			return $le;
		
		$type_inf = QModelQuery::GetTypesCache($class);
		next($type_inf);
		next($type_inf);
		next($type_inf);
		
		$entity = "";
		$pos = 0;
		while (($property = next($type_inf)))
		{
			if (!isset($property["[]"]))
			{
				if ($pos++)
					$entity .= ",";
				$entity .= key($type_inf);
			}
		}
		return static::$ListingEntity[$class] = qParseEntity($entity);
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetModelEntity($view_tag = null)
	{
		return "*";
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetListingQuery($selector = null)
	{
		$selector = $selector ?: static::GetListingEntity();
		return (is_array($selector) ? qImplodeEntity($selector) : $selector)." ??LIMIT[LIMIT ?,?]";
	}

	/**
	 * Gets a default for a item selector if none was specified
	 * 
	 * @return string
	 */
	public static function frame__GetItemQuery($view_tag = null, $selector = null)
	{
		$selector = null;
		if ($view_tag)
		{
			$app = \QApp::GetDataClass();
			$selector = $app::GetFormEntity_Final($view_tag);
			if (is_array($selector))
				$selector = qImplodeEntity($selector);
			// join it with static::GetModelEntity()
			$selector = qJoinSelectors($selector, static::GetModelEntity());
			if (is_array($selector))
				$selector = qImplodeEntity($selector);
		}
		return (($selector !== null) ? $selector : static::GetModelEntity())." WHERE 1 AND ??Id[Id=?] LIMIT 1";
	}

	public static function Security($value, $is_input_array = false, $selector = null, $maxselector = null, 
										$calling_class = null, $calling_method = null, $self = null, $apply_security = true, 
										$expected_type = null, $action = null, $custom_tag = null)
	{
		// we need to prepare input to a single way so we don't do any checks later on
		if (is_string($selector))
			$selector = qParseEntity($selector);
		if (is_string($maxselector))
			$maxselector = qParseEntity($maxselector);
		
		// @todo: prepare groups for user
		
		// 2. if (calling method on this class) check rights for it also (should be done on the inital call, not now)
		
		if (!$expected_type)
		{
			# @todo a bit more complex here based on $value["_ty"] if is array
			$expected_type = get_called_class();
		}
		else if (substr($expected_type, -2, 2) === "[]")
		{
			$expected_type = substr($expected_type, 0, -2);
			QModelArray::SecurityWorker();
		}
		else
			static::Security_wrap_();
	}
	
	public static function SecurityWorker(&$bag, &$op_cache, $self, $array, $array_refs, $action, $groups, $selector, $max_selector, $throw_error, $unset_if_not_selector, $unset_if_not_allowed)
	{
		// @todo we will need to avoid recursion if there is no selector ->_iid = QModel::$_IncrementedId++;
		// the group tag is constant for sure !
		// BIG_CACHE[0] = BIG_CACHE[groups_tag] // 0 
		
		$called_class = get_called_class();
		if ($op_cache[$called_class] === null)
			$op_cache[$called_class] = [];
		$cache = &$op_cache[$called_class];
		
		$asked_perms = null;
		$loading = false;
		if ($self)
		{
			// if there is no selector, we need to avoid recursion
			if ((!$selector) && (!$max_selector))
			{
				if ($self->_iid)
				{
					if ($bag[$self->_iid])
						return;
				}
				else
				{
					$self->_iid = QModel::$_IncrementedId++;
					$bag[$self->_iid] = true;
				}
			}
			$asked_perms = $action ?: ($self->_ts ?: QIModel::TransformRead);
		}
		else if ($array !== null)
		{
			$self = new $called_class();
			$loading = true;

			if (($obj_id = $array["_id"]) !== null)
				$self->setId($obj_id);
			if ($action === null)
			{
				if (($obj_ts = $array["_ts"]) !== null)
					$asked_perms = $self->_ts = (int)$obj_ts;
				else
					$asked_perms = QIModel::TransformRead;
			}
			else
				$asked_perms = $action;
		}
		else
			$asked_perms = $action ?: QIModel::TransformRead;			
		
		$asked_perms_tag = is_array($asked_perms) ? implode("\n", $asked_perms) : $asked_perms;
		if ($cache === null)
			$cache = [$asked_perms_tag => []];
		else if ($cache[$asked_perms_tag] === null)
			$cache[$asked_perms_tag] = [];
		$cache_p = &$cache[$asked_perms_tag];
		
		// 1. check security at class level (cache it)
		$full_perms = ($cache_p[0] !== null) ? $cache_p[0] : ($cache_p[0] = static::SecurityGetPerms($asked_perms, $groups, static::$_SecurityClass));
		
		if (!$full_perms)
		{
			if ($throw_error)
				throw new \Exception("You do not have rights on ".get_called_class());
			else
				return false;
		}

		// 3. check security at property level (cache it)
		$sp_list = static::$_SecurityProperties;
		$select_all = false;
		if ($max_selector !== null)
		{
			if ($max_selector["*"] !== null)
			{
				if ($selector["*"] !== null)
					$select_all = true;
				else
					$select_all = $selector;
			}
			else
			{
				if ($selector["*"] !== null)
					$select_all = $max_selector;
				else
					$select_all = array_intersect_key($select_all, $max_selector);
			}
		}
		else if ($selector !== null)
		{
			if ($max_selector["*"] !== null)
				$select_all = true;
			else
				$select_all = $max_selector;
		}
		else
			$select_all = true;
		
		if ($loading)
		{
			$type_inf = null;
			// we will need to load the data
			foreach ($array as $k => $v)
			{
				if (($k{0} === "_") ||
						(($select_all !== true) && ($select_all[$k] === null)))
					continue;
				
				$property_perms = null;
				if ($sp_list && (($sp = $sp_list[$k]) !== null))
				{
					$property_perms = ($cache_p[$k] !== null) ? $cache_p[$k] : ($cache_p[$k] = static::SecurityGetPerms($asked_perms, $groups, $sp));
					if (!$property_perms)
						// do not load it
						continue;
				}
				else if (static::$_SecurityPropertiesExclusive)
					// do not load it
					continue;

				if (is_array($v))
				{
					$expected_type = null;
					if (($vc = $v["_ty"]) && class_exists($vc))
					{
						$expected_type = $vc;
						unset($v["_ty"]);
					}
					else
					{
						if (!$type_inf)
							$type_inf = QModelQuery::GetTypesCache(get_class($self));
						$prop_inf = $type_inf[$k];
						if ($prop_inf["[]"])
							// is collection
							$expected_type = "\\QModelArray";
						else
							$expected_type = "\\".reset($prop_inf["#"]);
					}

					if ($expected_type && class_exists($expected_type))
					{
						$obj_id = is_array($v) ? $v["_id"] : null;
						$obj = $array_refs[$obj_id][$expected_type] ?: ($array_refs[$obj_id][$expected_type] = new $expected_type());

						// $prop_selector = $selector[$k];

						if ($obj instanceof QIModelArray)
						{
							$obj->setModelProperty($self->getModelType()->properties[$k], $self);
							$self->{"set{$k}"}($obj);

							foreach ($v as $vk => $vv)
							{
								if (is_array($vv))
								{
									$v_expected_type = null;
									if (($vc = $vv["_ty"]) && class_exists($vc))
										$v_expected_type =  $vc;
									else
									{
										if (!$type_inf)
											$type_inf = QModelQuery::GetTypesCache(get_class($self));
										$v_expected_type = "\\".reset($type_inf[$k]["[]"]["#"]);
									}

									if ($v_expected_type && class_exists($v_expected_type))
									{
										$v_obj_id = is_array($vv) ? $vv["_id"] : null;
										$v_obj = $array_refs[$v_obj_id][$v_expected_type] ?: ($array_refs[$v_obj_id][$v_expected_type] = new $v_expected_type());
										/*
										if ($v_obj instanceof QIModel)
										{
											// @todo
											// if ($prop_selector !== null)
											// $v_obj->extractFromArray($vv, $prop_selector, $obj, $include_nonmodel_properties, $array_refs);
										}
										*/
										$self->{"set{$k}_Item_"}($v_obj, $vk);
									}
									else 
										$self->{"set{$k}_Item_"}($vv, $vk);
								}
								else
									$self->{"set{$k}_Item_"}($vv, $vk);
							}
						}
						else if ($obj instanceof QIModel)
						{
							// @todo
							// $obj->extractFromArray($v, $prop_selector, $self, $include_nonmodel_properties, $array_refs);
							$self->{"set{$k}"}($obj);
						}
						else
							$self->{"set{$k}"}($obj);
					}
					else
						$self->{"set{$k}"}($v);
				}
				else
				{
					$self->{"set{$k}"}($v);
				}
			}
		}
		else if ($sp_list)
		{
			foreach ($sp_list as $k => $sp)
			{
				if (($k{0} === "_") ||
						(($select_all !== true) && ($select_all[$k] === null)))
				{
					if (static::$_SecurityPropertiesExclusive && $self)
						$self->$k = null;
					continue;
				}
				
				$property_perms = ($cache_p[$k] !== null) ? $cache_p[$k] : ($cache_p[$k] = static::SecurityGetPerms($asked_perms, $groups, $sp));
				if (!$property_perms)
				{
					// unset it
					if ($self)
						$self->$k = null;
					continue;
				}

				if ($self)
				{
					$v = $self->$k;
					if ($v instanceof QIModel)
					{
						if ($v instanceof QIModelArray)
						{
							foreach ($v as $i)
							{
								if ($i instanceof QIModel)
								{
									$i_class = get_class($i);
									$i_class::SecurityWorker($bag, $op_cache, $i, null, $action, $groups, $selector[$k], $max_selector[$k], $throw_error, $unset_if_not_selector, $unset_if_not_allowed);
								}
							}
						}
						else
						{
							$v_class = get_class($v);
							$v_class::SecurityWorker($bag, $op_cache, $v, null, $action, $groups, $selector[$k], $max_selector[$k], $throw_error, $unset_if_not_selector, $unset_if_not_allowed);
						}
					}
				}
			}
		}
		else if (static::$_SecurityPropertiesExclusive)
		{
			// this is bad, we don't have any list an no rights
			// unset everything
			foreach ($self as $k => $v)
			{
				if (($k{0} === "_") || (strtolower($k) === "id"))
					continue;
				
				// recurse first then unset, is there any point to recurse ?
				if ($v instanceof QIModel)
				{
					if ($v instanceof QIModelArray)
					{
						foreach ($v as $i)
						{
							if ($i instanceof QIModel)
							{
								$i_class = get_class($i);
								$i_class::SecurityWorker($bag, $op_cache, $i, null, $action, $groups, $selector[$k], $max_selector[$k], $throw_error, $unset_if_not_selector, $unset_if_not_allowed);
							}
						}
					}
					else
					{
						$v_class = get_class($v);
						$v_class::SecurityWorker($bag, $op_cache, $v, null, $action, $groups, $selector[$k], $max_selector[$k], $throw_error, $unset_if_not_selector, $unset_if_not_allowed);
					}
				}
				
				$this->$k = null;
			}
		}
		
		// if all was ok
		return true;
	}
	
	protected static function Security_wrap_22($self, $array, $action, $groups)
	{
		// we no longer need an expected type, the expected type IS static::class and this is generated, so there is no need to panic :)
		
		// the list of groups, null if there are none
		// $groups = [];

		$expected_type = get_called_class(); // expected type is to be generated
		
		// $groups = [] / $action (requested rights) / class name
		// (make sure we declare it in each trait!!!)
		// static::$_SecurityClass["group"] = [INT_PERMS, other_1 => true, other_2 => true, ...]
		
		// extra perms: $customAction
		// modifiers: class/static::, method - calling method, property, $this, (tag = [calling class, calling method])
		
		// for containment security, just check, don't populate with any data
		
		// @todo, prepare containment security, apply it at the end so it's more robust
		
		$asked_perms = null;
		if ($self)
		{
			$asked_perms = $action ?: ($self->_ts ?: QIModel::TransformRead);
		}
		else
		{
			$self = new $expected_type();
			
			if ($array !== null)
			{
				if (($obj_id = $array["_id"]) !== null)
					$self->setId($obj_id);
				if ($action === null)
				{
					if (($obj_ts = $array["_ts"]) !== null)
						$asked_perms = $self->_ts = (int)$obj_ts;
					else
						$asked_perms = QIModel::TransformRead;
				}
				else
					$asked_perms = $action;
			}
			else
				$asked_perms = $action ?: QIModel::TransformRead;			
		}
		
		// 1. check security at class level (cache it)
		// 2. if (calling method on this class) check rights for it also (should be done on the inital call, not now)
		// 3. check security at property level (cache it)
		// 4. check security per instance & instance vs property & instance vs method (@todo later)
		
		
		
		// then security per class instance (if applies)
		
	}
	
	public static function SecurityGetPerms($asked_perms, $groups, $perms)
	{
		$full_perms = false;
		$perms = array_intersect_key($perms, $groups);
		if (is_int($asked_perms))
		{
			$class_perms = 0;
			foreach ($perms as $perms_d)
			{
				// for int perms we use $perms_d[0]
				$class_perms |= $perms_d[0];
				if (($class_perms & $asked_perms) === $asked_perms)
				{
					// we have all that was asked for
					$full_perms = true;
					break;
				}
			}
		}
		else if (is_string($asked_perms))
		{
			$class_perms = null;
			foreach ($perms as $perms_d)
			{
				if (($class_perms = $perms_d[$asked_perms]))
				{
					$full_perms = true;
					break;
				}
			}
		}
		else if (is_array($asked_perms))
		{
			$bin_perms = null;
			$class_bin_perms = 0;
			$asked_perms_bp = null;
			if (($asked_perms_bp = $asked_perms[0]) && is_int($asked_perms_bp))
				$bin_perms = $asked_perms_bp;

			$temp_perms = $asked_perms;
			foreach ($perms as $perms_d)
			{
				if ($bin_perms && ($pd0 = $perms_d[0]))
					$class_bin_perms |= $pd0;
				$temp_perms = array_diff_key($temp_perms, $perms_d);
				if (empty($temp_perms) && (($bin_perms === null) || ($class_bin_perms === $bin_perms)))
				{
					$full_perms = true;
					break;
				}
			}
		}
		
		return $full_perms;
	}

	public static function GetLanguages_Dim()
	{
		return static::$DimsDef ? static::$DimsDef["lang"] : null;
	}
	
	public static function GetLanguage_Dim()
	{
		return static::$Dims ? static::$Dims["lang"] : static::$Language_Dim;
	}
	
	public static function SetLanguage_Dim($lang)
	{
		if (static::$Dims)
			static::$Dims["lang"] = $lang;
		static::$Language_Dim = $lang;
	}
	
	public static function SetDefaultLanguage_Dim($lang)
	{
		if (static::$DimsDef && static::$DimsDef["lang"] && (reset(static::$DimsDef["lang"]) !== $lang))
		{
			$new_lang = [$lang => $lang];
			foreach (static::$DimsDef["lang"] as $k => $v)
			{
				if ($k !== $lang)
					$new_lang[$k] = $v;
			}
			static::$DimsDef["lang"] = $new_lang;
		}
		static::$DefaultLanguage_Dim = $lang;
	}
	
	public static function GetDefaultLanguage_Dim()
	{
		return (static::$DimsDef && static::$DimsDef["lang"]) ? reset(static::$DimsDef["lang"]) : (static::$DefaultLanguage_Dim ?: static::$Language_Dim);
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetPropertyListingEntity($property)
	{
		$cc = get_called_class();
		if (($r = static::$_Cache["ple"][$cc][$property]))
			return $r;
		
		$prop_obj = static::GetTypeByName($cc)->properties[$property];
		$prop_obj_coll = $prop_obj->getCollectionType();
		$src_from_types = $prop_obj_coll ? $prop_obj_coll->getAllInstantiableReferenceTypes() : $prop_obj->getAllInstantiableReferenceTypes();
		
		foreach ($src_from_types as $ty)
		{
			$f_entity = $ty::GetListingEntity();
			if (is_string($f_entity))
				$f_entity = qParseEntity($f_entity);
			$form_selector = $form_selector ? qIntersectSelectors($form_selector, $f_entity) : $f_entity;
		}
		return (static::$_Cache["ple"][$cc][$property] = $form_selector);
	}
	
	public static function GetEntityForCreate()
	{
		return static::GetModelEntity();
	}
	
	public static function GetQueryForCreate()
	{
		return static::GetItemQuery();
	}
	
	public static function GetEntityForUpdate()
	{
		return static::GetModelEntity();
	}
	
	public static function GetQueryForUpdate()
	{
		return static::GetItemQuery();
	}
	
	public static function GetEntityForDelete()
	{
		return static::GetModelEntity();
	}
	
	public static function GetQueryForDelete()
	{
		return static::GetItemQuery();
	}
	
	public static function GetEntityForView()
	{
		return static::GetModelEntity();
	}
	
	public static function GetQueryForView()
	{
		return static::GetItemQuery();
	}

	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetPropertyModelEntity($property, $transform_state = null)
	{
		$cc = get_called_class();
		if (($r = static::$_Cache["pme"][$cc][$property]))
			return $r;
		
		$prop_reflection = static::GetTypeByName($cc)->properties[$property];
		$src_from_types = $prop_reflection->hasCollectionType() ? $prop_reflection->getCollectionType()->getAllInstantiableReferenceTypes() : $prop_reflection->getAllInstantiableReferenceTypes();
		foreach ($src_from_types as $ty)
		{
			$f_entity = $ty::GetModelEntity();
			if (is_string($f_entity))
				$f_entity = qParseEntity($f_entity, false, true, $ty);
			$form_selector = $form_selector ? qIntersectSelectors($form_selector, $f_entity) : $f_entity;
		}
		return (static::$_Cache["pme"][$cc][$property] = $form_selector);
	}
	
	public static function GetOptionsPool($property, $class_name = null)
	{
		$m_ty = static::GetTypeByName($class_name ?: get_called_class());
		if (!isset($m_ty->properties[$property]->storage["optionsPool"]))
			return null;
		$optionsPool = $m_ty->properties[$property]->storage["optionsPool"];
		return is_array($optionsPool) ? $optionsPool : preg_split("/\\s+/us", trim($optionsPool), 2);
	}

	/**
	 * General getter
	 * 
	 * @param string $property
	 * @return mixed
	 */
	public function __get($property)
	{
		//echo "<div style='color: red;'>GET :: {$property}</div>";
		return $this->get($property);
	}

	/**
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function __isset($property)
	{
		//echo "<div style='color: green;'>ISSET :: {$property}</div>";
		return isset($this->$property);
	}

	/**
	 * @param string $property
	 */
	public function __unset($property)
	{
		//echo "<div style='color: orange;'>UNSET :: {$property}</div>";
		unset($this->$property);
	}

	/**
	 * General setter
	 * 
	 * @param string $property
	 * @param mixed $value
	 */
	public function __set($property, $value)
	{	
		//echo "<div style='color: blue;'>SET :: {$property} = ".(is_scalar($value) ? $value : gettype($value))."</div>";
		if ($property{0} === '_')
			$this->$property = $value;
		else
			$this->{"set{$property}"}($value);
		//$this->{"set{$property}"}($value);
	}
	/**
	 * @param string $name
	 * @param array $arguments
	 * 
	 * @return mixed
	 */
	public function __call($name, $arguments)
	{
		$fc = substr($name, 0, 3);
		if ($fc === "get")
		{
			$prop = substr($name, 3);
			return $this->$prop;
		}
		else if ($fc === "set")
		{
			$prop = substr($name, 3);
			$this->$prop = $arguments[0];
		}
		else
			throw new Exception("Unknown method: ".get_class($this)."::{$name}");
	}
	/**
	 * 
	 * @param \QIModel $data
	 * @param type $ignore_nulls
	 * @param array $refs
	 * @param type $metadata
	 * @param type $array_in_items
	 * @return null
	 */
	public static function QOutputJson($data, $ignore_nulls = true, &$refs = null, $metadata = true, $array_in_items = true)
	{
		if ($refs === null)
			$refs = array();
		
		if ($data === null)
			echo "null";
		else if (is_string($data))
			//echo ($esc = json_encode($data, JSON_UNESCAPED_SLASHES)) ? $esc : "\"\"";
			//echo json_encode($data, JSON_UNESCAPED_SLASHES);
			echo ((($enc = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) && ($enc !== null)) ? $enc : 
				json_encode(iconv('UTF-8', 'UTF-8//IGNORE', $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		else if (is_bool($data))
			echo $data ? "true" : "false";
		else if (is_integer($data) || is_float($data))
			echo $data;
		else if ($data instanceof \QIModelArray)
			$data::QOutputJson($data, $ignore_nulls, $refs, $metadata, $array_in_items);
		else if ($data instanceof \QIModel)
		{
			// handle model
			$f_id = $data->getFullId();
			$m_type = $data->_ty ? $data->_ty->class : get_class($data);

			// type
			if ($metadata)
			{
				echo "{\"_ty\":";
				echo json_encode($m_type, JSON_UNESCAPED_SLASHES);

				if ($data->_wst)
				{
					echo ",\"_wst\":";
					echo json_encode($data->_wst, JSON_UNESCAPED_SLASHES);
				}
			}
			else
				echo "{";

			$id = $data->getId();
			if ($metadata)
			{
				if ($id === null)
				{
					echo ",\"_tmpid\":";
					echo $data->getTemporaryId();
				}
				else
				{
					echo ",\"_id\":";
					echo json_encode($id, JSON_UNESCAPED_SLASHES);
				}
			}
			$ts = $data->getTransformState();
			if ($metadata && ($ts !== null))
			{
				echo ",\"_ts\":";
				echo($ts === null ? "null" : $ts);
			}

			if ($refs[$f_id])
			{
				if ($metadata)
					echo ",\"_isref\": true}\n";
				else
					echo "}";
				return;
			}
			else if ($data->no_export && ((!is_array($ignore_nulls)) || (!$ignore_nulls[1])))
			{
				if ($metadata)
					echo ",\"_noexp\": true}\n";
				else
					echo "}";
				return;
			}

			$refs[$f_id] = true;

			$comma = $metadata;
			foreach ($data as $k => $v)
			{
				if (($k{0} === "_") || ($ignore_nulls && ($v === null) && !$data->wasSet($k)))
					continue;
				if ($comma)
					echo ",";
				echo json_encode((string)$k, JSON_UNESCAPED_SLASHES);
				echo ":";
				($v && ($v instanceof \QIModel)) ? 
					$v::qOutputJson($v, $ignore_nulls, $refs, $metadata, $array_in_items) : 
					static::qOutputJson($v, $ignore_nulls, $refs, $metadata, $array_in_items);

				$comma = true;
			}
			echo "}\n";
		}
		else if (is_array($data) || ($is_obj = is_object($data)))
		{
			$is_assoc = $is_obj || (array_values($data) !== $data);
			echo $is_assoc ? "{" : "[";
			$comma = false;
			foreach ($data as $k => $v)
			{
				if ($comma)
					echo ",";
				if ($is_assoc)
				{
					echo json_encode((string)$k, JSON_UNESCAPED_SLASHES);
					echo ":";
				}
				echo static::QOutputJson($v, $ignore_nulls, $refs, $metadata, $array_in_items);
				$comma = true;
			}
			echo $is_assoc ? "}" : "]";
		}
		else
			throw new Exception("Unknow data type");
	}
	
	/**
	 * 
	 * @param \QIModel $data
	 * @param type $ignore_nulls
	 * @param array $refs
	 * @param type $metadata
	 * @param type $array_in_items
	 * @return null
	 */
	public static function QToJSon($data, $ignore_nulls = false, &$refs = null, &$refs_no_class = null, $metadata = true, $array_in_items = true)
	{
		if ($refs === null)
			$refs = [];
		if ($refs_no_class === null)
			$refs_no_class = [];
		if ($data === null)
			echo "null";
		else if (is_string($data))
			echo json_encode($data, JSON_UNESCAPED_SLASHES);
		else if (is_bool($data))
			echo $data ? "true" : "false";
		else if (is_integer($data) || is_float($data))
			echo $data;
		else if ($data instanceof \QIModel)
			echo $data->toJSON(null, false, false, true, $ignore_nulls, $refs, $refs_no_class);
		else if (is_array($data) || ($is_obj = is_object($data)))
		{
			$is_assoc = $is_obj || (array_values($data) !== $data);
			echo $is_assoc ? "{" : "[";
			$comma = false;
			foreach ($data as $k => $v)
			{
				if ($comma)
					echo ",";
				if ($is_assoc)
				{
					echo json_encode((string)$k, JSON_UNESCAPED_SLASHES);
					echo ":";
				}
				echo static::QToJSon($v, $ignore_nulls, $refs, $refs_no_class, $metadata, $array_in_items);
				$comma = true;
			}
			echo $is_assoc ? "}" : "]";
		}
		else
			throw new Exception("Unknow data type");
	}

	//======================================================== CSV Export ========================================================

	protected static function ToCsvWalkSelector($classes, $selector = null, $cols_prefix = "", &$ret_keys = null, &$full_selector = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		if (!(($selector !== null) && is_array($selector)))
			return;
		
		// we put the data here
		if ($ret_keys === null)
			$ret_keys = [];
		if ($full_selector === null)
			$full_selector = [];
		
		$keys = array_keys($selector);
		//$prop = $keys[0];
		$all_keys = in_array("*", $keys);
		if ($all_keys)
		{
			$replacement = [];
			foreach ($classes as $class)
			{
				$all_keys = array_keys(QModelQuery::GetTypesCache($class));
				foreach ($all_keys as $k)
				{
					if ($k{0} !== "#")
						$replacement[] = $k;
				}
			}
			$pos = array_search("*", $keys);
			array_splice($keys, $pos, 1, $replacement);
		}
		
		foreach ($keys as $key)
		{
			$full_selector[$key] = [];
			if (($sub_sel = $selector[$key]))
			{
				// $is_collection = false;
				$prop_types = QQueryAnalyzer::GetPropertyTypes($classes, $key); //, $is_collection);
				self::ToCsvWalkSelector($prop_types, $sub_sel, $cols_prefix.$key.".", $ret_keys, $full_selector[$key]);
			}
			else
				$ret_keys[$cols_prefix.$key] = true;
		}
		
		return [$ret_keys, $full_selector];
	}
	/**
	 * Converts from array to QModelArray
	 * 
	 * @param array $element
	 * @return \QModelArray
	 * @throws Exception
	 */
	protected static function ArrayToModelArray($element)
	{
		if (is_array($element))
		{
			$array = new QModelArray();
			foreach ($element as $e)
				$array[] = $e;
			return $array;
		}
		else if ($element === null)
			return new QModelArray();
		else 
			throw new Exception("Not an array");
	}

	public static function ToCsvString($element, $selector = null)
	{
		if ($element === null)
			return null;
		else if (is_array($element))
			$element = static::ArrayToModelArray($element);
		
		ob_start();
		$element->toCSV($selector);
		return ob_get_clean();
	}

	public static function ToCsvFile($path, $element, $selector = null)
	{
		if ($element === null)
			return null;
		else if (is_array($element))
			$element = static::ArrayToModelArray($element);

		$stream = fopen($path, "wt");
		$element->toCSV($selector, $stream);
		fclose($stream);
	}

	public static function ToCsvResponse($element, $selector = null, $filename = "export.csv")
	{
		if ($element === null)
			return null;
		else if (is_array($element))
			$element = static::ArrayToModelArray($element);
		
		$now = gmdate("D, d M Y H:i:s");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		// force download
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");

		// disposition / encoding on response body
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");
		
		$stream = fopen('php://output', 'w+');
		$element->toCSV($selector, $stream);
		// fclose($stream);
	}

	public static function ImportFromCsv($file, $importType, $from, $selector)
	{
		
	}
	
	public static function GetDefaultAppPropertyForTypeValues($type = null)
	{
		return static::GetDefaultAppPropertiesForTypeValues($type, true);
	}
	
	public static function GetDefaultAppPropertiesForTypeValues($type = null, bool $only_first = false)
	{
		$type_name = $type ?: static::class;
		if (($app_property = static::$_Cache_DefaultAppPropertiesForTypeValues[$type]) !== null)
			return $only_first ? reset($app_property) : $app_property;
		
		$m_type = static::GetTypeByName($type);
		if (($type_property = $m_type->storage["appProperty"]) && ($type_property !== 'none') && ($type_property !== 'false'))
		{
			// explicit type property
			static::$_Cache_DefaultAppPropertiesForTypeValues[$type] = [$type_property => $type_property];
			return $only_first ? $type_property : [$type_property => $type_property];
		}
		
		$mty_inf = QModelQuery::GetTypesCache(QApp::GetDataClass());
		
		$props_list = [];
		
		foreach ($mty_inf as $prop => $inf)
		{
			if ($prop{0} === "#")
				continue;
			if ($inf["[]"] && $inf["[]"]["#"][$type_name])
				$props_list[$prop] = $prop;
		}
		
		static::$_Cache_DefaultAppPropertiesForTypeValues[$type] = $props_list;
		return $only_first ? reset($props_list) : $props_list;
	}
	
	public function getValsFor($property, $offset = 0, $limit = 20, $filter = null)
	{
		return static::GetValuesFor($property, get_class($this), $this, $offset, $limit, $filter);
	}
	
	public static function GetValuesForType($type = null, $offset = 0, $limit = 20, $filter = null, &$ret = null)
	{
		if ($offset === null)
			$offset = 0;
		if ($limit === null)
			$limit = 20;
		
		$type = $type ?: static::class;
		$app_property = static::GetDefaultAppPropertyForTypeValues($type);
		if (!$app_property)
			return false; // error
		$mty_inf = QModelQuery::GetTypesCache($type);
		$caption_props = $mty_inf["#%misc"]["model"]["captionProperties"] ?: ["Id" => true];
		if (is_int(key($caption_props)))
		{
			$cp = [];
			foreach ($caption_props as $p)
				$cp[$p] = [];
			$caption_props = $cp;
		}
		if (is_array($caption_props))
			$caption_props = qImplodeEntity($caption_props);
		
		$filter = $filter ?: [];
		if ($filter["LIMIT"] === null)
			$filter["LIMIT"] = [$offset, $limit];
			
		$ret_vals = \QApi::Query($app_property, $caption_props, $filter);
		if ($ret !== null)
			array_splice($ret, count($ret), 0, $ret_vals);

		return $ret_vals;
	}
	
	public static function GetValuesFor($property, $type = null, $instance = null, $offset = 0, $limit = 20, $filter = null)
	{
		$type = $type ?: static::class;
		$mty_inf = QModelQuery::GetTypesCache($type);
		if (!$mty_inf)
			return false; // error
		$m_prop = $mty_inf[$property];
		if (!$m_prop)
			return false; // error
		
		// we need to have a standard
		// 1. we return a list (null => nothing, true => anything)
		// 2. each element is either a scalar or 
		//		an obj that includes a descriptor (type)
		//      obj = {t: $the_type, v: $value}
		//      obj = {t: $range, v: [$start, $end]}

		// we can disable or hide invalid values
		// we also need to know what values are invalid in this context ... but can be valid
		
		$ret = [];
		// $,#,[] - collection repeats
		if (($scalar_ty = $m_prop['$']))
		{
			foreach ($scalar_ty as $sc_ty)
				static::GetValuesForScalar($sc_ty, $ret);
		}
		if (($reference_ty = $m_prop['#']))
		{
			foreach ($reference_ty as $ref_ty)
				static::GetValuesForType($ref_ty, $offset, $limit, $filter, $ret);
		}
		if (($collection_ty = $m_prop['[]']))
		{
			if (($scalar_ty = $collection_ty['$']))
			{
				foreach ($scalar_ty as $sc_ty)
					static::GetValuesForScalar($sc_ty, $ret);
			}
			if (($reference_ty = $collection_ty['#']))
			{
				foreach ($reference_ty as $ref_ty)
					static::GetValuesForType($ref_ty, $offset, $limit, $filter, $ret);
			}
		}
		
		return $ret;
	}
	
	public static function GetValuesForScalar($scalar_ty, &$ret = null)
	{
		if ($ret === null)
			$ret = [];
		switch ($scalar_ty)
		{
			case "int":
			case "integer":
			{
				// i - integer
				$ret[] = ["i"];
				break;
			}
			case "bool":
			case "boolean":
			{
				// b - boolean
				$ret[] = ["b"];
				break;
			}
			case "double":
			case "float":
			{
				// d - double
				$ret[] = ["d"];
				break;
			}
			case "string":
			{
				// s - string
				$ret[] = ["s"];
				break;
			}
			case "null":
			{
				// n - null
				$ret[] = ["n"];
				break;
			}
		}
		return $ret ?: null;
	}
	
	public static function GetTypesCache($class_name = null)
	{
		return \QModelQuery::GetTypesCache($class_name ?: static::class);
	}
	
	public function getIdFromMergeBy_2($set_it = true, $mergeBy = null, string $main_app_property = null)
	{
		if ($main_app_property)
			$main_app_properties = [$main_app_property => $main_app_property];
		if ($mergeBy === null)
		{
			if ($main_app_property)
			{
				$type_inf = \QModel::GetTypeByName(\QApp::GetDataClass());
				$prop_mby = $type_inf->properties[$main_app_property];
				if (!$prop_mby)
					throw new \Exception('Missing definition for: '.\QApp::GetDataClass()."::".$main_app_property);
				$mergeBy = $prop_mby->storage["mergeBy"];
			}
			
			if (!$mergeBy)
			{
				$type_inf = \QModel::GetTypesCache(get_class($this));
				$mergeBy = $type_inf["#%misc"]["mergeBy"];
			}
		}
		if ($mergeBy === null)
			return false;
		$mergeBy_parts = explode(",", $mergeBy);
		if (!$main_app_properties)
			$main_app_properties = static::GetDefaultAppPropertiesForTypeValues(get_class($this));
		if (!$main_app_properties)
			return false;
		
		foreach ($main_app_properties as $main_app_property)
		{
			//$sql_q = $main_app_property.".{Id,Name,TourOperator,Address.{City.Name,Country.Name} WHERE ";
			$sql_q = $main_app_property.".{{$mergeBy} WHERE ";
			$binds = [];
			$prepend_and = false;
			foreach ($mergeBy_parts as $_mby)
			{
				$mby = trim($_mby);
				if (empty($mby))
					continue;

				$sql_q .= ($prepend_and ? " AND " : "").trim($mby)."<=>?";
				$b_parts = explode(".", $mby);
				$obj = $this;
				foreach ($b_parts as $bp)
					$obj = $obj->{$bp};

				$binds[] = $obj;
				$prepend_and = true;
			}

			$sql_q .= " LIMIT 1}";
			$res = QQuery($sql_q, $binds);
			$db_id = null;

			if (isset($res->{$main_app_property}))
			{
				$db_id = reset($res->{$main_app_property})->getId();
				if ($set_it && ($db_id !== null))
				{
					$this->setId($db_id);
					$this->_found_on_merge = true;
				}
			}
			if ($db_id !== null)
				return $db_id;
		}
		
		return null;
	}
	
	public function getPropertyIdFromMergeBy($id, $property, $mergeBy, $set_it = true)
	{
		throw new \Exception('deprecated');
		
		if (empty($mergeBy))
			return null;

		$cc = get_class($this);
		$clone = new $cc();
		$clone->setId($id);

		$clone->populate("Id,{$property}" . (($mergeBy !== true) ? ".{{$mergeBy}}" : "") . " WHERE Id=?", $id);
		
		$prop = $clone->$property;
		if ($prop === null)
			return null;
		
		if ($mergeBy === true)
		{
			if ($this->$property !== null)
			{
				if (qis_array($this->$property))
					throw new \Exception('Empty merge by is only allowed for references, not collections: '.get_class($this).".".$property);
				$this->$property->setId($prop->getId());
				$this->$property->_found_on_merge = true;
			}
			else if ($this->wasSet($property))
			{
				return;
			}
			else
			{
				$this->{"set{$property}"}($prop);
			}
				
		}
		
		$mergeBy_parts = explode(",", $mergeBy);
		$elements = qis_array($prop) ? $prop : [$prop];
		$this_elements = qis_array($this->$property) ? $this->$property : [$this->$property];

		foreach ($elements as $e)
		{
			foreach ($this_elements as $th_e)
			{
				$matches = true;
				foreach ($mergeBy_parts as $mby)
				{
					$path_parts = explode('.', $mby);
					$comp_e = $e;
					$this_e = $th_e;
					foreach ($path_parts as $_pp)
					{
						if (($comp_e === null) || ($this_e === null))
							break;
						$pp = trim($_pp);
						$comp_e = $comp_e->$pp;
						$this_e = $this_e->$pp;
					}
					
					if (
							(($comp_e === null) && ($this_e !== null)) ||
							(($comp_e !== null) && ($this_e === null)) ||
							((string)$comp_e !== (string)$this_e)
						)
					{
						$matches = false;
						break;
					}
				}
				
				if ($matches)
				{
					if ($set_it)
					{
						$th_e->setId($e->getId());
						$th_e->_found_on_merge = true;
					}
					break;
				}
			}
		}
		return null;
	}
	
	public function has(array $properties_stack)
	{
		// @TODO !!!
		throw new \Exception('@TODO');
		
		if (!$properties_stack)
			return false;
		
		$obj = $this;
		
		$has = false;
		
		foreach ($properties_stack as $k => $v)
		{
			$k_is_int = is_int($k);
			if ($k_is_int)
			{
				// no condition
				if ((!$obj->wasSet($v)) && $obj->getId())
					// we need to populate all the way, it's easier
					$obj->populate($v." WHERE Id=?", $obj->getId());
				if ($obj->$v === null)
					return false;
				else
					$has = true;
			}
			else
			{
				// we have some conditions
				if (!$obj->wasSet($k))
				{
					$pop_query = $k." WHERE Id=?";
					$pop_params = [$obj->getId()];
					if (!is_array($v))
						$v = [$k => $v];
					$obj->populate($pop_query, $pop_params);
				}
			}
		}
		
		return $has;
	}
	
	public static function MergeWithoutProvision(string $from, $data, bool $trigger_provision = false, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		if ((!$data) || (count($data) === 0))
			return $data;
		
		$app = \QApp::NewData();
		$refl = \QModel::GetTypeByName(\QApp::GetDataClass());
		if (!$refl)
			return false;
		$p_refl = $refl->properties[$from];
		if (!$p_refl)
			return false;
		
		$class = get_called_class();
		
		$first_obj = qis_array($data) ? reset($data) : $data;
		$selector = $p_refl->name.".{".$first_obj::GetModelEntity()."}";
		
		$app->{"set{$p_refl->name}"}($data);
		
		return $app->merge($selector, null, $trigger_provision, $trigger_events, $trigger_save, $trigger_import); // $selector = null, $data = null, bool $trigger_events = true
	}
	
	public static function TransactionFlagBegin($flags)
	{
		$flag = new stdClass();
		$flag->flags = $flags;
		if (\QModel::$TransactionFlag)
			$flag->parent = \QModel::$TransactionFlag;
		return \QModel::$TransactionFlag = $flag;
	}
	
	public static function TransactionFlagEnd()
	{
		if (!($flag = \QModel::$TransactionFlag))
			return false;
		
		\QModel::$TransactionFlag = $flag->parent ?: null;
		return $flag;
	}
	
	public static function TransactionFlagGet(string $flag = null)
	{
		return (\QModel::$TransactionFlag && \QModel::$TransactionFlag->flags) ? ($flag ? \QModel::$TransactionFlag->flags[$flag] : \QModel::$TransactionFlag->flags) : null;
	}
	
	public static function TransactionFlagGetInstance()
	{
		return \QModel::$TransactionFlag;
	}
	
	######## QMODEL PATCH #############
	
	public function isNew(bool $test_with_merge_by = false, string $app_prop = null)
	{
		if (!$test_with_merge_by)
			return ($this->_is_new || ($this->_is_new = ($this->_tsx === \QIModel::TransformCreate)));
		else if ($this->_is_new_2 !== null)
			return $this->_is_new_2;
		else if ($this->getId())
			return ($this->_is_new_2 = false);
		else
		{
			// we presume that is starting point
			if (!$app_prop)
				throw new \Exception('Missing App Model Property');
			
			$this->getIdFromMergeBy_2(true, null, $app_prop);
			return ($this->_is_new_2 = ($this->getId() ? false : true));
		}
	}

	public function toRM()
	{
		return ($this->_to_rm || ($this->_to_rm = ($this->getTransformState() === \QIModel::TransformDelete)));
	}

	public function updateModel($model, $selector = null, &$_bag = [])
	{
		if ($selector === null)
			$selector = static::GetModelEntity();

		if (is_string($selector))
			$selector = qParseEntity($selector);

		if (!is_array($selector))
			return;

		$data = reset($selector);
		$prop = key($selector);
		$all_keys = ($prop === "*");

		$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;

		if ($_bag[$model->getTemporaryId()])
			return;
		$_bag[$model->getTemporaryId()] = $this;

		$modelType = $this->getModelType();

		if ($all_keys)
		{
			// skip #%tables
			next($type_inf);
			// skip #%table
			next($type_inf);
			// skip #%id
			next($type_inf);
			// skip #%misc
			next($type_inf);

			$prop = key($type_inf);
			// we no longer loop
			$data = $selector["*"];
		}

		try
		{
			while ($prop)
			{
				//echo "<div style='color: red;'>".$prop."</div>";
				// setup the property only if is accepted by the model
				if ($modelType->properties[$prop])
				{
					$val = $model->{$prop};
					if ($val !== null)
					{
						if ($val instanceof QIModel)
						{
							$vcls = get_class($val);
							// the collection - it can be a collection of models or a scalar collection
							if ($val instanceof QIModelArray)
							{
								if (!$this->{$prop})
									$this->{"set{$prop}"}(new $vcls());

								$p_meth = "set{$prop}_Item_";
								foreach ($val as $k => $item)
								{
									if (!$item)
										continue;

									if ($item instanceof QIModel)
									{
										$_itmcls = get_class($item);
										$toSetupItm = $this->{$prop}[$k] ?: ($_bag[$item->getTemporaryId()] ? $_bag[$item->getTemporaryId()] : new $_itmcls());
										$toSetupItm->updateModel($item, $selector[$prop], $_bag);
									}
									else
										$toSetupItm = $item;
									$this->$p_meth($toSetupItm, $k);
								}
							}
							else
							{
								$itm = $this->{$prop} ?: ($_bag[$val->getTemporaryId()] ? $_bag[$val->getTemporaryId()] : new $vcls());
								$itm->updateModel($val, $selector[$prop], $_bag);
								$this->{"set{$prop}"}($itm);
							}
						}
						else
							$this->{"set{$prop}"}($val);
					}
				}

				if ($all_keys)
				{
					next($type_inf);
					$prop = key($type_inf);
					$data = $selector[$prop] ?: [];
				}
				else
				{
					// here it must be an array
					$data = next($selector);
					$prop = key($selector);
				}
			}
		}
		catch (Exception $ex)
		{
			throw $ex;
		}
	}

	public function unsetProp($prop)
	{
		unset($this->{$prop});
		if ($this->_wst && $this->_wst[$prop])
			unset($this->_wst[$prop]);
		
	}

	public function restoreRemoteIds($force = false, &$_bag = [])
	{
		if ($_bag[$this->getTemporaryId()])
			return;

		$_bag[$this->getTemporaryId()] = $this;

		if ($this->__rid || $force)
		{
			if ($this->_synchronizable)
			{
				//unset the owner
				$this->unsetProp("Owner");
				// set the gid
				$this->setGid($this->getId());
			}

			// set the id as remote id
			$this->setId($this->__rid);
		}

		foreach ($this as $v)
		{
			if (!($v instanceof \QIModel))
				continue;

			if ($v instanceof \QModelArray)
			{
				foreach ($v as $_vv)
				{
					if ($_vv instanceof \QModel)
						$_vv->restoreRemoteIds($force, $_bag);
				}
			}
			else
				$v->restoreRemoteIds($force, $_bag);
		}
	}

	public function doOnSelector($selector, $callback, $params = null, &$_bag = [])
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector);

		if (!is_array($selector) || $_bag[$this->getTemporaryId()])
			return;

		$_bag[$this->getTemporaryId()] = $this;

		$data = reset($selector);
		$prop = key($selector);
		$all_keys = ($prop === "*");

		$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;

		$modelType = $this->getModelType();
		
		call_user_func_array($callback, [$this, $params]);

		if ($all_keys)
		{
			// skip #%tables
			next($type_inf);
			// skip #%table
			next($type_inf);
			// skip #%id
			next($type_inf);
			// skip #%misc
			next($type_inf);

			$prop = key($type_inf);
			// we no longer loop
			$data = $selector["*"];
		}

		try
		{
			while ($prop)
			{
				//echo "<div style='color: red;'>".$prop."</div>";
				// setup the property only if is accepted by the model
				if ($modelType->properties[$prop])
				{
					$val = $this->{$prop};
					if (($val !== null) && ($val instanceof QIModel))
					{
						// the collection - it can be a collection of models or a scalar collection
						if ($val instanceof QIModelArray)
						{
							foreach ($val as $item)
							{
								if ($item instanceof QIModel)
									$item->doOnSelector($selector[$prop], $callback, $params, $_bag);
							}
						}
						else
							$val->doOnSelector($selector[$prop], $callback, $params, $_bag);
					}
				}

				if ($all_keys)
				{
					next($type_inf);
					$prop = key($type_inf);
					$data = $selector[$prop] ?: [];
				}
				else
				{
					// here it must be an array
					$data = next($selector);
					$prop = key($selector);
				}
			}
		}
		catch (Exception $ex)
		{
			throw $ex;
		}
	}

	public function updateSyncProps($syncProps, $selector = null)
	{
		$this->doOnSelector($selector, function ($obj, $syncProps) {
			if ($obj->_synchronizable)
				$obj->extractFromArray($syncProps);
		}, $syncProps);
	}

	public function clearSyncProps($syncPropsSelector = null, $selector = null, &$_bag = [])
	{
		if (!$syncPropsSelector)
			$syncPropsSelector = $this::GetSyncProps();

		if (is_scalar($syncPropsSelector))
			$syncPropsSelector = qParseEntity($syncPropsSelector);
		else if (!is_array($syncPropsSelector))
			throw new \Exception("Sync props selector not provided!");

		// use the callback
		$this->doOnSelector($selector, function ($obj, $syncPropsSelector) {
			// unset properties here
			foreach ($syncPropsSelector as $sp => $sv)
			{
				$obj->unsetProp($sp);
			}
		}, $syncPropsSelector);
	}

	public function getEnumAcceptedValues($property)
	{
		$prop = $this->getModelType()->properties[$property];
		if (!$prop)
			return null;
		return $prop->storage["enum_values"] ? json_decode($prop->storage["enum_values"], true) : null;
	}
	
	public function getEnumCaptions($property)
	{
		$prop = $this->getModelType()->properties[$property];
		if (!$prop)
			return null;
		return $prop->storage["enum_captions"] ? json_decode($prop->storage["enum_captions"], true) : null;
	}
	
	public function getEnumStyles($property)
	{
		$prop = $this->getModelType()->properties[$property];
		if (!$prop)
			return null;
		return $prop->storage["enum_styles"] ? json_decode($prop->storage["enum_styles"], true) : null;
	}

	public function getEnumStyle($property)
	{
		return (($styles = $this->getEnumStyles($property)) && ($style = $styles[$this->{$property}])) ? $style : null;
	}

	public function getEnumCaption($property)
	{
		return (($captions = $this->getEnumCaptions($property)) && ($caption = $captions[$this->{$property}])) ? $caption : null;
	}

	/**
	 * Outputs the content of the object into a JSON string. 
	 * The function avoids recursion
	 * Also 2 objects of the same class and same id (getId() is used), will not be included twice.
	 * 
	 * @return string
	 */
	public function toJSON($selector = null, $include_nonmodel_properties = false, $with_type = true, $with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector);

		if (!(($selector !== null) && is_array($selector)))
			return;

		$class = get_class($this);

		$str = "{";
		$pp_comma = "";
		if ($with_type)
		{
			$str .= "\"_ty\":".json_encode($class, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$pp_comma = ",";
		}

		$id = $this->getId();
		$was_included = false;
		if ($id !== null)
		{
			if ($with_hidden_ids)
			{
				$str .= "{$pp_comma}\"_id\":".json_encode($id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				$pp_comma = ",";
			}

			if ($refs === null)
				$refs = [];
			if (isset($refs[$id][$class]))
				$was_included = true;
			else
				$refs[$id][$class] = $this;
		}
		else if (!is_array($selector))
		{
			if ($refs_no_class === null)
				$refs_no_class = [];
			if (($refs_class = $refs_no_class[$class]) && in_array($this, $refs_class, true))
				$was_included = true;
			else
				$refs_no_class[$class][] = $this;
		}

		$data = reset($selector);
		$prop = key($selector);
		$all_keys = ($prop === "*");
		$type_inf = $all_keys ? QModelQuery::GetTypesCache(get_class($this)) : null;
		
		if (!$was_included)
		{
			// handle all properties in the selector
			if ($all_keys)
			{
				if ($include_nonmodel_properties || !$type_inf)
				{
					$exposedProps = [];
					$refCls = new ReflectionClass($this);
					$props = $refCls->getProperties();
					if ($props && (count($props) > 0))
					{
						foreach ($props as $prop)
						{
							if ($prop->isPublic())
								$exposedProps[$prop->name] = $prop->name;
						}
					}

					reset($exposedProps);
					$prop = key($exposedProps);
				}
				else
				{
					// skip #%tables
					next($type_inf);
					// skip #%table
					next($type_inf);
					// skip #%id
					next($type_inf);
					// skip #%misc
					next($type_inf);

					$prop = key($type_inf);
				}
				// we no longer loop
				$data = null;
			}

			while ($prop)
			{
				$val = $this->{$prop};
				$ty = gettype($val);
				
				/* "boolean" "integer" "double" "string" "array" "object" "resource" "NULL" "unknown type"*/
				switch ($ty)
				{
					case "string":
					{
						$str .= "{$pp_comma}\"{$prop}\":".json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
						$pp_comma = ",";
						break;
					}
					case "NULL":
					{
						if (!$ignore_nulls)
						{
							$str .= "{$pp_comma}\"{$prop}\":null";
							$pp_comma = ",";
						}
						break;
					}
					case "integer":
					case "double":
					{
						$str .= "{$pp_comma}\"{$prop}\":".$val;
						$pp_comma = ",";
						break;
					}
					case "boolean":
					{
						$str .= "{$pp_comma}\"{$prop}\":".($val ? "true" : "false");
						$pp_comma = ",";
						break;
					}
					case "array":
					case "object":
					{
						if ($val instanceof QIModel)
						{
							$str .= "{$pp_comma}\"{$prop}\":";
							$str .= $val->toJSON($data, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
						}
						else if ($include_nonmodel_properties)
						{
							$str .= "{$pp_comma}\"{$prop}\":";
							$str .= self::NMtoJSON($val, $data, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
						}
						else
							$str .= "{$pp_comma}\"{$prop}\":".json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
						$pp_comma = ",";
						break;
					}
					default:
						break;
				}

				if ($all_keys)
				{
					if ($include_nonmodel_properties || !$type_inf)
					{
						next($exposedProps);
						$prop = key($exposedProps);
					}
					else
					{
						next($type_inf);
						$prop = key($type_inf);
					}
					$data = $selector[$prop] ?: [];
				}
				else
				{
					$data = next($selector);
					$prop = key($selector);
				}
			}
		}
		$str .= "}";
		
		return $str;
	}
	
	/**
	 * Export to json non model properties if needed
	 * 
	 * @param array|obj $data
	 * @param type $selector
	 * @param type $include_nonmodel_properties
	 * @param type $with_type
	 * @param type $with_hidden_ids
	 * @param type $ignore_nulls
	 * @param type $refs
	 * @param type $refs_no_class
	 */
	private static function NMtoJSON($data, $selector = null, $include_nonmodel_properties = false, $with_type = true, 
		$with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
	{
		if (!$data || (count($data) === 0))
			return "{}";

		if (is_string($selector))
			$selector = qParseEntity($selector);

		if (!(($selector !== null) && is_array($selector)))
			return "{}";

		$all_props = false;
		if ($selector && is_array($selector))
		{
			reset($selector);
			$all_props = (key($selector) == "*");
		}

		$str = "{";
		$pp_comma = "";
		foreach ($data as $prop => $val)
		{
			if (!is_null($selector) && !$selector[$prop] && !$all_props)
				continue;
			
			$ty = gettype($val);
			
			switch ($ty)
			{
				case "string":
				{
					$str .= "{$pp_comma}\"{$prop}\":".json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
					$pp_comma = ",";
					break;
				}
				case "NULL":
				{
					if (!$ignore_nulls)
					{
						$str .= "{$pp_comma}\"{$prop}\":null";
						$pp_comma = ",";
					}
					break;
				}
				case "integer":
				case "double":
				{
					$str .= "{$pp_comma}\"{$prop}\":".$val;
					$pp_comma = ",";
					break;
				}
				case "boolean":
				{
					$str .= "{$pp_comma}\"{$prop}\":".($val ? "true" : "false");
					$pp_comma = ",";
					break;
				}
				case "array":
				case "object":
				{
					if ($val instanceof QIModel)
					{
						$str .= "{$pp_comma}\"{$prop}\":";
						$str .= $val->toJSON($selector[$prop] ? $selector[$prop] : [], $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
					}
					else
					{
						$str .= "{$pp_comma}\"{$prop}\":";
						$str .= self::NMtoJSON($val, $selector, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
					}
					$pp_comma = ",";
				}
				default:
					break;
			}
		}
		$str .= "}";
		return $str;
	}

	/**
	 * Run a transform on a MODEL
	 * The action is one of the QIModel::ModelState*, custom actions may be used as strings
	 * Specific containers may be specified, if not the ones provided by QIModel::getContainers will be used
	 * If $recurse is set to true transform will be called foreach property that implements QIModel 
	 * The default is to recurse
	 * If $as_simulation is set to true the code should not actually do anything, it should just test and see if
	 * the actions can be executed ok. In the future we should provide output so that the user will be able to 
	 * see what can and can't be done and why. (TO DO)
	 * The operation should throw an error if it fails.
	 * In case of failure we should ROLLBACK (TO DO)
	 *
	 * @param mixed $parameters
	 * @param QIStorageContainer|QIStorageContainer[] $containers
	 * @param boolean $recurse
	 * @param QBacktrace $backtrace
	 * @param boolean $as_simulation
	 */
	public function transform($parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false, &$issues = null, &$root_issues = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		$is_root_call = ((!$backtrace) || ($backtrace === $backtrace->root));
		if ($is_root_call)
			$this->setupSyncPropsInSelector($recurse);
		return $this->frame_transform($parameters, $containers, $recurse, $backtrace, $as_simulation, $issues, $root_issues, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
	}
	/**
	 * Setup sync props in selector
	 * 
	 * @param string|boolean|array $selector
	 * @return null
	 */
	protected function setupSyncPropsInSelector(&$selector, &$bag = [], $depth = 0)
	{
		if (!$bag)
			$bag = [];

		// selector can't be null at this stage
		if (($selector === null) || ($selector === true) || isset($bag[$this->getTemporaryId()]))
			return;

		$bag[$this->getTemporaryId()] = $this;

		$is_root = ($depth === 0);
		$depth++;

		if (!$this->_synchronizable && !$is_root)
			return;

		if (!$selector)
			$selector = [];
		else if (is_string($selector))
			$selector = qParseEntity($selector);

		if ($this->_synchronizable)
			$selector = array_merge($selector, qParseEntity($this::GetSyncProps()));

		foreach ($this as $k => $itms)
		{
			if ((!($itms instanceof \QIModel)) || ($selector[$k] === null))
				continue;

			if (qis_array($itms))
			{
				foreach ($itms as $itm)
				{
					if ($itm instanceof \QModel)
						$itm->setupSyncPropsInSelector($selector[$k], $bag, $depth);
				}
			}
			else
				$itms->setupSyncPropsInSelector($selector[$k], $bag, $depth);
		}
	}

	/**
	 * 
	 * @param boolean|string|array $selector
	 * @return type
	 */
	protected function setupSyncProps()
	{
		if ($this->isNew())
		{
			if ($this->_synchronizable)
			{
				// if the owner was not set then set it as the current owner
				if (!$this->wasSet("Owner"))
					$this->setOwner(\Omi\App::GetCurrentOwner());

				// when in import process - we don't have the created by user
				if (!$this->wasSet("CreatedBy") && !\QApi::InImportProcess())
					$this->setCreatedBy(\Omi\User::GetCurrentUser()->getClone("Id"));

				if (\QApi::InImportProcess())
				{
					if (\QApi::$CurrentSupplier)
						$this->setSuppliedBy(\QApi::$CurrentSupplier);
				}
			}
		}

		if ($this->_synchronizable)
		{
			// however when we are in import process the owner must always be set as current owner
			if (\QApi::InImportProcess())
				$this->setOwner(\Omi\App::GetCurrentOwner());
			$this->setMTime(date("Y-m-d H:i:s"));
		}
	}

	/*===========================================================after and before process data============================================*/
	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeImport($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		if (!$this->getId())
			$this->_is_new = true;

		if ($this->getTransformState() == \QIModel::TransformDelete)
			$this->_to_rm = true;

		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		// setup sync props
		$this->setupSyncProps();

		if (($this instanceof \Omi\ITrackable) && $is_starting_point && $appProp)
			\QModel::TrackDataBeforeSave($this);

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;
		
		// do the call on the entire entity
		$this->callOnEntity($selector, "beforeImport", $transform_state, $_bag, $is_starting_point, $cc, $appProp);

		// do on after save and after import - we need to do same things on entities after saving and after import - this is where we will do them
		$this->beforeProcess($selector, $transform_state, $is_starting_point, $appProp, true);
	}
	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterImport($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		if (($this instanceof \Omi\ITrackable) && $is_starting_point && $appProp)
			\QModel::TrackDataAfterSave($this);

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;

		// do the call on the entire entity
		$this->callOnEntity($selector, "afterImport", $transform_state, $_bag, $is_starting_point, $cc, $appProp);

		// do on after save and after import - we need to do same things on entities after saving and after import - this is where we will do them
		$this->afterProcess($selector, $transform_state, $is_starting_point, $appProp, true);
	}
	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeSave($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		if (!$this->getId())
			$this->_is_new = true;

		if ($this->getTransformState() == \QIModel::TransformDelete)
			$this->_to_rm = true;
		
		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		// setup sync props
		$this->setupSyncProps();

		if (($this instanceof \Omi\ITrackable) && $is_starting_point && $appProp)
			\QModel::TrackDataBeforeSave($this);

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;

		// do the call on the entire entity
		$this->callOnEntity($selector, "beforeSave", $transform_state, $_bag, $is_starting_point, $cc, $appProp);
		
		// do on after save and after import - we need to do same things on entities after saving and after import - this is where we will do them
		$this->beforeProcess($selector, $transform_state, $is_starting_point, $appProp);
	}

	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterSave($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		if (($this instanceof \Omi\ITrackable) && $is_starting_point && $appProp)
			\QModel::TrackDataAfterSave($this);

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;

		// do the call on the entire entity
		$this->callOnEntity($selector, "afterSave", $transform_state, $_bag, $is_starting_point, $cc, $appProp);

		// do on after save and after import - we need to do same things on entities after saving and after import - this is where we will do them
		$this->afterProcess($selector, $transform_state, $is_starting_point, $appProp);
	}
	
	
	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterBeginTransaction($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;

		// do the call on the entire entity
		$this->callOnEntity($selector, "afterBeginTransaction", $transform_state, $_bag, $is_starting_point, $cc, $appProp);
	}

	/**
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeCommitTransaction($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		$cc = get_class($this);

		$id = $this->_id;
		if ($_bag === null)
			$_bag = [];
		else if (($id && ($this === $_bag[$cc][$id])) || ($_bag[$cc][""] && in_array($this, $_bag[$cc][""], true)))
			return;

		if ($id)
			$_bag[$cc][$id] = $this;
		else
			$_bag[$cc][""][] = $this;

		// do the call on the entire entity
		$this->callOnEntity($selector, "beforeCommitTransaction", $transform_state, $_bag, $is_starting_point, $cc, $appProp);
	}
	
	/**
	 * We need to check data on the entire entity
	 * 
	 * @param type $selector
	 * @param type $method
	 * @param type $transform_state
	 * @param type $_bag
	 * @param type $is_starting_point
	 * @param type $cc
	 */
	protected function callOnEntity($selector, $method, $transform_state, &$_bag, $is_starting_point = true, $cc = null, $appProp = null)
	{
		if (!in_array($method, ["afterSave", "beforeSave", "afterImport", "beforeImport", "beforeCommitTransaction", "afterBeginTransaction"]))
			throw new \Exception("Unimplemented!");

		if (!$cc)
			$cc = get_class($this);
		$_is_app = ($cc === \QApp::GetDataClass());

		/*
		$f = fopen("__call_dump.html", "a+");
		fwrite($f, "call {$method} on {$cc}[".($this->getId() ?: $this->getTemporaryId())."] "
			. "and it is ".($is_starting_point ? "" : "not ")."starting point<br/>THE APP PROP IS [{$appProp}]<br/><br/>");
		fclose($f);
		*/

		if (is_array($selector))
		{
			foreach ($selector as $k => $s)
			{
				if ($k === "*")
				{
					// do them all 
					foreach ($this as $p => $v)
					{
						if (($v instanceof QIModel) && ($p{0} !== "_"))
							$v->{$method}($s, $transform_state, $_bag, $_is_app, $_is_app ? $p : $appProp);
					}
					break;
				}
				else if (($k{0} !== "_") && (($obj = $this->$k) instanceof QIModel))
					$obj->{$method}($s, $transform_state, $_bag, $_is_app, $_is_app ? $k : $appProp);
			}
		}
		else if ($selector === true)
		{
			foreach ($this as $p => $v)
			{
				if (($v instanceof QIModel) && ($p{0} !== "_"))
					$v->{$method}(true, $transform_state, $_bag, $_is_app, $_is_app ? $p : $appProp);
			}
		}
	}
	
	public function markRelatedItemsForSync($appProp)
	{
		$itms = static::QueryAll("Id WHERE Gid=?", $this->getId());
		//qvardump($appProp, $itms, $this->getId());
		//die();
		
		// mark all items that needs to be updated
		if (!$itms || (count($itms) == 0))
			return;

		$app = \QApp::NewData();
		$app->{"set{$appProp}"}(new \QModelArray());

		foreach ($itms as $itm)
		{
			$itm->setToBeSynced(true);
			$app->$appProp[] = $itm;
		}
		
		$app->save($appProp . ".ToBeSynced");
	}

	/**
	 *  - do on before save and after import - we need to do same things on entities after saving and after import - this is where we will do them
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $is_starting_point
	 */
	protected function beforeProcess($selector, $transform_state, $is_starting_point, $appProp, $import = false)
	{
		//qvardump("execute before process [".microtime(true)."] on !".get_class($this)."::".($this->getId() ? $this->getId() : $this->getTemporaryId()));
		
		$dataCls = \QApp::GetDataClass();

		// if the sync items process was staSyncghrrted and the item is not new, is synchronizable, must be kept in sync and is starting point
		if ($is_starting_point && $appProp && $dataCls::$_SYNC_ITEMS_ON_PROCESS && !$this->isNew() && $this->_synchronizable && $this->_keepInSync)
		{
			// if we edit a single element - trigger the synchronization progress - only on save
			if (\QApi::$DataToProcess && \QApi::$DataToProcess->{$appProp} && ((count(\QApi::$DataToProcess->{$appProp}) === 1)) && !$import)
			{
				$this->markRelatedItemsForSync($appProp);
				/*
				$this->setToBeSynced(true);
				if ($this->getTransformState() == \QIModel::TransformDelete)
				{
					$clone = $this->getClone("Id, ToBeSynced");
					$clone->save("ToBeSynced");
				}
				*/
			}
			// else mark the product to be 
			else
			{
				
			}
		}
	}

	/**
	 *  - do on after save and after import - we need to do same things on entities after saving and after import - this is where we will do them
	 * 
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $is_starting_point
	 */
	protected function afterProcess($selector, $transform_state, $is_starting_point, $appProp, $import = false)
	{
		$dataCls = \QApp::GetDataClass();

		// if the sync items process was started and the item is not new, is synchronizable, must be kept in sync and is starting point
		if ($is_starting_point && $appProp && $dataCls::$_SYNC_ITEMS_ON_PROCESS && !$this->isNew() && $this->_synchronizable && $this->_keepInSync)
		{
			// if we edit a single element - trigger the synchronization progress
			if (\QApi::$DataToProcess && \QApi::$DataToProcess->{$appProp} && (count(\QApi::$DataToProcess->{$appProp}) === 1))
			{
				
			}
			// else mark the product to be 
			else
			{
				
			}
		}

		//qvardump("execute after process [".microtime(true)."] on !".get_class($this)."::".($this->getId() ? $this->getId() : $this->getTemporaryId()));
	}
	
	/*===========================================================after and before process data============================================*/
	/**
	 * filter for sync
	 * @return the current instance
	 */
	public function filterForSync()
	{
		return $this;
	}
	/**
	 * 
	 * @param \QIModel $obj
	 * @param \QIModel $newObj
	 * @param array $_bag
	 * @return type
	 * @throws Exception
	 */
	public function updateDependencies($obj, $newObj, &$_bag = [])
	{
		if ($_bag[$this->getTemporaryId()])
			return;
		$_bag[$this->getTemporaryId()] = $this;

		$type_inf = QModelQuery::GetTypesCache(get_class($this));

		// skip #%tables
		next($type_inf);
		// skip #%table
		next($type_inf);
		// skip #%id
		next($type_inf);
		// skip #%misc
		next($type_inf);

		$prop = key($type_inf);

		try
		{
			while ($prop)
			{
				$val = $this->{$prop};
				if (!$val || is_scalar($val) || (!($val instanceof QIModel)))
				{
					next($type_inf);
					$prop = key($type_inf);
					continue;
				}

				// the collection - it can be a collection of models or a scalar collection
				if ($val instanceof QIModelArray)
				{
					foreach ($val as $k => $item)
					{
						if (!($item instanceof \QModel))
							continue;
						($item === $obj) ? $this->{"set{$prop}_Item_"}($newObj, $k) : $item->updateDependencies($obj, $newObj, $_bag);
					}
				}
				else
					($val === $obj) ? $this->{"set{$prop}"}($newObj) : $val->updateDependencies($obj, $newObj, $_bag);

				next($type_inf);
				$prop = key($type_inf);
			}
		}
		catch (Exception $ex)
		{
			throw $ex;
		}
	}
	/**
	 * Returns true if an model was changed
	 * It checks whether a model have same data as in database
	 * 
	 * @param \QModel $obj
	 * @return boolean
	 * @throws \Exception
	 */
	public static function ModelWasChanged($obj)
	{
		if (!$obj->getId())
			return false;

		// get the entity from model data
		$dataEntity = qGetEntityFromData($obj);
		if (!$dataEntity)
			throw new \Exception("Cannot determine entity!");

		// implode the entity as string
		$dataEntityStr = qImplodeEntity($dataEntity);
		if (!$dataEntityStr)
			throw new \Exception("Cannot determine entity!");

		// intersect the entity with declared model entity
		// if we don't have entity then we don't have data to check - the object was not changed
		$entity = qIntersectSelectors($dataEntityStr, $obj::GetModelEntity());
		if (empty($entity))
			return false;

		$entityStr = qImplodeEntity($entity);
		if (strlen(trim($entityStr)) == 0)
			return false;

		// get clone
		$objClone = $obj->getClone("Id");
		
		// load data in cloned model using the entity
		$objClone->query($entityStr);

		// check if models have same data
		return !$obj::IsEqual($obj, $objClone);
	}
	/**
	 * Returns true if 2 objects are equal
	 * The method performs a data comparisson only
	 * 
	 * @param QModel $obj1
	 * @param QModel $obj2
	 * @return type
	 */
	public static function IsEqual($obj1, $obj2)
	{
		$diffs = $obj1::Compare($obj1, $obj2, ["MTime" => "MTime"]);
		return empty($diffs);
	}
	/**
	 * Compare 2 objects and return differences
	 * 
	 * @param mixed $obj1
	 * @param mixed $obj2
	 * @param array $diffs
	 * @param string $path
	 * @return array
	 */
	public static function Compare($obj1, $obj2, $skipProps = [], $path = null, &$diffs = [], &$_bag = [])
	{
		if (!$diffs || !is_array($diffs))
			$diffs = [];

		if (!$_bag || !is_array($_bag))
			$_bag = [];

		if (!$path)
			$path = is_object($obj1) ? get_class($obj1) : gettype($obj1);
		
		$isQm = ($obj1 instanceof \QModel);
		
		$toIterate = $obj1;
		
		$obj1Cls = get_class($obj1);

		$ids_set = false;
		$types_set = false;
		if ($isQm)
		{
			if (isset($_bag[$obj1->getTemporaryId()]))
				return;

			$_bag[$obj1->getTemporaryId()] = $obj1;
			$toIterate = $obj1->getModelType()->properties;
			
			if (!$obj2 || ($obj1->getId() != $obj2->getId()))
			{
				$diffs["_id"] = [$obj1->getId(), $obj2 ? $obj2->getId() : null];
				$ids_set = true;
			}

			$obj2Cls = null;
			if (!$obj2 || ($obj1Cls != ($obj2Cls = get_class($obj2))))
			{
				$diffs["_ty"] = [$obj1Cls, $obj2Cls];	
				$types_set = true;
			}
		}

		$skipCurrentObjProps = ($skipProps && $skipProps[$obj1Cls]);

		foreach ($toIterate as $key => $value)
		{
			// we may need to skip some properties from the compare process
			if (($skipProps && isset($skipProps[$key])) || 
				($skipCurrentObjProps && isset($skipCurrentObjProps[$obj1Cls]) && isset($skipCurrentObjProps[$obj1Cls][$key])))
			{
				continue;
			}

			if ($isQm)
				$value = $obj1->{$key};

			if (!$diffs[$key])
				$diffs[$key] = [];

			$vk = $obj2 ? (qis_array($obj1) ? $obj2[$key] : $obj2->{$key}) : null;

			if (is_object($value) || qis_array($value))
			{
				($value instanceof \QModel) ? 
					$value::Compare($value, $vk, $skipProps, $path.".".$key, $diffs[$key], $_bag) : 
					\QModel::Compare($value, $vk, $skipProps, $path.".".$key, $diffs[$key], $_bag);
			}
			else if ($value !== $vk)
			{
				if ($isQm)
				{
					if (!$ids_set)
					{
						$diffs["_id"] = [$obj1->getId(), $obj2 ? $obj2->getId() : null];
						$ids_set = true;
					}

					if (!$types_set)
					{
						$diffs["_ty"] = [get_class($obj1), $obj2 ? get_class($obj2) : null];
						$types_set = true;
					}
				}
				$diffs[$key][] = [$value, $vk];
			}
		}

		$diffs = array_filter($diffs);
		return $diffs;
	}
	
	/**
	 * Gets a default for a item selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetItemSyncQuery()
	{
		return static::GetModelSyncEntity()." WHERE 1 ??Id?<AND[Id=?] LIMIT 1";
	}
	
	/**
	 * Gets a default for a item selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetItemQuery($view_tag = null, $selector = null)
	{
		if ($view_tag && ($selector === null))
		{
			throw new \Exception('Deprecated situation, this should not happen!');
			
			/**
			$app = \QApp::GetDataClass();
			$selector = $app::GetFormEntity_Final($view_tag);
			if (is_string($selector))
				$selector = qParseEntity($selector);
			// join it with static::GetModelEntity()
			$selector = qJoinSelectors($selector, static::GetModelEntity());
			if (is_array($selector))
				$selector = qImplodeEntity($selector);
			*/
		}
		return (($selector !== null) ? (is_array($selector) ? qImplodeEntity($selector) : $selector) : static::GetModelEntity())." WHERE 1 "
				. " ??Id?<AND[Id=?] "
				. " ??Owner?<AND[Owner.Id=?] "
				. " ??CreatedBy?<AND[CreatedBy.Id=?] "
		. " LIMIT 1";
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetModelSyncEntity()
	{
		return "*";
	}
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetListingSyncQuery($selector = null)
	{
		$selector = $selector ?: static::GetListingSyncEntity();
		return (is_array($selector) ? qImplodeEntity($selector) : $selector)." ??LIMIT[LIMIT ?,?]";
	}
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetListingSyncEntity()
	{
		$class = get_called_class();
		$le = static::$ListingSyncEntity[$class];
		if ($le !== null)
			return $le;

		$type_inf = QModelQuery::GetTypesCache($class);
		next($type_inf);
		next($type_inf);
		next($type_inf);

		$entity = "";
		$pos = 0;
		while (($property = next($type_inf)))
		{
			if (!isset($property["[]"]))
			{
				if ($pos++)
					$entity .= ",";
				$entity .= key($type_inf);
			}
		}
		return static::$ListingSyncEntity[$class] = qParseEntity($entity);
	}
	/**
	 * Gets a default for a item selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetSyncPropertyItemQuery($property)
	{
		return null;
	}
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetSyncPropertyListingQuery($property)
	{
		return null;
	}
	/**
	 * Load system data if any
	 * 
	 * @param QModel $data
	 */
	public static function LoadSystemData($data, &$_bag = null)
	{
		if (!$data)
			return;

		if ($_bag[$data->getTemporaryId()])
			return;

		$_bag[$data->getTemporaryId()] = $data;

		$owner = \Omi\App::GetCurrentOwner();
		if (!$owner)
			throw new \Exception("Cannot get sync data without owner!");

		if ($data->_synchronizable && !$data->getId() && $data->Gid)
		{
			// set the id on data if we have it by gid
			$edata = $data::QueryFirst("Id WHERE Gid=? AND Owner.Id=?", [$data->Gid, $owner->getId()]);
			if ($edata && $edata->getId())
				$data->setId($edata->getId());
		}

		foreach ($data as $v)
		{
			if (!($v instanceof \QIModel))
				continue;

			if ($v instanceof \QModelArray)
			{
				foreach ($v as $_vv)
				{
					if ($_vv instanceof \QModel)
						$_vv::LoadSystemData($_vv, $_bag);
				}
			}
			else
				$v::LoadSystemData($v, $_bag);
		}
	}

	public static function SkipSyncSelector()
	{
		return null;
	}

	public static function SkipExpandSelector()
	{
		return null;
	}
	/**
	 * just make sure that we don't send data that is not owned
	 */
	public static function CleanupNotOwnedData($model, $owner = null)
	{
		if (!$model || !$model->_synchronizable)
			return;

		if ($owner == null)
		{
			$owner = $model->Owner;
			if (!$owner)
				return;
		}

		foreach ($model ?: [] as $key => $value)
		{
			if ($value instanceof \QIModel)
			{
				if ($value instanceof \QModelArray)
				{
					foreach ($value ?: [] as $uk => $itm)
					{
						if ($itm instanceof \QModel)
						{
							 if ($itm->_synchronizable && (!$itm->Owner || ($itm->Owner->getId() !== $owner->getId())))
								 unset($value[$uk]);
							 $itm::CleanupNotOwnedData($itm, $owner);
						}
					}
				}
				else if ($value instanceof \QModel)
				{
					if ($value->_synchronizable && (!$value->Owner || ($value->Owner->getId() !== $owner->getId())))
						unset($model->{$key});
						//$model->{$key} = null;
					$value::CleanupNotOwnedData($value, $owner);
				}
			}
		}
	}
	
	public static function GetAppPropertyForTypes($types)
	{
		if (is_scalar($types))
			$types = [$types => $types];
		
		// get the app data type
		$appDataType = \QModel::GetTypeByName(\QApp::GetDataClass());
		
		// go thourgh app data properties and return first option that matches property types
		foreach ($appDataType->properties as $prop)
		{
			if ($prop->storage["none"])
				continue;

			$ct = $prop->getCollectionType();
			if (!$ct)
				continue;

			$propTypes = $ct->getAllInstantiableReferenceTypes();
			$res = array_intersect_key($propTypes, $types);
			if ($res === $types)
				return $prop->name;
		}
		return null;
		
	}

	/**
	 * @api.enable
	 * 
	 * @param QModel $obj
	 */
	public static function TrackData($obj, $before = false, $toAdd = false, $toRm = false, $toJson = null, $executed = 0)
	{
		//ob_start();
		
		$user = \Omi\User::GetCurrentUser();
		if (!$user)
			throw new \Exception("Used not logged in!");

		if (!$toJson)
		{
			$objClone = $obj->getClone("Id");
			$objClone->query($objClone::GetModelEntity());
			$toJson = $objClone->toJSON();
		}

		//qvardump($objClone);

		//qvardump($before, $toAdd, $toRm);

		if ($before)
		{
			$data = [
				"Action" => $toAdd ? \Omi\TrackInfo::Add : ($toRm ? \Omi\TrackInfo::Delete : \Omi\TrackInfo::Update),
				"Class" => get_class($obj),
				"Identifier" => $obj->getId(),
				"DataBefore" => $toJson,
				"By" => $user->toArray("Id"),
				"Completed" => false
			];

			//if (!$toAdd)
			//	$data["Instance"] = $obj->toArray("Id");

			//qvardump("SAVE BEFORE  ::  ", $data);
			\QApi::Merge("TrackInfo", $data);
		}
		else
		{
			$params = [
				"By" => $user->getId(),
				"Completed" => false,
				"Class" => get_class($obj)
			];

			$toAdd ? ($params["Action"] = \Omi\TrackInfo::Add) : ($params["Identifier"] = $obj->getId());

			//qvardump($params);

			$trackInfos = \QApi::Query("TrackInfo", null, $params);
			$trackData = $trackInfos ? reset($trackInfos) : null;

			if ($trackData || ($executed > 9))
			{
				//qvardump($trackData->DataBefore);
				$initalObjFromData = $trackData ? \QModel::FromJSON($trackData->DataBefore) : null;
				$currentObjFromData = \QModel::FromJSON($toJson);

				$diffs = $initalObjFromData::Compare($initalObjFromData, $currentObjFromData, ["MTime" => "MTime"]);
				$changed = !empty($diffs);

				// if we just edit the data then don't save it if no changes were made
				if (!$toAdd && !$toRm && !$changed)
				{
					// if we don't have track data don't add it
					if ($trackData)
					{
						$trackData->setTransformState(\QIModel::TransformDelete);
						\QApi::Merge("TrackInfo", $trackData);
					}
				}
				else
				{
					$data = [
						"Action" => $toAdd ? \Omi\TrackInfo::Add : ($toRm ? \Omi\TrackInfo::Delete : \Omi\TrackInfo::Update),
						"Class" => get_class($obj),
						"Completed" => true,
						"DataAfter" => $toJson,
						"Date" => date("Y-m-d H:i:s"),
						"Identifier" => $obj->getId(),
						"Changed" => $changed,
						"Changes" => json_encode($diffs)
					];

					//if ($toAdd)
					//	$data["Instance"] = $obj->toArray("Id");

					if ($trackData)
						$data["Id"] = $trackData->getId();

					//qvardump("SAVE AFTER  ::  ", $data);
					\QApi::Merge("TrackInfo", $data);
				}
			}
			else
			{
				//var_dump("not found call again!".$executed);
				sleep(1);
				$cls = get_class($obj);
				//\QApi::ExecAsync($cls.'::TrackData', [$obj, $before, $toAdd, $toRm, $toJson, ++$executed]);
			}
		}

		/*
		$f = fopen("track_data.html", "a+");
		fwrite($f, ob_get_clean() . "<hr/><hr/><hr/>");
		fclose($f);
		*/
	}

	protected static function TrackDataBeforeSave($model)
	{
		/*
		$cls = get_class($model);
		// we need to load the initial data here because if we try to load the data async it will execute query after data is saved
		if ($model->getId())
		{
			$model_clone = $model->getClone("Id");
			$model_clone->query($model_clone::GetModelEntity());
			$toJson = $model_clone->toJSON();
		}
		else
			$toJson = $model->toJSON();
		//\QApi::ExecAsync($cls.'::TrackData', [$model, true, $model->isNew(), $model->toRM(), $toJson]);
		*/
	}
	
	protected static function TrackDataAfterSave($model)
	{
		//$cls = get_class($model);
		//\QApi::ExecAsync($cls.'::TrackData', [$model, false, $model->isNew(), $model->toRM()]);
	}

	public static function GetForSaveSelector($selector = null, $from = null, $state = null)
	{
		$cc = get_called_class();
		if ($from === null)
		{
			throw new \Exception("Not Implemented for now!");
			// determine here the selector from @storage.appProperty
		}
		return \QMySqlStorage::GetSaveSelector($from, $cc, null, $state, $selector);
	}

	/**
	 * Saves data without provision and executes triggers and before/after save
	 * 
	 * @param type $from
	 * @param type $data
	 * @param type $selector
	 * @return type
	 */
	public static function SaveWithoutProvision($from, $data, $selector = null)
	{
		if ((!$data) || (count($data) === 0))
			return $data;
		
		// $dataCls = \QApp::GetDataClass();

		$intialFrom = $from;
		$from = \QApi::GetFrom($from);

		$parsed_sources = $from ? \QApi::ParseSourceInfo($from) : [null, null];
		if (!$parsed_sources)
			throw new Exception("Source information error");

		if (is_string($selector))
			$selector = qParseEntity($selector);

		$storage_model = QApp::GetDataClass();

		$result = [];
		foreach ($parsed_sources as $src_key => $src_info)
		{
			// @todo : handle multiple requests on the same source
			$src_from = reset($src_info);
			$is_collection = false;
			$src_from_types = \QApi::DetermineFromTypes($storage_model, $src_from, $is_collection);
			
			// we will need to convert data here
			// $array, $type = "auto", $selector = null, $include_nonmodel_properties
			if ($src_from_types)
			{
				if ($data)
				{
					if (is_array($data))
					{
						// determine $data_is_collection - don't use the parameter
						/*==========================determine if data is provided as collection or as single item=========================*/
						$_ft = reset($src_from_types);
						$decode_type = $_ft ? $_ft.($is_collection ? "[]" : "") : "auto";
						
						$data_is_collection = true;
						$_ks = array_keys($data);
						
						$_dmt = \QModel::GetTypeByName($_ft);
						
						foreach ($_ks as $__k)
						{
							if ($_dmt->properties[$__k])
							{
								$data_is_collection = false;
								break;
							}
						}
						/*================================================================================================================*/

						if ((!$data_is_collection) && $is_collection)
							$data = [$data];
						$data = QModel::FromArray($data, $decode_type);
					}
				}

				if ($is_collection && $data && (!qis_array($data)))
				{
					$_item = $data;
					$data = new QModelArray();
					$data[] = $_item;
				}
			}
			
			$app = \QApp::NewData();
			$app->{"set{$from}"}($data);
			$first_obj = qis_array($data) ? reset($data) : $data;
			$selector = [$from => $first_obj::GetForSaveSelector($selector, $from)];
			// trigger all events without provision
			$result[$src_key] = $app->save($selector, null, null, false, true, true);
		}
		
		return !$result ? null : ((count($result) === 1) ? reset($result) : $result);
		
	}

	######## QMODEL PATCH END !! #############

	/**
	 * return filters to be used in listing query
	 * 
	 * @return string
	 */
	public static function GetListingQueryFilters()
	{
		return "";
	}
	
	// add lines from here on

}