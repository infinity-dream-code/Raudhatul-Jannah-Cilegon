-- VPSPaymentBUY2: limit jajan harian + utang (keduanya dari sm_batasan yang aktif)
-- Dibuat terpisah dari VPSPaymentBUY (milik user arieffarrel/SYSTEM_USER yang tidak bisa diubah).
-- Aplikasi (TapBelanjaController) memanggil VPSPaymentBUY2.
--
-- Aturan:
-- 1) Belanja dibayar dari saldo dulu. Kalau saldo kurang, sisanya jadi utang.
-- 2) Limit harian (batas_belanja_hari) hanya menghitung belanja yang dibayar dari SALDO.
--    Belanja pakai utang TIDAK dihitung ke limit harian. batas_belanja_hari = 0 berarti tanpa limit harian.
-- 3) Utang dibatasi batasan_utang: sisa saldo setelah belanja tidak boleh < -batasan_utang.
--
-- Contoh (limit harian 20.000, batas utang 20.000):
--   Anak 1 saldo 15.000: jajan 15.000 OK (saldo 0), jajan lagi 10.000 OK pakai utang (saldo -10.000).
-- Contoh (limit harian 15.000):
--   Anak 2 saldo 20.000: jajan 15.000 OK, jajan lagi 3.000 ditolak (limit harian), walau saldo masih 5.000.
--
-- Di sccttran_cashless, belanja yang sebagian pakai utang dicatat 2 baris dengan NOREFF sama:
-- METODE 'FROM SALDO' (bagian dari saldo) dan METODE 'UTANG' (bagian utang). scctcashout tetap 1 baris.

DROP FUNCTION IF EXISTS VPSPaymentBUY2;

DELIMITER $$
CREATE FUNCTION `VPSPaymentBUY2`(
    p_PID VARCHAR(32),
    p_Amount BIGINT,
    p_USER VARCHAR(50)
) RETURNS varchar(200) CHARSET latin1
BEGIN
    DECLARE v_VHASIL varchar(200);
    DECLARE v_CUSTID INT;
    DECLARE LimitTransaksi BIGINT DEFAULT 0;
    DECLARE LimitUtang BIGINT DEFAULT 0;
    DECLARE TotBelanjaSaldo BIGINT DEFAULT 0;
    DECLARE SALDO BIGINT DEFAULT 0;
    DECLARE PartSaldo BIGINT DEFAULT 0;
    DECLARE PartUtang BIGINT DEFAULT 0;
    DECLARE vTRANSNO VARCHAR(20);
    DECLARE vNAMA VARCHAR(50);

    SELECT CUSTID, NMCUST INTO v_CUSTID, vNAMA
    FROM SCCTCUST
    WHERE NOCUST = p_PID AND STCUST = '1'
    LIMIT 1;

    IF (v_CUSTID IS NULL) THEN
        RETURN 'UNKNOWN_OR_BLOCKED_CARD';
    END IF;

    SELECT
        COALESCE(MAX(batas_belanja_hari), 0),
        COALESCE(MAX(batasan_utang), 0)
    INTO LimitTransaksi, LimitUtang
    FROM (
        SELECT batas_belanja_hari, batasan_utang
        FROM sm_batasan
        WHERE aktif = 1
        ORDER BY urut DESC
        LIMIT 1
    ) b;

    SELECT COALESCE(SUM(KREDIT), 0) - COALESCE(SUM(DEBET), 0) INTO SALDO
    FROM SCCTTRAN_CASHLESS
    WHERE CUSTID = v_CUSTID;

    SET PartSaldo = LEAST(p_Amount, GREATEST(SALDO, 0));
    SET PartUtang = p_Amount - PartSaldo;

    IF (LimitTransaksi > 0 AND PartSaldo > 0) THEN
        SELECT COALESCE(SUM(DEBET), 0) INTO TotBelanjaSaldo
        FROM SCCTTRAN_CASHLESS
        WHERE CUSTID = v_CUSTID
          AND FIDBANK = 'BUY'
          AND UPPER(TRIM(COALESCE(METODE, ''))) <> 'UTANG'
          AND TRXDATE >= CURDATE()
          AND TRXDATE < CURDATE() + INTERVAL 1 DAY;

        IF ((TotBelanjaSaldo + PartSaldo) > LimitTransaksi) THEN
            RETURN 'Daily_Transaction_Limit_Exceeded';
        END IF;
    END IF;

    IF (PartUtang > 0 AND (SALDO - p_Amount) < -LimitUtang) THEN
        IF (LimitUtang > 0) THEN
            RETURN 'Debt_Limit_Exceeded';
        END IF;
        RETURN 'Insufficient_Balance';
    END IF;

    SELECT CONCAT(
        'VPS',
        DATE_FORMAT(NOW(), '%Y%m%d'),
        RIGHT(CONCAT('00000', COUNT(urut) + 1), 5)
    ) INTO vTRANSNO
    FROM SCCTTRAN_CASHLESS
    WHERE TRXDATE >= CURDATE()
      AND TRXDATE < CURDATE() + INTERVAL 1 DAY;

    IF (PartSaldo > 0) THEN
        INSERT INTO SCCTTRAN_CASHLESS (
            CUSTID, NOREFF, REFFBANK, TRXDATE, KDCHANNEL, DEBET, METODE, FIDBANK
        ) VALUES (
            v_CUSTID, vTRANSNO, '', NOW(), 11, PartSaldo, 'FROM SALDO', 'BUY'
        );
    END IF;

    IF (PartUtang > 0) THEN
        INSERT INTO SCCTTRAN_CASHLESS (
            CUSTID, NOREFF, REFFBANK, TRXDATE, KDCHANNEL, DEBET, METODE, FIDBANK
        ) VALUES (
            v_CUSTID, vTRANSNO, '', NOW(), 11, PartUtang, 'UTANG', 'BUY'
        );
    END IF;

    INSERT INTO SCCTCASHOUT (
        CUSTID, BILLAM, tanggalkeluar, teller, TRANSNO, FIDBANK
    ) VALUES (
        v_CUSTID, p_Amount, NOW(), p_USER, vTRANSNO, 'BUY'
    );

    SELECT CONCAT('OK', '|', vNAMA, '|', (SALDO - p_Amount)) INTO v_VHASIL;

    RETURN v_VHASIL;
END$$
DELIMITER ;
