<?php

trait QCodeSync2_Gen_Model
{
	protected static $PreparedFixers = null;
	protected static $PreparedValidators = null;
	protected static $PreparedEncoders = null;
	
	protected function generate_model_methods(ReflectionClass $src_class)
	{
		// var_dump("generateModelMethods", $element->classPath);
		
		// TO DO: WE NEED THE NAMESPACE !!!
		//echo "<textarea>{$source_tokens}</textarea>";
		$parent_class = $src_class->getParentClass();
		// throw new \Exception('we need the parents stack within the same BASE class !');
		
		foreach ($src_class->getProperties() as $prop)
		{
			// ignore statics
			if ($prop->isStatic() || ($prop->name === 'Del__') || ($prop->name{0} === '_'))
				continue;
			
			$meth_name = "set".ucfirst($prop->name)."";
			
			// if the method is user defined continue
			if ($parent_class->hasMethod($meth_name))
				continue;
			
			$type_inf = null;
			$doc_comm = $prop->getDocComment();
			$parsed = null;
			$parsed_data = [];
			if ($doc_comm)
			{
				// $this::parseDocComment($prop->getDocComment(), false, $ref_class->getNamespaceName());
				$parsed_data = $parsed = QCodeStorage::parseDocComment($doc_comm, false, $parent_class->getNamespaceName());
				$type_inf = $parsed ? $parsed["types"] : null;
			}
			
			if (!$type_inf)
				continue;
			
			$meth_str = $this->generateModelMethodsForType("\$this->".$prop->name, $prop, $parsed_data, $meth_name, $type_inf, false, (strtolower($prop->name) === "id"));
			
			if ($meth_str)
			{
				if (is_array($meth_str))
				{
					foreach ($meth_str as $m_name => $meth_body)
						$meths_to_set[$m_name] = $meth_body;
				}
				else
					$meths_to_set[$meth_name] = $meth_str;
			}
		}
		
		return $meths_to_set;
	}
	
	protected function generateModelMethodsForType($assign_to, ReflectionProperty $m_property, $parsed_data, $meth_name, $type_inf, $for_array = false, $for_id = false)
	{
		// fix type info a bit if possible
		if (is_array($type_inf) && (next($type_inf) === false))
			$type_inf = reset($type_inf);
		
		// now create casts for $type_inf
		$is_ok = null;
		$cast_str = null;
		$get_type = "";
		$before = "";
		$custom_assign = null;
		
		$fixval_str = null;
		$validation_str = null;

		if ($parsed_data["fixValue"])
			$fixval_str = self::GetFixValStr($parsed_data["fixValue"], $for_array);
		if ($parsed_data["encode"])
			$encode_str = self::GetEncodeValStr($parsed_data["encode"], $for_array);
		if ($parsed_data["validation"])
			$validation_str = self::GetValidationStr($parsed_data["validation"], $for_array);
		
		$acc_type = null;
		$valid_type = false;
		
		$possible_collection = false;
		$collection_only = false;

		if (is_string($type_inf))
		{
			list ($is_ok, $cast_str, $valid_type, $remove_thous_sep) = $this->generateModelMethodsGetParamsForType($type_inf);
		}
		else if ($type_inf instanceof QModelAcceptedType)
		{
			list ($is_ok, $cast_str, $valid_type, $remove_thous_sep) = $this->generateModelMethodsGetParamsForType($type_inf->type);
			$acc_type = $type_inf instanceof QModelAcceptedType ? $type_inf : null;
			$possible_collection = true;
			$collection_only = true;
		}
		else if (is_array($type_inf))
		{
			$accepted_obj_types = array();
			$accepted_scalar_types = array();
			
			// var_dump($type_inf);
			
			// all needs to be valid
			$valid_type = true;
			// now we will need to loop a bit
			// fill: $is_fail, $cast_str
			foreach ($type_inf as $_tyinf)
			{
				$use_ty = $_tyinf instanceof QModelAcceptedType ? $_tyinf->type : $_tyinf;
				$type_extracted_inf = $this->generateModelMethodsGetParamsForType($use_ty, true);

				if ($type_extracted_inf[3])
				{
					// it seems that we have this case and it is not treated
					//throw new \Exception("Thousands separator not implemented for mixed types");
				}
				
				// all needs to be valid
				$valid_type = $valid_type && $type_extracted_inf[0];
				
				if ($_tyinf instanceof QModelAcceptedType)
				{
					$accepted_obj_types[$use_ty] = $type_extracted_inf;
					$acc_type = $_tyinf;
					$possible_collection = true;
				}
				else if ($_tyinf{0} !== strtolower($_tyinf{0}))
					$accepted_obj_types[$use_ty] = $type_extracted_inf;
				else
					$accepted_scalar_types[$use_ty] = $type_extracted_inf;
			}
			
			// var_dump($accepted_obj_types);
			
			if ($valid_type)
			{
				$custom_assign = "";

				// var_dump($accepted_obj_types, $accepted_scalar_types);

				$scalar_types_count = $accepted_scalar_types ? count($accepted_scalar_types) : 0;

				$assign_scalar = null;
				// $cast_scalar = null;

				if ($accepted_scalar_types)
				{
					if ($scalar_types_count === 1)
					{
						// we will try to cast
						list(, $cast_str) = reset($accepted_scalar_types);
						$assign_scalar = "{$cast_str}\$value";
					}
					else
					{
						// to do, also populate aliases
						// exclusive or error
						$before = "\n\t\t\$posible_scalars = array('".implode("' => true, '", $this->getCompatbilePHPTypes(array_keys($accepted_scalar_types)))."' => true);";
						$assign_scalar = "\$posible_scalars[gettype(\$value)] ? \$value : (\$fail = null)";
					}
				}

				if ($accepted_obj_types)
				{
					$custom_assign .= "is_object(\$value) ? ((";
					$first = true;
					
					foreach ($accepted_obj_types as $oty => $oty_inf)
					{
						if (!$first)
							$custom_assign .= " || ";
						else
							$first = false;
						$custom_assign .= "((!__IN_PHP__) || (\$value instanceof \\{$oty}))";
					}
					$custom_assign .= ") ? \$value : (\$fail = null))";

					if (!$accepted_scalar_types)
						$custom_assign .= " : (\$fail = null)";
				}

				if ($accepted_scalar_types)
				{
					if ($accepted_obj_types)
						$custom_assign .= " : (";

					$custom_assign .= $assign_scalar;

					if ($accepted_obj_types)
						$custom_assign .= ")";
				}

				// stuff for: $custom_assign & $before

				$custom_assign = "(!\$check || (\$value === null)) ? \$value : (".$custom_assign.")";
			}
			// throw new Exception("to do");
		}
		else
		{
			qvar_dumpk($type_inf);
			throw new Exception("Bad type parsing. This should not be");
		}

		if ($valid_type)
		{
			$acc_type_meth_name = $meth_name."_Item_";
			$acc_type_str = null;
			if ($acc_type)
			{
				// to do ... create Setter/checker for it
				// set{$PropertyName}_Item_
				
				$acc_type_str = $this->generateModelMethodsForType($assign_to, $m_property, $parsed_data, $acc_type_meth_name, $acc_type->options, true);
				if (!$acc_type_str)
					$valid_type = false;
			}
			
			$property_name = $m_property->name;
			
			// $validation_str
			if ($validation_str)
			{
				if ($collection_only)
				{
					// @todo different for collection only
				}
				else if ($possible_collection)
					$is_ok = "(((!__IN_PHP__) || (\$value instanceof QIModelArray)) ? (".($is_ok ? $is_ok." && " : "")."({$validation_str})".") : (".($is_ok ?: "")."))";
				else 
					$is_ok = ($is_ok ? $is_ok." && " : "")."({$validation_str})";
			}
			
			$str = "	public function {$meth_name}(\$value, ".($for_array ? "\$key = null, \$row_id = null, " : "")."\$check = true, \$null_on_fail = false)
	{
		\$fail = false;{$get_type}{$before}".($possible_collection ? "
		if (is_array(\$value) && (\$check !== 1))
			\$value = new \QModelArray(\$value);" : "").(($fixval_str || $remove_thous_sep) ? ("
		\$value = ".($remove_thous_sep ? "str_replace(Q_Thousands_Separator, '', " : "(").
						($fixval_str ? "{$fixval_str});\n" : "\$value);\n")) : "").
			($encode_str ? "
		\$value = {$encode_str};" : "").
				"
		\$return = ".
				($custom_assign ?: "((\$check === false) ".($validation_str ? "" : " || (\$value === null)").") ? \$value : ".
					($is_ok ? "({$is_ok} ? {$cast_str}\$value : (\$fail = null))" : "{$cast_str}\$value")).";
		if ((\$fail === null) && (!\$null_on_fail))
			throw new \Exception(\"Failed to assign value in {$meth_name}\");
		if (\$check !== 1)
		{
			{$assign_to}".($for_array ? "[\$key]" : "")." = \$return;".
			($for_array ? "
			if ((\$key !== null) && (\$row_id !== null))
				{$assign_to}->setRowIdAtIndex(\$key, \$row_id);" : "").
			($for_id ? "
			\$this->_id = (is_string(\$return) && empty(\$return)) ? null : \$return;" : "")."
			\$this->".($for_array ? "{$property_name}->_wst[\$key]" : "_wst[\"{$property_name}\"]")." = true;
		}
		return \$return;
	}\n";

			return $acc_type_str ? array($meth_name => $str, $acc_type_meth_name => $acc_type_str) : $str;
		}
		else
			return null;
	}

	/**
	 * Sub method for generateModelMethods
	 * 
	 * @param string|QModelAcceptedType $type_inf
	 * @return string[]
	 * @throws Exception
	 */
	protected function generateModelMethodsGetParamsForType($type_inf)
	{
		$is_ok = null;
		$cast_str = null;
		$valid_type = false;
		
		$remove_thous_sep = false;
		
		if ($type_inf instanceof QModelAcceptedType)
		{
			$is_ok = "((!__IN_PHP__) || (\$value instanceof \\QIModelArray))";
			$valid_type = true;
		}
		else if ($type_inf{0} !== strtolower($type_inf{0}))
		{
				// var_dump("A: ".$type_inf);
			// reference
			$is_ok = "((!__IN_PHP__) || (\$value instanceof \\{$type_inf}))";
			$valid_type = true;
		}
		else 
		{
			switch ($type_inf)
			{
				case "int":
				case "integer":
				{
					$cast_str = "(int)";
					$valid_type = true;
					$remove_thous_sep = true;
					break;
				}
				case "float":
				case "double":
				{
					$cast_str = "(float)";
					$remove_thous_sep = true;
					$valid_type = true;
					break;
				}
				case "string":
				{
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "bool":
				case "boolean":
				{
					$cast_str = "(bool)";
					$valid_type = true;
					break;
				}
				case "date":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "datetime":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "array":
				{
					// parse date
					// throw new Exception("to do");
					$cast_str = "(array)";
					$valid_type = true;
					break;
				}
				case "time":
				{
					// parse date
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				case "file":
				{
					// to do
					// throw new Exception("to do");
					$cast_str = "(string)";
					$valid_type = true;
					break;
				}
				default:
				{
					// see: QModel::GetTypeByName
					// throw new Exception("to do: ".$type_inf);
					$valid_type = false;
					break;
				}
			}
		}
		
		return array($is_ok, $cast_str, $valid_type, $remove_thous_sep);
	}
	
	
	public static function GetFixValStr($cleanup_rule, $for_array = null, $value_replacement = null)
	{
		$cleanups = self::GetPreparedDataFixers();
		$rules = self::ParseValidationRules($cleanup_rule);
		return self::GetRuleStr($cleanups, $rules, $value_replacement);
	}
	
	public static function ParseValidationRules($rule)
	{
		$toks = token_get_all("<?php ".trim($rule));
		array_shift($toks);
		return $toks;
	}
	
	public static function GetPreparedDataFixers()
	{
		if (self::$PreparedFixers !== null)
			return self::$PreparedFixers;
		
		return self::$PreparedFixers = self::GetPreparedData(QAutoload::GetDataFixers());
	}
	
	public static function GetPreparedDataEncoders()
	{
		if (self::$PreparedEncoders !== null)
			return self::$PreparedEncoders;
		return self::$PreparedEncoders = self::GetPreparedData(QAutoload::GetDataEncoders());
	}

	
	protected static function GetPreparedData($fixers)
	{
		$ret = [];
		foreach ($fixers as $k => $_v)
		{
			$key_parsed = self::ParseValidationRules($k);
			$index = null;
			$vars = [];
			
			$v = is_array($_v) ? reset($_v) : $_v;
			$error_message = is_array($_v) ? next($_v) : null;
			$info_message = is_array($_v) ? next($_v) : null;

			foreach ($key_parsed as $kp)
			{
				if (is_array($kp))
				{
					if ((!$index) && ($kp[0] === T_STRING))
						$index = strtolower($kp[1]);
					else if ($kp[0] === T_VARIABLE)
						$vars[$kp[1]] = $kp[1];
				}
			}
			if ($index)
				$ret[$index] = [$key_parsed, self::ParseValidationRules($v), $vars, $error_message, $info_message];
		}
		return $ret;
	}
	
	
	protected static function GetRuleStr($parsed_rules, $current_rule, $value_replacement = null, $is_validation = false)
	{
		// var_dump($parsed_rules, $current_rule);
		$rule = "";
		$cr = current($current_rule);
		$has_mandatory = false;
	
		while ($cr)
		{
			$is_arr = is_array($cr);
			$txt = $is_arr ? $cr[1] : $cr;
			$txt_lc = strtolower($txt);
			if ($is_arr && ($cr[0] === T_STRING) && (($parsed_r = $parsed_rules[$txt_lc])))
			{
				if ($txt_lc === "mandatory")
					$has_mandatory = true;
				$rule .= "(";
				// we have a match, try to replace 
				// var_dump($parsed_r);

				list($parsed_r_def, $parsed_r_code, $parsed_r_vars /*, $error_message */) = $parsed_r;

				if ($parsed_r_vars)
				{
					$parsed_r_vars_by_index = array_values($parsed_r_vars);
					// 1. determine the values for the variables
					// 2. replace varibales in code
					// 3. replace entire block
					$values = [];
					$val_pos = 0;
					$var_txt = "";
					$bra_depth = 0;
					
					// T_LNUMBER || T_CONSTANT_ENCAPSED_STRING | T_DNUMBER | T_STRING && false true
					while (($cr = next($current_rule)))
					{
						$cr_isarr = is_array($cr);
						// $cr_type = $cr_isarr ? $cr[0] : null;
						$cr_txt = $cr_isarr ? $cr[1] : $cr;
						
						/*
						if (($cr_type === T_LNUMBER) || ($cr_type === T_CONSTANT_ENCAPSED_STRING) || ($cr_type === T_DNUMBER) || 
								(($cr_type === T_STRING) && ((strtolower($cr_txt) === "true") || (strtolower($cr_txt) === "false"))))
						{
							$values[$parsed_r_vars_by_index[$val_pos]] = $cr_txt;
							$val_pos++;
						}
						*/
						if ($cr_txt === "(")
						{
							if ($bra_depth > 0)
								$var_txt .= $cr_txt;
							$bra_depth++;
						}
						else if ($cr_txt === ")")
						{
							$bra_depth--;
							if ($bra_depth === 0)
								break;
							else
								$var_txt .= $cr_txt;
						}
						else if (($bra_depth === 1) && ($cr_txt === ","))
						{
							$values[$parsed_r_vars_by_index[$val_pos]] = $var_txt;
							$val_pos++;
							$var_txt = "";
						}
						else
						{
							$var_txt .= $cr_txt;
						}
					}
					
					if (strlen(trim($var_txt)) > 0)
					{
						$values[$parsed_r_vars_by_index[$val_pos]] = $var_txt;
						$val_pos++;
						$var_txt = "";
					}
					
					foreach ($parsed_r_code as $pr)
					{
						$pr_isarr = is_array($pr);
						$pr_type = $pr_isarr ? $pr[0] : null;
						$pr_txt = is_array($pr) ? $pr[1] : $pr;
						if (($pr_type === T_VARIABLE) && (($replacement = $values[$pr_txt]) !== null))
							$rule .= $replacement;
						else if ($value_replacement && ($pr_type === T_VARIABLE) && ($pr_txt === "\$value"))
							$rule .= $value_replacement;
						else
							$rule .= $pr_txt;
					}
				}
				else
				{
					foreach ($parsed_r_code as $pr)
					{
						$sub_isarr = is_array($pr);
						$sub_txt = $sub_isarr ? $pr[1] : $pr;
						if ($value_replacement && $sub_isarr && ($pr[0] === T_VARIABLE) && ($sub_txt === "\$value"))
							$rule .= $value_replacement;
						else
							$rule .= $sub_txt;
					}
				}
				$rule .= ")";
			}
			else if ($value_replacement && $is_arr && ($cr[0] === T_VARIABLE) && ($txt === "\$value"))
			{
				$rule .= $value_replacement;
			}
			else
			{
				$rule .= $txt;
			}
			$cr = next($current_rule);
		}

		if ($is_validation && (!$has_mandatory))
		{
			// loose rule, accept empty
			$val_str = $value_replacement ?: "\$value";
			$rule = "(({$val_str} === null) || ({$val_str} === \"\") || {$rule})";
		}
		return $rule;
	}

	public static function GetEncodeValStr($encode_rule, $for_array = null, $value_replacement = null)
	{
		$cleanups = self::GetPreparedDataEncoders();
		$rules = self::ParseValidationRules($encode_rule);
		return self::GetRuleStr($cleanups, $rules, $value_replacement);
	}
	
	
	public static function GetValidationStr($validation_rule, $for_array = null, $value_replacement = null)
	{
		$validators = self::GetPreparedDataValidators();
		$rules = self::ParseValidationRules($validation_rule);
		return self::GetRuleStr($validators, $rules, $value_replacement, true);
	}
	
	public static function GetPreparedDataValidators()
	{
		if (self::$PreparedValidators !== null)
			return self::$PreparedValidators;
		
		return self::$PreparedValidators = self::GetPreparedData(QAutoload::GetDataValidators());
	}
	
}

