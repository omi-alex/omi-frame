<?php

/**
 * A backtrace object can help you access previous data in a recursive algorithm
 * 
 */
class QBacktrace
{
	/**
	 * The parent reference
	 *
	 * @var QBacktrace
	 */
	public $parent;
	/**
	 * The element beeing referenced
	 *
	 * @var mixed
	 */
	public $reference;
	/**
	 * The root element of the backtrace
	 *
	 * @var QBacktrace 
	 */
	public $root;
	/**
	 *
	 * @var QBacktrace[]
	 */
	public $children;
	/**
	 * True if the children are to be tracked
	 *
	 * @var boolean
	 */
	public $track;

	/**
	 * Creates a backtrace element
	 *
	 * @param QBacktrace $parent
	 * @param mixed $ref
	 */
	public function __construct(QBacktrace $parent = null, $ref = null, $track_children = false)
	{
		$this->parent = $parent;
		$this->reference = $ref;
		$this->root = $parent ? $parent->root : $this;
		$this->track = $track_children;
		
		if ($track_children && $parent)
			$parent->children[] = $this;
	}

	/**
	 * Creates a leaf for this backtrace and returns it
	 *
	 * @param mixded $ref
	 * 
	 * @return QBacktrace
	 */
	public function next($ref)
	{
		return new QBacktrace($this, $ref, $this->track);
	}
}
