<?php

/**
 * @todo Expand the concept of an application module
 */
class QAppModule implements QIUrlController
{
	/**
	 * The parent. If it's under a QApp it will be the name of the QApp class or null
	 *
	 * @var QAppModule|string
	 */
	protected $parent;
	/**
	 * Child elements
	 *
	 * @var (QAppModule|QWebPage)[]
	 */
	protected $children = array();
	/**
	 * Child elements indexed by URL
	 *
	 * @var (QAppModule|QWebPage)[]
	 */
	protected $childrenByUrl;
	/**
	 * Gets a URL for the specified tag
	 * 
	 * @param string $tag
	 * 
	 * @return string
	 */
	public function getUrlForTag($tag)
	{
		if (isset($this) && $this->children)
		{
			$args = func_get_args();
			foreach ($this->children as $child)
			{
				if (($r = call_user_func_array(array($child, "getUrlForTag"), $args)) != null)
					return $r;
			}
		}
	}
	/**
	 * Does execution logic based on a URL.
	 * On the first call the instance can be null. Later the code decides.
	 * 
	 * @param QUrl $url
	 * @param QIUrlController|string $instance
	 * @param QIUrlController|string $parent
	 */
	public function loadFromUrl(QUrl $url, $parent = null)
	{
		if (isset($this))
		{
			$part = $url->current() ?: 0;
			
			if ($this->childrenByUrl && $this->childrenByUrl[$part])
			{
				$ch = $this->childrenByUrl[$part];
				$child = new $ch["class"]();
				$url->next();
				$child->loadFromUrl($url, $this);
			}
		}
		else
			throw new Exception("Unexpected atm");
	}
	
	/**
	 * Inits the controller based on 
	 * 
	 * @param QUrl $url
	 * @param QIUrlController|string $parent
	 */
	public function initController(QUrl $url = null, $parent = null)
	{
		
	}
}
