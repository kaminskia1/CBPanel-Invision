<?php

namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * subscriptions
 */
class _subscriptions extends \IPS\Dispatcher\Controller {

    protected $userData;

    /**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'subscriptions_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage() {
	    if (!$this->action()) {
            $this->userData = $this->userData( $this->subscriptionData() );
            $this->render();
        }
	}

	protected function action() {
        switch ( \IPS\Request::i()->action ) {
            case 'revoke':
                $this->revoke();
                return TRUE;
            case 'update':
                $this->update();
                return TRUE;
            case 'capture':
                $this->capture();
                return TRUE;
            case 'restore':
                $this->restore();
                return TRUE;
            default:
                return FALSE;
        }
    }

	protected function revoke() {
		$id = (int) \IPS\Request::i()->id;
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text('cbpanel_revoke_id', $id, TRUE, ['disabled'=>true]) );
		$form->add( new \IPS\Helpers\Form\Radio('cbpanel_revoke_confirm', NULL, TRUE, ['options'=>["No", "Yes"]]) );
		if ($values = $form->values()) {
            \IPS\Output::i()->output = "Redirecting...";
            if ($values['cbpanel_revoke_confirm']) {
                if ( (int)\IPS\Db::i()->select("COUNT(*)", 'nexus_purchases', ["(`ps_id`=" . $id . ") AND (`ps_active`=1) AND (`ps_cancelled`=0)"])->first() == 1) {
                    if (\IPS\Db::i()->update("nexus_purchases", "`ps_cancelled`=1", ["`ps_id`=" . $id])) {
                        \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=true') );
                    } else {
                        goto fail;
                    }
                } else {
                    goto fail;
                }
            } else {
                goto fail;
            }
            fail:
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=false') );
        } else {
		    \IPS\Output::i()->output = $form;
        }
	}

	protected function update() {
	    $sql = \IPS\Db::i()->select("`p_id`,`p_name`", 'nexus_packages');
        $product_id = [];
        $product_name = [];
	    for ($i=0;$i<$sql->count();$i++) {
	        $sql->next();
            array_push($product_id, $sql->current()['p_id']);
            array_push($product_name, $sql->current()['p_name']);
        }
	    $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Select('cbpanel_update_products', $product_id[0], TRUE, ['options'=>$product_name]) );
        $form->add( new \IPS\Helpers\Form\Radio('cbpanel_update_type', 1, TRUE, ['options'=>["Remove","Add"]]) );
        $form->add( new \IPS\Helpers\Form\Number('cbpanel_update_duration', 0, TRUE, ['min'=>0, 'max'=>999]));

        if ($values = $form->values()) {
            \IPS\Output::i()->output = "Redirecting...";
            $product = $product_id[$values['cbpanel_update_products']];
            if ($values['cbpanel_update_type'] == 0) {
                $sign = "-";
            } else {
                $sign = "+";
            }
            $time = $values['cbpanel_update_duration'] * 24 * 60 * 60;
            if ( \IPS\Db::i()->update('nexus_purchases', "`ps_expire`=`ps_expire`" . $sign . $time, ["(`ps_active`=1) AND (`ps_cancelled`=0) AND (`ps_item_id`=" . $product . ")"]) ) {
                \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=true') );
            } else {
                \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=false') );
            }
        } else {
            \IPS\Output::i()->output .= $form;
        }
    }

    protected function capture() {
	    $form = new \IPS\Helpers\Form;

	    $form->add( new \IPS\Helpers\Form\Select('cbpanel_capture_subscription', null, TRUE, ['options'=>$this->productData()]) );

	    if ($values = $form->values()) {
            $pid = $values['cbpanel_capture_subscription'];
            $subscriptionData = $this->subscriptionData();
            $new = [];
            foreach ($subscriptionData as $item) {
                if ($item->ps_item_id == $pid) {
                    array_push($new, $item->ps_id);
                }
            }
            \IPS\Db::i()->insert('cbpanel_capures', ['time'=>time(), 'purchase_ids'=>json_encode($new), 'product_name'=>$this->productData()[$pid]] );
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=true') );
        } else {
	        \IPS\Output::i()->output = $form;
        }
    }

    protected function restore() {
        $columns = [];
        $temp = [];
	    if ( \IPS\Db::i()->select("COUNT(*)", 'cbpanel_capures')->first() > 0) {
	        $data = \IPS\Db::i()->select("*", 'cbpanel_capures');
	        for($i=0;$i<$data->count();$i++) {
	            $data->next();
	            array_push($temp, $data->current());
            }
        }

	    // Process columns
        foreach ($temp as $row) {
            $columns[$row['id']] ="{$row['product_name']} (" . date("m/d/Y H:i:s", $row['time']) . ")";
        }
	    $form = new \IPS\Helpers\Form;

	    $form->add( new \IPS\Helpers\Form\Select('cbpanel_restore_subscription', null, TRUE, ['options'=>$columns]) );
        $form->add( new \IPS\Helpers\Form\Radio('cbpanel_restore_remove', 0, TRUE, ['options'=>['No','Yes']]) );
        if ($values = $form->values()) {
            $cid = (int)$values['cbpanel_restore_subscription'];
            if ( (int)\IPS\Db::i()->select('COUNT(*)', 'cbpanel_capures', ["`id`={$cid}"])->first() == 1 ) {
                $capture = \IPS\Db::i()->select("*", 'cbpanel_capures', ["`id`={$cid}"])->first();
                $error = [];
                // Start cycling through all the PID's and adding time
                $addTime = time() - (int)$capture['time'];
                foreach (json_decode($capture['purchase_ids']) as $id) {
                    if (\IPS\Db::i()->select('*', 'nexus_purchases', ["`ps_id`={$id} AND `ps_active`=0 AND (`ps_expire` + {$addTime} > " . time() . ")"])->count() == 1) {
                        \IPS\Db::i()->forceQuery("UPDATE `nexus_purchases` SET `ps_active`=1 WHERE ps_id`={$id}");
                    }
                    if (!\IPS\Db::i()->forceQuery("UPDATE `nexus_purchases` SET `ps_expire`=`ps_expire` + {$addTime} WHERE `ps_id`={$id}")) {
                        array_push($error, $id);
                    }
                }
                if ((int)$values['cbpanel_restore_remove'] == 1) {
                    \IPS\Db::i()->delete("cbpanel_capures", ["`id`={$values['cbpanel_restore_subscription']}"]);
                }     
                if (\count($error) > 0) {
                    \IPS\Output::i()->redirect(\IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=false&ids=[' . implode(", ", $error) . "]"));

                } else {
                    \IPS\Output::i()->redirect(\IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=true'));
                }
            } else {
                \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=subscriptions&success=false') );
            }
        } else {
            \IPS\Output::i()->output = $form;
        }
    }

    protected function subscriptionData($subscriptionData = []) {
        $data = \IPS\Db::i()->select("`ps_id`,`ps_member`,`ps_item_id`,`ps_expire`", "nexus_purchases", ["`ps_active`=1 AND `ps_expire`>0 AND `ps_cancelled`=0 AND `ps_active`=1"]);
        switch ($data->count()) {
            case 0:
                return [];
                break;
            case 1:
                return [(object)$data->first()];
                break;
            default:
                for ($i=0;$i<$data->count();$i++) {
                    $data->next();
                    array_push($subscriptionData, (object)$data->current());
                }
                return $subscriptionData;
        }
    }

	protected function userData($subscriptionData = []) {
        $keyQuery = \IPS\Db::i()->select("`user_id`,`key_string`,`product_id`", "cbpanel_license_keys", ['`key_state`=1']);
        switch ($keyQuery->count()) {
            case 0:
                $keyData = [];
                break;
            case 1:
                $keyData = (array)[(object)$keyQuery->first()];
                break;
            default:
                $keyData = [];
                for ($i=0;$i<$keyQuery->count();$i++) {
                    $keyQuery->next();
                    array_push($keyData, (object)$keyQuery->current());
                }
        }

        $idArray = array();
        for ($x=0;$x<\count($subscriptionData);$x++) {
            $idArray[$x] = $subscriptionData[$x]->ps_member;
        }
        $idArray = array_values(array_unique($idArray));
        $sql = "";
        for ($x=0;$x<\count($idArray);$x++) {
            if ($x != 0) {
                $sql .= " OR";
            }
            $sql .= " (`member_id`='" . $idArray[$x] . "')";
        }
        $userQuery = \IPS\Db::i()->select( "`member_id`,`email`", "core_members", ($sql == '') ? "`member_id`=-1" : [$sql] );
        $userData = [];
        if ($userQuery->count() != 0) {
            for ($d = 0; $d < $userQuery->count(); $d++) {
                $userQuery->next();
                $arr = $userQuery->current();
                $i = (object)[
                    'id' => $arr['member_id'],
                    'username' => $arr['member_id'],
                    'group' => \IPS\Member::load($arr['member_id'])->groupName,
                    'email' => $arr['email'],
                    'key' => $this->keys($arr['member_id'], $keyData),
                    'subscription' => $this->subscription($arr['member_id'], $subscriptionData),
                ];
                array_push($userData, $i);
            }
            foreach ($userData as $row) {
                $row->view = $row->id;
            }
        }
        return $userData;
	}

	protected function productData() {
        $que = \IPS\Db::i()->select("*", "nexus_packages");
        $data = [];
        if ($que->count() > 0) {
            for ($i=0;$i<$que->count();$i++) {
                $que->next();
                $temp = $que->current();
                $data[$temp['p_id']] = $temp['p_name'];
            }
        }
        return $data;
    }

	protected function keys($id, $keyData) {
		$keys = array();
		$products = array();
		for ($x=0;$x<\count($keyData);$x++) {
			if ($keyData[$x]->user_id == $id)
				array_push ( $keys, $keyData[$x]->key_string );
				array_push ( $products, $keyData[$x]->product_id );
		}
		return (object) [
			'key' => $keys,
			'product_id' => $products,
		];
	}

	protected function subscription($id, $subscriptionData) {
        $sub_id = array();
		$item = array();
		$expire = array();
		$name = array();
		for ($x=0;$x<\count($subscriptionData);$x++) {
			if ($subscriptionData[$x]->ps_member == $id) {

				$productData = $this->productData();
				if (isset( $productData[$subscriptionData[$x]->ps_item_id])) {
					$subscriptionData[$x]->package_name = $productData[(int)$subscriptionData[$x]->ps_item_id];
				} else {
					$subscriptionData[$x]->package_name = "Undefined";
				}

				array_push ( $sub_id, $subscriptionData[$x]->ps_id);
				array_push ( $item, $subscriptionData[$x]->ps_item_id);
				array_push ( $name, $subscriptionData[$x]->package_name);
				array_push ( $expire, date("m/d/y", $subscriptionData[$x]->ps_expire) );
			}
		}
		return (object) [
			'ps_id'        => $sub_id,
			'ps_item_id'   => $item,
			'ps_expire'    => $expire,
			'package_name' => $name,
		];
	}

	protected function tableBuilder() {
        $table = new \IPS\Helpers\Table\Custom( $this->userData, \IPS\Http\Url::internal("&app=cbpanel&module=cbpanel&controller=subscriptions"), array( array( 'email<>?', '' ) ), 'joined' );
        $table->include = array( 'username', 'group', 'email', 'subscription', 'key', 'view' );
        $table->mainCollumn = 'username';
        $table->sortBy = $table->sortBy ?: 'name';
        $table->langPrefix = "cbpanel_table_";
        $table->quickSearch = 'email';
        $table->noSort = array('username', 'group', 'view', 'subscription', 'key');
        $table->parsers = array(
            'username' => function($val, $row) {
                return \IPS\Theme::i()->getTemplate( 'subscriptions', 'cbpanel' )->username(
                    \IPS\Db::i()->select( 'name', 'core_members', ["`member_id`=" . $val])->first(), // User ID => Name
                    \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id=" . $val), // User ID => Profile URL
                    \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::load( $val ), 'tiny' ) // User ID => Photo HTML
                );
            },
            'group' => function($val, $row) {
                return "<div>" . htmlspecialchars_decode($val) . "</div>";
            },
            'subscription' => function($val, $row) {
                return \IPS\Theme::i()->getTemplate( 'subscriptions', 'cbpanel' )->subscription( $val, \IPS\Http\Url::internal("app=cbpanel&module=cbpanel&controller=subscriptions&action=revoke&id=") );
            },
            'key' => function($val, $row) {
                return \IPS\Theme::i()->getTemplate( 'subscriptions', 'cbpanel' )->key( $val );
            },
            'view' => function($val, $row) {
                return \IPS\Theme::i()->getTemplate( 'subscriptions', 'cbpanel' )->view( \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id=" . $val) );
            }
        );
        return $table;
    }

    protected function controls() {
        return \IPS\Theme::i()->getTemplate( 'subscriptions', 'cbpanel' )->controls();
    }

    protected function render() {
        \IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'subscriptions.css', 'cbpanel', 'admin' ) );
        \IPS\Output::i()->title = "Subscriptions";
        \IPS\Output::i()->output = $this->controls() . $this->tableBuilder();
    }

}