<?php

namespace pocketmine;

use raklib\protocol\EncapsulatedPacket;
use raklib\RakLib;
use pocketmine\network\protocol\DataPacket;
use pocketmine\network\proxylib\ProxyServer;
use pocketmine\network\ProxyInterface;

class ChunkMaker extends Thread {

	protected $classLoader;
	protected $shutdown;
	protected $internalQueue;
	protected $raklib;

	public function __construct(\ClassLoader $loader, $raklib) {
		$this->internalQueue = new \Threaded;
		$this->shutdown = false;
		$this->classLoader = $loader;
		$this->raklib = $raklib;
		$this->start(PTHREADS_INHERIT_CONSTANTS);
	}

	public function join() {
		$this->shutdown = true;
		parent::join();
	}

	public function run() {
		$this->registerClassLoader();
		gc_enable();
		ini_set("memory_limit", -1);
		ini_set("display_errors", 1);
		ini_set("display_startup_errors", 1);

		set_error_handler([$this, "errorHandler"], E_ALL);
		DataPacket::initPackets();
		new ChunkStorage($this);
	}

	public function sendData($data, $buffer) {
		if ($this->raklib instanceof ProxyServer) {
			$infoData = chr(ProxyInterface::PLAYER_PACKET_ID) . pack('N', $data['proxySessionId']) . chr(ProxyInterface::STANDART_PACKET_ID) . $buffer;
			$info = chr(strlen($data['proxyId'])) . $data['proxyId'] . $infoData;
			$this->raklib->writeToProxyServer($info);
		} else {
			$identifier = $data['identifier'];
			$pk = new EncapsulatedPacket();
			$pk->buffer = $buffer;
			$pk->reliability = 3;
			$enBuffer = chr(RakLib::PACKET_ENCAPSULATED) . chr(strlen($identifier)) . $identifier . chr(RakLib::PRIORITY_NORMAL) . $pk->toBinary(true);
			$this->raklib->pushMainToThreadPacket($enBuffer);
		}
	}

	public function pushMainToThreadPacket($data) {
		$this->internalQueue[] = $data;
	}

	public function readMainToThreadPacket() {
		return $this->internalQueue->shift();
	}

	public function isShutdown() {
		return $this->shutdown;
	}

}
