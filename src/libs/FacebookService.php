<?php

namespace app\facebook\libs;

use infuse\Utility as U;
use Pimple\Container;
use app\facebook\models\FacebookProfile;

class FacebookService
{
    private $app;
    private $profile;

    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->profile = null;
    }

    /**
     * Sets the appropriate API access token using
     * a given profile
     *
     * @param FacebookProfile $profile
     */
    public function setAccessTokenFromProfile(FacebookProfile $profile)
    {
        $this->app['facebook']->setAccessToken($profile->access_token);
        $this->profile = $profile;

        return $this;
    }

    /**
     * Performs an API call on the facebook API (if available) or
     * returns a mock response
     *
     * @param string $endpoint
     * @param string $method   HTTP method
     * @param array  $params   optional params
     *
     * @return object
     */
    public function api($endpoint, $method = null, $params = null)
    {
        $response = false;

        try {
            return $this->app[ 'facebook' ]->api($endpoint, $method, $params);
        } catch (\FacebookApiException $e) {
            // access token has expired
            $result = $e->getResult();
            $code = U::array_value($result, 'error.code');
            if ($code == 190) {
                // clear the access token of the user's profile
                if ($this->profile) {
                    $this->profile->grantAllPermissions();
                    $this->profile->set('access_token', '');
                    $this->profile->enforcePermissions();
                }
            } else {
                $this->app[ 'logger' ]->error($e);
            }

            return false;
        }
    }
}
