<?php
require_once './lib/SimpleCache.php';
require_once './lib/MediawikiConst.php';
require_once './lib/TablePrinter.php';
require_once './lib/DbConnection.php';

class AdminActivity {
	private $conn;

	public function __construct($dbConfig) {
		$this->conn = DbConnection::getConnection($dbConfig);

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
	 * Admin list.
	 *
	 * @return Admin list with placeholder values for stats.
	 * 	Crucially keys are `actor_id` (crucially for other functions).
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
				'aid' => $row['actor_id'],
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
	 * @return Same list as for `getAdmins()`, but with just one record.
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

	/**
	 * Get edit count from the Mediawiki namespace (interface).
	 *
	 * @param array $actorIds 
	 * @param integer $days
	 * @return array $actor_id => count.
	 */
	public function getMediaWikiEdits($actorIds, $days=365) {
		return $this->getNamespaceEdits($actorIds, MediawikiConst::NS_MEDIAWIKI, $days);
	}
	/**
	 * Get edit count from the Main namespace (articles).
	 *
	 * @param array $actorIds 
	 * @param integer $days
	 * @return array $actor_id => count.
	 */
	public function getMainEdits($actorIds, $days=365) {
		if (count($actorIds) > 10) {
			// try cache
			$cache = $this->cache['main_edits'];
			$cachedData = $cache->get();
			if ($cachedData !== null) {
				return $cachedData;
			}

			// fresh data (+save in cache)
			$data = $this->getNamespaceEdits($actorIds, MediawikiConst::NS_MAIN, $days);
			$cache->set($data);
		} else {
			$data = $this->getNamespaceEdits($actorIds, MediawikiConst::NS_MAIN, $days);
		}
		return $data;
	}

	/**
	 * Undocumented function
	 *
	 * @param array $actorIds 
	 * @param integer $ns Namespace number.
	 * @param integer $days
	 * @return array $actor_id => count.
	 */
	public function getNamespaceEdits($actorIds, $ns, $days=365) {
		if (!is_int($ns) || $ns < 0) {
			return [];
		}
		if (!is_int($days) || $days < 0) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($actorIds), '?'));
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
		$types = str_repeat('i', count($actorIds)) . 's'; // 's' for timestamp string
		$params = array_merge($actorIds, [$timestamp]);
		$stmt->bind_param($types, ...$params);
		$stmt->execute();
		$result = $stmt->get_result();
	
		$data = [];
		while ($row = $result->fetch_assoc()) {
			$data[$row['revactor_actor']] = $row['cnt'];
		}

		// echo "<pre>"; var_dump([
		// 	'actorIds'=>$actorIds,
		// 	'ns'=>$ns,
		// 	'data'=>$data,
		// 	'query'=>$query,
		// 	'types'=>$types,
		// 	'params'=>$params,
		// ]); echo "</pre>";
	
		return $data;
	}

	/**
	 * Stats of standard admin actions: delete, block, protect.
	 *
	 * @param array $actorIds 
	 * @param integer $days
	 * @return array $actor_id=>stats[] (`$actor_id => ['delete' => 0, 'block' => 0, 'protect' => 0, 'other' => 0]`).
	 */
	public function getAdminActions($actorIds, $days=365) {
		if (!is_int($days) || $days < 0) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($actorIds), '?'));
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
		$types = str_repeat('i', count($actorIds)) . 's'; // 's' for timestamp string
		$params = array_merge($actorIds, [$timestamp]);
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

		// other (Inne akcje administracyjne zapisane w logach)
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

	/**
	 * Full data of all administrators.
	 *
	 * @return array Full data for all current administrators (ids, name and all stats).
	 */
	public function getAdminStats($days=365) {
		$admins = $this->getAdmins();
		return $this->getBasicAdminStats($admins, $days);
	}
	private function getBasicAdminStats($admins, $days=365) {
		$actorIds = array_keys($admins);
		$mwEdits = $this->getMediaWikiEdits($actorIds, $days);
		$mainEdits = $this->getMainEdits($actorIds, $days);
		$adminActions = $this->getAdminActions($actorIds, $days);

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

	/**
	 * Get stats of a single user (possibly an admin in the past).
	 *
	 * @param string $username
	 * @return array Full data for the user (ids, name and all stats) for each $months.
	 */
	public function getSingleAdminStats($username, $months=[1,6,12]) {
		$adminsRaw = $this->getActor($username);
		if (count($adminsRaw) < 1) {
			return [];
		}
		$actor_id = array_keys($adminsRaw)[0];
		foreach ($months as $m) {
			$days = intval(round(365/12 * $m));
			$admins = $adminsRaw;
			$row = $this->getBasicAdminStats($admins, $days);
			$record = $row[$actor_id];
			$record['months'] = $m;
			$data[] = $record;
		}
		return $data;
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
