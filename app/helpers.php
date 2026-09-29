<?php

use App\Models\mst_thn_aka;

if (! function_exists('angkatan_label')) {
    /**
     * Label dari kolom mst_thn_aka.angkatan (relasi via thn_aka).
     */
    function angkatan_label(?string $thnAka): string
    {
        return mst_thn_aka::labelFor($thnAka);
    }
}
