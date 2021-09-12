<?php


namespace IPS\cbpanel\modules\front\customer;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * panel
 */
class _panel extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
		// This is the default method if no 'do' parameter is specified
        if( \IPS\Member::loggedIn()->member_id !== NULL ) {
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('usercp', 'cbpanel')->panel( \IPS\Member::loggedIn(), $this->hwidInfo() );
        }
	}
	

	/**
	 * Gathers HWID data for the customer
	 * 
	 * @return object
	 */
	protected function hwidInfo()
    {
		return (object) [
		    'days'         => $this->duration(),
			'resetMessage' => $this->checkHwid(),
			'lastReset'    => $this->lastHwidReset(),
			'isReset'      => ( ( \IPS\Request::i()->hwid == 1) && ( \IPS\Request::i()->wasConfirmed == 1) ) ? true : false
		];
	}


	protected function duration()
    {
	    return @json_decode(\IPS\Db::i()->select('data', 'cbpanel_settings')->first())->hardwareDuration ?: 0;
    }

	/**
	 * Check if user is requesting a HWID reset
	 *
	 * @return string
	 */
	protected function checkHwid()
    {
        if ( ( \IPS\Request::i()->hwid == 1) && ( \IPS\Request::i()->wasConfirmed == 1 ) ) {
			$member = \IPS\Member::loggedIn();
			$cbData = json_decode(\IPS\Db::i()->select('`cbpanel_data`', 'core_members', '`member_id`=' . $member->member_id)->first());
			$cbSettings = json_decode(\IPS\Db::i()->select('data', 'cbpanel_settings')->first());
			if (isset($cbSettings->hardwareDuration)) {
			    $delayDays = $cbSettings->hardwareDuration;
            } else {
			    $delayDays = 0;
            }
			$delaySeconds = $delayDays * 24 * 60 * 60;
			if ($cbData == null) {
				$cbData = (object)[];
			}
			if ( isset($cbData->userHwidDate) ) {
				$lastReset = $cbData->userHwidDate;
			} else {
				$lastReset = 0;
			}
			$time = time();
			if ( ($lastReset + $delaySeconds) <= $time ) {
				$cbData->userHwidDate = $time;
				\IPS\Db::i()->update('core_members', "`client_hwid`=0,`cbpanel_data`='" . json_encode($cbData) . "'", '`member_id`=' . $member->member_id);
				return "Your HWID has been successfully reset.";
			} else {
				$rem = date('m/d/y', $lastReset + $delaySeconds) . " at " . date('h:i:s a', $lastReset + $delaySeconds) . ".";
				return "Error: Your HWID can not be reset until " . $rem;
			}
		}
	}

	/**
	 * Check user's last reset date
	 * 
	 * @return string
	 */
	protected function lastHwidReset() {
		$cbData = json_decode(\IPS\Db::i()->select('`cbpanel_data`', 'core_members', '`member_id`=' . \IPS\Member::loggedIn()->member_id)->first());
		if ( @gettype($cbData) == 'object' ) {
		    return @$cbData->userHwidDate ? date('m/d/Y H:i:s', $cbData->userHwidDate) : "Never";
		}
		return "Never";
	}
}