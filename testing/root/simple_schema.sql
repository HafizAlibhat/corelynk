-- Simple database schema without foreign keys

CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `product_code` varchar(100) NOT NULL,
    `description` text,
    `unit` varchar(50) DEFAULT 'pcs',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `product_code` (`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `processes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `process_code` varchar(100) NOT NULL,
    `description` text,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `process_code` (`process_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `work_orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `wo_number` varchar(100) NOT NULL,
    `status` enum('planned','in_progress','completed','on_hold','cancelled') DEFAULT 'planned',
    `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
    `due_date` date,
    `notes` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wo_number` (`wo_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `work_order_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `work_order_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `quantity_ordered` int(11) NOT NULL,
    `quantity_completed` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `process_batches` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `batch_number` varchar(100) NOT NULL,
    `work_order_item_id` int(11) NOT NULL,
    `process_id` int(11) NOT NULL,
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
    UNIQUE KEY `batch_number` (`batch_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `process_batch_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `batch_id` int(11) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text,
    `old_status` varchar(50),
    `new_status` varchar(50),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT IGNORE INTO `products` (`id`, `name`, `product_code`, `description`, `unit`) VALUES
(1, 'Sample Product', 'PRD-001', 'Sample product for testing', 'pcs'),
(2, 'Test Product', 'PRD-002', 'Another test product', 'pcs');

INSERT IGNORE INTO `processes` (`id`, `name`, `process_code`, `description`) VALUES
(1, 'Laser Cutting', 'LC-001', 'Laser cutting process'),
(2, 'Assembly', 'ASM-001', 'Assembly process');

INSERT IGNORE INTO `work_orders` (`id`, `wo_number`, `status`) VALUES
(1, 'WO-0001', 'planned'),
(2, 'WO-0002', 'in_progress');

INSERT IGNORE INTO `work_order_items` (`id`, `work_order_id`, `product_id`, `quantity_ordered`) VALUES
(1, 1, 1, 100),
(2, 2, 2, 50);

INSERT IGNORE INTO `process_batches` (`id`, `batch_number`, `work_order_item_id`, `process_id`, `quantity`, `status`) VALUES
(1, 'BATCH-001', 1, 1, 50, 'in_progress'),
(2, 'BATCH-002', 2, 2, 25, 'completed');

INSERT IGNORE INTO `process_batch_logs` (`batch_id`, `action`, `description`) VALUES
(1, 'created', 'Batch created for laser cutting'),
(2, 'completed', 'Assembly batch completed');
