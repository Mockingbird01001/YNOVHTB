<?php

/**
 * @Author: Mockingbird
 * @Date:   2021-10-20 15:03:28
 * @Last Modified by:   yacine.B
 * @Last Modified time: 2021-11-10 16:49:35
 */

class Auth{

	private $options = [
		'restriction_msg' => "Vous n'avez pas le droit d'accéder à cette page",
	];
	private $session;

	public function __construct($session, $options = []){
		$this->options = array_merge($this->options, $options);
		$this->session = $session;
	}

////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////                                                                                    ////////////
////////////                 Les methode pour les divers autres modules                         ////////////
////////////                                                                                    ////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function hashPassword($password){
		return password_hash($password, PASSWORD_BCRYPT);
	}

	public function get_client_ip_env() {
		$ipaddress = '';
		if (getenv('HTTP_CLIENT_IP'))
			$ipaddress = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('HTTP_X_FORWARDED'))
			$ipaddress = getenv('HTTP_X_FORWARDED');
		else if(getenv('HTTP_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		else if(getenv('HTTP_FORWARDED'))
			$ipaddress = getenv('HTTP_FORWARDED');
		else if(getenv('REMOTE_ADDR'))
			$ipaddress = getenv('REMOTE_ADDR');
		else
			$ipaddress = 'UNKNOWN';
	 
		return $ipaddress;
	}

	// ###############################################################
	// 
	//                        Generique Methodes
	// 
	// ###############################################################

	public function isIndexHere($db, $table, $attributes, $values){
		$res = $db->query("SELECT id FROM $table WHERE $attributes", $values)->fetch();
		return ($res)? true : false ;  
	}

	public function getAllIndex($db, $table, $attributes='', $values, $getValue='*', $limit=''){
		return $db->query("SELECT $getValue FROM $table ".((!empty($attributes))? "WHERE $attributes" : '')." $limit", $values)->fetchAll();
	}

	public function getThis($db, $table, $attributes, $values, $getValue="*"){
		return $db->query("SELECT $getValue FROM $table WHERE $attributes", $values)->fetch();
	}

	public function deleteIndex($db, $table, $attributes, $values){
		$db->query("DELETE FROM $table WHERE $attributes", $values);
	}

	public function addIndex($db, $table, $attributes, $values){
		return $db->query("INSERT INTO $table SET $attributes", $values);
	}

	public function editIndex($db, $table, $attributes, $attributes2, $values){
		return $db->query("UPDATE $table SET $attributes WHERE $attributes2", $values);
	}

	public function getCount($db, $table, $field, $value){
		return $db->query("SELECT count(id) as nb FROM $table WHERE $field", $value)->fetch();
	}

	// ###############################################################
	// 
	//                    Fin Generique Methodes
	// 
	// ###############################################################
	
	public function register($db, $pseudo, $email, $password){
		$password = $this->hashPassword($password);
		$tokenConfirmation = Str::random(250);
		$db->query("INSERT INTO users SET pseudo=?, email=?, password=?, token=?, level=?, confirmation_token = ?", 
			[$pseudo, $email, $password, Str::random(50), 'member', $tokenConfirmation]
		);

	}

	public function confirm($db, $user_id, $token){
		$user = $db->query('SELECT * FROM users WHERE id = ?', [$user_id])->fetch();
		if($user && $user->confirmation_token == $token ){
			$db->query('UPDATE users SET confirmation_token = NULL, confirmation_at = NOW() WHERE id = ?', [$user_id]);
			$this->session->write('auth', $user);
			return true;
		}
		return false;
	}

	public function restrict(){
		if(!$this->session->read('auth')){
			$this->session->setFlash('danger', $this->options['restriction_msg']);
			header('Location: login.php');
			exit();
		}
	}

	public function user(){
		if(!$this->session->read('auth')){
			return false;
		}
		return $this->session->read('auth');
	}

	public function connect($user){
		$this->session->write('auth', $user);
	}

	public function connectFromCookie($db){
		if(isset($_COOKIE['remember']) && !$this->user()){
			$remember_token = $_COOKIE['remember'];
			$parts = explode('==', $remember_token);
			$user_id = $parts[0];
			$user = $db->query('SELECT * FROM users WHERE id = ?', [$user_id])->fetch();
			if($user){
				$expected = $user_id . '==' . $user->remember_token . sha1($user_id . 'YnovHtb2021');
				if($expected == $remember_token){
					$this->connect($user);
					setcookie('remember', $remember_token, time() + 60 * 60 * 24 * 7);
				} else{
					setcookie('remember', null, -1);
				}
			}else{
				setcookie('remember', null, -1);
			}
		}
	}

	public function login($db, $pseudoOrMail, $password, $remember = false){
		$user = $db->query('SELECT * FROM users WHERE (pseudo=? or email=?) AND confirmation_token IS NULL', [$pseudoOrMail, $pseudoOrMail])->fetch();
		if($user){
			if(password_verify($password, $user->password)){
				$this->connect($user);
				if($remember) $this->remember($db, $user->id) ;
				return $user;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	public function remember($db, $user_id){
		$remember_token = Str::random(250);
		$db->query('UPDATE users SET remember_token = ? WHERE id = ?', [$remember_token, $user_id]);
		setcookie('remember', $user_id . '==' . $remember_token . sha1($user_id . 'YnovHtb2021'), 0);

	}

	public function logout(){
		setcookie('remember', NULL, -1);
		$this->session->delete('auth');
	}

	public function changePassword($db, $email, $password){
		$res = $db->query("UPDATE users SET password=? WHERE email=?", [$this->hashPassword($password), $email]);
		return ($res)? true : false;
	}

	public function resetPassword($db, $email){
		$reset_token = Str::random(60);
		$user = $db->query('SELECT * FROM users WHERE email = ? AND confirmation_at IS NOT NULL', [$email])->fetch();
		if($user){
			$db->query('UPDATE users SET reset_token = ?, reset_at = NOW() WHERE id = ?', [$reset_token, $user->id]);
			return $user;
		}
		return false;
	}

	public function checkResetToken($db, $user_id, $token){
		return $db->query('SELECT * FROM users WHERE id = ? AND reset_token IS NOT NULL AND reset_token = ? AND reset_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)', [$user_id, $token])->fetch();
	}
}
