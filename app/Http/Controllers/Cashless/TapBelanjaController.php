<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use App\Support\FacePay\FaceKantinBelanjaLog;
use App\Support\FacePay\FaceStudentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TapBelanjaController extends Controller
{
    public string $title;

    public function __construct()
    {
        $this->title = 'Tap Belanja';
    }

    public function index(FaceStudentRepository $faceRepo)
    {
        $faceStatus = $faceRepo->pingStatus();

        return view('cashless.admin.tap_belanja.index', [
            'title' => $this->title,
            'faceDbReady' => $faceStatus['ok'],
        ]);
    }

    public function faceReferences(FaceStudentRepository $faceRepo): JsonResponse
    {
        $faceStatus = $faceRepo->pingStatus();
        if (!$faceStatus['ok']) {
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

    public function getSaldoByNis(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'nis' => ['required', 'string'],
                'siswaId' => ['nullable', 'string'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $nis = preg_replace('/\D/', '', (string) $request->input('nis'));
        if ($nis === '') {
            return response()->json(['ok' => false, 'error' => 'NIS tidak valid'], 422);
        }

        try {
            $resolved = $this->resolveSiswaByNis($nis);
            if (!$resolved) {
                return response()->json(['ok' => false, 'error' => 'Siswa / kartu tidak ditemukan di cashless'], 422);
            }

            $nama = $resolved['nama'];
            $saldo = $this->resolveSaldo($resolved['tap_id'], $resolved['cust_id'], $nama);

            return response()->json([
                'ok' => true,
                'data' => [
                    'nokartu' => $nis,
                    'tap_id' => $resolved['tap_id'],
                    'nama' => $nama,
                    'saldo' => $saldo,
                    'siswaId' => (string) $request->input('siswaId', ''),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('getSaldoByNis', ['nis' => $nis, 'error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'error' => 'Gagal inquiry saldo',
            ], 422);
        }
    }

    public function paymentByNis(Request $request, FaceKantinBelanjaLog $faceLog): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'nis' => ['required', 'string'],
                'nominal' => ['nullable'],
                'belanja' => ['nullable'],
                'saldoSebelum' => ['nullable', 'numeric'],
                'namaSiswa' => ['nullable', 'string'],
                'siswaId' => ['nullable', 'string'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $nis = preg_replace('/\D/', '', (string) $request->input('nis'));
        $nominal = $this->parseNominal($request->input('nominal') ?? $request->input('belanja'));
        if ($nis === '' || $nominal <= 0) {
            return response()->json(['ok' => false, 'error' => 'NIS atau nominal tidak valid'], 422);
        }

        $resolved = $this->resolveSiswaByNis($nis);
        if (!$resolved) {
            return response()->json(['ok' => false, 'error' => 'Siswa / kartu tidak ditemukan'], 422);
        }

        $saldoSebelum = $request->has('saldoSebelum') ? (int) $request->input('saldoSebelum') : null;
        $fakeRequest = new Request([
            'tap_id' => $resolved['tap_id'],
            'belanja' => number_format($nominal, 0, '', '.'),
        ]);
        $fakeRequest->setLaravelSession($request->session());
        $response = $this->payment($fakeRequest);
        $payload = $response->getData(true);

        if (($payload['code'] ?? null) === 1000) {
            $kantinUser = session('cashless_user', []);
            $faceLog->save([
                'siswaId' => (string) $request->input('siswaId', ''),
                'nis' => $nis,
                'namaSiswa' => (string) ($payload['data']['nama'] ?? $request->input('namaSiswa') ?? $resolved['nama']),
                'nominal' => $nominal,
                'saldoSebelum' => $saldoSebelum,
                'saldoSesudah' => isset($payload['data']['sisa_saldo']) ? (int) $payload['data']['sisa_saldo'] : null,
                'namaKantin' => (string) ($kantinUser['username'] ?? ''),
                'displayNameKantin' => (string) ($kantinUser['kantin'] ?? $kantinUser['username'] ?? ''),
                'metode' => 'facepay_cashless',
                'merchantStatus' => (string) ($payload['status'] ?? 'ok'),
                'merchantRaw' => $payload,
            ]);

            return response()->json([
                'ok' => true,
                'data' => $payload['data'] ?? [],
                'message' => $payload['message'] ?? 'Transaksi berhasil',
                'code' => $payload['code'] ?? 1000,
            ]);
        }

        return response()->json([
            'ok' => false,
            'error' => $payload['message'] ?? 'Pembayaran gagal',
            'code' => $payload['code'] ?? 9999,
        ], 422);
    }

    public function getSaldo(Request $request)
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
                $message = "{$message} Dan beberapa error lainnya";
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
                    'message' => 'Data tidak ditemukan, silahkan tap kartu yang valid',
                    'errors' => ['tap_id' => ['Data tidak ditemukan, silahkan tap kartu yang valid']],
                ], 422);
            }

            $nama = (string) ($siswa->nmcust ?? '');
            $saldo = $this->resolveSaldo($tapId, (string) $siswa->CUSTID, $nama);

            // Format yang diharapkan frontend: [tap_id, saldo, nama]
            return response()->json([
                'data' => [$tapId, (string) $saldo, $nama],
            ]);
        } catch (\Exception $e) {
            Log::error('getSaldo - Error:', [
                'message' => $e->getMessage(),
                'tap_id' => $tapId,
            ]);

            return response()->json([
                'message' => 'Gagal mendapatkan data saldo, silahkan coba lagi',
            ], 422);
        }
    }

    /**
     * Ambil saldo dari SP bila format valid; fallback ke sccttran_cashless.
     */
    private function resolveSaldo(string $tapId, string $custId, string &$nama): int
    {
        try {
            $rows = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$tapId]);
            $raw = (string) ($rows[0]->saldo ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), static fn ($v) => $v !== ''));

            // Format umum: status|saldo|nama ATAU id|saldo|nama
            if (count($parts) >= 3) {
                $maybeSaldo = $parts[1];
                if (is_numeric($maybeSaldo)) {
                    if (!empty($parts[2])) {
                        $nama = $parts[2];
                    }

                    return (int) $maybeSaldo;
                }
            }

            // Kadang SP hanya mengembalikan angka saldo
            if (count($parts) === 1 && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        } catch (\Throwable $e) {
            Log::warning('getSaldo - SP failed, fallback to sccttran_cashless', [
                'tap_id' => $tapId,
                'error' => $e->getMessage(),
            ]);
        }

        $agg = DB::connection('DATA_MYSQL')
            ->table('sccttran_cashless')
            ->where('CUSTID', $custId)
            ->selectRaw('COALESCE(SUM(KREDIT),0) - COALESCE(SUM(DEBET),0) AS saldo')
            ->value('saldo');

        return (int) $agg;
    }

    const STATUS_MAP = [
        'ok' => [
            'code' => 1000,
            'message' => 'Transaksi berhasil',
        ],
        'insufficient_balance' => [
            'code' => 2001,
            'message' => 'Saldo tidak cukup',
        ],
        'unknown_or_blocked_card' => [
            'code' => 2002,
            'message' => 'Kartu Terblokir',
        ],
        'daily_transaction_limit_exceeded' => [
            'code' => 2003,
            'message' => 'Limit transaksi sudah tercapai!',
        ],
    ];

    public function payment(Request $request)
    {
        Log::info('payment - Started', [
            'tap_id' => $request->tap_id,
            'belanja_raw' => $request->belanja,
            'session_user' => session('cashless_user.username'),
        ]);

        $validator = Validator::make(
            $request->all(),
            [
                'tap_id' => ['required', 'string'],
                'belanja' => ['required', 'regex:/^[0-9]+(\.[0-9]{3})*$/', 'not_in:0'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json([
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $nominal = str_replace('.', '', $request->belanja);

            $result = DB::connection('DATA_MYSQL')
                ->select(
                    'SELECT WebPaymentBUY(?,?,?) AS result',
                    [
                        $request->tap_id,
                        $nominal,
                        session('cashless_user.username'),
                    ]
                );

            $result = $result[0]->result ?? 'error';
            $statusKey = null;
            $data = [];

            if (str_contains($result, '|')) {
                $parts = explode('|', $result);
                $statusKey = strtolower($parts[0]);

                if ($statusKey === 'ok') {
                    $data = [
                        'nama' => $parts[1] ?? null,
                        'sisa_saldo' => $parts[2] ?? null,
                    ];
                }
            } else {
                $statusKey = strtolower($result);
            }

            $config = self::STATUS_MAP[$statusKey] ?? [
                'code' => 9999,
                'message' => 'Unknown error',
            ];

            return response()->json([
                'status' => $statusKey,
                'code' => $config['code'],
                'message' => $config['message'],
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('payment - Exception occurred', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memproses transaksi, silahkan coba lagi',
            ], 422);
        }
    }

    /**
     * @return array{tap_id: string, cust_id: string, nama: string}|null
     */
    private function resolveSiswaByNis(string $nis): ?array
    {
        $nis = trim($nis);
        if ($nis === '') {
            return null;
        }

        $siswa = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->join('sm_pin', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
            ->select(['scctcust.CUSTID', 'scctcust.nmcust', 'scctcust.nocust', 'sm_pin.PID'])
            ->where(function ($q) use ($nis) {
                $q->where('scctcust.NOCUST', $nis)
                    ->orWhereRaw('TRIM(scctcust.NOCUST) = ?', [$nis]);
            })
            ->first();

        if (!$siswa || blank($siswa->PID ?? null)) {
            return null;
        }

        return [
            'tap_id' => trim((string) $siswa->PID),
            'cust_id' => trim((string) $siswa->CUSTID),
            'nama' => trim((string) ($siswa->nmcust ?? '')),
        ];
    }

    private function parseNominal(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        $clean = preg_replace('/[^\d]/', '', (string) $raw);

        return max(0, (int) $clean);
    }
}
