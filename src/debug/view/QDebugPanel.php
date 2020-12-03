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
		
		include(__DIR__."/q_debug_panel.tpl");
	}
	
	protected function render_nodes(array $nodes_list, int $depth = 0)
	{
		foreach ($nodes_list as $node)
		{
			$pad = ($depth * 40);
			echo "<tr>";
			echo "<td style='padding-left: {$pad}px;'>{$node->caption}</td>";
			echo "<td>{$node->line} ./{$node->path}</td>";
			echo "<td class='tags'><div class='scrollable'>".htmlspecialchars( implode(", ", $node->tags) )."</div></td>";
			echo "<td class='big'><a class='qdbg' href='javascript://'>trace</a><div class='scrollable'>".
					htmlspecialchars( json_encode($node->trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) )."</div></td>";
			echo "<td class='big'><a class='qdbg' href='javascript://'>data</a><div class='scrollable'>".
					htmlspecialchars( json_encode($node->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) )."</div></td>";
			echo "<td class='big'><a class='qdbg' href='javascript://'>end data</a><div class='scrollable'>".
					htmlspecialchars( json_encode($node->data_end, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) )."</div></td>";
			echo "</tr>";
			
			if ($node->children)
				$this->render_nodes($node->children, $depth + 1);
		}
	}
}

