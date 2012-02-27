<?php
include_once 'Rest.php';

$appapikey = 'fb664fde7df7b3627ac2ba32e271e4e8';
$appsecret = 'd1775156f4401269a0f546e44e18284c';
$facebook = new Facebook($appapikey, $appsecret);
$api = $facebook->api_client;

function currentUser(){
	static $user;
	if(!$user){
		global $facebook;
		$user = new User($facebook->require_login());
	}
	return $user;
}

function facebook(){
	global $facebook;
	return $facebook;
}

define('FACEBOOK_API_VALIDATION_ERROR', 1);
class Facebook {
  public $api_client;
  public $api_key;
  public $secret;
  public $generate_session_secret;
  public $session_expires;

  public $fb_params;
  public $user;
  public $profile_user;
  public $canvas_user;
  protected $base_domain;

  public function __construct($api_key, $secret, $generate_session_secret=false) {
    $this->api_key                 = $api_key;
    $this->secret                  = $secret;
    $this->generate_session_secret = $generate_session_secret;
    $this->api_client = new FacebookRestClient($api_key, $secret, null);
    $this->validate_fb_params();

    $defaultUser = null;
    if ($this->user) {
      $defaultUser = $this->user;
    } else if ($this->profile_user) {
      $defaultUser = $this->profile_user;
    } else if ($this->canvas_user) {
      $defaultUser = $this->canvas_user;
    }

    $this->api_client->set_user($defaultUser);


    if (isset($this->fb_params['friends'])) {
      $this->api_client->friends_list =
        array_filter(explode(',', $this->fb_params['friends']));
    }
    if (isset($this->fb_params['added'])) {
      $this->api_client->added = $this->fb_params['added'];
    }
    if (isset($this->fb_params['canvas_user'])) {
      $this->api_client->canvas_user = $this->fb_params['canvas_user'];
    }
  }

  public function validate_fb_params($resolve_auth_token=true) {
    $this->fb_params = $this->get_valid_fb_params($_POST, 48 * 3600, 'fb_sig');

    if (!$this->fb_params) {
      $fb_params = $this->get_valid_fb_params($_GET, 48 * 3600, 'fb_sig');
      $fb_post_params = $this->get_valid_fb_params($_POST, 48 * 3600, 'fb_post_sig');
      $this->fb_params = array_merge($fb_params, $fb_post_params);
    }

    if ($this->fb_params) {
      $user               = isset($this->fb_params['user']) ?
                            $this->fb_params['user'] : null;
      $this->profile_user = isset($this->fb_params['profile_user']) ?
                            $this->fb_params['profile_user'] : null;
      $this->canvas_user  = isset($this->fb_params['canvas_user']) ?
                            $this->fb_params['canvas_user'] : null;
      $this->base_domain  = isset($this->fb_params['base_domain']) ?
                            $this->fb_params['base_domain'] : null;

      if (isset($this->fb_params['session_key'])) {
        $session_key =  $this->fb_params['session_key'];
      } else if (isset($this->fb_params['profile_session_key'])) {
        $session_key =  $this->fb_params['profile_session_key'];
      } else {
        $session_key = null;
      }
      $expires     = isset($this->fb_params['expires']) ?
                     $this->fb_params['expires'] : null;
      $this->set_user($user,
                      $session_key,
                      $expires);
    }
    else if ($cookies =
             $this->get_valid_fb_params($_COOKIE, null, $this->api_key)) {

      $base_domain_cookie = 'base_domain_' . $this->api_key;
      if (isset($_COOKIE[$base_domain_cookie])) {
        $this->base_domain = $_COOKIE[$base_domain_cookie];
      }

      $expires = isset($cookies['expires']) ? $cookies['expires'] : null;
      $this->set_user($cookies['user'],
                      $cookies['session_key'],
                      $expires);
    }
    else if ($resolve_auth_token && isset($_GET['auth_token']) &&
             $session = $this->do_get_session($_GET['auth_token'])) {
      if ($this->generate_session_secret &&
          !empty($session['secret'])) {
        $session_secret = $session['secret'];
      }

      if (isset($session['base_domain'])) {
        $this->base_domain = $session['base_domain'];
      }

      $this->set_user($session['uid'],
                      $session['session_key'],
                      $session['expires'],
                      isset($session_secret) ? $session_secret : null);
    }

    return !empty($this->fb_params);
  }

  public function promote_session() {
    try {
      $session_secret = $this->api_client->auth_promoteSession();
      if (!$this->in_fb_canvas()) {
        $this->set_cookies($this->user, $this->api_client->session_key, $this->session_expires, $session_secret);
      }
      return $session_secret;
    } catch (FacebookRestClientException $e) {
      if ($e->getCode() != FacebookAPIErrorCodes::API_EC_PARAM) {
        throw $e;
      }
    }
  }

  public function do_get_session($auth_token) {
    try {
      return $this->api_client->auth_getSession($auth_token, $this->generate_session_secret);
    } catch (FacebookRestClientException $e) {
      if ($e->getCode() != FacebookAPIErrorCodes::API_EC_PARAM) {
        throw $e;
      }
    }
  }

  public function expire_session() {
    try {
      if ($this->api_client->auth_expireSession()) {
        $this->clear_cookie_state();
        return true;
      } else {
        return false;
      }
    } catch (Exception $e) {
      $this->clear_cookie_state();
    }
  }

   public function logout($next) {
    $logout_url = $this->get_logout_url($next);

    $this->clear_cookie_state();

    $this->redirect($logout_url);
  }

  public function clear_cookie_state() {
    if (!$this->in_fb_canvas() && isset($_COOKIE[$this->api_key . '_user'])) {
       $cookies = array('user', 'session_key', 'expires', 'ss');
       foreach ($cookies as $name) {
         setcookie($this->api_key . '_' . $name,
                   false,
                   time() - 3600,
                   '',
                   $this->base_domain);
         unset($_COOKIE[$this->api_key . '_' . $name]);
       }
       setcookie($this->api_key, false, time() - 3600, '', $this->base_domain);
       unset($_COOKIE[$this->api_key]);
     }

     $this->user = 0;
     $this->api_client->session_key = 0;
  }

  public function redirect($url) {
    if ($this->in_fb_canvas()) {
      echo '<fb:redirect url="' . $url . '"/>';
    } else if (preg_match('/^https?:\/\/([^\/]*\.)?facebook\.com(:\d+)?/i', $url)) {
      echo "<script type=\"text/javascript\">\ntop.location.href = \"$url\";\n</script>";
    } else {
      header('Location: ' . $url);
    }
    exit;
  }

  public function in_frame() {
    return isset($this->fb_params['in_canvas'])
        || isset($this->fb_params['in_iframe']);
  }
  public function in_fb_canvas() {
    return isset($this->fb_params['in_canvas']);
  }

  public function get_loggedin_user() {
    return $this->user;
  }

  public function get_canvas_user() {
    return $this->canvas_user;
  }

  public function get_profile_user() {
    return $this->profile_user;
  }

  public static function current_url() {
    return 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  }

  public function require_login() {
    if ($user = $this->get_loggedin_user()) {
      return $user;
    }
    $this->redirect($this->get_login_url(self::current_url(), $this->in_frame()));
  }

  public function require_frame() {
    if (!$this->in_frame()) {
      $this->redirect($this->get_login_url(self::current_url(), true));
    }
  }

  public static function get_facebook_url($subdomain='www') {
    return 'http://' . $subdomain . '.facebook.com';
  }

  public function get_install_url($next=null) {
    return $this->get_add_url($next);
  }

  public function get_add_url($next=null) {
    $page = self::get_facebook_url().'/add.php';
    $params = array('api_key' => $this->api_key);

    if ($next) {
      $params['next'] = $next;
    }

    return $page . '?' . http_build_query($params);
  }

  public function get_login_url($next, $canvas) {
    $page = self::get_facebook_url().'/login.php';
    $params = array('api_key' => $this->api_key,
                    'v'       => '1.0');

    if ($next) {
      $params['next'] = $next;
    }
    if ($canvas) {
      $params['canvas'] = '1';
    }

    return $page . '?' . http_build_query($params);
  }

  public function get_logout_url($next) {
    $page = self::get_facebook_url().'/logout.php';
    $params = array('app_key'     => $this->api_key,
                    'session_key' => $this->api_client->session_key);

    if ($next) {
      $params['connect_next'] = 1;
      $params['next'] = $next;
    }

    return $page . '?' . http_build_query($params);
  }

  public function set_user($user, $session_key, $expires=null, $session_secret=null) {
    if (!$this->in_fb_canvas() && (!isset($_COOKIE[$this->api_key . '_user'])
                                   || $_COOKIE[$this->api_key . '_user'] != $user)) {
      $this->set_cookies($user, $session_key, $expires, $session_secret);
    }
    $this->user = $user;
    $this->api_client->session_key = $session_key;
    $this->session_expires = $expires;
  }

  public function set_cookies($user, $session_key, $expires=null, $session_secret=null) {
    $cookies = array();
    $cookies['user'] = $user;
    $cookies['session_key'] = $session_key;
    if ($expires != null) {
      $cookies['expires'] = $expires;
    }
    if ($session_secret != null) {
      $cookies['ss'] = $session_secret;
    }

    foreach ($cookies as $name => $val) {
      setcookie($this->api_key . '_' . $name, $val, (int)$expires, '', $this->base_domain);
      $_COOKIE[$this->api_key . '_' . $name] = $val;
    }
    $sig = self::generate_sig($cookies, $this->secret);
    setcookie($this->api_key, $sig, (int)$expires, '', $this->base_domain);
    $_COOKIE[$this->api_key] = $sig;

    if ($this->base_domain != null) {
      $base_domain_cookie = 'base_domain_' . $this->api_key;
      setcookie($base_domain_cookie, $this->base_domain, (int)$expires, '', $this->base_domain);
      $_COOKIE[$base_domain_cookie] = $this->base_domain;
    }
  }

  public static function no_magic_quotes($val) {
    if (get_magic_quotes_gpc()) {
      return stripslashes($val);
    } else {
      return $val;
    }
  }

  public function get_valid_fb_params($params, $timeout=null, $namespace='fb_sig') {
    $prefix = $namespace . '_';
    $prefix_len = strlen($prefix);
    $fb_params = array();
    if (empty($params)) {
      return array();
    }

    foreach ($params as $name => $val) {

      if (strpos($name, $prefix) === 0) {
        $fb_params[substr($name, $prefix_len)] = self::no_magic_quotes($val);
      }
    }

    if ($timeout && (!isset($fb_params['time']) || time() - $fb_params['time'] > $timeout)) {
      return array();
    }

    $signature = isset($params[$namespace]) ? $params[$namespace] : null;
    if (!$signature || (!$this->verify_signature($fb_params, $signature))) {
      return array();
    }
    return $fb_params;
  }

  public function verify_account_reclamation($user, $hash) {
    return $hash == md5($user . $this->secret);
  }

  public function verify_signature($fb_params, $expected_sig) {
    return self::generate_sig($fb_params, $this->secret) == $expected_sig;
  }

  public function verify_signed_public_session_data($signed_data,
                                                    $public_key = null) {

    if (!$public_key) {
      $public_key = $this->api_client->auth_getAppPublicKey(
        $signed_data['api_key']);
    }

    $data_to_serialize = $signed_data;
    unset($data_to_serialize['sig']);
    $serialized_data = implode('_', $data_to_serialize);

    $signature = base64_decode($signed_data['sig']);
    $result = openssl_verify($serialized_data, $signature, $public_key,
                             OPENSSL_ALGO_SHA1);
    return $result == 1;
  }

  public static function generate_sig($params_array, $secret) {
    $str = '';

    ksort($params_array);
    foreach ($params_array as $k=>$v) {
      $str .= "$k=$v";
    }
    $str .= $secret;

    return md5($str);
  }

  public function encode_validationError($summary, $message) {
    return json_encode(
               array('errorCode'    => FACEBOOK_API_VALIDATION_ERROR,
                     'errorTitle'   => $summary,
                     'errorMessage' => $message));
  }

  public function encode_multiFeedStory($feed, $next) {
    return json_encode(
               array('method'   => 'multiFeedStory',
                     'content'  =>
                     array('next' => $next,
                           'feed' => $feed)));
  }

  public function encode_feedStory($feed, $next) {
    return json_encode(
               array('method'   => 'feedStory',
                     'content'  =>
                     array('next' => $next,
                           'feed' => $feed)));
  }

  public function create_templatizedFeedStory($title_template, $title_data=array(),
                                    $body_template='', $body_data = array(), $body_general=null,
                                    $image_1=null, $image_1_link=null,
                                    $image_2=null, $image_2_link=null,
                                    $image_3=null, $image_3_link=null,
                                    $image_4=null, $image_4_link=null) {
    return array('title_template'=> $title_template,
                 'title_data'   => $title_data,
                 'body_template'=> $body_template,
                 'body_data'    => $body_data,
                 'body_general' => $body_general,
                 'image_1'      => $image_1,
                 'image_1_link' => $image_1_link,
                 'image_2'      => $image_2,
                 'image_2_link' => $image_2_link,
                 'image_3'      => $image_3,
                 'image_3_link' => $image_3_link,
                 'image_4'      => $image_4,
                 'image_4_link' => $image_4_link);
  }


}

