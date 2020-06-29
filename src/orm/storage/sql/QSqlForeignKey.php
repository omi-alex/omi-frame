<?php

/*

	[CONSTRAINT [symbol]] FOREIGN KEY
	    [index_name] (index_col_name, ...)
	    REFERENCES tbl_name (index_col_name,...)
	    [ON DELETE reference_option]
	    [ON UPDATE reference_option]
	
	reference_option:
	    RESTRICT | CASCADE | SET NULL | NO ACTION
	
	FOREIGN KEY (parent_id) REFERENCES parent(id) ON DELETE CASCADE
	
	
	id_index
	columns in index
	target table
	target columns 
	
	
	If the CONSTRAINT symbol clause is given, the symbol value must be unique in the database. If the clause is not given, InnoDB creates the name automatically. 
	
*/

class QSqlForeignKey extends QStorageTableReference
{
	use QSqlForeignKey_GenTrait;
	/**
	 * The index used by this foreign key
	 *
	 * @var QSqlTableIndex
	 */
	public $index;
	/**
	 * The index referenced by this foreign key
	 *
	 * @var QSqlTableIndex
	 */
	public $reference_index;
}
