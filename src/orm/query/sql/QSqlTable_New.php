<?php

trait QSqlTable_New
{
	protected function recurseTransactionList_New($connection, array $model_list, $ts = null, $selector = null)
	{
		# if ($selector === true)
		#	throw new \Exception('Not implemented');
		if ($selector === null)
			throw new \Exception('NULL SELECTOR ?!');
		
		### Merge By & Merch.Categ ... does not know how to merge by !!! --- options Pool !!! ---
				# we need to test a joined merge-by - property level and App.property also
				# also optimize if (property level === App.property)
		
		## !!! scalar collections !!!! ONE TO MANY & MANY TO MANY !!!!
		## Mixed many to many with one to many ... using many to many to cross over collections
		## Set backref with multiple types | ??? is it possible ?
		
		# what if we store Services/Products on different tables and have a collection on them ?!
							# will it be the same collection table ? it should if on the same definition ... needs testing !
							# test 1toM/M2M - INSERT / UPDATE / DELETE
		# test that ids are unique inside a collection without a merge by !?
		
		# merge by - defined at prop lvl but not in APP ?! - good/bad ?!
		
		# PERFORMANCE :: GROUP UPDATE/INSERT/DELETE on the same level
		
		/**
		 * @TODO
		 *		- !!!! Simplify setBackReference !!! some kind of callback based on an object's reference || watch out for merge by
		 *				- optimise to do less updates/inserts
		 *		- merge by should be fine now but it needs more tests !
		 *		- [done - must @test] $selector = true - infinite loop !!! make sure we don't repeat !
		 *		- [@test + add resolveOneToOneOps] one2one
		 *		- [stage 2] REAL DELETE OF RECORDS BASED ON CONFIG
		 *		- populate before insert | @storage.populateBeforeInsert | populate before insert as a way to merge by on a reference
		 *		- all other @TODO s
		 * 
		 *		- if we run just a delete via ($array->setTransformState(\QModel::TransformDelete, $pos)), 
		 *				the system will do a merge on the elements inside after the delete ... solutions ?
		 *		- test with multiple DBs !
		 * 
		 *		- if a collection is NOT storage options pool - default it to one to many ?! - @TBD
		 */
		
		/**
		 * @TESTING
		 *		- collection many to many
		 *		- walk code bit by bit and test it
		 *		- test various cases
		 * 
		 * 
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
				throw new \Exception('@TODO - check propery and parent model (A)');
				
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
			else
				throw new \Exception('Not a model.');
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
		
		$locked_objects = [];
		$merge_by_pool = [];
		
		$arr = \QSqlModelInfoType::GetTablePropertyList();
		$table_to_properties = [];
		foreach ($arr as $k => $v)
			$table_to_properties[$v][$k] = $k;
		
		$ticks = 0;
		$m1 = memory_get_usage();
		$t1 = microtime(true);
		static::recurseObjects_New($backrefs_list, $connection, $this->getStorage(), $ts, $loop_lists, 
										$process_objects, $selector, "", $ticks, $locked_objects, $merge_by_pool, $table_to_properties);
		$t2 = microtime(true);
		$m2 = memory_get_usage();
		
		unset($merge_by_pool);
		# $this->resolveBackrefs_NEW($backrefs_list);
		
		foreach ($locked_objects as $lo)
			unset($lo->_lk);
		
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
	
	protected static function recurseObjects_New(array &$backrefs_list, $connection, $storage, $ts, array &$loop_lists, 
					SplObjectStorage $process_objects, $selector = null, string $path = '', int &$ticks = 0, 
					array &$locked_objects = null, array &$merge_by_pool = null, array $table_to_properties = null)
	{
		# qvar_dumpk("PATH :: ".$path);
		
		$new_loop_list = []; # by path / type
		$new_loop_list_coll = [];
		$next_recurse_list = [];
		
		if ($selector === null)
			$selector = [];
		
		$cached_type_props = [];
		
		# qvar_dumpk('$selector :: '.qImplodeEntity($selector));
		
		foreach ($process_objects as $model_object)
		{
			# list($model_object, $model_parent, $model_property) = $process_objects->getInfo(); # object, parent, property
			$model_class = get_class($model_object);
			$po_info = $process_objects->getInfo();
			$new_loop_list[$po_info[2] ?: 0][$model_class][] = $po_info; # who was his parent ?! $property ... can be extracted from a QModelArray, 
			# $model_id = $model_object->_id ?: $model_object->Id;
			$model_properties = $cached_type_props[$model_class] ?: ($cached_type_props[$model_class] = \QModel::GetTypeByName($model_class)->properties);
			
			$loop_over = ($selector === true) ? $model_properties : $selector;
			
			foreach ($loop_over as $k => $sub_sel_or_val)
			{
				$val = ($selector === true) ? $sub_sel_or_val : $model_object->$k;
				
				if ($val instanceof \QIModel)
				{
					/*
					if ($next_recurse_list[$k] === null)
						$next_recurse_list[$k] = new SplObjectStorage();
					$next_recurse_list[$k][$val] = [$val, $model_object, $k];
					*/
					if ($selector === true)
					{
						if ($val->_lk)
							continue;
						$val->_lk = true;
						$locked_objects[] = $val;
					}
					
					$ticks++;
					
					if ($val instanceof \QIModelArray)
					{
						if (!isset($val->_ppty))
							$val->setModelProperty($k, $model_object);
						
						$new_loop_list_coll[$model_class][$k][] = [$val, $model_object, $k];
						# qvar_dumpk('$new_loop_list_coll', $new_loop_list_coll);
						
						$k_is_null = ($next_recurse_list[$k."[]"] === null);
						foreach ($val as $coll_val_key => $v)
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
							# else
							# {
								# $next_recurse_list[$k."[]"][] = [$v, ($ts ?? $val->getTransformState($coll_val_key)), $model_class, $model_object]; # ??? dono
								# $ticks++;
							# }
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
		
		$transaction_elems = [];
		
		if ($new_loop_list)
		{
			# qvar_dumpk('$new_loop_list KEYS', $new_loop_list);
			foreach ($new_loop_list as $parent_class_name_or_zero => $exec_items_list)
			{
				foreach ($exec_items_list as $class_name => $exec_items)
				{
					if ($exec_items)
					{
						# qvar_dumpk("# {$path} :: {$parent_class_name_or_zero} :: {$class_name} ========================================================== ");
						$connection->_stats->queries[] = "# {$path} :: {$parent_class_name_or_zero} :: {$class_name} ========================================================== ";
						$transaction_item = new QSqlTable_Titem($path, $exec_items, $connection, $storage, $selector, $ts, $class_name, false);
						$transaction_item->run_model($backrefs_list, $parent_class_name_or_zero, $merge_by_pool);
						
						if ($transaction_item->backrefs_list)
							$transaction_elems[] = $transaction_item;
					}
				}
			}
		}

		if ($new_loop_list_coll)
		{
			# qvar_dumpk('$new_loop_list_coll KEYS', $new_loop_list_coll);
			foreach ($new_loop_list_coll as $parent_class_name => $deep_items)
			{
				foreach ($deep_items as $prop_name => $exec_items)
				{
					if ($exec_items)
					{
						$coll_path = $path ? ($path.".".$prop_name) : $prop_name;
						# qvar_dumpk("# {$coll_path} :: {$parent_class_name} / {$prop_name} ========================================================== ");
						$connection->_stats->queries[] = "# {$coll_path} :: {$parent_class_name} / {$prop_name} ========================================================== ";
						
						$transaction_item_coll = new QSqlTable_Titem($coll_path, $exec_items, $connection, $storage, $selector, $ts, null, true, $parent_class_name, $prop_name);
						$transaction_item_coll->run_collection($backrefs_list, $table_to_properties);
					
						if ($transaction_item_coll->backrefs_list)
							$transaction_elems[] = $transaction_item_coll;
					}
				}
			}
		}
		
		foreach ($next_recurse_list as $k => $list)
		{
			$sub_sel = ($selector === true) ? true : ((substr($k, -2, 2) === '[]') ? $selector[substr($k, 0, -2)] : $selector[$k]);
			static::recurseObjects_New($backrefs_list, $connection, $storage, $ts, $loop_lists, $list, $sub_sel, 
						$path ? $path.".".$k : $k, $ticks, $locked_objects, $merge_by_pool, $table_to_properties);
		}
		
		foreach ($transaction_elems as $transaction_e)
		{
			$transaction_e->resolveBackrefs_NEW();
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
}
