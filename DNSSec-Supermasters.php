<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// ======== Settings

define('API_KEY', '<API KEY HERE THAT IS USED TO COMMUNICATE WITH POWERPANEL>');
define('BACKEND', 'mysql');

//For now only mysql is supported
if(BACKEND == 'mysql') {
	// Usually found in: /etc/powerdns/pdns.d/pdns.mysql.conf
	// Insert all the values in here

	define('BACKEND_HOST', '127.0.0.1'); //Standard: 127.0.0.1
	define('BACKEND_USER', 'pdns');
	define('BACKEND_PASSWORD', 'Abcdefghijklmnopqrstu12345678');
	define('BACKEND_DBNAME', 'pdns'); //Standard: pdns
}

define('SUDO_NEEDED', true); //Does "sudo" need to be in front of every command? Standard: true

//You can choose to allow/disallow IP addresses
$allowed_ip_addresses = array(
	'52.214.1.187' // api.powerpanel.io
);
if(isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ip_addresses)) {
	Api::stop('Unauthorized IP', 401);
}

// add this to /etc/sudoers (or use sudo visudo)
// www-data   ALL = NOPASSWD: /usr/bin/pdns_control, /bin/which, /usr/bin/pdnsutil, /user/bin/pdnssec


//Nginx example config:

/*
server {
	listen 8082;
	listen [::]:8082 ipv6only=on;

	root /var/www;
	index index.php index.html index.htm;

	server_name _;

	access_log /var/log/nginx/access.log;
	error_log /var/log/nginx/error.log error;

	location / {
		try_files $uri $uri/ =404;
	}

	location ~ \.php$ {
		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		# NOTE: You should have "cgi.fix_pathinfo = 0;" in php.ini
		fastcgi_pass unix:/var/run/php5-fpm.sock;
		fastcgi_index index.php;
		include fastcgi_params;
	}
}
*/

// ========= Code. Do not change anything if you don't know what you're doing

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

$body = trim(@file_get_contents('php://input'));
$json_body = json_decode($body, true);

if($json_body === null) {
	http_response_code(401);
	Api::stop("Not a JSON body", 404);
}

if( is_array($json_body) && isset($json_body['api_key']) && $json_body['api_key'] == API_KEY) {

	// === test

	if($json_body['command'] == 'test') {

		Debugger::set("test function....");
		$pdns = new PowerDns;
		if($pdns->connect() === false) {
			Debugger::set("No connection to PowerDNS");
			Api::stop("No connection to PowerDNS", 404);
		}
		Api::finish("Test successful");
	}

	// === add Supermasters

	if($json_body['command'] == 'addSupermasters') {

		Debugger::set("Adding supermasters....");
		$pdns = new PowerDns;
		if($pdns->connect() === false) {
			Debugger::set("No connection to PowerDNS");
			Api::stop("No connection to PowerDNS", 404);
		}

		if(isset($json_body['supermasters']) && $pdns->addSuperMasters( $json_body['supermasters'] )) {
			//ok
			Api::finish("Supermasters added");
		}
		else {
			if(!isset($json_body['supermasters'])) {
				Debugger::set("Field supermasters was not given");
			}
			Api::stop("Could not add supermasters", 400);
		}
	}

	// === Notify

	elseif($json_body['command'] == 'notify') {
		if(!isset($json_body['domainname'])) {
			Debugger::set("Field domainname was not given");
		}
		else {
			$pdns = new PowerDns;
			if($pdns->notify( $json_body['domainname'] )) {
				Api::finish("Notify scheduled");
			}
		}
		Api::stop("Could not run notify", 400);
	}

	// === Rectify zone

	elseif($json_body['command'] == 'rectifyZone') {
		if(!isset($json_body['domainname'])) {
			Debugger::set("Field domainname was not given");
		}
		else {
			$pdns = new PowerDns;
			if($pdns->notify( $json_body['domainname'] )) {
				Api::finish("rectify-zone done");
			}
		}
		Api::stop("Could not run rectifyZone", 400);
	}

	// === enable Dnssec

	elseif($json_body['command'] == 'enableDnssec') {
		if(!isset($json_body['domainname'])) {
			Debugger::set("Field domainname was not given");
		}
		else {
			$pdns = new PowerDns;
			if($pdns->enableDnssec( $json_body['domainname'] )) {

				$get_key = $pdns->getDnssecKey( $json_body['domainname'] );

				if($get_key != '') {
					Api::finish($get_key);
				}
				else {
					Api::stop("Was not able to get the Dnssec key", 400);
				}
			}
		}
		Api::stop("Could not run enableDnssec", 400);
	}

	// === disable Dnssec

	elseif($json_body['command'] == 'disableDnssec') {
		if(!isset($json_body['domainname'])) {
			Debugger::set("Field domainname was not given");
		}
		else {
			$pdns = new PowerDns;
			$get_key = $pdns->disableDnssec( $json_body['domainname'] );

			if($get_key != '') {
				Api::finish("Ok. Disabled");
			}
			else {
				Api::stop("Was not able to disable DNSSEC for this domain", 400);
			}
		}
		Api::stop("Could not run disableDnssec", 400);
	}

	// === getDnssecKey

	elseif($json_body['command'] == 'getDnssecKey') {
		if(!isset($json_body['domainname'])) {
			Debugger::set("Field domainname was not given");
		}
		else {
			$pdns = new PowerDns;
			$get_key = $pdns->getDnssecKey( $json_body['domainname'] );

			if($get_key != '') {
				Api::finish($get_key);
			}
			else {
				Api::stop("Was not able to get the Dnssec key", 400);
			}
		}
		Api::stop("Could not run getDnssecKey", 400);
	}
	else {
		//Give a 404 not found
		Api::stop("No command given", 404);
	}
}
else {
	//Unauthorized
	Api::stop('Unauthorized', 401);
}

class Api {

	private static $filepath = null;

	public static function stop($line, $code) {
		http_response_code($code);

		die( json_encode( array('status' => 'error', 'message' => $line, 'debugging' => Debugger::get()), JSON_PRETTY_PRINT ) );
	}

	public static function finish($line) {
		die( json_encode( array('status' => 'ok', 'message' => $line, 'debugging' => Debugger::get()), JSON_PRETTY_PRINT ) );
	}

	public static function isCommand($command_name = '') {
		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if($command_name == 'pdns_control') {
			$x = Api::doProc($sudo.'which pdns_control');

			if(isset($x[0]) && strpos($x[0], 'no tty present') !== false) {
				Debugger::set("Could not execute pdns_control ping. ".$x[0]);
				return false;
			}

			if(isset($x[0]) && strpos($x[0], 'pdns_control') !== false) {
				$filepath = $x[0];
				if(file_exists($filepath)) {
					//Ok, we made sure this file exists.

					$x = Api::doProc($sudo.'pdns_control ping');

					if(isset($x[0]) && $x[0] == 'PONG') {
						self::$filepath = $filepath;
						return true;
					}
					else {
						Debugger::set("Could not execute pdns_control. Permissions?");
						return false;
					}
				}
			}
		}
		elseif($command_name == 'pdnssec') {
			$x = Api::doProc($sudo.'which pdnssec');

			if(isset($x[0]) && strpos($x[0], 'no tty present') !== false) {
				Debugger::set("Could not execute pdnssec. ".$x[0]);
				return false;
			}

			if(isset($x[0]) && strpos($x[0], 'pdnssec') !== false) {
				$filepath = $x[0];
				if(file_exists($filepath)) {
					//Ok, we made sure this file exists.

					$x = Api::doProc($sudo.'pdnssec -h');

					if(isset($x[0]) && $x[0] == 'Usage:') {
						self::$filepath = $filepath;
						return true;
					}
					else {
						Debugger::set("Could not execute pdnssec. Permissions?");
						return false;
					}
				}
			}
		}
		elseif($command_name == 'pdnsutil') {
			$x = Api::doProc($sudo.'which pdnsutil');

			if(isset($x[0]) && strpos($x[0], 'no tty present') !== false) {
				Debugger::set("Could not execute pdnsutil. ".$x[0]);
				return false;
			}

			if(isset($x[0]) && strpos($x[0], 'pdnsutil') !== false) {
				$filepath = $x[0];
				if(file_exists($filepath)) {
					//Ok, we made sure this file exists.

					$x = Api::doProc($sudo.'pdnsutil -h');

					if(isset($x[0]) && $x[0] == 'Usage:') {
						self::$filepath = $filepath;
						return true;
					}
					else {
						Debugger::set("Could not execute pdnsutil. Permissions?");
						return false;
					}
				}
			}
			else {
				Debugger::set("Could not find pdnsutil");
				return false;
			}
		}
		else {
			Debugger::set("No command passed. Something went wrong");
			return false;
		}
	}

	public static function doProc($cmd, $trimlines = true) {

		$result = array();

		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w") // stderr is a file to write to
		);

		$pipes = array();
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (is_resource($process)) {
			while ($f = fgets($pipes[1])) {
				//-stdout--->;
				if( $trimlines === true && trim($f) == '' ) {
					//Not going to insert empty lines
				}
				else {
					$result[] = trim($f);
				}
			}
			fclose($pipes[1]);
			while ($f = fgets($pipes[2])) {
				//-strerr--->;
				if( $trimlines === true && trim($f) == '' ) {
					//Not going to insert empty lines
				}
				else {
					$result[] = trim($f);
				}
			}
			fclose($pipes[2]);
			proc_close($process);
		}
		return $result;
	}
}


class Debugger {

	private static $lines = array();

	public static function set($line) {
		self::$lines[] = $line;
	}

	public static function get() {
		return self::$lines;
	}

}

class PowerDns {

	private $class = null;

	public function __construct() {

	}

	public function __destruct() {

	}

	public function connect() {

		if(BACKEND == 'mysql') {
			$this->class = new PdnsMysql;
			return $this->class->connect();
		}
		else {
			Debugger::set("Connection method not supported");
			return false;
		}
	}

	public function addSuperMasters($supermasters = array()) {

		if(!is_array($supermasters)) {
			Debugger::set("Supermasters is not a valid array");
			return false;
		}
		return $this->class->addSuperMasters($supermasters);
	}

	private function cleanURL($url){
	  $url = preg_replace("/(^(http(s)?:\/\/|www\.))?(www\.)?([a-z-\.0-9]+)/","$5", trim($url));
	  if(preg_match("/^([a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6})/", $url, $domain)){
	    return $domain[1];
	  }
	  else return "invalid.tld";
	}

	public function enableDnssec($domainname = 'invalid.tld') {

		$domainname = $this->cleanURL($domainname);

		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if(Api::isCommand('pdns_control') === false) {
			Debugger::set("Couldn't execute pdns_control, check your permissions");
			return false;
		}

		if(Api::isCommand('pdnssec')) {
			//version 3
			$cmd = Api::doProc($sudo.'pdnssec secure-zone '.$domainname);

			$success = false;
			foreach($cmd AS $cmd_line) {
				if( strpos($cmd_line, 'Zone ') !== false && strpos($cmd_line, 'secure') !== false ) { //Zone example.org secured || Zone 'example.org' secured
					$success = true;
				}
				if( strpos($cmd_line, 'already secure') !== false ) { //Zone 'example.org' already secure, remove keys with pdnsutil remove-zone-key if needed
					$success = true;
				}
			}

			if($success === true) {
				//Api::doProc($sudo.'pdnssec increase-serial '.$domainname);
				//Api::doProc($sudo.'pdns_control notify '.$domainname);
			}

			return $success;
		}
		else {
			if(Api::isCommand('pdnsutil')) {
				Api::doProc($sudo.'pdnsutil disable-dnssec '.$domainname);
				//Disable force

				//Create a KSK
				$cmd = Api::doProc($sudo.'pdnsutil add-zone-key '.$domainname.' ksk 2048 active rsasha256');
				//Create a ZSK
				Api::doProc($sudo.'pdnsutil add-zone-key '.$domainname.' zsk 1024 active rsasha256');
				//Create an inactive ZSK for future rollover
				Api::doProc($sudo.'pdnsutil add-zone-key '.$domainname.' zsk 1024 inactive rsasha256');

				$success = false;
				foreach($cmd AS $cmd_line) {
					if( strpos($cmd_line, 'Added a KSK with algorithm') !== false) {
						//Added a KSK with algorithm = 8, active=1
						//Requested specific key size of 2048 bits
						$success = true;
					}
				}

				if($success === true) {
					//Api::doProc($sudo.'pdnsutil increase-serial '.$domainname);
					//Api::doProc($sudo.'pdns_control notify '.$domainname);
				}

				return $success;
			}
			else {
				Debugger::set("Both pdnssec + pdnsutil commands were not found or have no access");
				return false;
			}
		}
	}

	public function disableDnssec($domainname = 'invalid.tld') {

		$domainname = $this->cleanURL($domainname);

		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if(Api::isCommand('pdnssec')) {
			//version 3
			$cmd = Api::doProc($sudo.'pdnssec disable-dnssec '.$domainname);

			if( isset($cmd[0]) && $cmd[0] == 'Zone is not secured' ) {
				Debugger::set("Dnssec was already disabled");

				Api::doProc($sudo.'pdnssec increase-serial '.$domainname);
				Api::doProc($sudo.'pdns_control notify '.$domainname);
				return true;
			}
			elseif( $cmd == array() ) {
				//Empty array, nothing returns
				Api::doProc($sudo.'pdnssec increase-serial '.$domainname);
				Api::doProc($sudo.'pdns_control notify '.$domainname);
				return true;
			}
			else {
				//TODO: check show-zone and see if dnssec is disabled...

				Debugger::set("Unknown response. Not sure if Dnssec is disabled now: ". print_r($cmd,true));
				return false;
			}
		}
		else {
			if(Api::isCommand('pdnsutil')) {
				$cmd = Api::doProc($sudo.'pdnsutil disable-dnssec '.$domainname);

				if( isset($cmd[0]) && $cmd[0] == 'Zone is not secured' ) {
					Debugger::set("Dnssec was already disabled");

					Api::doProc($sudo.'pdnsutil increase-serial '.$domainname);
					Api::doProc($sudo.'pdns_control notify '.$domainname);
					return true;
				}
				elseif( $cmd == array() ) {
					//Empty array, nothing returns
					Api::doProc($sudo.'pdnsutil increase-serial '.$domainname);
					Api::doProc($sudo.'pdns_control notify '.$domainname);
					return true;
				}
				else {
					//TODO: check show-zone and see if dnssec is disabled...

					Debugger::set("Unknown response. Not sure if Dnssec is disabled now: ". print_r($cmd,true));
					return false;
				}
			}
			else {
				Debugger::set("Both pdnssec + pdnsutil commands were not found or have no access");
				return false;
			}
		}
	}

	public function getDnssecKey($domainname = 'invalid.tld') {

		$domainname = $this->cleanURL($domainname);

		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if(Api::isCommand('pdnssec')) {
			//version 3
			$cmd = Api::doProc($sudo.'pdnssec show-zone '.$domainname." | grep 'DNSKEY' | head -n1 | awk -F ' ' '{print $10}'");
			if( isset($cmd[0]) && (strlen($cmd[0]) > 80) && (!preg_match('/\s/', $cmd[0])) ) {
				return trim($cmd[0]);
			}
			else {
				return null;
			}
		}
		else {
			if(Api::isCommand('pdnsutil')) {
				$cmd = Api::doProc($sudo.'pdnsutil show-zone '.$domainname." | grep 'DNSKEY' | head -n1 | awk -F ' ' '{print $10}'");
				if( isset($cmd[0]) && (strlen($cmd[0]) > 80) && (!preg_match('/\s/', $cmd[0])) ) {
					return trim($cmd[0]);
				}
				else {
					return null;
				}
			}
			else {
				Debugger::set("Both pdnssec + pdnsutil commands were not found or have no access");
				return null;
			}
		}
	}

	public function rectifyZone($domainname = 'invalid.tld') {

		$domainname = $this->cleanURL($domainname);

		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if(Api::isCommand('pdnssec')) {
			//version 3
			$cmd = Api::doProc($sudo.'pdnssec rectify-zone '.$domainname);
			if(isset($cmd[0]) && $cmd[0] == 'Adding NSEC ordering information') {
				return true;
			}
			elseif(isset($cmd[0]) && $cmd[0] == 'Adding empty non-terminals for non-DNSSEC zone') {
				return true;
			}
			else {
				return false;
			}
		}
		else {
			if(Api::isCommand('pdnsutil')) {
				$cmd = Api::doProc($sudo.'pdnssec rectify-zone '.$domainname);
				if(isset($cmd[0]) && $cmd[0] == 'Adding NSEC ordering information') {
					return true;
				}
				elseif(isset($cmd[0]) && $cmd[0] == 'Adding empty non-terminals for non-DNSSEC zone') {
					return true;
				}
				else {
					return false;
				}
			}
			else {
				Debugger::set("Both pdnssec + pdnsutil commands were not found or have no access");
				return false;
			}
		}
	}

	public function notify($domainname = 'invalid.tld') {

		$domainname = $this->cleanURL($domainname);

		$sudo = '';
		if(SUDO_NEEDED === true) {
			$sudo = 'sudo ';
		}

		if(Api::isCommand('pdns_control')) {
			$cmd = Api::doProc($sudo.'pdns_control notify '.$domainname);

			if(isset($cmd[0]) && $cmd[0] == 'Added to queue') {
				return true;
			}
			else {
				Debugger::set("Got wrong result back:". print_r($cmd,true));
				return false;
			}
		}
		else {
			Debugger::set("Couldn't execute the command(s), check your permissions");
			return false;
		}
	}
}

class PdnsMysql {

	private $connection = null;
	private $result = null;

	public function __construct() {

	}

	public function __destruct() {

	}

	public function connect() {

		if(!extension_loaded ('PDO') || !defined('PDO::ATTR_DRIVER_NAME')) {
			Debugger::set("PDO Extension not installed for PHP");
			return false;
		}

		if(!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
			Debugger::set("MySQL PDO driver not installed for PHP (apt-get install php5-mysql)");
			return false;
		}

		if($this->connection) {
			return $this->connection;
		}
		else {
			try {
				$this->connection = new PDO('mysql:dbname='.BACKEND_DBNAME.';host='.BACKEND_HOST,BACKEND_USER,BACKEND_PASSWORD,
					array(
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
						PDO::ATTR_PERSISTENT => false
						//PDO::ATTR_EMULATE_PREPARES => false,
						//PDO::ATTR_TIMEOUT => 5 //seconds
					)
				);
				//$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				return $this->connection;
			}
			catch(PDOException $e) {
				Debugger::set( $e->getMessage() );
				$this->connection = null;
				return false;
			}
		}
	}

	private function closeConnection() {
		$this->connection = null;
	}

	private function setError($error) {
		$this->error = $error;
	}

	public function getError() {
		return $this->error;
	}

	private function query($sqlstring, $args = array(), $connection_count = 0) {
		if(!is_object($this->connection)) {
			//No connection? Make a connection.
			$this->connect();
		}
		if(is_object($this->connection)) {
			try {
				$statement = $this->connection->prepare($sqlstring);
				$statement->setFetchMode(PDO::FETCH_ASSOC);

				if( $count = $statement->execute($args) ) {
					$this->rows_affected = $count;
					$this->insertedId = $this->connection->lastInsertId();

					//Do not use fetAll() in combination with PDO::ERRMODE_EXCEPTION, it will create an Exception.
					$this->setResult( $statement->fetchAll() );
					return true;
				}
				else {
					$this->setResult( array() );
					$error = $statement->errorInfo();
					$this->insertedId = null;

					//Check if the error has connection lost (MySQL server has gone away) Try again:
					if( ($error[0] == 'HY000') && ($error[1] == 2006) ) {
						if( $connection_count > 2 ){
							return false;
						}
						else {
							$this->closeConnection();
							usleep(100000); //0.1 sec
							$this->connect();
							usleep(100000); //0.1 sec
							return $this->query($sqlstring, $args, $connection_count++);
						}
					}
					else {
						$this->setError($error);
					}
					return false;
				}
			}
			catch(PDOException $e) {
				Debugger::set( $e->getMessage() );
				return false;
			}
		}
		else {
			return false;
		}
	}

	private function setResult($result) {
		$this->result = $result;
	}

	private function getResult($one_result = false) {
		if($one_result === true) {
			return isset($this->result[0]) ? $this->result[0] : array();
		}
		return $this->result;
	}

	public function addSuperMasters(array $supermasters = array()) {

		//First we check if we can SELECT supermasters
		if($this->query("SELECT * FROM supermasters LIMIT 1")) {

			$added = false;

			foreach($supermasters AS $supermaster) {
				if(!isset($supermaster['name']) || trim($supermaster['name']) == '' || !isset($supermaster['ip_address']) || trim($supermaster['ip_address']) == '') {
					continue;
				}
				$this->query("SELECT * FROM `supermasters` WHERE `nameserver` = :nameserver", array(':nameserver' => trim($supermaster['name'])));
				$get_result = $this->getResult(true);
				if(count($get_result) == 0) {
					//Doesn't exist, insert
					if($this->query("INSERT INTO `supermasters` (`ip`, `nameserver`) VALUES (:ip, :nameserver)", array(':ip' => $supermaster['ip_address'], ':nameserver' => $supermaster['name']))) {
						Debugger::set("Supermaster ".$supermaster['name']." added");
						$added = true;
					}
				}
				else {
					//Already exists, update IP address
					if($this->query("UPDATE `supermasters` SET `ip` = :ip WHERE `nameserver` = :nameserver", array(':ip' => $supermaster['ip_address'], ':nameserver' => $supermaster['name']))) {
						Debugger::set("Supermaster ".$supermaster['name']." updated with IP ".$supermaster['ip_address']);
						$added = true;
					}
				}
			}

			if($added == false) {
				Debugger::set("No supermaster were added/updated or something went wrong. See further messages in debugging");
				return false;
			}
			return true;
		}
		else {
			Debugger::set("Couldn't SELECT from supermasters table");
			return false;
		}
	}

}
