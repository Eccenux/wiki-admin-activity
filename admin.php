<?php
/**
	Aktywność adminów na plwiki.

	Kiedy dany admin/user:
	- wykonał ostatnią edycję w przestrzeni głównej (ns:0).
	- wykonał ostatnią edycję w przestrzeni MediaWiki: (ns:8).
	- wykonał ostatnią logowaną akcję administracyjną (last log action).
	- wykonał ostatnią edycję zablokowanej strony (o ile da się to sprawdził).
*/
echo "<p>Test";

$arrMyCnf = parse_ini_file("../../.my.script.cnf", true);
$arrSrcDb = $arrMyCnf['plwikidb'];

// $arrSrcDb['host'], $arrSrcDb['dbname']
$host = "plwiki.labsdb";
$database = "plwiki_p";
$user = $arrSrcDb['user'];
$password = $arrSrcDb['password'];

$conn = new mysqli($host, $user, $password, $database);
// Check connection
if ($conn->connect_error) {
    die("<p>Błąd połączenia: " . $conn->connect_error);
} else {
    echo "<p>Połączenie poprawne.";
}

$user = 'Nux';

// Ostatnia edycja w ns:0
$query = "SELECT rev_timestamp, rev_page FROM revision_userindex 
		JOIN page ON rev_page = page_id
		WHERE rev_user_text = ? AND page_namespace = 0
		ORDER BY rev_timestamp DESC LIMIT 1";
$stmt = $conn->prepare($query);
if (!$stmt) {
	die("<p>Prepare failed: " . $conn->error); // Output MySQL error
}
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p>Ostatnia edycja w ns:0: " . ($row ? $row['rev_timestamp'] : "Brak") . "\n";

// Ostatnia akcja administracyjna
$query = "SELECT log_timestamp, log_type, log_action, log_title FROM logging_userindex 
		WHERE log_user_text = ?
		ORDER BY log_timestamp DESC LIMIT 1";
$stmt = $conn->prepare($query);
if (!$stmt) {
	die("<p>Prepare failed: " . $conn->error); // Output MySQL error
}
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p>Ostatnia akcja administracyjna: " . ($row ? $row['log_type'] . ' ' . $row['log_action'] . ' na ' . $row['log_title'] : "Brak") . "\n";

$conn->close();
