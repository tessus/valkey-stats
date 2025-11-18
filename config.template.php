<?php
// CONFIG: config.php

/*
	Servers are defined as an array
	[ Name, IP/Socket, Port, Password ]

	Name (string):                     name shown in drop-down list (also used for tls options and command mapping)
	IP/Socket (string):                IP address, hostname, or socket of the server
	Port (integer):                    port of server, use -1 for socket
	Password entry (string|array):     credentials for the server (optional)
*/

$servers = [
	[ 'Local', '127.0.0.1', 6379 ],
	[ 'Local socket', 'unix:///var/run/valkey.sock', -1 ],
	[ 'Local with password', '127.0.0.1', 6379, 'password_here' ],
	[ 'Local with user and password', '127.0.0.1', 6379, ['username', 'password_here'] ],
	[ 'TLS connection with user and password', 'tls://localhost', 6379, ['username', 'password_here'] ],
];

$tls = [
	'default-options' => [ // these are the default TLS options
		'verify_peer'       => true,
		'verify_peer_name'  => true,
		'allow_self_signed' => false,
		// see https://www.php.net/manual/en/context.ssl.php#refsect1-context.ssl-options
	],
	'TLS connection with user and password' => [ // must be a server name (first field in server array, name shown in drop-down list)
		'local_cert' => '/path/to/client.pem',
		'local_pk'   => '/path/to/clientkey.pem',
		'cafile'     => '/path/to/cacert.crt',
	]
];

// Show a 'Flush' button for databases
define("FLUSHDB", true);

// Ask for confirmation before flushing database
define("CONFIRM_FLUSHDB", true);

// Show a 'Flush All' button for the instance
define("FLUSHALL", true);

// Ask for confirmation before flushing the entire instance
define("CONFIRM_FLUSHALL", true);

// Position of status line: "bottom" or "top"
define("STATUS_LINE", "bottom");

// Show a 'Check for update' button
define("CHECK_FOR_UPDATE", true);

// Use the php extension, if loaded
define("USE_MODULE_IF_LOADED", true);

// Command Mapping
// Please note that this has been deprecated for years and is not supported when using the extension (module)
$command = [
	'Local'    => [         // must be a server name (first field in server array, name shown in drop-down list)
		'FLUSHDB'  => '',
		'FLUSHALL' => '',
		'AUTH'     => '',
		'INFO'     => '',
	],
];

// debug mode - you don't want to set this!
define("DEBUG", false);

// END CONFIG
