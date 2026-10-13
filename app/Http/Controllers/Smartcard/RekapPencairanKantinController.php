<?php

namespace App\Http\Controllers\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RekapPencairanKantinController extends Controller
{
    private const MAX_ROWS = 500;

    public function index(Request $request): View
    {
        $kantinUsername = trim((string) $request->query('kantin', ''));
        $dariTanggal = trim((string) $request->query('dari_tanggal', ''));
        $sampaiTanggal = trim((string) $request->query('sampai_tanggal', ''));
        $namaPenerima = trim((string) $request->query('nama_penerima', ''));
        $nominal = trim((string) $request->query('nominal', ''));

        $cariTransaksi = $request->boolean('cari_transaksi');
        $liatPencairan = $request->boolean('liat_pencairan');

        $kantinOptions = $this->fetchKantinOptions();
        $kantin = $this->resolveKantin($kantinUsername);

        $transaksiRows = collect();
        $transaksiTotal = 0;
        if ($cariTransaksi) {
            [$transaksiRows, $transaksiTotal] = $this->fetchTransaksiRows($kantin, $dariTanggal, $sampaiTanggal);
        }

        $pencairanRows = collect();
        $pencairanTotal = 0;
        $pencairanError = false;
        if ($liatPencairan) {
            [$pencairanRows, $pencairanTotal, $pencairanError] = $this->fetchPencairanRows(
                $kantin,
                $dariTanggal,
                $sampaiTanggal
            );
        }

        return view('smartcard.rekap-pencairan-kantin.index', [
            'kantinOptions' => $kantinOptions,
            'kantinUsername' => $kantinUsername,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'namaPenerima' => $namaPenerima,
            'nominal' => $nominal,
            'cariTransaksi' => $cariTransaksi,
            'liatPencairan' => $liatPencairan,
            'transaksiRows' => $transaksiRows,
            'transaksiTotal' => $transaksiTotal,
            'pencairanRows' => $pencairanRows,
            'pencairanTotal' => $pencairanTotal,
            'pencairanError' => $pencairanError,
            'previewNoTerima' => $this->generateNoTerima(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kantin' => ['required', 'string', 'max:50'],
            'dari_tanggal' => ['nullable', 'date'],
            'sampai_tanggal' => ['nullable', 'date'],
            'nama_penerima' => ['required', 'string', 'max:30'],
            'nominal' => ['required', 'integer', 'min:1'],
        ], [
            'kantin.required' => 'Pilih merchant yang mencairkan terlebih dahulu.',
            'nama_penerima.max' => 'Nama penerima maksimal 30 karakter.',
            'nominal.integer' => 'Nominal harus berupa angka bulat.',
            'nama_penerima.required' => 'Nama penerima wajib diisi.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.min' => 'Nominal harus lebih dari 0.',
        ]);

        $kantin = $this->resolveKantin(trim($validated['kantin']));
        if ($kantin === null) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['kantin' => 'Kantin tidak ditemukan.']);
        }

        $dari = !empty($validated['dari_tanggal'])
            ? Carbon::parse($validated['dari_tanggal'])->startOfDay()
            : now()->startOfMonth();
        $sampai = !empty($validated['sampai_tanggal'])
            ? Carbon::parse($validated['sampai_tanggal'])->endOfDay()
            : now()->endOfDay();
        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai->copy()->startOfDay(), $dari->copy()->endOfDay()];
        }

        $noTerima = $this->generateNoTerima();

        try {
            $db = DB::connection('DATA_MYSQL');
            $available = Schema::connection('DATA_MYSQL')->getColumnListing('sm_mercan_cair');

            $db->transaction(function () use ($db, $available, &$kantin, $validated, $noTerima, $dari, $sampai) {
                if ($kantin['kdmercan'] === '') {
                    $kantin['kdmercan'] = $this->assignKdMercan($kantin['username']);
                }

                $row = [
                    'KDMERCAN' => $kantin['kdmercan'],
                    'NamaPenerima' => trim($validated['nama_penerima']),
                    'TglTerima' => now()->format('Y-m-d H:i:s'),
                    'Nominal' => (int) $validated['nominal'],
                    'NoTerima' => $noTerima,
                    'dari_tgl_tran' => $dari->format('Y-m-d'),
                    'akhir_tgl_tran' => $sampai->format('Y-m-d'),
                ];

                // sm_mercan_cair.urut di sebagian database wajib diisi dan bukan auto-increment.
                if (in_array('urut', $available, true)) {
                    $row['urut'] = ((int) $db->table('sm_mercan_cair')->lockForUpdate()->max('urut')) + 1;
                }

                $db->table('sm_mercan_cair')->insert(
                    array_intersect_key($row, array_flip($available))
                );
            });
        } catch (\Throwable $e) {
            Log::error('RekapPencairanKantin store failed', [
                'kantin' => $kantin['username'],
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['kantin' => 'Gagal menyimpan pencairan: ' . Str::limit($e->getMessage(), 300)]);
        }

        return redirect()
            ->route('smartcard.rekap_pencairan_kantin', [
                'kantin' => $kantin['username'],
                'dari_tanggal' => $dari->format('Y-m-d'),
                'sampai_tanggal' => $sampai->format('Y-m-d'),
                'nama_penerima' => trim($validated['nama_penerima']),
                'nominal' => (int) $validated['nominal'],
                'liat_pencairan' => 1,
            ])
            ->with('smartcard_success', 'Data pencairan berhasil disimpan. No Terima: ' . $noTerima);
    }

    /**
     * sm_mercan_cair.KDMERCAN hanya varchar(3), jadi kantin tanpa kode merchant
     * dibuatkan kode 3 digit (001, 002, ...) dan disimpan ke sm_kantin.KDMERCAN.
     */
    private function assignKdMercan(string $username): string
    {
        $db = DB::connection('DATA_MYSQL');

        $codes = $db->table('sm_kantin')->lockForUpdate()->pluck('KDMERCAN')
            ->merge($db->table('sm_mercan_cair')->pluck('KDMERCAN'));
        if (Schema::connection('DATA_MYSQL')->hasTable('sm_mercan')) {
            $codes = $codes->merge($db->table('sm_mercan')->pluck('KDMERCAN'));
        }

        $used = $codes->map(static fn ($c) => trim((string) $c))
            ->filter(static fn ($c) => $c !== '')
            ->unique();

        $next = 1;
        while ($used->contains(str_pad((string) $next, 3, '0', STR_PAD_LEFT))) {
            $next++;
        }
        if ($next > 999) {
            throw new \RuntimeException('Kode merchant 3 digit sudah habis.');
        }

        $code = str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        $db->table('sm_kantin')
            ->whereRaw('TRIM(username) = ?', [$username])
            ->update(['KDMERCAN' => $code]);

        return $code;
    }

    /** Format: YYYYMMDD + urut 3 digit, contoh 20260607001 */
    private function generateNoTerima(): string
    {
        $prefix = now()->format('Ymd');

        $last = DB::connection('DATA_MYSQL')
            ->table('sm_mercan_cair')
            ->whereRaw('TRIM(NoTerima) LIKE ?', [$prefix . '%'])
            ->orderByDesc('NoTerima')
            ->value('NoTerima');

        $seq = 1;
        if ($last !== null && $last !== '') {
            $last = trim((string) $last);
            if (str_starts_with($last, $prefix) && strlen($last) > strlen($prefix)) {
                $tail = substr($last, strlen($prefix));
                if (ctype_digit($tail)) {
                    $seq = (int) $tail + 1;
                }
            }
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

  /**
   * Semua kantin dari sm_kantin; value = username (selalu ada, KDMERCAN sering kosong).
   *
   * @return list<object{kode: string, nama: string}>
   */
    private function fetchKantinOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('sm_kantin')
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->orderBy('NamaKantin')
                ->get(['username', 'NamaKantin'])
                ->map(static function ($row) {
                    $username = trim((string) ($row->username ?? ''));
                    $nama = trim((string) ($row->NamaKantin ?? ''));

                    return (object) [
                        'kode' => $username,
                        'nama' => $nama !== '' ? $nama . ' (' . $username . ')' : $username,
                    ];
                })
                ->filter(static fn ($r) => $r->kode !== '')
                ->unique('kode')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('RekapPencairanKantin fetchKantinOptions failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array{username: string, kdmercan: string, nama: string}|null
     */
    private function resolveKantin(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        try {
            $row = DB::connection('DATA_MYSQL')
                ->table('sm_kantin')
                ->whereRaw('TRIM(username) = ?', [$username])
                ->first(['username', 'KDMERCAN', 'NamaKantin']);
        } catch (\Throwable) {
            $row = null;
        }

        if (!$row) {
            return null;
        }

        return [
            'username' => trim((string) $row->username),
            'kdmercan' => trim((string) ($row->KDMERCAN ?? '')),
            'nama' => trim((string) ($row->NamaKantin ?? '')),
        ];
    }

    /**
     * Kode yang mungkin tercatat di sm_mercan_cair.KDMERCAN untuk kantin ini.
     *
     * @return list<string>
     */
    private function pencairanCodes(array $kantin): array
    {
        return array_values(array_unique(array_filter([
            $kantin['kdmercan'],
            $kantin['username'],
        ], static fn ($v) => $v !== '')));
    }

  /**
   * @return array{0: Collection<int, object>, 1: float}
   */
    private function fetchTransaksiRows(?array $kantin, string $dari, string $sampai): array
    {
        $hasMercan = Schema::connection('DATA_MYSQL')->hasTable('sm_mercan');

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->join('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('sm_kantin', function ($join) {
                $join->on(DB::raw('TRIM(sm_kantin.username)'), '=', DB::raw('TRIM(scctcashout.Teller)'));
            })
            ->when($hasMercan, function ($q) {
                $q->leftJoin('sm_mercan', function ($join) {
                    $join->on(DB::raw('TRIM(sm_mercan.KDMERCAN)'), '=', DB::raw('TRIM(sm_kantin.KDMERCAN)'));
                });
            })
            ->whereRaw('UPPER(TRIM(scctcashout.FIDBANK)) = ?', ['BUY']);

        $this->applySchoolScope($query);

        if ($kantin !== null) {
            $query->whereRaw('TRIM(scctcashout.Teller) = ?', [$kantin['username']]);
        }

        $from = $this->parseDate($dari);
        $to = $this->parseDate($sampai);
        if ($from) {
            $query->where('scctcashout.TanggalKeluar', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('scctcashout.TanggalKeluar', '<=', $to->copy()->endOfDay());
        }

        $rows = $query
            ->select([
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as saldo',
                $hasMercan
                    ? DB::raw('COALESCE(NULLIF(TRIM(sm_mercan.NamaMercan), \'\'), NULLIF(TRIM(sm_kantin.KDMERCAN), \'\'), \'-\') as mercan')
                    : DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.KDMERCAN), \'\'), \'-\') as mercan'),
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller), \'-\') as kantin'),
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut')
            ->limit(self::MAX_ROWS)
            ->get();

        $total = (float) $rows->sum(static fn ($r) => (float) ($r->saldo ?? 0));

        return [$rows, $total];
    }

    private function applySchoolScope($query): void
    {
        $code01 = \App\Support\SchoolScope::codeFromUser();
        if ($code01 === null || $code01 === '') {
            return;
        }

        $query->whereRaw('TRIM(scctcust.CODE01) = ?', [$code01]);
    }

  /**
   * @return array{0: Collection<int, object>, 1: float}
   */
    private function fetchPencairanRows(?array $kantin, string $dari, string $sampai): array
    {
        try {
            $available = Schema::connection('DATA_MYSQL')->getColumnListing('sm_mercan_cair');
            $has = static fn (string $col) => in_array($col, $available, true);

            $query = DB::connection('DATA_MYSQL')->table('sm_mercan_cair');

            if ($has('TglTerima')) {
                $query->orderByDesc('TglTerima');
            }
            if ($has('urut')) {
                $query->orderByDesc('urut');
            }

            if ($kantin !== null && $has('KDMERCAN')) {
                $codes = $this->pencairanCodes($kantin);
                $query->whereIn(DB::raw('TRIM(KDMERCAN)'), $codes);
            }

            $from = $this->parseDate($dari);
            $to = $this->parseDate($sampai);
            if ($from && $to) {
                $hasPeriode = $has('dari_tgl_tran') && $has('akhir_tgl_tran');
                $query->where(function ($q) use ($from, $to, $hasPeriode, $has) {
                    if ($hasPeriode) {
                        $q->where(function ($q2) use ($from, $to) {
                            $q2->where('dari_tgl_tran', '<=', $to->format('Y-m-d'))
                                ->where('akhir_tgl_tran', '>=', $from->format('Y-m-d'));
                        });
                    }
                    if ($has('TglTerima')) {
                        $q->orWhereBetween('TglTerima', [
                            $from->copy()->startOfDay(),
                            $to->copy()->endOfDay(),
                        ]);
                    }
                });
            }

            $select = [];
            foreach ([
                'TglTerima' => 'tgl_terima',
                'NamaPenerima' => 'nama_penerima',
                'Nominal' => 'nominal',
                'NoTerima' => 'no_terima',
                'dari_tgl_tran' => 'dari_tgl_tran',
                'akhir_tgl_tran' => 'akhir_tgl_tran',
            ] as $col => $alias) {
                if ($has($col)) {
                    $select[] = $col === $alias ? $col : "{$col} as {$alias}";
                }
            }

            if ($select === []) {
                return [collect(), 0, false];
            }

            $rows = $query->select($select)->limit(self::MAX_ROWS)->get();
            $total = (float) $rows->sum(static fn ($r) => (float) ($r->nominal ?? 0));

            return [$rows, $total, false];
        } catch (\Throwable $e) {
            Log::error('RekapPencairanKantin fetchPencairanRows failed', [
                'kantin' => $kantin['username'] ?? null,
                'dari' => $dari,
                'sampai' => $sampai,
                'message' => $e->getMessage(),
            ]);

            return [collect(), 0, true];
        }
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
}
