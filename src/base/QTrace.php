<?php

/**
 * 
 * @TODO - trace CURL requests / responses
 * 
 */
class QTrace 
{
	/**
	 * @var array
	 */
	protected static $Request;
	/**
	 * @var int
	 */
	protected static $Increment = 1;
	/**
	 * @var string
	 */
	protected static $Current;
	/**
	 * @var int
	 */
	protected static $LastFlushAt;
	
	protected static $IndexFilePath;
	protected static $IndexFile;
	protected static $DataFilePath;
	protected static $DataFile;
	protected static $RequestFilePath;
	protected static $RequestFile;
	
	/**
	 * @var int
	 */
	protected $id;
	/**
	 * @var string
	 */
	protected $uid;
	/**
	 * @var string
	 */
	protected $parent;
	/**
	 * @var int
	 */
	protected $last_child;
	/**
	 * @var array
	 */
	protected $index;
	/**
	 * @var array
	 */
	protected $data;
	
	public $trace_closure_params;

	public function trace(string $uid, array $config = null, \Closure $closure = null, $closure_context = null)
	{
		if (static::$Request === null)
			static::TraceRequest();
		
		$this->uid = $uid;
		$this->id = self::$Increment++;
		
		if (self::$Current)
			$this->parent = self::$Current;
		self::$Current = $this->id.'|'.$this->uid;
		
		$this->setupConfig($config);
		
		$this->traceStart();
		
		/*
		$t0 = microtime(true);
		// $ex = new Exception();
		$ex = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		$t1 = microtime(true);
		var_dump(($t1 - $t0)*1000, $ex);
		die;
		*/
		
		// @TODO - cache location, only extract what you need from it !
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		$this->traceParams($trace, 'trace');
		
		// @TODO - do the tracing
		// if there is nothing to trace, use information from the $closure if available
		$this->trace_closure_params = true;
		
		$return = null;
		if ($closure)
		{
			if ($this->trace_closure_params)
				$this->traceParams((new ReflectionFunction($closure))->getStaticVariables(), 'use');
			
			if (is_string($closure_context))
			{
				$exec = $closure->bindTo(null, $closure_context);
				$return = $exec();
			}
			else if (is_object($closure_context))
			{
				$exec = $closure->bindTo($closure_context);
				$return = $exec();
			}
			else
				$return = $closure();
		}
		// else 
		//		return;
		
		if ($this->parent)
			self::$Current = $this->parent;
	
		// this should be the last child or self
		$this->last_child = self::$Increment - 1;
		
		$this->traceEnd($return);
		
		return $return;
	}
	
	public function traceParams($params, $path = null, $selector = null)
	{
		// we will write a start and an end
		if ($selector === null)
			$selector = 2; // the default depth
		
		// use the selector to extract the data
		if (is_array($params) || is_object($params))
		{
			$data = [];
			$this->extractArray($params, $selector, $data);
		}
		else
			$data = $params;
		
		static::EncodeWrite(static::$DataFile, is_string($path) ? [$path] : $path, $data);
	}
	
	public function extractArray($params, $selector, array &$data)
	{
		// @TODO - use proper selector, use objects storage to avoid duplicates, 
			// mark duplicates and have some kind of references for it
			// other optimizations !!!
		
		if (($selector === false) || ($selector === null) || ($selector === 0))
			return;
		
		foreach ($params as $k => $v)
		{
			if (is_array($v) || is_object($v))
			{
				$data[$k] = [];
				$this->extractArray($v, $selector - 1, $data[$k]);
			}
			else
				$data[$k] = $v;
		}
	}
	
	public function setupConfig(array $config = null)
	{
		// @TODO - cache what is possible !!!
	}
	
	public function traceStart()
	{
		$start_pos = ftell(static::$DataFile);
		static::EncodeWrite(static::$IndexFile, ["traces", $this->id], ["startPos" => $start_pos, 'uid' => $this->uid]);
		static::EncodeWrite(static::$DataFile, ["traces", $this->id], ['uid' => $this->uid]);
	}
	
	public function traceEnd($return)
	{
		$end_pos = ftell(static::$DataFile);
		static::EncodeWrite(static::$IndexFile, ["traces", $this->id], ["endPos" => $end_pos]);
		static::EncodeWrite(static::$DataFile, ["traces", $this->id], ['uid' => $this->uid]);
		if ($return !== null)
			$this->traceParams($return, 'return');
	}
	
	public static function EncodeWrite($file, array $path = null, $data, bool $compress = null)
	{
		$path_str = $path ? implode("\x00", $path) : "";
		$s_data = serialize($data);
		$data_str = ($compress || (($compress === null) && (strlen($s_data) > 512))) ? ":c:".gzcompress($s_data) : ":p:".$s_data;
		
		fwrite($file, strlen($path_str)."\n");
		fwrite($file, $path_str);
		fwrite($file, "\n".strlen($data_str)."\n");
		fwrite($file, $data_str);
		fwrite($file, "\n");
	}
	
	public static function TraceRequest()
	{
		// @TODO
		static::$Request = [];
		
		static::$Request['id'] = uniqid();
		// @TODO SECURITY !!! - file must not be web accessible !
		$time = $_SERVER["REQUEST_TIME_FLOAT"];
		$path = \QAutoload::GetTempFolder()."/traces/".date('m (M)', $time)."-".date('d', $time)."-".date('Y', $time)."/".date('G', $time)."_00/";
		if (!is_dir($path))
			qmkdir($path);
		
		$path .= date('G:i s', $time)." - ";
		
		static::$IndexFilePath = $path.static::$Request['id'].'.index';
		static::$IndexFile = fopen(static::$IndexFilePath, "wt");
		
		static::$DataFilePath = $path.static::$Request['id'].'.data';
		static::$DataFile = fopen(static::$DataFilePath, "w");
		
		static::$RequestFilePath = $path.static::$Request['id'].'.request';
		static::$RequestFile = fopen(static::$RequestFilePath, "w");
		
		$data = ["time" => $time, "server" => $_SERVER];
		if ($_GET)
			$data['get'] = $_GET;
		if ($_FILES)
			$data['$_FILES'] = $_FILES;
		if (session_id())
			$data['session_id'] = session_id();
		
		static::EncodeWrite(static::$RequestFile, null, $data);
		
		// @TODO :: register_shutdown_function
		register_shutdown_function(function ()
		{
			static::TerminateRequest();

			if (static::$IndexFile)
				fclose(static::$IndexFile);
			if (static::$DataFile)
				fclose(static::$DataFile);
			if (static::$RequestFile)
				fclose(static::$RequestFile);
		});
		
		// @TODO :: Hook into QWebRequest to trace the response that was sent
	}
	
	public static function TraceResponse()
	{
		// @TODO
		// should be called when the script closes
	}
	
	public static function TerminateRequest()
	{
		// option 1 ... return & continue (maybe best)
		ignore_user_abort(true);
		set_time_limit(300);
		if(session_id())
			session_write_close();
		ob_end_flush();
		ob_flush();
		flush();
		if (function_exists("fastcgi_finish_request"))
			fastcgi_finish_request();
	}
}
