<?php

interface QIStorage extends QIStorageEntry
{
	/**
	 * Gets the storage containers that contain a certain type
	 * In some cases a model may be contained in more than one container (to have the full information), or different instances can be in more than one container
	 *
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 * 
	 * @return QIStorageContainer[]
	 */
	public function getStorageContainersForType($model_type);
	/**
	 * Gets the default storage container for the specified type
	 *
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 */
	public function getDefaultStorageContainerForType($model_type);
	/**
	 * Queries the storage to get the needed data
	 *
	 * @param QModelQuery $query
	 * @param QIModel[] $instances Must be indexed by ID (UID should be forced somehow, if ot available use: '$id/$type')
	 */
	public function queryStorage(QModelQuery $query, $instances = null);
	/**
	 * Ask the storage to create storage container(s) for the specified data types
	 *
	 * @param QModelTypeSqlInfo[] $model_info
	 * @param (string|QModelType)[] $data_types
	 * @param boolean $as_default
	 * @param QIStorageFolder $parent
	 * @param QIStorageContainer $containers
	 * @param string $prefix
	 * @param string $sufix
	 * 
	 * @return QIStorageContainer[]
	 */
	public function syncStorageContainersForDataTypes($model_info = null, $data_types = null, $as_default = false, QIStorageFolder $parent = null, $containers = null, $prefix = "", $sufix = "");
	// ($data_types, $actions = null
	// public function sync($action = null, $parameters = null, $containers = null, $recurse = true, QBacktrace $backtrace = null, $as_simulation = false);
	// how about sync on a collection ... because this is what is about ... 
	/**
	 * 
	 * @param string $storage_model
	 * @param string $from
	 * @param string $selector
	 * @param array $parameters
	 * 
	 * @return QIModel
	 */
	public static function ApiQuery($storage_model, $from, $from_type, $selector = null, $parameters = null, $only_first = false, $id = null, $skip_security = true, $sql_filter = null);
	/**
	 * 
	 * @param QIModel $data
	 * @param integer $state
	 * @param string $from
	 * @return mixed
	 * @throws Exception
	 */
	public static function ApiSave($storage_model, $from, $from_type, $data, $state = null, $selector = null, $initialDestination = null);

}
