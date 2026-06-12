BEGIN;

UPDATE clients c
SET
    full_name = p.new_name,
    updated_at = now()
FROM public.cleanup_suspicious_contact_names_20260609_preview p
WHERE c.id = p.id
  AND p.action IN ('clean_name', 'clear_name')
  AND c.full_name IS DISTINCT FROM p.new_name;

SELECT p.action, p.reason, count(*) AS affected_rows
FROM public.cleanup_suspicious_contact_names_20260609_preview p
WHERE p.action IN ('clean_name', 'clear_name')
GROUP BY p.action, p.reason
ORDER BY p.action, p.reason;

COMMIT;
