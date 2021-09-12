<?php


namespace IPS\cbpanel\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * @brief	product Config Requests API
 */
class _productconfig extends \IPS\Content\Api\ItemController
{
    /**
     * GET /cbpanel/productconfig
     * Get basic information about the authorized user
     *
     * @apimemberonly
     * @return	object
     */
    public function GETindex() {
        // Check that pid and uid have been set
        if ( !isset( \IPS\Request::i()->product_id ) || !isset( \IPS\Request::i()->user_id )) {
            return new \IPS\Api\Response( 200, (object)['error_code'=>"INVALID_PARAMETERS",'message'=>"Required parameters have not been provided."] );
        }
        // Grab the user's data
        $config = json_decode( \IPS\Db::i()->select('cbpanel_data', 'core_members', ["`member_id`=" . \IPS\Request::i()->user_id ])->first() );
        // Check if config exists
        if (@gettype($config) == "object") {
            $n = "cb_config_" . \IPS\Request::i()->product_id;
            if ( isset($config->$n) ) {
                return new \IPS\Api\Response( 200, $config->$n );
            } else {
                return new \IPS\Api\Response( 200, (object)['error_code'=>"NOT_FOUND",'message'=>"The requested config does not exist!"] );
            }
        } else {
            return new \IPS\Api\Response( 200, (object)['error_code'=>"NOT_FOUND",'message'=>"The requested config does not exist!"] );
        }


    }

}