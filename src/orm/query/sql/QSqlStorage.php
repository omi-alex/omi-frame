<?php

/*
what do we do with a property that has as type an interface ?
also a type may be a class that has supper classes 

-> we have a list of classes that could be used instead of a pointer :(
we can control this with storage rules

Options:

	0. First of all we should determine if we have more that one that is implemented (not abstract not interface)
	   Second, do we have any instance of the ones that are different
	   Third: default to something, query by type and fix the order when you put them back

	1. We can use a common table for all the classes that extend a certain class
	2. Each in it's own table -> 
			complicated query (only useful to pick them one by one)
			in this case we can't apply a FK
			
	3. In the end the main issue is that they may never need to be connected crossed, this means that 
	   for example, if we have 2 storage engines, the data is never mixed, we may have 2 types of tables (ex: SQL and CSV)
	   but the data is not crossed and it's different and in different boxes
	   
	so beside the "DATA TYPE" we may also specify that a certain TYPE will only be fonund in a certain TABLE 
	this should be specified in the UPPER CLASS, 
	
	EVEN BETTER the upper class may limit the type of a certain entry !!!
	
*/

abstract class QSqlStorage extends QFolderStorage 
{
	use QSqlStorage_GenTrait;
	/**
	 * The hostname
	 *
	 * @var string
	 */
	public $host;
	/**
	 * The username
	 *
	 * @var string
	 */
	public $user;
	/**
	 * The password
	 *
	 * @var string
	 */
	public $pass;
	/**
	 * The port
	 *
	 * @var string
	 */
	public $port;
	/**
	 * The socket
	 *
	 * @var string
	 */
	public $socket;
	/**
	 * The name of the default database
	 *
	 * @var string
	 */
	public $default_db;
	/**
	 * The connection resource object
	 *
	 * @var mysqli
	 */
	public $connection;
	/**
	 * The name of the default database
	 *
	 * @var QSqlDatabase
	 */
	public $default_database;
	/**
	 * The model type information
	 *
	 * @var QModelTypeSqlInfo[]
	 */
	public $_qmodeltypeinfo_;
	
	/**
	 * The default SQL storage constructor
	 *
	 * @param string $name
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $default_db
	 * @param integer $port
	 */
	public function __construct($name, $host, $user, $pass, $default_db, $port, $socket)
	{
		parent::__construct($name);
		
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->port = $port;
		$this->socket = $socket;
		$this->default_db = $default_db;
	}
	/**
	 * Gets the default database as an object
	 *
	 * @return QSqlDatabase
	 */
	public abstract function getDefaultDatabase();
	/**
	 * Gets the QModelTypeSqlInfo object for the specified type, or all the QModelTypeSqlInfo objects if no type was specified
	 *
	 * @param string $type
	 * 
	 * @return QModelTypeSqlInfo[]
	 */
	public function getModelTypeInfo($type = null)
	{
		if (!$this->_qmodeltypeinfo_)
		{
			// we most query for here ! TO DO
			$this->_qmodeltypeinfo_ = new QModelArray();
		}
		return $this->_qmodeltypeinfo_;
	}

	/**
	 * Gets the type id in the storage
	 * 
	 * @param string $type_name
	 * 
	 * @return integer
	 */
	abstract public function getTypeIdInStorage($type_name);
	
	abstract public function begin($nest_transaction = false);
	abstract public function commit();
	abstract public function rollback();
}
