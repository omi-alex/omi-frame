<?php

class QSqlParserModel
{
	/**
	 *
	 * @var QIModel
	 */
	public $inst;
	/**
	 *
	 * @var QModelType|QModelTypeScalar
	 */
	public $type;
	/**
	 *
	 * @var string|QModelProperty
	 */
	public $property;
	/**
	 *
	 * @var QSqlParserModel[]
	 */
	public $children;
	/**
	 *
	 * @var QSqlParserQuery
	 */
	public $query;
	
	public function sharePsQuery(QSqlParserQuery $ps_q, $ps_as)
	{
		if ($this->children)
		{
			foreach ($this->children as $child)
			{
				if (($child->type instanceof QModelAcceptedType) || $child->ps_query)
					continue;
				$child->ps_query = $ps_q;
				$child->ps_as = $ps_as;
				
				$child->sharePsQuery($ps_q, $ps_as);
			}
		}
	}

	public function dump($depth = 0)
	{
		if (func_num_args() === 0)
			echo "<pre>";
		
		$type = $this->property ? "PROP" : "TYPE";
		$sql_info = " | {$type} | as: {$this->as} | ps_as : {$this->ps_as}";
		// ps_query = $this->ps_query;
		// $new->ps_as

		echo str_pad("", $depth * 4, " ", STR_PAD_LEFT).($this->property ? $this->property->name : $this->type).$sql_info."\n";
		if ($this->children)
		{
			foreach ($this->children as $child)
				$child->dump($depth + 1);
		}

		if (func_num_args() === 0)
			echo "</pre>";
	}
	
	/**
	 * Trims all the whitespaces or commas from the end of the array
	 * 
	 * @param string[] $arr
	 */
	public function RtrimComma(&$arr)
	{
		$cnt = count($arr);
		$pos = $cnt - 1;
		$end = $arr[$pos];
		
		while (($end === ",") || ctype_space($end))
			$end = $arr[--$pos];
		if ($pos < ($cnt - 1))
			array_splice($arr, $pos + 1, $cnt - 1 - $pos);
	}
	
	/**
	 * Trims all the whitespaces or commas from the begining of the array
	 * 
	 * @param string[] $arr
	 */
	public function LtrimComma(&$arr)
	{
		// $cnt = count($arr);
		$pos = 0;
		$start = $arr[$pos];
		
		while (($start === ",") || ctype_space($start))
			$start = $arr[++$pos];
		if ($pos > 0)
			array_splice($arr, 0, $pos);
	}
}
