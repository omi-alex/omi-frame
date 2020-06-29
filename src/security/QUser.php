<?php

/**
 * Each object may have owner(s)
 * 
 * @todo
 * @storage.table $Users
 * 
 * @api.todo
 */
class QUser extends QModel 
{
	use QUser_GenTrait;
	/**
	 * @var integer
	 */
	protected $Id;
	/**
	 * The name of the user
	 *
	 * @var string
	 */
	protected $Name;
	/**
	 * @storage.index unique
	 * @storage.info The username should be the email!
	 * @validation mandatory
	 * @var string
	 */
	protected $Username;
	/**
	 * @storage.index unique
	 * @validation mandatory
	 * @var string
	 */
	protected $Email;
	/**
	 * @storage.info This field can be left blank. If new user a password will be autogenerated, if existing user then the password will remain the same! The password must have at least 8 characters, at least one lowercase letter, at least one uppercase letter, at least one number, at least one non-alphanumeric character (E.g. :%&*#@)  
	 * 
	 * @display.type password
	 * @var string
	 */
	protected $Password;

	/**
	 * Each user will have a group that only holds him
	 * @var QUserGroup
	 */
	protected $SelfGroup;
	/**
	 * @storage.collection $UsersGroupsList,User,Group
	 * @var QUserGroup[]
	 */
	protected $Groups;

	public function getPermissions($type)
	{
		// read it from cache or not at all
	}

	public function __toString()
	{
		return $this->Username;
	}

	public function GetCurrent()
	{
		$user = new QUser(); 
		$user->Name = $user->Username = "user_1";
		$user->setId(1);
		return $user;
	}
	
	public function GetCurrentUserId()
	{
		return 1;
	}
	
	public function IsLoggedInById($user_id)
	{
		// TO DO
		return true;
	}
	
	public function getGroupsIdsStr()
	{
		return "1,2,3,4,5";
	}
	
	public function getGroupsIds()
	{
		return array(1,2,3,4,5);
	}
	
	public function GetGroups()
	{
		return array();
		// return array("admin");
	}
	
	public function isLoggedIn()
	{
		// to do
		return true;
	}
	
	public function hasGroup($group)
	{
		// TO DO 
		if (is_string($group))
		{
			return true;
		}
		else
			return true;
	}
	
	public function hasAnyGroup($groups)
	{
		return true;
	}
	
	public function isRoot()
	{
		return false;
	}
	
	public function getModelRights($instance, $type, $property)
	{
		
	}

	public function isInGroupsDef($group_def)
	{
		if (is_string($group_def))
		{
			switch ($group_def)
			{
				case "owner":
				{
					// go for the join, it's better
					return array(":owner", "in", "grps" => $this->getGroupsIds());
				}
				case "users":
				{
					return $this->isLoggedIn();
				}
				case "anonym":
				{
					return true;
				}
				default:
				{
					return $this->hasGroup($group_def);
				}
			}
		}
		else
		{
			$group = end($group_def);
			if ($group === false)
				return false;
			
			// am I XXX of GROUP
			
			switch ($group_def)
			{
				case "owner":
				{
					// stack rule
					return array(":owner", "in", "grps" => $this->getGroupsIds());
				}
				case "users":
				{
					// stack rule
					return $this->isLoggedIn();
				}
				case "anonym":
				{
					// stack rule
					return true;
				}
				default:
				{
					// stack rule
					return $this->hasGroup($group_def);
				}
			}
		}
		/*
		 * $group_parts = preg_split("/\s+(of)\s+|(\s)/", $group, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		/**
		 *  for groups we support: 
		 *			1. predefined patterns: owner,users,anonym
		 *			2. relations (recursive)
		 *			3. constant group tag
		 */
		/*
		// determine the list of groups that are associated with me, some may be dynamic so we can only set them up as a Query
		$group_rule = null;
		// $gp_count = count($group_parts);
		$gp = end($group_parts);
		$g_rule = null;
		
		switch ($gp)
		{
			case "owner":
			{
				$g_rule = array(":owner", "in", "grps" => $user->getGroupsIds());
				break;
			}
			case "users":
			{
				$g_rule = "users";
				break;
			}
			case "anonym":
			{
				$g_rule = "anonym";
				break;
			}
			default:
			{
				// we have the name of a group
				$g_rule = QUserGroup::GetGroupId($gp);
				break;
			}
		}
		
		if ($g_rule === false)
			continue;
		
		do
		{
			$gp_rule = prev($group_parts);
			$gp_rule_on = prev($group_parts);
			
			if ($gp_rule && $gp_rule_on)
			{
				// $g_rule = array($gp_rule_on, $gp_rule, $g_rule);
				if ($gp_rule === "of")
				{
					if (($g_rule === true) || is_int($g_rule))
					{
						$g_rule = QUserGroup::GetRelationsFor($g_rule, $gp_rule_on);
					}
					else if (is_array($g_rule))
					{
						if (isset($g_rule["grps"]))
							$g_rule["grps"] = QUserGroup::GetRelationsFor($g_rule["grps"], $gp_rule_on);
						else
							$g_rule = QUserGroup::GetRelationsFor($g_rule, $gp_rule_on);
					}
					else 
						throw new Exception("Unsupported situation");
				}
				else 
					throw new Exception("Invalid rule: ".$gp_rule);
				
				$group_rule = $g_rule;
			}
			else
			{
				if ($g_rule === "users")
					$group_rule = $user->isLoggedIn();
				else if ($g_rule === "anonym")
					$group_rule = true;
				else
					$group_rule = $g_rule;
				break;
			}
		}
		while($gp_rule && $gp_rule_on);
		
		if (is_int($group_rule))
		{
			$group_rule = $user->hasGroup($group_rule);
		}
		else if (is_array($group_rule))
		{
			// only ids, with rules
			if (is_int($group_rule[0]))
			{
				// only ids
				$group_rule = $user->hasAnyGroup($group_rule);
			}
		}
		
		if (!$group_rule)
			continue;

		// at this point we have a list of groups
		// foreach ($group_parts as $gp)
		// now for perms : * / r / c / :owner
		// $perms = ($right{0} === ":") ? $right : qBinRights($right);
		
		// groups rule may be based on :owner, may be cascaded
		
		$g_rights = array();
		foreach ($right as $i_right)
		{
			$g_rights[] = $i_right;
		}
		
		$rule = array($group_rule, $g_rights);
		
		$rules[] = $rule;
		
		var_dump($rule);
		echo "<hr/>";
		 */
		return $group_def; //true;
	}
	
	public static function SecurityCheckUser($rights, $attach_on = null, $user = null)
	{
		return $user ? $user->securityCheck($rights, $attach_on) : self::GetCurrent()->securityCheck($rights, $attach_on);
	}
	
	public function memberOf($group)
	{
		return true;
	}
	
	public function is(string $group)
	{
		return $this->Groups ? $this->Groups->has($group, 'Name', true) : false;
	}
	
	/*
	public function securityCheck($rights, $attach_on = null)
	{
		$query_perms = false;
		$query_owner = false;
		$owner_filter = null;
		
		if (!$rights)
			return false;
		
		foreach ($rights as $perms => $groups)
		{
			if (is_int($perms))
			{
				if (!($u_perms = (QPermsFlagRead & $perms)))
					continue;
				$int_perms = true;
			}
			else
			{
				$u_perms = $perms;
				if ($perms !== ":owner")
					throw new Exception("Only `:owner` perms are implmented at the moment.");
				$query_perms = true;
				$int_perms = false;
			}
			
			foreach ($groups as $group)
			{
				$r_group = $group;
				if (is_string($group))
				{
					switch ($group)
					{
						case "owner":
						{
							$query_owner = true;
							// ":u:$this"
							$owner_filter[$u_perms][":u:{$this}"] = $this;
							break;
						}
						case "users":
						{
							$r_group = $this->isLoggedIn();
							if ($r_group && $int_perms)
								return true;
							break;
						}
						case "anonym":
						{
							$r_group = true;
							break;
						}
						default:
						{
							$r_group = $this->hasGroup($group) ? $group : false;
							break;
						}
					}
				}
				else
				{
					$b_group = end($group);
					$b_op = prev($group);
					$b_rel = prev($group);
					switch ($b_group)
					{
						case "owner":
						{
							$query_owner = true;
							// ":r:$rel:$op:$group:$this"
							$owner_filter[$u_perms][":r:{$b_rel}:{$b_op}:{$b_group}:{$this}"] = array($b_rel, $b_op, $b_group, $this);
							break;
						}
						case "anonym":
						{
							throw new Exception("No one can be in relation with `anonym`");
						}
						// 
						case "users":
						default:
						{
							// $r_group = $this->inRelationWith($b_rel, $b_op, $b_group);
							throw new Exception("not implemented");
							if ($r_group && $int_perms)
								return true;
							break;
						}
					}
				}
			}
		}
		
		if ($attach_on)
		{
			if ($query_perms)
				$attach_on->_qperm = $query_perms;
			if ($query_owner)
				$attach_on->_qown = $query_owner;

			if ($owner_filter)
			{
				if (!isset($attach_on->_ownfilt))
					$attach_on->_ownfilt = array();
				$attach_on->_ownfilt += $owner_filter;
			}
			
			// var_dump($attach_on->_qperm, $attach_on->_qown, $attach_on->_ownfilt, $attach_on->type."");
			// echo '<hr/>';
		}
		else
		{
			$ret = array();

			if ($query_perms)
				$ret["qperm"] = $query_perms;
			if ($query_owner)
				$ret["qown"] = $query_owner;

			if ($owner_filter)
				$ret["ownfilt"] = $owner_filter;
			
			// var_dump($ret);
			
			return $ret ?: null;
		}
	}
	*/
}
