<?php

namespace App\Http\Controllers\Smartcard;

use App\Exports\TransaksiBelanjaExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TransaksiBelanjaController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);

        $thnAka = $this->fetchThnAka();
        $kelasOptions = $this->fetchKelasOptions();
        $unitOptions = $this->fetchUnitOptions();

        $rows = $isSearch
            ? $this->fetchRows($filters)
            : new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

        $totalDebet = $isSearch ? $this->sumDebet($filters) : 0;

        return view('smartcard.transaksi-belanja.index', [
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'totalDebet' => $totalDebet,
            'thnAka' => $thnAka,
            'kelasOptions' => $kelasOptions,
            'unitOptions' => $unitOptions,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        $rows = $this->selectRows($this->baseQuery($filters))->get();

        $info = [];
        if ($filters['unit'] !== '') {
            $unit = collect($this->fetchUnitOptions())->first(fn ($u) => trim((string) $u->CODE01) === $filters['unit']);
            $info[] = 'Unit: ' . trim((string) ($unit->DESC01 ?? $filters['unit']));
        }
        if ($filters['angkatan'] !== '') {
            $info[] = 'Angkatan: ' . angkatan_label($filters['angkatan'], $filters['unit'] ?: null) . ' (' . $filters['angkatan'] . ')';
        }
        if ($filters['kelas_id'] !== '') {
            $kelas = collect($this->fetchKelasOptions())->firstWhere('id', (int) $filters['kelas_id']);
            if ($kelas) {
                $info[] = 'Kelas: ' . implode(' - ', array_filter([
                    trim((string) ($kelas->unit ?? '')),
                    trim((string) ($kelas->jenjang ?? '')),
                    trim((string) ($kelas->kelas ?? '')),
                ]));
            }
        }
        if ($filters['nis'] !== '') {
            $info[] = 'NIS: ' . $filters['nis'];
        }
        if ($filters['nama'] !== '') {
            $info[] = 'Nama: ' . $filters['nama'];
        }
        if ($filters['dari_tanggal'] !== '' || $filters['sampai_tanggal'] !== '') {
            $info[] = 'Periode: ' . ($filters['dari_tanggal'] ?: '-') . ' s/d ' . ($filters['sampai_tanggal'] ?: '-');
        }

        $filename = 'Transaksi_Belanja_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new TransaksiBelanjaExport($rows, $info), $filename);
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'unit' => trim((string) $request->query('unit', '')),
            'angkatan' => trim((string) $request->query('angkatan', '')),
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'dari_tanggal' => trim((string) $request->query('dari_tanggal', '')),
            'sampai_tanggal' => trim((string) $request->query('sampai_tanggal', '')),
            'kelas_id' => trim((string) $request->query('kelas_id', '')),
        ];
    }

  /**
   * @return list<object{thn_aka: string}>
   */
    private function fetchThnAka(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->get(['thn_aka'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

  /**
   * @return list<object{CODE01: string, DESC01: ?string}>
   */
    private function fetchUnitOptions(): array
    {
        try {
            $query = DB::connection('DATA_MYSQL')
                ->table('mst_sekolah')
                ->whereNotNull('CODE01')
                ->where('CODE01', '!=', '')
                ->orderBy('CODE01');

            $code = \App\Support\SchoolScope::codeFromUser();
            if (!blank($code)) {
                $query->whereRaw('TRIM(CODE01) = ?', [trim((string) $code)]);
            }

            return $query->get(['CODE01', 'DESC01'])->all();
        } catch (\Throwable) {
            return [];
        }
    }

  /**
   * @return list<object>
   */
    private function fetchKelasOptions(): array
    {
        try {
            $query = DB::connection('DATA_MYSQL')
                ->table('mst_kelas')
                ->orderBy('unit')
                ->orderBy('jenjang')
                ->orderBy('kelas');

            \App\Support\SchoolScope::applyKelas($query);

            return $query->get(['id', 'unit', 'jenjang', 'kelas'])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function baseQuery(array $filters)
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->join('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('sm_kantin', function ($join) {
                $join->on(DB::raw('TRIM(sm_kantin.username)'), '=', DB::raw('TRIM(scctcashout.Teller)'));
            })
            ->whereRaw('UPPER(TRIM(scctcashout.FIDBANK)) = ?', ['BUY']);

        // Siswa tampil hanya jika CODE01 = cyber_key.fid (fid kosong = semua).
        \App\Support\SchoolScope::apply($query, 'scctcust');
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['nis'] !== '') {
            $query->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%');
        }

        if ($filters['nama'] !== '') {
            $query->where('scctcust.NMCUST', 'like', '%' . $filters['nama'] . '%');
        }

        if ($filters['unit'] !== '') {
            $query->whereRaw('TRIM(scctcust.CODE01) = ?', [$filters['unit']]);
        }

        if ($filters['angkatan'] !== '') {
            $this->applyAngkatanLike($query, $filters['angkatan']);
        }

        if ($filters['dari_tanggal'] !== '') {
            $from = $this->parseDate($filters['dari_tanggal']);
            if ($from) {
                $query->where('scctcashout.TanggalKeluar', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('scctcashout.TanggalKeluar', '<=', $to->endOfDay());
            }
        }

        if ($filters['kelas_id'] !== '') {
            $this->applyKelasFilter($query, $filters['kelas_id']);
        }
    }

    private function applyAngkatanLike($query, string $value): void
    {
        $full = trim($value);
        $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
        $query->where(function ($q) use ($full, $base) {
            $q->whereRaw('TRIM(scctcust.DESC04) = ?', [$full]);
            if ($base !== '' && $base !== $full) {
                $q->orWhereRaw('TRIM(scctcust.DESC04) = ?', [$base])
                    ->orWhereRaw('REPLACE(TRIM(scctcust.DESC04), \' \', \'\') LIKE ?', [str_replace(' ', '', $base) . '%']);
            }
        });
    }

    private function applyKelasFilter($query, string $kelasId): void
    {
        $kelas = DB::connection('DATA_MYSQL')
            ->table('mst_kelas')
            ->where('id', (int) $kelasId)
            ->first(['id', 'unit', 'jenjang', 'kelas']);

        if (!$kelas) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unit = trim((string) ($kelas->unit ?? ''));
        $jenjang = trim((string) ($kelas->jenjang ?? ''));
        $kelasNama = trim((string) ($kelas->kelas ?? ''));

        $query->where(function ($q) use ($kelasId, $unit, $jenjang, $kelasNama) {
            $q->whereRaw('TRIM(scctcust.CODE03) = ?', [(string) $kelasId]);
            if ($unit !== '' && $jenjang !== '' && $kelasNama !== '') {
                $q->orWhere(function ($q2) use ($unit, $jenjang, $kelasNama) {
                    $q2->whereRaw('TRIM(scctcust.CODE02) = ?', [$unit])
                        ->whereRaw('TRIM(scctcust.DESC02) = ?', [$jenjang])
                        ->whereRaw('TRIM(scctcust.DESC03) = ?', [$kelasNama]);
                });
            }
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchRows(array $filters): LengthAwarePaginator
    {
        return $this->selectRows($this->baseQuery($filters))
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function selectRows($query)
    {
        return $query
            ->select([
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as debet',
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller)) as kantin'),
                'scctcust.DESC02 as kelas',
                'scctcust.DESC03 as kelompok',
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut');
    }

    private function sumDebet(array $filters): float
    {
        return (float) ($this->baseQuery($filters)->sum('scctcashout.BILLAM') ?? 0);
    }
}
