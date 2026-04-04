<?php

namespace hm\Farms;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerInteractEvent;

class Farms extends PluginBase implements Listener {
	public $farmlist, $farmdata, $growids, $blockids;
	public $farmconfig, $configdata;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		@mkdir ( $this->getDataFolder () );
		$this->farmlist = new Config ( $this->getDataFolder () . "farmlist.yml", Config::YAML );
		$this->farmdata = $this->farmlist->getAll ();
		$this->farmconfig = new Config ( $this->getDataFolder () . "speed.yml", Config::YAML, array (
				"growing-time" => 0 
		) );
		$this->configdata = $this->farmconfig->getAll ();
		$this->growids = [ 
				Item::SEEDS,
				Item::CARROT,
				Item::POTATO,
				Item::BEETROOT,
				Item::SUGAR_CANE,
				Item::SUGARCANE_BLOCK,
				Item::PUMPKIN_SEEDS,
				Item::MELON_SEEDS 
		];
		$this->blockids = [ 
				Item::WHEAT_BLOCK,
				Item::CARROT_BLOCK,
				Item::POTATO_BLOCK,
				Item::BEETROOT_BLOCK,
				Item::SUGARCANE_BLOCK,
				Item::SUGARCANE_BLOCK,
				Item::PUMPKIN_STEM,
				Item::MELON_STEM 
		];
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"Farms" 
		] ), 20 );
	}
	public function onDisable() {
		$this->getLogger ()->info ( "YML Saved" );
		$this->farmlist->setAll ( $this->farmdata );
		$this->farmlist->save ();
		$this->farmconfig->save ();
	}
	public function onBlock(PlayerInteractEvent $event) {
		$block = $event->getBlock ()->getSide ( 1 );
		$blockid = $block->getID ();
		$itemid = $event->getItem ()->getID ();
		
		if ($event->getBlock ()->getID () == Item::FARMLAND or $event->getBlock ()->getID () == Item::SAND) {
			foreach ( $this->growids as $index => $growid ) {
				if ($itemid == $growid) {
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['id'] = $this->blockids [$index];
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['damage'] = 0;
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['time'] = $this->configdata ["growing-time"];
					break;
				}
			}
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$blockid = $block->getID ();
		$itemid = $event->getItem ()->getID ();
		
		foreach ( $this->growids as $index => $growid ) {
			if ($itemid == $growid) {
				if (isset ( $this->farmdata [$block->x . "." . $block->y . "." . $block->z] ))
					unset ( $this->farmdata [$block->x . "." . $block->y . "." . $block->z] );
			}
		}
	}
	public function Farms() {
		$poslist = array_keys ( $this->farmdata );
		
		foreach ( $poslist as $p ) {
			$e = explode ( ".", $p );
			-- $this->farmdata [$p] ['time'];
			if ($this->farmdata [$p] ['time'] <= 0) {
				$id = $this->farmdata [$p] ['id'];
				if ($id == 59 or $id == 141 or $id == 142 or $id == 244) {
					++ $this->farmdata [$p] ['damage'];
					if ($this->farmdata [$p] ['damage'] >= 8) {
						unset ( $this->farmdata [$p] );
						return;
					}
					$np = new Vector3 ( $e [0], $e [1], $e [2] );
					$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] ) );
				}
				if ($id == 83) {
					++ $this->farmdata [$p] ['damage'];
					if ($this->farmdata [$p] ['damage'] >= 4) {
						unset ( $this->farmdata [$p] );
						return;
					}
					$np = new Vector3 ( $e [0], $e [1] + $this->farmdata [$p] ['damage'], $e [2] );
					if ($this->getServer ()->getDefaultLevel ()->getBlock ( $np )->getID () != Item::AIR) {
						unset ( $this->farmdata [$p] );
						return;
					}
					$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( $this->farmdata [$p] ['id'], 0 ) );
				}
				if ($id == 104) {
					++ $this->farmdata [$p] ['damage'];
					if ($this->farmdata [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++) {
							for($b = - 1; $b <= 1; $b ++) {
								$ground = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$np = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								if ($this->getServer ()->getDefaultLevel ()->getBlock ( $np )->getID () == Item::AIR) {
									if ($this->getServer ()->getDefaultLevel ()->getBlock ( $ground )->getID () == Item::FARMLAND) {
										$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( 86, 0 ) );
										unset ( $this->farmdata [$p] );
										return;
									}
								}
							}
						}
						if (isset ( $this->farmdata [$p] )) {
							unset ( $this->farmdata [$p] );
							return;
						}
					}
					$np = new Vector3 ( $e [0], $e [1], $e [2] );
					$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] ) );
				}
				if ($id == 105) {
					++ $this->farmdata [$p] ['damage'];
					if ($this->farmdata [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++) {
							for($b = - 1; $b <= 1; $b ++) {
								$ground = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$np = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								if ($this->getServer ()->getDefaultLevel ()->getBlock ( $np )->getID () == Item::AIR) {
									if ($this->getServer ()->getDefaultLevel ()->getBlock ( $ground )->getID () == Item::FARMLAND) {
										$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( 103, 0 ) );
										unset ( $this->farmdata [$p] );
										return;
									}
								}
							}
						}
						if (isset ( $this->farmdata [$p] )) {
							unset ( $this->farmdata [$p] );
							return;
						}
					}
					$np = new Vector3 ( $e [0], $e [1], $e [2] );
					$this->getServer ()->getDefaultLevel ()->setBlock ( $np, Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] ) );
				}
				$this->farmdata [$p] ['time'] = $this->configdata ["growing-time"];
			}
		}
	}
}

?>