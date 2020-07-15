<?php

abstract class QStorageEntry extends QModel implements QIStorageEntry
{
	use QStorageEntry_GenTrait;
	/**
	 * The name of the entry
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The parent of the folder
	 *
	 * @var QStorageFolder
	 */
	public $parent;
	/**
	 *
	 * @var QIStorage
	 */
	public $storage;
	
	/**
	 * The constructor for a QStorageEntry
	 *
	 * @param string $name
	 * @param QStorageFolder $parent
	 */
	public function __construct($name = null, QStorageFolder $parent = null)
	{
		$this->name = $name;
		$this->parent = $parent;
	}
	/**
	 * Gets the name of the entry
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}
	/**
	 * Gets the name of the entry
	 *
	 * @return QStorageFolder
	 */
	public function getParent()
	{
		return $this->parent;
	}
	/**
	 * Gets the Storage
	 *
	 * @return QIStorage
	 */
	public function getStorage()
	{
		return ($this instanceof QIStorage) ? $this : $this->parent->getStorage();
	}
}
