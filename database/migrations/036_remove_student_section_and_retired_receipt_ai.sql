-- Student section is not a required roster field; existing school records use N/A.
-- Apply with the corresponding application changes after taking a private backup.
ALTER TABLE students DROP COLUMN section;

-- AI receipt verification was retired; Accounting uses manual assessment review.
-- Keep accounting_receipts and all current assessment, payment and audit records.
DROP TABLE IF EXISTS accounting_receipt_verifications;
