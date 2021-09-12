<?php


namespace IPS\cbpanel\modules\front\activate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * activation
 */
class _activation extends \IPS\Dispatcher\Controller
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
		if (self::groupInfo()) {
			$data = array();
			$data['setup'] = false;
			if ((@isset($_POST['key'])) && (mb_strlen($_POST['key']) == 19)) {
				$data['key'] = \htmlspecialchars($_POST['key']);
				$data = self::run('state', $data);
			}
			\IPS\Output::i()->title = "Key Activation";
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'activation.css', 'cbpanel', 'front' ));
			\IPS\Output::i()->output = self::render($data);
		} else {

			\IPS\Output::i()->output = self::render(array('setup'=>true));
		}

	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it

	protected function render($data = array()) {
		$page = \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->header();
		if ($data['setup'] != true) {
			if (!isset($data['key'])) {
				$page .= \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->form($_SERVER['REQUEST_URI']);
			} else {
				if ($data['state'] == 2) {
                    $page .= \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->redeemed($data['product'], date("m/d/y", $data['expires']), "https://localhost/forum/files/fil");
                } else if ($data['state'] == 3) {
				    $page .= \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->full($_SERVER['REQUEST_URI']);
				} else {
                    $page .= \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->invalid($_SERVER['REQUEST_URI']);
                    unset($_POST['key']);
                }
			}
		} else {
			$page .= \IPS\Theme::i()->getTemplate('activation', 'cbpanel')->unconfigured();
		}
			return $page;
	}

	protected function run($type, $data) {
		$member = \IPS\Member::loggedIn();
		$groupInfo = self::groupInfo();
		$memberID = $member->member_id;
		if ($type == 'state') {
			$key = $data['key'];
			$q = \IPS\Db::i()->select('*', 'cbpanel_license_keys', ["key_string='" . $key . "' AND key_state=0"]);
			if (($q->count() > 0) && ($q->count() < 2)) {
			    if ($this->verify($q->first())) {
                    for ($i = 0; $i < count($groupInfo->blacklist); $i++) {
                        $groupInfo->blacklist[$i] = (int)$groupInfo->blacklist[$i];
                    }
                    if (!in_array($member->groups, $groupInfo->blacklist)) {
                        \IPS\Db::i()->update('core_members', "`member_group_id`='" . $groupInfo->customerGroup . "'", ["`member_id`=$memberID"]);
                    }
                    $q = $q->first();
                    \IPS\Db::i()->update('cbpanel_license_keys', "`key_state`=1, `user_id`=$memberID", ["`key_string`='$key'"]);
                    $d = time();
                    $time = (object)[
                        'from' => $d,
                        'to' => ($d + ($q['duration'] * 24 * 60 * 60)),
                    ];
                    $data = array_merge($data, array('expires' => $time->to, 'product' => $q['product_name'], 'state' => 2));
                    \IPS\Db::i()->insert('nexus_purchases', [
                        'ps_member' => $memberID,
                        'ps_name' => $q['product_name'],
                        'ps_active' => 1,
                        'ps_cancelled' => 0,
                        'ps_start' => $time->from,
                        'ps_expire' => $time->to,
                        'ps_renewals' => 0,
                        'ps_renewal_price' => 0.00,
                        'ps_renewal_unit' => 'd',
                        'ps_app' => 'nexus',
                        'ps_type' => 'package',
                        'ps_item_id' => $q['product_id'],
                        'ps_custom_fields' => [],
                        'ps_extra' => 'null',
                        'ps_parent' => 0,
                        'ps_invoice_pending' => 0,
                        'ps_invoice_warning_sent' => 0,
                        'ps_original_invoice' => 0,
                        'ps_tax' => 0,
                        'ps_can_reactivate' => 0,
                        'ps_renewal_currency' => 'EUR',
                        'ps_show' => 1,
                        'ps_grace_period' => 0,
                        'ps_conv_parent' => 0,
                    ]);
                    $data['state'] = 2;
                    return $data;
                } else {
			        $data['state'] = 3;
			        return $data;
                }
			} else {
				\IPS\Db::i()->insert('cbpanel_failed_key_attempt', [
				    'username' => $memberID,
                    'ip'       => self::grabIP(),
                    'key'      => $key
                ]);
				$data['state'] = 1;
				return $data;
			}
		} else {
			return false;
		}
	}
	
	protected function hexToStr($hex){
		$string='';
		for ($i=0; $i < \strlen($hex)-1; $i+=2){
			$string .= chr(hexdec($hex[$i].$hex[$i+1]));
		}
		return $string;
	}
	

	protected function grabIP() {
		if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}
		$client  = @$_SERVER['HTTP_CLIENT_IP'];
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		$remote  = $_SERVER['REMOTE_ADDR'];
		if (filter_var($forward, FILTER_VALIDATE_IP)) {
			$ip = $forward;
		} elseif(filter_var($client, FILTER_VALIDATE_IP)) {
			$ip = $client;
		} else {
			$ip = $remote;
		}
		return $ip;
	}

	protected function groupInfo() {
		$data = \IPS\Db::i()->select('data', 'cbpanel_settings', "`id`='groupInfo'");
		if ($data->count() > 0) {
		    return json_decode($data->first());

        } else {
		    return false;
        }
	}

	protected function verify($keyData) {
        if (
            ( // Compare used slots to max slots
                json_decode(
                    \IPS\Db::i()->select('p_cbpanel_data', 'nexus_packages', ["`p_id`=" . $keyData['product_id']] )->first()
                )->slots->used
                < // Use < instead of <= because it incorporates current + the new key
                json_decode(
                    \IPS\Db::i()->select('p_cbpanel_data', 'nexus_packages', ["`p_id`=" . $keyData['product_id']] )->first()
                )->slots->max
            )
            || // Unless slots are unlimited
            (
                json_decode(
                    \IPS\Db::i()->select('p_cbpanel_data', 'nexus_packages', ["`p_id`=" . $keyData['product_id']] )->first()
                )->slots->max
                ==
                -1
            )
        ) {
            return true;
        } else {
            return false;
        }
    }
}