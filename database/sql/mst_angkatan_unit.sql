-- Tahun akademik angkatan pertama per unit (mst_sekolah.CODE01)
-- Unit tanpa baris di sini memakai tahun akademik paling awal di mst_thn_aka.

CREATE TABLE mst_angkatan_unit (
    urut INT NOT NULL AUTO_INCREMENT,
    CODE01 VARCHAR(20) NOT NULL,
    thn_aka_mulai VARCHAR(18) NOT NULL,
    updated_by VARCHAR(50) NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (urut),
    UNIQUE KEY uk_angkatan_unit_code01 (CODE01)
);
