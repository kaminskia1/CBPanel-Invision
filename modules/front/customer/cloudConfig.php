<?php


namespace IPS\cbpanel\modules\front\customer;

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
    protected $userPackages = [];

    protected $userData;

    protected $tabs = [];

	public function execute() {
		parent::execute();
	}

	protected function manage() {
	    if (@\IPS\Member::loggedIn()->member_id == null) {
            \IPS\Output::i()->error( 'no_module_permission', '2S136/V', 403, '' );
        }
        $this->userData = \IPS\Member::loggedIn();
        $this->user();
        if (\count($this->userPackages) > 0) {
            \IPS\Output::i()->title = "Cloud Config";
            \IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css('cloud.css','cbpanel', 'front') );
            $this->tabs();

        }
	}

	protected function user() {
	    $compiledWhere = '';
        $where = [];
	    $time = time();
	    $d = \IPS\Db::i()->select('ps_item_id', 'nexus_purchases', ["`ps_active`=1 AND `ps_cancelled`=0 AND `ps_start`<{$time} AND `ps_expire`>{$time} AND `ps_member`={$this->userData->member_id}"]);
	    for ($i=0;$i<$d->count();$i++) {
	        $d->next();
	        if(!in_array($d->current(), $where));
	            array_push($where, $d->current());
        }
	    for ($i=0;$i<\count($where);$i++) {
	        if ($i != 0)
	            $compiledWhere .= " OR ";
	        $compiledWhere .= "`p_id`={$where[$i]}";
        }
	    $pData = \IPS\Db::i()->select('*', 'nexus_packages', [$compiledWhere]);
        for ($i=0;$i<$pData->count();$i++) {
            $pData->next();
            array_push($this->userPackages, $pData->current());
        }
        foreach ($this->userPackages as $package) {
            \IPS\Lang::saveCustom('cbpanel', "cbpanel_config_{$package['p_id']}", $package['p_name']);
            $this->tabs[$package['p_id']] = $package['p_id'];
        }
    }

    protected function tabs() {
        $activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->tabs ) ) ? \IPS\Request::i()->tab : $this->tabs[key($this->tabs)];

        // Display
        if ( \IPS\Request::i()->isAjax() ) {
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->box( $this->form() );
        } else {
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global', 'core')->tabs(
                $this->tabs,
                $activeTab,
                \IPS\Theme::i()->getTemplate( 'global', 'core' )->box( $this->form() ),
                \IPS\Http\Url::internal("app=cbpanel&module=customer&controller=cloudConfig")
            );
        }
    }

    protected function form() {
	    // Grab ID
        $pid = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->tabs ) ) ? \IPS\Request::i()->tab : $this->tabs[key($this->tabs)];
        // Grab Package from ID
        foreach ($this->userPackages as $package) {
            if ($package['p_id'] == $pid) {
                $active_package = $package;
                break;
            }
        }

        // If not configured, return false
        if ( !isset( json_decode($active_package['p_cbpanel_data'])->cloudForm ) ) {
            return "<div style='padding:20px'>This product does not have a cloud config.</div>";
        }

        // Register the lang keys
        foreach (json_decode($package['p_cbpanel_data'])->cloudLang as $k => $v) {
            \IPS\Lang::saveCustom('cbpanel', $k, $v);
        }

        // Retrieve past config
        $cf = "cb_config_{$pid}";
        $data = json_decode(\IPS\Db::i()->select('cbpanel_data', 'core_members', ["`member_id`={$this->userData->member_id}"])->first());
        if ( !isset($data) ) {
            $data = (object)[];
        }
        if ( !isset($data->$cf) ) {
            $data->$cf = (object)[];
        }

        // Build the form based on json
        $form = new \IPS\Helpers\Form;
        foreach (json_decode($package['p_cbpanel_data'])->cloudForm as $item => $type) {
            if (\substr($item, 0, 7) == "_HEADER") {
                $form->addHeader($type);
            } elseif (\substr($item,0,6) == "_BREAK") {
                $form->addSeparator();
            } else {
                $type[1] = (array)$type[1];
                $type[1]['options'] = (array)@$type[1]['options'];
                $class = "\IPS\Helpers\Form\\$type[0]";
                $name = "cbpanel_cc_{$item}";
                $form->add(new $class($name, (isset ($data->$cf->$name)) ? $data->$cf->$name : null, TRUE, (array)@$type[1]));
            }
        }
        $form->addHtml("<div style='width:100%;height:15px'></div>");

        // Process form inputs
        if ($values = $form->values()) {
            foreach ($values as $k => $v) {
                $data->$cf->$k = $v;
            }

            // Create a message and move to top
            if (\IPS\Db::i()->update('core_members', "`cbpanel_data`='" . json_encode($data) . "'", ["`member_id`={$this->userData->member_id}"])) {
                // Success
                $form->addHtml("<div class='cb_alert cb_success'>Config \"{$active_package['p_name']}\" has been saved!</div>");
            } else {
                // Failure
                $form->addHtml("<div class='cb_alert cb_fail'>There was an error saving your config for \"{$active_package['p_name']}\"! (Did you save twice?)</div>");
            }

            // Shift response message to top of elements
            array_unshift($form->elements[""], end($form->elements[""]));
            array_pop($form->elements[""]);

        }
        // Return the form
        return "<div style='padding:30px;'>{$form}</div>";

	}

}