<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\SmKantin;
use App\Models\ValidationMessage;
use App\Support\AndroidMerchantProcedure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserKantinController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'User Kantin';
    public string $dataTitle = 'User Kantin';

    public function index(Request $request)
    {
        $namaKantin = trim((string) $request->query('nama_kantin', ''));
        $username = trim((string) $request->query('username', ''));

        $query = SmKantin::query()->orderByDesc('urut');

        if ($namaKantin !== '') {
            $query->where('NamaKantin', 'like', '%' . $namaKantin . '%');
        }
        if ($username !== '') {
            $query->where('username', 'like', '%' . $username . '%');
        }

        $rows = $query->paginate(10)->withQueryString();

        return view('admin.master_data.user_kantin.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'rows' => $rows,
            'filters' => [
                'nama_kantin' => $namaKantin,
                'username' => $username,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'NamaKantin' => ['required', 'string', 'max:50'],
                'username' => ['required', 'string', 'max:50'],
            ],
            ValidationMessage::messages(),
            [
                'NamaKantin' => 'Nama Kantin',
                'username' => 'Username',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            $result = AndroidMerchantProcedure::add(
                (string) $request->input('NamaKantin'),
                (string) $request->input('username'),
            );
            AndroidMerchantProcedure::assertAddOk($result);
            DB::connection('DATA_MYSQL')->commit();

            return response()->json([
                'message' => 'User kantin berhasil ditambahkan. Password awal: 123',
            ], 200);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json([
                'message' => 'Gagal menambah user kantin.<hr>' . $e->getMessage(),
            ], 422);
        }
    }

    public function resetPassword(Request $request, $id)
    {
        $kantin = SmKantin::query()->where('urut', $id)->first();
        if (!$kantin) {
            return response()->json(['message' => 'Data kantin tidak ditemukan.'], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            AndroidMerchantProcedure::resetPassword(
                (string) $kantin->username,
                (string) (Auth::user()?->users ?? Auth::user()?->name ?? 'admin'),
                gethostname() ?: request()->ip()
            );
            DB::connection('DATA_MYSQL')->commit();

            return response()->json([
                'message' => 'Password user ' . $kantin->username . ' direset ke 123.',
            ], 200);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json([
                'message' => 'Gagal reset password.<hr>' . $e->getMessage(),
            ], 422);
        }
    }

    public function resetPasswordBulk(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'integer'],
            ],
            ValidationMessage::messages(),
            ['ids' => 'User kantin'],
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $list = SmKantin::query()
            ->whereIn('urut', $ids->all())
            ->get(['urut', 'username']);

        if ($list->isEmpty()) {
            return response()->json(['message' => 'Data kantin tidak ditemukan.'], 422);
        }

        $admin = (string) (Auth::user()?->users ?? Auth::user()?->name ?? 'admin');
        $machine = gethostname() ?: request()->ip();
        $ok = 0;
        $failed = [];

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            foreach ($list as $item) {
                try {
                    AndroidMerchantProcedure::resetPassword((string) $item->username, $admin, $machine);
                    $ok++;
                } catch (Exception $e) {
                    $failed[] = $item->username . ': ' . $e->getMessage();
                }
            }
            DB::connection('DATA_MYSQL')->commit();
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json([
                'message' => 'Gagal reset password jamak.<hr>' . $e->getMessage(),
            ], 422);
        }

        $message = "Berhasil reset {$ok} user kantin (password: 123).";
        if ($failed !== []) {
            $message .= '<hr>Gagal: ' . implode('<br>', $failed);
        }

        return response()->json([
            'message' => $message,
            'ok' => $ok,
            'failed' => count($failed),
        ], $failed === [] ? 200 : 422);
    }
}
