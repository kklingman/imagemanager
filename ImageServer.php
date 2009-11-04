<?php
/*========================================================================
  Name:  ImageServer.php
  Author: Karl Klingman
  Date: April 7, 2009
  Usage:  http://<host>/ImageService.php?<url>|<token>&[<width=x>]&[<height=x>]
  Description: Returns a scaled image (on the fly) when given either a URL to 
  an image or a Token (tinyurl) for ImageManager managed images.  
  The image will be scaled proportionately if either height or width is 
  specified or cropped if both are provided.
 
  See also ImageManager for how to store images images.
===========================================================================*/
  $database = 'imagemanager';
  $user = 'image';
  $passwd = 'master';
  $host = 'localhost';
  //------------------------------------------
  //HEIGHT AND WIDTH PARAMETERS
  //------------------------------------------
  $param_width  = isset($_REQUEST['width'])
          ?  $_REQUEST['width']  :  0;
  $param_height = isset($_REQUEST['height'])
          ?  $_REQUEST['height']  :  0;
  //--------------------------------------
  // CROPPING PARAMETER
  //--------------------------------------
  $crop = isset($_REQUEST['crop']);
  //--------------------------------------
  // FRAMING PARAMETER
  //--------------------------------------
  $frame = isset($_REQUEST['frame']);
  //------------------------------------
  // DETERMINE THE SCALING SOURCE TYPE
  //------------------------------------
  if (isset($_REQUEST['url'])) {
    $source_url = $_REQUEST['url'];
  }
  elseif (isset($_REQUEST['tinyurl'])) {
    $tinyurl    = $_REQUEST['tinyurl'];
    //------------------------------------------
    // GET THE URL FROM THE DATASTORE
    // *TODO* Add error handling here!!!
    //------------------------------------------
    mysql_pconnect($host, $user, $passwd );
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
    mysql_select_db($database);
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
    

    $q = "SELECT source_url FROM image WHERE tinyurl = '$tinyurl'";
    $result = mysql_query($q); 
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

    $row = mysql_fetch_array($result);
    $source_url = $row['source_url'];
    mysql_free_result($result);
  }
  else {
    header("HTTP/1.1 400 Bad Request");
    header("Status: 400");
    header("Connection: close");

    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
    echo "<title>400 Bad Request</title></head>";
    echo "<h1>400 Bad Request</h1>\n<p>Bad argument</p>\n</body></html>";

    exit;
  }
  //------------------------------- 
  // READ THE IMAGE
  // *TODO* add some better url handling - timeout, etc
  //------------------------------- 
  $stream = @fopen($source_url, "rb");
  if ($stream == false) {
    header("HTTP/1.1 404 Not Found");
    header("Status: 404");
    header("Cache-Control: no-store, no-cache");
    header("Connection: close");

    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
    echo "<title>404 Not Found</title></head>";
    echo "<h1>404 Not Found</h1>\n<p>Not Found: $source_url</p>\n</body></html>";

    exit;
  }
  $image_bytes = stream_get_contents($stream);
  fclose($stream);

// Add 404 process, send no photo found image with no-cache header

  $original_image = @imagecreatefromstring($image_bytes);
  if ($original_image == false) {
    header("HTTP/1.1 422 Unprocessable Entity");
    header("Status: 422");
    header("Cache-Control: no-store, no-cache");
    header("Connection: close");

    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>";
    echo "<title>422 Unprocessable Entity</title></head>";
    echo "<h1>422 Unprocessable Entity</h1>\n<p>Unprocessable Entity</p>\n</body></html>";

    exit;
  }
  $original_width = imagesx($original_image);
  $original_height = imagesy($original_image);
  $off_x = $off_y = 0;
  //-------------------------------
  // DON'T ALLOW IMAGE TO SCALE UP
  //-------------------------------
  if ($param_width > $original_width && $param_height > $original_height)  
  {
    $param_height = 0;
    $param_width = 0;
  }
  if ($param_height > $original_height) {
  	$param_height = $original_height;
  }
  if ($param_width > $original_width) {
  	$param_width = $original_width;
  }
  //------------------------------- 
  // SCALE THE IMAGE
  //------------------------------- 
  //---------------------------------
  // Display original sized image
  //---------------------------------
  if ($param_width == 0 && $param_height == 0) {
      display_original_image($original_image, $original_width, $original_height);
  }
  //---------------------------------
  // Display image scaled by width
  //---------------------------------
  elseif ($param_width != 0 && $param_height == 0)  {
     display_scaled_by_width($original_image, $original_width, $original_height, $param_width, $param_height);
  }
  //---------------------------------
  // Display image scaled by height
  //---------------------------------
  elseif($param_width == 0 && $param_height != 0) {
     display_scaled_by_height($original_image, $original_width, $original_height, $param_width, $param_height);
  }
  //-----------------------------------------
  // Display image scaled by width and height
  //-----------------------------------------
  else  { 
    //-------------------------
    // NO CROP OR FRAME
    //-------------------------
    if (!$frame && !$crop) {
      display_within_boundry(
	$original_image,
	$original_width,
	$original_height,
	$param_width,
	$param_height); 
    } 
    //-------------------------
    // CROP NO FRAME
    //-------------------------
    elseif($frame == false && $crop == true) { 
      display_crop_no_frame(
	$original_image,
	$original_width,
	$original_height,
	$param_width,
	$param_height);	
    }
    //-------------------------
    // FRAME NO CROP
    //-------------------------
    elseif($frame && !$crop) { 
      display_frame_no_crop (
	$original_image,
	$original_width,
	$original_height,
	$param_width,
	$param_height);	
    }
    //-------------------------
    // CROP AND FRAME
    //-------------------------
   elseif($frame && $crop) {
      display_crop_and_frame (
	$original_image,
	$original_width,
	$original_height,
	$param_width,
	$param_height);	
   }
 }

function ImageTrueColorToPalette2 ($original_image, $dither, $ncolors)
{
  $width = imagesx ($original_image);
  $height = imagesy ($original_image);
  $colors_handle = ImageCreateTrueColor ($width, $height);
  imagecopymerge ($colors_handle, $original_image, 0, 0, 0, 0, $width, $height, 100);
  ImageTrueColorToPalette ($original_image, $dither, $ncolors);
  ImageColorMatch ($colors_handle, $original_image);
  ImageDestroy ($colors_handle);
  return $original_image;
}
  //---------------------------------------
  // Display the original Image
  //---------------------------------------
 function display_original_image($original_image, $original_width, $original_height) {
//error_log("display_original_image");
    //$scaled_image = imagecreatetruecolor($original_width, $original_height);
    //imagecopyresampled($scaled_image, $original_image, 0,0,0,0,$original_width, 
    // $original_height, $original_width, $original_height);

// JLR
//    imagetruecolortopalette($original_image, true, 255);
    ImageTrueColorToPalette2 ($original_image, true, 255);
    header('Content-type: image/png');
    imagepng($original_image);
 }

  //---------------------------------------
  // Display scaled by height
  //---------------------------------------
  function display_scaled_by_height(
			$original_image, 
			$original_width, 
			$original_height, 
			$param_width,
      $param_height) {
    $new_height = $param_height;
    $new_width = floor($original_width * ($param_height / $original_height));
    $scaled_image = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($scaled_image, $original_image, 0,0,0,0,$new_width, 
      $new_height, $original_width, $original_height);
// JLR
//    imagetruecolortopalette($scaled_image, true, 255);
    ImageTrueColorToPalette2 ($scaled_image, true, 255);
    header('Content-type: image/png');
    imagepng($scaled_image);
  }
  //---------------------------------------
  // Display scaled by width
  //---------------------------------------
  function display_scaled_by_width(
			$original_image,
 	  	$original_width,
 		  $original_height,
      $param_width,
			$param_height) {
//error_log("display_scaled_by_width");
    $new_width = $param_width;
    $new_height = floor($original_height * ($param_width / $original_width));
    $scaled_image = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($scaled_image, $original_image, 0,0,0,0,$new_width, 
      $new_height, $original_width, $original_height);
// JLR
//    imagetruecolortopalette($scaled_image, true, 255);
    ImageTrueColorToPalette2 ($scaled_image, true, 255);
    header('Content-type: image/png');
    imagepng($scaled_image);
 } 
 //---------------------------------------
 // Display within HxW boundary
 //---------------------------------------
 function display_within_boundry (
	    $original_image,
 		  $original_width,
 	    $original_height,
 	    $param_width,
 	    $param_height) {
    
//error_log("display_within_boundary");
      $width_ratio = $param_width / $original_width;
      $height_ratio = $param_height / $original_height;
      if ($height_ratio < $width_ratio)  {
         $new_width = $original_width  *  $height_ratio;
         $new_height = $original_height * $height_ratio; 
      } else  {
         $new_width = $original_width * $width_ratio;
         $new_height = $original_height * $width_ratio;
      }
    $scaled_image = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($scaled_image, $original_image, 0,0,0,0,$new_width, 
      $new_height, $original_width, $original_height);
// JLR
//    imagetruecolortopalette($scaled_image, true, 255);
    ImageTrueColorToPalette2 ($scaled_image, true, 255);
    header('Content-type: image/png');
    imagepng($scaled_image);

 }

 //---------------------------------------
 // Display with cropping but no frame
 //---------------------------------------
 function display_crop_no_frame (
 		$original_image,
		$original_width,
		$original_height,
		$param_width,
		$param_height) {
//error_log("display_crop_no_frame");
      // Find viewport center crop ratio
      $ratio = $original_width / $param_width > $original_height / $param_height
        ?  $original_height / $param_height : $original_width / $param_width;
      $cropped_width   =  floor($ratio * $param_width);
      $cropped_height   =  floor($ratio * $param_height);
      $off_x =  floor(($original_width - $cropped_width) / 2);
      $off_y =  floor(($original_height - $cropped_height) / 2);
      $original_width = $cropped_width;
      $original_height = $cropped_height;
      $new_width  = $param_width;
      $new_height = $param_height;
      $scaled_image = imagecreatetruecolor($new_width, $new_height);
      imagecopyresampled($scaled_image, $original_image, 0,0,$off_x,$off_y,$new_width, 
      $new_height, $original_width, $original_height);
// JLR
//      imagetruecolortopalette($scaled_image, true, 255);
      ImageTrueColorToPalette2 ($scaled_image, true, 255);
      header('Content-type: image/png');
      imagepng($scaled_image);
  }  
 //---------------------------------------
 // Display with framing but no cropping
 //---------------------------------------
 function display_frame_no_crop (
 		$original_image,
		$original_width,
		$original_height,
		$param_width,
		$param_height) {

//error_log("display_frame_no_crop");
    //----------------------------------------
    // Make the scaled image fit into the boundaries         
    //----------------------------------------
     $width_ratio = $param_width / $original_width;
     $height_ratio = $param_height / $original_height;
     if ($height_ratio < $width_ratio)  {
       $new_width = $original_width  *  $height_ratio;
       $new_height = $original_height * $height_ratio; 
     } else  {
       $new_width = $original_width * $width_ratio;
       $new_height = $original_height * $width_ratio;
     }
      $scaled_image = imagecreatetruecolor($new_width, $new_height);
      imagecopyresampled($scaled_image, $original_image,0,0,0,0,$new_width, 
      $new_height, $original_width, $original_height);
// JLR
//      imagetruecolortopalette($scaled_image, true, 255);
      ImageTrueColorToPalette2 ($scaled_image, true, 255);
    //----------------------------------------
    // Create the background image
    //----------------------------------------
    $background_image = imagecreatetruecolor($param_width, $param_height);
    $black = imagecolorallocate($background_image, 0, 0, 0);
    imagecolortransparent ($background_image, $black);
// JLR
//    imagetruecolortopalette($background_image, true, 255);
    ImageTrueColorToPalette2 ($background_image, true, 255);
    //----------------------------------------
    // Calculate the offsets
    //----------------------------------------
    $offset_width  = floor(($param_width - $new_width) /2);
    $offset_height = floor(($param_height - $new_height) /2);
    imagecopymerge($background_image, $scaled_image,
       $offset_width,$offset_height,
       0, 0, 
       $new_width, $new_height, 100);
    //----------------------------------------
    // Display the image
    //----------------------------------------
     header('Content-type: image/png');
     imagepng($background_image);
 }

 //---------------------------------------
 // Display with cropping and framing
 //---------------------------------------
 function display_crop_and_frame (
 		$original_image,
		$original_width,
		$original_height,
		$param_width,
		$param_height) {

//error_log("display_crop_and_frame");

    //--------------------------------------------
    // Create the cropped image 
    //--------------------------------------------
    $ratio = $original_width / $param_width > $original_height / $param_height
    ?  $original_height / $param_height : $original_width / $param_width;
    $cropped_width   =  floor($ratio * $param_width);
    $cropped_height   =  floor($ratio * $param_height);
    $off_x =  floor(($original_width - $cropped_width) / 2);
    $off_y =  floor(($original_height - $cropped_height) / 2);
    $original_width = $cropped_width;
    $original_height = $cropped_height;
    $new_width  = $param_width;
    $new_height = $param_height;
    $scaled_image = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($scaled_image, $original_image, 0,0,$off_x,$off_y,$new_width, 
    $new_height, $original_width, $original_height);
// JLR
//    imagetruecolortopalette($scaled_image, true, 255);
    ImageTrueColorToPalette2 ($scaled_image, true, 255);
    //----------------------------------------
    // Create the background image
    //----------------------------------------
    $background_image = imagecreatetruecolor($param_width, $param_height);
    $black = imagecolorallocate($background_image, 0, 0, 0);
    imagecolortransparent ($background_image, $black);
// JLR
//    imagetruecolortopalette($background_image, true, 255);
    ImageTrueColorToPalette2 ($background_image, true, 255);
    //----------------------------------------
    // Calculate the offsets
    //----------------------------------------
    $offset_width  = floor(($param_width - $new_width) /2);
    $offset_height = floor(($param_height - $new_height) /2);
    imagecopymerge($background_image, $scaled_image,
       $offset_width,$offset_height,
       0, 0, 
       $new_width, $new_height, 100);
      //--------------------------------------------
      // Display the image
      //--------------------------------------------
      header('Content-type: image/png');
      imagepng($scaled_image);
  }  
?>

