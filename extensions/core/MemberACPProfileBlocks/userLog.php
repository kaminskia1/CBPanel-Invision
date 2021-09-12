<?php


namespace IPS\cbpanel\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile Block
 */
class _userLog extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get table HTML for said user
	 * 
	 * @return string
	 */
	protected function tableArray() {
		$data = \IPS\Db::i()->select('`timestamp`,`login_status`,`message`,`active_login_attempts`,`windows_username`,`hwid`', 'client_log', '`user_id`=' . $this->member->member_id, 'timestamp DESC', '25');
		$array = [];
		for ($i=0;$i<$data->count();$i++) {
			$data->next();
			array_push($array, $data->current());
		}
		return $array;

	}

	/**
	 * Get length of the SQL query
	 * 
	 * @return int
	 */
	protected function tableLength() {
		return \IPS\Db::i()->select('`timestamp`', 'client_log', '`user_id`=' . $this->member->member_id )->count();
	}
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		return \IPS\Theme::i()->getTemplate('loaderpanel', 'cbpanel')->userLog( $this->member, $this->tableArray(), $this->tableLength() );
	}
}