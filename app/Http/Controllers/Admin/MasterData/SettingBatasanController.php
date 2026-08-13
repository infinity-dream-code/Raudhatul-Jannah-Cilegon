<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\SmBatasan;
use App\Models\ValidationMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingBatasanController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'Setting Batasan';
    public string $dataTitle = 'Setting Batasan';

    public function index(Request $request)
    {
        $periode = trim((string) $request->query('periode', ''));
        $kelompok = trim((string) $request->query('kelompok_kantin', ''));
        $aktif = $request->query('aktif', 'all');

        $query = SmBatasan::query()->orderByDesc('urut');

        if ($periode !== '') {
            $query->where('periode', 'like', '%' . $periode . '%');
        }
        if ($kelompok !== '') {
            $query->where('kelompok_kantin', 'like', '%' . $kelompok . '%');
        }
        if ($aktif !== 'all' && $aktif !== '' && $aktif !== null) {
            $query->where('aktif', (int) $aktif);
        }

        $rows = $query->paginate(10)->withQueryString();

        return view('admin.master_data.setting_batasan.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'rows' => $rows,
            'filters' => [
                'periode' => $periode,
                'kelompok_kantin' => $kelompok,
                'aktif' => $aktif,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->makeValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            SmBatasan::create($this->payload($request));

            DB::connection('DATA_MYSQL')->commit();

            return response()->json([
                'message' => 'Setting batasan berhasil ditambahkan.',
            ]);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json([
                'message' => 'Gagal menambah setting batasan.<hr>' . $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $batasan = SmBatasan::query()->where('urut', $id)->first();
        if (!$batasan) {
            return response()->json(['message' => 'Data batasan tidak ditemukan.'], 422);
        }

        $validator = $this->makeValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            $batasan->update($this->payload($request));

            DB::connection('DATA_MYSQL')->commit();

            return response()->json([
                'message' => 'Setting batasan berhasil diubah.',
            ]);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json([
                'message' => 'Gagal mengubah setting batasan.<hr>' . $e->getMessage(),
            ], 422);
        }
    }

    private function makeValidator(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'periode' => ['required', 'string', 'max:50'],
                'batas_belanja_hari' => ['required', 'numeric', 'min:0'],
                'batas_cash' => ['required', 'numeric', 'min:0'],
                'aktif' => ['required', 'in:0,1'],
                'kelompok_kantin' => ['nullable', 'string', 'max:100'],
            ],
            ValidationMessage::messages(),
            [
                'periode' => 'Periode',
                'batas_belanja_hari' => 'Batas Belanja Hari',
                'batas_cash' => 'Batas Cash',
                'aktif' => 'Status Aktif',
                'kelompok_kantin' => 'Kelompok Kantin',
            ],
        );
    }

    private function payload(Request $request): array
    {
        return [
            'periode' => trim((string) $request->input('periode')),
            'batas_belanja_hari' => (float) $request->input('batas_belanja_hari'),
            'batas_cash' => (float) $request->input('batas_cash'),
            'aktif' => (int) $request->input('aktif'),
            'kelompok_kantin' => trim((string) $request->input('kelompok_kantin', '')) ?: null,
        ];
    }
}
