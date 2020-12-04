<?php

/**
 * QModelQuery 
 *
 * @author Alex
 */
class QModelQuery
{
	public static $_tmp_bq = [];
	public static $_tmp_time = 0;
	/**
	 *
	 * @var array[]
	 */
	public static $TypesCache = array();
	/**
	 * Executes a query
	 * 
	 * @param string $query
	 * @param QIModel $from
	 * @param QIModel[] $dataBlock 
	 * 
	 * @return QIModel
	 * @throws Exception
	 */
	public static function Query($query, $from = null, &$dataBlock = null, $skip_security = true, $binds = null, $initial_query = null, $filter_selector = null, $populate_only = false, \QIStorage $storage = null)
	{
		\QTrace::Begin_Trace([],
					[$query, $binds, $skip_security, $populate_only], ["query"]);
		
		try
		{
			if (is_string($filter_selector))
				$filter_selector = qParseEntity($filter_selector);
			// Execute the query
			if ($storage === null)
				$storage = QApp::GetStorage();
			$conn = $storage->connection;

			if ($dataBlock === null)
				$dataBlock = array();

			if (($from instanceof QIModelArray) || is_array($from))
			{
				// handle query on an array
				$saved_from = $from;
				list($from_blocks, $sql_items) = self::CreateModelBlocks($from, $dataBlock, $storage);
				if (empty($sql_items))
					return $sql_items;

				foreach ($from_blocks as $_from)
				{
					// Build the query 
					list($main_q, $frm_arr, $from) = self::QueryToStructInner($query, $_from, $skip_security, $sql_items[$_from], $filter_selector, $storage);

					$main_q->_dbg_query = ($initial_query && ($initial_query !== $query)) ? array($initial_query, $query) : $query;
					$main_q->_dbg_binds = $binds;

					$main_q->storage = $storage;

					if (QAutoload::$DebugPanel)
						QDebug::AddQuery($main_q->_dbg_query, $from, $binds, $dataBlock ? true : false, $skip_security);

					$main_q->query($dataBlock, $storage, $conn, $frm_arr, $populate_only);

					/*if ((!$skip_security) && QModel::$SecurityCheckQueriedData && $dataBlock)
					{
						//$user = QUser::GetCurrent();
						//QModel::SecurityCheck($from, null, null, $user, QPermsFlagRead, $dataBlock);
					}*/
				}

				if ($main_q->query_type === "INSERT")
				{
					$mysqli = $storage->connection;
					return $mysqli->insert_id;
				}


				return $saved_from;
			}
			else
			{
				// query as before for one instance
				// Build the query 
				list($main_q, $frm_arr, $from) = self::QueryToStructInner($query, $from, $skip_security, null, $filter_selector, $storage);

				$main_q->_dbg_query = ($initial_query && ($initial_query !== $query)) ? array($initial_query, $query) : $query;
				$main_q->_dbg_binds = $binds;

				$main_q->storage = $storage;

				if (QAutoload::$DebugPanel)
					QDebug::AddQuery($main_q->_dbg_query, $from, $binds, $dataBlock ? true : false, $skip_security);

				$main_q->query($dataBlock, $storage, $conn, $frm_arr, $populate_only);

				/*
				if ((!$skip_security) && QModel::$SecurityCheckQueriedData && $dataBlock)
				{
					//$user = QUser::GetCurrent();
					//QModel::SecurityCheck($from, null, null, $user, QPermsFlagRead, $dataBlock);
				}
				*/

				if ($main_q->query_type === "INSERT")
				{
					$mysqli = $storage->connection;
					return $mysqli->insert_id;
				}

				return $from;
			}
		}
		finally
		{
			\QTrace::End_Trace([], ['return' => $from]);
		}
	}
	
	public static function CreateModelBlocks($from, &$dataBlock, $storage)
	{
		$types = array();
		$sql_items = array();
		foreach ($from as $item)
		{
			if (!($item instanceof QIModel))
				continue;
			
			$t_id = $item->getId();
			/*
			if (!$t_id)
				throw new Exception("We can not query on items that do not have an Id");
			*/
			
			if (!$t_id)
				continue;
			
			$class = get_class($item);
			$types[$class] = $class;
			$t_ty = $storage->getTypeIdInStorage($class);
			$dataBlock[$t_id][$t_ty] = $item;
			$sql_items[$class][$t_id][$t_ty] = $item;
		}
		return [$types, $sql_items];
	}
	
	public static function RootBindQueryQuery($query, $binds, $from = null, &$dataBlock = null)
	{
		return self::BindQuery($query, $binds, $from, $dataBlock, true);
	}
	
	public static function BindQuery($query, $binds, $from = null, &$dataBlock = null, $skip_security = true, $filter_selector = null, $populate_only = false, \QIStorage $storage = null)
	{
		/*if (strpos($query, 'Services_Instances') !== false)
		{
			qvar_dumpk($query, $binds);
			\QSqlParserQuery::$_DebugOn = 1;
		}*/
		
		\QTrace::Begin_Trace(["caption" => "Query: ".substr($query, 0, 24)], ['$query' => $query, '$binds' => $binds, '$populate_only' => $populate_only], ["query"]);
		
		try
		{
			if (defined('q_lock_queries') && q_lock_queries)
				throw new \Exception('q_lock_queries');

			$run_query = self::PrepareBindQuery($query, $binds);

			if (defined('Q_QQuery_Debug_SQL') && Q_QQuery_Debug_SQL)
				qvar_dumpk($run_query);

			$result = self::Query($run_query, $from, $dataBlock, $skip_security, $binds, $query, $filter_selector, $populate_only, $storage);
			if ($dataBlock)
			{
				foreach ($dataBlock as $objs)
				{
					if ($objs)
					{
						foreach ($objs as $obj)
							$obj->init(false);
					}
				}
			}
			return $result;
		}
		finally
		{
			// \QSqlParserQuery::$_DebugOn = 0;

			\QTrace::End_Trace();
		}
	}
	
	/**
	 * 
	 * @param string $query
	 * @param QIModel $from
	 * 
	 * @return array(QModelQuery,array(QSqlParserModel))
	 */
	protected static function QueryToStructInner($query, $from = null, $skip_security = true, $query_items = null, $filter_selector = null, \QIStorage $storage = null)
	{
		$tokens = self::Parse($query);
		
		if ($from === null)
		{
			$from_type = QApp::GetDataClass();
			$from_inst = new $from_type();
			$from_inst->setId(QApp::Data()->getId());
		}
		else if (is_string($from))
		{
			$from_type = $from;
			$from_inst = new QModelArray();
		}
		else
		{
			$from_type = get_class($from);
			$from_inst = $from;
		}
		
		$frm = new QSqlParserModel();
		$frm->inst = $from_inst;
		$frm->type = $from_type;

		$frm_arr = self::initForType($frm, $skip_security);
			
		$main_q = $frm->query;
		if ($query_items)
		{
			$main_q->items = $query_items;
			$main_q->collection_as = $main_q->as;
			$main_q->collection_bk = $main_q->col_id_name;	
		}
		
		if ($storage === null)
			$storage = QApp::GetStorage();

		self::BuildQuery($tokens, $frm_arr, $main_q, $storage, 0, $skip_security, $filter_selector);
		return array($main_q, $frm_arr, $from_inst);
	}
	
	public function Parse($query)
	{
		// split query into tokens
		$tokens = null;
		$stat = preg_match_all("/".
				# Escaped identifiers have priority
				"'(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\'|". # string
				"[-+]?(?:[0-9]*\.?[0-9]+|[0-9]+)|". # number (not full validation)
				// Keywords: AND/OR/...
				"\bAS\b|\bSELECT\b|\bUPDATE\b|\bDELETE\b|\bINSERT\b|\bWHERE\b|\bORDER\\s+BY\b|\bHAVING\b|\bGROUP\\s+BY\b|\bAND\b|\bOR\b|\bBETWEEN\b|\bASC\b|\bDESC\b|\bLIMIT\b|".
					"\bNULL\b|\bTRUE\b|\bFALSE\b|\bIS_A\b|\bIS\b|\bLIKE\b|\bCASE\b|\bBINARY\b|\bNOT\b|\bDIV\b|\bIS\\s*NULL\b|\bIS\\s*NOT\\s*NULL\b|\bSQL_CALC_FOUND_ROWS\b|".
					"\bDISTINCT\b|\bEND\b|\bELSE\b|\bTHEN\b|\bSEPARATOR\b|".
					"\bINTERVAL\b|\bMONTH\b|\bDAY\b|\bWEEK\b|\bHOUR\b|\bMINUTE\b|\bSECOND\b|\bYEAR\b|".
				// FUNCTIONS: FuncName (
				"[\\p{L&}\\$]\\w+\\s*\\("."|\\p{L&}+\\s*\\(|".
				// Identifiers/entities
				"([\\`\\\"]?[\\p{L&}\\$]\\w[\\w\\\\]*[\\`\\\"]?|"."[\\`\\\"]?\\p{L&}[\\`\\\"]?+)|". # identifiers (can not start with a digit)
				"\\(\\+\\)|". # TO DO : control join type
				"\\(\\-\\)|". # TO DO : control join type
				"\\(\\s*\\)|". # empty brackets
				"\\<\\=\\>|".
				"\\:\\=|".
				"\\|{2}|".
				"\\&{2}|".
				"\\>{2}|".
				"\\<{2}|".
				"\\!\\=|".
				"\\<\\>|".
				"\\>\\=|".
				"\\<\\=|".
				"[\\!-\\/\\:-\\@\\[-\\^\\`\\{-\\~]{1}|". # from ASCII table we have ranges there also
				"(\\s+)".

			"/us", $query, $tokens, PREG_SET_ORDER);
		
		if ($stat === false)
			throw new Exception("Parsing failed for the query");
		
		return $tokens;
	}
	
	/**
	 * Builds the data for the query and the SQL queries.
	 * 
	 * @param string[] $toks
	 * @param mixed $from
	 * @param QSqlParserQuery $main_q
	 * 
	 * @return QSqlParserQuery
	 * 
	 * @throws Exception
	 */
	public function BuildQuery(&$toks, $from = null, QSqlParserQuery $main_q = null, $storage = null, $zone = 0, $skip_security = false, $filter_selector = null, 
								bool $debug = false, bool $inner_query = false)
	{
		// position at the current point 
		list($curr, $curr_i, $curr_s) = current($toks);
		
		// save the initial from to reset it later
		$base_from = $from;

		// pure_select means that a property/collection item is selected without any transformation
		$pure_sel = $inner_query ? false : ($zone === 0);
		$depth = 0;
		$main_q->p_index = $zone;
		
		$preceded_by_AS = false;
		
		$has_filter_selector = ($filter_selector !== null);
		
		while ($curr !== null)
		{
			// TO DO: only grab `*` as an identifier in the right context
			// add condition on the prev: , | func( | $BEGIN
			
			$is_selector = false;
			
			if (($curr_i && ($curr_i{0} !== "`") && ($curr_i{0} !== "\"")) || ($curr === "*"))
			{
				// test if it's a pure select
				if ($pure_sel && ($zone === 0) && ($depth === 0))
				{
					// look ahead
					$key = key($toks);
					$pure_sel = false;
					$starts_new_query = false;
					
					do
					{
						list($lka, $lka_i, $lka_s) = $toks[++$key];
						
						if ($lka_s)
							// skip whitespaces
							list($lka, $lka_i, $lka_s) = $toks[++$key];

						if ($lka === ":")
						{
							// read type & skip
							// skip ":"
							list($lka, $lka_i, $lka_s) = $toks[++$key];
							// skip whitespace
							if ($lka_s)
								list($lka, $lka_i, $lka_s) = $toks[++$key];
							// skip $type
							list($lka, $lka_i, $lka_s) = $toks[++$key];
							// skip whitespace
							if ($lka_s)
								list($lka, $lka_i, $lka_s) = $toks[++$key];
						}

						if ($lka === ".")
						{
							// skip "."
							list($lka, $lka_i, $lka_s) = $toks[++$key];
							// skip whitespace
							if ($lka_s)
								list($lka, $lka_i, $lka_s) = $toks[++$key];
							if ($lka === "{")
							{
								// yes it is a pure select
								$starts_new_query = true;
								$pure_sel = true;
								break;
							}
						}
						else
						{
							// the identifer has ended, we must check what is after
							// by , OR zone end|change 
							// "WHERE":"GROUP BY":"HAVING":"ORDER BY":"LIMIT":
							$pure_sel = ((!$lka) || ($lka === ",") || ($lka === "}") || 
										(($uc_lka = strtoupper($lka)) === "WHERE") || 
										($uc_lka === "GROUP BY") || ($uc_lka === "HAVING") || ($uc_lka === "ORDER BY") || ($uc_lka === "LIMIT"));
							
							break;
						}
					}
					while ($lka_i || ($lka === "*"));
				}
				else
					$pure_sel = false;
				
				// here we make sure * is not the math operator *
				if (($curr !== "*") || $pure_sel)
				{
					$is_selector = true;
				
					$next_q = $main_q;
					// $use_pure_sel = ($zone === 0) && $pure_sel;
					$set_columns = array();

					$c_filter_selector = $filter_selector;
					// we loop foreach identifier part: Orders.Items.Item:QProduct.Name
					do 
					{
						// identifer name
						$idf_name = $curr_i ?: $curr;

						list($idf_next, $curr_i, $curr_s) = next($toks);
						// skip whitespaces
						if ($curr_s)
							list($idf_next, $curr_i, $curr_s) = next($toks);

						$idf_filter = null;
						// if followed by `:` it means we have a type filter
						if ($idf_next === ":")
						{
							// go to next
							list($idf_filter, $curr_i, $curr_s) = next($toks);
							// skip whitespaces
							if ($curr_s)
								list($idf_filter, $curr_i, $curr_s) = next($toks);

							// at this point we have $idf_filter

							// position $idf_next
							list($idf_next, $curr_i, $curr_s) = next($toks);
							// skip whitespaces
							if ($curr_s)
								list($idf_next, $curr_i, $curr_s) = next($toks);
						}

						// PROPS 
						$types_mngd = array();
						$froms = $from;

						$ps_query = null;
						
						$sub_filter_selector = null;
						if ($idf_name === "*")
						{
							$sub_filter_selector = ($c_filter_selector["*"] !== null) ? true : $c_filter_selector;
							$c_filter_selector = false;
						}
						else if ($c_filter_selector !== false)
						{
							$c_filter_selector = is_array($c_filter_selector) ? (($c_filter_selector[$idf_name] !== null) ? $c_filter_selector[$idf_name] : $c_filter_selector["*"]) : $c_filter_selector;
						}
						
						if ((!$has_filter_selector) || ($zone !== 0) || (!$pure_sel) || $sub_filter_selector || ($c_filter_selector !== null))
						{
							// $sub_filter_selector = ;
							$toks_key = key($toks);
							$followed_by_IS_A = (!$pure_sel) && ($toks && $toks_key && ($tok_i = $toks[$toks_key]) && is_array($tok_i) && $tok_i[0] && (strtoupper($tok_i[0]) === "IS_A"));
							
							$jump_tokens = 0;
							self::handleIdentifier($main_q, $pure_sel, $froms, $types_mngd, $idf_name, $idf_filter, $idf_next, $ps_query, $preceded_by_AS, $followed_by_IS_A, 
															$starts_new_query, $toks, key($toks), $jump_tokens, $storage, $sub_filter_selector, $zone, $inner_query);
							
							while ($jump_tokens)
							{
								list($idf_next, $curr_i, $curr_s) = next($toks);
								$jump_tokens--;
							}
						}
						else
							// false means we don't add to query any more
							$c_filter_selector = false;
						// a pure select was made and the next query (if required) is to be used
						if ($ps_query)
							$next_q = $ps_query;

						$idf_ends = false;
						if ($idf_next === ".")
						{
							if (!$types_mngd)
							{
								if ($set_columns || ($c_filter_selector === false))
									$from = array();
								else
									throw new Exception("Invalid step into: ".$idf_name);
							}
							else
							{
								$from = $types_mngd;
							}

							list($idf_next, $curr_i, $curr_s) = next($toks);
							// skip whitespaces
							if ($curr_s)
								list($idf_next, $curr_i, $curr_s) = next($toks);

							if ($idf_next === "{")
							{
								// we open a new context
								list($idf_next, $curr_i, $curr_s) = next($toks);
								// skip whitespaces
								if ($curr_s)
									list($idf_next, $curr_i, $curr_s) = next($toks);

								$q_zone = $next_q->p_index;
								// $save_pp_comma = $next_q->pp_comma;
								// recursivity on {
								self::BuildQuery($toks, $from, $next_q, $storage, $pure_sel ? 0 : $q_zone, $skip_security, $c_filter_selector);
								$next_q->p_index = $q_zone;
								// $next_q->pp_comma = $save_pp_comma;
							}
						}
						else
						{
							// we should break
							$idf_ends = true;
						}

						list($curr, $curr_i, $curr_s) = current($toks);
						if ($idf_ends)
							break;
					}
					while ($curr_i || ($curr === "*"));

					// reset from 
					$from = $base_from;
					// reset $preceded_by_AS
					$preceded_by_AS = false;
				}
			}
			
			if (!$is_selector)
			{
				$q = $main_q;
				$break = false;

				if ($curr_s)
				{
					$q->parts[$q->p_index][] = $curr;
				}
				// depends on what zone we are in (or not ?!)
				else if (($len = strlen($curr)) === 1)
				{
					switch ($curr)
					{
						case "}":
						{
							// we need to close the context
							// next($toks);
							$break = true;
							break;
						}
						case "(":
						{
							$q->parts[$q->p_index][] = $curr;
							$depth++;
							break;
						}
						case ")":
						{
							$q->parts[$q->p_index][] = $curr;
							$depth--;
							break;
						}
						case ",":
						{
							if (($zone === 0) && ($depth === 0))
								$pure_sel = true;
							$q->parts[$q->p_index][] = $curr;
							break;
						}
						default:
						{
							$q->parts[$q->p_index][] = $curr;
							break;
						}
					}
				}
				else
				{
					$curr_uc = strtoupper($curr);
					switch ($curr_uc)
					{
						/* SELECT		: 0
						 * FROM & JOIN	: 1
						 * WHERE		: 2
						 * GROUP BY		: 3
						 * HAVING		: 4
						 * ORDER BY		: 5
						 * LIMIT		: 6
						 */
						case "WHERE":
						{
							// test parameters
							$hpm_rc = static::handle_Predicate_Modifiers('WHERE', $main_q, $main_q, $toks, $base_from, $storage);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$pure_sel = false;
								$zone = $q->p_index = 2;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "GROUP BY":
						{
							$hpm_rc = static::handle_Predicate_Modifiers('GROUP BY', $main_q, $main_q, $toks, $base_from, $storage);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$zone = $q->p_index = 3;
								$pure_sel = false;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "HAVING":
						{
							$hpm_rc = static::handle_Predicate_Modifiers('HAVING', $main_q, $main_q, $toks, $base_from, $storage);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$zone = $q->p_index = 4;
								$pure_sel = false;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "ORDER BY":
						{
							$hpm_rc = static::handle_Predicate_Modifiers('ORDER BY', $main_q, $main_q, $toks, $base_from, $storage);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$zone = $q->p_index = 5;
								$pure_sel = false;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "LIMIT":
						{
							$hpm_rc = static::handle_Predicate_Modifiers('LIMIT', $main_q, $main_q, $toks, $base_from, $storage);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$zone = $q->p_index = 6;
								$pure_sel = false;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "SELECT":
						{
							// check if it's an inner select
							$hpm_rc = static::handle_Inner_Select($main_q, $toks, $base_from, $storage, $skip_security, $zone);
							if ($hpm_rc === true)
							{
								// do nothing, it was handled
							}
							else
							{
								$q->query_type = "SELECT";

								$zone = $q->p_index = 0;
								$pure_sel = true;
								if (empty($q->parts[$q->p_index]))
									$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							break;
						}
						case "UPDATE":
						case "DELETE":
						case "INSERT":
						{
							$q->query_type = $curr_uc;
							
							// we need to reset this
							$q->parts[$q->p_index] = [];
							$q->parts[$q->p_index][] = $curr;
							$q->pp_comma = false;
							$pure_sel = true;
							break;
						}
						case "IS_A":
						{
							// not so simple ... fss ... we need to roll back 
							// $q->parts[$q->p_index][] = $curr;
							// Orders.Items.Item IS_A QProduct
							// `type` IN (1,2,3,4)
							//	"`$\type` IN (1,2,3,4)
							$q->parts[$q->p_index][] = " IN (";
							// skip whitespaces
							
							list($curr, $curr_i, $curr_s) = next($toks);
							if ($curr_s)
								list($curr, $curr_i, $curr_s) = next($toks);
							$ids = array();
							$classes = QAutoload::GetClassExtendedBy($curr);
							$classes ? array_unshift($classes, $curr) : ($classes = array($curr));
							foreach ($classes as $class)
								$ids[] = $storage->getTypeIdInStorage($class);

							$q->parts[$q->p_index][] = implode(",", $ids);
							$q->parts[$q->p_index][] = ") ";
							
							break;
						}
						case "AS":
						{
							$pure_sel = false;
							$preceded_by_AS = true;
							
							$q->parts[$q->p_index][] = $curr;
							$q->pp_comma = false;
							break;
						}
						case "SQL_CALC_FOUND_ROWS":
						{
							if (defined("MYSQL_USE_SQL_CALC_FOUND_ROWS") && MYSQL_USE_SQL_CALC_FOUND_ROWS)
							{
								$q->parts[$q->p_index][] = $curr;
								$q->pp_comma = false;
							}
							$q->calc_rows = true;
							break;
						}
						default:
						{
							// in function:
							if (($len > 1) && (substr($curr, -1, 1) === "("))
							{
								$q->pp_comma = false;
								$depth++;
							}
							
							$q->parts[$q->p_index][] = $curr;
							break;
						}
					}
				}

				list($curr, $curr_i, $curr_s) = next($toks);

				if ($break)
					break;
			}
		}

		return $main_q;
	}
	
	public static function PrepareBindQuery($query, $params)
	{
		if (!is_array($params))
			$params = array($params);
		
		$ret = preg_split("/".
				
				"('(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\')|". # avoid strings
				"(\\?\\?[A-Za-z0-9\\.\\-\\_]+(?:\\?[\\<\\>][^\\[]+)?\\[".
							"(?:[^\\]^\\']+|'(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\')+".
									"\\])|". # to be replaced if key exists
				"(\\<\\=\\>)|".
				"(\\?)".
				
					"/ius", $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		$prev_type = null;
		$prev_prev_type = null;
		
		$parts = array();
		$simple_binds_pos = 0;
		foreach ($ret as $k => $chunk)
		{
			if ($chunk === "?")
			{
				if (!array_key_exists($simple_binds_pos, $params))
					throw new Exception("Invalid static bind at position: ".json_encode($params));
				else
				{
					// test if there is a <=> before or after
					$has_OP_IS_NOT_DISTINCT_FROM = ($ret[$k - 1] === "<=>") || (ctype_space($ret[$k - 1]) && ($ret[$k - 2] === "<=>")) || 
													($ret[$k + 1] === "<=>") || (ctype_space($ret[$k + 1]) && ($ret[$k + 2] === "<=>"));
					
					$c_bind = $params[$simple_binds_pos++];
					// $parts[] = (is_string($c_bind) || is_object($c_bind)) ? "'"._mySc($c_bind)."'" : (is_null($c_bind) ? "NULL" : $c_bind);
					$parts[] = _myScBind($c_bind, false, $has_OP_IS_NOT_DISTINCT_FROM);
				}
			}
			else if (($chunk{0} === "?") && ($chunk{1} === "?"))
			{
				$type = 1;
				
				// 1. do the replace
				// 2. if ($prev_type is space && $prev_prev_type is replacement and was replaced prepend what needs to be)
				// TO DO: sufixes will be a bit more complicated but not impossible
				// 
				
				// ??Date?<AND[Date 'BET]WEEN' ? AND ?]
				// ??Limit[(?,?)]
				$_tag = substr($chunk, 2, ($br_pos = strpos($chunk, "[", 2)) - 2);
				$tag = (($p = strpos($_tag, "?")) === false) ? $_tag : substr($_tag, 0, $p);
					
				if (isset($params[$tag]) || array_key_exists($tag, $params))
				{
					// will be replaced
					$binds = $params[$tag];
					$binds = is_array($binds) ? $binds : array($binds);
					$c_bind = reset($binds);
					// var_dump($binds);
					
					$replacement = substr($chunk, $br_pos + 1, -1);
					
					// var_dump($replacement);
					
					// ?<! - lookbehind assertion
					// I need to match ? or ?@ that are not in a string
					
					$pend_dir = ($p !== false) ? $_tag{$p+1} : null;
					$pender = $pend_dir ? substr($_tag, $p + 2, $br_pos - $p - 2) : null;
					
					if ($pend_dir === "<")
						$parts[] = " {$pender} ";
					
					$rep_parts = preg_split("/".
				
							"(\\?\\@)|(\\?)|".
							"('(?:(?:[^\\\\\']+|(?:\\\\.)+)*)\')". # avoid strings

							"/ius", $replacement, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
					
					foreach ($rep_parts as $rp)
					{
						if ($rp === "?")
						{
							$parts[] = _myScBind($c_bind, false);
							// $parts[] = (is_string($c_bind) || is_object($c_bind)) ? "'"._mySc($c_bind)."'" : (is_null($c_bind) ? "NULL" : $c_bind);
							// repeat the last bind value if we run out of options
							$c_next = next($binds);
							$c_bind = (key($binds) !== null) ? $c_next : $c_bind;
						}
						else if ($rp === "?@")
						{
							if (!preg_match("/^[\\\\\\w\\.\\\$\\_]+\$/ius", $c_bind))
								throw new Exception("Invalid variable for non-parse bind ?@. The variable must only contain alphanumeric or underscore characters.");
							$parts[] = $c_bind;
							// repeat the last bind value if we run out of options
							$c_next = next($binds);
							$c_bind = (key($binds) !== null) ? $c_next : $c_bind;
						}
						else
							$parts[] = $rp;
					}
					
					if ($pend_dir === ">")
						$parts[] = " {$pender} ";
				}
			}
			else if (ctype_space($chunk))
			{
				$type = 2;
				$parts[] = $chunk;
			}
			else
			{
				$type = 3;
				$parts[] = $chunk;
			}
			
			$prev_prev_type = ($prev_type !== null) ? $prev_type : null;
			$prev_type = $type;
		}
		
		$r_query = implode("", $parts);
		
		// $e = microtime(true);
		// var_dump(round(($e - $t)*1000,4)."ms");
		
		return $r_query;
	}
	
	/**
	 * This method is called when we step into an identifier. 
	 * Example:
	 *		 .Image
	 *		 .Products
	 *		 Products.
	 *		 .Products.
	 *		 .Products.{
	 * 
	 * @param QSqlParserQuery $main_q The current Query
	 * @param boolean $pure_sel True if this identifier selects data as it is
	 * @param QSqlParserModel[] $froms The list of types (QSqlParserModel) that we select from
	 * @param QSqlParserModel[] $types_mngd The list of types (QSqlParserModel) for the next identifier in the same sequence
	 * @param string $idf_name The name of the identifier
	 * @param string $idf_filter Filter for the data types (TO DO)
	 * @param string $idf_next The next chunk/token in the identifier sequence
	 * @param QSqlParserQuery $next_q If we start a new query due to a collection (under pure select) we set this variable
	 * @param boolean $preceded_by_AS True if this identifier is a AS identifier
	 * @param boolean $followed_by_IS_A TRUE if after this identifier we have the IS_A operator, in this case we will add " AND $TYPE_COLUMN  ", then the rest of the code will add " IN (...) "
	 * @param boolean $starts_new_query If TRUE, then after this identifier a new SUB QUERY starts. Ex: Orders.{
	 */
	public function handleIdentifier(QSqlParserQuery $main_q, $pure_sel, $froms, &$types_mngd, $idf_name, $idf_filter, 
				$idf_next, &$next_q = null, $preceded_by_AS = false, $followed_by_IS_A = false, $starts_new_query = false, &$toks = null, $toks_index = 0, int &$jump_tokens = 0,
				$storage = null, $filter_selector = null, $zone = null, bool $inner_query = false)
	{
		// the identifier swquence ends with this element and we need to output the element in the query
		$is_last = ($idf_next !== ".");
		$is_last_with_Id = false;
		
		if ((!$inner_query) && ($main_q->query_type === 'SELECT') && (!$is_last) && ($toks_index !== null) && ($toks_index !== false) && ($toks[$toks_index + 1][1] === 'Id'))
		{
			$is_last = true;
			$is_last_with_Id = true;
			$jump_tokens = 2;
		}
		
		// echo "handleIdentifier ({$pure_sel}):: ".$idf_name."<br/>\n";
		
		// we keep track of joins to avoid making unnecessary joins
		// the list of columns to be set on the query, if more than one, COLASCE will be used
		$select_props = array();
		// we track created props per type alias
		$prop_select_index = array();
		// we reset the managed types
		$types_mngd = array();
		// queries are identified by their first alias (following queries will increment the alias)
		$queries = array();
		// we need to index the property's ALIAS based on [query's ID]/[property name], if it was already set do not set it again
		$prop_as_index = array();
		
		$joins = &$main_q->joins;
		
		$all_props = ($idf_name === "*");
		$caption_props = ($idf_name === "\$CaptionProperties");
		$type_target = ($idf_name === "Type__");
	
		/**
		 * foreach possible type we handle for the identifier
		 */
		foreach ($froms as $from)
		{
			// getting the type's name
			$from_k = is_string($from->type) ? $from->type : $from->type->class;
			// getting type information
			$type_inf = self::GetTypesCache($from_k);
			
			// $CaptionProperties
			
			// the current property 
			// next($type_inf)
			if ($all_props)
			{
				// skip #%tables
				next($type_inf);
				// skip #%table
				next($type_inf);
				// skip #%id
				next($type_inf);
				// skip #%misc
				next($type_inf);
				$c_prop = key($type_inf);
			}
			else if ($caption_props)
			{
				// var_dump($type_inf["#%table"], $type_inf["#%misc"]["model"]["captionProperties"]);
				$caption_props = $type_inf["#%misc"]["model"]["captionProperties"];
				if (!$caption_props)
					continue;
				$c_prop = current($caption_props);
			}
			else if ($type_target)
			{
				$c_prop = $idf_name;
			}
			else
				$c_prop = $idf_name;
			
			// eastablish the alias of the from element
			$from_as = ($pure_sel && $from->ps_as) ? $from->ps_as : $from->as;
			// establis the query we are on
			$from_q = ($pure_sel && $from->ps_query) ? $from->ps_query : $main_q;
			
			$queries[$from_q->as] = $from_q;
			
			// HANDLE AS
			if ($preceded_by_AS)
			{
				$as_name = $from_q->getNextAlias();
				$from->action_prop[$c_prop]["as"] = $as_name;
				$from_q->parts[$from_q->p_index][] = " `".$as_name."`";
				$from_q->pp_comma = (($from_q->p_index !== 2) && ($from_q->p_index !== 4));
				
				// return;
			}
			else
			{
				if (!$from_as)
				{
					if (\QAutoload::GetDevelopmentMode())
						var_dump($pure_sel);
					throw new Exception("Missing from as: {$from_k}");
				}

				// FOREACH PROPERTY
				do
				{
					if ($filter_selector && ($filter_selector !== true) && ($filter_selector[$c_prop] === null) && ($filter_selector["*"] === null))
						continue;
					
					//echo "We step into: {$from_k} :: {$c_prop} :: ps:".($pure_sel ? "yes" : "no")." :: {$from_as} <br/>\n";

					// we need to index the property ALIAS based on query/property name, if it was already set do not set it again
					$prop_as = $prop_as_index[$from_q->as][$c_prop] ?: ($prop_as_index[$from_q->as][$c_prop] = $from_q->getNextAlias());

					if ($type_target)
						# this is to make sure Type__ will point to the $_type column
						$prop_types = ["vc" => "\$_type", 'rc_t' => ['integer'], '$' => ['integer'],];
					else
						// reading property information from the cache
						$prop_types = $all_props ? current($type_inf) : $type_inf[$c_prop];

					// one type may not have this property, we continue for the other types
					if (!$prop_types)
						continue;

					// we make sure we don't create properties when not needed
					$prop_select_key = $from_as.".".($prop_types["vc"] ?: ($prop_types["rc"] ?: $c_prop."[]"));

					$prop_was_created = false;

					// either we have the propery object from a previous SELECT or it was created on this step
					// we only create a new `Property Object` if not already steped in or if there is not another property with the same PARENT & DESTINATION
					if ((!($prop_model = $from->children[$c_prop])) && (!($prop_model = $prop_select_index[$prop_select_key])))
					{
						// if not exists, create it
						$prop_model = new QSqlParserModel();
						$prop_model->property = $c_prop;
						$prop_select_index[$prop_select_key] = $prop_model;
						$prop_model->first_type = $from;

						$prop_model->ps_query = $from->ps_query;
					}
					else
						$prop_was_created = true;

					if (!$from->children[$c_prop])
						$from->children[$c_prop] = $prop_model;

					// ensure alias
					if (!$prop_model->as)
						$prop_model->as = $prop_as;
					
					// HANDLE COLLECTIONS
					// skip collection when last and selected via *
					if ((!($is_last && $all_props)) && ($prop_type = $prop_types["[]"]))
					{
						$one_to_many = $prop_types["o2m"];
						$collection_tab = $prop_types["ct"];

						list($fwd_tab_id, $fwd_tab_ty) = $prop_types["cf"];

						$backref_col = $prop_types["cb"];

						$collection_was_created = false;
						if (!($collection_model = $prop_model->children["[]"]))
						{
							$collection_model = new QSqlParserModel();
							$prop_model->children["[]"] = $collection_model;

							$collection_was_created = true;
						}

						// collection 
						if ($pure_sel && (!$main_q->_is_inner_query) && ($main_q->query_type === "SELECT") && (!$collection_model->was_started))
						{
							// we start a new query
							$collection_model->ps_query = $coll_q = new QSqlParserQuery();
							if ($main_q->_is_inner_query)
								$coll_q->_is_inner_query = true;

							$collection_model->was_started = true;

							$link_to = $main_q;
							// link the collection's query to the current query
							$link_to->extra_q[] = $coll_q;
							$coll_q->parent = $link_to;
							// inherit query type
							$coll_q->query_type = $link_to->query_type;
							$coll_q->is_collection = true;

							// establish alias
							$collection_model->ps_as = $coll_as = $coll_q->getNextTableAlias();
							// the next query is this one
							$next_q = $coll_q;

							if ($starts_new_query)
							{
								$coll_q->as = $collection_model->ps_as;
								// this would create a bug for: SELECT COUNT(Items.Id) AS ItemsCount,Items.{*,Product.*} WHERE Items IS_A MyCompany\Ecomm\Model\OrderItem
								// does removing this creates a bug in other cases ?
								// $collection_model->as = $coll_q->as;
							}
							$collection_model->table = $collection_tab;

							$coll_q->collection_as = $coll_as;

							$coll_q->collection_table = $collection_tab;
							$coll_q->parts[0] = array("SELECT", " ");
							$coll_q->parts[1][] = "FROM ".qEscTable($collection_tab)." AS `{$coll_as}`";

							if ($pure_sel)
								$coll_q->pure_joins[$coll_as] = $collection_tab;

							// propagate the PS query to avoid creating a new one
							$collection_model->sharePsQuery($coll_q, $coll_as);

							// TO DO : is this needed
							if (!$prop_model->was_q_set)
							{
								$from_q->f_actions[] = $prop_model;
								$prop_model->was_q_set = true;
							}

							if (!$from->action_make)
							{
								$from_tab_inf = $type_inf["#%tables"][$from->table];
								$from_tab_id = $from_tab_inf["id"];
								$from_tab_ty = $from_tab_inf["ty"];

								$from->action_make = array($from_as, $from_tab_id, $from_tab_ty);
								if (!$from->was_q_set)
								{
									$from_q->f_actions[] = $from;
									$from->was_q_set = true;
								}
							}

							// action_collections gather ids' for the IN
							if (!$from->action_collections)
							{
								$from->action_collections = array($coll_q);
								if (!$from->was_q_set)
								{
									$from_q->f_actions[] = $from;
									$from->was_q_set = true;
								}
							}
							else if (!in_array($coll_q, $from->action_collections, true))
							{
								$from->action_collections[] = $coll_q;
								if (!$from->was_q_set)
								{
									$from_q->f_actions[] = $from;
									$from->was_q_set = true;
								}
							}

							// ensure backref column is selected
							$coll_q->cols[$coll_as][$coll_as.".".$backref_col] = $backref_col;

							$coll_q->collection_bk = $backref_col;
							$coll_q->collection_types = $prop_types;

							if ($prop_type["#"])
							{
								// has reference type
								$collection_model->action_make = array($coll_as, $fwd_tab_id, $fwd_tab_ty);

								$coll_q->cols[$coll_as][$coll_as.".".$fwd_tab_id] = $fwd_tab_id;
								if (is_string($fwd_tab_ty))
									$coll_q->cols[$coll_as][$coll_as.".".$fwd_tab_ty] = $fwd_tab_ty;
							}

							$coll_rowid = ($one_to_many ? $fwd_tab_id : $prop_types["cid"]);
							$coll_q->cols[$coll_as][$coll_as.".".$coll_rowid] = $coll_rowid;
							$collection_model->action_set = array($coll_as.".".$backref_col, $c_prop, $coll_as.".".$coll_rowid);

							if (!$collection_model->was_q_set)
							{
								$coll_q->f_actions[] = $collection_model;
								$collection_model->was_q_set = true;
							}

							// this is the case when our collection is limited to certain types
							if (!is_array($prop_types["cf"][1]))
							{
								// $coll_q->limit_to_types = $prop_types;
								$coll_ref_col = $prop_types["cf"][1];
								$possib_ids = [];
								//qvardump($prop_types);
								// if we have references set them up here
								if ($prop_types["[]"]["refs"])
								{
									foreach ($prop_types["[]"]["refs"] as $_tmp_ty)
									{
										$_tmp_ty_id = $storage->getTypeIdInStorage($_tmp_ty);
										if ($_tmp_ty_id)
											$possib_ids[$_tmp_ty_id] = $_tmp_ty_id;
									}
									$coll_q->extra_where[] = "`{$coll_as}`.`{$coll_ref_col}`".(next($possib_ids) ? " IN (".implode(",", $possib_ids).")" : "=".reset($possib_ids));
								}
								else if ($prop_types["[]"]["$"])
								{
									// just do nothing here - the algorighm will contine
								}
									
								
							}
						}
						else if ((!($pure_sel && ($main_q->query_type === "SELECT") && (!$main_q->_is_inner_query))) && (!$collection_model->was_joined))
						{
							# $main_q->_is_inner_query
							
							$coll_q = $from_q;
							$coll_as = $from_q->getNextTableAlias();

							$from_tab_inf = $type_inf["#%tables"][$from->table];
							$from_tab_id = $from_tab_inf["id"];
							$from_tab_ty = $from_tab_inf["ty"];

							$collection_model->as = $coll_as;

							$join_sql = " LEFT OUTER JOIN ".qEscTable($collection_tab)." AS `{$coll_as}` ON (`{$from_as}`.`{$from_tab_id}`=`{$coll_as}`.`{$backref_col}`)";
							// var_dump($join_sql);
							// echo "WE JOIN 1TOM {$c_prop}/{$pure_sel}: ".$join_sql."<br/>\n";
							if ($pure_sel)
								$coll_q->pure_joins[$coll_as] = $collection_tab;

							$coll_q->parts[1][] = $join_sql;

							$collection_model->was_joined = true;
							$collection_model->table = $collection_tab;
						}
						else
						{
							$coll_q = ($pure_sel && $collection_model->ps_query) ? $collection_model->ps_query : $main_q;
							$coll_as = ($pure_sel && $collection_model->ps_as) ? $collection_model->ps_as : $collection_model->as;
							$collection_tab = $collection_model->table;
							
							if ($pure_sel)
								$next_q = $coll_q;
						}

						if ($is_last)
						{
							// $coll_q = ($pure_sel && $collection_model->ps_query) ? $collection_model->ps_query : $main_q;
							// $coll_as = ($pure_sel && $collection_model->ps_as) ? $collection_model->ps_as : $collection_model->as;
							// var_dump("is last", $pure_sel, $followed_by_IS_A, $coll_as, $coll_q->as);

							if ($prop_type["#"])
							{
								// has reference type
								$collection_ref = $prop_types["cf"];

								$coll_refr_as = $coll_q->getNextAlias();
								//var_dump($coll_q->as, array($coll_as, $collection_ref[0], $coll_refr_as, $collection_ref[1], true));
								// $select_props[$from_q->as][$c_prop][$from_as.".".$ref_column] = array($from_as, $ref_column, $prop_model->as."_\$r", $ref_types, true);
								$select_props[$coll_q->as][$c_prop][] = array($coll_as, $collection_ref[0], $coll_refr_as, $collection_ref[1], true);

								$queries[$coll_q->as] = $coll_q;
							}
							if ($prop_type["\$"])
							{
								$collection_val = $prop_types["cv"];

								$coll_val_as = $coll_q->getNextAlias();

								if ($followed_by_IS_A)
									throw new Exception("Not implemented");

								if ($pure_sel)
									$collection_model->action_scalar = array($coll_val_as, $collection_val[1]);

								if (($dims = $prop_types["dims"]) && ($_use_dim = QModel::GetDim(reset($dims))))
								{
									$cn_sufix = "_".$_use_dim;
									$select_props[$coll_q->as][$c_prop][$coll_as.".".$collection_val[0].$cn_sufix] = array($coll_as, $collection_val[0].$cn_sufix, $coll_val_as);
								}
								else
									$select_props[$coll_q->as][$c_prop][$coll_as.".".$collection_val[0]] = array($coll_as, $collection_val[0], $coll_val_as);
								$queries[$coll_q->as] = $coll_q;
							}
						}

						$types_join_inf = array();
						// setup a link on the parent : to be defined
						// setup a action_make
						if ((!$one_to_many) && (!$is_last) && $prop_type["j"])
						{
							list($ref_column, $ref_types) = $prop_types["cf"];

							foreach ($prop_type["j"] as $j_table => $j_types)
							{
								$join_key = $coll_as."/{$c_prop}/".$j_table;
								if (!$joins[$join_key])
								{
									$table_as = $from_q->getNextTableAlias();

									$ref_class = reset($j_types);
									$ref_type_inf = self::GetTypesCache($ref_class);

									$join_tab_inf = $ref_type_inf["#%tables"][$j_table];
									$join_tab_id = $join_tab_inf["id"];
									$join_tab_ty = $join_tab_inf["ty"];

									$extra_on_sql = null;

									if (is_string($join_tab_ty) && is_string($ref_types))
									{
										$extra_on_sql = "`{$coll_as}`.`{$ref_types}`=`{$table_as}`.`{$join_tab_ty}`";
									}
									else if (is_string($join_tab_ty))
									{
										$extra_on_sql = "`{$table_as}`.`{$join_tab_ty}`=".reset($ref_types);
									}
									else if (is_string($ref_types))
									{
										$extra_on_sql = "`{$coll_as}`.`{$ref_types}`=".reset($join_tab_ty);
									}

									$join_sql = "LEFT OUTER JOIN ".qEscTable($j_table)." AS `{$table_as}` ON (`{$coll_as}`.`{$ref_column}`=`{$table_as}`.`{$join_tab_id}`".($extra_on_sql ? " AND ".$extra_on_sql : "").")";
									// echo "WE JOIN M2M {$c_prop}/{$pure_sel}: ".$join_sql."<br/>\n";
									if ($pure_sel)
										$coll_q->pure_joins[$table_as] = $j_table;

									$coll_q->parts[1][] = $join_sql;

									// var_dump($join_sql);
									$coll_q->cols[$table_as]["{$table_as}.{$join_tab_id}"] = $join_tab_id;
									if (is_string($join_tab_ty))
										$coll_q->cols[$table_as]["{$table_as}.{$join_tab_ty}"] = $join_tab_ty;

									foreach ($j_types as $j_ty)
										$types_join_inf[$j_ty] = array($j_table, $table_as, $join_tab_id, $join_tab_ty);
								}
							}
						}

						if ((!$is_last) && $prop_type["refs"])
						{
							foreach ($prop_type["refs"] as $mngd_ty)
							{
								if ($starts_new_query || (!($ty_model = $collection_model->children[$mngd_ty])))
								{
									$ty_model = new QSqlParserModel();
									if (!$starts_new_query)
										$collection_model->children[$mngd_ty] = $ty_model;
									$ty_model->type = $mngd_ty;
								}

								if ($one_to_many)
								{
									$j_table = $collection_tab;
									$table_as = $coll_as;
									$join_tab_id = $fwd_tab_id;
									$join_tab_ty = $fwd_tab_ty;
								}
								else
								{
									list ($j_table, $table_as, $join_tab_id, $join_tab_ty) = $types_join_inf[$mngd_ty];
								}

								if ($pure_sel)
								{
									if (!$table_as)
									{
										if (\QAutoload::GetDevelopmentMode())
											var_dump($one_to_many);
										throw new Exception("Not ok 1");
									}
									$ty_model->ps_as = $table_as;
									$ty_model->action_make = array($table_as, $join_tab_id, $join_tab_ty);

									$ty_model->ps_query = $coll_q;
									if ($one_to_many)
									{
										$ty_model->ps_as = $coll_as;
										$ty_model->table = $collection_tab;
									}
									else
									{
										$ty_model->ps_as = $table_as;
										$ty_model->table = $j_table;
									}

									if (!$ty_model->was_q_set)
									{
										$ty_model->was_q_set = true;
										$coll_q->f_actions[] = $ty_model;
									}

									if (!$ty_model->as)
										$ty_model->as = $ty_model->ps_as;

									if (!($ty_model->ps_as && $ty_model->table && $ty_model->ps_query))
										throw new Exception("Not ok 2");
								}
								else
								{
									if ($one_to_many)
									{
										$ty_model->as = $coll_as;
										$ty_model->table = $collection_tab;
									}
									else
									{
										$ty_model->as = $table_as;
										$ty_model->table = $j_table;
									}

									if (!($ty_model->as && $ty_model->table))
									{
										if (\QAutoload::GetDevelopmentMode())
											var_dump($one_to_many, $coll_as, $collection_tab);
										throw new Exception("Not ok 2");
									}
								}

								if (!in_array($ty_model, $types_mngd, true))
									$types_mngd[] = $ty_model;
							}
						}
					}

					// HANDLE SCALARS
					if (($prop_type = $prop_types["\$"]))
					{
						$ref_column = $prop_types["vc"];
						$ref_types = $prop_types["rc_t"];
						// set the column to be set in place of the identifier

						if ($followed_by_IS_A)
							throw new Exception("Not implemented");

						if (($dims = $prop_types["dims"]) && ($_use_dim = QModel::GetDim(reset($dims))))
						{
							$cn_sufix = "_".$_use_dim;
							$select_props[$from_q->as][$c_prop][$from_as.".".$ref_column.$cn_sufix] = array($from_as, $ref_column.$cn_sufix, $prop_model->as);
						}
						else
							$select_props[$from_q->as][$c_prop][$from_as.".".$ref_column] = array($from_as, $ref_column, $prop_model->as);

						//var_dump("Sel prop {$from_k} :: {$c_prop} ", $select_props[$from_q->as][$c_prop][$from_as.".".$ref_column]);
						if ($pure_sel)
						{
							// in case of a pure select we also need to setup the actions
							$from->action_prop[$c_prop]["\$"] = $prop_model->as;

							if (is_string($ref_types))
							{
								$from->action_prop[$c_prop]["ty"] = "{$from_as}.{$ref_types}";
								// tell the query object to also select the type column
								$from_q->cols[$from_as]["{$from_as}.{$ref_types}"] = $ref_types;
							}
							else
								$from->action_prop[$c_prop]["ty"] = $ref_types;

							if (!$from->was_q_set)
							{
								$from->was_q_set = true;
								$from_q->f_actions[] = $from;
							}
						}
					}

					// HANDLE REFERENCES
					if (($prop_type = $prop_types["#"]))
					{
						// reference
						$types_join_inf = array();

						$ref_column = $prop_types["rc"];
						$ref_types = $prop_types["rc_t"];

						if ($pure_sel)
						{
							// Set a create action : will also link the property
							if (is_string($ref_types))
							{
								$from->action_prop[$c_prop]["ty"] = "{$from_as}.{$ref_types}";
								$from_q->cols[$from_as]["{$from_as}.{$ref_types}"] = $ref_types;
							}
							else
								$from->action_prop[$c_prop]["ty"] = $ref_types;

							$from->action_prop[$c_prop]["#"] = $from_as.".".$prop_model->as."_\$r";
							$from_q->cols[$from_as]["{$from_as}.{$prop_model->as}_\$r"] = $ref_column;

							if (!$from->was_q_set)
							{
								$from->was_q_set = true;
								$from_q->f_actions[] = $from;
							}
						}

						if ($is_last)
						{
							// select it
							if ($is_last_with_Id && (!$inner_query))
								$select_props[$from_q->as][$c_prop][$from_as.".".$ref_column] = array($from_as, $ref_column, $prop_model->as."_\$r");
							else
								$select_props[$from_q->as][$c_prop][$from_as.".".$ref_column] = array($from_as, $ref_column, $prop_model->as."_\$r", $ref_types, true);
						}
						else
						{
							// if it's not a reference we will need to make the joins
							foreach ($prop_types["j"] as $j_table => $j_types)
							{
								// aliases are now unique for the entire QQuery
								$join_key = $from_as."/{$c_prop}/".$j_table;
								// avoid making the join too many times
								if (!$joins[$join_key])
								{
									$table_as = $from_q->getNextTableAlias();

									$ref_class = reset($j_types);
									$ref_type_inf = self::GetTypesCache($ref_class);

									$join_tab_inf = $ref_type_inf["#%tables"][$j_table];
									$join_tab_id = $join_tab_inf["id"];
									$join_tab_ty = $join_tab_inf["ty"];

									$extra_on_sql = null;

									// if both tables have types columns
									if (is_string($join_tab_ty) && is_string($ref_types))
									{
										$extra_on_sql = "`{$from_as}`.`{$ref_types}`=`{$table_as}`.`{$join_tab_ty}`";
									}
									// if the joined table has type column
									else if (is_string($join_tab_ty))
									{
										$extra_on_sql = "`{$table_as}`.`{$join_tab_ty}`=".reset($ref_types);
									}
									// if the parent/from table has type column
									else if (is_string($ref_types))
									{
										$extra_on_sql = "`{$from_as}`.`{$ref_types}`=".reset($join_tab_ty);
									}

									$join_sql = "LEFT OUTER JOIN ".qEscTable($j_table)." AS `{$table_as}` ON (`{$from_as}`.`{$ref_column}`=`{$table_as}`.`{$join_tab_id}`".($extra_on_sql ? " AND ".$extra_on_sql : "").")";
									// echo "JOIN SCALAR: {$c_prop}/{$pure_sel}: ".$join_sql."<br/>\n";
									if ($pure_sel)
										$from_q->pure_joins[$table_as] = $j_table;

									// attach join to query
									$from_q->parts[1][] = $join_sql;

									// select the relevant columns also
									$from_q->cols[$table_as]["{$table_as}.{$join_tab_id}"] = $join_tab_id;
									if (is_string($join_tab_ty))
										$from_q->cols[$table_as]["{$table_as}.{$join_tab_ty}"] = $join_tab_ty;

									// save join info for all types that are in this JOIN
									foreach ($j_types as $j_ty)
										$types_join_inf[$j_ty] = array($j_table, $table_as, $join_tab_id, $join_tab_ty);

									$joins[$join_key] = $types_join_inf;
								}
								else
								{
									// echo "WE DID NOT JOIN {$c_prop} <br/>";
									$types_join_inf = $joins[$join_key];
								}
							}

							// setup type children and managed types
							foreach ($prop_types["refs"] as $mngd_ty)
							{
								if ((!($ty_model = $prop_model->children[$mngd_ty])))
								{
									$ty_model = new QSqlParserModel();
									// if (!$starts_new_query)
									$prop_model->children[$mngd_ty] = $ty_model;
									$ty_model->type = $mngd_ty;

									if ($pure_sel)
									{
										list($ty_model->table, $ty_model->ps_as, $join_tab_id, $join_tab_ty) = $types_join_inf[$mngd_ty];
										$ty_model->ps_query = $from_q;

										if (!$ty_model->as)
											$ty_model->as = $ty_model->ps_as;

										$ty_model->action_make = array($ty_model->ps_as, $join_tab_id, $join_tab_ty);

										if (!$ty_model->ps_as)
										{
											if (\QAutoload::GetDevelopmentMode())
												var_dump($mngd_ty, $types_join_inf[$mngd_ty]);
											throw new Exception("Not ok 3");
										}
									}
									else
									{
										list($ty_model->table, $ty_model->as, $join_tab_id, $join_tab_ty) = $types_join_inf[$mngd_ty];

										if (!$ty_model->as)
											throw new Exception("Not ok 3");
									}
								}
								else if (!$pure_sel)
								{
									// in any case update some vital info !
									list($ty_model->table, $ty_model->as, $join_tab_id, $join_tab_ty) = $types_join_inf[$mngd_ty];

									if (!$ty_model->as)
										throw new Exception("Not ok 3.1");
								}
								if (!in_array($ty_model, $types_mngd, true))
									$types_mngd[] = $ty_model;
							}
						}
					}

				}
				while (($all_props && next($type_inf) && ($c_prop = key($type_inf))) || ($caption_props && ($c_prop = next($caption_props))));
			}
		}
		
		// $select_props : [$query_identifier][$property_name]
		// var_dump($select_props);
		$is_not_insert = ($main_q->query_type !== "INSERT");
		
		if ($select_props && (!$preceded_by_AS))
		{
			// echo "===================<br/>\n";
			/*
			 * select the properties
			 */
			foreach ($select_props as $query_alias => $prop_data)
			{
				$p_query = $queries[$query_alias];
				// removes commas from the right side of the query
				if ($pure_sel)
					QSqlParserModel::RtrimComma($p_query->parts[$p_query->p_index]);

				// $force_comma = false;
				$pp_comma_zone = ($p_query->p_index !== 2) && ($p_query->p_index !== 4);
				
				// to do: I have just added $pure_sel in the condition, let's hope it's right
				// it is not because we TRIM comma !
				$prepend_comma = $pure_sel && $p_query->pp_comma && $pp_comma_zone;
				
				// do we need colasce ?!
				foreach ($prop_data as $p_data)
				{
					if ($prepend_comma)
						$p_query->parts[$p_query->p_index][] = ",";
					//var_dump($p_name, $p_data, $pure_sel, $p_query->p_index);
				
					$coalesce = next($p_data) ? true : false;
					$col_data = reset($p_data);
					
					// needed to keep track of commas
					$prop_alias = null;
					
					
					$inner_coma = false;
					$inner_sql = [];
					
					do
					{
						$inner_sql_itm = [];
						
						list($table_alias, $col_name, $prop_alias, $col_type_inf, $is_ref) = $col_data;
						if (($is_ref && (!$is_last_with_Id)) && (($p_query->query_type !== "SELECT") || (!$pure_sel)))
						{
							$ref_wkr_handled = false;
							if ($p_query->p_index === 0)
							{
								// @todo ... this is a bit dirty and will break in complex expressions
								// we will have to use $toks and look around a bit, 
								// we also need to check that this is the element to be set, that means we are BEFORE the '='
								
								$key = $ini_key = key($toks);
								
								// is it followed by equal ?
								list($idf_next, $curr_i, $curr_s) = $toks[$key];
								// skip whitespaces
								if ($curr_s)
									list($idf_next, $curr_i, $curr_s) = $toks[++$key];
								if ($idf_next === "=")
								{
									$ref_wkr_handled = true;
									
									$splice_pos = $key + 1;
									
									$expression_arr = [];
									do
									{
										// now we need to identify the expression after and duplicate it
										list($expression, $curr_i, $curr_s) = $toks[++$key];
										$expression_arr[] = $toks[$key];
										// skip whitespaces
										if ($curr_s)
										{
											list($expression, $curr_i, $curr_s) = $toks[++$key];
											$expression_arr[] = $toks[$key];
										}
										
										if ($expression === "(")
											throw new Exception("Only simple expressions are parsed for reference assignments");
										
										$up_exp = strtoupper($expression);
									}
									while (($expression !== null) && ($expression !== ",") && ($up_exp !== "WHERE") && 
												($up_exp !== "GROUP BY") && ($up_exp !== "HAVING") && ($up_exp !== "ORDER BY") && ($up_exp !== "LIMIT"));
									array_pop($expression_arr);
									$saved_expr = $expression_arr;
									
									if (!$expression_arr)
										throw new Exception("Parse error. No expression was identified.");
									
									// `$Reference`^ = SUBSTRING_INDEX('5168,20', ",", -1),
									array_unshift($expression_arr, ["SUBSTRING_INDEX("]);
									$expression_arr[] = [","];
									$expression_arr[] = ["','"];
									$expression_arr[] = [","];
									$expression_arr[] = ["-1"];
									$expression_arr[] = [")"];
									
									if (is_string($col_type_inf))
									{
										$expression_arr[] = [","];
										$expression_arr[] = [($is_not_insert ? "`{$table_alias}`." : "")."`{$col_type_inf}`"];
										$expression_arr[] = ["="];
										$expression_arr[] = ["SUBSTRING_INDEX("];
										foreach ($saved_expr as $se)
											$expression_arr[] = $se;
										$expression_arr[] = [","];
										$expression_arr[] = ["','"];
										$expression_arr[] = [","];
										$expression_arr[] = ["1"];
										$expression_arr[] = [")"];
									}
									
									array_splice($toks, $splice_pos, count($saved_expr), $expression_arr);
									array_jump($toks, $ini_key);
									
									$inner_sql_itm[] = ($inner_coma ? "," : "").($is_not_insert ? "`{$table_alias}`." : "")."`{$col_name}`";
								}
							}
							if (!$ref_wkr_handled)
							{
								$ref_wkr_col_name = ($is_not_insert ? "`{$table_alias}`." : "")."`{$col_name}`";
								
								$inner_sql_itm[] = ($inner_coma ? "," : "").
											"IF ({$ref_wkr_col_name}, CONCAT(".(is_string($col_type_inf) ? ($is_not_insert ? "`{$table_alias}`." : "")."`{$col_type_inf}`" : "'".reset($col_type_inf)."'").
												", ',', {$ref_wkr_col_name}), 0)";
							}
						}
						else
						{
							$inner_sql_itm[] = ($inner_coma ? "," : "").($is_not_insert ? "`{$table_alias}`." : "")."`{$col_name}`";
						}
						if ($followed_by_IS_A)
						{
							if (is_string($col_type_inf))
								$inner_sql_itm[] = " AND `{$table_alias}`.`{$col_type_inf}` ";
							else
								$inner_sql_itm[] = " AND '".reset($col_type_inf)."' ";
						}
						if ($inner_sql_itm)
							$inner_sql[implode('', $inner_sql_itm)] = $inner_sql_itm;
						
						// yes ! we need it false!
						$inner_coma = false;
					}
					while(($col_data = next($p_data)));
					
					if ($coalesce && (count($inner_sql) > 1))
						$p_query->parts[$p_query->p_index][] = " COALESCE(";
					
					$inner_coma = false;
					foreach ($inner_sql as $is_list)
					{
						if ($inner_coma)
							$p_query->parts[$p_query->p_index][] = ",";
						foreach ($is_list as $is_l)
							$p_query->parts[$p_query->p_index][] = $is_l;
						$inner_coma = true;
					}
					
					if ($coalesce && (count($inner_sql) > 1))
						$p_query->parts[$p_query->p_index][] = ") ";
					
					if ($pure_sel && $is_not_insert)
						$p_query->parts[$p_query->p_index][] = " AS `{$prop_alias}`";
					
					$prepend_comma = $p_query->pp_comma = $pp_comma_zone;
				}
			}
		
			//echo "===================<br/>\n";
		}
		else if ($pure_sel)
		{
			// we have not selected anything we should trim the comma
			QSqlParserModel::RtrimComma($main_q->parts[$main_q->p_index]);
		}
		
		// return;
	}
	
	protected static function initForType(QSqlParserModel $sql_type, $skip_security)
	{
		// start
		/*if (!$skip_security)
		{
			
			$extra_entities = null;
			$type = $sql_type->type;
			$sql_type->security = QModel::$SecurityApplySQLFilters ? $type::SecurityFilter(QUser::GetCurrent(), QPermsFlagRead, null, $extra_entities) : true;
			
		}*/
		
		/*if ((!$skip_security) && (($sql_type->security === false) || ($sql_type->security === null)))
			throw new Exception("You don't have read rights on: ".$sql_type->type);*/
		
		$is_collection = ($sql_type->inst instanceof QIModelArray);

		$sql_type->query = $sql_type->ps_query = $q = new QSqlParserQuery();
		
		$type_inf = self::GetTypesCache($sql_type->type);

		$q->parts[0][] = "SELECT";
		$q->parts[0][] = " ";
		$sql_type->as = $sql_type->ps_as = $as = $q->as = $q->getNextTableAlias();
		$q->as = $sql_type->as;

		$q->_tmpid ?: ($q->_tmpid = QSqlParserQuery::$TmpId++);

		$tab = $type_inf["#%table"];
		
		$sql_type->table = $tab;

		$q->parts[1][] = "FROM";
		$q->parts[1][] = " ".qEscTable($tab)." AS `{$as}` ";
		
		$q->pure_joins[$as] = $tab;

		$q->inst = $sql_type->inst;
		
		// add action: create instance
		if (!$sql_type->inst)
		{
			throw new Exception("TO DO: we need to link create info on \$sql_type");
		}
		
		// #%id
		list($col_id_name, $col_id_ty) = $type_inf["#%id"];
		$q->col_id_name = $col_id_name;

		if (is_string($col_id_ty))
		{
			$q->cols[$as]["{$as}.{$col_id_ty}"] = $col_id_ty;
			$sql_type->_ty_col_as = "{$as}.{$col_id_ty}";
		}

		// $q->actions[$as]["c"][$sql_type->type] = $sql_type;
		
		$q->cols[$as][$as.".".$col_id_name] = $col_id_name;
		$sql_type->id_col = $as.".".$col_id_name;
		
		if ($is_collection)
		{
			// we need to step for it as collection
			$collection_sql = new QSqlParserModel();
			$collection_sql->children["[]"] = $sql_type;
			$collection_sql->inst = $sql_type->inst;
			
			$sql_type->action_make = array($as, $col_id_name, $col_id_ty);
			$sql_type->action_set = array("{$as}.{$col_id_name}", $sql_type->property->name, "{$as}.{$col_id_name}");
			$sql_type->action_set_arr = $sql_type->inst;
			
			if (!$sql_type->was_q_set)
			{
				$q->f_actions[] = $sql_type;
				$sql_type->was_q_set = true;
			}
			 
			$sql_type->inst = null;
		}
		
		return array($sql_type->type => $sql_type);
	}

	public static function GetTypesCache($class_name)
	{
		if (($ref_type_inf = self::$TypesCache[$class_name]))
			return $ref_type_inf;
		$path = QAutoload::GetRuntimeFolder()."temp/sql/".qClassToPath($class_name).".type.php";
		if (file_exists($path))
		{
			include(QAutoload::GetRuntimeFolder()."temp/sql/".qClassToPath($class_name).".type.php");
			return self::$TypesCache[$class_name] = $Q_SQL_TYPE_CACHE;
		}
		return null;
	}
	
	public static function PopulateDataBlock($frm_arr, $sql_items, &$dataBlock, $storage)
	{
		if ((!$frm_arr) || (!$sql_items))

		foreach ($sql_items as $item_id => $item_b)
		{
			foreach ($item_b as $ty_id => $item)
			{
				if ($item_id !== null)
					$dataBlock[$item_id][$ty_id] = $item;
				
				$query_model = $frm_arr[get_class($item)];
				if ($query_model && $query_model->children)
					static::PopulateDataBlockRecursive($query_model->children, $item, $dataBlock, $storage);
			}
		}
	}
	
	protected static function PopulateDataBlockRecursive($frm_arr, $item, &$dataBlock, $storage)
	{
		foreach ($frm_arr as $frm)
		{
			if (($p = $frm->property) && (($v = $item->{$p}) instanceof \QIModel) && (($v_id = $v->getId()) !== null))
			{
				$dataBlock[$v_id][$storage->getTypeIdInStorage(get_class($v))] = $v;
				if ($frm->children)
					static::PopulateDataBlockRecursive($frm->children, $v, $dataBlock, $storage);
			}
			else if (($t = $frm->type))// && (($v = $item->{$p}) instanceof \QIModel) && (($v_id = $v->getId()) !== null))
			{
				// collection or type 
				if ($frm->children)
				{
					if (is_array($item) || ($item instanceof \QIModelArray))
					{
						foreach ($item as $v)
						{
							static::PopulateDataBlockRecursive($frm->children, $v, $dataBlock, $storage);
						}
					}
					else
						throw new \Exception("to do");
				}
			}
		}
	}
	
	protected static function handle_Predicate_Modifiers(string $predicate, \QSqlParserQuery $main_q, \QSqlParserQuery $q, array &$toks = null, $froms = null, $storage = null)
	{
		// 'WHERE'
		list($curr, $curr_i, $curr_s) = next($toks);
		
		// skip whitespaces
		if ($curr_s)
			list($curr, $curr_i, $curr_s) = next($toks);
		
		if ($curr !== '[')
		{
			// roll back
			prev($toks);
			prev($toks);
			return;
		}
		
		// ok, we've got some work to do
		
		// skip [
		list($curr, $curr_i, $curr_s) = next($toks);
		
		$depth = 0;
		$key = null;
		$target_q = [];
		// $target_q[] = ['Offers', 'Offers'];
		// $target_q[] = ['.'];
		// $target_q[] = [','];
		// $target_q[] = ['{'];
		
		while (($curr !== ']') || ($depth !== 0))
		{
			if (!$curr_s)
			{
				if ($curr === '=')
				{
				}
				else if ($curr === ';')
					$key = null;
				else if ($key === null)
				{
					$key = $curr;
				}
				else if ($key === 'target')
				{
					$target_q[] = current($toks);
				}
			}
			
			list($curr, $curr_i, $curr_s) = next($toks);
		}
		
		if ($target_q)
		{
			$target_q[] = ["."];
			$target_q[] = ["{"];
			// $target_q[] = ["Id", "Id"];
			$target_q[] = [" ", "", " "];
			$target_q[] = [$predicate];
			$key = key($toks);
			$c_toks = count($toks);
			
			
			// we need to handle this the right way
			/*
			WHERE [target=OfferPrices]
							PriceProfile.Id=7
			 */
			
			$brackets_depth = 0;
			
			for ($i = $key + 1; $i < $c_toks; $i++)
			{
				$curr = $toks[$i][0];
				if ($curr && (substr($curr, -1, 1) === '('))
					$brackets_depth++;
				else if ($curr && ($curr === ')'))
					$brackets_depth--;
				// move up to the first
				// "}" "SELECT" "WHERE" "GROUP BY" "HAVING" "ORDER BY" "LIMIT"
				else if (($brackets_depth === 0) && (($curr === '}') || ($curr === 'SELECT') || ($curr === 'WHERE') ||
						($curr === 'GROUP BY') || ($curr === 'HAVING') || ($curr === 'ORDER BY') || 
						($curr === 'LIMIT')))
				{
					break;
				}
				next($toks);
				$target_q[] = $toks[$i];
			}
			
			$zone = 0;
			// $toks, $from = null, QSqlParserQuery $main_q = null, $storage = null, $zone = 0, $skip_security = false, $filter_selector
			// OfferPrices
			// &$toks, $from = null, QSqlParserQuery $main_q = null, $storage = null, $zone = 0, $skip_security = false, $filter_selector = null
			reset($target_q);
			static::BuildQuery($target_q, $froms, $q, $storage, $zone, true, null, true);
			
			return true;
		}
	}
	
	protected static function handle_Inner_Select(\QSqlParserQuery $main_q, array &$toks = null, $froms = null, $storage = null, $skip_security = false, int $zone = 0)
	{
		// 'WHERE'
		$pos = key($toks);
		list($curr, $curr_i, $curr_s) = $toks[$pos - 1];
		
		// skip whitespaces
		if ($curr_s)
			list($curr, $curr_i, $curr_s) = $toks[$pos - 2];
		
		if ($curr && (substr($curr, -1, 1) === '('))
		{
			// we've got it !
		}
		else
			return false;
		
		// extract the inner query
		// The query starts from the top
	
		$depth = 1; // we have one bracket already
		$key = null;
		$target_q = [];
		
		// make sure we skip select
		$tok = next($toks);
		// we need to stop when $depth === 0
		do
		{
			$curr = $tok[0];
			if ($curr && (substr($curr, -1, 1) === '('))
				$depth++;
			else if ($curr && ($curr === ')'))
				$depth--;
			
			if ($depth === 0)
			{
				break;
			}
			$target_q[] = $tok;
		}
		while (($tok = next($toks)));
		
		$from_type = \QApp::GetDataClass();
		$from_inst = \QApp::NewData();
		
		$frm = new QSqlParserModel();
		$frm->inst = $from_inst;
		$frm->type = $from_type;
		
		$frm_arr = self::initForType($frm, $skip_security);
		$z_main_q = $frm->query;
		$z_main_q->_is_inner_query = true;
		
		// public function BuildQuery(&$toks, $from = null, QSqlParserQuery $main_q = null, $storage = null, $zone = 0, $skip_security = false, $filter_selector = null, bool $debug = false)
		static::BuildQuery($target_q, $frm_arr, $z_main_q, $storage, 0, true, null, false, true);
		
		$inner_query_str = $z_main_q->toSql().") ";
		
		$main_q->parts[$zone][] = $inner_query_str;
		
		return true;
	}
}
