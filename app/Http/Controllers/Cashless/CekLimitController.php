<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CekLimitController extends Controller
{
    private string $title = 'Cek Limit';
    private string $mainTitle = 'Cek Limit';
    private string $cacheKey = 'Cek Limit';

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';
        Cache::add($key, 1);
    }

    public function index()
    {
        $data['title'] = $this->title;

        return view('cashless.admin.cek_limit.index', $data);
    }

    public function getLimit(Request $request)
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
                    'data' => 'error',
                ], 422);
            }

            // Limit jajan default (sama pola sebelumnya)
            $limit = 20000;

            return response()->json([
                'data' => $limit,
                'nama' => $siswa->nmcust ?? '',
                'nis' => $siswa->nocust ?? '',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CekLimit - Error:', [
                'message' => $e->getMessage(),
                'tap_id' => $tapId,
            ]);

            return response()->json([
                'message' => 'gagal mendapatkan data limit, silahkan coba lagi',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
