<?php

/**
 * Old way of securing input
 * 
 * @param string $calling_class
 * @param string $calling_method
 * @param QIModel $instance
 * @param array $args
 */
function qCheckInput($calling_class, $calling_method, QIModel $instance = null, $args = [])
{
	// $calling_class, $calling_method, $instance, $args
	foreach ($args as $value)
	{
		if ($value instanceof QIModel)
		{
			// based on _wst, we traverse and setup security
			
			// $value::SecurityWorker($bag, $op_cache, $self, $array, $array_refs, $action, $groups, $selector, $max_selector, $throw_error, $unset_if_not_selector, $unset_if_not_allowed);
		}
	}
}

function qViewMode($obj, $property = null, $viewTag = null, $customAction = null)
{
	// read and/or edit
	
	// :: context (ex: for a certain view we use context security)
	// :: $customAction : yes no, it's an extra security
	// :: $calling_class = null, $calling_method = null
	
	
}

