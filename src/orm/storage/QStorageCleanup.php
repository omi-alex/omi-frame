<?php

/**
 * Description of QStorageCleanup
 *
 * @author Omi-Mihai
 */
class QStorageCleanup
{
	protected $storage;
	/**
	 * @var mysqli
	 */
	protected $sql;
	
	public static function Cleanup($storage_key = null)
	{
		(new QStorageCleanup())->runCleanup($storage_key);
	}
	
	protected function runCleanup($storage_key = null)
	{
		$this->storage = QApp::GetStorage($storage_key);
		if (!$this->storage)
			return false;
		$this->sql = $this->storage->connection;
		if (!$this->sql)
			return false;
		
		$this->createCleanupTable();
		
		try
		{
			$this->storage->begin();
			
			// do the work
			// echo 'do the work';
			
			$this->storage->ensureTypeIdsWasIncluded();
			
			// finally drop the tables
			$type_ids = $this->storage->getTypeIds();
			// $type_inf = self::GetTypesCache($from_k);
			$table_to_types = QSqlModelInfoType::GetTableToTypesList();
			$type_to_table = QSqlModelInfoType::GetTableTypeList();
			
			// WE NEVER DELETE FROM $APP
			
			$this->resetCleanupTable();
			
			// drop index before insert
			$this->sql->query('ALTER TABLE `$$Cleanup` DROP INDEX `rowid`, DROP INDEX `table`, DROP INDEX `count`');
			
			// stage 1. load data in
			foreach ($table_to_types as $table => $ty_inf)
			{
				$rowid = null;
				$stmt = $this->sql->prepare('INSERT INTO `$$Cleanup` (`table`,`rowid`,`count`) VALUES (\''._mySc($table).'\', ?, 0)');
				
				$res = $this->sql->query("SELECT `\$id` FROM `"._mySc($table)."`");
				while (($row = $res->fetch_assoc()) && ($rowid = (int)$row['$id']))
				{
					$stmt->bind_param('i', $rowid);
					$stmt->execute();
					// $stmt->reset();
				}
				
				$stmt->close();
			}
			$this->sql->query('ALTER TABLE `$$Cleanup` ADD UNIQUE `rowid` (`rowid`, `table`), ADD UNIQUE `table` (`table`, `rowid`), ADD INDEX (`count`)');
			
			// intram pe model . recurse . stop if used ... when we got zero we stop
			
			$app_class = QApp::GetDataClass();
			$app_id = QApp::Data()->getId();
			
			// update the ones in APP as referenced
			// $this->sql->query('UPDATE `$$Cleanup` SET `count` = `count` + 1 WHERE `table`=\''._mySc($type_to_table[$app_class]).'\';');
			
			
			// MODELS PROCESS START
			
			$this->sql->query('UPDATE `$$Cleanup` SET `count`=0');
			
			$models = [$app_class => [$app_id => $app_id]];
			$selectors_cache = [];
			
			while ($models)
			{
				$new_m = $this->processModels($models, $selectors_cache, $type_to_table);
				$models = $new_m;
			}
			
			$t0 = microtime(true);
			$res_tabs = $this->sql->query('SELECT DISTINCT `table` FROM `$$Cleanup` WHERE `count`=0');
			while (($tabs_row = $res_tabs->fetch_assoc()))
			{
				$clean_table = $tabs_row['table'];
				// var_dump($clean_table);
				// $q = $this->sql->query("SELECT `rowid` FROM `\$\$Cleanup` WHERE `table`='{$clean_table}' AND `count`=0");
				// echo "DELETE FROM `{$clean_table}` WHERE `\$id` IN (SELECT `rowid` FROM `\$\$Cleanup` WHERE `table`='{$clean_table}' AND `count`=0)";
				// echo "<br />\n";
				// var_dump($q->num_rows);
			}
			$t1 = microtime(true);
			// var_dump($t1 - $t0);

			
			$this->cleanupReferences();
			
			echo 'we are done ok';
			
			/*$columns_info = QSqlModelInfoType::GetColumnsTypeInfo();
			
			foreach ($columns_info as $table => $table_inf)
			{
				
			}*/

			$this->storage->commit();
		}
		catch (\Exception $ex)
		{
			$this->storage->rollback();
			var_dump($ex);
		}
		finally
		{
			// test
		}
	}
	
	/**
	 * @TODO - ENABLE EXECUTION OF QUERIES
	 * 
	 * @throws \Exception
	 */
	protected function cleanupReferences()
	{	
		$table_to_types = QSqlModelInfoType::GetTableToTypesList();
		$type_to_table = QSqlModelInfoType::GetTableTypeList();
		
		foreach ($type_to_table as $class => $tab)
		{
			$class_inf = QModelQuery::GetTypesCache($class);
			foreach ($class_inf as $prop_name => $inf)
			{
				if (($prop_name{0} === '#') && ($prop_name{1} === '%'))
					continue;
				
				if (($ref_inf = $inf['#']))
				{
					if (!$inf['j'])
					{
						// var_dump($inf);
						// throw new \Exception('Expected join information');
						// may happen for some interfaces
						continue;
					}
					// var_dump($class, $tab, $prop_name, $inf);
					foreach ($inf['j'] as $join_tab => $join_types)
					{
						$query = "UPDATE `{$tab}` SET `{$inf['rc']}`=NULL WHERE `{$inf['rc']}` IN (SELECT `rowid` FROM `\$\$Cleanup` WHERE `table`='{$join_tab}' AND `count`=0)";
						if (is_string($inf['rc_t']))
						{
							// var_dump($inf['rc_t']);
							// get a list of ids
							$type_ids = [];
							foreach ($join_types as $jt)
								$type_ids[] = $this->storage->getTypeIdInStorage($jt);
							if (count($type_ids) === 1)
								$query .= " AND `{$inf['rc_t']}`=".reset($type_ids);
							else
								$query .= " AND `{$inf['rc_t']}` IN (".implode(",", $type_ids).")";
						}
						echo ($query)."\n";
						
						// $this->sql->query($query);
					}
				}
				else if (($ref_inf = $inf['[]']['#']))
				{
					if (!is_string($inf['cb']))
					{
						var_dump('uuups', $inf);
						throw new \Exception('Unexpected');
					}
					$query = "UPDATE `{$inf['ct']}` SET `{$inf['cb']}`=NULL WHERE `{$inf['cb']}` IN (SELECT `rowid` FROM `\$\$Cleanup` WHERE `table`='{$class_inf["#%table"]}' AND `count`=0)";
					// var_dump($class_inf["#%table"], $inf);
					echo ($query)."\n";
					
					if ($inf['o2m'])
					{
						// one to many
					}
					else
					{
						if (!$inf['[]']['j'])
						{
							// var_dump($inf);
							// throw new \Exception('Expected join information');
							// may happen for some interfaces
							continue;
						}
						
						foreach ($inf['[]']['j'] as $join_tab => $join_types)
						{
							$collection_table = $inf['ct'];
							$table_m2m_exclusive = !$table_to_types[$collection_table];

							$query = "";
							if ($table_m2m_exclusive)
							{
								$query .= "DELETE FROM `{$collection_table}` ";
							}
							else
							{
								$query .= "UPDATE `{$collection_table}` SET ";
							}

							list($fwd_tab_id, $fwd_tab_ty) = $inf["cf"];
							$query .= " WHERE `{$fwd_tab_id}` IN (SELECT `rowid` FROM `\$\$Cleanup` WHERE `table`='{$join_tab}' AND `count`=0) ";
							if (is_string($fwd_tab_ty))
							{
								$type_ids = [];
								foreach ($join_types as $jt)
									$type_ids[] = $this->storage->getTypeIdInStorage($jt);
								if (count($type_ids) === 1)
									$query .= " AND `{$fwd_tab_ty}`=".reset($type_ids);
								else
									$query .= " AND `{$fwd_tab_ty}` IN (".implode(",", $type_ids).")";
							}

							// ($table_m2m_exclusive ? "DELETE FROM " : "UPDATE ").
							echo ($query)."\n";
						}
					}
				}
			}
		}
	}
	
	protected function processModels($models, &$selectors_cache, $type_to_table)
	{
		$return = [];
		
		foreach ($models as $class => $all_ids)
		{
			// var_dump('processModels: '.$class);
			
			// if too many ids, break them in batches
			$batch_size = 2000;
			$ids_count = count($all_ids);
			$batches = ceil($ids_count / $batch_size);
			$all_ids_v = array_values($all_ids);
			
			for ($bi = 0; $bi < $batches; $bi++)
			{
				$c_ids = array_slice($all_ids_v, $bi * $batch_size, $batch_size);
				$ids = [];
			
				$table_name = $type_to_table[$class];
				$proc_q = 'SELECT `rowid` FROM `$$Cleanup` WHERE `table`=\''._mySc($table_name).'\' AND `rowid` IN ('.implode(',', $c_ids).') AND `count`=0';
				// var_dump($proc_q);
				$not_processed = $this->sql->query($proc_q);
				while (($np_row = $not_processed->fetch_assoc()))
				{
					$np_rowid = (int)$np_row['rowid'];
					$ids[$np_rowid] = $np_rowid;
				}
				
				if (!$ids)
					continue;
				
				list($selector, $inf) = $selectors_cache[$class];
				if ($selector === null)
					list($selector, $inf) = $this->getSelectorForClass($class, $selectors_cache);

				foreach ($selector as $key => $sel)
				{
					// $data = $class::QueryByIds($ids, $sel); - we need the query without executing it !
					$is_collection = ($key !== '#');

					if ($is_collection)
					{
						$offset = 0;
						$step = 20000;

						do
						{
							$e_count = 0;
							$query = $sel.".{ ORDER BY Id LIMIT {$offset},{$step}} WHERE Id IN (?)";
							$data_block = null;

							// var_dump($query, $class);
							$res = QModelQuery::BindQuery($query, [array_values($ids)], $class, $data_block, true);
							if ($res)
							{
								foreach ($res as $model_obj)
								{
									$collection = $model_obj->$sel;
									if ($collection)
									{
										foreach ($collection as $obj)
										{
											$o_id = (int)$obj->getId();
											$return[get_class($obj)][$o_id] = $o_id;

											$e_count++;
										}
									}
								}
							}
							$offset += $step;
						}
						while ($e_count > 0);
					}
					else
					{
						$query = $sel." WHERE Id IN (?)";
						$data_block = null;
						// var_dump($query, $class);
						$res = QModelQuery::BindQuery($query, [array_values($ids)], $class, $data_block, true);

						if ($res)
						{
							foreach ($res as $model_obj)
							{
								foreach ($inf['#'] as $prop_name => $inf_i)
								{
									if (($obj = $model_obj->$prop_name))
									{
										$o_id = (int)$obj->getId();
										$return[get_class($obj)][$o_id] = $o_id;
									}
								}
							}
						}
					}
				}
			
				$this->sql->query('UPDATE `$$Cleanup` SET `count` = `count` + 1 WHERE `table`=\''._mySc($table_name).'\' AND `rowid` IN ('.implode(',', $ids).')');
			}
		}
		
		// $this->sql->query('UPDATE `$$Cleanup` SET `count` = `count` + 1 WHERE `table`=\''._mySc($type_to_table[$app_class]).'\'');
		/*
		$stats = [];
		foreach ($return as $k => $r)
			$stats[$k] = count($r);
		*/
		// var_dump($stats);
		return $return;
	}
	
	protected function getSelectorForClass($class, &$selectors_cache = null)
	{
		$selector = [];
		$ret_inf = [];
		$class_inf = QModelQuery::GetTypesCache($class);
		foreach ($class_inf as $prop_name => $inf)
		{
			if (($prop_name{0} === '#') && ($prop_name{1} === '%'))
				continue;

			if (($ref_inf = $inf['#']))
			{
				$selector['#'][$prop_name] = $prop_name;
				$ret_inf['#'][$prop_name] = $inf;
			}
			else if (($ref_inf = $inf['[]']['#']))
			{
				$selector[$prop_name] = $prop_name;
				$ret_inf[$prop_name] = $inf;
			}
		}
		
		if (($s = $selector['#']))
			$selector['#'] = implode(",", $s);
		
		if ($selectors_cache !== null)
			$selectors_cache[$class] = [$selector, $ret_inf];
		
		return [$selector, $ret_inf];
	}
	
	protected function createCleanupTable()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS `$$Cleanup` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`table` VARCHAR( 255 ) NOT NULL ,
`rowid` INT UNSIGNED NOT NULL ,
`count` INT UNSIGNED NOT NULL ,
INDEX ( `count` ),
UNIQUE ( `rowid`,`table` ),
UNIQUE ( `table`,`rowid` )
) ENGINE = InnoDB;';
		
		$this->sql->query($sql);
		/*
		$sql = 'CREATE TABLE `$$Cleanup_Refs` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`rowid` INT UNSIGNED NOT NULL ,
`ref` INT UNSIGNED NOT NULL ,
INDEX ( `rowid` ) ,
INDEX ( `ref` )
) ENGINE = InnoDB;';
		
		$this->sql->query($sql);*/
	}
	
	protected function resetCleanupTable()
	{
		$this->sql->query('TRUNCATE TABLE `$$Cleanup`');
		// $this->sql->query('TRUNCATE TABLE `$$Cleanup_Refs`');
	}
}