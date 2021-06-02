<?php

final class QSqlTable_Titem
{
	private static $_MergeByInfo = [];
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
	
	public function run_model(array &$backrefs_list, string $parent_class_name_or_zero)
	{
		# qvar_dumpk('$parent_class_name_or_zero', $parent_class_name_or_zero);
		
		$ts = $this->ts;
		$class_name = $this->class_name;
		$this->model_type = \QModel::GetTypeByName($class_name);
		$app_class_name = get_class(QApp::Data());
		$is_top_lvl_class = ($class_name === $app_class_name);
		
		# assert all
		
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
		$merge_by_find = [];
		
		# @TODO if we have 2-more objects same ref, and one gets inserted, will get an ID
		
		$ids_to_q = [];
		# $merge_by_to_q = [];
		
		$path_parts = explode('.', $this->path);
		$parent_property_name = end($path_parts);
		if (substr($parent_property_name, -2, 2) === '[]')
			$parent_property_name = substr($parent_property_name, 0, -2);
		
		$parent_types_cache = [];
		$parent_props_cache = [];
		
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
				if ($merge_by_meta === null)
				{
					$parent_property_obj = null;
					if ($parent_class_name_or_zero && $parent_property_name)
					{
						$parent_property_obj = $parent_props_cache[$parent_class_name_or_zero];
						if ($parent_property_obj === null)
						{
							$parent_type = $parent_types_cache[$parent_class_name_or_zero];
							if ($parent_type === null)
								$parent_types_cache[$parent_class_name_or_zero] = $parent_type = (\QModel::GetTypeByName($parent_class_name_or_zero) ?: false);
							$parent_props_cache[$parent_class_name_or_zero] = $parent_property_obj = ($parent_type->properties[$parent_property_name] ?: false);
						}
					}
					
					$merge_by_meta = $this->get_mergeby_meta($class_name, $parent_property_name, $parent_class_name_or_zero, $parent_property_obj ?: null);
					if ($merge_by_meta === false)
						continue;
				}
				$this->get_mergeby_data($merge_by_meta, $merge_by_data, $merge_by_find, $model, $parent_class_name_or_zero, $parent_model_object, $parent_property_name);
			}
		}
		# $t2 = microtime(true);
		# qvar_dumpk('Prepare mby: ' . (($t2 - $t1)*1000));
				
		# $t1 = microtime(true);
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
					$key_val_data = new \SplObjectStorage();
					$key_val_data[$tmp_app] = $mby_data_c[0];
					foreach ($mby_app_props as $app_prop)
						$this->process_merge_by(true, $mby_info, $array, $app_prop, $key_val_data, $merge_by_find, $class_name, $ids_to_q);
				}
				if ($mby_types[1] && $parent_property_name)
				{
					foreach ($mby_data_c as $k => $mby_data_item)
					{
						if (is_numeric($k))
							continue;
						
						$array = new \QModelArray();
						$array_count = 0;
						foreach ($mby_data_item as $parent_itm)
						{
							$tmp_parent_itm = new $parent_itm();
							$tmp_parent_itm->setId($parent_itm->getId());
							$array[] = $tmp_parent_itm;
							$array_count++;
						}

						$already_done_by_type = false;
						if ($tmp_app && ($array_count === 1) && ($array[0] instanceof $tmp_app) && ($array[0]->getId() == $tmp_app->getId()) && 
								(isset($mby_app_props[$parent_property_name])) && $key_val_data &&
								($key_val_data->count() === $mby_data_item->count()))
						{
							$key_val_data->rewind();
							$mby_data_item->rewind();
							$already_done_by_type = (count($key_val_data->getInfo()) === count($key_val_data->getInfo()));
						}
						
						if (!$already_done_by_type)
						{
							$this->process_merge_by(false, $mby_info, $array, $parent_property_name, $mby_data_item, $merge_by_find, $class_name, $ids_to_q);
						}
					}
				}
			}
		}
		# $t2 = microtime(true);
		# qvar_dumpk('RUN mby: ' . (($t2 - $t1)*1000));
		
		$existing_records = [];
		$id_col_name = null;
		
		$sql_info_model = $this->getSqlInfo_model($sql_cache, $selector);
		
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
		
		foreach ($this->items as $model_inf)
		{
			list ($model, $action, /*$parent_model_class */, $parent_model_object) = $model_inf;
			$model_id = (int)$model->getId() ?: null;
			
			#$model_id = $model->_id ?: $model->Id;
			# $action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
			$modify_action = ($update = ($action & QModel::TransformUpdate)) || ($action & QModel::TransformCreate);
			
			// @todo : this is a very ugly fix to avoid insering into the main entry !!!
			if ($is_top_lvl_class && $model_id)
			{
				if ($action & QModel::TransformCreate)
					$action &= ~QModel::TransformCreate;
				if ($action & QModel::TransformDelete)
					$action &= ~QModel::TransformDelete;
			}
			
			# getSqlInfo_model(array &$cache, array &$backrefs_list, QIModel $model, $selector = null)
			$sql_info = $this->getSqlInfo_prepare_vals($sql_cache, $this->class_name, $sql_info_model, $backrefs_list, $model, $selector, $this->model_type->properties, $model_id);
			$entry_exists = false;
			
			if ($modify_action)
			{
				$create = ($action & QModel::TransformCreate);
				
				$rowid = $model_id ?: null;
				$update_done = false;
				$meta = $sql_info['meta'];
				
				$record = $cols_inf = $changed_props = $cleanup_q = $sql_update = $sql_insert = null;
				
				/* # @TODO - merge by
				$duplicates_list = $mergeBy_duplicate_objs->contains($model) ? $mergeBy_duplicate_objs[$model] : null;
				$first_duplicate = $duplicates_list ? reset($duplicates_list) : null;
				$is_not_first = $duplicates_list ? ($first_duplicate !== $model) : null;
				*/
				
				$record_rowid = $model_id ? $existing_records[$model_id] : null;
				
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
								$one_to_one_ops[$class_name][$p_name][] = [$model, $p_name, $one_to_one];
							}

							if ($p_name !== "_type")
								$model->_ols[$p_name] = "n/a";
							
							# @TODO - do we need to ?!
							# if (($value instanceof QIModel) && (!($value instanceof QIModelArray)))
							# $this->setBackReference($backrefs_list, $sql_info_model, $cols[$p_name]["ref"], $value, $model);
						}
					}

					if (($record["_type"] !== null) && ($record["_type"] !== $this->model_type->getIntId()))
					{
						// we have a type change
						list ($yield_obj_q, $yield_obj_binds, $yield_type) = $this->getCleanupQueryForTypeChange(QModelType::GetModelTypeById($record["_type"]), $model->getModelType(), 
																$record[$model->getModelType()->getRowIdColumnName()], $connection);

						$yield_obj = (object)['type' => $yield_type, 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
						throw new \Exception('@TODO - Type change!');
					}

					// also detect type change ?! TO DO !!!! we skip atm
					unset($changed_props["_type"]);

					$upd_pairs = [];
					# $yield_obj_binds = [];
					
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

						if ((($prop_item = $model->$ch_prop) instanceof \QIModel) && ($prop_app_property = $this->model_type->properties[$ch_prop]->getAppPropertyFor(get_class($prop_item))))
						{
							$app_bindings[$prop_app_property][] = $prop_item;
						}
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
						if (($one_to_one = $meta[$p_name]['oneToOne']))
						{
							$one_to_one_ops[$class_name][$p_name][] = [$model, $p_name, $one_to_one];
						}
						
						if ((($prop_item = $model->$p_name) instanceof \QIModel) && ($prop_app_property = $model->getModelType()->properties[$p_name]->getAppPropertyFor(get_class($prop_item))))
						{
							$app_bindings[$prop_app_property][] = $prop_item;
						}
						
						# @TODO - do we need to ?!
						# if (($value instanceof QIModel) && (!($value instanceof QIModelArray)))
						# $this->setBackReference($backrefs_list, $sql_info_model, $cols[$p_name]["ref"], $value, $model);
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
					
					$model->setId($insert_id);

					/*if ($mergeBy_duplicate_objs->contains($model))
					{
						foreach ($mergeBy_duplicate_objs[$model] as $mergeby_duplicate)
						{
							if (($mergeby_duplicate !== $model) && ($mergeby_duplicate->getId() === null))
								$mergeby_duplicate->setId($res);
						}
					}*/

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
				}
			
			}
			else if ($action & QModel::TransformDelete)
			{
				if (!$model->getId())
					throw new \Exception('Missing Id for delete');
				
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
	}
	
	public function run_collection(array &$backrefs_list)
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
		
		# LOAD EXISTING DATA
		$find_by_uniqueness = (!$one_to_many) && $property->getCollectionType()->hasAnyInstantiableReferenceTypes();
		
		$reading_queries_by_rowid = [];
		$reading_queries_by_uniqn = [];
		$data_by_rowid = [];
		
		# READ EXISTING DATA
		# if (!$one_to_many)
		{
			foreach ($this->items as $model_inf)
			{
				list ($model, $parent_model) = $model_inf;
				$parent_id = (int)$parent_model->getId();

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
					if ($rowid === null)
					{
						if ($one_to_many && ($item instanceof QIModel))
							$rowid = (int)$item->getId();
						else if ($find_by_uniqueness && $item_is_model && ($tmp_mp_id = $parent_id) && ($tmp_it_id = (int)$item->getId()))
							$uniqueness = [$tmp_it_id, $tmp_mp_id];
					}

					if (!$one_to_many)
					{
						if ($rowid || $uniqueness)
						{
							// select & update
							if ($rowid)
							{
								// $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
								if (!isset($reading_queries_by_rowid[0]))
									$reading_queries_by_rowid[0] = "SELECT `".implode("`,`", $sql_info["cols"])."`,`".$sql_info["id_col"]."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."` IN ";
								$reading_queries_by_rowid[1][$rowid] = $rowid;
								$reading_queries_by_rowid[2] = $sql_info["id_col"];
							}
							else // by uniqueness
							{
								if (!isset($reading_queries_by_uniqn[0]))
									$reading_queries_by_uniqn[0] = [
											"SELECT `".implode("`,`", $sql_info["cols"])."`,`{$sql_info["id_col"]}`,`{$sql_info["cols"]["ref"]}`,`{$sql_info["cols"]["bkref"]}` FROM {$sql_info["tab"]} WHERE ",
											$sql_info["cols"]["ref"], $sql_info["cols"]["bkref"]];
								$reading_queries_by_uniqn[1][$uniqueness[0]."\n".$uniqueness[1]] = [$uniqueness[0], $uniqueness[1]];
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
								$reading_queries_by_rowid[0] = "SELECT `".implode("`,`", $sql_info["cols"])."`,`".$sql_info["id_col"]."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."` IN ";
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
			qvar_dumpk('$reading_queries_by_uniqn', $reading_queries_by_uniqn);
			throw new \Exception('@TODO');
		}
		
		$update_to_delete_queries = [];
		
		# if ($this->path === 'Services[].Categories')
		
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
							$uniqueness = [$tmp_it_id, $tmp_mp_id];
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
						
						if ($item_create && (!$update_done))
						{
							$ins_vals = array();
							$ins_cols = array();

							$ins_vals[] = $rowid ?: 'NULL';
							$ins_cols[] = $sql_info["id_col"];

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
							$yield_obj_q = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $ins_cols)."`) VALUES (".implode(",", $ins_vals)."); # ".
										json_encode([$this->path, $parent_model->getId(), $item->getId(), $item->_ts, $model->getTransformState($key)])." ";
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

						if ($item_is_model && ($insert_done || $update_done))
						{
							$coll_item_has_changes = true;
							if ($rowid)
							{
								if ($rowid && ($in_db = $data_by_rowid[$rowid]) && ($fwd_val = $in_db[$sql_info["cols"]["ref"]]) && ((int)$fwd_val == (int)$item->getId()))
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
								$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["ref"], $item, $rowid);
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
									$update_to_delete_queries[0] = "UPDATE ".$sql_info["tab"]." SET `{$col_backref}`=NULL WHERE `".$sql_info["id_col"]."` IN ";
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
								if ($rowid && ($in_db = $data_by_rowid[$rowid]) && ($backref_val = $in_db[$sql_info["cols"]["bkref"]]) && ((int)$backref_val == (int)$parent_id))
								{
									# @TODO , should we check the type ? can it make a difference ?!
									$coll_item_has_changes = false;
								}
								if ($coll_item_has_changes)
								{
									# qvar_dumpk("One2M BK ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["bkref"]." / ".$parent_model->Name." :: ".get_class($backref_val));
									$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val, $model, $key);
								}
							}
							else
							{
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
						$rowid = $model->getRowIdAtIndex($key);
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
						
						qvar_dumpk('$yield_obj_q', $yield_obj_q);
						
						$model->_tsx[$item_k] = QModel::TransformDelete;
					}
				}
				# @TODO delete
				throw new \Exception('@TODO - collection delete');
			}
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

		foreach ($props as $prop)
		{
			if ($prop->storage["none"] || $prop->hasCollectionType())
				continue;

			$p_name = $prop->name;

			if (($one_to_one = trim($prop->storage['oneToOne'])))
				$meta[$p_name]['oneToOne'] = [$one_to_one, $prop->getAllInstantiableReferenceTypes()];

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

		return ["sql" => null, "cols" => $cols, "vals" => null, "tab" => qEscTable($this->table_name), "id_col" => $rowid_col, "objs" => null, 'meta' =>  $meta];
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
	
	protected function setBackReference(&$backrefs_list, $sql_info, $update_column, QIModel $reference, $row_identifier, $model_array = null, $model_array_key = null)
	{
		// ensure tmpid
		$id = ($row_identifier instanceof QIModel) ? "#tmp/".($row_identifier->_tmpid ?: ($row_identifier->_tmpid = QModel::GetNextId()))."/#" : $row_identifier;
		$key = $sql_info["tab"].":".$id;
		
		if ($model_array && ($model_array_key !== null))
			$backrefs_list[$key][] = [$sql_info, $update_column, $reference, $row_identifier, $model_array, $model_array_key];
		else
			$backrefs_list[$key][] = [$sql_info, $update_column, $reference, $row_identifier];
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
			$vals["%"][] = $vals["_type"]["val"] = $this->extractTypeIdForVariable($model);
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
						$vals["%"][] = $vals[$p_name]["type"] = $this->extractTypeIdForVariable($value);
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
		$type_mergeBy = null;
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
			$parts = preg_split("/(\\s*\\,\\s*)/uis", $type_mergeBy, -1, PREG_SPLIT_NO_EMPTY);
			
			$merge_by_meta_type_parsed = [];
			foreach ($parts as $part)
				$merge_by_meta_type_parsed[] = preg_split("/(\\s*\\.\\s*)/uis", $part, -1, PREG_SPLIT_NO_EMPTY);
			
			# to cleanup spaces
			$type_mergeBy = preg_replace("/(\\s+)/uis", "", $type_mergeBy);
			
			// global merge by
			if ($property && ($app_property = $property->getAppPropertyFor($class_name)))
				$mby_app_props = [$app_property => $app_property];
			else
				$mby_app_props = \QModel::GetDefaultAppPropertiesForTypeValues($class_name);

			if (!$mby_app_props)
				throw new \Exception("We have a mergeBy on {$class_name} but we can not find it's app property");
		}
		
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
			$collection_back_ref_column = \QModelQuery::GetTypesCache($parent_class_name_or_zero)[$parent_property_name]['cb'];
			if (!$collection_back_ref_column)
				throw new \Exception("Unable to find collection back reference column for: {$parent_class_name_or_zero}.{$parent_property_name}");
			# return [$type_mergeBy ?: null, $property_mergeBy ?: null, $merge_by_meta_type_parsed, $merge_by_meta_prop_parsed];
			$ret = [];
			if ($type_mergeBy)
			{
				$ret[$type_mergeBy][0] = $merge_by_meta_type_parsed;
				$ret[$type_mergeBy][1] = count($ret) - 1; # the position
				$ret[$type_mergeBy][2][0] = 'type';
				$ret[$type_mergeBy][3] = $mby_app_props;
				$ret[$type_mergeBy][4] = \QApp::GetDataClass();
				$ret[$type_mergeBy][5] = \QApp::GetDataId();
				$ret[$type_mergeBy][6] = $type_mergeBy;
				$ret[$type_mergeBy][7] = explode(",", $type_mergeBy);
				$ret[$type_mergeBy][8] = $collection_back_ref_column;
			}
			if ($property_mergeBy)
			{
				$ret[$property_mergeBy][0] = $merge_by_meta_prop_parsed;
				$ret[$property_mergeBy][1] = count($ret) - 1; # the position
				$ret[$property_mergeBy][2][1] = 'prop';
				$ret[$property_mergeBy][6] = $property_mergeBy;
				$ret[$property_mergeBy][7] = explode(",", $property_mergeBy);
				$ret[$property_mergeBy][8] = $collection_back_ref_column;
			}
			return $ret;
		}
	}
	
	private function get_mergeby_data(array $merge_by_meta, array &$merge_by_data, array &$merge_by_find, \QIModel $model, string $parent_class_name_or_zero, \QIModel $parent_model_object, string $parent_property)
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
				# $parent_property_name, $parent_class_name_or_zero, $parent_model_object
				# set it up, do we want a key ?
				
				# WHERE (Id=1 AND (???) IN (...)) OR (...)
				if ($type_and_or_prop[0])
				{
					$merge_by_find[0][$key][] = $model;
					# $merge_by_data[$pos][0] = list of all key => data
					$merge_by_data[$pos][0][$key] = $data;
				}
				if ($type_and_or_prop[1])
				{
					$merge_by_find[$parent_property][(int)$parent_model_object->getId()][$key][] = $model;
					
					$ref = &$merge_by_data[$pos][$parent_class_name_or_zero];
					if (!$ref)
						$ref = new \SplObjectStorage();
					if (!isset($ref[$parent_model_object]))
						$ref[$parent_model_object] = [$key => $data];
					else
						$ref[$parent_model_object][$key] = $data;
					unset($ref);
				}
			}
		}
	}
	
	private function extract_data_from_path(array $walk_struct, \QIModel $model)
	{
		$data = [];
		$valid = true;
		$key = "";

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
				$key .= ($split ? "\x00NULL" : "NULL");
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
		
		return [$data, $valid, $key];
	}
	
	private function process_merge_by(bool $on_app_search, array $mby_info, \QModelArray $array, string $app_prop, \SplObjectStorage $key_val_data, array $merge_by_find, string $class_name, array &$ids_to_q)
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
		
		foreach ($key_val_data as $parent_obj)
		{
			$in_pairs = $key_val_data->getInfo();
			
			$prepend_bra = false;
			if ($count_arr > 1)
			{
				# small optimization, only set parent ID if more than one parent
				# @TODO - we set `$collection_backref_column` without a table alias so a conflict is possible, 
				#				it would be nice to predict or force the alias, atm the alias would be `B`
				#				but that is something internal and it's not safe to hard-code it
				$str_in = "({$mby_info[6]},`{$collection_backref_column}`) IN (";
			}
			else
				$str_in = "({$mby_info[6]}) IN (";
			
			foreach($in_pairs as $grp)
			{
				if ($prepend_bra)
					$str_in .= ",";
				
				$str_v = "";
				$cnt = 0;
				$prepend_com = false;
				foreach ($grp as $val)
				{
					if ($prepend_com)
						$str_v .= ",";
					$str_v .= $this->escape($val);
					$prepend_com = true;
					$cnt++;
				}
				
				# the ID of the parent
				if ($count_arr > 1) # small optimization, only set parent ID if more than one parent
				{
					if ($prepend_com)
						$str_v .= ",";
					$str_v .= $parent_obj->getId();
					$prepend_com = true;
					$cnt++;
				}
				
				$str_in .= ($cnt > 1) ? "({$str_v})" : $str_v;
				
				$prepend_bra = true;
			}
			$str_in .= ")";
			
			if ($prepend_or)
				$str .= " OR ";
			$str .= "{$str_in}";
			$prepend_or = true;
		}
		
		# @TODO ... test that with one item ($key_val_data_count === 1) it will not query too much data
		if ($count_arr > 1)
			$query_str = "{$app_prop}.{{$mby_info[6]} WHERE {$str} GROUP BY {$mby_info[6]},`{$collection_backref_column}`}";
		else
			$query_str = "{$app_prop}.{{$mby_info[6]} WHERE {$str} GROUP BY {$mby_info[6]}}";
		
		# $t1 = microtime(true);
		$array->query($query_str);
		# $t2 = microtime(true);
		# qvar_dumpk('query mby: ' . (($t2 - $t1)*1000));
		
		# FOR TYPE/APP :: $merge_by_find[0][$key][] = $model; 
		# FOR PROPS    :: $merge_by_find[$parent_property][(int)$parent_model_object->getId()][$key][] = $model;
		$merge_by_find_this_ctx = $on_app_search ? $merge_by_find[0] : $merge_by_find[$app_prop];
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
								$ids_to_q[$item_id] = $item_id;
							}
						}
					}
				}
			}
		}
	}
}

