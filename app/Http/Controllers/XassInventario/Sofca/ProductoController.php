<?php

namespace App\Http\Controllers\XassInventario\Sofca;

use App\Http\Controllers\Controller;
use App\Models\XassInventario\Sofca\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as Collection;

class ProductoController extends Controller
{
    protected $out;

    public function __construct()
    {
        $this->out = $this->respuesta_json('error', 400, 'Detalle mensaje de respuesta');
    }

    public function getProductos(Request $request)
    {
        try {
            $bodega = $request->get('cellar');
            $grupo = $request->get('group');
            $busqueda = $request->get('search');
            $tamano = $request->get('size') ?? 5;

            $data = Producto::selectRaw("id_fila, RTRIM(codigo) as codigo, nombre, RTRIM(nombre + ' - STOCK: '+ convert(varchar,CAST(stock AS DECIMAL(10,2)))) as descripcion, unidad, grupo, bodegacompra, convert(varchar,CAST(stock AS DECIMAL(10,2))) as stock");


            if (!empty($busqueda) && isset($busqueda)) {
                $data = $data->where('nombre', 'like', "%{$busqueda}%");
            }

            if (!empty($grupo) && isset($grupo)) {
                $data = $data->where('grupo', $grupo);
            }

            if (!empty($bodega) && isset($bodega)) {
                $data = $data->where('bodegacompra', $bodega);
            }

            $data = $data->take($tamano)
                ->where('stock', '>', 0)
                ->get();

            $this->out['dataArray'] = $data;
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
