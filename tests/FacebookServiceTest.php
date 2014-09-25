<?php

use Pimple\Container;

use app\facebook\libs\FacebookService;
use app\facebook\models\FacebookProfile;

class FacebookServiceTest extends \PHPUnit_Framework_TestCase
{
    public function testSetAccessTokenFromProfile()
    {
        $app = new Container();
        $facebook = Mockery::mock( 'Api' );
        $facebook->shouldReceive( 'setAccessToken' )->withArgs( [ 'token' ] )->once();
        $app[ 'facebook' ] = $facebook;

        $service = new FacebookService( $app );

        $profile = new FacebookProfile();
        $profile->access_token = 'token';

        $this->assertEquals($service, $service->setAccessTokenFromProfile($profile));
    }

    public function testApi()
    {
        $app = new Container();
        $facebook = Mockery::mock( 'Api' );
        $facebook->shouldReceive( 'api' )->withArgs( [ '/test', 'delete', [ 'test' => true ] ] )
            ->andReturn( [ 'worked' => true ] )->once();
        $app[ 'facebook' ] = $facebook;

        $service = new FacebookService( $app );

        $this->assertEquals( [ 'worked' => true ], $service->api( '/test', 'delete', [ 'test' => true ] ) );
    }

    public function testApiException()
    {
        $app = new Container();
        $facebook = Mockery::mock( 'Api' );
        $e = new FacebookApiException([]);
        $facebook->shouldReceive( 'api' )->withArgs( [ '/test', 'get', null ] )
            ->andThrow( $e )->once();
        $app[ 'facebook' ] = $facebook;
        $logger = Mockery::mock( 'Logger' );
        $logger->shouldReceive( 'error' )->withArgs( [ $e ] )->once();
        $app[ 'logger' ] = $logger;

        $service = new FacebookService( $app );

        $this->assertFalse( $service->api( '/test', 'get' ) );
    }

    public function testApiExpiredAccessToken()
    {
        $this->markTestIncomplete();
    }
}
