DROP TABLE IF EXISTS public.cleanup_suspicious_contact_names_20260609_preview;

CREATE TABLE public.cleanup_suspicious_contact_names_20260609_preview AS
WITH base AS (
    SELECT
        b.id,
        trim(c.full_name) AS current_name,
        b.reason
    FROM public.cleanup_suspicious_contact_names_20260609_backup b
    JOIN clients c ON c.id = b.id
),
candidates AS (
    SELECT
        id,
        current_name,
        reason,
        CASE
            WHEN reason IN ('placeholder', 'too_short', 'numeric_or_phone_like', 'no_letters') THEN NULL
            WHEN reason = 'email_in_name' THEN
                regexp_replace(current_name, '[[:alnum:]_.%+-]+@[[:alnum:].-]+\.[[:alpha:]]+', '', 'gi')
            WHEN reason = 'contains_long_number' AND lower(current_name) ~ '^(missed call|call from|whatsapp inquiry|party_name)' THEN NULL
            WHEN reason = 'contains_long_number' THEN
                regexp_replace(
                    regexp_replace(
                        regexp_replace(current_name, '(?i)\s*/\s*(po box|p\.o\. box|[0-9]).*$', ''),
                        '[+()0-9 .-]{5,}',
                        ' ',
                        'g'
                    ),
                    '(?i)\m(whatsapp|whats app|mobile|phone|inquiry|by)\M',
                    ' ',
                    'g'
                )
            WHEN reason = 'too_long' AND current_name LIKE '%;%' THEN split_part(current_name, ';', 1)
            WHEN reason = 'too_long' AND current_name LIKE '%|%' THEN split_part(current_name, '|', 1)
            WHEN reason = 'too_long' AND current_name ~* 'member of|property investment|real estate' THEN
                regexp_replace(current_name, '(?i)(member of|property investment|real estate).*$', '')
            WHEN reason = 'unusual_symbols' AND current_name ~ '[ØÙ�]' THEN NULL
            WHEN reason = 'unusual_symbols' AND lower(current_name) IN ('#n/a', 'typing...', 'typing…') THEN NULL
            WHEN reason = 'unusual_symbols' AND current_name ~ '^https?://' THEN NULL
            WHEN reason = 'unusual_symbols' AND current_name ~ '^[0-9.]+E\+[0-9]+$' THEN NULL
            WHEN reason = 'unusual_symbols' THEN
                regexp_replace(
                    regexp_replace(
                        regexp_replace(
                            regexp_replace(current_name, E'[\\r\\n\\t]+', ' ', 'g'),
                            E'\\\\[tnr]',
                            ' ',
                            'g'
                        ),
                        E'[|/()_~+`''#@*.:;\\[\\]{}<>="!?]+|[🤍⚡♡🗝️🇦🇪…·]',
                        ' ',
                        'g'
                    ),
                    '\s+',
                    ' ',
                    'g'
                )
            ELSE NULL
        END AS candidate
    FROM base
),
cleaned AS (
    SELECT
        id,
        current_name,
        reason,
        nullif(trim(regexp_replace(coalesce(candidate, ''), '\s+', ' ', 'g')), '') AS candidate
    FROM candidates
),
final AS (
    SELECT
        id,
        current_name,
        reason,
        CASE
            WHEN candidate IS NULL THEN NULL
            WHEN lower(candidate) IN ('na', 'n a') THEN NULL
            WHEN candidate ~* '^[a-z]\s+na$' THEN NULL
            WHEN candidate !~ '[[:alpha:]]' THEN NULL
            WHEN length(candidate) <= 1 THEN NULL
            WHEN length(candidate) > 90 THEN NULL
            ELSE candidate
        END AS new_name
    FROM cleaned
)
SELECT
    id,
    reason,
    current_name,
    new_name,
    CASE
        WHEN new_name IS NULL THEN 'clear_name'
        WHEN new_name IS DISTINCT FROM current_name THEN 'clean_name'
        ELSE 'keep_name'
    END AS action
FROM final;

SELECT action, reason, count(*)
FROM public.cleanup_suspicious_contact_names_20260609_preview
GROUP BY action, reason
ORDER BY action, reason;
