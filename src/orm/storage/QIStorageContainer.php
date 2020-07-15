<?php

interface QIStorageContainer extends QIStorageEntry 
{
	/**
	 * Gets a model by id and type
	 *
	 * @param integer|string $model_id
	 * @param QIModel $instance
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 * 
	 * @return QIModel
	 */
	public function storageGetModelById($model_id, QIModel $instance = null, $model_type = null);
	/**
	 * QIModel::sync will call QIModel::syncModel
	 * QIModel::syncModel will/may call $container::syncModel
	 * The action is one of the QIModel::ModelState* but custom actions may also be defined
	 * When we recurse a $backtrace will also be created
	 * 
	 * @param QIModel $model
	 * @param integer|string $action
	 * @param mixed $parameters
	 * @param QBacktrace $backtrace
	 * @param boolean $as_simulation
	 */
	public function storageSyncModel(QIModel $model, $action, $parameters, QBacktrace $backtrace = null, $as_simulation = false);
}

