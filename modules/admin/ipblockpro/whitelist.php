<?php


namespace IPS\cbpanel\modules\admin\ipblockpro;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * whitelist
 */
class _whitelist extends \IPS\Dispatcher\Controller
{	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'whitelist_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
        \IPS\Output::i()->title = "Whitelisted IPs";
        if ( \IPS\Request::i()->form == 'add') {
            $this->add();
        } elseif ( \IPS\Request::i()->form == 'remove') {
            $this->remove();
        } else {
            /* Create the table */
            $table = new \IPS\Helpers\Table\Db('cbpanel_ipfilter_whitelist', \IPS\Http\Url::internal('app=cbpanel&module=ipblockpro&controller=whitelist'));
            $table->include = array( 'ip','time', 'reason', 'id' );
            $table->quickSearch = "ip";
            $table->parsers = array(
                'time'=> function($val, $row) {
                    return (string)date('F j, Y, g:i a', $val);
                },
                'id'=> function($val, $row) {
                    return \IPS\Theme::i()->getTemplate('ipfilter','cbpanel')->removeIP($val);
                }
            );
            /* Display */
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('ipfilter', 'cbpanel')->addToWhitelist() . \IPS\Theme::i()->getTemplate('global', 'core')->block('title', (string) "<div style='padding-left:10px;padding-right:10px'>" . (string)$table . "</div>");
        }
	}

	protected function add()
    {
        $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Text('cbpanel_ipfilter_ip', "", true) );
        $form->add( new \IPS\Helpers\Form\Text('cbpanel_ipfilter_reason', "", true) );
        if ( $res = $form->values() ) {
            \IPS\Db::i()->delete('cbpanel_ipfilter_whitelist', ["`ip`='{$res['cbpanel_ipfilter_ip']}'"]);
            if ( \filter_var($res['cbpanel_ipfilter_ip'], FILTER_VALIDATE_IP) ) {
                \IPS\Db::i()->insert('cbpanel_ipfilter_whitelist', [
                    'ip'=>$res['cbpanel_ipfilter_ip'],
                    'reason'=>$res['cbpanel_ipfilter_reason'],
                    'time'=>time(),
                ]);
                \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=whitelist&state=success') );
            }
            \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=whitelist&state=failure') );
        } else {
            \IPS\Output::i()->output = $form;
        }
    }

    protected function remove()
    {
        if (\IPS\Request::i()->confirm == 1 && ( \IPS\Db::i()->select('id', 'cbpanel_ipfilter_whitelist', ['`id`=' . \IPS\Request::i()->id])->count() == 1) ) {
            if ( \IPS\Db::i()->delete('cbpanel_ipfilter_whitelist', ["`id`=" . \IPS\Request::i()->id]) ) {
                \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=whitelist&state=success') );
            }
        }
        \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=whitelist&state=failure') );
    }
}