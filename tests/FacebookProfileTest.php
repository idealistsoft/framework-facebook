<?php

use infuse\Database;

use app\facebook\models\FacebookProfile;
use app\users\models\User;

class FacebookProfileTest extends \PHPUnit_Framework_TestCase
{
    public static $profile;
    public static $facebook;

    public static function setUpBeforeClass()
    {
        // delete old profiles
        Database::delete( 'FacebookProfiles', [ 'id >= 1', 'id <= 2' ] );

/*
		$response = [
			'username' => 'j',
			'name' => 'Some other Jared',
			'link' => 'http://facebook.com/jaredtking',
			'hometown' => [
				'id' => 'Bixby' ],
			'gender' => 'male',
			'location' => [
				'id' => 'Tulsa' ],
			'birthday' => '01/01/1990',
			'bio' => 'test'
		];
		self::$facebook->setMockResponse( 'get', 'me', null, $response );

		$response = [ 'data' => range( 1, 15 ) ];
		self::$facebook->setMockResponse( 'get', 'me/friends', null, $response );
*/
    }

    public static function tearDownAfterClass()
    {
        if (self::$profile) {
            self::$profile->grantAllPermissions();
            self::$profile->delete();
        }
    }
    
    public function testUserPropertyForProfileId()
    {
        $profile = new FacebookProfile();
        $this->assertEquals( 'facebook_id', $profile->userPropertyForProfileId() );
    }

    public function testApiPropertyMapping()
    {
        $profile = new FacebookProfile();
        $expected = [
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
        $this->assertEquals( $expected, $profile->apiPropertyMapping() );
    }

    public function testDaysUntilStale()
    {
        $profile = new FacebookProfile();
        $this->assertEquals( 7, $profile->daysUntilStale() );
    }

    public function testNumProfilesToRefresh()
    {
        $profile = new FacebookProfile();
        $this->assertEquals( 180, $profile->numProfilesToRefresh() );
    }

    public function testUrl()
    {
        $profile = new FacebookProfile();
        $profile->profile_url = 'http://facebook.com/jaredtking';
        $this->assertEquals( 'http://facebook.com/jaredtking', $profile->url() );
    }

    public function testProfilePicture()
    {
        $profile = new FacebookProfile( 1 );
        $this->assertEquals( 'https://graph.facebook.com/1/picture?width=100&height=100', $profile->profilePicture( 100 ) );
    }

    public function testIsLoggedIn()
    {
        $profile = new FacebookProfile( 100 );

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me', 'get' ] )
            ->andReturn( [ 'id' => 100 ] )->once();
        $app[ 'facebook_service' ] = $facebook;

        $this->assertTrue( $profile->isLoggedIn() );
    }

    public function testIsNotLoggedIn()
    {
        $profile = new FacebookProfile( 100 );

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me', 'get' ] )
            ->andReturn( false )->once();
        $app[ 'facebook_service' ] = $facebook;

        $this->assertFalse( $profile->isLoggedIn() );
    }

    public function testGetFriendsCount()
    {
        $profile = new FacebookProfile();

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me/friends', 'get' ] )
            ->andReturn( [ 'data' => range( 1, 15 ) ] )->once();
        $app[ 'facebook_service' ] = $facebook;

        $this->assertEquals( 15, $profile->getFriendsCount() );
    }

    public function testGetFriendsCountNone()
    {
        $profile = new FacebookProfile();

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ $profile ] )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me/friends', 'get' ] )
            ->andReturn( false )->once();
        $app[ 'facebook_service' ] = $facebook;

        $this->assertEquals( -1, $profile->getFriendsCount() );
    }

    public function testCreate()
    {
        self::$profile = new FacebookProfile();
        self::$profile->grantAllPermissions();
        $this->assertTrue( self::$profile->create( [
            'id' => 1,
            'name' => 'Jared',
            'username' => 'jaredtking',
            'profile_url' => false,
            'birthday' => '5/21/1980',
            'access_token' => 'test' ] ) );
        $this->assertGreaterThan( 0, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testEdit()
    {
        sleep( 1 );
        $oldTime = self::$profile->get( 'last_refreshed' );

        self::$profile->grantAllPermissions();
        self::$profile->set( [
            'name' => 'Test',
            'profile_url' => 'http://facebook.com/jaredtking' ] );

        $this->assertNotEquals( $oldTime, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testRefreshProfile()
    {
        $response = [
            'username' => 'j',
            'name' => 'Some other Jared',
            'link' => 'http://facebook.com/jaredtking',
            'hometown' => [
                'id' => 12345 ],
            'gender' => 'male',
            'location' => [
                'id' => 54321 ],
            'birthday' => '01/01/1990',
            'bio' => 'test'
        ];

        $response2 = [ 'data' => range( 1, 15 ) ];

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->withArgs( [ self::$profile ] )->twice();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me', 'get' ] )
            ->andReturn( $response )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me/friends', 'get' ] )
            ->andReturn( $response2 )->once();
        $app[ 'facebook_service' ] = $facebook;

        $this->assertTrue( self::$profile->refreshProfile() );

        $expected = [
            'id' => '1',
            'username' => 'j',
            'name' => 'Some other Jared',
            'access_token' => 'test',
            'profile_url' => 'http://facebook.com/jaredtking',
            'hometown' => 12345,
            'gender' => 'male',
            'location' => 54321,
            'birthday' => gmmktime( 0, 0, 0, 1, 1, 1990 ),
            'age' => date( 'Y' ) - 1990,
            'bio' => 'test',
            'friends_count' => 15 ];

        $profile = self::$profile->toArray( [ 'last_refreshed', 'created_at', 'updated_at' ] );

        $this->assertEquals( $expected, $profile );
    }

    /**
	 * @depends testRefreshProfile
	 */
    public function testRefreshProfiles()
    {
        $response = [
            'username' => 'j',
            'name' => 'Some other Jared',
            'link' => 'http://facebook.com/jaredtking',
            'hometown' => [
                'id' => 12345 ],
            'gender' => 'male',
            'location' => [
                'id' => 54321 ],
            'birthday' => '01/01/1990',
            'bio' => 'test'
        ];

        $response2 = [ 'data' => range( 1, 15 ) ];

        $app = TestBootstrap::app();
        $facebook = Mockery::mock( 'FacebookService' );
        $facebook->shouldReceive( 'setAccessTokenFromProfile' )->twice();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me', 'get' ] )
            ->andReturn( $response )->once();
        $facebook->shouldReceive( 'api' )->withArgs( [ 'me/friends', 'get' ] )
            ->andReturn( $response2 )->once();
        $app[ 'facebook_service' ] = $facebook;

        $t = strtotime( '-1 year' );
        self::$profile->grantAllPermissions();
        self::$profile->set( 'last_refreshed', $t );

        $this->assertTrue( FacebookProfile::refreshProfiles() );

        self::$profile->load();
        $this->assertGreaterThan( $t, self::$profile->last_refreshed );
    }

    /**
	 * @depends testCreate
	 */
    public function testDelete()
    {
        self::$profile->grantAllPermissions();
        $this->assertTrue( self::$profile->delete() );
    }
}
