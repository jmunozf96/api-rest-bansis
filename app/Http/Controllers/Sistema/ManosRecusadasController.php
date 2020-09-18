<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Sistema\ManosRecusadas;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ManosRecusadasController extends Controller
{

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        //Recoger variables enviadas por post
        $hacienda = $request->post('hacienda');
        $fecha = $request->post('fecha');
        $idlote = $request->post('idlote');
        $dano_des = $request->post('dano_des');
        $cantidad = $request->post('cantidad');
        $otros = $request->post('otros');
        $otros_des = $request->post('otros_des');
        $mano = $request->post('mano');

        if ($this->validateData($hacienda) && $this->validateData($fecha) && $this->validateData($idlote)
            && $this->validateData($dano_des)) {
            //Guardamos los datos
            $manos_recusadas = new ManosRecusadas();
            $manos_recusadas->idhacienda = $hacienda;
            $manos_recusadas->fecha = $fecha;
            $manos_recusadas->idlote = $idlote;
            $manos_recusadas->dano_des = $dano_des;
            $manos_recusadas->cantidad = $cantidad;
            $manos_recusadas->otros = $otros;
            $manos_recusadas->otros_des = $otros_des;
            $manos_recusadas->mano = $mano;
            $manos_recusadas->created_at = Carbon::now()->format(config('constants.format_date'));
            $manos_recusadas->updated_at = Carbon::now()->format(config('constants.format_date'));

            $manos_recusadas->save();
        }

        return response()->json([
            'resp' => "Registros guardados con exito"
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

    private function validateData($data)
    {
        return !empty($data) && !is_null($data);
    }
}
