<?php
/**
 * Here we store some functions that are widely used
 * @todo Review functions that should be moved into methods
 */

/**
 * IMPORTANT CONSTANTS
 */

const S_View = 1;
const S_Add = 2;
const S_Edit = 4;
const S_Delete = 8;

const S_Anon = 1;
const S_Auth = 2;
const S_Any = 3;

$_q_base_num_format = null;

/**
 * @todo To review the location of these constants
 */
define("QPermsFlagNoRights",	0); // 
define("QPermsFlagInherit",		16); // for method & property - use from parent

define("QPermsFlagCreate",		1); // add an entry on a reference or within a collection
define("QPermsFlagDelete",		2); // remove an entry from a reference or collection

define("QPermsFlagAppend",		4); // you are allowed to append data to strings
define("QPermsFlagUpdate",		(8 | QPermsFlagAppend)); // you are allowed to change a field
define("QPermsFlagFix",			(32 | QPermsFlagUpdate)); // you may change a field and mark it as fixed

define("QPermsFlagMerge",		(QPermsFlagCreate | QPermsFlagUpdate));

define("QPermsFlagRead",		64);
define("QPermsFlagExecute",		128);

define("QPermsFlagAll",			(QPermsFlagCreate | QPermsFlagDelete | QPermsFlagAppend | QPermsFlagUpdate | QPermsFlagFix | QPermsFlagMerge | QPermsFlagRead | QPermsFlagExecute | QPermsFlagInherit));

// aliases
define("QPermsFlagSet",			QPermsFlagCreate);
define("QPermsFlagUnset",		QPermsFlagDelete);

/**
		- - no rights
		i - inherit
		c - create
		d - delete
		a - append
		u - update
		f - fix
		m - merge (create + update)
		r - read
		x - execute

		* - all rights
 */


/**
* Executes a query
* 
* @param string $query
* @param mixed[] $binds
* @param QIModel[] $dataBlock 
* 
* @return QIModel
* @throws Exception
*/
function QQuery($query, $binds = null, QIModel $from = null, &$dataBlock = null, $skip_security = true, $filter_selector = null, \QIStorage $storage = null)
{
	return QModelQuery::BindQuery($query, $binds, $from, $dataBlock, $skip_security, $filter_selector, false, $storage);
}

function QQueryProperty($property, $query, $binds = null, QIModel $from = null, &$dataBlock = null, $skip_security = true, $filter_selector = null)
{
	$data = QModelQuery::BindQuery($property.".{{$query}}", $binds, $from, $dataBlock, $skip_security, $filter_selector);
	return $data ? $data->$property: null;
}

function QQueryItem($property, $query, $binds = null, QIModel $from = null, &$dataBlock = null, $skip_security = true, $filter_selector = null)
{
	$data = QModelQuery::BindQuery($property.".{{$query}}", $binds, $from, $dataBlock, $skip_security, $filter_selector);
	return $data && $data->$property ? reset($data->$property) : null;
}

/**
 * 
 * @param string $query
 * @param mixed[] $binds
 * @param QIModel $from
 * @param QIModel[] $dataBlock
 * @return QIModel
 */
function QBindQuery($query, $binds = null, QIModel $from = null, &$dataBlock = null, $skip_security = true, $filter_query = null)
{
	// $query, $binds, $from = null, &$dataBlock = null, $skip_security = true, $filter_selector
	return QModelQuery::BindQuery($query, $binds, $from, $dataBlock, $skip_security, $filter_query);
}

/**
 * This way of querying skips security checks
 * 
 * @todo Deprecated
 * 
 * @param string $query
 * @param mixed[] $binds
 * @param QIModel $from
 * @param QIModel[] $dataBlock
 * @return QIModel
 */
function QRootQuery($query, $binds = null, QIModel $from = null, &$dataBlock = null)
{
	return QModelQuery::BindQuery($query, $binds, $from, $dataBlock, true);
}

/**
 * This way of querying skips security checks
 * 
 * @todo Deprecated
 * 
 * @param string $query
 * @param mixed[] $binds
 * @param QIModel $from
 * @param QIModel[] $dataBlock
 * @return QIModel
 */
function QRootBindQuery($query, $binds = null, QIModel $from = null, &$dataBlock = null)
{
	return QModelQuery::BindQuery($query, $binds, $from, $dataBlock, true);
}

/**
 * Escapes a value for DB
 * 
 * @param string $param
 * @return string
 */
function _mySc($param)
{
	return is_string($param) ? (($s = QApp::GetStorage()) ? $s->escapeString($param) : addslashes($param)) : $param;
}

/**
 * True if the input is either an array or a QIModelArray
 * 
 * @param QIModelArray $var
 * @return boolean
 */
function qis_array($var)
{
	return is_array($var) || ($var instanceof QIModelArray);
}

/**
 * Gets an URL for the specified tags and arguments
 * 
 * @param string $tag
 * @param mixed[] $args
 * 
 * @return string
 */
function qGetUrl($tag, $_arg0 = null, $_arg1 = null, $_arg2 = null, $_arg3 = null, $_arg4 = null, $_arg5 = null, $_arg6 = null, $_arg7 = null, $_arg8 = null, $_arg9 = null, $_arg10 = null, $_arg11 = null, $_arg12 = null, $_arg13 = null, $_arg14 = null, $_arg15 = null)
{
	return qUrl($tag, $_arg0, $_arg1, $_arg2, $_arg3, $_arg4, $_arg5, $_arg6, $_arg7, $_arg8, $_arg9, $_arg10, $_arg11, $_arg12, $_arg13, $_arg14, $_arg15);
}

/**
 * Outputs a PHP array into a file as the PHP code required to setup that array
 * 
 * @param array $array
 * @param string $var_name
 * @param string $file_path
 * 
 * @return boolean
 */
function qArrayToCodeFile($array, $var_name, $file_path)
{
	// $f = fopen($file_path, "wt");
	// if (!$f)
	// 	return false;
	
	file_put_contents($file_path, 
		qArrayToCode($array, $var_name, true));
	
	// fclose($f);
}

/**
 * Transforms a PHP array to the PHP code required to setup that array
 * 
 * @param array $array
 * @param string $var_name
 * @param boolean $add_php_tags
 * @param resource $stream
 * @param integer $depth
 * @param integer $force_index
 * @return string
 * @throws Exception
 */
function qArrayToCode($array, $var_name = null, $add_php_tags = true, $stream = null, $depth = 0, $force_index = false, $whitespace = true)
{
	if ($var_name && ($var_name{0} === "\$"))
		$var_name = substr($var_name, 1);
	
	$str = $stream ? null : "";
	if ($add_php_tags)
		$stream ? fwrite($stream, "<?php\n") : ($str .= "<?php\n");
	if ($var_name)
		$stream ? fwrite($stream, "\$".$var_name." = ") : ($str .= "\$".$var_name." = ");
	
	if ($array === null)
	{
		$stream ? fwrite($stream, "null") : ($str .= "null");
		if ($var_name)
			$stream ? fwrite($stream, $whitespace ? ";\n" : ";") : ($str .= $whitespace ? ";\n" : ";");
	}
	else if (is_array($array))
	{
		$empty = empty($array);
		
		$pad = $whitespace ? str_pad("", $depth + 1, "\t") : "";
		$stream ? fwrite($stream, "array(") : ($str .= "array(");
		if ((!$empty) && $whitespace)
			$stream ? fwrite($stream, "\n") : ($str .= "\n");

		$p = 0;
		foreach ($array as $k => $v)
		{
			if ($pad)
				$stream ? fwrite($stream, $pad) : ($str .= $pad);
			if ($force_index || ($k !== $p))
				$stream ? fwrite($stream, is_string($k) ? ("\"".qaddslashes($k)."\" => ") : $k." => ") : ($str .= is_string($k) ? ("\"".qaddslashes($k)."\" => ") : $k." => ");
			
			if (is_string($v))
				$stream ? fwrite($stream, ("\"".qaddslashes($v)."\"")) : ($str .= ("\"".qaddslashes($v)."\""));
			else if (is_int($v) || is_float($v))
				$stream ? fwrite($stream, $v) : ($str .= $v);
			else if (is_bool($v))
				$stream ? fwrite($stream, ($v ? "true" : "false")) : ($str .= ($v ? "true" : "false"));
			else if (is_null($v))
				$stream ? fwrite($stream, "null") : ($str .= "null");
			else if (is_array($v))
				$stream ? qArrayToCode($v, null, false, $stream, $depth + 1) : ($str .= qArrayToCode($v, null, false, null, $depth + 1, $force_index, $whitespace));
			else
				throw new Exception("ONLY Scalar types accepted");
			
			$stream ? fwrite($stream, $whitespace ? ",\n" : ",") : ($str .= $whitespace ? ",\n" : ",");
			if (is_int($k))
				$p = $k + 1;
		}
		
		if (!$empty)
			$stream ? fwrite($stream, $pad) : ($str .= $pad);
		$stream ? fwrite($stream, ")") : ($str .= ")");
		if ($depth === 0)
			$stream ? fwrite($stream, ";") : ($str .= ";");
	}
	
	if ($add_php_tags)
		$stream ? fwrite($stream, "\n?>") : ($str .= "\n?>");
	
	return $str;
}

/**
 * Fixes addslashes to add slash before the dolar sign ($)
 * 
 * @param string $val
 * @return string
 */
function qaddslashes($val)
{
	/** http://ro1.php.net/manual/en/language.types.string.php#language.types.string.syntax.double
		\n 	linefeed (LF or 0x0A (10) in ASCII)
		\r 	carriage return (CR or 0x0D (13) in ASCII)
		\t 	horizontal tab (HT or 0x09 (9) in ASCII)
		\v 	vertical tab (VT or 0x0B (11) in ASCII) (since PHP 5.2.5)
		\e 	escape (ESC or 0x1B (27) in ASCII) (since PHP 5.4.0)
		\f 	form feed (FF or 0x0C (12) in ASCII) (since PHP 5.2.5)
		\\ 	backslash
		\$ 	dollar sign
		\" 	double-quote
	 */
	
	return addcslashes($val, "\$\"\\\x00\r\n\t"); // str_replace("\$", "\\\$", addslashes($val));
}

/**
 * Parses a string into a associative array that would describe an entity
 * ex: Orders.*,Orders.Items.{Date,Product,Quantity},Orders.DeliveryAddresses.*
 * The {} can be used to nest properties relative to the parent
 * 
 * @param string $str
 * @param boolean $mark
 * 
 * @return array
 */
function qParseEntity(string $str, $mark = false, $expand_stars = false, $start_class = null)
{
	$tokens = preg_split("/(\s+|\,|\.|\:|\{|\})/us", $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	$entity = array();

	$ctx_ent = &$entity;
	$ctx_prev = null;
	$ctx_sel = &$entity;
	$selected = null;

	// . => go deeper
	// , => reset to last `level` in this context
	// { => start a new context
	// } => exit current context
	$has_star = false;

	foreach ($tokens as $tok)
	{
		$frts = $tok{0};
		switch ($frts)
		{
			case " ":
			case "\t":
			case "\n":
			case "\r":
			case "\v":
				break;
			case ".":
			{
				// there is nothing to do tbh
				break;
			}
			case ",":
			{
				$ctx_sel = &$ctx_ent;
				if ($selected !== null)
				{
					$selected[] = true;
					// make sure you unset and not assign to null as it is a reference
					unset($selected);
				}
				break;
			}
			case "{":
			{
				// creates a new context
				$ctx_prev = array(&$ctx_ent, $ctx_prev);
				$ctx_ent = &$ctx_sel;
				break;
			}
			case "}":
			{
				// closes the current context
				$ctx_ent = &$ctx_prev[0];
				$ctx_prev = &$ctx_prev[1];
				if ($selected !== null)
				{
					$selected[] = true;
					// make sure you unset and not assign to null as it is a reference
					unset($selected);
				}
				break;
			}
			default:
			{
				// identifier
				if ($expand_stars && (!$has_star) && (($tok === '*') || ($frts === '@')))
					$has_star = true;
				($ctx_sel[$tok] !== null) ? null : ($ctx_sel[$tok] = array());
				$ctx_sel = &$ctx_sel[$tok];
				$mark ? ($selected = &$ctx_sel) : null;
				break;
			}
		}
	}
	
	if ($selected !== null)
	{
		$selected[] = true;
		// make sure you unset and not assign to null as it is a reference
		unset($selected);
	}
	
	if ($expand_stars && $start_class && (($entity["*"] !== null) || $has_star))
		qExpandStars($entity, $start_class);
	
	return $entity;
}

function qExpandStars(&$entity, $class, $property = null)
{
	$add = [];
	if (is_array($class) && $class)
	{
		// multiple
		$types = [];
		foreach ($class as $sc)
			$types[$sc] = QModel::GetTypeByName($sc);
	}
	else if ($class)
	{
		// single
		$types = QModel::GetTypeByName($class);
	}
		
	$unset = null;
	foreach ($entity as $k => &$sub)
	{
		if ($k === "*")
		{
			if (is_array($types))
			{
				foreach ($types as $ty)
				{
					foreach ($ty->properties as $k => $v)
						$add[$k] = [];
				}
			}
			else
			{
				foreach ($types->properties as $k => $v)
					$add[$k] = [];
			}
		}
		else if ($k{0} === "@")
		{
			$unset[$k] = 1;
			
			$me_on = [];
			if ($k === "@M")
			{
				if (is_array($class))
				{
					foreach ($class as $c)
						$me_on[] = $property ? $c::GetPropertyModelEntity($property) : $c::GetModelEntity();
				}
				else
					$me_on[] = $property ? $class::GetPropertyModelEntity($property) : $class::GetModelEntity();
			}
			else if ($k === "@ML")
			{
				if (is_array($class))
				{
					foreach ($class as $c)
						$me_on[] = $property ? $c::GetPropertyListingEntity($property) : $c::GetListingEntity();
				}
				else
					$me_on[] = $property ? $class::GetPropertyListingEntity($property) : $class::GetListingEntity();
			}
			else if (substr($k, 0, 3) === '@M_')
			{
				if ($property)
					$meth_name = "GetPropertyEntityFor".substr($op, 3);
				else
					$meth_name = "GetEntityFor".substr($op, 3);
				$me_on = $class::$meth_name($property);
				
				if (is_array($class))
				{
					foreach ($class as $c)
						$me_on[] = $c::$meth_name($property);
				}
				else
					$me_on[] = $class::$meth_name($property);
			}
			if ($me_on)
			{
				foreach ($me_on as $_mon)
				{
					$mon = is_string($_mon) ? qParseEntity($_mon, true) : $_mon;
					foreach ($mon as $mk => $mv)
						$add[$mk] = $mv;
				}
			}
		}
		else if ($sub && ($sub_types = qMixedTypes($types, $k)))
		{
			qExpandStars($sub, $sub_types, $property);
		}
	}
	
	if ($add)
	{
		unset($entity["*"]);
		foreach ($add as $k => $v)
		{
			if ($entity[$k] === null)
				$entity[$k] = $v;
		}
	}
	
	if ($unset)
	{
		foreach ($unset as $k => $v)
			unset($entity[$k]);
	}
	
	return $entity;
}

function qMixedTypes($types, $property)
{
	$ret = [];
	if (is_array($types))
	{
		foreach ($types as $ty)
		{
			$prop = $ty->properties[$property];
			if ($prop)
			{
				if (($ref_types = $prop->getReferenceTypes()))
				{
					if (!$ret)
						$ret = $ref_types;
					else
					{
						foreach ($ref_types as $k => $v)
							$ret[$k] = $v;
					}
				}

				if ((($coll = $prop->getCollectionType())) && ($ref_types = $coll->getReferenceTypes()))
				{
					if (!$ret)
						$ret = $ref_types;
					else
					{
						foreach ($ref_types as $k => $v)
							$ret[$k] = $v;
					}
				}
			}
		}
	}
	else
	{
		$prop = $types->properties[$property];
		if ($prop)
		{
			if (($ref_types = $prop->getReferenceTypes()))
			{
				if (!$ret)
					$ret = $ref_types;
				else
				{
					foreach ($ref_types as $k => $v)
						$ret[$k] = $v;
				}
			}

			if ((($coll = $prop->getCollectionType())) && ($ref_types = $coll->getReferenceTypes()))
			{
				if (!$ret)
					$ret = $ref_types;
				else
				{
					foreach ($ref_types as $k => $v)
						$ret[$k] = $v;
				}
			}
		}	
	}
	return $ret ?: null;
}

function qGetEntityFromData($obj, &$entity = [])
{
	if (!$entity)
		$entity = [];

	$props = $obj->getModelType()->properties;
	
	foreach ($props as $key => $prop)
	{
		$value = $obj->{$key};

		if (!$value)
			continue;
		
		$entity[$key] = [];
		if ($value instanceof \QModel)
			qGetEntityFromData($value, $entity[$key]);
		else if ($value instanceof \QModelArray)
		{
			foreach ($value as $itm)
			{
				if ($itm instanceof \QModel)
					qGetEntityFromData($itm, $entity[$key]);
			}
		}
	}
	return $entity;
}

function qImplodeEntity($entity)
{
	$str = "";
	$pos = 0;
	foreach ($entity as $k => $ent)
	{
		if ($pos)
			$str .= ",";
		$str .= $k;
		if ($ent)
		{
			$str .= ".{";
			$str .= qImplodeEntity($ent);
			$str .= "}";
		}
		$pos = 1;
	}
	return $str;
}

function qImplodeEntityFormated($entity, $depth = 0)
{
	$str = "";
	$pos = 0;
	foreach ($entity as $k => $ent)
	{
		if ($pos)
			$str .= ",\n";
		$str .= str_pad("", $depth, "\t").$k;
		if ($ent)
		{
			$str .= ".{\n";
			$str .= qImplodeEntityFormated($ent, $depth + 1);
			$str .= "}";
		}
		$pos = 1;
	}
	return $str;
}

function qSelector_Remove_Ids(array $selector = null)
{
	if (($selector === null) || empty($selector))
		return $selector;
	else if (isset($selector['Id']))
		unset($selector['Id']);
	foreach ($selector as $k => $v)
	{
		if (($v === null) || empty($v))
		{
			# continue
		}
		else if (is_array($v))
			$selector[$k] = qSelector_Remove_Ids($v);
	}
	
	return $selector;
}


/**
 * @todo Review if we need it after we implement the new security
 * 
 * @param QIModel $var
 * @return \QIModel
 */
function qToSql($var)
{
	if ($var instanceof QIModel)
		return "\${".$var->getId().",".$var->getModelType()->getIntId()."}";
	else if (is_array($var))
	{
		$ret = array();
		foreach ($var as $v)
			$ret[] = qToSql($v);
		return implode(",", $ret);
	}
	else if (is_bool($var))
		return $var ? "TRUE" : "FALSE";
	else
		return $var;
}

/**
 * The undefined instance
 */
final class _QUndefined_
{
	private static $V;
	private function __construct(){}
	public static function Get()
	{
		return self::$V ?: (self::$V = new _QUndefined_());
	}
	public function __toString()
	{
		return "#undefined";
	}
}

$_QUndefined = _QUndefined_::Get();

function QUndefined()
{
	global $_QUndefined;
	return $_QUndefined;
}

/**
 * Handles a frame specific request
 * 
 * @todo Move into a specific request handler class
 * 
 * @param string $filter
 * @param QIModel $instance
 * @return mixed[]
 * @throws Exception
 */
function execQB($filter = null, $instance = null)
{
	$pos = 0;
	$ret = array();
	$request = null;
	
	while (($request = $_GET["_qb{$pos}"]) || ($request = $_POST["_qb{$pos}"]))
	{
		$request_key = "_qb{$pos}";
		
		$meta_str = $request["_q_"];
		if (empty($meta_str))
		{
			$pos++;
			continue;
		}

		list ($class, $method, $id) = explode(".", $meta_str, 3);
		if (($class !== "QApi") && (!qIsA($class, "QIModel")) && (!qIsA($class, "QViewBase")))
			throw new Exception("You may only call a QIModel or a QViewBase. You have called a `{$class}`");
	
		if ((($filter === null) || ($method === $filter)) && 
				($instance ? ((get_class($instance) === $class) && (($id === null) || ($id == (($instance instanceof QViewBase) ? $instance->getFullId() : $instance->getId())))) : true))
		{
			$m_type = QModel::GetTypeByName($class);
			if (!$m_type)
				throw new Exception("Type does not exists {$class}");
			else if (!method_exists($class, $method))
				throw new Exception("Method does not exists {$class}::{$method}");
			
			$m_type_meth = $m_type ? $m_type->methods[$method] : null;
			if ((!$m_type_meth) || (!$m_type->methodHasApiAccess($method)))
			{
				throw new Exception("You do not have access to {$class}::{$method}");
			}
			
			if ((!$instance) && (!$m_type_meth->static))
			{
				$instance = new $class();
				if (!$instance->_qini)
					$instance->init(true);
			}

			unset($request["_q_"]);
			
			$fake_parent = null;
			
			$refs = [];
			if (isset($_FILES[$request_key]))
			{
				// name, type, tmp_name, error, size
				$files_rq = $_FILES[$request_key];
				$f_name = $files_rq["name"];
				$f_type = $files_rq["type"];
				$f_tmp_name = $files_rq["tmp_name"];
				$f_error = $files_rq["error"];
				$f_size = $files_rq["size"];
				
				$params = extractQbRequest($request, $fake_parent, null, $f_name, $f_type, $f_tmp_name, $f_error, $f_size, $refs);
			}
			else
				$params = extractQbRequest($request, $fake_parent, null, null, null, null, null, null, $refs);
			
			// catch any output in case of a render method
			if (QAutoload::$DebugPanel)
				QDebug::SetRequestData(array("class" => $m_type->class, "id" => ($instance ? $instance->getId() : null), "method" => $method, "params" => $params));
			
			ob_start();
			if ($m_type_meth->static)
				$ret[$pos] = call_user_func_array(array($class, $method), is_array($params) ? $params : [$params]);
			else
				$ret[$pos] = call_user_func_array(array($instance, $method), is_array($params) ? $params : [$params]);
			$output = ob_get_clean();

			if (!empty($output))
			{
				if ($ret[$pos] === null)
					$ret[$pos] = $output;
				else if (QAutoload::GetDevelopmentMode())
					$ret["__hiddenOutput__"][] = $output;
			}

			// now unset it
			unset($_GET["_qb{$pos}"]);
			unset($_POST["_qb{$pos}"]);
		}

		$pos++;
	}
	
	if (QWebRequest::IsAjaxRequest() || QWebRequest::IsFastAjax())
	{
		if (QAutoload::$DebugPanel)
		{
			// we will need to send the debug info also
			ob_start();
			$dbg_panel = new QDebugPanelCtrl();
			// $this->addControl($dbg_panel, "debugPanel");
			$dbg_panel->init();
			$dbg_panel->renderRequestInfo();

			$ret_data = ob_get_clean();
			if ($ret_data)
				$ret["__debugData__"] = $ret_data;
		}
		if (QAutoload::$DebugStacks)
		{
			$ret["__debugStack__"] = QAutoload::$DebugStacks;
		}
		if (\QAutoload::GetDevelopmentMode())
		{
			$ar = \QWebRequest::GetAjaxResponse();
			foreach ($ar['__hiddenOutput__'] ?: [] as $output_x)
				$ret["__hiddenOutput__"][] = $output_x;
		}
		
		// we have an AJAX request
		QWebRequest::SetAjaxResponse($ret);
	}
	return $ret;
}

/**
 * Extracts the data from the request.
 * 
 * @todo Move into a specific request handler class
 * 
 * @param type $data
 * @param type $parent
 * @param type $key
 * @param type $f_name
 * @param type $f_type
 * @param type $f_tmp_name
 * @param type $f_error
 * @param type $f_size
 * @return boolean|\QFile
 * @throws Exception
 */
function extractQbRequest($data, &$parent = null, $key = null, $f_name = null, $f_type = null, $f_tmp_name = null, $f_error = null, $f_size = null, &$refs = null)
{
	if (is_array($data))
	{
		$file_path = null;
		$file_params = null;
		
		$class = $data["_ty"];
		if ((($class === "QFile") || qIsA($class, "QFile")) && (!($parent instanceof QFile)))
		{
			//  && ($data["_ftype"] === "_file")) || (($parent instanceof QFile) && ($key === "Path"))
			$is_QFile = ($data["_ftype"] !== "_file");
			
			if ($is_QFile)
			{
				$f_name = $f_name["Path"];
				$f_type = $f_type["Path"];
				$f_tmp_name = $f_tmp_name["Path"];
				$f_error = $f_error["Path"];
				$f_size = $f_size["Path"];
			}
			
			if ($f_error["_dom"])
			{
				if ($f_error["_dom"] === UPLOAD_ERR_NO_FILE)
					return;
				throw new Exception("Upload failed for file {$f_name["_dom"]}.\nError: ".$f_error["_dom"]);
			}
			
			if ($f_name["_dom"])
			{
				// @storage.filePath
				$prop = null;
				if ($parent instanceof QIModelArray)
					$prop = $parent->getModelProperty();
				else if ($parent instanceof QIModel)
					$prop = $parent->getModelType()->properties[$key];

				$params = array("name" => $f_name["_dom"], "type" => $f_type["_dom"], "tmp_name" => $f_tmp_name["_dom"], "error" => $f_error["_dom"], "size" => $f_size["_dom"]);
				$file_params = $params;

				if ($prop && ($filePath = $prop->storage["filePath"]))
				{
					$full_path = realpath($filePath);
					if (!is_dir($full_path))
						throw new Exception("The @storage.filePath {$full_path} specified in ".$prop->parent->class.".".$prop->name." is missing");
					$full_path = rtrim($full_path, "/\\")."/";
					$fn = $params["name"];

					$pos = 1;
					$ext = pathinfo($fn, PATHINFO_EXTENSION) ?: null;
					$baseFn = pathinfo($fn, PATHINFO_FILENAME);
					// avoid overwrite
					while (file_exists($full_path.$fn))
						$fn = $baseFn."-".($pos++).($ext !== null ? ".".$ext : "");
					move_uploaded_file($f_tmp_name["_dom"], $full_path.$fn);
					if (($chmod = $prop->storage["fileMode"]))
					{
						chmod($full_path.$fn, octdec($chmod));
					}

					$file_path = ($prop->storage["fileWithPath"]) ? (rtrim($filePath, "/\\")."/".$fn) : $fn;

					if (!$is_QFile)
						return $file_path;
				}
				else if (!$is_QFile)
				{
					if ($parent instanceof QIModel)
						return (($handleU_ret = $parent->handleUpload($key, $params)) !== QUndefined()) ? $handleU_ret : $params;
					else
						return $params;
				}
			}
			// else noting to do , no file was provided
		}
		
		$params = null;
		if ($class && ($class !== "array") && ($class !== "Array"))
		{
			if (class_exists($class))
			{
				$obj_id = $data["_id"] ?: ($data["Id"] ?: ($data["id"] ?: $data["ID"]));
				$params = $obj_id ? ($refs[$obj_id][$class] ?: ($refs[$obj_id][$class] = new $class())) : new $class();
			}
			else
				$params = null;
		}
		else
			$params = [];

		if ($class && ($params === null))
		{
			if (\QAutoload::GetDevelopmentMode())
				qvar_dumpk(func_get_args());
			throw new Exception("Invalid class {$class}");
		}

		// class does not exist
		if ($params === null)
			return null;

		if ($class)
			unset($data["_ty"]);

		if ($params instanceof QIModel)
		{
			if (($d_id = $data["_id"]))
			{
				$id_val = extractQbRequest($d_id);
				if ($id_val !== null)
					$params->setId($id_val);
				unset($data["_id"]);
			}
			if (($d_ts = $data["_ts"]))
			{
				$ts_ = extractQbRequest($d_ts);
				if ($ts_ !== null)
					$params->_ts = (int)$ts_;
				unset($data["_ts"]);
			}
			if (($d_tsp = $data["_tsp"]))
			{
				$params->_tsp = extractQbRequest($d_tsp);
				// var_dump(get_class($params), $params->_tsp);
				unset($data["_tsp"]);
			}
			if (($d_tmpid = $data["_tmpid"]))
			{
				$params->_tmpid = extractQbRequest($d_tmpid);
				unset($data["_tmpid"]);
			}
			if (($d_rowi = $data["_rowi"]))
			{
				$params->_rowi = extractQbRequest($d_rowi);
				unset($data["_rowi"]);
			}
			if (($_singleSync = $data["_singleSync"]))
			{
				$params->_singleSync = extractQbRequest($_singleSync);
				unset($data["_singleSync"]);
			}

			if ($params instanceof QFile)
			{
				if ($data["_ftype"])
					unset($data["_ftype"]);
				if ($data["_file"])
					unset($data["_file"]);
			}
		}

		if (!is_array($params))
		{
			if ($params instanceof QIModelArray)
			{
				$collection_prop = null;
				if (($parent instanceof QIModel) && $key)
				{
					$collection_prop = $parent->getModelType()->properties[$key];
					if ($collection_prop)
						$params->setModelProperty($collection_prop);
				}
				if ($collection_prop)
				{
					$set_meth = "set{$collection_prop->name}_Item_";
					$parent->{"set{$collection_prop->name}"}($params);
					foreach ($data as $k => $v)
					{
						$i_val = extractQbRequest($v, $params, $k, $f_name ? $f_name[$k] : null, $f_type ? $f_type[$k] : null, $f_tmp_name ? $f_tmp_name[$k] : null, $f_error ? $f_error[$k] : null, $f_size ? $f_size[$k] : null, $refs);
						$parent->$set_meth($i_val, $k);
					}
				}
				else
				{
					foreach ($data as $k => $v)
						$params[$k] = extractQbRequest($v, $params, $k, $f_name ? $f_name[$k] : null, $f_type ? $f_type[$k] : null, $f_tmp_name ? $f_tmp_name[$k] : null, $f_error ? $f_error[$k] : null, $f_size ? $f_size[$k] : null, $refs);
				}
			}
			else
			{
				$params_props = $params->getModelType()->properties;
				foreach ($data as $k => $v)
				{
					if ($params_props[$k])
					{
						$ex_v = extractQbRequest($v, $params, $k, $f_name ? $f_name[$k] : null, $f_type ? $f_type[$k] : null, $f_tmp_name ? $f_tmp_name[$k] : null, $f_error ? $f_error[$k] : null, $f_size ? $f_size[$k] : null, $refs);
						$params->{"set{$k}"}($ex_v);
					}
					else
						$params->$k = extractQbRequest($v, $params, $k, $f_name ? $f_name[$k] : null, $f_type ? $f_type[$k] : null, $f_tmp_name ? $f_tmp_name[$k] : null, $f_error ? $f_error[$k] : null, $f_size ? $f_size[$k] : null, $refs);
				}
			}
		}
		else
		{
			$use_data = (($class === "Array") && is_array($tmp_di = $data["_items"])) ? $tmp_di : $data;
			foreach ($use_data as $k => $v)
			{
				$params[$k] = extractQbRequest($v, $params, $k, $f_name ? $f_name[$k] : null, $f_type ? $f_type[$k] : null, $f_tmp_name ? $f_tmp_name[$k] : null, $f_error ? $f_error[$k] : null, $f_size ? $f_size[$k] : null, $refs);
			}
		}
		
		if (($params instanceof QFile) && $file_path)
		{
			$params->Path = $file_path;
			
			$params->_upload = $file_params;
			$params->handleUpload($key, $params->_upload);
		}

		return $params;
	}
	else if ($data === null)
	{
		return null;
	}
	else if (is_string($data))
	{
		if ($data{0} === "_")
			return (string)substr($data, 1);
		else if ($data === "true")
			return true;
		else if ($data === "false")
			return false;
		else if ($data === "null")
			return null;
		else if (is_numeric($data))
			return (strpos($data, ".") !== false) ? floatval($data) : intval($data);
		else 
			return $data;
	}
	else
		return $data;
}

/**
 * Checks that the input URL is in the path of the request
 * 
 * @param string $url
 * @return boolean
 */
function qurl_check($url)
{
	return QUrl::$Requested && (substr(QUrl::$Requested->url, 0, strlen($url)) === $url);
}

/**
 * 
 * @param string $property
 * @return QIModel|mixed
 */
function QData($property = null)
{
	return $property ? QApp::Data()->{$property} : QApp::Data();
}

/**
 * 
 * @param string $property
 * @return QIModel|mixed
 */
function QNewData()
{
	return QApp::QNewData();
}

/**
 * Gets a url based on a tag
 * 
 * @param string $tag
 * @param mixed $_arg0
 * @param mixed $_arg1
 * @param mixed $_arg2
 * @param mixed $_arg3
 * @param mixed $_arg4
 * @param mixed $_arg5
 * @param mixed $_arg6
 * @param mixed $_arg7
 * @return string
 */
function qUrl($tag, $_arg0 = null, $_arg1 = null, $_arg2 = null, $_arg3 = null, $_arg4 = null, $_arg5 = null, $_arg6 = null, $_arg7 = null, $_arg8 = null, $_arg9 = null, $_arg10 = null, $_arg11 = null, $_arg12 = null, $_arg13 = null, $_arg14 = null, $_arg15 = null)
{
	$url = null;
	return QApp::$UrlController->getUrlForTag_($tag, $url, $_arg0, $_arg1, $_arg2, $_arg3, $_arg4, $_arg5, $_arg6, $_arg7, $_arg8, $_arg9, $_arg10, $_arg11, $_arg12, $_arg13, $_arg14, $_arg15);
}

/**
 * Gets a standard MVVM bind
 * 
 * @param QIModel $object
 * @param string $rowid Rowid in case of an element within a collection
 * @return string
 */	
function qb($object, $rowid = null)
{
	if ($object instanceof QIModel)
		return "(".$object->getModelType()->class.(($id = $object->getId()) ? "|".$id : "").($rowid ?: "").")";
	return "";
}

/**
 * Debugs some variables
 * 
 * @return string
 */
function qDebug()
{
	ob_start();
	// QModel::DumpIt($self);
	qDebugStackInner(func_get_args(), false, false);
	return ob_get_clean();
}

/**
 * Better var_dump for objects/model
 * 
 * @return string
 */
function qvar_dumpk()
{
	ob_start();
	$ret = "";
	foreach (func_get_args() as $arg)
		$ret .= qDebugStackInner($arg, false, false);
	$ret = ob_get_clean();

	echo $ret;
	return $ret;
}

/**
 * Better var_dump for objects/model
 * 
 * @return string
 */
function qvar_dump()
{
	ob_start();
	$ret = "";
	foreach (func_get_args() as $arg)
		$ret .= qDebugStackInner($arg, false, false);
	$ret = ob_get_clean();
	
	if ((($hxrw = $_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($hxrw) === 'xmlhttprequest')) || 
											(filter_input(INPUT_POST, "__qAjax__") || filter_input(INPUT_GET, "__qAjax__")))
		\QWebRequest::AddHiddenOutput($ret);
	else
		echo $ret;
	
	return $ret;
}

function qdumptofile()
{
	$f = fopen("dump.html", "a+");
	ob_start();
	foreach (func_get_args() as $arg)
		qDebugStackInner($arg, false, false);
	fwrite($f, ob_get_clean());
	fclose($f);
}

function qvdumptofile()
{
	$f = fopen("vdump.html", "a+");
	ob_start();
	var_dump(func_get_args());
	echo "<hr/>";
	fwrite($f, ob_get_clean());
	fclose($f);
}

/**
 * Better get_dump for objects/model
 * 
 * @return string
 */
function qget_dump()
{
	ob_start();
	foreach (func_get_args() as $arg)
		qDebugStackInner($arg, false, false);
	return ob_get_clean();
}

/**
 * Better get_dump for objects/model
 * 
 * @return string
 */
function qtrack_dump()
{
	ob_start();
	foreach (func_get_args() as $arg)
		qDebugStackInner($arg, false, false);
	return (\QAutoload::$DebugStacks[] = ob_get_clean());
}

/**
 * Better var_dump for objects/model
 * 
 * @return string
 */
function qVarDump()
{
	return call_user_func("qvar_dump", func_get_args());
}

/**
 * Better var_dump for objects/model
 * 
 * @return string
 */
function qVarDumpK()
{
	return call_user_func("qvar_dumpk", func_get_args());
}

/**
 * Escapes the name of a table
 * 
 * @param string $table_name
 * @return string
 */
function qEscTable($table_name)
{
	if (($dot = strpos($table_name, ".")) !== false)
		return "`".substr($table_name, 0, $dot)."`.`".substr($table_name, $dot + 1)."`";
	else
		return "`".$table_name."`";
}

/**
 * Replaces the \ in class name (if any) with - for filesystem compatibility
 * 
 * @param string $class
 * @return string
 */
function qClassToPath($class)
{
	return str_replace("\\", "-", $class);
}

/**
 * Replaces the \ in class name (if any) with _ for php var name compatibility
 * 
 * @param string $class
 * @return string
 */
function qClassToVar($class)
{
	return str_replace("\\", "_", $class);
}

/**
 * The reverse for qClassToPath
 * 
 * @param string $path_part
 * @return string
 */
function qPathToClass($path_part)
{
	return str_replace("-", "\\", $path_part);
}

/**
 * Gets the name of a class without the namespace
 * @param string $class
 * @return string
 */
function qClassWithoutNs($class)
{
	return (($nsp = strrpos($class, "\\")) === false) ? $class : substr($class, $nsp + 1);
}

/**
 * Extracts class name and namespace from a full class name
 * 
 * @param string $full_class
 * @return string[]
 */
function qClassShortAndNamespace($full_class)
{
	return (($nsp = strrpos($full_class, "\\")) === false) ? [$full_class, null] : [substr($full_class, $nsp + 1), substr($full_class, 0, $nsp)];
}

function qClassRelativeToNamespace($full_class, $namespace = null)
{
	if (!$namespace)
		return $full_class;
	$ns_len = strlen($namespace);
	return ($ns_len && (substr($full_class, 0, $ns_len) === $namespace)) ? substr($full_class, $ns_len + 1) : "\\".$full_class;
}

/**
 * 
 * @param string|object $object_class
 * @param string $class
 * @return boolean
 */
function qIsA($object_class, $class)
{
		if ($object_class === $class)
			return true;
		else if (is_object($object_class))
			return $object_class instanceof $class;
		else if (is_string($object_class) && (class_exists($object_class) || interface_exists($object_class)))
		{
			if (interface_exists($class))
			{
				$ci = class_implements($object_class);// ($extby = QAutoload::GetClassExtendedBy($class)) && $extby[$object_class];
				return ($ci && $ci[$class]);
			}
			else 
				return is_subclass_of($object_class, $class);
		}
		else
			return false;
}

/**
 * Encodes data for a request
 * 
 * @param mixed $data
 * @param string $class_name
 * @param string $method
 * @param string $fullId
 * @param integer $index
 * @return mixed
 */
function qbEncodeRequest($data, $class_name, $method, $fullId = null, $index = 0)
{
	return qbEncodeElement(array("_qb{$index}" => array("_q_" => $class_name.".".$method.($fullId ? ".".$fullId : ""), 0 => $data)));
}

/**
 * Encodes one element for a request
 * 
 * @param mixed $v
 * @param string|integer $indx
 * @param string $key
 * @param array $post
 * @return string
 */
function qbEncodeElement($v, $indx = null, $key = null, &$post = null)
{
	$ty = gettype($v);
	if ($post)
	{
		switch($ty)
		{
			case "NULL":
				return "null";
			case "string":
				return $post[$indx] = ((($key === "_ty") || ($key === "_q_")) ? "" : "_") . urlencode($v);
			case "integer":
			case "double":
				return $post[$indx] = urlencode($v);
			case "boolean":
				return $post[$indx] = ($v ? "true" :  "false");
			case "array":
			{
				$ret = "";
				foreach ($v as $k => $v_itm)
				{
					$k_index = $indx ? $indx . "[" . rawurlencode($k) . "]" : rawurlencode($k);
					qbEncodeElement($v_itm, $k_index, $k, $post);
				}
				return $post;
			}
			default:
				return null;
		}
	}
	else
	{
		switch($ty)
		{
			case "NULL":
				return "null";
			case "string":
				return "&{$indx}=" . ((($key === "_ty") || ($key === "_q_")) ? "" : "_") . urlencode($v);
			case "integer":
			case "double":
				return "&{$indx}=" . urlencode($v);
			case "boolean":
				return $v ? "&{$indx}=true" : "&{$indx}=false";
			case "array":
			{
				$ret = "";
				foreach ($v as $k => $v_itm)
				{
					$k_index = $indx ? $indx . "[" . rawurlencode($k) . "]" : rawurlencode($k);
					$ret .= qbEncodeElement($v_itm, $k_index, $k);
				}
				return $ret;
			}
			default:
				return null;
		}
	}
}

/**
 * Encodes one element for a request
 * 
 * @param mixed $v
 * @param string|integer $indx
 * @param string $key
 * @param array $post
 * @return string
 */
function qbArrayToUrl($v, $indx = null)
{
	$ty = gettype($v);
	switch($ty)
	{
		case "NULL":
			return "null";
		case "string":
		case "integer":
		case "double":
			return "&{$indx}=" . urlencode($v);
		case "boolean":
			return $v ? "&{$indx}=true" : "&{$indx}=false";
		case "array":
		{
			$ret = "";
			foreach ($v as $k => $v_itm)
			{
				$k_index = $indx ? $indx . "[" . rawurlencode($k) . "]" : rawurlencode($k);
				$ret .= qbArrayToUrl($v_itm, $k_index);
			}
			return $ret;
		}
		default:
			return null;
	}
}

/**
 * Empties a directory 
 * 
 * @param string $dir
 * @param boolean $self
 * @return boolean
 */
function qEmptyDir($dir, $self = false)
{
	$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST);

	foreach ($files as $fileinfo)
		$fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());

	return $self ? rmdir($dir) : true;
}

/**
 * Gets the ID of a type
 * 
 * @param string $type
 * @return integer
 */
function getTypeId($type)
{
	if ($type{0} === strtolower($type{0}))
		return QModelType::GetScalarTypeId($type);
	else
		return QApp::GetStorage()->getTypeIdInStorage($type);
}

/**
 * @todo Deprecated
 * @deprecated 
 * 
 * @param string $html
 * @param string $bind
 * @return string
 */
function qb_inject_bind($html, $bind)
{
	if (($p = strpos($html, ">")) !== false)
	{
		if ($p && ($html{$p - 1} === "/"))
			$p--;
		return substr($html, 0, $p).$bind.substr($html, $p);
	}
	else
		return $html;
}

/**
 * Creates a directory using the result of umask() for permissions
 * 
 * @param string $path
 * @param boolean $recursive
 * @param integer $umask
 * @return boolean
 */
function qmkdir($path, $recursive = true, $umask = null)
{
	return empty($path) ? false : (is_dir($path) ? true : (mkdir($path, ($umask === null) ? (0777 & ~umask()) : $umask, $recursive)));
}

/**
 * Extracts data from a JSON 
 * 
 * @param mixed $data
 * @param string $expected_type
 * @param QIModel $parent
 * @return mixed
 * @throws Exception
 */
function extractJsonRequest($data, $expected_type = null, QIModel $parent = null)
{
	if (is_object($data))
	{
		if (!$expected_type)
			return $data;
		
		if (!($expected_type && qIsA($expected_type, "QIModel")))
			throw new Exception("Invalid input data type");
		
		if ($parent)
		{
			$ret = $parent;
			$ty = get_class($parent);
		}
		else if (($ty = $data->_ty) && class_exists($ty) && qIsA($ty, $expected_type))
		{
			$ret = new $ty();
		}
		else
		{
			$ret = new $expected_type();
			$ty = $expected_type;
		}
		
		$m_ty = QModel::GetTypeByName($ty);
		
		if (($id = $data->_id) || ($id = $data->id) || ($id = $data->Id))
			$ret->setId($id);
		
		$ret->apiAdaptInput($data);
		
		foreach ($m_ty->properties as $p_name => $property)
		{
			$v = $data->$p_name;
			
			// enforce data types as accepted by the $property's definition
			if ($v === null)
			{
				$ret->set($p_name, null);
			}
			else if (is_object($v))
			{
				if (!$property->hasReferenceType())
					throw new Exception("Invalid input data");
				
				$ret->set($p_name, extractJsonRequest($v, reset($property->getReferenceTypes())));
			}
			else if (is_array($v))
			{
				if (!$property->hasCollectionType())
					throw new Exception("Invalid input data");
				
				$arr = new QModelArray();
				
				$exp_acc_ty = $property->getCollectionType();
				
				foreach ($v as $arr_v)
				{
					if (is_null($arr_v))
						$arr[] = null;
					else if (is_object($arr_v))
					{
						if (!$exp_acc_ty->hasReferenceType())
							throw new Exception("Invalid input data");

						$arr[] = extractJsonRequest($arr_v, reset($exp_acc_ty->getReferenceTypes()));
					}
					else if (is_array($arr_v))
						throw new Exception("Invalid input data");
					else
						$arr[] = $arr_v;
				}
						
				$ret->set($p_name, $arr);
			}
			else
			{
				if (!$property->hasScalarType())
					throw new Exception("Invalid input data");
				
				$ret->set($p_name, $v);
			}
		}
		
		return $ret;
	}
	/*
	else if (is_array($data))
	{
		// we will do no conversion
	}*/
	else // array and scalars that have no type
		return $data;
}

/**
 * Gets the object's internal reference
 * 
 * @param stdClass $obj
 * @return integer
 */
function qGetObjRef($obj)
{
	ob_start();
	debug_zval_dump($obj);
	$str = ob_get_clean();
	return (($p1 = strpos($str, "#")) !== false) ? ((($p2 = strpos($str, " ", $p1 + 1)) !== false) ? (int)substr($str, $p1 + 1, $p2 - $p1 - 1) : null) : null;
}

/**
 * The debug stack
 * 
 * @return array
 */
function qDebugStack()
{
	/* we may create too much confusion with this so I have disabled it
	 * $args = func_get_args();
	 * if ((func_num_args() === 1) && is_array($args[0]))
		$args = $args[0];*/
	return qDebugStackInner(func_get_args(), true, true);
}

/**
 * Inner function for qDebugStack
 * 
 * @param mixed[] $args
 * @param boolean $with_stack
 * @param boolean $on_shutdown
 */
function qDebugStackInner($args, $with_stack = false, $on_shutdown = false, string $title = '', bool $collapsed = false, bool $with_border = true, int $max_depth = 8)
{
	if ($max_depth < 1)
		return;
	
	if ($on_shutdown)
		ob_start();
	
	$css_class = "_dbg_".uniqid();
	
	?><div class="<?= $css_class ?>">
		<script type="text/javascript">
			if (!window._dbgFuncToggleNext)
			{
				window._dbgFuncToggleNext = function(dom_elem)
				{
					var next = dom_elem ? dom_elem.nextSibling : null;
					// skip until dom element
					while (next && (next.nodeType !== 1))
						next = next.nextSibling;
					if (!next)
						return;
					
					if ((next.offsetWidth > 0) || (next.offsetHeight > 0))
						next.style.display = 'none';
					else
						next.style.display = 'block';
				};
			}
		</script>
	<style type="text/css">
		
		div.<?= $css_class ?> {
			font-family: monospace;
			font-size: 12px;
			<?php if ($with_border): ?>
			padding: 10px;
			margin: 10px;
			border: 2px dotted gray;
			<?php endif; ?>
		}
		
		div.<?= $css_class ?> h4 {
			font-size: 15px;
			margin: 5px 0px 5px 0px;
		}
		
		div.<?= $css_class ?> table {
			border-collapse: collapse;
			border: 1px solid black;
			padding: 3px;
		}
		
		div.<?= $css_class ?> table tr:first-child th {
			background-color: blue;
			color: white;
		}
		
		div.<?= $css_class ?> table th, div.<?= $css_class ?> table td {
			text-align: left;
			padding: 3px;
			border: 1px solid black;
			vertical-align: top;
		}

		div.<?= $css_class ?> table td {
			
		}
		
		div.<?= $css_class ?> ._dbg_params {
			cursor: pointer;
			color: blue;
		}
		
		div.<?= $css_class ?> pre {
			margin: 0;
		}
		
		<?php if ($collapsed): ?>
		div.<?= $css_class ?> pre div {
			display: none;
		}
		<?php else: ?>
		div.<?= $css_class ?> pre div > div {
			display: none;
		}
		<?php endif; ?>
		
		div.<?= $css_class ?> pre span._dbg_expand {
			cursor: pointer;
			color: blue;
		}
		
		div.<?= $css_class ?> pre span._dbg_s {
			color: green;
		}
		
		div.<?= $css_class ?> pre span._dbg_nl {
			color: red;
		}
		
		div.<?= $css_class ?> pre span._dbg_bl {
			color: orange;
		}
		
	</style>
	<?php

	$stack = debug_backtrace();
	// remove this call
	array_shift($stack);
	// and previous
	array_shift($stack);
	
	$stack_1 = end($stack);
	$stack_1_file = $stack_1["file"];
	
	// remove GetStack
	// array_pop($stack);
	
	// $stack = array_reverse($stack);
	$doc_root = $_SERVER["DOCUMENT_ROOT"];
	
	if ($title)
		echo "<h4>{$title}</h4>\n";
	
	// var_dump(array_keys($args));
	$bag = [];
	qDSDumpVar($args, $max_depth);

	if ($with_stack)
	{
		// 1. print stack
		?>
		<h4>Stack</h4>
		<table>
			<tr>
				<th>Module</th>
				<th>Calling From</th>
				<th>Line</th>
				<th>Called Class</th>
				<th>Function</th>
				<th>Called in File</th>
				<th>Params</th>
			</tr>
			<tr>
				<th colspan="3"></th>
				<th colspan="4">Entry: <?= $stack_1_file ?></th>
			</tr>
			<?php

				foreach ($stack as $jump)
				{
					$file = $jump["file"];

					$file_module = QAutoload::GetModulePathForPath($file);
					$caption_path = $file_module ? "[".basename($file_module)."] ".substr($file, strlen($file_module)) : $file;

					$base_name = basename($file);
					$calling_class = $base_name;
					if (($base_name{0} === strtoupper($base_name{0})) && (substr($base_name, -4) === ".php"))
						$calling_class = substr($base_name, 0, -4);

					$file_short = (substr($file_module, 0, strlen($doc_root)) === $doc_root) ? substr($file_module, strlen($doc_root)) : $file_module;

					?><tr>
						<th><?= $file_module ? "[".basename($file_module)."]" : "" ?></th>
						<th><?= $calling_class ?></th>
						<th><?= $jump["line"] ?></th>
						<th><?= $jump["class"].((($jump["object"] instanceof QIModel) && ($jo_id = $jump["object"]->getId())) ? "#".$jo_id : "") ?></th>
						<td><?= $jump["function"] ?></td>
						<td><?= $caption_path.($file_short ? "<br/>".$file_short : "") ?></td>
						<td class="_dbg_params" onclick="_dbgFuncToggleNext(this.parentNode);">[Show]</th>
					</tr>
					<tr style="display: none;">
						<td colspan="3"></td>
						<td colspan="4"><?php qDSDumpVar($jump["args"]) ?></td>
					</tr>
					<?php
				}

			?>
		</table>
		<?php
	}
	?></div><?php
	
	if ($on_shutdown)
	{
		// AJAX request
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'))
		{
			QAutoload::$DebugStacks[] = ob_get_clean();
		}
		else
			register_shutdown_function("qDebugStackOutput", ob_get_clean());
	}
}

/**
 * Inner function for qDebugStackInner
 * 
 * @param mixed $var
 * @param integer $max_depth
 * @param object[] $bag
 * @param integer $depth
 */
function qDSDumpVar($var, $max_depth = 8, &$bag = null, $depth = 0, $accessModifier = null, $wasSet = null)
{
	if ($max_depth < 0)
		return;
	
	$ty = gettype($var);
	
	if (!$bag)
		$bag = array();
	
	if ($depth === 0)
		echo "<pre>\n";
	
	$pad = str_repeat("\t", $depth);
	
	switch ($ty)
	{
		case "string":
		{
			echo "[string(".strlen($var).")]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo "<span class='_dbg_s'>";
			// wordwrap ( string $str [, int $width = 75 [, string $break = "\n" [, bool $cut = false ]]] )
			if (strlen($var) > (1024 * 1024))
			{
				// very big !
				echo '"'.preg_replace(['/\\r/us', '/\\n/us'], ["\\r", "\n"], htmlspecialchars(substr($var, 0, 1024*1024))).' [... truncated ...]"';
			}
			else
				echo '"'.preg_replace(['/\\r/us', '/\\n/us'], ["\\r", "\n"], htmlspecialchars($var)).'"';
			echo "</span>";
			break;
		}
		case "NULL":
		{
			echo ($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": <span class='_dbg_nl'>[null]</span>";
			break;
		}
		case "integer":
		{
			echo "[int]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo $var;
			break;
		}
		case "double":
		{
			echo "[float]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo $var;
			break;
		}
		case "boolean":
		{
			echo "[bool]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": <span class='_dbg_bl'>";
			echo $var ? "true" : "false";
			echo "</span>";
			break;
		}
		case "array":
		{
			echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[array(".count($var).")]:</span>\n";
			echo "<div>";
			foreach ($var as $k => $v)
			{
				echo $pad."\t<b>{$k}</b>";
				if ($max_depth)
					qDSDumpVar($v, $max_depth - 1, $bag, $depth + 1, $accessModifier, $wasSet);
				else
					echo "<span class='_dbg_nl'>*** too deep</span>";
				echo "\n";
			}
			echo "</div>";
			break;
		}
		case "object":
		{
			$obj_class = get_class($var);
			if (substr($obj_class, 0, strlen('class@anonymous')) === 'class@anonymous')
			{
				echo "#class@anonymous";
				break;
			}
			
			if ($obj_class === 'Generator')
			{
				echo "#Generator";
				break;
			}
			
			$ref_id = array_search($var, $bag, true);
			if ($ref_id === false)
			{
				end($bag);
				$ref_id = key($bag);
				$ref_id = ($ref_id === null) ? 0 : $ref_id + 1;
				
				$ref_id++;
				
				$bag[] = $var;
			}
			else
			{
				$ref_id++;
				
				echo "[{$obj_class}#{$ref_id}".($var->_id ? "; id:".$var->_id : ($var->Id ? "; Id:".$var->Id : ""))."]: <span class='_dbg_expand'>#ref</span>";
				return;
			}

			echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[{$obj_class}";
			if ($var instanceof \Closure)
				echo "]";
			else
				echo ($var instanceof QIModelArray ? "(".$var->count().")" : "").
						"#{$ref_id}".($var->_id ? "; id:".$var->_id : ($var->Id ? "; Id:".$var->Id : ""))."]"
						.($accessModifier ? "[{$accessModifier}]" : "");
			echo ":</span>\n";
			echo "<div>";

			$_isqm = ($var instanceof \QModel);
			$props = $_isqm ? $var->getModelType()->properties : $var;
			
			$_refCls = $_isqm ? $var->getModelType()->getReflectionClass() : null;

			$null_props = [];
			
			if ($_isqm || ($var instanceof \QModelArray))
			{
				if ($var->_ts !== null)
				{
					echo $pad."\t<b>_ts: </b>";
					echo "<span class='_dbg_nl'>{$var->_ts}</span>";
					echo "\n";
				}
				if ($var->_tsp !== null)
				{
					echo $pad."\t<b>_tsp: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_tsp)."</span>";
					echo "\n";
				}
				if ($var->_tsx !== null)
				{
					echo $pad."\t<b>_tsx: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_tsx)."</span>";
					echo "\n";
				}
				if ($var->_wst !== null)
				{
					echo $pad."\t<b>_wst: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_wst)."</span>";
					echo "\n";
				}
				if ($var->_rowi !== null)
				{
					echo $pad."\t<b>_rowi: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_rowi)."</span>";
					echo "\n";
				}
			}
			
			foreach ($props as $k => $_v)
			{
				if ($_isqm && (($k === "_typeIdsPath") || ($k === "_qini") || ($k === "_ty")))
					continue;
				
				$v = $_isqm ? $var->$k : $_v;
				
				$accessModifier = null;
				$wasSet = $_isqm ? $var->wasSet($k) : null;
				if ($_isqm && $props[$k])
				{
					// $v = $var->get($k);
					$refP = $_refCls->hasProperty($k) ? $_refCls->getProperty($k) : null;
					// get access type for property (for now we are interested only on public, private and protected
					if ($refP)
						$accessModifier = $refP->isPublic() ? "public" : ($refP->isPrivate() ? "private" : ($refP->isProtected() ? "protected" : null));
				}

				if ($v !== null)
				{
					echo $pad."\t<b>{$k}</b>";
					if ($max_depth)
					{
						qDSDumpVar($v, $max_depth - 1, $bag, $depth + 1, $accessModifier, $wasSet);
					}
					else
						echo "<span class='_dbg_nl'>*** too deep</span>";
					echo "\n";
				}
				else
					$null_props[$k] = $k;
			}
			
			if ($null_props)
			{
				ksort($null_props);
				echo $pad."\t<b>Null props: ".implode(", ", $null_props)."</b>";
			}
			echo "</div>";
			break;
		}
		case "resource":
		{
			echo get_resource_type($var)." #".intval($var);
			break;
		}
		case "function":
		{
			echo "#Closure";
			break;
		}
		default:
		{
			// unknown type
			break;
		}
	}
	
	if ($depth === 0)
		echo "</pre>\n";
}

/**
 * Outputs the debug stack
 * For now we just echo
 * 
 * @param string $output
 */
function qDebugStackOutput($output)
{
	echo $output;
}

/**
 * Transforms variables into strings that can be safety injected into an SQL query 
 * 
 * @param mixed $c_bind
 * @param boolean $array_brackets
 * @return string
 * @throws Exception
 */
function _myScBind($c_bind, $array_brackets = true, $pure_null = false)
{
	$bind_ty = gettype($c_bind);
	switch ($bind_ty)
	{
		case "string":
			// return is_string($param) ? (($s = QApp::GetStorage()) ? $s->escapeString($param) : addslashes($param)) : $param;
			return "'".(($s = QApp::GetStorage()) ? $s->escapeString($c_bind) : addslashes($c_bind))."'";
		case "integer":
		case "double":
			return (string)$c_bind;
		case "boolean":
			return $c_bind ? "1" : "0";
		case "NULL":
			// dirty but needed for binds
			return $pure_null ? "NULL" : "0";
		case "array":
			// set should be like this: ('a,d'), ('d,a'), ('a,d,a'), ('a,d,d'), ('d,a,d')
			return $array_brackets ? "(".implode(",", array_map('_myScBind', $c_bind)).")" : implode(",", array_map('_myScBind', $c_bind));
		case "object":
		{
			if ($c_bind instanceof QIModel)
			{
				// var_dump($c_bind->getId(), QApp::GetStorage()->getTypeIdInStorage(get_class($c_bind)).",".$c_bind->getId());
				// return is_string($param) ? (($s = QApp::GetStorage()) ? $s->escapeString($param) : addslashes($param)) : $param;
				return "'".addslashes(QApp::GetStorage()->getTypeIdInStorage(get_class($c_bind)).",".$c_bind->getId())."'";
			}
			else
				return "'".addslashes($c_bind)."'";
		}
		default:
			throw new Exception("_myScBind :: Can not bind type: ".$bind_ty);
	}
}

/**
 * @todo
 */
function qTranslate($string)
{
	return $string;
}

/**
 * Just like token_get_all, but we consolidate/join T_NS_SEPARATOR with T_STRING
 * For example an expression like: \Namespace1\Class1
 * will be joined in one T_STRING
 * If there are whitespaces, they will be moved after T_STRING (for performance)
 * 
 * @param string $source
 * @return (string|array)[]
 */
function qtoken_get_all($source, &$is_valid = null)
{
	$is_valid = true;
	return token_get_all($source);
	
	$tok = reset($toks);
	$pos = 0;
	
	// string | whitespace | separator
	$consolidate_in = null;
	$consolidate_type = null;
	$last_type = null;
	
	if ($is_valid !== null)
	{
		$brackets = 0;
		$php_tags = 0;

		while ($tok)
		{
			$type = is_array($tok) ? $tok[0] : $tok;

			switch ($type)
			{
				case T_WHITESPACE:
				{
					// break but don't break consolidate
					break;
				}
				case T_STRING:
				case T_NS_SEPARATOR:
				{
					// there is a point to do something - maybe
					if ($consolidate_in !== null)
					{
						if (($type === T_NS_SEPARATOR) || ($last_type !== $tok[0]))
						{
							// ensure type is string
							$toks[$consolidate_in][0] = T_STRING;
							$toks[$consolidate_in][1] .= $tok[1];
							$last_type = $type;
							$toks[$pos][0] = T_WHITESPACE;
							$toks[$pos][1] = "";
						}
						else
						{
							// string after string, we release
							$consolidate_in = null;
						}
					}
					else
					{
						// possible consolidate ahead
						$consolidate_in = $pos;
						$last_type = $consolidate_type = $type;
					}
					break;
				}
				case "{":
				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_STRING_VARNAME:
				{
					$brackets++;
					break;
				}
				case "}":
				{
					$brackets--;
					break;
				}
				case T_OPEN_TAG:
				case T_OPEN_TAG_WITH_ECHO:
				{
					$php_tags++;
					break;
				}
				case T_CLOSE_TAG:
				{
					$php_tags--;
					break;
				}
				default:
				{
					$consolidate_in = null;
					break;
				}
			}

			$tok = next($toks);
			$pos++;
		}
		
		$is_valid = (($brackets === 0) && (($php_tags === 0) || ($php_tags === 1)));
	}
	else
	{
		while ($tok)
		{
			$type = is_array($tok) ? $tok[0] : $tok;

			switch ($type)
			{
				case T_WHITESPACE:
				{
					// break but don't break consolidate
					break;
				}
				case T_STRING:
				case T_NS_SEPARATOR:
				{
					// there is a point to do something - maybe
					if ($consolidate_in !== null)
					{
						if (($type === T_NS_SEPARATOR) || ($last_type !== $tok[0]))
						{
							// ensure type is string
							$toks[$consolidate_in][0] = T_STRING;
							$toks[$consolidate_in][1] .= $tok[1];
							$last_type = $type;
							$toks[$pos][0] = T_WHITESPACE;
							$toks[$pos][1] = "";
						}
						else
						{
							// string after string, we release
							$consolidate_in = null;
						}
					}
					else
					{
						// possible consolidate ahead
						$consolidate_in = $pos;
						$last_type = $consolidate_type = $type;
					}
					break;
				}
				default:
				{
					$consolidate_in = null;
					break;
				}
			}

			$tok = next($toks);
			$pos++;
		}
	}
	
	return $toks;
}

/**
 * Calls for a render given a class name or instance, the name of the render method
 * and optional parameters and optional properties.
 * 
 * @param QWebControl|string $class_or_instance
 * @param string $method
 * @param mixed[] $params
 * @param mixed[] $properties
 * 
 * @return void
 * @throws Exception
 */
function qRender($class_or_instance, $method, $params = null, $properties = null)
{
	$obj = ($class_or_instance instanceof QWebControl) ? $class_or_instance : new $class_or_instance();
	if ($properties)
		$class_or_instance->extractFromArray($properties);
	
	$use_method = method_exists($obj, $method) ? $method : "render".ucfirst($method);
	if (!method_exists($obj, $use_method))
		throw new Exception("Invalid requested method ".(get_class($obj))."::{$method}() or ::{$use_method}()");
		
	return call_user_func_array([$obj, $use_method], $params);
}

/**
 * Gets the output of a render given a class name or instance, the name of the render method
 * and optional parameters and optional properties.
 * 
 * @param QWebControl|string $class_or_instance
 * @param string $method
 * @param mixed[] $params
 * @param mixed[] $properties
 * 
 * @return void
 * @throws Exception
 */
function qGetRender($class_or_instance, $method, $params = null, $properties = null)
{
	ob_start();
	qRender($class_or_instance, $method, $params, $properties);
	return ob_get_clean();
}


/**
 * The objective here is to limit the selector to the max selector and apply security
 * 
 * @param string $_className_
 * @param selector $selector
 * @param selector $_maxselector_
 * 
 * @return selector
 */
function qSecureSelector($selector, $_maxselector_ = null, $_className_ = null)
{
	// if ($_className_) also apply security
	// $data
	
	return $selector;
}

// @todo
function qSecurityCheck($value, $_selector_, $_class_ = null, $_method_ = null)
{
	// argument, or return ?!
	
	return $value;
}

/**
 * Intersects two selectors
 * 
 * @param selector $selector_1
 * @param selector $selector_2
 * 
 * @return selector
 */
function qIntersectSelectors($selector_1, $selector_2)
{
	if (is_string($selector_1))
		$selector_1 = qParseEntity($selector_1);
	if (is_string($selector_2))
		$selector_2 = qParseEntity($selector_2);
	return qIntersectSelectorsRec($selector_1, $selector_2);
}

/**
 * Recursive helper for qIntersectSelectors
 * 
 * @param selector $selector_1
 * @param selector $selector_2
 * 
 * @return selector
 */
function qIntersectSelectorsRec($selector_1, $selector_2)
{
	if ($selector_1 && $selector_2)
	{
		$result = [];
		// $all_1 = $selector_1["*"];
		$all_1 = $selector_1["*"];
		if ($all_1 !== null)
		{
			// we accept all from 2
			foreach ($selector_2 as $k => $v)
				$result[$k] = (($sv = $selector_1[$k]) !== null) ? qIntersectSelectorsRec($v, $sv) : [];
		}
		else if (($all_2 = $selector_2["*"]) !== null)
		{
			// we accept all from 1
			foreach ($selector_1 ?: [] as $k => $v)
				$result[$k] = (($sv = $selector_2[$k]) !== null) ? qIntersectSelectorsRec($v, $sv) : [];
		}
		else 
		{
			// there is no * on eiter side
			foreach ($selector_1 ?: [] as $k => $v)
			{
				if (($sv = $selector_2[$k]) !== null)
					$result[$k] = qIntersectSelectorsRec($v, $sv);
			}
		}
		return $result;
	}
	else
		return [];
}


function qSelectorsDiff($selector_1, $selector_2)
{
	if (is_string($selector_1))
		$selector_1 = qParseEntity($selector_1);
	if (is_string($selector_2))
		$selector_2 = qParseEntity($selector_2);
	return qSelectorsDiffRec($selector_1, $selector_2);
}

function qSelectorsDiffRec($selector_1, $selector_2)
{
	$difference = [];
	if (!empty($selector_1))
	{
		foreach ($selector_1 as $key => $value) 
		{
			if (is_array($value)) 
			{
				if(isset($selector_2[$key]) && is_array($selector_2[$key])) 
				{
					$new_diff = qSelectorsDiffRec($value, $selector_2[$key]);
					if (!empty($new_diff))
						$difference[$key] = $new_diff;
				}
				else
					$difference[$key] = $value;
			}
			else if (!array_key_exists($key, $selector_2) || ($selector_2[$key] !== $value))
				$difference[$key] = $value;
		}
	}
	else if (!empty($selector_2))
		$difference = $selector_2;
		
    return $difference;
}

/**
 * Gets a selector that represent all that $selector_2 has and it's missing in $selector_1
 * 
 * @param type $selector_1
 * @param type $selector_2
 * 
 * @return type
 */
function qSelectorsMissing($selector_1, $selector_2)
{
	if (is_string($selector_1))
		$selector_1 = qParseEntity($selector_1);
	if (is_string($selector_2))
		$selector_2 = qParseEntity($selector_2);
	return qSelectorsMissingRec($selector_1, $selector_2);
}

function qSelectorsMissingRec($selector_1, $selector_2)
{
	if (empty($selector_2))
		return [];
	else if (empty($selector_1))
		return $selector_2;
	else
	{
		$difference = [];
		foreach ($selector_2 as $key => $value)
		{
			$s1_value = $selector_1[$key];
			if ($s1_value === null)
				$difference[$key] = $value;
			else if (($s1_value !== null) && ($value !== null))
			{
				$new_diff = qSelectorsMissingRec($s1_value, $value);
				if (!empty($new_diff))
					$difference[$key] = $new_diff;
			}
		}
		
		return $difference;
	}
}

/**
 * Joins two selectors
 * 
 * @param selector $selector_1
 * @param selector $selector_2
 * 
 * @return selector
 */
function qJoinSelectors($selector_1, $selector_2)
{
	if (is_string($selector_1))
		$selector_1 = qParseEntity($selector_1);
	if (is_string($selector_2))
		$selector_2 = qParseEntity($selector_2);
	
	return qJoinSelectorsRec($selector_1, $selector_2);
}

/**
 * Recursive helper for qJoinSelectors
 * 
 * @param selector $selector_1
 * @param selector $selector_2
 * 
 * @return selector
 */
function qJoinSelectorsRec($selector_1, $selector_2)
{
	if ($selector_1 && $selector_2)
	{
		$result = $selector_1;
		foreach ($selector_2 as $k => $v)
		{
			if (($sv = $selector_1[$k]) !== null)
				$result[$k] = qJoinSelectorsRec($sv, $v);
			else
				$result[$k] = $v;
		}
		return $result;
	}
	else
		return $selector_1 ?: $selector_2;
}

function array_jump(&$arr, $pos)
{
	while ((($k = key($arr)) !== null) && ($k !== $pos))
	{
		next($arr);
	}
	
	return ($k === $pos);
}

function qrelative_path($path, $rel_to)
{
	$parts = preg_split("/(\\/)/us", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	$rel_parts = preg_split("/(\\/)/us", $rel_to, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	
	$ret = "";
	$r = reset($rel_parts);
	$in_sync = true;
	foreach ($parts as $p)
	{
		if (!($in_sync && ($p === $r)))
		{
			$in_sync = false;
			$ret .= $p;
		}
		$r = next($rel_parts);
	}
	return $ret;
}

function qPathForTag($tag = null, $path = null)
{
	return QAutoload::GetPathForTag($tag = null, $path);
}

function qWebPathForTag($tag = null, $path = null)
{
	return QAutoload::GetWebPathForTag($tag, $path);
}
function getDiffCaption($d1, $d2)
{
	$interval = date_diff(new DateTime($d1), new DateTime($d2));
	$ret = "";
	if ($interval->y > 0)
		$ret .= $interval->y . " year" . (($interval->y > 1) ? "s" : "");
	else if ($interval->m > 0)
		$ret .= $interval->m . " month" . (($interval->m > 1) ? "s" : "");
	else if ($interval->d > 0)
		$ret .= $interval->d . " day" . (($interval->d > 1) ? "s" : "");
	else if ($interval->h > 0)
		$ret .= $interval->h . " hour" . (($interval->h > 1) ? "s" : "");
	else if ($interval->i > 0)
		$ret .= $interval->i . " minute" . (($interval->i > 1) ? "s" : "");
	else if ($interval->s > 0)
		$ret .= $interval->s . " second" . (($interval->s > 1) ? "s" : "");
	return $ret. " ago";
}

function qVarExport($data, $export_obj_nulls = false, \SplObjectStorage $refs = null, &$obj_count_index = 1)
{
	$ty = gettype($data);
	switch($ty)
	{
		case "NULL":
		case "string":
		case "integer":
		case "double":
		case "boolean":
			return var_export($data, true);
		case "array":
		{
			if ($refs === null)
				$refs = new \SplObjectStorage();
			$ret = "[";
			foreach ($data as $k => $v)
				$ret .= var_export($k, true) . "=>" . qVarExport($v, $export_obj_nulls, $refs, $obj_count_index).",";
			$ret .= "]\n";
			return $ret;
		}
		case "object":
		{
			if ($refs === null)
				$refs = new \SplObjectStorage();
			else if ($refs->contains($data))
				return "qObjSetState(".var_export(get_class($data), true).", ".var_export($obj_count_index, true).", [], \$refs)";
			else
				$refs->attach($data);
			
			$ret = "qObjSetState(".var_export(get_class($data), true).", ".var_export($obj_count_index, true).", [";
			$obj_count_index++;
			$is_qmodel = ($data instanceof \QIModel);
			foreach ($data as $k => $v)
			{
				if ($is_qmodel && (($k === "_ty") || ($k === "_sc")))
					continue;
				if ((($v !== null) && ($v !== [])) || $export_obj_nulls)
					$ret .= var_export($k, true) . "=>" . qVarExport($v, $export_obj_nulls, $refs, $obj_count_index) . ",";
			}
			$ret .= "], \$refs)\n";
			return $ret;
		}
		default:
			return var_export(null, true);
	}
}

function qObjSetState($class, $tmp_id, $array, &$refs = [])
{
	if ($refs)
		$obj = $refs[$tmp_id];
	if ($obj === null)
	{
		$obj = new $class();
		$refs[$tmp_id] = $obj;
	}
	if ($obj instanceof \QIModelArray)
	{
		foreach ($array as $k => $v)
			$obj[$k] = $v;
	}
	else
	{
		foreach ($array as $k => $v)
			$obj->$k = $v;
	}
	return $obj;
}

/**
 * Executes a query and calls the callback for each element
 * 
 * @param string $collection
 * @param string $query
 * @param mixed[] $binds
 * @param callable $callback
 * @param callable $batch_callback
 * @param QIModel[] $dataBlock 
 * 
 * @return QIModel
 * @throws Exception
 */
function QQueryEach(string $collection, string $query = null, $binds = null, callable $callback = null, callable $batch_callback = null, $limit = null, QIModel $from = null, &$dataBlock = null, $skip_security = true, $filter_selector = null)
{
	if ($limit === null)
		$limit = 1024;
	
	$props = explode(".", $collection);
	$limit_offset = 0;
	
	// $loops = 0;
	
	do
	{
		$called = false;
		
		$new_q = $collection.".{{$query} LIMIT {$limit_offset},{$limit}}";

		$data = QModelQuery::BindQuery($new_q, $binds, $from, $dataBlock, $skip_security, $filter_selector, /* $populate_only = */ false);

		$bag = [$data];
		
		foreach ($props as $_prop)
		{
			$prop = trim($_prop);
			$new_bag = [];
			foreach ($bag as $item)
			{
				if ($item->$prop !== null)
				{
					if (qis_array($item->$prop))
					{
						foreach ($item->$prop as $i)
							$new_bag[] = $i;
					}
					else
						$new_bag[] = $item->$prop;
				}
			}
			$bag = $new_bag;
		}
		
		if ($bag)
		{
			$called = true;
			if ($callback)
			{
				foreach ($bag as $fi)
					// @TODO also add elements on the way
					$callback($fi);
			}
			if ($batch_callback)
				$batch_callback($bag);
		}
		
		$limit_offset += $limit;
		
		unset($data, $bag, $new_bag);
		
		/*$loops++;
		
		var_dump($loops);
		var_dump("Memory Usage: ". (memory_get_usage(true)/1024)." KB | ".(memory_get_usage()/1024)." KB | ".(memory_get_peak_usage(true)/1024)." KB | ".(memory_get_peak_usage()/1024)." KB");
		
		if ((memory_get_usage(true)/1024) > 16384)
		{
			analize_mem_usage();
			// die;
			// while(gc_collect_cycles());
		}*/
	}
	while ($called);
	
	unset($data, $bag, $new_bag);
}

function filePutContentsIfChanged_start()
{
	global $_filePutContentsIfChanged_;
	
	$_filePutContentsIfChanged_ = new stdClass();
	$_filePutContentsIfChanged_->files = [];
}

function filePutContentsIfChanged_commit(bool $roolback = false)
{
	global $_filePutContentsIfChanged_;
	if ($_filePutContentsIfChanged_ === null)
		return;
	
	foreach ($_filePutContentsIfChanged_->files ?: [] as $file_path => $file_m_time)
	{
		if ($roolback)
		{
			# restore the original file
			if (file_exists($file_path."._fpcic_bak"))
				file_put_contents($file_path, file_get_contents($file_path."._fpcic_bak"));
		}
		else if ((filesize($file_path) === filesize($file_path."._fpcic_bak")) && (file_get_contents($file_path) === file_get_contents($file_path."._fpcic_bak")))
		{
			# echo "Restore `{$file_path}` from ".filemtime($file_path)." TO {$file_m_time} <br/>\n";
			touch($file_path, $file_m_time);
		}
		# in all cases release the backup
		unlink($file_path."._fpcic_bak");
	}
	
	$_filePutContentsIfChanged_ = null;
}

function filePutContentsIfChanged_roolback()
{
	filePutContentsIfChanged_commit(true);
}

function filePutContentsIfChanged($filename, $data, $create_dir = false)
{
	global $_filePutContentsIfChanged_;
	
	$data = is_string($data) ? $data : (string)$data;
	if (file_exists($filename) && (filesize($filename) === strlen($data)) && (file_get_contents($filename) === $data))
		// we say that there is no change
		return true;
	else
	{
		if ($create_dir && (!is_dir($dir = dirname($filename))))
			mkdir($dir, (0777 & ~umask()), true);
		
		if (($_filePutContentsIfChanged_ !== null) && file_exists($filename) && (!$_filePutContentsIfChanged_->files[realpath($filename)]))
		{
			file_put_contents($filename."._fpcic_bak", file_get_contents($filename));
			$_filePutContentsIfChanged_->files[realpath($filename)] = filemtime($filename);
		}
		
		return file_put_contents($filename, $data);
	}
}

function full_path_to_web(string $fp = null)
{
	if ($fp === null)
		return null;
	return '/'.ltrim(substr($fp, strlen($_SERVER["DOCUMENT_ROOT"])), '/');
}

function qpreg_get(string $pattern, string $subject, array &$matches = null, int $flags = 0, int $offset = 0)
{
	$matches = null;
	$rc = preg_match($pattern, $subject, $matches, $flags, $offset);
	if ($rc === false)
		return false;
	else if ($rc === 0)
		return 0;
	$c_matches = count($matches);
	if ($c_matches === 1)
		// return general match
		return $matches[0];
	else if ($c_matches === 2)
		// return the first one marked
		return $matches[1];
	else 
		// return all matched and marked
		return array_slice($matches, 1);
}

/*
function qpreg_get_all(string $pattern, string $subject, array &$matches = null, int $flags = 0, int $offset = 0)
{
	$matches = null;
	$rc = preg_match_all($pattern, $subject, $matches, $flags, $offset);
	if ($rc === false)
		return false;
	
	var_dump($matches);
}
*/

function _trace(string $uid, array $config = null, \Closure $closure = null, $closure_context = null)
{
	return (new \QTrace())->trace($uid, $config, $closure, $closure_context);
}

function _trace_s(string $static_class_name, string $uid, array $config = null, \Closure $closure = null)
{
	return (new \QTrace())->trace($uid, $config, $closure, $static_class_name);
}

function q_get_lang()
{
	global $_T___INF, $_T___INF_LANG;
	if ($_T___INF === null)
		_T('test', 'test');
	return $_T___INF_LANG ?: null;
}

/**
 * TRANSLATE
 * 
 * @param type $uid
 * @param type $defaultText
 * @return type
 */
function _T($uid, $defaultText)
{
	global $_T___INF, $_T___INF_LANG, $_T___INF_DATA;
	if ($_T___INF === null)
	{
		// init
		$_T___INF = [];
		$c_user = class_exists('Omi\User') ? \Omi\User::GetCurrentUser() : null;
		if ($c_user && property_exists($c_user, 'UI_Language'))
		{
			if (!$c_user->wasSet('UI_Language'))
				$c_user->populate('UI_Language.Code');
			$ui_lang = $c_user->getUI_Language();
			if ($ui_lang && (!$ui_lang->wasSet('Code')))
				$ui_lang->populate('Code');
			$current_language = $ui_lang ? $ui_lang->getCode() : null;
			$_T___INF_LANG = $current_language ?: null;

			if ($_T___INF_LANG && file_exists("lang/{$_T___INF_LANG}.php"))
			{
				$_DATA__ = null;
				include("lang/{$_T___INF_LANG}.php");
				$_T___INF_DATA[$_T___INF_LANG] = $_DATA__;
			}
		}
		
		if ((!$_T___INF_LANG) && defined('Q_DEFAULT_USER_LANGUAGE') && Q_DEFAULT_USER_LANGUAGE && file_exists("lang/".Q_DEFAULT_USER_LANGUAGE.".php"))
		{
			# Q_DEFAULT_USER_LANGUAGE
			$_T___INF_LANG = Q_DEFAULT_USER_LANGUAGE;
			$_DATA__ = null;
			include("lang/{$_T___INF_LANG}.php");
			$_T___INF_DATA[$_T___INF_LANG] = $_DATA__;
		}
	}
	// UI_Language
	// $c_user = \Omi\User::GetCurrentUser();
	// qvar_dumpk($c_user);
	
	if ($_T___INF_LANG && $_T___INF_DATA)
		$ret_text = (($txt = $_T___INF_DATA[$_T___INF_LANG][$uid]) !== null) ? $txt : 
					((($s_txt = $_T___INF_DATA[$_T___INF_LANG][$defaultText]) !== null) ? $s_txt : $defaultText);
	else
		$ret_text = $defaultText;
	if (false && \QAutoload::GetDevelopmentMode()) # || ($_SERVER['REMOTE_ADDR'] === '176.24.78.34'))
	{
		# get the trace no matter what
		$dbg = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$last_trace = null;
		$called_in = null;
		foreach ($dbg as $trace)
		{
			if (($trace['function'] !== '_L') && ($trace['function'] !== '_T'))
			{
				# we stop
				$called_in = $trace;
				break;
			}
			$last_trace = $trace;
		}
		$lang_method = $last_trace['function'];
		$lang_line = $last_trace['line'];
		$lang_called_in_method = $called_in['function'];
		$lang_called_in_class = $called_in['class'];
		if ($lang_method === '_T')
		{
			$dbg_str = "uid:{$uid}";
		}
		else if ($lang_method === '_L')
		{
			# $dbg_str = "_L(\"".addslashes($defaultText)."\")\n<br/>{$lang_called_in_class}::{$lang_called_in_method} #{$lang_line}";
			$dbg_str = "_L(\"{$defaultText}\")";
		}
		# qvar_dumpk($lang_method, $lang_line, $lang_called_in_method, $lang_called_in_class, ($uid !== $defaultText) ? $uid : null, $defaultText);
		
		# $ret_text = "<span class='q_dbg_lang'>{$ret_text}<div>{$dbg_str}</div></span>";
		$ret_text = "{$ret_text} | {$dbg_str}";
	}
	return $ret_text;
}

function _L($tag, $lang = null, $arg_1 = null, $arg_2 = null, $arg_3 = null, $arg_4 = null, $arg_5 = null, $arg_6 = null, $arg_7 = null, $arg_8 = null)
{
	$dt = QLanguage::$Data[$tag];
	if ($dt === null)
		return _T($tag, $tag);
	if ($lang === null)
		$lang = QModel::GetLanguage_Dim();
	$data = $dt[$lang];
	if ($data === null)
		$lang = QModel::GetDefaultLanguage_Dim();
	$data = $dt[$lang];
	if ($data === null)
		return $tag;
	if ($data instanceof Closure)
		return $data($arg_1, $arg_2, $arg_3, $arg_4, $arg_5, $arg_6, $arg_7, $arg_8);
	else
		return $data;
}

function q_is_set_for_removal($item, $array = null)
{
	if (
		(($item instanceof \QIModel) && ($item->_ts == \QModel::TransformDelete)) 
		)
		return true;
	
	return false;
}

function q_find($what, $where)
{
	# q_find(['Service' => 'whatever', 'Type' => 'xxx', 'GroupName' => '???'], $elements[$k]->AssignedServices)
	
	return q_find_in_multiple($what, [$where]);
}

function q_find_in_multiple($what, $where)
{
	$find_key_value = null;
	$find_callback = null;
	$find_scalar = null;
	
	if (is_array($what))
	{
		# key => value condition
		$find_key_value = $what;
		/*
		foreach ($what as $k => $v)
		{
			$parts = explode(".", $k);
			$ref = &$find_key_value;
			foreach ($parts as $p)
			{
				$ref[$p] = [];
				$ref = &$ref[$p];
			}
			$ref = $v;
			unset($ref);
		}
		$find_key_value = $what;
		*/
	}
	else if (is_callable($what))
	{
		# callback condition
		$find_callback = $what;
		throw new \Exception('@TODO');
	}
	else if (is_scalar($what))
	{
		# just find one value
		$find_scalar = $what;
		throw new \Exception('@TODO');
	}
	
	$ret_vals = [];
	
	/*
	if ($find_key_value !== null)
	{
		$keys = [$find_key_value];
		$objs = [];
		foreach ($where as $o)
			$objs[] = [$o, $o];
		
		while ($keys && $objs)
		{
			$next_keys = [];
			$next_objs = [];
			
			foreach ($keys as $keys_cond)
			{
				foreach ($keys_cond as $k => $v)
				{
					if (is_scalar($v))
					{
						foreach ($objs as $o_data)
						{
							list($o, $ret_o) = $o_data;
							$o_val = is_array($o) ? $o[$k] : (is_object($o) ? $o->$k : null);
							# int,float,bool,string ("1" ? 1) ("1" ? true)
							$obj_found = ($o_val === $v) ? true : (
											(is_bool($v) || is_bool($o_val)) ? ((bool)$v === (bool)$o_val) : (
											(is_numeric($v) || is_numeric($o_val)) ? ((float)$v === (float)$o_val) : 
											((string)$v === (string)$o_val)));
							if ($obj_found)
								$ret_vals[] = $ret_o;
						}
					}
					else
					{
						$next_keys[] = $v;
						foreach ($objs as $o_data)
						{
							list($o, $ret_o) = $o_data;
							$next_o = is_array($o) ? $o[$k] : (is_object($o) ? $o->$k : null);
							if ($next_o)
								$next_objs[] = [$next_o, $ret_o];
						}
					}
				}
			}
			
			$keys = $next_keys;
			$objs = $next_objs;
		}
	}
	*/
	
	if ($find_key_value !== null)
	{	
		$objs = [];
		if (count($where) === 1)
			$objs = reset($where);
		else
		{
			foreach ($where as $o_list)
				foreach ($o_list as $o)
					$objs[] = $o;
		}
		
		foreach ($objs as $o_k => $o)
		{
			$cond_ok = true;
			foreach ($find_key_value as $k => $v)
			{
				$o_val = is_array($o) ? $o[$k] : (is_object($o) ? $o->$k : null);
				
				$cond_ok = ($o_val === $v) || 
							((is_bool($v) || is_bool($o_val)) && ((bool)$v === (bool)$o_val)) || 
							((is_numeric($v) || is_numeric($o_val)) && ((float)$v === (float)$o_val)) ||
							((string)$v === (string)$o_val);
				
				if (!$cond_ok)
					break;
			}
			if ($cond_ok)
				$ret_vals[$o_k] = $o;
		}
	}
	
	return $ret_vals;
}

function qformat_number($number, $locale = null)
{
	global $_q_base_num_format;

	$locale = $locale ?: (defined("Q_PRJ_LOCALE_CODE")? Q_PRJ_LOCALE_CODE : false);

	if (!$locale)
		return $number;
	
	$number = floatval(preg_replace("/[^-0-9\.]/","",$number));
	
	$fmt = $_q_base_num_format ?: ($_q_base_num_format = new NumberFormatter($locale, NumberFormatter::CURRENCY ));
	numfmt_set_symbol($fmt, NumberFormatter::CURRENCY_SYMBOL, "");
	
	return $fmt->format($number);
}

function q_merge_conf_data(array &$__CONF, string $attr, array $selector_value)
{
	foreach ($selector_value ?: [] as $key => $value)
	{
		$parts = explode(".", $key);
		$data = &$__CONF;
		foreach ($parts ?: [] as $p)
		{
			if (!isset($data[$p]))
				$data[$p] = [];
			$data = &$data[$p];
		}
		$data[$attr] = $value;
	}
}
