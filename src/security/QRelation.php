<?php

/**
 * @todo
 * 
 * A user may represent a group
 * 
 * @storage.table $GroupRelations
 * @api.todo
 */
class QRelation extends QModel
{
	use QRelation_GenTrait;
	/**
	 * The name of the relation
	 * It identifies the relation
	 *
	 * @var string
	 */
	public $Name;
	/**
	 *
	 * @var QUserGroup
	 */
	public $Subject;
	/**
	 *
	 * @var QUserGroup[]
	 */
	public $Groups;
}
