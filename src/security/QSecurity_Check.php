<?php

final class QSecurity_Check
{
	const Call_Type_Api = 1;
	const Call_Type_Api_Query = 2;
	const Call_Type_Api_Save = 4;
	
	protected static $Check_Login_Done = false;
	protected static $Log_Path = null;
	
	public static function Audit_Request()
	{
		# cookie @ session empty when not logged in
		# $_COOKIE :: PHPSESSID__fuse2[string(26)]: "npfg294m5i7p290todnnhrr9d5"
		if (!defined('Q_REQUEST_UID'))
			define('Q_REQUEST_UID', uniqid("", true));
		$request_id = Q_REQUEST_UID;
		
		$input_content = null;
		$raw_input = fopen('php://input', 'r');
		if ($raw_input)
		{
			$input_content = stream_get_contents($raw_input);
			rewind($raw_input);
		}
		
		$ip_val = inet_pton($_SERVER['REMOTE_ADDR']);
		
		$data = [
			'sname' => session_name(),
			'sid' => $_COOKIE[session_name()],

			'rm' => $_SERVER['REQUEST_METHOD'],
			'rp' => (int)$_SERVER['REMOTE_PORT'],
			'https' => $_SERVER['HTTPS'] ? 1 : 0,
			'rtime' => $_SERVER['REQUEST_TIME'],
			
			# these should be hashed
			'ra' => $ip_val,
			'ruri' => $_SERVER['REQUEST_URI'],
			'qs' => strlen($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null,
			'sf' => $_SERVER['SCRIPT_FILENAME'],
			'hh' => $_SERVER['HTTP_HOST'],
			'cookie' => $_SERVER['HTTP_COOKIE'],
			'ua' => $_SERVER['HTTP_USER_AGENT'],
			'pself' => $_SERVER['PHP_SELF'],
			
			# 'get' => $_GET ? json_encode($_GET) : null, # use query string
			'raw' => strlen($input_content) > 0 ? $input_content : null,
			'files' => $_FILES ?: null,
			'sess' => $_SESSION ?: null,
			# $_GET 
			# $_POST
			# $_FILES
			# $_SESSION

		];
		
		static::$Log_Path = static::Get_Logs_Path($request_id);
		
		$batch = gzencode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		file_put_contents(static::$Log_Path, "request(".strlen($batch)."):".$batch."\n", FILE_APPEND);
	}
	
	public static function Audit_Login()
	{
		$c_user = \Omi\User::GetCurrentUser(false, false);
		$data = false;
		if ($c_user)
		{
			$data = [
				'id' => $c_user->Id,
				'user' => $c_user->Username,
			];
		}
		
		$batch = gzencode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		file_put_contents(static::$Log_Path, "login(".strlen($batch)."):".$batch."\n", FILE_APPEND);
	}
	
	public static function Audit_CheckLogin($identity)
	{
		if (static::$Check_Login_Done)
			return;
		static::$Check_Login_Done = true;
		
		$data = false;
		if ($identity instanceof \QIModel)
		{
			$user = $identity->User;
			$owner = $user ? $user->Owner : null;
			$data = [
				'idnt' => $identity ? $identity->Id : null,
				'user' => $user ? $user->Id : null,
				'owner' => $owner ? $owner->Id : null,
			];
		}
		
		$batch = gzencode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		file_put_contents(static::$Log_Path, "check-login(".strlen($batch)."):".$batch."\n", FILE_APPEND);
	}
	
	public static function Secure_Request_Data(
			$data, 
			$selector = null, # array or string
			string $app_property = null, # if none ... pick it up ?! ... if more than one ... needs to respect all !
			int $perms_flag = \QModel::TransformRead,
			bool $perms_flag_overwrites = false,
			bool $perms_flag_safe = false,
			string $request_class = null, 
			string $request_method = null, 
			bool $audit = true, 
			bool $audit_only = true
			)
	{
		$bag = new \SplObjectStorage();
		# $app_class = defined('Q_DATA_CLASS') ? Q_DATA_CLASS : \QApp::GetDataClass();
		# $app = new $app_class;
		list ($indexed, $types_index) = static::Secure_Request_Data_Group($data, $bag, $selector, $app_property ?: "");
		
		# step 1: on the first level : ensure for path "..." ... owns it , enforce owner when missing ... etc
		#				drop data that should not be accessible
		
		# ORDER OF DOING THINGS : 1. populate / 2. enforce || path based
		#							enforce owner for new data !!!
		# 
	
		qvar_dumpk($app_property, $indexed, $types_index);
		die;
		
		# defined in :: QModelType :: methodHasApiAccess
		
		# FOUND IN:
			# base.php => execQB
			# QApi::Call & QApi::Query | QApi::Save | ::QSync/__QSync | Import
			# QViewBase :: ApiDynamic_Ctrl
			# Order :: checkStatusChange
			# DropDown :: GetRenderItems
			# QRESTController :: getRESTData
		
		# api.enabled | QApi::Query | QApi::Save | QApi::Other ???
		# @SECURE BOTH INPUT OUTPUT
		#	ABLE TO RUN AUDIT ONLY - DO NOT TOUCH INPUT/OUTPUT
		# ALSO : URL CONTROLLER REQUEST
				# ALSO = the main render of the controller / the render setup !
		# ALSO : In case of a Grid - audit & secure :: GetListData && FormSubmit
	}
	
	protected static function Secure_Request_Data_Group($data, \SplObjectStorage &$bag, $selector = null, string $path = "")
	{
		$indexed = [];
		$types_index = [];
		$items = [];
		
		if (is_array($data))
		{
			foreach ($data as $d)
				if ($d instanceof \QIModel)
					$items[$path][] = $d;
		}
		else if ($data instanceof \QIModel)
			$items[$path] = [$data];
		
		while ($items)
		{
			$new_items = [];
			
			foreach ($items as $i_path => $itm_list)
			{
				foreach ($itm_list as $itm)
				{
					if ($itm instanceof \QIModelArray)
					{
						if (!isset($bag[$itm]))
						{
							$indexed[$i_path]["[]"]["[]"] = $itm;
							$types_index["[]"][$i_path] = true;

							foreach ($itm as $ii)
							{
								if ($ii instanceof \QIModel)
									$new_items[$i_path][] = $ii;
							}
							$bag[$itm] = true;
						}
					}
					else if ($itm instanceof \QIModel)
					{
						if (!isset($bag[$itm]))
						{
							$class_name = get_class($itm);
							$itm_id = $itm->Id ?? ($itm->_id ?? ($itm->_tmpid ?? ""));
							$indexed[$i_path][$class_name][$itm_id][] = $itm;
							$types_index[$class_name][$i_path] = true;

							$loop_props = null;
							if ($selector === null)
								$loop_props = \QModel::GetTypeByName($class_name)->properties;
							else
								$loop_props = $selector;

							foreach ($loop_props as $prop_name => $prop)
							{
								$sub_itm = $itm->$prop_name;
								if ($sub_itm instanceof \QIModel)
									$new_items[$i_path ? ($i_path.".".$prop_name) : $prop_name][] = $sub_itm;
							}

							# avoid endless loop
							$bag[$itm] = true;
						}
					}
				}
			}
			
			# next increment
			$items = $new_items;
		}
		
		return [$indexed, $types_index];
	}
	
	protected static function Get_Logs_Path(string $request_id)
	{
		$m = null;
		$reg_ex = "/^(\\/home\\/([^\\/]+)\\/)public\\_html(?:\$|\\/)(.*)\$/uis";
		$rc = preg_match($reg_ex, Q_RUNNING_PATH, $m);
		if ((!$rc) || (count($m) < 4))
			throw new \Exception("Expecting Q_RUNNING_PATH to match pattern `{$reg_ex}`");
			
		$home_dir = $m[1];
		if (!is_dir($home_dir))
			throw new \Exception("Invalid home dir: `{$home_dir}`");
		$logs_dir = $home_dir."logs/";
		if (!is_dir($logs_dir))
			throw new \Exception("Invalid logs dir: `{$logs_dir}`");
			
		$app_rel_dir = $m[3];
		
		$audits_path = $logs_dir."/audits/".$app_rel_dir."/";
		if (!is_dir($audits_path))
			qmkdir($audits_path);
		$audits_path = realpath($audits_path)."/";
			
		$time = $_SERVER["REQUEST_TIME_FLOAT"];
		$path = $logs_dir . "/audits/" . $app_rel_dir . "/" . date('Y-m-d', $time) . "/";
		if (!is_dir($path))
			qmkdir($path);
		$path = realpath($path) . "/";
		
		$file_name = date('H:i s', $time)." - ".$request_id.".json.gzip";
		$path .= $file_name;
		
		return $path;
	}
}
