<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\SmBatasan;
use App\Models\ValidationMessage;
use App\Support\FacePay\FaceStudentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CekSaldoController extends Controller
{
    private string $title = 'Cek Saldo';
    private string $cacheKey = 'Cek Saldo';

    public function __construct()
    {
        Cache::add(Str::slug($this->cacheKey) . '_cache_version', 1);
    }

    public function index(FaceStudentRepository $faceRepo)
    {
        $isSiswa = $this->isSiswa();

        return view('cashless.admin.cek_saldo.index', [
            'title' => $this->title,
            'isSiswa' => $isSiswa,
            'faceDbReady' => $isSiswa ? false : $faceRepo->pingStatus()['ok'],
        ]);
    }

    public function faceReferences(FaceStudentRepository $faceRepo): JsonResponse
    {
        if ($this->isSiswa()) {
            return response()->json(['ok' => false, 'error' => 'Akses ditolak untuk role ini.'], 403);
        }

        if (!$faceRepo->pingStatus()['ok']) {
            return response()->json([
                'ok' => false,
                'error' => 'Layanan FacePay sementara tidak tersedia. Hubungi admin ICT.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'data' => $faceRepo->listWithFoto(),
        ]);
    }

    public function getDataByNis(Request $request): JsonResponse
    {
        if ($this->isSiswa()) {
            return response()->json(['message' => 'Akses ditolak untuk role ini.'], 403);
        }

        $nis = preg_replace('/\D/', '', (string) $request->input('nis'));
        if ($nis === '') {
            return response()->json(['message' => 'NIS tidak valid'], 422);
        }

        try {
            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->select(['CUSTID', 'NMCUST as nmcust', 'NOCUST as nocust'])
                ->whereRaw('TRIM(NOCUST) = ?', [$nis])
                ->first();

            if (!$siswa) {
                return response()->json(['message' => 'Siswa tidak ditemukan di cashless'], 422);
            }

            $pid = DB::connection('DATA_MYSQL')
                ->table('sm_pin')
                ->where('CUSTID', $siswa->CUSTID)
                ->value('PID');
            $tapId = $pid ? trim((string) $pid) : $nis;

            return response()->json($this->buildResponse($siswa, $tapId), 200);
        } catch (\Throwable $e) {
            Log::error('CekSaldo - getDataByNis error', ['nis' => $nis, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'gagal mendapatkan data saldo, silahkan coba lagi'], 422);
        }
    }

    private function isSiswa(): bool
    {
        return strtolower((string) session('cashless_user.role')) === 'siswa';
    }

    private function buildResponse(object $siswa, string $tapId): array
    {
        $nama = (string) ($siswa->nmcust ?? '');
        $nis = (string) ($siswa->nocust ?? '');
        $saldo = $this->resolveSaldo($tapId, (string) $siswa->CUSTID, $nama);

        $transaksi = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->where('CUSTID', $siswa->CUSTID)
            ->orderByDesc('TanggalKeluar')
            ->limit(10)
            ->get(['TanggalKeluar', 'BILLAM', 'Teller'])
            ->map(function ($row) {
                return [
                    'tanggal' => $row->TanggalKeluar,
                    'nominal' => (float) $row->BILLAM,
                    'merchant' => $row->Teller,
                ];
            })
            ->values();

        $limitInfo = SmBatasan::limitInfoFor((string) $siswa->CUSTID);

        return [
            'saldo' => $saldo,
            'batasan_limit' => SmBatasan::activeBatasanUtang(),
            'limit_harian' => $limitInfo['limit_harian'] ?? null,
            'sisa_limit_harian' => $limitInfo['sisa_limit_harian'] ?? null,
            'nama' => $nama,
            'nis' => $nis,
            'transaksi' => $transaksi,
        ];
    }

    public function getData(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'tap_id' => ['required', 'string'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa masalah validasi lainnya, silahkan periksa form anda!";
            }

            return response()->json([
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tapId = trim((string) $request->input('tap_id'));

        try {
            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->join('sm_pin', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
                ->select(['scctcust.CUSTID', 'scctcust.nmcust', 'scctcust.nocust'])
                ->where('sm_pin.PID', $tapId)
                ->first();

            if (!$siswa) {
                return response()->json([
                    'message' => 'Kartu diblokir atau tidak ditemukan',
                    'errors' => ['tap_id' => ['Kartu diblokir atau tidak ditemukan']],
                ], 422);
            }

            return response()->json($this->buildResponse($siswa, $tapId), 200);
        } catch (\Exception $e) {
            Log::error('CekSaldo - Error:', [
                'message' => $e->getMessage(),
                'tap_id' => $tapId,
            ]);

            return response()->json([
                'message' => 'gagal mendapatkan data saldo, silahkan coba lagi',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function resolveSaldo(string $tapId, string $custId, string &$nama): int
    {
        try {
            $rows = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$tapId]);
            $raw = (string) ($rows[0]->saldo ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), static fn ($v) => $v !== ''));

            if (count($parts) >= 3 && is_numeric($parts[1])) {
                if (!empty($parts[2])) {
                    $nama = $parts[2];
                }

                return (int) $parts[1];
            }

            if (count($parts) === 1 && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        } catch (\Throwable $e) {
            Log::warning('CekSaldo - SP failed, fallback sccttran_cashless', [
                'tap_id' => $tapId,
                'error' => $e->getMessage(),
            ]);
        }

        return (int) DB::connection('DATA_MYSQL')
            ->table('sccttran_cashless')
            ->where('CUSTID', $custId)
            ->selectRaw('COALESCE(SUM(KREDIT),0) - COALESCE(SUM(DEBET),0) AS saldo')
            ->value('saldo');
    }
}
