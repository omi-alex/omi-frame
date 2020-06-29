<?php

/**
 * A user may represent a group
 * 
 * @todo
 * 
 * @storage.table $UserGroups
 *
 * @api.todo
 */
class QUserGroup extends QModel   
{
	use QUserGroup_GenTrait;

	/**
	 * The name of the group
	 *
	 * @var string
	 */
	protected $Name;
	/**
	 * The list of users in the group
	 * 
	 * @storage.collection $UsersGroupsList,Group,User
	 *
	 * @var QUser[]
	 */
	protected $Users;
	/**
	 * If this group represents a user it will have a user link to it
	 * In this case Users should be empty
	 *
	 * @var QUser
	 */
	protected $SelfUser;
	/**
	 * @var QRelation[]
	 */
	protected $Relations;
	/**
	 * Beeing a member of this group also makes you a member of it's subgroups
	 *
	 * @var QUserGroup[]
	 */
	protected $Groups;
	
	/**
	 * @todo
	 * 
	 * @param type $group
	 * @return int
	 */
	public static function GetGroupId($group)
	{
		// TO DO 
		return 1;
	}
	
	/**
	 * @todo
	 * 
	 * @param int $group
	 * @param type $relation
	 * @return int
	 */
	public static function GetRelationsFor($group, $relation)
	{
		// TO DO 
		if (is_array($group))
		{
			$group[] = 99;
			return $group;
		}
		else
		{
			return array($group, 99);
		}
	}
}
