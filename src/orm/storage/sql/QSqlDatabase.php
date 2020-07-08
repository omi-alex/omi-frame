<?php

class QSqlDatabase extends QStorageFolder 
{
	use QSqlDatabase_GenTrait;
	/**
	 * The collation of the database
	 *
	 * @var string
	 */
	public $collation;
	/**
	 * The charset of the database
	 *
	 * @var string
	 */
	public $charset;
	
	// TO DO : there is more here
}
