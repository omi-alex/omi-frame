<?php

class QFileFilter extends RecursiveFilterIterator 
{
	/**
	 * @var Closure
	 */
	protected $filter;
	
	public function __construct(RecursiveIterator $it, Closure $filter = null) 
	{
		$this->filter = $filter;
		parent::__construct($it);
	}
	
	public function accept() 
	{
		// return (!$this->isFile() || $this->getMTime() >= $this->earliest_date);
		return ($filter = $this->filter) ? $filter($this) : true;
	}

	public function getChildren()
	{
		return new static($this->getInnerIterator()->getChildren(), $this->filter);
	}
}
