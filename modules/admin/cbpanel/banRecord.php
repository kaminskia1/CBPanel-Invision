<?php


namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * banRecord
 */
class _banRecord extends \IPS\Dispatcher\Controller
{	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'banRecord_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* Create the table */
        $table = new \IPS\Helpers\Table\Db( 'cbpanel_ban_reports', \IPS\Http\Url::internal( 'app=cbpanel&module=cbpanel&controller=banRecord' ) );
        $table->include = array( 'cbpanel_member_id','cbpanel_ban_uplay','cbpanel_ban_start','cbpanel_ban_end','cbpanel_ban_service','cbpanel_ban_duration','cbpanel_ban_extra','cbpanel_ban_file' );
        $table->quickSearch = "cbpanel_member_id";
        $table->parsers = array(
            'cbpanel_ban_start'=> function($val, $row) {
                return date(DATE_RFC2822, $val);
            },
            'cbpanel_ban_end'=> function($val, $row) {
                return date(DATE_RFC2822, $val);
            },
            'cbpanel_ban_file'=> function($val, $row) {
                return "<ul class=\"ipsControlStrip ipsType_noBreak ipsList_reset\" data-ipscontrolstrip=\"\">
                            <a href=\"{$val}\" target=\"_blank\" data-ipstooltip _title=\"View Image\">
                                <i class=\"ipsControlStrip_icon fa fa-fw fa-search\"></i>
                            </a>
                        </ul>";
            }
        );

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', "<div style='padding:10px'>" . $table . "</div>" );
	}

}