<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Sistema\Dano;
use Illuminate\Http\Request;

class DanoController extends Controller
{

    public function index()
    {
        $danos = Dano::select('id', 'nombre', 'detalle', 'categoria', 'estado')->get();
        return response()->json([
            'danos' => $danos
        ], 200);
    }


    public function store(Request $request)
    {
        //
    }


    public function show($id)
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
