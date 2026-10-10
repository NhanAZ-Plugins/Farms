# Farms

Farms is a historical PocketMine-MP plugin that tracks planted crops and changes blocks in the default world.

## Compatibility and status

The manifest declares PocketMine-MP API `1.4.1`. The source uses numeric item and block IDs, `CallbackTask`, `getDefaultLevel()` and legacy world APIs. It has not been ported to Axolotl-PM 5. Do not install this source on a current server or copy its data files into a real world.

## Historical data and behavior

`speed.yml` defines the growth interval. `farmlist.yml` stores tracked positions by coordinate, without a world identifier. The plugin reads those coordinates and changes blocks in the default level. A port must define how old coordinates map to worlds and migrate saved data safely before enabling block changes.

The manifest credits `Dấu Tên` and includes an edit credit for `Ghast Noob`. Keep both credits with the source. There is no license file, CI workflow, DevTools PHAR, Axolotl-PM 5 runtime test or verified release. Resolve redistribution rights with the rights holders before publishing a modified build.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
