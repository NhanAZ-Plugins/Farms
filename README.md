# Farms

Crop growth management plugin for Axolotl-PM API 5.

[![CI](https://github.com/NhanAZ-Plugins/Farms/actions/workflows/build.yml/badge.svg)](https://github.com/NhanAZ-Plugins/Farms/actions/workflows/build.yml)
[![Latest release](https://img.shields.io/github/v/release/NhanAZ-Plugins/Farms)](https://github.com/NhanAZ-Plugins/Farms/releases/latest)
[![Discord](https://img.shields.io/discord/986553214889517088?label=Discord&logo=discord&logoColor=white&color=5865F2)](https://discord.gg/j2X83ujT6c)


## Compatibility and validation

The manifest requires Axolotl-PM API 5.0.0. PHPStan max and reproducible PHAR builds are checked against the pinned API 5.0.0 and 5.49.1 source revisions. Those two checks do not prove compatibility with every API 5 release.

The build workflow verifies the PHAR and starts it on an isolated Axolotl-PM server. It does not simulate a connected player planting or harvesting crops.

## Farm data migration

New records include the world folder name and block coordinates. Older coordinate-only records are associated with the historical default world only after that chunk is loaded and the current block matches the old crop type. Unmatched or malformed entries are retained in `farmlist.yml`; this plugin does not force-load chunks or rewrite blocks during migration. Back up the plugin data before upgrading.

## Credits and distribution

The manifest retains the `NhanAZ` author and `NhanAZ` edit credits. This repository has no LICENSE file or verified redistribution grant, so no license was added and no stable release was published.


## Credits

Authors: NhanAZ.
