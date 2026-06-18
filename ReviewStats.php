<?php
require_once './lib/TablePrinter.php';

/**
 * Review stats (przeglądanie zmian).
 */
class ReviewStats {
	private $conn;
	/** User or project timezone. */
	public $timeZone = 'Europe/Paris';

	public function __construct($dbConfig) {
		// $host = "plwiki.analytics.db.svc.wikimedia.cloud";
		// $database = "plwiki_p";
		$host = $dbConfig['host'];
		$database = $dbConfig['database'];
		$user = $dbConfig['user'];
		$password = $dbConfig['password'];

		$this->conn = new mysqli($host, $user, $password, $database);
		if ($this->conn->connect_error) {
			die("ERROR: Connection failed: " . $this->conn->connect_error);
		}
	}

	private function sqlError() {
		die("<p>ERROR: Prepare failed: " . $this->conn->error); // Output MySQL error
	}

	/**
	 * Get user and actor data.
	 *
	 * @param string $username
	 * @return array|null Assoc. record with: user_id, actor_id, actor_name.
	 */
	public function getUserData($username) {
		$query = "
			SELECT user_id, actor_id, actor_name
			FROM user
			INNER JOIN actor ON user_id = actor_user
			WHERE actor_name = ?
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$stmt->close();
		return $row;
	}

	/**
	 * Convert a date/time string to a MediaWiki timestamp (uses timeZone).
	 *
	 * @param string $relativeStr Date/time string accepted by DateTime.
	 * Examples:
	 * - 'today'
	 * - 'today -14 days'
	 * 
	 * @return string MediaWiki timestamp in UTC.
	 */
	public function toWikiTimestamp($relativeStr) {
		$dt = new DateTime($relativeStr, new DateTimeZone($this->timeZone));
		$dt->setTimezone(new DateTimeZone('UTC'));
		return $dt->format('YmdHis');
	}

	/**
	 * Get review stats for an actor-user.
	 *
	 * @param string $username
	 * @param int $days
	 * @return array List per day, per namespace.
	 */
	public function getStats($username, $days = 14) {
		$days = intval($days);
		if ($days <= 0) $days = 7;

		$userData = $this->getUserData($username);
		if (!$userData) {
			return [];
		}

		// UTC offset
		$offsetHours = (new DateTime('now', new DateTimeZone($this->timeZone)))->getOffset() / 3600;

		// time boundary in UTC time
		$minTimestamp = $this->toWikiTimestamp("today -$days days");

		$query = "SELECT
				log_namespace AS ns
				, DATE_FORMAT(
					DATE_ADD(STR_TO_DATE(log_timestamp, '%Y%m%d%H%i%s'), INTERVAL $offsetHours HOUR),
					'%Y-%m-%d'
				) AS day
				, COUNT(*) AS review_count_total
				, SUM(log_action = 'approve') AS review_count_changes
				, SUM(log_action = 'approve-i') AS review_count_initial
			FROM logging
			WHERE log_type = 'review'
				AND (log_action = 'approve' OR log_action = 'approve-i')
				AND log_timestamp >= $minTimestamp
				AND log_actor = ?
			GROUP BY log_namespace, day
			HAVING review_count_total > 0
			ORDER BY log_namespace, day
		";

		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param("i", $userData['actor_id']);
		$stmt->execute();

		$result = $stmt->get_result();
		$rows = [];
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

		$stmt->close();

		return $rows;
	}

	/**
	 * Render stats data to an HTML table.
	 *
	 * @param array $data Data from `getStats`.
	 * @return Rendered html.
	 */
	public function renderStatsTable($data) {
		$columns = [
			['_cell' => 'NS', 'title' => 'Przestrzeń nazw (namespace)'],
			['_cell' => 'Dzień'],
			['_cell' => 'Razem', 'title' => 'Wszystkie przejrzane', 'class' => 'count-total'],
			['_cell' => 'Zmienione', 'title' => "Przejrzenie zmian na stronach", 'class' => 'count-changed'],
			['_cell' => 'Nowe', 'title' => "Przejrzenie nowych stron", 'class' => 'count-initial'],
		];
		$mapping = [
			'ns' => [],
			'day' => [],
			'review_count_total' => ['class' => 'count-total'],
			'review_count_changes' => ['class' => 'count-changed'],
			'review_count_initial' => ['class' => 'count-initial'],
		];

		// render
		$printer = new TablePrinter($mapping);
		$head = $printer->renderHead($columns);
		$html = <<<EOS
			<table class='wikitable sortable' border='1'>
			<thead>{$head}</thead>
			<tbody>
		EOS;
		foreach ($data as $admin) {
			$html .= $printer->renderRow($admin);
		}
		$html .= "</tbody></table>";
		return $html;
	}
}
