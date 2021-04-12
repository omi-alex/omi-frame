<?php

/**
 * The basic class for views
 */
class QViewBase extends QModel 
{
	use QViewBase_GenTrait, QViewBase_Methods;
	
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeJs = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeCss = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeJsLast = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeCssLast = array();
	/**
	 * The list of JS classes that map to PHP view classes
	 *
	 * @var string[]
	 */
	protected static $ViewJsClasses;
	/**
	 * The parent view
	 *
	 * @var QViewBase
	 */
	public $parent;
	/**
	 * The list of child views
	 *
	 * @var QViewBase[]
	 */
	public $children;
	/**
	 * The default tag name of a QViewBase object
	 *
	 * @var string
	 */
	public $tagName = "div";
	/**
	 * The name of the control as it is in it's parent
	 *
	 * @var string
	 */
	public $name;
	/**
	 *
	 * The js ctrl to load
	 * 
	 * @var string 
	 */
	public $jsCtrl;
	/**
	 * @var boolean
	 */
	public $is_dynamic_ctrl;
	/**
	 * Only used if it's a dynamic
	 * @var string
	 */
	public $dynamic_name;

	public $_rf_args = [];
	
	/**
	 * Sets the parent element for this instance
	 * 
	 * @param QViewBase $parent
	 */
	public function setParent(QViewBase $parent)
	{
		$this->parent = $parent;
	}
}
