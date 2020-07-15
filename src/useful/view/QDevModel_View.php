<?php

/**
 * @storage.table Zzz_DevModel_View
 * @model.captions {"Dev_Views": "Views"}
 * @model.captionProperties Tag
 */
class QDevModel_View extends QModel
{
	use QDevModel_View_GenTrait;
	
	/**
	 * @storage.index
	 * @display.properties blocked_when_data
	 * @var string
	 */
	protected $Tag;
	/**
	 * @var string
	 */
	protected $Caption;
	/**
	 * @display.properties blocked_when_data
	 * @validation mandatory
	 * @var string
	 */
	protected $Property;
	/**
	 * @var string
	 * @validation mandatory
	 */
	protected $DataType;
	/**
	 * @var boolean
	 */
	protected $DataIsCollection = true;
	/**
	 * @storage.admin.readonly
	 * @var boolean
	 */
	protected $DataIsStrict = true;
	/**
	 * @var string
	 * @validation mandatory
	 */
	protected $Table;
	/**
	 * @var string[]
	 * 
	 * @storage.display individual
	 */
	protected $Properties;
	
	/**
	 * @api.enable
	 * 
	 * @param string $from
	 * @param string $selector
	 * @param array $parameters
	 * 
	 * @return QIModel
	 */
	public static function Query_S($from, $selector = null, $parameters = null, $only_first = false, $id = null)
	{
		if (!\QAutoload::GetDevelopmentMode())
			throw new \Exception('You are not in development mode');
		
		$limit = $parameters['LIMIT'] ?: [0, 50];
		if (!is_array($limit))
			$limit = [0, $limit];
		unset($parameters['LIMIT']);

		if ($from === 'Dev_Views')
		{
			if ($only_first)
			{
				// only first
				$list = static::GetAllViews_S($parameters, $limit[0], $limit[1], is_array($id) ? reset($id) : $id);
				$ret_view = $list ? reset($list) : null;
				if ($ret_view)
				{
					$app_class = \QApp::GetDataClass();
					$model_entity = $app_class::GetFormEntity_Final($ret_view->Tag);
					if ($model_entity === null)
						$app_class::GetPropertyModelEntity($ret_view->Tag);
					if ($model_entity === null)
					{
						$m_type = \QModel::GetTypeByName($ret_view->DataType);
						$m_type->class::GetModelEntity();
					}
					if (is_string($model_entity))
						$model_entity = qParseEntity($model_entity);
					
					/*
					GetEntityForGenerateForm : show in form
					GetEntityForGenerateList : show in list

					GetFormEntity : query one
					GetListEntity : query list

					(done) GetFormEntity | GetPropertyModelEntity | GetModelEntity

					 */
					$selector_grid_form = $app_class::GetEntityForGenerateForm_Final($ret_view->Tag);
					$selector_grid_list = $app_class::GetEntityForGenerateList_Final($ret_view->Tag);
					$selector_query_form = $app_class::GetFormEntity_Final($ret_view->Tag);
					$selector_query_list = $app_class::GetListEntity_Final($ret_view->Tag);
					
					if (is_string($selector_grid_form))
						$selector_grid_form = qParseEntity($selector_grid_form);
					if (is_string($selector_grid_list))
						$selector_grid_list = qParseEntity($selector_grid_list);
					if (is_string($selector_query_form))
						$selector_query_form = qParseEntity($selector_query_form);
					if (is_string($selector_query_list))
						$selector_query_list = qParseEntity($selector_query_list);
					
					// @TODO - test if selectors are OUTSIDE $model_entity !!!
					
					$ret_view->Properties = [];
					
					if ($model_entity !== null)
						$ret_view->populateProperties(null, $ret_view->Properties, $model_entity, $selector_query_list, $selector_query_form, $selector_grid_list, $selector_grid_form);
				}
				return $ret_view;
			}
			else
			{
				// other params are possible
				return static::GetAllViews_S($parameters, $limit[0], $limit[1]);
			}
		}
	}
	
	public function populateProperties(\QModelType $m_type = null, array &$properties = null, array $model_entity = null, 
												$selector_query_list = null, $selector_query_form = null, $selector_grid_list = null, $selector_grid_form = null, 
												array $path = [], int $depth = 0)
	{
		// $data_type = $this->DataType;
		if (!$m_type)
			$m_type = \QModel::GetTypeByName($this->DataType);
		if (!$m_type)
			return;
		
		/*$sorted_props = [];
		foreach ($m_type->properties as $m_property)
			$sorted_props[$m_property->name] = $m_property;
		ksort($sorted_props);*/
		
		$selector_query_list_poses = [];
		$selector_query_form_poses = [];
		$selector_grid_list_poses = [];
		$selector_grid_form_poses = [];
		$pos = 0;
		foreach ($selector_query_list ?: [] as $k => $v)
			$selector_query_list_poses[$k] = $pos++;
		$pos = 0;
		foreach ($selector_query_form ?: [] as $k => $v)
			$selector_query_form_poses[$k] = $pos++;
		$pos = 0;
		foreach ($selector_grid_list ?: [] as $k => $v)
			$selector_grid_list_poses[$k] = $pos++;
		$pos = 0;
		foreach ($selector_grid_form ?: [] as $k => $v)
			$selector_grid_form_poses[$k] = $pos++;
		
		foreach ($m_type->properties as $m_property)
		{
			if (($m_property->storage["none"]) || ($m_property->name === 'Del__'))
				continue;
			
			$model_entity_prop = $model_entity ? $model_entity[$m_property->name] : null;
			if ($model_entity_prop === null)
				continue;
			
			if (is_string($m_property->types))
				$p_type = $m_property->types;
			else if ($m_property->types instanceof \QModelAcceptedType)
				$p_type = reset($m_property->types->options);
			else
				throw new \Exception('@not accepted');
			
			$isCollection = $m_property->hasCollectionType();
			$isStrict = $m_property->strict ? true : false;
			
			$ch_path = $path;
			$ch_path[] = $m_property->name;
			$properties[$m_property->name] = [
				'name' => $m_property->name,
				// 'is_selected' => $selector && ($selector[$m_property->name] !== null),
				'type' => $p_type,
				'is_collection' => $isCollection,
				'is_strict' => $isStrict,
				'path' => implode('.', $ch_path),
				'depth' => $depth,
				
				'selector_query_list' => $selector_query_list && ($selector_query_list[$m_property->name] !== null) ? true : false, 
				'selector_query_form' => $selector_query_form && ($selector_query_form[$m_property->name] !== null) ? true : false, 
				
				'selector_grid_list' => $selector_grid_list && ($selector_grid_list[$m_property->name] !== null) ? true : false, 
				'selector_grid_form' => $selector_grid_form && ($selector_grid_form[$m_property->name] !== null) ? true : false, 

				'selector_query_list_pos' => $selector_query_list_poses[$m_property->name] ?? -1, 
				'selector_query_form_pos' => $selector_query_form_poses[$m_property->name] ?? -1, 
				
				'selector_grid_list_pos' => $selector_grid_list_poses[$m_property->name] ?? -1, 
				'selector_grid_form_pos' => $selector_grid_form_poses[$m_property->name] ?? -1, 
				
				'children' => [],
			];
			
			if ($model_entity_prop && ($p_type{0} !== strtolower($p_type{0})) && 
						($prop_type_m_ty = \QModel::GetTypeByName($p_type)))
			{
				// has ref
				$this->populateProperties($prop_type_m_ty, $properties[$m_property->name]['children'], $model_entity_prop,
							$selector_query_list ? $selector_query_list[$m_property->name] : null,
							$selector_query_form ? $selector_query_form[$m_property->name] : null,
							$selector_grid_list ? $selector_grid_list[$m_property->name] : null,
							$selector_grid_form ? $selector_grid_form[$m_property->name] : null, $ch_path, $depth + 1);
			}
		}
		
		unset($properties);
	}
	
	public static function GetAllViews_S($parameters, $offset = 0, $count = 50, $id = null)
	{
		$storage_model	= \QApp::GetDataClass();
		$m_type = \QModel::GetTypeByName($storage_model);
		
		$all = [];
		
		foreach ($m_type->properties as $m_property)
		{
			if (($m_property->storage["none"]) || ($m_property->name === 'Id') || ($m_property->name === 'Del__'))
				continue;
			$src_from_types = \QApi::DetermineFromTypes($storage_model, $m_property->name);
			if (!$src_from_types)
				continue;
			
			if ($id !== null)
			{
				// @sto
			}
			
			$src_from = $m_property->name;
			
			$captions = [];
			
			$first_ty = reset($src_from_types);
			if ((strtolower($first_ty{0}) !== $first_ty{0}) && 
					($p_model_type = \QModel::GetTypeByName($first_ty)) && 
					$p_model_type->model["captions"])
			{
				$captions = json_decode($p_model_type->model["captions"], true);
			}
			
			$m_view = new \QDevModel_View();
			$m_view->Tag = $m_property->name;
			$m_view->Property = $m_property->name;
			$m_view->setId($m_view->Tag);
			$m_view->Caption = $captions[$m_view->Tag] ?: $m_view->Tag;
			$m_view->Table = $p_model_type ? $p_model_type->storage["table"] : null;
			
			$m_view->DataIsCollection = $m_property->hasCollectionType();
			$m_view->DataIsStrict = $m_property->strict ? true : false;
			if (is_string($m_property->types))
				$m_view->DataType = $m_property->types;
			else if ($m_property->types instanceof \QModelAcceptedType)
				$m_view->DataType = implode("; ", $m_property->types->options);
			else
				throw new \Exception('@not accepted');
			
			if (($id === null) || ($id === $m_view->Tag))
				$all[] = $m_view;
			
			// $views = [$class_short => $class_short];
			$additional_views = ($m_property->storage && $m_property->storage["views"]) ? explode(",", $m_property->storage["views"]) : [];
			
			foreach ($additional_views ?: [] as $av)
			{
				if ($m_type->properties[$av])
					throw new \Exception("Additional view named as existing property => ".$av);
				
				$add_view = new \QDevModel_View();
				$add_view->Tag = $av;
				$add_view->setId($av);
				$add_view->DataIsCollection = $m_view->DataIsCollection;
				$add_view->DataIsStrict = $m_view->DataIsStrict;
				$add_view->DataType = $m_view->DataType;
				$add_view->Property = $m_view->Property;
				$add_view->Caption = $captions[$add_view->Tag] ?: $add_view->Tag;
				$add_view->Table = $m_view->Table;
				
				if (($id === null) || ($id === $add_view->Tag))
					$all[] = $add_view;
			}
		}
		
		// first sort them
		usort($all, function ($a, $b) use ($parameters)
		{
			if (($oo = $parameters['OBY_Caption']))
				return ($oo === 'ASC') ? ($a->Caption <=> $b->Caption) : ($b->Caption <=> $a->Caption);
			else if (($oo = $parameters['OBY_Property']))
				return ($oo === 'ASC') ? ($a->Property <=> $b->Property) : ($b->Property <=> $a->Property);
			else if (($oo = $parameters['OBY_Tag']))
				return ($oo === 'ASC') ? ($a->Tag <=> $b->Tag) : ($b->Tag <=> $a->Tag);
			else
				return $a->Tag <=> $b->Tag;
		});
		
		// then apply filter
		$new_all = [];
		if ($parameters)
		{
			foreach ($all as $item)
			{
				$keep = true;
				foreach ($parameters as $k => $_v)
				{
					$v = trim(trim(trim($_v), '%'));
					if ((strlen(trim($v)) > 0) && substr($k, 0, strlen('QINSEARCH_')) === 'QINSEARCH_')
					{
						$prop = substr($k, strlen('QINSEARCH_'));
						if (strpos(strtolower($item->$prop), strtolower($v)) === false)
						{
							// to be removed
							$keep = false;
							break;
						}
					}
				}
				if ($keep)
					$new_all[] = $item;
			}
		}
		else
			$new_all = $all;
		
		$slice = array_slice($new_all, $offset, $count);
		
		$ma = new \QModelArray($slice);
		$ma->_qc = count($new_all);
		return $ma;
	}
	
	public static function GetListingEntity()
	{
		return "Tag, Property, Caption, DataType, DataIsCollection, DataIsStrict, Table";
	}
	
	/**
	 * Gets a default for a listing selector if none was specified
	 * 
	 * @return string
	 */
	public static function GetListingQuery($selector = null)
	{
		// return "Ddi_A, Ddi_B, DateTime, Answered, Elapsed, IsIntl, Trunk1, Trunk2, Customer, CallType";
		$selector = $selector ?: static::GetListingEntity();
		$q = (is_array($selector) ? qImplodeEntity($selector) : $selector)." "
			. "WHERE 1 "
				. "??Id?<AND[Id=?]"
				. " ??QINSEARCH_Tag?<AND[Tag LIKE (?)]"
				. " ??QINSEARCH_Caption?<AND[Caption LIKE (?)]"
				. " ??QINSEARCH_Property?<AND[Property LIKE (?)]"
			. " ORDER BY "
				. "??OBY_Tag?<,[Tag ?@]"
				. "??OBY_Caption?<,[Caption ?@]"
				. "??OBY_Property?<,[Property ?@]"
			. " ??LIMIT[LIMIT ?,?]";
		return $q;
	}
	
	public static function ProvisionViewData($data)
	{
		$app_prop = $data['Property'];
		$view_tag = $data['Tag'];
		$properties = $data['Properties'];
		
		$app_class = \QApp::GetDataClass();
		
		// determine the data type we are working on
		// identify the class file (if not on the last watch folder - create it as a patch from the last level)
		
		// $m_type = \QModel::GetTypeByName($ret_view->DataType);
		$class_name = static::GetFirstUsableType($app_class, $app_prop);
		if (!$class_name)
			return false;
		
		if ($properties)
		{
			$inf = [];
			$selector_query_list = $selector_query_form = $selector_grid_list = $selector_grid_form = [];
			$has_changes = static::ProvisionViewData_Class($inf, $app_class, $app_prop, $view_tag, $class_name, $properties, []);
			foreach ($inf['class_tokens'] ?: [] as $tokens)
				filePutContentsIfChanged($tokens->filename, (string)$tokens, true);
			
			// on this class ensure me
			$selector_model_entity = static::BuildSelector($properties);
			$me_class_path = static::Provision_EnsureClass($class_name);
			static::Provision_SyncProperty_Update_EntityMethod($me_class_path, $selector_model_entity);
			
			// if ($has_changes) // to ensure proper order we will always to this
			{
				$selector_query_list = static::BuildSelector($properties, 'selector_query_list', true);
				$selector_query_form = static::BuildSelector($properties, 'selector_query_form', true);
				$selector_grid_list = static::BuildSelector($properties, 'selector_grid_list', true);
				$selector_grid_form = static::BuildSelector($properties, 'selector_grid_form', true);
				
				$app_class_path = static::Provision_EnsureClass($app_class);
				static::Provision_SyncProperty_UpdateEntities($app_class_path, $view_tag, 
									[
										'selector_query_list' => ['GetListEntity', $selector_query_list], 
										'selector_query_form' => ['GetFormEntity', $selector_query_form],
										'selector_grid_list' => ['GetEntityForGenerateList', $selector_grid_list], 
										'selector_grid_form' => ['GetEntityForGenerateForm', $selector_grid_form]
									]);

				// trigger view sync | in an async mode
				file_put_contents('resync_model.txt', 'yes');
			}
		}
	}
	
	/**
	 * 
	 * @param string $class_name
	 * @param string $property
	 * @param array|\QModelType $type_inf
	 * @return type
	 * @throws \Exception
	 */
	public static function GetFirstUsableType(string $class_name, string $property, $type_inf = null)
	{
		if (!$type_inf)
			$type_inf = \QModelQuery::GetTypesCache($class_name);
		if (!$type_inf)
			return null;
		
		if (is_array($type_inf))
		{
			$prop_inf = $type_inf[$property];
			if (!$prop_inf)
				return null;
			
			return $prop_inf['[]'] ? reset($prop_inf['[]']['#']) : ($prop_inf['#'] ? reset($prop_inf['#']) : null);
		}
		else if ($type_inf instanceof \QModelType)
		{
			$prop_inf = $type_inf->properties[$property];
			if (!$prop_inf)
				return null;
			if (is_string($prop_inf->types))
				return ($prop_inf->types{0} === strtoupper($prop_inf->types{0})) ? $prop_inf->types : null;
			else if ($prop_inf->types instanceof \QModelAcceptedType)
			{
				if (count($prop_inf->types->options) > 1)
					throw new \Exception('Only one type is supported');
				$types = reset($prop_inf->types->options);
				return ($types{0} === strtoupper($types{0})) ? $types : null;
			}
			else
				return null;
		}
		else
			throw new \Exception('Unexpected input for \$type_inf');
	}
	
	public static function ProvisionViewData_Class(array &$inf, string $app_class, string $app_prop, string $view_tag, string $class_name, array $properties = [], array $path = [])
	{
		/**
		 * 1. Create property if not exists
		 *		Apply patching/create class on separate level
		 * 2. Ensure property in the model entity
		 *		Again, ensure patching
		 * 3. Include property in (as selected):
		 *					Query List
		 *					Query Form
		 *					Grid List
		 *					Grid Form
		 * 
		 * LAST: trigger generate for the specific view 
		 */
		
		$has_changes = false;
		
		// echo "Processing ... ". implode('.', $path)." <br/>\n";
		
		foreach ($properties as &$prop)
		{
			// ucfirst
			$prop['name'] = trim($prop['name']);
			if ($prop['name']{0} === strtolower($prop['name']{0}))
				$prop['name'] = ucfirst($prop['name']);
			
			if (!$prop['is_new'])
			{
				//if (!$prop['children'])
				//	echo "Skipping ... ". implode('.', $path).".{$prop['name']} <br/>\n";
				continue;
			}
			
			if (!$inf['class_path'][$class_name])
				$inf['class_path'][$class_name] = static::Provision_EnsureClass($class_name);
			
			static::Provision_SyncProperty($inf, $inf['class_path'][$class_name], $class_name, $prop);
			
			$has_changes = true;
		}
		
		foreach ($properties as $prop)
		{
			if ($prop['children'])
			{
				$p_class_name = static::GetFirstUsableType($class_name, $prop['name']);
				$p_path = $path;
				$p_path[] = $prop['name'];
				
				$ch = static::ProvisionViewData_Class($inf, $app_class, $app_prop, $view_tag, $p_class_name, $prop['children'], $p_path);
				if ($ch)
					$has_changes = true;
			}
		}
		
		return $has_changes;
	}
	
	public static function Provision_EnsureClass(string $class_name)
	{
		$last_wf = end(\QAutoload::GetWatchFolders());
		$class_path = \QAutoload::GetClassFileName($class_name);
		$class_wf = $class_path ? \QAutoload::GetModulePathForPath($class_path) : null;
		
		if ($last_wf !== $class_wf)
		{
			// create a patch in $last_wf
			list($sh_class, $namespace) = qClassShortAndNamespace($class_name);
			if ($namespace)
			{
				$namespace_parts = explode("\\", $namespace);
				if ($namespace_parts[0] === 'Omi')
					array_shift($namespace_parts);
			}
			else
				$namespace_parts = [];
			
			if (class_exists($class_name))
			{
				$path = $last_wf.'model/patches/'.(implode('/', $namespace_parts)).'/'.$sh_class.'.patch.php';
				$class_content = "";
				$php_obj = \QPHPToken::ParsePHPFile($class_path, false);
				
				$php_class = $php_obj->findFirstPHPTokenClass();
				
				$new_children = [];
				$in_class = false;
				
				foreach ($php_class->children as $rm_pos => $child)
				{
					if ( // ($child instanceof \QPHPTokenClassConst) || // we no longer remove constants as they are not picked !
							($child instanceof \QPHPTokenProperty) || 
							($child instanceof \QPHPTokenFunction))
					{
						continue;
					}
					
					if (is_array($child) && ($child[0] === T_CLASS))
					{
						$in_class = true;
					}
					else if ((!$in_class) && ($child instanceof \QPHPTokenDocComment))
					{
						// we need to remove the renames as they will break things !
						$parsed_toks = \QCodeStorage::parseClassDocComment($child->children[0][1]);
						if ($parsed_toks['patch']['rename'])
						{
							// these must be removed
							// \n * @patch.rename => (\n * @) | */$
							$matches = null;
							$reg_ex = '/\r?\n\s*\*\s*\@patch\.rename\s+.*(?=(?:\r?\n\s*\*\s*\@)|(?:\r?\n\s*\*\/$))/us';
							$child->children[0][1] = preg_replace($reg_ex, "", $child->children[0][1]);
						}
					}
					
					$new_children[] = $child;
				}
				$php_class->children = $new_children;
				
				// get everything except inner 
				$class_content = (string)$php_obj;
				
				// we also need the USE part !
				// qvar_dump($class_name, $class_content);
				// throw new \Exception('stop');
				filePutContentsIfChanged($path, $class_content, true);
				return $path;
			}
			else
			{
				// create a new data type
				throw new \Exception('@TODO new data type. Must send Table name.');
			}
		}
		else
			return $class_path;
	}
	
	public static function Provision_SyncProperty(array &$inf, string $class_path, string $class_name, array $prop_data)
	{
		if (!($php_obj = $inf['class_tokens'][$class_name]))
			// ->filename
			$php_obj = $inf['class_tokens'][$class_name] = \QPHPToken::ParsePHPFile($class_path, false);
		
		$php_class = $php_obj->findFirstPHPTokenClass();
		
		$prop_name = ucfirst(trim($prop_data['name']));
		
		// force strict atm
		$prop_type = trim($prop_data['type']);
		if (!trim($prop_type))
			$prop_type = 'string'; // default to string
		$is_ref_type = strtoupper($prop_type{0}) === $prop_type{0};
		$prop_type = $prop_type.($prop_data['is_collection'] ? "[]" : "").($is_ref_type ? ' !strict' : '');
	
		if (!$php_class->properties[$prop_name])
		{
			// create it
			$prop_str = '
	/**
	 * @var '.$prop_type.'
	 */
	protected $'.$prop_name.';
';
			$php_class->append($prop_str);
			$php_class->properties[$prop_name] = $prop_str;
		}
		else
		{
			// update it
			// @TODO - update the type
		}
	}
	
	public static function BuildSelector(array $properties, string $selector_tag = null, bool $has_order = false)
	{
		if (!$properties)
			return [];
		$ret = [];
		
		if ($has_order)
		{
			$positions = [];
			$lasts = [];
			$pos_offset = 0;
		}
		
		foreach ($properties as $prop)
		{
			$p_name = ucfirst(trim($prop['name']));
			if (($selector_tag === null) || $prop[$selector_tag])
			{
				if ($has_order)
				{
					$pos = ($prop[$selector_tag."_pos"] !== null) ? trim($prop[$selector_tag."_pos"]) : "";
					if ($pos === "")
						$lasts[] = $p_name;
					$pos = (int)$pos;
					if ($pos < 0)
						$lasts[] = $p_name;
					else
						$positions[$pos][] = $p_name;
				}
				if ($prop['children'])
					$ret[$p_name] = static::BuildSelector($prop['children'], $selector_tag, $has_order);
				else
					$ret[$p_name] = [];
			}
		}
		// reposition them
		if ($has_order)
		{
			ksort($positions);
			$ordered_selector = [];
			foreach ($positions as $poses_list)
			{
				foreach ($poses_list as $p_name)
					$ordered_selector[$p_name] = $ret[$p_name];
			}
			foreach ($lasts as $p_name)
				$ordered_selector[$p_name] = $ret[$p_name];
			
			return $ordered_selector;
		}
		else
			return $ret;
	}
	
	public static function Provision_SyncProperty_UpdateEntities(string $app_class_path, string $view_tag, array $selectors_list)
	{
		$php_obj = \QPHPToken::ParsePHPFile($app_class_path, true);
		$php_class = $php_obj->findFirstPHPTokenClass();
		
		foreach ($selectors_list as $sel_tag => $sel_inf)
		{
			list($method_name_short, $selector) = $sel_inf;
			$method_name = $method_name_short.'_Final';
			
			$selector_inner_str = "				\$ret = ".var_export($selector, true).";
				break;
";
			
			$selector_str = "
			{{$selector_inner_str}}
";
			$case_str = "
			case \"{$view_tag}\":{$selector_str}";
			
			$meth_str = "
	public static function {$method_name}(\$view_tag = null)
	{
		\$ret = null;
		switch (\$view_tag)
		{
			{$case_str}
			default:
			{
				\$ret = static::{$method_name_short}(\$view_tag);
				break;
			}
		}
		return \$ret;
	}
";
			// $code->findPHPClass()->findMethod("GetUrl_")->findSwitchCode();
			$entity_method = $php_class->findMethod($method_name);
			if (!$entity_method)
			{
				// we need to create it
				$php_class->append($meth_str);
			}
			else
			{
				// search for the case within
				$switch_code = $entity_method->findCode()->findSwitchCode();
				$switch_code->setCaseCode($view_tag, $selector_inner_str);
			}
		}
		
		// qvar_dump((string)$php_class);
		filePutContentsIfChanged($app_class_path, (string)$php_obj, true);
		
	}
	
	
	public static function Provision_SyncProperty_Update_EntityMethod(string $class_path, array $selector, string $method_name = 'GetModelEntity')
	{
		$php_obj = \QPHPToken::ParsePHPFile($class_path, true);
		$php_class = $php_obj->findFirstPHPTokenClass();
		
		$method_content_str = "return \"".qaddslashes(qImplodeEntity($selector))."\";\n\t";
		// $code->findPHPClass()->findMethod("GetUrl_")->findSwitchCode();
		$entity_method = $php_class->findMethod($method_name);
		if ($entity_method)
		{
			$entity_method->findCode()->inner("\n\t\t".$method_content_str);
		}
		else
		{
			$meth_str = "
	public static function {$method_name}(\$view_tag = null)
	{
		{$method_content_str}
	}
";
			// we need to create it
			$php_class->append($meth_str);
		}
		
		filePutContentsIfChanged($class_path, (string)$php_obj, true);
	}
}

