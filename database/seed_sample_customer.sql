-- Sample data for quick QA
INSERT INTO `customers` (`customer_code`,`name`,`type`,`status`,`metadata`,`created_by`) VALUES
('C-1','ACME Corp','business','active', JSON_OBJECT('primary_email','sales@acme.test','phone','+1234567890'), NULL);

SET @cust_id = LAST_INSERT_ID();

INSERT INTO `customer_contacts` (`customer_id`,`name`,`email`,`phone`,`role`,`preferred`) VALUES
(@cust_id,'John Doe','john.doe@acme.test','+1234567890','Sales',1);

INSERT INTO `customer_addresses` (`customer_id`,`type`,`line1`,`city`,`state`,`postal_code`,`country`) VALUES
(@cust_id,'billing','123 Industrial Road','Metropolis','State','12345','Country');
