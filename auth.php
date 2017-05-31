<?php
/*
NGINX sends headers as
Auth-User: somuser
Auth-Pass: somepass
On my php app server these are seen as
HTTP_AUTH_USER and HTTP_AUTH_PASS
*/
if (!isset($_SERVER["HTTP_AUTH_USER"] ) || !isset($_SERVER["HTTP_AUTH_PASS"] )){
  fail();
}

$userip=$_SERVER["REMOTE_ADDR"];
$username=$_SERVER["HTTP_AUTH_USER"] ;
$userpass=$_SERVER["HTTP_AUTH_PASS"] ;
$protocol=$_SERVER["HTTP_AUTH_PROTOCOL"] ;
$auth_method=$_SERVER["HTTP_AUTH_METHOD"];
if (isset($_SERVER["HTTP_AUTH_SALT"]))
{
	$auth_salt=$_SERVER["HTTP_AUTH_SALT"];
}
else
{
	$auth_salt="none";
}

$mysql_server="localhost";
$mysql_username="your_dbuser";
$mysql_password="your_dbpass";
$mysql_database="your_dbname";

// default backend port
$backend_port=110;

if ($protocol=="imap") {
  $backend_port=143;
}

if ($protocol=="smtp") {
  $backend_port=25;
}

// NGINX likes ip address so if your
// application gives back hostname, convert it to ip address here
$backend_ip["mailhost01"] ="your mail server's IP";
//$backend_ip["mailhost02"] ="your mail server's IP";

// Authenticate the user or fail
if (!authuser($username,$userpass,$auth_method,$auth_salt,$mysql_server,$mysql_username,$mysql_password,$mysql_database)){
  fail($username,$protocol,$auth_method,$userip);
  exit;
}

// Get the server for this user if we have reached so far
$userserver=getmailserver($username);

// Get the ip address of the server
// We are assuming that you backend returns hostname
// We try to get the ip else return what we got back
$server_ip=(isset($backend_ip[$userserver]))?$backend_ip[$userserver] :$userserver;

// Pass!
pass($server_ip, $backend_port,$username,$protocol,$auth_method,$userip);

//END

function authuser($user,$pass,$auth_method,$auth_salt,$mysql_server,$mysql_username,$mysql_password,$mysql_database){
  // password characters encoded by nginx:
  // " " 0x20h (SPACE)
  // "%" 0x25h
  // see nginx source: src/core/ngx_string.c:ngx_escape_uri(...)
  $pass = str_replace('%20',' ', $pass);
  $pass = str_replace('%25','%', $pass);

	$conn = new mysqli($mysql_server, $mysql_username, $mysql_password, $mysql_database);
    if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
    }

	if ($auth_method == "plain")
	{
		$pwd_check = $conn->query("SELECT username, password FROM ".$mysql_database." WHERE username = '".$user."' AND  password = '".$pass."'");
        if (mysqli_num_rows($pwd_check) > 0 )
        {
			$conn->close(); 
  			return true;
		}
		else
		{
			$conn->close();
			return false;
		}
	}
	elseif ($auth_method == "cram-md5")
	{
		$pass_plain = $conn->query("SELECT password FROM ".$mysql_database." WHERE username = '".$user."'")->fetch_row()[0];
		$pass_crypt = hash_hmac('md5',$auth_salt,$pass_plain,FALSE);
		if ($pass_crypt == $pass)
        {
            $conn->close();
            return true;
        }
        else
        {
            $conn->close();
            return false;
        }

	}
}

function getmailserver($user){
  // put the logic here to get the mailserver
  // backend for the user. You can get this from
  // some database or ldap etc
  // dummy logic, all users that start with a,c,f and g get mailhost01
  // the others get mailhost02
//  if in_array(substr($user,0,1), array("a", "c", "f", "g")){
//    return "mailhost01";
//  } else {
//    return "mailhost02";
//  }
	return "mailhost01";

}

function fail($username,$protocol,$auth_method,$userip){
  header("Auth-Status: Invalid login or password");

	file_put_contents("/var/log/nginx/auth.log", date("Y/m/d h:i:s")." ".$userip." ".$username." ".$protocol." ".$auth_method." AUTH failed\n", FILE_APPEND);

  exit;
}

function pass($server,$port,$username,$protocol,$auth_method,$userip){
  header("Auth-Status: OK");
  header("Auth-Server: $server");
  header("Auth-Port: $port");

	file_put_contents("/var/log/nginx/auth.log", date("Y/m/d h:i:s")." ".$userip." ".$username." ".$protocol." ".$auth_method." AUTH ok\n", FILE_APPEND);

  exit;
}
