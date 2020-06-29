<?php
/**
 * A PHP function is passed by its name as a string. 
 * A method of an instantiated object is passed as an array containing an object at index 0 and the method name at index 1. 
 * Static class methods can also be passed without instantiating an object of that class by passing the class name instead of an object at index 0. As of PHP 5.2.3, it is also possible to pass 'ClassName::methodName'.
 *
 */
class QModelTypeCallback extends QModelTypeScalar 
{
	/**
	 * The instance of the object
	 *
	 * @var QModelTypeCallback
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
	 * @return QModelTypeCallback
	 */
	public static function GetType()
	{
		return self::$type["callback"] ?: (self::$type["callback"] = new QModelTypeCallback());
	}
	/**
	 * Assigns a value to the passed variable. 
	 * If the function returns true(boolean) than it was a success.
	 *
	 * @param callback $target
	 * @param string|string[] $value
	 * 
	 * @return boolean
	 */
	public function assign(&$target, $value)
	{
		if (is_string($value) || (is_array($value) && (count($value) == 2) && is_string($value[0]) && is_string($value[1])))
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
	 * @param mixed $variable_1
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
		return (is_string($value) || (is_array($value) && (count($value) == 2) && is_string($value[0]) && is_string($value[1])));
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
		return is_array($variable) ? json_encode($variable) : (string)$variable;
	}
	
	// TYPE INFORMATION FROM HERE ON
	
	/**
	 * Gets the Id of the scalar
	 *
	 * @return integer
	 */
	public function getTypeId()
	{
		return QModelType::ModelTypeMixed;
	}
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		return "callback";
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
		return array("string", "array");
	}
	/**
	 * Gets the supported sql data types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedSqlDataTypes()
	{
		return array(QSqlTableColumn::TypeVarchar, 
						QSqlTableColumn::TypeText, QSqlTableColumn::TypeMediumText, QSqlTableColumn::TypeLongText, QSqlTableColumn::TypeChar);
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
