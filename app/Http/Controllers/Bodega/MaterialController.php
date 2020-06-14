<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\Material;
use App\Models\XassInventario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect', 'getOptions', 'getMateriales']]);
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function index()
    {
        try {
            $materiales = Material::with('getBodega', 'getGrupo')
                ->orderBy('codigo', 'asc')
                ->paginate(7);

            if (!is_null($materiales) && !empty($materiales)) {
                $this->out = $this->respuesta_json('success', 200, 'Datos encontrados.');
                $this->out['dataArray'] = $materiales;
            } else {
                $this->out['message'] = 'No hay datos registrados';
            }

        } catch (\Exception $ex) {
            $this->out['error'] = $ex->getMessage();
        }

        return \response()->json($this->out, $this->out['code']);
    }

    public function getMateriales(Request $request)
    {
        try {
            $grupo = $request->get('grupo');
            $bodega = $request->get('bodega');
            $busqueda = $request->get('params');
            $tamano = $request->get('size') ?? 5;

            $data = Material::selectRaw("id, codigo, descripcion, stock, idbodega, idgrupo");

            if (!empty($bodega) && isset($bodega)) {
                $data = $data->where('idbodega', $bodega);
            }

            if (!empty($grupo) && isset($grupo)) {
                $data = $data->where('idgrupo', $grupo);
            }

            if (!empty($busqueda) && isset($busqueda)) {
                $data = $data->where('descripcion', 'like', "%{$busqueda}%")->orWhere('codigo', 'like', "%{$busqueda}%");
            }

            $data = $data->take($tamano)
                ->where(['estado' => true])->where('stock', '>', 0)
                ->get();

            $this->out['dataArray'] = $data;
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, 200);
    }

    public function store(Request $request)
    {
        $json = $request->input('json');
        $params = json_decode($json);
        $params_array = json_decode($json, true);

        $validacion = Validator::make($params_array, [
            "articles" => 'required|array',
            "articles.codigo" => 'required',
            "articles.grupo" => 'required|array',
            "articles.bodega" => 'required|array',
        ], [
            "articles.codigo.required" => "Es necesario el codigo del articulo.",
            "articles.grupo.required" => "No se ha encontrado datos de grupo.",
            "articles.bodega.required" => "No se ha encontrado datos de bodega ."
        ]);

        $error = array();
        $error_objects = array();

        if ($validacion->fails()) {
            //Guardar el Articulo
            $articles = $params_array['articles'];
            if (count($articles) > 0) {
                foreach ($articles as $article) {
                    $article = (object)$article;

                    $respuesta = $this->storeMaterial($article);

                    if (!is_bool($respuesta)) {
                        $article->stock = "Ya existe en bodega";
                        array_push($error_objects, $article);
                        array_push($error, ['objeto_id' => $article->codigo, 'message' => $respuesta]);
                    }

                }

                $out['code'] = 200;
                $out['message'] = 'Transaccion precesada, materiales ingresados.';
                $out['status_error'] = false;

                if (count($error) > 0) {
                    $out['status_error'] = true;
                    $out['message'] = 'Transaccion precesada, se encontraron algunos errores.';
                    $out['errors'] = $error;
                    $out['error_objects'] = $error_objects;
                }

            } else {
                $out['message'] = "No hay articulos.";
            }
        } else {
            $out['message'] = "Error al procesar la solicitud";
            $out['errors'] = $validacion->errors();
        }

        return response()->json($out, $out['code']);
    }

    public function storeMaterial($new_article)
    {
        try {
            //Guardar articulo
            $article = Material::where([
                'codigo' => $new_article->codigo,
                'idbodega' => $new_article->bodega[0]['id'],
                'idgrupo' => $new_article->grupo[0]['id']
            ])->first();

            if (!$article) {
                $article = new Material();
                $article->codigo = $new_article->codigo;
                $article->idbodega = $new_article->bodega[0]['id'];
                $article->idgrupo = $new_article->grupo[0]['id'];
                $article->descripcion = $new_article->nombre;
                $article->stock = $new_article->stock;
                $article->stockminimo = 10;
                $article->stockmaximo = 0;
                $article->fecha_registro = date('Y-m-d');
                $article->created_at = Carbon::now()->format(config('constants.format_date'));
                $article->updated_at = Carbon::now()->format(config('constants.format_date'));
                return $article->save();
            }

            $article->estado = true;
            $article->update();

            throw new \Exception("Articulo: $article->descripcion, ya existe, status paso a activo.", 500);

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function updateStockMaterial(Request $request)
    {
        try {
            $codigo = $request->input('cod_material');
            $bodega = $request->input('bodega');
            $hacienda = $request->input('hacienda');
            if ($hacienda == 1) {
                $material = XassInventario\Primo\Producto::where(['codigo' => $codigo])->first();
            } else {
                $material = XassInventario\Sofca\Producto::where(['codigo' => $codigo])->first();
            }
            if (!is_null($material) && !empty($material) && is_object($material)) {
                $existe = Material::where(['codigo' => $codigo, 'idbodega' => $bodega, 'estado' => true])->first();
                if (!is_null($existe) && !empty($existe) && is_object($existe)) {
                    if (intval($existe->stock) != intval($material->stock)) {
                        $update_material = Material::where(['codigo' => $codigo, 'idbodega' => $bodega, 'estado' => true])->update([
                            'stock' => $material->stock,
                            'updated_at' => Carbon::now()->format(config('constants.format_date'))
                        ]);

                        if ($update_material) {
                            $this->out = $this->respuesta_json('success', 200, "El Stock de " . trim($material->nombre) . " ha sido actualizado");
                        } else {
                            $this->out['code'] = 500;
                            throw new \Exception("No se pudo actualizar el material: " . trim($material->nombre));
                        }
                    } else {
                        throw new \Exception('No hay cambios en el stock de ' . trim($material->nombre));
                    }
                } else {
                    throw new \Exception('No se puede actualizar el stock...');
                }

            } else {
                throw new \Exception('No se ha encontrado el articulo');
            }
        } catch (\Exception $exception) {
            $this->out['message'] = $exception->getMessage();
        }

        return response()->json($this->out, $this->out['code']);
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
        try {
            $material = Material::where(['id' => $id, 'estado' => true])->first();
            if (!is_null($material) && !empty($material) && is_object($material)) {
                $material->estado = 0;
                if ($material->update()) {
                    $this->out = $this->respuesta_json('success', 200, 'Dato fuera de linea...');
                } else {
                    throw new \Exception("Error, no se pudo dar de baja...");
                }
            } else {
                throw new \Exception("Error, material se encuentra fuera de linea o no existe en los registros, intente nuevamente...");
            }
        } catch (\Exception $ex) {
            $this->out['message'] = $ex->getMessage();
        }

        return \response()->json($this->out, $this->out['code']);
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
