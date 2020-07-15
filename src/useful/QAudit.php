<?php

class QAudit extends \QModel
{
	// protected static $db_conn = null;
	protected static $store_file = null;
	protected static $store_file_name = null;
	protected static $parents = [];
	protected static $request_id = null;
	// protected static $async_started = false;
	protected static $buffer = "";
	
	protected static $audit_call_index = 1;
	protected static $audit_backup_index = 1;
	
	public static $total_time = 0;
	public static $ForceBacktrace = false;

	public static function Audit($class, $method, $type, $data, $uid = null, $end_parent = false, $with_backtrace = false)
	{
		return;
		$t0 = microtime(true);
		
		static::EnsureConnection();
		
		$parent_id = static::$parents ? end(static::$parents) : null;
		
		/*fwrite(static::$store_file, ",\n");
		fwrite(static::$store_file, json_encode([
					"Date" => microtime(true),
					"Request" => static::$request_id,
					"Class" => $class,
					"Method" => $method,
					"Type" => $type,
					"Data" => $data,
					"UID" => $uid,
					"Parent" => $parent_id,
					"Ends" => $end_parent
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));*/
		static::$buffer .= ",\n".json_encode([
					"Date" => microtime(true),
					"Request" => \QWebRequest::GetRequestId(),
					"Class" => $class,
					"Method" => $method,
					"Backtrace" => ($with_backtrace || static::$ForceBacktrace) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 128) : null,
					"Type" => $type,
					"Data" => $data,
					"UID" => $uid,
					"Parent" => $parent_id,
					"Ends" => $end_parent
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (strlen(static::$buffer) >= (128 * 1024))
		{
			// write buffer
			fwrite(static::$store_file, static::$buffer);
			static::$buffer = null;
		}
		
		static::$total_time += (microtime(true) - $t0);
	}
	
	public static function CleanupAsync()
	{
		return;
		$links = $errors = $reject = [static::$db_conn];
		while (!mysqli_poll($links, $errors, $reject, 1))
			continue;
		static::$db_conn->reap_async_query();
	}
	
	public static function Cleanup()
	{
		return;
		if (static::$store_file)
		{
			if (static::$buffer)
			{
				fwrite(static::$store_file, static::$buffer);
				static::$buffer = null;
			}
			fwrite(static::$store_file, "]");
			fclose(static::$store_file);
			static::$store_file = null;
			static::$store_file_name = null;
		}
	}
	
	public static function CreateAuditTable()
	{
		return;
		static::EnsureConnection();
		
		static::$db_conn->query("CREATE TABLE IF NOT EXISTS `\$Audit` (
  `\$id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Request` int(10) unsigned NOT NULL DEFAULT '0',
  `Date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CalledClass` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `CalledMethod` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `Type` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `Data` longtext COLLATE utf8_unicode_ci NOT NULL,
  `Uid` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ParentUid` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `Ends` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`\$id`),
  UNIQUE KEY `CalledClass` (`CalledClass`,`CalledMethod`),
  KEY `Request` (`Request`),
  KEY `Date` (`Date`),
  KEY `Type` (`Type`),
  KEY `ParentUid` (`ParentUid`),
  KEY `Uid` (`Uid`,`Ends`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=REDUNDANT AUTO_INCREMENT=1 ;
");
	}
	
	protected static function EnsureConnection()
	{
		return;
		if (static::$store_file !== null)
			return;
		
		$audit_dir = "_audits/calls/".date("Y-m-d")."/";
		static::$store_file_name = $file_name = $audit_dir.date("H.i.s")."-".\QWebRequest::GetRequestId()."-".(static::$audit_call_index++).".json";
		if (!is_dir($audit_dir))
			qmkdir($audit_dir);
		
		static::$store_file = fopen($file_name, "wt");
		// proper chmod
		chmod($file_name, 0000);
		fwrite(static::$store_file, "[null");
		register_shutdown_function(["QAudit", "Cleanup"]);
	}
	
	public static function AuditRequest()
	{
		return;
		$audit_dir = "_audits/requests/".date("Y-m-d")."/";
		$file_name = $audit_dir.date("H.i.s")."-".\QWebRequest::GetRequestId().".json.gz";
		if (!is_dir($audit_dir))
			qmkdir($audit_dir);
		
		file_put_contents($file_name, 
				gzencode(json_encode(["ID" => \QWebRequest::GetRequestId(), "SERVER" => $_SERVER, "POST" => $_POST, "SESSION" => $_SESSION, "FILES" => $_FILES], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
		// proper chmod
		chmod($file_name, 0000);
	}
	
	public static function BackupTransform(QIModel $model, $selector = "*", $parameters = null, $with_backtrace = false)
	{
		return;
		if (!$model->getId())
			return;
		
		$t0 = microtime(true);
		
		if (is_string($selector))
			$selector = qParseEntity($selector);
		else if (!is_array($selector))
		{
			if ($selector)
				$selector = qParseEntity("*");
			else
				// we do not backup if we don't know how deep
				return false;
		}

		$transform_state = isset($parameters["ts"]) ? $parameters["ts"] : null;
		$binds = [];
		list($query, $new_selector) = static::BackupTransformTraverseModel([$model], $selector, $transform_state, $binds, ($model instanceof \QIModelArray));
		$query .= " WHERE Id=?";
		$binds[] = $model->getId();

		$m_class = get_class($model);
		$db_model = new $m_class();
		$db_model->setId($model->getId());

		try {
			$db_model->query($query, $binds);
		}
		catch (\Exception $ex)
		{
			qvardump($selector);
			//qvardump($db_model, $query, $binds);
			throw $ex;
			
		}
		
		// now save the data
		// $t0 = microtime(true);
		$audit_dir = "_audits/transforms/".date("Y-m-d")."/".date("H.i.s", $_SERVER["REQUEST_TIME"])."-".\QWebRequest::GetRequestId()."/";
		$file_name = $audit_dir.date("H.i.s").".".(static::$audit_backup_index++).".json.gz";
		if (!is_dir($audit_dir))
			qmkdir($audit_dir);
		
		$parent_id = static::$parents ? end(static::$parents) : null;
		
		$header = json_encode([
					"Date" => microtime(true),
					"Request" => \QWebRequest::GetRequestId(),
					"Class" => $m_class,
					"Backtrace" => ($with_backtrace || static::$ForceBacktrace) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 128) : null,
					"Type" => "transform",
					"Parameters" => ["selector" => $selector, "ts" => $transform_state],
					"Parent" => $parent_id
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		
		file_put_contents($file_name, 
				gzencode("[{$header},".$db_model->toJSON($new_selector, false, true, true, true)."]"));
		// proper chmod
		chmod($file_name, 0000);
		// $t1 = microtime(true);
		static::$total_time += (microtime(true) - $t0);
	}
	
	protected static function BackupTransformTraverseModel($models, $selector, $ts, &$binds, $is_collection = false)
	{
		return;
		// $action = ($ts !== null) ? $ts : (($model->_ts !== null) ? $model->_ts : QModel::TransformMerge);
		$props = [];
		$sub_sels = [];
		$ids = [];
		$first_sel_loop = true;
		$collection_inf = [];
		
		if (($has_star = (($selector["*"] !== null) || ($ts & \QModel::TransformDelete))))
			unset($selector["*"]);
		$types_inf = [];
		
		foreach ($models as $m)
		{
			// for delete we need to save everything
			if ((!$has_star) && ($ts === null) && ($m->_ts & \QModel::TransformDelete))
			{
				$has_star = true;
				$first_sel_loop = true;
			}
			
			$m_class = get_class($m);
			if ($has_star && (!$types_inf[$m_class]))
			{
				$types_inf[$m_class] = $info = QModelQuery::GetTypesCache($m_class);
				foreach ($info as $p_name => $v)
				{
					if (($p_name{0} !== '#') && ($p_name{1} !== '%') && ($selector[$p_name] === null))
						$selector[$p_name] = [];
				}
			}
			
			foreach ($selector as $sk => $sv)
			{
				$props[$sk] = [];
				if ($sv)
				{
					if ($first_sel_loop)
						$sub_sels[$sk] = $sv;

					$val = $m->{$sk};
					if (($val instanceof \QIModelArray) || is_array($val))
					{
						if (!$collection_inf[$sk])
							$collection_inf[$sk] = true;
						foreach ($val as $itm)
						{
							if ($itm instanceof \QIModel)
								$props[$sk][] = $itm;
						}
					}
					else if ($val instanceof \QIModel)
						$props[$sk][] = $val;
				}
			}
			$first_sel_loop = false;
			
			$id = $m->getId();
			if ($id)
				$ids[$id] = $id;
			else // if merge by
			{
				// @todo
			}
		}
		
		$q = "";
		
		foreach ($props as $name => $p_models)
		{
			$q .= ",".$name;
			if ($p_models)
			{
				$q .= ".{";
				list($r_q, $selector[$name]) = static::BackupTransformTraverseModel($p_models, $sub_sels[$name], $ts, $binds, $collection_inf[$name]);
				$q .= $r_q;
				$q .= "}";
			}
		}
		$q = substr($q, 1);
		
		if ($is_collection && $ids)
		{
			$q .= " WHERE Id IN(?)";
			$binds[] = $ids;
		}	
		
		return [$q, $selector];
	}
}
