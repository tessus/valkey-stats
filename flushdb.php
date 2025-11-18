<?php
session_start();

if (!isset($_GET['id']) || isset($_GET['id']) && $_GET['id'] != $_SESSION['id'])
{
	$_SESSION['id'] = '';
	die("Invalid request.");
}

if (file_exists(dirname(__FILE__)."/config.php"))
{
    require_once dirname(__FILE__).'/config.php';
}
if (!$servers)
{
	die("No servers in config found.");
}

// Default settings
if (!defined('DEBUG'))
{
	define("DEBUG", false);
}
if (!defined('USE_MODULE_IF_LOADED'))
{
	define("USE_MODULE_IF_LOADED", true);
}

// Process GET request
$server = 0;
if (isset($_GET['s']) && intval($_GET['s']) < count($servers) && intval($_GET['s']) >= -1)
{
	$server = intval($_GET['s']);
}
$serverName = $servers[$server][0];
if (isset($_GET['db']))
{
	$db = intval($_GET['db']);
}
if (isset($_GET['async']))
{
	$async = intval($_GET['async']);
}

// Functions
function debug($var, $pre = true)
{
	if (DEBUG)
	{
		if ($pre) echo "<pre>".PHP_EOL;
		var_dump($var);
		if ($pre) echo "</pre>".PHP_EOL;
	}
}

// Talk to server
$error = null;

// Talk to server
if (extension_loaded("redis") && USE_MODULE_IF_LOADED)
{	// Use PHP extension
	$valkey = new Redis();

	$context = [];
	// build tls context here
	if (str_starts_with($servers[$server][1], 'tls://'))
	{
		$tls_stream = $tls['default-options'];
		if (array_key_exists($servers[$server][0], $tls))
		{
			$tls_stream = array_replace($tls['default-options'], $tls[$servers[$server][0]]);
		}
		$context = [
			'stream' => $tls_stream
		];
	}

	try {
		$rc = $valkey->connect($servers[$server][1], $servers[$server][2], 0, null, 0, 0, $context);
	} catch (Exception $e) {
		$error = $e->getMessage();
		goto extensionEnd;
	}

	if ($rc === false)
	{
		$error = "Cannot connect to database.";
		goto extensionEnd;
	}

	isset($servers[$server][3]) ? $pwdEntry = $servers[$server][3] : $pwdEntry = null;
	if (!is_null($pwdEntry) && !empty($pwdEntry))
	{
		try {
			$valkey->auth($pwdEntry);
		} catch (Exception $e) {
			$error = $e->getMessage();
			goto extensionEnd;
		}
	}

	// flush stuff here
	$SYNC = null;
	if (!$async) // sync flush requires the parameter to be bool(true)
	{
		$SYNC = true;
	}
	if ($db != -1) // one specific database
	{
		$select_rc = $valkey->select($db);
		if ($select_rc === true)
		{
			$flush_rc = $valkey->flushDB($SYNC);
		}
		if ($select_rc === false || $flush_rc === false)
		{
			$error = "Cannot flush db$db.";
		}
	}
	else // entire instance
	{
		$flushall_rc = $valkey->flushAll($SYNC);
		if ($flushall_rc === false)
		{
			$error = "Cannot flush instance.";
		}
	}

	extensionEnd:
	if (!empty($error))
	{
		die($error);
	}
}	// End Use PHP extension
else
{	// Use Raw Socket
	// Command mapping
	$FLUSHDB = 'FLUSHDB';
	if (isset($command[$serverName]['FLUSHDB']) && !is_null($command[$serverName]['FLUSHDB']) && !empty($command[$serverName]['FLUSHDB']))
	{
		$FLUSHDB = $command[$serverName]['FLUSHDB'];
	}
	$FLUSHALL = 'FLUSHALL';
	if (isset($command[$serverName]['FLUSHALL']) && !is_null($command[$serverName]['FLUSHALL']) && !empty($command[$serverName]['FLUSHALL']))
	{
		$FLUSHALL = $command[$serverName]['FLUSHALL'];
	}
	$AUTH = 'AUTH';
	if (isset($command[$serverName]['AUTH']) && !is_null($command[$serverName]['AUTH']) && !empty($command[$serverName]['AUTH']))
	{
		$AUTH = $command[$serverName]['AUTH'];
	}

	$fp = @fsockopen($servers[$server][1], $servers[$server][2], $errno, $errstr, 30);

	$info = array();

	if (!$fp)
	{
		die($errstr);
	}
	else
	{
		$vkCommand = '';
		$ASYNC     = '';
		$numCmds   = 0;

		isset($servers[$server][3]) ? $pwdEntry = $servers[$server][3] : $pwdEntry = null;
		if (!is_null($pwdEntry) && !empty($pwdEntry))
		{
			if (is_array($pwdEntry))
			{
				if (!isset($pwdEntry[1]) || is_null($pwdEntry[1]) || empty($pwdEntry[1]))
				{
					$pwdEntry[1] = '0';
				}
				$credentials = "$pwdEntry[0] $pwdEntry[1]";
			}
			else
			{
				$credentials = $pwdEntry;
			}
			$vkCommand = "$AUTH $credentials\r\n";
			$numCmds++;
		}
		if ($async) // we want async flush
		{
			$ASYNC = ' ASYNC';
		}
		if ($db != -1) // one specific database
		{
			$vkCommand .= "SELECT $db\r\n$FLUSHDB$ASYNC\r\nQUIT\r\n";
			$numCmds += 3;
		}
		else // entire instance
		{
			$vkCommand .= "$FLUSHALL$ASYNC\r\nQUIT\r\n";
			$numCmds += 2;
		}

		fwrite($fp, $vkCommand);
		while (!feof($fp))
		{
			$info[] = trim(fgets($fp));
		}
		fclose($fp);
	}

	for ($i = 0; $i < $numCmds; $i++)
	{
		if ($info[$i] != '+OK')
		{
			debug($vkCommand);
			debug($info);
			die($info[$i]);
		}
	}
}	// End Use Raw Socket

$_SESSION['id'] = '';
echo "Success";
