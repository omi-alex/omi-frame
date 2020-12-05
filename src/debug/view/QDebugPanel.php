<?php

/**
 * 
 */
class QDebugPanel
{	
	public static function Run()
	{
		(new static)->run_instance();
	}
	
	protected function run_instance()
	{
		if ((!defined('dev_ip')) || (dev_ip !== $_SERVER['REMOTE_ADDR']))
			throw new \Exception("IP not authorized");
		
		$this->trace = new \QTrace();
		$this->trace->init();
		$this->trace->touch();
		
		$this->trace->cleanup();
		
		include(__DIR__."/q_debug_panel.tpl");
	}
	
	protected function render_nodes(array $nodes_list, int $depth = 0)
	{
		foreach ($nodes_list as $node)
		{
			$pad = ($depth * 40);
			echo "<tr>";
			echo "<td style='padding-left: {$pad}px;'>{$node->caption}</td>";
			echo "<td class='big'><a class='qdbg fas fa-route' href='javascript://'> </a><div class='scrollable'>".
					htmlspecialchars( qvar_get($node->trace) )."</div>";
			echo " <a class='qdbg fas fa-database' href='javascript://'> </a><div class='scrollable'>".
					htmlspecialchars( qvar_get($node->data) )."</div>";
			echo " <a class='qdbg fas fa-check' href='javascript://'> </a><div class='scrollable'>".
					htmlspecialchars( qvar_get($node->data_end) )."</div>";
			// echo "<td>{$node->line} ./{$node->path}</td>";
			foreach ($node->called_in as $called_in)
			{
				# [$rel_path, $trace['class'], $trace['type'], $trace['function'], $trace['line']]
				list ($rel_path, $trace_class, $trace_type, $trace_func, $trace_line) = $called_in;
				$hidden = "<i class='q-dots'>...</i><span style='display: none'>line: {$trace_line} @ {$rel_path}</span>";
				echo "<td>".
						($trace_class ? ($trace_class.$trace_type) : '').$trace_func." ".$hidden."</td>";
			}
			echo "<td class='tags'><div class='scrollable'>".htmlspecialchars( implode(", ", $node->tags) )."</div></td>";
			echo "</tr>";
			
			if ($node->children)
				$this->render_nodes($node->children, $depth + 1);
		}
	}
}

