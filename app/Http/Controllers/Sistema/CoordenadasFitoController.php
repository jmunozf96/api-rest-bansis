<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Sistema\CoordenadasFito;
use Illuminate\Http\Request;

class CoordenadasFitoController extends Controller
{

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        $latitud = $request->post('latitud');
        $longitud = $request->post('longitud');

        if (!empty($latitud) && !empty($longitud)) {
            $coordenadas = new CoordenadasFito();
            $coordenadas->latitud = $latitud;
            $coordenadas->longitud = $longitud;
            $coordenadas->estado = true;
            $coordenadas->save();
        }

        return response()->json([
            'datos' => [
                'latitud' => $latitud,
                'longitud' => $longitud
            ]
        ], 200);
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
