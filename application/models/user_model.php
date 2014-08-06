<?php
if ( ! defined('BASEPATH')) 
  exit('No direct script access allowed');

/**
 * User model.
 */
class User_model extends MY_Model
{

  /**
   * Table name.
   */
  const TABLE_NAME = 'user';

  // Roles
  const ROLE_DEFAULT = 0;
  const ROLE_ADMIN = 1;

  // Status
  const STATUS_DISABLE = 0;
  const STATUS_ACTIVE = 1;
  const STATUS_WAITING = 2;
  const STATUS_NO_ACCOUNT = 3;
  
  // Other
  const NO_NAME = "----";

  // Table fields
  public $id;
  public $email;
  public $name = NULL;
  public $password = NULL;
  public $alias = NULL;
  public $bio = NULL;
  public $city = NULL;
  public $roles = self::ROLE_DEFAULT;
  public $confirmation;
  public $dateadd = 0;
  public $dateupdate = 0;
  public $status = self::STATUS_ACTIVE;
  
  public function __construct($data = array()) {
	$this->TABLE_NAME = self::TABLE_NAME;
    parent::__construct($data);
  }
  
  /**
   * Return a string with user denomination
   * @return string
   */
  public function get_name() {
    $name = trim($this->name);
    empty($name) and $name = trim($this->email);
	empty($name) and $name = self::NO_NAME;
    return $name;
  }
  
  /**
   * Checks if user is within a specific role.
   *
   * @param int $role 
   * @return boolean
   */
  public function is($role)
  {
    return $role & $this->roles;
  }
  
  /**
   * Create a new identity or link it to user if exists
   */
  protected function match_identity() {
/*  	$this->load->model('User_identity_model');
	$res = $this->User_identity_model->match($value,$type,$user_id);
	if (!$res) {
		$this->load->helper('email');
		admin_report("Problem matching identity $value","Data given: value = $value, type = $type, user_id = $user_id");
	}*/
	return true;
  }

  /**
   * Inserts the current user on database.
   *
   * @return boolean
   */
  public function insert()
  {
  	if (	empty($this->email) || 
  			empty($this->password) || 
  			$this->get_by_email($this->email)) 
  		return false;
	
	$this->dateadd = gmdate("Y-m-d H:i:s");
	$this->dateupdate = gmdate("Y-m-d H:i:s");
	$this->set_alias(FALSE);
	$res = parent::insert();
	
	if ($res) $this->match_identity();
	
	return $res;
  }

  /**
   * Updates the current user to database.
   *
   * @return boolean
   */
  public function update()
  {
  	if ( empty($this->email) ) 
  		return false;

	$this->dateupdate = gmdate("Y-m-d H:i:s");
	$this->set_alias(FALSE);
	$res = parent::update();

	
	if ($res) $this->match_identity();
	
	return $res;
	
  }

  /**
   * Gets all users.
   *
   * @return array
   */
  public function get_all()
  {
    $query = $this->db->get(self::TABLE_NAME);
	return $this->get_self_results($query);
  }

  /**
   * Gets an user by its e-mail.
   *
   * @param string $email 
   * @return User_model|null
   */
  public function get_by_email($email)
  {
    $query = $this->db->get_where(self::TABLE_NAME, array('email' => $email));
    return $this->get_first_self_result($query);
  }

  /**
   * Gets an user by its alias.
   *
   * @param string $alias 
   * @return User_model|null
   */
  public function get_by_alias($alias)
  {
    $query = $this->db->get_where(self::TABLE_NAME, array('alias' => $alias));
    return $this->get_first_self_result($query);
  }

  /**
   * Encrypts the given password using database ENCODE function.
   *
   * @param string $password 
   * @return string
   */
  public function encrypt_password($password)
  {
    $sql = "SELECT ENCODE(?, ?) AS `password`";
    $query = $this->db->query($sql, array($password, ENCODE_CODE_WORD));
    return $query->num_rows() > 0 ? $query->row()->password : null;
  }
  
  /**
   * reset user password
   *
   * @return string
   */
  public function reset_password() {
  	if (!isset($this->id)) return false;
	
  	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $password = substr(str_shuffle($chars),0,8);
  
  	log_message('info',"Password changed: $password");
  
  	$this->password = $this->encrypt_password($password);
	$this->update();
  	
  	return $password; 
  }

  /**
   * Set user confirmation code
   * 
   * @return string
   */
  public function set_confirmation() {
  	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $confirmation = substr(str_shuffle($chars),0,24);
	
	$this->confirmation = $confirmation;
	$this->status = self::STATUS_WAITING;

	return $confirmation;
  }
  
  /**
   * Check if confirmation is OK
   * 
   * @return user
   */
  public function check_confirmation($confirmation) {
  	if (empty($confirmation)) return false;
    $query = $this->db->get_where(self::TABLE_NAME, array('confirmation' => $confirmation));
    if ($query->num_rows() > 0) {
    	$user = $this->get_first_self_result($query);
    	$user->confirmation = NULL;
		$user->status = self::STATUS_ACTIVE;
		$user->update();
		return $user;
    } else {
        return false;
    }
  }

  /**
   * Format object name into url alias
   * THIS FUNCTION SAVES THE NEW ALIAS IF ALIAS WAS EMPTY
   * 
   * @return string
   */
	protected function set_alias($save = TRUE) {
		$alias = $this->alias;
		if (empty($alias)) {
			if (!empty($this->email)) $name = array_shift(explode('@',$this->email));
			else if (!empty($name)) $name = $this->name;
			else $name = 'newuser';

			$table = array(
		        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
		        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
		        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
		        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
		        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
		        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
		        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
		        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
		    );
			$alias = preg_replace("/[^a-zA-Z0-9]+/", "_", strtr($name, $table));
			$query = $this->db->get_where(self::TABLE_NAME,array('alias' => $alias));
			if ($query->num_rows() > 0) {
				$city = $this->city;
				$alias = preg_replace("/[^a-zA-Z0-9]+/", "_", strtr($name.' '.$city, $table));
				$base = $alias;
				$query = $this->db->get_where(self::TABLE_NAME,array('alias' => $alias));
				while ($query->num_rows() > 0) {
					$alias = $base.$i++;
					$query = $this->db->get_where(self::TABLE_NAME,array('alias' => $alias));
				}
			}
			$this->alias = $alias;
			if ($save) $this->update();
		}
		return $alias;	
	}

}

/* End of file user_model.php */
/* Location: ./application/models/user_model.php */