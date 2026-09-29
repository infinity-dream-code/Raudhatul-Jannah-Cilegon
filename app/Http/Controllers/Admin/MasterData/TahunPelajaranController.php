<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\mst_thn_aka;
use App\Models\ValidationMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TahunPelajaranController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'Tahun Akademik';
    public string $dataTitle = 'Tahun Akademik';

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['dataTitle'] = $this->dataTitle;
        $data['columnsUrl'] = route('admin.master-data.tahun-pelajaran.get-column');
        $data['datasUrl'] = route('admin.master-data.tahun-pelajaran.get-data');

        return view('admin.master_data.tahun_akademik.index', $data);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'no'],
            ['data' => 'angkatan_label', 'name' => 'Angkatan', 'searchable' => false, 'orderable' => false],
            ['data' => 'thn_aka', 'name' => 'Tahun Pelajaran', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $columnIndex_arr = $request->get('order', []);
        $columnName_arr = $request->get('columns', []);
        $order_arr = $request->get('order', []);
        $search_arr = $request->get('search', []);
        $searchValue = $search_arr['value'] ?? '';

        $columnName = 'thn_aka';
        $columnSortOrder = 'desc';
        $allowedSort = ['thn_aka', 'angkatan', 'urut'];

        if (!empty($order_arr)) {
            $columnIndex = $columnIndex_arr[0]['column'] ?? null;
            if ($columnIndex !== null && !empty($columnName_arr[$columnIndex]['data']) && $columnName_arr[$columnIndex]['data'] !== 'no') {
                $requested = $columnName_arr[$columnIndex]['data'];
                if (in_array($requested, $allowedSort, true)) {
                    $columnName = $requested;
                    $columnSortOrder = $order_arr[0]['dir'] ?? 'desc';
                }
            }
        }

        $totalRecords = mst_thn_aka::count();
        $filteredQuery = mst_thn_aka::query()
            ->when($searchValue !== '', function ($q) use ($searchValue) {
                $q->where(function ($q2) use ($searchValue) {
                    $q2->where('thn_aka', 'like', '%' . $searchValue . '%')
                        ->orWhere('angkatan', 'like', '%' . $searchValue . '%');
                });
            });

        $totalRecordswithFilter = (clone $filteredQuery)->count();

        $records = $filteredQuery
            ->orderBy($columnName, $columnSortOrder)
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecordswithFilter,
            'data' => $records,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(), [
            'thn_aka' => ['required', 'regex:/^\d{4}\/\d{4}(?:\s*-\s*(GANJIL|GENAP))?$/', function ($attribute, $value, $fail) {
                if (strlen($value) > 18) {
                    $fail('Tahun Pelajaran tidak boleh lebih dari 18 karakter');
                }
            }],
        ], ValidationMessage::messages(), ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $exists = mst_thn_aka::where('thn_aka', $request->thn_aka)->first();
        if ($exists) {
            return response()->json(['message' => 'Tahun Pelajaran sudah ada'], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            mst_thn_aka::create([
                'urut' => mst_thn_aka::nextUrut(),
                'thn_aka' => $request->thn_aka,
                'angkatan' => (string) mst_thn_aka::nextAngkatanNumber(),
            ]);
            mst_thn_aka::forgetAngkatanNumberMap();

            DB::connection('DATA_MYSQL')->commit();

            return response()->json(['message' => 'Data ' . $this->mainTitle . ' telah disimpan']);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json(['message' => 'Data ' . $this->mainTitle . ' gagal disimpan', 'error' => $e->getMessage()], 422);
        }
    }
}
