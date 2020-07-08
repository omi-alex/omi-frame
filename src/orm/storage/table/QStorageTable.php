<?php

abstract class QStorageTable extends QStorageEntry implements QIStorageContainer 
{
	use QStorageTable_GenTrait;
	// a storage table is a table with headings (columns) and data inside it
	// later on this can be used to interact with CSV(s),XLS and more table like structures
	// we may also consider XML like models in the future
	
	/**
	 * The list of columns
	 *
	 * @var QStorageTableColumn[]
	 */
	public $columns;
	/**
	 * The list of references
	 *
	 * @var QStorageTableReference[]
	 */
	public $references;
}
