<?php


namespace IPS\cbpanel\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * @brief	product Config Requests API
 */
class _cloudapp extends \IPS\Content\Api\ItemController
{
    protected $member;

    protected $error;

    /**
     * GET /cbpanel/cloudapp
     * Get basic information about the authorized user
     *
     * @param \IPS\Request::i()->method
     * @param \IPS\Request::i()->username
     * @param \IPS\Request::i()->password
     *
     * @apimemberonly
     * @return    object
     */
    public function GETindex() {

        $this->error = [
            'credentials' => (object)['errorCode'=>'CA1', "errorMessage"=>"The provided credentials are invalid."],
            'method' => (object)['errorCode' => 'CA2', 'errorMessage' => 'The supplied method is invalid.'],
            'parameters' => (object)['errorCode' => 'CA3', 'errorMessage' => 'Provided parameters are invalid.'],
        ];

        switch (\IPS\Request::i()->method) {
            case 'auth':
                return new \IPS\Api\Response( 200, $this->auth());
            case 'grabSettings':
                return new \IPS\Api\Response( 200, $this->grabSettings());
            case 'grabSubscriptions':
                return new \IPS\Api\Response( 200, $this->grabSubscriptions());
            case 'updateSettings':
                return new \IPS\Api\Response( 200, $this->updateSettings());
            default:
                return new \IPS\Api\Response( 200, $this->error['method']);
        }
    }

    /**
     * Check if provided credentials are valid, set member object to said member if true
     *
     * @param \IPS\Request::i()->username
     * @param \IPS\Request::i()->password
     *
     * @return bool
     */
    protected function login() {
        if ( isset( \IPS\Request::i()->username ) && isset( \IPS\Request::i()->password ) && !\filter_var( \IPS\Request::i()->username, FILTER_VALIDATE_EMAIL ) ) {
            if (\IPS\Db::i()->select('member_id', 'core_members', ["`name`='" . htmlspecialchars(\IPS\Request::i()->username) . "'"])->count() == 1) {
                $login = new \IPS\Login();
                $member = \IPS\Member::load( \IPS\Db::i()->select( 'member_id', 'core_members', ["`name`='" . \IPS\Request::i()->username . "'"] )->first() );
                if ( $login->usernamePasswordMethods()[1]->authenticatePasswordForMember( $member, \IPS\Request::i()->password ) ) {
                    $this->member = $member;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * GET
     *
     * @return array
     */
    protected function grabSubscriptions() {
        if ($this->login()) {
            $compiledWhere = '';
            $where = [];
            $time = time();
            $d = \IPS\Db::i()->select('ps_item_id', 'nexus_purchases', ["`ps_active`=1 AND `ps_cancelled`=0 AND `ps_start`<{$time} AND `ps_expire`>{$time} AND `ps_member`={$this->member->member_id}"]);
            for ($i = 0; $i < $d->count(); $i++) {
                $d->next();
                if (!in_array($d->current(), $where)) ;
                array_push($where, $d->current());
            }
            for ($i = 0; $i < count($where); $i++) {
                if ($i != 0)
                    $compiledWhere .= " OR ";
                $compiledWhere .= "`p_id`={$where[$i]}";
            }
            $packages = [];
            $pData = \IPS\Db::i()->select('`p_id`,`p_name`', 'nexus_packages', [$compiledWhere]);
            for ($i = 0; $i < $pData->count(); $i++) {
                $pData->next();
                array_push($packages, $pData->current());
            }
            return $packages;
        } else {
            return $this->error['credentials'];
        }
    }

    /**
     * GET
     *
     * @return null|object
     */
    protected function grabSettings() {
        if ($this->login()) {
            return json_decode(\IPS\Db::i()->select('cbpanel_data', 'core_members', ["`member_id`={$this->member->member_id}"])->first());
        } else {
            return $this->error['credentials'];
        }
    }

    /**
     * POST
     *
     * @param \IPS\Request::i()->product_id
     * @param \IPS\Request::i()->data
     *
     * @return object
     */
    protected function updateSettings() {
        if ($this->login()) {
            // Commented because very confusing
            if (isset(\IPS\Request::i()->product_id) && isset(\IPS\Request::i()->data)) {
                // Set $pid to provided product id
                $pid = \IPS\Request::i()->product_id;

                // Check if product id is valid
                if (\IPS\Db::i()->select('`p_id`', 'nexus_packages', ["`p_id`={$pid}"])->count() == 1) {
                    // Append config tag
                    $name = 'cb_config_' . $pid;
                    // Grab user settings
                    $settings = @$this->grabSettings();
                    // Set config to settings part
                    $config = $settings->$name;
                    // Grab new data
                    $data = json_decode(\IPS\Request::i()->data);
                    // Grab master form config
                    $configMaster = json_decode(\IPS\Db::i()->select('p_cbpanel_data', 'nexus_packages', ["`p_id`={$pid}"])->first());
                    // Check that data is an object, that the config exists, and that the selected product has a cloud config
                    if ((@gettype($data) == "object") && (@gettype($config) == "object") && (@gettype($configMaster->cloudForm) == "object")) {
                        // Convert object into dictionary array, for in_array. Also set to the config
                        $configMaster = $configMaster->cloudForm;
                        // Filter through all new data
                        foreach ($data as $k => $v) {
                            // Strip away cbpanel_cc_ on the tag
                            $tag = mb_substr($k, 11);
                            // Check if the tag exists on master config
                            if (@gettype($configMaster->$tag) != 'NULL') {
                                // Tag exists, now check that the data types are same
                                $dataTypes = array(
                                    "YesNo" => "boolean",
                                    "Number" => "integer",
                                    "Select" => "string",
                                    "Color" => "string",
                                );
                                // If type of provided value is same as the real value, approve it
                                if (gettype($v) == $dataTypes[$configMaster->$tag[0]]) {
                                    // Save provided value to user's config
                                    $config->$k = $v;
                                }
                            }
                        }
                        // Update config in database
                        if (\IPS\Db::i()->update('core_members', ["cbpanel_data" => json_encode($settings)], ["member_id={$this->member->member_id}"])) {
                            // Query sucessful
                            $settings->_APP_SQL = true;
                        } else {
                            // Query failed, most likely because nothing changed
                            $settings->_APP_SQL = false;
                        }
                        // Set settings to config
                        $settings->$name = $config;
                        // Return settings for api output
                        return $settings;
                    } else {
                        return $this->error['parameters'];
                    }
                } else {
                    return $this->error['parameters'];
                }
            } else {
                return $this->error['parameters'];
            }
        } else {
            return $this->error['credentials'];
        }
    }

    /**
     * GET
     *
     * return object
     */
    protected function auth() {
        if ($this->login()) {
            return (object)[
                'id' => $this->member->member_id,
                'username' => $this->member->name,
                'email' => $this->member->email,
                'photoURL' => $this->member->get_photo(),
                'settings' => $this->grabSettings(),
            ];
        } else {
            return $this->error['credentials'];
        }
    }

}