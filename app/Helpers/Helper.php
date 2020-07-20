<?php

namespace App\Helpers;

use App\Perfil;
use Illuminate\Support\Facades\DB;

class Helper
{
    public function eliminar_acentos($cadena)
    {

        //Reemplazamos la A y a
        $cadena = str_replace(
            array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
            array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'),
            $cadena
        );

        //Reemplazamos la E y e
        $cadena = str_replace(
            array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
            array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'),
            $cadena);

        //Reemplazamos la I y i
        $cadena = str_replace(
            array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
            array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
            $cadena);

        //Reemplazamos la O y o
        $cadena = str_replace(
            array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
            array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
            $cadena);

        //Reemplazamos la U y u
        $cadena = str_replace(
            array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
            array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
            $cadena);

        //Reemplazamos la N, n, C y c
        $cadena = str_replace(
            array('Ñ', 'ñ', 'Ç', 'ç'),
            array('N', 'n', 'C', 'c'),
            $cadena
        );

        return $cadena;
    }

    public function extraerRepetidosArray($datos)
    {

    }

    public function getRecursosUser($id)
    {
        $recursos_Padres = Perfil::select('recurso.id')
            ->where(['iduser' => $id, 'recurso.padreId' => null])
            ->join('SIS_RECURSOS as recurso', 'recurso.id', 'SIS_PERFIL_USUARIOS.idrecurso')
            ->get()->pluck('id');

        $recursos = Perfil::select('iduser', 'idrecurso', 'recurso.nombre')
            ->where(['iduser' => $id, 'recurso.padreId' => null])
            ->orderBy('recurso.nombre')
            ->join('SIS_RECURSOS as recurso', 'recurso.id', 'SIS_PERFIL_USUARIOS.idrecurso')
            ->with(['recurso' => function ($query) use ($id, $recursos_Padres) {
                $query->select('id', 'nombre', 'tipo', 'padreId', 'ruta');
                $query->where(['estado' => true]);
                $query->whereIn('id', $recursos_Padres);

                $query->with(['recursoHijo' => function ($query) use ($id) {
                    $query->select('id', 'nombre', 'tipo', 'padreId', 'ruta');
                    $query->where(['estado' => true]);

                    $recursos = Perfil::select('iduser', 'idrecurso')->where(['iduser' => $id])->pluck('idrecurso');
                    $query->whereIn('id', $recursos);

                    $query->with(['recursoHijo' => function ($query) use ($id) {
                        $query->select('id', 'nombre', 'tipo', 'padreId', 'ruta');
                        $query->where(['estado' => true]);

                        $recursos = Perfil::select('iduser', 'idrecurso')->where(['iduser' => $id])->pluck('idrecurso');
                        $query->whereIn('id', $recursos);
                    }]);
                }]);

            }])
            /*            ->whereHas('recurso', function ($query) {
                            $query->where([
                                'padreId' => null,
                                'estado' => true
                            ]);
                        })*/
            ->get();

        if (count($recursos) > 0) {
            return $recursos;
        }

        return [];
    }

    public function getColorHexadecimal($idcalendar)
    {
        $color = '';
        switch ($idcalendar) {
            case 'lila':
                $color = '#5D0577';
                break;
            case 'amarillo':
                $color = '#F0F043';
                break;
            case 'blanco':
                $color = '#ffffff';
                break;
            case 'azul':
                $color = '#0242A2';
                break;
            case 'verde':
                $color = '#20A84D';
                break;
            case 'negro':
                $color = '#000';
                break;
            case 'cafe':
                $color = '#725934';
                break;
            case 'rojo':
                $color = '#D01C00';
                break;
            case 'naranja':
                $color = '#F5A300';
                break;
        }
        return $color;
    }
}
