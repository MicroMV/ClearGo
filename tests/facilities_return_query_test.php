<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$source = file_get_contents(__DIR__ . '/../clearance/office_action.php');
if (!preg_match('/\$database->prepare\("(UPDATE facilities_obligations[^"\r\n]+)"\)/', $source, $match)) {
    throw new RuntimeException('Facilities return query not found.');
}
$database = getDatabaseConnection();
// Use a temporary table so the production query runs without changing student records.
$database->exec("CREATE TEMPORARY TABLE facilities_obligations (id INT, student_id INT, academic_period_id INT, status VARCHAR(20), remarks VARCHAR(1000), returned_by INT, returned_at DATETIME)");
try {
    $database->exec("INSERT INTO facilities_obligations VALUES (1,1,1,'OUTSTANDING','Original note',NULL,NULL), (2,1,1,'OUTSTANDING','Original note',NULL,NULL)");
    $statement = $database->prepare($match[1]);
    foreach ([1 => '', 2 => 'Returned in good condition'] as $id => $remarks) {
        $statement->execute(['remarks' => $remarks, 'user_id' => 1, 'id' => $id, 'student_id' => 1, 'period_id' => 1]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Item not returned.');
    }
    $records = $database->query('SELECT * FROM facilities_obligations ORDER BY id')->fetchAll();
    if ($records[0]['remarks'] !== 'Original note' || $records[1]['remarks'] !== 'Returned in good condition') {
        throw new RuntimeException('Return notes were not handled correctly.');
    }
    foreach ($records as $record) {
        if ($record['status'] !== 'RETURNED' || !$record['returned_at']) throw new RuntimeException('Return status or date missing.');
    }
    $statement->execute(['remarks' => '', 'user_id' => 1, 'id' => 1, 'student_id' => 1, 'period_id' => 1]);
    if ($statement->rowCount() !== 0) throw new RuntimeException('An already returned item was updated.');
    echo "Facilities return query checks passed.\n";
} finally {
    $database->exec('DROP TEMPORARY TABLE facilities_obligations');
}
