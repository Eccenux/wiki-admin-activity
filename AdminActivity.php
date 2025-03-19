<?php
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
		$admins = [];
		while ($row = $result->fetch_assoc()) {
			$admins[$row['actor_id']] = [
				'uid' => $row['user_id'],
				'admin' => $row['actor_name'],
				'delete_restore' => 0,
				'block_unblock_users' => 0,
				'protect_unprotect' => 0,
				'mediawiki_edits' => 0,
				'total' => 0
			];
		}
		return $admins;
	}

	public function getMediaWikiEdits($admins) {
		$placeholders = implode(',', array_fill(0, count($admins), '?'));
		$query = "SELECT rev_actor as revactor_actor, count(*) as cnt
				FROM revision
				LEFT JOIN page ON rev_page = page_id
				WHERE page_namespace = 8
					AND rev_actor IN ($placeholders)
				GROUP BY rev_actor
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param(str_repeat('i', count($admins)), ...array_keys($admins));
		$stmt->execute();
		$result = $stmt->get_result();
	
		$data = [];
		while ($row = $result->fetch_assoc()) {
			$data[$row['revactor_actor']] = $row['cnt'];
		}
	
		return $data;
	}
	
	public function getAdminActions($admins) {
		$placeholders = implode(',', array_fill(0, count($admins), '?'));
		$query = "SELECT log_actor, log_type, count(*) as cnt
				FROM logging
				WHERE log_type IN ('delete', 'block', 'protect')
					AND log_actor IN ($placeholders)
				GROUP BY log_type, log_actor
		";
		$stmt = $this->conn->prepare($query);
		if (!$stmt) {
			$this->sqlError();
		}
		$stmt->bind_param(str_repeat('i', count($admins)), ...array_keys($admins));
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
		$mwEdits = $this->getMediaWikiEdits($admins);
		$adminActions = $this->getAdminActions($admins);

		foreach ($admins as $actor_id => &$admin) {
			if (isset($mwEdits[$actor_id])) {
				$admin['mediawiki_edits'] = $mwEdits[$actor_id];
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

	public function displayTable() {
		$data = $this->getAdminStats();
		echo "<table border='1'>
				<tr>
					<th>UID</th>
					<th>Admin</th>
					<th>Usuwanie / Przywracanie</th>
					<th>(Od)blokowanie Osób</th>
					<th>(Od)blokowanie Stron</th>
					<th>Edycje MW</th>
					<th>Suma</th>
				</tr>";
		foreach ($data as $admin) {
			echo "<tr>
					<td>{$admin['uid']}</td>
					<td>{$admin['admin']}</td>
					<td>{$admin['delete_restore']}</td>
					<td>{$admin['block_unblock_users']}</td>
					<td>{$admin['protect_unprotect']}</td>
					<td>{$admin['mediawiki_edits']}</td>
					<td>{$admin['total']}</td>
				</tr>";
		}
		echo "</table>";
	}
}
