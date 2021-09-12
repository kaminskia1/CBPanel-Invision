<?php


namespace IPS\cbpanel\modules\front\tester;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * bugReport
 */
class _bugReport extends \IPS\Dispatcher\Controller
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
        if ($this->checkGroup()) {
            $this->form();
        } else {
            \IPS\Output::i()->redirect("https://localhost/");
        }
    }

    protected function checkGroup() {
        $member = \IPS\Member::loggedIn();
        if (!is_null($member->member_id)) {
        }
        return true;
    }

    protected function products() {
        $p = \IPS\Db::i()->select('*', "nexus_packages");
        for ($i=0;$i<$p->count();$i++) {
            $p->next();
            $tmp = $p->current();

            $pre = [];

            $tmp['p_cbpanel_data'] = json_decode($tmp['p_cbpanel_data']);
            if ($tmp['p_cbpanel_data']->isproduct) {
                $pre[$tmp['p_id']] = $tmp['p_name'];
            }
        }
        return $pre;
    }

    protected function userPCs(&$form) {
        $dataQuery = json_decode(\IPS\Db::i()->select('cbpanel_data', 'core_members', ["`member_id`=" . \IPS\Member::loggedIn()->member_id]));
        if (!is_null(@$dataQuery->bug_pc)) {
            $pcs = [];
            foreach ($dataQuery->bug_pc as $pc) {
                array_push($pcs, [$pc->id => $pc->name]);
            }


            $form->add( new \IPS\Helpers\Form\Select('cbpanel_bug_pc', 0, $pcs) );
        } else {
            $form->addHtml("<div style='background-color: rgba(	213, 5, 5, .5);margin-bottom:15px;border-radius:3px' class='ipsPad'>You do not have any PC's added! Please set one up <b><a href='" . \IPS\Http\Url::internal("/manage-pc/") . "'>here</a></b> before submitting a report</div>");
        }
    }

    protected function form() {

        \IPS\Output::i()->output = "<div class=\"ipsPageHeader ipsClearfix ipsSpacer_bottom\">
                                        <h1 class=\"ipsType_pageTitle\">Report a Bug</h1>
                                    </div>";

        $form = new \IPS\Helpers\Form;
        $form->class = "ipsForm_vertical";
        $this->userPCs($form);
        $form->add( new \IPS\Helpers\Form\Select('cbpanel_bug_product', 0, true, ['options'=>$this->products()]) );
        $form->add( new \IPS\Helpers\Form\Date('cbpanel_bug_time', null, true, ['time'=>true]) );
        $form->add( new \IPS\Helpers\Form\Timezone('cbpanel_bug_timezone', null, true) );
        $form->add( new \IPS\Helpers\Form\TextArea('cbpanel_bug_description', null, true) );
        $form->add( new \IPS\Helpers\Form\Text('cbpanel_bug_create', null, true) );
        $form->add( new \IPS\Helpers\Form\Upload('cbpanel_bug_file', null, false, ['storageExtension'=>"BugFile", "temporary"=>FALSE, 'maxFileSize'=>null, 'multiple'=>true]) );

        if ($val = $form->values()) {
            if (isset($val['cbpanel_bug_pc'])) {

                $files = [];
                foreach ($val['cbpanel_bug_file'] as $item) {
                    array_push($files, "{$item->baseUrl()}/{$item->container}/{$item->filename}");
                }
                json_encode($files);

                \IPS\Db::i()->insert('cbpanel_bug_report', [
                    'cbpanel_bug_pc' => $val['cbpanel_bug_pc'],
                    'cbpanel_bug_product' => $val['cbpanel_bug_product'],
                    'cbpanel_bug_time' => $val['cbpanel_bug_time']->getTimestamp(),
                    'cbpanel_submit_time' => time(),
                    'cbpanel_bug_timezone' => $val['cbpanel_bug_timezone'],
                    'cbpanel_bug_description' => $val['cbpanel_bug_description'],
                    'cbpanel_bug_create' => $val['cbpanel_bug_create'],
                    'cbpanel_bug_file' => $files


                ]);
            } else {
                \IPS\Output::i()->output = "<div class=\"ipsPageHeader ipsClearfix ipsSpacer_bottom\">
                                                <h1 class=\"ipsType_pageTitle\">Error</h1>
                                            </div>
                                            <div style='background-color: rgba(	213, 5, 5, .5);margin-bottom:15px;border-radius:3px' class='ipsPad'>
                                                You do not have any PC's added! Please set one up <b><a href='" . \IPS\Http\Url::internal("/manage-pc/") . "'>here</a></b> before submitting a report
                                            </div>";
            }

        } else {
            \IPS\Output::i()->output .= (string)$form;
        }
    }
}