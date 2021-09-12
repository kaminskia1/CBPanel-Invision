<?php


namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * cloudConfig
 */
class _cloudConfig extends \IPS\Dispatcher\Controller
{

    protected $packages = [];

    protected $tabs = [];
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
    protected function manage() {
        $this->packages();
        if (\count($this->packages) > 0) {
            $this->render();
        }
    }

    protected function packages() {
        $pData = \IPS\Db::i()->select('*', 'nexus_packages');
        for ($i=0;$i<$pData->count();$i++) {
            $pData->next();
            array_push($this->packages, $pData->current());
        }
        foreach ($this->packages as $package) {
            \IPS\Lang::saveCustom('cbpanel_package_', "{$package['p_id']}", $package['p_name']);
            $this->tabs[$package['p_id']] = "{$package['p_id']}";
        }
    }

    protected function tabs() {
        $activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->tabs ) ) ? \IPS\Request::i()->tab : $this->tabs[key($this->tabs)];
        // Display
        if ( \IPS\Request::i()->isAjax() ) {
            return \IPS\Theme::i()->getTemplate( 'global', 'core' )->box( $this->form() );
        } else {
            return \IPS\Theme::i()->getTemplate('global', 'core')->tabs(
                $this->tabs,
                $activeTab,
                $this->form(),
                \IPS\Http\Url::internal("app=cbpanel&module=cbpanel&controller=cloudConfig")
            );
        }
    }

    protected function form() {
        // Grab ID
        $pid = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->tabs ) ) ? \IPS\Request::i()->tab : $this->tabs[key($this->tabs)];

        // Grab current data
        $data = json_decode( \IPS\Db::i()->select('p_cbpanel_data','nexus_packages',["`p_id`={$pid}"])->first() );
        if ($data == null) {
            $data = (object)[];
        }

        // Check if old JSON exists
        if (@$data->cloudForm != null) {
            $json = json_encode($data->cloudForm);
        } else {
            $json = "";
        }

        // Check if old lang exists
        $lang = [];
        if (@$data->cloudLang != null) {
            foreach ($data->cloudLang as $k => $v) {
                array_push($lang, "{$k}|{$v}");
            }
        }

        // Start building the form
        $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Stack('cbpanel_admincc_lang', $lang, FALSE));
        $form->add( new \IPS\Helpers\Form\TextArea('cbpanel_admincc_json', $json, FALSE, ['rows'=>30]) );

        // Process form inputs
        if ($values = $form->values()) {
            $keyArray = [];
            if (\count($values['cbpanel_admincc_lang']) > 0) {
                foreach ($values['cbpanel_admincc_lang'] as $langKey) {
                    $exploded = explode('|',$langKey); // Key|Value
                    if (\count($exploded) == 2) {
                        $keyArray[$exploded[0]] = $exploded[1];
                    }
                }
                $keyArray = (object)$keyArray;
            }
            $data->cloudLang = $keyArray;
            $data->cloudForm = json_decode($values['cbpanel_admincc_json']);
            \IPS\Db::i()->update('nexus_packages', "`p_cbpanel_data`='" . json_encode($data) . "'", ["`p_id`={$pid}"]);
        }
        return $form;

    }

    protected function render() {
        \IPS\Output::i()->title = "Cloud Config Manager";
        \IPS\Output::i()->output = $this->tabs();
    }
}
