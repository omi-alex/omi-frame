<?php

/**
 * @todo Handle development vs production. Do not echo information when in production mode. 
 * @todo Handle when in Ajax mode
 * 
 * @api.todo
 * 
 */
class QErrorHandler
{
	/**
	 * The callbacks
	 *
	 * @var callback[]
	 */
	protected static $Callbacks = array();
	/**
	 * @var \Throwable
	 */
	protected static $UncaughtException;
	/**
	 * The error handler that converts standard php errors in throw errors
	 * 
	 * @todo Handle development vs production
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param integer $errline
	 */
	public static function HandleError($errno, $errstr, $errfile, $errline)
	{
		if (($errno == E_NOTICE) || ($errno == E_USER_NOTICE) || ($errno == E_STRICT) || ($errno == E_DEPRECATED))
		{
			/**
			 * @todo Handle notice also
			 */
			return;
		}
		
		if (($errno == E_WARNING) || ($errno == E_USER_WARNING) || ($errno == E_COMPILE_WARNING) || ($errno == E_CORE_WARNING))
		{
			# throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			return;
		}
		else 
		{
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}
	}
	
	/**
	 * The UncaughtExceptionHandler catches all errors that are not caught within a try/catch block
	 *
	 * @param Exception $ex
	 */
	public static function UncaughtExceptionHandler($ex)
	{
		if (!$ex)
			return;
		
		$errno = $ex->getCode();
		
		if (($errno == E_NOTICE) || ($errno == E_USER_NOTICE) || ($errno == E_STRICT) || ($errno == E_WARNING) || ($errno == E_DEPRECATED))
		{
			/**
			 * @todo Handle notice also
			 */
			return;
		}

		static::$UncaughtException = $ex;
		
		$headers_sent = headers_sent();
		
		if (!$headers_sent)
			header("HTTP/1.1 500 Internal Server Error");
		
		
		// if (!\QApp::Get_QWebRequest_HandleShutdown_Registered())
		{
			if (QAutoload::GetDevelopmentMode())
			{
				self::LogError($ex, $err_uid, $backtrace_stack);
				echo self::GetExceptionToHtml($ex, $headers_sent ? false : true);
				return;
			}
		}
		
		// if we are NOT in development mode and we HAVE a runtime folder
		// check that it was deployed
		if ((!QAutoload::GetDevelopmentMode()) && (QAutoload::GetRuntimeFolder() !== null))
		{
			$was_deployed = QAutoload::WasDeployed();
			if (!$was_deployed)
			{
				// also put a warning that deployment was not made
				echo QAutoload::GetWasNotDeployedMessage();
			}
		}

		$is_ajax = (($hxrw = filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH') && (strtolower($hxrw) === 'xmlhttprequest')) || 
											(filter_input(INPUT_POST, "__qAjax__") || filter_input(INPUT_GET, "__qAjax__")));
		$in_production = !\QAutoload::GetDevelopmentMode();
		$err_uid = uniqid();
		$backtrace_stack = $ex->getTrace();
		
		if ($is_ajax)
		{
			// echo self::FrameErrorBoundyMark;
			if ($in_production)
			{
				echo json_encode(array("errstr" => $ex->getMessage(), "erruid" => $err_uid));
				self::LogError($ex, $err_uid, $backtrace_stack);
			}
			else
			{
				echo json_encode(array("errstr" => $ex->getMessage(), "erruid" => $err_uid, "errfile" => $ex->getFile(), 
					"errline" => $ex->getLine(), "stack" => $ex->getTraceAsString(), "trace" => $backtrace_stack));
				self::LogError($ex, $err_uid, $backtrace_stack);
			}
			// echo self::FrameErrorBoundyMark;
		}
		else 
		{
			if ($in_production)
			{
				$data = array("Message" => $ex->getMessage(), "erruid" => $err_uid);
				self::LogError($ex, $err_uid, $backtrace_stack);
				echo "<table>";
				foreach ($data as $k => $v)
				{
					if (is_scalar($v))
						echo "<tr><th align='left' valign='top'>{$k}</th><td valign='top'>".nl2br($v)."</td></tr>";
				}
				echo "</table>";
			}
			else
			{
				// this will be handled later
				/*$data = array("Message" => $ex->getMessage(), "erruid" => $err_uid, "File" => $ex->getFile(), 
					"Line" => $ex->getLine(), "Stack" => $ex->getTraceAsString(), "trace" => $backtrace_stack);*/
				self::LogError($ex, $err_uid, $backtrace_stack);
			}
		}
	}
	
	public static function GetExceptionToHtml(Throwable $ex, bool $with_html_head = true)
	{
		$out = "";
		if ($with_html_head)
		{
			$out .= "<!doctype html>
<html>
	<head>
		<title>Error - ".htmlspecialchars($ex->getMessage())."</title>
	</head>
<body>\n";
		}
		
		$stack_html = "<style type='text/css'>
			.q-error-handler-print, .q-error-handler-print * {
				font-family: monospace;
			}
			.q-error-handler-print-stack th, .q-error-handler-print-stack td {
				vertical-align: top;
				text-align: left;
			}
			.q-error-handler-print-stack pre {
				margin: 0;
			}
		</style>
		<table class='q-error-handler-print-stack'>
<tr>
	<th>Class</th><th>Function</th><th>Line</th><th>Args</th><th>File</th>
</tr>
";
		$common_path = "";
		$trace = $ex->getTrace();
		foreach ($trace as $t)
		{
			if (!$common_path)
				$common_path = $t['file'];
			else
			{
				$up_to = strlen($common_path) < strlen($t['file']) ? strlen($common_path) : strlen($t['file']);
				for ($i = 0; $i < $up_to; $i++)
				{
					if ($common_path{$i} !== $t['file']{$i})
						break;
				}
				$common_path = substr($common_path, 0, $i);
			}
		}
		
		foreach ($trace as $t)
		{
			$file = $common_path ? substr($t['file'], strlen($common_path)) : $t['file'];
			ob_start();
			$bag = [];
			qDebugStackInner($t['args'], false, false, '', true, false);
			$args = ob_get_clean();
			$stack_html .= "<tr>
				<td>".htmlspecialchars($t['class'])."</td><td>".htmlspecialchars($t['type']).htmlspecialchars($t['function'])."</td><td>".
						htmlspecialchars($t['line'])."</td><td>{$args}</td><td>".
						htmlspecialchars($file)."</td>
			</tr>
			";
		}
		
		$stack_html .= "</table>\n";
		
		$data = array("Message" => "<b style='color: red;'>".$ex->getMessage()."</b>", "erruid" => $err_uid, 
						"File" => $ex->getFile(), 
					"Line" => $ex->getLine());
		if ($trace)
			$data["Stack"] = $stack_html;
		else
			$data["Stack"] = "<i>No Call Stack</i>";
		
		$out .= "<table class='q-error-handler-print'>\n";
		foreach ($data as $k => $v)
		{
			if (is_scalar($v))
				$out .=  "<tr><th align='left' valign='top'>{$k}</th><td valign='top'>".($v)."</td></tr>\n";
		}
		$out .= "</table>";
		
		if ($with_html_head)
		{
			$out .= "</body>\n</html>\n";
		}
		
		return $out;
	}
	
	/**
	 * Logs an error to file
	 * 
	 * @param Exception $ex
	 * @param string $err_uid
	 * @param array $backtrace_stack
	 */
	public static function LogError($ex, $err_uid = null, $backtrace_stack = null)
	{
		if (is_string($ex))
			$ex = new \Exception($ex);
		if (!$err_uid)
			$err_uid = uniqid();
		if (!$backtrace_stack)
			$backtrace_stack = $ex->getTrace();
		
		$file_path = QAutoload::GetRuntimeFolder()."temp/error_logs/".date("Y/m/d_").$err_uid.".log.html";
		$dir = dirname($file_path);
		if (!is_dir($dir))
			qmkdir($dir, true);
		
		ob_start();
		echo "<h3>".$ex->getMessage()."</h3>";
		echo "<div><b>File: </b>{$ex->getFile()}</div>";
		echo "<div><b>Line: </b>{$ex->getLine()}</div>";
		echo "<div><b>Error Id: </b>{$err_uid}</div>";
		if ($backtrace_stack)
		{
			echo "<div><b>STACK:</b></div>";
			qvar_dumpk($backtrace_stack);
		}
		echo "<div><b>REQUEST INFO:</b></div>";
		qvar_dumpk(['$_SERVER' => $_SERVER, '$_GET' => $_GET, '$_POST' => $_POST, '$_SESSION' => $_SESSION, "SESSION_ID" => session_id()]);
		if (class_exists('Omi\App'))
		{
			echo "<div><b>Omi\App :: Statics</b></div>";
			$class = new ReflectionClass('Omi\App');
			$staticProperties = $class->getStaticProperties();
			qvar_dumpk($staticProperties);
		}
		if (class_exists('Omi\User'))
		{
			echo "<div><b>Omi\User :: Statics</b></div>";
			$class = new ReflectionClass('Omi\User');
			$staticProperties = $class->getStaticProperties();
			qvar_dumpk($staticProperties);
		}
		$data = ob_get_clean();
		
		// echo $data;
		
		if (function_exists("gzcompress"))
			file_put_contents($file_path.".gzip", gzencode($data));
		else
			file_put_contents($file_path, $data);
	}
	
	/**
	 * Adds a Shutdown callback
	 * Returns the position where the callback was set
	 * 
	 * @param callback $callback
	 * @return integer 
	 */
	public static function AddShutdownCallback($callback)
	{
		self::$Callbacks["shutdown"][] = $callback;
		end(self::$Callbacks["shutdown"]);
		return key(self::$Callbacks["shutdown"]);
	}
	
	/**
	 * Removes a Shutdown callback based on the position where it was added
	 * 
	 * @param integer $position
	 */
	public static function RemoveShutdownCallback($position)
	{
		unset(self::$Callbacks["shutdown"][$position]);
	}
	
	/**
	 * Manages the shutdown event
	 * In case of a fatal error will trigger QErrorHandler::HandleError
	 *
	 */
	public static function OnShutdown()
	{
		// \QWebRequest::DebugDataFlush();
		
		if (!empty(self::$Callbacks["shutdown"]))
		{
			foreach (self::$Callbacks["shutdown"] as $callback)
			{
				// make the call
				call_user_func($callback);
			}
		}
		
		$isError = false;
		$error = error_get_last();
		
		if ($error)
		{
			switch($error['type'])
			{
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
				{
					$isError = true;
					break;
				}
				default:
				{
					break;
				}
			}
		}

        if ($isError)
        {
			self::HandleError($error["type"], $error["message"], $error["file"], $error["line"], true);
        }
		
		// \QWebRequest::DebugDataFlush();
	}
	
	/**
	 * @return \Throwable
	 */
	public static function GetUncaughtException()
	{
		return static::$UncaughtException;
	}
	
	public static function Cleanup_On_End()
	{
		# @TODO - we need to close all transactions
		
		$storage = \QApp::GetStorage();
		if (($storage instanceof \QSqlStorage) && ($storage->connection instanceof \mysqli))
		{
			# make sure we close the connection , and rollback any unfinished transactions
			$rc_rb = $storage->connection->rollback(MYSQLI_TRANS_COR_RELEASE);
			$rc = $storage->connection->close();
			file_put_contents("test_connection_close.txt", "\n" . date('Y-m-d H:i:s') . ' | ' . json_encode(['rollback' => $rc_rb, 'close' => $rc]), FILE_APPEND);
		}
		
		# @TODO - maybe close other resources
		# $all_resources = get_resources();

		/**
		if (is_array($all_resources))
		{
			foreach ($all_resources as $resource)
			{
				$type = get_resource_type($resource);
				if ($type === 'stream-context')
					var_dump([$type => stream_context_get_options($resource)]);
				else
					var_dump([$type => $resource]);
			}
		}
		# var_dump($all_resources);
		$all_resources_str = ob_get_clean();
		*/
		# file_put_contents("test_close_resources.txt", "\n" . date('Y-m-d H:i:s') . ' | ' . json_encode(['$all_resources' => $all_resources_str]), FILE_APPEND);
	}
}
