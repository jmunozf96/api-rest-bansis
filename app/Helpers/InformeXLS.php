<?php


namespace App\Helpers;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InformeXLS implements FromCollection, WithHeadings,
    WithStyles, WithColumnWidths, WithProperties, WithTitle
{
    protected $collection;
    protected $cabeceras;
    protected $titulo;

    public function __construct($collection, $cabeceras, $titulo)
    {
        $this->collection = $collection;
        $this->cabeceras = $cabeceras;
        $this->titulo = $titulo;
        $this->collection();
    }

    public function collection()
    {
        return new Collection($this->collection);
    }

    public function headings(): array
    {
        return $this->cabeceras;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 13,
            'C' => 50,
            'F' => 40,
            'E' => 13,
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Ing. Jesus Munoz F.',
            'lastModifiedBy' => 'Ing. Jesus Munoz F.',
            'title' => 'Informes Excel',
            'description' => 'Informe Excel',
            'company' => 'Valores y Administracion',
        ];
    }


    public function title(): string
    {
        return $this->titulo;
    }
}

;
