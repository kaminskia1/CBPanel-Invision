//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cbpanel_hook_StripeCheckoutGateway extends _HOOK_CLASS_
{

	/**
	 * Gateways
	 * 
	 * @return array
	 */
	static public function gateways()
	{
		try
		{
	        try
	        {
	            $array = parent::gateways();
	            $array['StripeCheckout'] = 'IPS\cbpanel\StripeCheckout';
	
	            return $array;
	        }
	        catch (\RuntimeException $e)
	        {
	            if (method_exists(get_parent_class(), __FUNCTION__))
	            {
	                return \call_user_func_array('parent::' . __FUNCTION__, \func_get_args());
	            }
	            else
	            {
	                throw $e;
	            }
	        }
		}
		catch ( \RuntimeException $e )
		{
			if ( method_exists( get_parent_class(), __FUNCTION__ ) )
			{
				return \call_user_func_array( 'parent::' . __FUNCTION__, \func_get_args() );
			}
			else
			{
				throw $e;
			}
		}
	}

}
