<?php

class QSqlTableIndex extends QModel 
{
	use QSqlTableIndex_GenTrait;
	/**
	 * The Primary Key index type
	 *
	 */
	const IndexPrimary = 1;
	/**
	 * The unique index type
	 *
	 */
	const IndexUnique = 2;
	/**
	 * Simple index type
	 *
	 */
	const IndexNormal = 3;
	/**
	 * Full text index type
	 *
	 */
	const IndexFullText = 4;
	/**
	 * The name of the index
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The type of the index as defined by the QSqlTableIndex constants
	 *
	 * @var integer
	 */
	public $type;
	/**
	 * The list of columns that compose the index
	 *
	 * @var QSqlTableColumn[]
	 */
	public $columns;
	/**
	 * The table where the index exists
	 *
	 * @var QSqlTable
	 */
	public $table;

	/**
	 * The constructor of QSqlTableIndex
	 *
	 * @param QSqlTable $table The table where the index exists
	 * @param integer $type The type of the index as defined by the QSqlTableIndex constants
	 * @param string $name The name of the index
	 * @param QSqlTableColumn[] $fields The list of fields that compose the index
	 * @param QSqlTableIndex $foreign_key
	 */
	public function __construct($table = null, $type = null, $name = null, $columns = null)
	{
		$this->table = $table;
		$this->type = $type;
		$this->name = $name;
		if (is_array($columns))
		{
			$this->columns = new QModelArray();
			foreach ($columns as $c)
				$this->columns[$c->name] = $c;
		}
		else if ($columns)
		{
			$this->columns = new QModelArray();
			foreach ($columns as $c)
				$this->columns[$c->name] = $c;
		}
		$this->foreign_key = $foreign_key;
	}
}
