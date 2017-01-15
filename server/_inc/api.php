<?php
/**
* MainClass
*/
define('DATAFOLDER', '/var/www/html/phpchat/server/data/');
define('USERLISTFILANAME', 'userList');
define('MESSAGELISTFILENAME', 'messageList_');
error_reporting(E_ALL);
class MainClass 
{
	private $random_id_array = [];
	function __construct(){
		
	}
	public function setOptions($arr){
		foreach ($arr as $key => $value) {
			$this->$key = $value;
		}
	}
	public function log($txt){
		echo date('Y-m-d H:i:s').' : '.$txt."\n";
	}
	public function error($txt){
		echo date('H:i:s').' : '.$txt."\n";
		die();
	}
	public function jsonSave($json,$name){
		$fname = DATAFOLDER.$name.".json";
		$t = $this->jsonRead($name);
		$t[]=$json;
		$str = json_encode($t);
		$file = fopen($fname,"w");
		fwrite($file,$str);
		fclose($file);		
	}
	public function jsonSaveOverWrite($arr,$name){
		$fname = DATAFOLDER.$name.".json";
		$t = array();
		foreach ($arr as $v) {
			$t[] = json_encode($v);
		}

		$str = (json_encode($t));
		$file = fopen($fname,"w");
		fwrite($file,$str);
		fclose($file);		
	}
	public function jsonRead($name){
		return file_exists(DATAFOLDER.$name.".json") ?  json_decode(file_get_contents(DATAFOLDER.$name.".json",true)) : array();
	}
	public function mask($text){
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCn', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCNN', $b1, 127, $length);
		return $header.$text;
	}
	public function unmask($text) {
		$length = ord($text[1]) & 127;
		if($length == 126) {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		}
		elseif($length == 127) {
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		}
		else {
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		$text = "";
		for ($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}
	public function addRandomID(){
		$rand = rand(100000000,999999999);
		if(in_array($rand, $this->random_id_array)){
			$this->addRandomID();
		}else{
			$this->random_id_array[] = $rand;
			return $rand;
		}
	}
}

/**
* ChatApp (host,port)
*/
class ChatApp extends MainClass{
	
	protected $message_array = [];
	protected $json_array =[];
	
	

	protected $Socket;
	protected $Communication;

	function __construct($host,$port){
		set_time_limit(0);

		$this->Socket = new ChatAppSocket($host,$port);
		$this->Communication = new ChatAppCommunication();

		
	}
	function __destruct(){
    }
	
	function run(){
		$this->log("host : ".$this->Socket->host);
		$this->log("port:".$this->Socket->port);
		$this->log("server is running...");
		while (true) {
			$this->waitForChange();
			$this->checkNewClients();
			$this->checkMessageRecieved();
			$this->checkDisconnect();
			//$this->Communication->checkOldMessages();
		}
	}
	public function waitForChange(){
		//$this->log('waiting for change');
		$null = NULL;
		$this->Socket->setChanged ( array_merge([$this->Socket->socket], $this->Socket->clients) );
		
		socket_select($this->Socket->changed,$null, $null,$null);
	}
	private function checkNewClients(){
		//$this->log('checking new clients');
		if (in_array($this->Socket->socket, $this->Socket->changed)){

			
			$socket_new = socket_accept($this->Socket->socket);
			$this->log('socket accepted');
			$header = socket_read($socket_new, 1024);

			$this->Socket->addClient($socket_new);

			$this->Socket->perform_handshaking($header, $socket_new);
			socket_getpeername($socket_new, $ip);
				
			$this->log($ip.' connected.');
			$this->Communication->askForInformation($socket_new);
			$found_socket = array_search($this->Socket->socket, $this->Socket->changed);
			$this->Socket->removeChanged($found_socket);

		}
	}
	private function checkMessageRecieved(){
		//$this->log('checking new messages');
		foreach ($this->Socket->changed as $key=>$socket) {
			while(socket_recv($socket, $buf, 1024, 0) >= 1){
				$received_text = $this->unmask($buf); 
				$tst_msg = json_decode($received_text);
				$this->Communication->processAnswer($tst_msg,$socket);
				$this->Socket->removeChanged($key);
				break;
			}
		}
	}
	
	
	
    private function checkDisconnect(){
    	//$this->log('checking disconnect');
    	foreach ($this->Socket->changed as $changed_socket ) {
    		@$buf = socket_read($changed_socket, 1024, PHP_NORMAL_READ);
			if ($buf === false) {
				$found_socket = array_search($changed_socket, $this->Socket->clients);
				socket_getpeername($changed_socket, $ip);
				$this->Communication->removeRegisteredUser($changed_socket);
				$this->Socket->removeClient($found_socket);
				$this->log($ip.' disconnected.');
				/*
				$response = $this->mask(
					json_encode(
						array(
							'type'=>'system',
							'message'=>$ip.' disconnected',
							'online_users'=>$this->getOnlineUsers()
							)
						)
					);
				$this->sendMessage($response);*/
			}
    	}
    }
	
}

/**
* ChatAppSocket(host,port)
* 
*/
class ChatAppSocket extends MainClass
{
	public $clients = [];
	public $changed;
	public $socket;
	public $host = "localhost";
	public $port = 9000;

	
	function __construct($host,$port){
		$this->setHost($host);
		$this->setPort($port);
		$this->setSocket();
	}
	function __destruct(){
		foreach($this->clients as $client) {
            socket_close($client);
        }
        socket_close($this->socket);
	}

	/* socket */
	private function setSocket(){
		$socket  = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($socket, 0, $this->port);
		socket_listen($socket);
		$this->socket = $socket;
		//socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));
		//socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 1, 'usec' => 0));
	}
	public function getSocket(){
		return $this->socket;
	}

	/* host */
	public function setHost($host){
		$this->host = $host;
	}
	public function getHost(){
		return $this->host;
	}

	/* port */
	public function setPort($port){
		$this->port = $port;
	}
	public function getPort(){
		return $this->port;
	}
	/* changed */
	public function setChanged($changed){
		$this->changed = $changed;
	}
	public function getChanged(){
		return $this->changed;
	}
	public function removeChanged($i){
		unset($this->changed[$i]);
	}

	/* clients */
	public function getClients(){
		return $this->clients;
	}
	public function addClient($foo){
		$this->clients[] = $foo;
	}
	public function removeClient($i){
		unset($this->clients[$i]);
	}

	/*  */
	
	public function hasNewClient(){
		return (in_array($this->getSocket(), $this->getChanged())) ;
						
	}
	public function perform_handshaking($receved_header,$client_conn){
		$headers = array();
		$host = $this->getHost();
		$port = $this->getPort();
		$lines = preg_split("/\r\n/", $receved_header);
		foreach($lines as $line){
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
				$headers[$matches[1]] = $matches[2];
			}
		}
		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"WebSocket-Origin: $host\r\n" .
			"WebSocket-Location: ws://$host:$port/chat2/server/server.php\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
			socket_write($client_conn,$upgrade,strlen($upgrade));
	}
}
/**
* ChatAppCommunication
*/
class ChatAppCommunication extends MainClass
{
	protected $Users = [];
	
	function __construct(){
		$this->loadUsers();
	}
	public function askForInformation($socket){
		$this->log('asking for information ... ');
		$response = $this->mask(
					json_encode(
						array(
							'type'=>'system',
							'message'=>'tell_me_your_information'
							)
						)
					);
		$this->sendMessage($response,$socket);
	}
	public function sendMessage($msg,$socket=false,$socket2=false){
		
		
		/* socket 1 ve 2 arasında ozel mesaj*/
		if($socket){
			socket_write($socket,$msg,strlen($msg));
		}
		if($socket2){
			socket_write($socket2,$msg,strlen($msg));
		}
		/*global oda
		if(!$socket && $socket2){
			foreach($this->clients as $changed_socket){
				socket_write($changed_socket,$msg,strlen($msg));
			}
		}*/
		return true;
	}

	/* users */
	private function saveUsers(){

	}
	private function loadUsers(){
		$a = $this->jsonRead(USERLISTFILANAME);
		foreach ($a as $user) {
			$_temp = json_decode($user[0]);
			$this->Users[$_temp->user_id] = new ChatAppUser($_temp->user_id,false);
		}
	}
	private function findUser($user_id){
		return (isset($this->Users[$user_id])) ? $this->Users[$user_id] : false;
	}
	private function getUsers(){

	}
	private function addUser(){

	}
	private function removeUser(){

	}


	/* communications */
	public function processAnswer($tst_msg,$socket){
		switch(@$tst_msg->type){
			case 'chat' :
				
				$this->chatMessage($tst_msg);
				break;
			case 'system':
				switch ($tst_msg->message) {
					case 'my_information':
						$this->log('client information received ... ');
						if(!$this->registerNewUser($tst_msg,$socket)){
							echo "unknown user byess ";
							socket_close($socket);
						}
						break;
					case 'tell_me_online_users':
						$this->setLastHeartBeat($tst_msg->last_heartbeat,$tst_msg->user_id);
						$json = json_encode(
						array(
							'type'=>'system',
							'online_users'=>$this->getOnlineUsers()
							)
						);
						$response_text = $this->mask($json);
						$this->sendMessage($response_text,$socket);
						break;
					case 'mark_message_read':
						$this->markMessageRead($tst_msg->message_id,$tst_msg->user_id);
						break;
					case 'type_start':
						$this->typeStart($tst_msg);
						break;
					case 'type_end':
						$this->typeEnd($tst_msg);
						break;
					default:
						//unknown message			
						break;
				}
				break;
			default :
				//unknown type
				break;
		}
	}
	private function chatMessage($tst_msg){
		$from = $tst_msg->from;
		if($from ==NULL)return false;


		$to = isset($tst_msg->to) ? $tst_msg->to : false;

		$fromUser = $this->findUser($from);
		$toUser = $this->findUser($to);
		if(!$toUser)return false;

		

		$dest_sock = false;
		$dest_sock2 = false;
		if($toUser){
			$dest_sock = $fromUser->socket;//alıcı
			$dest_sock2 = $toUser->socket; //gonderici
		}
		$this->log('from '.$from.' to '.$to.' : '.$tst_msg->content);

		$message_id = $this->addRandomID();
		$respTime = time();

		
							
		$json_str = json_encode(
			array(
				'message_id'=>$message_id,
				'from' => $from,
				'to'  => $to,
				'is_read'=>false,
				'is_delivered'=>false,
				'is_sent'=>false,
				'sendTime'=>$tst_msg->sendTime,
				'windowID' => $tst_msg->windowID,
				'content'=>$tst_msg->content,
				'user_name'=>$tst_msg->user_name,
				'type'=>'private_chat'
				)
			);
		$json = json_decode($json_str);

		if($fromUser){
			$fromUser->addMessage($json);
			$this->sendSavedMessages($fromUser);
			$this->markMessageSend($json->message_id,$json->from);
		}
		if($toUser){
			$toUser->addMessage($json);
			$this->sendSavedMessages($toUser);
		}
		

		//$response_text = $this->mask($json_str);
		
		/*
		if($from<$to){
			$fname = 'chatBetween_'.$from.'_and_'.$to;	
		}else{
			$fname = 'chatBetween_'.$to.'_and_'.$from;
		}
		
		if(!$toUser->isOnline()){
			$tst_msg->message_id = $message_id;
			$this->addOldMessage($tst_msg);
			return false;
		}*/

		//$this->jsonSave($json,$fname);
		//$this->sendMessage($response_text,$dest_sock,$dest_sock2);
		return true;
	}
	private function registerNewUser($tst_msg,$socket){

		if(!isset($tst_msg->user_id))return false;

		//if(isset($this->Users[$tst_msg->user_id]))return false;
		$User = $this->findUser($tst_msg->user_id);
		if($User ){

			/*
			if($this->Users[$User->user_id]->socket ){
				socket_close($this->Users[$User->user_id]->socket );
			}*/

			$this->Users[$User->user_id] = new ChatAppUser($tst_msg->user_id,$socket);
			$this->log('registered new user : '.$tst_msg->user_name);
			$this->sendSavedMessages($this->Users[$tst_msg->user_id]);
			return true;
		}else{
			$this->log('user '.$tst_msg->user_name.' does not exists');
			return false;
		}
	}
	private function setLastHeartBeat($time,$user_id){
		$this->findUser($user_id)->last_heartbeat = $time;
		
	}

	private function markMessageSend($msgid,$user_id){
		$t = $this->jsonRead(MESSAGELISTFILENAME.$user_id);
		$new_messages = array();
		foreach ($t as $json) {
			$_tem = json_decode($json);
			if($_tem->message_id == $msgid){
				$_tem->is_sent= true;
				$this->messageSendSuccess($_tem,$user_id);
			}
			$new_messages[] =$_tem;
		}
		@$this->jsonSaveOverWrite($new_messages, MESSAGELISTFILENAME.$user_id);



	}
	private function messageSendSuccess($msg,$user_id){

		$response = $this->mask(
			json_encode(
				array(
					'type'=>'system',
					'message'=>'msg_send_success',
					'to'=>$msg->to,
					'message_id'=>$msg->message_id
					)
				)
		);

		@$this->sendMessage($response,$this->findUser($user_id)->socket);
	}
	private function markMessageRead($msgid,$user_id){
		$t = $this->jsonRead(MESSAGELISTFILENAME.$user_id);
		$new_messages = array();
		foreach ($t as $json) {
			$_tem = json_decode($json);
			if($_tem->message_id == $msgid){
				$_tem->is_read= true;
				
				$this->messageReadSuccess($_tem,$user_id);
				if($user_id != $_tem->from)$this->markMessageDelivered($msgid,$_tem);
			}
			$new_messages[] =$_tem;
		}
		@$this->jsonSaveOverWrite($new_messages, MESSAGELISTFILENAME.$user_id);



	}
	private function messageReadSuccess($msg,$user_id){

		$response = $this->mask(
			json_encode(
				array(
					'type'=>'system',
					'message'=>'msg_read_success',
					'from'=>$msg->from,
					'message_id'=>$msg->message_id
					)
				)
		);

		@$this->sendMessage($response,$this->findUser($user_id)->socket);
	}
	private function markMessageDelivered($msgid,$msg){
		$t = $this->jsonRead(MESSAGELISTFILENAME.$msg->from);
		$new_messages = array();
		foreach ($t as $json) {
			$msg = json_decode($json);
			if($msg->message_id == $msgid){
				$msg->is_delivered= true;
				
				$this->messageDeliveredSuccess($msg);
			}
			$new_messages[] =$msg;
		}
		@$this->jsonSaveOverWrite($new_messages, MESSAGELISTFILENAME.$msg->from);
		
	}
	private function messageDeliveredSuccess($msg){
		$response = $this->mask(
			json_encode(
				array(
					'type'=>'system',
					'message'=>'msg_delivered',
					'from'=>$msg->from,
					'message_id'=>$msg->message_id
					)
				)
		);
		$this->sendMessage($response,$this->findUser($msg->from)->socket);
	}
	private function typeStart($msg){
		$response = $this->mask(
			json_encode(
				array(
					'type'=>'system',
					'message'=>'type_started',
					'from'=>$msg->from
					)
				)
		);
		$this->sendMessage($response,$this->findUser($msg->to)->socket);
	}
	private function typeEnd($msg){
		$response = $this->mask(
			json_encode(
				array(
					'type'=>'system',
					'message'=>'type_ended',
					'from'=>$msg->from
					)
				)
		);
		$this->sendMessage($response,$this->findUser($msg->to)->socket);
	}
	private function getOnlineUsers(){
		$out = array();
		foreach ($this->Users as $User) {
			if($User->isOnline())$out[] = array('user_id'=>$User->user_id,'user_name'=>$User->user_name);
		}
		return $out;
	}
	private function addOldMessage($tst_msg){
		$this->oldMessages[$tst_msg->message_id] = $tst_msg;
	}


	private function sendSavedMessages($User){
		$this->log('chat ready for user  ... '.$User->user_id);
		foreach ($User->Messages as $Messsage) {
			$Messsage->prepareForSending();
			$response = $this->mask($Messsage->msg_str);
			$this->sendMessage($response,$User->socket);
			$User->removeMessage($Messsage->message_id);
		}
	}
	
	public function removeRegisteredUser($socket){

		foreach ($this->Users as $User ) {
			if($User->socket === $socket){
					$this->log('removing registered user '. $User->user_name);
					$this->Users[$User->user_id] = new ChatAppUser($User->user_id,false);
				break;
			}
		}
		
	}

}

/**
* ChatAppUser
*/
class ChatAppUser extends MainClass
{
	public $socket = false;
	public $Messages = [];

	function __construct($user_id,$socket=false){
		$this->setUserOptions($user_id);
		$this->setSocket($socket);
		$this->setMessages();
	}

	/* useroptions */
	private function setUserOptions($user_id){
		$a = $this->jsonRead(USERLISTFILANAME);
		foreach ($a as $user) {
			$_temp = json_decode($user[0]);
			if($user_id == $_temp->user_id){
				$this->user_id = $_temp->user_id;
				$this->user_name = $_temp->user_name;
				break;
			}
			
		}
	}

	/* messages */
	private function setMessages(){
		
		$t = $this->jsonRead(MESSAGELISTFILENAME.$this->user_id);
		foreach ($t as $json) {
			$_tem = json_decode($json);
			$_tem->old= true;
			$this->Messages[] = new ChatAppMessage($_tem);
		}
	}
	public function addMessage($json){
		$this->Messages[] = new ChatAppMessage($json);
		$fname = MESSAGELISTFILENAME.$this->user_id;
		$this->jsonSave( json_encode($json),$fname);
	}
	public function removeMessage($msg_id){
		$_temp = array();
		for ($i = 0; $i<count($this->Messages);$i++) {
			if(!$this->Messages[$i]->message_id == $msg_id){
				$_temp[] = $this->Messages[$i];
			}
		}
		$this->Messages = $_temp;
	}

	/* socket */
	public function setSocket($socket){
		$this->socket = $socket;
	}
	private function getSocket(){
		return $this->socket;
	}

	/* */
	public function isOnline(){
		return ($this->socket);
	}
}

/**
* ChatAppMessage
*/
class ChatAppMessage extends MainClass
{
	public $message_id;
	public $from;
	public $to;
	public $is_read = false;
	public $is_delivered = false;
	public $is_sent = false;
	public $sendTime ;
	public $windowID ;
	public $content ;
	public $user_name ;
	public $sentToSocket ;

	public $msg_str;

	function __construct($json){
		$this->message_id = $json->message_id;
		$this->from = $json->from;
		$this->to = $json->to;
		$this->is_read = $json->is_read;
		$this->is_delivered = $json->is_delivered;
		$this->is_sent = $json->is_sent;
		$this->sendTime = $json->sendTime;
		$this->windowID = $json->windowID;
		$this->content = $json->content;
		$this->user_name = $json->user_name;
		$this->prepareForSending();
	}
	public function prepareForSending(){
		$arr =  array(
			'message_id' =>$this->message_id , 
			'from' =>$this->from , 
			'to' =>$this->to , 
			'is_read' =>$this->is_read , 
			'is_delivered' =>$this->is_delivered , 
			'is_sent' =>$this->is_sent , 
			'sendTime' =>$this->sendTime , 
			'windowID' =>$this->windowID , 
			'content' =>$this->content , 
			'user_name' =>$this->user_name , 
			'type' =>'private_chat'
			);
		$this->msg_str = json_encode($arr);

	}
}

?>