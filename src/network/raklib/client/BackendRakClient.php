<?php

/*
 *
 *                            _____      _
 *     /\                    |  __ \    | |
 *    /  \   __ _ _   _  __ _| |__) |___| | __ _ _   _
 *   / /\ \ / _` | | | |/ _` |  _  // _ \ |/ _` | | | |
 *  / ____ \ (_| | |_| | (_| | | \ \  __/ | (_| | |_| |
 * /_/    \_\__, |\__,_|\__,_|_|  \_\___|_|\__,_|\__, |
 *               |_|                              |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AquaRelay Team
 * @link https://www.aquarelay.dev/
 *
 */

declare(strict_types=1);

namespace aquarelay\network\raklib\client;

use aquarelay\lang\TranslationFactory;
use aquarelay\network\compression\ZlibCompressor;
use aquarelay\network\PacketBatchDecoder;
use aquarelay\network\raklib\RakLibInterface;
use aquarelay\player\Player;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\ByteBufferReader;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\utils\Binary;
use raklib\client\ClientSocket;
use raklib\generic\DisconnectReason;
use raklib\generic\Session;
use raklib\generic\SocketException;
use raklib\protocol\ACK;
use raklib\protocol\ConnectionRequest;
use raklib\protocol\Datagram;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\MessageIdentifiers;
use raklib\protocol\NACK;
use raklib\protocol\NewIncomingConnection;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\Packet;
use raklib\protocol\PacketReliability;
use raklib\protocol\PacketSerializer;
use raklib\protocol\UnconnectedPing;
use raklib\utils\InternetAddress;
use Ramsey\Uuid\Uuid;
use function microtime;
use function min;
use function ord;
use function random_int;
use function strlen;
use const PHP_INT_MAX;

final class BackendRakClient extends Session
{
	private const ID_ACK = 0xC0;

	private const ID_USER_PACKET_ENUM = 0x80;

	private const DEFAULT_MTU = 1492;

	private ClientSocket $socket;

	private ConnectionState $connState = ConnectionState::UNCONNECTED;

	private int $mtu = self::DEFAULT_MTU;

	/** @var DataPacket[] */
	private array $sendQueue = [];

	private ?int $rakCookie = null;
	private bool $compressionEnabled = false;

	public function __construct(
		InternetAddress $address,
		private Player $player
	) {
		$clientId = random_int(1, PHP_INT_MAX);
		parent::__construct(
			$player->getNetworkSession()->getLogger(),
			$address,
			$clientId,
			$this->mtu
		);
		$this->socket = new ClientSocket($address);
		$this->socket->setBlocking(false);
	}

	public function connect() : void
	{
		$this->sendUnconnectedPing();
	}

	public function isConnected() : bool
	{
		return $this->connState === ConnectionState::LOGGED_IN;
	}

	public function sendGamePacket(DataPacket $packet) : void
	{
		if ($this->connState->value < ConnectionState::LOGGED_IN->value) {
			$this->sendQueue[] = $packet;
			return;
		}
		$this->encodeAndSend($packet);
	}

	public function tick() : void
	{
		try {
			while (($buf = $this->socket->readPacket()) !== null) {
				$pid = ord($buf[0]);

				if ($pid < self::ID_USER_PACKET_ENUM) {
					$this->handleInternalPacket($buf);
				} else {
					$s = new PacketSerializer($buf);
					$pk = match ($pid) {
						self::ID_ACK => new ACK(),
						0xA0 => new NACK(), // ID_NACK
						default => new Datagram(),
					};
					$pk->decode($s);
					$this->handlePacket($pk);
				}
			}
			$this->update(microtime(true));
		} catch (SocketException|\ValueError $e) {
			$this->getLogger()->debug("Backend packet reading error: " . $e->getMessage());
			$this->player->disconnect(TranslationFactory::translate("proxy.backend.read_error", [Uuid::uuid4()->toString()]));

			$this->getLogger()->warning("Backend '{$this->player->getBackendServer()?->getName()}' down, redirecting to fallback");
			$this->player->tryFallbackOrDisconnect();
		} catch (\Throwable $e) {
			$this->getLogger()->logException($e);
		}
	}

	public function close() : void
	{
		$this->socket->close();
	}

	public function disconnect() : void
	{
		$this->initiateDisconnect(DisconnectReason::CLIENT_DISCONNECT);
	}

	protected function sendPacket(Packet $packet) : void
	{
		$this->sendRaw($packet);
	}

	protected function onPacketAck(int $identifierACK) : void
	{
	}

	protected function onDisconnect(int $reason) : void
	{
		$this->close();
	}

	protected function handleRakNetConnectionPacket(string $packet) : void
	{
		$id = ord($packet[0]);
		if ($id === MessageIdentifiers::ID_CONNECTION_REQUEST_ACCEPTED && $this->connState === ConnectionState::CONNECTING_3) {
			$this->connState = ConnectionState::CONNECTED;
			$this->state = self::STATE_CONNECTED;

			$this->sendNewIncomingConnection();
			$this->sendNetworkSettingsRequest();
			$this->connState = ConnectionState::GAME_HANDSHAKE;
		}
	}

	protected function onPacketReceive(string $packet) : void
	{
		$id = ord($packet[0]);
		if ($id === RakLibInterface::MCPE_RAKNET_PACKET_ID_BYTE) {
			if ($this->connState === ConnectionState::GAME_HANDSHAKE) {
				foreach (PacketBatchDecoder::decodeRaw($packet, $this->getLogger(), false) as $buffer) {
					$pk = PacketPool::getInstance()->getPacket($buffer);
					if ($pk instanceof DataPacket) {
						$pk->decode(new ByteBufferReader($buffer), $this->player->getProtocol());
						if ($pk instanceof NetworkSettingsPacket) {
							$this->compressionEnabled = true;
							$this->connState = ConnectionState::LOGGED_IN;

							$this->encodeAndSend($this->player->getLoginPacket());

							foreach ($this->sendQueue as $p) {
								$this->encodeAndSend($p);
							}

							$this->sendQueue = [];
							return;
						}

						$this->player->handleBackendPacket($pk);
					}
				}

				return;
			}

			foreach (PacketBatchDecoder::decodeRaw($packet, $this->getLogger(), $this->compressionEnabled) as $buffer) {
				$pk = PacketPool::getInstance()->getPacket($buffer);
				if ($pk instanceof DataPacket) {
					$pk->decode(new ByteBufferReader($buffer), $this->player->getProtocol());
					$this->player->handleBackendPacket($pk);
				}
			}
		}
	}

	protected function onPingMeasure(int $pingMS) : void
	{
		$this->player->getNetworkSession()->setPing($pingMS);
	}

	private function handleInternalPacket(string $buf) : void
	{
		$s = new PacketSerializer($buf);
		$pid = ord($buf[0]);

		match ($pid) {
			MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_1 => $this->handleOpenConnectionReply1($s),
			MessageIdentifiers::ID_OPEN_CONNECTION_REPLY_2 => $this->handleOpenConnectionReply2($s),
			default => null,
		};
	}

	private function handleOpenConnectionReply1(PacketSerializer $s) : void
	{
		$pk = new OpenConnectionReply1();
		$pk->decode($s);
		$this->mtu = min($this->mtu, $pk->mtuSize);
		$this->rakCookie = $pk->cookie;
		$this->sendRequest2();
	}

	private function handleOpenConnectionReply2(PacketSerializer $s) : void
	{
		$pk = new OpenConnectionReply2();
		$pk->decode($s);
		$this->mtu = $pk->mtuSize;
		$this->sendConnectionRequest();
	}

	protected function sendUnconnectedPing() : void
	{
		$pk = new UnconnectedPing();
		$pk->sendPingTime = (int) (microtime(true) * 1000);
		$pk->clientId = $this->getID();
		$this->sendRawPacket($pk);
		$this->sendRequest1();
	}

	private function sendRequest1() : void
	{
		$pk = new OpenConnectionRequest1();
		$pk->protocol = RakLibInterface::RAKNET_PROTOCOL_VERSION;
		$pk->mtuSize = $this->mtu;
		$this->sendRawPacket($pk);
		$this->connState = ConnectionState::CONNECTING_1;
	}

	private function sendRequest2() : void
	{
		$packet = new OpenConnectionRequest2();
		$packet->clientID = $this->getID();
		$packet->serverAddress = new InternetAddress("127.0.0.1", $this->getAddress()->getPort(), 4);
		$packet->mtuSize = $this->mtu;

		if ($this->rakCookie !== null) {
			$packet->cookie = $this->rakCookie;
		}

		$this->sendRawPacket($packet);
		$this->connState = ConnectionState::CONNECTING_2;
	}

	private function sendNewIncomingConnection() : void
	{
		$address = $this->player->getServer()->getAddress();
		if (empty($address) || $address === "0.0.0.0") {
			$address = "127.0.0.1";
		}

		$port = $this->player->getServer()->getPort();

		$packet = new NewIncomingConnection();
		$packet->address = new InternetAddress($address, $port, 4);

		$internalAddr = new InternetAddress("127.0.0.1", 0, 4);
		$packet->systemAddresses = [];
		for ($i = 0; $i < 10; ++$i) {
			$packet->systemAddresses[] = $internalAddr;
		}

		$ping = (int) (microtime(true) * 1000);
		$packet->sendPingTime = $ping;
		$packet->sendPongTime = $ping;

		$this->sendEncapsulated($packet);
	}

	private function sendConnectionRequest() : void
	{
		$pk = new ConnectionRequest();
		$pk->clientID = $this->getID();
		$pk->sendPingTime = (int) (microtime(true) * 1000);
		$pk->useSecurity = true;
		$this->sendEncapsulated($pk, PacketReliability::RELIABLE_ORDERED, 0, true);
		$this->connState = ConnectionState::CONNECTING_3;
	}

	private function sendNetworkSettingsRequest() : void
	{
		$packet = RequestNetworkSettingsPacket::create($this->player->getProtocol());
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $this->player->getProtocol());

		$payload = $writer->getData();

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID . $batch;

		$this->sendEncapsulatedRaw($final);
	}

	private function encodeAndSend(DataPacket $packet) : void
	{
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $this->player->getProtocol());
		$this->sendBatch($writer->getData());
	}

	private function sendBatch(string $payload) : void
	{
		if (empty($payload)) return;

		$header = Binary::writeUnsignedVarInt(strlen($payload));
		$batch = $header . $payload;

		$finalPayload = "\x00" . ZlibCompressor::getInstance()->compress($batch);
		$final = RakLibInterface::MCPE_RAKNET_PACKET_ID . $finalPayload;

		$this->sendEncapsulatedRaw($final);
	}

	private function sendEncapsulated(Packet $packet, int $reliability = PacketReliability::RELIABLE, int $orderChannel = 0, bool $immediate = false) : void
	{
		$s = new PacketSerializer();
		$packet->encode($s);
		$this->sendEncapsulatedRaw($s->getBuffer(), $reliability, $orderChannel, $immediate);
	}

	private function sendEncapsulatedRaw(string $payload, int $reliability = PacketReliability::RELIABLE, int $orderChannel = 0, bool $immediate = false) : void
	{
		$encapsulated = new EncapsulatedPacket();
		$encapsulated->reliability = $reliability;
		$encapsulated->orderChannel = $orderChannel;
		$encapsulated->buffer = $payload;
		$this->addEncapsulatedToQueue($encapsulated, $immediate);
	}

	private function sendRawPacket(Packet $packet) : void
	{
		$this->sendRaw($packet);
	}

	private function sendRaw(Packet|string $packet) : void
	{
		if ($packet instanceof Packet) {
			$s = new PacketSerializer();
			$packet->encode($s);
			$buffer = $s->getBuffer();
		} else {
			$buffer = $packet;
		}
		$this->socket->writePacket($buffer);
	}
}
