<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\mst_kelas;
use App\Models\User;
use App\Models\ValidationMessage;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class ManajemenAdminController extends Controller
{
    private string $title;
    private string $mainTitle;
    private string $dataTitle;
    private string $showTitle;
    private string $datasUrl;
    private string $columnsUrl;

    public function __construct()
    {
        $this->title = "Manajemen Admin";
        $this->mainTitle = "Manajemen Admin";
        $this->showTitle = "Detail Admin";
        $this->datasUrl = route("cashless.admin.manajemen-admin.get-data");
        $this->columnsUrl = route("cashless.admin.manajemen-admin.get-column");
    }

    public function index()
    {
        $data["title"] = $this->title;
        $data["mainTitle"] = $this->mainTitle;
        $data["showTitle"] = $this->showTitle;
        $data["columnsUrl"] = $this->columnsUrl;
        $data["datasUrl"] = $this->datasUrl;
        $loggedInUser = Auth::user();
        $userRoles = $loggedInUser->getRoleNames();
        if ($userRoles[0] == "super-admin") {
            $data["role"] = Role::all();
        } else {
            $data["role"] = Role::whereIn("name", $userRoles)->get();
        }
        return view("cashless.admin.manajemen_admin.index", $data);
    }

    public function getColumn()
    {
        return [
            ["data" => "no", "name" => "no", "className" => "text-center"],
            [
                "data" => "username",
                "name" => "Username",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "nama",
                "name" => "Nama",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "email",
                "name" => "Email",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "kd_kelompok",
                "name" => "KODE Kelompok",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "kelompok",
                "name" => "Kelompok",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "keterangan",
                "name" => "Keterangan",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "edit",
                "name" => "Edit",
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonText" => "Edit",
                "buttonClass" => "btn btn-sm btn-warning",
                "buttonLink" => "#modal-edit",
                "buttonIcon" => "ri-pencil-line me-2",
            ],
            [
                "data" => "edit",
                "name" => "Ganti Password",
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonText" => "Ganti Password",
                "buttonClass" => "btn btn-sm btn-linkedin",
                "buttonLink" => "#modal-edit-password",
                "buttonIcon" => "ri-key-2-line me-2",
            ],
            [
                "data" => "edit",
                "name" => "Reset Password",
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonText" => "Reset Password",
                "buttonClass" => "btn btn-sm btn-facebook",
                "buttonLink" => "#modal-reset-password",
                "buttonIcon" => "ri-key-line me-2",
            ],
            [
                "data" => "delete",
                "name" => "Hapus",
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonText" => "Hapus",
                "buttonClass" => "btn btn-sm btn-danger",
                "buttonLink" => "#modal-delete",
                "buttonIcon" => "ri-delete-bin-5-line me-2",
            ],
        ];
    }

    public function getData(Request $request)
    {
        $draw = (int) $request->get("draw", 1);
        $start = (int) $request->get("start", 0);
        $rowperpage = (int) $request->get("length", 10);

        $columnName = "users.created_at";
        $columnSortOrder = "asc";

        $order = $request->get("order", []);
        if (!empty($order)) {
            $columnIndex = (int) ($order[0]["column"] ?? -1);
            $columns = $request->get("columns", []);

            if ($columnIndex >= 0 && isset($columns[$columnIndex])) {
                $columnData = $columns[$columnIndex]["data"] ?? "";
                if (
                    !in_array($columnData, ["no", "item_id"], true) &&
                    $columnData !== ""
                ) {
                    $columnName = $columnData;
                    $columnSortOrder =
                        strtolower($order[0]["dir"] ?? "asc") === "desc"
                            ? "desc"
                            : "asc";
                }
            }
        }

        $searchValue = $request->input("search.value", "");
        // Total records
        $totalRecords = User::select("count(*) as allcount")->count();
        $totalRecordswithFilter = User::whereAny(
            ["username", "name", "email"],
            "like",
            "%" . $searchValue . "%",
        )
            ->whereNot("id", Auth::user()->id)
            ->select("count(*) as allcount")
            ->count();

        $loggedInUser = Auth::user();
        $userRoles = $loggedInUser->getRoleNames();
        // Fetch records
        $records = User::leftJoin(
            "model_has_roles",
            "users.id",
            "=",
            "model_has_roles.model_id",
        )
            ->leftJoin("roles", "model_has_roles.role_id", "=", "roles.id")
            ->whereAny(
                ["users.username", "users.name", "users.email"],
                "like",
                "%" . $searchValue . "%",
            )
            ->whereNot("users.id", Auth::user()->id)
            ->whereDoesntHave("roles", function ($query) {
                $query->whereIn("name", ["siswa"]);
            })
            ->orderBy($columnName, $columnSortOrder)
            ->whereHas("roles", function ($query) use ($userRoles) {
                if ($userRoles[0] == "admin") {
                    $query->whereIn("name", $userRoles);
                }
            })
            ->select(
                "users.id as id",
                "roles.name as role",
                "users.email",
                "users.username",
                "users.kd_kelompok",
                "users.kelompok",
                "users.keterangan",
                "users.name as nama",
            )
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->map(function ($item, $index) {
                $item->no = $index + 1;
                $item->item_id = Crypt::encrypt($item->id);
                $item->edit = true;
                $item->delete = true;
                unset($item->id);
                return $item;
            })
            ->toArray();

        // dd($records);
        $response = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordswithFilter,
            "data" => $records,
        ];
        return response()->json($response);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "username" => [
                    "string",
                    "max:255",
                    "unique:users",
                    'regex:/^[a-z0-9]+$/i', // Hanya huruf kecil dan angka diizinkan
                    function ($attribute, $value, $fail) {
                        if (preg_match("/\s/", $value)) {
                            $fail(
                                $attribute . " tidak boleh mengandung spasi.",
                            );
                        }
                        if (preg_match("/[^a-z0-9]/i", $value)) {
                            $fail(
                                $attribute .
                                    " hanya boleh mengandung huruf dan angka.",
                            );
                        }
                    },
                ],
                //            'nowa' => ['nullable', 'regex:/^\d+$/'],
                "password" => ["required", "string", "min:6", "confirmed"],
                "role" => ["required"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                "username" => $request->username,
                "name" => $request->nama,
                "email" => $request->email,
                //                'nowa' => $request->nowa,
                "password" => bcrypt($request->password),
            ]);
            $user->syncRoles($request->role);

            DB::commit();
            return response()->json(
                ["message" => "Sukses, data Admin telah disimpan "],
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Gagal, data Admin gagal disimpan",
                    "error" => $e,
                ],
                422,
            );
        }
    }

    public function changePassword($id, Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "old_password" => ["required", "string", "min:6"],
                "password" => ["required", "string", "min:6", "confirmed"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }
        try {
            $decryptedId = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return response()->json(
                ["message" => "Data tidak ditemukan!"],
                422,
            );
        }

        $record = User::where("id", $decryptedId)->first();
        if (!$record) {
            return response()->json(
                ["message" => "{$this->mainTitle} tidak ditemukan!"],
                422,
            );
        }

        if (!Hash::check($request->old_password, $record->password)) {
            return response()->json(
                ["message" => "Password lama tidak sesuai!"],
                422,
            );
        }

        try {
            DB::beginTransaction();
            $record->update([
                "password" => bcrypt($request->password),
            ]);

            DB::commit();
            return response()->json(
                ["message" => "Sukses, password diubah "],
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                ["message" => "Gagal, password diubah", "error" => $e],
                422,
            );
        }
    }

    public function update($id, Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "username" => [
                    "string",
                    "max:255",
                    'regex:/^[a-z0-9]+$/i',
                    function ($attribute, $value, $fail) {
                        if (preg_match("/\s/", $value)) {
                            $fail(
                                $attribute . " tidak boleh mengandung spasi.",
                            );
                        }
                        if (preg_match("/[^a-z0-9]/i", $value)) {
                            $fail(
                                $attribute .
                                    " hanya boleh mengandung huruf dan angka.",
                            );
                        }
                    },
                ],
                //            'tanda_tangan' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }
        try {
            $decryptedId = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return response()->json(
                ["message" => "Data tidak ditemukan!"],
                422,
            );
        }

        $record = User::where("id", $decryptedId)->first();
        if (!$record) {
            return response()->json(
                ["message" => "{$this->mainTitle} tidak ditemukan!"],
                422,
            );
        }

        $recordWithUsername = User::where("username", $request->username)
            ->whereNot("id", $decryptedId)
            ->first();

        if ($recordWithUsername) {
            return response()->json(
                ["message" => "Username '$request->username' sudah digunakan!"],
                422,
            );
        }

        if ($request->usename !== $record->username){
            $exist = DB::table('scctcashout_backup_cutoff')->where('teller', '=', $request->username)->first();
            if ($exist) {
                return response()->json(
                    ["message" => "User $record->username telah melakukan transaksi dan tidak dapat diubah!"],
                    422,
                );
            }
        }

        try {
            DB::beginTransaction();

            $record->update([
                "username" => $request->username,
                "name" => $request->nama,
                "email" => $request->email,
                //                'nowa' => $request->nowa,
            ]);

            DB::commit();
            return response()->json(
                ["message" => "Sukses, data Admin telah disimpan "],
                200,
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Gagal, data Admin gagal disimpan",
                    "error" => $e->getMessage(),
                ],
                422,
            );
        }
    }

    public function resetPassword($id, Request $request)
    {
        try {
            $decryptedId = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return response()->json(
                ["message" => "Data tidak ditemukan!"],
                422,
            );
        }

        $record = User::where("id", $decryptedId)->first();
        if (!$record) {
            return response()->json(
                ["message" => "{$this->showTitle} tidak ditemukan!"],
                422,
            );
        }

        try {
            DB::beginTransaction();
            $default_password = config("app.default_password");
            if (!$default_password) {
                throw new Exception("Gagal, Password Admin gagal direset!");
            }
            $record->update([
                "password" => $request->username,
            ]);

            DB::commit();
            return response()->json(
                ["message" => "Sukses, password Admin telah direset!"],
                200,
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Gagal, password Admin gagal direset!",
                    "error" => $e->getMessage(),
                ],
                422,
            );
        }
    }
}

