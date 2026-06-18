<?php
require_once './lib/SimpleCache.php';
require_once './lib/MediawikiConst.php';
require_once './lib/TablePrinter.php';

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

		$day_minutes = 1440; // Cache for 1 day
		$baseDir = "./.cache/";
		if (!is_dir($baseDir)) {
			mkdir($baseDir, 0777, true);
		}
		$this->cache = [
			'main_edits' => new SimpleCache($baseDir . 'main_edits_cache.json', $day_minutes),
		];
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

		// CAST(SUBSTRING(CAST(log_timestamp AS CHAR(14)), 1, 10) AS UNSIGNED) AS dayInt
		$query = "SELECT COUNT(*) AS review_count_total, log_namespace AS ns,
				DATE_FORMAT(
					DATE_ADD(STR_TO_DATE(log_timestamp, '%Y%m%d%H%i%s'), INTERVAL $offsetHours HOUR),
					'%Y%m%d'
				) AS dayInt
			FROM logging
			WHERE log_type = 'review'
				AND (log_action = 'approve' OR log_action = 'approve-i')
				AND log_timestamp >= $minTimestamp
				AND log_actor = ?
			GROUP BY log_namespace, dayInt
			HAVING review_count_total > 0
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
	 * Render admin data to an HTML table.
	 *
	 * @param array $data Full admin data (ids, name and all stats).
	 * @return Rendered html for the admin array.
	 */
	public function renderTable($data, $dataType) {
		$is_single = $dataType === 'details';
		$name_head = ($is_single) ? "User" : "Admin";

		// columns
		$columns = [];
		if ($is_single) {
			$columns = [
				['_cell' => 'L. mies.', 'title' => 'L. miesięcy wstecz.'],
			];
		} else {
			$columns = [
				['_cell' => 'UID', 'class' => 'user-id', 'title' => 'user_id'],
				['_cell' => 'AID', 'class' => 'actor-id', 'title' => 'actor_id'],
				['_cell' => "Admin"],
			];
		}
		$columns[] = ['_cell' => 'Usuwanie / Przywracanie'];
		$columns[] = ['_cell' => '(Od)blokowanie osób'];
		$columns[] = ['_cell' => '(Od)blokowanie stron'];
		$columns[] = ['_cell' => 'Inne logowane (*)', 'title' => 'Inne akcje administracyjne zapisane w logach'];
		$columns[] = ['_cell' => 'Edycje MW', 'title' => 'Edycje w przestrzeni nazw MediaWiki'];
		$columns[] = ['_cell' => 'Suma akcji', 'class' => 'admin-total'];
		$columns[] = ['_cell' => 'Edycje artykułów', 'title' => 'Edycje w głównej przestrzeni nazw (ns:0)'];

		// mapping
		$mapping = [];
		if ($is_single) {
			$mapping['months'] = [];
		} else {
			$mapping['uid'] = ['class' => 'user-id'];
			$mapping['aid'] = ['class' => 'actor-id'];
			$mapping['admin'] = ['_render' => function($value, $row) {
				$url = "index.php?" . http_build_query(['action' => 'details', 'username' => $row['admin']], '', '&amp;');
				$content = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				return "<a href='{$url}' class='user-link main'>{$content}</a>";
			}];
		}
		$mapping['delete'] = [];
		$mapping['block'] = [];
		$mapping['protect'] = [];
		$mapping['other'] = [];
		$mapping['mediawiki_edits'] = [];
		$mapping['total'] = ['class'=>'admin-total'];
		$mapping['main_edits'] = [];

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
