<?php

trait QModel_Security
{
	protected static $_Security_App_Props_Config;
	
	public static function PopulateSecurity($user_can = null, $values = null, $selector = null, array $groups = null)
	{
		if ($selector === null)
			$selector = static::GetModelEntity();
		if (is_string($selector))
			$selector = qParseEntity($selector, true, true, static::class);
		
		$can_flag = 0;
		$can_list = null;
		
		if ($user_can === null)
		{
			// all allowed actions will be extracted
		}
		else if (is_int($user_can))
			$can_flag = is_int($user_can);
		else if (is_array($user_can))
		{
			if (is_int($user_can[0]))
			{
				$can_flag = $user_can[0];
				array_shift($user_can);
				$can_list = $user_can;
			}
			else
				$can_list = $user_can;
		}
		else if (is_string($user_can))
			$can_list = [$user_can => 1]; // @todo test if 1 is faster than true
		
		if (!($can_flag || $can_list))
			// we are not requesting any specific permissions, all will be accepted
			$can_flag = $can_list = null;
		
		$run_static = ($values === null) || ($values === false);
		if ($values)
		{
			// group them by class 
			$group_by_class = [];
			foreach ($values as $v)
				$group_by_class[get_class($v)][] = $v;
		}
		else if ($run_static)
		{
			$group_by_class = [static::class => [($c = new \stdClass())]];
			$c->_ty = static::class;
		}
		else
			return null;
		
		$objs = ($selector === true) ? new \SplObjectStorage() : null;
		
		$ret = [];
		foreach ($group_by_class as $class => $vals)
		{
			$rv = $class::UserCanRawRec($can_flag, $can_list, $vals, $selector, $groups, $run_static, $objs);
			if ($run_static && $rv)
			{
				foreach ($rv as $rvv)
					$ret[] = $rvv;
			}
		}
		return $run_static ? $vals : $ret;
	}
	
	public static function UserCanRaw(array $values = null, int $can_flag = null, array $can_list = null, $selector = null, array $groups = null)
	{
		return false;
	}
	
	public static function UserCanRawRec(int $can_flag = null, array $can_list = null, array $values = null, $selector = null, array $groups = null, bool $run_static = false, \SplObjectStorage $objects = null)
	{
		if ($selector === false)
			return;
		
		if ($values)
		{
			// array $values = null, int $can_flag = null, array $can_list = null, $selector = null, array $groups = null
			// var_dump(static::class."::UserCanRaw | ".count($values));
			static::UserCanRaw($values, $can_flag, $can_list, $selector, $groups);
			
			$recurse_stack = [];
			
			if ($selector) // we must have a reason to build the $recurse_stack
			{
				$class_name = static::class;
				$type_inf = \QModelQuery::GetTypesCache($class_name);
				if (!$type_inf)
					// the App does not have type info atm
					throw new \Exception("Unable to find type info for: ".$class_name);

				// the rest are the non-static
				// we need to adapt them and push the forward
				$recurse_by_tyinf = !(is_array($selector) && $selector);
				$recurse_on = $recurse_by_tyinf ? $type_inf : array_intersect_key($selector, $type_inf);
				
				foreach ($recurse_on as $k => $v)
				{
					if (($k{0} === '#') && ($k{1} === '%'))
						continue;

					foreach ($values as $value)
					{
						if ($run_static)
						{
							$ty_inf = $recurse_by_tyinf ? $v : $type_inf[$k];
							$possib_types = $ty_inf['refs'] ?: $ty_inf['[]']['refs'] ?: null;
							// add a value foreach possible types
							if ($possib_types)
							{
								// create a value foreach possible type
								foreach ($possib_types as $pty)
								{
									$c = $recurse_stack[$k][$pty][] = new \stdClass();
									$c->_ty = $pty;
									$value->$k[] = $c;
								}
							}
						}
						else
						{
							$p_value = $value->$k;
							if (is_array($p_value) || ($p_value instanceof \QIModelArray))
							{
								foreach ($p_value as $pv)
								{
									if (($pv instanceof \QIModel) && (($selector !== true) || (!$objects->contains($pv))))
									{
										if ($selector === true)
											$objects[$pv] = 1;
										$recurse_stack[$k][get_class($pv)][] = $pv;
									}
								}
							}
							else if (($p_value instanceof \QIModel) && (($selector !== true) || (!$objects->contains($p_value))))
							{
								if ($selector === true)
									$objects[$p_value] = 1;
								$recurse_stack[$k][get_class($p_value)][] = $p_value;
							}
						}
					}
				}
			}

			foreach ($recurse_stack as $p_name => $p_stack)
			{
				foreach ($p_stack as $class => $p_vals)
				{
					// int $can_flag = null, array $can_list = null, array $values = null, $selector = null, array $groups
					$ucr_ret = $class::UserCanRawRec($can_flag, $can_list, $p_vals, ($selector === true) ? $selector : $selector[$p_name], $groups, $run_static, $objects);//, $props_stack ? $props_stack.".".$p_name : $p_name);
					if ($run_static && $ucr_ret)
					{
						foreach ($values as &$v)
							$v->$p_name[$class] = $ucr_ret;
					}
				}
			}
		}
		
		return $values;
	}
	
	public static function DumpSecurityInfo($values = null, array $selector = null, bool $static_info = false, string $depth = "")
	{
		if (!$values)
			return false;
		
		$all_has_info = false;
		
		foreach ($values as $k => $v)
		{
			if (($static_info && (!is_object($v))) || ((!$static_info) && (!($v instanceof \QIModel))))
				continue;
			
			$has_info = false;
			
			ob_start();
			
			$can_flag = $v->_scf;
			$can_list = $v->_sl;
			$ty = $v->_ty ?: get_class($v);
			$tid = $v->_tid;
			
			echo $depth."[{$ty}".($tid ? " #{$tid}" : "")."]\n";
			$sec_keys = ($can_flag && $can_list) ? ($can_flag + $can_list) : ($can_flag ?: $can_list ?: []);
			if ($can_flag || $can_list)
				$all_has_info = $has_info = true;
			foreach ($sec_keys as $p_name => $p_can)
			{
				$p_caption = (($p_name === 0) || ($p_name === "0")) ? "class" : $p_name;
				echo $depth."\t{$p_caption}: ".$can_flag[$p_name]." | ".($can_list ? "'".implode("', '", array_keys($can_list[$p_name]))."'" : "-")."\n";
			}
			
			foreach ($v as $p_name => $p_val)
			{
				if (is_string($p_name) && ($p_name{0} === '_'))
					continue;
				if (!$p_val)
					continue;
				
				ob_start();
				$prop_has_info = false;
				
				echo $depth."\t\${$p_name}:\n";
				if ($static_info)
				{
					foreach ($p_val as $class_name => $instances)
					{
						$r_has_info = static::DumpSecurityInfo($instances, $selector ? $selector[$p_name] : null, $static_info, $depth."\t\t");
						if ($r_has_info)
							$prop_has_info = $all_has_info = $has_info = true;
					}
				}
				else
				{
					$instances = ($p_val instanceof \QIModel) ? [$p_val] : (qis_array($p_val) ? $p_val : null);
					if ($instances)
					{
						$r_has_info = static::DumpSecurityInfo($instances, $selector ? $selector[$p_name] : null, $static_info, $depth."\t\t");
						if ($r_has_info)
							$prop_has_info = $all_has_info = $has_info = true;
					}
				}
				
				if ($prop_has_info)
					ob_flush();
				else
					ob_end_clean();
			}
			
			if ($has_info)
				ob_flush();
			else
				ob_end_clean();
		}
		
		return $all_has_info;
	}
	
	public function userHasRelation(string $relation, array $values = null, $selector = null)
	{
		// if cached, instant return
		if (($r = $this->_gp['rel'][$relation]) !== null)
			// $this->_gp['rel'] - storing relations
			return $r;
		
		if (!$values)
			$values = [$this];
		if (is_string($selector))
			$selector = [$selector => [true]];
		
		$rels = explode(":", $relation);
		// $relation = '".implode(":", $rg_condition)."'
		$start_elements = [];
		$start_elements = $selector ? static::UserHasRelationFind($values, $selector, $start_elements) : $values;
		$se_by_class = [];
		$with_ids = [];
		foreach ($start_elements as $se)
		{
			$se_by_class[$cn = get_class($se)][] = $se;
			if ($se->getId())
			{
				if ($with_ids[$cn] === null)
					$with_ids[$cn] = new \QModelArray();
				$with_ids[$cn][] = $se;
			}
		}
		
		foreach ($se_by_class as $class => $elems)
			$class::UserHasRelationRec($rels, $elems, $with_ids[$class]);
		
		/*
		foreach ($start_elements as $val)
		{
			echo "GOT: ".($val->_gp['rel'][$relation] ? "true" : "false")." ON ".get_class($val)." | ".$val->getId()."\n";
		}*/
		
		$r = $this->_gp['rel'][$relation];
		if ($r === null)
			return $this->_gp['rel'][$relation] = false;
		else
			return $r;
	}
	
	public static function UserHasRelationRec(array $rels, array $values, $with_ids = null)
	{
		if (!$rels)
			return;
		if (count($rels) > 1)
			throw new \Exception('Multilevel conditions are not implemented yet.');
		// @model.securityRelation.owner Users.AccountManager IF Users.AccountManager.Active
		$mty = static::GetTypeByName(static::class);
		if (!($mty && $mty->model))
			throw new \Exception('Missing model info on: '.static::class);
		$frst_rel = reset($rels);
		// var_dump($frst_rel, $mty->model, 'securityRelation.'.$frst_rel, $mty->model['securityRelation.'.$frst_rel]);
		$securityRelation = $mty->model['securityRelation.'.$frst_rel];
		if (!$securityRelation)
			throw new \Exception('Missing security relation');
		
		$user_id = ($user = \Omi\User::GetSecurityCurrentUser()) ? $user->getId() : null;
		if (!$user_id)
			throw new \Exception('No user is logged in');
		list($rel_selector, $rel_condition) = preg_split("/\\s+IF\\s+/us", trim($securityRelation), 2, PREG_SPLIT_NO_EMPTY);
		// $relation_target = ['Users' => ['AccountManager' => [true]]];
		if ($with_ids)
		{
			// $t0 = microtime(true);
			$with_ids->populate($rel_selector.".{Id WHERE Id=?}", $user_id);
			// $t1 = microtime(true);
			// var_dump("Populate: ".round(($t1 - $t0)*1000, 2)." FOR ".$rel_selector.".{Id WHERE Id={$user_id}}");
		}
		
		$pe = qParseEntity($rel_selector, true);
		
		$find_el = [];
		static::UserHasRelationFind($values, $pe, $find_el, $user_id, true, implode(":", $rels));
	}
	
	public static function UserHasRelationFind($values, array $selector, array &$return, $extract_base_if_id = null, bool $is_base = true, $set_relation = null)
	{
		$is_targeted = $selector[0] ? true : false;
		foreach ($values as $val)
		{
			$ret_code = false;
			if ($is_targeted)
			{
				if ($extract_base_if_id)
				{
					if ((string)$val->getId() === (string)$extract_base_if_id)
					{
						$ret_code = true;
					}
				}
				else
					$return[] = $val;
			}
			if ((!$extract_base_if_id) || (!$ret_code))
			{
				foreach ($selector as $k => $v)
				{
					if ($k === 0)
						continue;
					$val_v = $val->$k;
					if (is_array($val_v) || ($val_v instanceof \QIModelArray))
					{
						$rc = static::UserHasRelationFind($val_v, $v, $return, $extract_base_if_id, false);
						if ($extract_base_if_id && $rc)
						{
							$ret_code = true;
							break;
						}
					}
					else if ($val_v instanceof \QIModel)
					{
						$rc = static::UserHasRelationFind([$val_v], $v, $return, $extract_base_if_id, false);
						if ($extract_base_if_id && $rc)
						{
							$ret_code = true;
							break;
						}
					}
				}
			}
			if ($extract_base_if_id && $is_base)
			{
				// echo "Setting: ".($ret_code ? "true" : "false")." ON ".get_class($val)." | ".$val->getId()."\n";
				$val->_gp['rel'][$set_relation] = $ret_code;
			}
		}
		
		return $extract_base_if_id ? $ret_code : $return;
	}
	
	public static function ExtractSQLFilter(string $rule, array $groups = null, array $relations = null)
	{
		$options = explode(",", $rule);
		$return = false;
		
		$sql_or = [];
		foreach ($options as $_opt)
		{
			$opt = trim($_opt);
			$and = explode("+", $opt);
			
			$is_true = true;
			$sql = [];

			foreach ($and as $_cond)
			{
				// top_company+admin,is:partner:of:\$each,is:owner:of:\$each
				$cond = trim($_cond);
				if (substr($cond, 0, 3) === 'SQL:')
					$sql[] = $cond;
				else if (substr($cond, 0, 3) === 'is:')
				{
					$user_id = ($user = \Omi\User::GetSecurityCurrentUser()) ? $user->getId() : null;
					if (!$user_id)
					{
						// we can't have a realtion without a user
						$is_true = false;
						break;
					}
					
					$rels_parts = preg_split("/(\\:(?:of\\:)?)/us", trim(substr($cond, 3)), -1, PREG_SPLIT_NO_EMPTY);
					$last = end($rels_parts);
					if ($last === '$any')
						throw new \Exception('$any not implemented here atm');
					if (($last === '$this') || ($last === '$each'))
						array_pop($rels_parts);
					if (count($rels_parts) > 1)
						throw new \Exception('Conditions stack is not implemented');
					
					$relation = reset($rels_parts);
					$relation_cond = null;
					// prepare relations
					foreach ($relations as $r)
					{
						list($rel_name, $rel_cond) = preg_split("/(\\s+)/us", $r, 2, PREG_SPLIT_NO_EMPTY);
						if ($rel_name === $relation)
						{
							$relation_cond = $rel_cond;
							break;
						}
					}
					if (!$relation_cond)
						throw new \Exception('Unable to find relation definition for `'.$relation.'`');
					
					if (($rc_pos = strpos($relation_cond, '?') ) !== false)
						$sql[] = str_replace('?', "'".addslashes($user_id)."'", $relation_cond);
					else
						$sql[] = $relation_cond.".Id='".addslashes($user_id)."'";
				}
				else if (!$groups[$cond])
				{
					$is_true = false;
					break;
				}
			}

			if (!$is_true)
				continue; // not possible to satisfy condition
			
			if ($sql)
			{
				$sql_or[] = "(".implode(") AND (", $sql).")";
			}
			else // we have a full condition satisfied
			{
				$return = true;
				break;
			}
		}
		
		if ($return === true)
			return true;
		else if ($sql_or)
			return "(".implode(" OR ", $sql_or).")";
		else
			return false;
	}
	
	public static function GetFinalSecurityForAppProperty(string $app_property, string $what = null, \QModelProperty $property = null)
	{
		// $what = 'filter', ...
		//	then we will need relations
		
		$ret = [];
			
		if (static::$_Security_App_Props_Config === null)
		{
			$cfg_path = \QAutoload::GetRuntimeFolder()."_props_security_cfg.php";
			if (file_exists($cfg_path))
			{
				$__DATA = null;
				require($cfg_path);
				static::$_Security_App_Props_Config = $__DATA;
			}
			if (static::$_Security_App_Props_Config === null)
				static::$_Security_App_Props_Config = [];
		}
		if (($security_mode = static::$_Security_App_Props_Config[$app_property]))
		{
			$s_modes = explode(",", $security_mode);
			
			foreach ($s_modes as $_smode)
			{
				$s_mode = trim($_smode);
				if (($what === 'filter') || (!$what))
				{
					// non-customer,customer groups
					// notcustomer
					// filter is expected as an array with a single element
					switch ($_smode)
					{
						case 'top':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'notcustomer+toplevel';
							break;
						}
						case 'strict-box':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'notcustomer+is:owner:of:$each';
							# $ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'owner_customer+is:owner_customer:of:$each';
							break;
						}
						case 'strict-box-and-customers':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'is:owner:of:$each';
							# $ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'owner_customer+is:owner_customer:of:$each';
							break;
						}
						case 'strict-box-customer':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'customer+is:ownerCustomer:of:$each';
							break;
						}
						case 'strict-box-customer-self':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'customer+is:customer:of:$each';
							break;
						}
						case 'strict-box-and-customers':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'is:ownerOrCustomer:of:$each';
							break;
						}
						case 'tree-box':
						case 'tree-box-customer':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'notcustomer+is:parentPartner:of:$each';
							break;
						}
						case 'customer-link':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'customer+is:customer:of:$each';
							break;
						}
						case 'customer-enterprise-link':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'customer+is:customer:of:$each';
							break;
						}
						case 'customer-self':
						{
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'customer+is:customer:of:$each';
							break;
						}
						case 'sipuser-customer-link':
						{
							# $ret['relation'][] = 'sipuser User.Customer.OwnUsers';
							$ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'sipuser+is:sipuser:of:$each';
							break;
						}
					}
				}
				if (($what === 'relation') || (!$what))
				{
					switch ($_smode)
					{
						case 'strict-box':
						{
							$ret['relation'][] = 'owner (Owner.Users.Id=? AND Owner.Users.Customer.Id IS NULL)';
							break;
						}
						case 'strict-box-and-customers':
						{
							$ret['relation'][] = 'owner (Owner.Users.Id=?)';
							# $ret['filter'][0] .= ($ret['filter'][0] ? "," : "").'owner_customer+is:owner_customer:of:$each';
							break;
						}
						case 'strict-box-customer-self':
						{
							$ret['relation'][] = 'customer (Users.Id=? AND Users.Customer.Id=Id)';
							break;
						}
						case 'strict-box-customer':
						{
							$ret['relation'][] = 'ownerCustomer (Owner.Users.Id=? AND Owner.Users.Customer.Id=Customer.Id)';
							break;
						}
						case 'strict-box-and-customers':
						{
							$ret['relation'][] = 'ownerOrCustomer Owner.Users.Id=?';
							break;
						}
						case 'tree-box':
						{
							$ret['relation'][] = 'parentPartner Owner.ParentsStack.Users';
							break;
						}
						case 'tree-box-customer':
						{
							$ret['relation'][] = 'parentPartner Customer.Owner.ParentsStack.Users';
							/* $ret['relation'][] = 'parentPartner Customer.Id IN (SELECT Customers.{ IF(Id, Id, NULL) '.
														'WHERE Owner.ParentsStack.Users.Id=? GROUP BY Id} )';
							*/
							break;
						}
						case 'customer-link':
						{
							$ret['relation'][] = 'customer Customer.OwnUsers';
							break;
						}
						case 'customer-enterprise-link':
						{
							$ret['relation'][] = 'customer Enterprise.Company.OwnUsers';
							break;
						}
						case 'sipuser-customer-link':
						{
							$ret['relation'][] = 'sipuser User.Customer.OwnUsers';
							break;
						}
						case 'customer-self':
						{
							$ret['relation'][] = 'customer OwnUsers';
							break;
						}
					}
				}
				
			}
			// strict-box, it means it has an owner
			/*
			@security.allow crud ON @M TO toplevel+admin,partner+is:partner:of:$each,owner+is:owner:of:$each,customer+is:customer:of:$each
			@security.filter toplevel+admin,partner+is:partner:of:$each,owner+is:owner:of:$each,customer+is:customer:of:$each

			@security.relation owner Users
			@security.relation partner Owner.Users
			@security.relation customer Customer.OwnUsers

			this only works if Customer.OwnUsers
			 */
		}
		
		/*
		if ($what === 'relation')
		{
			qvar_dump($ret, $ret ? ($what ? $ret[$what] : $ret) : (($what && $property && $property->security) ? $property->security[$what] : ($property ? $property->security : null)));
			die;
		}*/
		
		$the_return = $ret ? ($what ? $ret[$what] : $ret) : (($what && $property && $property->security) ? $property->security[$what] : ($property ? $property->security : null));
		return $the_return;
	}
	
	// is:employee:of:partner:of:owner:of:$each
	// of is optional
	
	// $value, $user
	// $user IN ($value->owners->partners->employees)
	// how do we implement this ?
	// ($values)->populate("")
	// $class = get_class(reset($values))
	// $class::Query("Id,(employees.partners.owners.Id AS COND_1),OTHER_CONDITIONS WHERE employees.partners.owners.Id = $user.Id OR OTHER_CONDITION GROUP BY ??? LIMIT \$NO_CONDITIONS")
	
	// Step1: Class::SecurityPrepareRelations( $list_of_all )
	// Step2: $value/$each/$any->userHasRelation($relation)
	
	// is:employee:of:partner:of:owner:of:$each
	
	// auch for ANY :)
	
	// IF_EACH $each->userHasRelation(['owner', 'partner', 'employee'])
	// @relation owner Company.Owners WHERE AnyCondition | hope we can parse it
	// @relation partner Company.Partners
	
	// how will the codition work for nested ... ? ... should be fine , I hope
	
	
}
