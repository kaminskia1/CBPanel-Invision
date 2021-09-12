<?php


\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';
\IPS\Session\Front::i();

\IPS\Output::i()->output = "Redirecting...";

\IPS\IPS::$PSR0Namespaces['Stripe'] = \IPS\ROOT_PATH . '/applications/cbpanel/sources/Stripe';
\Stripe\Stripe::setApiKey('apikey');
\Stripe\Stripe::setCABundlePath(\IPS\ROOT_PATH . '/applications/cbpanel/sources/Stripe/ca-certificates.crt');

if (isset(\IPS\Request::i()->do) && \in_array(\IPS\Request::i()->do, ['startSession','finishSession']) && (\IPS\Request::i()->token != null) && (\strlen(\IPS\Request::i()->token) == 128)) {

    $row = \IPS\Db::i()->select("*", 'cbpanel_stripe_checkout_tokens', ["`token`='" . \IPS\Request::i()->token . "'"]);

    // Check if entry exists
    if ($row->count() == 1) {

        $row = $row->first();

        if ( (\IPS\Request::i()->do == 'startSession') && (\IPS\nexus\Invoice::load($row['invoice_id'])->status == \IPS\nexus\Transaction::STATUS_PENDING)) {
            $row = \IPS\Db::i()->select("*", 'cbpanel_stripe_checkout_tokens', ["`token`='" . \IPS\Request::i()->token . "'"]);

            // Check if entry exists
            if ($row->count() == 1) {
                $row = $row->first();

                echo '
                    <noscript>JavaScript must be enabled!</noscript>
                    <p>Redirecting...</p>
                    <script src="https://js.stripe.com/v3/"></script>
                    <script>
                        var stripe = Stripe("' . $row['public_key'] . '");
                        
                        stripe.redirectToCheckout({
                          sessionId: "' . $row['checkout_session_id'] . '",
                        }).then(function (r) {
                            document.getElementsByTagName("body")[0].innerHTML = "Error:" + r.error.message
                        });
                    </script>';
                exit;
            }

        } else if ((\IPS\Request::i()->do == 'finishSession')) {

            $transaction = \IPS\nexus\Transaction::load( $row['transaction_id'] );

            if (\Stripe\PaymentIntent::retrieve( $row['payment_intent'] )->status == 'succeeded') {

                // Invalidate the token
                \IPS\Db::i()->update('cbpanel_stripe_checkout_tokens', [
                    'completed' => 1,
                ], ["`token`='" . \IPS\Request::i()->token . "'"]);

                if ( $transaction->status == \IPS\nexus\Transaction::STATUS_PENDING ) {
                    // Update to gateway pending in event webhook has not gone through yet to prevent user from paying multiple times
                    $transaction->status = \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING;
                    $transaction->save();
                }
            }
            \IPS\Output::i()->redirect($transaction->url());
        }
    }

}




//
//
//
//    /* Load Source */
//    try
//    {
//        $transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->nexusTransactionId );
//        $source = $transaction->method->api( 'sources/' . preg_replace( '/[^A-Z0-9_]/i', '', \IPS\Request::i()->source ), NULL, 'get' );
//        if ( $source['client_secret'] != \IPS\Request::i()->client_secret )
//        {
//            throw new \Exception;
//        }
//    }
//    catch ( \Exception $e )
//    {
//        \IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=checkout&controller=checkout&do=transaction&id=&t=" . \IPS\Request::i()->nexusTransactionId, 'front', 'nexus_checkout', \IPS\Settings::i()->nexus_https ) );
//    }
//
//    /* If we're a guest, but the transaction belongs to a member, that's because the webhook has
//        processed the transaction and created an account - so we need to log the newly created
//        member in. This is okay to do because we've checked client_secret is correct, meaning
//        we know this is a genuine redirect back from Stripe after payment of this transaction */
//    if ( !\IPS\Member::loggedIn()->member_id and $transaction->member->member_id )
//    {
//        \IPS\Session::i()->setMember( $transaction->member );
//        \IPS\Member\Device::loadOrCreate( $transaction->member, FALSE )->updateAfterAuthentication( NULL );
//    }
//
//    /* And then send them on */
//    if ( $source['status'] === 'failed' )
//    {
//        \IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl() );
//    }
//    else
//    {
//        \IPS\Output::i()->redirect( $transaction->url()->setQueryString( 'pending', 1 ) );
//    }
//}

