<?php

interface QIStorageEntry 
{
	/**
	 * Gets the name of the entry
	 *
	 * @return string
	 */
	public function getName();
	/**
	 * Gets the parent
	 *
	 * @return QIStorageFolder
	 */
	public function getParent();
	/**
	 * Gets the Storage
	 *
	 * @return QIStorage
	 */
	public function getStorage();
}
