<?php

class QDeploy
{
	public static function Run()
	{
		// where do we save the data and how
		
		$data = static::ReadData();
		// var_dump($data);
		
		static::Render($data);
		
		// $file = sha1($user.'@'.$host).".inf";
		// file_put_contents($user.'@'.$host, $user.'\n'.$host."\n".$port);
		
		// prepare everything in one folder
		//		copy
		//		obfuscate all PHP classes
		//		do not upload tempates,urls,php classes that are not needed
		// ignore config.php
		// upload folder
		// trigger DB Struncture update somehow !
		
		// flag that we have handled it
		return true;
	}
	
	public static function Render($data)
	{
		?><!doctype html>
<html>
	<head>
		<title>Deploy</title>
	</head>
	<body>
	<?php
		if (!$data)
		{
			echo "Please setup some data in: ".static::GetPath();
			return;
		}
		else
		{
			?><div style="clear: both;">
				<div style="width: 100px; float: left;">
					<b>Tag</b>
				</div>
				<div style="width: 250px; float: left;">
					<b>Local Path</b>
				</div>
				<div style="width: 150px; float: left;">
					<b>Host</b>
				</div>
				<div style="width: 100px; float: left;">
					<b>User</b>
				</div>
				<div style="width: 250px; float: left;">
					<b>Remote Path</b>
				</div>
				<div style="width: 150px; float: left;">
					<b>Password</b>
				</div>
				<div style="clear: both;" />
		</div>
			<?php
			foreach ($data as $k => $d)
			{
				?>
		<div>
			<form name="f_<?= $k ?>">
				<div style="width: 100px; float: left;">
					<b><?= $k ?></b>
				</div>
				<div style="width: 250px; float: left;">
					<?= htmlspecialchars($d->local) ?>
				</div>
				<div style="width: 150px; float: left;">
					<?= htmlspecialchars($d->host) ?>
				</div>
				<div style="width: 100px; float: left;">
					<?= htmlspecialchars($d->user) ?>
				</div>
				<div style="width: 250px; float: left;">
					<?= htmlspecialchars($d->remote) ?>
				</div>
				<div style="width: 150px; float: left;">
					<input type="text" name="pass" value="" />
				</div>
				<div style="width: 100px; float: left;">
					<input type="submit" name="deploy" value="Deploy" />
				</div>
			</form>
			<div style="clear: both;" />
		</div>
				<?php
			}
		}
		?>
	</body>
</html><?php
	}
	
	public static function GetPath()
	{
		return getenv("HOME")."/.omi-deploy/deploy.inf";
	}
	
	public static function ReadData()
	{
		$path = static::GetPath();
		if (!file_exists($path))
			return false;
		
		$content = file_get_contents($path);
		
		if (!$content)
			return false;
		
		$json = null;
		try
		{
			$json = json_decode($content);
		}
		catch (\Exception $ex)
		{
			// no error
		}
		
		return ($json !== null) ? $json : false;
	}
	
}
