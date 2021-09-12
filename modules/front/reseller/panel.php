<?php


namespace IPS\cbpanel\modules\front\reseller;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * panel
 */
class _panel extends \IPS\Dispatcher\Controller
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
        if ( \in_array(json_decode(\IPS\Db::i()->select("data", 'cbpanel_settings')->first())->reseller->group ?? -1, \IPS\Member::loggedIn()->groups) ) {

            $res = (object)[
                'active' => (object)[],
                'used' => (object)[],
            ];

            $member = \IPS\Member::loggedIn();

            foreach ( \IPS\Db::i()->select('*','cbpanel_reseller_licensing', ["`reseller_member_id`=" . $member->member_id]) as $row ) {
                $id = $row['id'];
                $row['reference_package_name'] = \IPS\nexus\Package::load( $row['reference_package'] )->name;
                if ($row['redeemed'] == 0) {
                    $res->active->$id = (object)$row;
                } else {
                    $res->used->$id = (object)$row;
                }
            }


            \IPS\Output::i()->title = "Reseller Panel";
            \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('rcp', 'cbpanel')->panel( $res, \IPS\Http\Url::internal('app=cbpanel&module=reseller&controller=panel&do=reveal&id=' ) );

        } else {
            \IPS\Output::i()->error('The page you are trying to access is not available for your account.', '2S333/1', '401');
        }
    }


    protected function reveal()
    {
        // Verify that is reseller
        if (\in_array(json_decode(\IPS\Db::i()->select("data", 'cbpanel_settings')->first())->reseller->group ?? -1, \IPS\Member::loggedIn()->groups)) {

            // Verify that reseller owns the key
            $data = (object)\IPS\Db::i()->select('*', 'cbpanel_reseller_licensing', ["`id`=" . (int)\IPS\Request::i()->id])->first();
            if ( \IPS\Member::loggedIn()->member_id == $data->reseller_member_id ) {

                \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'rcp', 'cbpanel' )->key( $data->license_key );
            } else {
                \IPS\Output::i()->error('The page you are trying to access is not available for your account.', '2S333/1', '401');
            }
        } else {
            \IPS\Output::i()->error('The page you are trying to access is not available for your account.', '2S333/1', '401');
        }
    }
}

