<?php
namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * keys
 */
class _keys extends \IPS\Dispatcher\Controller
{

    protected $data = [];

    /**
     * Execute
     *
     * @return	void
     */
    public function execute() {
        \IPS\Dispatcher::i()->checkAcpPermission( 'keys_manage' );
        parent::execute();
    }
    /**
     * ...
     *
     * @return	void
     */
    protected function manage() {
        if ( isset( \IPS\Request::i()->generate ) ) {
            $this->generate();
        } else if( isset( \IPS\Request::i()->revokeID ) ) {
            $this->revoke();
        } else {
            $this->run();
            $this->render();
        }
    }

    protected function revoke() {
        \IPS\Dispatcher::i()->checkAcpPermission( 'keys_edit' );
        $revokeID = (int)\IPS\Request::i()->revokeID;
        $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Number('cbpanel_revoke_id', $revokeID, ['disabled'=>true]) );
        $form->add( new \IPS\Helpers\Form\Radio('cbpanel_revoke_confirm', $revokeID, ['options'=>["No","Yes"]]) );
        if ($values = $form->values()) {
            \IPS\Output::i()->output = "Redirecting...";
            if ($values['cbpanel_revoke_confirm'] == 1) {
                \IPS\Db::i()->delete('cbpanel_license_keys', ["`id`='{$revokeID}'"]);
                \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=keys&success=true') );
            } else {
                \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=keys&success=false') );
            }
        } else {
            \IPS\Output::i()->output = $form;
        }
    }

    protected function generate() {
        \IPS\Dispatcher::i()->checkAcpPermission( 'keys_create' );
        if ( \IPS\Request::i()->isAjax() ) {
            if (
                \IPS\Request::i()->count > 0 &&
                mb_strlen(\IPS\Request::i()->product_name ) > 0 &&
                \IPS\Request::i()->product_id > 0 &&
                \IPS\Request::i()->duration > 0 ) {
                $insertData = array();
                $keys = array();
                for ($i=0;$i<(int)\IPS\Request::i()->count;$i++) {
                    $key = \strtoupper(\substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4));
                    array_push($keys, $key);
                    array_push($insertData, array(
                        'key_string'=>$key,
                        'product_name'=>\IPS\Request::i()->product_name,
                        'product_id'=>\IPS\Request::i()->product_id,
                        'key_state'=>0,
                        'duration'=>\IPS\Request::i()->duration
                    ) );
                }
                if ( \IPS\Db::i()->insert('cbpanel_license_keys', $insertData) ) {
                    echo json_encode($keys);
                    die();
                }
            }
        }
        echo '["Invalid parameters!"]';
        die();
    }

    protected function run() {
        $keySelect = \IPS\Db::i()->select('*', 'cbpanel_license_keys', null, "`id` DESC LIMIT 25");

        $this->data['key'] = array();
        for ($i=0;$i<$keySelect->count();$i++) {
            $keySelect->next();
            array_push($this->data['key'], (object)$keySelect->current());
        }
        for ($i=0;$i<\count($this->data['key']);$i++) {
            $this->data['key'][$i]->username = \IPS\Member::load( $this->data['key'][$i]->user_id )->name;
        }

        $failSelect = \IPS\Db::i()->select('*', 'cbpanel_failed_key_attempt', null, "`id` DESC LIMIT 25");

        $this->data['failed'] = [];

        for($i=0;$i<$failSelect->count();$i++) {
            $failSelect->next();
            array_push($this->data['failed'], (object)$failSelect->current());
        }

        $productSelect = \IPS\Db::i()->select('`p_id`,`p_name`', 'nexus_packages');

        $this->data['product'] = (object)[];
        for($i=0;$i<$productSelect->count(0);$i++) {
            $productSelect->next();
            $d = $productSelect->current()['p_id'];
            $this->data['product']->$d = $productSelect->current()['p_name'];
        }
    }


    protected function js() {
        return "
            $('button.ipsButton.ipsButton_primary.ipsGenerate').click(()=>{
                $.post({
                    url: document.location.href,
                    data: {
                        generate: 1,
                        count: $('input.ipsFieldRowItem_input.genProductCount').val(),
                        product_name: $('input.ipsFieldRowItem_input.genProductName').val(),
                        product_id: $('select.ipsFieldRow_select.genProductID.ipsSelect').val(),
                        duration: $('input.ipsFieldRowItem_input.genProductDuration').val(),
                    },
                    success: (a)=>{
                        a = JSON.parse(a);
                        $('textarea.ipsOutput').val( a.join('\\n') );
                    }
                });
            
            });
        ";
    }
    
    protected function render() {
        \IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'keys.css', 'cbpanel', 'admin' ) );
        \IPS\Output::i()->title = "License Keys";
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('licenseKey', 'cbpanel')->generate( $this->data['product'] );
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('licenseKey', 'cbpanel')->keys( $this->data['key'], \IPS\Http\Url::internal("app=cbpanel&module=cbpanel&controller=keys&revokeID=") );
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('licenseKey', 'cbpanel')->failedAttempts( $this->data['failed'] );
        \IPS\Output::i()->output .= "<script>{$this->js()}</script>";
    }

}