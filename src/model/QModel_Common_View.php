<?php

trait QModel_Common_View
{
	protected static $_PropertyData = [];
	
	protected static $_PropertiesCaptions = [];
	
	
	public static function GetCaption($caption, $parent_model = null, $property = null, $propAlias = null, $full_cache_skip = false)
	{
		$types_identif = is_array($parent_model) ? implode("~", $parent_model) : $parent_model;

		if (!$full_cache_skip && static::$_PropertiesCaptions[$propAlias] && static::$_PropertiesCaptions[$propAlias][$types_identif] && 
			static::$_PropertiesCaptions[$propAlias][$types_identif][$property ?: "view"])
		{
			$caption = static::$_PropertiesCaptions[$propAlias][$types_identif][$property ?: "no_prop"];
		}
		else if ($propAlias)
		{
			if ($property)
			{
				$mixed_data = static::MixPropertyData($parent_model, $property, $propAlias);
				// mixed types - details about the field
				$mixed_types = $mixed_data["types"];

				$captions = ($mixed_types["storage.full"] && $mixed_types["storage.full"]["captions"]) ? 
					json_decode($mixed_types["storage.full"]["captions"], true) : null;
				
				if ($captions && $captions[$propAlias])
					$caption = $captions[$propAlias];
			}
			else if (($propAlias_caption = static::GetViewCaption($parent_model, $propAlias)))
				$caption = $propAlias_caption;

			if (!$full_cache_skip)
			{
				if (!static::$_PropertiesCaptions[$propAlias])
					static::$_PropertiesCaptions[$propAlias] = [];

				if (!static::$_PropertiesCaptions[$propAlias][$types_identif])
					static::$_PropertiesCaptions[$propAlias][$types_identif] = [];

				static::$_PropertiesCaptions[$propAlias][$types_identif][$property ?: "no_prop"] = $caption;
			}
		}
		
		$caption = preg_replace_callback('/(?<!\b)[A-Z][a-z]+|(?<=[a-z])[A-Z]/', function($match) {
			return ' '. $match[0];
		}, $caption);
		$caption = preg_replace("/([\\_\\s])+/uis", " ", $caption);
		
		return $caption;

		/*
		return preg_replace_callback("/(( Or)(?= )|( Or$))|(( Of)(?= )|( Of$))|(( The)(?= )|( The$))|(( And)(?= )|( And$))/", function ($matches) {
			return strtolower($matches[0]);
		}, trim(implode(" ", preg_split('/(?=[A-Z])/', $caption))));
		*/
	}
	
	/**
	 * Returns view caption
	 * 
	 * @param array|string $types
	 * @param string $propAlias
	 * @return string
	 */
	public static function GetViewCaption($types, $propAlias)
	{
		// check if types
		if (!$types)
			return null;

		if (!is_array($types))
			$types = [$types];

		$ret = null;
		foreach ($types as $ty)
		{
			$type_inf = \QModelQuery::GetTypesCache($ty);
			if ($type_inf["#%misc"]["model"]["captions"] && ($c_arr = json_decode($type_inf["#%misc"]["model"]["captions"], true)) && ($ret = $c_arr[$propAlias]))
				break;
		}
		return $ret;
	}
	
	/**
	 * 
	 * @param type $src_from_types
	 * @param type $k
	 * @return type
	 */
	public static function MixPropertyData($src_from_types, $k, $propAlias = null, $force_mandatory = false, 
		$force_validation = null, $force_fix = null)
	{
		$r_types = [];
		
		if (is_string($src_from_types))
			$src_from_types = [$src_from_types];

		$src_from_types_indx = implode("~", $src_from_types)."~".$propAlias;

		if (static::$_PropertyData[$src_from_types_indx] && static::$_PropertyData[$src_from_types_indx][$k])
			return static::$_PropertyData[$src_from_types_indx][$k];

		if (!static::$_PropertyData[$src_from_types_indx])
			static::$_PropertyData[$src_from_types_indx] = [];

		foreach ($src_from_types as $src_type)
		{
			$mi = \QModel::GetTypeByName($src_type);
			
			$prop = $mi->properties[$k];
			if (!$prop)
				continue;
			
			$prop_display = $prop->display;
			$ty_arr = is_array($prop->types) ? $prop->types : [$prop->types];
			$prop_storage = is_array($prop->storage) ? $prop->storage : [$prop->storage];
						
			$prop_cfg = is_array($prop->cfg) ? $prop->cfg : (($prop->cfg !== null) ? [$prop->cfg] : []);
			$prop_validation = $force_validation ?: ($prop->validation ? $prop->validation : null);
			$prop_fix = $force_fix ?: ($prop->fixValue ? $prop->fixValue : null);

			$caption = $prop->name;
			if ($propAlias)
			{
				$captions = ($prop_storage && $prop_storage['captions']) ? json_decode($prop_storage['captions'], true) : null;
				if ($captions && $captions[$propAlias])
					$caption = $captions[$propAlias];
			}	

			$prop_caption = preg_replace_callback('/(?<!\b)[A-Z][a-z]+|(?<=[a-z])[A-Z]/', function($match) {
				return ' '. $match[0];
			}, $caption);

			$alert = null;
			$info = null;

			if ($prop_storage["mandatory"] || $force_mandatory)
			{
				if (!$force_mandatory)
				{
					$mandatory_on_views = json_decode($prop_storage["mandatory"], true);
					if ($mandatory_on_views && isset($mandatory_on_views[$propAlias]))
						$prop_validation = "mandatory";
				}
				else
					$prop_validation = "mandatory";
			}

			if ($prop_validation)
			{
				// get tokens from validation str
				$tokens = \QCodeSync::ParseValidationRules($prop_validation);

				$validation_str = \QCodeSync::GetValidationStr($prop_validation, null, "\$value");
				$validation_toks = token_get_all("<?php ".$validation_str);
				array_shift($validation_toks);

				list($alert, $info) = \QCodeSync::GetValidationData($prop_validation, $prop_caption);

				// get javascript validation function
				$ret = [];
				$js_validation_resp = \QPHPTokenDocComment::CreateJsFunc($validation_toks, null, null, $ret);

				$is_mandatory = false;
				foreach ($tokens as $key => $tok)
				{
					if ($tok[0] === T_STRING)
					{
						if ($tok[1] === 'mandatory')
						{
							$is_mandatory = true;
							break;
						}
					}
				}
			}
			

			if ($prop_fix)
			{
				$fix_str = \QCodeSync::GetFixValStr($prop_fix, null, "\$value");
				$fix_toks = token_get_all("<?php " . $fix_str);
				array_shift($fix_toks);
				
				// get javascript validation function
				$ret = array();
				$js_fix_resp = \QPHPTokenDocComment::CreateJsFunc($fix_toks, null, null, $ret);
			}

			foreach ($ty_arr as $prop_types)
			{ 
				if (is_string($prop_types))
				{
					if ($prop_types{0} === strtolower($prop_types{0}))
						$r_types["\$"][$prop_types] = $prop_types;
					else
						$r_types["#"][$prop_types] = $prop_types;
				}
				else if ($prop_types instanceof \QModelAcceptedType)
				{
					foreach ($prop_types->options as $opt)
						$r_types["[]"][$opt] = $opt;
				}
			}

			if (!isset($prop_storage['display']))
				$prop_storage['display'] = [];

			else if (is_string($prop_storage['display']))
				$prop_storage['display'] = [$prop_storage['display'] => $prop_storage['display']];

			if (!$prop_display)
				$prop_display = [];

			$r_types["storage"]				= isset($prop_storage['type']) ? $prop_storage['type'] : null;
			$r_types["storage.full"]		= $prop_storage ? $prop_storage : null;
			$r_types["admin.readonly"]		= $prop_storage["admin.readonly"];
			$r_types["dependency"]			= isset($prop_storage['dependency']) ? $prop_storage['dependency'] : null;
			$r_types["display"]				= array_merge($prop_display, $prop_storage['display']);
			$r_types["mandatory"]			= $is_mandatory;
			$r_types["default"]				= $prop->default;
			$r_types["js_validation"]		= isset($js_validation_resp) ? implode("", $js_validation_resp[1]) : null;
			$r_types["js_fix"]				= isset($js_fix_resp) ? implode("", $js_fix_resp[1]) : null;
			$r_types["validation_alert"]	= $alert;
			$r_types["validation_info"]		= $info;
			$r_types['prop_caption']		= $prop_caption;
			
			// $prop_cfg = is_array($prop->cfg) ? $prop->cfg : [$prop->cfg];
			$r_types['cfg']					= $prop_cfg;
			
			// setup mixed data on property
			$prop->_mixed_data = $r_types;
		}
		return (static::$_PropertyData[$src_from_types_indx][$k] = ["types" => $r_types]);
	}
}