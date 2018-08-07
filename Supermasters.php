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

//You can choose to allow/disallow IP addresses
$allowed_ip_addresses = array(
	'52.214.1.187' // api.powerpanel.io
);
if(isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ip_addresses)) {
	Api::stop('Unauthorized IP', 401);
}

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
	elseif($json_body['command'] == 'deleteSupermasters') {

		Debugger::set("Deleting supermasters....");
		$pdns = new PowerDns;
		if($pdns->connect() === false) {
			Debugger::set("No connection to PowerDNS");
			Api::stop("No connection to PowerDNS", 404);
		}

		if(isset($json_body['supermasters']) && $pdns->deleteSuperMasters( $json_body['supermasters'] )) {
			//ok
			Api::finish("Supermasters deleted");
		}
		else {
			if(!isset($json_body['supermasters'])) {
				Debugger::set("Field supermasters was not given");
			}
			Api::stop("Could not delete supermasters", 400);
		}
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

	public function deleteSuperMasters(array $supermasters = array()) {

		//First we check if we can SELECT supermasters
		if($this->query("SELECT * FROM supermasters LIMIT 1")) {

			foreach($supermasters AS $supermaster) {
				if(!isset($supermaster['name']) || trim($supermaster['name']) == '' || !isset($supermaster['ip_address']) || trim($supermaster['ip_address']) == '') {
					continue;
				}
				$this->query("DELETE FROM `supermasters` WHERE `nameserver` = :nameserver", array(':nameserver' => trim($supermaster['name'])));
			}
			Debugger::set("Supermasters were deleted");
			return true;
		}
		else {
			Debugger::set("Couldn't SELECT from supermasters table");
			return false;
		}
	}

}
