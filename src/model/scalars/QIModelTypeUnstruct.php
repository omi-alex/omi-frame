<?php

/**
 * INTEGER, INT, SMALLINT, TINYINT, MEDIUMINT, BIGINT

DECIMAL, NUMERIC

FLOAT, DOUBLE

BIT


DATE, DATETIME, TIMESTAMP 
TIME
YEAR 

CHAR and VARCHAR Types

BINARY and VARBINARY Types
BLOB and TEXT Types
ENUM Type
SET Type

TO DO:

ModelTypeMixed
ModelTypeCallback
ModelTypeScalar
ModelTypeScalarOrArray

ModelTypeDate
ModelTypeDateTime
ModelTypeTime
ModelTypeTimestamp

ENUM + SET + FILE


ALSO Enum & SET !!! ?!!?

 *
 */

/**
 * The interface for unstructured types definitions
 * A unstructured type can be: scalar, resource, array, object/stdClass
 * These types of objects are stored by conversion to string
 */
interface QIModelTypeUnstruct 
{
	/**
	 * Gets the instance of the type
	 * 
	 * @return QIModelTypeUnstruct
	 */
	public static function GetType();
	/**
	 * Assigns a value to the passed variable. 
	 * If the function returns true(boolean) than it was a success.
	 *
	 * @param mixed $target
	 * @param mixed $value
	 * 
	 * @return boolean
	 */
	public function assign(&$target, $value);
	/**
	 * Tests if the two values are the same
	 *
	 * @param integer $variable_1
	 * @param mixed $variable_2
	 * 
	 * @return boolean
	 */
	public function equals($variable_1, $variable_2);
	/**
	 * Checks if the variable can be converted (is valid) to this scalar type
	 *
	 * @param mixed $variable
	 * 
	 * @return boolean
	 */
	public function isCompatible($variable);
	/**
	 * In case of a pseudotype (or other cases) we may need to serialize the variable before pushing it to storage
	 * This method can be useful in a lot of cases. Optional parameters can be added for numeric, date types and so on
	 *
	 * @param mixed $variable
	 * 
	 * @return string
	 */
	public function toString($variable);
	
	// TYPE INFORMATION FROM HERE ON
	
	/**
	 * Gets the Id of the scalar
	 *
	 * @return integer
	 */
	public function getTypeId();
	/**
	 * Gets the name of the data type
	 *
	 * @return string
	 */
	public function getTypeName();
	/**
	 * Gets the aliases of the type
	 *
	 * @return string[]
	 */
	public function getAliases();
	/**
	 * Gets the supported php types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedPhpTypes();
	/**
	 * Gets the supported sql data types where the scalar can be stored in order of preferences
	 *
	 * @return integer[]
	 */
	public function getSupportedSqlDataTypes();
	/**
	 * Reports if this type is a pseudo type
	 *
	 * @return boolean
	 */
	public function isPseudoType();
	/**
	 * Must always return false for scalars, even for array like
	 * 
	 * @return boolean
	 */
	public function isCollection();
}
