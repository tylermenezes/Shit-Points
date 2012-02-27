<?php
	class Permissions{
		private $can_moderate;
		private $can_debug;
		private $can_editprofiles;
		private $can_forcematchpercent;
		private $can_modifysettings;
		
		public function __construct($can_moderate, $can_debug, $can_editprofiles, $can_forcematchpercent, $can_modifysettings){
			$this->can_moderate = $can_moderate;
			$this->can_debug = $can_debug;
			$this->can_editprofiles = $can_editprofiles;
			$this->can_forcematchpercent = $can_forcematchpercent;
			$this->can_modifysettings = $can_modifysettings;
		}
		
		public function canModerate(){
			return $this->can_moderate;
		}
		
		public function canDebug(){
			return $this->can_debug;
		}
		
		public function canEditProfiles(){
			return $this->can_editprofiles;
		}
		
		public function canForceMatchPercent(){
			return $this->can_forcematchpercent;
		}
		
		public function canModifySettings(){
			return $this->can_modifysettings;
		}
		
		public static function getPermissionsForUser($id){
			$query = "
				SELECT *
				FROM `permissions`
				
				WHERE `id` =
					(
						SELECT `permission_id`
						FROM `users`
						
						WHERE `fb_id` = %d
					)
					
				LIMIT 1;
			";
			
			$results = Sql::get(sprintf($query, $id), 60);
			$results = $results[0];
			
			return new Permissions($results["can_moderate"], $results["can_debug"], $results["can_editprofiles"], $results["can_forcematchpercent"], $results["can_modifysettings"]);
		}
	}
	
	function requireDebug(){
		if(!currentUser()->getPermissions()->canDebug()){
			die("You don't have permissions to do that!");
		}
	}
?>