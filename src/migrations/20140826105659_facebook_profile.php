<?php

use Phinx\Migration\AbstractMigration;

class FacebookProfile extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
      if( !$this->hasTable( 'FacebookProfiles' ) )
      {
        $table = $this->table( 'FacebookProfiles', [ 'id' => false ] );
        $table->addColumn( 'id', 'biginteger', [ 'length' => 20 ] )
              ->addColumn( 'username', 'string' )
              ->addColumn( 'name', 'string' )
              ->addColumn( 'access_token', 'string' )
              ->addColumn( 'profile_url', 'string', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'hometown', 'biginteger', [ 'length' => 25, 'null' => true, 'default' => null ] )
              ->addColumn( 'location', 'biginteger', [ 'length' => 25, 'null' => true, 'default' => null ] )
              ->addColumn( 'gender', 'string', [ 'length' => 6, 'null' => true, 'default' => null ] )
              ->addColumn( 'birthday', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'age', 'integer', [ 'length' => 3 ] )
              ->addColumn( 'bio', 'string', [ 'length' => 1000, 'null' => true, 'default' => null ] )
              ->addColumn( 'friends_count', 'integer', [ 'null' => true, 'default' => null ] )
              ->addColumn( 'last_refreshed', 'integer' )
              ->addColumn( 'created_at', 'integer' )
              ->addColumn( 'updated_at', 'integer', [ 'null' => true, 'default' => null ] )
              ->create();
      }
    }
    
    /**
     * Migrate Up.
     */
    public function up()
    {
    
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}