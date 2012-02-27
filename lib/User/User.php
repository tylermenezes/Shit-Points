<?php
	require_once("Permissions.php");

	class User{
		private $id;
		private $permissions;
		private $first_name;
		private $last_name;
		private $gender;
		private $locale;
		private $isFilled = false;
		
		function __construct($id){
			$this->id = $id;
			$r = Sql::get(sprintf("SELECT COUNT(*) FROM `users` WHERE `fb_id` = '%s' LIMIT 1;", $id));
			if(!$r[0]["COUNT(*)"]){
				Sql::exec(sprintf("INSERT IGNORE INTO `users` VALUES (%d, 10, 0);", $id));
				$this->sendNotification($this, __g('notification welcome'));
			}
		}

		public function incrementCount(){
			$key = file_get_contents("http://graph.facebook.com/oauth/access_token?type=client_cred&client_id=132925230085251&&client_secret=d1775156f4401269a0f546e44e18284c");
			$url = "https://api.facebook.com/method/dashboard.incrementCount?uid=" . $this->getId() . "&" . $key;
			file_get_contents($url);
		}

		public function resetCounter(){
			$key = file_get_contents("http://graph.facebook.com/oauth/access_token?type=client_cred&client_id=132925230085251&&client_secret=d1775156f4401269a0f546e44e18284c");
                        $url = "https://api.facebook.com/method/dashboard.setCount?count=0&uid=" . $this->getId() . "&" . $key;
                        file_get_contents($url);
		}
		
		public function getId(){
			return $this->id;
		}

		public function getNotifications(){
			return Notification::getAllToUser($this);
		}

		public function getUnreadNotifications(){
			$notifications = $this->getNotifications();
			if(!$notifications){
				return false;
			}

			foreach($notifications as $not){
				if(!$not->getRead()){
					$nots[] = $not;
				}
			}
			return $nots;
		}

		public function hasUnreadNotifications(){
			return ($this->getUnreadNotifications() > 0);
		}

		public function sendNotification($to, $message){
			Notification::send($to, $this, $message);
		}

		private function assignFromJson(){
			if(!$isFilled){
				$page = Cache::get("socialgraph:" . $this->id);
				if(!$page){
					$page = file_get_contents("https://graph.facebook.com/" . $this->id);
					Cache::add("socialgraph:" . $this->id, $page);
				}
				$json = json_decode($page);
				$this->first_name = $json->first_name;
				$this->last_name = $json->last_name;
				$this->gender = $json->gender;
				$this->locale = $json->locale;
				$isFilled = true;
			}
		}

		public function getFirstName(){
			$this->assignFromJson();
			return $this->first_name;
		}

		public function getLastName(){
			$this->assignFromJson();
			return $this->last_name;
		}

		public function getName(){
			return $this->getFirstName() . " " . $this->getLastName();
		}

		public function getGender(){
			$this->assignFromJson();
			return $this->gender;
		}

		public function getLocale(){
			$this->assignFromJson();
			return $this->locale;
		}
		
		public function getPermissions(){
			if(!$user->permissions){
				$this->permissions = Permissions::getPermissionsForUser($this->id);
			}
			
			return $this->permissions;
		}

		private $currentShit = null;
		
		public function getShit(){
			if($this->currentShit === null){
				$query = "SELECT `points` FROM `users` WHERE `fb_id` = '%s';";
				$newshit = Sql::get(sprintf($query, $this->getId()));
				$this->currentShit = $newshit[0]["points"];
				if(strrpos($this->currentShit, ".") !== false){
					$this->currentShit = substr($this->currentShit, 0, strrpos($this->currentShit, "."));
				}
			}
			return $this->currentShit;
		}

		public function getFormattedShit(){
			return $this->formatShit($this->getShit());
		}

		private function formatShit($shit){
			if(rand(0,10) == 1){
				for($i = 1; $i < strlen($shit); $i++){
					if(rand(0,100) < 30){
						$shit = substr($shit, 0, $i) . "," . substr($shit, $i);
						$i++;
					}
				}
			}else{
				for($i = strlen($shit) - 3; $i > 0; $i -= 3){
					$shit = substr($shit, 0, $i) . "," . substr($shit, $i);
				}
			}

			return $shit;
		}

		public function getShortShit($maxLen = 25){
			if(strlen($this->getShit()) <= $maxLen){
				return $this->getFormattedShit();
			}else{
				$shit = $this->getShit();
				$exp = strlen($shit) - $maxLen;
				$shit = substr($shit, 0, $maxLen);
				$shit = $this->formatShit($shit);
				return $shit . " x 10<sup>" . $exp . "</sup>";
			}
		}

		public function setShit($new){
			$query = "UPDATE `users` SET `points` = %s WHERE `fb_id` = '%s';";
			Sql::exec(sprintf($query, $new, $this->getId()));
			$this->currentShit = $new;
		}

		public function pushShit($amount = 1){
			$this->setShit($this->getShit() + $amount);
		}

		public function popShit($amount = 1){
			$this->setShit($this->getShit() - $amount);
		}

		public function sendShit($amount, $to){
			$u = new User($to);
			$u->pushShit($amount);
			$this->popShit($amount);
		}
	}
?>
