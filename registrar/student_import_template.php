<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/student_import_functions.php';
requireRole('REGISTRAR');

$period = getDatabaseConnection()->query("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' ORDER BY id LIMIT 1")->fetch();
$periodTitle = $period === false
    ? 'ACADEMIC YEAR 2026-2027 1ST SEMESTER'
    : 'ACADEMIC YEAR ' . $period['school_year'] . ' ' . $period['semester'] . ' SEMESTER';

header('Cache-Control: no-store');
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="cleargo-student-import-template.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    fputcsv($output, [$periodTitle]);
    fputcsv($output, []);
    fputcsv($output, ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address']);
    fputcsv($output, ['1', 'C-2026-0889', 'Gogolin, Ella Victoria, Dawis', 'ACT - AppDev', 'First Year', 'ellavictoriagogolin@thelewiscollege.edu.ph']);
    fclose($output);
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="cleargo-student-import-template.xlsx"');
echo createStudentImportTemplateXlsx($periodTitle);
