<?php

/**
 * @todo This one is on the TO DO list it is not final
 */
interface QIModelArray extends QIModel, ArrayAccess, IteratorAggregate, Countable
{
	/**
	 * Gets the model property that this collection is under
	 * 
	 * @return QModelProperty
	 */
	public function getModelProperty();
	/**
	 * Sets the model property that this collection is under
	 * 
	 * @param string|QModelProperty $property
	 */
	public function setModelProperty($property = null);
	/**
	 * Gets the list of changed keys
	 * 
	 * @return string[]
	 */
	public function getChangedKeys();
	/**
	 * Gets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * 
	 * @return integer
	 */
	public function getRowIdAtIndex($key);
	/**
	 * Sets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * @param integer $row_id
	 * 
	 */
	public function setRowIdAtIndex(string $key, string $row_id);
}
