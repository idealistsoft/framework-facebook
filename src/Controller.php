<?php

namespace app\facebook;

use infuse\Utility as U;
use infuse\View;
use Facebook;
use app\users\models\User;
use app\facebook\models\FacebookProfile;
use app\facebook\libs\FacebookService;

class Controller
{
    use \InjectApp;

    public static $properties = [
        'models' => ['FacebookProfile'],
    ];

    public static $scaffoldAdmin;

    private $facebook;

    public function middleware($req, $res)
    {
        // add routes
        $this->app->get('/facebook/connect', ['facebook\\Controller', 'connect'])
                  ->get('/facebook/callback', ['facebook\\Controller', 'callback'])
                  ->post('/facebook/disconnect', ['facebook\\Controller', 'disconnect']);

        $this->app[ 'facebook' ] = function ($c) {
            return new Facebook($c[ 'config' ]->get('facebook'));
        };

        $this->app[ 'facebook_service' ] = function ($c) {
            return new FacebookService($c);
        };
    }

    public function connect($req, $res)
    {
        $facebook = $this->app[ 'facebook' ];

        // check if we should destroy old sessions first
        if ($req->query('logout')) {
            $facebook->destroySession();
        }

        $fbid = $facebook->getUser();

        if ($fbid) {
            try {
                // long-lived sessions
                $facebook->setExtendedAccessToken();

                $user_profile = $facebook->api('me');

                return $this->loginOrRegister($fbid, $user_profile, $req, $res);
            } catch (\FacebookApiException $e) {
                $this->app[ 'logger' ]->error($e);
                $fbid = null;
            }
        }

        $callbackUrl = $this->app[ 'config' ]->get('facebook.callbackUrl');
        if ($req->query('forceLogin')) {
            $callbackUrl .= '?forceLogin=t';
        }

        $res->redirect($facebook->getLoginUrl([
            'scope' => 'read_stream,user_birthday,publish_actions',
            'redirect_uri' => $callbackUrl, ]));
    }

    public function disconnect($req, $res)
    {
        $currentUser = $this->app[ 'user' ];

        if ($currentUser->isLoggedIn() || $currentUser->facebookConnected()) {
            $currentUser->set('facebook_id', null);
        }

        $redir = '/profile';
        if ($req->query('r')) {
            $redir = $req->query('r');
        }

        $res->redirect($redir);
    }

    public function callback($req, $res)
    {
        $facebook = $this->app[ 'facebook' ];

        $fbid = $facebook->getUser();

        if ($fbid) {
            try {
                $user_profile = $facebook->api('me');

                return $this->loginOrRegister($fbid, $user_profile, $req, $res);
            } catch (\FacebookApiException $e) {
                $this->app[ 'logger' ]->error($e);
                $fbid = null;
            }
        }

        $res->redirect('/');
    }

    private function loginOrRegister($fbid, $user_profile, $req, $res)
    {
        $currentUser = $this->app[ 'user' ];

        $facebook = $this->app[ 'facebook' ];

        // get friend count
        $friendCount = 0;
        try {
            $friends = $facebook->api('me/friends');

            $friendCount = count((array) U::array_value($friends, 'data'));
        } catch (\FacebookApiException $e) {
            $this->app[ 'logger' ]->error($e);
        }

        // generate parameters to update profile
        $profileUpdateArray = [
            'id' => $fbid,
            'access_token' => $facebook->getAccessToken(),
            'friends_count' => $friendCount, ];

        // fbid matches existing user?
        $user = User::findOne([
            'where' => [
                'facebook_id' => $fbid, ], ]);

        if ($user) {
            // check if we are dealing with a temporary user
            if (!$user->isTemporary()) {
                if ($user->id() != $currentUser->id()) {
                    if ($req->query('forceLogin') || !$currentUser->isLoggedIn()) {
                        // log the user in
                        $this->app[ 'auth' ]->signInUser($user->id(), 'facebook');
                    } else {
                        $logoutNextUrl = $this->app[ 'base_url' ].'facebook/connect?logout=t';

                        // inform the user that the facebook account they are trying to connect
                        // belongs to someone else
                        return new View('switchingAccounts/facebook', [
                            'title' => 'Switch accounts?',
                            'otherUser' => $user,
                            'otherProfile' => $user->facebookProfile(),
                            'logoutUrl' => $facebook->getLogoutUrl(['next' => $logoutNextUrl]), ]);
                    }
                }

                $profile = new FacebookProfile($fbid);

                // create or update the profile
                if ($profile->exists()) {
                    $profile->set($profileUpdateArray);
                } else {
                    $profile = new FacebookProfile();
                    $profile->create($profileUpdateArray);
                }

                // refresh profile from API
                $profile->refreshProfile($user_profile);

                return $this->finalRedirect($req, $res);
            } else {
                // show finish signup screen
                $req->setSessoin('fbid', $fbid);

                return $res->redirect('/signup/finish');
            }
        }

        if ($currentUser->isLoggedIn()) {
            // add to current user's account
            $currentUser->set('facebook_id', $fbid);
        } else {
            // save this for later
            $req->setSession('fbid', $fbid);
        }

        $profile = new FacebookProfile($fbid);

        // create or update the profile
        if ($profile->exists()) {
            $profile->set($profileUpdateArray);
        } else {
            $profile = new FacebookProfile();
            $profile->create($profileUpdateArray);
        }

        // refresh profile from API
        $profile->refreshProfile($user_profile);

        // get outta here
        if ($currentUser->isLoggedIn()) {
            $this->finalRedirect($req, $res);
        } else {
            $res->redirect('/signup/finish');
        }
    }

    private function finalRedirect($req, $res)
    {
        if ($redirect = $req->cookies('redirect')) {
            $req->setCookie('redirect', '', time() - 86400, '/');
            $res->redirect($redirect);
        } elseif ($redirect = $req->query('redir')) {
            $res->redirect($redirect);
        } else {
            $res->redirect('/profile');
        }
    }

    public function refreshProfiles()
    {
        return FacebookProfile::refreshProfiles();
    }
}
