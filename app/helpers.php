<?php

use App\Models\mst_thn_aka;

if (! function_exists('angkatan_label')) {
    /**
     * Label angkatan dari thn_aka; bila $code01 diisi, nomor dihitung per unit.
     */
    function angkatan_label(?string $thnAka, ?string $code01 = null): string
    {
        return mst_thn_aka::labelFor($thnAka, $code01);
    }
}
