<?php

/**
 * This is the basic interface for all classes participating in the Model structure
 * 
 * A MODEL object will set certain hidden properties, starting with the underscore "_" char.
 * DO NOT define or set properties on QIModel object that start with the underscore "_" char.
 * 
 */
interface QIModel 
{
	/**
	 * The object is in a unknown sync state
	 * @todo
	 */
	const SyncStateUnknown = 0;
	/**
	 * The object is in sync with the Storage
	 * This may only happen when a LOCK was acquired !
	 * @todo
	 */
	const SyncStateInSync = 1;
	
	/**
	 * Basic Transform options
	 */
	
	/**
	 * No action flag
	 */
	const TransformNoAction = 0;
	/**
	 * The create flag. Will force a INSERT like opertation.
	 */
	const TransformCreate = 1;
	/**
	 * The delete flag. Will force a DELETE like opertation.
	 */
	const TransformDelete = 2;
	/**
	 * This will request a collection to empty existing elements if they are not in the new list. 
	 * Must be set on the collection object / QModelArray.
	 */
	const TransformReplace    = 15; // 1024 + 13 (TransformDelete + TransformMerge)
	
	/**
	 * Append, Merge and Fix are all Update based
	 */
	
	/**
	 * The update flag. Will force a UPDATE like opertation.
	 */
	const TransformUpdate = 4;
	/**
	 * The update flag. Will force a MERGE like opertation or SELECT then INSERT/UPDATE if MERGE is not supported.
	 * 8 + 4 + 1 (4 = update, 1 = create, 8 = merge specific)
	 */
	const TransformMerge  = 13;
	/**
	 * @todo
	 * Not implemented atm
	 * The append flag. Can only be applied to properties that are a collection or to properties that are strings
	 * The concept is to allow only appending in collections and appending data to strings.
	 * 16 + 4 (4 = update, 16 = append specific)
	 */
	const TransformAppend = 20;
	/**
	 * @todo
	 * Not implemented atm
	 * The concept is that we apply a fix to a field. The difference from Update is that we will 
	 * not trigger any events (except data checks), also if there is any logging of changes it will 
	 * flag it as a Fix.
	 * 32 + 4 (4 = update, 32 = fix specific)
	 */
	const TransformFix    = 36;
	/**
	 * Read action
	 */
	const TransformRead    = 256;
	/**
	 * Can call a method
	 */
	const TransformExecute    = 512;
	
	/**
	 * When the object is not processed it's state should be TransformStateNormal
	 */
	const TransformStateNormal = 1;
	/**
	 * When the object is transformed/processed it's state should be TransformStateProcessing
	 */
	const TransformStateProcessing = 2;

	/**
	 * A MODEL will usualy have a Storage container as a DB object that duplicates the LIVE Model 
	 * in order to spped up things. In this case when we ask for a container, we should explain it's role
	 *
	 * If the QIModel::getContainers($use_for = StorageContainerForRead) then it will be used for READ operations
	 */
	const StorageContainerForRead = 1;
	/**
	 * If the QIModel::getContainers($use_for = StorageContainerForWrite) then it will be used for WRITE operations
	 */
	const StorageContainerForWrite = 2;
	
	/**
	 * HIDDEN PROPERTIES
	 *  
	 * A few things any MODEL should have
	 * 
	 * $_ty The type of the model
	 * $_sc The storage container (must be stored with a role)
	 * $_sci The storage container identifier (new way)
	 * $_id The id of the object (integer/string)
	 * 
	 * $_ts  This is the transform state of the object : No Action, Create / Update / ... + Custom
	 * # // NO LONGER USED $_tsp The transform state of the properties, key-value array at the moment
	 * 
	 * $_ols The old, changed values
	 * 
	 * $_lk True if the object is locked
	 */
	
	/**
	 * The init function
	 * 
	 * @param boolean $recursive
	 */
	public function init($recursive = false);
	
	/**
	 * Gets the model type
	 * 
	 * @return QModelType
	 */
	public function getModelType();
	/**
	 * Gets the [default] storage container that this model should be in
	 * In case his information is spread over multiple places it will return them all
	 * The role of the container is specified by $use_for
	 *
	 * @param integer $use_for
	 * 
	 * @return QIStorageContainer|QIStorageContainer[]
	 */
	public function getContainers($use_for = null);

	/**
	 * Sets a container or more
	 * 
	 * @param QIStorage|QIStorage[] $container
	 * @param integer $use_for
	 */
	public function setContainers($container, $use_for = null);
	
	/**
	 * Gets the identifier of the object
	 *
	 * @return integer|string
	 */
	public function getId();
	/**
	 * Sets the identifier of the object
	 * 
	 * @param integer|string $id
	 */
	public function setId($id);
	/**
	 * Gets the transform state of the object 
	 * 
	 * @return integer|string 
	 */
	public function getTransformState();
	/**
	 * Sets the transform state of the object 
	 * 
	 * @param integer|string $transf_state 
	 */
	public function setTransformState($transf_state);
	/**
	 * Gets the lock state of this object
	 * 
	 * @return bool|integer
	 */
	public function getLockState();
	/**
	 * Sets the lock state of this object
	 * 
	 * @param bool|integer $lock_state
	 */
	public function setLockState($lock_state);
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
	public function get($property = null, $query_if_unset = null, $requester = null, $reason = null);
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
	public function set($property, $value = null, $transform_with = null, $requester = null, $reason = null);
	/**
	 * Queries the object to get more data
	 * For QIModelArray a new instance will be created and returned
	 * 
	 * @param string|array $fields
	 * @param string|array $where
	 * @param string|array $group_by
	 * @param string|array $order_by
	 * @param integer $limit_count
	 * @param integer $limit_offset
	 * 
	 * @return QIModel
	 */
	// public function query($fields, $where = null, $group_by = null, $order_by = null, $limit_count = null, $limit_offset = null);
	
	/**
	 * Checks if a property was set
	 * 
	 * @param string $property
	 * 
	 * @return bool 
	 */
	public function wasSet($property);
	
	/**
	 * Tests if a property was changed
	 * If no property is specified tests if any property was changed
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function wasChanged($property = null);
	
	/**
	 * Checks if a property has the old value saved
	 * 
	 * @param string $property
	 * @return boolean
	 */
	public function hasOldValue($property);
	
	/**
	 * Gets the old/initial value of a property
	 * 
	 * @param string $property
	 * @return mixed
	 */
	public function getOldValue($property);
	
	/**
	 * Gets the list of changed properties
	 * 
	 * @return string[]
	 */
	public function getChangedProperties();
	
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
	public function transform($parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false, &$issues = null, &$root_issues = null);
	
	/**
	 * This method should be called before a model transform 'Per container'
	 *
	 * @param QIStorageContainer $container
	 * @param mixded $params
	 */
	public function beforeTransformModelByContainer(QIStorageContainer $container, $params = null);
	/**
	 * Run a MERGE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function merge($selector = false);
	/**
	 * Run a INSERT on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function insert($selector = false);
	/**
	 * Run a UPDATE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function update($selector = false);
	/**
	 * Run a DELETE on a MODEL
	 *
	 * @param string|boolean|null $selector
	 */
	public function delete($selector = false);
	
	/**
	 * Gets the model defaults foreach property
	 * 
	 * @return QIModel[]
	 */
	public static function getModelDefaults();
	/**
	 * Loads the model defaults foreach property
	 * 
	 */
	public function loadModelDefaults();
	
	/**
	 * Cleans up the transform/transaction locks setup on the object
	 */
	public function cleanupLocks();
	
	/**
	 * @todo
	 * 
	 * @param QUser $User
	 * @param type $req_rights
	 * @param type $property
	 * @param type $extra_entities
	 */
	public function SecurityFilter(QUser $User, $req_rights, $property = null, &$extra_entities = null);
	
	/**
	 * @todo This should be changed
	 * 
	 * Called when an upload was made and you need to do something with the file
	 * As an option the file could be handled on upload
	 * 
	 * @param string $property
	 * @param string[] $upload_info
	 */
	public function handleUpload($property, $upload_info);
}

