<?php
class FacebookRestClient {
	public $secret;
	public $session_key;
	public $api_key;
	public $friends_list;
	public $user;
	public $added;
	public $is_user;
	public $canvas_user;
	public $batch_mode;
	private $batch_queue;
	private $pending_batch;
	private $call_as_apikey;
	private $use_curl_if_available;
	private $format = null;
	private $using_session_secret = false;
	private $rawData = null;

	const BATCH_MODE_DEFAULT = 0;
	const BATCH_MODE_SERVER_PARALLEL = 0;
	const BATCH_MODE_SERIAL_ONLY = 2;

	public function __construct($api_key, $secret, $session_key=null) {
		$this->secret			 = $secret;
		$this->session_key	= $session_key;
		$this->api_key			= $api_key;
		$this->batch_mode = FacebookRestClient::BATCH_MODE_DEFAULT;
		$this->last_call_id = 0;
		$this->call_as_apikey = '';
		$this->use_curl_if_available = true;
		$this->server_addr =
			Facebook::get_facebook_url('api') . '/restserver.php';
		$this->photo_server_addr =
			Facebook::get_facebook_url('api-photo') . '/restserver.php';

		if (!empty($GLOBALS['facebook_config']['debug'])) {
			$this->cur_id = 0;
			?>
<script type="text/javascript">
var types = ['params', 'xml', 'php', 'sxml'];
function getStyle(elem, style) {
	if (elem.getStyle) {
		return elem.getStyle(style);
	} else {
		return elem.style[style];
	}
}
function setStyle(elem, style, value) {
	if (elem.setStyle) {
		elem.setStyle(style, value);
	} else {
		elem.style[style] = value;
	}
}
function toggleDisplay(id, type) {
	for (var i = 0; i < types.length; i++) {
		var t = types[i];
		var pre = document.getElementById(t + id);
		if (pre) {
			if (t != type || getStyle(pre, 'display') == 'block') {
				setStyle(pre, 'display', 'none');
			} else {
				setStyle(pre, 'display', 'block');
			}
		}
	}
	return false;
}
</script>
<?php
		}
	}

	public function set_user($uid) {
		$this->user = $uid;
	}


	public function use_session_secret($session_secret) {
		$this->secret = $session_secret;
		$this->using_session_secret = true;
	}

	public function set_use_curl_if_available($use_curl_if_available) {
		$this->use_curl_if_available = $use_curl_if_available;
	}

	public function begin_batch() {
		if ($this->pending_batch()) {
			$code = FacebookAPIErrorCodes::API_EC_BATCH_ALREADY_STARTED;
			$description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
			throw new FacebookRestClientException($description, $code);
		}

		$this->batch_queue = array();
		$this->pending_batch = true;
	}

	public function end_batch() {
		if (!$this->pending_batch()) {
			$code = FacebookAPIErrorCodes::API_EC_BATCH_NOT_STARTED;
			$description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
			throw new FacebookRestClientException($description, $code);
		}

		$this->pending_batch = false;

		$this->execute_server_side_batch();
		$this->batch_queue = null;
	}

	public function pending_batch() {
		return $this->pending_batch;
	}

	private function execute_server_side_batch() {
		$item_count = count($this->batch_queue);
		$method_feed = array();
		foreach ($this->batch_queue as $batch_item) {
			$method = $batch_item['m'];
			$params = $batch_item['p'];
			list($get, $post) = $this->finalize_params($method, $params);
			$method_feed[] = $this->create_url_string(array_merge($post, $get));
		}

		$serial_only =
			($this->batch_mode == FacebookRestClient::BATCH_MODE_SERIAL_ONLY);

		$params = array('method_feed' => json_encode($method_feed),
										'serial_only' => $serial_only,
										'format' => $this->format);
		$result = $this->call_method('facebook.batch.run', $params);

		if (is_array($result) && isset($result['error_code'])) {
			throw new FacebookRestClientException($result['error_msg'],
																						$result['error_code']);
		}

		for ($i = 0; $i < $item_count; $i++) {
			$batch_item = $this->batch_queue[$i];
			$batch_item['p']['format'] = $this->format;
			$batch_item_result = $this->convert_result($result[$i],
																								 $batch_item['m'],
																								 $batch_item['p']);

			if (is_array($batch_item_result) &&
					isset($batch_item_result['error_code'])) {
				throw new FacebookRestClientException($batch_item_result['error_msg'],
																							$batch_item_result['error_code']);
			}
			$batch_item['r'] = $batch_item_result;
		}
	}

	public function begin_permissions_mode($permissions_apikey) {
		$this->call_as_apikey = $permissions_apikey;
	}

	public function end_permissions_mode() {
		$this->call_as_apikey = '';
	}

	public function set_use_ssl_resources($is_ssl = true) {
		$this->use_ssl_resources = $is_ssl;
	}

	public function application_getPublicInfo($application_id=null,
																						$application_api_key=null,
																						$application_canvas_name=null) {
		return $this->call_method('facebook.application.getPublicInfo',
				array('application_id' => $application_id,
							'application_api_key' => $application_api_key,
							'application_canvas_name' => $application_canvas_name));
	}

	public function auth_createToken() {
		return $this->call_method('facebook.auth.createToken');
	}

	public function auth_getSession($auth_token,
																	$generate_session_secret = false,
																	$host_url = null) {
		if (!$this->pending_batch()) {
			$result = $this->call_method(
				'facebook.auth.getSession',
				array('auth_token' => $auth_token,
							'generate_session_secret' => $generate_session_secret,
							'host_url' => $host_url));
			$this->session_key = $result['session_key'];

			if (!empty($result['secret']) && !$generate_session_secret) {
				$this->secret = $result['secret'];
			}

			return $result;
		}
	}

	public function auth_promoteSession() {
			return $this->call_method('facebook.auth.promoteSession');
	}

	public function auth_expireSession() {
			return $this->call_method('facebook.auth.expireSession');
	}

	public function auth_revokeExtendedPermission($perm, $uid=null) {
		return $this->call_method('facebook.auth.revokeExtendedPermission',
				array('perm' => $perm, 'uid' => $uid));
	}

	public function auth_revokeAuthorization($uid=null) {
			return $this->call_method('facebook.auth.revokeAuthorization',
					array('uid' => $uid));
	}

	public function auth_getAppPublicKey($target_app_key) {
		return $this->call_method('facebook.auth.getAppPublicKey',
					array('target_app_key' => $target_app_key));
	}

	public function auth_getSignedPublicSessionData() {
		return $this->call_method('facebook.auth.getSignedPublicSessionData',
															array());
	}

	public function connect_getUnconnectedFriendsCount() {
		return $this->call_method('facebook.connect.getUnconnectedFriendsCount',
				array());
	}

	public function connect_registerUsers($accounts) {
		return $this->call_method('facebook.connect.registerUsers',
				array('accounts' => $accounts));
	}

	public function connect_unregisterUsers($email_hashes) {
		return $this->call_method('facebook.connect.unregisterUsers',
				array('email_hashes' => $email_hashes));
	}

	public function &events_get($uid=null,
															$eids=null,
															$start_time=null,
															$end_time=null,
															$rsvp_status=null) {
		return $this->call_method('facebook.events.get',
				array('uid' => $uid,
							'eids' => $eids,
							'start_time' => $start_time,
							'end_time' => $end_time,
							'rsvp_status' => $rsvp_status));
	}

	public function &events_getMembers($eid) {
		return $this->call_method('facebook.events.getMembers',
			array('eid' => $eid));
	}

	public function &events_rsvp($eid, $rsvp_status) {
		return $this->call_method('facebook.events.rsvp',
				array(
				'eid' => $eid,
				'rsvp_status' => $rsvp_status));
	}

	public function &events_cancel($eid, $cancel_message='') {
		return $this->call_method('facebook.events.cancel',
				array('eid' => $eid,
							'cancel_message' => $cancel_message));
	}

	public function events_create($event_info, $file = null) {
		if ($file) {
			return $this->call_upload_method('facebook.events.create',
				array('event_info' => $event_info),
				$file,
				$this->photo_server_addr);
		} else {
			return $this->call_method('facebook.events.create',
				array('event_info' => $event_info));
		}
	}

	public function events_invite($eid, $uids, $personal_message) {
		return $this->call_method('facebook.events.invite',
															array('eid' => $eid,
																		'uids' => $uids,
																		'personal_message', $personal_message));
	}

	public function events_edit($eid, $event_info, $file = null) {
		if ($file) {
			return $this->call_upload_method('facebook.events.edit',
				array('eid' => $eid, 'event_info' => $event_info),
				$file,
				$this->photo_server_addr);
		} else {
			return $this->call_method('facebook.events.edit',
				array('eid' => $eid,
				'event_info' => $event_info));
		}
	}

	public function &fbml_refreshImgSrc($url) {
		return $this->call_method('facebook.fbml.refreshImgSrc',
				array('url' => $url));
	}

	public function &fbml_refreshRefUrl($url) {
		return $this->call_method('facebook.fbml.refreshRefUrl',
				array('url' => $url));
	}

	public function &fbml_setRefHandle($handle, $fbml) {
		return $this->call_method('facebook.fbml.setRefHandle',
				array('handle' => $handle, 'fbml' => $fbml));
	}

	public function &fbml_registerCustomTags($tags) {
		$tags = json_encode($tags);
		return $this->call_method('facebook.fbml.registerCustomTags',
															array('tags' => $tags));
	}

	public function &fbml_getCustomTags($app_id = null) {
		return $this->call_method('facebook.fbml.getCustomTags',
															array('app_id' => $app_id));
	}

	public function &fbml_deleteCustomTags($tag_names = null) {
		return $this->call_method('facebook.fbml.deleteCustomTags',
															array('tag_names' => json_encode($tag_names)));
	}

	public function &intl_getTranslations($locale = 'en_US', $all = false) {
		return $this->call_method('facebook.intl.getTranslations',
															array('locale' => $locale,
																		'all'		=> $all));
	}

	public function &intl_uploadNativeStrings($native_strings) {
		return $this->call_method('facebook.intl.uploadNativeStrings',
				array('native_strings' => json_encode($native_strings)));
	}

	public function &feed_publishTemplatizedAction($title_template,
																								 $title_data,
																								 $body_template,
																								 $body_data,
																								 $body_general,
																								 $image_1=null,
																								 $image_1_link=null,
																								 $image_2=null,
																								 $image_2_link=null,
																								 $image_3=null,
																								 $image_3_link=null,
																								 $image_4=null,
																								 $image_4_link=null,
																								 $target_ids='',
																								 $page_actor_id=null) {
		return $this->call_method('facebook.feed.publishTemplatizedAction',
			array('title_template' => $title_template,
						'title_data' => $title_data,
						'body_template' => $body_template,
						'body_data' => $body_data,
						'body_general' => $body_general,
						'image_1' => $image_1,
						'image_1_link' => $image_1_link,
						'image_2' => $image_2,
						'image_2_link' => $image_2_link,
						'image_3' => $image_3,
						'image_3_link' => $image_3_link,
						'image_4' => $image_4,
						'image_4_link' => $image_4_link,
						'target_ids' => $target_ids,
						'page_actor_id' => $page_actor_id));
	}

	public function &feed_registerTemplateBundle($one_line_story_templates,
																							 $short_story_templates = array(),
																							 $full_story_template = null,
																							 $action_links = array()) {

		$one_line_story_templates = json_encode($one_line_story_templates);

		if (!empty($short_story_templates)) {
			$short_story_templates = json_encode($short_story_templates);
		}

		if (isset($full_story_template)) {
			$full_story_template = json_encode($full_story_template);
		}

		if (isset($action_links)) {
			$action_links = json_encode($action_links);
		}

		return $this->call_method('facebook.feed.registerTemplateBundle',
				array('one_line_story_templates' => $one_line_story_templates,
							'short_story_templates' => $short_story_templates,
							'full_story_template' => $full_story_template,
							'action_links' => $action_links));
	}







	public function &feed_getRegisteredTemplateBundles() {
		return $this->call_method('facebook.feed.getRegisteredTemplateBundles',
				array());
	}









	public function &feed_getRegisteredTemplateBundleByID($template_bundle_id) {
		return $this->call_method('facebook.feed.getRegisteredTemplateBundleByID',
				array('template_bundle_id' => $template_bundle_id));
	}








	public function &feed_deactivateTemplateBundleByID($template_bundle_id) {
		return $this->call_method('facebook.feed.deactivateTemplateBundleByID',
				array('template_bundle_id' => $template_bundle_id));
	}

	const STORY_SIZE_ONE_LINE = 1;
	const STORY_SIZE_SHORT = 2;
	const STORY_SIZE_FULL = 4;























	public function &feed_publishUserAction(
			$template_bundle_id, $template_data, $target_ids='', $body_general='',
			$story_size=FacebookRestClient::STORY_SIZE_ONE_LINE,
			$user_message='') {

		if (is_array($template_data)) {
			$template_data = json_encode($template_data);
		} // allow client to either pass in JSON or an assoc that we JSON for them

		if (is_array($target_ids)) {
			$target_ids = json_encode($target_ids);
			$target_ids = trim($target_ids, "[]"); // we don't want square brackets
		}

		return $this->call_method('facebook.feed.publishUserAction',
				array('template_bundle_id' => $template_bundle_id,
							'template_data' => $template_data,
							'target_ids' => $target_ids,
							'body_general' => $body_general,
							'story_size' => $story_size,
							'user_message' => $user_message));
	}













	public function stream_publish(
		$message, $attachment = null, $action_links = null, $target_id = null,
		$uid = null) {

		return $this->call_method(
			'facebook.stream.publish',
			array('message' => $message,
						'attachment' => $attachment,
						'action_links' => $action_links,
						'target_id' => $target_id,
						'uid' => $this->get_uid($uid)));
	}









	public function stream_remove($post_id, $uid = null) {
		return $this->call_method(
			'facebook.stream.remove',
			array('post_id' => $post_id,
						'uid' => $this->get_uid($uid)));
	}









	public function stream_addComment($post_id, $comment, $uid = null) {
		return $this->call_method(
			'facebook.stream.addComment',
			array('post_id' => $post_id,
						'comment' => $comment,
						'uid' => $this->get_uid($uid)));
	}









	public function stream_removeComment($comment_id, $uid = null) {
		return $this->call_method(
			'facebook.stream.removeComment',
			array('comment_id' => $comment_id,
						'uid' => $this->get_uid($uid)));
	}








	public function stream_addLike($post_id, $uid = null) {
		return $this->call_method(
			'facebook.stream.addLike',
			array('post_id' => $post_id,
						'uid' => $this->get_uid($uid)));
	}








	public function stream_removeLike($post_id, $uid = null) {
		return $this->call_method(
			'facebook.stream.removeLike',
			array('post_id' => $post_id,
						'uid' => $this->get_uid($uid)));
	}








	public function &feed_getAppFriendStories() {
		return $this->call_method('facebook.feed.getAppFriendStories');
	}










	public function &fql_query($query) {
		return $this->call_method('facebook.fql.query',
			array('query' => $query));
	}











	public function &fql_multiquery($queries) {
		return $this->call_method('facebook.fql.multiquery',
			array('queries' => $queries));
	}

















	public function &friends_areFriends($uids1, $uids2) {
		return $this->call_method('facebook.friends.areFriends',
								 array('uids1' => $uids1,
											 'uids2' => $uids2));
	}









	public function &friends_get($flid=null, $uid = null) {
		if (isset($this->friends_list)) {
			return $this->friends_list;
		}
		$params = array();
		if (!$uid && isset($this->canvas_user)) {
			$uid = $this->canvas_user;
		}
		if ($uid) {
			$params['uid'] = $uid;
		}
		if ($flid) {
			$params['flid'] = $flid;
		}
		return $this->call_method('facebook.friends.get', $params);

	}












	public function &friends_getMutualFriends($target_uid, $source_uid = null) {
		return $this->call_method('facebook.friends.getMutualFriends',
															array("target_uid" => $target_uid,
																		"source_uid" => $source_uid));
	}






	public function &friends_getLists() {
		return $this->call_method('facebook.friends.getLists');
	}







	public function &friends_getAppUsers() {
		return $this->call_method('facebook.friends.getAppUsers');
	}












	public function &groups_get($uid, $gids) {
		return $this->call_method('facebook.groups.get',
				array('uid' => $uid,
							'gids' => $gids));
	}









	public function &groups_getMembers($gid) {
		return $this->call_method('facebook.groups.getMembers',
			array('gid' => $gid));
	}










	public function data_getCookies($uid, $name) {
		return $this->call_method('facebook.data.getCookies',
				array('uid' => $uid,
							'name' => $name));
	}












	public function data_setCookie($uid, $name, $value, $expires, $path) {
		return $this->call_method('facebook.data.setCookie',
				array('uid' => $uid,
							'name' => $name,
							'value' => $value,
							'expires' => $expires,
							'path' => $path));
	}











	public function &links_get($uid, $limit, $link_ids = null) {
		return $this->call_method('links.get',
				array('uid' => $uid,
							'limit' => $limit,
							'link_ids' => $link_ids));
	}











	public function &links_post($url, $comment='', $uid = null) {
		return $this->call_method('links.post',
				array('uid' => $uid,
							'url' => $url,
							'comment' => $comment));
	}












	public function permissions_checkGrantedApiAccess($permissions_apikey) {
		return $this->call_method('facebook.permissions.checkGrantedApiAccess',
				array('permissions_apikey' => $permissions_apikey));
	}








	public function permissions_checkAvailableApiAccess($permissions_apikey) {
		return $this->call_method('facebook.permissions.checkAvailableApiAccess',
				array('permissions_apikey' => $permissions_apikey));
	}











	public function permissions_grantApiAccess($permissions_apikey, $method_arr) {
		return $this->call_method('facebook.permissions.grantApiAccess',
				array('permissions_apikey' => $permissions_apikey,
							'method_arr' => $method_arr));
	}








	public function permissions_revokeApiAccess($permissions_apikey) {
		return $this->call_method('facebook.permissions.revokeApiAccess',
				array('permissions_apikey' => $permissions_apikey));
	}











	public function payments_setProperties($properties) {
		return $this->call_method ('facebook.payments.setProperties',
				array('properties' => json_encode($properties)));
	}

	public function payments_getOrderDetails($order_id) {
		return json_decode($this->call_method(
				'facebook.payments.getOrderDetails',
				array('order_id' => $order_id)), true);
	}

	public function payments_updateOrder($order_id, $status,
																				 $params) {
		return $this->call_method('facebook.payments.updateOrder',
				array('order_id' => $order_id,
							'status' => $status,
							'params' => json_encode($params)));
	}

	public function payments_getOrders($status, $start_time,
																			 $end_time, $test_mode=false) {
		return json_decode($this->call_method('facebook.payments.getOrders',
				array('status' => $status,
							'start_time' => $start_time,
							'end_time' => $end_time,
							'test_mode' => $test_mode)), true);
	}










	public function gifts_get() {
		return json_decode(
				$this->call_method('facebook.gifts.get',
													 array()),
				true
				);
	}








	public function gifts_update($update_array) {
		return json_decode(
			$this->call_method('facebook.gifts.update',
												 array('update_str' => json_encode($update_array))
												),
			true
		);
	}













	public function dashboard_setNews($uid, $news) {
		return $this->call_method('facebook.dashboard.setNews',
															array('uid'	=> $uid,
																		'news' => $news)
														 );
	}








	public function dashboard_getNews($uid) {
		return json_decode(
			$this->call_method('facebook.dashboard.getNews',
												 array('uid' => $uid)
												), true);
	}








	public function dashboard_clearNews($uid) {
		return $this->call_method('facebook.dashboard.clearNews',
															array('uid' => $uid)
														 );
	}













	public function &notes_create($title, $content, $uid = null) {
		return $this->call_method('notes.create',
				array('uid' => $uid,
							'title' => $title,
							'content' => $content));
	}










	public function &notes_delete($note_id, $uid = null) {
		return $this->call_method('notes.delete',
				array('uid' => $uid,
							'note_id' => $note_id));
	}













	public function &notes_edit($note_id, $title, $content, $uid = null) {
		return $this->call_method('notes.edit',
				array('uid' => $uid,
							'note_id' => $note_id,
							'title' => $title,
							'content' => $content));
	}













	public function &notes_get($uid, $note_ids = null) {
		return $this->call_method('notes.get',
				array('uid' => $uid,
							'note_ids' => $note_ids));
	}










	public function &notifications_get() {
		return $this->call_method('facebook.notifications.get');
	}








	public function &notifications_send($to_ids, $notification, $type) {
		return $this->call_method('facebook.notifications.send',
				array('to_ids' => $to_ids,
							'notification' => $notification,
							'type' => $type));
	}













	public function &notifications_sendEmail($recipients,
																					 $subject,
																					 $text,
																					 $fbml) {
		return $this->call_method('facebook.notifications.sendEmail',
				array('recipients' => $recipients,
							'subject' => $subject,
							'text' => $text,
							'fbml' => $fbml));
	}













	public function &pages_getInfo($page_ids, $fields, $uid, $type) {
		return $this->call_method('facebook.pages.getInfo',
				array('page_ids' => $page_ids,
							'fields' => $fields,
							'uid' => $uid,
							'type' => $type));
	}









	public function &pages_isAdmin($page_id, $uid = null) {
		return $this->call_method('facebook.pages.isAdmin',
				array('page_id' => $page_id,
							'uid' => $uid));
	}








	public function &pages_isAppAdded($page_id) {
		return $this->call_method('facebook.pages.isAppAdded',
				array('page_id' => $page_id));
	}









	public function &pages_isFan($page_id, $uid = null) {
		return $this->call_method('facebook.pages.isFan',
				array('page_id' => $page_id,
							'uid' => $uid));
	}




























	public function &photos_addTag($pid,
																 $tag_uid,
																 $tag_text,
																 $x,
																 $y,
																 $tags,
																 $owner_uid=0) {
		return $this->call_method('facebook.photos.addTag',
				array('pid' => $pid,
							'tag_uid' => $tag_uid,
							'tag_text' => $tag_text,
							'x' => $x,
							'y' => $y,
							'tags' => (is_array($tags)) ? json_encode($tags) : null,
							'owner_uid' => $this->get_uid($owner_uid)));
	}
















	public function &photos_createAlbum($name,
																			$description='',
																			$location='',
																			$visible='',
																			$uid=0) {
		return $this->call_method('facebook.photos.createAlbum',
				array('name' => $name,
							'description' => $description,
							'location' => $location,
							'visible' => $visible,
							'uid' => $this->get_uid($uid)));
	}















	public function &photos_get($subj_id, $aid, $pids) {
		return $this->call_method('facebook.photos.get',
			array('subj_id' => $subj_id, 'aid' => $aid, 'pids' => $pids));
	}













	public function &photos_getAlbums($uid, $aids) {
		return $this->call_method('facebook.photos.getAlbums',
			array('uid' => $uid,
						'aids' => $aids));
	}










	public function &photos_getTags($pids) {
		return $this->call_method('facebook.photos.getTags',
			array('pids' => $pids));
	}













	public function photos_upload($file, $aid=null, $caption=null, $uid=null) {
		return $this->call_upload_method('facebook.photos.upload',
																		 array('aid' => $aid,
																					 'caption' => $caption,
																					 'uid' => $uid),
																		 $file);
	}











	public function video_upload($file, $title=null, $description=null) {
		return $this->call_upload_method('facebook.video.upload',
																		 array('title' => $title,
																					 'description' => $description),
																		 $file,
																		 Facebook::get_facebook_url('api-video') . '/restserver.php');
	}








	public function &video_getUploadLimits() {
		return $this->call_method('facebook.video.getUploadLimits');
	}









	public function &users_getInfo($uids, $fields) {
		return $this->call_method('facebook.users.getInfo',
									array('uids' => $uids,
												'fields' => $fields));
	}
















	public function &users_getStandardInfo($uids, $fields) {
		return $this->call_method('facebook.users.getStandardInfo',
															array('uids' => $uids,
																		'fields' => $fields));
	}






	public function &users_getLoggedInUser() {
		return $this->call_method('facebook.users.getLoggedInUser');
	}







	public function &users_hasAppPermission($ext_perm, $uid=null) {
		return $this->call_method('facebook.users.hasAppPermission',
				array('ext_perm' => $ext_perm, 'uid' => $uid));
	}







	public function &users_isAppUser($uid=null) {
		if ($uid === null && isset($this->is_user)) {
			return $this->is_user;
		}

		return $this->call_method('facebook.users.isAppUser', array('uid' => $uid));
	}








	public function &users_isVerified() {
		return $this->call_method('facebook.users.isVerified');
	}


















	public function &users_setStatus($status,
																	 $uid = null,
																	 $clear = false,
																	 $status_includes_verb = true) {
		$args = array(
			'status' => $status,
			'uid' => $uid,
			'clear' => $clear,
			'status_includes_verb' => $status_includes_verb,
		);
		return $this->call_method('facebook.users.setStatus', $args);
	}









	public function &comments_get($xid) {
		$args = array('xid' => $xid);
		return $this->call_method('facebook.comments.get', $args);
	}
















	public function &comments_add($xid, $text, $uid=0, $title='', $url='',
																$publish_to_stream=false) {
		$args = array(
			'xid'							 => $xid,
			'uid'							 => $this->get_uid($uid),
			'text'							=> $text,
			'title'						 => $title,
			'url'							 => $url,
			'publish_to_stream' => $publish_to_stream);

		return $this->call_method('facebook.comments.add', $args);
	}










	public function &comments_remove($xid, $comment_id) {
		$args = array(
			'xid'				=> $xid,
			'comment_id' => $comment_id);
		return $this->call_method('facebook.comments.remove', $args);
	}
























	public function &stream_get($viewer_id = null,
															$source_ids = null,
															$start_time = 0,
															$end_time = 0,
															$limit = 30,
															$filter_key = '',
															$exportable_only = false,
															$metadata = null,
															$post_ids = null) {
		$args = array(
			'viewer_id'	=> $viewer_id,
			'source_ids' => $source_ids,
			'start_time' => $start_time,
			'end_time'	 => $end_time,
			'limit'			=> $limit,
			'filter_key' => $filter_key,
			'exportable_only' => $exportable_only,
			'metadata' => $metadata,
			'post_ids' => $post_ids);
		return $this->call_method('facebook.stream.get', $args);
	}











	public function &stream_getFilters($uid = null) {
		$args = array('uid' => $uid);
		return $this->call_method('facebook.stream.getFilters', $args);
	}










	public function &stream_getComments($post_id) {
		$args = array('post_id' => $post_id);
		return $this->call_method('facebook.stream.getComments', $args);
	}















	function profile_setFBML($markup,
													 $uid=null,
													 $profile='',
													 $profile_action='',
													 $mobile_profile='',
													 $profile_main='') {
		return $this->call_method('facebook.profile.setFBML',
				array('markup' => $markup,
							'uid' => $uid,
							'profile' => $profile,
							'profile_action' => $profile_action,
							'mobile_profile' => $mobile_profile,
							'profile_main' => $profile_main));
	}











	public function &profile_getFBML($uid=null, $type=null) {
		return $this->call_method('facebook.profile.getFBML',
				array('uid' => $uid,
							'type' => $type));
	}













	public function &profile_getInfo($uid=null) {
		return $this->call_method('facebook.profile.getInfo',
				array('uid' => $uid));
	}









	public function &profile_getInfoOptions($field) {
		return $this->call_method('facebook.profile.getInfoOptions',
				array('field' => $field));
	}















	public function &profile_setInfo($title, $type, $info_fields, $uid=null) {
		return $this->call_method('facebook.profile.setInfo',
				array('uid' => $uid,
							'type' => $type,
							'title'	 => $title,
							'info_fields' => json_encode($info_fields)));
	}












	public function profile_setInfoOptions($field, $options) {
		return $this->call_method('facebook.profile.setInfoOptions',
				array('field'	 => $field,
							'options' => json_encode($options)));
	}

	/////////////////////////////////////////////////////////////////////////////
	// Data Store API














	public function &data_setUserPreference($pref_id, $value, $uid = null) {
		return $this->call_method('facebook.data.setUserPreference',
			 array('pref_id' => $pref_id,
						 'value' => $value,
						 'uid' => $this->get_uid($uid)));
	}















	public function &data_setUserPreferences($values,
																					 $replace = false,
																					 $uid = null) {
		return $this->call_method('facebook.data.setUserPreferences',
			 array('values' => json_encode($values),
						 'replace' => $replace,
						 'uid' => $this->get_uid($uid)));
	}














	public function &data_getUserPreference($pref_id, $uid = null) {
		return $this->call_method('facebook.data.getUserPreference',
			 array('pref_id' => $pref_id,
						 'uid' => $this->get_uid($uid)));
	}












	public function &data_getUserPreferences($uid = null) {
		return $this->call_method('facebook.data.getUserPreferences',
			 array('uid' => $this->get_uid($uid)));
	}














	public function &data_createObjectType($name) {
		return $this->call_method('facebook.data.createObjectType',
			 array('name' => $name));
	}














	public function &data_dropObjectType($obj_type) {
		return $this->call_method('facebook.data.dropObjectType',
			 array('obj_type' => $obj_type));
	}
















	public function &data_renameObjectType($obj_type, $new_name) {
		return $this->call_method('facebook.data.renameObjectType',
			 array('obj_type' => $obj_type,
						 'new_name' => $new_name));
	}
















	public function &data_defineObjectProperty($obj_type,
																						 $prop_name,
																						 $prop_type) {
		return $this->call_method('facebook.data.defineObjectProperty',
			 array('obj_type' => $obj_type,
						 'prop_name' => $prop_name,
						 'prop_type' => $prop_type));
	}















	public function &data_undefineObjectProperty($obj_type, $prop_name) {
		return $this->call_method('facebook.data.undefineObjectProperty',
			 array('obj_type' => $obj_type,
						 'prop_name' => $prop_name));
	}

















	public function &data_renameObjectProperty($obj_type, $prop_name,
																						$new_name) {
		return $this->call_method('facebook.data.renameObjectProperty',
			 array('obj_type' => $obj_type,
						 'prop_name' => $prop_name,
						 'new_name' => $new_name));
	}











	public function &data_getObjectTypes() {
		return $this->call_method('facebook.data.getObjectTypes');
	}














	public function &data_getObjectType($obj_type) {
		return $this->call_method('facebook.data.getObjectType',
			 array('obj_type' => $obj_type));
	}















	public function &data_createObject($obj_type, $properties = null) {
		return $this->call_method('facebook.data.createObject',
			 array('obj_type' => $obj_type,
						 'properties' => json_encode($properties)));
	}

















	public function &data_updateObject($obj_id, $properties, $replace = false) {
		return $this->call_method('facebook.data.updateObject',
			 array('obj_id' => $obj_id,
						 'properties' => json_encode($properties),
						 'replace' => $replace));
	}














	public function &data_deleteObject($obj_id) {
		return $this->call_method('facebook.data.deleteObject',
			 array('obj_id' => $obj_id));
	}













	public function &data_deleteObjects($obj_ids) {
		return $this->call_method('facebook.data.deleteObjects',
			 array('obj_ids' => json_encode($obj_ids)));
	}
















	public function &data_getObjectProperty($obj_id, $prop_name) {
		return $this->call_method('facebook.data.getObjectProperty',
			 array('obj_id' => $obj_id,
						 'prop_name' => $prop_name));
	}
















	public function &data_getObject($obj_id, $prop_names = null) {
		return $this->call_method('facebook.data.getObject',
			 array('obj_id' => $obj_id,
						 'prop_names' => json_encode($prop_names)));
	}
















	public function &data_getObjects($obj_ids, $prop_names = null) {
		return $this->call_method('facebook.data.getObjects',
			 array('obj_ids' => json_encode($obj_ids),
						 'prop_names' => json_encode($prop_names)));
	}
















	public function &data_setObjectProperty($obj_id, $prop_name,
																				 $prop_value) {
		return $this->call_method('facebook.data.setObjectProperty',
			 array('obj_id' => $obj_id,
						 'prop_name' => $prop_name,
						 'prop_value' => $prop_value));
	}
















	public function &data_getHashValue($obj_type, $key, $prop_name = null) {
		return $this->call_method('facebook.data.getHashValue',
			 array('obj_type' => $obj_type,
						 'key' => $key,
						 'prop_name' => $prop_name));
	}
















	public function &data_setHashValue($obj_type,
																		 $key,
																		 $value,
																		 $prop_name = null) {
		return $this->call_method('facebook.data.setHashValue',
			 array('obj_type' => $obj_type,
						 'key' => $key,
						 'value' => $value,
						 'prop_name' => $prop_name));
	}

















	public function &data_incHashValue($obj_type,
																		 $key,
																		 $prop_name,
																		 $increment = 1) {
		return $this->call_method('facebook.data.incHashValue',
			 array('obj_type' => $obj_type,
						 'key' => $key,
						 'prop_name' => $prop_name,
						 'increment' => $increment));
	}














	public function &data_removeHashKey($obj_type, $key) {
		return $this->call_method('facebook.data.removeHashKey',
			 array('obj_type' => $obj_type,
						 'key' => $key));
	}














	public function &data_removeHashKeys($obj_type, $keys) {
		return $this->call_method('facebook.data.removeHashKeys',
			 array('obj_type' => $obj_type,
						 'keys' => json_encode($keys)));
	}


















	public function &data_defineAssociation($name, $assoc_type, $assoc_info1,
																				 $assoc_info2, $inverse = null) {
		return $this->call_method('facebook.data.defineAssociation',
			 array('name' => $name,
						 'assoc_type' => $assoc_type,
						 'assoc_info1' => json_encode($assoc_info1),
						 'assoc_info2' => json_encode($assoc_info2),
						 'inverse' => $inverse));
	}














	public function &data_undefineAssociation($name) {
		return $this->call_method('facebook.data.undefineAssociation',
			 array('name' => $name));
	}


















	public function &data_renameAssociation($name, $new_name, $new_alias1 = null,
																				 $new_alias2 = null) {
		return $this->call_method('facebook.data.renameAssociation',
			 array('name' => $name,
						 'new_name' => $new_name,
						 'new_alias1' => $new_alias1,
						 'new_alias2' => $new_alias2));
	}














	public function &data_getAssociationDefinition($name) {
		return $this->call_method('facebook.data.getAssociationDefinition',
			 array('name' => $name));
	}











	public function &data_getAssociationDefinitions() {
		return $this->call_method('facebook.data.getAssociationDefinitions',
			 array());
	}

















	public function &data_setAssociation($name, $obj_id1, $obj_id2, $data = null,
																			$assoc_time = null) {
		return $this->call_method('facebook.data.setAssociation',
			 array('name' => $name,
						 'obj_id1' => $obj_id1,
						 'obj_id2' => $obj_id2,
						 'data' => $data,
						 'assoc_time' => $assoc_time));
	}














	public function &data_setAssociations($assocs, $name = null) {
		return $this->call_method('facebook.data.setAssociations',
			 array('assocs' => json_encode($assocs),
						 'name' => $name));
	}















	public function &data_removeAssociation($name, $obj_id1, $obj_id2) {
		return $this->call_method('facebook.data.removeAssociation',
			 array('name' => $name,
						 'obj_id1' => $obj_id1,
						 'obj_id2' => $obj_id2));
	}














	public function &data_removeAssociations($assocs, $name = null) {
		return $this->call_method('facebook.data.removeAssociations',
			 array('assocs' => json_encode($assocs),
						 'name' => $name));
	}















	public function &data_removeAssociatedObjects($name, $obj_id) {
		return $this->call_method('facebook.data.removeAssociatedObjects',
			 array('name' => $name,
						 'obj_id' => $obj_id));
	}

















	public function &data_getAssociatedObjects($name, $obj_id, $no_data = true) {
		return $this->call_method('facebook.data.getAssociatedObjects',
			 array('name' => $name,
						 'obj_id' => $obj_id,
						 'no_data' => $no_data));
	}
















	public function &data_getAssociatedObjectCount($name, $obj_id) {
		return $this->call_method('facebook.data.getAssociatedObjectCount',
			 array('name' => $name,
						 'obj_id' => $obj_id));
	}
















	public function &data_getAssociatedObjectCounts($name, $obj_ids) {
		return $this->call_method('facebook.data.getAssociatedObjectCounts',
			 array('name' => $name,
						 'obj_ids' => json_encode($obj_ids)));
	}















	public function &data_getAssociations($obj_id1, $obj_id2, $no_data = true) {
		return $this->call_method('facebook.data.getAssociations',
			 array('obj_id1' => $obj_id1,
						 'obj_id2' => $obj_id2,
						 'no_data' => $no_data));
	}








	public function admin_getAppProperties($properties) {
		return json_decode(
				$this->call_method('facebook.admin.getAppProperties',
						array('properties' => json_encode($properties))), true);
	}








	public function admin_setAppProperties($properties) {
		return $this->call_method('facebook.admin.setAppProperties',
			 array('properties' => json_encode($properties)));
	}










	public function admin_setLiveStreamViaLink($xid, $via_href, $via_text) {
		return $this->call_method('facebook.admin.setLiveStreamViaLink',
															array('xid'			=> $xid,
																		'via_href' => $via_href,
																		'via_text' => $via_text));
	}









	public function admin_getLiveStreamViaLink($xid) {
		return $this->call_method('facebook.admin.getLiveStreamViaLink',
															array('xid' => $xid));
	}












	public function &admin_getAllocation($integration_point_name, $uid=null) {
		return $this->call_method('facebook.admin.getAllocation',
				array('integration_point_name' => $integration_point_name,
							'uid' => $uid));
	}













	public function &admin_getMetrics($start_time, $end_time, $period, $metrics) {
		return $this->call_method('admin.getMetrics',
				array('start_time' => $start_time,
							'end_time' => $end_time,
							'period' => $period,
							'metrics' => json_encode($metrics)));
	}













	public function admin_setRestrictionInfo($restriction_info = null) {
		$restriction_str = null;
		if (!empty($restriction_info)) {
			$restriction_str = json_encode($restriction_info);
		}
		return $this->call_method('admin.setRestrictionInfo',
				array('restriction_str' => $restriction_str));
	}











	public function admin_getRestrictionInfo() {
		return json_decode(
				$this->call_method('admin.getRestrictionInfo'),
				true);
	}









	public function admin_banUsers($uids) {
		return $this->call_method(
			'admin.banUsers', array('uids' => json_encode($uids)));
	}








	public function admin_unbanUsers($uids) {
		return $this->call_method(
			'admin.unbanUsers', array('uids' => json_encode($uids)));
	}











	public function admin_getBannedUsers($uids = null) {
		return $this->call_method(
			'admin.getBannedUsers',
			array('uids' => $uids ? json_encode($uids) : null));
	}














	public function &call_method($method, $params = array()) {
		if ($this->format) {
			$params['format'] = $this->format;
		}
		if (!$this->pending_batch()) {
			if ($this->call_as_apikey) {
				$params['call_as_apikey'] = $this->call_as_apikey;
			}
			$data = $this->post_request($method, $params);
			$this->rawData = $data;
			$result = $this->convert_result($data, $method, $params);
			if (is_array($result) && isset($result['error_code'])) {
				throw new FacebookRestClientException($result['error_msg'],
																							$result['error_code']);
			}
		}
		else {
			$result = null;
			$batch_item = array('m' => $method, 'p' => $params, 'r' => & $result);
			$this->batch_queue[] = $batch_item;
		}

		return $result;
	}

	protected function convert_result($data, $method, $params) {
		$is_xml = (empty($params['format']) ||
							 strtolower($params['format']) != 'json');
		return ($is_xml) ? $this->convert_xml_to_result($data, $method, $params)
										 : json_decode($data, true);
	}






	public function setFormat($format) {
		$this->format = $format;
		return $this;
	}






	public function getFormat() {
		return $this->format;
	}







	 public function getRawData() {
		 return $this->rawData;
	 }










	public function call_upload_method($method, $params, $file, $server_addr = null) {
		if (!$this->pending_batch()) {
			if (!file_exists($file)) {
				$code =
					FacebookAPIErrorCodes::API_EC_PARAM;
				$description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
				throw new FacebookRestClientException($description, $code);
			}

			if ($this->format) {
				$params['format'] = $this->format;
			}
			$data = $this->post_upload_request($method,
																				 $params,
																				 $file,
																				 $server_addr);
			$result = $this->convert_result($data, $method, $params);

			if (is_array($result) && isset($result['error_code'])) {
				throw new FacebookRestClientException($result['error_msg'],
																							$result['error_code']);
			}
		}
		else {
			$code =
				FacebookAPIErrorCodes::API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE;
			$description = FacebookAPIErrorCodes::$api_error_descriptions[$code];
			throw new FacebookRestClientException($description, $code);
		}

		return $result;
	}

	protected function convert_xml_to_result($xml, $method, $params) {
		$sxml = simplexml_load_string($xml);
		$result = self::convert_simplexml_to_array($sxml);

		if (!empty($GLOBALS['facebook_config']['debug'])) {
			print '<div style="margin: 10px 30px; padding: 5px; border: 2px solid black; background: gray; color: white; font-size: 12px; font-weight: bold;">';
			$this->cur_id++;
			print $this->cur_id . ': Called ' . $method . ', show ' .
						'<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'params\');">Params</a> | '.
						'<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'xml\');">XML</a> | '.
						'<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'sxml\');">SXML</a> | '.
						'<a href=# onclick="return toggleDisplay(' . $this->cur_id . ', \'php\');">PHP</a>';
			print '<pre id="params'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($params, true).'</pre>';
			print '<pre id="xml'.$this->cur_id.'" style="display: none; overflow: auto;">'.htmlspecialchars($xml).'</pre>';
			print '<pre id="php'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($result, true).'</pre>';
			print '<pre id="sxml'.$this->cur_id.'" style="display: none; overflow: auto;">'.print_r($sxml, true).'</pre>';
			print '</div>';
		}
		return $result;
	}

	protected function finalize_params($method, $params) {
		list($get, $post) = $this->add_standard_params($method, $params);
		$this->convert_array_values_to_json($post);
		$post['sig'] = Facebook::generate_sig(array_merge($get, $post),
																					$this->secret);
		return array($get, $post);
	}

	private function convert_array_values_to_json(&$params) {
		foreach ($params as $key => &$val) {
			if (is_array($val)) {
				$val = json_encode($val);
			}
		}
	}





	private function add_standard_params($method, $params) {
		$post = $params;
		$get = array();
		if ($this->call_as_apikey) {
			$get['call_as_apikey'] = $this->call_as_apikey;
		}
		if ($this->using_session_secret) {
			$get['ss'] = '1';
		}

		$get['method'] = $method;
		$get['session_key'] = $this->session_key;
		$get['api_key'] = $this->api_key;
		$post['call_id'] = microtime(true);
		if ($post['call_id'] <= $this->last_call_id) {
			$post['call_id'] = $this->last_call_id + 0.001;
		}
		$this->last_call_id = $post['call_id'];
		if (isset($post['v'])) {
			$get['v'] = $post['v'];
			unset($post['v']);
		} else {
			$get['v'] = '1.0';
		}
		if (isset($this->use_ssl_resources) &&
				$this->use_ssl_resources) {
			$post['return_ssl_resources'] = true;
		}
		return array($get, $post);
	}

	private function create_url_string($params) {
		$post_params = array();
		foreach ($params as $key => &$val) {
			$post_params[] = $key.'='.urlencode($val);
		}
		return implode('&', $post_params);
	}

	private function run_multipart_http_transaction($method, $params, $file, $server_addr) {

		$boundary = '--------------------------FbMuLtIpArT' .
								sprintf("%010d", mt_rand()) .
								sprintf("%010d", mt_rand());
		$content_type = 'multipart/form-data; boundary=' . $boundary;
		$delimiter = '--' . $boundary;
		$close_delimiter = $delimiter . '--';
		$content_lines = array();
		foreach ($params as $key => &$val) {
			$content_lines[] = $delimiter;
			$content_lines[] = 'Content-Disposition: form-data; name="' . $key . '"';
			$content_lines[] = '';
			$content_lines[] = $val;
		}
		$content_lines[] = $delimiter;
		$content_lines[] =
			'Content-Disposition: form-data; filename="' . $file . '"';
		$content_lines[] = 'Content-Type: application/octet-stream';
		$content_lines[] = '';
		$content_lines[] = file_get_contents($file);
		$content_lines[] = $close_delimiter;
		$content_lines[] = '';
		$content = implode("\r\n", $content_lines);
		return $this->run_http_post_transaction($content_type, $content, $server_addr);
	}

	public function post_request($method, $params) {
		list($get, $post) = $this->finalize_params($method, $params);
		$post_string = $this->create_url_string($post);
		$get_string = $this->create_url_string($get);
		$url_with_get = $this->server_addr . '?' . $get_string;
		if ($this->use_curl_if_available && function_exists('curl_init')) {
			$useragent = 'Facebook API PHP5 Client 1.1 (curl) ' . phpversion();
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url_with_get);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			$result = $this->curl_exec($ch);
			curl_close($ch);
		} else {
			$content_type = 'application/x-www-form-urlencoded';
			$content = $post_string;
			$result = $this->run_http_post_transaction($content_type,
																								 $content,
																								 $url_with_get);
		}
		return $result;
	}







	protected function curl_exec($ch) {
			$result = curl_exec($ch);
			return $result;
	}

	protected function post_upload_request($method, $params, $file, $server_addr = null) {
		$server_addr = $server_addr ? $server_addr : $this->server_addr;
		list($get, $post) = $this->finalize_params($method, $params);
		$get_string = $this->create_url_string($get);
		$url_with_get = $server_addr . '?' . $get_string;
		if ($this->use_curl_if_available && function_exists('curl_init')) {
			$post['_file'] = '@' . $file;
			$useragent = 'Facebook API PHP5 Client 1.1 (curl) ' . phpversion();
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url_with_get);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
			$result = $this->curl_exec($ch);
			curl_close($ch);
		} else {
			$result = $this->run_multipart_http_transaction($method, $post,
																											$file, $url_with_get);
		}
		return $result;
	}

	private function run_http_post_transaction($content_type, $content, $server_addr) {

		$user_agent = 'Facebook API PHP5 Client 1.1 (non-curl) ' . phpversion();
		$content_length = strlen($content);
		$context =
			array('http' =>
							array('method' => 'POST',
										'user_agent' => $user_agent,
										'header' => 'Content-Type: ' . $content_type . "\r\n" .
																'Content-Length: ' . $content_length,
										'content' => $content));
		$context_id = stream_context_create($context);
		$sock = fopen($server_addr, 'r', false, $context_id);

		$result = '';
		if ($sock) {
			while (!feof($sock)) {
				$result .= fgets($sock, 4096);
			}
			fclose($sock);
		}
		return $result;
	}

	public static function convert_simplexml_to_array($sxml) {
		$arr = array();
		if ($sxml) {
			foreach ($sxml as $k => $v) {
				if ($sxml['list']) {
					$arr[] = self::convert_simplexml_to_array($v);
				} else {
					$arr[$k] = self::convert_simplexml_to_array($v);
				}
			}
		}
		if (sizeof($arr) > 0) {
			return $arr;
		} else {
			return (string)$sxml;
		}
	}

	protected function get_uid($uid) {
		return $uid ? $uid : $this->user;
	}
}


class FacebookRestClientException extends Exception {
}

class FacebookAPIErrorCodes {

	const API_EC_SUCCESS = 0;




	const API_EC_UNKNOWN = 1;
	const API_EC_SERVICE = 2;
	const API_EC_METHOD = 3;
	const API_EC_TOO_MANY_CALLS = 4;
	const API_EC_BAD_IP = 5;
	const API_EC_HOST_API = 6;
	const API_EC_HOST_UP = 7;
	const API_EC_SECURE = 8;
	const API_EC_RATE = 9;
	const API_EC_PERMISSION_DENIED = 10;
	const API_EC_DEPRECATED = 11;
	const API_EC_VERSION = 12;
	const API_EC_INTERNAL_FQL_ERROR = 13;
	const API_EC_HOST_PUP = 14;
	const API_EC_SESSION_SECRET_NOT_ALLOWED = 15;
	const API_EC_HOST_READONLY = 16;




	const API_EC_PARAM = 100;
	const API_EC_PARAM_API_KEY = 101;
	const API_EC_PARAM_SESSION_KEY = 102;
	const API_EC_PARAM_CALL_ID = 103;
	const API_EC_PARAM_SIGNATURE = 104;
	const API_EC_PARAM_TOO_MANY = 105;
	const API_EC_PARAM_USER_ID = 110;
	const API_EC_PARAM_USER_FIELD = 111;
	const API_EC_PARAM_SOCIAL_FIELD = 112;
	const API_EC_PARAM_EMAIL = 113;
	const API_EC_PARAM_USER_ID_LIST = 114;
	const API_EC_PARAM_FIELD_LIST = 115;
	const API_EC_PARAM_ALBUM_ID = 120;
	const API_EC_PARAM_PHOTO_ID = 121;
	const API_EC_PARAM_FEED_PRIORITY = 130;
	const API_EC_PARAM_CATEGORY = 140;
	const API_EC_PARAM_SUBCATEGORY = 141;
	const API_EC_PARAM_TITLE = 142;
	const API_EC_PARAM_DESCRIPTION = 143;
	const API_EC_PARAM_BAD_JSON = 144;
	const API_EC_PARAM_BAD_EID = 150;
	const API_EC_PARAM_UNKNOWN_CITY = 151;
	const API_EC_PARAM_BAD_PAGE_TYPE = 152;
	const API_EC_PARAM_BAD_LOCALE = 170;
	const API_EC_PARAM_BLOCKED_NOTIFICATION = 180;




	const API_EC_PERMISSION = 200;
	const API_EC_PERMISSION_USER = 210;
	const API_EC_PERMISSION_NO_DEVELOPERS = 211;
	const API_EC_PERMISSION_OFFLINE_ACCESS = 212;
	const API_EC_PERMISSION_ALBUM = 220;
	const API_EC_PERMISSION_PHOTO = 221;
	const API_EC_PERMISSION_MESSAGE = 230;
	const API_EC_PERMISSION_OTHER_USER = 240;
	const API_EC_PERMISSION_STATUS_UPDATE = 250;
	const API_EC_PERMISSION_PHOTO_UPLOAD = 260;
	const API_EC_PERMISSION_VIDEO_UPLOAD = 261;
	const API_EC_PERMISSION_SMS = 270;
	const API_EC_PERMISSION_CREATE_LISTING = 280;
	const API_EC_PERMISSION_CREATE_NOTE = 281;
	const API_EC_PERMISSION_SHARE_ITEM = 282;
	const API_EC_PERMISSION_EVENT = 290;
	const API_EC_PERMISSION_LARGE_FBML_TEMPLATE = 291;
	const API_EC_PERMISSION_LIVEMESSAGE = 292;
	const API_EC_PERMISSION_CREATE_EVENT = 296;
	const API_EC_PERMISSION_RSVP_EVENT = 299;




	const API_EC_EDIT = 300;
	const API_EC_EDIT_USER_DATA = 310;
	const API_EC_EDIT_PHOTO = 320;
	const API_EC_EDIT_ALBUM_SIZE = 321;
	const API_EC_EDIT_PHOTO_TAG_SUBJECT = 322;
	const API_EC_EDIT_PHOTO_TAG_PHOTO = 323;
	const API_EC_EDIT_PHOTO_FILE = 324;
	const API_EC_EDIT_PHOTO_PENDING_LIMIT = 325;
	const API_EC_EDIT_PHOTO_TAG_LIMIT = 326;
	const API_EC_EDIT_ALBUM_REORDER_PHOTO_NOT_IN_ALBUM = 327;
	const API_EC_EDIT_ALBUM_REORDER_TOO_FEW_PHOTOS = 328;

	const API_EC_MALFORMED_MARKUP = 329;
	const API_EC_EDIT_MARKUP = 330;

	const API_EC_EDIT_FEED_TOO_MANY_USER_CALLS = 340;
	const API_EC_EDIT_FEED_TOO_MANY_USER_ACTION_CALLS = 341;
	const API_EC_EDIT_FEED_TITLE_LINK = 342;
	const API_EC_EDIT_FEED_TITLE_LENGTH = 343;
	const API_EC_EDIT_FEED_TITLE_NAME = 344;
	const API_EC_EDIT_FEED_TITLE_BLANK = 345;
	const API_EC_EDIT_FEED_BODY_LENGTH = 346;
	const API_EC_EDIT_FEED_PHOTO_SRC = 347;
	const API_EC_EDIT_FEED_PHOTO_LINK = 348;

	const API_EC_EDIT_VIDEO_SIZE = 350;
	const API_EC_EDIT_VIDEO_INVALID_FILE = 351;
	const API_EC_EDIT_VIDEO_INVALID_TYPE = 352;
	const API_EC_EDIT_VIDEO_FILE = 353;

	const API_EC_EDIT_FEED_TITLE_ARRAY = 360;
	const API_EC_EDIT_FEED_TITLE_PARAMS = 361;
	const API_EC_EDIT_FEED_BODY_ARRAY = 362;
	const API_EC_EDIT_FEED_BODY_PARAMS = 363;
	const API_EC_EDIT_FEED_PHOTO = 364;
	const API_EC_EDIT_FEED_TEMPLATE = 365;
	const API_EC_EDIT_FEED_TARGET = 366;
	const API_EC_EDIT_FEED_MARKUP = 367;




	const API_EC_SESSION_TIMED_OUT = 450;
	const API_EC_SESSION_METHOD = 451;
	const API_EC_SESSION_INVALID = 452;
	const API_EC_SESSION_REQUIRED = 453;
	const API_EC_SESSION_REQUIRED_FOR_SECRET = 454;
	const API_EC_SESSION_CANNOT_USE_SESSION_SECRET = 455;





	const FQL_EC_UNKNOWN_ERROR = 600;
	const FQL_EC_PARSER = 601;
	const FQL_EC_PARSER_ERROR = 601;
	const FQL_EC_UNKNOWN_FIELD = 602;
	const FQL_EC_UNKNOWN_TABLE = 603;
	const FQL_EC_NOT_INDEXABLE = 604;
	const FQL_EC_NO_INDEX = 604;
	const FQL_EC_UNKNOWN_FUNCTION = 605;
	const FQL_EC_INVALID_PARAM = 606;
	const FQL_EC_INVALID_FIELD = 607;
	const FQL_EC_INVALID_SESSION = 608;
	const FQL_EC_UNSUPPORTED_APP_TYPE = 609;
	const FQL_EC_SESSION_SECRET_NOT_ALLOWED = 610;
	const FQL_EC_DEPRECATED_TABLE = 611;
	const FQL_EC_EXTENDED_PERMISSION = 612;
	const FQL_EC_RATE_LIMIT_EXCEEDED = 613;
	const FQL_EC_UNRESOLVED_DEPENDENCY = 614;
	const FQL_EC_INVALID_SEARCH = 615;
	const FQL_EC_CONTAINS_ERROR = 616;

	const API_EC_REF_SET_FAILED = 700;




	const API_EC_DATA_UNKNOWN_ERROR = 800;
	const API_EC_DATA_INVALID_OPERATION = 801;
	const API_EC_DATA_QUOTA_EXCEEDED = 802;
	const API_EC_DATA_OBJECT_NOT_FOUND = 803;
	const API_EC_DATA_OBJECT_ALREADY_EXISTS = 804;
	const API_EC_DATA_DATABASE_ERROR = 805;
	const API_EC_DATA_CREATE_TEMPLATE_ERROR = 806;
	const API_EC_DATA_TEMPLATE_EXISTS_ERROR = 807;
	const API_EC_DATA_TEMPLATE_HANDLE_TOO_LONG = 808;
	const API_EC_DATA_TEMPLATE_HANDLE_ALREADY_IN_USE = 809;
	const API_EC_DATA_TOO_MANY_TEMPLATE_BUNDLES = 810;
	const API_EC_DATA_MALFORMED_ACTION_LINK = 811;
	const API_EC_DATA_TEMPLATE_USES_RESERVED_TOKEN = 812;




	const API_EC_NO_SUCH_APP = 900;




	const API_EC_BATCH_TOO_MANY_ITEMS = 950;
	const API_EC_BATCH_ALREADY_STARTED = 951;
	const API_EC_BATCH_NOT_STARTED = 952;
	const API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE = 953;




	const API_EC_EVENT_INVALID_TIME = 1000;
	const API_EC_EVENT_NAME_LOCKED	= 1001;




	const API_EC_INFO_NO_INFORMATION = 1050;
	const API_EC_INFO_SET_FAILED = 1051;




	const API_EC_LIVEMESSAGE_SEND_FAILED = 1100;
	const API_EC_LIVEMESSAGE_EVENT_NAME_TOO_LONG = 1101;
	const API_EC_LIVEMESSAGE_MESSAGE_TOO_LONG = 1102;




	const API_EC_PAYMENTS_UNKNOWN = 1150;
	const API_EC_PAYMENTS_APP_INVALID = 1151;
	const API_EC_PAYMENTS_DATABASE = 1152;
	const API_EC_PAYMENTS_PERMISSION_DENIED = 1153;
	const API_EC_PAYMENTS_APP_NO_RESPONSE = 1154;
	const API_EC_PAYMENTS_APP_ERROR_RESPONSE = 1155;
	const API_EC_PAYMENTS_INVALID_ORDER = 1156;
	const API_EC_PAYMENTS_INVALID_PARAM = 1157;
	const API_EC_PAYMENTS_INVALID_OPERATION = 1158;
	const API_EC_PAYMENTS_PAYMENT_FAILED = 1159;
	const API_EC_PAYMENTS_DISABLED = 1160;




	const API_EC_CONNECT_FEED_DISABLED = 1300;




	const API_EC_TAG_BUNDLE_QUOTA = 1400;




	const API_EC_SHARE_BAD_URL = 1500;




	const API_EC_NOTE_CANNOT_MODIFY = 1600;




	const API_EC_COMMENTS_UNKNOWN = 1700;
	const API_EC_COMMENTS_POST_TOO_LONG = 1701;
	const API_EC_COMMENTS_DB_DOWN = 1702;
	const API_EC_COMMENTS_INVALID_XID = 1703;
	const API_EC_COMMENTS_INVALID_UID = 1704;
	const API_EC_COMMENTS_INVALID_POST = 1705;
	const API_EC_COMMENTS_INVALID_REMOVE = 1706;




	const API_EC_GIFTS_UNKNOWN = 1900;




	const API_EC_DISABLED_ALL = 2000;
	const API_EC_DISABLED_STATUS = 2001;
	const API_EC_DISABLED_FEED_STORIES = 2002;
	const API_EC_DISABLED_NOTIFICATIONS = 2003;
	const API_EC_DISABLED_REQUESTS = 2004;
	const API_EC_DISABLED_EMAIL = 2005;






	public static $api_error_descriptions = array(
			self::API_EC_SUCCESS					 => 'Success',
			self::API_EC_UNKNOWN					 => 'An unknown error occurred',
			self::API_EC_SERVICE					 => 'Service temporarily unavailable',
			self::API_EC_METHOD						=> 'Unknown method',
			self::API_EC_TOO_MANY_CALLS		=> 'Application request limit reached',
			self::API_EC_BAD_IP						=> 'Unauthorized source IP address',
			self::API_EC_PARAM						 => 'Invalid parameter',
			self::API_EC_PARAM_API_KEY		 => 'Invalid API key',
			self::API_EC_PARAM_SESSION_KEY => 'Session key invalid or no longer valid',
			self::API_EC_PARAM_CALL_ID		 => 'Call_id must be greater than previous',
			self::API_EC_PARAM_SIGNATURE	 => 'Incorrect signature',
			self::API_EC_PARAM_USER_ID		 => 'Invalid user id',
			self::API_EC_PARAM_USER_FIELD	=> 'Invalid user info field',
			self::API_EC_PARAM_SOCIAL_FIELD => 'Invalid user field',
			self::API_EC_PARAM_USER_ID_LIST => 'Invalid user id list',
			self::API_EC_PARAM_FIELD_LIST => 'Invalid field list',
			self::API_EC_PARAM_ALBUM_ID		=> 'Invalid album id',
			self::API_EC_PARAM_BAD_EID		 => 'Invalid eid',
			self::API_EC_PARAM_UNKNOWN_CITY => 'Unknown city',
			self::API_EC_PERMISSION				=> 'Permissions error',
			self::API_EC_PERMISSION_USER	 => 'User not visible',
			self::API_EC_PERMISSION_NO_DEVELOPERS	=> 'Application has no developers',
			self::API_EC_PERMISSION_ALBUM	=> 'Album not visible',
			self::API_EC_PERMISSION_PHOTO	=> 'Photo not visible',
			self::API_EC_PERMISSION_EVENT	=> 'Creating and modifying events required the extended permission create_event',
			self::API_EC_PERMISSION_RSVP_EVENT => 'RSVPing to events required the extended permission rsvp_event',
			self::API_EC_EDIT_ALBUM_SIZE	 => 'Album is full',
			self::FQL_EC_PARSER						=> 'FQL: Parser Error',
			self::FQL_EC_UNKNOWN_FIELD		 => 'FQL: Unknown Field',
			self::FQL_EC_UNKNOWN_TABLE		 => 'FQL: Unknown Table',
			self::FQL_EC_NOT_INDEXABLE		 => 'FQL: Statement not indexable',
			self::FQL_EC_UNKNOWN_FUNCTION	=> 'FQL: Attempted to call unknown function',
			self::FQL_EC_INVALID_PARAM		 => 'FQL: Invalid parameter passed in',
			self::API_EC_DATA_UNKNOWN_ERROR => 'Unknown data store API error',
			self::API_EC_DATA_INVALID_OPERATION => 'Invalid operation',
			self::API_EC_DATA_QUOTA_EXCEEDED => 'Data store allowable quota was exceeded',
			self::API_EC_DATA_OBJECT_NOT_FOUND => 'Specified object cannot be found',
			self::API_EC_DATA_OBJECT_ALREADY_EXISTS => 'Specified object already exists',
			self::API_EC_DATA_DATABASE_ERROR => 'A database error occurred. Please try again',
			self::API_EC_BATCH_ALREADY_STARTED => 'begin_batch already called, please make sure to call end_batch first',
			self::API_EC_BATCH_NOT_STARTED => 'end_batch called before begin_batch',
			self::API_EC_BATCH_METHOD_NOT_ALLOWED_IN_BATCH_MODE => 'This method is not allowed in batch mode'
	);
}
