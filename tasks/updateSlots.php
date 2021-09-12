<?php


namespace IPS\cbpanel\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * updateSlots Task
 */
class _updateSlots extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
	    // Default Object
        $obj = json_encode((object) [
            'isProduct'=>false,
            'statusCode'=>1,
            'statusMessage'=>"Undetected",
            'slots'=>(object) [
                'used'=>0,
                "max"=>-1,
            ],
        ]);

        // Set entry to default if it's null
        \IPS\Db::i()->update( 'nexus_packages', "`p_cbpanel_data`='$obj'", ['`p_cbpanel_data`=null OR `p_cbpanel_data` IS NULL'] );

        $data = array(
            'product'=>array(),
            'subscription'=>array(),
            'count'=>array(),
        );

        // Package Data
        $packageData = \IPS\Db::i()->select('`p_id`,`p_stock`,`p_cbpanel_data`', 'nexus_packages');

        for ($i=0; $i<$packageData->count(); $i++) {

            $packageData->next();

            $arr = (object)$packageData->current();

            $arr = (object)[
                'id'       => $packageData->current()['p_id'],
                'stock'    => $packageData->current()['p_stock'],
                'settings' => json_decode( $packageData->current()['p_cbpanel_data'] ),
            ];


            if ($arr->settings->isProduct) {

                array_push($data['product'], $arr);

            }

        }


        // Active Subscription Data
        $subData = \IPS\Db::i()->select('`ps_item_id`,`ps_id`,`ps_member`', 'nexus_purchases', ["(`ps_active`=1) AND (`ps_cancelled`=0) AND (`ps_start`>0) AND (`ps_expire`>" . time() . ")"]);
        for ($i=0; $i<$subData->count(); $i++) {

            $subData->next();

            if ( (int)\IPS\Db::i()->select('member_group_id', 'core_members', ["`member_id`='" . $subData->current()['ps_member'] . "'"])->first() == 7) {
                $arr = (object)[
                    'id' => $subData->current()['ps_item_id'],
                    'pid' => $subData->current()['ps_id'],
                    'mid' => $subData->current()['ps_member'],
                ];
            }

            array_push($data['subscription'], $arr);

        }


        for ($i=0;$i<\count($data['subscription']);$i++) {

            if (!isset($data['count'][$data['subscription'][$i]->id])) {

                $data['count'][$data['subscription'][$i]->id] = 1;

            } else {

                $data['count'][$data['subscription'][$i]->id] += 1;

            }

        }


        for ($i=0;$i<\count($data['product']);$i++) {

            if ( !isset( $data['count'][$data['product'][$i]->id] ) ) {

                $data['product'][$i]->settings->slots->used = 0;

            } else {

                $data['product'][$i]->settings->slots->used = $data['count'][$data['product'][$i]->id];

            }

            $freeSlots = $data['product'][$i]->settings->slots->max - $data['product'][$i]->settings->slots->used;

            if ($freeSlots > 0) {

                \IPS\Db::i()->update('nexus_packages', "`p_stock`={$freeSlots}", ["`p_id`=" . $data['product'][$i]->id]);

            } else if ($data['product'][$i]->settings->slots->max == -1) {

                \IPS\Db::i()->update('nexus_packages', "`p_stock`=-1", ["`p_id`=" . $data['product'][$i]->id]);

            } else {

                \IPS\Db::i()->update('nexus_packages', "`p_stock`=0", ["`p_id`=" . $data['product'][$i]->id]);

            }
            \IPS\Db::i()->update(
                'nexus_packages',
                "`p_cbpanel_data`='" . json_encode($data['product'][$i]->settings) . "'",
                ["`p_id`=" . $data['product'][$i]->id]
            );

        }
        return NULL;
	}

	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{

	}
}