<?php

namespace App\Model;

use App\Pixie;
use RelationTest\Model\Role;

/**
 * Class User.
 * @property WishList|WishList[] wishlists
 * @property WishList[] lists
 * @property Pixie $pixie
 * @property string $username
 * @property string $password
 * @property string $photo
 *
 * @property Role $roles
 * @property WishListFollowers $wishlistFollowers
 * @package App\Model
 */
class User extends BaseModel {

    public $table = 'tbl_users';
    public $defaultWishList = null;

    protected $has_many = array(
        'wishlists' => array(
            'model' => 'wishList',
            'key' => 'user_id'
        ),
        'wishlistFollowers' => array(
            'model' => 'WishListFollowers',
            'key' => 'user_id'
        ),
        'roles' => array(
            'model' => 'Role',
            'through' => 'tbl_users_roles',
            'key' => 'user_id',
            'foreign_key' => 'role_id'
        )
    );

    /**
     * @var array Cached roles
     */
    protected $_roles;

    public function checkExistingUser($dataUser) {
        if (strlen($dataUser['username']) && iterator_count($this->getUserByUsername($dataUser['username'])) > 0 ||
                strlen($dataUser['email']) && iterator_count($this->getUserByEmail($dataUser['email'])) > 0) {
            return true;
        } else {
            return false;
        }
    }

    protected function getUserByUsername($username) {
        return $this->pixie->db->query('select')
                        ->table($this->table)
                        ->where('username', $username)
                        ->execute();
    }

    protected function getUserByEmail($email) {
        return $this->pixie->db->query('select')
                        ->table($this->table)
                        ->where('email', $email)
                        ->execute();
    }

    public function RegisterUser($dataUser) {
        $dataUser['password'] = $this->pixie->auth->provider('password')->hash_password($dataUser['password']);
        $dataUser['created_on'] = $dataUser['last_login'] = date('Y-m-d H:i:s');
        $allowed = ['first_name', 'last_name', 'email', 'password', 'username', 'created_on', 'last_login'];
        $allowedData = [];
        foreach ($dataUser as $key => $field) {
            if (in_array($key, $allowed)) {
                $allowedData[$key] = $field;
            }
        }

        $this->pixie->orm->get('User')->values($allowedData)->save();
    }

    public function checkLoginUser($login) {
        if (preg_match("/[a-z0-9_-]+(\\.[a-z0-9_-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\\.)+([a-z]{2,4})/i", $login)) {
            /** @var User $user */
            $user = $this->pixie->orm->get('User')->where('email', $login)->find();
            if ($user->loaded())
                $login = $user->username;
        }
        return $login;
    }

    public function loadUserModel($login) {
        /** @var User $user */
        $user = $this->pixie->orm->get('User')->where('username', $login)->find();
        if ($user->loaded())
            return $user;
        return null;
    }

    public function saveOAuthUser($username, $oauth_uid, $oauth_provider) {
        $user = $this->pixie->orm->get('User');
        $user->username = $username;
        $user->oauth_provider = $oauth_provider;
        $user->oauth_uid = $oauth_uid;
        $user->created_on = date('Y-m-d H:i:s');
        return $user->save();
    }

    public function getEmailData($email) {
        /** @var User $user */
        $user = $this->pixie->orm->get('User')->where('email', $email)->find();

        if ($user->loaded()) {
            $host = $_SERVER['HTTP_HOST'] ? $host = 'http://'.$_SERVER['HTTP_HOST'] : '';
            $host = $host ?: $this->pixie->config->get('parameters.host');
            $host = $host ?: 'http://hackazon.com';

            return array(
                'to' => $email,
                'from' => 'RobotHackazon@hackazon.com',
                'subject' => 'recovering password',
                'text' => 'Hello, ' . $user->username . ".\nRecovering link is here "
                    . $host . '/user/recover?recover=' . $this->getTempPassword($user),
            );
        }
        return null;
    }

    /**
     * @param User $user
     * @return null|string
     */
    private function getTempPassword($user) {
        $arr = array(
            'a', 'b', 'c', 'd', 'e', 'f',
            'g', 'h', 'i', 'j', 'k', 'l',
            'm', 'n', 'o', 'p', 'q', 'r',
            's', 't', 'u', 'v', 'w', 'x',
            'y', 'z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J',
            'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V',
            'W', 'X', 'Y', 'Z', '1', '2',
            '3', '4', '5', '6', '7', '8',
            '9', '0',
        );
        $password = "";
        for ($i = 0; $i < 32; $i++)
            $password .= $arr[rand(0, count($arr) - 1)];
        $user->recover_passw = md5($password);
        $user->save();
        if ($user->loaded())
            return $password;

        return null;
    }

    public function checkRecoverPass($username, $recover_passw) {
        $user = $this->loadUserModel($username);
        if ($user && md5($recover_passw) === $user->recover_passw)
            return true;
        else
            return false;
    }

    public function getUserByRecoveryPass($recover_passw) {
        /** @var User $user */
        $user = $this->pixie->orm->get('User')->where('recover_passw', md5($recover_passw))->find();
        if ($user && $user->loaded())
            return $user;
        else
            return null;
    }

    public function changeUserPassword($username, $new_passw) {
        $user = $this->loadUserModel($username);
        if ($user) {
            $user->password = $this->pixie->auth->provider('password')->hash_password($new_passw);
            $user->recover_passw = null;
            $user->save();
            if ($user->loaded())
                return true;
        }
        return false;
    }

    public function get($propertyName) {
        if ($propertyName == 'lists') {
            return $this->wishlists->find_all()->as_array();
        }
        if ($propertyName == 'wishlistFollowers') {
            $followers = $this->pixie->db->query('select')->table($this->pixie->orm->get('User')->table, 'u')
                    ->join(array('tbl_wishlist_followers', 'wf'), array('wf.follower_id', 'u.id'))
                    ->fields('u.id')
                    ->where('wf.user_id', $this->id)
                    ->execute();
            $followerIds = array();
            /** @var User $followers */
            foreach ($followers->as_array() as $u) {
                $followerIds[] = $u->id;
            }
            if (empty($followerIds)) {
                return array();
            }
            $followerIds = implode(',', $followerIds);
            $followers = $this->pixie->orm->get('User')
                            ->where('id', 'IN', $this->pixie->db->expr('(' . $followerIds . ')'))->find_all()->as_array();
            return $followers;
        }
        return null;
    }

    public function getRoles($refresh = false)
    {
        if (!$this->loaded()) {
            throw new \LogicException('Can\'t get roles of invalid user object.');
        }

        if (!$refresh && $this->_roles !== null) {
            return $this->_roles;
        }

        $this->_roles = [];
        $roles = $this->roles->find_all()->as_array();
        /** @var \App\Model\Role $role */
        foreach ($roles as $role) {
            $this->_roles[] = $role->name;
        }

        return $this->_roles;
    }

    public function hasRole($role)
    {
        return in_array($role, $this->getRoles());
    }
}