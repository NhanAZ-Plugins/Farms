<?php

declare(strict_types=1);

namespace hm\Farms;

final class FarmData{
	public const SCHEMA_VERSION = 2;
	public const MAX_DELAY_SECONDS = 86400;

	/**
	 * @param array<array-key, mixed> $stored
	 * @return array{
	 *     farms: array<string, int>,
	 *     legacy: array<string, array{id: int, age: int, delay: int}>,
	 *     unparsed: array<array-key, mixed>
	 * }
	 */
	public static function decode(array $stored) : array{
		$farms = [];
		$legacy = [];
		/** @var array<array-key, mixed> $unparsed */
		$unparsed = [];

		if(($stored["schema-version"] ?? null) !== self::SCHEMA_VERSION){
			foreach($stored as $position => $record){
				if(!is_string($position)){
					$unparsed["legacy-key-" . count($unparsed)] = $record;
					continue;
				}
				$normalized = self::normalizeLegacyRecord($record);
				if(self::parseLegacyPosition($position) !== null && $normalized !== null){
					$legacy[$position] = $normalized;
				}else{
					$unparsed[$position] = $record;
				}
			}
			return ["farms" => $farms, "legacy" => $legacy, "unparsed" => $unparsed];
		}

		$rawFarms = $stored["farms"] ?? [];
		if(is_array($rawFarms)){
			$invalidFarms = [];
			foreach($rawFarms as $position => $delay){
				if(is_string($position) && self::parsePositionKey($position) !== null && is_int($delay) && $delay >= 0 && $delay <= self::MAX_DELAY_SECONDS){
					$farms[$position] = $delay;
				}else{
					$invalidFarms[is_string($position) ? $position : "entry-" . count($invalidFarms)] = $delay;
				}
			}
			if($invalidFarms !== []){
				$unparsed["farms"] = $invalidFarms;
			}
		}else{
			$unparsed["farms"] = $rawFarms;
		}

		$rawLegacy = $stored["legacy"] ?? [];
		if(is_array($rawLegacy)){
			$invalidLegacy = [];
			foreach($rawLegacy as $position => $record){
				$normalized = self::normalizeLegacyRecord($record);
				if(is_string($position) && self::parseLegacyPosition($position) !== null && $normalized !== null){
					$legacy[$position] = $normalized;
				}else{
					$invalidLegacy[is_string($position) ? $position : "entry-" . count($invalidLegacy)] = $record;
				}
			}
			if($invalidLegacy !== []){
				$unparsed["legacy"] = $invalidLegacy;
			}
		}else{
			$unparsed["legacy"] = $rawLegacy;
		}

		$existingUnparsed = $stored["unparsed"] ?? [];
		if(is_array($existingUnparsed)){
			$unparsed = array_replace_recursive($unparsed, $existingUnparsed);
		}elseif($existingUnparsed !== []){
			$unparsed["unparsed"] = $existingUnparsed;
		}
		foreach($stored as $key => $value){
			if(!in_array($key, ["schema-version", "farms", "legacy", "unparsed"], true)){
				$unparsed["root:" . (string) $key] = $value;
			}
		}

		return ["farms" => $farms, "legacy" => $legacy, "unparsed" => $unparsed];
	}

	/**
	 * @param array<string, int> $farms
	 * @param array<string, array{id: int, age: int, delay: int}> $legacy
	 * @param array<array-key, mixed> $unparsed
	 * @return array<string, mixed>
	 */
	public static function encode(array $farms, array $legacy, array $unparsed) : array{
		return [
			"schema-version" => self::SCHEMA_VERSION,
			"farms" => $farms,
			"legacy" => $legacy,
			"unparsed" => $unparsed
		];
	}

	public static function positionKey(string $worldFolder, int $x, int $y, int $z) : string{
		return rawurlencode($worldFolder) . "|" . $x . "|" . $y . "|" . $z;
	}

	/** @return array{world: string, x: int, y: int, z: int}|null */
	public static function parsePositionKey(string $key) : ?array{
		$parts = explode("|", $key);
		if(count($parts) !== 4 || $parts[0] === "" || preg_match('/^-?\d{1,10}$/D', $parts[1]) !== 1 || preg_match('/^-?\d{1,10}$/D', $parts[2]) !== 1 || preg_match('/^-?\d{1,10}$/D', $parts[3]) !== 1){
			return null;
		}
		$world = rawurldecode($parts[0]);
		if(str_contains($world, "\0")){
			return null;
		}
		return ["world" => $world, "x" => (int) $parts[1], "y" => (int) $parts[2], "z" => (int) $parts[3]];
	}

	/** @return array{x: int, y: int, z: int}|null */
	public static function parseLegacyPosition(string $key) : ?array{
		if(preg_match('/^(-?\d{1,10})\.(-?\d{1,10})\.(-?\d{1,10})$/D', $key, $matches) !== 1){
			return null;
		}
		return ["x" => (int) $matches[1], "y" => (int) $matches[2], "z" => (int) $matches[3]];
	}

	/** @return array{id: int, age: int, delay: int}|null */
	public static function normalizeLegacyRecord(mixed $record) : ?array{
		if(!is_array($record)){
			return null;
		}
		$id = self::integerValue($record["id"] ?? null);
		$age = self::integerValue($record["age"] ?? $record["damage"] ?? null);
		$delay = self::integerValue($record["delay"] ?? $record["time"] ?? null);
		if($id === null || !in_array($id, [59, 83, 104, 105, 141, 142, 244], true) || $age === null || $age < 0 || $age > 15 || $delay === null || $delay < 0 || $delay > self::MAX_DELAY_SECONDS){
			return null;
		}
		return ["id" => $id, "age" => $age, "delay" => $delay];
	}

	public static function normalizeGrowthDelay(mixed $value) : int{
		$delay = self::integerValue($value);
		return $delay !== null && $delay >= 0 && $delay <= self::MAX_DELAY_SECONDS ? $delay : 0;
	}

	private static function integerValue(mixed $value) : ?int{
		if(is_int($value)){
			return $value;
		}
		if(is_string($value) && preg_match('/^-?\d{1,10}$/D', $value) === 1){
			return (int) $value;
		}
		return null;
	}
}
