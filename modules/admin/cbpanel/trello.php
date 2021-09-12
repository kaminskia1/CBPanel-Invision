<?php


namespace IPS\cbpanel\modules\admin\cbpanel;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * trello
 */
class _trello extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'trello_manage' );
		parent::execute();
	}

	/**
	 * ...
	 *
	 * @return	void
	 */
	protected function manage()
	{
	    if ( @isset($_GET['form']) ) {
	        switch($_GET['form']) {
                case 'newCard':
                    $this->newCard();
                    break;
                case 'newColumn':
                    $this->newColumn();
                    break;
                case 'editCard':
                    if (!isset($_GET['id'])) {
                        goto exitForm;
                    }
                    $this->editCard();
                    break;
                case 'removeColumn':
                    if (!isset($_GET['name'])) {
                        goto exitForm;
                    }
                    $this->removeColumn();
                    break;
                case 'removeCard':
                    if (!isset($_GET['id'])) {
                        goto exitForm;
                    }
                    $this->removeCard();
                    break;
                default:
                    goto exitForm;
            }
        } else {
	        exitForm:
            $this->setup();
            $this->render( $this->data() );
        }
	}

	// Forms
    protected function newCard() {
	    $columns = (array)json_decode( \IPS\Db::i()->select('data', 'cbpanel_trello_columns')->first() );

	    $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Text( 'trello_newcard_title', '', TRUE ) );
        $form->add( new \IPS\Helpers\Form\TextArea('trello_newcard_desc', '', TRUE));
        $form->add( new \IPS\Helpers\Form\Select('trello_newcard_column', $columns[0], TRUE, ['options'=>$columns]));
        $form->add( new \IPS\Helpers\Form\Text('trello_newcard_collab', '', TRUE));

	    if ($values = $form->values()) {
            $values['trello_newcard_collab'] = explode(" ", htmlspecialchars($_POST['trello_newcard_collab']));
            $data = (object)[
                'author' => \IPS\Member::loggedIn()->name,
                'assigned' => $values['trello_newcard_collab'],
                'title' => $values['trello_newcard_title'],
                'message' => $values['trello_newcard_desc'],
                'column' => $this->idToColumn($values['trello_newcard_column']),
            ];
            \IPS\Db::i()->insert('cbpanel_trello_events', ['data'=>json_encode($data)] );

	        // Redirect
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=trello&success=true') );
        } else {
            \IPS\Output::i()->output = $form;
        }
    }


    protected function newColumn() {
	    $form = new \IPS\Helpers\Form;
	    $form->add( new \IPS\Helpers\Form\Text('trello_newcolumn_title', '', TRUE) );
        if ($values = $form->values()) {
            $oldColumns = json_decode(\IPS\Db::i()->select('data','cbpanel_trello_columns')->first());
            array_push($oldColumns, $values['trello_newcolumn_title']);
            \IPS\Db::i()->update('cbpanel_trello_columns', "`data`='" . json_encode($oldColumns) . "'");
            // Process and redirect
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=trello&success=true') );

        } else {
            \IPS\Output::i()->output = $form;
        }
    }

    protected function editCard() {
        $columns = json_decode( \IPS\Db::i()->select('data', 'cbpanel_trello_columns')->first() );
	    $eventData = json_decode( \IPS\Db::i()->select('data', 'cbpanel_trello_events', "`id`='" . htmlspecialchars($_GET['id']) . "'")->first() );
	    $assigned = '';
	    for($i=0;$i<count($eventData->assigned);$i++) {
            if ($i != 0) {
                $assigned .= " ";
            }
            $assigned .= $eventData->assigned[$i];

	    }
	    $form = new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Text('trello_editcard_title', $eventData->title, TRUE ) );
        $form->add( new \IPS\Helpers\Form\TextArea('trello_editcard_desc', $eventData->message, TRUE ) );
        $form->add( new \IPS\Helpers\Form\Select('trello_editcard_column', $columns[0], TRUE, ['options'=>$columns]) );
        $form->add( new \IPS\Helpers\Form\Text('trello_editcard_collab', $assigned, TRUE ) );

        if ($values = $form->values()) {
            // Process and redirect
            \IPS\Output::i()->output = "Redirecting...";
            $values['trello_editcard_collab'] = explode(" ", $values['trello_editcard_collab']);
            $newData = (object)[
                 'author' => $eventData->author,
                 'assigned' => $values['trello_editcard_collab'],
                 'title' => $values['trello_editcard_title'],
                 'message' => $values['trello_editcard_desc'],
                 'column' => $this->idToColumn($values['trello_editcard_column']),
            ];
            \IPS\Db::i()->update('cbpanel_trello_events', "`data`='" . json_encode($newData) . "'", "`id`=" . \filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) );
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=trello&success=true') );
        } else {
            \IPS\Output::i()->output .= $form;
        }

    }

    protected function removeColumn() {
        if ($_GET['wasConfirmed'] == (1 || "1")) {
            \IPS\Output::i()->output = "Redirecting...";
            $column = \filter_var(@$_GET['name'], FILTER_SANITIZE_STRING);

            // Grab all event data
            $eventData = \IPS\Db::i()->select("*", 'cbpanel_trello_events');
            $arr = [];
            for ($i=0;$i<$eventData->count();$i++) {
                $eventData->next();
                array_push($arr, $eventData->current());
            }
            // Mark events for removal and remove
            foreach ($arr as $item) {
                if (json_decode($item['data'])->column == $column) {
                    \IPS\Db::i()->delete("cbpanel_trello_events", "`id`=" . $item['id']);
                }
            }

            // Remove column
            $columnData = (array)json_decode(\IPS\Db::i()->select('data', 'cbpanel_trello_columns')->first());
            if (($id = array_search($column, $columnData)) !== false) {
                unset($columnData[$id]);
            }
            \IPS\Db::i()->update('cbpanel_trello_columns', "`data`='" . json_encode($columnData) . "'");
            \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=trello') );
        }
    }

    protected function removeCard() {
        if (@$_GET['wasConfirmed'] == (1 || "1")) {
            \IPS\Output::i()->output = "Redirecting...";
            $id = \filter_var(@$_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            \IPS\Db::i()->delete('cbpanel_trello_events', "`id`='" . $id . "'");
        }
        \IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=cbpanel&module=cbpanel&controller=trello') );
    }

    // Rendering Function

    protected function render($data) {
	    \IPS\Output::i()->title = "Trello";
	    \IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'trello.css', 'cbpanel', 'admin' ) );
	    \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('trello', 'cbpanel')->header();
        if (@count($data->columns) > 0) {
            foreach ($data->columns as $column) {
                $arr = $this->filter($column, $data->events);
                \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('trello', 'cbpanel')->column($column, $arr);
            }
        } else {
            \IPS\Output::i()->output .= "<div class='error'>No columns to show!</div>";
        }
        \IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('trello', 'cbpanel')->footer();
    }



    protected function filter($needle, $array) {
	    $newArr = [];
        foreach ($array as $event) {
            if (@$event->column == $needle) {
                array_push($newArr, $event);
            }
        }
        return $newArr;
    }

    protected function data() {
	    $data = (object)[];
	    $data->events = $this->eventData();
	    $data->columns = $this->columnData();
	    return $data;
    }

    protected function columnData() {
	    return json_decode( \IPS\Db::i()->select('data','cbpanel_trello_columns')->first() );

    }

    protected function eventData() {
	    /*
	     * Example Event
	     *
	     * {
	     *  id: '',
	     *  author: '',
	     *  assigned: [],
	     *  title: '',
	     *  message: '',
	     *  column: '',
	     * }
	     */
	    $arr = [];

	    // Push events
	    $db = \IPS\Db::i()->select('*','cbpanel_trello_events');
	    if ($db->count() > 0) {
            for ($i = 0; $i < $db->count(); $i++) {
                $db->next();
                $eventObj = (object) json_decode($db->current()['data']);
                $eventObj->id = $db->current()['id'];
                array_push($arr, $eventObj);
            }
            return $arr;
        } else {
	        return [];
        }
    }

    protected function setup() {
	    if (\IPS\Db::i()->select('data', 'cbpanel_trello_columns')->count()  == 0) {
	        \IPS\Db::i()->insert('cbpanel_trello_columns', ['data'=>'[]']);
        }
    }

    protected function idToUsername($id) {
	    return \IPS\Member::load($id)->name;
    }

    protected function idToColumn($id) {
	    $data = (array)json_decode( \IPS\Db::i()->select('data', 'cbpanel_trello_columns')->first() );
	    return $data[$id];
    }
}