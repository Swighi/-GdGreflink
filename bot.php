<?php
define("Telegram", "675423800:AAGkL87hrAcF9yRS0SGiBY14iqoJEVSotTE"); 

$input = file_get_contents("php://input");
$updates = json_decode($input, true);

$photo = $updates['message']['photo'];
$message = $updates['message']['text'];
$caption = $updates['message']['caption'];

$originalMessage = $updates['message']['reply_to_message'];
$originalMessageId = $updates['message']['reply_to_message']['message_id'];
$originalUser = $updates['message']['reply_to_message']['from'];
$originalUserId = $updates['message']['reply_to_message']['from']['id'];

$message_id = $updates['message']['message_id'];
$chat = $updates['message']['chat'];
$chat_title = $updates['message']['chat']['title'];
$chat_id = $updates['message']['chat']['id'];
$chatType = $updates['message']['chat']['type'];
$user = $updates['message']['from']; //get user
$user_id = $updates['message']['from']['id']; //not used yet
$user_username = $updates['message']['from']['username']; //username @test
$user_firstname = $updates['message']['from']['first_name'];
$entireMessage = $message;
$message = explode(' ', trim($message));
$message[0] = explode('@', trim($message[0]))[0];

$forward_id = $updates['message']['forward_from_chat']['id'];
$forward_from_chatType = $updates['message']['forward_from_chat']['type'];

if(isset($caption))
    $messageToCheck = $caption;
else
    $messageToCheck = $entireMessage;

if($chatType == "group" || $chatType == "supergroup")
{
    if($message[0] == "/configura")
    {
        if(!isAdmin($chat_id, "574110811"))
        {
            sendMessage($chat_id, "Non sono amministratore del gruppo, per poter proseguire devo essere impostato come amministratore");
            return;
        }

        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            sendMessage($chat_id, "Inviami la tag di Amazon (reflink) da utilizzare");
            setStatus($chat_id, $user_id, "in attesa di tag");
        }
    }
    else if(getStatus($chat_id, $user_id) == "in attesa di tag")
    {
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            setStatus($chat_id, $user_id, null);
            configureGroup($chat_id, $entireMessage, $chat_title, $user_username);
            sendMessage($chat_id, "Sarà utilizzata la tag <b>".$entireMessage."</b>");
        }
    }
    else if($message[0] == "/whitelist")
    {
        if(isFounder($chat_id, $user_id) || isAdmin($chat_id, $user_id))
        {
            if(isset($message[1]) && $message[1][0] == "@")
            {
                $channel_username = $message[1];
                $result = getChannelId($channel_username);
                $channel_id = $result['channel_id'];
                $channel_name = $result['channel_name'];
                $configuration_message_id = $result['msg_id'];
                if(!isset($channel_name) || strlen($channel_name) <= 0)
                {
                    sendMessage($chat_id, "Non posso aggiungere alla whitelist un canale di cui non sono admin.\nRendimi admin del canale da aggiungere per proseguire.");
                    return;
                }
                deleteConfigurationMessage($channel_id, $configuration_message_id); //cancella in automatico messaggio di configurazione
                addToWhitelist($chat_id, $channel_id); //aggiunge channel_id in whitelist per chat_id
                sendMessage($chat_id, "Il canale <b>".$channel_name."</b> è stato aggiunto alla whitelist");
                leaveChat($channel_id); //esce dal canale per motivi di sicurezza
            }
            else
            {
                sendMessage($chat_id, "Comando non valido.\nSintassi comando: /whitelist @username_del_canale");
            }
        }
    }
    else if(isAmazonSite($messageToCheck) || isAmazonShort($messageToCheck))
    {
        if($forward_from_chatType == "channel")
        {
            if(channelIsInWhitelist($chat_id, $forward_id))
                return;
        }

        if($user_id == "189327343") //falco whitelist, danyus rompicoglioni
            return;

        if(!isAdmin($chat_id, "574110811"))
        {
            sendMessage($chat_id, "Non sono amministratore del gruppo, per poter proseguire devo essere impostato come amministratore");
            return;
        }
        
        $firstParameter = false;

        if(strpos($messageToCheck, '?') == false)
        {
            $messageToCheck .= "?";   
            $firstParameter = true;
        }

        $line = explode(PHP_EOL, trim($messageToCheck));
        $words = array();
        $newMessage = "<b>Messaggio originariamente inviato da ".$user_firstname."</b>";
        if(isset($user_username))
            $newMessage .= " (@".$user_username.")";

        $newMessage .= "\n\n";

        for($i = 0; $i < count($line); $i++)
        {
            $word = explode(' ', $line[$i]);
            for($j = 0; $j < count($word); $j++)
            {
                if(isAmazonSite($word[$j]))
                {
                    if(strpos($word[$j], 'images') === false)
                    {
                        $word[$j] = replaceRefLink($word[$j], $chat_id, $firstParameter);
                    }
                }
                else if(isAmazonShort($word[$j]))
                {
                    $urlToAnalyze = substr($word[$j], strpos($word[$j], "http"));
                    $longUrl = str_replace(' ', '%20', getLongUrl($urlToAnalyze));
                    if(isset($longUrl))
                    {
                        $word[$j] = replaceRefLink($longUrl, $chat_id, $firstParameter);
                    }
                }
                $newMessage .= $word[$j]." ";
            }
            $newMessage .= "\n";
        }
        
        if($newMessage[strlen($newMessage)-3] === "?")
        {
            $newMessage = substr($newMessage, 0, -3);
        }

        deleteMessage($chat_id, $message_id);

        if(isset($originalMessageId))
            sendMessage($chat_id, $newMessage, $originalMessageId);
        else
            sendMessage($chat_id, $newMessage);
    }
}

function sendMessage($chat_id, $text, $originalMessageId = null)
{
    $curl = curl_init();
    if(isset($originalMessageId))
    {
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => Telegram."/sendMessage?chat_id=".$chat_id."&text=".urlencode($text)."&parse_mode=html&reply_to_message_id=".$originalMessageId
        ));
    }
    else
    {
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => Telegram."/sendMessage?chat_id=".$chat_id."&text=".urlencode($text)."&parse_mode=html"
        ));
    }

    curl_exec($curl);
    curl_close($curl);
}

function addToWhitelist($chat_id, $channel_id)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT IGNORE INTO `whitelist` (`chat_id`, `channel_id`) VALUES ('".$chat_id."', '".$channel_id."');");
    $conn->close();
}

function channelIsInWhitelist($chat_id, $channel_id)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT * FROM whitelist WHERE chat_id='".$chat_id."' AND channel_id='".$channel_id."';");
    $conn->close();

    if($result->num_rows > 0)
        return true;
    else
        return false;
}

function getChannelId($chat_id)
{    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/sendMessage?chat_id=".$chat_id."&text=".urlencode("Messaggio di configurazione <a href='https://t.me/amzreflinkbot'>Amazon Reflink Master</a>")."&parse_mode=html"
    ));

    $json = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($json, true)['result'];
    return array("channel_id" => $result['chat']['id'], "msg_id"=>$result['message_id'], "channel_name"=>$result['chat']['title']);
}

function leaveChat($channel_id)
{
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/leaveChat?chat_id=".$channel_id
    ));

    curl_exec($curl);
    curl_close($curl);
}

function deleteConfigurationMessage($channel_id, $msg_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/deleteMessage?chat_id=".$channel_id."&message_id=".$msg_id
    ));

    curl_exec($curl);
    curl_close($curl);
}

function deleteMessage($chat_id, $message_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/deleteMessage?chat_id=".$chat_id."&message_id=".$message_id
    ));

    curl_exec($curl);
    curl_close($curl);
}

function replaceRefLink($url, $chat_id, $firstParameter)
{
    $linkSplitted = explode('&', $url);

    $newRefLink = "";
    $found = false;
    $tag = getTag($chat_id);

    for($i = 0; $i < count($linkSplitted); $i++)
    {
        if(substr($linkSplitted[$i], 0, 3) == "tag")
        {
            $found = true;
            if($tag != "") //cambia tag altrimenti la rimuove
            {
                $newRefLink .= "&tag=".getTag($chat_id);
            }
        }
        else
        {
            if($i == 0)
                $newRefLink .= $linkSplitted[$i];
            else
                $newRefLink .= "&".$linkSplitted[$i];
        }
    }

    if(!$found)
    {
        if(strpos($newRefLink, 'tag=') !== false)
        {
            preg_match('/'.preg_quote("tag=").'(.*?)'.preg_quote("-21").'/is', $newRefLink, $match);

            if($tag != "") //cambia tag altrimenti la rimuove
            {
                $newRefLink = substr($newRefLink, 0, strpos($newRefLink, $match[0]))."tag=".getTag($chat_id);
            }
            else
            {
                $newRefLink = substr($newRefLink, 0, strpos($newRefLink, $match[0]));
            }
        }
        else
        {
            if($tag != "")
            {
                if($firstParameter)
                    $newRefLink .= "tag=".getTag($chat_id);
                else
                    $newRefLink .= "&tag=".getTag($chat_id);
            }
        }
    }

    return get_tiny_url($newRefLink);
}

function configureGroup($chat_id, $tag, $groupName, $user_username)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->query("INSERT INTO `refTag` (`id`, `tag`, `groupName`, `addedBy`) VALUES ('".$chat_id."', '".addslashes($tag)."', '".addslashes($groupName)."', '".addslashes($user_username)."') ON DUPLICATE KEY UPDATE tag='".$tag."';");
    $conn->close();
}

function getTag($chat_id)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT tag FROM refTag WHERE id = '".$chat_id."';");
    $conn->close();

    return $result->fetch_assoc()['tag'];
}

function isAdmin($chat_id, $user_id)
{
    $member = getChatMember($chat_id, $user_id);
    if($member['result']['status'] == "administrator")
        return true;
    else
        return false;
}

function isFounder($chat_id, $user_id)
{
    $member = getChatMember($chat_id, $user_id);
    if($member['result']['status'] == "creator")
        return true;
    else
        return false;
}

function getChatMember($chat_id, $user_id)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => Telegram."/getChatMember?chat_id=".$chat_id."&user_id=".$user_id
    ));

    $json = curl_exec($curl);
    curl_close($curl);

    //$json = file_get_contents(Telegram."/getChatMember?chat_id=".$chat_id."&user_id=".$user_id);
    
    return json_decode($json, true);
}

function setStatus($id, $user_id, $status = null)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    if(isset($status))
        $conn->query("INSERT INTO user_status (id, userId, status) VALUES ('".$id."', '".$user_id."','".$status."') ON DUPLICATE KEY UPDATE status='".$status."';");
    else
        $conn->query("DELETE FROM `user_status` WHERE `id`='".$id."';");
    $conn->close();
}

function getStatus($id, $user_id)
{
    require('config.php');
    $conn = new mysqli($servername, $username, $password, $dbname);
    $result = $conn->query("SELECT status FROM user_status WHERE id='".$id."' AND userId ='".$user_id."';");
    $conn->close();

    if($result)
        return $result->fetch_assoc()['status'];
    else
        return null;
}

function get_tiny_url($url)  { 
	$ch = curl_init();  
    $timeout = 5;
	curl_setopt($ch,CURLOPT_URL,'http://tinyurl.com/api-create.php?url='.$url);  
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);  
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);  
	$data = curl_exec($ch);  
	curl_close($ch);  
	return $data;  
}

function isAmazonSite($message)
{
    if((strpos($message, 'amazon.it/') !== false) || (strpos($message, 'amazon.com/') !== false) || (strpos($message, 'amazon.de/') !== false) || (strpos($message, 'amazon.es/') !== false) || 
        (strpos($message, 'amazon.fr/') !== false) || (strpos($message, 'amazon.co.uk/') !== false) || (strpos($message, 'amazon.com.br/') !== false) || (strpos($message, 'amazon.com.mx/') !== false) ||
        (strpos($message, 'amazon.co.jp/') !== false) || (strpos($message, 'amazon.cn/') !== false) || (strpos($message, 'amazon.in/') !== false) || (strpos($message, 'amazon.nl/') !== false) ||
        (strpos($message, 'amazon.ca/') !== false) || (strpos($message, 'amazon.com.au/') !== false))
    {
        return true;
    }
    else
    {
        return false;
    }
}

function isAmazonShort($string)
{
    if((strpos($string, 'amzn.to') !== false))
    {
        return true;
    }
    else
        return false;
}

function getLongUrl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

    curl_close($ch);

    if(preg_match('~Location: (.*)~i', $result, $match)) 
    {
        return trim($match[1]); 
    }
    else
        return null;
}
?>
