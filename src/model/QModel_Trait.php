<?php

trait QModel_Trait
{
	public static function ExportDataToFlatCsv_deprecated($data, string $selector = null, $destination = null, bool $start = true, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\')
	{
		// for testing only
		set_time_limit(3);
		
		if ($data === null)
			return "";
		if (!(($data instanceof \QModelArray) || is_array($data)))
			throw new \Exception('Bad input');
		if (!((($data instanceof \QModelArray) && $data->count()) || ($data && is_array($data))))
			return "";

		if ($selector === null)
		{
			// extract flat selector
			$elements = [reset($data)];
			$a_selector = [];
			while ($elements)
			{
				foreach ($elements as $k => $e)
				{
					$ty_inf = \QModelQuery::GetTypesCache(get_class($e));
					if (!$ty_inf)
						continue;
					foreach ($ty_inf as $k => $ty)
					{
						// do it from here on
						throw new \Exception('@TODO build flat selector from cache');
					}
				}
			}
		}
		else if (is_string($selector))
		{
			$fields = preg_split('/(\\s*,\\s*)/us', $selector, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($fields as $k => $v)
				$fields[$k] = trim($v);
			$a_selector = [];
			foreach ($fields as $k => $p)
				$a_selector[] = preg_split('/(\\s*\\.\\s*)/us', $p, -1, PREG_SPLIT_NO_EMPTY);
		}
		else if (!(($selector === null) || is_array($selector)))
			throw new \Exception("Bad input");

		// flat selector
		$stream = fopen('php://memory', 'r+');
		fputcsv($stream, $fields, $delimiter, $enclosure, $escape_char);
		
		$matrix = [];
		$row_index = 0;
		
		$elements = [];
		foreach ($data as $d)
			$elements[] = [$d, $a_selector];
		
		$sel_index = [];
		
		for ($j = 0; $j < count($elements); $j++)
		{
			list($first, $c_selector, $copy_row)  = $elements[$j];
			if ($first === null)
				continue;
			$row = &$matrix[$j];
			if ($copy_row !== null)
			{
				foreach ($matrix[$copy_row] as $k => $v)
					$row[$k] = $v;
			}
			// ensure proper order and alignment
			else if (!$row)
			{
				foreach ($fields as $f)
					$row[$f] = "";
			}
			
			$new_elements = new \SplObjectStorage();
			
			foreach ($c_selector as $f_index => $props)
			{
				$obj = $first;
				// first resolve all scalars
				$len = count($props);
				for ($i = 0; $i < $len; $i++)
				{
					$c_prop = $props[$i];
					
					/*$debug = (($obj instanceof \Omi\Comm\Pricing) && ($c_prop === 'Interval'));
					if ($debug)
					{
						qvar_dump($obj, $i, $c_prop, $props);
					}*/
					
					$obj = $obj ? $obj->{"get{$c_prop}"}() : null;
					
					// last
					if (($obj instanceof \QModelArray) || is_array($obj))
					{
						$is_first = true;
						$next_obj = null;
						foreach ($obj as $o)
						{
							if ($o === null)
								continue;
							if ($is_first)
							{
								// we will process it here
								$is_first = false;
								$next_obj = $o;
							}
							else
							{
								$sel_index_key = implode(".", array_slice($props, 0, $i + 1));
								$sel_elems = $sel_index[$sel_index_key];
								if ($sel_elems === null)
								{
									$sel_elems = [];
									foreach ($a_selector as $a_findex => $opts)
									{
										if ($sel_index_key === implode(".", array_slice($opts, 0, $i + 1)))
											$sel_elems[$a_findex] = array_slice($opts, $i + 1);
									}
									$sel_index[$sel_index_key] = $sel_elems;
								}
								$new_elements[$o] = [$o, $sel_elems, $j];
							}
						}
						// we need to fill for the first element on this row
						$obj = $next_obj;
					}
					
					if ($i === ($len - 1))
					{
						if ($obj instanceof \QIModel)
							$row[$fields[$f_index]] = (string)$obj->getId();
						else
							$row[$fields[$f_index]] = (string)$obj;
					}
				}
			}
			
			// we need to remove the element processed and add new elements if exists
			if ($new_elements && $new_elements->count())
			{
				$arr = [];
				foreach ($new_elements as $ne)
					$arr[] = $new_elements[$ne];
				$arr = array_reverse($arr);
				array_splice($elements, $j + 1, 0, $arr);
			}
		}
		
		/*
		echo "<table>\n<tr>";
		echo "<th>ROWI</th>";
		foreach ($fields as $f)
			echo "<th>".end(explode(".", $f))."</th>";
		echo "</tr>\n";
		foreach ($matrix as $i => $record)
		{
			echo "<tr>";
			echo "<td>{$i}</td>";
			foreach ($record as $r)
				echo "<td>{$r}</td>";
			echo "</tr>\n";
		}
		echo "</table>\n";
		*/
		
		foreach ($matrix as $i => $record)
			fputcsv($stream, $record, $delimiter, $enclosure, $escape_char);
		
		rewind($stream);
		return stream_get_contents($stream);
	}
		
	public static function ExportDataToFlatCsv($data, string $selector = null, bool $return_matrix = false, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\')
	{
		if ($data === null)
			return "";
		if (!(($data instanceof \QModelArray) || is_array($data)))
			throw new \Exception('Bad input');
		if (!((($data instanceof \QModelArray) && $data->count()) || ($data && is_array($data))))
			return "";

		if ($selector === null)
		{
			throw new \Exception('Not implemented');
			// extract flat selector
			$elements = [reset($data)];
			$a_selector = [];
			while ($elements)
			{
				foreach ($elements as $k => $e)
				{
					$ty_inf = \QModelQuery::GetTypesCache(get_class($e));
					if (!$ty_inf)
						continue;
					foreach ($ty_inf as $k => $ty)
					{
						// do it from here on
						throw new \Exception('@TODO build flat selector from cache');
					}
				}
			}
		}
		else if (is_string($selector))
		{
			$fields = preg_split('/(\\s*,\\s*)/us', $selector, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($fields as $k => $v)
				$fields[$k] = trim($v);
			$a_selector = [];
			$impl_selector = [];
			foreach ($fields as $k => $p)
			{
				$parsed_parts = preg_split('/(\\s*\\.\\s*)/us', $p, -1, PREG_SPLIT_NO_EMPTY);
				$a_selector[] = $parsed_parts;
				$imploded = implode('.', $parsed_parts);
				$impl_selector[$imploded] = $k;
			}
		}
		else if (!(($selector === null) || is_array($selector)))
			throw new \Exception("Bad input");
		
		if ($return_matrix)
		{
			
		}
		else
		{
			// flat selector
			$stream = fopen('php://memory', 'r+');
			fputcsv($stream, $fields, $delimiter, $enclosure, $escape_char);
		}
		
		$matrix = [];
		
		$parsed_selector = qParseEntity($selector);
		static::ExportDataToFlatCsv_Traverse($matrix, 0, $parsed_selector, $data, $impl_selector);
		
		ksort($matrix);
		
		/*if (\QAutoload::GetDevelopmentMode())
		{
			static::ExportDataToFlatCsv_Print_Matrix($matrix, $impl_selector);
			return;
		}*/
		
		if ($return_matrix)
		{
			foreach ($matrix as $k => $record)
			{
				ksort($record);
				$aligned_record = [];
				foreach ($impl_selector as $v)
					$aligned_record[] = $record[$v];
				$matrix[$k] = $aligned_record;
			}
			
			return [$impl_selector, $matrix];
		}
		else
		{
			foreach ($matrix as $record)
			{
				ksort($record);
				$aligned_record = [];
				foreach ($impl_selector as $v)
					$aligned_record[] = $record[$v];
				fputcsv($stream, $aligned_record, $delimiter, $enclosure, $escape_char);
			}

			rewind($stream);
			return stream_get_contents($stream);
		}
	}
	
	public static function ExportDataToFlatCsv_Traverse(array &$matrix, int $matrix_pos, array $selector, $elements, array $matchup, string $position = "", bool $is_start = true)
	{
		if ((!$elements) || ($selector === null) || ($selector === []))
			return;
		
		foreach ($elements as $e)
		{
			if (is_object($e))
			{
				$next_bag_pos = $matrix_pos;
				
				$copy_except = [];
				
				foreach ($selector as $k => $v)
				{
					$is_last_index = $matchup[$position ? $position . "." . $k : $k];
					$k_position = ($position ? $position . "." . $k : $k);
					
					$o_val = $e->$k;
					if ($o_val === null)
					{
						
					}
					else if (qis_array($o_val))
					{
						$start_pos = $matrix_pos;
						$bag_pos = $matrix_pos;
						
						foreach ($o_val as $o_val_val)
						{
							if ($is_last_index !== null)
							{
								$matrix[$bag_pos][$is_last_index] = ($o_val_val instanceof \QIModel) ? (string)$o_val_val->getId() : (string)$o_val_val;
							}

							if (is_object($o_val_val) && $v)
							{
								# echo "Inside: {$k_position} at {$bag_pos}<br/>\n";
								$bag_pos = static::ExportDataToFlatCsv_Traverse($matrix, $bag_pos, $v, [$o_val_val], $matchup, $k_position, false);
							}
							
							$bag_pos++;
						}
						
						if (($bag_pos - 1) > $next_bag_pos)
						{
							# echo "Incrementing from: {$next_bag_pos} to ".($bag_pos - 1)." at {$k_position} </br>\n";
							$next_bag_pos = $bag_pos - 1;
						}
						
						if (($bag_pos - 1) > $start_pos)
						{
							$copy_except[$k] = [$start_pos, $bag_pos - 1];
						}
					}
					else if (is_object($o_val))
					{
						if ($is_last_index !== null)
						{
							$matrix[$matrix_pos][$is_last_index] = ($o_val instanceof \QIModel) ? (string)$o_val->getId() : (string)$o_val;
						}
						
						if ($v)
						{
							# echo "Inside: {$k_position} at {$matrix_pos}<br/>\n";
							$next_bag_pos = static::ExportDataToFlatCsv_Traverse($matrix, $matrix_pos, $v, [$o_val], $matchup, $k_position, false);
						}
					}
					else if ($is_last_index !== null)
					{
						$matrix[$matrix_pos][$is_last_index] = (string)$o_val;
					}
				}
				
				if ($copy_except)
				{
					foreach ($copy_except as $except => $copy_from_to)
					{
						list($copy_from, $copy_to) = $copy_from_to;
						$copy_row = $matrix[$copy_from];
						$copy_cols = [];
						$avoid_copy = $position ? $position . "." . $except : $except;
						$avoid_copy_len = strlen($avoid_copy);
						
						foreach ($matchup as $m => $m_pos)
						{
							// $is_last_index = $matchup[$position ? $position . "." . $k : $k];
							if ((substr($m, 0, $avoid_copy_len) === $avoid_copy) && (($m{$avoid_copy_len} === '.') || (strlen($m) === $avoid_copy_len)))
								continue;
							$copy_cols[] = $m_pos;
						}
						
						for ($i = ($copy_from + 1); $i <= $copy_to; $i++)
						{
							foreach ($copy_cols as $copy_col)
								$matrix[$i][$copy_col] = $copy_row[$copy_col];
						}
					}
				}
			}
			
			$matrix_pos = $next_bag_pos;
			if ($is_start)
				$matrix_pos++;
		}
		
		return $matrix_pos;
	}
	
	public static function ExportDataToFlatCsv_Print_Matrix($matrix, $impl_selector)
	{
		
		?><style type='text/css'>
			#hor-minimalist-b {
				font-family: "Lucida Sans Unicode", "Lucida Grande", Sans-Serif;
				font-size: 12px;
				background: #fff;
				border-collapse: collapse;
				text-align: left;
				margin: 20px;
			}
			#hor-minimalist-b th {
				font-size: 14px;
				font-weight: normal;
				color: #039;
				border-bottom: 2px solid #6678b1;
				padding: 10px 8px;
			}
			#hor-minimalist-b td {
				border-bottom: 1px solid #ccc;
				color: #669;
				padding: 6px 8px;
			}
		</style><?php
		echo "<table id='hor-minimalist-b'>";
		echo "<tr>";
		echo "<th></th>";
		foreach ($impl_selector as $imp => $v)
			echo "<th>".str_replace('.', '. ', $imp)."</th>";
		echo "</tr>";
		
		ksort($matrix);
		
		foreach ($matrix as $indx => $row)
		{
			ksort($row);
			echo "<tr>";
			echo "<td>{$indx}</td>";
			foreach ($impl_selector as $v)
				echo "<td>".$row[$v]."</td>";
			echo "</tr>";
		}
		echo "</table>";
	}
	
	public static function ModelGetIndexedCollection($list, bool $with_index = false)
	{
		if (!qis_array($list))
			return false;
		$ret = [];
		if ($with_index)
			$ret_index = [];
		$is_q_array = ($list instanceof \QModelArray);
		$by_index_pos = 0;
		foreach ($list as $key => $v)
		{
			if (($v instanceof \QIModel) && $v->getId())
				$ret[$n_index = $v->getId().":id"] = $v;
			else if ($is_q_array && (($rowid = $list->getRowIdAtIndex($key)) !== null))
				$ret[$n_index = $rowid.":rid"] = $v;
			else if (is_scalar($v))
				$ret[$n_index = $v.":sclr"] = $v;
			else
			{
				$ret[$n_index = $by_index_pos.":ndx"] = $v;
				$by_index_pos++;
			}
			if ($with_index)
				$ret_index[$n_index] = $key;
			
		}
		return $with_index ? [$ret, $ret_index] : $ret;
	}
	
	public static function ModelGetChanges(\QIModel $model_a, \QIModel $model_b, $selector = null, bool $ignore_transform_state = true)
	{
		$ret = [];
		if (get_class($model_a) !== get_class($model_b))
			$ret['_ty'] = [get_class($model_a), get_class($model_b)];
		if (!$ignore_transform_state)
		{
			if (($model_a->_ts !== $model_b->_ts) && (($model_a->_ts & \QIModel::TransformDelete) || ($model_b->_ts & \QIModel::TransformDelete)))
				$ret['_ts'] = [$model_a->_ts, $model_b->_ts];
			if (($model_a->_tsx !== $model_b->_tsx) && (($model_a->_tsx & \QIModel::TransformDelete) || ($model_b->_tsx & \QIModel::TransformDelete)))
				$ret['_tsx'] = [$model_a->_tsx, $model_b->_tsx];
		}
		
		if ($selector === null)
		{
			$selector_a = $model_a::GetModelEntity();
			$selector_b = $model_b::GetModelEntity();
			$selector = qIntersectSelectors($selector_a, $selector_b);
		}
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		if (!is_array($selector))
			# there is an error
			return false;
		
		foreach ($selector as $k => $sub_selector)
		{
			$a = $model_a->$k;
			$b = $model_b->$k;
			
			if (($a === null) && ($b === null))
			{
				
			}
			else if ((qis_array($a) || ($a === null)) && (qis_array($b) || ($b === null)))
			{
				# both are an array
				# we need a proper matching, prioritize id
				# group them somehow, create a key
				list($arr_a, $arr_a_index) = $a ? static::ModelGetIndexedCollection($a, true) : [[],[]];
				list($arr_b, $arr_b_index) = $b ? static::ModelGetIndexedCollection($b, true) : [[],[]];
				
				/*$a_to_remove = ($this->DDIs->_tsp && ($this->DDIs->_tsp[$route_k] & \QIModel::TransformDelete)) || 
							($route->getTransformState() & \QIModel::TransformDelete) || 
								($route->_tsx & \QIModel::TransformDelete);*/
				
				$common_elements = [];
				$final_index = [];
				
				foreach ($arr_a as $aa_k => $aa_v)
				{
					if (($in_b = $arr_b[$aa_k]) !== null)
					{
						if (($aa_v instanceof \QIModel) && ($in_b instanceof \QIModel))
							$common_elements[$aa_k] = [$aa_v, $in_b];
						if ($a->_tsp || $b->_tsp)
						{
							$a_tsp = $a->_tsp[$arr_a_index[$aa_k]];
							$b_tsp = $b->_tsp[$arr_b_index[$aa_k]];
							if (($a_tsp & \QModel::TransformDelete) || ($b_tsp & \QModel::TransformDelete))
							{
								# has ts changes
								$ret[$k][$aa_k]['_ts'] = [$a_tsp, $b_tsp];
								if (($aa_v instanceof \QIModel) && ($in_b instanceof \QIModel) && ($aa_v->getId() || $in_b->getId()))
									$ret[$k][$aa_k]['Id'] = [$aa_v->getId(), $in_b->getId()];
							}
						}
					}
					else
						$ret[$k][$aa_k] = [$aa_v, null];
				}
				foreach ($arr_b as $bb_k => $bb_v)
				{
					if ($arr_a[$bb_k] === null)
						$ret[$k][$bb_k] = [null, $bb_v];
				}
				
				if ($common_elements && $sub_selector)
				{
					foreach ($common_elements as $aa_k => $c_elems)
					{
						$ss_ret = static::ModelGetChanges($c_elems[0], $c_elems[1], $sub_selector, $ignore_transform_state);
						if ($ss_ret !== null)
						{
							$ret[$k][$aa_k] = $ss_ret;
						}
					}
				}
			}
			else if (($a instanceof \QIModel) && (!($a instanceof \QIModelArray)) && ($b instanceof \QIModel) && (!($b instanceof \QIModelArray)))
			{
				# we need a proper matching, prioritize id
				if ($a->getId() == $b->getId())
				{
					if ($sub_selector)
					{
						$ss_ret = static::ModelGetChanges($a, $b, $sub_selector, $ignore_transform_state);
						if ($ss_ret !== null)
							$ret[$k] = $ss_ret;
					}
				}
				else
				{
					$ret[$k] = [$a, $b];
				}
			}
			else if ($a != $b)
			{
				$ret[$k] = [$a, $b];
			}
			
		}
		
		if ($ret && ($model_a->getId() || $model_b->getId()))
			$ret['Id'] = [$model_a->getId(), $model_b->getId()];
		
		return $ret ?: null;
	}
	
		
	/**
	 * @param mixed $data
	 * @param string|array|boolean $selector
	 * @param \QModelProperty $prop
	 * @param boolean $includeNonModelProps
	 * @param \QModel $syncItm
	 * @param \QModel|QModelArray $parent
	 * @param array $_bag
	 * @return \QModel
	 * @throws \Exception
	 */
	public static function GetRemoteSyncedData($data, $selector = true, $prop = null, $includeNonModelProps = false, $syncItm = null, $parent = null, 
		\SplObjectStorage &$_bag = null, $entityLoaded = false, array &$_all_objects = null)
	{
		//init the bag
		// bag will be used to set references but only for QModels and not for QModelArrays
		if ($_bag === null)
			$_bag = new \SplObjectStorage();

		if ($_all_objects === null)
		{
			$_all_objects = [];
			static::GetRemoteSyncedData_Populate_All_Objects($_all_objects, $syncItm);
		}
		
		// if we don't have data or we have scalar data then return it
		if (!$data || is_scalar($data) || is_resource($data))
			return $data;
		
		if (is_object($data) && isset($_bag[$data]))
			return $_bag[$data];

		// if data is not a QIModel it can be only object or array (from above we know that is not a scalar or a resource)
		if (!($data instanceof \QIModel))
		{
			# qvar_dumpk('$data', $data);
			# throw new \Exception('Still here ?!!?');
			$_isarr = is_array($data);
			
			if (!$_isarr)
			{
				if (\QAutoload::GetDevelopmentMode())
					qvar_dumpk('$data', $data);
				throw new \Exception('Still here ?!!?');
			}
			
			foreach ($data as $_k => $_v)
			{
				$_sd = ($_v && ($_v instanceof \QIModel)) ? 
						$_v::GetRemoteSyncedData($_v, $selector, $prop, $includeNonModelProps, $syncItm, $parent, $_bag, $entityLoaded, $_all_objects) : 
						\QModel::GetRemoteSyncedData($_v, $selector, $prop, $includeNonModelProps, $syncItm, $parent, $_bag, $entityLoaded, $_all_objects);
				$_isarr ? ($data[$_k] = $_sd) : ($data->{$_k} = $_sd);
			}
			
			return $data;
		}

		// if the selector is provided then parse it
		if (is_string($selector))
			$selector = qParseEntity($selector);

		$dcls = get_called_class();

		// use selector
		$use_selector = ($selector === true) ? $dcls::GetListingSyncEntity() : $selector;
		$is_str_use_selector = is_string($use_selector);
		$use_selector_str = $is_str_use_selector ? $use_selector : qImplodeEntity($use_selector);
		$use_selector_arr = $is_str_use_selector ? qParseEntity($use_selector) : $use_selector;

		$skipSyncSelector = $dcls::SkipSyncSelector();
		if ($skipSyncSelector && is_string($skipSyncSelector))
			$skipSyncSelector = qParseEntity($skipSyncSelector);

		// if the item was already processed then return it
		# $_dindx = $dcls . "~" . ($data->getId() ? $data->getId() : $data->getTemporaryId());
		# if (isset($_bag[$_dindx]))
		#	return $_bag[$_dindx];

		// we must know if the property is subpart (fk expanded)
		// if it is subpart there is no need to load by gid or setup gid property
		$_isSubpart = ($prop && $prop->storage && $prop->storage["dependency"] && ($prop->storage["dependency"] === "subpart"));

		// use gid for finding the element
		// we need to use gid when is not a subpart element (be aware that we still need to use gid if subpart element but in collection)
		$_useGid = (!$_isSubpart || ($prop && $prop->hasCollectionType()));

		// if we don't have sync item then look for it only if is not subpart
		// if the entity was previously loaded then don't load it again
		if (!$syncItm && $_useGid)
		{
			if ($entityLoaded)
			{
				$syncItm = $_all_objects[$data->getId()."|". get_class($data)];
			}
			else
			{
				# throw new \Exception('!$entityLoaded AAA');
				
				$owner = \Omi\App::GetCurrentOwner();
				if (!$owner)
					throw new \Exception("Cannot get sync data without owner!");

				$syncItmObj = new $dcls();
				if ($syncItmObj->_synchronizable)
				{
					//$selector
					//$syncItm = $dcls::QueryFirst("Id, Gid WHERE Gid=? AND Owner.Id=?", [$data->getId(), $owner->getId()]);

					$syncItm = $dcls::QueryFirst("Id, Gid" . ((strlen($use_selector_str) > 0) ? ", " . $use_selector_str : "") . 
						" WHERE Gid=? AND Owner.Id=?", [$data->getId(), $owner->getId()]);
				}
				else
				{
					// do nothing for now
				}
			}
		}
		// if we have the entity loaded is ok for first level - then we still need to do lookup for references & references in collection
		// if ($entityLoaded === true)
		// 	$entityLoaded = false;

		// if the sync item was not found create a new one
		if (!$syncItm)
		{
			$syncItm = new $dcls();

			if ($_useGid && $syncItm->_synchronizable)
			{
				// all models involved in sync mechanism needs to have the Gid column
				if (!method_exists($dcls, "setGid"))
					throw new \Exception("All models used in sync and not subpart needs to have the GID property defined!");

				// set the gid
				//echo "<div style='color: green;'>{$dcls}</div>";
				$syncItm->setGid($data->getId());
			}

			if ($syncItm->_synchronizable)
			{
				# 
			}
			else
				$syncItm->setId($data->getId());
		}
		
		// set remote id
		$syncItm->__rid = $data->getId();

		$dataCls = \QApp::GetDataClass();
		// do this only for removal for now
		if ($dataCls::$_SYNC_ITEMS_ON_PROCESS && ($data->getTransformState() == \QIModel::TransformDelete))
			$syncItm->setTransformState($data->getTransformState());

		// index here only QIModels
		$_bag[$data] = $syncItm;

		// go through properties and update data
		foreach ($data as $pName => $value)
		{
			if (in_array($pName, 
						['_wst','_ty','_qini','_ols','_found_on_merge','_ts','_tsx','_scf','_is_new','_sl','_tid','_lk','_gp',
							'_typeIdsPath','_tmpid']))
				continue;
			
			$_prop = $data->getModelType()->properties[$pName];

			// if we don't have prop (it means is a hidden property of the model) and we don't want to include non model prop or
			if ((!$_prop && !$includeNonModelProps) || 
				// prop exists but was not set or
				($_prop && !$data->wasSet($pName)) || 
					// is id or gid or owner or storage id
					in_array($pName, ["Id", "id", "_id", "Gid", "Owner", "MTime", "_keepInSync"]) || 
					// not in selector
					(!$selector || (is_array($selector) && !isset($selector["*"]) && !isset($selector[$pName]))))
			{
				//echo "<div style='color: red;'>SKIP {$pName}</div>";
				continue;
			}

			// if skip sync - keep it as it is
			if ($skipSyncSelector && is_array($skipSyncSelector) && isset($skipSyncSelector[$pName]))
			{
				if ($_prop)
				{
					$syncItm->{"set" . $pName}($value);
				}
				else
				{
					$syncItm->{$pName} = $value;
				}
				continue;
			}
			
			//if (\QModel::$Dump)
			//	echo "<div style='color: blue;'>Process: {$pName}</div>";

			// if we don't have property then it is a non model flag
			if (!$_prop)
			{
				$syncItm->{$pName} = \QModel::GetRemoteSyncedData($value, is_array($selector) ? $selector[$pName] : $selector, $_prop, 
						$includeNonModelProps, null, $syncItm, $_bag, $entityLoaded, $_all_objects);
				continue;
			}

			$_iscollection = $_prop->hasCollectionType();
			$_isreference = $_prop->hasReferenceType();

			// if we don't have value or we have value and the value is a scalar then setup the value and continue
			if (!$value || (!$_iscollection && !$_isreference))
			{
				$syncItm->{"set" . $pName}($value);
				continue;
			}

			// the property is collection
			if ($_iscollection)
			{
				// check if property is scalar
				$_isScalar = $_prop->isScalar();

				// get class
				$_vcls = get_class($value); // it should be QModelArray

				// we need to be able to sync one by one items in collection
				$_singleItmSync = ($data->{$pName} && $data->{$pName}->_singleSync);
				$_singleItm = $_singleItmSync ? reset($data->{$pName}) : null;

				// load collection data only if item has id
				if ((!$entityLoaded) && $syncItm->getId() && !$syncItm->{$pName})
				{
					# throw new \Exception('!$entityLoaded BBB');
					
					$coll_sync_selector = $use_selector_arr ? $use_selector_arr[$pName] : null;
					if ($coll_sync_selector && !is_string($coll_sync_selector))
						$coll_sync_selector = qImplodeEntity($coll_sync_selector);

					//if (\QModel::$Dump)
					//	qvardump("load collection {$pName}", $syncItm);

					$syncItm->query($_isScalar ? 
						$pName . ($_singleItmSync ? "" : "") : 
						//$pName . ".{Id, Gid" . ($_singleItmSync ? " WHERE Gid='" . (($_singleItm && $_singleItm->getId()) ? $_singleItm->getId() : 0)."'" : "") . " ORDER BY Id DESC}"
						$pName . ".{Id, Gid" . (($coll_sync_selector && (strlen($coll_sync_selector) > 0)) ? ", " . $coll_sync_selector : "") . ($_singleItmSync ? " WHERE Gid='" . (($_singleItm && $_singleItm->getId()) ? $_singleItm->getId() : 0)."'" : "") . " ORDER BY Id DESC}"
					);
				}

				// if we don't have collection data - init data here
				if (!$syncItm->{$pName})
					$syncItm->{"set" . $pName}(new $_vcls());

				//echo "<div style='color: red;'>{$pName}</div>";
				//qvardump($syncItm->{$pName});

				// just call get sync data and within the call we pass the parent - so the collection items will be pushed straight on parent 
				// by calling set{CollName}_Item_ method
				$value::GetRemoteSyncedData($value, is_array($selector) ? $selector[$pName] : $selector, $_prop, 
					$includeNonModelProps, $syncItm->{$pName}, $syncItm, $_bag, $entityLoaded, $_all_objects);
			}

			// if the property is reference and it is a subpart then query for data to update if already exists (avoid spamming the database)
			else if ($_isreference)
			{
				// load reference data only if id
				if ((!$entityLoaded) && $syncItm->getId() && !$syncItm->{$pName} && ($_prop->storage && $_prop->storage["dependency"] && ($_prop->storage["dependency"] === "subpart")))
				{
					# throw new \Exception('!$entityLoaded CCC');
					
					$ref_sync_selector = $use_selector_arr ? $use_selector_arr[$pName] : null;
					if ($ref_sync_selector && !is_string($ref_sync_selector))
						$ref_sync_selector = qImplodeEntity($ref_sync_selector);

					//if (\QModel::$Dump)
					//	qvardump("load reference {$pName}", $syncItm);

					//$syncItm->query($pName . ".Id");
					$syncItm->query($pName . ".{Id" . (($ref_sync_selector && (strlen($ref_sync_selector) > 0)) ? ", " . $ref_sync_selector : "") . "}");
				}
				
				$syncItm->{"set".$pName}($value::GetRemoteSyncedData($value, is_array($selector) ? $selector[$pName] : $selector, $_prop, $includeNonModelProps,
						$syncItm->{$pName}, $syncItm, $_bag, $entityLoaded, $_all_objects));
			}
		}
		return $syncItm;
	}
	
	/**
	 * We need to setup reference data to modules
	 * Ex: We have a company that has an address linked to it and the address is linked to a country, a county and a city
	 * When we will send the data to be remotely merged, we need to send the country, county and the city on the app as well so they would be added to remote app
	 * 
	 * @param \Omi\App $appData
	 * @param mixed $currentData
	 * @param array $_bag
	 * @param array $_byAppPropItems
	 * 
	 * @return null
	 */
	public static function SetupToSendData($appData, $currentData = null, &$_bag = null, &$_byAppPropItems = null, string $path = "")
	{
		if (!$_bag)
			$_bag = [];

		if (!$_byAppPropItems)
			$_byAppPropItems = [];

		// if we don't have current data then use the app data as initial data
		if (!$currentData)
		{
			$currentData = $appData;
			// index existing app data info for avoid duplicating data
			foreach ($currentData as $_k => $_v)
			{
				if (($_k{0} === "_") || !$_v || is_scalar($_v))
					continue;

				if (!qis_array($_v))
					$_v = [$_v];

				if (!$_byAppPropItems[$_k])
					$_byAppPropItems[$_k] = [];

				foreach ($_v as $_eitm)				
					$_byAppPropItems[$_k][$_eitm->getId()] = $_eitm;
			}
		}

		// if we don't have data or we have scalar data then return it
		if (!$currentData || is_scalar($currentData) || is_resource($currentData))
			return;
		
		// if data is not a QIModel it can be only object or array (from above we know that is not a scalar or a resource)
		if (!($currentData instanceof \QIModel))
		{
			// call again the method in a loop
			foreach ($currentData as $value)
			{
				($value && ($value instanceof \QIModel)) ? 
					$value::SetupToSendData($appData, $value, $_bag, $_byAppPropItems, $path) : 
					\QModel::SetupToSendData($appData, $value, $_bag, $_byAppPropItems, $path);
			}
			return;
		}
		// if data is \QModelArray then call the method on \QModelArray class
		else if ($currentData instanceof \QModelArray)
		{
			$currentData::SetupToSendData($appData, $currentData, $_bag, $_byAppPropItems, $path);
			return;
		}

		// if the object was already processed return
		if ($_bag[$currentData->getTemporaryId()])
			return;

		// store the object to bag
		$_bag[$currentData->getTemporaryId()] = $currentData;

		// get the model type
		$modelType = null;
		
		$skipExpandSelector = $currentData::SkipExpandSelector();
		if ($skipExpandSelector && is_string($skipExpandSelector))
			$skipExpandSelector = qParseEntity($skipExpandSelector);

		// go through properties and if we find properties that are collections or references that are not subpart 
		// (it means that they are picked from dropdown - they are added into data app) we will store it onto collect data
		foreach ($currentData as $key => $value)
		{
			if (!($value instanceof \QIModel))
				continue;
			// if the property is a hidden property or we don't have value or the value is scalar then continue
			if (($key{0} === "_") || !$currentData->wasSet($key) || !$value || is_scalar($value) || 
				(($skipExpandSelector && is_array($skipExpandSelector) && isset($skipExpandSelector[$key]))))
				continue;

			# if ($key === "Owner")
			{
				//$currentData->setOwner(null);
				//continue;
			}
			
			// determine prop

			// we need to check only references and collections
			if (!$modelType)
				$modelType = $currentData->getModelType();
			if (!$modelType)
				throw new \Exception('Unable to determine model type');
			$_prop = $modelType->properties[$key];
			# if (!$_prop || (!$_prop->hasReferenceType() && !$_prop->hasCollectionType()))
			#	continue;
			if (!$_prop)
			{
				if (\QAutoload::GetDevelopmentMode())
					qvar_dumpk('$currentData / $key', $currentData, $key);
				throw new \Exception('Bad data X1!');
			}

			// if the property is not subpart then it needs to be linked to app
			# $_isSubpart = ($_prop->storage && $_prop->storage["dependency"] && ($_prop->storage["dependency"] === "subpart"));
			$prop_path = ($path ? $path."." : "").$key;
			$expand_in_props = static::$Sync_Mapping[$prop_path];
			
			$act_on_elements = ($value instanceof \QIModelArray) ? $value : [$value];
			foreach ($act_on_elements as $itm)
			{
				$itm_class = get_class($itm);
				$eep = ($expand_in_props === false) ? false : ($expand_in_props[$itm_class] ?: $expand_in_props[0]);
				if (is_string($eep))
					$eep = [$eep];
				
				if ($path && ($eep === null))
				{
					# for now to see things
					$_isSubpart = ($_prop->storage && $_prop->storage["dependency"] && ($_prop->storage["dependency"] === "subpart"));
					$possible_props = $currentData::GetAppProperty_For_Sync($_prop->name, $itm);
					throw new \Exception('Sync rule not defined for: '.$prop_path." | subpart: ".
							($_isSubpart ? 'true' : 'false')." | props: ".(var_export($possible_props, true))
							." | class: ".(get_class($itm)));
				}

				foreach ($eep ?: [] as $itmAppProp)
				{
					// init the main data for the app property if necessary
					if (!$appData->$itmAppProp)
						$appData->{"set{$itmAppProp}"}(new \QModelArray());

					if (!$_byAppPropItems[$itmAppProp])
						$_byAppPropItems[$itmAppProp] = [];

					if (!isset($_byAppPropItems[$itmAppProp][$itm->getId()]))
					{
						$appData->{"set{$itmAppProp}_Item_"}($itm);
						$_byAppPropItems[$itmAppProp][$itm->getId()] = $itm;
					}
				}
			}
			
			foreach ($act_on_elements as $itm)
				$itm::SetupToSendData($appData, $itm, $_bag, $_byAppPropItems, $prop_path);
			
			if (false)
			{
				// if is not subpart and we have app property set up in data model
				if (!$_isSubpart)
				{
					// if is collection - save all items
					// make sure that they are not duplicated
					if ($_prop->hasCollectionType())
					{
						foreach ($value as $itm)
						{
							if (!($itm instanceof \QModel))
								continue;

							if (($itm instanceof \Omi\Comm\Reseller) && ($_prop->name === 'Owner'))
							{
								# $itmAppProp = 'Suppliers';
								throw new \Exception('This should not happen !');
							}
							else
								$itmAppProp = $currentData::GetAppProperty_For_Sync($_prop->name, $itm);

							if (!$itmAppProp)
							{
								//qvardump($currentData, $_prop->name);
								throw new \Exception("App property [{$itmAppProp}] not found!");
							}

							// init the main data for the app property if necessary
							if (!$appData->$itmAppProp)
								$appData->{"set{$itmAppProp}"}(new \QModelArray());

							if (!$_byAppPropItems[$itmAppProp])
								$_byAppPropItems[$itmAppProp] = [];

							if (!isset($_byAppPropItems[$itmAppProp][$itm->getId()]))
							{
								$appData->{"set{$itmAppProp}_Item_"}($itm);
								$_byAppPropItems[$itmAppProp][$itm->getId()] = $itm;
							}
						}
					}
					else if ($_prop->hasReferenceType())
					{
						// determine the app property for the current property
						if (($value instanceof \Omi\Comm\Reseller) && ($_prop->name === 'Owner'))
						{
							qvar_dumpk("ddddd", $currentData, $currentData->_synchronizable, $value);
							throw new \Exception('yyeexd');
							$appProperty = 'Suppliers';
						}
						else
							$appProperty = $currentData::GetAppProperty_For_Sync($_prop->name, $value);

						//echo "<div style='color: red;'>{$appProperty}</div>";
						if (!$appProperty)
						{
							//qvardump($currentData, $_prop->name);
							throw new \Exception("App property not found!");
						}

						// init the main data for the app property if necessary
						if (!$appData->$appProperty)
							$appData->{"set{$appProperty}"}(new \QModelArray());

						if (!$_byAppPropItems[$appProperty])
							$_byAppPropItems[$appProperty] = [];

						// if is reference save it but again make sure that was not saved before - avoid duplicates
						if (!isset($_byAppPropItems[$appProperty][$value->getId()]))
						{
							$appData->{"set{$appProperty}_Item_"}($value);
							$_byAppPropItems[$appProperty][$value->getId()] = $value;
						}

					}
				}
				// setup to send data on children
				$value::SetupToSendData($appData, $value, $_bag, $_byAppPropItems);
			}
		}
	}
	
	/**
	 * Returns the app property for the provided property
	 * The method depends on the instance that calls it even if static it must be called on right type and provide the right property
	 * 
	 * @param string|\QModelProperty $_prop
	 * @param \QModel $model
	 * @return string
	 */
	public static function GetAppProperty_For_Sync($_prop, $model = null)
	{
		// the model class is in fact the called class (the method depends on the class that is triggered)
		$modelCls = get_called_class(); 
		if ($modelCls === \QApp::GetDataClass())
			return $_prop;

		// get the model type for current class
		$modelType = static::GetTypeByName($modelCls);

		// if the property is string load the \QModelProperty instance
		if (is_string($_prop))
			$_prop = $modelType->properties[$_prop];

		if (!$_prop)
		{
			//qvardump($modelCls, $cp, $_prop);
			throw new \Exception("Property not found/provided!");
		}

		// get the app data type
		$appDataType = \QModel::GetTypeByName(\QApp::GetDataClass());

		// get the property options pool (if the options pool is not declared then the first occurence of the model type will be returned)
		$optionsPool = $modelCls::GetOptionsPool($_prop->name);

		// if we have options pool defined return it
		if ($optionsPool && ($appProp = reset($optionsPool)))
			return $appDataType->properties[$appProp] ? $appDataType->properties[$appProp]->name : null;
		
		$p_types = $_prop->hasCollectionType() ? 
			$_prop->getCollectionType()->getAllInstantiableReferenceTypes() : 
			$_prop->getAllInstantiableReferenceTypes();

		if (is_array($p_types))
		{
			$f_type = null;
			$model_cls = get_class($model);
			foreach ($p_types ?: [] as $cls)
			{
				if ($cls === $model_cls)
				{
					$f_type = $cls;
					break;
				}
			}

			if ($f_type)
				$p_types = [$f_type => $f_type];
		}

		// get the possible properties for types
		$props_list = static::GetAppPropertyForTypes_For_Sync($p_types);
		
		if (!$props_list)
		{
			return null;
		}
		else if (count($props_list) === 1)
		{
			return reset($props_list);
		}
		else
		{
			# throw new \Exception("Unable to determine APP property for: ".json_encode($p_types)." | PROPS: | ".json_encode($props_list));
			# return $type;
			return $props_list;
		}
	}
	
	public static function GetAppPropertyForTypes_For_Sync($types)
	{
		if (is_scalar($types))
			$types = [$types => $types];
		
		// get the app data type
		$appDataType = \QModel::GetTypeByName(\QApp::GetDataClass());
		
		$ret = [];
		// go thourgh app data properties and return first option that matches property types
		foreach ($appDataType->properties as $prop)
		{
			if ($prop->storage["none"])
				continue;

			$ct = $prop->getCollectionType();
			if (!$ct)
				continue;

			$propTypes = $ct->getAllInstantiableReferenceTypes();
			$res = array_intersect_key($propTypes, $types);
			if ($res === $types)
			{
				# return $prop->name;
				$ret[$prop->name] = $prop->name;
			}
		}
		
		return $ret;
	}
	
	
	/**
	 * Extracts data from a JSON string
	 * 
	 * @param string $json_string
	 * @param string $type
	 * @param string|array $selector In this case a null selector means we will not use a selector
	 * 
	 * @return QIModel
	 */
	public static function FromJSON($json_string, $type = "auto", $selector = null, $include_nonmodel_properties = false)
	{
		$data_array = json_decode($json_string, true);
		$type = ($type === "auto") ? get_called_class() : $type;
		return self::FromArray($data_array, $type, $selector, $include_nonmodel_properties);
	}
	
	/**
	 * Extracts data from a simple PHP array
	 * 
	 * @param array $array
	 * @param string $type Examples: Order, Order[], 
	 * @param string|array $selector In this case a null selector means we will not use a selector
	 * 
	 * @return QIModel|QIModel[]
	 */
	public static function FromArray($array, $type = "auto", $selector = null, $include_nonmodel_properties = false)
	{
		if ($array === null)
			return null;
		else if (!is_array($array))
			throw new Exception("Input argument \$array must be an array or null");
		
		$using_called_class = null;
		$type = ($type === "auto") ? ($using_called_class = get_called_class()) : $type;
		
		if (is_string($selector))
			$selector = qParseEntity($selector);
		
		// we also accept $type = "array"
		if ($type === "array")
		{
			$is_array = true;
			// switching back to auto
			$type = "auto";
		}
		else if (substr($type, -2, 2) === "[]")
		{
			$is_array = true;
			$type = substr($type, 0, -2);
		}
		else if ($using_called_class && (key($array) === 0))
			// we have an non-associative array inside an array, that means we have an array of elements
			$is_array = true;
		else if (($arr_class = $array["_ty"]) && class_exists($arr_class) && qIsA($arr_class, "QIModelArray"))
			$is_array = true;
		else
			$is_array = false;
		
		if ($is_array)
		{
			if (($arr_class ?: $array["_ty"]) !== null)
			{
				if (class_exists($arr_class) && qIsA($arr_class, "QIModelArray"))
					$ret = new $arr_class();
				unset($array["_ty"]);
			}
			else
				$ret = new QModelArray();

			$items_array = $array["_items"] ?: $array;
			foreach ($items_array as $k => $item)
			{
				// repeat logic
				if (is_array($item))
				{
					$i_class = (($c = $item["_ty"]) && class_exists($c)) ? $c : (((($type{0} === "\\") || ($type{0} !== strtolower($type{0}))) && class_exists($type)) ? $type : null);
					$ret[$k] = static::FromArray($item, $i_class, $selector, $include_nonmodel_properties);
				}
				else
					$ret[$k] = $item;
			}
			return $ret;
		}
		else
		{
			$class = (($c = $array["_ty"]) && class_exists($c)) ? $c : (((($type{0} === "\\") || ($type{0} !== strtolower($type{0}))) && class_exists($type)) ? $type : null);
			if (!$class)
				throw new Exception("Unable to determine type for input data");
			$obj = new $class();
			if (($obj_id = $array["_id"]) !== null)
				$obj->setId($obj_id);
			$refs = [];
			$obj->extractFromArray($array, $selector, null, $include_nonmodel_properties, $refs);
			return $obj;
		}
	}
	
	public function extractFromArray($array, $selector = null, $parent = null, $include_nonmodel_properties = false, &$refs = null)
	{
		$all_keys = $selector && ($selector["*"] !== null);
		$type_inf = QModelQuery::GetTypesCache(get_class($this));
		
		$wst = $array["_wst"];
		
		foreach($array as $k => $v)
		{
			switch ($k)
			{
				case "_id":
				{
					if ($v !== null)
						$this->setId($v);
					break;
				}
				case "_ty":
				case "_tmpid":
				case "_iro":
				{
					// ignore
					break;
				}
				case "_ts":
				{
					$this->_ts = (int)$v;
					break;
				}
				case "_tsp":
				{
					$this->_tsp = $v;
					break;
				}
				case "_uploads":
				{
					if (is_array($v))
					{
						foreach ($v as $upload_key => $upload_info)
						{
							$prop_inf = $this->getModelType()->properties[$upload_key];
							if ($prop_inf)
							{
								$upload_Path = $prop_inf->storage["filePath"];
								if ($upload_Path && ($upload_info["error"] == 0) && file_exists($upload_info["tmp_name"]))
								{
									$save_dir = rtrim($upload_Path, "\\/")."/";
									if (!is_dir($save_dir))
										qmkdir($save_dir);
									
									$save_path_fn = pathinfo($upload_info["name"], PATHINFO_FILENAME);
									$save_path_ext = pathinfo($upload_info["name"], PATHINFO_EXTENSION);
									$index = 0;
									// make sure we don't overwrite
									while (file_exists($save_path = $save_dir.$save_path_fn.($index ? "-".$index : "").".".$save_path_ext))
											$index++;
									move_uploaded_file($upload_info["tmp_name"], $save_path);
									
									$upload_Mode = $prop_inf->storage["fileMode"];
									if ($upload_Mode)
										chmod($save_path, octdec($upload_Mode));
									$upload_withPath = $prop_inf->storage["fileWithPath"];
									$property_value = $upload_withPath ? $save_path : basename($save_path);
									$this->{"set{$upload_key}"}($property_value);
									$array[$upload_key] = $property_value;
								}
							}
						}
					}
					break;
				}
				case "_rowi":
				{
					$this->_rowi = $v;
					break;
				}
				default:
				{
					// not in the selector
					// or we have wst flag and the property was not set
					if (($k{0} === "_") || (($selector !== null) && (!$all_keys) && ($selector[$k] === null)) || ($wst && !$wst[$k]))
						break;

					$ty = gettype($v);
					
					/* "boolean" "integer" "double" "string" "array" "object" "resource" "NULL" "unknown type"*/
					switch ($ty)
					{
						case "string":
						case "NULL":
						case "integer":
						case "boolean":
						case "double":
						{
							if (is_string($v) && (strtolower($v) === "null"))
								$v = null;

							if ($this->getModelType()->properties[$k])
								$this->{"set{$k}"}($v);
							else
								$this->{$k} = $v;
							break;
						}
						case "array":
						{
							$expected_type = null;
							if (($vc = $v["_ty"]) && class_exists($vc))
							{
								$expected_type = $vc;
								unset($v["_ty"]);
							}
							else
							{
								$prop_inf = $type_inf[$k];
								if ($prop_inf["[]"])
									// is collection
									$expected_type = "\\QModelArray";
								else
								{
									if (!$prop_inf["#"])
									{
										qvardump($k, $v);
										throw new \Exception("Expected type cannot be identified!");
									}
									$expected_type = "\\".reset($prop_inf["#"]);
								}
							}
							
							if ($expected_type && class_exists($expected_type))
							{
								$obj_id = is_array($v) ? ($v["_id"] ?: ($v["Id"] ?: ($v["id"] ?: $v["ID"]))) : null;
								if ($obj_id)
									$obj = $refs[$obj_id][$expected_type] ?: ($refs[$obj_id][$expected_type] = new $expected_type());
								else
									$obj = new $expected_type();
								
								// if we have _wst we should setup only properties that were set when encode to array
								$prop_selector = $selector[$k];
								
								if ($obj instanceof QIModelArray)
								{
									$obj->setModelProperty($this->getModelType()->properties[$k], $this);
									$this->{"set{$k}"}($obj);
									
									if (isset($v["_tsp"]))
									{
										$obj->_tsp = $v["_tsp"];
										unset($v["_tsp"]);
									}
									if (isset($v["_rowi"]))
									{
										$obj->_rowi = $v["_rowi"];
										unset($v["_rowi"]);
									}
									if (isset($v["_iro"]))
									{
										// $obj->_rowi = $v["_iro"];
										unset($v["_iro"]);
									}
									
									// for backward compatibility
									if (isset($v["_items"]))
									{
										$use_v = $v["_items"];
										unset($v["_items"]);
									}
									else
										$use_v = $v;

									/*
									if (($use_v = $v["_items"]))
									{
										unset($v["_items"]);
									}
									else
										$use_v = $v;
									*/

									foreach ($use_v as $vk => $vv)
									{
										if (is_array($vv))
										{
											$v_expected_type = null;
											if (($vc = $vv["_ty"]) && class_exists($vc))
												$v_expected_type =  $vc;
											else
												$v_expected_type = reset($type_inf[$k]["[]"]["#"]);

											if ($v_expected_type && class_exists($v_expected_type))
											{
												$v_obj_id = is_array($vv) ? ($vv["_id"] ?: ($vv["Id"] ?: ($vv["id"] ?: $vv["ID"]))) : null;
												if ($v_obj_id)
													$v_obj = $refs[$v_obj_id][$v_expected_type] ?: ($refs[$v_obj_id][$v_expected_type] = new $v_expected_type());
												else
													$v_obj = new $v_expected_type();
												
												if ($v_obj instanceof QIModel)
													$v_obj->extractFromArray($vv, $prop_selector, $obj, $include_nonmodel_properties, $refs);

												$this->{"set{$k}_Item_"}($v_obj, $vk);
											}
											else 
												$this->{"set{$k}_Item_"}($vv, $vk);
										}
										else
										{
											$this->{"set{$k}_Item_"}($vv, $vk);
										}
									}
									
									# here we unset all _rowi,_tsp where there is no data, to avoid issues if we get a populate
									{
										$unset_rowis = [];
										foreach ($obj->_rowi ?: [] as $rowi_itm_pos => $rowi_itm_id)
										{
											if (!isset($obj[$rowi_itm_pos]))
												$unset_rowis[] = $rowi_itm_pos;
										}
										foreach($unset_rowis as $unsetri_key)
											unset($obj->_rowi[$unsetri_key]);
										
										$unset_tsps = [];
										foreach ($obj->_tsp ?: [] as $tsp_itm_pos => $tsp_itm_state)
										{
											if (!isset($obj[$tsp_itm_pos]))
												$unset_tsps[] = $tsp_itm_pos;
										}
										foreach($unset_tsps as $unsettsp_key)
											unset($obj->_tsp[$unsettsp_key]);
									}
									
									/*
									if (\QAutoload::GetDevelopmentMode())
									{
										# we need to set the transform state info on the items where possible
										if ($obj->_tsp && $obj->_rowi)
										{
											foreach ($obj->_tsp as $_tsp_pos => $_tsp_state)
											{
												if (($_tsp_state & \QModel::TransformDelete) && ($obj[$_tsp_pos] instanceof \QIModel))
													$obj[$_tsp_pos]->setTransformState($_tsp_state);
											}
										}
									}
									*/
								}
								else if ($obj instanceof QIModel)
								{
									$obj->extractFromArray($v, $prop_selector, $this, $include_nonmodel_properties, $refs);
									$this->{"set{$k}"}($obj);
								}
								else
									$this->{"set{$k}"}($obj);
							}
							else
								$this->{"set{$k}"}($v);
							
							break;
						}
						case "object":
						{
							throw new Exception("should not be, to do ?!");
						}
					}
				}
			}
		}
	}
	
	protected static $Sync_Mapping = [
		"Offers.NuviaOffer" => ["Nuvia_Offers"],
		"Offers.NuviaOffer.Item" => false,
		"Offers.NuviaOffer.Items" => false,
		"Offers.NuviaOffer.Item.Merch" => ['Omi\Nuvia\Service' => ["Nuvia_Services"], 'Omi\Nuvia\Product' => ["Nuvia_Products"]],
		"Offers.Items" => false,
		"Offers.Items.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Items.Merch.ProvisioningSettings" => false,
		"Offers.Items.Merch.Content" => false,
		"Offers.Products" => false,
		"Offers.Services" => false,
		
		"Offers.Products.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Services.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		
		"Offers.Content" => false,
		"Offers.Items.Merch.DefaultOffer" => ["Offers"],
		"Offers.Items.Merch.Manufacturer" => ["Manufacturers"],
		"Offers.Items.Merch.Term" => ["Terms"],
		"Offers.Items.Merch.Categories" => ["MerchCategories"],
		"Offers.Items.Merch.Categories.Content" => false,
		"Offers.Items.Merch.IncompatibleWith" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Items.Merch.AvailableOptions" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Items.Merch.AvailableOptions.ProvisioningSettings" => false,
		"Offers.Items.Merch.AvailableOptions.Content" => false,
		"Offers.Items.Merch.AvailableOptions.Categories" => ["MerchCategories"],
		
		"Offers.Items.Merch.Categories.Parent" => ["MerchCategories"],
		"Offers.Items.Merch.Categories.Parent.Parent" => ["MerchCategories"],
		"Offers.Items.Merch.Categories.Parent.Parent.Content" => false,
		"Offers.Items.Merch.Categories.Parent.Content" => false,

		"Offers.Items.Merch.IncompatibleWith.Content" => false,
		"Offers.Items.Merch.IncompatibleWith.Categories.Content" => false,
		"Offers.Items.Merch.IncompatibleWith.Categories" => ["MerchCategories"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Parent" => ["MerchCategories"],
		"Offers.Items.Merch.IncompatibleWith.Manufacturer" => ["Manufacturers"],
		"Offers.Items.Merch.IncompatibleWith.IncompatibleWith" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		
		"Offers.Items.Merch.DefaultOffer.Items" => false,
		"Offers.Items.Merch.DefaultOffer.Items.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Items.Merch.DefaultOffer.Content" => false,		
		"Offers.Items.Merch.IncompatibleWith.Categories.Parent.Parent" => ["MerchCategories"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Parent.Content" => false,

		"Offers.Items.Merch.DefaultOffer.Items.Merch.ProvisioningSettings" => false,
		"Offers.Items.Merch.DefaultOffer.Items.Merch.Content" => false,

		"Offers.Items.Merch.DefaultOffer.Items.Merch.DefaultOffer" => ["Offers"],

		"Offers.Items.Merch.DefaultOffer.Services" => false,
		"Offers.Items.Merch.DefaultOffer.Products" => false,
		
		"Offers.Items.Merch.DefaultOffer.Services.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		"Offers.Items.Merch.DefaultOffer.Products.Merch" => ['Omi\Comm\Service' => ["Services"], 'Omi\Comm\Product' => ["Products"]],
		
		# Owners
		"Offers.Owner" => ["Suppliers"],
		"Offers.Content.Owner" => ["Suppliers"],
		"Offers.Products.Merch.Owner" => ["Suppliers"],
		"Offers.Products.Owner" => ["Suppliers"],
		# "Owner" => ["Suppliers"],
		"Offers.Items.Merch.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Owner" => ["Suppliers"],
		"Offers.Items.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Manufacturer.Owner" => ["Suppliers"],
		"Offers.Services.Owner" => ["Suppliers"],
		"Offers.Services.Merch.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Categories.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Categories.Owner" => ["Suppliers"],
		"Offers.Items.Merch.AvailableOptions.Owner" => ["Suppliers"],
		
		"Offers.Items.Merch.Categories.Parent.Parent.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Categories.Parent.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Manufacturer.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.Categories.Parent.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.DefaultOffer.Items.Owner" => ["Suppliers"],
		"Offers.Items.Merch.DefaultOffer.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.DefaultOffer.Owner" => ["Suppliers"],
		
		"Offers.Items.Merch.Categories.Parent.Parent.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Parent.Owner" => ["Suppliers"],
		"Offers.Items.Merch.IncompatibleWith.Categories.Parent.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.AvailableOptions.Content.Owner" => ["Suppliers"],
		
		"Offers.Items.Merch.DefaultOffer.Items.Merch.Content.Owner" => ["Suppliers"],
		"Offers.Items.Merch.DefaultOffer.Items.Merch.Owner" => ["Suppliers"],
		
		"PricingIntervals.Owner" => ["Suppliers"],
		"PricingIntervals.Owner.WhiteLabel" => false,
		
		# Partners
		
		"Resellers.HeadOffice" => false,
		"Resellers.HeadOffice.Country" => ["Countries"],
		"Resellers.HeadOffice.County" => ["Counties"],
		"Resellers.HeadOffice.City" => ["Cities"],
		"Resellers.Sites.Address.Country" => ["Countries"],
		"Resellers.Sites.Address.County" => ["Counties"],
		"Resellers.Sites.Address.City" => ["Cities"],
		"Resellers.Suppliers" => ["Suppliers"],
		"Resellers.Suppliers.WhiteLabel" => false,
		"Resellers.WhiteLabel" => false,
		"Resellers.Sites" => false,
		"Resellers.Sites.Address" => false,
		
	];
}

