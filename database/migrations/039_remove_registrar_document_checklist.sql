-- Registrar checks documents manually and records missing documents in office remarks.
-- Apply with the matching application update, after confirming both retired tables
-- are empty and saving their structures. Existing remarks, approvals, signatures,
-- requirements, and audit history are preserved.
DROP TABLE IF EXISTS student_documents;
DROP TABLE IF EXISTS document_requirements;
