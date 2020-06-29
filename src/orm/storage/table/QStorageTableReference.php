<?php

class QStorageTableReference extends QModel  
{
	use QStorageTableReference_GenTrait;
	/**
	 * Rejects the delete or update operation for the parent table.
	 *
	 */
	const OnActionOptionRESTRICT = 1;
	/**
	 * Delete or update the row from the parent table and automatically delete or update the matching rows in the child table. 
	 *
	 */
	const OnActionOptionCASCADE = 2;
	/**
	 * Delete or update the row from the parent table and set the foreign key column or columns in the child table to NULL.
	 * In case the column(s) are 'NOT NULL' zero should be set, or empty string
	 *
	 */
	const OnActionOptionSET_NULL = 3;
	/**
	 * In standard SQL, NO ACTION means no action in the sense that an attempt to delete or update a primary key value is not 
	 * permitted to proceed if there is a related foreign key value in the referenced table. 
	 * InnoDB rejects the delete or update operation for the parent table. 
	 *
	 */
	const OnActionOptionNO_ACTION = 4;

	/**
	 * The name of the reference
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The column(s) that points to
	 *
	 * @var QStorageTableColumn[]
	 */
	public $columns;
	/**
	 * The column(s) that is(are) referenced
	 *
	 * @var QStorageTableColumn[]
	 */
	public $references;
	/**
	 * The parent table
	 *
	 * @var QStorageTable
	 */
	public $parent;
	/**
	 * The action taken on delete
	 *
	 * @var integer
	 */
	public $on_delete;
	/**
	 * The action taken on update
	 *
	 * @var integer
	 */
	public $on_update;
}
