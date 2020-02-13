<?php

namespace App\Http\Controllers\Empacadora;

use App\Http\Controllers\Controller;
use App\Models\Empacadora\EMP_COD_COORP;
use Illuminate\Http\Request;

class EmpCodCoorpController extends Controller
{

    public function index()
    {
        $codigos = EMP_COD_COORP::all()->load(['caja']);
        return response()->json($codigos, 200);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
