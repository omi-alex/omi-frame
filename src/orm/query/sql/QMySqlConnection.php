<?php

class QMySqlConnection extends \mysqli
{
	public function query($query, $resultmode = MYSQLI_STORE_RESULT)
	{
		/*
		$r_matches = null;
		$rc = preg_match("/^\\s*(\w+)\\s+/ius", $query, $r_matches);
		$q_type = $rc ? strtoupper($r_matches[1]) : null;
		
		\QAudit::Audit("QMySqlConnection", "query", $q_type, $query);
		*/
		
		return parent::query($query, $resultmode);
	}
		
	public function real_query($query)
	{
		throw new \Exception("not implemented");
	}
}

