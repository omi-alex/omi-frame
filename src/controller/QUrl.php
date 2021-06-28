<?php

/**
 * When a request is processed by QWebRequest, a QUrl instance is created in QUrl::$Requested
 * QUrl splits the request url into an array.
 * As controllers are processing the request they consume the parts of the url. They also have the option to go back.
 * 
 */
class QUrl
{
	/**
	 * The QUrl object that associted to the current request.
	 * 
	 * @var QUrl
	 */
	public static $Requested;
	/**
	 * The Full URL
	 *
	 * @var strig
	 */
	public $url;
	/**
	 * The parts of the URL
	 *
	 * @var string[]
	 */
	protected $parts = array();
	/**
	 * The parts that were consumed
	 *
	 * @var boolean[]
	 */
	public $consumed;
	/**
	 * Number of parts
	 *
	 * @var integer
	 */
	public $count;
	/**
	 * The extension
	 *
	 * @var string
	 */
	public $extension;

	/**
	 * The constructor for the QUrl object
	 * 
	 * @param string $url
	 * @param boolean $init
	 */
	public function __construct($url = "", $init = true)
	{
		$this->url = $url;
		$this->count = 0;
		if ($init)
			$this->init($url);
	}
	
	/**
	 * Inits the URL object from a string
	 * 
	 * @param string $url
	 */
	public function init($url)
	{
		$parts = explode("/", $url);
		$this->parts = array();
		$this->consumed = array();
		$last = null;
		foreach ($parts as $p)
		{
			// looks like there is no need to decode
			//$this->parts[] = $last = urldecode($p);
			$this->parts[] = $last = str_replace("%2f", "/", $p);

			$this->consumed[] = false;
			$this->count++;
		}
		if ($last && (($ep = strrpos($last, ".")) !== false))
			$this->extension = substr($last, $ep + 1);
	}
	
	public static function Get_Current_Parts()
	{
		return static::$Requested ? static::$Requested->parts : null;
	}
	
	/**
	 * Gets the current url part
	 * 
	 * @return string
	 */
	public function current()
	{
		return current($this->parts);
	}
	
	/**
	 * The current position
	 * 
	 * @return integer
	 */
	public function key()
	{
		return key($this->parts);
	}
	
	/**
	 * Resets the pointer in the list and returns the first element.
	 * 
	 * @return string
	 */
	public function reset()
	{
		return reset($this->parts);
	}
	
	/**
	 * Goes to the next postion and returns it.
	 * 
	 * @return string
	 */
	public function next()
	{
		return next($this->parts);
	}
	
	/**
	 * Goes to the prev postion and returns it.
	 * 
	 * @return string
	 */
	public function prev()
	{
		return prev($this->parts);
	}
	
	/**
	 * Goes to the last postion and returns it.
	 * 
	 * @return string
	 */
	public function end()
	{
		return end($this->parts);
	}

	public function onLastPos()
	{
		return (($this->key() + 1) === count($this->parts));
	}
	
	/**
	 * Prepends a string to the url chunnks.
	 * 
	 * @return string
	 */
	public function prepend($part)
	{
		$this->url = ($this->url === null) ? $part : ($part."/".$this->url);
		if ($this->parts === null)
			$this->parts = array($part);
		else
			array_unshift($this->parts, $part);
		return $this;
	}

	/**
	 * Appends a string to the url chunnks.
	 * 
	 * @return string
	 */
	public function append($part)
	{
		if ($this->url && (substr($this->url, -1) === "/"))
			$this->url = substr($this->url, 0, -1);

		$this->url = ($this->url === null) ? $part : ($this->url."/".$part);
		if ($this->parts === null)
			$this->parts = array($part);
		else
			array_push($this->parts, $part);
		return $this;
	}
	
	/**
	 * Tests if the current QUrl is a index request
	 * Only returns true for the root and not for subdirectories.
	 * 
	 * @return boolean
	 */
	public function isIndex()
	{
		// var_dump($this, key($this->parts), $this->current(), $this->_isIndex);
		if ($this->_isIndex)
			return true;

		switch ($this->current())
		{
			case "":
			case "index.php":
			case "index.html":
			case "index.htm":
				return true;
			default:
			{
				// see how the URL is structured
				// protocol://SERVER/path
				
				/*
				if (($pp = strpos($this->url, "://")) !== false)
				{
					// protocol included
					
				}
				else
				{
					// no protocol
					
				}
				 */
				
				return false;
			}
		}
	}
	
	/**
	 * If edit the QUrl we may need to reset that is index.
	 * 
	 * @param boolean $value
	 */
	public function setIsIndex($value = true)
	{
		$this->_isIndex = $value;
	}
	
	/**
	 * Just like array_splice, but acts on $this->parts
	 * 
	 * @param integer $offset
	 * @param integer $length
	 * @param string[] $replacement
	 */
	public function splice($offset, $length = 0, $replacement = null)
	{
		$save_pos = key($this->parts);
		array_splice($this->parts, $offset, $length, $replacement);
		array_jump($this->parts, $save_pos);
		$this->url = implode("/", $this->parts);
	}
	
	/**
	 * Gets part of the URL as string from the current position onward
	 * 
	 * @return string
	 */
	public function getFromCurrentAsString()
	{
		$key = key($this->parts);
		if ($key === null)
			return "";
		$save_arr = $this->parts;
		// looks like array_slice() is reseting the pointer in the array we need to make sure it's modified
		$save_arr[] = "";
		array_pop($save_arr);
		return implode("/", array_slice($save_arr, $key));
	}
	
	/**
	 * Gets part of the URL as string from the current position onward
	 * 
	 * @return string
	 */
	public function getConsumedAsString()
	{
		$save_arr = $this->parts;
		// looks like array_slice() is reseting the pointer in the array we need to make sure it's modified
		$save_arr[] = "";
		array_pop($save_arr);
		$key = key($this->parts);
		return implode("/", array_slice($save_arr, 0, $key));
	}


	/**
	 * Gets the current object instance in it's string representation
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->url ?: "";
	}
	
	public static function WithQueryString($params = null)
	{
		if (!($url = static::$Requested))
			return null;
		if ($params)
		{
			if ($_GET)
			{
				$arr = $_GET;
				foreach ($params as $k => $v)
				{
					if ($v === null)
						unset($arr[$k]);
					else
						$arr[$k] = $v;
				}
				$params = $arr;
			}
			$qs = http_build_query($params);
		}
		else
			$qs = $_SERVER['QUERY_STRING'];
		
		return implode($url->parts, "/").($qs ? "?" : "").$qs;
	}
}

