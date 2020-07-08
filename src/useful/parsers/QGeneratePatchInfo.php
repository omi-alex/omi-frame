<?php

class QGeneratePatchInfo
{
	/**
	 * The class name
	 *
	 * @var string
	 */
	public $class_name;
	/**
	 * The type of the file
	 *
	 * @var string
	 */
	public $type;
	/**
	 * True if it's a patch
	 *
	 * @var boolean
	 */
	public $is_patch = false;
	/**
	 * The original path
	 * 
	 * @var string
	 */
	public $path;
	/**
	 * The original path
	 * 
	 * @var string
	 */
	public $file_name;
	
	/**
	 * Constructor for QGeneratePatchInfo
	 * 
	 * @param string $path
	 */
	public function __construct($path)
	{
		$this->path = $path;
		$this->file_name = basename($path);
		$parts = explode(".", $this->file_name);
		$count = count($parts);
		if ($count < 2)
			return null;
		
		$end = end($parts);
		$prev = prev($parts);
		
		if ($end === "tpl")
		{
			$this->is_patch = ($prev === "patch");
			$this->class_name = $parts[0];
			$this->type = $end;
		}
		else if ($end === "php")
		{
			if ($prev === "url")
			{
				$this->is_patch = (prev($parts) === "patch");
				$this->class_name = $parts[0];
				$this->type = $prev;
			}
			else if ($prev === "event")
			{
				$this->is_patch = (prev($parts) === "patch");
				$this->class_name = $parts[0];
				$this->type = $prev;
			}
			else 
			{
				$this->is_patch = ($prev === "patch");
				$this->class_name = $parts[0];
				$this->type = $end;
			}
		}
	}
	
	/**
	 * Gets the filename 
	 * 
	 * @return string
	 */
	public function getFilename()
	{
		return $this->file_name;
	}
	
	public function isTemplate()
	{
		return ($this->type === "tpl");
	}
	
	public function expandOutput()
	{
		return (($this->type === "tpl") || ($this->type === "url") || ($this->type === "event"));
	}
	
	public function getClassName()
	{
		return $this->class_name;
	}
	
	public function getPath()
	{
		return $this->path;
	}
}
