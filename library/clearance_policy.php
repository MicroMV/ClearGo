<?php
declare(strict_types=1);

function libraryBooksReturnedSql(string $studentAlias): string
{
    return "NOT EXISTS (SELECT 1 FROM borrowed_books book WHERE book.student_id={$studentAlias}.id AND book.status='BORROWED')";
}

function libraryOutstandingBooks(PDO $database, int $studentId, bool $lock = false): array
{
    $query = $database->prepare("SELECT id,book_title,borrowed_at,due_at FROM borrowed_books WHERE student_id=:student_id AND status='BORROWED' ORDER BY borrowed_at,id" . ($lock ? ' FOR UPDATE' : ''));
    $query->execute(['student_id' => $studentId]);
    return $query->fetchAll();
}

function requireLibraryBooksReturned(PDO $database, int $studentId): void
{
    if (!$database->inTransaction()) { throw new LogicException('Book returns must be checked in the clearance transaction.'); }
    $books = libraryOutstandingBooks($database, $studentId, true);
    if ($books !== []) {
        throw new RuntimeException('Return all borrowed books before Library clearance. Unreturned books: ' . count($books) . '.');
    }
}
