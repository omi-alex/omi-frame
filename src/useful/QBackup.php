<?php

class QBackup
{
	public static function BackupMySqlStorage(\QMySqlStorage $storage = null/*, $skip_tables = null, $explicit_tables = null*/)
	{
		if ($storage === null)
			$storage = \QApp::GetStorage();
		if (!($storage instanceof \QMySqlStorage))
			return false;
		
		$user = $storage->user;
		$pass = $storage->pass;
		$port = $storage->port;
		$host = $storage->host;
		$default_db = $storage->default_db;
		
		$databases_to_backup = [$default_db => $default_db];

		$all_elements = \QSqlModelInfoType::GetTableTypeList();
		foreach ($all_elements as $tb_name)
		{
			if (($p = strrpos($tb_name, ".")) !== false)
			{
				$db_name = trim(substr($tb_name, 0, $p));
				if ($db_name)
					$databases_to_backup[$db_name] = $db_name;
			}
		}

		$command = "mysqldump --single-transaction --default-character-set=utf8 --user='{$user}' --password='{$pass}' ".($host ? " --host={$host} " : "").($port ? " --port={$port} " : "")." --databases ";
		// now normalize things
		foreach ($databases_to_backup as $v)
			$command .= " \"{$v}\" ";
		
		$user_inf = posix_getpwuid(posix_getuid());
		
		$backup_dir = (isset($user_inf["dir"]) ? $user_inf["dir"] : ".")."/_backups".
						(($host && ($host !== "localhost") && ($host !== "127.0.0.1")) ? "/".$host : "").
						"/".$default_db."/";
		$file_name = $backup_dir.date("Y-m-d_H.i.s").".sql.gz";
		if (!is_dir($backup_dir))
			qmkdir ($backup_dir);
		
		$command .= " | gzip > ".$file_name;
		$ret = exec($command);
		exec("chmod 0000 ".$file_name);
		return $ret;
	}
}
