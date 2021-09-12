<?php


namespace IPS\cbpanel\modules\front\reseller;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * redeem
 */
class _redeem extends \IPS\Dispatcher\Controller
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
        if ( \in_array(json_decode(\IPS\Db::i()->select("data", 'cbpanel_settings')->first())->reseller->group ?? -1, \IPS\Member::loggedIn()->groups) ) {

            \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, \IPS\Theme::i()->css('redeem.css', 'cbpanel', 'front'));

            $form = new \IPS\Helpers\Form('cbpanel_redeem_form', 'Submit');
            $form->addHeader('cbpanel_redeem_header');
            $form->add(new \IPS\Helpers\Form\Text('cbpanel_redeem_key', "", true, ['placeholder' => "XXXX-XXXX-XXXX-XXXX-XXXX", 'size' => 24, 'regex' => '/....-....-....-....-..../', 'maxLength' => 24, 'minLength' => 24]));
            // $form->add( new \IPS\Helpers\Form\Captcha() );
            $form->class = "ipsType_center";
            if ($values = $form->values()) {

                try {
                    $licenseKey = \IPS\nexus\Purchase\LicenseKey::load($values['cbpanel_redeem_key']);
                    if ($licenseKey->active) {

                        $purchase = $licenseKey->purchase;
                        $item = \IPS\nexus\Package::load($purchase->item_id);
                        $resell_data = \json_decode($item->seo_name);


                        if (isset($resell_data->Count) && isset($resell_data->Package) && isset($resell_data->Duration) && isset($resell_data->Renewal) && isset($resell_data->Unit)) {

                            $licenseKey->active = false;
                            $licenseKey->save();

                            $keys = [];
                            for ($i = 0; $i < $resell_data->Count; $i++) {
                                $key = \strtoupper(\substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4) . "-" . \substr(md5(uniqid(mt_rand(), true)), 0, 4));
                                \IPS\Db::i()->insert('cbpanel_reseller_licensing', [
                                    'license_key' => $key,
                                    'purchase_license_key' => $licenseKey->key,
                                    'reference_package' => $resell_data->Package,
                                    'package_duration' => $resell_data->Duration,
                                    'package_renewal' => $resell_data->Renewal,
                                    'package_unit' => $resell_data->Unit,
                                    'reseller_member_id' => \IPS\Member::loggedIn()->member_id,
                                ]);
                                array_push($keys, $key);
                            }
                            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('rcp', 'cbpanel')->redeem_success($item->name, $keys, \mb_substr($item->name, 3));

                        } else {
                            $form->addMessage("Internal Error");
                            \IPS\Output::i()->output = $form;
                        }


                    } else {
                        $form->addMessage("Invalid Key!");
                        \IPS\Output::i()->output = $form;
                    }

                } catch (\OutOfRangeException $e) {
                    $form->addMessage("Invalid Key!");
                    \IPS\Output::i()->output = $form;
                }

            } else {
                \IPS\Output::i()->output = $form;
            }
        } else {
            \IPS\Output::i()->error('The page you are trying to access is not available for your account.', '2S333/1', '401');
        }

	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}