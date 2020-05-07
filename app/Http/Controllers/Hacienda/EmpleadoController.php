<?php

namespace App\Http\Controllers\Hacienda;

use App\Http\Controllers\Controller;
use App\Models\Hacienda\Empleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpleadoController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'getEmpleados', 'getEmpleadosInventario']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        $empleados = Empleado::with('hacienda', 'labor')->orderBy('updated_at', 'DESC')->paginate(7);

        if (!is_null($empleados) && !empty($empleados) && count($empleados) > 0) {
            $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
            $this->out['dataArray'] = $empleados;
        } else {
            $this->out['message'] = 'No hay datos registrados';
        }

        return response()->json($this->out, $this->out['code']);
    }

    public function getEmpleados(Request $request)
    {
        try {
            $hacienda = $request->get('hacienda');
            $labor = $request->get('labor');
            $busqueda = $request->get('params');
            $tamano = $request->get('size') ?? 5;

            $data = Empleado::selectRaw("id, cedula, nombre1, nombre2, apellido1, apellido2, (nombres + ' CI: ' + cedula) as descripcion, nombres");


            if (!empty($busqueda) && isset($busqueda)) {
                $data = $data->where('nombres', 'like', "%{$busqueda}%")->orWhere('cedula', 'like', "%{$busqueda}%");
            }

            if (!empty($hacienda) && isset($hacienda)) {
                $data = $data->where('idhacienda', $hacienda);
            }

            if (!empty($labor) && isset($labor)) {
                $data = $data->where('idlabor', $labor);
            }

            $data = $data->take($tamano)
                ->where('estado', true)
                ->get();

            $this->out['dataArray'] = $data;
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, 200);
    }

    public function getEmpleadosInventario($hacienda, $empleado)
    {
        $empleados = Empleado::select('id', 'cedula', 'idhacienda', 'nombres as descripcion')
            ->where([
                'idhacienda' => $hacienda,
                'estado' => 1
            ])
            ->whereNotIn('id', [$empleado])
            ->has('inventario')
            ->with(['inventario' => function ($query) use ($hacienda) {
                $query->select('id', 'idempleado', 'idmaterial', 'tot_egreso');
                $query->where(['estado' => 1]);
                $query->with(['material' => function ($query) use ($hacienda) {
                    $query->select('id', 'codigo', 'stock', 'descripcion');
                }]);
            }])
            ->get();
        return response()->json($empleados, 200);
    }

    public function store(Request $request)
    {
        $json = $request->input('json', null);
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        if (!empty($params_array) && count($params_array) > 0) {
            $validacion = Validator::make($params_array,
                [
                    'cedula' =>
                        "required|min:1|max:10|unique:HAC_EMPLEADOS,cedula,NULL,NULL",
                    'idhacienda' => 'required',
                    'nombre1' => 'required',
                    'nombre2' => 'required',
                    'apellido1' => 'required',
                    'apellido2' => 'required',
                ],
                [
                    'cedula.unique' => 'El empleado con cedula ' . $params_array['cedula'] . ' ya se encuentra registrado...'
                ]);

            if ($validacion->fails()) {
                $this->out['message'] = "Los datos enviados no son correctos";
                $this->out['error'] = $validacion->errors();
            } else {
                $empleado = new Empleado();
                $empleado->cedula = $params_array['cedula'];
                $empleado->idhacienda = $params_array['idhacienda'];
                $empleado->nombre1 = strtoupper($params_array['nombre1']);
                $empleado->nombre2 = strtoupper($params_array['nombre2']);
                $empleado->apellido1 = strtoupper($params_array['apellido1']);
                $empleado->apellido2 = strtoupper($params_array['apellido2']);
                $empleado->nombres = strtoupper($params_array['apellido1'] . ' ' . $params_array['apellido2'] . ' ' . $params_array['nombre1'] . ' ' . $params_array['nombre2']);
                $empleado->idlabor = $params_array['idlabor'];
                $empleado->created_at = Carbon::now()->format("d-m-Y H:i:s");
                $empleado->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                $empleado->save();

                $this->out = $this->respuesta_json('success', 200, 'Datos guardados correctamente');
                $this->out['empleado'] = $empleado;
            }

        } else {
            $this->out['message'] = "No se han recibido parametros";
        }

        return response()->json($this->out, $this->out['code']);
    }


    public function show($id)
    {
        $empleado = Empleado::find($id);

        if (is_object($empleado) && !empty($empleado)) {
            $this->out = $this->respuesta_json('success', 200, 'Dato encontrado.');
            $this->out['empleado'] = $empleado->load('hacienda');
        } else {
            $this->out['message'] = 'No existen datos con el parametro enviado.';
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function update(Request $request, $id)
    {
        $empleado = Empleado::find($id);

        if (is_object($empleado) && !empty($empleado)) {
            $json = $request->input('json', null);
            $params = json_decode($json);
            $params_array = json_decode($json, true);

            if (!empty($params_array) && count($params_array) > 0) {
                $validacion = Validator::make($params_array,
                    [
                        'cedula' =>
                            "required|min:1|max:10",
                        'idhacienda' => 'required',
                        'nombre1' => 'required',
                        'nombre2' => 'required',
                        'apellido1' => 'required',
                        'apellido2' => 'required',
                    ],
                    [
                        'cedula.unique' => 'El empleado con cedula ' . $params_array['cedula'] . ' ya se encuentra registrado...'
                    ]);


                if ($validacion->fails()) {
                    $this->out['message'] = "Los datos enviados no son correctos";
                    $this->out['error'] = $validacion->errors();
                } else {
                    unset($params_array['id']);
                    unset($params_array['created_at']);

                    $empleado->cedula = $params_array['cedula'];
                    $empleado->idhacienda = $params_array['idhacienda'];
                    $empleado->nombre1 = strtoupper($params_array['nombre1']);
                    $empleado->nombre2 = strtoupper($params_array['nombre2']);
                    $empleado->apellido1 = strtoupper($params_array['apellido1']);
                    $empleado->apellido2 = strtoupper($params_array['apellido2']);
                    $empleado->nombres = strtoupper($params_array['apellido1'] . ' ' . $params_array['apellido2'] . ' ' . $params_array['nombre1'] . ' ' . $params_array['nombre2']);
                    $empleado->idlabor = $params_array['idlabor'];
                    $empleado->updated_at = Carbon::now()->format("d-m-Y H:i:s");
                    $empleado->save();

                    $this->out = $this->respuesta_json('success', 200, 'Datos actualizados correctamente');
                    $this->out['empleado'] = $empleado;
                }

            } else {
                $this->out['message'] = "No se han recibido parametros.";
            }
        } else {
            $this->out['message'] = "No existen datos con el parametro enviado.";
        }
        return response()->json($this->out, $this->out['code']);
    }

    public function destroy($id)
    {
        try {
            $empleado = Empleado::find($id);

            if (is_object($empleado) && !empty($empleado)) {
                $empleado->delete();
                $this->out = $this->respuesta_json('success', 200, 'Dato eliminado correctamente.');
            } else {
                $this->out['message'] = 'No existen datos con el parametro enviado.';
            }
            return response()->json($this->out, $this->out['code']);
        } catch (\Exception $e) {
            $this->out['code'] = 500;
            $this->out['message'] = 'No se puede eliminar el registro, conflicto en la base de datos, por favor contactar con el administrador del sistema.';
            $this->out['error_message'] = $e->getMessage();
        }
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
