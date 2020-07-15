<?php
/**
 * @package bitwise
 * @subpackage sql
 *
 */

/**
 * Represents an QSqlNull abstract data
 * 
 * @package bitwise
 * @subpackage sql
 */
final class QSqlNull 
{
	/**
	 * The dafault static null
	 *
	 * @var QSqlNull
	 */
	protected static $default;
	
	/**
	 * The default constructor
	 *
	 */
	private function __construct()
	{
		
	}
	
	/**
	 * Gets the only instance of QSqlNull
	 *
	 * @return QSqlNull
	 */
	public static function Get()
	{
		return self::$default ?: (self::$default = new QSqlNull());
	}
	
	public function __toString()
	{
		return "<QSqlNull>";
	}

}
