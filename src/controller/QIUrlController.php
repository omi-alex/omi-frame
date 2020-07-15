<?php

/**
 * Url controllers must implement this interface
 */
interface QIUrlController 
{
	/**
	 * Gets a URL for the specified tag
	 * 
	 * @param string $tag
	 * 
	 * @return string
	 */
	public function getUrlForTag($tag);
	/**
	 * Does execution logic based on a URL.
	 * On the first call the instance can be null. Later the code decides.
	 * 
	 * @param QUrl $url
	 * @param QIUrlController|string $parent
	 */
	public function loadFromUrl(QUrl $url, $parent = null);
	/**
	 * Inits the controller based on 
	 * 
	 * @param QUrl $url
	 * @param QIUrlController|string $parent
	 */
	public function initController(QUrl $url = null, $parent = null);
}
