ALTER TABLE llx_c_subtotal_free_text ADD INDEX idx_c_subtotal_free_text_entity_active (entity, active);
ALTER TABLE llx_c_subtotal_free_text ADD INDEX idx_c_subtotal_free_text_entity_label (entity, label);
