@extends('Informes.InformeBase')

@section('title', 'Informe Semanal Enfunde')

@section('cuerpo-inform')
    <table width="100%">
        <tr>
            <td valign="top"><img src="{{asset('images/banano-logo.png')}}" alt="" width="80"/></td>
            <td align="right">
                <h3>{{$hacienda->detalle}}</h3>
                <pre>
                    Enfunde Semanal
                    Listado de loteros - lotes
                </pre>
            </td>
        </tr>
    </table>

    <table width="100%">
        <tr>
            <td><strong>Periodo:</strong> Linblum - Barrio teatral</td>
            <td><strong>Semana:</strong> Linblum - Barrio Comercial</td>
        </tr>

    </table>

    <table width="100%">
        <thead style="background-color: lightgray;">
        <tr>
            <th align="center" width="10">&nbsp;&nbsp;#</th>
            <th align="center" width="30">&nbsp;&nbsp;Lote</th>
            <th align="center" width="20">&nbsp;&nbsp;Cant. Presente</th>
            <th align="center" width="20">&nbsp;&nbsp;Cant. Futuro</th>
            <th align="center" width="20">&nbsp;&nbsp;Total</th>
        </tr>
        </thead>
    </table>

    @if(count($detalle) > 0)
        @foreach($detalle as $key => $value)
            @php
                $total_presente = 0;
                $total_futuro = 0;
            @endphp
            <table width="100%">
                <thead style="background-color: lightgray;">
                <tr>
                    <th colspan="5" align="left">&nbsp;&nbsp;{{$value->nombres}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($value->lotes as $key => $lote)
                    <tr>
                        <th width="10">{{$key + 1}}</th>
                        <td align="center" width="30">{{$lote->alias}}</td>
                        <td align="right" width="20">{{$lote->cant_pre}}</td>
                        <td align="right" width="20">{{$lote->cant_fut}}</td>
                        <td align="right" width="20">{{$lote->cant_pre + $lote->cant_fut}}</td>
                    </tr>
                    {!! $total_presente += $lote->cant_pre !!}
                    {!! $total_futuro += $lote->cant_fut !!}
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td align="right" width="20" class="gray-primary">{{$total_presente}}</td>
                    <td align="right" width="20" class="gray-primary">{{$total_futuro}}</td>
                    <td align="right" width="20" class="gray-primary">{{$total_presente + $total_futuro}}</td>
                </tr>
                </tfoot>
            </table>
        @endforeach
    @endif
@endsection
