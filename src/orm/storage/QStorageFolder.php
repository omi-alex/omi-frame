<?php

abstract class QStorageFolder extends QStorageEntry implements QIStorageFolder 
{
	use QStorageFolder_GenTrait;
	/**
	 * The children list
	 *
	 * @var QIStorageEntry[]
	 */
	public $children;
	/**
	 * Gets the child entries
	 *
	 * @return QIStorageEntry[]
	 */
	public function getChildEntries()
	{
		return $this->children;
	}
}
