<?php

class QModelArray extends ArrayObject implements QIModelArray
{
	public $_qc;
	
	public $_ty;
	
	public $_ts; // we need this
	public $_tsp;
	public $_tsx;
	
	public $_findex;
	
	public $_scf;
	public $_sl;
	public $_tid;
	
	public $_rowi;
	public $_iro;
	// public $_byid;
	// public $_byid_r;
	
	/**
	 * @todo The current implmentation is not final
	 * @var boolean
	 */
	public static $SecurityCheckTransform = false;
	
	/**
	 * Hidden properties
	 * 
	 *  A few things any MODEL should have
	 * 
	 * $_ty The type of the model
	 * $_sc The storage container (must be stored with a role)
	 * $_id The id of the object (integer/string)
	 * 
	 * $_ts  This is the transform state of the object : No Action, Create / Update / ... + Custom
	 * $_tsp The transform state of the properties, key-value array at the moment
	 *       The $_tsp is useful when removing Unstructured elements that can not be marked for deletion
	 * 
	 * $_ols The old, changed values
	 * 
	 * $_lk True if the object is locked
	 * 
	 * $_rowi - remembers the records ids on a many to many collection
	 * 
	 * $_ppty - property where this collection is linked
	 * 
	 * $_qc - the query count
	 */
	
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
	 * Gets the keys of the Array object
	 * 
	 * @return (string|integer)[]
	 */
	public function getKeys()
	{
		$arr = $this->exchangeArray(array());
		$keys = array_keys($arr);
		$this->exchangeArray($arr);
		return $keys;
	}
	
	/**
	 * Identifies and calls QModelProperty::fixVal() on the property linked to this collection
	 * 
	 * @param mixed $val
	 * @return mixed
	 */
	public function fixVal($val)
	{
		return $this->_ppty ? $this->_ppty->getCollectionType()->fixVal($val) : $val;
	}
	
	/**
	 * Prepends an element to this collection
	 * 
	 * @param mixed $data
	 * @param string|integer $key
	 */
	public function prepend($data, $key = null)
	{
		if ($key)
		{
			$arr = array($key => $data) + $this->exchangeArray(array());
			$this->exchangeArray($arr);
		}
		else
		{
			$arr = $this->exchangeArray(array());
			array_unshift($arr, $data);
			$this->exchangeArray($arr);
		}
	}
	
	/**
	 * Similar to array_splice
	 * 
	 * @param integer $offset
	 * @param integer $length
	 * @param array $replacement
	 * @return array
	 */
	public function splice($offset, $length = 0, $replacement = null)
	{
		$arr = $this->exchangeArray(array());
		$ret = array_splice($arr, $offset, $length, $replacement);
		$this->exchangeArray($arr);
		return $ret;
	}
	
	/**
	 * In case this collection was created by a query it will set the total 
	 * number of records that the collections would have had if no LIMIT would have 
	 * been applied
	 * 
	 * @param integer $count
	 */
	public function setQueryCount($count)
	{
		$this->_qc = $count;
	}
	
	/**
	 * In case this collection was created by a query it will get the total 
	 * number of records that the collections would have had if no LIMIT would have 
	 * been applied
	 * 
	 * @return integer
	 */
	public function getQueryCount()
	{
		return $this->_qc;
	}
	
	/**
	 * Gets the model type
	 * 
	 * @return QModelType
	 */
	public function getModelType()
	{
		return $this->_ty ?: ($this->_ty = QModel::GetTypeByName(get_class($this)));
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
	public function getContainers($use_for = self::StorageContainerForRead)
	{
		if (!$this->_sc)
			$this->_sc = $this->getDefaultContainers();
		return is_array($this->_sc) ? $this->_sc[$use_for] : $this->_sc;
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
		return QApp::GetStorage()->getDefaultStorageContainerForType($this->getModelProperty());
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
	 * Sets the identifier of the object
	 * 
	 * @param integer|string $id
	 */
	public function setId($id)
	{
		$this->_id = $id;
	}
	/**
	 * Gets the transform state of the object 
	 * 
	 * @return integer|string 
	 */
	public function getTransformState($key = null, $forProps = false)
	{
		// 	 * $_ts  This is the transform state of the object : No Action, Create / Update / ... + Custom
		//return $this->_ts;
		return ($key !== null) ? ($this->_tsp ? $this->_tsp[$key] : null) : ($forProps ? $this->_tsp : $this->_ts);
	}
	
	/**
	 * Sets the transform state of the object 
	 * 
	 * @param integer|string $transf_state 
	 */
	public function setTransformState($transf_state, $key = null)
	{
		//$this->_ts = $transf_state;
		if ($key !== null)
		{
			if (!$this->_tsp)
				$this->_tsp = array();
			$this->_tsp[$key] = $transf_state;
		}
		else
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
	 * If $query_if_unset === true it will read from all containers that are used by this instance
	 * If $property is null then we do a reload of this object from the storage(s)
	 *
	 * @param string|string[] $key
	 * @param boolean|QIStorageContainer|QIStorageContainer[] $query_if_unset
	 * @param mixed $requester
	 * @param mixed $reason
	 * 
	 * @return mixed
	 */
	public function get($key = null, $query_if_unset = null, $requester = null, $reason = null)
	{
		if ($query_if_unset)
			throw new Exception("\$query_if_unset is not yet supported in QModelArray");
		
		if (is_string($key))
			return $this[$key];
		else
		{
			// at this point $property should be an array of strings
			$ret = new QModelArray();
			foreach ($key as $k)
				$ret[$k] = $this[$k];
			return $ret;
		}
	}
	/**
	 * Sets the key with the specified name
	 * Set also may specify the `$requester` 
	 * Based on the requester we may decide to set the data differently
	 * We may also specify more keys at a time. In such case we expect either an object or an associative array 
	 * and $value is expected to be null
	 * If $transform_with === true it will transform with all containers that are used by this instance
	 *
	 * @param string|string[] $key
	 * @param mixed $value
	 * @param boolean|QIStorageContainer|QIStorageContainer[] $transform_with The containers to transform with
	 * @param mixed $requester
	 * @param mixed $reason
	 * 
	 */
	public function set($key, $value = null, $transform_with = null, $requester = null, $reason = null)
	{
		if (qis_array($key))
		{
			$c_type = ($prop = $this->getModelProperty()) ? $prop->getCollectionType() : null;
			// at this point $property should be an array of strings
			foreach ($key as $k => $_v)
			{
				$v = $c_type ? $c_type->fixVal($_v) : $_v;
				// manage saving the old property
				if ((!(isset($this->_ols[$k]) || ($this->_ols && array_key_exists($k, $this->_ols)))) && (($value === null) || ($this[$k] !== $v)))
					$this->_ols[$k] = $this[$k];
				// end old
				$this[$k] = $v;
			}
		}
		else 
		{
			// manage saving the old property
			if ((!(isset($this->_ols[$key]) || ($this->_ols && array_key_exists($key, $this->_ols)))) && (($value === null) || ($this[$key] !== $value)))
				$this->_ols[$key] = $this[$key];
			if (($prop = $this->getModelProperty()) !== null)
				$value = $prop->getCollectionType()->fixVal($value);
			// end old
			$this[$key] = $value;
		}
		/*
		else
			throw new Exception("Invalid key type or null");
		 * 
		 */
	}
	
	/**
	 * 
	 * Queries the object to get more data
	 * For QIModelArray a new instance will be created and returned
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public function populate($query, $binds = null, &$dataBlock = null, $skip_security = true, \QIStorage $storage = null)
	{
		if (($query === null) && ($binds === null))
		{
			$selector = static::GetModelEntity();
			if (is_array($selector))
				$selector = qImplodeEntity($selector);
			$query = $selector." WHERE Id IN (?)";
			$binds = [];
			foreach ($this as $item)
				$binds[] = $item->getId();
		}
		return QModelQuery::BindQuery($query, $binds, $this, $dataBlock, $skip_security, null, true, $storage);
	}
	
	/**
	 * 
	 * Queries the object to get more data
	 * For QIModelArray a new instance will be created and returned
	 * 
	 * @param string $query
	 * @param array|string $binds
	 * @param array $dataBlock
	 * @param boolean $skip_security
	 * 
	 * @return QIModel
	 */
	public function query($query, $binds = null, &$dataBlock = null, $skip_security = false)
	{
		return QModelQuery::BindQuery($query, $binds, $this, $dataBlock, $skip_security);
	}
	
	/**
	 * Checks if a property was set
	 * 
	 * @param string $key
	 * 
	 * @return bool 
	 */
	public function wasSet($key)
	{
		// we try to use isset first as it is a lot faster
		return isset($this[$key]) || $this->_wst[$key];
	}
	
	/**
	 * Tests if a key was changed
	 * If no key is specified tests if any key was changed
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function wasChanged($key = null)
	{
		if ($key)
		{
			// if not _ols or _ols[$key] then it was not changed 
			return ((!$this->_ols) || (!(isset($this->_ols[$key]) || array_key_exists($key, $this->_ols)))) ? false : $this[$key] !== $this->_ols[$key];
		}
		else
		{
			if ($this->_old)
			{
				foreach ($this->_old as $k => $v)
					if ($v !== $this[$k])
						return true;
				return false;
			}
			else
				return false;
		}
	}
	
	/**
	 * Checks if a key has the old value saved
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function hasOldValue($key)
	{
		return isset($this->_ols[$key]) || ($this->_ols && array_key_exists($key, $this->_ols));
	}
	
	/**
	 * Gets the old/initial value of a key
	 * 
	 * @param string $key
	 * @return mixed
	 */
	public function getOldValue($key)
	{
		return isset($this->_ols[$key]) ? $this->_ols[$key] : null;
	}
	
	/**
	 * Gets the list of changed keys
	 * 
	 * @return string[]
	 */
	public function getChangedProperties()
	{
		return $this->getChangedKeys();
	}
	
	/**
	 * Gets the list of changed keys
	 * 
	 * @return string[]
	 */
	public function getChangedKeys()
	{
		if ($this->_old)
		{
			$list = array();
			foreach ($this->_old as $k => $v)
				if ($v !== $this[$k])
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
	 * Run a transform on a MODEL
	 * The action is one of the QIModel::ModelState*, custom actions may be used as strings
	 * Specific containers may be specified, if not the ones provided by QIModel::getContainers will be used
	 * If $recurse is set to true transform will be called foreach key that implements QIModel 
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
	public function transform($parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, 
			$as_simulation = false, &$issues = null, &$root_issues = null, bool $trigger_provision = true, bool $trigger_events = true, 
			bool $trigger_save = false, bool $trigger_import = false)
	{
		// if the object is not marked for transform or a lock is already in place on this object we avoid infinte loop
		if ($this->_lk)
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
			// $type = $this->getModelType();
			
			$rec_isarr = is_array($recurse);
		
			foreach ($this as $key => $value)
			{
				// $value = $this->{$property->name};
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

			foreach ($this as $item)
			{
				if ($item instanceof QIModel)
					$item->cleanupLocks();
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
	 * Gets the model property that this collection is under
	 * 
	 * @return QModelProperty
	 */
	public function getModelProperty()
	{
		return $this->_ppty;
	}
	/**
	 * Sets the model property that this collection is under
	 * 
	 * @param string|QModelProperty $property
	 * @param QIModel $parent
	 */
	public function setModelProperty($property = null, QIModel $parent = null)
	{
		if (is_string($property) && $parent)
			$property = $parent->getModelType()->properties[$property];
		$this->_ppty = $property;
		$this->_pinst = $parent;
	}
	
	/**
	 * Gets the model type
	 * 
	 * @param string $type
	 * 
	 * @return QModelType|QIModelTypeUnstruct
	 */
	public function GetTypeByName($type)
	{
		return QModel::GetTypeByName($type);
	}
	
	/**
	 * Gets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * 
	 * @return integer
	 */
	public function getRowIdAtIndex($key)
	{
		return $this->_rowi[$key];
	}
	/**
	 * Sets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * @param integer $row_id
	 * 
	 */
	public function setRowIdAtIndex(string $key, string $row_id)
	{
		$this->_rowi[$key] = $row_id;
		$this->_iro[$row_id] = $key;
	}
	
	/**
	 * Gets an item from the collection based on it's rowid
	 * 
	 * @param integer $row_id
	 * @return mixed
	 */
	public function getByRowId($row_id)
	{
		return ($k = $this->_iro[$row_id]) ? $this[$k] : null;
	}
	
	/**
	 * Gets an item (QIModel) by id
	 * 
	 * @param integer|string $id
	 * @return \QIModel
	 */
	public function getById($id)
	{
		if (($this->_indx !== null) && ($item = $this->_indx[$id]))
			return $item;
		else
		{
			$this->_indx = [];
			$item = null;
			foreach ($this as $v)
			{
				if (($v instanceof QIModel) && ($i_id = $v->getId()))
				{
					if ($i_id == $id)
						$item = $v;
					$this->_indx[$i_id] = $v;
				}
			}
			return $item;
		}
	}
	
	/**
	 * Sets a value with specifed rowid, existing items (with the same rowid) will be replaced
	 * 
	 * @param type $row_id
	 * @param type $value
	 * 
	 */
	public function setWithRowId($row_id, $value)
	{
		if (($key = $this->_iro[$row_id]) !== null)
			$this[$key] = $value;
		else
		{
			// VERY UGLY TO BE IMPROVED !!! 
			// TO DO
			
			$this[] = $value;
			end($this);
			$key = key($this);
			
			$this->_iro[$row_id] = $key;
			$this->_rowi[$key] = $row_id;
		}
	}
	
	/**
	 * Gets the full id of this instance
	 * 
	 * @return string
	 */
	public function getFullId()
	{
		return $this->_ppty ? ($this->_id ? $this->_id."\$".$this->_ppty->getId() : 
				("?".($this->_tmpid ?: ($this->_tmpid = QModel::GetNextId()))."\$".$this->_ppty->getId())) : 
				("?".($this->_tmpid ?: ($this->_tmpid = QModel::GetNextId())));
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
		{
			$is_first = true;
			$str = "";
		}
		else
			$is_first = false;
		if (!$refs)
			$refs = array();
		// link it as a reference
		$str .= "{\"_ty\":\"".$this->getModelType()->class."\"";
		if (!$is_first)
		{
			$id = $this->getId();
			if ($id === null)
			{
				$str .= ",\"_tmpid\":".$this->getTemporaryId();
			}
			else
			{
				$str .= ",\"_id\":".json_encode($id);
			}
		}
		$ts = $this->getTransformState();
		if ($ts !== null)
		{
			$str .= ",\"_ts\":".json_encode($ts);
		}
		$tsp = $this->getTransformState(null, true);
		if ($tsp !== null)
			$str .= ",\"_tsp\":".json_encode($tsp);
		if (!$is_first)
		{
			$f_id = $this->getFullId();
			if ($refs[$f_id] === $this)
			{
				$str .= ",\"_isref\": true}\n";
				return;
			}
		}
		
		if ($this->_qc !== null)
		{
			$str .= ",\"_qc\":".json_encode($this->_qc);
		}
		
		$refs[$f_id] = $this;
		
		// we also need to add a bit of data
		if ($this->_rowi)
		{
			$str .= ",\"_rowi\":".json_encode($this->_rowi);
			
		}
		
		$str .= ",\"_items\":{";
		
		$comma = false;
		foreach ($this as $k => $v)
		{
			if ($k{0} == "_")
				continue;
			
			$str .= ($comma ? "," : "")."\n\"".$k."\":";
			if ($v === null)
				$str .= "null";
			else if (is_string($v))
			{
				$str .= json_encode($v);
			}
			else if (is_integer($v) || is_float($v))
				$str .= $v;
			else if (is_bool($v))
				$str .= $v ? "true" : "false";
			else if ($v instanceof QIModel)
				$v->exportToJs($ignore_nulls, $str, $refs);
			else if (is_array($v) || is_object($v))
			{
				$str .= json_encode($v);
			}
			
			$comma = true;
		}
		$str .= "}\n}\n"; // close data block also
		return $str;
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
	 * Alias for "save"
	 *
	 * @param string|boolean|null $selector
	 */
	public function store($selector = false, $state = null)
	{
		return $this->save($selector, $state);
	}
	/**
	 * Saves the model in the Storage, based on it's state, the default is merge
	 *
	 * @param string|boolean|null $selector
	 */
	// public function save($selector = null, $data = null, $state = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	public function save($selector = null, array $data = null, $state = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if ($data)
			throw new \Exception('Not implemented yet');
			// $this->extractFromArray($data);
		// $parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false, &$issues = null, &$root_issues = null, bool $trigger_provision = true, bool $trigger_events = true
		$issues = null;
		$root_issues = null;
		return $this->transform($state, null, $selector, null, false, $issues, $root_issues, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
		
		/*
		if ($selector === null)
			$selector = static::GetModelEntity();
		$ret = [];
		QApp::GetStorage()->begin();
		try
		{
			foreach ($this as $k => $v)
			{
				if ($v instanceof QIModel)
					$ret[$k] = $v->save($selector, $state, $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
			}
			QApp::GetStorage()->commit();
		}
		catch (Exception $ex)
		{
			QApp::GetStorage()->rollback();
			throw $ex;
		}
		return $ret;*/
	}
	
	/**
	 * Run a MERGE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function merge($selector = null, bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false)
	{
		if ($selector === null)
		{
			// $selector = static::GetModelEntity();
			$sels = [];
			foreach ($this as $item)
			{
				$class = get_class($item);
				if ($sels[$class] === null)
					$sels[$class] = $class::GetModelEntity();
			}
			if (count($sels[$class]) > 1)
				throw new \Exception('Multiple types not implemented');
			$selector = reset($sels);
		}
		return $this->save($selector, array("ts" => QModel::TransformMerge), $trigger_provision, $trigger_events, $trigger_save, $trigger_import);
	}
	/**
	 * Run a INSERT on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function insert($selector = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		return $this->save($selector, array("ts" => QModel::TransformCreate));
	}
	/**
	 * Run a UPDATE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function update($selector = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		return $this->save($selector, array("ts" => QModel::TransformUpdate));
	}
	/**
	 * Run a DELETE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function delete($selector = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		return $this->save($selector, array("ts" => QModel::TransformDelete));
	}
	
	/**
	 * Called when an upload was made and you need to do something with the file
	 * As an option the file could be handled on upload
	 * 
	 * @param string $key
	 * @param string[] $upload_info
	 */
	public function handleUpload($key, $upload_info)
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
	
	public function first()
	{
		return reset($this);
	}
	
	public function reset()
	{
		return reset($this);
	}
	
	public function next()
	{
		return next($this);
	}
	
	public function prev()
	{
		return prev($this);
	}
	
	public function current()
	{
		return current($this);
	}
	
	public function key()
	{
		return key($this);
	}
	
	public function each()
	{
		return each($this);
	}
	
	public function last()
	{
		return end($this);
	}
	
	public function end()
	{
		return end($this);
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
		$str = "{";
		$pp_comma = "";
		if ($with_type)
		{
			$str .= "\"_ty\":".json_encode(get_class($this), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$pp_comma = ",";
		}
		
		foreach ($this as $prop => $val)
		{
			$ty = gettype($val);
			/* "boolean" "integer" "double" "string" "array" "object" "resource" "NULL" "unknown type"*/
			switch ($ty)
			{
				case "string":
				case "array":
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
				case "object":
				{
					if ($val instanceof QIModel)
					{
						$str .= "{$pp_comma}\"{$prop}\":";
						$str .= $val->toJSON($selector, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
					}
					else
						$str .= "{$pp_comma}\"{$prop}\":".json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
	 * Marks the element at the specified $key for deletion by applying 
	 *		$this->_tsp[$key] = self::TransformDelete and if the element in the collection is instanceof QIModel
	 *		$this[$key]->_ts = self::TransformDelete 
	 * 
	 * @param string|int|(string|int)[] $key The position where to mark. If key is an array it will mark multiple elements
	 */
	public function markDelete($key)
	{
		if (is_array($key))
		{
			foreach ($key as $k)
			{
				$obj = $this->_tsp[$k] = self::TransformDelete;
				if ($obj instanceof QIModel)
					$obj->markDelete();
			}
		}
		else
		{
			$obj = $this->_tsp[$key] = self::TransformDelete;
			if ($obj instanceof QIModel)
				$obj->markDelete();
		}
	}
	
	/**
	 * Marks the element at the specified $key for beeing removed from the collection by applying $this->_tsp[$key] = self::TransformDelete
	 * @param string|int|(string|int)[] $key The position where to mark. If key is an array it will mark multiple elements
	 */
	public function markUnlink($key)
	{
		if (is_array($key))
		{
			foreach ($key as $k)
				$this->_tsp[$k] = self::TransformDelete;
		}
		else
			$this->_tsp[$key] = self::TransformDelete;
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
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		$class = get_class($this);
		$arr = [];
		if ($with_type)
			$arr["_ty"] = $class;
		
		$was_included = false;
		if ($refs_no_class === null)
			$refs_no_class = [];

		if (($refs_class = $refs_no_class[$class]))
		{
			foreach ($refs_class as $_obj)
			{
				if ($this === $_obj)
				{
					$was_included = true;
					break;
				}
			}
		}
		
		if (!$was_included)
		{
			$refs_no_class[$class][] = $this;
			
			if ($include_nonmodel_properties)
			{
				$ref_cls = new \ReflectionClass($this);
				$props = $ref_cls->getProperties();

				foreach ($props ?: [] as $prop)
				{
					$key = $prop->name;
					$val = $this->{$key};
					
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
							$arr[$key] = $val;
							break;
						}
						case "NULL":
						{
							if (!$ignore_nulls)
								$arr[$key] = null;
							break;
						}
						case "object":
						{
							if ($val instanceof QIModel)
								$arr[$key] = $val->toArray($selector, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
							else
								$arr[$key] = (array)$val;
						}
						default:
							break;
					}
				}
			}

			foreach ($this as $key => $val)
			{
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
						$arr[$key] = $val;
						break;
					}
					case "NULL":
					{
						if (!$ignore_nulls)
							$arr[$key] = null;
						break;
					}
					case "object":
					{
						if ($val instanceof QIModel)
							$arr[$key] = $val->toArray($selector, $include_nonmodel_properties, $with_type, $with_hidden_ids, $ignore_nulls, $refs, $refs_no_class);
						else
							$arr[$key] = (array)$val;
					}
					default:
						break;
				}
			}
		}
		
		return $arr;
	}
	/**
	 * 
	 * @param \QModelArray $data
	 * @param boolean $ignore_nulls
	 * @param array $refs
	 * @param boolean $metadata
	 * @param boolean $array_in_items
	 * @return type
	 */
	public static function QOutputJson($data, $ignore_nulls = false, &$refs = null, $metadata = true, $array_in_items = true)
	{
		// handle model
		$f_id = $data->getFullId();
		$m_type = $data->_ty ? $data->_ty->class : get_class($data);

		if ($f_id === null)
			$f_id = $m_type;

		// type
		if ($metadata)
		{
			echo "{\"_ty\":";
			echo json_encode($m_type);
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

		if ($metadata && ($data->_qc !== null))
		{
			echo ",\"_qc\":";
			echo json_encode($data->_qc, JSON_UNESCAPED_SLASHES);
		}
		if ($metadata && $data->_rowi)
		{
			echo ",\"_rowi\":";
			echo json_encode($data->_rowi, JSON_UNESCAPED_SLASHES);
		}

		if ($metadata)
		{
			if ($array_in_items)
				echo ",\"_items\":{";
			else
				echo ",\"__len__\":".count($data);
		}

		$refs[$f_id] = true;

		$comma = ($metadata && !$array_in_items);

		foreach ($data as $k => $v)
		{
			if (($k{0} === "_") || ($ignore_nulls && ($v === null)))
				continue;

			if ($comma)
				echo ",";
			echo json_encode((string)$k, JSON_UNESCAPED_SLASHES);
			echo ":";

			($v instanceof \QIModel) ? 
				$v::QOutputJson($v, $ignore_nulls, $refs, $metadata, $array_in_items) : 
				\QModel::QOutputJson($v, $ignore_nulls, $refs, $metadata, $array_in_items);
			$comma = true;
		}

		if ($metadata)
		{
			if ($array_in_items)
				echo "}";
		}
		echo "}\n";
	}
	/**
	 * Outputs the content of the object into a CSV
	 * 
	 * @return string
	 */
	public function toCSV($selector = null, $stream = null, &$data = null, 
							$all_keys = null, $full_selector = null,
							$cols_prefix = "", $is_top = true, $top_model = true, $first_element = false, $data_pos = 0)
	{
		if (!$stream)
			$stream = fopen('php://output', 'w+');
			// $stream = fopen('php://temp', 'w+'); - a virtual stream
		
		$first = true;
		$pos = 0;
		
		foreach ($this as $element)
		{
			if ($element instanceof QIModel)
			{
				// if is top model - reset data for each item because otherwise items are duplicated
				// they are written to file if top_model and if data is the global data then it will sum all items
				if ($top_model)
					$data_itm = null;

				list($all_keys, $full_selector) = $element->toCSV(
								$selector, $stream, $data_itm, $all_keys, $full_selector, $cols_prefix, 
								false, $top_model, $is_top && $first, $data_pos + $pos);
				if ($is_top && $full_selector)
					$selector = $full_selector;
			}
			else
			{
				if ($is_top && $first)
					echo fputcsv($stream, ["Values"]);
				
				// is scalar
				$data[$data_pos + $pos][rtrim($cols_prefix, ".")] = $element;
				// then the value
				if ($is_top && $first)
					echo fputcsv($stream, [$element]);
			}
			
			if ($first)
				$first = false;
			
			$pos++;
		}
	}
	
	public function find($value, string $property = null, bool $index = false, bool $strict = true)
	{
		// _findex
		if ($index && (($v = $this->_findex[$property][$value]) !== null))
			return $v;
		foreach ($this as $v)
		{
			if ($property && $v && ($strict ? ($v->$property === $value) : ($v->$property == $value)))
			{
				if ($index)
					$this->_findex[$property][$value] = $v;
				return $v;
			}
		}
		
		return false;
	}
	
	public function in_array($value, bool $strict = true)
	{
		foreach ($this as $v)
		{
			if (($value === $v) || ((!$strict) && ($value == $v)))
				return true;
		}
		return false;
	}
	
	public function has($value, string $property = null, bool $index = false, bool $strict = true)
	{
		return ($this->find($value, $property, $index, $strict) !== false);
	}
	
	/* NOT GOOD !
	public function findElementBy_RowId_Or_Id(\QIModel $element, $rowid = null)
	{
		if ($rowid && ($k = $this->_iro[$rowid]))
		{
			$compare_with = $this[$k];
			if ($compare_with && ($element === $compare_with))
				return $compare_with;

			if (($compare_with instanceof $element) && ((string)$element->getId() === (string)$compare_with->getId()))
				return $compare_with;
		}
		else if (($k = $this->_byid[$element->getId()]))
		{
			foreach ($k as $at_index)
			{
				$compare_with = $this[$at_index];
				if (!$compare_with)
					throw new \Exception('Corrupted index QModelArray::_byid');

				if ($compare_with && ($element === $compare_with))
					return $compare_with;

				if (($compare_with instanceof $element) && ((string)$element->getId() === (string)$compare_with->getId()))
					return $compare_with;
			}
		}
		
		return false;
	}
	*/
	
	public function getIdsFromMergeBy($detected_type = null, $detected_merge_by = null, $max_query_len = 32768)
	{
		throw new \Exception('deprecated');
		
		$ids_setup = 0;
		
		$map_mergeby = [];
		$queries = [];
		$binds = [];
		$data_map = [];
		$bind_sizes = [];
		$merge_by_selector = [];
		
		if ($detected_type && $detected_merge_by)
		{
			$_mergeBy_parts = explode(",", $detected_merge_by);
			$mergeBy_parts = [];
			foreach ($_mergeBy_parts as $mbk => $_mby)
			{
				if (!empty($mby = trim($_mby)))
					$mergeBy_parts[] = $mby;
			}

			$property = \QApp::GetDefaultAppPropertyForTypeValues($detected_type);
			if (!$property)
			{
				qvardump($detected_type, $detected_merge_by, $_mergeBy_parts);
				throw new \Exception("Property not detected!");
			}
			$merge_by_selector[$property] = $detected_merge_by;
			$map_mergeby[$detected_type] = [$mergeBy_parts, $property];
			$bind_sizes[$property] = count($mergeBy_parts);
		}
		
		$binds_len = [];
		
		foreach ($this as $item)
		{
			if (($item instanceof \QIModel) && ($item->getId() === null))
			{
				$i_class = get_class($item);
				$mergeby_inf = $map_mergeby[$i_class];
				
				if (($mergeby_inf === null) && ($type_inf = \QModel::GetTypesCache($i_class)) && ($type_mergeBy = $type_inf["#%misc"]["mergeBy"]))
				{
					$_mergeBy_parts = explode(",", $type_mergeBy);
					$mergeBy_parts = [];
					foreach ($_mergeBy_parts as $mbk => $_mby)
					{
						if (!empty($mby = trim($_mby)))
							$mergeBy_parts[] = $mby;
					}

					$property = \QApp::GetDefaultAppPropertyForTypeValues($i_class);
					if (!$property)
					{
						qvardump($i_class, $mergeby_inf);
						throw new \Exception("Property not detected!");
					}

					$mergeby_inf = $map_mergeby[$i_class] = [$mergeBy_parts, $property];
					$bind_sizes[$property] = count($mergeBy_parts);
					$merge_by_selector[$property] = $type_mergeBy;
				}
				else if (!$mergeby_inf)
				{
					// make sure we don't repeat the info for the same type
					$map_mergeby[$i_class] = false;
					continue;
				}

				list($mergeBy_parts, $property) = $mergeby_inf;

				if ($binds[$property] === null)
				{
					$binds[$property] = [];
					$queries[$property] = [];
					$binds_len[$property] = 0;
				}
				
				$i_key = "";
				
				$sql_q = "(";
				$prepend_and = false;
				foreach ($mergeBy_parts as $mby)
				{
					$sql_q .= ($prepend_and ? " AND " : "").trim($mby)."=?";
					$b_parts = explode(".", $mby);
					$obj = $item;
					foreach ($b_parts as $bp)
						$obj = $obj->{$bp};
						
					if ($obj === null)
						$i_key .= ",null";
					else if ($obj instanceof \QIModel)
						$i_key .= ",".var_export([$obj->getId(), get_class($obj)], true);
					else if (is_scalar($obj))
						$i_key .= ",".var_export($obj, true);
					else 
						throw new \Exception("Unexpected data type");

					$binds[$property][] = $obj;
					$prepend_and = true;
				}
				$sql_q .= ")";
				
				// define item key
				$binds_len[$property] += strlen($i_key);
				
				$queries[$property][] = $sql_q;
				$data_map[sha1($i_key)] = $item;
			}
		}
		
		// now query
		foreach ($queries as $property => $q_sqls)
		{
			$q_binds = $binds[$property];
			$bind_size = $bind_sizes[$property];
			$extra_selector = $merge_by_selector[$property];
			
			// how do we determine $query_len ?
			$count_q_sqls = count($q_sqls);
			$estimated_len = (int)ceil((64 + $count_q_sqls * (strlen(reset($q_sqls)) + 5) + $binds_len[$property]) * 1.5);
			$blocks = (int)ceil($estimated_len / $max_query_len);
			$parts_per_query = (int)floor($count_q_sqls / $blocks);
			
			$index = 0;
			
			for ($i = 0; $i < $blocks; $i++)
			{
				$parts = array_slice($q_sqls, $index, $parts_per_query);
				$qpart_binds = array_slice($q_binds, $index * $bind_size, $parts_per_query * $bind_size);
				
				$sql = $property.".{Id,{$extra_selector} WHERE ".implode(" OR ", $parts)." LIMIT ".count($parts)."}";
				try
				{
					$res = QQuery($sql, $qpart_binds);
				}
				catch (\Exception $ex)
				{
					qvardump($queries, $property, $sql, $qpart_binds);
					throw $ex;
				}
				
				if ($res && $res->{$property})
				{
					foreach ($res->{$property} as $item)
					{
						list($mergeBy_parts,) = $map_mergeby[get_class($item)];
						$i_key = "";
						foreach ($mergeBy_parts as $mby)
						{
							$b_parts = explode(".", $mby);
							$obj = $item;
							foreach ($b_parts as $bp)
								$obj = $obj->{$bp};

							if ($obj === null)
								$i_key .= ",null";
							else if ($obj instanceof \QIModel)
								$i_key .= ",".var_export([$obj->getId(), get_class($obj)], true);
							else if (is_scalar($obj))
								$i_key .= ",".var_export($obj, true);
							else 
								throw new \Exception("Unexpected data type");
						}
						
						if (($i_item = $data_map[sha1($i_key)]))
						{
							$i_item->setId($item->getId());
							$ids_setup++;
						}
					}
				}

				$index += $parts_per_query;	
			}
		}
		
		return $ids_setup;
	}
	
	//  public void ArrayObject::append ( mixed $value )
	/* NOT GOOD !!!
	public function offsetSet($index, $newval)
	{
		$rv = parent::offsetSet($index, $newval);
		if (($newval instanceof \QIModel) && ($id = $newval->getId()))
		{
			if ($index === null)
			{
				// $cp = key($this);
				end($this);
				$index = key($this);
			}
			$this->_byid[$id][] = $index;
			$this->_byid_r[$index][] = $id;
		}
		return $rv;
	}
	
	public function offsetUnset($index)
	{
		if (($ids = $this->_byid_r[$index]) !== null)
		{
			unset($this->_byid_r[$index]);
			foreach ($ids as $id)
			{
				if (($ref = &$this->_byid[$id]))
				{
					foreach ($ref as $k => $v)
					{
						if ($v === $index)
						{
							unset($ref[$k]);
							if (empty($ref))
								unset($this->_byid[$id]);
							break;
						}
					}
				}
			}
		}
		
		if (($row_id = $this->_rowi[$index]) !== null)
		{
			unset($this->_rowi[$index]);
			unset($this->_iro[$row_id]);
		}
		
		return parent::offsetUnset($index);
	}
	 */
	
	######## QMODEL PATCH #############
	
	public $_singleSync;
	
	
	
	/**
	 * touch items
	 */
	public function touch()
	{
		foreach ($this as $value)
		{
			if ($value instanceof \QIModel)
				$value->touch();
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeImport($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;

		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->beforeImport($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterImport($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;

		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->afterImport($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeCommitTransaction($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;

		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->beforeCommitTransaction($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterBeginTransaction($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;

		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->afterBeginTransaction($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function beforeSave($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;
		
		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->beforeSave($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * @param type $selector
	 * @param type $transform_state
	 * @param type $_bag
	 * @return type
	 */
	public function afterSave($selector = null, $transform_state = null, &$_bag = null, $is_starting_point = true, $appProp = null)
	{
		if (!$selector)
			return;

		foreach ($this as $item)
		{
			if ($item instanceof QIModel)
				$item->afterSave($selector, $transform_state, $_bag, $is_starting_point, $appProp);
		}
	}
	/**
	 * Get Synced data for the collection
	 * 
	 * @param \QModelArray $data
	 * @param string|array|boolean $selector
	 * @param \QModelProperty $prop
	 * @param boolean $includeNonModelProps
	 * @param \QModelArray $syncItm
	 * @param QIModel $parent
	 * @param array $_bag
	 * @return \QModelArray
	 */
	public static function GetRemoteSyncedData($data, $selector = true, $prop = null, $includeNonModelProps = false, $syncItm = null, 
				$parent = null, \SplObjectStorage &$_bag = null, $entityLoaded = false, array &$_all_objects = null)
	{
		if ($_all_objects === null)
		{
			$_all_objects = [];
			\QModel::GetRemoteSyncedData_Populate_All_Objects($_all_objects, $syncItm);
		}
		if ($_bag === null)
			$_bag = new \SplObjectStorage();
		
		if (is_object($data) && isset($_bag[$data]))
			return $_bag[$data];
		
		$dcls = get_called_class();
		// if no sync item is provided then initialize with data class
		if (!$syncItm)
			$syncItm = new $dcls();

		$_bag[$data] = $syncItm;
		// we need the prop for some markups like:
		// 1. index the existing data based on collection items type (if we have scalar items then we will index them by value, if not we will index them by Gid)
		// 2. call the method to set the items in the new collection
		// 3. when mark for removal to know if we mark only on collection at index or if there is a need to mark the item from collection to

		// get the prop name and check if the property is scalar or is m2m
		$pName = $prop ? $prop->name : null;
		$_isScalar = $prop ? $prop->isScalar() : null;
		$_isM2m = $prop ? !$prop->isOneToMany() : false;

		// index the existing data so we can update existing linked items 
		// and remove the items that are not in data
		$_maxk = -1;
		$_existingData = [];
		foreach ($syncItm as $_k => $_v)
		{
			// if is not scalar and we don't have Gid set then it should be removed from collection
			$_existingData[$_isScalar ? $_v : ($_v->Gid ?: uniqid())] = [$_k, $_v];
			if (!$_k || ($_k > $_maxk))
				$_maxk = $_k;
		}

		//echo "<div style='color: blue'>".($prop ? $prop->name : "No property")."</div>";
		//qvardump($syncItm, $_existingData);

		// go through data items
		$processedData = [];

		$__retained_elements = [];

		foreach ($data as $item)
		{
			//$isoff = ($item instanceof \Omi\Comm\Offer);
			//if ($isoff)
			//	\QModel::$Dump = true;

			// the item index is the id if QModel, the item itself if is scalar
			$itmIndx = $_isScalar ? $item : $item->getId();

			// check to see if the item exists and its position
			list($indx, $_sitm) = isset($_existingData[$itmIndx]) ? $_existingData[$itmIndx] : [++$_maxk, null];

			// store the item as processed
			$processedData[$itmIndx] = $item;

			// get the synced item
			$_syncedItm = ($__sy_isqm = ($item instanceof \QIModel)) ? 
				$item::GetRemoteSyncedData($item, $selector, $prop, $includeNonModelProps, $_sitm, $syncItm, $_bag, $entityLoaded, $_all_objects) : 
				\QModel::GetRemoteSyncedData($item, $selector, $prop, $includeNonModelProps, $_sitm, $syncItm, $_bag, $entityLoaded, $_all_objects);

			// we need to make sure that for m2m we don't repeat the element
			if ($__sy_isqm && $_isM2m && !$_isScalar)
			{
				$__syitm_indx = ($_syncedItm->getId() ?: $_syncedItm->getTemporaryId());
				if ($__retained_elements[$__syitm_indx])
					continue;
				$__retained_elements[$__syitm_indx] = true;
			}

			// set the synced item at its position in the child data array
			($parent && $pName) ? $parent->{"set".$pName."_Item_"}($_syncedItm, $indx) : $syncItm->set($indx, $_syncedItm);
			
			//if ($isoff)
			//	die();
		}

		// if we have existing data 
		// go through it and remove the items that were not processed
		// if the collection is one to many then mark the collection item for removal to
		if ($_existingData && (count($_existingData) > 0))
		{
			foreach ($_existingData as $_indx => $_editm)
			{
				if (isset($processedData[$_indx]))
					continue;

				list($_sk, $_si) = $_editm;
					$syncItm->setTransformState(\QIModel::TransformDelete, $_sk);
				if (!$_isScalar && !$_isM2m)
					$_si->setTransformState(\QIModel::TransformDelete);
			}
		}

		return $syncItm;
	}
		/**
	 * Get Synced data for the collection
	 * 
	 * @param \QModelArray $data
	 * @param string|array|boolean $selector
	 * @param \QModelProperty $prop
	 * @param boolean $includeNonModelProps
	 * @param \QModelArray $syncItm
	 * @param QIModel $parent
	 * @param array $_bag
	 * @return \QModelArray
	 */
	public static function GetSystemSyncedData($data, $selector = true, $prop = null, $includeNonModelProps = false, $syncItm = null, $parent = null, &$_bag = null)
	{
		$dcls = get_called_class();
		// if no sync item is provided then initialize with data class
		if (!$syncItm)
			$syncItm = new $dcls();

		// we need the prop for some markups like:
		// 1. index the existing data based on collection items type (if we have scalar items then we will index them by value, if not we will index them by Gid)
		// 2. call the method to set the items in the new collection
		// 3. when mark for removal to know if we mark only on collection at index or if there is a need to mark the item from collection to

		// get the prop name and check if the property is scalar or is m2m
		$pName = $prop ? $prop->name : null;
		$_isScalar = $prop ? $prop->isScalar() : null;
		$_isM2m = $prop ? !$prop->isOneToMany() : false;

		// index the existing data so we can update existing linked items 
		// and remove the items that are not in data
		$_maxk = -1;
		$_existingData = [];
		foreach ($syncItm as $_k => $_v)
		{
			// if is not scalar and we don't have Gid set then it should be removed from collection
			$_existingData[$_isScalar ? $_v : $_v->getId() ? $_v->getId() : uniqid()] = [$_k, $_v];
			if (!$_k || ($_k > $_maxk))
				$_maxk = $_k;
		}

		// go through data items
		$processedData = [];

		$__retained_elements = [];
		foreach ($data as $item)
		{
			// the item index is the id if QModel, the item itself if is scalar
			$itmIndx = $_isScalar ? $item : ($item->Gid ?: $item->getTemporaryId());
			//qvardump("\$itmIndx", $itmIndx);

			// check to see if the item exists and its position
			list($indx, $_sitm) = isset($_existingData[$itmIndx]) ? $_existingData[$itmIndx] : [++$_maxk, null];

			// store the item as processed
			$processedData[$itmIndx] = $item;

			// get the synced item
			if ($_isScalar)
			{
				$_syncedItm = $item;
			}
			else
			{
				$_syncedItm = ($__sy_isqm = ($item instanceof \QIModel)) ? 
					$item::GetSystemSyncedData($item, $selector, $prop, $includeNonModelProps, $_sitm, $syncItm, $_bag) : 
					\QModel::GetSystemSyncedData($item, $selector, $prop, $includeNonModelProps, $_sitm, $syncItm, $_bag);
			}
			
			// we need to make sure that for m2m we don't repeat the element
			if ($__sy_isqm && $_isM2m && !$_isScalar)
			{
				$__syitm_indx = ($_syncedItm->getId() ?: $_syncedItm->getTemporaryId());
				if ($__retained_elements[$__syitm_indx])
					continue;
				$__retained_elements[$__syitm_indx] = true;
			}

			// set the synced item at its position in the child data array
			($parent && $pName) ? $parent->{"set".$pName."_Item_"}($_syncedItm, $indx) : $syncItm->set($indx, $_syncedItm);
		}

		// if we have existing data 
		// go through it and remove the items that were not processed
		// if the collection is one to many then mark the collection item for removal to
		if ($_existingData && (count($_existingData) > 0))
		{
			foreach ($_existingData as $_indx => $_editm)
			{
				if (isset($processedData[$_indx]))
					continue;

				list($_sk, $_si) = $_editm;
				$syncItm->setTransformState(\QIModel::TransformDelete, $_sk);
				if (!$_isScalar && !$_isM2m)
					$_si->setTransformState(\QIModel::TransformDelete);
			}
		}
		
		return $syncItm;
	}
	/**
	 * We need to setup reference data to modules
	 * Ex: We have a company that has an address linked to it and the address is linked to a country, a county and a city
	 * When we will send the data to be remotely merged, we need to send the country, county and the city on the app as well so they would be added to remote app
	 * 
	 * @param \Omi\App $appData
	 * @param \QModelArray $currentData
	 * @param array $_bag
	 * @param array $_byAppPropItems
	 * 
	 * @return null
	 */
	public static function SetupToSendData($appData, $currentData = null, &$_bag = null, &$_byAppPropItems = null, string $path = "", array &$new_app_items = null, bool $set_on_app = true)
	{
		if (!$currentData || (count($currentData) === 0))
			return;
		
		if ($new_app_items === null)
			$new_app_items = [];

		// just go thorugh all items and call the method on correct instance if QIModel, call on QModel otherwise
		foreach ($currentData as $item)
		{
			(!($item instanceof \QIModel)) ? 
				\QModel::SetupToSendData($appData, $item, $_bag, $_byAppPropItems, $path, $new_app_items, $set_on_app) : 
				$item::SetupToSendData($appData, $item, $_bag, $_byAppPropItems, $path, $new_app_items, $set_on_app);
		}
	}
	
	######## QMODEL PATCH END !! #############

	public function getClone($selector)
	{
		if (is_string($selector))
			$selector = qParseEntity($selector);
		if (!is_array($selector))
			return;
		
		$clone = new static;
		foreach ($this as $pos => $val)
		{
			if (is_scalar($val))
				$clone[$pos] = $val;
			else if ($val instanceof \QIModel)
				$clone[$pos] = $val->getClone($selector);
			else
				throw new \Exception('Unexpected data');
		}
			
		return $clone;
	}
	
	/**
	 * Transforms the object into a PHP array. 
	 * The selector is mandatory.
	 * 
	 * @return array
	 */
	public function exportToArray($selector, $with_type = false, $with_hidden_ids = false, $ignore_nulls = true)
	{
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		$arr = [];
		if ($with_type)
			$arr['_ty'] = get_class($this);
		
		foreach ($this as $k => $val)
		{
			$ty = gettype($val);
			switch ($ty)
			{
				case "string":
				case "array":
				case "integer":
				case "double":
				case "boolean":
				{
					$arr[$k] = $val;
					break;
				}
				case "NULL":
				{
					if (!$ignore_nulls)
						$arr[$k] = null;
					break;
				}
				case "object":
				{
					if ($val instanceof QIModel)
						$arr[$k] = $val->exportToArray($selector, $with_type, $with_hidden_ids, $ignore_nulls);
					else
						$arr[$k] = (array)$val;
					break;
				}
				default:
				{
					break;
				}
			}
		}
		
		return $arr;
	}
}
