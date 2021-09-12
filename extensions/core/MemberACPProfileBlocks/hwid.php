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
class _hwid extends \IPS\core\MemberACPProfile\Block
{
    /**
     * Retreive User HWID
     *
     * @return string
     */
    public function getHWID() {
        $hwid = \IPS\Db::i()->select('`client_hwid`', 'core_members', '`member_id`=' . $this->member->member_id)->first();
        if ($hwid == 0 || null) {
            return "Not set";
        } else {
            return $hwid;
        }
    }

    /**
     * Retreive cbpanel data per user
     *
     * @return string
     */
    protected function getData($name) {
        $cbData = json_decode(\IPS\Db::i()->select('`cbpanel_data`', 'core_members', '`member_id`=' . $this->member->member_id)->first());
        if (isset($cbData->$name)) {
            return date("m/d/Y H:i:s", $cbData->$name);
        } else {
            return null;
        }
    }


    /**
     * Check if HWID should be reset
     *
     * @return void
     */
    protected function checkReset() {
        if ( isset($_GET['hwid']) == true && isset($_GET['wasConfirmed']) ) {
            if ( ((bool)$_GET['hwid'] == true) && ($_GET['wasConfirmed'] == 1) ) {
                $cbData = json_decode(\IPS\Db::i()->select('`cbpanel_data`', 'core_members', '`member_id`=' . $this->member->member_id)->first());
                if (!isset($cbData)) {
                    $cbData = (object)[];
                }
                $cbData->adminHwidDate = time();
                $cbData = json_encode($cbData);
                \IPS\Db::i()->update('core_members', "`cbpanel_data`='" . $cbData .  "'", '`member_id`=' . $this->member->member_id);
                \IPS\Db::i()->update('core_members', '`client_hwid`=0', '`member_id`=' . $this->member->member_id);
                \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('&app=core&module=members&controller=members&do=view&id=' . $this->member->member_id . '&tab=cbpanel_Main') );
            }
        }
    }

	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
        $this->checkReset();
		return \IPS\Theme::i()->getTemplate('loaderpanel', 'cbpanel')->hwid( $this->member, $this->getHWID(), $this->getData('adminHwidDate'), $this->getData('userHwidDate') );
    }

}