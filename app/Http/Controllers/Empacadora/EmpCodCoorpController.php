<?php

namespace App\Http\Controllers\Empacadora;

use App\Http\Controllers\Controller;
use App\Models\Empacadora\EMP_COD_COORP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpCodCoorpController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $codigos = EMP_COD_COORP::all()->load(['caja']);

        if (!is_null($codigos) && !empty($codigos) && count($codigos) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['codigos'] = $codigos;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array,
                [
                    'descripcion' => "required|unique:EMP_COD_COORP,descripcion,NULL,NULL,id_caja,$params->id_caja",
                    'id_caja' => ['required', 'exists:EMP_CAJAS,id'],
                ],
                [
                    'descripcion.unique' => 'El codigo con el mismo tipo de caja ya se encuentra registrado.',
                    'id_caja.exists' => 'El parametro de caja no existe.'
                ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $codigo = new EMP_COD_COORP();
                $codigo->descripcion = $params_array['descripcion'];
                $codigo->id_caja = $params_array['id_caja'];
                $codigo->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['codigo'] = $codigo;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function show($id)
    {
        $codigo = EMP_COD_COORP::find($id)->load(['caja']);

        if (is_object($codigo) && !empty($codigo)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['codigo'] = $codigo;
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $codigo = EMP_COD_COORP::find($id);

        if (is_object($codigo) && !empty($codigo)) {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'descripcion' => "required|unique:EMP_COD_COORP,descripcion,NULL,NULL,id_caja,$params->id_caja",
                        'id_caja' => ['required', 'exists:EMP_CAJAS,id'],
                    ],
                    [
                        'descripcion.unique' => 'El codigo con el mismo tipo de caja ya se encuentra registrado o no se han detectado cambios en el registro actual.',
                        'id_caja.exists' => 'El parametro de caja no existe.'
                    ]);

                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    unset($params_array['id']);
                    unset($params_array['created_at']);

                    $codigo->descripcion = $params_array['descripcion'];
                    $codigo->id_caja = $params_array['id_caja'];
                    $codigo->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                    $this->out['codigo'] = $codigo;
                }

            } else {
                $this->out['message'] = "No se han recibido parametros";
            }
        } else {
            $this->out['message'] = "No existen datos con el parametro enviado.";
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        $codigo = EMP_COD_COORP::find($id);

        if (is_object($codigo) && !empty($codigo)) {
            $codigo->delete();
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
