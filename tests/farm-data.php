<?php

declare(strict_types=1);

require dirname(__DIR__) . "/src/hm/Farms/FarmData.php";

use hm\Farms\FarmData;

$assert = static function(bool $condition, string $message) : void{
	if(!$condition){
		throw new RuntimeException($message);
	}
};

$position = FarmData::positionKey("world one", -17, 64, 32);
$assert(FarmData::parsePositionKey($position) === ["world" => "world one", "x" => -17, "y" => 64, "z" => 32], "Position keys must round-trip world names and negative coordinates.");
$assert(FarmData::parsePositionKey("world|x|0|1") === null, "Malformed coordinates must be rejected.");
$assert(FarmData::parseLegacyPosition("-17.64.32") === ["x" => -17, "y" => 64, "z" => 32], "Legacy coordinates must preserve negative coordinates.");
$assert(FarmData::parseLegacyPosition("1.2") === null, "Incomplete legacy coordinates must be rejected.");

$legacy = FarmData::decode([
	"-17.64.32" => ["id" => 59, "damage" => 3, "time" => 0],
	"99.64.99" => ["id" => 1, "damage" => 0, "time" => 3],
	"unrecognized" => ["keep" => "this"]
]);
$assert($legacy["legacy"]["-17.64.32"] === ["id" => 59, "age" => 3, "delay" => 0], "Recognized historical crop records must be retained for safe migration.");
$assert(isset($legacy["unparsed"]["99.64.99"], $legacy["unparsed"]["unrecognized"]), "Unsupported and malformed legacy data must be preserved.");

$encoded = FarmData::encode([$position => 12], $legacy["legacy"], $legacy["unparsed"]);
$decoded = FarmData::decode($encoded);
$assert($decoded["farms"] === [$position => 12], "Current farm records must survive a save/load round trip.");
$assert($decoded["unparsed"] === $legacy["unparsed"], "Unparsed data must survive a save/load round trip.");
$assert(FarmData::normalizeGrowthDelay(0) === 0, "A zero delay must remain supported.");
$assert(FarmData::normalizeGrowthDelay(86400) === 86400, "The maximum delay must remain supported.");
$assert(FarmData::normalizeGrowthDelay(-1) === 0, "Negative delays must be rejected safely.");
$assert(FarmData::normalizeGrowthDelay("1.5") === 0, "Fractional delays must be rejected safely.");

echo "Farm data migration checks passed.\n";
