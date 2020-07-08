<?php

abstract class QStorageTableColumn extends QModel
{
	use QStorageTableColumn_GenTrait;
	/**
	 * The name of the column
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The parent table
	 *
	 * @var QStorageTable
	 */
	public $table;
}
