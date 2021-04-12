<?php

/**
 * QWebControl
 *
 * @author Alex
 */
class QWebControl extends QViewBase
{
	use QWebControl_GenTrait, QWebControl_Methods;
	
	/**
	 * Specify the method to be redered
	 *
	 * @var string
	 */
	public $renderMethod = "render";
	/**
	 * True if the control should be rerendered
	 *
	 * @var boolean
	 */
	public $changed;

}
