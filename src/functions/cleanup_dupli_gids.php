<?php

	re_map_ids([100 => 10], "Omi\\Comm\\Customer");
	
	function re_map_ids(array $ids_map, string $class_name, string $cast_type = null)
	{
		# @TODO - also able to change data type if possible !!!!!!!
		
		$table_name = \QSqlModelInfoType::GetTableNameForType($class_name);
		$ttl = \QSqlModelInfoType::GetReverseTablesForPropertyTypes($table_name);
		$types_ids = \QMySqlStorage::GetModelTypeIds();
		$old_type_id = is_array($types_ids) ? $types_ids[$class_name] : null;
		if (!$old_type_id)
			throw new \Exception('Unable to establish type for the selected class');
		if (!$cast_type)
			$cast_type = $class_name;
		$new_type_id = is_array($types_ids) ? $types_ids[$cast_type] : null;
		
		$sqls = ["BEGIN;"];
		
		$table_prop_map = [];
		
		foreach ($ttl as $type_prop => $info)
		{
			$sqls[] = "\n\n\n# {$type_prop}\n";
			
			$sql_info = \QModelQuery::GetTypesCache($type_prop);
			
			foreach ($info as $prop_name => $misc)
			{
				$prop_sql = $sql_info[$prop_name];
				if (!$prop_sql)
					throw new \Exception('Broken!');
				if ((!$prop_sql['[]']) && (!$prop_sql['#']))
					throw new \Exception('Not expected!');
				else if (($prop_sql['[]']) && ($prop_sql['#']))
					throw new \Exception('Not expected!');
				
				$refs = $prop_sql['[]'] ? $prop_sql['[]']['refs'] : $prop_sql['refs'];
				if (!$refs)
					throw new \Exception('ex');
				if (!$refs[$cast_type])
					continue;
				
				# qvar_dumpk($type_prop.".".$prop_name, $refs);
				$sqls[] = "# {$type_prop}.{$prop_name}\n";
								
				if ($prop_sql['[]']) # collection
				{
					if (!$prop_sql['ct'])
						throw new \Exception('Fail!');
					
					if (!is_array($prop_sql['cf']))
						throw new \Exception('@TODO!');
					
					if ($prop_sql['o2m'])
					{
						# qvar_dumpk("# {$type_prop}.{$prop_name}" , $prop_sql);
						
						# one to many
						foreach ($ids_map as $old_id => $new_id_inf)
						{
							$new_id = is_array($new_id_inf) ? $new_id_inf[0] : $new_id_inf;
							$type_id = is_array($new_id_inf) ? (is_numeric($new_id_inf[1]) ? $new_id_inf[1] : $types_ids[$new_id_inf[1]]) : $new_type_id;
							
							# @TODO - check type , include type !!!
							$sqls[] = "UPDATE `{$prop_sql['ct']}` SET `{$prop_sql['cf'][0]}`='{$new_id}',`{$prop_sql['cf'][1]}`='{$type_id}' ".
											"WHERE `{$prop_sql['cf'][0]}`=`{$old_id}` AND `{$prop_sql['cf'][1]}`='{$old_type_id}';\n";
							# qvar_dumpk(end($sqls));
						}
					}
					else
					{
						# qvar_dumpk("# {$type_prop}.{$prop_name}" , $prop_sql);
						
						foreach ($ids_map as $old_id => $new_id)
						{
							$new_id = is_array($new_id_inf) ? $new_id_inf[0] : $new_id_inf;
							$type_id = is_array($new_id_inf) ? (is_numeric($new_id_inf[1]) ? $new_id_inf[1] : $types_ids[$new_id_inf[1]]) : $new_type_id;
							# @TODO - check type , include type !!!
							$sqls[] = "UPDATE `{$prop_sql['ct']}` SET `{$prop_sql['cf'][0]}`='{$new_id}',`{$prop_sql['cf'][1]}`='{$type_id}' "
										. "	WHERE `{$prop_sql['cf'][0]}`=`{$old_id}` AND `{$prop_sql['cf'][1]}`='{$old_type_id}';\n";
							# qvar_dumpk(end($sqls));
						}
						
						# die;
					}
				}
				if ($prop_sql['#'])
				{
					# foreach ($prop_sql['j'] as $j_table_name => $j_types)
					$j_table_name = $sql_info['#%table'];
					{
						foreach ($ids_map as $old_id => $new_id)
						{
							$new_id = is_array($new_id_inf) ? $new_id_inf[0] : $new_id_inf;
							$type_id = is_array($new_id_inf) ? (is_numeric($new_id_inf[1]) ? $new_id_inf[1] : $types_ids[$new_id_inf[1]]) : $new_type_id;
							# @TODO - check type , include type !!!
							if (is_string($prop_sql['rc_t']))
								$sqls[] = "UPDATE `{$j_table_name}` SET `{$prop_sql['rc']}`='{$new_id}',`{$prop_sql['rc_t']}`='{$type_id}' "
											. "WHERE `{$prop_sql['rc']}`=`{$old_id}` AND `{$prop_sql['rc_t']}`='{$old_type_id}';\n";
							else
								$sqls[] = "UPDATE `{$j_table_name}` SET `{$prop_sql['rc']}`='{$new_id}' WHERE `{$prop_sql['rc']}`=`{$old_id}`;\n";
						}
					}
				}
			}
		}
		
		$sqls[] = "\n\nCOMMIT;";
		
		qvar_dumpk(implode('', $sqls));
		
		
		# do all in a big transaction
		
		# 1. do a check that the new ID is either null or exists, and check the type also
		#		MUST NOT MAP TO ITSELF - no opp
		# 2. update in the relevant tables
		# 3. remove (if not null)
		
	}
	