<?php
/**
 * 
 */
final class QSqlTable_Titem
{
	private static $_MergeByInfo = [];
	private static $__Tmp_Model_Id = 0;
	/**
	 * @var string
	 */
	public $path;
	/**
	 * @var array
	 */
	public $items;
	public $ts;
	public $class_name;
	/**
	 * @var boolean
	 */
	public $is_collection;
	public $connection;
	public $selector;
	public $storage;
	
	# determined
	public $table_name;
	public $model_type;
	public $backrefs_list = [];
	
	public function __construct(string $path, array $items, $connection, $storage, $selector, $ts, string $class_name = null, bool $is_collection = false)
	{
		$this->path = $path;
		$this->items = $items;
		$this->connection = $connection;
		$this->storage = $storage;
		$this->selector = $selector;
		$this->ts = $ts;
		$this->class_name = $class_name;
		$this->is_collection = $is_collection;
	}
	
	public function run_model(array &$backrefs_list, string $parent_class_name_or_zero, array &$existing_records_global, array &$merge_by_pool, array &$merge_by_linked, array &$merge_by_posib_not_linked)
	{
		# qvar_dumpk('$parent_class_name_or_zero', $parent_class_name_or_zero);
		# qvar_dumpk($this->path." : count(".count($this->items).")");
		
		$ts = $this->ts;
		$class_name = $this->class_name;
		$this->model_type = \QModel::GetTypeByName($class_name);
		$app_class_name = get_class(QApp::Data());
		$is_top_lvl_class = ($class_name === $app_class_name);
		
		# we know it's class, parent class, and property ... determine all mergeBy info
		
		$sql_cache = [];
		$connection = $this->connection;
		$selector = $this->selector;
		
		$storage_table_obj = QApp::GetStorage()->getDefaultStorageContainerForType($this->model_type);
		$this->table_name = $storage_table_obj->name;
		
		# @TODO
		# $mergeBy_duplicate_objs = new \SplObjectStorage();
		$merge_by_meta = null;
		$merge_by_data = [];
		
		$merge_by_find = []; # $merge_by_pool :: Parent_Class/Parent_Id/Property
		
		# @TODO if we have 2-more objects same ref, and one gets inserted, will get an ID
		
		$ids_to_q = [];
		# $merge_by_to_q = [];
		
		$path_parts = explode('.', $this->path);
		$parent_property_name = end($path_parts);
		if (substr($parent_property_name, -2, 2) === '[]')
			$parent_property_name = substr($parent_property_name, 0, -2);
		
		$parent_types_cache = [];
		$parent_props_cache = [];
		
		# qvar_dumpk($parent_property_name, $parent_class_name_or_zero, $this->is_collection);
		$parent_type_obj = $parent_class_name_or_zero ? \QModel::GetTypeByName($parent_class_name_or_zero) : null;
		# $parent_property_reflection = $parent_type_obj ? $parent_type_obj->properties[$parent_property_name] : null;
		# $parent_property_is_collection = $parent_type_obj ? $parent_property_reflection->hasCollectionType() : null;
		# no point to merge by if not inside a collection
		# if (!$parent_property_is_collection)
		#	$merge_by_meta = false;
		
		$is_collection_on_top_level = ($parent_class_name_or_zero === $app_class_name) && 
										$parent_type_obj->properties[$parent_property_name]->hasCollectionType();
		
		$walk_struct = null;
		
		# if ($merge_by_meta === null) # always here
		{
			$parent_property_obj = null;
			if ($parent_class_name_or_zero && $parent_property_name)
			{
				$parent_property_obj = $parent_props_cache[$parent_class_name_or_zero];
				if ($parent_property_obj === null)
				{
					$parent_type = $parent_types_cache[$parent_class_name_or_zero];
					if ($parent_type === null)
						$parent_types_cache[$parent_class_name_or_zero] = $parent_type = ($parent_type_obj ?: false);
					$parent_props_cache[$parent_class_name_or_zero] = $parent_property_obj = ($parent_type->properties[$parent_property_name] ?: false);
				}
			}

			$merge_by_meta = $this->get_mergeby_meta($class_name, $parent_property_name, $parent_class_name_or_zero, $parent_property_obj ?: null);
			# if ($merge_by_meta === false)
			#	continue;
		}
		
		$selector_for_mergeby_was_checked = false;
		# $t1 = microtime(true);
		foreach ($this->items as $model_inf)
		{
			# @TODO - merge by rules also !
			list ($model, /*$action*/, /*$parent_model_class */, $parent_model_object) = $model_inf;
			$model_id = (int)$model->getId() ?: null;
			
			if ($model_id)
				$ids_to_q[$model_id] = $model_id;
			else if ($merge_by_meta !== false)
			{
				if (!$selector_for_mergeby_was_checked)
				{
					if (!empty($merge_by_meta))
					{
						# Merge by properties must be in the save selector for elements without an id
						if (empty($selector))
						{
							# qvar_dumpk($this->path, $merge_by_meta);
							throw new \Exception('Merge by properties must be in the save selector for elements without an id (A).');
						}
						else if ($selector !== true)
						{
							foreach ($merge_by_meta as $merge_by_meta_sel => $merge_by_meta_misc)
							{
								if (qSelectorsMissing($selector, $merge_by_meta_sel))
								{
									qvar_dumpk($this->path, $selector, $merge_by_meta_sel);
									throw new \Exception('Merge by properties must be in the save selector for elements without an id (B).');
								}
							}
						}
					}
					$selector_for_mergeby_was_checked = true;
				}
				
				$this->get_mergeby_data($merge_by_meta, $merge_by_data, $merge_by_find, $merge_by_pool, $merge_by_linked, $is_collection_on_top_level, $model, $parent_class_name_or_zero, $parent_model_object, $parent_property_name);
			}
			
			if ($is_collection_on_top_level && $merge_by_meta)
			{
				$mby_key = $model->_gp[0];
				if ($mby_key)
				{
					$mby_key = $mby_key[2] ?: $mby_key[1];	
				}
				else
				{
					if (!$walk_struct)
						$walk_struct = reset($merge_by_meta)[0];
					list (/*$data, */$valid, $mby_key) = $this->extract_data_from_path($walk_struct, $model);
					if (!$valid)
						throw new \Exception('The object does not have all data for merge by (B): '.reset($merge_by_meta)[6].' | '.$mby_key);
				}
				
				$merge_by_linked[$parent_class_name_or_zero][$parent_model_object->getId()][$parent_property_name][$mby_key] = true;
			}
		}
						
		# $t2 = microtime(true);
		# qvar_dumpk('Prepare mby: ' . (($t2 - $t1)*1000));
		
		# $t1 = microtime(true);
		
		$ids_found_by_mby = [];
		
		if ($merge_by_meta && $merge_by_data)
		{
			foreach ($merge_by_meta as $mby_key => $mby_info)
			{
				list($mby_parts, $mby_pos, $mby_types, $mby_app_props) = $mby_info;
				$mby_data_c = $merge_by_data[$mby_pos];
				
				$tmp_app = null;
				$key_val_data = null;
				
				if ($mby_types[0])
				{
					# app merge by
					$array = new \QModelArray();
					$tmp_app = \QApp::NewData();
					$array[] = $tmp_app;
					$key_val_data = [];
					$key_val_data[$tmp_app->getId()] = $mby_data_c[0];
					foreach ($mby_app_props as $app_prop)
						$this->process_merge_by(true, $mby_info, $array, $app_prop, $key_val_data, $merge_by_find[$mby_pos], $class_name, $ids_to_q, $ids_found_by_mby);
				}
				if ($mby_types[1] && $parent_property_name)
				{
					if ($mby_types[0])
					{
						throw new \Exception('We need to test this !?');
					}
					
					foreach ($mby_data_c as $k => $mby_data_item)
					{
						if (is_numeric($k))
							continue;
						
						$array = new \QModelArray();
						$array_count = 0;
						foreach ($mby_data_item as $parent_itm_id => $parent_itm)
						{
							$tmp_parent_itm = new $k();
							$tmp_parent_itm->setId($parent_itm_id);
							$array[] = $tmp_parent_itm;
							$array_count++;
						}

						$this->process_merge_by(false, $mby_info, $array, $parent_property_name, $mby_data_item, $merge_by_find[$mby_pos], $class_name, $ids_to_q, $ids_found_by_mby);
					}
				}
			}
		}
		# $t2 = microtime(true);
		# qvar_dumpk('RUN mby: ' . (($t2 - $t1)*1000));
		
		if (!isset($existing_records_global[$class_name]))
			$existing_records_global[$class_name] = [];
		$existing_records = &$existing_records_global[$class_name];
		$id_col_name = null;
		
		$sql_info_model = $this->getSqlInfo_model($sql_cache, $selector);
		# $extend_selector = $sql_info_model['extend_selector'];
		# if ($extend_selector) { }
		# idf object ... relation | type:id | parent(ty:id):property:mergeby
		# load object -> do action | set backref
		# @storage.oneToOne --> put it on the backref action

		if ($ids_to_q)
		{
			$id_col_name = $sql_info_model["id_col"];
			
			$yield_obj_q = "SELECT `".$id_col_name.($sql_info_model["cols"]["%"] ? "`,`".implode("`,`", $sql_info_model["cols"]["%"]) : "").
							"` FROM ".$sql_info_model["tab"]." WHERE `{$id_col_name}` IN ("
								.implode(",", $ids_to_q).");";
						
			$res_sel = $this->query($yield_obj_q);
			if ($res_sel === false)
			{
				if (\QAutoload::GetDevelopmentMode())
					qvar_dumpk('$yield_obj_q', $yield_obj_q);
				throw new \Exception($connection->error);
			}
			while (($row = $res_sel->fetch_assoc()))
				$existing_records[$row[$id_col_name]] = $row;
		}
		
		$merge_by_needs_insert = $merge_by_meta && reset($merge_by_meta)[10] ? true :  false;
		$merge_by_needs_insert_meta = $merge_by_needs_insert ? reset($merge_by_meta) : null;
		
		$one_to_one_ops = [];

		foreach ($this->items as $model_inf)
		{
			list ($model, $action, /*$parent_model_class */, $parent_model_object) = $model_inf;
			$model_id = (int)$model->getId() ?: null;
			
			#$model_id = $model->_id ?: $model->Id;
			# $action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
			$create = ($action & QModel::TransformCreate);
			$modify_action = ($update = ($action & QModel::TransformUpdate)) || $create;
			
			if ($create && (!$model_id) && $merge_by_meta && ($tmp_mbymodel = $model->_gp))
			{
				# check if another one with the same merge-by was inserted
				foreach ($tmp_mbymodel as $tmp_mbykeys)
				{
					if (isset($tmp_mbykeys[2])) # by property
						$simblings = $merge_by_find[$tmp_mbykeys[0]][$tmp_mbykeys[1]][$tmp_mbykeys[2]];
					else if (isset($tmp_mbykeys[1])) # by type
						$simblings = $merge_by_find[$tmp_mbykeys[0]][$tmp_mbykeys[1]];
					else
						continue;

					foreach ($simblings ?: [] as $simbling_model)
					{
						$simbling_id = $simbling_model->getId();
						if ($simbling_id)
						{
							$model->setId($simbling_id);
							$model_id = $simbling_id;
						}
					}
				}
			}
			
			// @todo : this is a very ugly fix to avoid insering into the main entry !!!
			if ($is_top_lvl_class && $model_id)
			{
				if ($create)
					$action &= ~QModel::TransformCreate;
				if ($action & QModel::TransformDelete)
					$action &= ~QModel::TransformDelete;
			}
			
			# getSqlInfo_model(array &$cache, array &$backrefs_list, QIModel $model, $selector = null)
			$sql_info = $this->getSqlInfo_prepare_vals($sql_cache, $this->class_name, $sql_info_model, $backrefs_list, $model, $selector, $this->model_type->properties, $model_id);
			$entry_exists = false;
			
			if ($modify_action)
			{
				$rowid = $model_id ?: null;
				$update_done = false;
				$meta = $sql_info['meta'];
				
				$record = $cols_inf = $changed_props = $cleanup_q = $sql_update = $sql_insert = null;
				
				/* # @TODO - merge by
				$duplicates_list = $mergeBy_duplicate_objs->contains($model) ? $mergeBy_duplicate_objs[$model] : null;
				$first_duplicate = $duplicates_list ? reset($duplicates_list) : null;
				$is_not_first = $duplicates_list ? ($first_duplicate !== $model) : null;
				*/
				
				$record_rowid = null;
				if ($model_id)
				{
					$record_rowid = $existing_records[$model_id];
					if (!$record_rowid)
						throw new \Exception("Missig record for item with id.");
				}
				
				if ($update && ($record_rowid !== null))
				{
					$record = $record_rowid;
					$entry_exists = true;

					$model->_tsx = $action & (~QModel::TransformCreate);

					$changed_props = [];
					
					$sql_info_vals = $sql_info["vals"];

					// detect changed properties
					// also detect type change ?! TO DO !!!!
					foreach ($sql_info["cols"] as $p_name => $cols_inf)
					{
						if ($p_name === "%")
							continue;

						$value = $model->{"get{$p_name}"}();
						if (($value === null) && (!$model->_wst[$p_name]))
							# was not set
							continue;

						$scalar_was_changed = false;

						if ($cols_inf["val"] && (($old_sc_val = $record[$cols_inf["val"]]) !== $value))
						{
							# @TODO later ... type aware compare :: '2021-06-04 00:00:00' == '2021-06-04' !!!
							#					... we need to be aware of the SQL engine format/cleanup vs our format
							
							if ($value === null)
								$scalar_was_changed = $model->_wst[$p_name]; # explicit set to null
							else if (is_bool($value))
								$scalar_was_changed = ($value !== (bool)$old_sc_val);
							else
								$scalar_was_changed = (((string)$old_sc_val) !== ((string)$value));
							/*$scalar_was_changed = (($value === null) || is_bool($value)) ? 
								($model->_wst[$p_name] ? true : false) : 
								(((string)$old_sc_val) !== ((string)$value));*/
						}

						if (
								// SCALAR
								($scalar_was_changed) ||
								// REFERENCE
								(($cols_inf["ref"]) && ($record[$cols_inf["ref"]] != (($value instanceof QIModel) ? (int)$value->getId() : null))) || 
								// TYPE
								(($cols_inf["type"]) && ($record[$cols_inf["type"]] != $this->extractTypeIdForVariable($value)))
							)
						{
							$changed_props[$p_name] = $p_name;
							
							if (($one_to_one = $meta[$p_name]['oneToOne']))
							{
								# if ($old_value) ... bla 
								# qvar_dumpk('@TODO oneToOne', $one_to_one, $record[$cols_inf["ref"]], $record[$cols_inf["type"]], $value, $model);
								# throw new \Exception('ex:oneToOne :: update');
								# $one_to_one_ops[][$p_name][] = [$model, $p_name, $one_to_one];
								if ($value)
									$value->{"set{$one_to_one[0]}"}($model);
								else if (($old_value = $record[$cols_inf["ref"]]))
								{
									# @TODO --- yeah ... back-reference ?! - why not !
									# @TODO --- back-reference in both cases !!!
									qvar_dumpk("@TODO - unset oneToOne");
									throw new \Exception('@TODO - unset oneToOne');
								}
							}

							if ($p_name !== "_type")
								$model->_ols[$p_name] = "n/a";
							
							# @TODO - populate before insert ?!
							if (($value instanceof QIModel) && (!($value instanceof QIModelArray)))
							{
								$info_to_set = [$cols_inf["ref"] => $value];
								if ($cols_inf['type'])
									$info_to_set[$cols_inf['type']] = $value->get_Type_Id();
								
								$this->setBackReference_New($sql_info, "UPDATE", $cols_inf["ref"], $model, $info_to_set);
							}
						}
					}

					if (($record["_type"] !== null) && ($record["_type"] !== $this->model_type->getIntId()))
					{
						// we have a type change
						list ($yield_obj_q, $yield_obj_binds, $yield_type) = $this->getCleanupQueryForTypeChange(QModelType::GetModelTypeById($record["_type"]), $model->getModelType(), 
																$record[$model->getModelType()->getRowIdColumnName()], $connection);

						# $yield_obj = (object)['type' => $yield_type, 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
						throw new \Exception('@TODO - Type change!');
					}

					// also detect type change ?! TO DO !!!! we skip atm
					unset($changed_props["_type"]);

					$upd_pairs = [];
					# $yield_obj_binds = [];
					
					# @TODO - update existing records !
					
					foreach ($changed_props as $ch_prop)
					{
						if (($c = $sql_info["cols"][$ch_prop]["val"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=".$this->escape($sql_info_vals[$ch_prop]["val"]);
							# $yield_obj_binds[] = $sql_info["vals"][$ch_prop]["val"];
						}
						if (($c = $sql_info["cols"][$ch_prop]["ref"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=".$this->escape($sql_info_vals[$ch_prop]["ref"]);
							# $yield_obj_binds[] = $sql_info["vals"][$ch_prop]["ref"];
						}
						if (($c = $sql_info["cols"][$ch_prop]["type"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=".$this->escape($sql_info_vals[$ch_prop]["type"]);
							# $yield_obj_binds[] = $sql_info["vals"][$ch_prop]["type"];
						}

						# deprecated
						/*
						if ((($prop_item = $model->$ch_prop) instanceof \QIModel) && ($prop_app_property = $this->model_type->properties[$ch_prop]->getAppPropertyFor(get_class($prop_item))))
						{
							$app_bindings[$prop_app_property][] = $prop_item;
						}
						*/
					}

					// var_dump($upd_pairs);
					if ((!empty($upd_pairs)) && $changed_props)
					{
						$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=".$this->escape($rowid);
						$rc = $this->query($yield_obj_q);
						if (!$rc)
							throw new \Exception($connection->error);
					}

					$update_done = true;
				}
				
				if ($create && (!$update_done))
				{
					if ($rowid !== null)
					{
						$sql_info["cols"]["%"][] = $sql_info["id_col"];
						$sql_info["vals"]["%"][] = $rowid ?: null;
					}

					$model->_tsx = $action & (~QModel::TransformUpdate);
					
					if (empty($sql_info["cols"]["%"]))
					{
						$sql_info["cols"]["%"][$sql_info["id_col"]] = $sql_info["id_col"];
						$sql_info["vals"]["%"][$sql_info["id_col"]] = null;
					}
					
					foreach ($sql_info["cols"] as $p_name => $cols_inf)
					{
						if ($p_name === "%")
							continue;
					
						$value = $model->{"get{$p_name}"}();
						if (($value === null) && (!$model->_wst[$p_name]))
							# was not set
							continue;
							
						if ($value && ($one_to_one = $meta[$p_name]['oneToOne']))
						{
							# @TODO - old value must be set back to null if it's no longer the same
							#		- new value must be linked to model if exists
							#		- so there will be up to 2 back-ref actions to be set
							#		- how will this affect change events ????
							
							# qvar_dumpk('xxxxxxxxxxxxxx', $value, $one_to_one);
							# $value->{"set{$one_to_one[0]}"}($model);
							# qvar_dumpk('---------', $value);
							# die;
							# $this->setBackReference_New($sql_info, "UPDATE", $cols_inf["ref"], $model, $info_to_set);
							# $this->setBackReference_New($sql_in, $upd_pairs, $yield_obj_q, $identified_by, $changed_props)
						}
						
						# deprecated
						/*
						if ((($prop_item = $model->$p_name) instanceof \QIModel) && ($prop_app_property = $model->getModelType()->properties[$p_name]->getAppPropertyFor(get_class($prop_item))))
						{
							$app_bindings[$prop_app_property][] = $prop_item;
						}
						*/
						
						if (($value instanceof QIModel) && (!($value instanceof QIModelArray)))
						{
							# $this->setBackReference($backrefs_list, $sql_info_model, $cols[$p_name]["ref"], $value, $model);
							$info_to_set = [$cols_inf["ref"] => $value];
							if ($cols_inf['type'])
								$info_to_set[$cols_inf['type']] = $value->get_Type_Id();
							# qvar_dumpk('setBackReference_New REF', $info_to_set);
							$this->setBackReference_New($sql_info, "INSERT", $cols_inf["ref"], $model, $info_to_set);
						}
					}
					
					$escaped_vals = [];
					$use_cols = [];
					foreach ($sql_info["vals"]["%"] as $tmp_ins_val)
					{
						$escaped_vals[] = $this->escape($tmp_ins_val);
					}
										
					$yield_obj_q = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $sql_info["cols"]["%"])."`) VALUES (".implode(",", $escaped_vals).");";
										
					$rc = $this->query($yield_obj_q);
					if (!$rc)
					{
						if (\QAutoload::GetDevelopmentMode())
							qvar_dumpk('$yield_obj_q', $yield_obj_q, $sql_info, $model_id, $existing_records);
						throw new \Exception($this->connection->error);
					}
					
					$insert_id = (int)$this->connection->insert_id;
					if (!$insert_id)
						throw new \Exception('This should not happen.');
					
					$inserted_row = array_combine($sql_info_model["cols"]["%"], $sql_info["vals"]["%"]);
					$inserted_row[$sql_info['id_col']] = $insert_id;
					$existing_records[$insert_id] = $inserted_row;
					
					$model->setId($insert_id);

					/* # OLD WAY - deprecated
					if ($meta)
					{
						# @TODO oneToOne
						
						foreach ($meta as $p_name => $m_pdata)
						{
							$value = $model->$p_name;
							if (($value instanceof \QIModel) && ($one_to_one = $m_pdata['oneToOne']))
							{
								list($one_to_one_name, $one_to_one_types) = $one_to_one;
								$value->{"set{$one_to_one_name}"}($model);

								if (($selector !== null) && ($selector !== true) && ($selector[$p_name][$one_to_one_name] === null))
								{
									$selector[$p_name][$one_to_one_name] = [];
								}
							}
						}
					}
					*/
				}
			
				if ($merge_by_needs_insert && (empty($ids_found_by_mby) || (!$ids_found_by_mby[$model->getId()])))
				{
					# $ret[$type_mergeBy][3] = $mby_app_props; $ret[$type_mergeBy][4] = \QApp::GetDataClass(); $ret[$type_mergeBy][5] = \QApp::GetDataId();
					$merge_by_posib_not_linked[$merge_by_needs_insert_meta[4]][$merge_by_needs_insert_meta[5]][reset($merge_by_needs_insert_meta[3])][$model->_gp[0][1]] = $model;
					# @TODO ... 1. make sure it's not already in
					#			2. add it in the collection
					#			3. the collection must be processed last, bu hu hu
					# qvar_dumpk('$merge_by_needs_insert', $ids_found_by_mby, $model);
					# die;
					# flag it as not inserted ?!
				}
			}
			else if ($action & QModel::TransformDelete)
			{
				if (!$model->getId())
					throw new \Exception('Missing Id for delete');
				
				# @TODO we should still do the ONE TO ONE ? - do we respect ref rules ? - I think we should !!!!
				/*
				if ($value && ($one_to_one = $meta[$p_name]['oneToOne']))
				{
					qvar_dumpk('@TODO oneToOne');
					throw new \Exception('ex:oneToOne :: insert');

					$one_to_one_ops[$class_name][$p_name][] = [$model, $p_name, $one_to_one];
				}
				*/
				
				# useless in soft-delete mode
				
				/*
				$yield_obj_q = "UPDATE {$sql_info["tab"]} SET {$sql_info["tab"]}.`Del__`=1 WHERE `{$sql_info["id_col"]}`=?";
				// $yield_obj_q = "DELETE FROM {$sql_info["tab"]} WHERE `{$sql_info["id_col"]}`=?";
				$yield_obj_binds = [$model->getId()];

				$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
				yield($yield_obj);

				if (($res = $yield_obj->result))
				{
					$model->_tsx = QModel::TransformDelete;
				}
				# @TODO
				qvar_dumpk('action', $action);
				throw new \Exception('@Todo - delete');
				*/
			}
		}
		
		unset($merge_by_find);
		
		# return [$extend_selector ?: null, ];
	}
	
	public function run_collection(array &$backrefs_list, array $table_to_properties)
	{
		# mergeBy can be per type / collection
		# mergeBy can be on run_model also, mergeBy can be anywhere where there is no ID
		# mergeBy can be relative to collection also
		
		$ts = $this->ts;
		$class_name = $this->class_name;
		$this->model_type = \QModel::GetTypeByName($class_name);
		$app_class_name = get_class(QApp::Data());
		$is_top_lvl_class = ($class_name === $app_class_name);
		
		$sql_cache = [];
		$connection = $this->connection;
		$selector = $this->selector;
		
		list($first_collection) = reset($this->items);
		
		if (!$first_collection)
			throw new \Exception('Missing first collection');
		
		# qvar_dumpk('$first_collection', $first_collection, $first_collection->getModelProperty());
		$property = $first_collection->getModelProperty();
		if (!$property)
			throw new \Exception('Missing property');

		$one_to_many = $property->isOneToMany();
		$storage_table_obj = QApp::GetStorage()->getDefaultStorageContainerForType($property);
		if (!$storage_table_obj)
			throw new \Exception('Missing storage_table_obj');
		$this->table_name = $storage_table_obj->name;
		if (!$this->table_name)
			throw new \Exception('Missing table_name');
		
		# getSqlInfo_collection(&$cache, $one_to_many = false, QModelProperty $property = null, $selector = null)
		$sql_info = $this->getSqlInfo_collection($sql_cache, $one_to_many, $property);
		$sql_info_table_types = null;
		
		if (!$one_to_many)
		{
			$sql_info_tab = str_replace('`', '', $sql_info['tab']);
			$sql_info_table_types = \QSqlModelInfoType::GetTableTypes($sql_info_tab);
			
			if (!$sql_info_table_types)
			{
				if (count($table_to_properties[$sql_info_tab]) > 1)
				{
					# If we implement multiple collections to the same M2M table ... we will need to do some testing
					#			most notably on the DELETE SIDE !!! & then cleanup
					throw new \Exception('Multiple collections inside the same many to many table is not implemented. '.$sql_info_tab);
				}
			}
		}
		
		# LOAD EXISTING DATA
		$find_by_uniqueness = (!$one_to_many) && $property->getCollectionType()->hasAnyInstantiableReferenceTypes();
		
		$reading_queries_by_rowid = [];
		$reading_queries_by_uniqn = [];
		$data_by_rowid = [];
		$data_by_uniqueness = [];
		
		$collection_has_item_type = $sql_info['cols']['type'] ? true : false;
		$collection_has_bkref_type = $sql_info['cols']['bkref_type'] ? true : false;
		
		$tmp_use_cols = [$sql_info["cols"]['ref'], $sql_info["cols"]['bkref']];
									if ($collection_has_item_type)
										$tmp_use_cols[] = $sql_info["cols"]['type'];
									if ($collection_has_bkref_type)
										$tmp_use_cols[] = $sql_info["cols"]['bkref_type'];
		
		# READ EXISTING DATA
		# if (!$one_to_many)
		
		{
			foreach ($this->items as $model_inf)
			{
				list ($model, $parent_model) = $model_inf;
				$parent_id = (int)$parent_model->getId();
				$parent_type_id = $parent_model->get_Type_Id();
				
				$action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
				$update = ($action & QModel::TransformUpdate);

				foreach ($model as $key => $item)
				{
					$item_is_model = ($item instanceof QIModel);
					$item_action = $item_is_model ? (($item->_ts !== null) ? $item->_ts : QModel::TransformMerge) : null;
					$item_update = $item_action ? ($item_action & QModel::TransformUpdate) : $update;

					$rowid = (int)$model->getRowIdAtIndex($key);
					
					$uniqueness = null;
					// hasAnyInstantiableReferenceTypes
					// for QIModel on one to many, the rowid is the ID of the element
					if (($rowid === 0) && $item_is_model)
					{
						if ($one_to_many)
							$rowid = (int)$item->getId();
						else if ($find_by_uniqueness && ($tmp_mp_id = $parent_id) && ($tmp_it_id = (int)$item->getId()))
						{
							$uniqueness = ["{$tmp_it_id}\x00{$tmp_mp_id}".
												($collection_has_item_type ? "\x00".$item->get_Type_Id() : '').
												($collection_has_bkref_type ? "\x00".$parent_type_id : ''), 
											"({$tmp_it_id},{$tmp_mp_id}".
												($collection_has_item_type ? ",".$item->get_Type_Id() : '').
												($collection_has_bkref_type ? ",".$parent_type_id : '').
												')'];
						}
					}
					
					if (!$one_to_many)
					{
						# if ($rowid || $uniqueness)
						{
							// select & update
							if ($rowid)
							{
								// $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
								if (!isset($reading_queries_by_rowid[0]))
								{
									$reading_queries_by_rowid[0] = "SELECT `".implode("`,`", $sql_info["cols"])."`,`".$sql_info["id_col"]."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."` IN ";
								}
								$reading_queries_by_rowid[1][$rowid] = $rowid;
								$reading_queries_by_rowid[2] = $sql_info["id_col"];
							}
							else if ($uniqueness) // by uniqueness
							{
								if (!isset($reading_queries_by_uniqn[0]))
								{
									$reading_queries_by_uniqn[0] = [
											"SELECT `".implode("`,`", $tmp_use_cols)."`,`{$sql_info["id_col"]}` FROM {$sql_info["tab"]} WHERE (`".implode("`,`", $tmp_use_cols)."`) IN ",
											$tmp_use_cols];
								}
								$reading_queries_by_uniqn[1][$uniqueness[0]] = $uniqueness[1];
							}
						}
					}
					# else // ONE TO MANY
					else
					{
						if ($rowid)
						{
							// $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
							if (!isset($reading_queries_by_rowid[0]))
							{
								$reading_queries_by_rowid[0] = "SELECT `".implode("`,`", $sql_info["cols"])."`,`".$sql_info["id_col"]."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."` IN ";
							}
							$reading_queries_by_rowid[1][$rowid] = $rowid;
							$reading_queries_by_rowid[2] = $sql_info["id_col"];
						}
					}
				}
			}
		}
		
		if ($reading_queries_by_rowid && $reading_queries_by_rowid[1])
		{
			$id_col_name = $reading_queries_by_rowid[2];
			$res = $this->query($reading_queries_by_rowid[0]."( ".implode(",", $reading_queries_by_rowid[1])." )");
			if (!$res)
				throw new \Exception($connection->error);
			while (($row = $res->fetch_assoc()))
				$data_by_rowid[$row[$id_col_name]] = $row;
		}
		
		if ($reading_queries_by_uniqn && $reading_queries_by_uniqn[1])
		{
			$q = $reading_queries_by_uniqn[0][0]." (". implode(",", $reading_queries_by_uniqn[1]).");";
			
			$res = $this->query($q);
			if (!$res)
			{
				if (\QAutoload::GetDevelopmentMode())
					qvar_dumpk($q);
				throw new \Exception($connection->error);
			}
			while (($row = $res->fetch_row()))
			{
				$record_id = array_pop($row);
				$data_by_uniqueness[implode("\x00", $row)] = $record_id;
			}
		}
		
		$update_to_delete_queries = [];
		
		# if ($this->path === 'Services[].Categories')
		$replacements = new \SplObjectStorage();
		# WRITE OPs
		foreach ($this->items as $model_inf)
		{
			list ($model, $parent_model) = $model_inf;
			$parent_id = (int)$parent_model->getId();

			$action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
			$update = ($action & QModel::TransformUpdate);
			$create = ($action & QModel::TransformCreate);
			$modify_action = $update || $create;
			
			#if ($this->path === 'Services[].Categories')
			#	$connection->_stats->queries[] = "# Starting parent: ".$parent_id;
			
			if ($modify_action)
			{
				// this means all existing items NOT IN the $model will be removed from the collection
				$replace_elements = ($model->_ts & QModel::TransformDelete) && (($model->_ts & QModel::TransformCreate) || ($model->_ts & QModel::TransformUpdate))
										&& $parent_model && $parent_model->getId();
				if ($replace_elements)
					$replacements[$model] = $parent_model;
				
				foreach ($model as $key => $item)
				{
					# $connection->_stats->queries[] = "# Starting item: ".$item->getId();
					
					$item_is_model = ($item instanceof QIModel);
					$item_action = $item_is_model ? (($item->_ts !== null) ? $item->_ts : QModel::TransformMerge) : null;
					$item_update = $item_action ? ($item_action & QModel::TransformUpdate) : $update;
					$item_delete = $item_action ? ($item_action & QModel::TransformDelete) : false;
					$item_create = $item_action ? (($item_action & QModel::TransformCreate) ? true : false) : $create;

					$rowid = (int)$model->getRowIdAtIndex($key);
					
					$uniqueness = null;
					$has_changed = false;
					$was_removed = false;
					
					// hasAnyInstantiableReferenceTypes
					// for QIModel on one to many, the rowid is the ID of the element
					if ($rowid === null)
					{
						if ($one_to_many && ($item instanceof QIModel))
						{
							$rowid = (int)$item->getId();
							qvar_dumpk('$data_by_rowid', $data_by_rowid);
							throw new \Exception('pick by $data_by_rowid');
						}
						else if ($find_by_uniqueness && $item_is_model && ($tmp_mp_id = $parent_id) && ($tmp_it_id = (int)$item->getId()))
						{
							$uniqueness = ["{$tmp_it_id}\x00{$tmp_mp_id}".
												($collection_has_item_type ? "\x00".$item->get_Type_Id() : '').
												($collection_has_bkref_type ? "\x00".$parent_type_id : ''), 
											"({$tmp_it_id},{$tmp_mp_id}".
												($collection_has_item_type ? ",".$item->get_Type_Id() : '').
												($collection_has_bkref_type ? ",".$parent_type_id : '').
												')'];
							qvar_dumpk($uniqueness, $data_by_uniqueness);
							
							throw new \Exception('pick by uniqueness');
						}
					}
					
					if (!$one_to_many)
					{
						$update_done = false;

						if ($item_update && ($rowid || $uniqueness))
						{
							$record = null;
							// select & update
							if ($rowid)
							{
								$record = $data_by_rowid[$rowid];
							}
							else // by uniqueness
							{
								/* $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."`,`{$sql_info["id_col"]}` FROM {$sql_info["tab"]} WHERE ".
										"`{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
										"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
								# $yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
								# $record = ?
								$connection->query('COMMIT;');
								die("PICK IT BY UIQUENESS");
							}
							// echo ($sql_select)."<br/>\n";
							//qvdumptofile($sql_select);

							// if select failed
							if ($record !== null)
							{
								if (!$rowid)
									$rowid = $record[$sql_info["id_col"]];

								if (!$rowid)
									throw new Exception("This should not be");

								$upd_pairs = [];
								$yield_obj_binds = [];
								
								if ($sql_info["cols"]["type"])
								{
									$new_type = $this->extractTypeIdForVariable($item);
									$upd_pairs[] = "`".$sql_info["cols"]["type"]."`=?";
									$yield_obj_binds[] = $new_type;
									$has_changed = ($has_changed || ($new_type != $record[$sql_info["cols"]["type"]]));
								}
								
								if ($item_is_model)
								{
									if ($sql_info["cols"]["val"])
									{
										$upd_pairs[] = "`".$sql_info["cols"]["val"]."`=NULL";
										$has_changed = ($has_changed || (null !== $record[$sql_info["cols"]["val"]]));
									}
									$has_changed = ($has_changed || (!$item->getId()) || ($item->getId() != $record[$sql_info["cols"]["ref"]]));
								}
								else
								{
									$upd_pairs[] = "`{$sql_info["cols"]["val"]}`=?";
									$yield_obj_binds[] = $item;
									if ($sql_info["cols"]["ref"])
									{
										$upd_pairs[] = "`".$sql_info["cols"]["ref"]."`=NULL";
										$has_changed = ($has_changed || (null !== $record[$sql_info["cols"]["ref"]]));
									}
									$has_changed = ($has_changed || ($item !== $record[$sql_info["cols"]["val"]]));
								}
								
								if ((!empty($upd_pairs)) && $has_changed)
								{
									qvar_dumpk('$upd_pairs', $upd_pairs, '$has_changed', $has_changed);
									$connection->query("COMMIT;");
									die("\$upd_pairs::XXXX");

									// may be empty if there is nothing to update
									// $sql_update = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=".$rowid;

									$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=?";
									$yield_obj_binds[] = $rowid;

									$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];

									if (($record = $yield_obj->result) === false)
									{
										var_dump($yield_obj);
										throw new Exception($connection->error);
									}

									//static::DumpQ($sql_update, $__t);
								}

								$update_done = true;
							}

						}
						
						$insert_done = false;
						$insert_id = null;
						
						$backref_collection_insert = null;
						
						if ($item_create && (!$update_done))
						{
							$ins_vals = array();
							$ins_cols = array();

							# $ins_vals[] = $rowid ?: 'NULL';
							# $ins_cols[] = $sql_info["id_col"];

							$item_is_model = ($item instanceof QIModel);
							if ($sql_info["cols"]["type"])
							{
								$ins_cols[] = $sql_info["cols"]["type"];
								$ins_vals[] = $this->extractTypeIdForVariable($item);
							}
							if ($item_is_model)
							{
								if ($sql_info["cols"]["val"])
								{
									$ins_cols[] = $sql_info["cols"]["val"];
									$ins_vals[] = 'NULL';
								}
							}
							else if ($sql_info["cols"]["val"])
							{
								$ins_cols[] = $sql_info["cols"]["val"];
								$ins_vals[] = $this->escape($item);
								if ($sql_info["cols"]["ref"])
								{
									$ins_cols[] = $sql_info["cols"]["ref"];
									$ins_vals[] = 'NULL';
								}
							}

							// $sql_insert = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $ins_cols)."`) VALUES (".implode(",", $ins_vals).");";
							
							if ($item_is_model)
							{
								$backref_collection_insert = [$sql_info, $ins_cols, $ins_vals];
								$insert_done = true;
							}
							else # if (!$item_is_model)
							{
								$ins_cols[] = $sql_info['cols']['bkref'];
								$ins_vals[] = $parent_id;
								if ($sql_info['cols']['bkref_type'])
								{
									$ins_cols[] = $sql_info['cols']['bkref_type'];
									$ins_vals[] = $parent_model->get_Type_Id();
								}

								$yield_obj_q = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $ins_cols)."`) VALUES (".implode(",", $ins_vals).");";
								$rc = $this->query($yield_obj_q);
								if (!$rc)
									throw new \Exception($this->connection->error);

								$insert_id = $this->connection->insert_id;
								if (!$insert_id)
									throw new \Exception('This should not happen.');
								
								$insert_done = true;

								$model->setRowIdAtIndex($key, $insert_id);
								$rowid = $insert_id;
							}
							
						}

						if ($item_is_model && ($insert_done || $update_done))
						{
							$coll_item_has_changes = true;
							if ($rowid)
							{
								if (($in_db = $data_by_rowid[$rowid]) && ($fwd_val = $in_db[$sql_info["cols"]["ref"]]) && ((int)$fwd_val == (int)$item->getId()))
								{
									# @TODO - for forward also check type
									$coll_item_has_changes = false;
								}
							}
							else if ($find_by_uniqueness && $uniqueness)
							{
								throw new \Exception('@TODO');
							}
														
							// reference on ManyToMany only	
							if ($coll_item_has_changes)
								# qvar_dumpk("M2M FWD ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["ref"]." / {$rowid}");
							{
								# qvar_dumpk($sql_info, $sql_info["cols"]["ref"], $item, $rowid, null, null, $backref_collection_insert);
								if ($backref_collection_insert)
								{
									$bk_cols_to_set = [$item, (int)$parent_id];# array_combine($backref_collection_insert[1], $backref_collection_insert[2]);
									if ($sql_info["cols"]["type"])
										$bk_cols_to_set[] = $this->extractTypeIdForVariable($item);
									if ($sql_info["cols"]["bkref_type"])
										$bk_cols_to_set[] = $parent_model->get_Type_Id();

									# $sql_info["cols"]["type"]
									$identifier_group_cols = implode("_\x00_", ($sql_info["cols"]["type"]) ? 
											[$sql_info["cols"]["type"], $sql_info['cols']['bkref'], $sql_info['cols']['ref']] : 
											[							$sql_info['cols']['bkref'], $sql_info['cols']['ref']]);
									
									$this->setBackReference_New($sql_info, "M2M_INSERT", 
															$identifier_group_cols, 
															[$item, $parent_id, $parent_model], $bk_cols_to_set, $model, $key);
								}
								else
									throw new \Exception("@TODO .....");
								# $this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["ref"], $item, $rowid, null, null, $backref_collection_insert);
							}
						}
						
						if (($rowid !== null) && ($item_delete || ($model->getTransformState($key) & QModel::TransformDelete)))
						{
							# @TODO - this should be done in one go with `$id_col` IN ... not one by one !!!
							
							$yield_obj_q = "DELETE FROM ".$sql_info["tab"]." WHERE `".$sql_info["id_col"]."`={$rowid}; # ". json_encode([$this->path, $data_by_rowid[$rowid], $item->_ts, $model->getTransformState($key)])." ";
							# qvar_dumpk('$parent_model', $parent_model);
							$rc = $this->query($yield_obj_q);
							if (!$rc)
								throw new \Exception($this->connection->error);
							
							$was_removed = true;
						}
						# else @TODO - delete by uniqeness ?
					}
					else
					{
						$col_backref = $sql_info["cols"]["bkref"];

						if ($item_is_model && ($_otm_item_id = (int)$item->getId()))
						{
							// handle delete
							if (($_otm_item_id !== null) && ($item_delete || ($model->getTransformState($key) & QModel::TransformDelete)))
							{
								// $sql_delete = "UPDATE ".$sql_info["tab"]." SET `{$col_backref}`=NULL WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($_otm_item_id, $connection);
								if (!isset($update_to_delete_queries[0]))
								{
									$update_to_delete_queries[0] = "UPDATE ".$sql_info["tab"]." SET `{$col_backref}`=NULL".(
											$sql_info["cols"]["bkref_type"] ? ",`{$sql_info["cols"]["bkref_type"]}`=NULL" : ""
											)." WHERE `".$sql_info["id_col"]."` IN ";
								}
								$update_to_delete_queries[1][$_otm_item_id] = $_otm_item_id;

								$was_removed = true;
							}
						}
					}

					# then both (one to many and many to many)
					{
						$backref_val = ($one_to_many ? $item : $rowid);
						// var_dump("Item is: ".(is_object($item) ? get_class($item) : $item));

						// Backref 
						if ((!$was_removed) && $backref_val)
						{
							// var_dump("We set back ref for ".get_class($backref_val)." via table ".$sql_info["tab"]." / ".$sql_info["cols"]["bkref"], $one_to_many);
							// var_dump(is_object($backref_val) ? get_class($backref_val) : $backref_val, $rowid);
							if ($one_to_many)
							{
								// var_dump($sql_info["tab"], $sql_info["id_col"], $sql_info["cols"], $model->getModelProperty()."");
								$coll_item_has_changes = true;
								if ($rowid && ($in_db = $data_by_rowid[$rowid]) && ($backref_val = $in_db[$sql_info["cols"]["bkref"]]) && 
										((int)$backref_val == (int)$parent_id) && ((!($backref_type = $sql_info["cols"]["bkref_type"])) || 
											($parent_model->get_Type_Id() == $in_db[$sql_info["cols"]["bkref_type"]])))
								{
									# @TODO , should we check the type ? can it make a difference ?!
									$coll_item_has_changes = false;
								}
								if ($coll_item_has_changes)
								{
									# qvar_dumpk("One2M BK ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["bkref"]." / ".$parent_model->Name." :: ".get_class($backref_val));
									# $this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val, $model, $key);
									# qvar_dumpk('$backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val, $model, $key', 
									#		$sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val, $model, $key);
									
									$this->setBackReference_New($sql_info, "ONE2M_UPDATE", $sql_info["cols"]["bkref"],
											$item, [0 => $parent_id, 1 => $parent_model->get_Type_Id()], $model, $key);
								}
							}
							else
							{
								/*
								$coll_item_has_changes = true;
								if ($rowid)
								{
									if ($rowid && ($in_db = $data_by_rowid[$rowid]) && ($backref_val = $in_db[$sql_info["cols"]["bkref"]]) && ((int)$backref_val == (int)$parent_id))
									{
										$coll_item_has_changes = false;
									}
								}
								else if ($find_by_uniqueness && $uniqueness)
								{
									throw new \Exception("@TODO!");
								}
								if ($coll_item_has_changes)
								{
									# qvar_dumpk("Many2M BK ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["bkref"]." / ".$parent_model->Name." :: ".get_class($backref_val));
									$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val);
								}
								*/
							}
						}
					}

				}
			}
			else
			{
				# $find_by_uniqueness = false; // @todo - check find by uniqunes (!$one_to_many) && $property->getCollectionType()->hasAnyInstantiableReferenceTypes();
				
				foreach ($model as $item_k => $item)
				{
					$item_is_model = ($item instanceof QIModel);
					$item_action = $item_is_model ? (($item->_ts !== null) ? $item->_ts : ($model->getTransformState($item_k) ?: QModel::TransformMerge)) : $model->getTransformState($item_k);
					
					$item_do_delete = $item_action & QModel::TransformDelete;
					
					if ($item_do_delete)
					{
						$uniqueness = null;
						$rowid = $model->getRowIdAtIndex($item_k);
						
						if ($rowid === null)
						{
							if ($one_to_many && $item_is_model)
								$rowid = $item->getId();
							else if ($find_by_uniqueness && $item_is_model && ($tmp_mp_id = $parent_model->getId()) && ($tmp_it_id = $item->getId()))
								$uniqueness = [$tmp_it_id, $tmp_mp_id];
						}
						
						if ($one_to_many)
						{
							if ($rowid)
							{
								// `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
								/*$q_str = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
											"WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection).
												" AND `{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($parent_model->getId(), $connection);*/
												
								$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
												($sql_info["cols"]["bkref_type"] ? ",`{$sql_info["cols"]["bkref_type"]}`=NULL " : "").
											"WHERE `".$sql_info["id_col"]."`='{$rowid}'".
												" AND `{$sql_info["cols"]["bkref"]}`='".$parent_model->getId()."'";
								# $yield_obj_binds = [$rowid, $parent_model->getId()];
							}
							else
							{
								/*$q_str = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
											" WHERE `{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
													"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
								
								$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
												($sql_info["cols"]["bkref_type"] ? ",`{$sql_info["cols"]["bkref_type"]}`=NULL " : "").
											" WHERE `{$sql_info["cols"]["ref"]}`='{$uniqueness[0]}' AND ".
													"`{$sql_info["cols"]["bkref"]}`='{$uniqueness[1]}'";
								# $yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
							}
						}
						else
						{

							if ($rowid)
							{
								// `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
								/* $q_str = "DELETE FROM ".$sql_info["tab"].
											" WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection).
												" AND `{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($parent_model->getId(), $connection); */
								$yield_obj_q = "DELETE FROM ".$sql_info["tab"].
											" WHERE `".$sql_info["id_col"]."`='{$rowid}'".
												" AND `{$sql_info["cols"]["bkref"]}`='".$parent_model->getId()."'";
								# $yield_obj_binds = [$rowid, $parent_model->getId()];
							}
							else
							{
								/*$q_str = "DELETE FROM ".$sql_info["tab"].
											" WHERE `{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
													"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
													
								$yield_obj_q = "DELETE FROM ".$sql_info["tab"].
											" WHERE `{$sql_info["cols"]["ref"]}`='{$uniqueness[0]}' AND ".
													"`{$sql_info["cols"]["bkref"]}`='{$uniqueness[1]}'";
								# $yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
							}
						}
						
						$rc = $this->query($yield_obj_q);
						if (!$rc)
							throw new \Exception($this->connection->error);
						
						$model->_tsx[$item_k] = QModel::TransformDelete;
					}
				}
				
				# @TODO delete
				# throw new \Exception('@TODO - collection delete');
			}
		}
		
		# $sql_info_col_type
		$delete_for_replacements = [];
		$cols_backref_type = $sql_info['cols']['bkref_type'];
		foreach ($replacements as $model)
		{
			$parent_model = $replacements->getInfo();
			$parent_id = (int)$parent_model->getId();
			$parent_type = $cols_backref_type ? $parent_model->get_Type_Id() : null;
			$delete_for_replacements[$parent_id] = [];
			
			foreach ($model as $key => $item)
			{
				$item_action = ($item instanceof QIModel) ? (($item->_ts !== null) ? $item->_ts : QModel::TransformMerge) : null;
				$item_delete = $item_action ? ($item_action & QModel::TransformDelete) : false;
				if (!$item_delete)
				{
					$rowid = (int)$model->getRowIdAtIndex($key);
					$delete_for_replacements[$cols_backref_type ? $parent_id."/".$parent_type : $parent_id][$rowid] = $rowid;
				}
			}
			
			if ($cols_backref_type)
				$delete_for_replacements[$parent_id] = "(`{$sql_info['cols']['bkref']}`='{$parent_id}' AND `{$cols_backref_type}`='{$parent_type}' AND `{$sql_info['id_col']}` NOT IN (".implode(',', $delete_for_replacements[$parent_id])."))";
			else
				$delete_for_replacements[$parent_id] = "(`{$sql_info['cols']['bkref']}`='{$parent_id}' AND `{$sql_info['id_col']}` NOT IN (".implode(',', $delete_for_replacements[$parent_id])."))";
		}
		
		if ($delete_for_replacements)
		{
			if ($one_to_many || $sql_info_table_types) # update to null
			{
				$sql = "UPDATE {$sql_info['tab']} SET {$sql_info['cols']['ref']}=NULL".($sql_info['cols']['type'] ? ",{$sql_info['cols']['type']}=NULL" : "").
							" WHERE ".implode(" OR ", $delete_for_replacements);
				qvar_dumpk("@TODO", $sql);
				throw new \Exception('@todo');
			}
			else # delete if not used for something else UPDATE !!!
			{
				$sql = "DELETE FROM {$sql_info['tab']} WHERE ".
										implode(" OR ", $delete_for_replacements).";";
			}
			$rc = $this->query($sql);
			if (!$rc)
				throw new \Exception($this->connection->error);
		}
		
		if ($update_to_delete_queries && $update_to_delete_queries[1])
		{
			$res = $this->query($update_to_delete_queries[0]."( ".implode(",", $update_to_delete_queries[1])." )");
			if (!$res)
				throw new \Exception($connection->error);
		}
	}
		
	protected function getSqlInfo_model(array &$cache, $selector = null)
	{
		$selector_isarr = is_array($selector);
		
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();

		// get only those that pend an update
		$m_type = $this->model_type;
		$props = $m_type->properties;
		$cols = [];

		$full_tname = $m_type->getTableName();
		$cols_type_inf_tab = $cols_type_inf[$full_tname];

		// some things can be cached
		$cache_key = $m_type->class;
		$rowid_col = $m_type->getRowIdColumnName();

		$meta = [];

		$cols["%"] = [];

		// $table_types =  QSqlModelInfoType::GetTableTypes($m_type->getTableName());
		if ($cols_type_inf_tab && ($ci = $cols_type_inf_tab[$rowid_col]) && is_string($ci))
			$cols["%"][] = $cols["_type"]["val"] = $ci;
		
		$extend_selector = [];

		foreach ($props as $prop)
		{
			if ($prop->storage["none"] || $prop->hasCollectionType())
				continue;

			$p_name = $prop->name;

			if (	// if we have a selector and this property is not included
					(($selector === false) || ($selector_isarr && ($selector[$prop->name] === null) && ($selector["*"] === null))) )
			{
				continue;
			}
			else if (strtolower($p_name) === "id")
			{
				// check id
				continue;
			}

			if (($one_to_one = trim($prop->storage['oneToOne'])))
			{
				$meta[$p_name]['oneToOne'] = [$one_to_one, $prop->getAllInstantiableReferenceTypes()];
				# @TODO - put some logging that we have extended the selector !
				# if (!isset($selector[$prop->name][$one_to_one]))
				#	$extend_selector[$prop->name][$one_to_one] = [];
			}

			if (!($cols[$p_name] = $cached_prop = $cache[$cache_key][$p_name]))
			{
				$prop_cname = $prop->getColumnName();

				if ($prop->hasScalarType())
				{
					$dims = $prop->storage["dims"];
					if ($dims && ($_use_dim = QModel::GetDim(reset($dims))))
					{
						// handle fixed size dimentions here
						$cn_sufix = "_".$_use_dim;
						$cols["%"][] = 
							$cols[$p_name]["val"] = $prop_cname.$cn_sufix;
					}
					else
						$cols["%"][] = 
							$cols[$p_name]["val"] = $prop_cname;
				}
				if ($prop->hasReferenceType())
				{
					// put it in the backref list
					$cols["%"][] = 
						$cols[$p_name]["ref"] = $prop->getRefColumnName();
				}
				if ($cols_type_inf_tab && ($ci = $cols_type_inf_tab[$prop_cname]) && is_string($ci))
				{
					$cols["%"][] = 
						$cols[$p_name]["type"] = $ci;
				}

				$cached_prop = $cache[$cache_key][$p_name] = $cols[$p_name];
			}
			else
			{
				if ($cached_prop['val'])
					$cols["%"][] = $cached_prop['val'];
				if ($cached_prop['ref'])
					$cols["%"][] = $cached_prop['ref'];
				if ($cached_prop['type'])
					$cols["%"][] = $cached_prop['type'];
			}
			
			if (!$cached_prop)
				throw new \Exception('Should not be.');
		}
		
		return ["sql" => null, "cols" => $cols, "vals" => null, "tab" => qEscTable($this->table_name), "id_col" => $rowid_col, 
						"objs" => null, 'meta' =>  $meta, 'extend_selector' => $extend_selector];
	}
	
	protected function getSqlInfo_collection(array &$cache, bool $one_to_many, QModelProperty $property)
	{
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();

		$full_tname = $property->getCollectionTableName();
		$cols_type_inf_tab = $cols_type_inf[$full_tname];
		
		// this is quite standard and can be indexed
		$cache_key = $property->parent->class.".".$property->name;
		if (!($ret_cache = $cache[$cache_key]))
		{
			$cols = [];
			$rowid_col = $property->getCollectionTableRowId();
			$cols["bkref"] = $property->getCollectionBackrefColumn();
			if (($bkref_type = $cols_type_inf_tab[$cols["bkref"]]))
				$cols["bkref_type"] = $bkref_type;
			
			if ($one_to_many)
			{
				// we only need the backref column
				return $cache[$cache_key] = ["sql" => null, "cols" => $cols, "tab" => qEscTable($this->table_name), "id_col" => $rowid_col];
			}
			else
			{
				$acc_types = $property->getCollectionType();

				$coll_fwdcolumn = $property->getCollectionForwardColumn();

				if ($acc_types->hasReferenceType())
					$cols["ref"] = $coll_fwdcolumn;
				if ($acc_types->hasScalarType())
					$cols["val"] = $property->getCollectionValueColumn();

				// var_dump($cols_type_inf_tab, $coll_fwdcolumn);

				if ($cols_type_inf_tab && ($ci = $cols_type_inf_tab[$coll_fwdcolumn]) && is_string($ci))
				{
					$cols["type"] = $ci;
				}

				return $cache[$cache_key] = ["sql" => null, "cols" => $cols, "tab" => qEscTable($this->table_name), "id_col" => $rowid_col];
			}
		}
		return $ret_cache;
	}
	
	protected function setBackReference_New(array $sql_info, string $sql_action, 
													string $idf_by_grouping,
													$identified_by, array $cols_to_set, 
													\QModelArray $set_rowid_array = null, $set_rowid_at_pos = null)
	{
		$this->backrefs_list[$sql_info['tab']][$sql_action][$idf_by_grouping][] = [$sql_info, $sql_action, $identified_by, $cols_to_set, $set_rowid_array, $set_rowid_at_pos];
	}
	
	protected function setBackReference(&$backrefs_list, $sql_info, $update_column, QIModel $reference, $row_identifier, 
										$model_array = null, $model_array_key = null, $backrefs_collection_insert = null)
	{
		throw new \Exception('deprecated');
		// ensure tmpid
		$id = ($row_identifier instanceof QIModel) ? "#tmp/".($row_identifier->_tmpid ?: ($row_identifier->_tmpid = QModel::GetNextId()))."/#" : $row_identifier;
		$key = $sql_info["tab"].":".$id;
		
		if ($model_array && ($model_array_key !== null))
			$backrefs_list[$key][] = [$sql_info, $update_column, $reference, $row_identifier, $model_array, $model_array_key, $backrefs_collection_insert];
		else
			$backrefs_list[$key][] = [$sql_info, $update_column, $reference, $row_identifier, null		   , null,			  $backrefs_collection_insert];
	}
	
	protected function getSqlInfo_prepare_vals(array &$cache, string $class_name, array $sql_info_model, array &$backrefs_list, \QIModel $model, $selector, $props, int $model_id = null)
	{
		$selector_isarr = is_array($selector);
		
		$vals = [];
		# $objects = [];
		
		$cols = $sql_info_model['cols'];
		$cols['%'] = []; # reset columns to write
		
		// $table_types =  QSqlModelInfoType::GetTableTypes($m_type->getTableName());
		if (($ci = $cols["_type"]["val"]) && is_string($ci))
		{
			$cols["%"][] = $cols["_type"]["val"] = $ci;
			$vals["%"][] = $vals["_type"]["val"] = $model->get_Type_Id();
		}

		foreach ($props as $p_name => $prop)
		{
			if ($prop->storage["none"])
				continue;

			if (	// if we have a selector and this property is not included
					(($selector === false) || ($selector_isarr && ($selector[$prop->name] === null) && ($selector["*"] === null))) ||  
					// if the model was not set/changed
					(!$model->wasSet($p_name)))
			{
				continue;
			}
			else if (strtolower($p_name) === "id")
			{
				// check id
				$model->setId($model->$p_name, 1);
				continue;
			}

			$value = $model->$p_name;
			# value check
			$model->{"set{$p_name}"}($value, 1);
			$cached_prop = $cache[$class_name][$p_name];

			if ($value instanceof QIModel)
			{
				# $objects[] = array($value, $prop);

				if (!($value instanceof QIModelArray))
				{
					if ($cached_prop["type"])
					{
						$vals["%"][] = $vals[$p_name]["type"] = $value->get_Type_Id();
						$cols["%"][] = $cached_prop["type"];
					}
					if ($cached_prop["ref"])
					{
						$vals["%"][] = $vals[$p_name]["ref"] = (int)$value->getId();
						$cols["%"][] = $cached_prop["ref"];
					}

					// TO DO : when we setup this one here, we don't yet know if it was changed and a lot of times it is not needed
					// var_dump($p_name);

					// var_dump("SQLINF: We set back ref for ".get_class($value)." via table ".$sql_info["tab"]." / ".$cols[$p_name]["ref"]." on model :" .  get_class($model));
					#qvar_dumpk("PROP: ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$cols[$p_name]["ref"]." / ".$value->Name);
					
					# qvar_dumpk("getSqlInfo_prepare_vals ".$sql_info_model["tab"]." / ".$sql_info_model["id_col"]." / ".$cols[$p_name]["ref"]."}");
					# $this->setBackReference($backrefs_list, $sql_info_model, $cols[$p_name]["ref"], $value, $model);
				}
			}
			else
			{
				if ($cached_prop["type"])
				{
					// TO DO: force certain types and throw error when not ok
					$vals["%"][] = $vals[$p_name]["type"] = ($value !== null) ? $this->extractTypeIdForVariable($value) : null;
					$cols["%"][] = $cached_prop["type"];
				}
				if ($cached_prop["val"])
				{
					$vals["%"][] = $vals[$p_name]["val"] = $value;
					$cols["%"][] = $cached_prop["val"];
				}
				if ($cached_prop["ref"])
				{
					$vals["%"][] = $vals[$p_name]["ref"] = null;
					$cols["%"][] = $cached_prop["ref"];
				}
			}
		}
		
		$copy = $sql_info_model;
		$copy['vals'] = $vals;
		$copy['cols'] = $cols;
		# $copy['objs'] = $objects;

		# return array("sql" => null, "cols" => $cols, "vals" => $vals, "tab" => qEscTable($this->name), "id_col" => $rowid_col, "objs" => $objects, 'meta' =>  $meta);
		return $copy;
	}
	
	private function extractTypeIdFor_Class_Name(string $class_name)
	{
		return $this->storage->getTypeIdInStorage($class_name);
	}
	
	private function extractTypeIdForVariable($var)
	{
		if ($var instanceof QIModel)
			return $this->storage->getTypeIdInStorage($var->getModelType()->class);
		else
		{
			$v_ty = QModel::GetTypeForVariable($var);
			return $v_ty ? $v_ty->getTypeId() : 0;
		}
	}
	
	private function escape($value)
	{
		if ($value === null)
			return "NULL";
		else if (is_int($value) || is_float($value))
			return (string)$value;
		else if (is_bool($value))
			return $value ? '1' : '0';
		else
			return "'".$this->connection->escape_string($value)."'";
	}
	
	private function query(string $query)
	{
		# echo $query."<br/>\n";
		if (!$this->connection->_stats)
		{
			$this->connection->_stats = new \stdClass();
			$this->connection->_stats->ops = 0;
			$this->connection->_stats->selects = 0;
			$this->connection->_stats->updates = 0;
			$this->connection->_stats->inserts = 0;
			$this->connection->_stats->deletes = 0;
			$this->connection->_stats->queries = [];
			$this->connection->_stats->exec_time = 0;
		}
		$this->connection->_stats->ops++;
		$this->connection->_stats->queries[] = $query;
		$t0 = microtime(true);
		$ret = $this->connection->query($query);
		$t1 = microtime(true);
		$this->connection->_stats->exec_time += ($t1 - $t0);
		return $ret;
	}
	
	private function get_mergeby_meta(string $class_name, string $parent_property_name, string $parent_class_name_or_zero, \QModelProperty $property = null)
	{
		$property_mergeBy = null;
		if ($parent_class_name_or_zero && $parent_property_name)
		{
			$key = $parent_class_name_or_zero.".".$parent_property_name;
			if (($mby_inf = static::$_MergeByInfo[$key]) !== null)
				$property_mergeBy = $mby_inf;
			else if (($property_inf = \QModel::GetTypeByName($parent_class_name_or_zero)->properties[$parent_property_name]) && ($property_mergeBy = $property_inf->storage["mergeBy"]))
				static::$_MergeByInfo[$key] = $property_mergeBy;
			else
				static::$_MergeByInfo[$key] = false;
		}
		
		$type_mergeBy = null;
		$type_mergeBy_needs_insert = false; # do we need to make sure the item will be inserted in the collection
		if (!$property_mergeBy)
		{
			{
				if (($mby_inf = static::$_MergeByInfo[$class_name]) !== null)
					$type_mergeBy = $mby_inf;
				else if (($type_inf = \QModel::GetTypesCache($class_name)) && ($type_mergeBy = $type_inf["#%misc"]["mergeBy"]))
					static::$_MergeByInfo[$class_name] = $type_mergeBy;
				else
					static::$_MergeByInfo[$class_name] = false;
			}

			if ($type_mergeBy)
			{
				$app_data_class_name = \QApp::GetDataClass();
				# we are under app -> use it
				# if not under app - storage - use optionsPool or error !
				$options_pool_prop = $property->storage["optionsPool"] ?: 
									(($parent_class_name_or_zero === $app_data_class_name) ? $property->name : false);
				if ($options_pool_prop)
				{
					$op_property = $options_pool_prop ? \QModel::GetTypeByName($app_data_class_name)->properties[$options_pool_prop] : false;
					if (!$op_property)
						throw new \Exception('Options pool not found: '.$options_pool_prop);

					$mby_app_props = [$options_pool_prop => $options_pool_prop];
					/*if ($property && ($app_property = $property->getAppPropertyFor($class_name)))
						$mby_app_props = [$app_property => $app_property];
					else
						$mby_app_props = \QModel::GetDefaultAppPropertiesForTypeValues($class_name);*/
					if (($parent_class_name_or_zero !== $app_data_class_name) || ($parent_property_name !== $options_pool_prop))
					{
						$type_mergeBy_needs_insert = true;
						# qvar_dumpk($parent_class_name_or_zero, $parent_property_name, $property, $class_name, $mby_app_props);
						# die("dasdad");
					}

					if (!$mby_app_props)
						throw new \Exception("We have a mergeBy on {$class_name} but we can not find it's app property");
						
					$parts = preg_split("/(\\s*\\,\\s*)/uis", $type_mergeBy, -1, PREG_SPLIT_NO_EMPTY);

					$merge_by_meta_type_parsed = [];
					foreach ($parts as $part)
						$merge_by_meta_type_parsed[] = preg_split("/(\\s*\\.\\s*)/uis", $part, -1, PREG_SPLIT_NO_EMPTY);

					# to cleanup spaces
					$type_mergeBy = preg_replace("/(\\s+)/uis", "", $type_mergeBy);
				}
				else
				{
					# we don't have optionsPool nor we are on app level, so we will ignore merge-by
					$type_mergeBy = null;
				}
			}
		}
		
		if ($property_mergeBy)
		{
			$parts = preg_split("/(\\s*\\,\\s*)/uis", $property_mergeBy, -1, PREG_SPLIT_NO_EMPTY);
			
			$merge_by_meta_prop_parsed = [];
			foreach ($parts as $part)
				$merge_by_meta_prop_parsed[] = preg_split("/(\\s*\\.\\s*)/uis", $part, -1, PREG_SPLIT_NO_EMPTY);
			
			# to cleanup spaces
			$property_mergeBy = preg_replace("/(\\s+)/uis", "", $property_mergeBy);
		}
				
		if ((!$type_mergeBy) && (!$property_mergeBy))
			return false;
		else
		{
			$property_is_collection = $property->hasCollectionType();
			
			$sql_property_info = \QModelQuery::GetTypesCache($parent_class_name_or_zero)[$parent_property_name];
			if ($property_is_collection)
			{
				$collection_back_ref_column = $sql_property_info['cb'];
				if (!$collection_back_ref_column)
					throw new \Exception("Unable to find collection back reference column for: {$parent_class_name_or_zero}.{$parent_property_name}");
			}
			else
			{
				if (isset($property->storage["optionsPool"]) && ($op = $property->storage["optionsPool"]))
					$mby_app_props = [$op => $op];
				
				$reference_column = $sql_property_info['rc'];
				if (!$reference_column)
					throw new \Exception("Unable to find property reference column for: {$parent_class_name_or_zero}.{$parent_property_name}");
			}
			# return [$type_mergeBy ?: null, $property_mergeBy ?: null, $merge_by_meta_type_parsed, $merge_by_meta_prop_parsed];
			$ret = [];
			if ($property_mergeBy)
			{
				$ret[$property_mergeBy][0] = $merge_by_meta_prop_parsed;
				$ret[$property_mergeBy][1] = count($ret) - 1; # the position
				$ret[$property_mergeBy][2][1] = 'prop';
				
				$ret[$property_mergeBy][3] = [$parent_property_name => $parent_property_name];
				$ret[$property_mergeBy][4] = $parent_class_name_or_zero;
				
				$ret[$property_mergeBy][6] = $property_mergeBy;
				$ret[$property_mergeBy][7] = explode(",", $property_mergeBy);
				$ret[$property_mergeBy][8] = $property_is_collection ? $collection_back_ref_column : $reference_column;
				$ret[$property_mergeBy][9] = $property_is_collection;
				$ret[$property_mergeBy][10] = false;
			}
			if ($type_mergeBy)
			{
				if (!$mby_app_props)
					throw new \Exception("We have a mergeBy on {$class_name} but we can not find it's app property (B)");
				else if (count($mby_app_props) !== 1)
					throw new \Exception("We have a mergeBy on {$class_name} with too many options. Please use storage pool to restrict.");
				
				$ret[$type_mergeBy][0] = $merge_by_meta_type_parsed;
				$ret[$type_mergeBy][1] = count($ret) - 1; # the position
				$ret[$type_mergeBy][2][0] = 'type';
				$ret[$type_mergeBy][3] = $mby_app_props;
				$ret[$type_mergeBy][4] = \QApp::GetDataClass();
				$ret[$type_mergeBy][5] = \QApp::GetDataId();
				$ret[$type_mergeBy][6] = $type_mergeBy;
				$ret[$type_mergeBy][7] = explode(",", $type_mergeBy);
				$ret[$type_mergeBy][8] = $property_is_collection ? $collection_back_ref_column : $reference_column;
				$ret[$type_mergeBy][9] = $property_is_collection;
				$ret[$type_mergeBy][10] = $type_mergeBy_needs_insert;
			}
			
			return $ret;
		}
	}
	
	private function get_mergeby_data(array $merge_by_meta, array &$merge_by_data, array &$merge_by_find, array &$merge_by_pool, array &$merge_by_linked, 
								bool $is_collection_on_top_level, \QIModel $model, 
								string $parent_class_name_or_zero, \QIModel $parent_model_object, string $parent_property)
	{
		# qvar_dumpk('$merge_by_meta_type, $merge_by_meta_prop', $merge_by_meta_type, $merge_by_meta_prop);
		# so ... if enough info was set per model
		
		# loooks like we will use qmodelarray->query ... , then find a way to optimize it ... test how slow it is on over 4000 items
		# bulks of 500/1000 ? not a bad ideea ...
		/*
		if ($type_mergeBy)
		{
			$parts = preg_split("/(\\s*\\,\\s*)/uis", $type_mergeBy, -1, PREG_SPLIT_NO_EMPTY);
			
			$type_mergeBy = [];
			foreach ($parts as $part)
				$type_mergeBy[] = preg_split("/(\\s*\\.\\s*)/uis", $part, -1, PREG_SPLIT_NO_EMPTY);
		}
		*/
		
		# JSON_ARRAY(1, "abc", NULL, TRUE, CURTIME()) # works!
		# SELECT (Some.Deeper.Path AS Merge_By_1_),... 
		#  Add group by !!!!
		
		# $t0 = microtime(true);
		
		# @TODO - performace, some generated function to grab the mergeBy info !
		foreach ($merge_by_meta as $merge_by_info)
		{
			list ($walk_struct, $pos, $type_and_or_prop) = $merge_by_info;
			
			list ($data, $valid, $key) = $this->extract_data_from_path($walk_struct, $model);
			
			if ($valid)
			{
				if ($type_and_or_prop[1])
				{
					$p_id = (int)$parent_model_object->getId();
					
					# qvar_dumpk("A", $merge_by_info, $parent_class_name_or_zero, $p_id, $parent_property, $model, $parent_model_object);
					# die;
					
					$merge_by_find_pos_p_id = &$merge_by_find[$pos][$p_id];
					if ($merge_by_find_pos_p_id === null)
					{
						$merge_by_find[$pos][$p_id] = &$merge_by_pool[$parent_class_name_or_zero][$p_id][$parent_property];
						$merge_by_find_pos_p_id = &$merge_by_find[$pos][$p_id];
					}
					
					$merge_by_find_pos_p_id[$key][] = $model;
					unset($merge_by_find_pos_p_id);
					# set merge by info on model here ... not the best place 
					$model->_gp[] = [$pos, (int)$parent_model_object->getId(), $key];
					
					$merge_by_data[$pos][$parent_class_name_or_zero][$p_id][$key] = $data;
				}
				# property has priority
				else if ($type_and_or_prop[0])
				{
					if (!isset($merge_by_find[$pos]))
						$merge_by_find[$pos] = &$merge_by_pool[\QApp::GetDataClass()][\QApp::GetDataId()][reset($merge_by_info[3])];
					
					$merge_by_find[$pos][$key][] = $model;
					# set merge by info on model here ... not the best place 
					$model->_gp[] = [$pos, $key];
					# $merge_by_data[$pos][0] = list of all key => data
					$merge_by_data[$pos][0][$key] = $data;
				}
				
			}
			else
				throw new \Exception('The object does not have all data for merge by: '.$merge_by_info[6].' | '.$key);
		}
	}
	
	private function extract_data_from_path(array $walk_struct, \QIModel $model)
	{
		$data = [];
		$valid = true;
		$key = "";
		# $has_null = false;

		$split = false;
		foreach ($walk_struct as $paths)
		{
			$obj = $model;
			$prev = null;

			foreach ($paths as $prop)
			{
				if (!($obj instanceof \QIModel))
				{
					$valid = false;
					break;
				}
				$prev = $obj;
				$obj = $obj->$prop;
			}
			if (!$valid)
				break;

			if (($obj === null) && (!$prev->wasSet($prop)))
			{
				$valid = false;
				break;
			}
			$data[] = $obj;
			if (is_null($obj))
			{
				$key .= ($split ? "\x00NULL" : "NULL");
				# $has_null = true;
			}
			else if (is_scalar($obj))
				$key .= ($split ? "\x00s:{$obj}" : "s:{$obj}");
			else if ($obj instanceof \QIModel)
			{
				$oid = $obj->getId();
				if (!$oid)
				{
					$valid = false;
					break;
				}
				# $oty = $obj->getModelType() # @TODO - by type also ?!
				$key .= ($split ? "\x00o:{$oid}" : "o:{$oid}");
			}
			else
				throw new \Exception('Not expected.');

			$split = true;
		}
		
		return [$data, $valid, $key/*, $has_null*/];
	}
	
	private function process_merge_by(bool $on_app_search, array $mby_info, \QModelArray $array, string $app_prop, 
											array $key_val_data, array $merge_by_find, string $class_name, array &$ids_to_q, array &$ids_found_by_mby)
	{
		# @TODO test merge by in more complex situations
		# @TODO ... if query too long, split it in chunks
		# @TODO ... it is not ideal ... in case of property merge by ... we will pull a lot of data that we don't need 
		#					... as we can not put a reverse condition like $Parent.Id=1
		
		# $key_val_data_count = $key_val_data->count();
		# $merge_by_str = $app_prop.".".implode(",{$app_prop}.", $mby_info[7]);
		
		$count_arr = $array->count();
		if ($count_arr === 0)
			return; # nothing
			
		$str = "";
		$prepend_or = false;
		
		$collection_backref_column = $mby_info[8];
		$columns_splitted = $mby_info[7];
		
		$str_for_nulls = null;
		$cont_cols = count($columns_splitted);
		
		foreach ($key_val_data as $parent_obj_id => $in_pairs)
		{
			$prepend_bra = false;
			if ($cont_cols > 1)
				$str_in = "(";	
			foreach($in_pairs as $grp)
			{
				if ($prepend_bra)
					$str_in .= ",";
				
				$str_v = "";
				$cnt = 0;
				$prepend_com = false;
				$has_nulls = false;
				
				foreach ($grp as $val)
				{
					if ($val === null)
					{
						$has_nulls = true;
						break;
					}
					
					if ($prepend_com)
						$str_v .= ",";
					$str_v .= $this->escape($val);
					$prepend_com = true;
					$cnt++;
				}
				
				if ($has_nulls)
				{
					if ($cont_cols > 1) # small optimization, only set parent ID if more than one parent
					{
						$str_v = "";
						$cnt = 0;
						$prepend_com = false;

						foreach ($grp as $val)
						{
							if ($prepend_com)
								$str_v .= " AND ";
							if ($val === null)
								$str_v .= $columns_splitted[$cnt]." IS NULL";
							else
								$str_v .= $columns_splitted[$cnt].'='.$this->escape($val);
							$prepend_com = true;
							$cnt++;
						}
						
						#if ($count_arr > 1) # small optimization, only set parent ID if more than one parent
							$str_v .= " AND `{$collection_backref_column}`={$parent_obj_id}";
						
						if ($str_for_nulls !== null)
							$str_for_nulls .= " OR ";
						$str_for_nulls .= "({$str_v})";
					}
					else if ($str_for_nulls === null)
					{
						# OR Column IS NULL
						#if ($count_arr > 1) # small optimization, only set parent ID if more than one parent
							$str_for_nulls = " OR (".$columns_splitted[0]." IS NULL AND `{$collection_backref_column}`={$parent_obj_id})";
						#else
						#	$str_for_nulls = " OR ".$columns_splitted[0]." IS NULL";
					}
				}
				else
				{
					# the ID of the parent
					if ($count_arr > 1) # small optimization, only set parent ID if more than one parent
					{
						if ($prepend_com)
							$str_v .= ",";
						$str_v .= $parent_obj_id;
						$prepend_com = true;
						$cnt++;
					}

					$str_in .= ($cnt > 1) ? "({$str_v})" : $str_v;

					$prepend_bra = true;
				}
			}
			if ($cont_cols > 1)
				$str_in .= ")";
			
			if ($prepend_bra) # do not concat if we did not add to it
			{
				if ($prepend_or)
					$str .= ",";
				$str .= "{$str_in}";
				$prepend_or = true;
			}
		}
		
		# @TODO ... test that with one item ($key_val_data_count === 1) it will not query too much data
				
		$has_nulls_str = ($str_for_nulls !== null) && (strlen($str_for_nulls) > 0);
		
		if (!empty($str))
		{
			$str = ($count_arr > 1) ? "({$mby_info[6]},`{$collection_backref_column}`) IN ({$str})" :
										"({$mby_info[6]}) IN ({$str})";
			if ($has_nulls_str)
				$str .= " OR ".$str_for_nulls;
		}
		else if ($has_nulls_str)
			$str = $str_for_nulls;

		$query_str = "{$app_prop}.{{$mby_info[6]} WHERE {$str} GROUP BY {$mby_info[6]}}".($count_arr > 1 ? ",`{$collection_backref_column}`" : "");
		
		/*if (substr($query_str, 0, strlen('Merch_Categories.{')) === 'Merch_Categories.{')
		{
			$zid = $this->__id ?: ($this->__id = ++static::$__Tmp_Model_Id);
			qvar_dumpk("\$query_str :: {$zid} | ".$this->path." - ".$this->class_name . "\n\t\t\t\t  " . $query_str);
			# throw new \Exception('ex');
		}*/
		
		# $t1 = microtime(true);
		$array->query($query_str);
		$merge_by_needs_insert = $mby_info[10] ? true :  false;
			
		# some will be in the collection, some will not ve
		# $merge_by_needs_insert = $merge_by_meta ? reset($merge_by_meta)[10] : false;
		
		# $zid = $this->__id ?: ($this->__id = ++static::$__Tmp_Model_Id);
		# qvar_dumpk("\$query_str :: {$zid} | ".$this->path." - ".$this->class_name . "\n\t\t\t\t  " . $query_str, $array);
		
		# if (substr($query_str, 0, strlen('Merch_Categories.{')) === 'Merch_Categories.{')
		{
			# qvar_dumpk($array);
		}
		
		# qvar_dumpk($query_str, $array);
		
		# $t2 = microtime(true);
		# qvar_dumpk('query mby: ' . (($t2 - $t1)*1000));
		
		# FOR TYPE/APP :: $merge_by_find[0][$key][] = $model; 
		# FOR PROPS    :: $merge_by_find[$parent_property][(int)$parent_model_object->getId()][$key][] = $model;
		$merge_by_find_this_ctx = $merge_by_find;
		$merge_by_find_now = $on_app_search ? $merge_by_find_this_ctx : null;
		
		list ($walk_struct, /*$pos*/, /*$type_and_or_prop*/) = $mby_info;
		foreach ($array as $parent_obj)
		{
			if (!$on_app_search)
				$merge_by_find_now = $merge_by_find_this_ctx[(int)$parent_obj->getId()];
			
			foreach ($parent_obj->$app_prop ?: [] as $item)
			{
				# extract key, find objects
				list (/*$data*/, $valid, $key) = $this->extract_data_from_path($walk_struct, $item);
				
				if ($valid)
				{
					$matching_objects = $merge_by_find_now[$key];

					if ($matching_objects)
					{
						$item_id = null;
						foreach ($matching_objects as $obj)
						{
							if ((!$obj->getId()) && (get_class($item) === $class_name)) # make sure the class is also ok!
							{
								if ($item_id === null)
									$item_id = (int)$item->getId();
								$obj->setId($item_id);
								# qvar_dumpk("found: ", $obj, $item_id);
								$ids_to_q[$item_id] = $item_id;
								
								if ($merge_by_needs_insert)
									$ids_found_by_mby[$item_id] = $item_id;
							}
						}
					}
				}
			}
		}
	}
	
	public function resolveBackrefs_NEW()
	{
		if (!$this->backrefs_list)
			return;
	
		\QApp::GetStorage()->connection->_stats->queries[] = "#### ------------------------ BACKREFS :: ".$this->path." -----------------------";
		
		foreach ($this->backrefs_list as $table_escaped => $list_of_tables)
		{
			# qvar_dumpk('$table_escaped', $table_escaped);
			
			foreach ($list_of_tables as $sql_operation => $list_of_sql_ops_grpuped)
			{
				# $sql_operation = INSERT
				
				foreach ($list_of_sql_ops_grpuped as /*$columns_for_idf => */ $list_of_sql_ops)
				{
					$possible_duplicates = [];
					$duplicates_keys_index = [];
					
					if ($sql_operation === "M2M_INSERT")
					{
						$query_in = [];
						$query_in_cols = [];
						$query_in_ref = null;
						$query_in_bkref = null;
						$query_in_type = null;
						$query_in_bkref_type = null;
						
						foreach ($list_of_sql_ops as $ops_info)
						{
							list ($sql_info, /* */, $identified_by, $cols_as_key_val, $set_rowid_array, $set_rowid_at_pos) = $ops_info;
							
							if ($cols_as_key_val[0] instanceof \QIModel)
							{
								if (!($v_id = $cols_as_key_val[0]->getId()))
									throw new \Exception('Must have an id when we set the ref/backref in many to many');
								$cols_as_key_val[0] = (int)$v_id;
							}
							
							# $cols_as_key_val
							if (empty($query_in_cols))
							{
								$query_in_ref = $sql_info['cols']['ref'];
								$query_in_bkref = $sql_info['cols']['bkref'];
								$query_in_type = $sql_info['cols']['type'];
								$query_in_bkref_type = $sql_info['cols']['bkref_type'];
								
								$query_in_cols = [$query_in_ref, $query_in_bkref];
								if ($query_in_type)
									$query_in_cols[] = $query_in_type;
								if ($query_in_bkref_type)
									$query_in_cols[] = $query_in_bkref_type;
								
								if ((!$query_in_ref) || (!$query_in_bkref))
									throw new \Exception('Many to many collection must have ref and backref columns.');
							}
							
							$value_ref = $cols_as_key_val[0];
							$value_bkref = $cols_as_key_val[1];
							if ($query_in_type)
							{
								$value_type = (int)$cols_as_key_val[2];
								if (!$value_type)
									throw new \Exception('Unable to identify type.');
							}
							else
								$value_type = null;
							if ($query_in_bkref_type)
							{
								$value_bkref_type = $query_in_type ? (int)$cols_as_key_val[3] : (int)$cols_as_key_val[2];
								if (!$value_bkref_type)
									throw new \Exception('Unable to identify backref type.');
							}
							else
								$value_bkref_type = null;
							$value_ref = (int)$value_ref;
							$value_bkref = (int)$value_bkref;
							
							if ((!$value_ref) || (!$value_bkref))
								throw new \Exception('Item in many to many collection must have ref and backref set.');
							
							$query_in[] = "{$value_ref},{$value_bkref}".($query_in_type ? ",{$value_type}" : "")
												.($query_in_bkref_type ? ",{$value_bkref_type}" : "");
 						}
						
						if ($query_in)
						{
							$cols_tmp_str = "`{$query_in_ref}`,`{$query_in_bkref}`".($query_in_type ? ",`$query_in_type`" : "").
												($query_in_bkref_type ? ",`$query_in_bkref_type`" : "");
							$check_duplicates_query = "SELECT `{$sql_info['id_col']}`,{$cols_tmp_str} FROM {$table_escaped} ".
									"WHERE ({$cols_tmp_str}) IN ((".implode("),(", $query_in)."));";
									
							$rc = $this->query($check_duplicates_query);
							if (!$rc)
								throw new \Exception($this->connection->error);
							
							while (($tmp_row = $rc->fetch_row()))
							{
								$record_id = $tmp_row[0];
								array_shift($tmp_row);
								$possible_duplicates[implode(",", $tmp_row)] = (int)$record_id;
							}
						}
					}
					
					foreach ($list_of_sql_ops as $ops_info)
					{
						list ($sql_info, /* */, $identified_by, $cols_as_key_val, $set_rowid_array, $set_rowid_at_pos) = $ops_info;
						
						\QApp::GetStorage()->connection->_stats->queries[] = "#### ------------------------ \$sql_operation :: ".$sql_operation." -----------------------";

						if ($sql_operation === "M2M_INSERT")
						{
							if ($cols_as_key_val[0] instanceof \QIModel)
							{
								if (!($v_id = $cols_as_key_val[0]->getId()))
									throw new \Exception('Must have an id when we set the ref/backref in many to many');
								$cols_as_key_val[0] = (int)$v_id;
							}
							# switch from "INSERT" to "UPDATE" if it's the same
							
							$duplicates_keys = implode(",", $cols_as_key_val);
							$is_duplicate_id = $possible_duplicates[$duplicates_keys];
							
							if ($is_duplicate_id)
							{
								# we switch to an update
								$set_cols = "`{$query_in_ref}`='{$cols_as_key_val[0]}',`{$query_in_bkref}`='{$cols_as_key_val[1]}'".
													($query_in_type ? ",`$query_in_type`='{$cols_as_key_val[2]}'" : "").
													($query_in_bkref_type ? ",`$query_in_bkref_type`='".($cols_as_key_val[$query_in_type ? 3 : 2])."'" : "");
								#foreach ($cols_as_key_val as $k => $v)
								#	$set_cols[] = "`{$k}`='{$v}'";
								
								$sql_q = "UPDATE {$table_escaped} SET {$set_cols} WHERE `{$sql_info['id_col']}`='".(int)$is_duplicate_id."';";
							}
							else
							{
								$sql_q = "INSERT INTO {$table_escaped} ({$cols_tmp_str}) VALUES (".implode(",", $cols_as_key_val).");";
							}
							
							$rc = $this->query($sql_q);
							if (!$rc)
								throw new \Exception($this->connection->error);

							if ($is_duplicate_id)
							{
								$insert_id = $is_duplicate_id;
							}
							else
							{
								$insert_id = $this->connection->insert_id;
								if (!$insert_id)
									throw new \Exception('This should not happen.');
								$possible_duplicates[$duplicates_keys] = $insert_id;
							}
							
							if ($set_rowid_array && ($set_rowid_at_pos !== null))
								$set_rowid_array->setRowIdAtIndex($set_rowid_at_pos, $insert_id);
						}
						else if ($sql_operation === "ONE2M_UPDATE")
						{
							if (!$identified_by->getId())
								throw new \Exception('Failed to get an id');
							
							$sql_q = "UPDATE {$table_escaped} SET `{$sql_info['cols']['bkref']}`='{$cols_as_key_val[0]}' ".
										($sql_info['cols']['bkref_type'] ? ",`{$sql_info['cols']['bkref_type']}`='{$cols_as_key_val[1]}'" : "").
										" WHERE `{$sql_info['id_col']}`='".(int)$identified_by->getId()."';";

							$rc = $this->query($sql_q);
							if (!$rc)
								throw new \Exception($this->connection->error);
						}
						else if (($sql_operation === "INSERT") || ($sql_operation === "UPDATE"))
						{
							if (!$identified_by->getId())
								throw new \Exception('Failed to get an id where we set the value');
							$val_to_set = reset($cols_as_key_val);
							if (!$val_to_set->getId())
								throw new \Exception('Failed to get an id for the value to set');
							
							$sql_to_set = [];
							foreach ($cols_as_key_val as $k => $v)
							{
								$final_v = $v;
								if ($v instanceof \QIModel)
								{
									$final_v = (int)$v->getId();
									if (!$final_v)
										throw new \Exception('Failed to get an id for the value to set');
								}
								$sql_to_set[] = "`{$k}`='{$final_v}'";
							}
							
							$sql_q = "UPDATE {$table_escaped} SET ".implode(",", $sql_to_set).
										" WHERE `{$sql_info['id_col']}`='".(int)$identified_by->getId()."';";
							
							$rc = $this->query($sql_q);
							if (!$rc)
								throw new \Exception($this->connection->error);
						}
						else
							throw new \Exception("Unknown sql op: ".$sql_operation);
					}
				}
			}
		}
		
		\QApp::GetStorage()->connection->_stats->queries[] = "#### ------------------------ END BACKREFS :: ".$this->path." -----------------------";
	}
}

