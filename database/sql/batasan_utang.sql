-- 1) Tambah kolom batasan_utang di sm_batasan (aman, default 0 = tidak boleh utang)
ALTER TABLE sm_batasan
    ADD COLUMN batasan_utang INT NOT NULL DEFAULT 0
    AFTER batas_cash;

-- 2) Update VPSPaymentBUY: boleh belanja meski saldo kurang,
--    selama sisa saldo setelah transaksi >= -batasan_utang
--    Contoh: batasan_utang=20000, saldo 5000, belanja 15000 → OK (sisa -10000)
--            belanja 30000 → Insufficient_Balance / Debt_Limit_Exceeded

DROP FUNCTION IF EXISTS VPSPaymentBUY;

DELIMITER $$
CREATE DEFINER=`arieffarrel`@`%` FUNCTION `VPSPaymentBUY`(
    p_PID VARCHAR(32),
    p_Amount BIGINT,
    p_USER VARCHAR(50)
) RETURNS varchar(200) CHARSET latin1
BEGIN
    DECLARE v_VHASIL varchar(200);
    DECLARE v_CUSTID INT;
    DECLARE LimitTransaksi BIGINT;
    DECLARE LimitUtang BIGINT;
    DECLARE TotBelanja BIGINT;
    DECLARE SALDO BIGINT;
    DECLARE vTRANSNO VARCHAR(20);
    DECLARE vNAMA VARCHAR(50);

    SELECT CUSTID, NMCUST INTO v_CUSTID, vNAMA
    FROM SCCTCUST
    WHERE NOCUST = p_PID AND STCUST = '1';

    IF (v_CUSTID IS NOT NULL) THEN

        SELECT
            COALESCE(batas_belanja_hari, 0),
            COALESCE(batasan_utang, 0)
        INTO LimitTransaksi, LimitUtang
        FROM sm_batasan
        WHERE aktif = 1
        LIMIT 0, 1;

        SELECT COALESCE(SUM(SCCTTRAN_CASHLESS.DEBET), 0) INTO TotBelanja
        FROM SCCTTRAN_CASHLESS
        WHERE SCCTTRAN_CASHLESS.CUSTID = v_CUSTID
          AND FIDBANK = 'BUY'
          AND DATE_FORMAT(TRXDATE, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d');

        SELECT SUM(KREDIT - DEBET) INTO SALDO
        FROM SCCTTRAN_CASHLESS
        WHERE SCCTTRAN_CASHLESS.CUSTID = v_CUSTID;

        SET SALDO = COALESCE(SALDO, 0);
        SET LimitUtang = COALESCE(LimitUtang, 0);

        IF ((p_Amount + TotBelanja) < LimitTransaksi) THEN

            -- Boleh utang: p_Amount <= SALDO + LimitUtang
            IF (p_Amount <= (SALDO + LimitUtang)) THEN

                SELECT CONCAT(
                    'VPS',
                    DATE_FORMAT(NOW(), '%Y%m%d'),
                    RIGHT(CONCAT('00000', COUNT(urut) + 1), 5)
                ) INTO vTRANSNO
                FROM SCCTTRAN_CASHLESS
                WHERE DATE_FORMAT(TRXDATE, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d');

                INSERT INTO SCCTTRAN_CASHLESS (
                    CUSTID, NOREFF, REFFBANK, TRXDATE, KDCHANNEL, DEBET, METODE, FIDBANK
                ) VALUES (
                    v_CUSTID, vTRANSNO, '', NOW(), 11, p_Amount, 'FROM SALDO', 'BUY'
                );

                INSERT INTO SCCTCASHOUT (
                    CUSTID, BILLAM, tanggalkeluar, teller, TRANSNO, FIDBANK
                ) VALUES (
                    v_CUSTID, p_Amount, NOW(), p_USER, vTRANSNO, 'BUY'
                );

                SELECT CONCAT('OK', '|', vNAMA, '|', (SALDO - p_Amount)) INTO v_VHASIL;

            ELSE
                IF (LimitUtang > 0 AND SALDO < p_Amount) THEN
                    SELECT 'Debt_Limit_Exceeded' INTO v_VHASIL;
                ELSE
                    SELECT 'Insufficient_Balance' INTO v_VHASIL;
                END IF;
            END IF;

        ELSE
            SELECT 'Daily_Transaction_Limit_Exceeded' INTO v_VHASIL;
        END IF;

    ELSE
        SELECT 'UNKNOWN_OR_BLOCKED_CARD' INTO v_VHASIL;
    END IF;

    RETURN v_VHASIL;
END$$
DELIMITER ;
