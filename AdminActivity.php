<?php
require_once './lib/SimpleCache.php';
require_once './lib/MediawikiConst.php';

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

	/**
	 * Admin list.
	 *
	 * @return Admin list with placeholder values for stats.
	 */
	public function getAdmins() {
		$query = "SELECT user_id, actor_id, actor_name 
				FROM user 
				INNER JOIN actor ON user_id = actor_user
				WHERE user_id IN (
					SELECT ug_user FROM user_groups WHERE ug_group = 'sysop'
				)
				ORDER BY user_id";
				//ORDER BY actor_name";
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
				'delete' => 0,
				'block' => 0,
				'protect' => 0,
				'other' => 0,
				'mediawiki_edits' => 0,
				'main_edits' => 0,
				'total' => 0
			];
		}
		return $admins;
	}

	/**
	 * Get any actor with admin-like data (good for ex-admins).
	 * 
	 * @return Same list as for `getAdmins()`.
	 */
	public function getActor($username) {
		$query = "SELECT user_id, actor_id, actor_name 
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

		return $this->fetchAndPrepareAdmins($result);
	}

	public function getMediaWikiEdits($admins, $days=365) {
		return $this->getNamespaceEdits($admins, MediawikiConst::NS_MEDIAWIKI, $days);
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
			$data = $this->getNamespaceEdits($admins, MediawikiConst::NS_MAIN, $days);
			$cache->set($data);
		} else {
			$data = $this->getNamespaceEdits($admins, MediawikiConst::NS_MAIN, $days);
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
	
		// Note! revision_userindex might be MUCH faster (e.g. 0.28 seconds vs 86.32 seconds for a single actor)
		$query = "SELECT rev_actor as revactor_actor, count(*) as cnt
				FROM revision_userindex
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

	/** Standard admin actions: delete, block, protect. */
	public function getAdminActions($admins, $days=365) {
		if (!is_int($days) || $days < 0) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($admins), '?'));
		$timestamp = date('YmdHis', strtotime("-$days days")); // Generate timestamp in PHP

		// base actions
		$query = <<<EOS
				SELECT log_actor, log_type
					, count(*) as cnt
					, LEFT(min(log_timestamp), 8) as min_t
					, LEFT(max(log_timestamp), 8) as max_t
					, min(log_id) as min_id, max(log_id) as max_id
				FROM logging_userindex
				WHERE log_actor IN ($placeholders)
					AND log_timestamp >= ?
					AND (
						log_type IN ('block', 'protect')
						OR (log_type = 'delete' AND (log_action IS NULL OR log_action != 'delete_redir'))
					)
				GROUP BY log_type, log_actor
		EOS;
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
				$data[$actor_id] = ['delete' => 0, 'block' => 0, 'protect' => 0, 'other' => 0];
			}
			//if ( in_array($row['log_type'], ['delete', 'block', 'protect']) ) {
			$data[$actor_id][$row['log_type']] = $count;
		}

		// other
		$query = <<<EOS
				SELECT log_actor
					, count(*) as cnt
					, LEFT(min(log_timestamp), 8) as min_t
					, LEFT(max(log_timestamp), 8) as max_t
					, min(log_id) as min_id, max(log_id) as max_id
				FROM logging_userindex
				WHERE log_actor IN ($placeholders)
					AND log_timestamp >= ?
					AND (
						log_type IN ('abusefilter', 'contentmodel', 'gblblock', 'managetags')
						OR log_action IN ('patrol', 'rights', 'setmentor', 'merge')
						OR (log_type = 'massmessage' AND log_action = 'send')
						OR (log_type = 'tag' AND log_action = 'update')
						OR (log_action = 'move'  AND log_params LIKE '%s:10:"5::noredir";s:1:"1"%')
					)
				GROUP BY log_actor
		EOS;
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param($types, ...$params);
		$stmt->execute();
		$result = $stmt->get_result();

		while ($row = $result->fetch_assoc()) {
			$actor_id = $row['log_actor'];
			$count = $row['cnt'];
			if (!isset($data[$actor_id])) {
				$data[$actor_id] = ['delete' => 0, 'block' => 0, 'protect' => 0, 'other' => 0];
			}
			$data[$actor_id]['other'] = $count;
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
			$sum = 0;
			if (isset($mwEdits[$actor_id])) {
				$admin['mediawiki_edits'] = $mwEdits[$actor_id];
				$sum += $mwEdits[$actor_id];
			}
			if (isset($mainEdits[$actor_id])) {
				$admin['main_edits'] = $mainEdits[$actor_id];
			}
			if (isset($adminActions[$actor_id])) {
				foreach($adminActions[$actor_id] as $key => $value) {
					$admin[$key] = $value;
					$sum += $value;
				}
			}
			$admin['total'] = $sum;
		}

		return $admins;
	}

	public function getSingleAdminStats($username) {
		$admins = $this->getActor($username);
		return $this->getBasicAdminStats($admins);
	}

	public function renderTable($data) {
		$is_single = count($data) == 1;
		$name_head = ($is_single) ? "User" : "Admin";
		$html = <<<EOS
			<table class='wikitable sortable' border='1'>
			<thead>
				<tr>
					<th class="user-id" title='user_id'>UID</th>
					<th class="actor-id" title='actor_id'>AID</th>
					<th>{$name_head}</th>
					<th>Usuwanie / Przywracanie</th>
					<th>(Od)blokowanie osób</th>
					<th>(Od)blokowanie stron</th>
					<th title='Inne akcje administracyjne zapisane w logach'>Inne logowane</th>
					<th title='Edycje w przestrzeni nazw MediaWiki'>Edycje MW</th>
					<th>Suma akcji</th>
					<th title='Edycje w głównej przestrzeni nazw (ns:0)'>Edycje artykułów</th>
				</tr>
			</thead>
			<tbody>
		EOS;
		foreach ($data as $actor_id => $admin) {
			if ($is_single) {
				$name_cell = $admin['admin'];
			} else {
				$detUrl = "index.php?" . http_build_query(['action' => 'details', 'username' => $admin['admin']], '', '&amp;');
				$name_cell = "<a href='{$detUrl}' class='user-link main'>{$admin['admin']}</a>";
			}

			$html .= <<<EOS
				<tr>
					<td class="user-id">{$admin['uid']}</td>
					<td class="actor-id">{$actor_id}</td>
					<td>{$name_cell}</td>
					<td>{$admin['delete']}</td>
					<td>{$admin['block']}</td>
					<td>{$admin['protect']}</td>
					<td>{$admin['other']}</td>
					<td>{$admin['mediawiki_edits']}</td>
					<td>{$admin['total']}</td>
					<td>{$admin['main_edits']}</td>
				</tr>
			EOS;
		}
		$html .= "</tbody></table>";
		return $html;
	}
}
