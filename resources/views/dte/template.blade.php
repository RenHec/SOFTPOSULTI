<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $data['Certificacion']['NumeroAutorizacion']['Llave'] }}</title>
</head>

<body>
    <div style="margin: 0px; width: 100%; height: 100%;">
        <div style="width: 100%;">
            <p style="text-align: center; align-content: center; vertical-align: middle;">
                <!-- Header text -->
                <span style="font-size: 18px; font-weight: 700; text-transform: uppercase;">
                    {{ $data['Emisor']['NombreComercial'] }}
                </span>
                <br />

                <!-- business information here -->
                <span style="font-size: 14px; text-transform: uppercase;">
                    <strong>NIT: {{ $data['Emisor']['NITEmisor'] }}</strong>
                </span>
                <br />
                <span style="font-size: 12px; text-transform: uppercase;">
                    {{ $data['Emisor']['NombreEmisor'] }}
                </span>
                <br />
                <span style="font-size: 12px; text-transform: uppercase;">
                    {{ $data['Emisor']['DireccionEmisor']['Completa'] }}
                </span>
                <br />
            </p>
        </div>
        <div style="border-top: 1px solid #242424; clear: both; margin-bottom: 0px;">
            <p style="font-size: 14px; text-align: center; align-content: center; vertical-align: middle;">
                <strong>FACTURA</strong>
            </p>
            <p style="text-align: center; align-content: center; vertical-align: middle; font-size: 14px;">
                <strong>No. de Autorización</strong>
                <br>
                <span style="font-size: 16px; text-transform: uppercase;">
                    {{ $data['Certificacion']['NumeroAutorizacion']['Llave'] }}
                </span>
            </p>
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>Serie</strong> {{ $data['Certificacion']['NumeroAutorizacion']['Serie'] }}
            </p>
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>Número</strong> {{ $data['Certificacion']['NumeroAutorizacion']['Numero'] }}
            </p>
        </div>
        <hr>
        <div style="clear: both; margin-bottom: 0px;">
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>Fecha Emisión</strong> {{ $data['DatosGenerales']['FechaHoraEmision'] }}
            </p>
        </div>
        <div style="clear: both; margin-bottom: 0px;">
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>NIT</strong> {{ $data['Receptor']['IDReceptor'] }}
            </p>
        </div>
        <div style="clear: both; margin-bottom: 0px;">
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>Nombre</strong> {{ $data['Receptor']['NombreReceptor'] }}
            </p>
        </div>
        <div style="clear: both; margin-bottom: 0px;">
            <p style="text-align: left; vertical-align: middle; font-size: 14px;">
                <strong>Dirección</strong> {{ $data['Receptor']['DireccionReceptor']['Completa'] }}
            </p>
        </div>
        <div style="border-bottom: 1px solid rgb(10, 8, 8);"></div>
        <table style="clear: both; margin-bottom: 0px; padding-top: 5px !important; font-size: 12px; width: 100%;">
            <thead style="border-bottom: 1px solid rgb(24, 24, 24);">
                <tr>
                    <th>Descripción</th>
                    <th>SubTotal</th>
                </tr>
            </thead>
        </table>
        <table style="width: 100%;">
            <tbody>
                {{ count($data['Items']['Productos']) }}
                @for($i = 0; $i < count($data['Items']['Productos']); $i++) <tr>
                    <td style="width: 65%; border-bottom: 1px solid black;">
                        <p style="white-space: nowrap;">
                            #{{ $data['Items']['Productos'][$i]['NumeroLinea'] }}
                        </p>
                        <p style="text-align: left; font-size: 12px;">
                            {{ $data['Items']['Productos'][$i]['Producto']['Descripcion'] }}
                            <br>
                            {{ $data['Items']['Productos'][$i]['Producto']['Cantidad'] }}
                            X {{ $data['Items']['Productos'][$i]['Producto']['PrecioUnitario'] }}
                            = {{ $data['Items']['Productos'][$i]['Producto']['Precio'] }}
                            @if($data['Items']['AplicaDescuento'])
                            - {{ $data['Items']['Productos'][$i]['Producto']['Descuento'] }}
                            @endif
                        </p>
                    </td>
                    <td style="width: 35%; border-bottom: 1px solid black;">
                        <p style="text-align: right; font-weight: bold; font-size: 14px; vertical-align: middle;">
                            {{$data['Items']['Productos'][$i]['Producto']['Total']}}
                        </p>
                    </td>
                    </tr>
                    @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align: left; font-size: 18px;">
                        <strong>Total Q</strong>
                    </td>
                    <td style="text-align: right; font-size: 18px;">
                        <strong>{{ $data['Totales']['GranTotal'] }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p
                            style="clear: both; margin-bottom: 0px; text-align: center; align-content: center; vertical-align: middle;  font-size: 14px;">
                            <strong>{{ $data['Certificacion']['NITCertificador'] }}</strong>
                        <p
                            style="text-align: center; align-content: center; vertical-align: middle; font-size: 12px; text-transform: uppercase;">
                            {{ $data['Certificacion']['NombreCertificador'] }}
                        </p>
                        <p style="text-align: center; vertical-align: middle; font-size: 11px;">
                            Fecha Certificación {{ $data['Certificacion']['FechaHoraCertificacion'] }}
                        </p>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>

</html>