<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'Finotetsidik');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$name = 'teacher_assignments';
$result = $conn->query("SELECT * FROM migrations WHERE migration LIKE '%$name%'");
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo $row['id'] . " | " . $row['migration'] . " | " . $row['batch'] . "\n";
    }
} else {
    echo "No migration found matching '$name'\n";
}

$conn->close();
?>
