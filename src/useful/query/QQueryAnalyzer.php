<?php

final class QQueryAnalyzer
{
	protected static $_sopbycode = null;

	public static $SearchOpCodes = [
		"LIKE" => 1
	];

	public static function Analyze($query = null, $start_type = null)
	{
		if ($start_type === null)
			$start_type = \QApp::GetDataClass();
		if ($start_type === null)
			return false;
		if ($query === null)
			$query = $start_type::GetListingQuery();

		$tokens = static::ParseSql($query);
		$zone = "SELECTOR";
		$q_type = "SELECT";
		$types = is_array($start_type) ? $start_type : [$start_type];
		
		$pos = 0;
		
		// complex extract: selector -> info
		//					query zone -> info
		//					if in a subquery that is a collection with a pure select
		//							the info goes to that selector
		//					else
		//							the info is for the upper query
		
		return static::ExtractSqlInfoWorker($tokens, $pos, $zone, $q_type, $types);
	}

	protected static function ExtractSqlInfoHandleIdentifier($identifier, $types_data, &$data)
	{
		$data["_ty"] = $types_data ? array_keys($types_data) : null;
	}

	public static function GetSearchOpCode($op)
	{
		return static::$SearchOpCodes[$op];
	}
	
	public static function GetSearchOpByCode($code)
	{
		if (!static::$_sopbycode)
		{
			foreach (static::$SearchOpCodes ?: [] as $op => $_code)
				static::$_sopbycode[$_code] = $op;
		}
		return static::$_sopbycode[$code];
	}
	
	public static function GetPropertyTypes($types_data, $property, &$is_collection = null)
	{
		if (!$types_data)
			return null;
		
		$prop_types = [];
		foreach ($types_data as $_ty_data)
		{
			$is_str_ty = is_string($_ty_data);
			$is_scalar = $is_str_ty && (strtolower($_ty_data{0}) === $_ty_data{0});
			if ($is_scalar)
				continue;
			$ty_data = $is_str_ty ? \QModelQuery::GetTypesCache($_ty_data) : $_ty_data;
			if (($pty = $ty_data[$property]))
			{
				if ($pty["[]"] && ($y = $pty["[]"]["refs"]))
				{
					$is_collection = true;
					foreach ($y as $k => $v)
						$prop_types[$k] = $v;
				}
				if (($y = $pty["refs"]))
				{
					foreach ($y as $k => $v)
						$prop_types[$k] = $v;
				}
				if (($y = $pty["\$"]))
				{
					foreach ($y as $k => $v)
						$prop_types[$k] = $v;
				}
			}
		}
		return $prop_types;
	}
	
	protected static function ExtractSqlInfoWorker($tokens, &$pos, &$zone, &$q_type, $types, &$data = null, &$q_data = null, &$binds_inf = null, $binds_prefix = [], $idf_parts = [], &$root_data = null)
	{
		$chain = [];
		$last_conditional = null;
		
		if ($binds_inf === null)
		{
			$binds_inf = [];
			$is_root = true;
		}
		else
			$is_root = false;
		
		if ($data === null)
		{
			$data = [];
			$root_data = &$data;
		}
		$q_data = &$data;
		
		if ($data["_ty"] === null)
			$data["_ty"] = $types;
		else
		{
			foreach ($types as $v)
			{
				if (!in_array($v, $data["_ty"]))
					$data["_ty"][] = $v;
			}
		}
		
		$types_data = [];
		
		$inter_types = ($types_data[$types[0]] = \QModelQuery::GetTypesCache($types[0]));
		$types_count = count($types);
		
		for ($i = 1; $i < $types_count; $i++)
			$inter_types = array_intersect_key($inter_types, ($types_data[$types[$i]] = \QModelQuery::GetTypesCache($types[$i])));
		
		$preceded_by_AS = false;
		$last_was = false;
		$zone_limit_before_comma = false;
		
		while (($tok = $tokens[$pos]) !== null)
		{
			list($curr, $curr_i, $curr_s) = $tok;
			$break = false;
			// we need to handle * , if we are in a pure select, then it is a selector, else it's a math operator
			
			if ($curr_s)
			{
				// nothing for space
			}
			// handle identifier
			else if ($curr_i && ($curr_i !== "*"))
			{
				if (!($inter_types[$curr_i] || $preceded_by_AS))
				{
					$pos++;
					continue;
				}
				
				// "*" can be: first (after whitespace), after SELECT, after a first level "," under the current context: { ... }
				if ($data[$curr_i] === null)
					$data[$curr_i] = [];
				
				$c_idf_parts = $idf_parts;
				$c_idf_parts[] = $curr_i;
				
				if ($preceded_by_AS)
				{
					// if not identifier, then we have a INT or STRING (this is all that we support at the moment)
					// reset it
					$data[$curr_i]["_as"] = [];
					$preceded_by_AS = false;
					
					end($chain);
					$prev_chain = prev($chain);
					if ($prev_chain[0] === "identifier")
					{
						$prev_idf_data = end($prev_chain[1]);
						list(, $prev_idf_types, $prev_idf_name, $prev_idf_parent_types) = $prev_idf_data;
						// we have an identifier
						$data[$curr_i]["_ty"] = array_values($prev_idf_types);
						$data[$curr_i]["_prop"] = $prev_idf_name;
						$data[$curr_i]["_idf"] = static::ExtractSqlInfoIdfChunks($prev_chain, $idf_parts);
						$data[$curr_i]["_prop_ty"] = array_keys($prev_idf_parent_types);
						$data[$curr_i]["_readonly"] = false;
					}
					else
					{
						// @todo - we should conside more scenarios 
						// atm we default to string|int
						$data[$curr_i]["_ty"] = ["integer", "string"];
						$data[$curr_i]["_readonly"] = true;
					}
					
					$chain[] = ["as-tag", $curr_i];
				}
				else
				{
					$is_collection = false;
					$prop_types = static::GetPropertyTypes($types_data, $curr_i, $is_collection);
					static::ExtractSqlInfoHandleIdentifier($curr_i, $prop_types, $data[$curr_i]);

					$a_pos = $pos + 1;
					$had_dot = false;

					$a_data = &$data[$curr_i];
					$a_types = $prop_types;

					$last_was = ["identifier", $prop_types];
					$chain_element = [];
					$chain_element[] = ["identifier", $prop_types, $curr_i, $types_data];
					
					while (($a_tok = $tokens[$a_pos++]) !== null)
					{
						list($a_curr, $a_curr_i, $a_curr_s) = $a_tok;

						if ($a_curr === ".")
						{
							// we step into
							$had_dot = true;
						}
						else if (($a_curr === "{") || ($a_curr_i && ($a_curr_i !== "*")))
						{
							// new context
							if (!$had_dot)
								throw new Exception("Parse error");
							// recurse building the query
							if ($a_curr === "{")
							{
								$pos = $a_pos;
								$sub_zone = "SELECTOR";
								// $is_collection = $a_types["[]"] ? true : false;
								if ($is_collection)
									$a_q_data = &$a_data;
								else
									$a_q_data = &$q_data;
								static::ExtractSqlInfoWorker($tokens, $pos, $sub_zone, $q_type, array_values($a_types), $a_data, $a_q_data, $binds_inf, $binds_prefix, $c_idf_parts, $root_data);
								// fix pos, as it will get incremented at the bottom of the loop
								$pos--;
								
								$last_was = ["sub-query", $a_types];
								$chain_element[] = ["sub-query", $a_types];
								break;
							}
							else
							{
								if ($a_data[$a_curr_i] === null)
									$a_data[$a_curr_i] = [];
								$a_data = &$a_data[$a_curr_i];
								$saved_a_types = $a_types;
								$a_types = static::GetPropertyTypes($a_types, $a_curr_i, $is_collection);
								static::ExtractSqlInfoHandleIdentifier($a_curr_i, $a_types, $a_data);
								$last_was = ["identifier", $a_types];
								$chain_element[] = ["identifier", $a_types, $a_curr_i, $saved_a_types];
								
								$pos = $a_pos;
								// fix pos, as it will get incremented at the bottom of the loop
								$pos--;
								
								$c_idf_parts[] = $a_curr_i;
							}

							$had_dot = false;
						}
						else if ($a_curr_s)
						{
							// whitespace
						}
						else 
						{
							// identifier ended
							break;
						}
					}
					
					$chain[] = ["identifier", $chain_element];
					if ($last_conditional)
					{
						$last_idf = end($chain_element);
						if ($last_idf[0] === "identifier")
						{
							list (,$last_idf_types,$last_idf_prop,$last_idf_prop_types) = $last_idf;
							$last_conditional["first_idf"]["_ty"] = array_values($last_idf_types);
							$last_conditional["first_idf"]["_prop"] = $last_idf_prop;
							$last_conditional["first_idf"]["_idf"] = static::ExtractSqlInfoIdfChunks(end($chain), $idf_parts);
							$last_conditional["first_idf"]["_prop_ty"] = array_keys($last_idf_prop_types);
							
							static::ExtractSqlInfoLinkIdfToBind($last_conditional["first_idf"]["_idf"], $last_conditional["bind_path"], $root_data, $zone, $binds_inf);
						}
						unset($last_conditional);
					}
				}
			}
			// handle binds
			else if ($curr{0} === "?")
			{
				$bind_key = null;
				
				$ret = preg_split("/".
						"(\\?\\?[A-Za-z0-9\\.\\-\\_]+(?:\\?[\\<\\>][^\\[]+)?\\[".
									"(?:[^\\]^\\']+|'(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\')+".
											"\\])|". # to be replaced if key exists
						"(\\?+)".

							"/ius", $curr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				
				$bind_data = [];
				$binds_path = $binds_prefix;
				
				if ($ret[2] !== null)
				{
					// this is a conditional bind
					$bind_data["conditional"] = true;
					$bind_data["zone"] = $zone;
				}
				else
				{
					// @todo also look ahead in the future
					$bind_data = static::ExtractSqlInfoBinds($tokens, $pos, $chain, $zone, $q_type, $idf_parts);
					$bind_data["zone"] = $zone;
				}
				
				if (($bind_key = $ret[1]))
				{
					// has a key
					$binds_prefix[] = $bind_key;
					$binds_path = $binds_prefix;
				}
				else
				{
					$binds_path[] = true; // next index in data;
				}
				
				// @todo , in case of nested conditionals this will not work !!!, nested conditionals are not implemented atm
				unset($last_conditional);
				
				$bi = &$binds_inf;
				$last_k = count($binds_path) - 1;
				foreach ($binds_path as $k => $_bp_k)
				{
					if ($_bp_k === true)
					{
						$bi[] = [];
						// get the next one
						$bp_k = (end($bi) === false) ? 0 : key($bi);
						$binds_path[$k] = $bp_k;
					}
					else
						$bp_k = $_bp_k;
					
					if ($bi[$bp_k] === null)
						$bi[$bp_k] = [];
					if ($last_k === $k)
					{
						// is last we attach info
						$bind_data["bind_path"] = $binds_path;
						$bi[$bp_k]["__d__"] = $bind_data;
						if ($bind_data["_idf"])
							static::ExtractSqlInfoLinkIdfToBind($bind_data["_idf"], $binds_path, $root_data, $zone, $binds_inf);
						if ($bind_data["conditional"])
							$last_conditional = &$bi[$bp_k]["__d__"];
					}
					else
						$bi = &$bi[$bp_k];
				}
				
				$bind_data_for_zone = ["path" => $binds_path];
				if ($zone === "LIMIT")
					$bind_data_for_zone["before_comma"] = $zone_limit_before_comma;
				$q_data["_q"][$zone]["binds"][] = $bind_data_for_zone;
				
				$chain[] = ["bind", $curr, $binds_path];
			}
			// depends on what zone we are in (or not ?!)
			else if (($len = strlen($curr)) === 1)
			{
				$last_was = $curr;
				$chain[] = ["op", $curr];
				switch ($curr)
				{
					case "}":
					{
						// we need to close the context
						$break = true;
						break;
					}
					case "(":
					{
						// $q->parts[$q->p_index][] = $curr;
						// $depth++;
						break;
					}
					case ")":
					{
						// $q->parts[$q->p_index][] = $curr;
						// $depth--;
						break;
					}
					case "]":
					{
						// we exit one bind level
						unset($last_conditional);
						array_pop($binds_prefix);
						break;
					}
					case ",":
					{
						// if (($zone === 0) && ($depth === 0))
						//	$pure_sel = true;
						$zone_limit_before_comma = false;
						break;
					}
					default:
					{
						//v$q->parts[$q->p_index][] = $curr;
						break;
					}
				}
			}
			else
			{
				$curr_uc = strtoupper($curr);
				$last_was = $curr_uc;
				$chain[] = ["sql", $curr_uc, $curr];
				
				switch ($curr_uc)
				{
					case "WHERE":
					case "GROUP BY":
					case "ORDER BY":
					case "HAVING":
					case "LIMIT":
					{
						$zone = $curr;
						if ($curr_uc === "LIMIT")
							// we are before the comma
							$zone_limit_before_comma = true;
						$q_data["_q"][$zone] = [];
						break;
					}
					case "SELECT":
					case "INSERT":
					case "UPDATE":
					case "DELETE":
					{
						$zone = "SELECTOR";
						$type = $curr;
						$q_data["_q"][$zone] = [];
						break;
					}
					case "IS_A":
					{
						break;
					}
					case "AS":
					{
						$preceded_by_AS = true;
						break;
					}
					case "SQL_CALC_FOUND_ROWS":
					{
						break;
					}
					default:
					{
						$last_was = $curr;
						break;
					}
				}
			}
			
			$pos++;
			
			if ($break)
				break;
		}
		
		if ($is_root)
			$data["__binds__"] = $binds_inf;
		
		return $data;
	}

	public static function ParseSql($query)
	{
		$tokens = null;
		$stat = preg_match_all("/".
				"'(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\'|". # string
				"[-+]?(?:[0-9]*\.?[0-9]+|[0-9]+)|". # number (not full validation)
				// Keywords: AND/OR/...
				"\bAS\b|\bSELECT\b|\bUPDATE\b|\bDELETE\b|\bINSERT\b|\bWHERE\b|\bORDER\\s+BY\b|\bHAVING\b|\bGROUP\\s+BY\b|\bAND\b|\bOR\b|\bBETWEEN\b|\bASC\b|\bDESC\b|\bLIMIT\b|".
					"\bNULL\b|\bTRUE\b|\bFALSE\b|\bIS\\s+NULL\b|\bIS_A\b|\bIS\b|\bLIKE\b|\bCASE\b|\bBINARY\b|\bNOT\b|\bDIV\b|\bISNULL\b|\bSQL_CALC_FOUND_ROWS\b|".
					"\bDISTINCT\b|\bEND\b|\bELSE\b|\bTHEN\b|\bSEPARATOR\b|".
				// FUNCTIONS: FuncName (
				"[\\p{L&}\\$]\\w+\\s*\\("."|\\p{L&}+\\s*\\(|". // Identifiers/entities
				"([\\`\\\"]?[\\p{L&}\\$]\\w[\\w\\\\]*[\\`\\\"]?|"."[\\`\\\"]?\\p{L&}[\\`\\\"]?+)|". # identifiers (can not start with a digit)
				"\\(\\+\\)|". # TO DO : control join type
				"\\(\\-\\)|". # TO DO : control join type
				"\\(\\s*\\)|". # empty brackets
				"\\?\\?[A-Za-z0-9\\.\\-\\_]+(?:\\?[\\<\\>][^\\[]+)?\\[|". # 
				"[\\?]+|".
				"\\:\\=|".
				"\\|{2}|".
				"\\&{2}|".
				"\\>{2}|".
				"\\<{2}|".
				"\\]|".
				"\\!\\=|".
				"\\<\\>|".
				"\\>\\=|".
				"\\<\\=|".
				"[\\!-\\/\\:-\\@\\[-\\^\\`\\{-\\~]{1}|". # from ASCII table we have ranges there also
				"(\\s+)".

			"/ius", $query, $tokens, PREG_SET_ORDER);
		
		if ($stat === false)
			throw new Exception("Parsing failed for the query");
		
		return $tokens;
	}
	
	protected static function ExtractSqlInfoBinds($tokens, $pos, $chain, $zone, $q_type, $idf_parts)
	{
		// @todo also implement selector zone, it should not be so hard if it's similar to WHERE/HAVING - we need to test a bit
		if (($zone === "WHERE") || ($zone === "HAVING"))
			return static::ExtractSqlInfoBindsConditions($tokens, $pos, $chain, $zone, $q_type, $idf_parts);
		else if ($zone === "GROUP BY")
		{
			// nothing to do here atm
			return [];
		}
		else if ($zone === "ORDER BY")
		{
			// pick the closest identifier
			$identifier = end($chain);
			if ($identifier[0] === "identifier")
			{
				$identifier = end($identifier[1]);
				
				$data = [];
				$data["_ty"] = array_values($identifier[1]);
				$data["_prop"] = $identifier[2];
				$data["_idf"] = static::ExtractSqlInfoIdfChunks(end($chain), $idf_parts);
				$data["_prop_ty"] = array_keys($identifier[3]);
				
				return $data;
			}
			else
				return [];
		}
		else if ($zone === "LIMIT")
		{
			return [];
		}
	}
	
	protected static function ExtractSqlInfoBindsConditions($tokens, $pos, $chain, $zone, $q_type, $idf_parts)
	{
		$data = [];
		
		$types = null;
		$idf_compatible = false;
		
		$identifier = null;
		$last_idf_parent_prop = null;
		$last_idf_parent_data = null;
		$last_idf_full = null;
		$op = null;
		$op_index = null;
		
		/*
		$dump = [];
		foreach ($chain as $cv)
		{
			if ($cv[1] && is_string($cv[1]))
			$dump[] = $cv[1];
		}
		
		$debug = (end($dump) === "??Parent?<AND[");*/
		
		// walk only on known patterns for now
		$prev = end($chain);
		if ($prev[1] === "AND")
		{
			// look back for between or bail out
			prev($chain);
			$prev_bind_2 = prev($chain);
			if ($prev_bind_2[1] === "BETWEEN")
			{
				$prev = $prev_bind_2;
				$op_index = 1;
			}
			else
			{
				// reset position
				next($chain);
				next($chain);
			}
		}
		
		// we skip some operators if found
		if (($prev[1] === "!") || ($prev[1] === "NOT") || ($prev[1] === "COALESCE"))
			$prev = prev($chain);
		$prev_2 = prev($chain);
		// we skip some operators if found
		if (($prev[1] === "!") || ($prev_2[1] === "NOT") || ($prev_2[1] === "COALESCE"))
			$prev_2 = prev($chain);

		if (is_string($prev[1]))
		{
			$compare_to = strtoupper($prev[1]);
			if (($prev[1] === "(") && $prev_2 && is_string($prev_2[1]))
				$compare_to = strtoupper($prev_2[1].$prev[1]);
			else if (substr($prev[1], -1, 1) === "(")
				$compare_to = strtoupper(trim(substr($prev[1], 0, -1))."(");
			
			$op = null;
			
			switch ($compare_to)
			{
				case "=":
				case "<=>":
				case "<>":
				case "!=":
				case "IS NULL":
				case "IS NOT NULL":
				case "COALESCE(":
				case "IN(":
				{
					// same as the identifier if found
					$op = rtrim($compare_to, "(");
					$idf_compatible = true;
					break;
				}
				case "IS_A":
				{
					// must be a type | compatible with identifier if found
					$types = "class-name";
					$op = $compare_to;
					$idf_compatible = true;
					break;
				}
				case "LIKE(":
				case "REGEXP(":
				case "RLIKE(":
				{
					// must be a string
					$op = rtrim($compare_to, "(");
					$types = ["string"];
					$idf_compatible = false;
					break;
				}
				case "<":
				case ">":
				case ">=":
				case "<=":
				case "BETWEEN":
				case "+":
				case "-":
				case "/":
				case "*":
				case "%":
				{
					if (($compare_to === "BETWEEN") && ($op_index === null))
						$op_index = 0;
					
					$op = $compare_to;
					// same as the identifier if found, but scalar
					$types = ["integer", "float"];
					$idf_compatible = true;
					break;
				}
				default:
					break;
			}
			
			// lookup identifier
			$identifier = current($chain);
			if ($identifier[0] !== "identifier")
				$identifier = prev($chain);

			if ($identifier[0] === "identifier")
			{
				$last_idf_full = $identifier;
				$last_idf_inf = end($identifier[1]);
				list(, $last_idf_types, $last_idf_parent_prop, $last_idf_parent_data) = $last_idf_inf;

				if ($idf_compatible)
				{
					if ($types !== null)
					{
						if ($types === "class-name")
						{
							//
						}
						else if (is_array($types))
						{
							// we will need to intersect
							$types = array_intersect($types, $last_idf_types);
						}
						else
						{
							throw new \Exception("Not implemented");
						}
					}
					else
						$types = $last_idf_types;
				}
			}
		}
		
		if ($types === "class-name")
			$data["_is_class_name"] = true;
		else if ($types)
			$data["_ty"] = array_values($types);
		if ($op !== null)
			$data["_op"] = $op;
		if ($op_index !== null)
			$data["_op_index"] = $op_index;
		if ($last_idf_parent_prop)
			$data["_prop"] = $last_idf_parent_prop;
		if ($last_idf_full)
			$data["_idf"] = static::ExtractSqlInfoIdfChunks($last_idf_full, $idf_parts);
		if ($last_idf_parent_data)
			$data["_prop_ty"] = key($last_idf_parent_data);
		
		return $data;
		
	}
	
	protected static function ExtractSqlInfoIdfChunks($chain_element, $prepend = null)
	{
		$ret = $prepend ?: [];
		foreach ($chain_element[1] as $identifier)
		{
			// list(, $prev_idf_types, $prev_idf_name, $prev_idf_parent_types) = $prev_idf_data;
			$idf_name = $identifier[2];
			$ret[] = $idf_name;
		}
		return $ret;
	}
	
	protected static function ExtractSqlInfoLinkIdfToBind($idf_path, $bind_path, &$root_data, $zone, $binds_inf)
	{
		$data = &$root_data;
		foreach ($idf_path as $p)
			$data = &$data[$p];
		$data["_binds"][] = [$zone, $bind_path];
	}

	public static function GetSearchInfo($analyze_result)
	{
		$binds = null;
		if ((!$analyze_result) || (!($binds = $analyze_result["__binds__"])))
			return null;

		$return = [];

		foreach ($binds as $k => $data)
		{
			if (substr($k, 0, strlen("QINSEARCH_")) !== "QINSEARCH_")
				continue;

			$k = substr($k, strlen("QINSEARCH_"));
			
			// check that we are in the right place
			if ((!($data)) || (!($inf = $data["__d__"])) || (!($inf["zone"] === "WHERE")) || (!($inf["conditional"])))
				continue;

			$params = [];
			$p_pos = 0;
			foreach ($data as $pk => $p_data)
			{
				if ($pk === "__d__")
					continue;

				$params[$pk] = $p_data["__d__"];
				if ($p_pos === 0)
				{
					if (!$params[$pk]["_ty"])
						$params[$pk]["_ty"] = $data["__d__"]["first_idf"]["_ty"];
					if (!$params[$pk]["_prop"])
						$params[$pk]["_prop"] = $data["__d__"]["first_idf"]["_prop"];
					if (!$params[$pk]["_idf"])
						$params[$pk]["_idf"] = $data["__d__"]["first_idf"]["_idf"];
					if (!$params[$pk]["_prop_ty"])
						$params[$pk]["_prop_ty"] = $data["__d__"]["first_idf"]["_prop_ty"];
				}
				$p_pos++;
			}
			
			$return[$k] = [
				"inf" => $inf,
				"params" => $params
			];
		}
		
		return $return ?: null;
	}
	
	public static function GetOrderByInfo($analyze_result)
	{
		$binds = null;
		if ((!$analyze_result) || (!($binds = $analyze_result["__binds__"])))
			return null;
		
		$return = [];
		
		foreach ($binds as $k => $data)
		{
			// check that we are in the right place
			if ((!($data)) || (!($inf = $data["__d__"])) || (!($inf["zone"] === "ORDER BY")) || (!($inf["conditional"])))
				continue;
			
			$params = [];
			$p_pos = 0;
			foreach ($data as $pk => $p_data)
			{
				if ($pk === "__d__")
					continue;
				$params[$pk] = $p_data["__d__"];
				if ($p_pos === 0)
				{
					if (!$params[$pk]["_ty"])
						$params[$pk]["_ty"] = $data["__d__"]["first_idf"]["_ty"];
					if (!$params[$pk]["_prop"])
						$params[$pk]["_prop"] = $data["__d__"]["first_idf"]["_prop"];
					if (!$params[$pk]["_idf"])
						$params[$pk]["_idf"] = $data["__d__"]["first_idf"]["_idf"];
					if (!$params[$pk]["_prop_ty"])
						$params[$pk]["_prop_ty"] = $data["__d__"]["first_idf"]["_prop_ty"];
				}
				$p_pos++;
			}
			
			$return[$k] = [
				"inf" => $inf,
				"params" => $params
			];
		}
		
		return $return ?: null;
	}
}
