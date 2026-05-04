-- Add gate pass reference columns to process_batches table
ALTER TABLE process_batches 
ADD COLUMN outgoing_gate_pass_id INT NULL AFTER created_by,
ADD COLUMN incoming_gate_pass_id INT NULL AFTER outgoing_gate_pass_id;

-- Add foreign key constraints (optional, comment out if causing issues)
-- ALTER TABLE process_batches 
-- ADD CONSTRAINT fk_batch_outgoing_gate_pass 
-- FOREIGN KEY (outgoing_gate_pass_id) REFERENCES gate_passes(id) ON DELETE SET NULL;

-- ALTER TABLE process_batches 
-- ADD CONSTRAINT fk_batch_incoming_gate_pass 
-- FOREIGN KEY (incoming_gate_pass_id) REFERENCES gate_passes(id) ON DELETE SET NULL;
