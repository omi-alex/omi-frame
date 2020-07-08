<?php

final class QSecurity 
{
	const anon = 1;
	const auth = 2;
	const any = 3;
	
	const View = 1;
	const Add = 2;
	const Edit = 4;
	const Delete = 8;
	
	const ModelContext = 1;
	const ContextContext = 2;
	
	const Read = 1;
	
	protected static $Security = [];
	protected static $SecurityFolder;
	protected static $RecordLevelSecurity = [];
	
	protected static $TestProp;
	
	/**
	 * Security types:
	 *		0 - "none"			- no security is enforce by default
	 *		1 - "permissive"	- allow create and own for all DATA_CLASS.PROPERTIES, all elements that don't have record level security 
	 *							  will be forbidden by default
	 *		2 - "strict"		- only allow access if there is explicit access
	 */
	protected static $SecurityModel = 0;
	
	public static function GetPredefinedRoles()
	{
		/**
		 * 	anonymous:		1 (not authenticated)
		 *	authenticated: 	2
		 *	anyone:			3 (authenticated + anonymous)
		 */
		return [
			QSecurity::anon => "anon",
			QSecurity::auth => "auth",
			QSecurity::any => "any",
		];
	}
	
	public static function GetPrecheckedRoles()
	{
		return [QSecurity::anon, QSecurity::auth, QSecurity::any];
	}
	
	public static function GetClassContexts()
	{
		return [QSecurity::ModelContext => "Model", QSecurity::ContextContext => "Context"];
	}
	
	public static function GetPrecheckedContexts()
	{
		return [QSecurity::ContextContext => "Context"];
	}
	
	/**
	 * Secure query
	 *		1. Can execute that, by default only user (logged in)
	 *		2. Secure selector
	 *		3. Get Query filter
	 *		4. Secure return ? - optional, may not be needed
	 * 
	 * @return boolean
	 */
	public static function Security_UserCanQuery($src_from, $src_from_types = null)
	{
		if ($src_from_types === null)
		{
			$storage_model = QApp::GetDataClass();
			$src_from_types = \QApi::DetermineFromTypes($storage_model, $src_from);
		}
		
		// we need read perms
		if (is_array($src_from))
			$src_from = implode('.', $src_from);
		
		if (($security = static::$Security[$src_from]) === null)
			$security = static::LoadSecurityForModel($src_from);
		
		if (($security === false) || ($security === null))
			return false;
		else
			return ($security[0] & \QSecurity::Read) ? true : false;
	}
	
	/**
	 * Secure query
	 *		2. Secure selector
	 *		3. Get Query filter
	 */
	public static function Security_ReduceSelector($src_from = null, $storage_model = null, $multiple = false)
	{
		// @TODO - cache the info !
		
		// we are only allowed to use this
		
		if ($storage_model === null)
			$storage_model = QApp::GetDataClass();
		
		// 1. the model selector
		if ($multiple)
			$model_selector = $storage_model::GetPropertyListingEntity($src_from);
		else
			$model_selector = $storage_model::GetPropertyModelEntity($src_from);
		
		// 2. the allowed elements for read
		// we need this cached, we can't process it here as it would be too slow
		// we will cache it on the fly
		if (($security = static::$Security[$src_from]) === null)
			$security = static::LoadSecurityForModel($src_from);
		if (($security === false) || ($security === null))
			return false;
		
		// 
	}
	
	
	/*
		Query($from, $selector = null, $parameters = null, $only_first = false, $id = null)
		foreach ($parsed_sources as $src_key => $src_info)
		{
			$storage::ApiQuery($storage_model, $src_from, $src_from_types, $selector, $parameters, $only_first, $id);
			// @todo : handle multiple requests on the same source
			$src_from = reset($src_info);
			$storage = QApp::GetStorage($src_key);
			$storage_model = QApp::GetDataClass();
			$src_from_types = static::DetermineFromTypes($storage_model, $src_from);
			$result[$src_key] = $storage::ApiQuery($storage_model, $src_from, $src_from_types, $selector, $parameters, $only_first, $id);
		}
		*/
	
	public static function LoadSecurityForModel(string $path)
	{
		// @TODO 
		static::$Security[$path] = false;
		return false;
	}
	
	public static function InitSecurity()
	{
		$t0 = microtime(true);
		
		$s_dir = \QAutoload::GetRuntimeFolder()."~security/";
		if (!is_dir($s_dir))
			// no security is setup
			return false;
		
		static::$SecurityFolder = $s_dir;
		include($s_dir.'@security.php');
		include($s_dir.'@record.php');
		
		// public static Closure fromCallable ( callable $callable )
		$t1 = microtime(true);
		// var_dump(($t1 - $t0)*1000, static::$RecordLevelSecurity, static::$TestProp, $s_dir,"InitSecurity");
		// die;
		
		$resp = static::UserCan(\QSecurity::Read, ['GenbandSBC_Endpoints' => true]);
		//qvar_dump($resp);
		
		//die;
	}
	
	// secure save
	
	// secure call
	
	public static function GetSQLFilter(string $property, \QUser $user = null)
	{
		$sp = static::$Security[$property];
		if ($sp === null)
			$sp = static::EnsurePerms($property);
		if ($sp === false)
		{
			// this means we don't have any rules
			/**
			 * Security types:
			 *		0 - "none"			- no security is enforce by default
			 *		1 - "permissive"	- allow create and own for all DATA_CLASS.PROPERTIES, all elements that don't have record level security 
			 *							  will be forbidden by default
			 *		2 - "strict"		- only allow access if there is explicit access
			 */
			if (static::$SecurityModel === 0)
				return null;
			else
				throw new \Exception('@todo');
		}
		
		$perms = $sp[0];
		$rules = $perms[S_View];
		if (is_int($rules))
			$rules = $perms[$rules];
		if (!$rules)
			return null;
		
		if (!is_array($rules))
			$rules = [$rules];
		
		$filter = null;
		
		$user = \Omi\App::GetSecurityUser();
		if (!$user)
			throw new \Exception('Missing user');
		
		foreach ($rules as $rule)
		{
			list ($group, /* static */, /* dynamic */ , $sql_filter) = $rule;
			if (!$sql_filter)
				continue;
			if ($group)
			{
				// check if group is satisfied
			}
			
			$filter = ($sql_filter instanceof \Closure) ? $sql_filter(\Omi\App::GetSecurityUser()) : $sql_filter;
			// qvar_dump($filter);
			if ($filter)
				break;
		}
		
		return $filter;
	}
	
	public static function EnsurePerms(string $property)
	{
		$s_dir = static::$SecurityFolder;
		if (file_exists($s_dir.$property.".php"))
		{
			$__PERMS = null;
			require($s_dir.$property.".php");
			if ($__PERMS === null)
				$sp = static::$Security[$property] = false;
			else 
				$sp = static::$Security[$property] = $__PERMS;
		}
		else
			$sp = static::$Security[$property] = false;
		
		return $sp;
	}

	public static function UserCan(int $access, array $path, \QIModel $object = null, \QUser $user = null)
	{
		$property = key($path);
		$sp = static::$Security[$property];
		if ($sp === null)
		{
			$sp = static::EnsurePerms($property);
		}
		if ($sp === false)
		{
			// this means we don't have any rules
			/**
			 * Security types:
			 *		0 - "none"			- no security is enforce by default
			 *		1 - "permissive"	- allow create and own for all DATA_CLASS.PROPERTIES, all elements that don't have record level security 
			 *							  will be forbidden by default
			 *		2 - "strict"		- only allow access if there is explicit access
			 */
			if (static::$SecurityModel === 0)
				return true;
			else
				throw new \Exception('@todo');
		}
		else
		{
			$perms = $sp[0];
			$rules = $perms[$access];
			if (is_int($rules))
				$rules = $perms[$rules];
			
			// @TODO - we should now loop properties and get proper data
		}
		
		return true;
	}	
}
