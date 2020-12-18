<?php


namespace App\Helpers;

use TCPDF;

class InformePDF
{
    protected $pdf;

    public function __construct($titulo)
    {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT,
            PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->pdf->SetTitle($titulo);
        $this->pdf->SetMargins(5, PDF_MARGIN_TOP, 5);
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $this->informacion();
        $this->pie();
    }

    public function cabecera($titulo, $detalle)
    {
        //PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH
        $this->pdf->setHeaderData('', 0,
            $titulo, $detalle);
        $this->pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', 9));
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    }

    public function informacion(){
        $this->pdf->SetAuthor(config('app.name'));
    }

    public function pie(){
        $this->pdf->setFooterData();
        $this->pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', 9));
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    }

    public function build(){
        return $this->pdf;
    }

    public function generar($nombre)
    {
        $this->pdf->Output($nombre, "I");
    }
}
