<?php

interface QIStorageFolder extends QIStorageEntry 
{
	/**
	 * Gets the child entries
	 *
	 * @return QIStorageEntry[]
	 */
	public function getChildEntries();
}
