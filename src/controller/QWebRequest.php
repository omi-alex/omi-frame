<?php

/**
 * The QWebRequest class manages the web request:
 * 
 * - Translates the URL into a list of callbacks
 * - Executes the callbacks
 * - Returns the response
 * 
 */
class QWebRequest
{
	/**
	 * The URL that will answer to RESTful requests
	 *
	 * @var string
	 */
	protected static $REST_API_URL = "API/RESTful/";
	protected static $REST_API_URL_NEW = "~API/RESTful/";
	
	/**
	 * @var boolean
	 */
	protected static $RemoteRequest = false;
	/**
	 * @var boolean
	 */
	protected static $AsyncRequest = false;
	
	public static $_pid = null;

	
	/**
	 * The URL that will answer to SOAP requests
	 *
	 * @var string
	 */
	protected static $SOAP_API_URL = "API/SOAP/";
	
	/**
	 * The protocol of the request, ex: "HTTP/1.1"
	 *
	 * @var string 
	 */
	protected static $RequestProtocol;
	/**
	 * True if it's an AJAX request.
	 * The information is extracted from the header : HTTP_X_REQUESTED_WITH should be xmlhttprequest
	 *
	 * @var boolean
	 */
	protected static $AjaxRequest = null;
	/**
	 *
	 * @var boolean
	 */
	public static $FastAjax = false;
	/**
	 * The respnse that will be sent back to the browser for ajax requests
	 *
	 * @var mixed[]
	 */
	protected static $AjaxResponse;
	/**
	 * @var string
	 */
	protected static $ControllerOutput;
	/**
	 * True if a SSH request
	 * 
	 * @var boolean
	 */
	protected static $SSLEnabled;
	/**
	 * The original request
	 *
	 * @var string
	 */
	protected static $OriginalRequest;
	/**
	 * The base href of the running controller
	 * 
	 * @var string
	 */
	protected static $BaseHref;
	/**
	 * Pushes items in array inside a "items" property
	 *
	 * @var boolean
	 */
	protected static $ArrayInItems = true;
	
	protected static $BaseUrl;
	
	protected static $MultiResponseId;
	protected static $MultiRequestId;
	
	protected static $RequestId;
	
	protected static $AjaxResponseSent = false;
	protected static $ControllerOutputSent = false;
	
	protected static $_DebugDataFile = null;


	/**
	 * Processes the request
	 * Will handle: HTTP/HTTPS/RESTful/SOAP
	 */
	public static function frame_Process($App = "QApp", QIUrlController $controller = null, $skip_url = null)
	{
		static::$RequestId = uniqid();
		// audit request
		\QAudit::AuditRequest();
		
		self::$AjaxRequest = self::IsAjaxRequest();
		self::$FastAjax = $fast_call = ($_POST["__qFastAjax__"] || $_GET["__qFastAjax__"]);
		
		// disabled atm
		if (false && (Q_DEBUG || Q_DEV) && (!static::$AjaxRequest) && (!self::$FastAjax))
		{
			// @TODO - JS must ensure that the parent iframe is in dev mode
			
			$with_ifr = rtrim(Q_APP_REL ,'/').'/~dev';
			$with_ifr_len = strlen($with_ifr);
			$last_ch = $_SERVER['REQUEST_URI']{$with_ifr_len};
			$has_iframe_tag = (substr($_SERVER['REQUEST_URI'], 0, $with_ifr_len) === $with_ifr) && (($last_ch === '') || ($last_ch === '?') || ($last_ch === '/') || ($last_ch === '&'));
			
			if (!$has_iframe_tag)
			{
				$with_ifr_dev = rtrim(Q_APP_REL ,'/').'/__q_dev__';
				$with_ifr_dev_len = strlen($with_ifr_dev);
				$last_ch_dev = $_SERVER['REQUEST_URI']{$with_ifr_dev_len};
				$has_iframe_dev_tag = (substr($_SERVER['REQUEST_URI'], 0, $with_ifr_dev_len) === $with_ifr_dev) && (($last_ch_dev === '') || ($last_ch_dev === '?') || ($last_ch_dev === '/') || ($last_ch_dev === '&'));
			}
			
			// @TODO ... switch to ~dev based on some seesion var ? or other ... make sure the parent is on dev mode!
			if ($has_iframe_tag)
			{
				// return the iframe
				static::RenderDevIframe('__q_dev__');
				// we must stop here
				die;
			}
			else if ($has_iframe_dev_tag)
			{
				/// for ($i = 0; $i < 100; $i++)
				static::RenderDevIframe_Dev('__q_dev__');
				die;
			}
			// else continue normal
		}
		
		// detect the type of the request
		// Content-Type: application/soap+xml; charset=utf-8
		if ($_GET["__or__"] || ($_GET["__or__"] !== null))
		{
			// var_dump($_GET["__or__"]);
			$qs = $_SERVER["QUERY_STRING"];
			$matches = null;
			//preg_match("/__or__\\=(.*?)(?:\\&|\$)/us", $qs, $matches);
			preg_match("/(?:^|\\&|\\?)__or__\\=(.*?)(?:\\&|\$)/us", $qs, $matches);
			// remove __q_noiframe__ if exists !
			if ($matches[1])
				$matches[1] = preg_replace('/(?:^|\&)__q_noiframe__\b\/?/us', '', $matches[1]);
			self::$OriginalRequest = urldecode($matches[1]);
			unset($_GET["__or__"]);
		}
		
		if ($_GET["__MultiResponseId"])
		{
			static::$MultiRequestId = (string)$_GET["__MultiResponseId"];
			unset($_GET["__MultiResponseId"]);
		}
		
		self::$BaseHref = BASE_HREF;
		if ($skip_url)
		{
			self::$OriginalRequest = substr(self::$OriginalRequest, strlen($skip_url));
			if (self::$OriginalRequest === false)
				self::$OriginalRequest = "";
			self::$BaseHref .= rtrim($skip_url, "/")."/";
		}
			
		self::$RequestProtocol = $_SERVER["SERVER_PROTOCOL"];
		if (($tmp_p = strpos(self::$RequestProtocol, "/")) !== false)
			self::$RequestProtocol = substr(self::$RequestProtocol, 0, $tmp_p);
		self::$SSLEnabled = isset($_SERVER["HTTPS"]) && (strtolower($_SERVER["HTTPS"]) != "off");
		self::$AjaxRequest = self::IsAjaxRequest();
		$IframeWorkaround = ($_POST["__qIframe__"]) || ($_GET["__qIframe__"]);
		
		if ($_GET["__customExtract__"] || $_POST["__customExtract__"])
			self::$ArrayInItems = false;
		
		/*
		if ($_POST)
		{
			file_put_contents("dump.".date("Y-m-d H:i:s", time()).".json", json_encode($_POST));
		}
		*/
	   $managed = false;

		// we need to convert the raw request into one or more manageble callbacks
		$compare_or = trim(self::$OriginalRequest);
		$compare_or = (substr($compare_or, -1, 1) === '/') ? $compare_or : $compare_or.'/';

		if (substr($compare_or, 0, strlen(self::$REST_API_URL)) == self::$REST_API_URL)
		{
			self::$RequestProtocol = "REST";
			// TO DO : if ($request_controller_script) include($request_controller_script);
			// REST request
			// throw new Exception("REST request :: TO DO");
			static::HandleRESTFul(substr($compare_or, strlen(self::$REST_API_URL)));
		}
		else if (substr($compare_or, 0, strlen(self::$REST_API_URL_NEW)) == self::$REST_API_URL_NEW)
		{
			self::$RequestProtocol = "REST";
			static::HandleRESTFul(substr($compare_or, strlen(self::$REST_API_URL_NEW)));
		}
		else if (substr(self::$OriginalRequest, 0, strlen(self::$SOAP_API_URL)) == self::$SOAP_API_URL)
		{
			self::$RequestProtocol = "SOAP";
			
			// TO DO : if ($request_controller_script) include($request_controller_script);
			// SOAP request
			// load up SOAP server / and so on
			throw new Exception("SOAP request :: TO DO");
		}
		else 
		{
			self::$RequestProtocol = "HTTP";
			
			$url = QUrl::$Requested = new QUrl(self::$OriginalRequest);
			
			if ($_GET['_deploy_'])
			{
				if (!QAutoload::GetDevelopmentMode())
				{
					http_response_code('403');
					echo 'HTTP/1.1 403 Forbidden';
				}
				else
				{
					$managed = QDeploy::Run();
				}
			}
			// URL managed or not
			else if ($fast_call)
			{
				if ($controller)
					$managed = is_string($controller) ? $controller::initController($url) : $controller->initController($url);
				else
					$managed = $App::initController($url);
				// execute the fast call : qbMethod="fast-ajax"
				execQB();
			}
			else 
			{
				ob_start();
				if ($controller)
					$managed = is_string($controller) ? $controller::loadFromUrl($url) : $controller->loadFromUrl($url);
				else
					$managed = $App::loadFromUrl($url);
				static::$ControllerOutput = ob_get_clean();
			}

			if ((!$managed) && (!$fast_call))
			{
				// call for a 404 management
				// The URL manager must translate the path
				if (!headers_sent())
				{
					header("HTTP/1.1 404 Not Found");
					header("Status: 404 Not Found");
					die("HTTP/1.1 404 Not Found");
				}
			}
		}
		
		if (self::$AjaxRequest || self::$FastAjax)
		{
			if ($IframeWorkaround)
			{
				echo "<!doctype html>\n<html>\n<head>\n<title>Iframe</title>\n</head>\n<body>\n<textarea>";
				ob_start();
			}
			self::SendAjaxResponse();
			if ($IframeWorkaround)
			{
				$out = ob_get_clean();
				echo htmlspecialchars($out);
				echo "</textarea>\n</body>\n</html>";
			}
		}
		else
		{
			echo static::$ControllerOutput;
			static::$ControllerOutputSent = true;
		}
		
		return $managed;
	}

	/**
	 * Gets the RESTful API URL
	 * 
	 * @return string
	 */
	public static function GetRESTfulApiURL()
	{
		return self::$REST_API_URL;
	}
	
	/**
	 * Gets the SOAP API URL
	 * 
	 * @return string
	 */
	public static function GetSOAPApiURL()
	{
		return self::$SOAP_API_URL;
	}
	
	/**
	 * Gets the protocol of the request, ex: "HTTP/1.1"
	 * 
	 * @return string
	 */
	public static function GetRequestProtocol()
	{
		return self::$RequestProtocol;
	}
	
	/**
	 * True if it's an AJAX request.
	 * The information is extracted from the header : HTTP_X_REQUESTED_WITH should be xmlhttprequest
	 * 
	 * @return boolean
	 */
	public static function GetAjaxRequest()
	{
		return self::IsAjaxRequest();
	}
	
	/**
	 * True if it's an AJAX request.
	 * The information is extracted from the header : HTTP_X_REQUESTED_WITH should be xmlhttprequest
	 * 
	 * @return boolean
	 */
	public static function IsAjaxRequest()
	{
		// make sure it's HTTP request before we look in INPUT_POST / INPUT_GET
		return (self::$AjaxRequest !== null) ? self::$AjaxRequest : 
				(self::$AjaxRequest = (($hxrw = $_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($hxrw) === 'xmlhttprequest')) || 
											(filter_input(INPUT_POST, "__qAjax__") || filter_input(INPUT_GET, "__qAjax__")));
	}
	
	/**
	 * True if a SSH request
	 * 
	 * @var boolean
	 */
	public static function GetSSLEnabled()
	{
		return self::$SSLEnabled;
	}
	/**
	 * The original request
	 *
	 * @var string
	 */
	public static function GetOriginalRequest()
	{
		return self::$OriginalRequest;
	}
	
	/**
	 * Gets the ajax response that will be sent to the client
	 * 
	 * @return mixed[]
	 */
	public static function GetAjaxResponse()
	{
		return self::$AjaxResponse;
	}
	
	/**
	 * Sets the ajax response that will be sent to the client
	 * 
	 * @param mixed $callback_data
	 */
	public static function SetAjaxResponse($callback_data)
	{
		if (!$callback_data)
			return;
		
		if (self::$AjaxResponse)
		{
			foreach ($callback_data as $pos => $data)
				self::$AjaxResponse[$pos] = $data;
		}
		else
			self::$AjaxResponse = $callback_data;
	}
	
	/**
	 * Sets the render ajax response
	 * 
	 * @param string $ctrl_id
	 * @param string $buffer
	 */
	public static function SetRenderAjaxResponse($ctrl_id, $buffer)
	{
		if (self::IsAjaxCallback())
		{
			if ($ctrl_id)
				self::$AjaxResponse["_qGq_"][$ctrl_id] = $buffer;
			else
				self::$AjaxResponse["_qGq_"][] = $buffer;
		}
	}
	
	/**
	 * Sends the AJAX response
	 */
	public static function frame_SendAjaxResponse()
	{
		\QWebControl::AddJsLastResources();
		\QWebControl::AddCssLastResources();
		if (QWebControl::$IncludeJs)
		{
			$jss = [];
			foreach (QWebControl::$IncludeJs ?: [] as $k => $js)
				$jss[$k] = \QWebControl::GetPreventCacheResourceSource($js);
			
			if (self::$AjaxResponse === null)
				self::$AjaxResponse = ["___js" => $jss];
			else
				self::$AjaxResponse["___js"] = $jss;
		}
		if (QWebControl::$IncludeCss)
		{
			$csss = [];
			foreach (QWebControl::$IncludeCss ?: [] as $k => $css)
				$csss[$k] = \QWebControl::GetPreventCacheResourceSource($css);

			if (self::$AjaxResponse === null)
				self::$AjaxResponse = ["___css" => $csss];
			else
				self::$AjaxResponse["___css"] = $csss;
		}
		
		if (self::$AjaxResponse !== null)
		{
			self::$AjaxResponse['__ResponseId__'] = self::GetRequestId();
			
			if (static::$MultiResponseId)
				self::$AjaxResponse["__MultiResponseId"] = static::$MultiResponseId;
			$refs = [];
			if (\QAutoload::GetDevelopmentMode())
				self::$AjaxResponse['__devmode__'] = true;
			\QModel::QOutputJson(self::$AjaxResponse, true, $refs, true, self::$ArrayInItems);
		}
		
		static::$AjaxResponseSent = true;
	}
	
	public static function AddHiddenOutput(string $output)
	{
		self::$AjaxResponse["__hiddenOutput__"][] = $output;
	}

	/**
	 * Checks if is an Ajax Request/Callback
	 * Returns false in case self::$FastAjax is true
	 * Use self::IsAjaxRequest() if you need to test for any form of ajax
	 * 
	 * @return boolean
	 */
	public static function IsAjaxCallback()
	{
		return self::IsAjaxRequest() && (!self::IsFastAjax());
	}

	/**
	 * Checks if is fast ajax.
	 * 
	 * @return boolean
	 */
	public static function IsFastAjax()
	{
		return self::$FastAjax;
	}
	
	/**
	 * Gets the base href of the running controller
	 * 
	 * @return string
	 */
	public static function GetBaseHref()
	{
		return self::$BaseHref;
	}
	
	/**
	 * Gets request full url
	 * 
	 * @return string
	 */
	public static function GetRequestFullUrl($with_query_string = false)
	{
		$ssl = ((!empty($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] === 'on')) ? "s" : "";
		$port = $_SERVER['SERVER_PORT'];
		$port = ((!$ssl && ($port == '80')) || ($ssl && ($port == '443'))) ? '' : ':'.$port;
		
		$url = "http{$ssl}://".$_SERVER['HTTP_HOST'].$port.self::$BaseHref.self::$OriginalRequest;
		if ($with_query_string && (!empty($_GET)))
			return $url."?".http_build_query($_GET);
		else
			return $url;
	}

	public static function GetServerName()
	{
		$protocol = (isset($_SERVER['HTTPS'])) ? (($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http") : 'http';
		return  $protocol . "://" . $_SERVER['HTTP_HOST'];
	}

	/**
	 * Returns the base url of the application
	 * 
	 * @return string
	 */
	public static function GetBaseUrl()
	{
		if (self::$BaseUrl)
			return self::$BaseUrl;
		return self::$BaseUrl = ((substr(BASE_HREF, 0, 4) != "http") ? self::GetServerName() : "").BASE_HREF;
	}
	
	public static function SetMultiResponseId($multi_id)
	{
		static::$MultiResponseId = $multi_id;
	}
	
	public static function GetMultiRequestId()
	{
		return static::$MultiRequestId;
	}
	
	public static function SetMultiRequestId($multi_req_id)
	{
		static::$MultiRequestId = $multi_req_id;
	}
	
	public static function GetMultiResponseId()
	{
		return static::$MultiResponseId;
	}
	
	public static function RenderBeforeBodyEnds()
	{
		if (\QAutoload::GetDevelopmentMode())
		{
			?>
		<script type="text/javascript">
			window["__ResponseId__"] = <?= json_encode(\QWebRequest::GetRequestId()) ?>;
		</script>
<?php
		}
		if (static::$MultiResponseId)
		{
			?>
		<script type="text/javascript">
			window["__MultiResponseId"] = <?= json_encode(static::$MultiResponseId) ?>;
		</script>
<?php
		}
	}
	
	public static function GetRequestId()
	{
		return (static::$RequestId !== null) ? static::$RequestId : (static::$RequestId = uniqid("", true));
	}
	
	public static function HandleShutdown(string $content)
	{
		$legcay_error_handling = \QApp::GetLegacyErrorHandling();
		$uncaughtException = \QErrorHandler::GetUncaughtException();
		
		if ($legcay_error_handling && ($_POST["__asyncRequest__"] || $_POST["__remoteRequest__"]))
		{
			if ($uncaughtException)
				static::HandleLegacyForAsyncRequest($uncaughtException);
			static::HandleLegacyForAsyncRequest_OnShutdown();
		}
		
		if (\QAutoload::GetDevelopmentMode())
		{
			// first determine if we stopped because of an error
			// Throwable 
			/*    Throwable::getMessage - Gets the message
				Throwable::getCode - Gets the exception code
				Throwable::getFile - Gets the file in which the exception was created
				Throwable::getLine - Gets the line on which the object was instantiated
				Throwable::getTrace - Gets the stack trace
				Throwable::getTraceAsString - Gets the stack trace as a string
				Throwable::getPrevious - Returns the previous Throwable
				Throwable::__toString - Gets a string representation of the thrown object*/
			
			// in development mode, inject data, later: identify window
			if ($_GET['_deploy_'])
			{
				// nothing
				qvar_dump($uncaughtException);
			}
			else if (self::$FastAjax || self::IsAjaxRequest())
			{
				// qvar_dump($uncaughtException);
				// static::$AjaxResponseSent
				// echo json_encode(['__error__' => ($uncaughtException ? $uncaughtException->getMessage() : 'no error'), '__hiddenOutput__' => $content]);
				if ($uncaughtException)
				{
					$send_json = ['__hiddenOutput__' => $content, '__devmode__' => true];
					if ($legcay_error_handling)
					{
						$send_json['EXCEPTION'] = [
								"Message" => implode(", ", [
									"Message: "	.$uncaughtException->getMessage()."\n",
									"File: "	.$uncaughtException->getFile()."\n",
									"Line: "	.$uncaughtException->getLine()."\n",
									"Code: "	.$uncaughtException->getCode()."\n",
									"Trace: "	.$uncaughtException->getTraceAsString()
								]),
								"__cust__" => true
							];
					}
					
					if (static::$AjaxResponseSent)
					{
						// we have a problem :)
					}
					
					$send_json['__error__'] = \QErrorHandler::GetExceptionToHtml($uncaughtException, false);
					
					echo json_encode($send_json);
				}
				else
				{
					echo $content;
				}
			}
			else // controller managed
			{
				// $ControllerOutputSent
				if ($uncaughtException)
				{
					if (static::$ControllerOutputSent)
					{
						echo \QErrorHandler::GetExceptionToHtml($uncaughtException);
					}
					else
					{
						// qvar_dump($uncaughtException->getTrace());
						// print exception
						echo \QErrorHandler::GetExceptionToHtml($uncaughtException);
					}
					
					echo $content;
				}
				else
				{
					// all is ok, send the output
					echo $content;
				}
			}
		}
		else
		{
			if ($uncaughtException)
			{
				// if (true) // || $legcay_error_handling)
				{
					\QErrorHandler::LogError($uncaughtException);
					
					if (self::$FastAjax || self::IsAjaxRequest())
					{
						$json = [
							"EXCEPTION" => [
								"Message" => $uncaughtException->getMessage(),
								"__cust__" => true
							]
						];
						echo json_encode($json);
					}
					else
					{
						if (!headers_sent())
							header("HTTP/1.1 500 Internal Server Error");
						echo $uncaughtException->getMessage();
					}
				}
			}
			else
			{
				echo $content;
			}
		}
	}
	
	public static function HandleLegacyForAsyncRequest_OnShutdown()
	{
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
			if (!is_dir("logs/errors/"))
				qmkdir("logs/errors/");
			$file = $_POST["__asyncRequest__"] ? "logs/errors/async_err__" . date("Y_m_d") . ".html" : "logs/errors/remote_err__" . date("Y_m_d") . ".html";

			ob_start();
			var_dump($error);
			$str = ob_get_clean();
			file_put_contents($file, $str, FILE_APPEND);
		}
	}
	
	public static function HandleLegacyForAsyncRequest($exception)
	{
		if (!is_dir("logs/errors/"))
			qmkdir("logs/errors/");
		$file = $_POST["__asyncRequest__"] ? "logs/errors/async_ex__" . date("Y_m_d") . ".html" : "logs/errors/remote_ex__" . date("Y_m_d") . ".html";

		ob_start();
		var_dump($exception->getMessage()."|".$exception->getFile()."|".$exception->getLine()."|".$exception->getTraceAsString());
		$str = ob_get_clean();

		file_put_contents($file, $str, FILE_APPEND);
		\QErrorHandler::LogError($exception);
	}
	
	public static function RenderDevIframe(string $append_url_dev = '__q_dev__')
	{
		?><!doctype html>
<html>
	<head>
		<Title>Replace title from JS</Title>
		<base href="<?= BASE_HREF ?>" />
		<style type="text/css">
			body, iframe {
				border: 0;
				margin: 0;
				padding: 0;
				box-sizing: border-box;
			}
			div.left, div.right {
				
			}
			div.left {
				flex: 1;
				resize: horizontal;
			}
			div.right {
				width: 500px;
			}
			div.scroll-bar {
				width: 4px;
				background-color: gray;
				cursor: col-resize;
			}
			::-webkit-resizer, ::-moz-resizer {
				border: 2px solid yellow;
			}
			iframe {
				height: 100%;
				width: 100%;
			}
			html, body, div.left, div.right {
				height:100%;
				overflow: hidden;
			}
			.container {
				display: flex;
				width: 100%;
				height: 100%;
			}
		</style>
		
		<script type="text/javascript">
			// console.log(XMLHttpRequest);
			// alert(document.getElementById('iframe_main'));
			window.$base_href = '<?= Q_APP_REL ?>';
		</script>
	</head>
	<body>
		<?php
			$rel_req = $_GET['__or__'] ? preg_replace('/^~dev\b\/?/us', '', $_GET['__or__']) : "";
			$query_string = $_SERVER['QUERY_STRING'] ? preg_replace('/(?:^|\&)__or__\b(?:\s*\=\s*[^\&\?\=]*)/us', '', $_SERVER['QUERY_STRING']) : "";
			$req = Q_APP_REL.$rel_req.($query_string ? "?".$query_string : "");
			// qvar_dump($req);
		?>
		<div class='container'>
			<div class="left"><iframe id='iframe_main' frameborder="0" scrolling="auto" allowfullscreen src="<?= $req ?>"></iframe></div>
			<div class="scroll-bar"></div>
			<div class="right"><iframe id='iframe_dev' frameborder="0" scrolling="auto" allowfullscreen src="<?= Q_APP_REL.$append_url_dev ?>"></iframe></div>
		</div>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/jquery-2.1.4.min.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/functions.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/debug.js"></script>
	</body>
</html>
<?php
	}
	
	public static function RenderDevIframe_Dev(string $append_url_dev = '__q_dev__')
	{
		?><!doctype html>
<html>
	<head>
		<Title></Title>
		<base href="<?= BASE_HREF ?>" />
		<script type="text/javascript">
			// console.log(XMLHttpRequest);
			// alert(document.getElementById('iframe_main'));
			window.$base_href = '<?= Q_APP_REL ?>';
		</script>
	</head>
	<body>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/phpjs.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/jquery-2.1.4.min.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/functions.js"></script>
		<script type="text/javascript" src="<?= Q_FRAME_REL; ?>view/js/debug_dev.js"></script>
	</body>
</html>
<?php
	}
	
	public static function AddDebugData($data, $params = null)
	{
		// jQuery.ajax('temp/dev_resp/' + $resp_id, {
		if (static::$_DebugDataFile === null)
		{
			$path = Q_RUNNING_PATH . 'temp/dev_resp/' . static::GetRequestId();
			if (!is_dir(dirname($path)))
				qmkdir(dirname($path));
			static::$_DebugDataFile = fopen($path, "a");
			fwrite(static::$_DebugDataFile, "[null\n");
		}
		
		fwrite(static::$_DebugDataFile, ",\n");
		fwrite(static::$_DebugDataFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, 5));
	}
	
	public static function DebugDataFlush()
	{
		if (static::$_DebugDataFile !== null)
			fflush(static::$_DebugDataFile);
	}
	
	/**
	 * Processes the request
	 * Will handle: HTTP/HTTPS/RESTful/SOAP
	 */
	public static function Process($App = "QApp", QIUrlController $controller = null, $skip_url = null)
	{
		self::$AsyncRequest = $_POST["__asyncRequest__"];
		self::$RemoteRequest = ($_POST["__remoteRequest__"] || $_SERVER['HTTP_REMOTEREQUEST']);
		self::$_pid = uniqid("_pid_", true);
		//\QApi::DebugAsync("Process " . self::$_pid . " started at: " . date("Y-m-d H:i:s") . "==========================<br/>", $_POST);
		//\QApi::DebugRemote("Process " . self::$_pid . " started at: " . date("Y-m-d H:i:s") . "==========================<br/>", $_POST);
		return static::frame_Process($App, $controller, $skip_url);
	}

	public static function IsRemoteRequest()
	{
		return self::$RemoteRequest;
	}
	
	public static function SetIsRemoteRequest(bool $value = null)
	{
		self::$RemoteRequest = $value;
	}

	public static function IsAsyncRequest()
	{
		return self::$AsyncRequest;
	}

	/**
	 * Sends the AJAX response
	 */
	public static function SendAjaxResponse()
	{
		if (self::IsRemoteRequest())
		{
			if (!self::$AjaxResponse)
				self::$AjaxResponse = [null];
			self::$AjaxResponse[] = \QApi::Call('GetCurrentOwner');
		}
		static::frame_SendAjaxResponse();
	}
	/**
	 * Returns the base url of the application
	 * 
	 * @return string
	 */
	public static function GetBaseUrlSecured()
	{
		if (self::$BaseUrl)
			return self::$BaseUrl;
		return self::$BaseUrl = ((substr(BASE_HREF, 0, 4) != "http") ? "https://" . $_SERVER['HTTP_HOST'] : "").BASE_HREF;
	}
	
	public static function ResetStaticContext()
	{
		$data = [
			"FastAjax" => static::$FastAjax,
			"RemoteRequest" => static::$RemoteRequest, 
		];
		
		static::$FastAjax = null;
		static::$RemoteRequest = null;
		
		return $data;
	}
	
	public static function RestoreStaticContext($data)
	{
		foreach ($data as $k => $v)
			static::$$k = $v;
	}
	
	public static function HandleRESTFul($request = null)
	{
		$url = QUrl::$Requested = new QUrl($request);
		
		if (empty($request))
		{
			// specs here
			include(__DIR__.'/api_specs.tpl');
		}
		else
		{
			$response = null;
			
			if  ((strtolower(trim($_SERVER["CONTENT_TYPE"])) === 'application/json') && (empty($_POST)))
			{
				$_POST = json_decode(file_get_contents('php://input'), true);
			}
			
			header('Content-Type: application/json');

			try
			{
				$url_first = $url->reset();
				
				$json_str = file_get_contents("php://input");
				
				$json_data = null;
				if (!empty($json_str))
					$json_data = json_decode($json_str, true);
				
				if ($url_first === 'login')
				{
					// !!! for test only !!!
					/*if (empty($json_str))
					{
						$json_str = '{"user": "global4_sa", "pass": "Global4%pax52"}';
						$json_data = json_decode($json_str, true);
					}*/
					// !!! end test only !!!
					
					// extract $user, $pass from JSON 
					if ($json_data)
					{
						$user = $json_data['user'];
						$pass = $json_data['pass'];
						if ($user && $pass)
							$response = static::HandleRESTFul_Login($user, $pass);
					}
					if (!$response)
					{
						http_response_code(401);
						// header('HTTPS/1.0 401 Unauthorized');
					}
				}
				elseif ($url_first === 'get-login-url')
				{
					$login_url_code = \Omi\User::createLoginUrl();
					$response = ['login_url_code' => $login_url_code, "url" => static::GetBaseUrlSecured() . '?login_access_code=' . urlencode($login_url_code)];
				}
				else
				{
					// check login
					$userIsLoggedIn = \Omi\User::CheckLogin();
					
					if (!$userIsLoggedIn)
					{
						// header('HTTPS/1.0 401 Unauthorized');
						http_response_code(401);
						$response = false;
					}
					else
					{
						// handle requests here
						
						$allowed_resources = static::GetAPIAllowedProperties();
						
						if (($collection = $allowed_resources[$url_first]))
						{
							// auto-handle - atm listing and view only
							// Query($from, $selector = null, $parameters = null, $only_first = false, $id = null)
							if ($url_first === 'Quotes')
								$filter = ['IsQuote' => 1];
							else if ($url_first === 'Orders')
								$filter = ['IsQuote' => 0];
							
							$next_url = $url->next();
							$id = $next_url ? $url->next() : null;
							
							$only_first = false;
							$parameters = $json_data;
							if ($next_url)
								$only_first = true;
							
							$selector = filter_input(INPUT_GET, 'selector') ?: null;
							
							if ($next_url && (!in_array($next_url, ["view"])))
								$response = false;
							else
							{
								if (!is_array($parameters))
									$parameters = null;
								else
								{
									$new_params = [];
									foreach ($parameters as $param_k => $param_v)
									{
										$skip = false;
										switch ($param_k)
										{
											case "Gid":
											case "NoGid":
											case "HasGid":
											case "Owner":
											case "InSyncProcess":
											case "ToBeSynced":
											case "LastSyncedAt":
											case "CreatedBy":
											case "SuppliedBy":
											{
												$skip = true;
												break;
											}
										}
										if (!$skip)
											$new_params[$param_k] = $param_v;
									}
									$parameters = $new_params;
								}
								
								ob_start();
								\QApi::$DebugApi = false;
								$response = \QApi::Query($collection, $selector, $parameters, $only_first, $id);
								ob_end_clean();
							}
						}
					}
				}

				// QOutputJson($data, $ignore_nulls = true, &$refs = null, $metadata = true, $array_in_items = true)
				
				$refs = null;
				\QModel::QOutputJson($response, true, $refs, false);
				
				// qvar_dump($response);
				// echo json_encode($response);
			}
			catch (\Exception $ex)
			{
				http_response_code(500);
				echo json_encode(["error" => ["message" => "There is an error."]]);
			}
		}
		
		
	}
	
	public static function HandleRESTFul_Login($user_or_email, $pass)
	{
		if ($user_or_email && $pass)
		{
			$login = \Omi\User::Login(trim($user_or_email), trim($pass));
			
			if (($login === true) || ($login instanceof \QUser))
			{
				// if we don't have url current then we are in backend/
				//$_SESSION['show_message'] = true;
				return $login ? true : false;
			}
			$error = $login;
		}
		else
			$error = "Username and password are mandatory!";
		return false;
	}	

	public static function GetAPIAllowedProperties()
	{
		return [
				'Customers'			=> 'Customers',
				'Partners'			=> 'Partners',
				'Offers'			=> 'Offers',
				'PriceProfiles'		=> 'PriceProfiles',
				'Products'			=> 'Products',
				'Services'			=> 'Services',
				'MerchCategories'	=> 'MerchCategories',
				'Manufacturers'		=> 'Manufacturers',
				'Nuvia_Users'		=> 'Nuvia_Users',

				'Quotes' => 'Orders',
				'Orders' => 'Orders'
			];
	}
}
