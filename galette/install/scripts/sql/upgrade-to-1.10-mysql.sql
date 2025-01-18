-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount decimal(15,2) NULL DEFAULT NULL;
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh varchar(200) NOT NULL DEFAULT '';
-- Add payment type to transactions
ALTER TABLE galette_transactions ADD type_paiement_trans int(10) unsigned NULL DEFAULT NULL;
ALTER TABLE galette_transactions ADD FOREIGN KEY (type_paiement_trans)
    REFERENCES galette_paymenttypes (type_id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;
-- Add field_min_size to galette_field_types
ALTER TABLE galette_field_types ADD field_min_size integer(10) NULL DEFAULT NULL;
-- Add display properties to core fields
ALTER TABLE galette_fields_config ADD width_in_forms tinyint(1) NOT NULL DEFAULT 1;
-- Add display properties to dynamic fields
ALTER TABLE galette_field_types ADD field_width_in_forms tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE galette_field_types ADD field_information_above tinyint(1) NOT NULL DEFAULT 0;

-- change character set and collation
ALTER TABLE galette_adherents CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_cotisations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_database CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_dynamic_fields CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_field_types CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_fields_categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_fields_config CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_groups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_groups_managers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_groups_members CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_import_model CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_l10n CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_mailing_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_paymenttypes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_pdfmodels CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_pictures CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_preferences CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_reminders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_searches CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_socials CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_statuts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_texts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_titles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_tmplinks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_tmppasswds CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_transactions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_types_cotisation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- change dynamic fields permissions
ALTER TABLE galette_field_types CHANGE field_perm field_perm INT(10) NOT NULL DEFAULT 1;

-- table for documents
DROP TABLE IF EXISTS galette_documents;
CREATE TABLE galette_documents (
  id_document int(10) unsigned NOT NULL auto_increment,
  type varchar(250) NOT NULL,
  visible tinyint(1) NOT NULL,
  filename varchar(255) DEFAULT NULL,
  comment text,
  creation_date datetime NOT NULL,
  PRIMARY KEY (id_document),
  KEY (type)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;


-- change fields types and default values
UPDATE galette_cotisations SET montant_cotis = 0.00 WHERE montant_cotis IS NULL;
ALTER TABLE galette_cotisations CHANGE montant_cotis montant_cotis decimal(15,2) NOT NULL;
UPDATE galette_transactions SET trans_amount = 0.00 WHERE trans_amount IS NULL;
ALTER TABLE galette_transactions CHANGE trans_amount trans_amount decimal(15,2) NOT NULL;

-- table for payments schedules
DROP TABLE IF EXISTS galette_payments_schedules;
CREATE TABLE galette_payments_schedules (
  id_schedule int(10) unsigned NOT NULL auto_increment,
  id_cotis int(10) unsigned NOT NULL,
  id_paymenttype int(10) unsigned NOT NULL,
  creation_date datetime NOT NULL,
  scheduled_date datetime NOT NULL,
  amount decimal(15,2) NOT NULL,
  paid tinyint(1) DEFAULT FALSE,
  comment text,
  PRIMARY KEY (id_schedule),
  FOREIGN KEY (id_cotis) REFERENCES galette_cotisations (id_cotis) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_paymenttype) REFERENCES galette_paymenttypes (type_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

ALTER TABLE galette_types_cotisation CHANGE cotis_extension cotis_extension TINYINT NOT NULL DEFAULT '0';
UPDATE galette_types_cotisation SET cotis_extension=-1 WHERE cotis_extension=1;

