<?php

class QModelTypeResource extends QModelTypeScalar 
{
	/**
	 * The instance of the object
	 *
	 * @var QModelTypeResource
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
	 * @return QModelTypeResource
	 */
	public static function GetType()
	{
		return self::$type["resource"] ?: (self::$type["resource"] = new QModelTypeResource());
	}
	/**
	 * Assigns a value to the passed variable. 
	 * If the function returns true(boolean) than it was a success.
	 *
	 * @param resource $target
	 * @param resource $value
	 * 
	 * @return boolean
	 */
	public function assign(&$target, $value)
	{
		if (is_resource($value))
		{
			$target = $value;
			return true;
		}
		else 
			return false;
	}
	/**
	 * Tests if the two values are the same
	 *
	 * @param resource $variable_1
	 * @param resource $variable_2
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
	 * @param resource $variable
	 * 
	 * @return boolean
	 */
	public function isCompatible($variable)
	{
		return is_resource($value);
	}
	/**
	 * In case of a pseudotype (or other cases) we may need to serialize the variable before pushing it to storage
	 * This method can be useful in a lot of cases. Optional parameters can be added for numeric, date types and so on
	 *
	 * @param mixed $variable
	 * 
	 * @return string
	 */
	public function toString($variable)
	{
		// we should force string based on it's type
		return "#resource#";
	}
	
	// TYPE INFORMATION FROM HERE ON
	
	/**
	 * Gets the Id of the scalar
	 *
	 * @return integer
	 */
	public function getTypeId()
	{
		return QModelType::ModelTypeResource;
	}
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		return "resource";
	}
	/**
	 * Gets the aliases of the type
	 *
	 * @return string[]
	 */
	public function getAliases()
	{
		return null;
	}
	/**
	 * Gets the supported php types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedPhpTypes()
	{
		return null;
	}
	/**
	 * Gets the supported sql data types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedSqlDataTypes()
	{
		return null;
	}
	/**
	 * Reports if this type is a pseudo type
	 *
	 * @return boolean
	 */
	public function isPseudoType()
	{
		return true;
	}
}
