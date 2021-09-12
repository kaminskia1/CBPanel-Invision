<?php


namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{

    protected $settingsData;

    protected $redirect = false;

    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('settings_manage');
        parent::execute();
    }

    /**
     * ...
     *
     * @return    void
     */
    protected function manage()
    {
        // Basic outputs
        \IPS\Output::i()->cssFiles = array_merge(\IPS\Output::i()->cssFiles, \IPS\Theme::i()->css('settings.css', 'cbpanel', 'admin'));
        \IPS\Output::i()->title = "Settings";

        // Grab old data
        $this->settingsData = json_decode(\IPS\Db::i()->select('data', 'cbpanel_settings')->first());

        /**
         * Start card processing
         */
        $this->activation();
        $this->hwid();
        $this->reseller();
        /**
         * End card processing
         */

        // Save the data
        \IPS\Db::i()->update('cbpanel_settings', "`data`='" . json_encode($this->settingsData) . "'");

        // Redirect if necessary
        $this->redirect ? \IPS\Output::i()->redirect(\IPS\Http\Url::Internal('app=cbpanel&module=cbpanel&controller=settings')) : null;

    }

    // Create new methods with the same name as the 'do' parameter which should execute it

    protected function activation()
    {

        $this->settingsData->activation = $this->settingsData->activation ?? (object)[];
        $this->settingsData->activation->group = $this->settingsData->activation->group ?? null;
        $this->settingsData->activation->blacklist = $this->settingsData->activation->blacklist ?? [];

        if (\IPS\Request::i()->form == true) {

            if (\IPS\Request::i()->type == 'activation') {

                // Pre-processing
                    $groups = [];
                    foreach (\IPS\Member\Group::Groups() as $k => $v) {
                        $groups[$k] = $v->name;
                    }
                // End Pre-processing

                // Start Form
                    $form = new \IPS\Helpers\Form('cbpanel_settings_form');
                    $form->class = "ipsForm_vertical";
                    $form->add(new \IPS\Helpers\Form\Select('cbpanel_settings_customer_group', $this->settingsData->activation->group ?? null, true, ['options' => $groups]));
                    $form->add(new \IPS\Helpers\Form\Select('cbpanel_settings_customer_blacklist', $this->settingsData->activation->blacklist, false, ['options' => $groups, 'multiple' => true]));

                // End Form

                // Add Keys
                \IPS\Lang::saveCustom('cbpanel', 'cbpanel_settings_customer_group', 'Customer Group');
                \IPS\Lang::saveCustom('cbpanel', 'cbpanel_settings_customer_blacklist', 'Ignored Groups');
                // End Add Keys

                if ($val = $form->values()) {

                $black = [];
                foreach ($val['cbpanel_settings_customer_blacklist'] as $k => $v) {
                    array_push($black, $v);
                }


                    $this->settingsData->activation->group = $val['cbpanel_settings_customer_group'];
                    $this->settingsData->activation->blacklist = $black;
                    $this->redirect = true;
                } else {
                    \IPS\Output::i()->output = $form;
                }

            }

            // Return null because form not called
            return null;
        }

        // Output card
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('settings', 'cbpanel')->card('Key Redemption', \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=settings&form=true&type=activation'));

    }

    protected function hwid()
    {
        $this->settingsData->hwid = $this->settingsData->hwid ?? (object)[];
        $this->settingsData->hwid->duration = $this->settingsData->hwid->duration ?? null;

        if (\IPS\Request::i()->form == true) {

            if (\IPS\Request::i()->type == 'hwid') {

                // Start Form
                    $form = new \IPS\Helpers\Form('cbpanel_settings_form');
                    $form->class = "ipsForm_vertical";
                    $form->add( new \IPS\Helpers\Form\Number( 'cbpanel_settings_hwid_duration', $this->settingsData->hwid->duration ?? 0, true ) );
                // End Form

                // Add Keys
                    \IPS\Lang::saveCustom('cbpanel', 'cbpanel_settings_hwid_duration', 'HWID Reset Delay');
                // End Add Keys

                if ($val = $form->values()) {
                    $this->settingsData->hwid->duration = $val['cbpanel_settings_hwid_duration'];
                    $this->redirect = true;
                } else {
                    \IPS\Output::i()->output = $form;
                }

            }

            // Return null because form not called
            return null;
        }

        // Output card
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('settings', 'cbpanel')->card('Hardware ID', \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=settings&form=true&type=hwid'));

    }

    protected function reseller()
    {

        // Presets
        $this->settingsData->reseller = $this->settingsData->reseller ?? (object)[];
        $this->settingsData->reseller->group = $this->settingsData->reseller->group ?? null;
        $this->settingsData->reseller->packages = $this->settingsData->reseller->packages ?? (object)[];

        if (\IPS\Request::i()->form == true) {

            if (\IPS\Request::i()->type == 'reseller') {

                // Pre-processing
                    $groups = [];
                    foreach (\IPS\Member\Group::Groups() as $k => $v) {
                        $groups[$k] = $v->name;
                    }

                    $products = (object)[
                        'kv' => [],
                        'selected' => [],
                    ];

                    foreach (\IPS\Db::i()->select('p_id,p_name', 'nexus_packages') as $pkg) {

                        // Push to selected if selected
                        \in_array($pkg['p_id'], (array)$this->settingsData->reseller->packages) ? array_push($products->selected, $pkg['p_id']) : null;

                        // Push to key-val
                        $products->kv[$pkg['p_id']] = $pkg['p_name'];
                    }
                // End Pre-processing


                // Start Form
                    $form = new \IPS\Helpers\Form('cbpanel_settings_form');
                    $form->class = "ipsForm_vertical";
                    $form->add(new \IPS\Helpers\Form\Select('cbpanel_settings_reseller_group', $this->settingsData->reseller->group ?? null, true, ['options' => $groups]));
                    $form->add(new \IPS\Helpers\Form\Select('cbpanel_settings_reseller_products', $products->selected, false, ['options' => $products->kv, 'multiple' => true]));
                // End form

                // Add Keys
                    \IPS\Lang::saveCustom('cbpanel', 'cbpanel_settings_reseller_group', 'Reseller Group');
                    \IPS\Lang::saveCustom('cbpanel', 'cbpanel_settings_reseller_products', 'Reseller Products');
                // End Add Keys

                if ($val = $form->values()) {
                    $this->settingsData->reseller->group = $val['cbpanel_settings_reseller_group'];
                    $this->settingsData->reseller->packages = $val['cbpanel_settings_reseller_products'];
                    $this->redirect = true;
                } else {
                    \IPS\Output::i()->output = $form;
                }

            }

            // Return null because form not called
            return null;
        }

        // Output card
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('settings', 'cbpanel')->card('Reseller', \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=settings&form=true&type=reseller'));

    }
}