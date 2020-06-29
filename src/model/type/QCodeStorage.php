<?php

/**
 * QCodeStorage gets model information and stores it in a QModelType object.
 * 
 * @todo The functionality in QCodeStorage should be moved to useful/code and QCodeStorage should be removed
 * 
 */
class QCodeStorage 
{
	/**
	 * The default code storage
	 *
	 * @var QCodeStorage
	 */
	protected static $Default;
	
	// const ParamsRegExp = "/(^.*?(?=(?:\\n\\s*(?:validator|filter)\\:)|\$))|(?:(?:^|\\n)\\s*(?:((?:filter)|(?:validator))\\:)\\s*(.*?)(?=(?:\\n\\s*(?:validator|filter)\\:)|\$))/ius";
	const ParamsRegExp = "/(.*?)(?:(?:\\n\\s*(?:(validator|filter))\\:)|\$)/ius";
	
	/**
	 * Gets a model by id and type
	 * Here the $model_id is the name of the class (full with namespace)
	 *
	 * @param integer|string $model_id
	 * @param QIModel $instance
	 * @param QModelType $model_type
	 * 
	 * @return QModelType
	 */
	public function storageGetModelById($model_id, QIModel $instance = null, $model_type = null, $generate_security = true)
	{
		if ($model_id{0} == strtolower($model_id{0}))
			return false;
		// we expect model id to be a class name (full namespace)
		if ($model_type && ((!($model_type instanceof QModelType) || ($model_type->class != "QModelType"))))
			throw new Exception("QCodeStorage::getModelById only returns objects of type QModelType");

		$class = $model_id;
		$is_class = class_exists($class);
		$is_interface = $is_class ? false : interface_exists($model_id);
		if (!($is_class || $is_interface))
			return null;

		// var_dump("storageGetModelById: {$class}");
		// we will use reflection to analyze the class
		$ref_class = new ReflectionClass($class);
		
		// make sure we have a QIModel
		if (!($ref_class->implementsInterface("QIModel") || ($class === "QApi")))
			return null;
		
		$model_type = $instance ?: new QModelType();
		$model_type->parent = get_parent_class($class);
		$model_type->class = $class;

		$parent_type = static::LoadDataFromParent($model_type);
		
		// $model_type->_id = $model_id;
		$model_type->__refl = $ref_class;

		$this->docComment = $ref_class->getDocComment() ?: null;
		/*if ((!$this->docComment) && $parent_type->docComment)
			$this->docComment = $parent_type->docComment;*/
		
		// $constants = $ref_class->getConstants(); // TO DO
		$ret_data = self::parseClassDocComment($this->docComment);
		if ($ret_data)
		{
			foreach ($ret_data as $k => $v)
			{
				$model_type->$k = $v;
			}
		}
		
		$model_type->path = $ref_class->getFileName();
		
		$model_type->is_final = $ref_class->isFinal();
		$model_type->is_abstract = $ref_class->isAbstract();
		$model_type->is_interface = $ref_class->isInterface();
		$model_type->is_trait = $ref_class->isTrait();

		$model_type->properties = new QModelArray();
		$model_type->methods = $model_type->methods ?: new QModelArray();
		
		// $methods = $ref_class->getMethods( /* $filter */ ); | we only get exposed methods !!! not all !!! TO DO
		// $namespace = $ref_class->getNamespaceName(); TO DO
		
		// we may wanna recurse on parent too if it's not setup
		// $parent = $ref_class->getParentClass(); TO DO ... lasy load for the parent class
		
		$traits = $ref_class->getTraits();
		
		$properties = $ref_class->getProperties();
		$props_defaults = $ref_class->getDefaultProperties();
		
		foreach ($properties as $_prop)
		{
			// skip statics for now, we should put them in another array later on
			
			$prop = $_prop;
			
			if ($prop->isStatic())
				continue;
			$name = $prop->getName();

			// The problem:
			// ClassX extends ClassY
			// ClassX overwrites prop1 from ClassY
			// ClassX is patched at some level ClassX.patch.php -> prop1 will go to the trait
			// ClassZ extends ClassX
			// The doc comment for property prop1 will be pulled from ClassY and not from ClassX because reflection priority is somehow wrong -> parent class and then trait

			// fix for property doc comment issue - go through entire stack of parents to find the property in trait if any
			// if (($in_trait_prop = $this->findPropDefinition())
			if ($prop->class !== $model_type->class)
			{
				$prop = $this->findPropDefinition($name, [$ref_class]);
				if (!$prop)
					throw new \Exception('Unable to find propery `'.$name.'` for class: '.$ref_class->name);
			}
			
			// also skip _q[...]_ pattern
			if ((substr($name, 0, 2) == "_q") && (substr($name, -1, 1) == "_"))
				continue;

			$doc_comment = $prop->getDocComment();
			$use_namespace = $prop->getDeclaringClass()->getNamespaceName();
			
			$parse_data = $this::parseDocComment($doc_comment, false, $use_namespace);
			if (!isset($parse_data["types"]))
				continue;
			
			$model_prop = new QModelProperty();
			
			foreach ($parse_data as $pdk => $pdv)
			{
				$model_prop->{$pdk} = $pdv;
			}
			
			$model_prop->name = $name;
						
			// $model_prop->comment = $prop->getDocComment();
			//$model_prop->value = $prop->getValue($prop);
			if (isset($props_defaults[$model_prop->name]))
				$model_prop->default = $props_defaults[$model_prop->name];
			
			$model_type->properties[$name] = $model_prop;
			$model_prop->parent = $model_type;
		}
		
		// only public methods atm
		$methods = $ref_class->getMethods(ReflectionMethod::IS_PUBLIC);
		foreach ($methods as $method)
		{
			// skip statics for now, we should put them in another array later on
			// if ($method->isStatic())
			//	continue;
			
			$m_name = $method->name;
			// skip methods starting with "_"
			if (($m_name{0} === "_") || ($m_name === "SecurityFilter"))
				continue;
			
			$parse_data = $this::parseDocComment($method->getDocComment());
			
			if ($parse_data["api"] || $parse_data["security"] || $parse_data["storage"])
			{
				$model_meth = new QModelMethod();
				$model_meth->static = $method->isStatic();
				$model_meth->name = $m_name;
				foreach ($parse_data as $pdk => $pdv)
				{
					/*if (($pdk === "security") && $pdv["rights"])
					{
						$model_meth->rights = $pdv["rights"];
						unset($pdv["rights"]);
					}*/
					$model_meth->{$pdk} = $pdv;
				}
				
				$model_type->methods[$m_name] = $model_meth;
				$model_meth->parent = $model_type;
				
				$declaring_class = $method->getDeclaringClass()->name;
				if ($declaring_class !== $model_type->class)
					$model_meth->in = $declaring_class;
			}
		}
		
		// no security from here
		// if ($generate_security)
		//	$this->parseSecurityRights($model_type);
		
		return $model_type;
	}
	
	/**
	 * Parses the Class's doc comment to extract metadata needed by the framework
	 * 
	 * @param ReflectionClass $class
	 * @return array[]
	 */
	public static function parseClassDocComment($doc_comm)
	{
		$matches = null;
		
		//var_dump($doc_comm);

		// remove *, /** and */ from the comment
		$doc_comm = preg_replace("/\\r|(?<=\\n|^)\\s*\\*+\\/\$"."|(?<=\\n|^)\\s*\\*+"."|^\\s*\\/\\*+/us", "", $doc_comm);
		
		if (preg_match_all("/".
						// identifier: ex: @storage.table Files
									"@([a-zA-Z\\.\\_]+)". // isolate the identifier
									// "\\s?(?=\\s|\\=|\\n)". // look ahead assert 
									"[\\ \\t]*(?:\\=[\\ \\t]*|[\\ \\t]+|(?=\\n))". // equal or whitespace
									// select the value of the identifier with a look ahead assertion for the next identifier
									"(.*?(?=\\n\\s*@[a-zA-Z\\.\\_]+\\s*(?:\\s|\\=|\\n)|\$))".
					
						"/us", $doc_comm, $matches) === false)
		{
			return null;
		}
		
		if (!$matches)
			return null;
		
		$types = $matches[1];
		$values = $matches[2];
		if (!($types && $values))
			return null;
		
		$returned_data = array();
		
		// $append_in_last = false;
		// $security_rights = false;
			
		$pos = 0;
		foreach ($types as $type)
		{
			$value = $values[$pos];
			if (substr($type, 0, 8) == "storage.")
			{
				$key = substr($type, 8);
				$value = trim($value, "\n\r\t =");
				if (!isset($returned_data["storage"]))
					$returned_data["storage"] = array();
				
				if ($key === "dims")
				{
					if ($value)
						$returned_data["storage"][$key] = explode(",", $value);
				}
				else
					$returned_data["storage"][$key] = (strlen($value) == 0) ? true : $value;
			}
			else if (substr($type, 0, 4) == "api.")
			{
				$key = substr($type, 4);
				$value = trim($value, "\n\r\t =");
				if (!isset($returned_data["api"]))
					$returned_data["api"] = array();
				
				$returned_data["api"][$key] = (strlen($value) == 0) ? true : $value;
			}
			else if ((substr($type, 0, 7) == "entity.") || ($type === "entity"))
			{
				$key = ($type === "entity") ? "def" : substr($type, 7);
				$value = trim($value, "\n\r\t =");
				if (!isset($returned_data["entity"]))
					$returned_data["entity"] = array();
				
				if ($key === "def")
					$returned_data["entity"][$key] = qParseEntity($value);
				else
					$returned_data["entity"][$key] = (strlen($value) == 0) ? true : $value;
			}
			else if ($type == "security.rights")
			{
				$returned_data["rights"] = $value;
			}
			else if (substr($type, 0, 9) == "security.")
			{
				$key = substr($type, 9);
				$value = trim($value, "\n\r\t =");
				if (!isset($returned_data["security"]))
					$returned_data["security"] = array();
				
				$returned_data["security"][$key][] = (is_string($value) && (strlen($value) === 0)) ? true : $value;
			}
			else if (substr($type, 0, 6) == "patch.")
			{
				$key = substr($type, 6);
				$value = trim($value, "\n\r\t =");
			
				if ($key === "rename")
				{
					$returned_data["patch"]["rename"] = array();
					$patch_renames = explode("\n", $value);
					foreach ($patch_renames as $_patch_rename)
					{
						$patch_rename = trim($_patch_rename);
						$rename_data = preg_split("/\\s+/uis", $patch_rename, 2, PREG_SPLIT_NO_EMPTY);
						$returned_data["patch"]["rename"][$rename_data[0]] = $rename_data[1];
					}
				}
				else if ($key === "remove")
				{
					// var_dump($key, $value);
					$returned_data["patch"]["remove"] = array();
					$patch_removes = preg_split("/[\\s\\,]+/uis", $value, -1, PREG_SPLIT_NO_EMPTY);
					foreach ($patch_removes as $pr)
						$returned_data["patch"]["remove"][trim($pr)] = trim($pr);
				}
			}
			if (substr($type, 0, 6) == "model.")
			{
				$key = substr($type, 6);
				$value = trim($value, "\n\r\t =");
				if (!isset($returned_data["model"]))
					$returned_data["model"] = array();
				
				if ($key === "captionProperties")
				{
					$nv_value = array();
					$cp_parts = explode(",", $value);
					foreach ($cp_parts as $cpp_v)
						$nv_value[] = trim($cpp_v);
					$value = $nv_value;
				}
				
				$returned_data["model"][$key] = (is_string($value) && (strlen($value) === 0)) ? true : $value;
			}
			
			$pos++;
		}

		return $returned_data;
	}
	
	protected function appendSecurityEntity(&$entities, $rights, $entity_str, $condition_str)
	{
		if (!$condition_str)
			return;
		
		$entity = qParseEntity($entity_str);
		self::appendSecurityEntityRecursive($entities, $entity, $rights);
	}
	
	protected function appendSecurityEntityRecursive(&$entities, $entity, $rights)
	{
		foreach ($entity as $k => &$v)
		{
			$entities[$k][0] = $entities[$k][0] ? ($entities[$k][0] | $rights) : $rights;
			if (is_array($v))
			{
				if (!$entities[$k][1])
					$entities[$k][1] = array();
				self::appendSecurityEntityRecursive($entities[$k][1], $v, $rights);
			}
		}
	}
	
	public function parseSecurityRights(QModelType $model_type, &$meta_data = null, $write_to_file = true)
	{
		$rights = array();
		$props_count = 0;
		if ($model_type->rights)
		{
			$rights[0] = $model_type->rights;
			$model_type->rights = null;
		}
		if ($model_type->properties)
		{
			foreach ($model_type->properties as $prop_n => $prop)
			{
				if ($prop->rights)
				{
					$rights[$prop_n] = $prop->rights;
					$prop->rights = null;
					$props_count++;
				}
			}
		}
		
		if (empty($rights))
			return false;
		
		$filter_class = "<?php\n\n"."class ".$model_type->getClassNameWithoutNs()."\n{\n"."\tpublic function SecurityFilter(QUser \$User, \$req_rights, \$property = null, &\$extra_entities = null)\n\t{\n";
		
		$filter_class .= "\t\tif (\$property === null)\n\t\t{\n";
		$filter_class .= "\t\t\t\$filters = array();\n";
		
		$in_props = false;
		
		$entities = array();
		
		$filter = "";
		
		foreach ($rights as $r_key => $right)
		{
			$matches = null;
			if (preg_match_all("/".
									"(\\n\\s*\\#[^\\n]+)?". // isolate names & comments if specified
							"(?:\\n\\s*|^\\s*)". // ignore starting spaces & misc
										"([a-zA-Z\\*\\-]+)\\s*(?:\\[([^\\]]*)\\])*?". // isolate the rights & extra entity
										"\\s*\\=\\>\\s*". 
										// select the value of the identifier with a look ahead assertion for the next identifier
										"(.*?(?=\$|\\n\\s*\\#|(?:\\n\\s*[a-zA-Z\\*\\-]+\\s*(?:\\[[^\\]]*\\])*?\\s*\\=\\>)))".

							"/us", $right, $matches) === false)
					throw new Exception("Security rights parse error");
			
			$in_props = ($r_key === 0) ? false : true;
			$extab = $in_props ? "\t\t\t" : "\t";
			$pos = 0;
			if ($in_props)
				$filter .= "\t\t\t\tcase \"".addslashes($r_key)."\":\n\t\t\t\t{\n";
			
			$ma_rules = $matches[4];
			$ma_entities = $matches[3];
			$ma_rights = $matches[2];
			
			if ($meta_data !== null)
				$meta_data[] = $matches;
			
			foreach ($ma_rules as $parse)
			{
				// $toks = qtoken_get_all($parse);
				$blocks = null;
				if (preg_match_all("/".
						
						"(?:(?:\\@[a-zA-Z]+)?\\{"."(?:(?>[^\\{\\}]+)|(?R))*"."\\})|".
						"[a-zA-Z\\_][a-zA-Z0-9\\_]+|". // simple groups
						"\\+|\\,|\\(|\\)". // AND (+) OR (,) 
						
						"/us", $parse, $blocks) === false)
					throw new Exception("Security rights parse error");
				
				if (empty($blocks) || empty($blocks[0]))
					throw new Exception("Security rights parse error");
				
				if ($ma_entities[$pos])
				{
					// appendSecurityEntity(&$entities, $rights, $entity_str, $condition_str)
					self::appendSecurityEntity($entities, qBinRights($ma_rights[$pos]), $ma_entities[$pos], $ma_rules[$pos]);
					
					$pos++;
					continue;
				}
				
				$filter .= "\t\t{$extab}/**\n\t\t{$extab} * ".str_replace("\n", "\n\t\t{$extab} * ", trim($matches[0][$pos]))."\n\t\t{$extab} */\n".
				
							"\t\t{$extab}if (\$req_rights & ".qBinRights($ma_rights[$pos])." /** rights: '{$ma_rights[$pos]}' **/)\n\t\t{$extab}{\n";
									
				$filter_1 = "\t\t\t{$extab}if (";
				
				$filter_2 = "\n\t\t\t{$extab}if (";
				$filter_3 = "";
				$rpos = 0;
				
				$sql_rules_count = 0;
				$grp_rules_count = 0;

				foreach ($blocks[0] as $block)
				{
					if ($block === ",")
					{
						// $check .= " || ";
						$filter_1 .= " || ";
						$filter_2 .= " || ";
						$filter_3 .= " OR ";
					}
					else if ($block === "+")
					{
						// $check .= " && ";
						$filter_1 .= " && ";
						$filter_2 .= " && ";
						$filter_3 .= " AND ";
					}
					else if (($block === "(") || ($block === ")"))
					{
						// $check .= " && ";
						$filter_1 .= $block;
						$filter_2 .= $block;
						$filter_3 .= $block;
					}
					else if ($block{0} === "{")
					{
						$sql_rules_count++;
						// $check .= " && ";
						// $filter .= substr($block, 1, -1);
						$filter_1 .= "false";
						$filter_2 .= "true";
						// parse PHP returns
						$sql_q = str_replace(array("\"", "'", "\n", "\r", "\\", "\"", "\$"), array("\\\"", "\\'", "\\\n", "\\\r", "\\\\", "\\\"", "\\\$"), substr($block, 1, -1));
						$sql_q = preg_replace_callback("/(?:\\{"."(?:(?>[^\\{\\}]+)|(?R))*"."\\})/us", function ($matches) { 
							
								if (!empty($matches[0]))
									return "\".qToSql(".str_replace(array("\\\"", "\\'", "\\\n", "\\\r", "\\\\", "\\\"", "\\\$"), array("\"", "'", "\n", "\r", "\\", "\"", "\$"), substr($matches[0], 1, -1)).").\"";
				
							}, $sql_q);
						
						$filter_3 .= $sql_q;
					}
					else
					{
						$grp_rules_count++;
						// group name | or predefined group
						$filter_1 .= "(\$r_{$rpos} = \$User->memberOf(\"".qaddslashes($block)."\"))";
						$filter_2 .= "\$r_{$rpos}";
						$filter_3 .= "\".(\$r_{$rpos} ? \"TRUE\" : \"FALSE\").\"";
						$rpos++;
					}
				}
				$filter .= (($grp_rules_count > 0) ? $filter_1.")\n\t\t\t{$extab}{\n\t\t\t\t{$extab}"."return true;\n\t\t\t{$extab}}\n" : "").
							(($sql_rules_count > 0) ? 
								($filter_2.")\n\t\t\t{$extab}{\n\t\t\t\t{$extab}"."\$filters[] = \"".$filter_3."\";\n\t\t\t{$extab}}\n") :
									"\t\t\t{$extab}return false;\n").
							"\t\t{$extab}}\n\n";

				$pos++;
			}
			
			if ($r_key === 0)
			{
				// end type put property
				$filter .= "\t\t\treturn \$filters ?: false;\n". "\t\t}\n\t\t"."else\n\t\t{\n".
						"\t\t\tswitch(\$property)\n".
								"\t\t\t{\n";
			}
			if ($in_props)
				// close the case
				$filter .= "\t\t\t\t\t"."return false;\n\t\t\t\t}\n";
		}
		
		if ($write_to_file)
		{
			if ($entities)
				$filter_class .= "\t\t\t\$extra_entities = ".str_replace("\n", "\n\t\t\t\t\t", qArrayToCode($entities, null, false))."\n";

			$filter_class .= $filter;
			$filter_class .= "\t\t\t\tdefault:\n\t\t\t\t{\n\t\t\t\t\treturn true;\n\t\t\t\t}\n\t\t\t}\n\t\t}"."\n\t}\n}\n\n\n?>";

			$render_code = QPHPToken::ParsePHPString($filter_class, false);
			$patch_php_code = QPHPToken::ParsePHPFile($model_type->path);
			
			$patch_php_code->findFirst(".QPHPTokenClass")->mergeWithClass($render_code->findFirst(".QPHPTokenClass"), true);

			// echo "<textarea>{$patch_php_code}</textarea>";
			// die();
			
			QCodeSync::syncCompiledCode($model_type->path, $patch_php_code);
			
			// $compiled_path = QCodeSync::getCompiledPath($model_type->path);

			// filePutContentsIfChanged($compiled_path, $patch_php_code->toString(), true);
		}
	}
	
	/**
	 * Parses the comment of the property to extract extra data
	 * The data will be exported as an array of associative values
	 *
	 * @param string $doc_comm Doc comment
	 * @return array
	 */
	public static function parseDocComment($doc_comm, $general = false, $namespace = null, &$extract_comment = null)
	{
		$matches = null;
		
		/**
		 * @todo TO DO: we do not respect the new standard !!!
		 * https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md
		 * We do not pick the comment !!!
		 */
		
		// remove *, /** and */ from the comment
		$doc_comm = preg_replace("/\\r|(?<=\\n|^)\\s*\\*+\\/\$"."|(?<=\\n|^)\\s*\\*+"."|^\\s*\\/\\*+".
										"|\\*+\\/\$/us", "", $doc_comm)."\n"; // must end with a newline 
		
		$the_comment_matches = null;
		if (preg_match("/^(.*)\\n\\s+(@|\$)/Uus", $doc_comm, $the_comment_matches))
		{
			$extract_comment = trim($the_comment_matches[1]);
		}
		
		if (preg_match_all("/".
						// identifier: ex: @storage.table Files
									"@([a-zA-Z\\.\\_]+)". // isolate the identifier
									// "\\s?(?=\\s|\\=|\\n)". // look ahead assert 
									"[\\ \\t]*(?:\\=[\\ \\t]*|[\\ \\t]+|(?=\\n))". // equal or whitespace
									// select the value of the identifier with a look ahead assertion for the next identifier
									"(.*?(?=\\n\\s*@[a-zA-Z\\.\\_]+\\s*(?:\\s|\\=|\\n)|\$))".
					
						"/us", $doc_comm, $matches) === false)
		{
			return null;
		}
		
		if (!$matches)
			return null;
		
		$types = $matches[1];
		$values = $matches[2];
		if (!($types && $values))
			return null;
		
		$returned_data = array();
		
		// $append_in_last = false;
		// $security_rights = false;
		
		$pos = 0;
		foreach ($types as $type)
		{
			$value = trim($values[$pos]);
			
			if ($type === "var")
			{
				// @todo - review how we parse this !
				if (strpos($value, "!strict") !== false)
				{
					$returned_data["strict"] = true;
					$value = str_replace("!strict", "", $value);
				}
				
				$acc_types = self::parseAcceptedTypes($value, $namespace);
				
				// here we decide if we include the property
				$returned_data["types"] = $acc_types;
				$append_in_last = false;
			}
			else 
			{
				if (substr($type, 0, 8) === "storage.")
				{
					$key = substr($type, 8);
					$value = trim($value, "\n\r\t =");
					if (!isset($returned_data["storage"]))
						$returned_data["storage"] = array();
					
					if ($key === "dims")
					{
						if ($value)
							$returned_data["storage"][$key] = explode(",", $value);
					}
					else
						$returned_data["storage"][$key] = (strlen($value) == 0) ? true : $value;
					$append_in_last = false;
				}
				else if (substr($type, 0, 4) === "api.")
				{
					$key = substr($type, 4);
					$value = trim($value, "\n\r\t =");
					if (!isset($returned_data["api"]))
						$returned_data["api"] = array();
					$returned_data["api"][$key] = (strlen($value) == 0) ? true : $value;
					$append_in_last = false;
				}
				else if (substr($type, 0, 8) === "display.")
				{
					$key = substr($type, 8);
					$value = trim($value, "\n\r\t =");
					if (!isset($returned_data["display"]))
						$returned_data["display"] = array();
					$returned_data["display"][$key] = ($value && (strpos($value, ",") !== false)) ? explode(",", $value) : $value;
					$append_in_last = false;
				}
				else if (substr($type, 0, 11) === "validation.")
				{
					$key = substr($type, 11);
					$value = trim($value, "\n\r\t =");
					if (!isset($returned_data["validation"]))
						$returned_data["validation"] = array();
					$returned_data["validation"][$key] = explode(",", (strlen($value) == 0) ? true : $value);
					$append_in_last = false;
				}
				else if ($type === "filter")
				{
					$returned_data["filter"] = $value;
				}
				else if ($type === "validation")
				{
					$returned_data["validation"] = $value;
					
				}
				else if ($type === "fixValue")
				{
					$returned_data["fixValue"] = $value;
					//$returned_data["fixValue"] = preg_replace("/\\$/", "\\\$", $value);
				}
				else if ($type === "encode")
				{
					$returned_data["encode"] = $value;
				}
				else if ($type === "security.rights")
				{
					$returned_data["rights"] = $value;
				}
				else if (substr($type, 0, 9) === "security.")
				{
					$key = substr($type, 9);
					$value = trim($value, "\n\r\t =");
					if (!isset($returned_data["security"]))
						$returned_data["security"] = array();

					$returned_data["security"][$key][] = (strlen($value) == 0) ? true : $value;
				}
				else if ($type === "param")
				{
					// int|string $transform_state
					$params_parsing = null;
					if (preg_match("/\\s*([\\w\\|\\[\\]]+)\\s+\\$([\\w\\_0-9]+)(.*)\$/us", $value, $params_parsing))
					{
						$param_acc_types = self::parseAcceptedTypes($params_parsing[1], $namespace);
						
						$param_name = trim($params_parsing[2]);
						
						$param_comment = null;
						$param_extra = [];
						if (($param_comment = $params_parsing[3]))
						{
							// this is the comment, search for filter: and validator:
							$param_comment_matches = null;
							if (preg_match_all(self::ParamsRegExp, $param_comment, $param_comment_matches))
							{
								$next_ret_type = "";
								$param_comment = null;
								foreach (reset($param_comment_matches) as $rmk => $rm)
								{
									$rcm_val = trim($param_comment_matches[1][$rmk]);
									switch ($next_ret_type)
									{
										case "":
											$param_comment = $rcm_val;
											break;
										case "validator":
											$param_extra["validator"] = $rcm_val;
											break;
										case "filter":
											$param_extra["filter"] = $rcm_val;
											break;
									}

									$next_ret_type = $param_comment_matches[2][$rmk];
								}
							}
						}

						$param_data_arr = ["name" => $param_name, "type" => $param_acc_types, "comment" => $param_comment];
						foreach ($param_extra as $p_extra_type => $p_extra_data)
							$param_data_arr[$p_extra_type] = $p_extra_data;
						$returned_data["params"][$param_name] = $param_data_arr;
					}
				}
				else if ($type === "return")
				{
					$return_parsing = null;
					if (preg_match("/\\s*([\\w\\|\\[\\]]+)(.*)\$/us", $value, $return_parsing))
					{
						$return_extra = [];
						$return_comment_matches = null;
						$return_comment = $return_parsing[2];
						
						if (preg_match_all(self::ParamsRegExp, $return_comment, $return_comment_matches))
						{
							$next_ret_type = "";
							$return_comment = null;
							foreach (reset($return_comment_matches) as $rmk => $rm)
							{
								$rcm_val = trim($return_comment_matches[1][$rmk]);
								switch ($next_ret_type)
								{
									case "":
										$return_comment = $rcm_val;
										break;
									case "validator":
										$return_extra["validator"] = $rcm_val;
										break;
									case "filter":
										$return_extra["filter"] = $rcm_val;
										break;
								}
								
								$next_ret_type = $return_comment_matches[2][$rmk];
							}
						}
						
						$param_acc_types = self::parseAcceptedTypes($return_parsing[1], $namespace);
						// var_dump($params_parsing[1], $param_acc_types);
						$returned_data["return"] = ["type" => $param_acc_types, "comment" => $return_comment];
						foreach ($return_extra as $rext_k => $rext_v)
							$returned_data["return"][$rext_k] = $rext_v;
					}
					else
					{
						$param_acc_types = self::parseAcceptedTypes($value, $namespace);
						// var_dump($params_parsing[1], $param_acc_types);
						$returned_data["return"] = ["type" => $param_acc_types];
					}
				}
				else if ($general)
				{
					$returned_data[$type] = (strlen($value) == 0) ? true : $value;
				}
			}
			$pos++;
		}
		
		return $returned_data;
	}
	
	/**
	 * Extract the data types from the 'type' expression
	 * Complex example: QModelEntity|int[]|string[MyStrArr]|QModelEntityCollection|(QModelEntity|QModelEntityCollection)[MyArr]|(int|string)[]|char
	 *
	 * @param string $acc_types_string
	 * @param string $namespace
	 * 
	 * @return string|string[]|array[]
	 */
	protected function parseAcceptedTypes($acc_types_string, $namespace = null)
	{
		$matches = null;
		$parsed = preg_match_all('/[\\\\a-zA-Z0-9_]+|\\[|\\]|\\(|\\)|\\|/', $acc_types_string, $matches);
		if (($parsed === false) || (!$matches) || (empty($matches[0])))
			return null;
		
		$dat = $matches[0];
		
		// $r_count = 0;
		// $len = count($dat);
		$result = null;
		$acc_type = null;
		self::parseDataType($dat, $result, $acc_type);
		
		if ($namespace)
		{
			// apply the full namespace to @var when it's the case
			if (is_string($result))
			{
				if (($result{0} === "\\") || (strtolower($result{0}) !== $result{0}))
					$result = self::ApplyNamespaceToName($result, $namespace);
			}
			else if (is_array($result))
			{
				foreach ($result as $k => $res)
				{
					if (is_string($res) && (($res{0} === "\\") || (strtolower($res{0}) !== $res{0})))
						$result[$k] = self::ApplyNamespaceToName($res, $namespace);
					else if ($res instanceof QModelAcceptedType)
						$res->applyNamespaceToTypes($namespace);
				}
			}
			else if ($result instanceof QModelAcceptedType)
				$result->applyNamespaceToTypes($namespace);
			
			// var_dump($namespace, $result);
		}
		
		return (is_array($result) && (count($result) === 1)) ? reset($result) : $result;
	}
	
	public static function ApplyNamespaceToName($class, $namespace)
	{
		if ($class{0} === "\\")
			return substr($class, 1);
		else
			return $namespace ? $namespace."\\".$class : $class;
	}
	
	/**
	 * Sub method of parseAcceptedTypes
	 * 
	 * @param type $dat
	 * @param type $result
	 * 
	 */
	protected function parseDataType($dat, &$result, &$acc_type)
	{
		$ty = current($dat);
		
		if (!$ty)
			return;
		
		if ($ty == "(")
		{
			// multiple types in array
			$types = array();
			$current = null;
			do
			{
				$__ty = next($dat);
				$types[$__ty] = $__ty;
				$check = next($dat);
				// $current = next($dat);
			}
			while ($check != ")");
			
			$current = next($dat);
			
			if ($current == "[")
			{
				$next = next($dat);
				if ($next == "]")
				{
					if (!$acc_type)
						$result[] = $acc_type = new QModelAcceptedType("QModelArray", $types);
					else
						$acc_type->setTypes($types);
					// move to |
					next($dat);
					// move to next
					if (next($dat))
						self::parseDataType($dat, $result, $acc_type);
				}
				else
				{
					if (!$acc_type)
						$result[] = $acc_type = new QModelAcceptedType($next, $types);
					else
						$acc_type->setTypes($types);
					// move to ]
					next($dat);
					// move to |
					next($dat);
					// move to next
					if (next($dat))
						self::parseDataType($dat, $result, $acc_type);
				}
			}
			else
			{
				$result[] = $types;
				if (next($dat))
					self::parseDataType($dat, $result, $acc_type);
			}
		}
		else
		{
			$next = next($dat);
			if ($next == "[")
			{
				// we have an array type
				$next_2 = next($dat);
				if ($next_2 == "]")
				{
					if (!$acc_type)
						$result[] = $acc_type = new QModelAcceptedType("QModelArray", array($ty => $ty));
					else
						$acc_type->setTypes(array($ty));
					// skip to |
					next($dat);
					// skip next
					if (next($dat))
						self::parseDataType($dat, $result, $acc_type);
				}
				else
				{
					if (!$acc_type)
						$result[] = $acc_type = new QModelAcceptedType($next_2, array($ty => $ty));
					else
						$acc_type->setTypes(array($ty));
					// skip to ]
					next($dat);
					// skip to |
					next($dat);
					// skip to next
					if (next($dat))
						self::parseDataType($dat, $result, $acc_type);
				}
			}
			else
			{
				$result[] = $ty;
				
				if ($next == "|")
				{
					// advance one position
					if (next($dat))
						self::parseDataType($dat, $result, $acc_type);
				}
			}
		}
	}
	
	public static function CacheData($className, $cache_path = null)
	{
		if (!$cache_path)
			$cache_path = QAutoload::GetRuntimeFolder()."temp/types/".qClassToPath($className).".type.php";
		else if (strpos($cache_path, "\\") !== false)
			throw new Exception("Not converted ok");
		
		$dir = dirname($cache_path);
		if (!is_dir($dir))
			mkdir($dir, 0755, true);
		
		$cStorage = self::GetDefault();
		$type = $cStorage->storageGetModelById($className);
		
		if (!$type)
		{
			if (qIsA($className, "QIModel"))
				throw new Exception("Invalid type for {$className}");
			return null;
		}
		
		$str = 
"<?php
				
\$Q_TYPECACHE_".qClassToVar($className)." = \$_qtmp = new QModelType();
\$_qtmp->class = ".json_encode($className).";\n".
(($type->is_final) ? ("\$_qtmp->is_final = ".($type->is_final ? "true" : "false").";\n") : "").
(($type->is_abstract) ? ("\$_qtmp->is_abstract = ".($type->is_abstract ? "true" : "false").";\n") : "").
(($type->is_interface) ? ("\$_qtmp->is_interface = ".($type->is_interface ? "true" : "false").";\n") : "").
(($type->parent !== null) ? ("\$_qtmp->parent = ".json_encode($type->parent).";\n") : "").
(($type->path !== null) ? ("\$_qtmp->path = ".json_encode($type->path, JSON_UNESCAPED_SLASHES).";\n") : "").
(($type->is_collection) ? ("\$_qtmp->is_collection = ".($type->is_collection ? "true" : "false").";\n") : "").
(($type->implements !== null) ? ("\$_qtmp->implements = ".($type->implements ? qArrayToCode($type->implements, null, false, null, 1) : "null").";\n") : "").
/* (($type->rights !== null) ? ("\$_qtmp->rights = ".($type->rights ? qArrayToCode($type->rights, null, false, null, 1) : "null").";\n") : "").*/
(($type->security !== null) ? ("\$_qtmp->security = ".($type->security ? qArrayToCode($type->security, null, false, null, 1) : "null").";\n") : "").
(($type->storage !== null) ? ("\$_qtmp->storage = ".($type->storage ? qArrayToCode($type->storage, null, false, null, 1) : "null").";\n") : "").
(($type->entity !== null) ? ("\$_qtmp->entity = ".($type->entity ? qArrayToCode($type->entity, null, false, null, 1) : "null").";\n") : "").
(($type->model !== null) ? ("\$_qtmp->model = ".($type->model ? qArrayToCode($type->model, null, false, null, 1) : "null").";\n") : "").
(($type->api !== null) ? ("\$_qtmp->api = ".($type->api ? qArrayToCode($type->api, null, false, null, 1) : "null").";\n") : "").
"
\$_qtmp->properties = new QModelArray();
\$_qtmp->methods = new QModelArray();
";
		
		if ($type->properties)
		{
			foreach ($type->properties as $prop)
			{
				
				$str .= 
"\$_qtmp->properties[".json_encode($prop->name)."] = \$_qtprop = new QModelProperty();
\$_qtprop->parent = \$_qtmp;
\$_qtprop->name = ".json_encode($prop->name).";\n".
(($prop->default !== null) ? ("\$_qtprop->default = ".(is_array($prop->default) ? qArrayToCode($prop->default, null, false, null, 1) : json_encode($prop->default)).";\n") : "").
(($prop->comment !== null) ? ("\$_qtprop->comment = ".json_encode($prop->comment).";\n") : "").
(($prop->storage !== null) ? ("\$_qtprop->storage = ".($prop->storage ? qArrayToCode($prop->storage, null, false, null, 1) : "null").";\n") : "").
(($prop->display !== null) ? ("\$_qtprop->display = ".($prop->display ? qArrayToCode($prop->display, null, false, null, 1) : "null").";\n") : "").
(($prop->getter !== null) ? ("\$_qtprop->getter = ".json_encode($prop->getter).";\n") : "").
(($prop->setter !== null) ? ("\$_qtprop->setter = ".json_encode($prop->setter).";\n") : "").
/* (($prop->rights !== null) ? ("\$_qtprop->rights = ".($prop->rights ? qArrayToCode($prop->rights, null, false, null, 1) : "null").";\n") : ""). */
(($prop->validation !== null) ? ("\$_qtprop->validation = ".json_encode($prop->validation).";\n") : "").
(($prop->filter !== null) ? ("\$_qtprop->filter = ".json_encode($prop->filter).";\n") : "").
(($prop->fixValue !== null) ? ("\$_qtprop->fixValue = ".json_encode($prop->fixValue).";\n") : "").
(($prop->encode !== null) ? ("\$_qtprop->encode = ".json_encode($prop->encode).";\n") : "").
(($prop->api !== null) ? ("\$_qtprop->api = ".($prop->api ? qArrayToCode($prop->api, null, false, null, 1) : "null").";\n") : "").
(($prop->strict !== null) ? ("\$_qtprop->strict = ".($prop->strict ? "true" : "false").";\n") : "").
(($prop->security !== null) ? ("\$_qtprop->security = ".($prop->security ? qArrayToCode($prop->security, null, false, null, 1) : "null").";\n") : "");
				// types : string|QModelAcceptedType|(string|QModelAcceptedType)[]
				if (is_string($prop->types))
				{
					$str .= "\$_qtprop->types = ".json_encode($prop->types).";\n";
				}
				else if ($prop->types instanceof QModelAcceptedType)
				{
					if ($prop->strict)
						$prop->types->strict;
					$str .= "\$_qtprop->types = ".$prop->types->toPhpCode($prop->strict).";\n";
				}
				else if (qis_array($prop->types))
				{
					$str .= "\$_qtprop->types = array(";
					
					foreach ($prop->types as $value)
					{
						if (is_string($value))
						{
							$str .= json_encode($value, JSON_UNESCAPED_SLASHES).",";
						}
						else if ($value instanceof QModelAcceptedType)
						{
							if ($prop->strict)
								$value->strict;
							$str .= "\n\t\t\t".$value->toPhpCode($prop->strict).",";
						}
					}
					
					$str .= ");\n";
				}
			}
		}
		
		if ($type->methods)
		{
			foreach ($type->methods as $method)
			{
				$str .= 
					"\$_qtmp->methods[".json_encode($method->name)."] = \$_qtmeth = new QModelMethod();\n".
					"\$_qtmeth->parent = \$_qtmp;\n".
					"\$_qtmeth->name = ".json_encode($method->name).";\n".
					"\$_qtmeth->static = ".json_encode($method->static).";\n".
					(($method->rights !== null) ? ("\$_qtmeth->rights = ".($method->rights ? qArrayToCode($method->rights, null, false, null, 1) : "null").";\n") : "").
					(($method->api !== null) ? ("\$_qtmeth->api = ".($method->api ? qArrayToCode($method->api, null, false, null, 1) : "null").";\n") : "").
					(($method->in !== null) ? ("\$_qtmeth->in = ".json_encode($method->in).";\n") : "").
					(($method->security !== null) ? ("\$_qtmeth->security = ".($method->security ? qArrayToCode($method->security, null, false, null, 1) : "null").";\n") : "");
			}
		}
		
		//var_dump($cache_path);
		//echo "<textarea>{$str}</textarea>";
		
		$has_changes = (!file_exists($cache_path)) || (file_get_contents($cache_path) !== $str);
		if ($has_changes)
			file_put_contents($cache_path, $str);
	
		return [$type, $has_changes];
	}
	
	/**
	 * Loads the type data from the parent type
	 * 
	 * @param QModelType $type
	 * 
	 */
	public static function LoadDataFromParent(QModelType $type)
	{
		$parent = $type->parent;
		if (!$parent)
			return;

		if (!qIsA($parent, "QIModel"))
			return;
		
		$p_type = QModel::GetTypeByName($parent);
		
		if ($p_type->rights)
		{
			if (!$type->rights)
				$type->rights = $p_type->rights;
			else
				$type->rights = array_merge_recursive($p_type->rights, $type->rights);
		}
		
		if ($p_type->security)
		{
			if (!$type->security)
				$type->security = $p_type->security;
			else
				$type->security = array_merge_recursive($p_type->security, $type->security);
		}
		
		if ($p_type->storage)
		{
			if (!$type->storage)
				$type->storage = $p_type->storage;
			else
				$type->storage = array_merge_recursive($p_type->storage, $type->storage);
		}
		
		if ($p_type->api)
		{
			if (!$type->api)
				$type->api = $p_type->api;
			else
				$type->api = array_merge_recursive($p_type->api, $type->api);
		}
		
		if ($p_type->methods)
		{
			foreach ($p_type->methods as $mn => $p_meth)
			{
				$meth_type = $type->methods ? $type->methods[$mn] : null;
				if (!$meth_type)
				{
					// var_dump("fixing:: {$type->class} :: {$mn}");
					if ($p_meth->api && $p_meth->api["enable"])
					{
						$meth_type = new QModelMethod();
						$meth_type->name = $mn;
						$meth_type->comments = $p_meth->comments;
						$meth_type->no_export = $p_meth->no_export;
						// $meth_type->parameters = $p_meth->comments;
						$meth_type->parent = $type;
						if (!$type->methods)
							$type->methods = new QModelArray();
						$type->methods[$mn] = $meth_type;
						$meth_type->api = $p_meth->api;
					}
					else
						continue;
				}
			}
		}
		
		if ($p_type->properties)
		{
			foreach ($p_type->properties as $p_name => $prop)
			{
				$prop_type = $type->properties[$p_name];
				if (!$prop_type)
					continue;

				if ($prop->storage)
				{
					if (!$prop_type->storage)
						$prop_type->storage = $prop->storage;
					else
						$prop_type->storage = array_merge_recursive($prop->storage, $prop_type->storage);
				}
				if ($prop->rights)
				{
					if (!$prop_type->rights)
						$prop_type->rights = $prop->rights;
					else
						$prop_type->rights = array_merge_recursive($prop->rights, $prop_type->rights);
				}
				if ($prop->validation)
				{
					if (!$prop_type->validation)
						$prop_type->validation = $prop->validation;
					else
					{
						//qvardump($prop->validation, $prop_type->validation);
						// find a better solution here
						if (is_array($prop_type->validation) && is_array($prop_type->validation))
							$prop_type->validation = array_merge_recursive($prop->validation, $prop_type->validation);
					}
				}
				if ($prop->api)
				{
					if (!$prop_type->api)
						$prop_type->api = $prop->api;
					else
						$prop_type->api = array_merge_recursive($prop->api, $prop_type->api);
				}
				if ($prop->security)
				{
					if (!$prop_type->security)
						$prop_type->security = $prop->security;
					else
						$prop_type->security = array_merge_recursive($prop->security, $prop_type->security);
				}

				if ($prop->getter && (!$prop_type->getter))
					$prop_type->getter = $prop->getter;
				if ($prop->setter && (!$prop_type->setter))
					$prop_type->setter = $prop->setter;
			}
		}
		
		return $p_type;
		
		/*
		PER TYPE
		========
		rights
		security
		storage
		api
		========
		PER PROP
		========
		storage
		getter
		setter
		rights
		validation
		api
		security

		 * 
		 */
	}
	
	public static function GetDefault()
	{
		if (!self::$Default)
			self::$Default = new QCodeStorage();
		return self::$Default;
	}
	
	public function findPropDefinition(string $property_name, array $look_in)
	{
		// The problem:
		// ClassX extends ClassY
		// ClassX overwrites prop1 from ClassY
		// ClassX is patched at some level ClassX.patch.php -> prop1 will go to the trait
		// ClassZ extends ClassX
		// The doc comment for property prop1 will be pulled from ClassY and not from ClassX because reflection priority is somehow wrong -> parent class and then trait
		
		$new_lookin = [];
		
		foreach ($look_in as $class)
		{
			$refl = ($class instanceof \ReflectionClass) ? $class : new \ReflectionClass($class);
			if (!$refl)
				throw new \Exception('Invalid class: '.$class);
			
			if ($refl->hasProperty($property_name) && ($class_prop = $refl->getProperty($property_name)) && ($class_prop->class === $refl->name))
				return $class_prop;
			
			if (!($refl->isTrait() || $refl->isInterface()))
			{
				// class_gen_trait, extends
				$gen_trait_name = $refl->name."_GenTrait";
				// qvar_dump($gen_trait_name, trait_exists($gen_trait_name));
				if (trait_exists($gen_trait_name))
					$new_lookin[$gen_trait_name] = $gen_trait_name;
				if (($parent_class = get_parent_class($refl->name)))
					$new_lookin[$parent_class] = $parent_class;
			}
		}
		
		if ($new_lookin)
		{
			// qvar_dump($new_lookin);
			return $this->findPropDefinition($property_name, $new_lookin);
		}
		else
			return null;
	}
}
