-- Add missing tables for the batch management system

CREATE TABLE IF NOT EXISTS `work_order_items` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `work_order_id` int(10) unsigned NOT NULL,
    `product_id` int(10) unsigned NOT NULL,
    `quantity_ordered` int(11) NOT NULL,
    `quantity_completed` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `work_order_id` (`work_order_id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `process_batches` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `batch_number` varchar(100) NOT NULL,
    `work_order_item_id` int(10) unsigned NOT NULL,
    `process_id` int(10) unsigned NOT NULL,
    `status` enum('pending','in_progress','completed','quality_check','hold','rejected') DEFAULT 'pending',
    `quantity` int(11) NOT NULL,
    `quantity_completed` int(11) DEFAULT 0,
    `quantity_rejected` int(11) DEFAULT 0,
    `started_at` timestamp NULL DEFAULT NULL,
    `completed_at` timestamp NULL DEFAULT NULL,
    `notes` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `batch_number` (`batch_number`),
    KEY `work_order_item_id` (`work_order_item_id`),
    KEY `process_id` (`process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `process_batch_logs` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `batch_id` int(10) unsigned NOT NULL,
    `user_id` int(10) unsigned DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text,
    `old_status` varchar(50),
    `new_status` varchar(50),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing column to work_orders table if it doesn't exist
ALTER TABLE `work_orders` ADD COLUMN IF NOT EXISTS `wo_number` varchar(100) AFTER `id`;

-- Update existing work_orders to have wo_number values
UPDATE `work_orders` SET `wo_number` = CONCAT('WO-', LPAD(id, 4, '0')) WHERE `wo_number` IS NULL OR `wo_number` = '';

-- Insert sample data
INSERT IGNORE INTO `work_order_items` (`id`, `work_order_id`, `product_id`, `quantity_ordered`) VALUES
(1, 1, 1, 100),
(2, 1, 2, 50),
(3, 2, 1, 75),
(4, 2, 3, 25);

INSERT IGNORE INTO `process_batches` (`id`, `batch_number`, `work_order_item_id`, `process_id`, `quantity`, `status`) VALUES
(1, 'BATCH-001', 1, 1, 50, 'in_progress'),
(2, 'BATCH-002', 2, 2, 25, 'completed'),
(3, 'BATCH-003', 3, 1, 37, 'pending'),
(4, 'BATCH-004', 4, 3, 12, 'quality_check');

INSERT IGNORE INTO `process_batch_logs` (`batch_id`, `action`, `description`) VALUES
(1, 'created', 'Batch created for laser cutting'),
(1, 'started', 'Production started'),
(2, 'created', 'Assembly batch created'),
(2, 'completed', 'Assembly batch completed'),
(3, 'created', 'New batch pending approval'),
(4, 'created', 'Quality check batch created');
