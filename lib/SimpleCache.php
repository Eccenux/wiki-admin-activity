<?php
class SimpleCache {
	private $cacheFile;
	private $expirationTime;

	public function __construct($cacheFile, $minutes = 1440) {
		$this->cacheFile = $cacheFile;
		$this->expirationTime = $minutes * 60; // Convert minutes to seconds
	}

	public function get() {
		if (file_exists($this->cacheFile)) {
			$cacheData = json_decode(file_get_contents($this->cacheFile), true);
			if ($cacheData && isset($cacheData['timestamp']) && $cacheData['timestamp'] > time() - $this->expirationTime) {
				return $cacheData['data'];
			}
		}
		return null;
	}

	public function set($data) {
		file_put_contents($this->cacheFile, json_encode([
			'timestamp' => time(),
			'data' => $data
		]));
	}
}
