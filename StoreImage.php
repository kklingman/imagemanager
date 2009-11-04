<?php
  $host = 'localhost';
  $user = 'image';
  $password = 'master';

  // CREATE TOKEN FROM THE REQUEST
  if(isset($_REQUEST['url'])) {
     // replaces spaces in the URL string with %20
     $url = str_replace (" ", "%20", $_REQUEST['url']);
     $token = md5($url);

    mysql_connect($host, $user, $password);
   if (mysql_error())
    {
      header("HTTP/1.1 401 Bad Request");
      header("Status: 401");
      header("Connection: close");

      echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
      echo "<title>401  Unauthorized</title></head>";
      echo "<h1>401 Unauthorized</h1>\n<p>Open failed</p>\n</body></html>";

      exit;
    }
    mysql_select_db('imagemanager');
    if (mysql_error())
    {
      header("HTTP/1.1 400 Bad Request");
      header("Status: 400");
      header("Connection: close");

      echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
      echo "<title>400 Bad Request</title></head>";
      echo "<h1>400 Bad Request</h1>\n<p>Database Error</p>\n</body></html>";

      exit;
    }
    $query = "INSERT INTO image (tinyurl,source_url) values('$token','$url') " .
	"ON DUPLICATE KEY UPDATE source_url='$url'";
    mysql_query($query); 
    if (mysql_error())
    {
      header("HTTP/1.1 400 Bad Request");
      header("Status: 400");
      header("Connection: close");

      echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
      echo "<title>400 Bad Request</title></head>";
      echo "<h1>400 Bad Request</h1>\n<p>Select Error</p>\n</body></html>";

      exit;
    }
     
    header('Content-type: text/html');
      echo "$token\t$url";
  }
  else {
    header('Content-type: text/html');
    echo "Error: No image specified.";
 }
?>

