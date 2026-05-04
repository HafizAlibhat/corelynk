USE production_management_system;

INSERT INTO gate_passes (gate_pass_number, type, vendor_id, purpose, items, status, expected_date, notes, created_by) VALUES 
('GP-20250816-0001', 'incoming', 1, 'Raw material delivery', '[{"description":"Steel Sheets","quantity":"100","unit":"Pcs"}]', 'pending', '2025-08-17 10:00:00', 'Urgent delivery required', 1),
('GP-20250816-0002', 'outgoing', 2, 'Finished goods dispatch', '[{"description":"Finished Products","quantity":"50","unit":"Pcs"}]', 'completed', '2025-08-16 15:30:00', 'Customer delivery', 1),
('GP-20250816-0003', 'incoming', 1, 'Tool maintenance return', '[{"description":"Cutting Tools","quantity":"25","unit":"Pcs"}]', 'approved', '2025-08-17 09:00:00', 'Maintenance completed', 1);
