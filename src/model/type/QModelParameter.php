<?php

/**
 * Object representing a parameter within a method
 */
final class QModelParameter 
{
	/**
	 * The name of the parameter
	 *
	 * @var string
	 */
	public $name;
	/**
	 * Gets the type accepted by the parameter
	 *
	 * @var QModelType|QIModelTypeUnstruct
	 */
	public $type;
	/**
	 * The parent method
	 *
	 * @var QModelMethod
	 */
	public $parent;
	/**
	 * The default value if any of the parameter
	 *
	 * @var scalarorarray
	 */
	public $default_value;
	/**
	 * In case the default value is null ($x = null), we need to make a difference
	 *
	 * @var boolean
	 */
	public $default_is_null;
	/**
	 * True if the value is passed by reference
	 *
	 * @var boolean
	 */
	public $passed_by_reference;
	/**
	 * Flags that no export should be don for this instance
	 *
	 * @var boolean
	 */
	public $no_export = true;
}
