<?php

/**
 * QSqlTransaction
 *
 * @author Alex
 */
class QSqlTransaction
{
	/**
	 *
	 * @var QSqlTransaction
	 */
	public $parent;
	/**
	 *
	 * @var string
	 */
	public $save_point;
	/**
	 * @var boolean
	 */
	public $exec_save_point;
}
