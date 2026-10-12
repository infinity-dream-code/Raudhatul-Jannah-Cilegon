<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MstAngkatanUnit;
use App\Models\mst_sekolah;
use App\Models\mst_thn_aka;
use App\Models\ValidationMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SettingAngkatanUnitController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'Setting Angkatan Unit';
    public string $dataTitle = 'Setting Angkatan Unit';

    public function index()
    {
        $settings = collect();
        $tableReady = true;
        try {
            $settings = MstAngkatanUnit::query()->get()->keyBy(fn ($row) => trim((string) $row->CODE01));
        } catch (Exception $e) {
            $tableReady = false;
        }

        $defaultStart = mst_thn_aka::defaultStartYear();

        $rows = mst_sekolah::query()
            ->select(['CODE01', 'DESC01'])
            ->whereNotNull('CODE01')
            ->orderBy('DESC01')
            ->get()
            ->map(function ($unit) use ($settings) {
                $code = trim((string) $unit->CODE01);
                $setting = $settings->get($code);

                return (object) [
                    'CODE01' => $code,
                    'DESC01' => $unit->DESC01,
                    'thn_aka_mulai' => $setting->thn_aka_mulai ?? null,
                    'updated_by' => $setting->updated_by ?? null,
                    'updated_at' => $setting->updated_at ?? null,
                ];
            });

        $thnAka = mst_thn_aka::query()
            ->whereNotNull('thn_aka')
            ->where('thn_aka', '!=', '')
            ->orderBy('thn_aka')
            ->pluck('thn_aka')
            ->unique()
            ->values();

        return view('admin.master_data.setting_angkatan_unit.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'rows' => $rows,
            'thnAka' => $thnAka,
            'defaultStart' => $defaultStart,
            'tableReady' => $tableReady,
        ]);
    }

    public function update(Request $request, string $code01)
    {
        $code01 = trim($code01);
        $unit = mst_sekolah::query()->where('CODE01', $code01)->first();
        if (!$unit) {
            return response()->json(['message' => 'Unit tidak ditemukan.'], 422);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'thn_aka_mulai' => ['nullable', 'regex:/^\d{4}\/\d{4}/', 'max:18'],
            ],
            ValidationMessage::messages(),
            ['thn_aka_mulai' => 'Tahun Akademik Angkatan 1'],
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $thnAkaMulai = trim((string) $request->input('thn_aka_mulai', ''));

        try {
            if ($thnAkaMulai === '') {
                MstAngkatanUnit::query()->where('CODE01', $code01)->delete();
            } else {
                MstAngkatanUnit::query()->updateOrCreate(
                    ['CODE01' => $code01],
                    [
                        'thn_aka_mulai' => $thnAkaMulai,
                        'updated_by' => Auth::user()->username ?? Auth::user()->name ?? null,
                        'updated_at' => now(),
                    ]
                );
            }

            mst_thn_aka::forgetUnitStartYearMap();

            return response()->json([
                'message' => 'Setting angkatan unit ' . e($unit->DESC01) . ' berhasil disimpan.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan setting angkatan unit. Pastikan tabel mst_angkatan_unit sudah dibuat.',
            ], 422);
        }
    }
}
