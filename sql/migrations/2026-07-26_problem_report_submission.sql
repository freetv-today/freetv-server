-- FreeTV public problem-report submission migration
--
-- Apply this migration once to an existing MariaDB database after reviewing
-- duplicate durable reports with:
--
-- SELECT playlist_id, identifier, COUNT(*) AS duplicate_count
-- FROM problem_reports
-- WHERE playlist_id IS NOT NULL
-- GROUP BY playlist_id, identifier
-- HAVING COUNT(*) > 1;
--
-- The unique-key statement below intentionally fails if duplicate durable rows
-- exist. Resolve those rows manually; this migration never deletes or merges
-- problem_reports.
--
-- Existing problem_report_ips rows are preserved, but their link to a durable
-- report is permanently removed. The old reported_at values become attempted_at.

USE freetv;

ALTER TABLE problem_reports
  ADD UNIQUE KEY uq_problem_reports_playlist_identifier (playlist_id, identifier);

ALTER TABLE problem_report_ips
  DROP FOREIGN KEY fk_problem_report_ips_report;

ALTER TABLE problem_report_ips
  DROP INDEX idx_problem_report_ips_report,
  DROP INDEX idx_problem_report_ips_ip,
  DROP COLUMN problem_report_id,
  CHANGE COLUMN reported_at attempted_at DATETIME NOT NULL,
  ADD KEY idx_problem_report_ips_ip_attempted (ip_address, attempted_at);
