<?php

namespace App\Http\Controllers;

use App\EMP_DESTINO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpDestinoController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $destinos = EMP_DESTINO::all();

        if (!is_null($destinos) && !empty($destinos) && count($destinos) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Destinos encontrados.');
            $this->out['destinos'] = $destinos;
        } else {
            $this->out['message'] = 'No hay destinos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }


    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array, [
                'descripcion' => 'required|unique:EMP_DESTINO,descripcion',
                'continente' => 'required|alpha'
            ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $destino = new EMP_DESTINO();
                $destino->descripcion = $params_array['descripcion'];
                $destino->continente = $params_array['continente'];
                $destino->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['destino'] = $destino;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }


    public function show($id)
    {
        $destino = EMP_DESTINO::find($id);

        if (is_object($destino) && !empty($destino)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['destino'] = $destino;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }


    public function update(Request $request, $id)
    {
        $destino = EMP_DESTINO::find($id);

        if (is_object($destino) && !empty($destino)) {

            $json = $request->input('json');
            $params_array = json_decode($json, true);

            $validacion = Validator::make($params_array, [
                'descripcion' => 'required',
                'continente' => 'required|alpha'
            ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                unset($params_array['id']);
                unset($params_array['created_at']);

                $destino->descripcion = $params_array['descripcion'];
                $destino->continente = $params_array['continente'];
                $destino->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['destino'] = $destino;
            }

        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }


    public function destroy($id)
    {
        $destino = EMP_DESTINO::find($id);

        if (is_object($destino) && !empty($destino)) {
            $destino->delete();
            $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
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
