<?php

namespace app\facebook\models;

use infuse\Utility as U;
use app\social\models\SocialMediaProfile;

class FacebookProfile extends SocialMediaProfile
{
    public static $properties = [
        'id' => [
            'type' => 'number',
            'admin_hidden_property' => true,
        ],
        'username' => [
            'type' => 'string',
            'searchable' => true,
        ],
        'name' => [
            'type' => 'string',
            'searchable' => true,
        ],
        'access_token' => [
            'type' => 'string',
            'admin_hidden_property' => true,
        ],
        'profile_url' => [
            'type' => 'string',
            'null' => true,
            'admin_truncate' => false,
            'admin_html' => '<a href="{profile_url}" target="_blank">Profile</a>',
        ],
        'hometown' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'location' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'gender' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'birthday' => [
            'type' => 'date',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'age' => [
            'type' => 'number',
            'db_type' => 'int',
            'admin_hidden_property' => true,
        ],
        'bio' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        'friends_count' => [
            'type' => 'number',
            'null' => true,
            'admin_hidden_property' => true,
        ],
        // the last date the profile was refreshed from facebook
        'last_refreshed' => [
            'type' => 'date',
            'admin_hidden_property' => true,
        ],
    ];

    public function userPropertyForProfileId()
    {
        return 'facebook_id';
    }

    public function apiPropertyMapping()
    {
        return [
            'username' => 'username',
            'name' => 'name',
            'profile_url' => 'link',
            'hometown' => 'hometown.id',
            'location' => 'location.id',
            'gender' => 'gender',
            'bio' => 'bio',
            'friends_count' => 'friends_count',
            'age' => 'age',
            'birthday' => 'birthday' ];
    }

    public function daysUntilStale()
    {
        return 7;
    }

    public function numProfilesToRefresh()
    {
        return 180;
    }

    public function url()
    {
        return $this->profile_url;
    }

    public function profilePicture($size = 80)
    {
        return 'https://graph.facebook.com/'.$this->_id.'/picture?width='.$size.'&height='.$size;
    }

    public function isLoggedIn()
    {
        $facebook = $this->app[ 'facebook_service' ];
        $facebook->setAccessTokenFromProfile($this);

        $me = $facebook->api('me', 'get');

        return is_array($me) && $me[ 'id' ] == $this->_id;
    }

    public function getProfileFromApi()
    {
        $facebook = $this->app[ 'facebook_service' ];
        $facebook->setAccessTokenFromProfile($this);

        $profile = $facebook->api('me', 'get');

        if (!is_array($profile) || count($profile) == 0) {
            return false;
        }

        // parse birthday
        list($profile[ 'age' ], $profile[ 'birthday' ]) = $this->parseBirthday(U::array_value($profile, 'birthday'));

        // get # of friends profile has
        $friendCount = $this->getFriendsCount();
        if ($friendCount >= 0) {
            $profile[ 'friends_count' ] = $friendCount;
        }

        return $profile;
    }

    public function getFriendsCount()
    {
        $facebook = $this->app[ 'facebook_service' ];
        $facebook->setAccessTokenFromProfile($this);

        $friends = $facebook->api('me/friends', 'get');

        if (is_array($friends)) {
            return count((array) U::array_value($friends, 'data'));
        }

        return -1;
    }

    private function parseBirthday($birthdayStr)
    {
        if (empty($birthdayStr)) {
            return [ 0, 0 ];
        }

        // month/day/year
        $exp = explode('/', $birthdayStr);

        $birthday = false;
        if (count($exp) == 3) {
            $birthday = \DateTime::createFromFormat('U', gmmktime(0, 0, 0, $exp[ 0 ], $exp[ 1 ], $exp[ 2 ]));
        }
        if (!$birthday) {
            $birthday = new \DateTime();
        }

        $diff = $birthday->diff(new \DateTime());
        $age = $diff->y;
        $birthdayTs = (int) (floor($birthday->format('U') / 86400) * 86400);

        return [ $age, $birthdayTs ];
    }
}
