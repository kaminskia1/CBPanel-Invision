<?php


namespace IPS\cbpanel\modules\front\redeem;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
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
     * @return    void
     */
    public function execute()
    {

        parent::execute();
    }

    /**
     * ...
     *
     * @return    void
     */
    protected function manage()
    {
        $member = \IPS\Member::loggedIn();
        if ($member->member_id != null) {

            $settings = json_decode(\IPS\Db::i()->select('data', 'cbpanel_settings')->first())->activation;

            \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, \IPS\Theme::i()->css('redeem.css', 'cbpanel', 'front'));

            $form = new \IPS\Helpers\Form('cbpanel_redeem_form', 'Submit');
            $form->addHeader('cbpanel_redeem_header');
            $form->add(new \IPS\Helpers\Form\Text('cbpanel_redeem_key', "", true, ['size' => 19, 'regex' => '/....-....-....-..../', 'maxLength' => 19, 'minLength' => 19]));
           // $form->add( new \IPS\Helpers\Form\Captcha() );
            $form->class = "ipsType_center";

            if ($val = $form->values()) {

                $data = \IPS\Db::i()->select('*','cbpanel_reseller_licensing', ["`license_key`='{$val['cbpanel_redeem_key']}'"]);
                if ($data->count() == 1) {

                    $data = (object)$data->first();
                    if ($data->redeemed == 0) {

                        $member = \IPS\Member::loggedIn();

                        $product = \IPS\nexus\Package::load( $data->reference_package );

                        $resellData = (array)[
                            'Duration' => $data->package_duration,
                            'Unit' => $data->package_unit,
                            'Renewal' => $data->package_renewal,
                            'Package' => $data->reference_package,
                            'ExpireDate' => date('m/d/y', time() + ( $data->package_duration['Duration'] * 24 * 60 * 60 )),
                            'PackageName' => $product->name,
                        ];

                        $possiblePastPurchases = \IPS\Db::i()->select('*', 'nexus_purchases', ["`ps_item_id`={$resellData['Package']} AND `ps_member`={$member->member_id}"]);
                        if ($possiblePastPurchases->count() == 1) {

                            // Purchase exists
                            $updatearr = [
                                'ps_expire' => (((int)$possiblePastPurchases->first()['ps_expire'] < time()) ? time() : (int)$possiblePastPurchases->first()['ps_expire']) + ($resellData['Duration'] * 24 * 60 * 60)
                            ];


                            if ($possiblePastPurchases->first()['ps_active'] == 0) {
                                // Not active, set new group and active state
                                $updatearr['ps_active'] = 1;
                                $updatearr['ps_extra'] = json_encode(['old_primary_group' => $member->group['g_id']]);
                            }


                            // Move the group
                            if (!\array_intersect($member->groups, $settings->blacklist)) {
                                $updatearr['ps_extra'] = json_encode(['old_primary_group' => $member->group['g_id']]);
                                \IPS\Db::i()->update('core_members', [ 'member_group_id' => $settings->group ], ["`member_id`=" . $member->member_id] );
                            }


                            \IPS\Db::i()->update( 'nexus_purchases', $updatearr, [ "`ps_id`=" . $possiblePastPurchases->first()['ps_id'] ]);
                        } else {

                            // If not, run through default method

                            // Move the user's group
                            if (!\array_intersect($member->groups, $settings->blacklist)) {
                                \IPS\Db::i()->update('core_members', [ 'member_group_id' => $settings->group ], ["`member_id`=" . $member->member_id] );
                                \IPS\Db::i()->update( 'nexus_purchases', [ 'ps_extra'=>json_encode(['old_primary_group' => $member->group['g_id']]) ], [ "`ps_id`=" . $possiblePastPurchases->first()['ps_id'] ]);
                            }

                            // Set the expire date in resell data
                            $resellData['ExpireDate'] = date('m/d/y', time() + ( $resellData['Duration'] * 24 * 60 * 60 ));

                            // Create the purchase
                            \IPS\Db::i()->insert('nexus_purchases', [
                                'ps_member' => $member->member_id,
                                'ps_name' => $product->name,
                                'ps_active' => 1,
                                'ps_cancelled' => 0,
                                'ps_start' => time(),
                                'ps_expire' => time() + ( $resellData['Duration'] * 24 * 60 * 60 ),
                                'ps_renewals' => $resellData['Duration'],
                                'ps_renewal_price' => $resellData['Renewal'],
                                'ps_renewal_unit' => $resellData['Unit'],
                                'ps_app' => 'nexus',
                                'ps_type' => 'package',
                                'ps_item_id' => $resellData['Package'],
                                'ps_custom_fields' => [],
                                'ps_extra' => json_encode(['old_primary_group' => $member->group['g_id']]),
                                'ps_parent' => 0,
                                'ps_invoice_pending' => 0,
                                'ps_invoice_warning_sent' => 0,
                                'ps_original_invoice' => -1,
                                'ps_tax' => 1,
                                'ps_can_reactivate' => 1,
                                'ps_renewal_currency' => 'EUR',
                                'ps_show' => 1,
                                'ps_grace_period' => 0,
                                'ps_billing_agreement' => null,
                                'ps_fee' => null,
                                'ps_conv_parent' => 0,
                            ]);

                        }

                        \IPS\Db::i()->update('cbpanel_reseller_licensing', [
                            'member_id' => $member->member_id,
                            'redeemed' => 1,
                        ], ["`license_key`='{$val['cbpanel_redeem_key']}'"]);

                        \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'redeem', 'cbpanel' )->success( $val['cbpanel_redeem_key'], (object)$resellData );
                    } else {
                        $form->addMessage("Invalid Key!");
                        \IPS\Output::i()->output = $form;
                    }
                } else {
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

}