START TRANSACTION;

INSERT INTO db_finance.pos_stamp_ledger (
    member_id,
    order_id,
    payment_id,
    campaign_id,
    ledger_type,
    stamp_in,
    stamp_out,
    balance_after,
    expired_at,
    notes,
    created_at
)
SELECT
    m.id AS member_id,
    NULL AS order_id,
    NULL AS payment_id,
    fc.id AS campaign_id,
    l.ledger_type,
    CASE
        WHEN l.ledger_type = 'ADJUST' AND l.stamp_amount > 0 THEN l.stamp_amount
        WHEN l.ledger_type = 'EARN' THEN GREATEST(l.stamp_amount, 0)
        ELSE 0
    END AS stamp_in,
    CASE
        WHEN l.ledger_type = 'ADJUST' AND l.stamp_amount < 0 THEN ABS(l.stamp_amount)
        WHEN l.ledger_type IN ('REDEEM', 'EXPIRE') THEN ABS(l.stamp_amount)
        ELSE 0
    END AS stamp_out,
    l.balance_after,
    l.expires_at AS expired_at,
    CONCAT('[core-pos_stamp_ledger#', l.id, ']', CASE WHEN COALESCE(l.notes, '') <> '' THEN CONCAT(' ', l.notes) ELSE '' END) AS notes,
    l.created_at
FROM core.pos_stamp_ledger l
JOIN core.crm_customer c
    ON c.id = l.customer_id
LEFT JOIN core.pos_stamp_campaign sc
    ON sc.id = l.campaign_id
JOIN db_finance.crm_member m
    ON LOWER(TRIM(m.member_name)) = LOWER(TRIM(c.customer_name))
   AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.mobile_phone, ''), '+62', '0'), ' ', ''), '-', ''), '(', ''), ')', '')
       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, ''), '+62', '0'), ' ', ''), '-', ''), '(', ''), ')', '')
LEFT JOIN db_finance.pos_stamp_campaign fc
    ON fc.campaign_code = sc.campaign_code
LEFT JOIN db_finance.pos_stamp_ledger tgt
    ON tgt.member_id = m.id
   AND tgt.ledger_type = l.ledger_type
   AND tgt.created_at = l.created_at
   AND tgt.stamp_in = CASE
       WHEN l.ledger_type = 'ADJUST' AND l.stamp_amount > 0 THEN l.stamp_amount
       WHEN l.ledger_type = 'EARN' THEN GREATEST(l.stamp_amount, 0)
       ELSE 0
   END
   AND tgt.stamp_out = CASE
       WHEN l.ledger_type = 'ADJUST' AND l.stamp_amount < 0 THEN ABS(l.stamp_amount)
       WHEN l.ledger_type IN ('REDEEM', 'EXPIRE') THEN ABS(l.stamp_amount)
       ELSE 0
   END
   AND COALESCE(tgt.notes, '') = CONCAT('[core-pos_stamp_ledger#', l.id, ']', CASE WHEN COALESCE(l.notes, '') <> '' THEN CONCAT(' ', l.notes) ELSE '' END)
WHERE tgt.id IS NULL
ORDER BY m.id ASC, l.created_at ASC, l.id ASC;

UPDATE db_finance.crm_member m
SET m.stamp_balance_cache = COALESCE((
    SELECT sl.balance_after
    FROM db_finance.pos_stamp_ledger sl
    WHERE sl.member_id = m.id
    ORDER BY sl.created_at DESC, sl.id DESC
    LIMIT 1
), 0)
WHERE EXISTS (
    SELECT 1
    FROM db_finance.pos_stamp_ledger sl
    WHERE sl.member_id = m.id
);

COMMIT;
