-- Fix missing AUTO_INCREMENT and PRIMARY KEY on journal_entries and journal_lines
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `journal_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;

-- You can add similar fixes for other tables if needed:
-- ALTER TABLE `cheques` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;
-- ALTER TABLE `cheque_lines` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;
-- ALTER TABLE `vendors` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;
-- ALTER TABLE `vendor_contacts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;
