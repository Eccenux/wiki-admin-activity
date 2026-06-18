<?php

/**
 * Connection singleton.
 */
class DbConnection {
	private static $conn = null;

	private function __construct() {}

	/**
	 * Get shared mysqli connection.
	 *
	 * @return mysqli
	 */
	public static function getConnection($dbConfig) {
		if (self::$conn === null) {
			self::$conn = new mysqli(
				$dbConfig['host'],
				$dbConfig['user'],
				$dbConfig['password'],
				$dbConfig['database']
			);

			if (self::$conn->connect_error) {
				die("ERROR: Connection failed: " . self::$conn->connect_error);
			}
		}

		return self::$conn;
	}
}