<?php


namespace App\Models\Sisban;


use Illuminate\Support\Facades\DB;

class HelperCosecha
{
    public static function tabla_temporal_data_cintas()
    {
        DB::connection('SISBAN')->unprepared(DB::raw("
                    create table cosecha_cintas
                    (
                        [cs_id] [numeric](5, 0) identity not null PRIMARY KEY,
                        [cs_haciend] [numeric](2, 0) NOT NULL,
                        [cs_fecha] [date] NOT NULL,
                        [cs_convoy] [numeric](4, 0) NOT NULL,
                        [cs_n] [numeric](4, 0) NOT NULL,
                        [cs_avance] [numeric](4, 0) NOT NULL,
                        [cs_hora] [numeric](4, 0) NOT NULL,
                        [cs_tipo] [char](1) NOT NULL,
                        [cs_seccion] [char](3) NOT NULL,
                        [cs_garru] [numeric](3, 0) NOT NULL,
                        [cs_color] [numeric](5, 0) NOT NULL,
                        [cs_peso] [numeric](6, 2) NOT NULL,
                        [cs_dano] [numeric](2, 0) NOT NULL,
                        [cs_nivdano] [numeric](1, 0) NOT NULL,
                        [fechacre] [datetime] NULL,
                        [equipocre] [varchar](200) NULL
                    )
                "));
    }

    public static function tabla_temporal_data_cintas_drop()
    {
        DB::connection('SISBAN')->unprepared(DB::raw("DROP TABLE IF EXISTS cosecha_cintas"));
    }
}
