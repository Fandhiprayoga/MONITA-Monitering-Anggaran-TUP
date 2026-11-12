<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin();

        return view('main.bank', [
            'banks' => Bank::query()->orderBy('kode_bank')->get(),
            'editingBank' => $request->filled('edit')
                ? Bank::findOrFail($request->integer('edit'))
                : new Bank(['status' => 'aktif']),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request);
        $data['kode_bank'] = strtoupper(trim($data['kode_bank']));
        $data['nama_bank'] = trim($data['nama_bank']);

        Bank::create($data);

        return redirect()->route('bank.index')->with('success', 'Bank berhasil ditambahkan.');
    }

    public function update(Request $request, Bank $bank)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request, $bank);
        $data['kode_bank'] = strtoupper(trim($data['kode_bank']));
        $data['nama_bank'] = trim($data['nama_bank']);

        $bank->update($data);

        return redirect()->route('bank.index')->with('success', 'Bank berhasil diperbarui.');
    }

    private function validatedData(Request $request, ?Bank $bank = null): array
    {
        $request->merge([
            'kode_bank' => strtoupper(trim((string) $request->input('kode_bank', ''))),
        ]);

        $uniqueCode = Rule::unique('banks', 'kode_bank');
        if ($bank) {
            $uniqueCode->ignore($bank->id);
        }

        return $request->validate([
            'kode_bank' => ['required', 'string', 'max:30', $uniqueCode],
            'nama_bank' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);
    }

    private function ensureAdmin(): void
    {
        abort_unless(session('user_role') === 'admin', 403, 'Akses hanya untuk admin.');
    }
}
