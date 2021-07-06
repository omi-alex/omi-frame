<?php
/**
 * @package bitwise
 * @subpackage sql
 *
 */

/**
 * Represents an SQL table
 * 
 * @package bitwise
 * @subpackage sql
 */
class QSqlTable extends QStorageTable
{
	use QSqlTable_GenTrait, QSqlTable_New;
	
	protected static $Max_Allowed_Packet;
	protected static $_MergeByInfo = [];
	protected static $_MergeByInfoProps = [];
	protected static $_MergeByInfo_Parsed = [];
	protected static $_PopulateInfoProps = [];
	
	protected static $_ModelKeyAutoIncrement = 1;
	
	public static $_Transactions_Count = 0;
	
	public static $_tmp_time = 0;
	
	public static $_DEBUG = false;
	
	public static $_DEBUG_CUSTOM = false;
	
	/**
	 * The name of the table
	 *
	 * @var string
	 */
	public $name;
	/**
	 * The database that contains the table
	 *
	 * @var QSqlDatabase
	 */
	public $parent;
	/**
	 * The column of the table (array of QSqlTableColumn)
	 *
	 * @var QSqlTableColumn[]
	 */
	public $columns;
	/**
	 * The engine type for the table
	 *
	 * @var string
	 */
	public $engine;
	/**
	 * The collation of the table
	 *
	 * @var string
	 */
	public $collation;
	/**
	 * The charset of the table
	 *
	 * @var string
	 */
	public $charset;
	/**
	 * The indexes of the table (array of QSqlTableIndex)
	 *
	 * @var QSqlTableIndex[]
	 */
	public $indexes;
	/**
	 * The foreign keys of the table (array of QSqlForeignKey)
	 *
	 * @var QSqlForeignKey[]
	 */
	public $references;
	/**
	 * The table comments
	 *
	 * @var string
	 */
	public $comment;
	
	public function getPrimaryKey()
	{
		return $this->indexes["PRIMARY"];
	}
	
	public function getIndexForColumn($column_name, $must_be_first_in_index = true)
	{
		foreach ($this->indexes as $index)
		{
			foreach ($index->columns as $column)
			{
				if ($column->name == $column_name)
					return $index;
				if ($must_be_first_in_index)
					break;
			}
		}
		return null;
	}

	/**
	 * Gets a model by id and type
	 *
	 * @param integer|string $model_id
	 * @param QIModel $instance
	 * @param QIModelTypeUnstruct|QModelType $model_type
	 * 
	 * @return QIModel
	 */
	public function storageGetModelById($model_id, QIModel $instance = null, $model_type = null)
	{
		// TO DO
	}

	/**
	 * QIModel::sync will call QIModel::syncModel
	 * QIModel::syncModel will/may call $container::syncModel
	 * The action is one of the QIModel::ModelState* but custom actions may also be defined
	 * When we recurse a $backtrace will also be created
	 * 
	 * @param QIModel $model
	 * @param integer|string $action
	 * @param mixed $parameters
	 * @param QBacktrace $backtrace
	 * @param boolean $as_simulation
	 */
	public function storageSyncModel(QIModel $model, $action, $parameters, QBacktrace $backtrace = null, $as_simulation = false)
	{
		// Depreciated ?!
		throw new Exception("No longer used");
	}
	
	public static function DumpQ($q, $__t)
	{
		return;
		/*
		if (!\QModel::$Dump)
			return;

		if (!\QModel::$__Cache["TransformQueries"])
			\QModel::$__Cache["TransformQueries"] = 0;
		\QModel::$__Cache["TransformQueries"]++;

		$f = fopen("TRANSFORM_QUERIES.txt", "a+");
		fwrite($f, "QUERY[" . \QModel::$__Cache["TransformQueries"] . "] :: {$q}\n\nExecuted in: " . (microtime(true) - $__t) . 
			" seconds\n\n=======================================================================\n");
		fclose($f);*/
	}
	
	public function executeTransaction(QBacktrace $backtrace, $transform_state = null, $selector = null, 
										bool $trigger_provision = true, bool $trigger_events = true, bool $trigger_save = false, bool $trigger_import = false, 
										\QIStorage $storage = null)
	{
		static::$_Transactions_Count++;
		$start_time = microtime(true);
		
		// $t1 = microtime(true);		
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		if (!$storage)
			$storage = $this->getStorage();
		
		$mysqli = $storage->connection;

		$models_ref = $backtrace->reference;
		
		$model_list = ($models_ref instanceof \QModelArray) ? $models_ref->getArrayCopy() : (is_array($models_ref) ? $models_ref : [$models_ref]);
		
		\QModel::TransactionFlagBegin(['trigger_events' => $trigger_events, 'trigger_provision' => $trigger_provision]);
		
		if ($trigger_import)
		{
			foreach ($model_list as $model)
				$model->beforeImport($selector);
		}
		if ($trigger_save)
		{
			foreach ($model_list as $model)
				$model->beforeSave($selector);
		}
		
		$storage->begin();

		$all_objects = array();
		
		$transaction_was_commited = false;
		
		try
		{
			if ($trigger_events || $trigger_provision)
			{
				foreach ($model_list as $model)
					$model->afterBeginTransaction($selector, ($transform_state !== null) ? $transform_state : ($model->_ts));
			}
			
			$m1 = memory_get_usage();
			$t1 = microtime(true);
			if (($selector !== true) && defined('QQQQQ_TEST_NEW_TRANSFORM') && QQQQQ_TEST_NEW_TRANSFORM && \QAutoload::GetDevelopmentMode())
			{
				$this->recurseTransactionList_New($mysqli, $model_list, $transform_state, $selector);
				
				$t2 = microtime(true);
				$m2 = memory_get_usage();

				/*qvar_dumpk("mem: " . round(($m2 - $m1) / 1024),
						"time: " . round(($t2 - $t1), 5), $mysqli->_stats);*/
			}
			else
			{
				$this->recurseTransactionList($mysqli, $model_list, $transform_state, $selector);
			}
			
			
			if ($trigger_events || $trigger_provision)
			{
				foreach ($model_list as $model)
					$model->beforeCommitTransaction($selector, ($transform_state !== null) ? $transform_state : ($model->_ts));
			}

			$this->getStorage()->commit();
			$transaction_was_commited = true;
			
			if ($trigger_import)
			{
				foreach ($model_list as $model)
					$model->afterImport($selector);
			}
			if ($trigger_save)
			{
				foreach ($model_list as $model)
					$model->afterSave($selector);
			}
			
		}
		catch (Exception $ex)
		{
			$this->getStorage()->rollback();
			throw $ex;
		}
		finally
		{
			if (!$transaction_was_commited)
			{
				$rc_rb = $this->getStorage()->rollback(); # make sure we rollback
				# file_put_contents("test_alex_rollback_on_die.txt", "\n" . date('Y-m-d H:i:s') . ' | ' . json_encode(['rollback' => $rc_rb]), FILE_APPEND);
			}
			
			\QModel::TransactionFlagEnd();
			// do a cleanup
			foreach ($all_objects as $obj)
			{
				if ($obj instanceof \QIModel)
				{
					unset($obj->_tsx);
					unset($obj->_ts);
				}
			}
			
			static::$_tmp_time += (microtime(true) - $start_time);
		}
	}
	
	private function extractTypeIdForVariable($var)
	{
		if ($var instanceof QIModel)
			return $this->getStorage()->getTypeIdInStorage($var->getModelType()->class);
		else
		{
			$v_ty = QModel::GetTypeForVariable($var);
			return $v_ty ? $v_ty->getTypeId() : 0;
		}
	}
	
	protected function escapeScalar($value, $connection)
	{
		if ($value === null)
			return "NULL";
		else if (is_int($value) || is_float($value))
			return (string)$value;
		else if (is_string($value))
			return "'".$connection->real_escape_string($value)."'";
		else if (is_bool($value))
			return $value ? "1" : "0";
		else if (is_array($value) || is_object($value))
			return "'".$connection->real_escape_string(json_encode($value))."'";
		else
			throw new Exception("Unknown value type");
	}
	
	protected static function EscapeScalar_S($value, $connection)
	{
		if ($value === null)
			return "NULL";
		else if (is_int($value) || is_float($value))
			return (string)$value;
		else if (is_string($value))
			return "'".$connection->real_escape_string($value)."'";
		else if (is_bool($value))
			return $value ? "1" : "0";
		else if (is_array($value) || is_object($value))
			return "'".$connection->real_escape_string(json_encode($value))."'";
		else
			throw new Exception("Unknown value type");
	}

	/**
	 * Does the transaction in a recursive manner
	 * 
	 * @param array $all_objects Gathers the object in an array indexed by type & id to avoid infinite loops
	 * @param type $sql_cache Caches SQL info
	 * @param type $backrefs_list Links between objects to be cleaned up at the end of the transaction
	 * @param type $connection The SQL connection to use
	 * @param QIModel $model The current model beeing processed
	 * @param QModelProperty $property The property beeing processed
	 * @param QIModel $parent_model The parent of the current model
	 * @param integer $ts The transform state to be forced if present
	 * @param array $selector The selector to be used if present
	 * @return type
	 * 
	 * @throws Exception
	 */
	protected function recurseTransaction(&$sql_cache, &$backrefs_list, $connection, \QIModel $model, QModelProperty $property = null, 
												QIModel $parent_model = null, $ts = null, $selector = null, \SplObjectStorage $mergeBy_duplicate_objs = null, 
												array &$one_to_one_ops = null, array &$app_bindings = null, $mysqli = null)
	{
		// if there is no action we default to null
		$action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
		
		// @todo : this is a very ugly fix to avoid insering into the main entry !!!
		if ($model->_id && (get_class($model) === get_class(QApp::Data())))
		{
			if ($action & QModel::TransformCreate)
				$action &= ~QModel::TransformCreate;
			if ($action & QModel::TransformDelete)
				$action &= ~QModel::TransformDelete;
		}
		
		// echo "We enter: ".get_class($model)." :: ".$model->getId()."<br/>";
		$is_collection = ($model instanceof QIModelArray);
		$modify_action = ($update = ($action & QModel::TransformUpdate)) || ($action & QModel::TransformCreate);
		$one_to_many = $is_collection && $property->isOneToMany();
		$prepare_values = /* $modify_action && */ (!$is_collection);
		
		$model->beforeTransformModelByContainer($this, $action);
		
		$class_name = get_class($model);
		
		// var_dump("recurseTransaction::".get_class($model)." :: ", $is_collection ? "collection" : $model->getId());
		
		// &$cache, &$backrefs_list, $prepare_values, QIModel $model, $is_collection = false, $one_to_many = false, QModelProperty $property = null
		$sql_info = $this->getSqlInfo($sql_cache, $backrefs_list, $connection, $prepare_values, $model, $is_collection, $one_to_many, $property, $selector);
		
		$meta = $sql_info['meta'];

		$entry_exists = false;
		
		if ($modify_action)
		{
			// Create & update
			$create = ($action & QModel::TransformCreate) ? true : false;
			
			if ($model instanceof QIModelArray)
			{
				$find_by_uniqueness = (!$one_to_many) && $property->getCollectionType()->hasAnyInstantiableReferenceTypes();
				
				$set_method = $property ? "set".$property->name."_Item_" : null;
				if ($parent_model && $property)
					$parent_model->{"set{$property->name}"}($model, 1);
					
				// this means all existing items NOT IN the $model will be removed from the collection
				$replace_elements = ($model->_ts & QModel::TransformDelete) && (($model->_ts & QModel::TransformCreate) || ($model->_ts & QModel::TransformUpdate))
										&& $parent_model && $parent_model->getId();
				
				$keep_non_populated_elements = $replace_elements ? [] : null;
				
				foreach ($model as $key => $item)
				{
					$item_is_model = ($item instanceof QIModel);
					if ($replace_elements)
					{
						// in replace mode, flag populated elements for delete
						if (($model->_tsp[$key] === null) && ((!($item instanceof \QIModel)) || ($item->_ts === null)))
							$model->_tsp[$key] = \QIModel::TransformDelete;
						else
							$keep_non_populated_elements[$key] = $item;
					}
					
					$item_action = $item_is_model ? (($item->_ts !== null) ? $item->_ts : QModel::TransformMerge) : null;
					
					$rowid = $model->getRowIdAtIndex($key);
					
					$uniqueness = null;
					// hasAnyInstantiableReferenceTypes
					// for QIModel on one to many, the rowid is the ID of the element
					if ($rowid === null)
					{
						if ($one_to_many && ($item instanceof QIModel))
							$rowid = $item->getId();
						else if ($find_by_uniqueness && $item_is_model && ($tmp_mp_id = $parent_model->getId()) && ($tmp_it_id = $item->getId()))
							$uniqueness = [$tmp_it_id, $tmp_mp_id];
					}
					
					// we do the check here
					if ($parent_model && $set_method)
						$parent_model->$set_method($item, $key, $rowid, 1);
					
					$item_update = $item_action ? ($item_action & QModel::TransformUpdate) : $update;
					$item_create = $item_action ? (($item_action & QModel::TransformCreate) ? true : false) : $create;
					$item_delete = $item_action ? ($item_action & QModel::TransformDelete) : false;
					
					$sql_select = $sql_update = $sql_insert = $sql_delete = $record = null;
					$has_changed = false;
					$was_removed = false;
					
					if (!$one_to_many)
					{
						$update_done = false;
						if ($item_update && ($rowid || $uniqueness))
						{
							// select & update
							if ($rowid)
							{
								// $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."` FROM {$sql_info["tab"]} WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
								$yield_obj_q = "SELECT `".implode("`,`", $sql_info["cols"])."`,`".$sql_info["id_col"]."` FROM {$sql_info["tab"]} WHERE ";
								$yield_obj_binds = [$rowid];
								
								$yield_obj = (object)['type' => 'select', 'params' => ['q' => $yield_obj_q, 'c' => ["`".$sql_info["id_col"]."`"], 'binds' => $yield_obj_binds]];
								yield($yield_obj);
							}
							else // by uniqueness
							{
								/* $sql_select = "SELECT `".implode("`,`", $sql_info["cols"])."`,`{$sql_info["id_col"]}` FROM {$sql_info["tab"]} WHERE ".
										"`{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
										"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
								$yield_obj_q = "SELECT `".implode("`,`", $sql_info["cols"])."`,`{$sql_info["id_col"]}`,`{$sql_info["cols"]["ref"]}`,`{$sql_info["cols"]["bkref"]}` FROM {$sql_info["tab"]} WHERE ";
								$yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
								
								$yield_obj = (object)['type' => 'select', 'params' => ['q' => $yield_obj_q, 'c' => ["`{$sql_info["cols"]["ref"]}`", "`{$sql_info["cols"]["bkref"]}`"], 'binds' => $yield_obj_binds]];
								yield($yield_obj);
							}
							// echo ($sql_select)."<br/>\n";
							//qvdumptofile($sql_select);
							
							$record = $yield_obj ? $yield_obj->result : null;
							
							// if select failed
							if ($record !== null)
							{
								if (!$rowid)
									$rowid = $record[$sql_info["id_col"]];
								
								if (!$rowid)
									throw new Exception("This should not be");

								$upd_pairs = array();
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
									// may be empty if there is nothing to update
									// $sql_update = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=".$rowid;
									
									$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=?";
									$yield_obj_binds[] = $rowid;

									$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
									yield($yield_obj);
									
									if (($record = $yield_obj->result) === false)
									{
										var_dump($sql_update);
										throw new Exception($connection->error);
									}
									
									//static::DumpQ($sql_update, $__t);
								}
								
								$update_done = true;
							}
							
						}
						
						if ($item_create && (!$update_done))
						{
							$ins_vals = array();
							$ins_cols = array();
							
							$ins_vals[] = $rowid;
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
									$ins_vals[] = null;
								}
							}
							else if ($sql_info["cols"]["val"])
							{
								$ins_cols[] = $sql_info["cols"]["val"];
								$ins_vals[] = $item;
								if ($sql_info["cols"]["ref"])
								{
									$ins_cols[] = $sql_info["cols"]["ref"];
									$ins_vals[] = null;
								}
							}
							
							// $sql_insert = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $ins_cols)."`) VALUES (".implode(",", $ins_vals).");";
							$yield_obj_q = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $ins_cols)."`) VALUES (".implode(",", array_fill(0, count($ins_vals), '?')).");";
							$yield_obj_binds = $ins_vals;
							
							$yield_obj = (object)['type' => 'insert', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
							yield($yield_obj);
							
							if (!($res = $yield_obj->result))
								throw new \Exception('insert has failed');
								
							$insert_id = $res;

							$model->setRowIdAtIndex($key, $insert_id);
							$rowid = $insert_id;
						}

						if ($item_is_model && ($item_create || $item_update))
						{
							// var_dump("M2M FWD ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["ref"]." / {$rowid}");
							// reference on ManyToMany only
							$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["ref"], $item, $rowid);
						}
						
						if (($rowid !== null) && ($item_delete || ($model->getTransformState($key) & QModel::TransformDelete)))
						{
							// $sql_delete = "DELETE FROM ".$sql_info["tab"]." WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($rowid, $connection);
							$yield_obj_q = "DELETE FROM ".$sql_info["tab"]." WHERE `".$sql_info["id_col"]."`=?";
							$yield_obj_binds = [$rowid];

							$yield_obj = (object)['type' => 'delete', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
							yield($yield_obj);
							
							// echo $sql_delete."<br/>";
							//qvdumptofile($sql_delete);
							
							if (($res = $yield_obj->result) === false)
							{
								if (\QAutoload::GetDevelopmentMode())
									qvar_dump($yield_obj_q, $yield_obj_binds);
								throw new Exception($connection->error);
							}
							//static::DumpQ($sql_delete, $__t);
							
							$was_removed = true;
						}
					}
					else // ONE TO MANY
					{
						$col_backref = $sql_info["cols"]["bkref"];
						
						if ($item_is_model && ($_otm_item_id = $item->getId()))
						{
							// handle delete
							if (($_otm_item_id !== null) && ($item_delete || ($model->getTransformState($key) & QModel::TransformDelete)))
							{
								// $sql_delete = "UPDATE ".$sql_info["tab"]." SET `{$col_backref}`=NULL WHERE `".$sql_info["id_col"]."`=".$this->escapeScalar($_otm_item_id, $connection);
								$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET `{$col_backref}`=NULL WHERE `".$sql_info["id_col"]."`=?";
								$yield_obj_binds = [$_otm_item_id];
								
								$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
								yield($yield_obj);
								
								//$__t = microtime(true);
								if (($res = $yield_obj->result) === false)
								{
									var_dump($sql_delete);
									throw new Exception($connection->error);
								}
								//static::DumpQ($sql_delete, $__t);

								$was_removed = true;
								
							}
						}
					}

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
							// var_dump("One2M BK ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["bkref"]." / ".$parent_model->Name." :: ".get_class($backref_val));
							$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val, $model, $key);
						}
						else
						{
							// var_dump("M2M BK ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$sql_info["cols"]["bkref"]." / ");
							$this->setBackReference($backrefs_list, $sql_info, $sql_info["cols"]["bkref"], $parent_model, $backref_val);
						}
					}
				}
				
				if ($keep_non_populated_elements !== null)
				{
					// we need to remove elements that were set for delete
					$model->exchangeArray($keep_non_populated_elements);
				}
			}
			else
			{
				$rowid = $model->getId();
				$update_done = false;
				
				$sql_select = $record = $cols_inf = $changed_props = $cleanup_q = $sql_update = $sql_insert = null;
				
				$duplicates_list = $mergeBy_duplicate_objs->contains($model) ? $mergeBy_duplicate_objs[$model] : null;
				$first_duplicate = $duplicates_list ? reset($duplicates_list) : null;
				$is_not_first = $duplicates_list ? ($first_duplicate !== $model) : null;
				
				$max_loops_to_wait = 15;
				while ($is_not_first && ($max_loops_to_wait > 0) && ($first_duplicate->getId() === null))
				{
					yield((object)['type' => 'no-op']);
					$max_loops_to_wait--;
					if ($max_loops_to_wait < 1)
						throw new Exception('We have an issue. The object we wait for is not getting an ID');
				}
				
				if ($is_not_first)
				{
					$rowid = $first_duplicate->getId();
					if ($rowid === null)
						throw new \Exception('We have waited for an id but failed. This should not happen.');
					$model->setId($rowid);
				}
				
				$record_rowid = null;
				if ($rowid)
				{
					// $sql_select = "SELECT `".$sql_info["id_col"].($sql_info["cols"]["%"] ? "`,`".implode("`,`", $sql_info["cols"]["%"]) : "")."` FROM ".$sql_info["tab"]." WHERE `".$sql_info["id_col"]."`=".$rowid;
					$yield_obj_q = "SELECT `".$sql_info["id_col"].($sql_info["cols"]["%"] ? "`,`".implode("`,`", $sql_info["cols"]["%"]) : "")."`,`".$sql_info["id_col"]."` FROM ".$sql_info["tab"]." WHERE ";
					$yield_obj_binds = [$rowid];

					$yield_obj = (object)['type' => 'select', 'params' => ['q' => $yield_obj_q, 'c' => ["`".$sql_info["id_col"]."`"], 'binds' => $yield_obj_binds]];
					yield($yield_obj);
					
					$record_rowid = $yield_obj->result;
					
					if ($record_rowid === null)
					{
						// huston we have a problem, we should trigger a cleanup for this ID
						if (QAutoload::GetDevelopmentMode())
							throw new \Exception("The object is no longer in the DB: ".get_class($model)." | id: ".$model->getId());
						else
							// not good but let's hope it works
							$model->setId(null);
					}
				}
				else
					yield((object)['type' => 'no-op']);
				
				if ($update && ($record_rowid !== null))
				{
					$record = $record_rowid;
					$entry_exists = true;

					$model->_tsx = $action & (~QModel::TransformCreate);

					$changed_props = array();

					// detect changed properties
					// also detect type change ?! TO DO !!!!
					foreach ($sql_info["cols"] as $p_name => $cols_inf)
					{
						if ($p_name === "%")
							continue;

						$value = $model->$p_name;

						$scalar_was_changed = false;
						if ($cols_inf["val"] && (($old_sc_val = $record[$cols_inf["val"]]) !== $value))
						{
							$scalar_was_changed = (($value === null) || is_bool($value)) ? 
								($model->_wst[$p_name] ? true : false) : 
								(((string)$old_sc_val) !== ((string)$value));
						}

						if (
								// SCALAR
								($scalar_was_changed) ||
								// REFERENCE
								(($cols_inf["ref"]) && ($record[$cols_inf["ref"]] != (($value instanceof QIModel) ? $value->getId() : null))) || 
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
						}
					}

					if (($record["_type"] !== null) && ($record["_type"] !== $model->getModelType()->getIntId()))
					{
						// we have a type change
						list ($yield_obj_q, $yield_obj_binds, $yield_type) = $this->getCleanupQueryForTypeChange(QModelType::GetModelTypeById($record["_type"]), $model->getModelType(), 
																$record[$model->getModelType()->getRowIdColumnName()], $connection);

						$yield_obj = (object)['type' => $yield_type, 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
						yield($yield_obj);
					}

					// also detect type change ?! TO DO !!!! we skip atm
					unset($changed_props["_type"]);

					$upd_pairs = array();
					$yield_obj_binds = [];

					foreach ($changed_props as $ch_prop)
					{
						if (($c = $sql_info["cols"][$ch_prop]["val"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=?";
							$yield_obj_binds[] = $sql_info["vals"][$ch_prop]["val"];
						}
						if (($c = $sql_info["cols"][$ch_prop]["ref"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=?";
							$yield_obj_binds[] = $sql_info["vals"][$ch_prop]["ref"];
						}
						if (($c = $sql_info["cols"][$ch_prop]["type"]) !== null)
						{
							$upd_pairs[] = "`{$c}`=?";
							$yield_obj_binds[] = $sql_info["vals"][$ch_prop]["type"];
						}

						if ((($prop_item = $model->$ch_prop) instanceof \QIModel) && ($prop_app_property = $model->getModelType()->properties[$ch_prop]->getAppPropertyFor(get_class($prop_item))))
						{
							$app_bindings[$prop_app_property][] = $prop_item;
						}
					}

					//var_dump($upd_pairs);
					if ((!empty($upd_pairs)) && $changed_props)
					{
						// may be empty if there is nothing to update
						// var_dump($changed_props);
						// $sql_update = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=".$rowid;

						$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $upd_pairs)." WHERE `".$sql_info["id_col"]."`=?";
						$yield_obj_binds[] = $rowid;

						$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
						yield($yield_obj);
					}

					$update_done = true;
				}
				
				if ($create && (!$update_done))
				{
					if ($rowid !== null)
					{
						$sql_info["cols"]["%"][] = $sql_info["id_col"];
						$sql_info["vals"]["%"][] = $rowid;
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
					}
					
					$yield_obj_q = "INSERT INTO ".$sql_info["tab"]." (`".implode("`,`", $sql_info["cols"]["%"])."`) VALUES (".implode(",", array_fill(0, count($sql_info["vals"]["%"]), '?')).");";
					$yield_obj_binds = $sql_info["vals"]["%"];
					
					$yield_obj = (object)['type' => 'insert', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
					yield($yield_obj);
					
					if (!($res = $yield_obj->result))
						throw new \Exception('Insert failed');
					
					$model->setId($res);

					if ($mergeBy_duplicate_objs->contains($model))
					{
						foreach ($mergeBy_duplicate_objs[$model] as $mergeby_duplicate)
						{
							if (($mergeby_duplicate !== $model) && ($mergeby_duplicate->getId() === null))
								$mergeby_duplicate->setId($res);
						}
					}

					if ($meta)
					{
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
			// update, select, insert, add backreference as sql,replacements
			# $model->_ts = null; # do not use this any more because we reset it at the end
		}
		else if ($selector !== false)
		{
			if ($is_collection)
			{
				$find_by_uniqueness = false; // @todo - check find by uniqunes (!$one_to_many) && $property->getCollectionType()->hasAnyInstantiableReferenceTypes();
				
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
											"WHERE `".$sql_info["id_col"]."`=?".
												" AND `{$sql_info["cols"]["bkref"]}`=?";
								$yield_obj_binds = [$rowid, $parent_model->getId()];
							}
							else
							{
								/*$q_str = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
											" WHERE `{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
													"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
								
								$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET `{$sql_info["cols"]["bkref"]}`=NULL ".
											" WHERE `{$sql_info["cols"]["ref"]}`=? AND ".
													"`{$sql_info["cols"]["bkref"]}`=?";
								$yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
							}
							
							$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
							yield($yield_obj);
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
											" WHERE `".$sql_info["id_col"]."`=?".
												" AND `{$sql_info["cols"]["bkref"]}`=?";
								$yield_obj_binds = [$rowid, $parent_model->getId()];
							}
							else
							{
								/*$q_str = "DELETE FROM ".$sql_info["tab"].
											" WHERE `{$sql_info["cols"]["ref"]}`=".$this->escapeScalar($uniqueness[0], $connection)." AND ".
													"`{$sql_info["cols"]["bkref"]}`=".$this->escapeScalar($uniqueness[1], $connection);*/
													
								$yield_obj_q = "DELETE FROM ".$sql_info["tab"].
											" WHERE `{$sql_info["cols"]["ref"]}`=? AND ".
													"`{$sql_info["cols"]["bkref"]}`=?";
								$yield_obj_binds = [$uniqueness[0], $uniqueness[1]];
							}
							
							$yield_obj = (object)['type' => 'delete', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
							yield($yield_obj);
						}
						
						$model->_tsx[$item_k] = QModel::TransformDelete;
					}
				}
			}
			else
			{
				$do_delete = $action & QModel::TransformDelete;
				
				if ($do_delete)
				{
					// $q_str = "UPDATE {$sql_info["tab"]} SET {$sql_info["tab"]}.`Del__`=1 WHERE `{$sql_info["id_col"]}`=".$this->escapeScalar($model->getId(), $connection);
					
					if (!$model->getId())
						throw new \Exception('Missing Id');
					
					$yield_obj_q = "UPDATE {$sql_info["tab"]} SET {$sql_info["tab"]}.`Del__`=1 WHERE `{$sql_info["id_col"]}`=?";
					// $yield_obj_q = "DELETE FROM {$sql_info["tab"]} WHERE `{$sql_info["id_col"]}`=?";
					$yield_obj_binds = [$model->getId()];

					$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
					yield($yield_obj);
					
					if (($res = $yield_obj->result))
					{
						$model->_tsx = QModel::TransformDelete;
						
						// we also need to cleanup all the references to this object
						/* $cleanup_queries = static::GetCleanupQueries($this->getStorage(), $sql_info["tab"], $class_name, $model->getId());
						foreach ($cleanup_queries ?: [] as $cq)
						{
							$cq_res = $mysqli->query($cq);
							qvar_dump($cq, $cq_res);
						}*/
					
						# DO NOT APPLY ONE TO ONE ON DELETE FOR SOFT DELETE !!!
						/*
						foreach ($meta ?: [] as $p_name => $m_pdata)
						{
							$value = $model->$p_name;
							if ($value === null)
							{
								$model_cls = get_class($model);
								$model_clone = new $model_cls();
								$model_clone->setId($model->getId());
								$model_clone->populate($p_name);
								$value = $model_clone->{$p_name};
							}

							if (($value instanceof \QIModel) && ($one_to_one = $m_pdata['oneToOne']))
							{
								list($one_to_one_name, $one_to_one_types) = $one_to_one;
								$value->{"set{$one_to_one_name}"}($model);

								$one_to_one_sql_info = $this->getSqlInfo($sql_cache, $backrefs_list, $connection, false, $value, false, false, null, [$one_to_one_name => []]);
								
								$_vty = \QModelQuery::GetTypesCache(get_class($value));
								
								$one_to_one_table = $_vty["#%table"];

								$upd_pairs = [];
								$yield_obj_binds = [];
								
								if (($c = $one_to_one_sql_info["cols"][$one_to_one_name]["ref"]) !== null)
								{
									$upd_pairs[] = "`{$c}`=?";
									$yield_obj_binds[] = $one_to_one_sql_info["vals"][$one_to_one_name]["ref"];
								}
								if (($c = $one_to_one_sql_info["cols"][$one_to_one_name]["type"]) !== null)
								{
									$upd_pairs[] = "`{$c}`=?";
									$yield_obj_binds[] = $one_to_one_sql_info["vals"][$one_to_one_name]["type"];
								}
								
								$yield_obj_q = "UPDATE `{$one_to_one_table}` SET ".implode(", ", $upd_pairs).
												" WHERE `{$one_to_one_sql_info["id_col"]}`=?";
												
								$yield_obj_binds[] = $value->getId();

								$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
								yield($yield_obj);
							}
						}
						*/
					}
				}
				
				#if ($do_delete)
				#	$model->_ts = null;
			}
			
			# $model->_ts = null;
		}
	}
	
	protected function getEscapedVals($vals, $connection)
	{
		if ($vals === null)
			return null;
		$ret = array();
		foreach ($vals as $k => $v)
			$ret[$k] = $this->escapeScalar($v, $connection);
		return $ret;
	}
	
	protected function getSqlInfo(&$cache, &$backrefs_list, $connection, $prepare_values, QIModel $model, $is_collection = false, $one_to_many = false, QModelProperty $property = null, $selector = null)
	{
		$selector_isarr = is_array($selector);
		
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();

		if ($is_collection)
		{
			$full_tname = $property->getCollectionTableName();
			$cols_type_inf_tab = $cols_type_inf[$full_tname];

			// this is quite standard and can be indexed
			$cache_key = $property->parent->class.".".$property->name;
			if (!($ret_cache = $cache[$cache_key]))
			{
				$cols = array();
				$rowid_col = $property->getCollectionTableRowId();
				$cols["bkref"] = $property->getCollectionBackrefColumn();
				if ($one_to_many)
				{
					// we only need the backref column
					return $cache[$cache_key] = array("sql" => null, "cols" => $cols, "tab" => qEscTable($this->name), "id_col" => $rowid_col);
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

					return $cache[$cache_key] = array("sql" => null, "cols" => $cols, "tab" => qEscTable($this->name), "id_col" => $rowid_col);
				}
			}
			return $ret_cache;
		}
		else
		{
			// get only those that pend an update
			$m_type = $model->getModelType();
			$props = $m_type->properties;
			$cols = array();
			
			$full_tname = $m_type->getTableName();
			$cols_type_inf_tab = $cols_type_inf[$full_tname];
			
			// some things can be cached
			$cache_key = $m_type->class;
			$rowid_col = $m_type->getRowIdColumnName();
			
			$vals = $prepare_values ? array() : null;
			
			$objects = $prepare_values ? array() : null;
			
			$meta = [];
			
			$cols["%"] = array();
			
			// $table_types =  QSqlModelInfoType::GetTableTypes($m_type->getTableName());
			if ($cols_type_inf_tab && ($ci = $cols_type_inf_tab[$rowid_col]) && is_string($ci))
			{
				$cols["%"][] = $cols["_type"]["val"] = $ci;
				$vals["%"][] = $vals["_type"]["val"] = $this->extractTypeIdForVariable($model);
			}
			
			$sql_info = array("sql" => null, "tab" => qEscTable($this->name), "id_col" => $rowid_col);
			
			$model_id = $model->getId();
			if ((!$model_id) && (($model_id = $model->Id) || ($model_id = $model->_id)))
			{
				$model->setId($model_id, 1);
				$model_id = $model->getId();
			}
			
			foreach ($props as $prop)
			{
				if ($prop->storage["none"])
					continue;

				$p_name = $prop->name;

				if (($one_to_one = trim($prop->storage['oneToOne'])))
					$meta[$p_name]['oneToOne'] = [$one_to_one, $prop->getAllInstantiableReferenceTypes()];

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
				
				$model->{"set{$p_name}"}($model->$p_name, 1);
				
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
							$cols[$p_name]["val"] = $prop_cname.$cn_sufix;
						}
						else
							$cols[$p_name]["val"] = $prop_cname;
					}
					if ($prop->hasReferenceType())
					{
						// put it in the backref list
						$cols[$p_name]["ref"] = $prop->getRefColumnName();
					}
					if ($cols_type_inf_tab && ($ci = $cols_type_inf_tab[$prop_cname]) && is_string($ci))
						$cols[$p_name]["type"] = $ci;
					
					$cached_prop = $cache[$cache_key][$p_name] = $cols[$p_name];
				}
				
				if ($prepare_values)
				{
					$value = $model->$p_name;
					if ($value instanceof QIModel)
					{
						$objects[] = array($value, $prop);
						
						if (!($value instanceof QIModelArray))
						{
							if ($cached_prop["type"])
							{
								$vals["%"][] = $vals[$p_name]["type"] = $this->extractTypeIdForVariable($value);
								$cols["%"][] = $cached_prop["type"];
							}
							if ($cached_prop["ref"])
							{
								$vals["%"][] = $vals[$p_name]["ref"] = $value->getId();
								$cols["%"][] = $cached_prop["ref"];
							}

							// TO DO : when we setup this one here, we don't yet know if it was changed and a lot of times it is not needed
							// var_dump($p_name);
							
							// var_dump("SQLINF: We set back ref for ".get_class($value)." via table ".$sql_info["tab"]." / ".$cols[$p_name]["ref"]." on model :" .  get_class($model));
							// var_dump("PROP: ".$sql_info["tab"]." / ".$sql_info["id_col"]." / ".$cols[$p_name]["ref"]." / ".$value->Name);
							
							$this->setBackReference($backrefs_list, $sql_info, $cols[$p_name]["ref"], $value, $model);
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
			}
			
			return array("sql" => null, "cols" => $cols, "vals" => $vals, "tab" => qEscTable($this->name), "id_col" => $rowid_col, "objs" => $objects, 'meta' =>  $meta);
		}
	}
	
	protected function setBackReference(&$backrefs_list, $sql_info, $update_column, QIModel $reference, $row_identifier, $model_array = null, $model_array_key = null)
	{
		// ensure tmpid
		$id = ($row_identifier instanceof QIModel) ? "#tmp/".($row_identifier->_tmpid ?: ($row_identifier->_tmpid = QModel::GetNextId()))."/#" : $row_identifier;
		$key = $sql_info["tab"].":".$id;
		
		if ($model_array && ($model_array_key !== null))
			$backrefs_list[$key][] = array($sql_info, $update_column, $reference, $row_identifier, $model_array, $model_array_key);
		else
			$backrefs_list[$key][] = array($sql_info, $update_column, $reference, $row_identifier);
	}
	
	protected function getCleanupQueryForTypeChange(QModelType $old_model, QModelType $new_model, $rowid, $connection)
	{
		if ($old_model->getTableName() === $new_model->getTableName())
		{
			$upd_pairs = array();
			
			foreach ($old_model->properties as $p_name => $prop)
			{
				if (strtolower($p_name) === "id")
					continue;
				if ($prop->storage["none"])
					continue;

				if (!$new_model->properties[$p_name])
				{
					if ($prop->hasScalarType())
						$upd_pairs[] = "`{$prop->getColumnName()}`=".$this->escapeScalar(null, $connection);
					if ($prop->hasReferenceType())
					{
						// put it in the backref list
						$upd_pairs[] = "`{$prop->getRefColumnName()}`=".$this->escapeScalar(null, $connection);
					}
					if ($prop->isMultiType())
						$upd_pairs[] = "`{$prop->getTypeColumnName()}`=".$this->escapeScalar(null, $connection);
				}
			}

			if (!empty($upd_pairs))
				return ["UPDATE ".qEscTable($old_model->getTableName())." SET ".implode(",", $upd_pairs)." WHERE `".$old_model->getRowIdColumnName()."`=?" , [$rowid], 'update'];
			else
				return false;
		}
		else
		{
			return ["DELETE FROM ".qEscTable($old_model->getTableName())." WHERE `id`=?", [$rowid], 'delete'];
		}
	}

	protected function resolveBackrefs(array $backrefs_list)
	{
		$storage = $this->getStorage();
		$mysqli = $storage->connection;
		$cols_type_inf = QSqlModelInfoType::GetColumnsTypeInfo();
			
		// $tx = microtime(true);
		foreach ($backrefs_list as $data)
		{
			list($sql_info, $update_column, $reference, $row_identifier, $model_array, $model_array_key) = reset($data);

			$cols_ty_tab = $cols_type_inf[str_replace("`", "",$sql_info["tab"])];

			$parts = array();
			$yield_obj_binds = [];
			
			foreach ($data as $info)
			{
				list(/* ignore sql info */, $update_column, $reference) = $info;
				if ($update_column === null)
				{
					continue;
				}
				$parts[] = "`{$update_column}`=?";
				$yield_obj_binds[] = $reference->getId();

					// var_dump($zzz_sql_info["tab"]." :: ". ($my_row_identifier instanceof QIModel ? $my_row_identifier->getId() : $my_row_identifier));

				$cols_ty_col = $cols_ty_tab ? ($cols_ty_tab[$update_column] ?: $cols_ty_tab[substr($update_column, strlen(QORM_TYCOLPREFIX))]) : null;

				// var_dump($my_sql_info["tab"], $my_sql_info["id_col"], $update_column, $reference->getId(), get_class($reference));

				if (is_string($cols_ty_col) && ($reference instanceof QIModel))
				{
					$bkref_type = $storage->getTypeIdInStorage(get_class($reference));
					$parts[] = "`{$cols_ty_col}`=?";
					$yield_obj_binds[] = $bkref_type;
					// var_dump($cols_ty_col, $bkref_type);
				}
			}

			// for many to many collections we may need to setup the type, check before we do this update

			$row_id = (($row_identifier instanceof QIModel) ? $row_identifier->getId() : $row_identifier);
			
			if ($parts)
			{
				// $query = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $parts)." WHERE `{$sql_info["id_col"]}`=".$row_id;
				
				$yield_obj_q = "UPDATE ".$sql_info["tab"]." SET ".implode(",", $parts)." WHERE `{$sql_info["id_col"]}`=?";
				$yield_obj_binds[] = $row_id;

				$yield_obj = (object)['type' => 'update', 'params' => ['q' => $yield_obj_q, 'binds' => $yield_obj_binds]];
				yield($yield_obj);
			}
			else
			{
				throw new Exception("Empty parts");
			}

			if ($model_array && ($model_array_key !== null))
				$model_array->setRowIdAtIndex($model_array_key, $row_id);
		}
	}
	
	protected function recurseObjects(\QIModel $model, \QIModel $parent = null, \QModelProperty $property = null, $selector = null, 
										array &$all_objects = null, array &$all_objects_groped = null, array &$merge_by_models = null, 
										string $prop_path = "", string $parent_prop_merge_by = null, array &$populate = null, 
										\QIModel $property_parent = null, array &$pure_populate = null, array &$populate_before_merge_by = null,
										array $parent_mergeBy_entitity = null)
	{
		if ($model->_lk === 1)
			return;
		$model->_lk = 1;
		
		$selector_isarr = is_array($selector);
		if (!(($selector === true) || $selector_isarr))
			return;
		
		$all_objects[] = $model;
		$all_objects_groped[$prop_path ?: 0][] = [$model, $parent, $property, $selector];
		$is_collection = ($model instanceof QIModelArray);
		
		if ($is_collection)
		{
			// this means all existing items NOT IN the $model will be removed from the collection
			$replace_elements = ($model->_ts & QModel::TransformDelete) && (($model->_ts & QModel::TransformCreate) || ($model->_ts & QModel::TransformUpdate))
									&& $parent;
			if ($replace_elements)
				$pure_populate[get_class($parent)][$property->name][] = $parent;
			
			foreach ($model as $key => $item)
			{
				if ($replace_elements)
				{
					// make sure we have a transform state, elements without one will be removed !!!
					if (($model->_tsp[$key] === null) && ((!($item instanceof QIModel)) || ($item->_ts === null)))
						$model->_tsp[$key] = $model->_ts & (~\QIModel::TransformDelete);
				}
				
				if ($item instanceof QIModel)
				{
					$containers = $item->getContainers(QModel::StorageContainerForWrite);
					if (qis_array($containers))
					{
						foreach ($containers as $container)
							$container->recurseObjects($item, $model, $property, $selector, $all_objects, $all_objects_groped, $merge_by_models, $prop_path."[]", $parent_prop_merge_by, $populate, $property_parent, $pure_populate, $populate_before_merge_by, $parent_mergeBy_entitity);
					}
					else
						$containers->recurseObjects($item, $model, $property, $selector, $all_objects, $all_objects_groped, $merge_by_models, $prop_path."[]", $parent_prop_merge_by, $populate, $property_parent, $pure_populate, $populate_before_merge_by, $parent_mergeBy_entitity);
				}
			}
		}
		else
		{
			$type_mergeBy_entity = null;
			// prepare merge by
			$class_name = get_class($model);
			
			// we only do it for global merge by
			if (($model->getId() === null) && (!$parent_prop_merge_by) && ($type_mergeBy = static::GetMergeByInfo($class_name)))
			{
				# @TODO - $type_mergeBy + "shallow delete" ==> NOT GOOD !!! the deleted object will remain in the table , only 'de-linked'
				
				// global merge by
				if ($property && ($app_property = $property->getAppPropertyFor($class_name)))
					$class_property = $app_property;
				else
					$class_property = \QModel::GetDefaultAppPropertyForTypeValues($class_name);

				if (!$class_property)
					throw new \Exception("We have a mergeBy on {$class_name} but we can not find it's app property");
				
				// $type_mergeBy_entity = static::GetMergeByInfo_Parsed($type_mergeBy, get_class($parent));
				static::PrepareModelsForMergeBy($populate_before_merge_by, $merge_by_models, [$model], $type_mergeBy, \QApp::GetDataClass(), $class_property);
			}
			
			$props = $model->getModelType()->properties;
			$loop_by_selector = ($selector_isarr && (count($selector_isarr) < count($props)));
			$loop_by = $loop_by_selector ? $selector : $props;
			
			if ($loop_by && $props)
			{
				foreach ($loop_by as $loop_k => $loop_v)
				{
					$prop = $loop_by_selector ? $props[$loop_k] : $loop_v;
					$p_name = $loop_k;
					// if we have a selector and not selected, skip
					if ((!$prop) || ($prop->storage["none"]) || ($selector_isarr && ($selector[$p_name] === null) && ($selector["*"] === null)))
						continue;
					
					$item = $model->{$p_name};
					if (($item instanceof QIModel) && ($item->_lk !== 1))
					{
						if ($item instanceof QIModelArray)
							$item->setModelProperty($prop);
						
						$deprecated_populateBeforeInsert = false;
						
						if (($prop_mby = $prop->storage['mergeBy']))
						{
							if (($prop_mby == '1') || ($prop_mby === true))
							{
								// deprecated 
								$prop_mby = null;
								$deprecated_populateBeforeInsert = true;
							}
							else
							{
								// mergeBy from properties will overwrite the mergeBy defined on the class level
								// $prop_merge_by_models[$class_name ?: ($class_name = get_class($model))][$p_name][] = $model;
								static::$_MergeByInfoProps[$class_name][$p_name] = $prop_mby;
								
								// contextual merge by
								if ($item instanceof \QIModelArray)
									static::PrepareModelsForMergeBy($populate_before_merge_by, $merge_by_models, $item, $prop_mby, $class_name, $prop->name, $model);
								else
									static::PrepareModelsForMergeBy($populate_before_merge_by, $merge_by_models, [$item], $prop_mby, $class_name, $prop->name, $model);
							}
						}
						if (($populateBeforeInsert = $prop->storage['populateBeforeInsert'] ?: $deprecated_populateBeforeInsert))
						{
							// this directive will force the model (if it has an id after mergeBy is resolved) to load the data and populate on it
							if ($item->getId() === null)
							{
								static::$_PopulateInfoProps[$class_name][$p_name] = $populateBeforeInsert;
								$populate[$class_name][] = $model;
							}
						}
						
						$containers = $item->getContainers(QModel::StorageContainerForWrite);
						if (qis_array($containers))
						{
							foreach ($containers as $container)
								$container->recurseObjects($item, $model, $prop, $selector_isarr ? ((($si = $selector[$p_name]) !== null) ? $si : $selector["*"]) : $selector, $all_objects, $all_objects_groped, $merge_by_models, $prop_path ? $prop_path.".".$p_name : $p_name, $prop_mby, $populate, $model, $pure_populate, $populate_before_merge_by, $type_mergeBy_entity ? $type_mergeBy_entity[$p_name] : null);
						}
						else
							$containers->recurseObjects($item, $model, $prop, $selector_isarr ? ((($si = $selector[$p_name]) !== null) ? $si : $selector["*"]) : $selector, $all_objects, $all_objects_groped, $merge_by_models, $prop_path ? $prop_path.".".$p_name : $p_name, $prop_mby, $populate, $model, $pure_populate, $populate_before_merge_by, $type_mergeBy_entity ? $type_mergeBy_entity[$p_name] : null);
					}
				}
			}
		}
	}
	
	public static function GetMergeByInfo(string $class_name)
	{
		if (($mby_inf = static::$_MergeByInfo[$class_name]) !== null)
			return $mby_inf;
		else if (($type_inf = \QModel::GetTypesCache($class_name)) && ($type_mergeBy = $type_inf["#%misc"]["mergeBy"]))
		{
			static::$_MergeByInfo[$class_name] = $type_mergeBy;
			return $type_mergeBy;
		}
		else
		{
			static::$_MergeByInfo[$class_name] = false;
			return false;
		}
	}

	protected function recurseTransactionList($connection, array $model_list, $ts = null, $selector = null)
	{
		$t1 = microtime(true);
		
		$max_row = static::$Max_Allowed_Packet ?: (static::$Max_Allowed_Packet = $connection->query("SHOW VARIABLES LIKE 'max_allowed_packet';")->fetch_assoc());
		$max_query_len = $max_row["Value"] ?: next($max_row) ?: $max_query_len;
		
		$all_objects = [];
		$all_objects_grouped = [];
		$merge_by_models = [];
		$prop_merge_by_models = [];
		$sql_cache = [];
		$one_to_one_ops = [];
		
		$app_bindings = [];
		
		$backrefs_list = [];
		
		$pp_statements = [];
		
		$mergeBy_duplicate_objs = new \SplObjectStorage();
		$objects_bag = new \SplObjectStorage();
		$reverse_objects_bag = [];
		$populate = [];
		$populate_before_merge_by = [];
		// select in bulk, update/delete in bulk
		// pp statements
		// Here we 'walk' the model and prepare data for the next steps
		$pure_populate = [];
		foreach ($model_list as $model)
			$this->recurseObjects($model, null, null, $selector, $all_objects, $all_objects_grouped, $merge_by_models, "", null, $populate, null, $pure_populate, $populate_before_merge_by);
		
		if ($populate_before_merge_by)
		{
			foreach ($populate_before_merge_by as $impl_ent => $elements)
			{
				$qma_list = new \QModelArray($elements);
				$qma_list->populate($impl_ent);
			}
		}
		$t_mb = microtime(true);
		
		// class_name, ?property_name, [] => object
		// $merge_by_models[get_class($parent)][$property->name][] = [$model, $type_mergeBy, $parent, $property->name];
		// here we resolve mergeBy directives
		foreach ($merge_by_models as $p_class_name => $objects_blocks)
		{
			foreach ($objects_blocks as $p_property => $objects)
			{
				list(/*$model*/, $type_mergeBy /*, $parent, $property_name */) = reset($objects);
				
				$this->findIdFromMergeBy($p_class_name, $objects, $type_mergeBy, $p_property, $max_query_len, $mergeBy_duplicate_objs, $objects_bag, $reverse_objects_bag);

			}
		}
		
		if ($pure_populate)
		{
			// $pure_populate[get_class($parent)][$property->name][] = $parent;
			foreach ($pure_populate as $class_name => $pp_data)
			{
				foreach ($pp_data as $populate_properties => $models)
				{
					$q_array = new QModelArray($models);
					if (!$q_array->count())
						continue;
					$q_array->populate($populate_properties);
				}
			}
		}
		
		if ($populate)
		{
			// if we have populate directives we resolve them
			foreach ($populate as $class_name => $models)
			{
				$popinfo_class = static::$_PopulateInfoProps[$class_name];
				$props_list = array_keys($popinfo_class);
				$q_array = new QModelArray();
				
				$m_by_index = [];
				// we should be carefull to only apply it to elements with IDs
				foreach ($models as $m)
				{
					$m_id = $m->getId();
					if ($m_id && (!$q_array[$m_id]))
					{
						$q_array[$m_id] = $m_clone = new $m();
						$m_clone->setId($m_id);
						$m_by_index[$m_id] = $m;
					}
				}
				if ($q_array->count() > 0)
				{
					$q_array->populate(implode(", ", $props_list));
					
					foreach ($q_array as $m_id => $clone)
					{
						$p_model = $m_by_index[$m_id];
						foreach ($props_list as $property)
						{
							$this->populateBeforeInsert($clone, $p_model, $property, $popinfo_class[$property]);
						}
					}
				}
			}
		}
		
		$yield_generators = [];
		$step_index = 0;
		
		/*
		 * This is the complicated bit. 
		 * We use the yield directive to interrupt $this->recurseTransaction()
		 * and resolve queries in bulk 
		 */
		do
		{
			$yield_requests = [];
			
			if (($step_index > 0) && (!$yield_generators))
				break;
			
			foreach ($all_objects_grouped as $grp => $models_array)
			{
				if (($step_index > 0) && (!$yield_generators[$grp]))
					continue;
				
				foreach ($models_array as $m_index => $model_stack)
				{
					if (($step_index > 0) && (!$yield_generators[$grp][$m_index]))
						continue;
					
					list ($model, $parent_model, $property, $m_selector) = $model_stack;
					$containers = $model->getContainers(QModel::StorageContainerForWrite);
					if (!qis_array($containers))
						$containers = [$containers];
					
					foreach ($containers as $container_index => $container)
					{
						if ($step_index === 0)
						{
							// init
							$yield_gen = $yield_generators[$grp][$m_index][$container_index] = 
								$container->recurseTransaction($sql_cache, $backrefs_list, $connection, $model, $property, $parent_model, $ts, $m_selector, $mergeBy_duplicate_objs, $one_to_one_ops, $app_bindings, $connection);
						}
						else
						{
							// continue
							$yield_gen = $yield_generators[$grp][$m_index][$container_index];
							$yield_gen->next(); // execute the next step
						}
						
						$yield_request = $yield_gen->current();
						
						if ($yield_request)
							$yield_requests[] = $yield_request;
						else
						{
							unset($yield_generators[$grp][$m_index][$container_index]);
							if (empty($yield_generators[$grp][$m_index]))
							{
								unset($yield_generators[$grp][$m_index]);
								if (empty($yield_generators[$grp]))
									unset($yield_generators[$grp]);
							}
						}
					}
				}
			}
			
			if ($yield_requests)
			{
				$select_ops = [];
				// resolve here all requests
				foreach ($yield_requests ?: [] as $yield_request)
				{
					$pp_params = $yield_request->params;
					
					if ($yield_request->type === 'select')
					{
						// we resolve select requests later, see $select_ops
						$pp_key = sha1($pp_params['q'].implode(",", $pp_params["c"]));
						$select_ops[$pp_key][] = $yield_request;
					}
					else if ($yield_request->type === 'no-op')
					{
						// nothing to do, we just return true
						$yield_request->result = true;
					}
					else
					{
						$pp_key = sha1($pp_params['q']);
						// update, delete, insert
						$stmt = $pp_statements[$pp_key] ?: ($pp_statements[$pp_key] = $connection->prepare($pp_params['q']));
						if (!$stmt)
							throw new \Exception('Query error: '.$pp_params['q']."\n".$connection->error);
						$yield_request->result = $this->mysqliExecWithParams($stmt, $pp_params['binds'], $pp_params['q'], $yield_request->type, $connection);
					}
				}
				
				// resolve select requests
				foreach ($select_ops as $select_o)
				{
					$select_return_index = [];
				
					$query = reset($select_o)->params["q"];
					$is_first = true;
					$multiple = count($select_o) > 1;
					
					$common_cols = [];
					
					// test to use IN instead of OR if possible
					$has_one_identic_col = true;
					$has_one_identic_col_val = null;
					foreach ($select_o as $yield_request)
					{
						if (count($yield_request->params["c"]) !== 1)
						{
							$has_one_identic_col = false;
							break;
						}
						
						$first_yr_col = (string)reset($yield_request->params["c"]);
						if ($has_one_identic_col_val === null)
							$has_one_identic_col_val = $first_yr_col;
						else if ($has_one_identic_col_val !== $first_yr_col)
						{
							$has_one_identic_col = false;
							break;
						}
					}
					
					if ($has_one_identic_col)
						$query .= "{$has_one_identic_col_val} IN (";
					
					foreach ($select_o as $yield_request)
					{
						$sub_q = "";
						
						if ((!$is_first) && (!$has_one_identic_col))
							$sub_q .= " OR ";
						
						$binds = $yield_request->params["binds"];
						$cols = $yield_request->params["c"];
						if (!$common_cols)
							$common_cols = $cols;
						else if ($common_cols !== $cols)
							throw new \Exception("We've made a mistake when grouping the select queries.");
						
						$fc = true;
						if ($multiple && (!$has_one_identic_col))
							$sub_q .= "(";
						
						$binds_index = [];
						
						foreach ($cols as $i => $c)
						{
							if (!$has_one_identic_col)
							{
								if (!$fc)
									$sub_q .= " AND ";
								$sub_q .= "{$c}=".$this->escapeScalar($binds[$i], $connection);
							}
							$binds_index[] = ($binds[$i] === null) ? null : (string)$binds[$i];
							$fc = false;
						}
						if ($multiple && (!$has_one_identic_col))
							$sub_q .= ")";
						
						$sri_key = json_encode($binds_index);
						if (!$select_return_index[$sri_key])
						{
							$query .= $has_one_identic_col ? ($is_first ? "" : ",").$this->escapeScalar(reset($binds), $connection) : $sub_q;
							$is_first = false;
						}
						$select_return_index[$sri_key][] = $yield_request;
					}
					
					if ($has_one_identic_col)
						$query .= ") ";
					
					// cleanup column names to avoid escape chars
					foreach ($common_cols as $k => $v)
					{
						if (($v{0} === '`') || ($v{0} === '\'') || ($v{0} === "\""))
							$common_cols[$k] = substr($v, 1, -1);
					}
					
					$result = $connection->query($query);
					if (!$result)
					{
						throw new \Exception('Query error: '.$query."\n".$connection->error);
					}
					else
					{
						while (($record = $result->fetch_assoc()))
						{
							$r_binds = [];
							foreach ($common_cols as $c)
							{
								$rv = $record[$c];
								if (($rv === null) && (!array_key_exists($c, $record)))
									throw new \Exception('We did not add one or more columns needed to identify the return');
								$r_binds[] = $rv;
							}
							
							$yield_reqsts = $select_return_index[json_encode($r_binds)];
							if (!$yield_reqsts)
								throw new \Exception('Error we could not id the request. Review the way you index data in $select_return_index.');
							
							foreach ($yield_reqsts as $yield_req)
								$yield_req->result = $record;
						}
						
						$result->free();
					}
				}
				
			}
			
			// do the next step
			$step_index++;
		}
		while ($yield_requests);
		
		unset($yield_generators);
		
		$resolv_updates = [];
		foreach ($this->resolveBackrefs($backrefs_list) as $upd)
			$resolv_updates[] = $upd;
		
		// resolve here all requests for Backrefs
		foreach ($resolv_updates ?: [] as $yield_request)
		{
			$pp_params = $yield_request->params;
			$pp_key = sha1($pp_params['q']);

			// update, delete, insert
			$stmt = $pp_statements[$pp_key] ?: ($pp_statements[$pp_key] = $connection->prepare($pp_params['q']));
			if (!$stmt)
				throw new \Exception('Query error: '.$pp_params['q']."\n".$connection->error);
			$yield_request->result = $this->mysqliExecWithParams($stmt, $pp_params['binds'], $pp_params['q'], $yield_request->type, $connection);
		}
		
		// $one_to_one_ops
		$this->resolveOneToOneOps($one_to_one_ops, $cache, $backrefs_list, $connection);
		
		if ($app_bindings)
		{
			$app_model = \QApp::NewData();
			$app_refl = $app_model->getModelType();
			
			foreach ($app_bindings as $property_name => $objects)
			{
				$is_collection = $app_refl->properties[$property_name]->hasCollectionType();
				if ($is_collection)
					$app_model->{"set{$property_name}"}(new \QModelArray());
				foreach ($objects as $o)
				{
					if (!($o_id = $o->getId()))
						continue;
					$clone_class = get_class($o);
					$clone = new $clone_class;
					$clone->setId($o_id);
					if ($is_collection)
						$app_model->{"set{$property_name}_Item_"}($clone);
					else
					{
						$app_model->{"set{$property_name}"}($clone);
						break;
					}
				}
			}
			$save_properties = array_keys($app_bindings);
			$app_model->merge(implode(",", $save_properties));
		}
		
		// free statements
		foreach ($pp_statements as $pp_s)
			$pp_s->close();
		
		// free locks
		foreach ($all_objects as $obj)
			$obj->_lk = null;
	}
	
	protected function mysqliExecWithParams(\mysqli_stmt $stmt, array $binds, string $query = null, string $yield_type = null, $connection = null)
	{
		//  mysqli_stmt $stmt , string $types , mixed &$var1 [, mixed &$... 
		$types = '';
		$args = [$stmt, &$types];
		foreach ($binds as $k => $b)
		{
			if (is_string($b))
			{
				$types .= 's';
				$args[] = &$binds[$k];
			}
			else if (is_int($b))
			{
				$types .= 'i';
				$args[] = &$binds[$k];
			}
			else if (is_null($b))
			{
				$types .= 'i';
				$args[] = &$binds[$k];
			}
			else if (is_float($b))
			{
				$types .= 'd';
				$args[] = &$binds[$k];
			}
			else if (is_bool($b))
			{
				$types .= 'i';
				$$k = $b ? 1 : 0;
				$args[] = &$$k;
			}
			else if (is_array($b) || is_object($b))
			{
				$types .= 's';
				$$k = json_encode($b);
				$args[] = &$$k;
			}
			else
			{
				qvar_dump($b);
				throw new \Exception('not implemented');
			}
		}
		
		// WARN !!! bind_param is BY REFERENCE !!!!
		
		$bind_res = call_user_func_array('mysqli_stmt_bind_param', $args);
		if (!$bind_res)
			throw new \Exception('Failed to bind: '.$query."\n".$stmt->error);
		
		$ret = $stmt->execute();
		if (!$ret)
			throw new \Exception('Execute query has failed: '.$query."\n".$stmt->error);
		
		if ($yield_type === 'insert')
		{
			return $connection->insert_id;
		}
		else
			return $ret;
	}
	
	// $p_class_name, $objects, $type_mergeBy, $p_property, $max_query_len, $mergeBy_duplicate_objs
	public function findIdFromMergeBy(string $p_class_name, array $objects, string $merge_by_rule = null, string $property = null, $max_query_len = 32768, 
				\SplObjectStorage $mergeBy_duplicate_objs = null, 
				\SplObjectStorage $objects_bag = null, array &$reverse_objects_bag = null)
	{
		$ids_setup = 0;
		
		// $map_mergeby = [];
		$queries = [];
		$binds = [];
		$binds_len = 0;
		
		$mergeBy_keys = [];
		$data_map = [];
		// $bind_sizes = [];
		// $merge_by_selector = [];
		
		$type_mergeBy = $merge_by_rule;
		if (!$type_mergeBy)
			throw new \Exception('Missing mergeBy definition');
		if (!$property)
		{
			qvar_dump($p_class_name, $objects, $merge_by_rule, $property);
			throw new \Exception('Missing property');
		}
		
		// we need to prepare the merge by parts
		$_mergeBy_parts = explode(",", $type_mergeBy);
		$mergeBy_parts = [];
		foreach ($_mergeBy_parts as $mbk => $_mby)
		{
			if (!empty($mby = trim($_mby)))
				$mergeBy_parts[] = $mby;
		}
		
		$bind_size = count($mergeBy_parts); // +1 to include Parent.Id
		$extra_selector = $type_mergeBy;
		
		$merge_by_duplicates = [];
		$data_map_dups = [];
		
		$parents_map = new \QModelArray();
		
		$app_parent = \QApp::NewData();
	
		$use_IN = (count($mergeBy_parts) === 1);
		foreach ($objects as $item_data)
		{
			// $merge_by_models[get_class($parent)][$property->name][] = [$model, $type_mergeBy, $parent, $property->name];
			list($item, /*$merge_by_rule*/, $parent/*, $property*/) = $item_data;
			if ($item === null)
				throw new \Exception("Should never happen");
			
			if (!$parent)
				$parent = $app_parent;
			$p_id = $parent->getId();
			$p_clone = null;
			if ($p_id !== null)
			{
				$p_clone = new $parent();
				$p_clone->setId($p_id);
				$parents_map[$p_id] = $p_clone;
			}
			
			$i_key = "";

			$sql_q = $use_IN ? "" : "(";
			$prepend_and = false;
			
			$item_binds = [];
			// if it's just one, use IN (...)
			
			foreach ($mergeBy_parts as $mby)
			{
				if ($use_IN)
					$sql_q .= "?";
				else
					$sql_q .= ($prepend_and ? " AND " : "").trim($mby)."<=>?";
				
				$b_parts = explode(".", $mby);
				$obj = $item;
				foreach ($b_parts as $bp)
					$obj = $obj->{$bp};

				if ($obj === null)
					$i_key .= ",null";
				else if ($obj instanceof \QIModel)
					$i_key .= ",".var_export([$obj->getId(), get_class($obj)], true);
				else if (is_int($obj) || is_float($obj))
					$i_key .= ",".var_export((string)$obj, true);
				else if (is_bool($obj))
					$i_key .= ",".($obj ? "'1'" : "'0'");
				else if (is_scalar($obj))
					$i_key .= ",".var_export($obj, true);
				else 
					throw new \Exception("Unexpected data type");

				if ($p_id !== null)
					$item_binds[] = $obj;
				
				$prepend_and = true;
			}
			
			if (!$use_IN)
				$sql_q .= ")";
			
			if ($p_id !== null)
				$binds_len += strlen($i_key);
					
			$full_key = $this->setObjectKey($objects_bag, $reverse_objects_bag, $item, $i_key, $parent, null, $property);
			$parent_key = $objects_bag[$parent];
			if ($p_clone)
			{
				// ensure the same key for the clone
				$objects_bag[$p_clone] = $parent_key;
			}
			
			// duplicates if they have the same parent and the same key
			// this will get ugly, what if the parent is a duplicate ?
			if ($merge_by_duplicates[$full_key])
			{
				$merge_by_duplicates[$full_key][] = $item;
			}
			else if (($dup_first = $data_map_dups[$parent_key][$i_key]))
			{
				// they key already exists
				// we don't want to add it to the query
				$merge_by_duplicates[$full_key] = [$dup_first, $item];
			}
			else
			{
				$data_map_dups[$parent_key][$i_key] = $item;
			}
			
			// this is not right for now
			if ($p_id !== null)
			{
				if (!$mergeBy_keys[$i_key])
				{
					// define item key
					$queries[] = $sql_q;
					foreach ($item_binds as $i_bind)
						$binds[] = $i_bind;
				}

				$mergeBy_keys[$i_key][] = $item;
				
				$data_map[$p_id][$i_key][] = $item;
			}
		}
		
		// now query
		$q_sqls = $queries;
		$q_binds = $binds;
		
		// how do we determine $query_len ?
		$count_q_sqls = count($q_sqls);
		// $estimated_len = (int)ceil((64 + $count_q_sqls * (strlen(reset($q_sqls)) + 5) + $binds_len) * 1.5);
		$parts_per_query = floor(1024 / $bind_size);
		$blocks = ceil($count_q_sqls/$parts_per_query);
		// $blocks = (int)ceil($estimated_len / $max_query_len);
		// $parts_per_query = (int)floor($count_q_sqls / $blocks);

		$index = 0;

		if (count($parents_map) > 0)
		{
			for ($i = 0; $i < $blocks; $i++)
			{
				$parts = array_slice($q_sqls, $index, $parts_per_query);
				$qpart_binds = array_slice($q_binds, $index * $bind_size, $parts_per_query * $bind_size);
				
				if ($use_IN)
					$sql = $property.".{Id,{$extra_selector} WHERE ".reset($mergeBy_parts)." IN (".implode(",", $parts).")}";
				else
					$sql = $property.".{Id,{$extra_selector} WHERE ".implode(" OR ", $parts)."}";
				try
				{
					$res = $parents_map->query($sql, $qpart_binds);
				}
				catch (\Exception $ex)
				{
					qvar_dump($queries, $property, $sql, $qpart_binds);
					throw $ex;
				}
				
				foreach ($res ?: [] as $parent_item)
				{
					$item_list = $parent_item->$property;
					$p_id = $parent_item->getId();
					// $parent_key = $objects_bag[$parent_item];
					
					if (($item_list instanceof \QIModel) && (!qis_array($item_list)))
						$item_list = [$item_list];
					
					foreach ($item_list ?: [] as $ik => $item)
					{
						if ($item === null)
						{
							qvar_dump($parent_item, $property);
							throw new \Exception("It should not select nulls, pos = {$ik}");
						}
						
						$i_key = "";
						foreach ($mergeBy_parts as $mby)
						{
							$b_parts = explode(".", $mby);
							$obj = $item;
							foreach ($b_parts as $bp)
								$obj = $obj->{$bp};

							if ($obj === null)
								$i_key .= ",null";
							else if ($obj instanceof \QIModel)
								$i_key .= ",".var_export([(int)$obj->getId(), get_class($obj)], true);
							else if (is_int($obj) || is_float($obj))
								$i_key .= ",".var_export((string)$obj, true);
							else if (is_bool($obj))
								$i_key .= ",".($obj ? "'1'" : "'0'");
							else if (is_scalar($obj))
								$i_key .= ",".var_export($obj, true);
							else 
								throw new \Exception("Unexpected data type");
						}

						if (($i_items = $data_map[$p_id][$i_key]))
						{
							foreach ($i_items as $i_item)
							{
								// echo "Setting id ".(get_class($item)).": ".$item->getId()."<br/>\n";
								$i_item->setId($item->getId());
								$ids_setup++;
							}
						}
						else
						{
							// @TODO - atm we allow this as we extract more data them we need !
							// throw new \Exception('Key indexing has failed. Review how you create \$i_key');
						}
					}
				}

				$index += $parts_per_query;	
			}
		}
		
		if ($mergeBy_duplicate_objs)
		{
			// for all the objects that were not processed 
			foreach ($merge_by_duplicates as $key => $duplicates)
			{
				// $mergeByDuplicates
				if (reset($duplicates)->getId() === null) // it was not found in the DB
				{
					foreach ($duplicates as $dup_obj)
						$mergeBy_duplicate_objs[$dup_obj] = $duplicates;
				}
			}
		}
		
		return $ids_setup;
	}
	
	public function setObjectKey(\SplObjectStorage $bag, array &$reverse_bag, \QIModel $object, string $key, \QIModel $parent = null, string $parent_key = null, string $property = null)
	{
		if ($property)
			$key = $property."\x00".$key;
		
		if ($parent_key)
		{
			$key = $parent_key."\x00".$key;
		}
		else if ($parent)
		{
			if (isset($bag[$parent]))
				$p_key = $bag[$parent];
			else if (($p_id = $parent->getId()))
			{
				$p_key = $p_id;
				$bag[$parent] = $p_key;
			}
			else 
			{
				$p_key = static::$_ModelKeyAutoIncrement++;
				$bag[$parent] = $p_key;
			}
			
			$key = $p_key."\x00".$key;
		}
		
		if (isset($bag[$object]))
			return $bag[$object];
		
		$bag[$object] = $key;
		$reverse_bag[$key][] = $object;
		return $key;
	}
	
	function resolveOneToOneOps(array $one_to_one_ops, &$cache, &$backrefs_list, $connection)
	{
		$dummy_types = [];
		$dummy_cont = [];
		$dummy_sql = [];
		$pp_statements = [];
		
		foreach ($one_to_one_ops as $class_name => $list)
		{
			foreach ($list as $p_name => $one_to_one_op_list)
			{
				list(/*$model*/, /*$p_name*/, $o2o_def) = reset($one_to_one_op_list);
				list($one_to_one_name, $one_to_one_types) = $o2o_def;
				
				foreach ($one_to_one_op_list as $one_to_one_op_data)
				{
					list($model) = $one_to_one_op_data;

					$value = $model->$p_name;
					if (($value === null) && ($model->_tsx & \QModel::TransformCreate))
					{
						// if it's null and was created there is nothing to do
						continue;
					}
					
					foreach ($one_to_one_types as $type)
					{
						if (!($obj = $dummy_types[$type]))
						{
							$obj = $dummy_types[$type] = new $type();
							// fake set to get column info !
							$obj->{"set{$one_to_one_name}"}(null);
						}
						if (!($container = $dummy_cont[$type]))
							$container = $dummy_cont[$type] = is_array($containers = $obj->getContainers(QModel::StorageContainerForWrite)) ? reset($containers) : $containers;
						if (!($sql_info = $dummy_sql[$type][$one_to_one_name]))
							$sql_info = $dummy_sql[$type][$one_to_one_name] = $container->getSqlInfo($cache, $backrefs_list, $connection, false, $obj, false, false, null, ["Id" => [], $one_to_one_name => []]);
						
						$query = "";
						$binds = [];
						
						$ref_col = $sql_info['cols'][$one_to_one_name]['ref'];
						$type_col = $sql_info['cols'][$one_to_one_name]['type'];
						
						if ($value === null)
						{
							$query = "UPDATE {$sql_info['tab']} SET `{$ref_col}`=NULL";
							if ($type_col)
								$query .= ", `{$type_col}`=NULL";
							$query .= " WHERE `{$ref_col}`=?";
							$binds = [(int)$model->getId()];
						}
						else
						{
							$query = "UPDATE {$sql_info['tab']} SET `{$ref_col}`=?";
							$binds[] = (int)$model->getId();
							if ($type_col)
							{
								$query .= ", `{$type_col}`=?";
								$binds[] = (int)$this->getStorage()->getTypeIdInStorage($class_name);
							}
							$query .= " WHERE `{$sql_info['id_col']}`=?";
							$binds[] = (int)$value->getId();
						}
						
						$pp_key = sha1($query);
						$stmt = $pp_statements[$pp_key] ?: ($pp_statements[$pp_key] = $connection->prepare($query));
						if (!$stmt)
							throw new \Exception('Query error: '.$query."\n".$connection->error);
						$update_ret = $this->mysqliExecWithParams($stmt, $binds, $query, 'update', $connection);
						if (!$update_ret)
							throw new \Exception('Update has failed');
					}
				}
			}
		}
		
		foreach ($pp_statements as $pp_s)
			$pp_s->close();
	}
	
	function populateBeforeInsert($clone, $p_model, $property, $populate_by, $set_it = true)
	{
		$prop = $clone->$property;
		if ($prop === null)
			return;

		$p_model_prop = $p_model->$property;

		if (($populate_by === true) || ($populate_by == 1))
		{
			if ($p_model_prop !== null)
			{
				if (qis_array($p_model_prop))
					throw new \Exception('Empty merge by is only allowed for references, not collections: '.get_class($p_model).".".$property);
				if ($p_model_prop instanceof \QIModel)
				{
					if (get_class($p_model_prop) !== get_class($prop))
						throw new \Exception("Different data type: ".get_class($p_model_prop)." vs ".get_class($prop));
					$p_model_prop->setId($prop->getId());
				}
			}
			else if ($p_model->wasSet($property))
			{
				// if null was set explicit
			}
			else
			{
				$p_model->{"set{$property}"}($prop);
			}
		}
		
		$populate_by_parts = explode(",", $populate_by);
		$elements = qis_array($prop) ? $prop : [$prop];
		$this_elements = qis_array($p_model->$property) ? $p_model->$property : [$p_model->$property];

		foreach ($elements as $e)
		{
			foreach ($this_elements as $th_e)
			{
				$matches = true;
				foreach ($populate_by_parts as $mby)
				{
					$path_parts = explode('.', $mby);
					$comp_e = $e;
					$this_e = $th_e;
					foreach ($path_parts as $_pp)
					{
						if (($comp_e === null) || ($this_e === null))
							break;
						$pp = trim($_pp);
						$comp_e = $comp_e->$pp;
						$this_e = $this_e->$pp;
					}
					
					if (
							(($comp_e === null) && ($this_e !== null)) ||
							(($comp_e !== null) && ($this_e === null)) ||
							((string)$comp_e !== (string)$this_e)
						)
					{
						$matches = false;
						break;
					}
				}
				
				if ($matches)
				{
					if ($set_it)
						$th_e->setId($e->getId());
					break;
				}
			}
		}
	}
	
	/**
	 * 
	 * @param QMySqlStorage $storage
	 * @param string $table_name
	 * @param string $class_name
	 * @param int|string|array $rowid
	 * 
	 * @return string[]
	 */
	public static function GetCleanupQueries($storage, string $table_name = null, string $class_name = null, $rowid = null)
	{
		if ($table_name === null)
		{
			// do a cleanup on all tables
			$all_tables = \QSqlModelInfoType::GetTableToTypesList();
			if (!$all_tables)
				return;
			$all_tables = array_keys($all_tables);
			
			$all_q = [];
			foreach ($all_tables ?: [] as $tab)
			{
				$all_q += static::GetCleanupQueries($storage, $tab);
			}
			
			return $all_q;
		}
		
		// remove ` if present
		$table_name = trim(str_replace('`', '', $table_name));
		$table_name_esc = '`'.implode('`.`', explode('.', $table_name)).'`';
		
		$type_ids = [];
		if ($rowid)
		{
			// $rowid = static::EscapeScalar_S($rowid, $storage->connection);
			$rowid_in = [];
			if (qis_array($rowid))
			{
				foreach ($rowid as $ri)
					$rowid_in[] = static::EscapeScalar_S($ri, $storage->connection);
			}
			else
				$rowid_in[] = static::EscapeScalar_S($rowid, $storage->connection);
			$rowid_in_str = implode(",", $rowid_in);
		}
		
		$table_types = \QSqlModelInfoType::GetReverseTablesForPropertyTypes($table_name);
		if (!$table_types)
			return [];
		
		if ($class_name)
		{
			// filter for the one with class name
			$count = 0;
			$new = [];
			foreach ($table_types as $c => $d)
			{
				foreach ($d as $p => $ty)
				{
					if ($ty[$class_name])
					{
						$new[$c][$p][$class_name] = $class_name;
						$count++;
					}
				}
			}
			$table_types = $new;
		}
		
		$queries = [];
		
		foreach ($table_types as $c => $d)
		{
			$type_inf = \QModelQuery::GetTypesCache($c);
			if (!$type_inf)
				continue;
			
			foreach ($d as $p => $ty)
			{
				$prop_inf = $type_inf[$p];
				if (!$prop_inf)
					continue;
				
				if ($prop_inf['[]'] && $prop_inf['o2m'])
				{
					// one to many
					// (deprecated - we need to cleanup now) nothing to do on one to many, the record will be removed and the reference lost
					// we need to update the reference to null
					
					$prop_table_esc = '`'.implode('`.`', explode('.', $prop_inf['ct'])).'`';
					$ref_col = is_array($prop_inf['cb']) ? $prop_inf['cb'][0] : $prop_inf['cb'];
					$ref_col_ty = (is_array($prop_inf['cb']) && $prop_inf['cb'][1] && (!is_array($prop_inf['cb'][1]))) ? $prop_inf['cb'][1] : null;
					
					$ty_id = [];
					foreach ($ty as $_ty)
						$ty_id[] = $type_ids[$_ty] ?: ($type_ids[$_ty] = static::EscapeScalar_S($storage->getTypeIdInStorage($_ty), $storage->connection));
					$ty_id = implode(",", $ty_id);
					
					// the table is also used for storing other data, we will just unset
					if ($rowid !== null)
						$q = "UPDATE {$prop_table_esc} SET `{$ref_col}`=NULL ".($ref_col_ty ? ",{$ref_col_ty}=NULL" : "").
								" WHERE `{$ref_col}` IN ({$rowid_in_str}) ".($ref_col_ty ? " AND `{$ref_col_ty}` IN ({$ty_id})" : "").";";
					else
						$q = "UPDATE {$prop_table_esc} SET `{$ref_col}`=NULL ".($ref_col_ty ? ",{$ref_col_ty}=NULL" : "").
								" WHERE `{$ref_col}` NOT IN (SELECT * FROM (".
									"SELECT `\$id` FROM {$table_name_esc}) AS `tmp_tbl__`) ".($ref_col_ty ? " AND `{$ref_col_ty}` IN ({$ty_id})" : "").";";
				}
				else if ($prop_inf['[]'] && (!$prop_inf['o2m']))
				{
					// we need to know if this m2m table is used for types as well
					$many_to_many_table_types = \QSqlModelInfoType::GetTableTypes($prop_inf['ct']);
					$do_update = $many_to_many_table_types ? true : false;
					// many to many
					$ty_id = [];
					foreach ($ty as $_ty)
						$ty_id[] = $type_ids[$_ty] ?: ($type_ids[$_ty] = static::EscapeScalar_S($storage->getTypeIdInStorage($_ty), $storage->connection));
					$ty_id = implode(",", $ty_id);
					
					$where_col = is_array($prop_inf['cf']) ? $prop_inf['cf'][0] : $prop_inf['cf'];
					$where_col_ty = (is_array($prop_inf['cf']) && $prop_inf['cf'][1] && (!is_array($prop_inf['cf'][1]))) ? $prop_inf['cf'][1] : null;
					
					$prop_table_esc = '`'.implode('`.`', explode('.', $prop_inf['ct'])).'`';
					
					if ($do_update)
					{
						// the table is also used for storing other data, we will just unset
						if ($rowid !== null)
							$q = "UPDATE {$prop_table_esc} SET `{$where_col}`=NULL ".($where_col_ty ? ",{$where_col_ty}=NULL" : "").
									" WHERE `{$where_col}` IN ({$rowid_in_str}) ".($where_col_ty ? " AND `{$where_col_ty}` IN ({$ty_id})" : "").";";
						else
							$q = "UPDATE {$prop_table_esc} SET `{$where_col}`=NULL ".($where_col_ty ? ",{$where_col_ty}=NULL" : "").
									" WHERE `{$where_col}` NOT IN (SELECT * FROM (".
										"SELECT `\$id` FROM {$table_name_esc}) AS `tmp_tbl__`) ".($where_col_ty ? " AND `{$where_col_ty}` IN ({$ty_id})" : "").";";
					}
					else
					{
						if ($rowid !== null)
							$q = "DELETE FROM {$prop_table_esc} WHERE `{$where_col}` IN ({$rowid_in_str}) ".($where_col_ty ? " AND `{$where_col_ty}` IN ({$ty_id})" : "").";";
						else
							$q = "DELETE FROM {$prop_table_esc} WHERE `{$where_col}` NOT IN (SELECT * FROM (".
										"SELECT `\$id` FROM {$table_name_esc}) AS `tmp_tbl__`) ".($where_col_ty ? " AND `{$where_col_ty}` IN ({$ty_id})" : "").";";
					}
					// index to remove duplicates !!!
					$queries[$q] = $q;
				}
				else if ($prop_inf['#'])
				{
					// reference
					$ty_id = [];
					foreach ($ty as $_ty)
						$ty_id[] = $type_ids[$_ty] ?: ($type_ids[$_ty] = static::EscapeScalar_S($storage->getTypeIdInStorage($_ty), $storage->connection));
					$ty_id = implode(",", $ty_id);
					
					$ref_col = $prop_inf['rc'];
					$ref_col_ty = is_array($prop_inf['rc_t']) ? null : $prop_inf['rc_t'];
					
					$prop_table_esc = '`'.implode('`.`', explode('.', $type_inf['#%table'])).'`';
					
					if ($rowid !== null)
					{
						$q = "UPDATE {$prop_table_esc} ".
									"SET `{$ref_col}`=NULL".($ref_col_ty ? ",{$ref_col_ty}=NULL" : "").
									" WHERE `{$ref_col}` IN ({$rowid_in_str})".($ref_col_ty ? " AND {$ref_col_ty} IN ({$ty_id})" : "").";";
					}
					else
					{
						$q = "UPDATE {$prop_table_esc} ".
									"SET `{$ref_col}`=NULL".($ref_col_ty ? ",{$ref_col_ty}=NULL" : "").
									" WHERE `{$ref_col}` NOT IN (SELECT * FROM (".
												"SELECT `\$id` FROM {$table_name_esc}) AS `tmp_tbl__`)".($ref_col_ty ? " AND {$ref_col_ty} IN ({$ty_id})" : "").";";
					}
					// index to remove duplicates !!!
					$queries[$q] = $q;
				}
			}
		}
		
		return $queries;
	}
	
	public static function GetMergeByInfo_Parsed(string $mby_entity, string $parent_class, string $property_name = null)
	{
		if (($rv = static::$_MergeByInfo_Parsed[$parent_class][$property_name ?: 0]))
			return $rv;
		else
			return (static::$_MergeByInfo_Parsed[$parent_class][$property_name ?: 0] = qParseEntity ($mby_entity));
	}
	
	public static function MergeByPopulateIfNeeded(array &$populate_before_merge_by, \QIModel $model, array $entitity, string $impl_ent = null, bool $debug = false)
	{
		$rv = false;
		
		foreach ($entitity as $k => $v)
		{
			if (!$model->wasSet($k))
			{
				$rv = true;
				break;
			}
			else if ($v && ($mv = $model->$k))
			{
				if (qis_array($mv))
				{
					foreach ($mv as $mv_v)
					{
						if (static::MergeByPopulateIfNeeded($populate_before_merge_by, $mv_v, $v, null, $debug))
						{
							$rv = true;
							break;
						}
					}
					if ($rv === true)
						break;
				}
				else if (static::MergeByPopulateIfNeeded($populate_before_merge_by, $mv, $v, null, $debug))
				{
					$rv = true;
					break;
				}
			}
		}
		
		if (($rv === true) && (($m_id = $model->getId()) !== null))
		{
			if ($impl_ent === null)
				$impl_ent = trim(qImplodeEntity($entitity));
			$populate_before_merge_by[$impl_ent][] = $model;
		}
		
		return $rv;
	}
	
	public static function PrepareModelsForMergeBy(array &$populate_before_merge_by, array &$merge_by_models, $models_list, string $type_mergeBy, 
													string $parent_class_name, string $parent_prooperty_name, \QIModel $parent_model = null)
	{
		$type_mergeBy_entity = static::GetMergeByInfo_Parsed($type_mergeBy, $parent_class_name, $parent_prooperty_name);
		$impl_ent = trim($type_mergeBy);
		if ($type_mergeBy_entity && ($impl_ent !== 'Id'))
		{
			foreach ($models_list as $model)
			{
				if ((!($model instanceof \QIModel)) || ($model->getId() !== null))
					continue;
				
				$merge_by_models[$parent_class_name][$parent_prooperty_name][] = [$model, $type_mergeBy, $parent_model, $parent_prooperty_name];
				static::MergeByPopulateIfNeeded($populate_before_merge_by, $model, $type_mergeBy_entity, $impl_ent);
			}
		}
	}
	
	public static function CleanupForRemovedElements($remove_elements)
	{
		if (!$remove_elements)
			return [];
		
		$by_class = [];
		foreach ($remove_elements ?: [] as $re)
		{
			$id = $re->getId();
			if (!$id)
				continue;
			$by_class[get_class($re)][$id] = $id;
		}
		
		if (!$by_class)
			return [];
		
		$storage = \QApp::GetStorage();
		foreach ($by_class as $class_name => $ids)
		{
			if (!$ids)
				continue;
			
			$_vty = \QModelQuery::GetTypesCache($class_name);
			$table_name = $_vty["#%table"];
			
			$queries = static::GetCleanupQueries($storage, $table_name, $class_name, $ids);
			$mysqli = $storage->connection;
			// echo "<div style='font-family: monospace;'>";
			foreach ($queries as $q)
			{
				// echo "<b>{$q}</b><br/>\n";
				$res = $mysqli->query($q);
				if (!$res)
				{
					// echo "<span style='color: red;'>ERROR [{$mysqli->errno}]: {$mysqli->error}</span><br/>\n";
					throw new \Exception("ERROR [{$mysqli->errno}]: {$mysqli->error}");
				}
				else
				{
					/*
					if (!$mysqli->affected_rows)
						echo "No change<br/>\n";
					else
						echo "<span style='color: blue;'>Affected Rows: ".$mysqli->affected_rows."</span><br/>\n";
					*/
				}
				// echo "--------------------------------------------<br/>\n";
			}
			// echo "</div>";

			/*if ($return_output)
				return [$queries, ob_get_clean()];
			else
				return [$queries];*/
		}
	}
}
