<?php
error_reporting(-1);
$handle = fopen("serverlogs.txt", "a");
if(null != $handle)
{
	fwrite($handle,"***********************************************\r\n");
	fflush($handle);
}
//upload directory
$uploads_dir = "/test/";//current directory
$server_upload_dir = "/testserver/";	
$uploadOk = 0;
$safetoupload = 1;

//variables related to file
$file_supported = array("jpg","bmp","jpeg","mp4","txt","png");//supported file formats, add new format here
$file_name = $_FILES['userfile']['name']; // get filename
$file_parts = pathinfo($file_name);
$file_extension = $file_parts['extension']; //get file extension

//$newline = "\r\n";
$newline = "</br>";

//function get the location of the client using IP address
function get_data($geolocationurl)
{
	$ch = curl_init();
	$timeout = 10;
	curl_setopt($ch, CURLOPT_URL, $geolocationurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

//function to copy the file to the other server
function send_file($handle,$serverurl,$move_dir)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => '@' . realpath($move_dir)));
	curl_setopt($ch, CURLOPT_URL, $serverurl);
	echo "$newlineCopying file to server $serverurl .$newline";
	if(!curl_exec($ch))
	{
		if(null != $handle)
		{
			fwrite($handle,"File copy to $serverurl failed\r\n");
			fwrite($handle,curl_error($ch));
			fwrite($handle,"\r\n");
			fflush($handle);
		}
		echo "File copy to server failed" . curl_error($ch) . $newline;			
	}
	if(null != $handle)
	{
		fwrite($handle,"File successfully copied to $serverurl\r\n");
		fflush($handle);
	}
	curl_close($ch);
}
//function to establish connection with other server
function connect($handle,$serverurl,$username,$password,$message)
{
	$exit = 0;
	for($retry = 0;$retry < 3 && $exit == 0; $retry++)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$serverurl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,"username=$username&password=$password&message=$message");
		//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);//time out for connection 
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$msg = curl_exec ($ch);
		if(null != $msg)
		{
			if(null != $handle)
				{
					fwrite($handle,"----connection established to $serverurl....\r\n");
					fflush($handle);
				}
			$exit = 1;
		}
		else
		{
			if(null != $handle)
			{
				fwrite($handle,"@@@@retrying to connect $serverurl....\r\n");
				fwrite($handle,curl_error($ch));
				fwrite($handle,"\r\n");
				fflush($handle);
			}
		}
		curl_close($ch);
	}
	if($exit == 0)
	{
		if(null != $handle)
		{
			fwrite($handle,"!!!!could not connect to $serverurl after multiple attempts.\r\n");
			fflush($handle);
		}
	}
	return $msg;
}

//function to initiate the data transfer
function initiate($handle,$serverurl,$data)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$serverurl);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,"data=$data");
	//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);//time out for connection 
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$msg = curl_exec($ch);
	if(null != $msg)
	{
		if(null != $handle)
		{
			fwrite($handle,"----initiate file transfer to $serverurl....\r\n");
			fflush($handle);
		}
	}
	else
	{
		if(null != $handle)
		{
			fwrite($handle,"@@@@Failed to initiate file transfer to $serverurl....\r\n");
			fwrite($handle,curl_error($ch));
			fwrite($handle,"\r\n");
			fflush($handle);
		}
	}
	curl_close($ch);
	return $msg;
}

//variables related to determine client location
$clientip = $_SERVER['REMOTE_ADDR'];
$geolocationurl = "http://ip-api.com/php/" . $clientip;
$data = get_data($geolocationurl);
$format = unserialize($data);
$country = $format["countryCode"];

//check for supported file formats
for($i = 0;$i< count($file_supported); $i++)
{
	if(strcasecmp($file_extension,$file_supported[$i]) == 0)
	{
		if(null != $handle)
		{
		fwrite($handle,"$file_extension format is supported \r\n");
		fflush($handle);
		}
		$uploadOk = 1;
		break;
	}
}

//Block an upload from a particular country
if(strcmp($country, "AF") == 0)
{
	if(null != $handle)
	{
		fwrite($handle,"File upload from $country not supported \r\n");
		fflush($handle);
	}
	$safetoupload = 0;
	$uploadOk = 0;
	print_r ($_FILES);
}
echo $newline;

if($safetoupload == 0)//exit if it is not safe
{
	die("File upload from $country country is not supported");
}

if( $uploadOk == 1)
{
	$currentdir = getcwd();
	$move_dir = $currentdir . $uploads_dir . $file_name;
	if(is_uploaded_file($_FILES['userfile']['tmp_name']))//upload file here
	{
		$dest=  $_FILES['userfile'] ['name'];
		echo  "Uploading FILE ".  $_FILES['userfile']['name']  ." to $move_dir. $newline";
		
		if (move_uploaded_file ($_FILES['userfile'] ['tmp_name'], "$move_dir"))
		{
			if(null != $handle)
			{
				fwrite($handle,"File $move_dir Upload successful\r\n");
				fflush($handle);
			}
			echo "Upload successful. $newline";	
		}
		else
		{
			echo "ERROR - uploading file $newline";
			if(null != $handle)
			{
				fwrite($handle," ERROR - uploading file $move_dir\r\n");
				fflush($handle);
			}
		}
		
		// Mail/message/Geo location START
		$to = "hospet.anandkumar@gmail.com";
		$mailsubject = "$file_name uploaded to server";
		$mailmessage = "$file_name was uploaded from IP address:" . $clientip . " City : " . $format["city"] . " Region : " . $format["regionName"]. " Country : " . $format["countryCode"];
		
		$mobnumber = "4087979588";
		$mobmail = $mobnumber . "@tmomail.net";
		$mobsubject = "$file_name uploaded from IP address";
		$mobmessage = $clientip . " City : " . $format["city"] . " Region : " . $format["regionName"]. " Country : " . $country;

		if(mail($to, $mailsubject, $mailmessage))
			echo "Sent mail to $to $newline";
		else
			echo "Error !!! in sending mail to $to $newline";

		if(mail($mobmail, $mobsubject, $mobmessage))
			echo "Sent sms to $mobnumber. $newline";
		else
			echo "Failed !!! to send an sms $newline";
		// Mail/message/Geo location END
		
		// file transfer to other servers, add the server url to below array
		$serverurl = array("http://ignite-now.us/demo.php","http://amoghshettar.us/demo.php","http://pingkarma.com/demo.php");
		$url1 = array("http://ignite-now.us/prot.php","http://amoghshettar.us/prot.php","http://pingkarma.com/prot.php");
		$url2 = array("http://ignite-now.us/prot1.php","http://amoghshettar.us/prot1.php","http://pingkarma.com/prot1.php");
		
		echo "Start of protocol $newline";
		$username = "Anand";
		$password = "Kumar";
		$message = "login";
		$data = "request to send file";
		
		for($i = 0;$i<3;$i++)
		{
			//establish connection
			
			$ret_val1 = connect($handle,$url1[$i],$username,$password,$message);
			$ret_val1 = strip_tags($ret_val1);			// Remove the html tags and trim white spaces
			$ret_val1 = ltrim($ret_val1);
			$ret_val1 = rtrim($ret_val1);
			if(null != $handle)
			{
				fwrite($handle,"function connect returned $ret_val1\r\n");
				fflush($handle);
			}
			if(strcmp($ret_val1,"success") == 0)
			{
				$ret_val2 = initiate($handle,$url2[$i],$data);// is it ok to transfer?
				$ret_val2 = strip_tags($ret_val2);
				$ret_val2 = ltrim($ret_val2);
				$ret_val2 = rtrim($ret_val2);
				if(null != $handle)
				{
					fwrite($handle,"function initiate returned $ret_val2\r\n");
					fflush($handle);
				}
				if(strcmp($ret_val2,"ok") == 0) 
				{
					send_file($handle,$serverurl[$i],$move_dir);//start transfer
				}
				else
					echo "Protocol error!! Failed to initiate file transfer to $serverurl[$i].$newline";
			}
			else
				echo "Protocol error!! Failed to connect with the server to $serverurl[$i].$newline";
		}
		echo "End of protocol $newline";
	}  
	else 
	{
		echo "is_uploaded_file function return error$newline";
		echo "filename ". $_FILES['userfile']['tmp_name'] . $newline;
		print_r($_FILES);
	}
}
else if(is_uploaded_file($_FILES['file']['tmp_name']))//upload file here
{
	$dest=  $_FILES['file'] ['name'];
	$move_dir = $currentdir . $server_upload_dir . $dest;
	echo  "ServerFile ".  $_FILES['file']['name']  ." uploaded successfully to $move_dir. $newline";
	if (move_uploaded_file ($_FILES['file'] ['tmp_name'], "$move_dir"))
	{
		if(null != $handle)
		{
			fwrite($handle,"File successfully received from server\r\n");
			fflush($handle);
		}
		echo "File copy successful$newline";
	}
	else
	{
		if(null != $handle)
		{
			fwrite($handle,"ERROR !!! while receiving file from server\r\n");
			fflush($handle);
		}	
		echo "ERROR !!! copying file to server$newline";
	}
}
else //$uploadOk == 0
{
		echo "$newlineERROR!!! Fileformat " . $file_extension .  " not supported$newline$newline";
		echo "SUPPORTED FILE FORMATS : ";
		foreach($file_supported as $element)
			echo $newline . $element;
        echo $newline;
}
if($handle)
	fclose($handle);
?>