<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ValidationMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class ProfileAdminController extends Controller
{
    private string $title;
    private string $mainTitle;
    private string $dataTitle;
    private string $showTitle;

    public function __construct()
    {
        $this->title = 'Profil Admin';
        $this->mainTitle = 'Profil Admin';
        $this->showTitle = 'Detail Admin';
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['showTitle'] = $this->showTitle;
        $data['admin_id'] = session('cashless_user.id');
        $data['username'] = session('cashless_user.username');
        $data['nama_kantin'] = session('cashless_user.kantin');
        return view('cashless.admin.profile_admin.index', $data);
    }

    public function changePassword($id, Request $request)
    {
        Log::info('changePassword - Started', [
            'id' => $id,
            'request_all' => $request->all(),
            'session_user' => session('cashless_user.username')
        ]);

        $validator = Validator::make($request->all(), [
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ], ValidationMessage::messages(),
            ValidationMessage::attributes());

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            Log::warning('changePassword - Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422
            );
        }

        // Cek record di sm_kantin berdasarkan urut
        Log::info('changePassword - Looking for record', ['urut' => $id]);
        
        $record = DB::connection('DATA_MYSQL')->table('sm_kantin')
            ->where('urut', $id)->first();
        
        Log::info('changePassword - Record found', [
            'found' => !is_null($record),
            'record_username' => $record->username ?? null,
            'record_password_hash' => $record->password ?? null,
            'old_password_input' => $request->old_password
        ]);

        if (!$record) {
            Log::error('changePassword - Record not found', ['urut' => $id]);
            return response()->json(
                ["message" => "{$this->mainTitle} tidak ditemukan!"], 422
            );
        }

        // Hash MD5 dari password lama yang diinput
        $hashedOldPassword = md5($request->old_password);
        Log::info('changePassword - Password comparison', [
            'input_md5' => $hashedOldPassword,
            'db_password' => $record->password,
            'match' => ($hashedOldPassword === $record->password)
        ]);

        if (!hash_equals(md5($request->old_password), $record->password)) {
            Log::warning('changePassword - Old password mismatch');
            return response()->json(
                ["message" => "Password lama tidak sesuai!"], 422
            );
        }

        try {
            Log::info('changePassword - Calling WebChangePassMerchant', [
                'username' => session('cashless_user.username'),
                'old_password' => $request->old_password,
                'new_password' => $request->password
            ]);

            DB::beginTransaction();
            
            $result = DB::connection('DATA_MYSQL')
                ->select('CALL WebChangePassMerchant(?,?,?)',
                    [
                        session('cashless_user.username'),
                        $request->old_password,
                        $request->password
                    ]);
            
            Log::info('changePassword - Stored procedure result', ['result' => $result]);
            
            DB::commit();
            
            Log::info('changePassword - Success');
            return response()->json(['message' => 'Sukses, password diubah'], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('changePassword - Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal, password diubah', 
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => [
                'string',
                'max:255',
                'regex:/^[a-z0-9]+$/i',
                function ($attribute, $value, $fail) {
                    if (preg_match('/\s/', $value)) {
                        $fail($attribute . ' tidak boleh mengandung spasi.');
                    }
                    if (preg_match('/[^a-z0-9]/i', $value)) {
                        $fail($attribute . ' hanya boleh mengandung huruf dan angka.');
                    }
                },
            ],
        ], ValidationMessage::messages(),
            ValidationMessage::attributes());

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
                422
            );
        }
        
        try {
            $decryptedId = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return response()->json(
                ["message" => "Data tidak ditemukan!"],
                422
            );
        }

        $record = User::where('id', $decryptedId)->first();
        if (!$record) {
            return response()->json(
                ["message" => "{$this->mainTitle} tidak ditemukan!"],
                422
            );
        }

        $recordWithUsername = User::where('username', $request->username)
            ->whereNot('id', $decryptedId)->first();

        if ($recordWithUsername) {
            return response()->json(
                ["message" => "Username sudah digunakan!"], 422
            );
        }

        try {
            DB::beginTransaction();
            DB::commit();
            return response()->json(['message' => 'Sukses, data Admin telah disimpan '], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal, data Admin gagal disimpan', 'error' => $e], 422);
        }
    }
}
