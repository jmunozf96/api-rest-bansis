<?php

namespace App\Http\Controllers\Bodega;

use App\Http\Controllers\Controller;
use App\Models\Bodega\Material;
use App\Models\XassInventario\Primo\Producto;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Expr\Cast\Object_;

class MaterialController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->middleware('api.auth', ['except' => ['index', 'show', 'customSelect', 'getOptions']]);
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
                $article->created_at = Carbon::now()->format("d-m-Y H:i:s");
                $article->updated_at = Carbon::now()->format("d-m-Y H:i:s");
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
            $material = Producto::where('codigo', $codigo)->first();
            if (!is_null($material) && !empty($material) && is_object($material)) {
                $existe = Material::where(['codigo' => $codigo, 'estado' => true])->first();
                if (!is_null($existe) && !empty($existe) && is_object($existe)) {
                    if (intval($existe->stock) != intval($material->stock)) {
                        $update_material = Material::where('codigo', $codigo)->update([
                            'stock' => $material->stock,
                            'updated_at' => Carbon::now()->format("d-m-Y H:i:s")
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
