-- Saldo saku per siswa, sumber sama dengan VPSPaymentBUY / Tap Belanja:
-- SUM(KREDIT - DEBET) dari sccttran_cashless (bukan sccttran).

CREATE OR REPLACE VIEW v_saldo_saku AS
SELECT
    c.CUSTID,
    c.CODE04,
    c.CODE01,
    c.NOCUST,
    c.NMCUST,
    c.NUM2ND,
    c.STCUST,
    c.CODE02,
    c.DESC02,
    c.CODE03,
    c.DESC03,
    c.GENUS,
    c.DESC04,
    COALESCE(t.SALDO, 0) AS SALDO
FROM scctcust c
LEFT JOIN (
    SELECT
        CUSTID,
        SUM(COALESCE(KREDIT, 0) - COALESCE(DEBET, 0)) AS SALDO
    FROM sccttran_cashless
    GROUP BY CUSTID
) t ON t.CUSTID = c.CUSTID;
