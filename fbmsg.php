<?php
$secret_password = "";	// password for adding a trusted client to the messenger bot
$ha_token = "";		// long time access token from homeassistant
$ha_url = ""; 		// url for your homeassistant example 'service.yoururl.com:port'
$access_token = "";	// the facebook application access token
$verify_token = ""; 	// if you are using challenges with facebook, enter it here

// =============================================================

if (isset($_REQUEST['hub_challenge'])) {
  echo verifyToken($_REQUEST['hub_challenge'],$_REQUEST['hub_challenge']);
}

$input   = json_decode(file_get_contents('php://input'), true);
$messages = parseIncomming($input);

foreach ($messages as $message) {
  $response = handleMessage($message);
}

// =============================================================

function verifyToken($hub_verify_token, $challange)
{
  global $verify_token;
  try {
   if ($hub_verify_token === $verify_token) {
    return $challange;
   }
   else {
    throw new Exception("Token not verified");
   }
  }

  catch(Exception $ex) {
   return $ex->getMessage();
  }
}

function isRegistered($userId)
{
	$uuids = file_get_contents("./uuids.txt");
	if(strpos($uuids, 'UID:'.$userId.';') !== FALSE)
		return true;
	else
		return false;
}

function createSimpleResponseMessage($recipient,$message) {
   global $access_token;
   $response = ['recipient' => ['id' => $recipient], 'message' => ['text' => $message], 'access_token' => $access_token];
   return postResponseMessage($response);
}

function handleMessage($input)
{
  global $access_token,$secret_password;
  $senderId = $input['senderid'];
  $messageType = $input['type'];

  if ($messageType==="text") {
     $messageText = $input['message'];

     if (substr($messageText, 0, 1 ) === "/") {
        $messageArray = explode(' ', $input['message']);
	$eventCommand = ltrim(strtolower($messageArray[0]), '/');
	$discard = array_shift($messageArray);

	if ($eventCommand === "whoami") {
		return createSimpleResponseMessage($senderId,"Your ID: ".$senderId);
	} elseif ($eventCommand === "authorize") {
		if (isRegistered($senderId)) {
   	        return createSimpleResponseMessage($senderId,"I already know you.");
		} elseif ($secret_password === $messageArray[1]) {
			file_put_contents("./uuids.txt", 'UID:'.$senderId.';', FILE_APPEND);
   	        	return createSimpleResponseMessage($senderId,"I will now listen to you.");
		} else {
   	        	return createSimpleResponseMessage($senderId,"Supplied password is incorrect.");
		}
        } else {
		if (!isRegistered($senderId)) {
   	       		return createSimpleResponseMessage($senderId,"I don't know you.");
        	} else {
	        	return raiseHomeAssistantEvent(['command' => $eventCommand, 'senderid' => $senderId, 'arguments' => $messageArray], 'command');
		}
        } 
     } else { // NOT A COMMAND
	if (isRegistered($senderId)) {
		return raiseHomeAssistantEvent($input, 'conversation');
	} else {
   	        return createSimpleResponseMessage($senderId,"I don't know you.");
	}
     } 

  } else { // OTHER TYPES BESIDES TEXT
	if (isRegistered($senderId)) {
		return raiseHomeAssistantEvent($input, 'conversation');
	} else {
   	        return createSimpleResponseMessage($senderId,"I don't know you.");
	}
  } 

}

function raiseHomeAssistantEvent($message,$eventType)
{
  global $ha_token,$ha_url;
  $url      = 'https://'.$ha_url.'/api/events/messenger.'.$eventType;
  $ch       = curl_init($url);

  $jsonDataEncoded = json_encode($message);

  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Authorization: Bearer '.$ha_token]);

  return curl_exec($ch);
}

function postResponseMessage($message)
{
  $url      = 'https://graph.facebook.com/v2.6/me/messages';
  $ch       = curl_init($url);

  $jsonDataEncoded = json_encode($message);

  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

  return curl_exec($ch);
}

function parseIncomming($input)
{
  file_put_contents("./debug.txt", json_encode($input));

  try {
   $result = [];
   $payloads = null;
   $senderId = $input['entry'][0]['messaging'][0]['sender']['id'];
   $messageText = $input['entry'][0]['messaging'][0]['message']['text'];
   $attachments = $input['entry'][0]['messaging'][0]['message']['attachments'];

   if (!empty($attachments)) {
     foreach ($attachments as $attachment) {
	if ($attachment['type']==="image") {
         array_push($result, ['type'=>'image','senderid' => $senderId, 'url'=>$attachment['payload']['url']]);
	}
	if ($attachment['type']==="audio") {
         array_push($result, ['type'=>'audio','senderid' => $senderId, 'url'=>$attachment['payload']['url']]);
	}
	if ($attachment['type']==="video") {
         array_push($result, ['type'=>'video','senderid' => $senderId, 'url'=>$attachment['payload']['url']]);
	}
	if ($attachment['type']==="location") {
         array_push($result, ['type'=>'location','senderid' => $senderId, 'title'=>$attachment['title'],'lon'=>$attachment['payload']['coordinates']['long'],'lat'=>$attachment['payload']['coordinates']['lat'],'url'=>$attachment['url']]);
	}
     }
   }

   if (!empty($messageText)) {
	array_push($result, ['type' => 'text', 'senderid' => $senderId, 'message' => $messageText]);
   }

   return $result;
  }

  catch(Exception $ex) {
   return $ex->getMessage();
  }
}
