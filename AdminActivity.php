<?php
require_once './SimpleCache.php';

class AdminActivity {
	private $conn;

	public function __construct($dbConfig) {
		// $host = "plwiki.analytics.db.svc.wikimedia.cloud";
		// $database = "plwiki_p";
		$host = $dbConfig['host'];
		$database = $dbConfig['database'];
		$user = $dbConfig['user'];
		$password = $dbConfig['password'];

		$this->conn = new mysqli($host, $user, $password, $database);
		if ($this->conn->connect_error) {
			die("Connection failed: " . $this->conn->connect_error);
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
		die("<p>Prepare failed: " . $this->conn->error); // Output MySQL error
	}
	public function getAdmins() {
		$query = "SELECT user_id, actor_id, actor_name 
				FROM user 
				INNER JOIN actor ON user_id = actor_user
				WHERE user_id IN (
					SELECT ug_user FROM user_groups WHERE ug_group = 'sysop'
				)
				ORDER BY actor_name";
		$result = $this->conn->query($query);
		if (!$result) {
			$this->sqlError();
		}

		return $this->fetchAndPrepareAdmins($result);
	}
	private function fetchAndPrepareAdmins($result) {
		$admins = [];
		while ($row = $result->fetch_assoc()) {
			$admins[$row['actor_id']] = [
				'uid' => $row['user_id'],
				'admin' => $row['actor_name'],
				'delete_restore' => 0,
				'block_unblock_users' => 0,
				'protect_unprotect' => 0,
				'mediawiki_edits' => 0,
				'main_edits' => 0,
				'total' => 0
			];
		}
		return $admins;
	}
	/** Get any actor by same structure as for admins (good for ex-admins). */
	public function getActor($username) {
		$query = "SELECT user_id, actor_id, actor_name 
				FROM user 
				INNER JOIN actor ON user_id = actor_user
				WHERE actor_name LIKE ?
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();

		return $this->fetchAndPrepareAdmins($result);
	}

	public function getMediaWikiEdits($admins, $days=365) {
		return $this->getNamespaceEdits($admins, 8, $days);
	}
	public function getMainEdits($admins, $days=365) {
		if (count($admins) > 10) {
			// try cache
			$cache = $this->cache['main_edits'];
			$cachedData = $cache->get();
			if ($cachedData !== null) {
				return $cachedData;
			}

			// fresh data (+save in cache)
			$data = $this->getNamespaceEdits($admins, 0, $days);
			$cache->set($data);
		} else {
			$data = $this->getNamespaceEdits($admins, 0, $days);
		}
		return $data;
	}

	public function getNamespaceEdits($admins, $ns, $days=365) {
		if (!is_int($ns) || $ns < 0) {
			return [];
		}
		if (!is_int($days) || $days < 0) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($admins), '?'));
		$timestamp = date('YmdHis', strtotime("-$days days")); // Generate timestamp in PHP
	
		$query = "SELECT rev_actor as revactor_actor, count(*) as cnt
				FROM revision
				LEFT JOIN page ON rev_page = page_id
				WHERE page_namespace = $ns
					AND rev_actor IN ($placeholders)
					AND rev_timestamp >= ?
				GROUP BY rev_actor
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$types = str_repeat('i', count($admins)) . 's'; // 's' for timestamp string
		$params = array_merge(array_keys($admins), [$timestamp]);
		$stmt->bind_param($types, ...$params);
		$stmt->execute();
		$result = $stmt->get_result();
	
		$data = [];
		while ($row = $result->fetch_assoc()) {
			$data[$row['revactor_actor']] = $row['cnt'];
		}
	
		return $data;
	}

	public function getAdminActions($admins, $days=365) {
		if (!is_int($days) || $days < 0) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($admins), '?'));
		$timestamp = date('YmdHis', strtotime("-$days days")); // Generate timestamp in PHP

		$query = "SELECT log_actor, log_type, count(*) as cnt
				FROM logging
				WHERE log_type IN ('delete', 'block', 'protect')
					AND log_actor IN ($placeholders)
					AND log_timestamp >= ?
				GROUP BY log_type, log_actor
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$types = str_repeat('i', count($admins)) . 's'; // 's' for timestamp string
		$params = array_merge(array_keys($admins), [$timestamp]);
		$stmt->bind_param($types, ...$params);
		$stmt->execute();
		$result = $stmt->get_result();

		$data = [];
		while ($row = $result->fetch_assoc()) {
			$actor_id = $row['log_actor'];
			$count = $row['cnt'];
			if (!isset($data[$actor_id])) {
				$data[$actor_id] = ['delete_restore' => 0, 'block_unblock_users' => 0, 'protect_unprotect' => 0];
			}
			if ($row['log_type'] == 'delete') {
				$data[$actor_id]['delete_restore'] = $count;
			} elseif ($row['log_type'] == 'block') {
				$data[$actor_id]['block_unblock_users'] = $count;
			} elseif ($row['log_type'] == 'protect') {
				$data[$actor_id]['protect_unprotect'] = $count;
			}
		}
		return $data;
	}

	public function getAdminStats() {
		$admins = $this->getAdmins();
		return $this->getBasicAdminStats($admins);
	}
	private function getBasicAdminStats($admins) {
		$mwEdits = $this->getMediaWikiEdits($admins);
		$mainEdits = $this->getMainEdits($admins);
		$adminActions = $this->getAdminActions($admins);

		foreach ($admins as $actor_id => &$admin) {
			if (isset($mwEdits[$actor_id])) {
				$admin['mediawiki_edits'] = $mwEdits[$actor_id];
			}
			if (isset($mainEdits[$actor_id])) {
				$admin['main_edits'] = $mainEdits[$actor_id];
			}
			if (isset($adminActions[$actor_id])) {
				$admin['delete_restore'] = $adminActions[$actor_id]['delete_restore'];
				$admin['block_unblock_users'] = $adminActions[$actor_id]['block_unblock_users'];
				$admin['protect_unprotect'] = $adminActions[$actor_id]['protect_unprotect'];
			}
			$admin['total'] = $admin['delete_restore'] + $admin['block_unblock_users'] + $admin['protect_unprotect'] + $admin['mediawiki_edits'];
		}

		return $admins;
	}

	public function getSingleAdminStats($username) {
		$admins = $this->getActor($username);
		return $this->getBasicAdminStats($admins);
	}

	public function renderTable($data) {
		$html = "<table class='wikitable sortable' border='1'>
			<thead>
				<tr>
					<th title='user_id'>UID</th>
					<th title='actor_id'>AID</th>
					<th>Admin</th>
					<th>Usuwanie / Przywracanie</th>
					<th>(Od)blokowanie osób</th>
					<th>(Od)blokowanie stron</th>
					<th title='Edycje w przestrzeni nazw MediaWiki'>Edycje MW</th>
					<th>Suma akcji</th>
					<th title='Edycje w głównej przestrzeni nazw (ns:0)'>Edycje artykułów</th>
				</tr>
			</thead>
			<tbody>
		";
		foreach ($data as $actor_id => $admin) {
			$detUrl = "index.php?" . http_build_query(['action' => 'details', 'username' => $admin['admin']], '', '&amp;');
			$html .= "
				<tr>
					<td>{$admin['uid']}</td>
					<td>{$actor_id}</td>
					<td><a href='{$detUrl}'>{$admin['admin']}</a></td>
					<td>{$admin['delete_restore']}</td>
					<td>{$admin['block_unblock_users']}</td>
					<td>{$admin['protect_unprotect']}</td>
					<td>{$admin['mediawiki_edits']}</td>
					<td>{$admin['total']}</td>
					<td>{$admin['main_edits']}</td>
				</tr>
			";
		}
		$html .= "</tbody></table>";
		return $html;
	}
}
