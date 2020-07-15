<?php

/**
 * Object representing a method within a class/type
 */
final class QModelMethod 
{
	/**
	 * The name of the method
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The parent type/class
	 *
	 * @var QModelType
	 */
	public $parent;
	/**
	 * The list of arguments accepted by this method
	 *
	 * @var QModelParameter[]
	 */
	public $parameters;
	/**
	 * The return type
	 *
	 * @var QModelType|QIModelTypeUnstruct|(QModelType|QIModelTypeUnstruct)[]
	 */
	public $return_type;
	/**
	 *
	 * @var boolean 
	 */
	public $static;
	/**
	 * The comments of the method
	 *
	 * @var string
	 */
	public $comments;
	/**
	 * The class, interface or trait where it was declared
	 *
	 * @var string
	 */
	public $in;
	
	/**
	 * The body of the method as PHP code
	 *
	 * @var string
	 */
	// public $body;

	/**
	 * Flags that no export should be don for this instance
	 *
	 * @var boolean
	 */
	public $no_export = true;
}
