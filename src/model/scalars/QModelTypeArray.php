<?php

class QModelTypeArray extends QModelTypeScalar
{
	/**
	 * The instance of the object
	 *
	 * @var ModelTypeArray
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
	 * @return QModelTypeArray
	 */
	public static function GetType()
	{
		return self::$type["array"] ?: (self::$type["array"] = new QModelTypeArray());
	}
	/**
	 * Assigns a value to the passed variable. 
	 * If the function returns true(boolean) than it was a success.
	 *
	 * @param array $target
	 * @param mixed $value
	 * 
	 * @return boolean
	 */
	public function assign(&$target, $value)
	{
		if (is_array($value))
		{
			$target = $value;
			return true;
		}
		else if (is_string($value))
		{
			$tmp = (strlen($value) > 0) ? json_decode($value, true) : false;
			if ($tmp)
			{
				$target = $tmp;
				return true;
			}
			else 
				return false;
		}
		else 
			return false;
			
	}
	/**
	 * Tests if the two values are the same
	 *
	 * @param array $variable_1
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
		return is_array($variable);
	}
	/**
	 * In case of a pseudotype (or other cases) we may need to serialize the variable before pushing it to storage
	 * This method can be useful in a lot of cases. Optional parameters can be added for numeric, date types and so on
	 *
	 * @param array $variable
	 * 
	 * @return string
	 */
	public function toString($variable)
	{
		return json_encode($variable);
	}
	
	// TYPE INFORMATION FROM HERE ON
	
	/**
	 * Gets the Id of the scalar
	 *
	 * @return integer
	 */
	public function getTypeId()
	{
		return QModelType::ModelTypeArray;
	}
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		return "array";
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
		return array("array");
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
		return false;
	}
}
