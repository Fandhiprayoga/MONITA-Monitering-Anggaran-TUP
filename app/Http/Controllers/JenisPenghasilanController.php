<?php

namespace App\Http\Controllers;

use App\Models\JenisPenghasilan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPenghasilanController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin();

        return view('main.jenis-penghasilan', [
            'jenisPenghasilans' => JenisPenghasilan::query()->orderBy('kode_jenis')->get(),
        ]);
    }

    public function create()
    {
        $this->ensureAdmin();

        return view('main.jenis-penghasilan-form', [
            'jenisPenghasilan' => new JenisPenghasilan(),
        ]);
    }

    public function edit(JenisPenghasilan $jenisPenghasilan)
    {
        $this->ensureAdmin();

        return view('main.jenis-penghasilan-form', compact('jenisPenghasilan'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request);
        $data['kode_jenis'] = strtoupper(trim($data['kode_jenis']));
        $data['nama_jenis_pph'] = trim($data['nama_jenis_pph']);

        JenisPenghasilan::create($data);

        return redirect()->route('jenis-penghasilan.index')->with('success', 'Jenis penghasilan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisPenghasilan $jenisPenghasilan)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request, $jenisPenghasilan);
        $data['kode_jenis'] = strtoupper(trim($data['kode_jenis']));
        $data['nama_jenis_pph'] = trim($data['nama_jenis_pph']);

        $jenisPenghasilan->update($data);

        return redirect()->route('jenis-penghasilan.index')->with('success', 'Jenis penghasilan berhasil diperbarui.');
    }

    public function destroy(JenisPenghasilan $jenisPenghasilan)
    {
        $this->ensureAdmin();
        $jenisPenghasilan->delete();

        return redirect()->route('jenis-penghasilan.index')->with('success', 'Jenis penghasilan berhasil dihapus.');
    }

    private function validatedData(Request $request, ?JenisPenghasilan $jenisPenghasilan = null): array
    {
        $request->merge([
            'kode_jenis' => strtoupper(trim((string) $request->input('kode_jenis', ''))),
        ]);

        $uniqueCode = Rule::unique('jenis_penghasilan', 'kode_jenis');
        if ($jenisPenghasilan) {
            $uniqueCode->ignore($jenisPenghasilan->id);
        }

        return $request->validate([
            'kode_jenis' => ['required', 'string', 'max:30', $uniqueCode],
            'nama_jenis_pph' => ['required', 'string', 'max:255'],
            'tarif_pph' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'deskripsi' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function ensureAdmin(): void
    {
        abort_unless(session('user_role') === 'admin', 403, 'Akses hanya untuk admin.');
    }
}
