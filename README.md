# Farms

Farms speeds up the growth stages of player-planted wheat, carrots, potatoes, beetroot, sugar cane, pumpkin stems and melon stems. The `growing-time` setting in `speed.yml` is the number of one-second checks between stages; `0` advances eligible crops on each check. Mature blocks return to normal Axolotl-PM growth behavior.

## Compatibility and validation

The manifest requires Axolotl-PM API 5.0.0. PHPStan max and reproducible PHAR builds are checked against the pinned API 5.0.0 and 5.49.1 source revisions. Those two checks do not prove compatibility with every API 5 release.

The build workflow verifies the PHAR and starts it on an isolated Axolotl-PM server. It does not simulate a connected player planting or harvesting crops.

## Farm data migration

New records include the world folder name and block coordinates. Older coordinate-only records are associated with the historical default world only after that chunk is loaded and the current block matches the old crop type. Unmatched or malformed entries are retained in `farmlist.yml`; this plugin does not force-load chunks or rewrite blocks during migration. Back up the plugin data before upgrading.

## Credits and distribution

The manifest retains the `Dấu Tên` author and `Ghast Noob` edit credits. This repository has no LICENSE file or verified redistribution grant, so no license was added and no stable release was published.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
