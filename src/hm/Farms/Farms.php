<?php

declare(strict_types=1);

namespace hm\Farms;

use pocketmine\block\Beetroot;
use pocketmine\block\Carrot;
use pocketmine\block\Potato;
use pocketmine\block\PumpkinStem;
use pocketmine\block\Sugarcane;
use pocketmine\block\Wheat;
use pocketmine\block\MelonStem;
use pocketmine\block\utils\Ageable;
use pocketmine\block\utils\BlockEventHelper;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\world\World;

final class Farms extends PluginBase implements Listener{
	private const MAX_TRACKED_CROPS = 100000;
	private const MAX_UPDATES_PER_TICK = 256;

	private Config $farmList;
	private Config $farmConfig;

	/** @var array<string, int> */
	private array $farms = [];
	/** @var array<string, array{id: int, age: int, delay: int}> */
	private array $legacyFarms = [];
	/** @var array<array-key, mixed> */
	private array $unparsedFarmData = [];

	private int $growthDelay = 0;
	private int $farmCursor = 0;
	private int $legacyCursor = 0;

	protected function onEnable() : void{
		@mkdir($this->getDataFolder(), 0777, true);
		$this->farmList = new Config($this->getDataFolder() . "farmlist.yml", Config::YAML, FarmData::encode([], [], []));
		$decoded = FarmData::decode($this->farmList->getAll());
		$this->farms = $decoded["farms"];
		$this->legacyFarms = $decoded["legacy"];
		$this->unparsedFarmData = $decoded["unparsed"];

		$this->farmConfig = new Config($this->getDataFolder() . "speed.yml", Config::YAML, ["growing-time" => 0]);
		$this->growthDelay = FarmData::normalizeGrowthDelay($this->farmConfig->get("growing-time", 0));
		$this->farmConfig->set("growing-time", $this->growthDelay);
		$this->farmConfig->save();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{ $this->tickFarms(); }), 20);
		if($this->legacyFarms !== [] || $this->unparsedFarmData !== []){
			$this->getLogger()->warning("Legacy farm records are retained until they can be safely matched to crops in a loaded default-world chunk. Unrecognized records are preserved in farmlist.yml.");
		}
	}

	protected function onDisable() : void{
		$this->saveFarmData();
		$this->farmConfig->save();
	}

	/**
	 * @priority MONITOR
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void{
		if($event->isCancelled()){
			return;
		}
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $placedBlock]){
			if(!$this->isTrackedCrop($placedBlock)){
				continue;
			}
			$world = $placedBlock->getPosition()->getWorld();
			$worldFolder = $world->getFolderName();
			$key = FarmData::positionKey($worldFolder, $x, $y, $z);
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($world, $key, $x, $y, $z) : void{
				if(!$this->isChunkLoadedAt($world, $x, $z)){
					return;
				}
				if($this->isTrackedCrop($world->getBlockAt($x, $y, $z))){
					if(!isset($this->farms[$key]) && count($this->farms) >= self::MAX_TRACKED_CROPS){
						$this->getLogger()->warning("Crop tracking limit reached; newly planted crops will use vanilla growth until space is available.");
						return;
					}
					$this->farms[$key] = $this->growthDelay;
					$this->saveFarmData();
				}
			}), 1);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void{
		if($event->isCancelled()){
			return;
		}
		$block = $event->getBlock();
		$position = $block->getPosition();
		$key = FarmData::positionKey($position->getWorld()->getFolderName(), $position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		if(isset($this->farms[$key])){
			unset($this->farms[$key]);
			$this->saveFarmData();
		}
	}

	private function tickFarms() : void{
		$this->migrateLegacyRecords();
		$keys = array_keys($this->farms);
		$total = count($keys);
		if($total === 0){
			$this->farmCursor = 0;
			return;
		}
		$count = min(self::MAX_UPDATES_PER_TICK, $total);
		for($i = 0; $i < $count; ++$i){
			$key = $keys[($this->farmCursor + $i) % $total];
			$this->advanceFarm($key);
		}
		$this->farmCursor = ($this->farmCursor + $count) % max(1, $total);
	}

	private function advanceFarm(string $key) : void{
		$position = FarmData::parsePositionKey($key);
		if($position === null){
			unset($this->farms[$key]);
			return;
		}
		$world = $this->getServer()->getWorldManager()->getWorldByName($position["world"]);
		if($world === null || !$world->isInWorld($position["x"], $position["y"], $position["z"]) || !$this->isChunkLoadedAt($world, $position["x"], $position["z"])){
			return;
		}
		$block = $world->getBlockAt($position["x"], $position["y"], $position["z"]);
		if(!$this->isTrackedCrop($block) || !$block instanceof Ageable){
			unset($this->farms[$key]);
			return;
		}
		if($this->farms[$key] > 0){
			--$this->farms[$key];
			return;
		}
		if($block->getAge() >= $block->getMaxAge()){
			unset($this->farms[$key]);
			return;
		}
		$grownBlock = clone $block;
		$grownBlock->setAge($block->getAge() + 1);
		BlockEventHelper::grow($block, $grownBlock, null);
		$this->farms[$key] = $this->growthDelay;
	}

	private function migrateLegacyRecords() : void{
		if($this->legacyFarms === []){
			$this->legacyCursor = 0;
			return;
		}
		$world = $this->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			return;
		}
		$keys = array_keys($this->legacyFarms);
		$total = count($keys);
		$count = min(64, $total);
		for($i = 0; $i < $count; ++$i){
			$legacyKey = $keys[($this->legacyCursor + $i) % $total];
			$coordinates = FarmData::parseLegacyPosition($legacyKey);
			if($coordinates === null || !$world->isInWorld($coordinates["x"], $coordinates["y"], $coordinates["z"]) || !$this->isChunkLoadedAt($world, $coordinates["x"], $coordinates["z"])){
				continue;
			}
			$legacy = $this->legacyFarms[$legacyKey];
			$block = $world->getBlockAt($coordinates["x"], $coordinates["y"], $coordinates["z"]);
			if($this->legacyIdMatchesBlock($legacy["id"], $block) && $this->isTrackedCrop($block) && count($this->farms) < self::MAX_TRACKED_CROPS){
				$key = FarmData::positionKey($world->getFolderName(), $coordinates["x"], $coordinates["y"], $coordinates["z"]);
				$this->farms[$key] = min($legacy["delay"], $this->growthDelay);
				unset($this->legacyFarms[$legacyKey]);
			}
		}
		$this->legacyCursor = ($this->legacyCursor + $count) % max(1, $total);
	}

	private function isTrackedCrop(\pocketmine\block\Block $block) : bool{
		return $block instanceof Wheat || $block instanceof Carrot || $block instanceof Potato || $block instanceof Beetroot || $block instanceof Sugarcane || $block instanceof PumpkinStem || $block instanceof MelonStem;
	}

	private function legacyIdMatchesBlock(int $id, \pocketmine\block\Block $block) : bool{
		return match($id){
			59 => $block instanceof Wheat,
			83 => $block instanceof Sugarcane,
			104 => $block instanceof PumpkinStem,
			105 => $block instanceof MelonStem,
			141 => $block instanceof Carrot,
			142 => $block instanceof Potato,
			244 => $block instanceof Beetroot,
			default => false
		};
	}

	private function isChunkLoadedAt(World $world, int $x, int $z) : bool{
		return $world->isChunkLoaded($x >> 4, $z >> 4);
	}

	private function saveFarmData() : void{
		$this->farmList->setAll(FarmData::encode($this->farms, $this->legacyFarms, $this->unparsedFarmData));
		$this->farmList->save();
	}
}
