<p align="center">
	<a href="https://aquarelay.dev">
       <img src=".github/readme/banner.png" alt="The AquaRelay logo" title="AquaRelay" loading="eager" />
	</a><br>
	<b>Blazingly fast, lightweight, and easy to use Minecraft: Bedrock Edition proxy server written in PHP</b>
</p>

<p align="center">
	<a href="https://github.com/AquaRelay/AquaRelay/actions/workflows/php.yml">
      <img src="https://github.com/AquaRelay/AquaRelay/actions/workflows/php.yml/badge.svg" alt="CI" />
    </a>
	<a href="https://discord.gg/VBjqNgCPq4">
      <img src="https://img.shields.io/discord/1456325670790762599?label=discord&color=7289DA&logo=discord" alt="Discord" />
    </a>
	<br>
	<a href="https://github.com/AquaRelay/AquaRelay/releases">
      <img alt="GitHub all releases" src="https://img.shields.io/github/downloads/AquaRelay/AquaRelay/total?label=downloads">
    </a>
    <a href="https://www.gnu.org/licenses/lgpl-3.0.html" target="_blank">
      <img alt="License: LGPL-3" src="https://img.shields.io/badge/License-LGPL--3-yellow.svg" />
    </a>
</p>

---
> [!IMPORTANT]
> This project is still under development, if you have found a bug please report it from the issues tab.

---

## Features
- Fast transfer support between Bedrock servers
- Supports **PocketMine-MP**, **Nukkit**, and **PowerNukkitX**
- Lightweight and minimal resource usage
- Easy configuration with simple YAML files
- Plugin-friendly architecture
- Modern PHP codebase
- Actively maintained and open source

---

## Requirements
- **PHP 8.1 or higher**
- Supported OS: Linux, macOS, Windows
- A Bedrock-compatible server (PocketMine-MP / Nukkit / PowerNukkitX)

---

## Installation

### Download
You can download the latest release from:
https://github.com/AquaRelay/AquaRelay/releases

### Setup
1. Download PHP (minimum 8.1)
2. Make a folder of your server. Put the `AquaRelay.phar` file and start script files into the folder.
3. Start the server using the start script files.
4. Change the `config.yml` file

---

## Usage

Once running, players can:

* Join through the proxy address
* Seamlessly transfer between servers
* Experience reduced connection latency

AquaRelay acts as a transparent layer between clients and backend servers.

---

## Development

### Running from source

```bash
git clone https://github.com/AquaRelay/AquaRelay.git
cd AquaRelay
composer install
php AquaRelay.php
```

### Contributing

We welcome all contributions ❤️
Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

---

## Support

* 💬 Discord: [https://discord.gg/VBjqNgCPq4](https://discord.gg/VBjqNgCPq4)
* 🐞 Issues: [https://github.com/AquaRelay/AquaRelay/issues](https://github.com/AquaRelay/AquaRelay/issues)

---

## License

This project is licensed under the **LGPL-3.0** License.
See the [LICENSE](LICENSE) file for details.