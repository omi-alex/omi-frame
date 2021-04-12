<?php

trait QWebPage_Methods
{
	
	/**
	 * The init
	 * 
	 * @param boolean $recursive
	 */
	public function init($recursive = true)
	{
		if ($this->_ini)
			return true;
		$this->_ini = true;
		
		self::$IncludeJs[] = QAutoload::GetTempWebPath("model_type.js");
		$this->includeJsClass("QModel");
		$this->includeJsClass("QModelArray");
		
		parent::init($recursive);
	}
	
	/**
	 * Gets the full id of this web page
	 * 
	 * @return string
	 */
	public function getFullId()
	{
		return get_class($this);
	}

	/**
	 * Return itself
	 * 
	 * @return \QWebPage
	 */
	public function getWebPage()
	{
		return $this;
	}
	
	/**
	 * Gets the browser's Html version
	 * 
	 * @return integer
	 */
	public static function BrowserHtmlVersion()
	{
		$matches = null;
		$res = preg_match("/(?:^|\\s)Mozilla\\/([0-9]+)\\.[0.9]+/us", filter_input(INPUT_SERVER, "HTTP_USER_AGENT"), $matches);
		if ($res)
			return (int)$matches[1];
		else
			return null;
	}
	
	public function renderBody()
	{
		
	}
}
