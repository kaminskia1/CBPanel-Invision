<?php


namespace IPS\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * CustomRedirect Gateway
 */
class _CustomRedirect extends \IPS\nexus\Gateway
{
    /* !Features */

    const SUPPORTS_REFUNDS = FALSE;
    const SUPPORTS_PARTIAL_REFUNDS = FALSE;


    /**
     * Admin can manually charge using this gateway?
     *
     * @param \IPS\nexus\Customer $customer The customer we're wanting to charge
     * @return    bool
     */
    public function canAdminCharge(\IPS\nexus\Customer $customer)
    {
        return FALSE;
    }

    /* !Payment Gateway */

    /**
     * Should the submit button show when this payment method is shown?
     *
     * @return    bool
     */
    public function showSubmitButton()
    {
        return FALSE;
    }


    public function auth(\IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array(), $source = NULL)
    {
        throw new \RuntimeException("Custom redirect attempted");
    }

    /* !ACP Configuration */

    /**
     * [Node] Add/Edit Form
     *
     * @param \IPS\Helpers\Form $form The form
     * @return    void
     */
    public function form(&$form)
    {
        $form->addHeader('customredirect_basic_settings');
        $form->add(new \IPS\Helpers\Form\Translatable('paymethod_name', NULL, TRUE, array('app' => 'nexus', 'key' => "nexus_paymethod_{$this->id}")));
        $form->add(new \IPS\Helpers\Form\Select('paymethod_countries', ($this->id and $this->countries !== '*') ? explode(',', $this->countries) : '*', FALSE, array('options' => array_map(function ($val) {
            return "country-{$val}";
        }, array_combine(\IPS\GeoLocation::$countries, \IPS\GeoLocation::$countries)), 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'no_restriction')));
        $this->settings($form);
    }

    /**
     * Settings
     *
     * @param \IPS\Helpers\Form $form The form
     * @return    void
     */
    public function settings(&$form)
    {
        $settings = json_decode($this->settings, TRUE);
        $form->addHeader('customredirect_redirect_link');
        $form->add(new \IPS\Helpers\Form\Text('customredirect_redirect_url', $settings ? $settings['redirect_url'] : NULL, TRUE));
    }

    /**
     * Test Settings
     *
     * @param array $settings Settings
     * @return    bool
     * @throws    \InvalidArgumentException
     */
    public function testSettings($settings)
    {
        return $settings;
    }


    /**
     * Payment Screen Fields
     *
     * @param	\IPS\nexus\Invoice		$invoice	Invoice
     * @param	\IPS\nexus\Money		$amount		The amount to pay now
     * @param	\IPS\nexus\Customer		$member		The member the payment screen is for (if in the ACP charging to a member's card) or NULL for currently logged in member
     * @param	array					$recurrings	Details about recurring costs
     * @param	bool					$type		'checkout' means the cusotmer is doing this on the normal checkout screen, 'admin' means the admin is doing this in the ACP, 'card' means the user is just adding a card
     * @return	array
     */
    public function paymentScreen( \IPS\nexus\Invoice $invoice, \IPS\nexus\Money $amount, \IPS\nexus\Customer $member = NULL, $recurrings = array(), $type = 'checkout' )
    {
        return array(
            new \IPS\Helpers\Form\Custom(
                "",
                null,
                false,
                array(
                    'getHtml'=> function($s)
                    {
                        return "<h3>Please click <a href='" . $s->options['custom_url'] . "'>here</a> for more information</h3>";
                    },
                    'custom_url' => json_decode($this->settings, true)['redirect_url'],
                )
            )
        );
    }

}