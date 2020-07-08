<?php

class QModelTypeFloat extends QModelTypeScalar 
{
	/**
	 * The instance of the object
	 *
	 * @var QModelTypeFloat
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
	 * @return QModelTypeFloat
	 */
	public static function GetType()
	{
		return self::$type["float"] ?: (self::$type["float"] = new QModelTypeFloat());
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
		if (is_numeric($value))
		{
			$target = (float)$value;
			return true;
		}
		else 
			return false;
			
	}
	/**
	 * Tests if the two values are the same
	 *
	 * @param integer $variable_1
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
		return is_numeric($variable);
	}
	/**
	 * In case of a pseudotype (or other cases) we may need to serialize the variable before pushing it to storage
	 * This method can be useful in a lot of cases. Optional parameters can be added for numeric, date types and so on
	 *
	 * @param float $variable
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
		return QModelType::ModelTypeFloat;
	}
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		return "float";
	}
	/**
	 * Gets the aliases of the type
	 *
	 * @return string[]
	 */
	public function getAliases()
	{
		return array("double");
	}
	/**
	 * Gets the supported php types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedPhpTypes()
	{
		return array("float", "string");
	}
	/**
	 * Gets the supported sql data types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedSqlDataTypes()
	{
		return array(QSqlTableColumn::TypeFloat, QSqlTableColumn::TypeDouble, QSqlTableColumn::TypeDecimal, 
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
