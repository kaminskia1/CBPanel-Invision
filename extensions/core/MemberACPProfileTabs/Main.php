<?php


namespace IPS\cbpanel\extensions\core\MemberACPProfileTabs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile Tab
 */
class _Main extends \IPS\core\MemberACPProfile\MainTab
{

	/**
	 * Can view this Tab
	 *
	 * @return	bool
	 */
	public function canView() 
	{
		return (bool) \IPS\Member::loggedIn()->hasAcpRestriction( 'cbpanel', 'loader', 'view' );
	}

	/**
	 * Get left-column blocks
	 *
	 * @return	array
	 */
	public function leftColumnBlocks()
	{
		return array(
            'IPS\cbpanel\extensions\core\MemberACPProfileBlocks\UserInfo',
		);
	}
	
	/**
	 * Get main-column blocks
	 *
	 * @return	array
	 */
	public function mainColumnBlocks()
	{
		return array(
			'IPS\cbpanel\extensions\core\MemberACPProfileBlocks\hwid',
			'IPS\cbpanel\extensions\core\MemberACPProfileBlocks\userLog',
		);
	}
	
}