<?php

/**
 * @TODO - trace CURL requests / responses
 */
final class QTrace
{
	const Keep_Active_After = (60 * 10); # 10 minutes after the last ->touch()
	
	const FLUSH_EVERY_X_SEC = 1.25; // in sec
	const MAX_TOTAL_TRACE_WRITE = 67108864; # 64 MB max
	
	/**
	 * @var QTrace
	 */
	protected static $Default;
	
	protected $home_dir;
	protected $logs_dir;
	protected $app_rel_dir;
	protected $traces_path;
	
	protected $node;
	protected $root_node;
	protected $request_id;
	
	protected $last_id = 0;
	
	protected $file_index;
	protected $file_list;
	protected $file_data;
	
	protected $last_flush;
	
	protected $total_bytes_written = 0;
	
	public function touch()
	{
		# we mark for active
		touch($this->traces_path."last_active.mtime");
	}
	
	public function init(bool $trace_request = false)
	{
		$m = null;
		$reg_ex = "/^(\\/home\\/([^\\/]+)\\/)public\\_html(?:\$|\\/)(.*)\$/uis";
		$rc = preg_match($reg_ex, Q_RUNNING_PATH, $m);
		if ((!$rc) || (count($m) < 4))
			throw new \Exception("Expecting Q_RUNNING_PATH to match pattern `{$reg_ex}`");
			
		$this->home_dir = $m[1];
		if (!is_dir($this->home_dir))
			throw new \Exception("Invalid home dir: `{$this->home_dir}`");
		$this->logs_dir = $this->home_dir."logs/";
		if (!is_dir($this->logs_dir))
			throw new \Exception("Invalid logs dir: `{$this->logs_dir}`");
			
		$this->app_rel_dir = $m[3];
		$this->traces_path = $this->logs_dir."/traces/".$this->app_rel_dir."/";
		if (!is_dir($this->traces_path))
			qmkdir($this->traces_path);
		$this->traces_path = realpath($this->traces_path)."/";
		
		if ($trace_request)
		{
			$last_active = file_exists($this->traces_path."last_active.mtime") ? filemtime($this->traces_path."last_active.mtime") : false;
			
			if ((time() - $last_active) > static::Keep_Active_After)
				return false;
			
			$this->init_for_trace();
		
			# do the tracing
			$this->root_node = $this->begin_tr(["server" => $_SERVER], ["request"]);
			
			$ref_to_this = $this;
			
			register_shutdown_function(function () use ($ref_to_this)
			{
				// $out = ob_get_clean();
				// $this->dump();
				// qvar_dumpk("done !!!");
				// echo $out;
				// force it at the correct position
				$ref_to_this->node = $ref_to_this->root_node;
				$ref_to_this->end_tr();
				
				if ($ref_to_this->file_index)
					fclose($ref_to_this->file_index);
				if ($ref_to_this->file_list)
					fclose($ref_to_this->file_list);
				if ($ref_to_this->file_data)
					fclose($ref_to_this->file_data);
			});
		}
	}
	
	protected function init_for_trace()
	{
		$this->request_id = uniqid();
		
		$time = $_SERVER["REQUEST_TIME_FLOAT"];
		$path = $this->logs_dir."/traces/".$this->app_rel_dir."/".date('Y-m-d', $time)."/";
		if (!is_dir($path))
			qmkdir($path);
		$path = realpath($path)."/";
		
		$this->file_name = date('H:i s', $time)." - ".$this->request_id;
		$path .= $this->file_name;
		
		$this->file_index = fopen($path.".index", "w");
		if (!$this->file_index)
			throw new \Exception('Unable to start index file');
		$this->file_list  = fopen($path.".list" , "w");
		if (!$this->file_list)
			throw new \Exception('Unable to start list file');
		$this->file_data  = fopen($path.".data" , "w");
		if (!$this->file_data)
			throw new \Exception('Unable to start data file');
		
		$this->last_flush = microtime(true);
	}
	
	public static function Extract_Array($params, int $max_depth = 4)
	{
		if (($params instanceof \QModel) || ($params instanceof \QModelArray))
		{
			# exportToArray($selector, $with_type = false, $with_hidden_ids = false, $ignore_nulls = true)
			# toJSON($selector = null, $include_nonmodel_properties = false, $with_type = true, $with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
			# public function toArray($selector = null, $include_nonmodel_properties = false, $with_type = true, $with_hidden_ids = true, $ignore_nulls = true, &$refs = null, &$refs_no_class = null)
			return $params->toArray(null, false, true, true, true);
		}
		// @TODO - use proper selector, use objects storage to avoid duplicates, 
			// mark duplicates and have some kind of references for it
			// other optimizations !!!
		if ($max_depth <= 0)
			return null;
		
		if (($is_obj = is_object($params)))
		{
			$data = new \stdClass();
			$data->_ty = get_class($params);

			foreach ($params as $k => $v)
			{
				if (is_array($v) || is_object($v))
					$data->$k = static::Extract_Array($v, $max_depth - 1);
				else if (is_scalar($v) || is_null($v))
					$data->$k = $v;
			}

			return $data;
		}
		else if (($is_arr = is_array($params)))
		{
			$data = [];
			foreach ($params as $k => $v)
			{
				if (is_array($v) || is_object($v))
					$data[$k] = static::Extract_Array($v, $max_depth - 1);
				else if (is_scalar($v) || is_null($v))
					$data[$k] = $v;
			}
			return $data;
		}
		else if (is_scalar($params))
			return $params;
		else
			return null;
	}
	
	protected function trace_internal(int $id, int $parent_id = null, array $config = null, array $data = null, array $tags = null, bool $is_start = false, 
											bool $is_end = false, array $debug_backtrace = null)
	{
		$do_flush = (microtime(true) - ($this->last_flush ?: 0) >= static::FLUSH_EVERY_X_SEC);
		if ($do_flush)
			$this->last_flush = microtime(true);
		//  ftell ( resource $handle ) : int
		# handle list
		{
			$skip_qtrace = true;
			$caption = null;
			$line = null;
			$file = null;
			$path = null;
			
			# $cfg_calledin = $config['@calledin'] ? true : false;
			$called_in = [];
			if (isset($config['caption']))
				$caption = $config['caption'];
			
			foreach ($debug_backtrace ?: [] as $trace)
			{
				if ($skip_qtrace && ($trace['class'] === 'QTrace')) // && ($trace['file'] === __FILE__)
					continue;
				
				$skip_qtrace = false;
				$rel_path = qrelative_path($trace['file'], Q_RUNNING_PATH);
				
				if ($caption === null)
				{
					$caption = ($trace['class'] ? $trace['class'].$trace['type'] : ""). $trace['function'];
					if ($caption === null)
						$caption = "";
				}
				
				# called in
				$called_in[] = [$rel_path, $trace['class'], $trace['type'], $trace['function'], $trace['line']];
				if (count($called_in) >= 3)
					break;
			}
			
			$list_data = [$caption, $tags, $called_in];
			
			$list_write = json_encode($list_data)."\n";
			$list_start = ftell($this->file_list);
			$list_witten = fwrite($this->file_list, $list_write);
			
			$this->total_bytes_written += $list_witten;
			
			if ($do_flush)
				fflush($this->file_list);
		}
		
		# handle data 
		{
			$data_data = [$debug_backtrace, static::Extract_Array($data)];
			
			# if (($data['$return'] instanceof \QModelArray) && (reset($data['$return']) instanceof \Omi\Genband\SBC\Endpoint))
			{
				// qvar_dumpk($data, static::Extract_Array($data));
			}
			
			$data_write = json_encode($data_data)."\n";
			$data_start = ftell($this->file_data);
			$data_witten = fwrite($this->file_data, $data_write);
			
			$this->total_bytes_written += $data_witten;
			
			if ($do_flush)
				fflush($this->file_data);
		}
		
		# handle index
		{
			# id,parent,start,end,list_start,list_end,data_start,data_end
			$index_witten = fputcsv($this->file_index, [$id, $parent_id ?: 0, $is_start, $is_end, $list_start, $list_witten, $data_start, $data_witten]);
			
			$this->total_bytes_written += $index_witten;
			
			if ($do_flush)
				fflush($this->file_index);
		}
	}
	
	public static function Add_Trace(array $config = null, array $data = null, array $tags = null)
	{
		if (static::$Default)
		{
			static::$Default->begin_tr($config, $data, $tags);
			static::$Default->end_tr($config, $data, $tags);
		}
	}
	
	public static function Begin_Trace(array $config = null, array $data = null, array $tags = null)
	{
		if (static::$Default)
			static::$Default->begin_tr($config, $data, $tags);
	}
	
	public static function End_Trace(array $config = null, array $data = null, array $tags = null)
	{
		if (static::$Default)
			static::$Default->end_tr($config, $data, $tags);
	}
	
	protected function begin_tr(array $config = null, array $data = null, array $tags = null)
	{
		if ($this->total_bytes_written >= static::MAX_TOTAL_TRACE_WRITE)
			return null;
		
		$new_node = new \QTrace_Node();
		$new_node->id = ++$this->last_id;
		$new_node->parent = $this->node;
		
		$this->node = $new_node;
		
		$this->trace_internal($this->node->id, isset($this->node->parent->id) ? $this->node->parent->id : 0, $config, $data, $tags, true, false, 
				debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) );
		
		return $new_node;
	}
	
	protected function end_tr(array $config = null, array $data = null, array $tags = null)
	{
		if ($this->total_bytes_written >= static::MAX_TOTAL_TRACE_WRITE)
			return null;
		
		$this->trace_internal($this->node->id, isset($this->node->parent->id) ? $this->node->parent->id : 0, $config, $data, $tags, false, true);
		$this->node = $this->node ? $this->node->parent : null;
	}
	
	public function dump()
	{
		# qvar_dumpk($this->node);
	}
	
	public static function Run(bool $trace_request = false)
	{
		# security checks first
		if ((!defined('dev_ip')) || (dev_ip !== $_SERVER['REMOTE_ADDR']))
			return false;
		
		if (!static::$Default)
		{
			static::$Default = new static;
			$keep_it_active = static::$Default->init($trace_request);
			if ($trace_request && ($keep_it_active === false))
			{
				# was not active for a while ... we will not start it !
				static::$Default = null;
				return null;
			}
			return true;
		}
		else
			return null; # NO OP
	}
	
	public static function Dump_Info()
	{
		if (static::$Default)
			static::$Default->dump();
	}
	
	public static function Get_Request_Id()
	{
		return static::$Default ? static::$Default->request_id : null;
	}
	
	public function cleanup()
	{
		$latest_dirs = scandir($this->traces_path, SCANDIR_SORT_DESCENDING);
		
		$latest_d = null;
		foreach ($latest_dirs as $ld)
		{
			if (($ld !== '.') && ($ld !== '..') && is_dir($this->traces_path.$ld))
			{
				if ($latest_d === null)
				{
					$latest_d = $ld;
				}
				else 
				{
					exec("rm ".$this->traces_path.$ld."/*");
					$rc = rmdir($this->traces_path.$ld);
				}
			}
		}
	}
	
	public function get_last_requests()
	{
		$latest_dirs = scandir($this->traces_path, SCANDIR_SORT_DESCENDING);
		
		$latest_d = null;
		foreach ($latest_dirs as $ld)
		{
			if (($ld !== '.') && ($ld !== '..') && is_dir($this->traces_path.$ld))
			{
				$latest_d = $ld;
				break;
			}
		}
		if (!$latest_d)
			return null;
		
		$latest_requests = scandir($this->traces_path.$latest_d, SCANDIR_SORT_DESCENDING);
				
		$ret = [];
		
		foreach ($latest_requests as $lr)
		{
			$m = null;
			$rc = preg_match("/^\\s*(.*)\\s*\\-\\s*([\\w\\d]+)\\s*\\.\\s*(\\w+)\$/uis", $lr, $m);
			if ($rc && $m)
			{
				$ret[trim($m[2])]['dir'] = $this->traces_path.$latest_d."/";
				$ret[trim($m[2])]['caption'] = $latest_d." ".trim($m[1]);
				$ret[trim($m[2])][trim($m[3])] = $lr;
			}
		}
		
		return $ret;
	}
	
	public function get_request_traces(array $request, bool $with_data = false)
	{
		$list_path = $request['dir'].$request['list'];
		if ((!$list_path) || (!file_exists($list_path)))
			return false;
		
		$index_path = $request['dir'].$request['index'];
		if ((!$index_path) || (!file_exists($index_path)))
			return false;
		
		$list_f = file_get_contents($list_path);
		$index_f = fopen($index_path, "r");
		
		$data_f = null;
		if ($with_data)
		{
			$data_path = $request['dir'].$request['data'];
			if ((!$data_path) || (!file_exists($data_path)))
				return false;
			
			$data_f = file_get_contents($data_path);
		}
		
		$tree = null;
		$tree_index = [];
		while (($row = fgetcsv($index_f)))
		{
			# fputcsv($this->file_index, [$id, $parent_id ?: 0, $is_start, $is_end, $list_start, $list_witten, $data_start, $data_witten]);
			list ($id, $parent_id, $is_start, $is_end, $list_start, $list_witten, $data_start, $data_witten) = $row;
			$node = $tree_index[$id];
			$parent = null;
			if (!$node)
			{
				$tree_index[$id] = $node = new \stdClass();
				if (!$tree)
				{
					$tree = $node;
					$tree->id = $id;
				}
			}
			if ($parent_id)
			{
				$parent = $tree_index[$parent_id];
				if (!$parent)
					$tree_index[$parent_id] = $parent = new \stdClass();
				$parent->children[$id] = $node;
			}
			# $node->inf = [$is_start, $is_end];
			// $list_start, $list_witten, $data_start, $data_witten
			if ($is_start)
			{
				list ($caption, $tags, $called_in) = json_decode(substr($list_f, $list_start, $list_witten));
				$node->caption = $caption;
				$node->tags = $tags;
				# $node->line = $line;
				# $node->path = $path;
				$node->called_in = $called_in;
			}
			
			if ($with_data)
			{
				// $data_start, $data_witten
				list ($backtrace, $data) = json_decode(substr($data_f, $data_start, $data_witten));
				if ($backtrace)
					$node->trace = $backtrace;
				if ($is_start)
					$node->data = $data;
				else if ($is_end)
					$node->data_end = $data;
			}
		}
		fclose($index_f);
		
		# qvar_dumpk(strlen(json_encode($tree))); // 14k
		
		return $tree;
	}
}

final class QTrace_Node
{
	public $id;
	public $parent;
}

