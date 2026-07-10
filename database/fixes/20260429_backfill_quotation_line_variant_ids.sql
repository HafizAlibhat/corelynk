-- PRODUCTION PATCH (2026-04-29)
-- Purpose:
-- 1) Repair legacy quotation lines where product_variant_id became NULL.
-- 2) Restore variant image/attribute rendering in quotation PDFs.
--
-- Safety:
-- - Updates only rows where product_variant_id IS NULL.
-- - Uses exact match: quotation_lines.product_code = product_variants.art_number.
-- - art_number is globally unique by schema, so mapping is deterministic.

UPDATE quotation_lines ql
JOIN product_variants pv ON pv.art_number = ql.product_code
SET ql.product_variant_id = pv.id
WHERE ql.product_variant_id IS NULL;

-- Optional verification:
-- SELECT COUNT(*) AS still_missing
-- FROM quotation_lines ql
-- JOIN product_variants pv ON pv.art_number = ql.product_code
-- WHERE ql.product_variant_id IS NULL;
