START TRANSACTION;

INSERT INTO db_finance.pos_voucher_issue (
    voucher_issue_no,
    campaign_id,
    member_id,
    source_order_id,
    source_payment_id,
    voucher_code,
    voucher_status,
    amount_snapshot,
    percent_snapshot,
    issued_at,
    expired_at,
    redeemed_at,
    notes,
    created_at
)
SELECT
    CONCAT('MGVI-', w.id) AS voucher_issue_no,
    fc.id AS campaign_id,
    m.id AS member_id,
    NULL AS source_order_id,
    NULL AS source_payment_id,
    w.voucher_code,
    CASE w.voucher_status
        WHEN 'AVAILABLE' THEN 'OPEN'
        WHEN 'RESERVED' THEN 'OPEN'
        WHEN 'REDEEMED' THEN 'REDEEMED'
        WHEN 'VOID' THEN 'VOID'
        ELSE 'EXPIRED'
    END AS voucher_status,
    CASE
        WHEN fc.voucher_type = 'AMOUNT' THEN fc.discount_value
        WHEN fc.voucher_type = 'FREE_PRODUCT' THEN fc.discount_value
        ELSE 0
    END AS amount_snapshot,
    CASE
        WHEN fc.voucher_type = 'PERCENT' THEN fc.discount_value
        ELSE 0
    END AS percent_snapshot,
    w.issued_at,
    w.expired_at,
    vr.created_at AS redeemed_at,
    CONCAT('[core-pos_voucher_wallet#', w.id, ']', CASE WHEN COALESCE(w.notes, '') <> '' THEN CONCAT(' ', w.notes) ELSE '' END) AS notes,
    w.created_at
FROM core.pos_voucher_wallet w
JOIN core.crm_customer c
    ON c.id = w.customer_id
LEFT JOIN core.pos_voucher_issue_campaign ic
    ON ic.id = w.issue_campaign_id
LEFT JOIN core.pos_voucher_campaign sc
    ON sc.id = COALESCE(w.campaign_id, ic.voucher_rule_id)
JOIN db_finance.crm_member m
    ON LOWER(TRIM(m.member_name)) = LOWER(TRIM(c.customer_name))
   AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.mobile_phone, ''), '+62', '0'), ' ', ''), '-', ''), '(', ''), ')', '')
       = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, ''), '+62', '0'), ' ', ''), '-', ''), '(', ''), ')', '')
JOIN db_finance.pos_voucher_campaign fc
    ON fc.campaign_code = sc.campaign_code
LEFT JOIN core.pos_voucher_redemption vr
    ON vr.wallet_id = w.id
LEFT JOIN db_finance.pos_voucher_issue tgt
    ON tgt.voucher_code = w.voucher_code
WHERE tgt.id IS NULL
ORDER BY m.id ASC, w.issued_at ASC, w.id ASC;

INSERT INTO db_finance.pos_voucher_redemption (
    voucher_issue_id,
    member_id,
    order_id,
    payment_id,
    redeem_amount,
    redeemed_at,
    notes
)
SELECT
    vi.id AS voucher_issue_id,
    vi.member_id,
    NULL AS order_id,
    NULL AS payment_id,
    vr.discount_amount AS redeem_amount,
    vr.created_at AS redeemed_at,
    CONCAT('[core-pos_voucher_redemption#', vr.id, ']') AS notes
FROM core.pos_voucher_redemption vr
JOIN core.pos_voucher_wallet w
    ON w.id = vr.wallet_id
JOIN db_finance.pos_voucher_issue vi
    ON vi.voucher_code = w.voucher_code
LEFT JOIN db_finance.pos_voucher_redemption tgt
    ON tgt.voucher_issue_id = vi.id
   AND COALESCE(tgt.notes, '') = CONCAT('[core-pos_voucher_redemption#', vr.id, ']')
WHERE tgt.id IS NULL
ORDER BY vr.created_at ASC, vr.id ASC;

UPDATE db_finance.pos_voucher_issue vi
JOIN (
    SELECT
        vr.voucher_issue_id,
        MAX(vr.redeemed_at) AS redeemed_at,
        SUM(vr.redeem_amount) AS redeem_amount
    FROM db_finance.pos_voucher_redemption vr
    GROUP BY vr.voucher_issue_id
) agg
    ON agg.voucher_issue_id = vi.id
SET vi.redeemed_at = agg.redeemed_at,
    vi.voucher_status = CASE
        WHEN vi.voucher_status = 'VOID' THEN 'VOID'
        WHEN vi.voucher_status = 'EXPIRED' THEN 'EXPIRED'
        ELSE 'REDEEMED'
    END;

COMMIT;
