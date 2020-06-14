<?php

namespace App\Http\Controllers;

use App\Recurso;
use Illuminate\Http\Request;

class RecursosController extends Controller
{
    protected $respuesta;

    public function __construct()
    {
        $this->respuesta = $this->response_array('error', 400, 'Describa mensaje');
    }

    public function index()
    {
        $recursos = Recurso::where(['padreId' => null])
            ->with(['recursoHijo' => function ($query) {
                $query->where('estado', true);
                $query->with(['recursoHijo' => function ($query) {
                    $query->where('estado', true);
                }]);
            }])
            ->where('estado', true)
            ->get();

        if (!is_null($recursos) && !empty($recursos) && count($recursos) > 0) {
            $this->respuesta = $this->response_array('success', 200, 'Datos encontrados.');
            $this->respuesta['dataArray'] = $recursos;
        } else {
            $this->respuesta['message'] = 'No hay datos registrados';
        }

        return response()->json($this->respuesta, $this->respuesta['code']);
    }

    protected function response_array(...$data)
    {
        return [
            'status' => $data[0],
            'code' => $data[1],
            'message' => $data[2]
        ];
    }
}
