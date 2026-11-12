-- Existing installation: run this file only. New installation: campusvoice.sql, then this file.
-- Additive, rerunnable upgrade for MySQL/MariaDB. No existing row is changed/deleted.
USE campusvoice;
SET NAMES utf8mb4;
SET @cv_ddl = IF(
 (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Reflections' AND COLUMN_NAME='Version')=0,
 'ALTER TABLE Reflections ADD COLUMN Version INT UNSIGNED NOT NULL DEFAULT 0',
 'SELECT 1');
PREPARE cv_stmt FROM @cv_ddl;
EXECUTE cv_stmt;
DEALLOCATE PREPARE cv_stmt;
CREATE TABLE IF NOT EXISTS ReflectionActivities (
 Id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ReflectionId INT UNSIGNED NOT NULL,
 UserId INT UNSIGNED NOT NULL,
 Type ENUM('RequestInfo','Supplement','Resolved','Closed') NOT NULL,
 Content TEXT NOT NULL,
 Version INT UNSIGNED NOT NULL,
 ResponseId INT UNSIGNED NULL UNIQUE,
 CreatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CONSTRAINT fk_activity_ref FOREIGN KEY (ReflectionId) REFERENCES Reflections(Id) ON DELETE RESTRICT,
 CONSTRAINT fk_activity_user FOREIGN KEY (UserId) REFERENCES Users(Id) ON DELETE RESTRICT,
 CONSTRAINT fk_activity_response FOREIGN KEY (ResponseId) REFERENCES Responses(Id) ON DELETE RESTRICT,
 UNIQUE KEY uq_activity_version (ReflectionId,Version),
 INDEX ix_activity_timeline (ReflectionId,CreatedAt,Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
