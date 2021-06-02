<?php

trait QSqlTable_New
{
	protected function recurseTransactionList_New($connection, array $model_list, $ts = null, $selector = null)
	{
		if ($selector === true)
			throw new \Exception('Not implemented');
		else if ($selector === null)
			throw new \Exception('NULL SELECTOR ?!');
		
		# qvar_dumpk("BEFORE START: ".(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])." sec");
		
		/**
		 * @TODO
		 *		- merge by should be fine now but it needs more tests !
		 *		- $selector = true
		 *		- one2one
		 *		- REAL DELETE OF RECORDS BASED ON CONFIG
		 *		- populate before insert | @storage.populateBeforeInsert | populate before insert as a way to merge by on a reference
		 *		- all other @TODO s
		 */
		
		# Removed :: beforeTransformModelByContainer

		# qvar_dumpk($model_list);
		/*
		$res = $connection->query('SELECT @@autocommit');
		if (!$res)
			throw new \Exception($connection->error);
		qvar_dumpk('SELECT @@autocommit', $res->fetch_row());
		*/
		$t1 = microtime(true);
				
		# function toJSON($selector = null, $include_nonmodel_properties = false, $with_type = true, $with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
		
		$max_row = static::$Max_Allowed_Packet ?: (static::$Max_Allowed_Packet = $connection->query("SHOW VARIABLES LIKE 'max_allowed_packet';")->fetch_assoc());
		$max_query_len = $max_row["Value"] ?: next($max_row) ?: $max_query_len;
		
		$all_objects = new SplObjectStorage();
		$process_objects = new SplObjectStorage();
		foreach ($model_list as $obj)
		{
			# @TODO - put QModelArray in the list, if we have it's parent model and property info, we can use it , if not throw an exception
			if ($obj instanceof \QIModelArray)
			{
				# @TODO - check propery and parent model
				throw new \Exception('@TODO - check propery and parent model');
				
				/*
				foreach ($obj as $v)
				{
					if ($v instanceof \QIModelArray)
						throw new \Exception('QIModelArray inside QIModelArray');
					else if ($obj instanceof \QIModel)
						$process_objects[$v] = $v;
				}
				*/
			}
			else if ($obj instanceof \QIModel)
				$process_objects[$obj] = [$obj, ($ts ?? ($obj->_ts ?? QModel::TransformMerge)), null]; # object, parent, property
		}
				
		# we prepare the info ... so then we can call `recurseTransaction` in a loop per level / (?type - or no type) 
		#				... recurseTransaction may get a list ... and just use that instead of a single one
		#				... also bulk/multi query vs normal query | 
		#				... are backrefs optimsed ? they should run in batches bulk/multi query | 
		#				... we need a multi-query function that can handle errors ok !
		# would YIELD WORK OK in this case ?!, it should
		# 
		
		# qvar_dumpk(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);die;
		
		# qvar_dumpk('$selector', $selector);
		
		$loop_lists = [];
		$backrefs_list = [];
		
		$ticks = 0;
		$m1 = memory_get_usage();
		$t1 = microtime(true);
		static::recurseObjects_New($backrefs_list, $connection, $this->getStorage(), $ts, $loop_lists, $process_objects, $selector, "", $ticks);
		$t2 = microtime(true);
		$m2 = memory_get_usage();
		
		$this->resolveBackrefs_NEW($backrefs_list);
		
		# $connection->query("COMMIT;");
		
		/* qvar_dumpk("mem: " . round(($m2 - $m1) / 1024),
					"time: " . round(($t2 - $t1), 5), $ticks, 
				$connection->_stats);
		*/
		# not a cheap traverse ... maybe we should also prepare the OPs
		# and Q the OPs on a para thread to gain some speed !!!
		#			!!!! this is very smart !!!!

		/*
		foreach ($loop_lists as $k => $data)
		{
			foreach ($data as $ty => $spl_obj)
				qvar_dumpk("{$k}/{$ty}", $spl_obj);
		}
		 * 
		 */
		
		# die("aaa");
	}
	
	protected static function recurseObjects_New(array &$backrefs_list, $connection, $storage, $ts, array &$loop_lists, SplObjectStorage $process_objects, $selector = null, string $path = '', int &$ticks = 0)
	{
		$new_loop_list = []; # by path / type
		$new_loop_list_coll = [];
		$next_recurse_list = [];
		
		foreach ($process_objects as $model_object)
		{
			# list($model_object, $model_parent, $model_property) = $process_objects->getInfo(); # object, parent, property
			$model_class = get_class($model_object);
			$po_info = $process_objects->getInfo();
			$new_loop_list[$po_info[2] ?: 0][$model_class][] = $po_info; # who was his parent ?! $property ... can be extracted from a QModelArray, 
			# $model_id = $model_object->_id ?: $model_object->Id;
			
			foreach ($selector ?: [] as $k => $sub_sel)
			{
				$val = $model_object->$k;
				
				if ($val instanceof \QIModel)
				{
					/*
					if ($next_recurse_list[$k] === null)
						$next_recurse_list[$k] = new SplObjectStorage();
					$next_recurse_list[$k][$val] = [$val, $model_object, $k];
					*/
					
					$ticks++;
					
					if ($val instanceof \QIModelArray)
					{
						if (!isset($val->_ppty))
							$val->setModelProperty($k, $model_object);
						
						$new_loop_list_coll[$model_class][$k][] = [$val, $model_object, $k];
						
						$k_is_null = ($next_recurse_list[$k."[]"] === null);
						foreach ($val as $v)
						{
							if ($v instanceof \QIModel)
							{
								if ($k_is_null)
								{
									$next_recurse_list[$k."[]"] = new SplObjectStorage();
									$k_is_null = false;
								}
								
								/* DO WE NEED THIS ?! ($model - $v)
								if ((!$model_id) && (($model_id = $model->Id) || ($model_id = $model->_id)))
								{
									$model->setId($model_id, 1);
									$model_id = $model->getId();
								}
								*/
								$next_recurse_list[$k."[]"][$v] = [$v, ($ts ?? ($v->_ts ?? QModel::TransformMerge)), $model_class, $model_object]; # ??? dono
								
								$ticks++;
							}
						}
					}
					else
					{
						if ($next_recurse_list[$k] === null)
							$next_recurse_list[$k] = new SplObjectStorage();
						$next_recurse_list[$k][$val] = [$val, ($ts ?? ($val->_ts ?? QModel::TransformMerge)), $model_class, $model_object]; # ??? dono
					}
				}
			}
		}
		
		if ($new_loop_list)
		{
			# qvar_dumpk('$new_loop_list KEYS', array_keys($new_loop_list));
			foreach ($new_loop_list as $parent_class_name_or_zero => $exec_items_list)
			{
				foreach ($exec_items_list as $class_name => $exec_items)
				{
					if ($exec_items)
					{
						$connection->_stats->queries[] = "# {$path} :: {$class_name} ========================================================== ";
						$transaction_item = new QSqlTable_Titem($path, $exec_items, $connection, $storage, $selector, $ts, $class_name, false);
						$transaction_item->run_model($backrefs_list, $parent_class_name_or_zero);
					}
				}
			}
		}
		if ($new_loop_list_coll)
		{
			foreach ($new_loop_list_coll as $parent_class_name => $deep_items)
			{
				foreach ($deep_items as $prop_name => $exec_items)
				{
					if ($exec_items)
					{
						$coll_path = $path ? ($path.".".$prop_name) : $prop_name;
						$connection->_stats->queries[] = "# {$coll_path} :: {$parent_class_name} / {$prop_name} ========================================================== ";
						$transaction_item_coll = new QSqlTable_Titem($coll_path, $exec_items, $connection, $storage, $selector, $ts, null, true, $parent_class_name, $prop_name);
						$transaction_item_coll->run_collection($backrefs_list);
					}
				}
			}
		}
		
		foreach ($next_recurse_list as $k => $list)
		{
			$sub_sel = (substr($k, -2, 2) === '[]') ? $selector[substr($k, 0, -2)] : $selector[$k];
			static::recurseObjects_New($backrefs_list, $connection, $storage, $ts, $loop_lists, $list, $sub_sel, $path ? $path.".".$k : $k, $ticks);
		}
	}
	
	public static function test_multi_updates($connection, string $table_name, array $bulk_ops, array $bulk_ops_types)
	{
		$all_qs = [];
		foreach ($bulk_ops as $id => $updates)
		{
			$str = "UPDATE `{$table_name}` SET ";
			foreach ($updates as $k => $v)
				$str .= "`{$k}`='".addslashes($v)."',";
			$str = substr($str, 0, -1);
			$str .= " WHERE `\$id`='{$id}';\n";
			$all_qs[] = $str;
		}
		
		$queries = "BEGIN;\n".implode('', $all_qs)."\nCOMMIT;\n";
		
		$t1 = microtime(true);
		if (($res = $connection->multi_query($queries)) === false)
		{
			var_dump($queries);
			throw new Exception($connection->error);
		}
		
		while ($connection->next_result()) {;}
		
		# best time = 0.03681 | unstable ?!
		
		/*
		do
		{
			$nr = $connection->next_result();
			if (!$nr)
			{
				var_dump('$nr', $nr);
				break;
			}
		}
		while (true);
		*/
		$t2 = microtime(true);
		
		qvar_dumpk('$res', $res);
		
		qvar_dumpk($t2 - $t1);
		
		die("ALL EXEC!");
	}
	
	public static function test_pp_stmts_updates($connection, string $table_name, array $bulk_ops, array $bulk_ops_types)
	{
		# qvar_dumpk($bulk_ops);

		$params_types = "";
		
		$prep_sql = "UPDATE `{$table_name}` SET ";
		foreach ($bulk_ops_types as $k => $d_type)
		{
			$prep_sql .= "`{$k}`=?,";
			if (($d_type === 'int') || ($d_type === 'integer'))
				$params_types .= "i";
			else if ($d_type === 'string')
				$params_types .= "s";
			else if ($d_type === 'boolean')
				$params_types .= "i";
			else if ($d_type === 'datetime')
				$params_types .= "s";
			else
				throw new \Exception('eeee');
		}
		$params_types .= "i"; # for ID
				
		$prep_sql = substr($prep_sql, 0, -1);
		$prep_sql .= " WHERE `\$id`=?;";
		
		# $ids_list = [];
		# foreach ($bulk_ops as $id => $values)
		#	$ids_list[] = $id;
		
		$data = [];
		
		# qvar_dumpk("SELECT SQL_NO_CACHE `\$id`,`".implode('`,`', array_keys($bulk_ops_types))." FROM `{$table_name}` WHERE `\$id` IN (".implode(",", array_keys($bulk_ops)).");");
		# die;
		
		$t1 = microtime(true);
		$resz = $connection->query("SELECT SQL_NO_CACHE `\$id`,`".implode('`,`', array_keys($bulk_ops_types))."` FROM `{$table_name}` WHERE `\$id` IN (".implode(",", array_keys($bulk_ops)).");");
		while (($r = $resz->fetch_row()))
			$data[$r[0]] = $r;

		$t2 = microtime(true);
		qvar_dumpk($t2 - $t1, $data);
		die("SELECT");
		
		$stmt = $connection->prepare($prep_sql);
		
		$t1 = microtime(true);
		$connection->query("BEGIN;");
		
		foreach ($bulk_ops as $id => $values)
		{
			$valss = $values;
			$valss[] = $id;
			$stmt->bind_param($params_types, ...array_values($valss));
			$rc = $stmt->execute();
		}
		
		$connection->query("COMMIT;");
		$t2 = microtime(true);
		
		qvar_dumpk($t2 - $t1);
	}
	
	public static function test_rnd_updates(array $bulk_ops, array $bulk_ops_types)
	{
		$rand_ops = [];
		foreach ($bulk_ops as $id => $value)
		{
			if (!is_int($id))
				continue;
			$new_row = [];
			foreach ($value as $k => $v)
			{
				if ($k === 'Gid') # do not change Gid
					$new_row[$k] = $v;
				else if (($bulk_ops_types[$k] === 'int') || ($bulk_ops_types[$k] === 'integer'))
					$new_row[$k] = random_int(1, 255);
				else if ($bulk_ops_types[$k] === 'string')
					$new_row[$k] = uniqid('', true);
				else if ($bulk_ops_types[$k] === 'boolean')
					$new_row[$k] = random_int(0, 1);
				else if ($bulk_ops_types[$k] === 'datetime')
					$new_row[$k] = date("Y-m-d H:i:s", random_int(0, time()));
				else
					$new_row[$k] = null;
			}

			$rand_ops[$id] = $new_row;
		}
		
		# static::test_multi_updates($rand_ops, $bulk_ops_types);
		
		return $rand_ops;
	}
	
	protected function resolveBackrefs_NEW(array $backrefs_list)
	{
		$storage = $this->getStorage();
		$connection = $storage->connection;
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();
			
		// $tx = microtime(true);
		foreach ($backrefs_list as $data)
		{
			list($sql_info, $update_column, $reference, $row_identifier, $model_array, $model_array_key) = reset($data);

			$cols_ty_tab = $cols_type_inf[str_replace("`", "",$sql_info["tab"])];

			$parts = array();
			# $yield_obj_binds = [];
			
			foreach ($data as $info)
			{
				list(/* ignore sql info */, $update_column, $reference) = $info;
				if ($update_column === null)
				{
					continue;
				}
				
				$reference_id = $reference->getId();
				$reference_id_str = ($reference_id === null) ? 'NULL' : (string)$reference_id;
				$parts[] = "`{$update_column}`={$reference_id_str}";
				# $yield_obj_binds[] = $reference->getId();

				// var_dump($zzz_sql_info["tab"]." :: ". ($my_row_identifier instanceof QIModel ? $my_row_identifier->getId() : $my_row_identifier));

				$cols_ty_col = $cols_ty_tab ? ($cols_ty_tab[$update_column] ?: $cols_ty_tab[substr($update_column, strlen(QORM_TYCOLPREFIX))]) : null;

				// var_dump($my_sql_info["tab"], $my_sql_info["id_col"], $update_column, $reference->getId(), get_class($reference));

				if (is_string($cols_ty_col) && ($reference instanceof QIModel))
				{
					$bkref_type = $storage->getTypeIdInStorage(get_class($reference));
					$bkref_type_str = ($bkref_type === null) ? 'NULL' : (string)$bkref_type;
					$parts[] = "`{$cols_ty_col}`={$bkref_type_str}";
					# $yield_obj_binds[] = $bkref_type;
					// var_dump($cols_ty_col, $bkref_type);
				}
			}

			// for many to many collections we may need to setup the type, check before we do this update

			$row_id = (($row_identifier instanceof QIModel) ? $row_identifier->getId() : $row_identifier);
			
			if ($parts && $row_id)
			{
				// $query = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $parts)." WHERE `{$sql_info["id_col"]}`=".$row_id;
				
				$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $parts)." WHERE `{$sql_info["id_col"]}`={$row_id};";
				$rc = $connection->query($yield_obj_q);
				if (!$rc)
					throw new \Exception($connection->error);
			}
			else
			{
				if (\QAutoload::GetDevelopmentMode())
					qvar_dumpk('$parts && $row_id', $parts, $row_id, $row_identifier);
				throw new Exception("Empty parts");
			}

			if ($model_array && ($model_array_key !== null))
				$model_array->setRowIdAtIndex($model_array_key, $row_id);
		}
	}
}
