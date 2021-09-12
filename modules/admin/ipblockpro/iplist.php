<?php


namespace IPS\cbpanel\modules\admin\ipblockpro;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * iplist
 */
class _iplist extends \IPS\Dispatcher\Controller
{	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'iplist_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
        \IPS\Output::i()->title = "Gathered IPs";
	    if ( \IPS\Request::i()->form == 'add') {
            $this->add();
        } elseif ( \IPS\Request::i()->form == 'edit') {
	        $this->edit();
        } else {
            /* Create the table */
            $table = new \IPS\Helpers\Table\Db('cbpanel_ipfilter_list', \IPS\Http\Url::internal('app=cbpanel&module=ipblockpro&controller=iplist'));
            $table->include = array( 'ip','value','time','type', 'id' );

            $table->quickSearch = "ip";
            $table->parsers = array(
                'time'=> function($val, $row) {
                    return (string)date('F j, Y, g:i a', $val);
                },
                'id'=> function($val, $row) {
                    return \IPS\Theme::i()->getTemplate('ipfilter','cbpanel')->editIP($val);
                }
            );
            /* Display */
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('ipfilter', 'cbpanel')->addToList() . \IPS\Theme::i()->getTemplate('global', 'core')->block('title', "<div style='padding-left:10px;padding-right:10px'>" . (string)$table . "</div>");
        }
	}

	protected function add()
    {
	    $form = new \IPS\Helpers\Form;
	    $form->add( new \IPS\Helpers\Form\Text('cbpanel_ipfilter_ip', null, true) );
	    $form->add( new \IPS\Helpers\Form\Number('cbpanel_ipfilter_value', 0, true, ['min'=>0,'max'=>1, 'decimals'=>true]) );

	    if ( $res = $form->values() ) {
            \IPS\Db::i()->delete('cbpanel_ipfilter_list', ["`ip`='{$res['cbpanel_ipfilter_ip']}'"]);
	        if (\filter_var($res['cbpanel_ipfilter_ip'], FILTER_VALIDATE_IP) && ($res['cbpanel_ipfilter_value'] >= 0 && $res['cbpanel_ipfilter_value'] <= 1)) {
                \IPS\Db::i()->insert('cbpanel_ipfilter_list', [
                    'ip'=>$res['cbpanel_ipfilter_ip'],
                    'value'=>$res['cbpanel_ipfilter_value'],
                    'time'=>time(),
                    'type'=>'MANUAL'
                ]);
                \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=iplist&state=success') );
            }
            \IPS\Output::i()->redirect( \IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=iplist&state=failure') );
        } else {
	        \IPS\Output::i()->output = $form;
        }
    }

    protected function edit()
    {
        $a = \IPS\Db::i()->select('*', 'cbpanel_ipfilter_list', ["`id`=" . \IPS\Request::i()->id]);
	    if ($a->count() == 1) {
            $ipdata = $a->first();
            $form = new \IPS\Helpers\Form;
            $form->add(new \IPS\Helpers\Form\Text('cbpanel_ipfilter_ip', $ipdata['ip'], true, ['disabled'=>true]));
            $form->add(new \IPS\Helpers\Form\Number('cbpanel_ipfilter_value', $ipdata['value'], true, ['min' => 0, 'max' => 1, 'decimals'=>true]));
            $form->add(new \IPS\Helpers\Form\Radio('cbpanel_ipfilter_type', null, true, ['options' => ['Edit', 'Delete']]));
            if ($res = $form->values()) {
                if ((int)$res['cbpanel_ipfilter_type'] == 0) {
                    if ( \IPS\Db::i()->update('cbpanel_ipfilter_list', ["value"=>"{$res['cbpanel_ipfilter_value']}"], ["`id`=" . \IPS\Request::i()->id])) {
                        \IPS\Output::i()->redirect(\IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=iplist&state=true'));
                    }
                } else {
                    if ( \IPS\Db::i()->delete('cbpanel_ipfilter_list', ["`id`=" . \IPS\Request::i()->id])) {
                        \IPS\Output::i()->redirect(\IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=iplist&state=true'));
                    }
                }
            } else {
                \IPS\Output::i()->output = $form;
            }
        } else {
            \IPS\Output::i()->redirect(\IPS\Http\Url::Internal('app=cbpanel&module=ipblockpro&controller=iplist&state=failure'));
        }
    }
}