<?php


namespace IPS\cbpanel\modules\admin\cheapboost;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * clientlog
 */
class _clientlog extends \IPS\Dispatcher\Controller
{

    protected $table;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clientlog_manage' );
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
		$table = new \IPS\Helpers\Table\Db( 'client_log', \IPS\Http\Url::internal( 'app=cbpanel&module=cbpanel&controller=clientlog' ) );
        $table->include = array( 'timestamp','ip','hwid','loader_version','windows_username','country','city','region_name','active_login_attempts','login_status','login_user','message','log_type' );
        $table->quickSearch = "login_user";
        $table->parsers = array(
            'hwid' => function($val, $row) {
                return "<input readonly class='hwid' value='" . $val . "'>";
            }
        );
		/* Display */

        \IPS\Output::i()->title = "Client Log";
        \IPS\Output::i()->output = "
            <style>
                .hwid {
                    background: rgba(0,0,0,0);
                    width: 100%;
                    border: 0px;
                    overflow: hidden;
                }
                .hwid:focus {
                    outline: 0;
                }
            </style>
        ";
        \IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', "<div style='padding:10px'>" . $table . "</div>" );

    }
}