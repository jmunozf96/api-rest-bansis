<?php

namespace App\Models\Bodega;

use App\Models\BaseModel;

class EgresoBodegaDetalleTransfer extends BaseModel
{
    protected $table = "BOD_TRANSFER_SALDO";

    public static function existTransferbyDetalleEgreso($idDetalleEgreso)
    {
        //Funcion para saber si el id de un detalle existente, sea una transferencia
        return self::where([
            'idEgreso' => $idDetalleEgreso,
            'debito' => true,
            'estado' => true
        ])->first();
    }

    public static function existTransfer(EgresoBodegaDetalleTransfer $transferencia)
    {
        return self::where([
            'idEgreso' => $transferencia->idEgreso,
            'idInvEmp' => $transferencia->idInvEmp,
            'debito' => true,
            'estado' => true
        ])->first();
    }
}
