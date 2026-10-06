-- Contoh dari karanganyar_yys_adnan.v_saldo_saku
-- Jalankan di database tujuan (CREATE OR REPLACE)

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
    COALESCE(SUM(t.KREDIT), 0) - COALESCE(SUM(t.DEBET), 0) AS SALDO
FROM scctcust c
LEFT JOIN sccttran t
    ON t.CUSTID = c.CUSTID
   AND t.FIDBANK = '23'
GROUP BY
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
    c.DESC04;
