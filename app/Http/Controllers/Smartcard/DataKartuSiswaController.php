<?php

namespace App\Http\Controllers\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DataKartuSiswaController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $custid = $isSearch ? (int) $request->query('custid', 0) : 0;
        $noKartu = $isSearch ? trim((string) $request->query('no_kartu', '')) : '';
        $pin = trim((string) $request->query('pin', '123'));
        if ($pin === '') {
            $pin = '123';
        }

        $nama = '';
        $siswaLabel = $isSearch ? trim((string) $request->query('siswa_search', '')) : '';
        if ($isSearch && $custid > 0) {
            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->where('CUSTID', $custid)
                ->first(['NOCUST', 'NMCUST']);
            if ($siswa) {
                $nama = trim((string) ($siswa->NMCUST ?? ''));
                if ($siswaLabel === '') {
                    $nis = trim((string) ($siswa->NOCUST ?? ''));
                    $siswaLabel = $nis !== '' && $nama !== '' ? $nis . ' - ' . $nama : ($nis !== '' ? $nis : $nama);
                }
            }
        }

        return view('smartcard.data-kartu-siswa.index', [
            'kartuRows' => $this->fetchRows($custid, $noKartu, $isSearch),
            'isSearch' => $isSearch,
            'custid' => $custid,
            'noKartu' => $noKartu,
            'pin' => $pin,
            'nama' => $nama,
            'siswaLabel' => $siswaLabel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'custid' => ['required', 'integer', 'min:1'],
            'no_kartu' => ['required', 'string', 'max:16'],
            'pin' => ['nullable', 'string', 'max:6'],
        ], [
            'custid.required' => 'Pilih siswa (NIS) terlebih dahulu.',
            'custid.min' => 'Data siswa tidak valid.',
            'no_kartu.required' => 'Nomor kartu wajib diisi.',
            'no_kartu.max' => 'Nomor kartu maksimal 16 karakter.',
            'pin.max' => 'PIN maksimal 6 karakter.',
        ]);

        $custid = (int) $validated['custid'];
        $noKartu = preg_replace('/\s+/', '', (string) $validated['no_kartu']);
        if ($noKartu === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Nomor kartu RFID wajib diisi.');
        }
        $pin = trim((string) ($validated['pin'] ?? ''));
        if ($pin === '') {
            $pin = '123';
        }

        $siswa = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->first(['CUSTID', 'NOCUST', 'NMCUST']);

        if (!$siswa) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Siswa tidak ditemukan di database.');
        }

        $db = DB::connection('DATA_MYSQL');

        try {
            $hasUrut = Schema::connection('DATA_MYSQL')->hasColumn('sm_pin', 'urut');

            $result = $db->transaction(function () use ($db, $custid, $noKartu, $pin, $hasUrut) {
                $existsPid = $db->table('sm_pin')
                    ->where('PID', $noKartu)
                    ->lockForUpdate()
                    ->exists();
                if ($existsPid) {
                    return 'duplicate';
                }

                $row = [
                    'CUSTID' => $custid,
                    'PID' => $noKartu,
                    'PIN' => $pin,
                    'BLOKIR' => '0',
                ];

                // Di sebagian database sm_pin punya kolom urut (bukan auto-increment), di sebagian tidak.
                if ($hasUrut) {
                    $row['urut'] = ((int) $db->table('sm_pin')->lockForUpdate()->max('urut')) + 1;
                }

                $db->table('sm_pin')->insert($row);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Log::error('DataKartuSiswa store failed', [
                'custid' => $custid,
                'pid' => $noKartu,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Gagal menyimpan data kartu: ' . \Illuminate\Support\Str::limit($e->getMessage(), 300));
        }

        if ($result === 'duplicate') {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Nomor kartu sudah digunakan. Setiap nomor kartu harus unik.');
        }

        return redirect()
            ->route('smartcard.data_kartu', ['search' => 1, 'custid' => $custid])
            ->with('smartcard_success', 'Kartu ' . $noKartu . ' berhasil disimpan untuk ' . trim((string) ($siswa->NMCUST ?? '')) . '.');
    }

    public function toggleBlokir(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pid' => ['required', 'string', 'max:16'],
            'blokir' => ['required', 'in:0,1'],
        ]);

        $pid = trim((string) $validated['pid']);
        $blokir = (string) $validated['blokir'];

        try {
            $updated = DB::connection('DATA_MYSQL')
                ->table('sm_pin')
                ->where('PID', $pid)
                ->update(['BLOKIR' => $blokir]);
        } catch (\Throwable $e) {
            Log::error('DataKartuSiswa toggleBlokir failed', ['pid' => $pid, 'message' => $e->getMessage()]);

            return redirect()->back()->with('smartcard_error', 'Gagal mengubah status blokir kartu ' . $pid . '.');
        }

        if ($updated === 0 && !DB::connection('DATA_MYSQL')->table('sm_pin')->where('PID', $pid)->exists()) {
            return redirect()->back()->with('smartcard_error', 'Kartu ' . $pid . ' tidak ditemukan.');
        }

        return redirect()->back()->with(
            'smartcard_success',
            'Kartu ' . $pid . ($blokir === '1' ? ' berhasil diblokir.' : ' berhasil dibuka blokirnya.')
        );
    }

    public function searchSiswa(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['rows' => []]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where(function ($builder) use ($like) {
                $builder->where('NOCUST', 'like', $like)
                    ->orWhere('NMCUST', 'like', $like)
                    ->orWhere('NUM2ND', 'like', $like);
            })
            ->orderBy('NMCUST')
            ->limit(40);

        SchoolScope::apply($query, 'scctcust');

        $rows = $query
            ->get(['CUSTID', 'NOCUST', 'NMCUST', 'NUM2ND', 'DESC04'])
            ->map(function ($s) {
                $nocust = trim((string) ($s->NOCUST ?? ''));
                $nmcust = trim((string) ($s->NMCUST ?? ''));
                $num2nd = trim((string) ($s->NUM2ND ?? ''));
                $nisLike = $nocust !== '' ? $nocust : $num2nd;
                $label = $nisLike !== '' && $nmcust !== ''
                    ? $nisLike . ' - ' . $nmcust
                    : ($nisLike !== '' ? $nisLike : $nmcust);

                return [
                    'cid' => (int) $s->CUSTID,
                    'label' => $label,
                    'nocust' => $nocust,
                    'nis' => $nocust,
                    'nis_like' => $nisLike,
                    'num2nd' => $num2nd,
                    'nmcust' => $nmcust,
                    'angkatan' => trim((string) ($s->DESC04 ?? '')),
                ];
            })
            ->values()
            ->all();

        return response()->json(['rows' => $rows]);
    }

    private function fetchRows(int $custid, string $noKartu, bool $isSearch): LengthAwarePaginator
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->join('scctcust', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
            ->select([
                'scctcust.NOCUST as nis',
                'scctcust.NMCUST as nama',
                'sm_pin.PID as no_kartu',
                'sm_pin.BLOKIR as blokir',
            ]);

        SchoolScope::apply($query, 'scctcust');

        if ($isSearch) {
            if ($custid > 0) {
                $query->where('sm_pin.CUSTID', $custid);
            }
            if ($noKartu !== '') {
                $query->where('sm_pin.PID', $noKartu);
            }
        }

        return $query
            ->orderByDesc('scctcust.NOCUST')
            ->orderByDesc('sm_pin.PID')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
