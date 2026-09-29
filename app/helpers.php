<?php

use App\Models\mst_thn_aka;

if (! function_exists('angkatan_label')) {
    /**
     * Display label for a stored tahun akademik value (e.g. "2024/2025" → "Angkatan 1").
     */
    function angkatan_label(?string $thnAka): string
    {
        return mst_thn_aka::labelFor($thnAka);
    }
}
