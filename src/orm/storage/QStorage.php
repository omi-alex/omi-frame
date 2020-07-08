<?php

/**
 * A storage holds collections of objects (QIModel)
 * The storage must know how to get information based on filters/queries
 * The storage must knkw how to create containers that will hold the information
 * 
 *
 */
abstract class QStorage extends QStorageEntry implements QIStorage 
{
	/**
	 * The key for the default tag
	 *
	 */
	const DefaultTag = "default";
	/**
	 * The constructor
	 *
	 * @param string $name
	 */
	public function __construct($name = null, $set_storage = true)
	{
		$this->name = $name;
	}

	/**
	 * Gets the parent
	 *
	 * @return null
	 */
	public function getParent()
	{
		return null;
	}
	/**
	 * Gets the Storage
	 *
	 * @return QStorage
	 */
	public function getStorage()
	{
		return $this;
	}
}
