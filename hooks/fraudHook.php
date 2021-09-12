//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cbpanel_hook_fraudHook extends _HOOK_CLASS_
{


	/** 
	 * Check if rule matches transaction
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	The transaction
	 * @return	bool
	 */
	public function matches( \IPS\nexus\Transaction $transaction )
	{
		try
		{
	    // check if slots full
			return parent::matches( $transaction );
		}
		catch ( \RuntimeException $e )
		{
			if ( method_exists( get_parent_class(), __FUNCTION__ ) )
			{
				return call_user_func_array( 'parent::' . __FUNCTION__, func_get_args() );
			}
			else
			{
				throw $e;
			}
		}
	}

}
