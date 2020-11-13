<?php

/**
 * The basic class for views
 */
class QViewBase extends QModel 
{
	use QViewBase_GenTrait;
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeJs = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeCss = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeJsLast = array();
	/**
	 *
	 * @var string[]
	 */
	public static $IncludeCssLast = array();
	/**
	 * The list of JS classes that map to PHP view classes
	 *
	 * @var string[]
	 */
	protected static $ViewJsClasses;
	/**
	 * The parent view
	 *
	 * @var QViewBase
	 */
	public $parent;
	/**
	 * The list of child views
	 *
	 * @var QViewBase[]
	 */
	public $children;
	/**
	 * The default tag name of a QViewBase object
	 *
	 * @var string
	 */
	public $tagName = "div";
	/**
	 * The name of the control as it is in it's parent
	 *
	 * @var string
	 */
	public $name;
	/**
	 *
	 * The js ctrl to load
	 * 
	 * @var string 
	 */
	public $jsCtrl;
	/**
	 * @var boolean
	 */
	public $is_dynamic_ctrl;
	/**
	 * Only used if it's a dynamic
	 * @var string
	 */
	public $dynamic_name;

	public $_rf_args = [];
	/**
	 * Constructor
	 * 
	 * @param string $name
	 */
	public function __construct($name = null)
	{
		$this->name = $name;
	}
	
	/**
	 * Calls for the init
	 * 
	 * @param boolean $recursive
	 */
	public function init($recursive = true)
	{
		if ($recursive && $this->children)
		{
			foreach ($this->children as $child)
				$child->init($recursive);
		}
		$this->_ini = true;
	}
	
	/**
	 * Adds a CSS file to resources to be sent on this request
	 * 
	 * @param string $url
	 */
	public function addCss($url)
	{
		self::$IncludeCss[$url] = $url;
	}
	
	/**
	 * Adds a JS file to resources to be sent on this request
	 * 
	 * @param string $url
	 */
	public function addCssLast($url)
	{
		self::$IncludeCssLast[$url] = $url;
	}
	
	/**
	 * Adds a JS file to resources to be sent on this request
	 * 
	 * @param string $url
	 */
	public function addJs($url)
	{
		if (($url{0} === '/') && ($dr = $_SERVER['DOCUMENT_ROOT']) && ($dr_len = strlen($_SERVER['DOCUMENT_ROOT'])) && (substr($url, 0, $dr_len) === $dr))
		{
			// we need it relative
			$url = substr($url, $dr_len - 1);
		}
		self::$IncludeJs[$url] = $url;
	}
	
	/**
	 * Adds a JS file to resources to be sent on this request
	 * 
	 * @param string $url
	 */
	public function addJsLast($url)
	{
		self::$IncludeJsLast[$url] = $url;
	}

	/**
	 * Gets the JS class name for this instance
	 * 
	 * @return string
	 */
	public function getJsClassName()
	{
		return QAutoload::GetJSClassFor(get_class($this));
	}

	/**
	 * The default render 
	 */
	public function render()
	{
		$this->includeJsClass();
		if (\QWebRequest::IsAjaxCallback())
		{
			$this->renderAjaxStart();
			if (!$this->changed)
				return;
		}
		if (\QAutoload::$DebugPanel)
			$this->renderDebugInfo(__FILE__, "");
		
		$jsCtrlToLoad = $this->jsCtrl ?: ($this->is_dynamic_ctrl ? get_parent_class($this) : get_class($this));
		
		// @todo this is a bit ugly, to be changed
		
		?><<?= $this->tagName ?> qCtrl="<?= $this->name."(".$jsCtrlToLoad.")"?>" class="QWebControl<?= $this->isOmiControl ? " omi-control" : "" ?>"<?php
		
		if ($this->isOmiControl)
			?> q-ctrl="<?= $jsCtrlToLoad ?>"<?php
		
		if ($this->is_dynamic_ctrl)
			?> q-dyn-parent="<?= get_class($this->parent) ?>" q-dyn-inst="<?= $this->dynamic_name ?>"<?php

		// close
		?>><?php
		$this->renderInner();
		?></<?= $this->tagName ?>><?php
		
		if (\QWebRequest::IsAjaxCallback())
			$this->renderAjaxEnd();
	}

	/**
	 * Calls for the render of the view
	 * 
	 * @param mixed $data
	 * @param QBacktrace $backtrace
	 */
	public function renderInner($data = null, QBacktrace $backtrace = null)
	{
		if ($this->children)
		{
			$next_bk = $backtrace ? new QBacktrace($backtrace, array($this, $data)) : null;
			foreach ($this->children as $child)
				$child->render($data, $next_bk);
		}
	}
	
	/**
	 * Gets the full id of this control
	 * 
	 * @return string
	 */
	public function getFullId()
	{
		if ($this->_ctrlfid)
			return $this->_ctrlfid;
		else
		{
			$fid = $this->name;
			$p = $this->parent;
			while ($p)
			{
				if ($p instanceof QWebPage)
					break;
				$fid = $p->name . "-" . $fid;
				$p = $p->parent;
			}
			return ($this->_ctrlfid = $fid);
		}
	}
	
	/**
	 * Gets the parent web page
	 * 
	 * @return QWebPage
	 */
	public function getWebPage()
	{
		if (!$this->parent)
			throw new Exception("missing parent");
		return $this->_wp ?: ($this->parent ? ($this->_wp = $this->parent->getWebPage()) : null);
	}
	
	/**
	 * Sets the parent element for this instance
	 * 
	 * @param QViewBase $parent
	 */
	public function setParent(QViewBase $parent)
	{
		$this->parent = $parent;
	}
	
	/**
	 * Includes the JS & CSS resources for the specified PHP class (must be a web element), 
	 * if none is specified it uses the class of this instance.
	 * For optimization both JS & CSS are included on the same call.
	 * 
	 * @param string $class
	 */
	public function includeJsClass($class = null)
	{
		self::IncludeResourcesForClass($class ?: get_class($this));
	}
	
	public static function IncludeResourcesForClass_new(array $resources = null)
	{
		if ($resources === null)
			$resources = static::_get_Tpl_Compiled_Res();
		
		foreach ($resources ?: [] as $r_type => $r_list)
		{
			foreach ($r_list ?: [] as $res_data)
			{
				$web_path = \QApp::GetWebPath($res_data['layer_path'].$res_data['file']);
				if ($r_type === 'js')
					self::$IncludeJs[$web_path] = $web_path;
				else if ($r_type === 'css')
					self::$IncludeCss[$web_path] = $web_path;
			}
		}
	}
	
	public static function IncludeResourcesForClass($class = null)
	{
		$class = $class ?: get_called_class();
		$data_js = array();
		$data_css = array();

		//$dev_mode = QAutoload::GetDevelopmentMode();
		while ($class && (self::$IncludeJs[$class] === null))
		{
			$js_paths = QAutoload::GetJsClassPath($class);
			$css_paths = QAutoload::GetCssClassPath($class);
			
			// self::$IncludeJs[$class] = $js_path ? QApp::GetWebPath($js_path) : "";
			/*if ($js_path && (!$dev_mode) && file_exists(substr($js_path,0, -2)."min.js"))
				$js_path = substr($js_path, 0, -2)."min.js";
			if ($css_path && (!$dev_mode) && file_exists(substr($css_path, 0, -3)."min.css"))
				$css_path = substr($css_path, 0, -3)."min.css";*/

			if (is_scalar($js_paths))
				$js_paths = [$js_paths => $js_paths];
			
			foreach ($js_paths ?: [] as $js_path)
			{
				if (empty($js_path))
					continue;
				$js_web_path = $js_path ? QApp::GetWebPath($js_path) : "";
				if (!empty($js_web_path))
					$data_js[$class][$js_web_path] = $js_web_path;
				if (file_exists(substr($js_path, 0, -3).".gen.js"))
				{
					$js_web_path_gen = $js_path ? QApp::GetWebPath(substr($js_path, 0, -3).".gen.js") : "";
					if (!empty($js_web_path_gen))
						$data_js[$class][$js_web_path_gen] = $js_web_path_gen;
				}
			}
			
			if (is_scalar($css_paths))
				$css_paths = [$css_paths];

			foreach ($css_paths ?: [] as $css_path)
			{
				if (empty($css_path))
					continue;
				$css_web_path = $css_path ? QApp::GetWebPath($css_path) : "";
				if (!empty($css_web_path))
					$data_css[$class][$css_web_path] = $css_web_path;
				if (file_exists(substr($css_path, 0, -4).".gen.css"))
				{
					$css_web_path_gen = $css_path ? QApp::GetWebPath(substr($css_path, 0, -4).".gen.css") : "";
					if (!empty($css_web_path_gen))
						$data_css[$class][$css_web_path_gen] = $css_web_path_gen;
				}
			}

			$class = get_parent_class($class);
		}
		
		foreach (array_reverse($data_js) as $f_class => $paths)
		{
			foreach ($paths as $path)
				self::$IncludeJs[$f_class][$path] = $path;
		}
		foreach (array_reverse($data_css) as $f_class => $paths)
		{
			foreach ($paths as $path)
				self::$IncludeCss[$f_class][$path] = $path;
		}
		# self::$IncludeJs += array_reverse($data_js);
		# self::$IncludeCss += array_reverse($data_css);
	}
	
	public static function GetResourcesForClass($class = null)
	{
		throw new \Exception('GetResourcesForClass used ?!');
		
		$class = $class ?: get_called_class();
		$data = array();
		$data_css = array();
		
		while ($class)
		{
			$js_paths = QAutoload::GetJsClassPath($class);
			$css_paths = QAutoload::GetCssClassPath($class);
			
			if (is_scalar($js_paths))
				$js_paths = [$js_paths];
			
			foreach ($js_paths ?: [] as $js_path)
			{
				$js_web_path = $js_path ? QApp::GetWebPath($js_path) : "";
				if (!empty($js_web_path))
					$data[$class] = $js_web_path;
			}

			if (is_scalar($css_paths))
				$css_paths = [$css_paths];

			foreach ($css_paths ?: [] as $css_path)
			{
				$css_web_path = $css_path ? QApp::GetWebPath($css_path) : "";
				if (!empty($css_web_path))
				{
					$data_css[$class] = $css_web_path;
				}
			}
			$class = get_parent_class($class);
		}
		
		return [$data, $data_css];
	}
	
	/**
	 * Renders the debug log
	 * 
	 * @param string $fp
	 * @param string $debug_log
	 */
	public function renderDebugInfo($fp, $debug_log)
	{
		?><!-- fp: <?= $fp ?><?= $debug_log ? "\n\t\t\tlog: ".trim($debug_log) : "" ?> --><?php
	}

	/**
	 * Gets the web path relative to the path of this class
	 * 
	 * @param string $rel_path
	 * @return string
	 */
	public function webPath($rel_path = "", $rel_to = null)
	{
		$classDir = $this->__classDir ?: ($this->__classDir = ($rel_to ?: dirname(QAutoload::GetClassFileName(get_class($this)))."/"));
		$fp = realpath($classDir.$rel_path);
		
		if ($fp)
			return QApp::GetWebPath($fp);
		else
			throw new Exception("Path not found: {$rel_path}");
	}
	
	/**
	 * Gets the web path relative to the path of this class
	 * 
	 * @param string $rel_path
	 * @return string
	 */
	public function modPath($rel_path = "", $rel_to = null)
	{
		$modDir = $this->__modDir ?: ($this->__modDir = QAutoload::GetModulePathForPath($rel_to ?: QAutoload::GetClassFileName(get_class($this))));
		$fp = realpath($modDir.$rel_path);
		
		if ($fp)
			return QApp::GetWebPath($fp);
		else
			throw new Exception("Path not found: {$rel_path}");
	}
	
	/**
	 * Sets the arguments that are later used by render methods
	 * @param array $args
	 * @param string $method
	 */
	public function setArguments($args, $method = "render")
	{
		$this->_rf_args[$method] = $args;
	}
	
	/**
	 * Gets the arguments that are later used by render methods
	 * @param string $method
	 * 
	 * @return array
	 */
	public function getArguments($method = "render")
	{
		return $this->_rf_args ? $this->_rf_args[$method] : null;
	}
	
	/**
	 * Unsets the arguments that are later used by render methods
	 * @param string $method
	 */
	public function unsetArguments($method = "render")
	{
		if ($this->_rf_args)
			unset($this->_rf_args[$method]);
	}
	
	
	public static function RenderS_Ctrl(string $dynamic_name = null, $parent = null, string $method = "", array $context_vars = null)
	{
		$cc = get_called_class();
		$do_init = false;
		
		static::IncludeResourcesForClass($cc);
		
		$dyn_meth_name = "GetDynamic_Ctrl_{$dynamic_name}";
		$ctrl = $parent::$dyn_meth_name();
		if ($parent)
		{
			$parent->addControl($ctrl);
			// if parent was already init
			if ($parent->_ini)
			{
				$do_init = true;
				// $ctrl->init();
			}
		}
		else
		{
			$do_init = true;
			// $ctrl->init();
		}

		if ($context_vars !== null)
			$ctrl->_ctx_vars = $context_vars;

		$ctrl->_rf_method = $method;

		if ($parent)
			$ctrl->parent = $parent;

		$ctrl->is_dynamic_ctrl = true;
		if ($parent)
			$ctrl->parent = $parent;
		if ($dynamic_name)
			$ctrl->dynamic_name = $dynamic_name;

		if ($do_init)
			$ctrl->init(true);

		return call_user_func_array([$ctrl, "render{$method}"], $ctrl->_render_args ?: []);
	}
	
	/**
	 * Static render call
	 * 
	 * @param string $method "render" will be prepended to the method
	 * @param array $args
	 */
	public static function RenderS($parent = null, $method = "")
	{
		$cc = get_called_class();
		if ($parent && ($cc === get_class($parent)))
		{
			$ctrl = $parent;
		}
		else
		{
			static::IncludeResourcesForClass($cc);
			$ctrl = new $cc();
			if ($parent)
			{
				$parent->addControl($ctrl);
				// if parent was already init
				if ($parent->_ini)
					$ctrl->init();
			}
			else
				$ctrl->init();
		}
		
		return call_user_func_array([$ctrl, "render{$method}"], array_slice(func_get_args(), 2));
	}
	
	public static function Nl2BrIfNotHtml($content)
	{
		if (preg_match("/\\<\\/\\w+\\s*\\>/ius", $content))
			return $content;
		else
			return nl2br($content);
	}

	public static function GetPreventCacheResourceSource($url)
	{
		if (strpos($url, "?d=") !== false)
			return $url;

		return $url . "?d=" . microtime(true);
	}

	public static function AddJsLastResources()
	{
		foreach (self::$IncludeJsLast ?: [] as $k => $v)
		{
			if (isset(self::$IncludeJs[$k]))
				continue;
			self::$IncludeJs[$k][$v] = $v;
		}
	}
	
	public static function AddCssLastResources()
	{		
		foreach (self::$IncludeCssLast ?: [] as $k => $v)
		{
			if (isset(self::$IncludeCss[$k]))
				continue;

			self::$IncludeCss[$k][$v] = $v;
		}	
	}
	
	public function getQCtrl_Attr()
	{
		return $this->is_dynamic_ctrl ? $this->dynamic_name."(".get_parent_class($this).")" : $this->name."(".get_class($this).")";
	}
	
	/**
	 * @api.enable
	 * 
	 * @param string $dynamic_name
	 * @param array $args
	 */
	public static function ApiDynamic_Ctrl(string $dynamic_name, string $method, array $args = null)
	{
		if ($args === null)
			$args = [];
		
		$dyn_meth_name = 'GetDynamic_Ctrl_' . $dynamic_name;
		$ctrl = static::$dyn_meth_name();
		
		$class = get_parent_class($ctrl);
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

		if ($m_type_meth->static)
		{
			return $ctrl::$method(...$args);
		}
		else
		{
			if (!$ctrl->_qini)
				$ctrl->init(true);
			return $ctrl->$method(...$args);
		}
	}
	
	protected function _get_Tpl_Compiled_Res()
	{
		return [];
	}
}
