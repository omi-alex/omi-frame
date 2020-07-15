<?php

class QSecurityGenerator
{
	protected static $CachePropertyStackInfo = [];
	public static $ActionsMapping = [
			'crud' => S_View | S_Add | S_Edit | S_Delete,
			'add' => S_Add,
			'delete' => S_Delete,
			'edit' => S_Edit,
			'merge' => S_Add | S_Edit,
			'read' => S_View,
			'view' => S_View,
		];
	
	protected $className;
	
	public static function GenerateFromRules(array $rules, array $relations = null)
	{
		foreach ($rules as $class_name => $sub_rules)
		{
			$gen = new static();
			$gen->className = $class_name;
			$gen->generate($sub_rules, $relations ? $relations[$class_name] : null);
		}
	}
	
	public static function GenerateForClass(string $full_class_name)
	{
		$type_inf = \QModel::GetTypeByName($full_class_name);
		
		$rules = [];
		if ($type_inf->security)
		{
			foreach ($type_inf->security as $s_type => $s_rules)
			{
				if (!(($s_type === 'allow') || ($s_type === 'deny')))
					continue;
				foreach ($s_rules as $s_rule)
				$rules[0][] = [$s_type, $s_rule];
			}
		}
		
		foreach ($type_inf->properties ?: [] as $prop)
		{
			if ($prop->security)
			{
				foreach ($prop->security as $s_type => $s_rules)
				{
					if (!(($s_type === 'allow') || ($s_type === 'deny')))
						continue;
					foreach ($s_rules as $s_rule)
					$rules[$prop->name][] = [$s_type, $s_rule];
				}
			}
		}
		
		// must have some security rules
		if (!$rules)
			return null;
		
		$gen = new static();
		$gen->className = $full_class_name;
		return $gen->generate($rules);
	}
	
	public function generate($rules, bool $save_to_file = false)
	{
		$gen_rules = [];
		
		$rule_index = 0;
		
		foreach ($rules as $property => $p_rules)
		{
			foreach ($p_rules as $rule)
			{
				$directive = $rule[0];
				$r_property = ($property && is_string($property)) ? $property : null;
				
				if (!is_string($rule[1]))
				{
					qvar_dump($rule[1]);
					die("bad rule");
				}
				
				list($actions, $on, $groups, $condition) = $this->parseRule(trim($rule[1]), $r_property, $rule_index);
				
				// list($flag_actions, $str_actions) = $actions;
				// here we could join some things if they are the same ... maybe $actions ?!
				$gen_rules[] = [$actions[0], $actions[1], $on, $groups, $condition, $rule[0]." ".$rule[1], $r_property];
				$rule_index++;
			}
		}
		
		$func_str = $this->createFunctionString($gen_rules);
		
		$p = strrpos($this->className, '\\');
		$ns = ($p !== false) ? substr($this->className, 0, $p) : null;
		$class_name = (($p !== false) ? substr($this->className, $p + 1) : $this->className)."__GenTrait";
		
		$trait = 
"<?php

".($ns ? "namespace {$ns};

" : "").
"class {$class_name}
{
{$func_str}
}
";
		if ($save_to_file)
		{
			// echo $trait;
			$fp = "code/tests/security/".($ns ? str_replace("\\", "/", $ns)."/" : "")."{$class_name}.php";
			if (!is_dir(dirname($fp)))
				qmkdir(dirname($fp));
			if ((!file_exists($fp)) || (file_get_contents($fp) !== $trait))
				file_put_contents($fp, $trait);
		}
		
		return $trait;
	}
	
	public static function DeterminePropertyStackInfo(array $props_stack, string $class)
	{
		// we need to cache this
		$key = $class."::".implode(".", $props_stack);
		if (($ret = static::$CachePropertyStackInfo[$key]) !== null)
			return $ret;
		
		if (!is_int(key($props_stack)))
			$props_stack = array_values($props_stack); // ensure values only
		$type_infs = [$class => \QModelQuery::GetTypesCache($class)];
		
		$count = count($props_stack);
		$is_collection = false;
		$is_reference = false;
		
		for ($i = 0; $i < $count; $i++)
		{
			$is_last = ($i === ($count -1));
			$prop = $props_stack[$i];
			$is_collection = false;
			$is_reference = false;
			
			$child_ty = [];
			
			foreach ($type_infs as $typeinf)
			{
				if (!is_array($typeinf))
					// there was a scalar somewhere in the stack
					continue;
				
				if (($ty = $typeinf[$prop]))
				{
					if (($collection_info = $ty['[]']))
					{
						$is_collection = true;
						if (($types = $collection_info["refs"]))
						{
							foreach ($types as $ty_name)
							{
								if (!$child_ty[$ty_name])
									$child_ty[$ty_name] = $is_last ? $ty_name : \QModelQuery::GetTypesCache($ty_name);
							}
						}
					}
					else if (($reference_info = $ty['#']))
					{
						$is_reference = true;
						if (($types = $ty["refs"]))
						{
							foreach ($types as $ty_name)
							{
								if (!$child_ty[$ty_name])
									$child_ty[$ty_name] = $is_last ? $ty_name : \QModelQuery::GetTypesCache($ty_name);
							}
						}
					}
					else if (($scalar_info = $ty['$']))
					{
						// scalar
						if ($is_last)
						{
							foreach ($scalar_info as $ty_name)
								$child_ty[$ty_name] = $ty_name;
						}
					}
				}
			}
		
			$type_infs = $child_ty;
		}
		
		return (static::$CachePropertyStackInfo[$key] = [$type_infs, $is_reference, $is_collection]);
	}
	
	public function determineSelectorOptions(array $props_stack, string $class = null)
	{
		if ($class === null)
			$class = $this->className;
		
		return static::DeterminePropertyStackInfo($props_stack, $class);
	}
	
	protected function parseRule(string $rule, string $property = null, $rule_index = null)
	{
		$actions = $on = $groups = $condition = null;
		
		$sequence = ['ON', 'TO', 'IF'];
		$reg_exp_opts = ['ON', 'TO', '(?:IF|IF_ANY|IF_EACH)'];
		$idfs = [0 => 'actions', 'ON' => 'on', 'TO' => 'groups', 'IF' => 'condition'];
		$next_pos = 0;
		$n_key = reset($idfs);
		$next = null;
		
		$condition_type = 'IF';
		while ($sequence)
		{
			list($substr, $$n_key, $next) = qpreg_get("/(^([\w\\,\\@\\s\\.\\}\\{\\+\\:\\*\\$]+?)\s+(".implode('|', $reg_exp_opts).")\s+)/u", substr($rule, $next_pos));
			// , 'IF_ANY' => 'condition', 'IF_EACH' => 'condition'
			if (($next === 'IF_ANY') || ($next === 'IF_EACH'))
			{
				$condition_type = $next;
				$next = 'IF';
			}
			if (!$substr)
				break;
		
			$n_key = $idfs[$next];
			while (($next !== array_shift($sequence)) && ($next !== false));
			$next_pos += strlen($substr);
		}
		
		// then whatever remains ... 
		if (($rest = trim(substr($rule, $next_pos))))
			$$n_key = $rest;
		
		// if ($property)
		//	$on = $on ? $property."{{$on}}" : $property;
		if (($condition_type !== 'IF') && ($condition !== null))
			$condition = $condition_type." ".$condition;
		
		$parsed_groups = $this->parseGroups($groups);
		$parsed_on =  $this->parseOn($on, $property);
		
		return [$this->parseActions($actions), $parsed_on, $parsed_groups, $this->parseCondition($condition, $property, $parsed_groups, $rule_index)];
	}
	
	protected function parseActions($actions)
	{
		$r_actions = [0, []];
		$parts = preg_split("/(\s*,\s*)/us", $actions);
		
		foreach ($parts as $part)
		{
			$mapping = static::$ActionsMapping[$part] ?: $part;
			if (is_int($mapping))
				$r_actions[0] |= $mapping;
			else if (is_string($mapping))
				$r_actions[1][$mapping] = 1;
			else if (is_array($mapping))
			{
				foreach ($mapping as $m)
					$r_actions[1][$m] = 1;
			}
		}
		
		return $r_actions;
	}
	
	protected function parseOn($on, string $property = null)
	{
		// fill in Entity tags
		/*
		$on_parts = preg_split("/(\\@\\w+)/us", $on, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$new_on = [];
		foreach ($on_parts as $op)
		{
			if ($op{0} === "@")
			{
				$me_on = null;
				
				
				if (is_array($me_on))
					$new_on[] = qImplodeEntity($me_on);
				else
					$new_on[] = $op;
			}
			else
				$new_on[] = $op;
		}
		
		$on = implode($new_on);
		*/
		if ($property)
		{
			$on = $on ? $property."{{$on}}" : $property;
		}
		
		if (!is_string($on))
			throw new \Exception('Fail, expected string');
		
		return qParseEntity($on, true, true, $this->className);
	}
	
	protected function parseGroups($groups)
	{
		$groups = trim($groups);
		if (!$groups)
			return null;
		
		$parts = preg_split("/(\s*,\s*)/us", $groups);
		$ret = [];
		
		$relation_conditions = [];
		
		foreach ($parts as $p)
		{
			$and_groups = preg_split("/(\s*\\+\s*)/us", trim($p), -1, PREG_SPLIT_NO_EMPTY);
			sort($and_groups);
			
			$a_grps = [];
			$rel_cond = [];
			$has_rel_cond = false;
			
			foreach ($and_groups as $ag)
			{
				if (substr($ag, 0, 3) === "is:")
				{
					$rel_parts = preg_split("/(\\s*\\:\\s*(?:of\\s*\\:\\s*)?)/us", substr($ag, 3), -1, PREG_SPLIT_NO_EMPTY);
					$rel_cond[$ag] = $rel_parts;
					$has_rel_cond = true;
				}
				else
				{
					$a_grps[$ag] = $ag;
					$rel_cond[0][$ag] = $ag;
				}
			}
			
			if ($has_rel_cond)
				$relation_conditions[implode("+", $and_groups)] = $rel_cond;
			
			if ($a_grps && (!$has_rel_cond))
			{
				ksort($a_grps);
				$ret[implode("+", $a_grps)] = (count($a_grps) > 1) ? $a_grps : reset($a_grps);
			}
		}
		
		return [$ret, $relation_conditions];
	}
	
	protected function parseGroupRelationsToConditions($rel_grps, $rule_index, $property)
	{
		$prepend_IF = null;
		$prepend_EACH = null;
		$prepend_ANY = null;
		
		foreach ($rel_grps as $rg)
		{
			$static_grps = $rg[0];
			// $relation_conditions ... the rest
			foreach ($rg as $rg_k => $_rg_condition)
			{
				if ($rg_k === 0)
					continue;
				$rg_condition = $_rg_condition;
				$target = end($rg_condition);
				if (($target !== '$this') && ($target !== '$any') && ($target !== '$each'))
				{
					$target = '$this';
				}
				else
					array_pop($rg_condition);

				$r_cond = "("; // \$cond_grp_{$rule_index} || 
				if ($static_grps)
				{
					// append static groups condition
					$and = [];
					foreach ($static_grps as $i)
						$and[] = "\$groups[".var_export($i, true)."]";
					$r_cond .= "((".implode(" && ", $and).") && ";
				}
				
				$selector_str = (($target === '$this') || ($target === '$value')) ? "" : (($target === '$each') ? ", '{$property}'" : ", ".$this->getRelationsSelectorVarNameForAny($rg_condition));
				$r_cond .= (($target === '$this') ? '$value' : $target)."->userHasRelation('".implode(":", $rg_condition)."', \$values{$selector_str})";
				if ($static_grps)
				{
					$r_cond .= ")";
				}
				$r_cond .= ")";

				if (($target === '$this') || ($target === '$value'))
					$prepend_IF[] = $r_cond;
				else if ($target === '$any')
					$prepend_ANY[] = $r_cond;
				else if ($target === '$each')
					$prepend_EACH[] = $r_cond;
				}
		}
		
		$prepend_IF = $prepend_IF ? "\$cond_grp_{$rule_index}_if = (\$cond_grp_{$rule_index} || ".implode(" || ", $prepend_IF).")" : null;
		$prepend_EACH = $prepend_EACH ? "\$cond_grp_{$rule_index}_each = (\$cond_grp_{$rule_index} || \$cond_grp_{$rule_index}_if  || ".implode(" || ", $prepend_EACH).")" : null;
		$prepend_ANY = $prepend_ANY ? "\$cond_grp_{$rule_index} || \$cond_grp_{$rule_index}_if || \$cond_grp_{$rule_index}_each || ".implode(" || ", $prepend_ANY) : null;
		
		return [$prepend_IF, $prepend_EACH, $prepend_ANY];
	}
	
	protected function parseCondition($condition, $property = null, $parsed_groups = null, $rule_index = null)
	{
		if (!$condition)
			return [null, true];
		
		$condition = trim($condition);
		
		if ($parsed_groups && ($rel_grps = $parsed_groups[1]))
		{
			// we need to move the rule to the condition
			list($prepend_IF, $prepend_EACH, $prepend_ANY) = $this->parseGroupRelationsToConditions($rel_grps, $rule_index, $property);
		}
		
		// => $this -> $this->$property | if property is collection use foreach / condition (when static)
		$prop_inf = $property ? \QModelQuery::GetTypesCache($this->className)[$property] : null;
		$is_collection = $prop_inf && $prop_inf['[]'];
		
		$toks = token_get_all("<?php ".$condition);
		
		// remove <?php
		array_shift($toks);
		$is_static = true;
		// the props are expected NOT to be static as they could have been placed on the current prop otherwise
		// $is_static_props = false;
		
		$ret_IF = [];
		$ret_IF_ANY = [];
		$ret_IF_EACH = [];
		
		// the default is pointing to IF
		$ret = &$ret_IF;
		$zone = 'IF';
		
		foreach ($toks as $tok)
		{
			if (is_array($tok))
			{
				if (($tok[0] === T_VARIABLE) && ($tok[1] === '$this'))
				{
					if ($zone === 'IF')
						$is_static = false;
					$ret[] = '$value';
				}
				else if (($tok[0] === T_STRING) && ($tok[1] === 'IF_ANY'))
				{
					unset($ret); // unlink it first
					$ret = &$ret_IF_ANY;
					$zone = 'IF_ANY';
					if ($ret_IF_ANY)
						$ret[] = " && ";
				}
				else if (($tok[0] === T_STRING) && ($tok[1] === 'IF_EACH'))
				{
					unset($ret); // unlink it first
					$ret = &$ret_IF_EACH;
					$zone = 'IF_EACH';
					if ($ret_IF_EACH)
						$ret[] = " && ";
				}
				else if (($tok[0] === T_STRING) && ($tok[1] === 'IF_COND'))
				{
					unset($ret); // unlink it first
					$ret = &$ret_IF;
					$zone = 'IF';
					if ($ret_IF)
						$ret[] = " && ";
				}
				else
					$ret[] = $tok[1];
			}
			else
				$ret[] = $tok;
		}
		
		$ret_IF = $ret_IF ? trim(implode("", $ret_IF)) : null;
		$ret_IF_ANY = $ret_IF_ANY ? trim(implode("", $ret_IF_ANY)) : null;
		$ret_IF_EACH = $ret_IF_EACH ? trim(implode("", $ret_IF_EACH)) : null;
		
		if ($prepend_IF)
			$ret_IF = "(".trim($prepend_IF).") && (".trim($ret_IF).")";
		if ($prepend_ANY)
			$ret_IF_ANY = "(".trim($prepend_ANY).") && (".trim($ret_IF_ANY).")";
		if ($prepend_EACH)
			$ret_IF_EACH = "(".trim($prepend_EACH).") && (".trim($ret_IF_EACH).")";
		
		return [$ret_IF, $is_static, $ret_IF_ANY, $ret_IF_EACH];
	}
	
	protected function splitOnByLevels(array $on, string $prefix = "", array &$levels = null)
	{
		if ($levels === null)
			$levels = [];
		foreach ($on as $k => $v)
		{
			/*if (($k === 0) && ($v === true))
				$levels[$prefix][$k] = true;
			else */
			if (is_array($v))
			{
				$req_count = 1;
				if ($v[0] === true)
				{
					$levels[$prefix][$k] = [];
					$req_count = 2;
				}
				if (count($v) >= $req_count)
					$this->splitOnByLevels($v, $prefix ? $prefix.'.'.$k : $k, $levels);
			}
		}
		return $levels;
	}
	
	protected function createFunctionString(array $rules)
	{
		/** @TODO - setup caching capabilities here
				if is static | it's not dependent on \$this
				caching per rule !
				caching per ID
		 */
		
		$rules_code = [];
		foreach ($rules as $r_index => $r)
		{
			list($action_flags, $action_list, $on, $groups, $condition, $rule_str, $rule_property) = $r;
			$original_on = $on;
			if ($on && is_array($on))
			{
				$levels = $this->splitOnByLevels($on);
			}
			else
			{
				if ($rule_property)
					throw new \Exception("We should always have ON if we have a property. (to double check)");
				// not having on
				$levels[""]["class"][0] = true;
			}
			
			list($str_top, $str_body, $str_bottom) = $this->createFunctionStringForRule([$action_flags, $action_list, $r_index, $levels, $groups, $condition, $rule_str, $rule_property, $original_on]);
			$rules_code[0][] = $str_top;
			$rules_code[1][] = $str_body;
			$rules_code[2][] = $str_bottom;
		}
		
		return "	public static function UserCanRaw(array \$values = null, int \$can_flag = null, array \$can_list = null, \$selector = null, array \$groups = null)
	{
		// @TODO - inject caching here
		\$cache = [];
".implode("", $rules_code[0])."
		foreach (\$values as \$value)
		{
".implode("", $rules_code[1])."
		}
".implode("", $rules_code[2])."
	}\n\n";
	}
	
	protected function createFunctionStringForRule($rule)
	{
		list($action_flags, $action_list, $rule_index, $on_levels, $groups_data, $condition, $rule_str, $rule_property, $original_on) = $rule;
		
		list($groups, $groups_relations) = $groups_data;
		
		// THIS IS PER CONDITION
		
		$action_flags_str = (string)$action_flags;
		$action_list_str = qArrayToCode($action_list, null, false, null, 1, false, false);
		list($condition_str, $condition_is_static, $condition_for_any, $condition_for_each) = $condition;
		
		// @TODO - finish - GROUPS, ON | ON it's easier at this stage
		//						FOR GROUPS we need to do joined groups: admin+fuse2,superadmin
		//						IT's easy ... generate code as needed !!! ($groups[admin] && $groups[fuse2] && ...) || $groups[superadmin]
		//						Drop
		
		$grp_conditions = [];
		foreach ($groups ?: [] as $group => $v)
		{
			if (is_array($v))
			{
				$and = [];
				foreach ($v as $i)
					$and[] = "\$groups[".var_export($i, true)."]";
				$grp_conditions[] = "(".implode(" && ", $and).")";
			}
			else
				$grp_conditions[] = "\$groups[".var_export($group, true)."]";
		}
		$grp_condition_str = $grp_conditions ? "(".implode(" || ", $grp_conditions).")" : "";
		
		$on_conditions = [];
		/*
		foreach ($on ?: [] as $prop => $v)
			$on_conditions[] = "\$on[".var_export($prop, true)."]";
		// ((is_string(\$on) && __SEC_EXTR_ON[$on]) || (\$on_count = count(array_intersect_key(\$on, __SEC_EXTR_ON)))) &&
		if ($on && is_array($on))
		{
			// $on_cond
			$on_condition_str = "((\$on !== null) && (((\$on_count === 0) && \$on_cond[\$on]) || (\$on_count === count(array_intersect_key(\$on, \$on_cond)))))";
		}
		else if (is_string($on))
		{
			// one property condition
			$on_condition_str = "(\$on === ".var_export($on, true).")";
		}
		else // must be null here
		{
			$on_condition_str = "(\$on === null)";
		}
		*/
		
		$cond_list = ["((\$can_flag === null) || \$can_flag_jc_{$rule_index} || \$can_list_jc_{$rule_index})"];
		
		if ($grp_condition_str && (!$groups_relations))
			$cond_list[] = $grp_condition_str;
		/*if ($on_condition_str)
			$cond_list[] = $on_condition_str;*/
		if ($condition_is_static && $condition_str)
			$cond_list[] = $condition_str;
		
		$_scf_str = "";
		$_sl_str = "";
		
		$indx = 1;
		
		$last_level_k = null;
		
		$apply_str = "";
		
		$vars_declared = [];
		$vars_per_stack = [];
		
		$value_name = $item_name = null;
		
		if ($groups_relations)
		{
			$groups_relations_grps = [];
			$groups_relations_grps_str = "";
			$put_grp_rel_conditions = true;
			
			foreach ($groups_relations as $gr)
			{
				foreach ($gr as $k => $i)
				{
					if (($k === 0) || (!is_array($i)))
						continue;
					
					$target = end($i);
					if ($target === '$any')
					{
						$i_copy = $i;
						array_pop($i_copy);
						$rel_sel_any_name = $this->getRelationsSelectorVarNameForAny($i_copy);
						$top_str .= "\t\t{$rel_sel_any_name} = ".qArrayToCode($original_on, null, false, null, 1, false, false).";\n";
					}
				}
			}
			
			foreach ($groups_relations as $gr)
			{
				if (!$gr[0])
				{
					$put_grp_rel_conditions = false;
					break;
				}
				
				$and = [];
				foreach ($gr[0] as $i)
					$and[] = "\$groups[".var_export($i, true)."]";
				$groups_relations_grps[] = "(".implode(" && ", $and).")";
			}
			
			$groups_relations_grps_str = implode(" || ", $groups_relations_grps);
			
			if ($grp_conditions)
				$top_str .= "\t\t\$cond_grp_{$rule_index} = {$grp_condition_str};\n";
			else
				// we need to set it atm as we use it in parseConditions
				$top_str .= "\t\t\$cond_grp_{$rule_index} = false;\n";
			$top_str .= "\t\t\$cond_grp_{$rule_index}_if = false;\n";
			$top_str .= "\t\t\$cond_grp_{$rule_index}_each = false;\n";
			
			if ($put_grp_rel_conditions && $groups_relations_grps_str)
			{
				$top_str .= "\t\t\$cond_part_{$rule_index} = ".implode(" && ", $cond_list)." && (\$cond_grp_{$rule_index} || {$groups_relations_grps_str});\n";
			}
			else
			{
				// there is no point to add any more group conditions, we will need to always validate items anyway
				$top_str .= "\t\t\$cond_part_{$rule_index} = ".implode(" && ", $cond_list).";\n";
			}
		}
		else
		{
			$top_str .= "\t\t\$cond_part_{$rule_index} = ".implode(" && ", $cond_list).";\n";
		}
		
		foreach ($on_levels ?: [] as $k => $v)
		{
			// @todo handle $each for collection properties
			if ($k && ($k !== $last_level_k))
			{
				
				// open x foreach loops if needed or close
				$k_split = explode('.', $k);
				$c_k_split = count($k_split);
				$last_split = $last_level_k ? explode('.', $last_level_k) : [];
				$c_last_split = count($last_split);
				
				list(, $can_be_object, $can_be_collection) = $this->determineSelectorOptions($k_split);
				
				// skip common elements
				for ($i = 0; $i < $c_last_split; $i++)
				{
					if ($last_split[$i] !== $k_split[$i])
						break;
				}
				$common_i = $i;
				// close any opened elements
				$z = 0;
				for (($i = $common_i); $i < $c_last_split; $i++)
					$apply_str .= "\t\t\t\t".str_pad("", ($c_last_split - ($z++) - 1), "\t")."}\n";
				// open foreach for remaining elements
				for (($i = $common_i); $i < $c_k_split; $i++)
				{
					$var_offset = $i;
					do
					{
						$new_var_name = "\$_".($i > 0 ? "_" : "").implode("_", array_slice($k_split, $var_offset, $i + 1));
						// $vars_per_stack
						if (!$vars_declared[$new_var_name])
						{
							$item_name = $vars_per_stack[implode(".", array_slice($k_split, 0, $i + 1))] = $vars_declared[$new_var_name] = $new_var_name;
							break;
						}
						else
							$var_offset--;
					}
					while (true);
					if ($i === 0)
						$value_name = "\$value"."->".$k_split[$i];
					else
						$value_name = $vars_per_stack[implode(".", array_slice($k_split, 0, $i))]."->".$k_split[$i];
					
					$we_are_at_property = $rule_property && ($i === 0) && ($k === $k_split[0]);
					
					$extra_var = "";
					$extra_cond = "";
					if ($we_are_at_property)
					{
						if ($condition_for_each)
						{
							$extra_var = "\$each = ";
							$extra_cond .= " && ({$condition_for_each})";
						}
					}
					else if ($condition_for_any)
					{
						// do not apply ANY at the propety level !
						$extra_var = "\$any = ";
						$extra_cond .= " && ({$condition_for_any})";
					}
					
					if ($can_be_collection)
					{
						if ($can_be_object)
						{
							$apply_str .= "\t\t\t\t".str_pad("", $i, "\t").
											"if ((!is_array({$value_name})) && (!({$value_name} instanceof QIModelArray))) {$value_name} = [{$value_name}];\n";
						}
						
						$apply_str .= "\t\t\t\t".str_pad("", $i, "\t").
										"foreach ({$value_name} ?: [] as {$item_name})\n".
											"\t\t\t\t".str_pad("", $i, "\t")."{\n";
						
						if ($we_are_at_property)
						{
							if ($condition_for_each)
							{
								// print each
								$condition_for_each_negative_str = $condition_for_each ? "(\$each = {$item_name}) && (!({$condition_for_each}))" : "";
								$apply_str .= "\t\t\t\t\t".str_pad("", $i, "\t").
											"if ({$condition_for_each_negative_str})\n".
												"\t\t\t\t\t\t".str_pad("", $i, "\t")."continue;\n";
							}
						}
						else if ($condition_for_any)
						{
							// do not apply ANY at the propety level !
							$condition_for_any_negative_str = $condition_for_any ? "(\$any = {$item_name}) && (!({$condition_for_any}))" : "";
							$apply_str .= "\t\t\t\t\t".str_pad("", $i, "\t").
										"if ({$condition_for_any_negative_str})\n".
											"\t\t\t\t\t\t".str_pad("", $i, "\t")."continue;\n";
						}
					}
					else
					{
						$apply_str .= "\t\t\t\t".str_pad("", $i, "\t").
										"if (({$item_name} = {$extra_var}{$value_name}){$extra_cond})\n".
									"\t\t\t\t".str_pad("", $i, "\t")."{\n";
					}
				}
			}
			
			foreach ($v as $_vk => $vv)
			{
				$vk = ($_vk === 'class') ? 0 : "'{$_vk}'";
				
				if ($k === "")
				{
					$_scf_str .= "\t\t\t\t\t\$value->_scf[{$vk}] |= \$can_flag_jc_{$rule_index};\n";
					$_sl_str .=  "\t\t\t\t\tif (!\$value->_sl[{$vk}])\n".
						"\t\t\t\t\t\t\$value->_sl[{$vk}] = \$can_list_jc_{$rule_index};\n".
					"\t\t\t\t\telse\n".
						"\t\t\t\t\t\t\$value->_sl[{$vk}] += \$can_list_jc_{$rule_index};\n";
				}
				else // if ($condition_is_static && (!$condition_for_props))
				{
					$pad_depth = count(explode(".", $k));
					
					if ($action_flags)
					{
						// $allowed_by_val_str_0 .= "\t\t\$allowed['".implode("']['", explode('.', $k))."'][-1][0][{$vk}] |= {$action_flags_str};\n";
						$apply_str .= str_pad("", $pad_depth, "\t")."\t\t\t\t{$item_name}->_scf[{$vk}] |= {$action_flags_str};\n";
					}
					if ($action_list)
					{
						// $allowed_by_val_str_0 .= "\t\t\$alw_{$indx} = &\$allowed['".implode("']['", explode('.', $k))."'][-1];\n"; // [0][{$vk}]
						// $allowed_by_val_str_0 .= "\t\t\$alw_{$indx}[1][{$vk}] = (\$alw_{$indx}[1][{$vk}] ?: []) + {$action_flags_str};\n";
						// $allowed_bottom[] = "\$alw_{$indx}";
						$apply_str .= str_pad("", $pad_depth, "\t")."\t\t\t\t{$item_name}->_sl[{$vk}] = {$item_name}->_sl[{$vk}] ? ({$item_name}->_sl[{$vk}] + {$action_flags_str}) : {$action_flags_str};\n";
					}

					$indx++;
				}
			}
			
			if ($k && ($k !== $last_level_k))
			{
				
			}
			
			$last_level_k = $k;
		}
		
		// at the end close for the last element
		$last_split = $last_level_k ? explode('.', $last_level_k) : [];
		$c_last_split = count($last_split);
		// close any opened elements
		for ($i = 0; $i < $c_last_split; $i++)
			$apply_str .= "\t\t\t".str_pad("", ($c_last_split - $i), "\t")."}\n";
		
		$compact_rule_str = "";
		$rule_str_parts = implode(" ", preg_split("/(\\s*\\r?\\n\\s*)/us", $rule_str, -1, PREG_SPLIT_NO_EMPTY));
		
		$sec_rule_str = "\t\t# Security Rule #{$rule_index}:".($rule_property ? " \${$rule_property}" : "")." {$rule_str_parts}\n";
		
		$body[0] = $sec_rule_str.
($action_flags ? "\t\t"."\$can_flag_jc_{$rule_index} = (\$can_flag !== null) ? \$can_flag & {$action_flags_str} : {$action_flags_str};\n" : 
					"\t\t"."\$can_flag_jc_{$rule_index} = null;\n").
($action_list ? "\t\t"."\$can_list_jc_{$rule_index} = \$can_list ? array_intersect_key(\$can_list, {$action_list_str}) : {$action_list_str};\n" :
				"\t\t"."\$can_list_jc_{$rule_index} = null;\n").
// (is_array($on) ? "\t\t"."\$on_cond_{$rule_index} = ".qArrayToCode($on, null, false, null, 1, false, false).";\n" : "").
				$top_str.
			"\n";

		if ($condition_for_each && ($on_levels[""] || $on_levels["property"] || $on_levels["class"]))
		{
			throw new \Exception("You can not target a property or a class from an each condition");
		}
		else if ($rule_property && $on_levels["class"])
		{
			throw new \Exception("You can not target a class from a property");
		}

		$body[1] = "\t".$sec_rule_str.
"			if (\$cond_part_{$rule_index}".(((!$condition_is_static) && $condition_str) ? " && (".$condition_str.")" : "").")
			{
".($on_levels[""] ? 
"				if (\$can_flag_jc_{$rule_index})
				{
{$_scf_str}				}
".($action_list ? 
"				if (\$can_list_jc_{$rule_index})
				{
".$_sl_str.
"				}
" : "").
"" : "").$apply_str.
"
			}

";

		$body[2] = "";
						
		return $body;
	}
	
	protected function getRelationsSelectorVarNameForAny($rg_condition)
	{
		return "\$rel_sel_any_".(implode("___", $rg_condition));
	}
	
	protected static function ExtractSecurityFromTokens(\QPHPToken $tokens)
	{
		$d_comment = $tokens->docComment;
		$str = ($d_comment instanceof \QPHPToken) ? $d_comment."" : (is_array($d_comment) ? $d_comment[1] : $d_comment);
		
		$has_security = strpos($str, "@security.");
		if (!$has_security)
			return null;
		
		$parts = \QCodeStorage::parseDocComment($str);
		if (!($security = $parts["security"]))
			return null;
		
		$rules = [];
		foreach ($security as $s_key => $s_value_list)
		{
			if (($s_key === 'allow') || ($s_key === 'deny'))
			{
				if (is_array($s_value_list))
				{
					foreach ($s_value_list as $s_value)
						$rules[] = [$s_key, $s_value];
				}
			}
		}
		return $rules ?: null;
	}
}

