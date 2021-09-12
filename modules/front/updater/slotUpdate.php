<?php


namespace IPS\cbpanel\modules\front\updater;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * slotUpdate
 */
class _slotUpdate extends \IPS\Dispatcher\Controller
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
		require("conf_global.php");
		$conn = new \mysqli($INFO['sql_host'], $INFO['sql_user'], $INFO['sql_pass'], $INFO['sql_database'], $INFO['sql_port']);
		$this->refresh($conn);
		die();
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it

	
	protected function refresh($conn) {
		
		/**
		 * `p_cbpanel_data` JSON Object:
		 * 		{
		 * 			"isProduct":bool,
		 * 			"status":"String",
		 * 			"slots":{
		 * 				"used":int,
		 * 				"max":int
		 * 			}
		 * 		}
		 */
		if (!$conn->query("SHOW COLUMNS FROM `nexus_packages` LIKE 'p_cbpanel_data'")->num_rows > 0) {
			// First load set-up, if column on nexus packages doesn't exist then create it
			$conn->query("ALTER TABLE `nexus_packages` ADD `p_cbpanel_data` TEXT");
		}

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




		$conn->query("UPDATE `nexus_packages` SET `p_cbpanel_data`='$obj' WHERE `p_cbpanel_data`=null OR `p_cbpanel_data` IS NULL");
		$data = array(
			'product'=>array(),
			'subscription'=>array(),
			'count'=>array(),
		);




		// Package Data
		$packageData = $conn->query("SELECT `p_id`,`p_stock`,`p_cbpanel_data` FROM `nexus_packages`");
		while ($row = $packageData->fetch_assoc()) {
			$i = (object) [
				'id' => $row['p_id'],
				'stock' => (int)$row['p_stock'],
				'settings' => json_decode($row['p_cbpanel_data']), // $package[$i]->settings->id/isProduct/status/slots[used/max]
			];
			if ($i->settings->isProduct) {
				array_push($data['product'], $i);
			}
		}





		// Active Subscription Data
		$subData = $conn->query("SELECT * FROM `nexus_purchases` WHERE (`ps_active`=1) AND (`ps_cancelled`=0) AND (`ps_start`>0) AND (`ps_expire`>" . time() . ")");
		while ($row = $subData->fetch_assoc()) {
			$i = (object) [	
				'id' => $row['ps_item_id'],
				'pid' => $row['ps_id'],
				'mid' => $row['ps_member'],
			];
			array_push($data['subscription'], $i);	
		}





		for ($i=0;$i<count($data['subscription']);$i++) {
			if (!isset($data['count'][$data['subscription'][$i]->id])) {
				$data['count'][$data['subscription'][$i]->id] = 1;
			} else {
				$data['count'][$data['subscription'][$i]->id] += 1;
			}
		}


		for ($i=0;$i<count($data['product']);$i++) {
			if (!isset($data['count'][$data['product'][$i]->id])) {
				$data['count'][$data['product'][$i]->id] = 0;
			} else {
				$data['product'][$i]->settings->slots->used = $data['count'][$data['product'][$i]->id];
			}
			$freeSlots = $data['product'][$i]->settings->slots->max - $data['product'][$i]->settings->slots->used;
			if ($freeSlots > 0) {
				$conn->query("UPDATE `nexus_packages` SET `p_stock`=" . $freeSlots . " WHERE `p_id`=" . $data['product'][$i]->id);
			} else if ($data['product'][$i]->settings->slots->max == -1) {
				$conn->query("UPDATE `nexus_packages` SET `p_stock`=-1 WHERE `p_id`=" . $data['product'][$i]->id);
			} else {
				$conn->query("UPDATE `nexus_packages` SET `p_stock`=0 WHERE `p_id`=" . $data['product'][$i]->id);
			}
			$conn->query("UPDATE `nexus_packages` SET `p_cbpanel_data`='" . json_encode($data['product'][$i]->settings) . "' WHERE `p_id`=" . $data['product'][$i]->id);
		}


	}
}