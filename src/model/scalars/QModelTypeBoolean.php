<?php

class QModelTypeBoolean extends QModelTypeScalar 
{
	/**
	 * The instance of the object
	 *
	 * @var QModelTypeBoolean
	 */
	private static $type;
	
	/**
	 * The protected constructor
	 *
	 */
	protected function __construct()
	{
		
	}
	
	/**
	 * Gets the instance of the type
	 * 
	 * @return QModelTypeBoolean
	 */
	public static function GetType()
	{
		return self::$type["boolean"] ?: (self::$type["boolean"] = new QModelTypeBoolean());
	}
	/**
	 * Assigns a value to the passed variable. 
	 * If the function returns true(boolean) than it was a success.
	 *
	 * @param boolean $target
	 * @param mixed $value
	 * 
	 * @return boolean
	 */
	public function assign(&$target, $value)
	{
		$target = ($value == true);
		return true;
	}
	/**
	 * Tests if the two values are the same
	 *
	 * @param boolean $variable_1
	 * @param mixed $variable_2
	 * 
	 * @return boolean
	 */
	public function equals($variable_1, $variable_2)
	{
		return $variable_1 == $variable_2;
	}
	/**
	 * Checks if the variable can be converted (is valid) to this scalar type
	 *
	 * @param mixed $variable
	 * 
	 * @return boolean
	 */
	public function isCompatible($variable)
	{
		return true;
	}
	/**
	 * In case of a pseudotype (or other cases) we may need to serialize the variable before pushing it to storage
	 * This method can be useful in a lot of cases. Optional parameters can be added for numeric, date types and so on
	 *
	 * @param boolean $variable
	 * 
	 * @return string
	 */
	public function toString($variable)
	{
		return (string)$variable;
	}
	
	// TYPE INFORMATION FROM HERE ON
	
	/**
	 * Gets the Id of the scalar
	 *
	 * @return integer
	 */
	public function getTypeId()
	{
		return QModelType::ModelTypeBoolean;
	}
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		return "boolean";
	}
	/**
	 * Gets the aliases of the type
	 *
	 * @return string[]
	 */
	public function getAliases()
	{
		return array("bool");
	}
	/**
	 * Gets the supported php types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedPhpTypes()
	{
		return array("boolean", "integer", "float", "string");
	}
	/**
	 * Gets the supported sql data types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedSqlDataTypes()
	{
		return array(QSqlTableColumn::TypeBool, QSqlTableColumn::TypeBit, QSqlTableColumn::TypeTinyint, QSqlTableColumn::TypeSmallint, 
						QSqlTableColumn::TypeInt, QSqlTableColumn::TypeBigint, QSqlTableColumn::TypeDecimal, QSqlTableColumn::TypeFloat, QSqlTableColumn::TypeDouble, 
						QSqlTableColumn::TypeVarchar, QSqlTableColumn::TypeChar);
	}
	/**
	 * Reports if this type is a pseudo type
	 *
	 * @return boolean
	 */
	public function isPseudoType()
	{
		return false;
	}
}
