<?php


namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * products
 */
class _products extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('products_manage');
        parent::execute();
    }

    /**
     * ...
     *
     * @return    void
     */
    protected function manage()
    {
        // This is the default method if no 'do' parameter is specified
        $this->data = $this->productData();
        switch( \IPS\Request::i()->action ) {
            case 'update':
                $this->update();
                break;
            default:
                $this->render();

        }
    }

    protected $data;

    protected function filterPackage($data) {
        foreach ($data['package'] as $product) {
            if ($product->id == (int)\IPS\Request::i()->id) {
                return $product;
            }
        }
        return false;
    }

    protected function filterStatus(&$obj, $status) {
        $codes = [
            1=>"Undetected",
            2=>"Detected",
            3=>"Updating",
            4=>$status
        ];
        $obj->statusMessage = $codes[$obj->statusCode];
        return $obj;
    }

    protected function update() {
        $data = $this->filterPackage($this->data);
        $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Text( 'cbpanel_product_id', (int) \IPS\Request::i()->id, TRUE, ['disabled'=>true] ) );
        $form->add( new \IPS\Helpers\Form\Text( 'cbpanel_product_name', $data->name, TRUE ) );
        $form->add( new \IPS\Helpers\Form\Select( 'cbpanel_product_status_code', $data->settings->statusCode, TRUE, ['options'=>[1=>"Undetected", 2=>"Detected", 3=>"Updating", 4=>"Custom"]] ) );
        $form->add( new \IPS\Helpers\Form\Text( 'cbpanel_product_status_text', $data->settings->statusMessage, FALSE ) );
        $form->add( new \IPS\Helpers\Form\Number( 'cbpanel_product_max_slot', $data->settings->slots->max, TRUE ) );
        if ($values = $form->values()) {
            \IPS\Output::i()->output = "Redirecting...";
            \IPS\Db::i()->update('nexus_packages', "`p_name`='" . $values['cbpanel_product_name'] . "'", "`p_id`=" . \filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) );
            $obj = json_decode( \IPS\Db::i()->select('p_cbpanel_data', 'nexus_packages', "`p_id`=" . \filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) )->first() );
            $obj->statusCode = (int)$values['cbpanel_product_status_code'];
            $this->filterStatus($obj, $values['cbpanel_product_status_text']);
            $obj->slots->max = (string)$values['cbpanel_product_max_slot'];
            \IPS\Db::i()->update( 'nexus_packages', "`p_cbpanel_data`='" . json_encode($obj) . "'", "`p_id`=" . \filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) );
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=products&success=true') );
        } else {
            \IPS\Output::i()->output = $form;
        }
    }
    /**
     *
     * `p_cbpanel_data` JSON Object:
     * 		{
     * 			"isproduct":bool,
     * 			"status":"String",
     * 			"slots":{
     * 				"used":int,
     * 				"max":int
     * 			}
     * 		}
     */

    protected function productData() {
        $obj = json_encode((object) [
            'isProduct'=>true,
            'statusCode'=>1,
            'statusMessage'=>"Undetected",
            'slots'=>(object) [
                'used'=>0,
                "max"=>-1
            ]
        ]);
        \IPS\Db::i()->update('nexus_packages', "`p_cbpanel_data`='" . $obj . "'", ["`p_cbpanel_data`=null OR `p_cbpanel_data` IS NULL"]);
        $data = array(
            'package'=>array(),
            'subscription'=>array(),
            'table'=>array(),
        );

        $packageData = \IPS\Db::i()->select('*', 'nexus_packages');
        for ($i=0;$i<$packageData->count();$i++) {
            $packageData->next();
            $d = (object) [
                'id' => $packageData->current()['p_id'],
                'image' => $packageData->current()['p_image'],
                'name' => $packageData->current()['p_name'],
                'settings' => json_decode($packageData->current()['p_cbpanel_data']), // $package[$i]->settings->id/isProduct/status/slots[used/max]
                'stock' => $packageData->current()['p_stock']
            ];
            array_push($data['package'], $d);
        }

        // Will be used for calculating total slots
        $subData = \IPS\Db::i()->select('*', 'nexus_purchases', ["(`ps_active`=1) AND (`ps_cancelled`=0) AND (`ps_start`>0) AND (`ps_expire`>" . time() . ")"]);
        for ($i=0;$i<$subData->count();$i++) {
            $subData->next();
            $d = (object) [
                'id' => $subData->current()['ps_item_id'],
                'pid' => $subData->current()['ps_id'],
                'mid' => $subData->current()['ps_member'],
            ];
            array_push($data['subscription'], $d);
        }
        //$apiKey = json_decode(\IPS\Db::i()->select('data', 'cbpanel_settings')->first());
        //$apiKey = $apiKey->apiKey;
        for ($i=0;$i<\count($data['package']);$i++) {
            if ($data['package'][$i]->settings->isProduct) {
                // If product, add to table
                $id = $data['package'][$i]->id;
                $d = (object) [
                    'cname' => $data['package'][$i]->name,
                    'statusMessage' => array($data['package'][$i]->settings->statusCode, $data['package'][$i]->settings->statusMessage),
                    'slots' => array($data['package'][$i]->settings->slots->used, $data['package'][$i]->settings->slots->max),
                    'button' => array($id),
                    //'file'=>array($id),
                    //'upload'=>array($id, $i, $apiKey),
                ];
                array_push($data['table'], $d);
            }
        }
        return $data;
    }

    protected function render() {
        $data = $this->data;
        $table = new \IPS\Helpers\Table\Custom( $data['table'], \IPS\Http\Url::internal("app=cbpanel&module=cbpanel&controller=products"), array( array( 'email<>?', '' ) ), 'joined' );
		$table->include = array( 'cname', 'statusMessage', 'slots', 'button');
		$table->mainCollumn = 'cname';
		$table->sortBy = $table->sortBy ?: 'cname';
		$table->langPrefix = "cbpanel_table_";
		$table->quickSearch = 'cname';
		$table->noSort = array('slots','button');
        $table->parsers = array(
            'statusMessage' => function($val, $row) {
                return $val[1];
            },
            'slots' => function($val, $row) {
                return $val[0] . "/" . $val[1];
            },
            'button' => function($val, $row) {
                return \IPS\Theme::i()->getTemplate('products', 'cbpanel')->updateForm( \IPS\Http\Url::internal("app=cbpanel&module=cbpanel&controller=products&action=update&id=" . $val[0]) );
            }
        );
        \IPS\Output::i()->title = "Products";
		\IPS\Output::i()->output = (string)$table;

    }
}