<?php

class QSqlParserQuery
{
	public $select;
	public $from;
	public $where;
	public $groupBy;
	public $having;
	public $orderBy;
	public $limit;
	public $limitCount;
	public $collection_table;

	public $parts;

	public $c_as = null;
	public $t_as = null;

	public $actions = array();

	public $p_index = 0;
	
	public $pp_comma = false;
	
	public $query_type = "SELECT";
	
	// inner query means something like Data.Id IN (SELECT ...
	public $_is_inner_query = false;
	
	public static $TmpId = 1;
	
	public static $_DebugOn = 0;

	public static $Zones = [0 => "SELECT",
							1 => "FROM", // & JOIN	: 1
							2 => "WHERE",
							3 => "GROUP BY",
							4 => "HAVING",
							5 => "ORDER BY",
							6 => "LIMIT"];
	
	protected static $SettersDefined = [];
	
	/**
	 *
	 * @var array[]
	 */
	public $cols_type_inf;
	
	protected $_colls_indexes;
	
	/**
	 * TO DO: there are properties that are not used. Clean them up. 
	 */

	/**
	 * SELECT		: 0
	 * FROM & JOIN	: 1
	 * WHERE		: 2
	 * GROUP BY		: 3
	 * HAVING		: 4
	 * ORDER BY		: 5
	 * LIMIT		: 6
	 */
	public $parent;
	public $children;
	
	public function __construct()
	{
		$this->_tmp_id = self::$TmpId++;
		$this->cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();
		$this->joins = array();
		$this->_colls_indexes = new \SplObjectStorage();
	}

	public function getNextAlias()
	{
		if ($this->c_as === null)
			return ($this->c_as = "a");
		$fst = $this->c_as{0};
		// if at z , rewind to a and increment the second
		if ($fst === 'z')
			return $this->c_as = ("a".((strlen($this->c_as) > 1) ? (substr($this->c_as, 1) + 1) : "1"));
		else 
		{
			$this->c_as{0} = chr(ord($fst) + 1);
			return $this->c_as;
		}
	}

	public function getNextTableAlias()
	{
		if ($this->parent)
			return $this->parent->getNextTableAlias();
		
		if ($this->t_as === null)
			return ($this->t_as = "A");
		$fst = $this->t_as{0};
		// if at Z , rewind to A and increment the second
		if ($fst === 'Z')
			return $this->t_as = ("A".((strlen($this->t_as) > 1) ? (substr($this->t_as, 1) + 1) : "1"));
		else 
		{
			$this->t_as{0} = chr(ord($fst) + 1);
			return $this->t_as;
		}
	}
	
	public function __toString()
	{
		return $this->toSQL();
	}

	public function toSQL($with_count = false)
	{
		$fq = "";
		$this->_count_q = "";
		
		if ($this->items && (!$this->_is_inner_query))
		{
			// $model = $this->model;
			// $prop_f = $model->parent;
			// $prop = $prop_f->property;
			$as = $this->collection_as; // $this->query->getNextTableAlias();
			
			$bk_ref = $this->collection_bk;
			
			if (empty($this->parts[2]))
				$this->parts[2][] = " WHERE ";
			else
			{
				// find pos after WHERE
				$pos = 0;
				
				while ((($t = $this->parts[2][$pos++]) !== null) && (strtoupper($t) !== "WHERE"));
				
				array_splice($this->parts[2], $pos, 0, array("("));
				$this->parts[2][] = " ) AND ";
			}
			
			$in_s = "";
			$p = 0;
			
			foreach ($this->items as $id => $ty)
			{
				if ($id || ($id == "0"))
				{
					$in_s .= ($p ? "," : "").$id;
					$p++;
				}
			}

			if ($in_s)
			{
				$this->parts[2][] = " `{$as}`.`{$bk_ref}` IN (";
				$this->parts[2][] = $in_s;
				$this->parts[2][] = ") ";
			}
			else // if ($this->items)
			{
				// force false atm
				// @todo
				$this->parts[2][] = " FALSE ";
			}
		}
		
		/**
		 * SELECT		: 0
		 * FROM & JOIN	: 1
		 * WHERE		: 2
		 * GROUP BY		: 3
		 * HAVING		: 4
		 * ORDER BY		: 5
		 * LIMIT		: 6
		 */
		
		if (!$this->parts[0])
			$this->parts[0] = array();
		if (!$this->parts[1])
			$this->parts[1] = array();
		if (!$this->parts[2])
			$this->parts[2] = array();
		
		ksort($this->parts, SORT_NUMERIC);
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();
		
		if (($this->query_type !== "SELECT"))
		{
			// switch "0" with "1" and modify a bit
			// we need to make some changes then put them back
			// var_dump($this->parts);
			$saved_0 = $this->parts[0];
			$this->parts[0] = $this->parts[1];
			$this->parts[1] = $saved_0;
			
			if ($this->query_type === "DELETE")
			{
				$this->parts[1] = [];
				$this->parts[0][0] = "DELETE ".implode(",", array_keys($this->pure_joins))." FROM ";
			}
			else // INSERT & UPDATE
			{
				$this->parts[1][0] = "SET";
				$this->parts[0][0] = $this->query_type;
				
				if ($this->query_type === "INSERT")
					$this->parts[0] = ["INSERT `".reset($this->pure_joins)."` "];
			}
		}
		
		$is_insert_query = ($this->query_type === "INSERT");
		$has_where = null;
		
		foreach ($this->parts as $i => $p)
		{
			// for group by & order by 5,3
			$saved_block = null;
			if (($i === 3) || ($_is_gby = ($i === 5)))
			{
				$saved_block = reset($p);
				array_shift($p);
				QSqlParserModel::LtrimComma($p);
			}
			QSqlParserModel::RtrimComma($p);
			if (($i === 3) || ($i === 5))
			{
				array_unshift($p, " ");
				array_unshift($p, $saved_block);
			}
				
			$q = "\n".implode(" \n", $p);
			
			// var_dump("TO SQL #".$this->_tmp_id, $this->cols);
			if ((!$this->_is_inner_query) && ($i === 0) && $this->cols && ($this->query_type === "SELECT"))
			{
				$sel_only = (empty($p) || (strtoupper(end($p) ?: "") === "SELECT") || (strtoupper(end($p) ?: "") === "SQL_CALC_FOUND_ROWS"));

				// hopefully this means that is main query
				if (!$this->parent)
				{
					$q .= ($sel_only ? " " : ",")."`{$this->as}`.`Del__` AS `Del__`";
					$sel_only = false;
				}

				foreach ($this->cols as $as => $list)
				{
					foreach ($list as $alias => $c)
					{
						$q .= ($sel_only ? " " : ",")."`{$as}`.`{$c}`".(is_string($alias) ? " AS `{$alias}`" : "");
						$sel_only = false;
					}
				}
			}
			
			// WHERE when $i === 2
			if (($i === 2) && (!$is_insert_query))
			{
				$has_where = $has_where ?: ($p ? true : false);
				// if QIModelArray we started with a collection
				if ($this->inst)
				{
					$append_sql = null;
					
					$inst_ty = $this->inst_type_obj;
					
					if ((!($this->inst instanceof QIModelArray)) && ($this->inst->getId() !== null))
					{
						$inst_ty = $this->inst->getModelType();
						$append_sql = " `{$this->as}`.`".$inst_ty->getIdCn()."`='".$this->inst->getId()."' ";
					}
					
					if ($inst_ty)
					{
						$inst_tab = $inst_ty->getTableName();
						$inst_id_col = $inst_ty->getIdCn();
						
						$inst_ty_val = $this->inst_type;
						
						// TO DO ... also include types that are extended by !!!
						
						$extra_ty_col = ($ci = $cols_type_inf[$inst_tab]) ? $ci[$inst_id_col] : null;
						if (is_string($extra_ty_col))
						{
							$append_sql_ty = " `{$this->as}`.`{$extra_ty_col}`='{$inst_ty_val}' ";
							$append_sql ? ($append_sql .= " AND ".$append_sql_ty) : $append_sql = $append_sql_ty;
						}
					}
					
					if (!empty($append_sql))
					{
						if (!$has_where)
							$q .= "WHERE";
						else 
							$q .= " AND ";
						$has_where = true;
						
						$q .= $append_sql;
					}
				}
				if ($this->extra_where)
				{
					foreach ($this->extra_where as $extra_where)
					{
						if (!$has_where)
							$q .= "WHERE";
						else 
							$q .= " AND ";
						$has_where = true;

						if (is_array($extra_where))
							$q .= "\n".implode(" \n", $extra_where)." ";
						else
							$q .= "\n".$extra_where." ";
						// var_dump($q);
					}
				}
			}
			$q .= "\n";
			
			/**
			* Workaround for bug 18454
			* Fixed in MySQL 5.6.2.
			* http://bugs.mysql.com/bug.php?id=18454
			*/
			/**
		 * SELECT		: 0
		 * FROM & JOIN	: 1
		 * WHERE		: 2
		 * GROUP BY		: 3
		 * HAVING		: 4
		 * ORDER BY		: 5
		 * LIMIT		: 6
		 */
			
			
			// avoid adding empty statement blocks for: WHERE, GROUP BY, HAVING, ORDER BY, LIMIT
			if (($i >= 2 /* WHERE */) && (($q_len = strlen($q)) < 64) && (strtoupper(trim($q)) === strtoupper(static::$Zones[$i])))
				// avoid adding empty statement blocks
				continue;
			
		
			// define("MYSQL_USE_SQL_CALC_FOUND_ROWS", true);
			if ($with_count)
			{
				// skip columns and place COUNT(*) instead
				if ($i === 0)
					$this->_count_q .= "SELECT 1 ";
				// SKIP ORDER BY & LIMIT
				else if (($i !== 5) && ($i !== 6))
					$this->_count_q .= $q;
			}
			
			$fq .= $q;
		}
		
		if ($with_count)
		{
			$this->_count_q = "SELECT COUNT(*) AS `FOUND_ROWS` FROM ({$this->_count_q}) AS `CQ`";
		}
		
		return $fq;
	}

	public function dumpModel(QSqlParserModel $model, $depth = 0)
	{
		if ($depth === 0)
			echo "<pre>";
		$pad = str_pad("", $depth, "\t");
		echo "<b style='color: green;'>".($model->type ?: $model->property)." [".  spl_object_hash($model)."]</b>\n";
		
		if ($model->children)
		{
			foreach ($model->children as $c_name => $child)
			{
				echo $pad."\t".$c_name." => ";
				$this->dumpModel($child, $depth + 2);
			}
		}
		
		if ($depth === 0)
			echo "</pre>";
	}
	
	public function query(&$objs, $storage, $conn, $base_from = null, $populate_only = false)
	{
		if ($this->is_collection && (!$this->items))
		{
			// var_dump("no itms bby");
			return;
		}
		
		// $objs = array();
		if ($base_from)
		{
			$frm = reset($base_from);
			
			$start_inst = $this->inst ?: $frm->inst;

			if ($start_inst instanceof QIModelArray)
			{
				// nothing here
				$this->inst_type = $storage->getTypeIdInStorage($frm->type);
				$this->inst_type_obj = QModel::GetTypeByName($frm->type);
			}
			else
			{
				$this->inst_type_obj = $start_inst->getModelType();
				
				$t_id = $start_inst->getId();
				$t_ty = $storage->getTypeIdInStorage($this->inst_type_obj->class);
				$objs[$t_id][$t_ty] = $start_inst;
				
				$this->inst_type = $t_ty;
			}
		}

		/**
		 * We don't use SQL_CALC_FOUND_ROWS for the performance penalty
		 * 
		 * Workaround for bug 18454
		 * Fixed in MySQL 5.6.2.
		 * http://bugs.mysql.com/bug.php?id=18454
		 */
		// define("MYSQL_USE_SQL_CALC_FOUND_ROWS", true);
		$exe_q = $this->toSQL((defined("MYSQL_USE_SQL_CALC_FOUND_ROWS") && MYSQL_USE_SQL_CALC_FOUND_ROWS) ? false : true);

		if (false && \QAutoload::GetDevelopmentMode())
		{
			echo "<hr/>";
			/*
			if (defined('dev_ip') && ($_SERVER['REMOTE_ADDR'] === dev_ip) && (strpos($exe_q, 'Nuvia_Sites') !== false))
			{
				qvar_dump($exe_q);
			}
			*/
			echo "<pre>{$exe_q}</pre>";
		}
		
		$root_q = $this->getRootQuery();
		
		$t1 = microtime(true);
		$result = $conn->query($exe_q);
		$t2 = microtime(true);
		// qvdumptofile($exe_q, $t2 - $t1);
		// echo $exe_q;
		// echo ($t2 - $t1)." seconds<hr/>";

		if ($result === false)
		{
			if ($root_q->_dbg_binds)
				var_dump($root_q->_dbg_query, $root_q->_dbg_binds, $exe_q);
			else
				var_dump($root_q->_dbg_query, $exe_q);
			throw new Exception($conn->error);
		}
		
		$collection_arr = null;
		
		// echo "<table style='border: 1px solid black; border-collapse: collapse;'>\n";
		// $headers = null;
		
		try
		{
			if ($this->query_type === "SELECT")
			{
				$_this_f_actions = $this->cleanup_F_Actions();
		
				while ($row = $result->fetch_assoc())
				{
					// $row_index_tmp++;
					// echo "Row: ".$row_index_tmp."<br/>";
				
					/*
					if (!$headers)
					{
						$headers = true;
						echo "<tr><th style='border: 1px solid black;'>".implode("</th><th style='border: 1px solid black;'>", array_keys($row))."</th></tr>\n";
					}
					echo "<tr><td style='border: 1px solid black;'>".implode("</td><td style='border: 1px solid black;'>", $row)."</td></tr>\n";	
					 */			

					if (!$_this_f_actions)
					{
						var_dump($root_q->_dbg_query, $exe_q);
						throw new Exception("No actions on query");
					}

					foreach ($_this_f_actions as $sql_type)
					{
						$object = null;
						$type_id = null;
						$val_id = null;
						$type_name = null;

						if ($sql_type->inst)
						{
							$object = $sql_type->inst;
							$type_name = get_class($object);
							$type_id = $storage->getTypeIdInStorage($type_name);
							$val_id = $object->getId();
						}
						else if ($sql_type->action_make)
						{
							list($ty_model_as, $join_tab_id, $ty_col) = $sql_type->action_make;
							// echo "Action make [".($sql_type->action_collections ? "COLL" : "")."|".($sql_type->action_set ? "SET" : "")."]: $ty_model_as, $join_tab_id, ".(is_array($ty_col) ? implode(":", $ty_col) : $ty_col)." <br/>\n";
							/*$am = $sql_type->action_make;
							$t_index = ($am ? 
									"a|".$am[0].$am[1].(is_array($am[2]) ? implode(';', $am[2]) : $am[2]) : 
									($sql_type->inst ? "i|".get_class($sql_type->inst)."|".$sql_type->inst->getId() : 
									"n|")
								).'|'.$sql_type->type."|".$sql_type->ps_as."|p";
							echo "Make index: {$t_index}<br/>\n";*/

							if (is_string($ty_col))
							{
								$type_id = $row[$ty_model_as.".".$ty_col];
								if ($type_id)
								{
									$type_id = (int)$type_id;
									$type_name = $storage->getTypeNameInStorageById($type_id);
								}
							}
							else
							{
								list($type_id, $type_name) = $ty_col;
							}

							if ($type_name && $type_id)
							{
								$val_id = $row[$ty_model_as.".".$join_tab_id];

								if ($val_id)
								{
									if (is_numeric($val_id))
										$val_id = (int)$val_id;

									if (!($object = $objs[$val_id][$type_id]))
									{
										$object = new $type_name();
										$object->setId($val_id);
									
										// echo "We create :: {$type_name}[#{$val_id}]<br/>\n";

										$objs[$val_id][$type_id] = $object;
									}
								}
							}
						}

						if (!($object || $sql_type->action_scalar))
							continue;
					
						if (isset($row["Del__"]) && $object)
						{
							$object->Del__ = filter_var($row["Del__"], FILTER_VALIDATE_BOOLEAN);
						}

						if ($sql_type->action_set)
						{
							list($backref_col, $prop_name, $coll_rowid) = $sql_type->action_set;
						
							// echo "Action SET: $backref_col, $prop_name, $coll_rowid<br/>\n";
						
							// identify parent
							$partent_id = (int)$row[$backref_col];
							$item_rowid = (int)$row[$coll_rowid];
							$coll_arr = $sql_type->action_set_arr;
							$coll_parent = $partent_id ? $this->items[$partent_id] : null;
						
							// echo "Action set | $backref_col, $prop_name, $coll_rowid<br/>\n";

							if ((!$coll_arr) && $partent_id && $coll_parent)
							{
								if (!$item_rowid)
									throw new Exception("missing rowid");

								// $prop_name is missing if the query starts from an array
								$coll_parent = reset($coll_parent);
								if ($prop_name)
								{
									// $prop_name is missing if the query starts from an array
									if (!($coll_arr = $coll_parent->$prop_name))
									{
										$coll_parent->{"set{$prop_name}"}(($coll_arr = new QModelArray()));
										$collection_arr = $coll_arr;
										$coll_arr->_key = 0;
										$coll_arr->setModelProperty($coll_parent->getModelType()->properties[$prop_name], $coll_parent);
									}
								}
								else
									$coll_arr = $this->inst;
							}

							if ($coll_arr)
							{
								if (!$item_rowid)
									throw new Exception("missing rowid");
								// set an element in a collection
								if ($object)
								{
									if (!(is_object($coll_arr)))
									{
										var_dump($coll_arr, $coll_parent);
										throw new Exception("Failed");
									}
									
									if ($populate_only && (($has_elem = $this->findElementBy_RowId_Or_Id($coll_arr, $object, $item_rowid)) !== false))
									{
										$objs[$object->getId()][$storage->getTypeIdInStorage(get_class($has_elem))] = $object = $has_elem;
									}
									else
									{
										if ($coll_parent && $prop_name)
										{
											# possible problem here with populate !!!!
											if ($coll_arr->_key === null)
											{
												# in case we have a populate we need to establish the next key!
												$coll_arr->_key = 0;
												foreach ($coll_arr ?: [] as $carr_k => $carr_v)
												{
													if ((string)$carr_k === (string)(int)$carr_k)
														$coll_arr->_key = max($coll_arr->_key, (int)$carr_k + 1);
												}
											}
											$coll_parent->{"set{$prop_name}_Item_"}($object, $coll_arr->_key++, $item_rowid, false);
										}
										else
											$coll_arr->setWithRowId($item_rowid, $object);
									}
								}
								else if ($sql_type->action_scalar)
								{
									list($collection_as /*, $coll_val_col_ty */) = $sql_type->action_scalar;
									// echo "Action scalar | {$collection_as}<br/>\n";
								
									$coll_val = $row[$collection_as];

									if ($populate_only && (($has_elem = $this->findElementBy_RowId_Or_Id($coll_arr, $coll_val, $item_rowid)) !== false))
									{
										// nothing, just don't set it again
									}
									else
									{
										if ($coll_parent && $prop_name)
										{
											# possible problem here with populate !!!!
											if ($coll_arr->_key === null)
											{
												# in case we have a populate we need to establish the next key!
												$coll_arr->_key = 0;
												foreach ($coll_arr ?: [] as $carr_k => $carr_v)
												{
													if ((string)$carr_k === (string)(int)$carr_k)
														$coll_arr->_key = max($coll_arr->_key, (int)$carr_k + 1);
												}
											}
											$coll_parent->{"set{$prop_name}_Item_"}($coll_val, $coll_arr->_key++, $item_rowid, false);
										}
										else
											$coll_arr->setWithRowId($item_rowid, $coll_val);
									}
								}
							}
						}

						if (!$object)
							continue;

						if ($sql_type->action_collections)
						{
							// echo "Action action_collections<br/>\n";
							foreach ($sql_type->action_collections as $collection_query)
							{
								if ($val_id !== null)
									$collection_query->items[$val_id][$type_id] = $object;
							}
						}

						if ($sql_type->action_prop && ($type_action_prop = $sql_type->action_prop[($object_class = get_class($object))]))
						{
							//var_dump($sql_type->action_prop);
							foreach ($type_action_prop as $prop_name => $prop_opts)
							{
								// echo "action_prop: {$prop_name}<br/>\n";
							
								$p_ty = $prop_opts["ty"];

								$p_type_name = null;
								// here we just determine type
								if (is_string($p_ty))
								{
									$p_type_id = $row[$p_ty];
									if ($p_type_id)
									{
										$p_type_id = (int)$p_type_id;
										$p_type_name = $storage->getTypeNameInStorageById($p_type_id);
									}
								}
								else
								{
									list($p_type_id, $p_type_name) = $p_ty;
									// we have a scalar
									if (!$p_type_name)
									{
										$p_type_name = $p_type_id;
										$p_type_id = null;
									}
								}
							
							
								// scalar 
								if ((($p_type_id === null) || (($prop_name === "Id") && $prop_opts["\$"])) && is_string($p_type_name))
								{
									$prop_val = $row[$prop_opts["\$"]];
									if (($prop_val !== null) && ((!$populate_only) || (!$object->_wst[$prop_name])))
									{
										if (static::$SettersDefined[$object_class][$prop_name] ?? (static::$SettersDefined[$object_class][$prop_name] = method_exists($object, "set{$prop_name}")))
											// setGid($value, $check = true, $null_on_fail = false)
											$object->{"set{$prop_name}"}($prop_val, true, false);
										else
											$object->{$prop_name} = $prop_val;
									}
								}
								// reference
								else if ($p_type_name && $p_type_id)
								{
									if ($populate_only && ((($obj_prop_v = $object->{$prop_name}) !== null) || $object->_wst[$prop_name]))
									{
										// the item can be set but as a new instance and we will get here because of the $object->_wst[$prop_name] condition 
										// we need to check again if we have model and if it has id
										if ($obj_prop_v && $obj_prop_v->getId())
											$objs[$obj_prop_v->getId()][$storage->getTypeIdInStorage(get_class($obj_prop_v))] = $obj_prop_v;
									}
									else
									{
										$prop_val_id = $row[$prop_opts["#"]];
										if (is_numeric($prop_val_id))
											$prop_val_id = (int)$prop_val_id;

										if ($prop_val_id)
										{
											if (!($prop_val = $objs[$prop_val_id][$p_type_id]))
											{
												if ((!$populate_only) || (!$object->_wst[$prop_name]))
												{
													$prop_val = new $p_type_name();
													$prop_val->setId($prop_val_id);
												}


												$objs[$prop_val_id][$p_type_id] = $prop_val;
											}

											// else we have it
											$object->{"set{$prop_name}"}($prop_val, false);
										}
									}
								}
								// alias
								else if (($alias_opt = $prop_opts["as"]))
								{
									$as_val = $row[$alias_opt];
									if (($as_val !== null) && ((!$populate_only) || (!$object->_wst[$prop_name])))
										$object->{$prop_name} = $as_val;
								}
							}
						}
					}
				}

				//echo "Load time: ".((microtime(true) - $t_zero)*1000)."<br/>\n";

				if ($this->calc_rows && $collection_arr)
				{
					if ($this->_count_q)
					{
						$calc_res = $conn->query($this->_count_q);
					}
					else
						$calc_res = $conn->query("SELECT FOUND_ROWS() AS `FOUND_ROWS`;");

					$found_rows = $calc_res->fetch_assoc();
					$collection_arr->setQueryCount((int)$found_rows["FOUND_ROWS"]);
				}

				if ($this->extra_q)
				{
					foreach ($this->extra_q as $subq)
					{
						$subq->parent = $this;
						// query(&$objs, $storage, $conn, $base_from = null, $populate_only = false)
						$subq->query($objs, $storage, $conn, null, $populate_only);
					}
				}	
			}
		}
		finally
		{
			if ($result && $result instanceof \mysqli_result)
			{
				$result->free();
			}
			unset($result);
		}
	}
	
	public function getRootQuery()
	{
		return $this->parent ? $this->parent->getRootQuery() : $this;
	}

	protected function cleanup_F_Actions()
	{
		$f_actions = [];
		
		foreach ($this->f_actions as $sql_type)
		{
			$index = null;
			if ($sql_type->action_prop)
			{
				$am = $sql_type->action_make;
				$index = ($am ? 
								"a|".$am[0].$am[1].(is_array($am[2]) ? implode(';', $am[2]) : $am[2]) : 
								($sql_type->inst ? "i|".get_class($sql_type->inst)."|".$sql_type->inst->getId() : 
								"n|")
							)."|p";
				// echo "Index: $index<br/>\n";$sql_type->type.'|'.$sql_type->ps_as
				
				if (!($obj_indx = $f_actions[$index]))
				{
					$obj_indx = new \stdClass();
					$f_actions[$index] = $obj_indx;
					$obj_indx->action_make = $am;
					$obj_indx->inst = $sql_type->inst;
					// $obj_indx->ps_as = $sql_type->ps_as;
					// $obj_indx->type = $sql_type->type;
				}
				
				foreach ($sql_type->action_prop as $k => $ap)
					// we also index by type to be able to filter
					$obj_indx->action_prop[$sql_type->type][$k] = $ap;
				
				unset($sql_type->action_prop);
			}
			if ($sql_type->action_set || $sql_type->action_collections)
				$f_actions[] = $sql_type;
		}

		return $f_actions ?: null;
	}
	
	public function findElementBy_RowId_Or_Id(\QModelArray $coll_arr, $element, $item_rowid)
	{
		if ($item_rowid && ($k = $coll_arr->_iro[$item_rowid]))
		{
			$compare_with = $coll_arr[$k];
			if (($compare_with && ($element === $compare_with)) ||
				(($compare_with instanceof $element) && ((string)$element->getId() === (string)$compare_with->getId())))
				return $compare_with;
		}
		if ($element instanceof \QIModel)
		{
			$indx = $this->_colls_indexes[$coll_arr] ?? null;
			if (($indx === null) || ($indx === false))
			{
				$indx = [];
				// it was never setup
				foreach ($coll_arr as $e)
				{
					if (($e instanceof \QIModel) && (($id = $e->getId()) !== null))
						$indx[$id][get_class($e)] = $e;
				}
				$this->_colls_indexes[$coll_arr] = $indx;
			}
			
			$id = $element->getId();
			if ($id === null)
				throw new \Exception('this should never happen');
			if (($itm = $indx[$id][get_class($element)]) !== null)
				return $itm;
		}
		
		return false;
	}
}
