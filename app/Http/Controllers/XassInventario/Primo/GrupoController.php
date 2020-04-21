<?php

namespace App\Http\Controllers\XassInventario\Primo;

use App\Http\Controllers\Controller;
use App\Models\XassInventario\Primo\Grupo;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function getGruposPadre()
    {
        try {
            $padres = Grupo::selectRaw('distinct Padre')->get();
            $gruposPadre = Grupo::select('Id_Fila', 'Codigo', 'Padre', 'Nombre as descripcion')
                ->whereIn('Codigo', $padres)->orderBy('Nombre', 'asc')->get();
            $this->out['dataArray'] = $gruposPadre;
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, 200);
    }

    public function getGruposHijos($idpadre)
    {
        try {
            $grupos = Grupo::select('Id_Fila', 'Codigo', 'Padre', 'Nombre as descripcion')->where('Padre', $idpadre)->get();
            $this->out['dataArray'] = $grupos;
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, 200);
    }

    public function respuesta_json(...$datos)
    {
        return array(
            'status' => $datos[0],
            'code' => $datos[1],
            'message' => $datos[2]
        );
    }
}
