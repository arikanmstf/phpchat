<?php
	session_name('HORICHATAPP');
	session_start();


	class Api{
		

		function Api(){
			$this->Session = new Session();
		}
		public function is_logged_in(){
		
			return $this->Session->is_logged_in();

		}
		public function session(){
			$this->Session->login();
			$this->Session->logout();
		}

	}
	class Session{
		protected $users = Array(
				'chrome' => 123456,
				'firefox'=> 112233,
				'osman'  => 123457,
				'test'	 => 235444,
				'demo'   => 132322,
				'polo'	 => 313132
		);

		function Session(){
			
			$this->getAllAtrs();
		}
		public function is_logged_in(){
			return @(isset($this->sesToken));
		}
		public function login(){
			if(isset($_POST['name']) && $_POST['enter'] ){

				$colours = array('007AFF','FF7000','FF7000','15E25F','CFC700','CFC700','CF1100','CF00BE','F00');
				$i= array_rand($colours);


				$chatFriends =[];
				$registered = false;

				foreach ($this->users as $key => $value) {
					if($key != $_POST['name']){
						$chatFriends[] = Array('user_name'=>$key,'user_id'=>$value);
					}
				}
				foreach ($this->users as $key => $value) {

					if ($key == $_POST['name']){
						$registered = true;
					}
				}
				if(!$registered)die('not registered user');

				$t = $colours[$i];
				$this->addAtr(
						array(
								'name' => $_POST['name'],
								'TextColor'=> $t,
								'sesToken' => time(),
								'chatFriends' => $chatFriends,
								'user_id' => $this->users[$_POST['name']]
							)
					);
				header('Location: '.$_SERVER['PHP_SELF']);
			}
			
		}
		public function logout(){
			if(isset($_GET['logout'])){
				if($_GET['logout']){
					session_destroy();
					header('Location: '.$_SERVER['PHP_SELF']);
				}
			}
		}
		private function addAtr($arr){
			foreach ($arr as $key => $value) {
				$_SESSION[$key] = $value;
			}
		}
		private function getAtr($key){
			return (isset($_SESSION[$key])) ? $_SESSION[$key] : false;
		}
		private function getAllAtrs(){
			foreach ($_SESSION as $key => $value) {
				$this->$key  = $value;
			}
		}
	}

?>