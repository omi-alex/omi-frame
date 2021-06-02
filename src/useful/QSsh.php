<?php

class QSsh
{
	protected $connection;
	protected $host;
	protected $port = 22;
	
	protected $user;
	protected $pass;
	
	public function __construct(string $host, string $user, string $pass = null, int $port = null)
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		if ($port !== null)
			$this->port = $port;
	}
	
	public function connect($pass = null)
	{
		$pass = ($pass !== null) ? $pass : $this->pass;
		
		$this->connection = ssh2_connect($this->host, $this->port);
		if ($this->connection === false)
			return false;
		
		$auth = ssh2_auth_password($this->connection, $this->user, $this->pass);
		if ($auth === false)
		{
			unset($this->connection);
			return false;
		}
		
		return true;
	}
	
	public function exec(string $command, &$error = null)
	{
		$ssh_connection = $this->connection;
		if (!$this->connection)
			return false;

		$stream = ssh2_exec($ssh_connection, $command, true);
		stream_set_blocking($stream, true);
		$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
		$stream_err = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

		$t_stream_out = stream_get_contents($stream_out);
		$error = stream_get_contents($stream_err);

		return $t_stream_out;
	}
	
	public function upload(string $local_path, string $remote_path, int $create_mode = null)
	{
		$ssh_connection = $this->connection;
		if (!$this->connection)
			return false;
		
		if ($create_mode !== null)
			return ssh2_scp_send($ssh_connection, $local_path, $remote_path, $create_mode);
		else
			return ssh2_scp_send($ssh_connection, $local_path, $remote_path);
	}
	
	protected function isLocalDirExec($dir)
	{
		$out = exec("[ -d \"{$dir}\" ] && echo \"yes\"");
		return (strtolower(trim($out)) === "yes");
	}
	
	protected function isDir(string $dir)
	{
		$error = null;
		$out = $this->exec("[ -d \"{$dir}\" ] && echo \"yes\"", $error);
		return (strtolower(trim($out)) === "yes");
	}
	
	public function copyFolder(string $source_folder, string $destination, string $set_owner = null, string $set_group = null)
	{
		$ssh_connection = $this->connection;
		if (!$this->connection)
			return false;
		
		$error = null;

		if (!$this->isDir($destination))
		{
			$out = $this->exec("mkdir ".rtrim($destination, "/\\"), $error);
		}
		if ($set_owner)
		{
			if (!$set_group)
				$set_group = $set_owner;
			$out = $this->exec("chown {$set_owner}:{$set_group} ".$destination, $error);
		}
		$files = scandir($source_folder);
		foreach ($files as $file)
		{
			if (($file === ".") || ($file === ".."))
				continue;
			$fp = $source_folder.$file;
			if (is_dir($fp))
				$this->copyFolder($fp."/", $destination.$file."/", $set_owner, $set_group);
			else if (is_file($fp))
			{
				$out = $this->exec("cp \"{$fp}\" \"".$destination.$file."\"", $error);
				$out = $this->exec("chmod 0644 \"".$destination.$file."\"", $error);
				
				if ($set_owner)
				{
					if (!$set_group)
						$set_group = $set_owner;
					$out = $this->exec("chown {$set_owner}:{$set_group} ".$destination.$file, $error);
				}
			}
		}
	}
}
