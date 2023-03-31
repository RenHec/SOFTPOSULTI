<?php

namespace App\Http\Controllers\DTE;

use Exception;
use XMLWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\DTE\Control;
use App\Transaction;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ControlFELController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Transaction  $transaccion
     * @return \Illuminate\Http\Response
     */
    public function certificar(Transaction $transaccion)
    {
        try {
            $base = Config::get("facturaDTE.url.base");
            $version = Config::get("facturaDTE.url.version");

            $token = $this->tokenGenerate($base, $version);
            $documento = $this->certificarDTE($base, $version, $token, $transaccion, $this->generateXML($transaccion->id));

            $output = [
                'success' => true,
                'msg' => "El certificado {$documento} fue creado"
            ];

            return redirect()->route('sells.index')->with('status', $output);
        } catch (\Exception $e) {
            Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => $e->getMessage()
            ];

            return redirect()->route('sells.index')->with('status', $output);
        }
    }

    private function tokenGenerate(string $base, string $version)
    {
        try {
            $expira = Config::get("facturaDTE.get_token.expira_en");

            $client = new Client();
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];

            $data = [
                'Username' => Config::get("facturaDTE.login.username"),
                'Password' => Config::get("facturaDTE.login.password")
            ];

            if (empty($expira)) {
                $res = $client->post("{$base}/{$version}/login/get_token", [
                    'headers' => $headers,
                    'body' => json_encode($data),
                ]);

                $getToken = json_decode($res->getBody(), true);

                Config::set("facturaDTE.get_token.token", $getToken["Token"]);
                Config::set("facturaDTE.get_token.expira_en", $getToken["expira_en"]);
                Config::set("facturaDTE.get_token.otorgado_a", $getToken["otorgado_a"]);
            } else if (!empty($expira)) {
                if (date('Ymd') >= date('Ymd', strtotime($expira))) {
                    $res = $client->post("{$base}/{$version}/login/get_token", [
                        'headers' => $headers,
                        'body' => json_encode($data),
                    ]);

                    $getToken = json_decode($res->getBody(), true);
                }
            }

            return Config::get("facturaDTE.get_token.token");
        } catch (\GuzzleHttp\Exception\BadResponseException  $th) {
            $error = json_decode($th->getResponse()->getBody()->getContents(), true);
            throw new Exception("Ocurrio un problema al obtener el token del certificador, {$error['description']}");
        } catch (\Throwable $th) {
            throw new Exception("Ocurrio un error en el proceso para obtener el token {$data['Username']}");
        }
    }

    private function certificarDTE(string $base, string $version, string $token, Transaction $transaction, string $xml): string
    {
        try {
            $client = new Client();

            $headers = [
                'Accept' => '*/*',
                'Content-Type' => 'application/json',
                'Authorization' => "{$token}",
                'User-Agent' => $_SERVER['HTTP_USER_AGENT']
            ];

            $nit = Config::get('facturaDTE.DTE.Emisor.NITEmisor');
            $username = explode('.', Config::get("facturaDTE.login.username"));

            $res = $client->request('POST', "{$base}/{$version}/FELRequestV2", [
                'headers' => $headers,
                'query' => [
                    'NIT' => str_pad(strval($nit), 12, "0", STR_PAD_LEFT),
                    'TIPO' => 'CERTIFICATE_DTE_XML_TOSIGN',
                    'FORMAT' => 'XML,PDF,HTML',
                    'USERNAME' => $username[2]
                ],
                'body' => $xml
            ]);

            $getBody = json_decode($res->getBody(), true);

            DB::beginTransaction();

            $control = $this->saveData($getBody, $transaction->id);
            $transaction->invoice_no = $control->autorizacionSAT;
            $transaction->save();

            DB::commit();

            $documento = str_replace('-', '', $control->autorizacionSAT);
            Storage::disk('certificado')->put("{$documento}.pdf", base64_decode($control->responsePDF, true));

            if (Config::get('facturaDTE.generar_ticket')) {
                Storage::disk('certificado')->put("TICKET_{$documento}.pdf", $this->generateTicket($control->responseXML));
            }

            $transaction->transaction_date = date('Y-m-d H:i:s');
            $transaction->save();

            return "{$transaction->invoice_no}";
        } catch (\GuzzleHttp\Exception\BadResponseException  $th) {
            DB::rollBack();
            $error = json_decode($th->getResponse()->getBody()->getContents(), true);
            if (intVal($error['Codigo']) == 9022) {
                $UUID = explode('UUID', $error['ResponseDATA1']);
                $autorizacionSAT = explode(',', $UUID[1]);
                $error['Autorizacion'] = mb_strtoupper(str_replace(' ', '', $autorizacionSAT[0]));
                $error['NIT_COMPRADOR'] = mb_strtoupper(str_replace(['a', ' '], ['', ''], $autorizacionSAT[1]));

                $transaccion = Transaction::find($transaction->id);
                $fechaTransaccion = date("Y-m-d", strtotime($transaccion->transaction_date));
                $horaTransaccion = date("H:i:s", strtotime($transaccion->transaction_date));
                $error['Fecha_DTE'] = "{$fechaTransaccion}T{$horaTransaccion}";
            }
            $this->saveData($error, $transaction->id, $th->getMessage());
            throw new Exception("Ocurrio un problema al certificar el documento, {$error['Mensaje']}");
        } catch (\Throwable $th) {
            throw new Exception("Ocurrio un error en el proceso para obtener el certificar DTE, {$th->getMessage()}");
        }
    }

    private function generateXML(int $transaction_id): string
    {
        try {
            $query = Transaction::where('id', $transaction_id)
                ->with([
                    'contact',
                    'sell_lines' => function ($q) {
                        $q->whereNull('parent_sell_line_id');
                    },
                    'sell_lines.product',
                    'sell_lines.product.unit',
                    'sell_lines.variations',
                    'sell_lines.variations.product_variation',
                    'payment_lines',
                    'sell_lines.modifiers',
                    'sell_lines.lot_details',
                    'tax',
                    'sell_lines.sub_unit',
                    'table',
                    'service_staff',
                    'sell_lines.service_staff',
                    'types_of_service',
                    'sell_lines.warranties',
                    'media'
                ]);

            $transaccion = $query->firstOrFail();

            $prefix = "dte";

            $xml = new XMLWriter();
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');

            /* =============== INICIO GTDocumento ======== */
            $xml->startElementNs($prefix, 'GTDocumento', null);
            $xml->startAttribute("xmlns:{$prefix}");
            $xml->text("http://www.sat.gob.gt/dte/fel/0.2.0");
            $xml->startAttribute("xmlns:xsi");
            $xml->text("http://www.w3.org/2001/XMLSchema-instance");
            $xml->startAttribute("Version");
            $xml->text("0.1");
            $xml->endAttribute();

            /* =============== INICIO SAT ======== */
            $xml->startElementNs($prefix, 'SAT', null);
            $xml->startAttribute("ClaseDocumento");
            $xml->text($prefix);
            $xml->endAttribute();

            /* =============== INICIO DTE ======== */
            $xml->startElementNs($prefix, 'DTE', null);
            $xml->startAttribute("ID");
            $xml->text("DatosCertificados");
            $xml->endAttribute();

            /* =============== INICIO DatosEmision ======== */
            $xml->startElementNs($prefix, 'DatosEmision', null);
            $xml->startAttribute("ID");
            $xml->text(Config::get("facturaDTE.DTE.DatosCertificados.ID"));
            $xml->endAttribute();

            /* =============== INICIO DatosGenerales ======== */
            $xml->startElementNs($prefix, 'DatosGenerales', null);
            $xml->startAttribute("Tipo");
            $xml->text(Config::get("facturaDTE.DTE.DatosCertificados.Tipo"));
            $xml->startAttribute("FechaHoraEmision");
            $fechaTransaccion = date("Y-m-d", strtotime($transaccion->transaction_date));
            $horaTransaccion = date("H:i:s", strtotime($transaccion->transaction_date));
            $xml->text("{$fechaTransaccion}T{$horaTransaccion}");
            $xml->startAttribute("CodigoMoneda");
            $xml->text(Config::get("facturaDTE.DTE.DatosCertificados.CodigoMoneda"));
            $xml->endAttribute();

            $xml->endElement();
            /* =============== FIN DatosGenerales ======== */

            /* =============== INICIO Emisor ======== */
            $xml->startElementNs($prefix, 'Emisor', null);
            $xml->startAttribute("NITEmisor");
            $xml->text(Config::get("facturaDTE.DTE.Emisor.NITEmisor"));
            $xml->startAttribute("NombreEmisor");
            $xml->text(Config::get("facturaDTE.DTE.Emisor.NombreEmisor"));
            $xml->startAttribute("CodigoEstablecimiento");
            $xml->text(Config::get("facturaDTE.DTE.Emisor.CodigoEstablecimiento"));
            $xml->startAttribute("NombreComercial");
            $xml->text(Config::get("facturaDTE.DTE.Emisor.NombreComercial"));
            $xml->startAttribute("AfiliacionIVA");
            $xml->text(Config::get("facturaDTE.DTE.Emisor.AfiliacionIVA"));
            $xml->endAttribute();

            /* =============== INICIO DireccionEmisor ======== */
            $xml->startElementNs($prefix, 'DireccionEmisor', null);
            $xml->writeElementNs($prefix, 'Direccion', null, Config::get("facturaDTE.DTE.Emisor.DireccionEmisor.Direccion"));
            $xml->writeElementNs($prefix, 'CodigoPostal', null, Config::get("facturaDTE.DTE.Emisor.DireccionEmisor.CodigoPostal"));
            $xml->writeElementNs($prefix, 'Municipio', null, Config::get("facturaDTE.DTE.Emisor.DireccionEmisor.Municipio"));
            $xml->writeElementNs($prefix, 'Departamento', null, Config::get("facturaDTE.DTE.Emisor.DireccionEmisor.Departamento"));
            $xml->writeElementNs($prefix, 'Pais', null, Config::get("facturaDTE.DTE.Emisor.DireccionEmisor.Pais"));
            $xml->endElement();
            /* =============== FIN DireccionEmisor ======== */

            $xml->endElement();
            /* =============== FIN Emisor ======== */

            /* =============== INICIO Receptor ======== */
            $xml->startElementNs($prefix, 'Receptor', null);
            $xml->startAttribute("NombreReceptor");
            $xml->text(str_replace(" ", "", $transaccion->contact->name) == "" ? $transaccion->contact->supplier_business_name : $transaccion->contact->name);
            $xml->startAttribute("CorreoReceptor");
            $xml->text($transaccion->contact->email);
            $xml->startAttribute("IDReceptor");
            $xml->text(is_null($transaccion->contact->contact_id) ? 'CF' : $transaccion->contact->contact_id);
            $xml->endAttribute();

            /* =============== INICIO DireccionReceptor ======== */
            $xml->startElementNs($prefix, 'DireccionReceptor', null);
            $xml->writeElementNs($prefix, 'Direccion', null, is_null($transaccion->contact->address_line_1) ? 'ciudad' : $transaccion->contact->address_line_1);
            $xml->writeElementNs($prefix, 'CodigoPostal', null, is_null($transaccion->contact->zip_code) ? "0" : $transaccion->contact->zip_code);
            $xml->writeElementNs($prefix, 'Municipio', null, is_null($transaccion->contact->city) ? "" : $transaccion->contact->city);
            $xml->writeElementNs($prefix, 'Departamento', null, is_null($transaccion->contact->state) ? "" : $transaccion->contact->state);
            $xml->writeElementNs($prefix, 'Pais', null, "GT");
            $xml->endElement();
            /* =============== FIN DireccionReceptor ======== */

            $xml->endElement();
            /* =============== FIN Receptor ======== */

            /* =============== INICIO Frases ======== */
            $xml->startElementNs($prefix, 'Frases', null);

            /* =============== INICIO Frase ======== */
            $xml->startElementNs($prefix, 'Frase', null);
            $xml->startAttribute("TipoFrase");
            $xml->text(Config::get("facturaDTE.DTE.Frases.TipoFrase"));
            $xml->startAttribute("CodigoEscenario");
            $xml->text(Config::get("facturaDTE.DTE.Frases.CodigoEscenario"));
            $xml->endAttribute();
            $xml->endElement();
            /* =============== FIN Frase ======== */

            $xml->endElement();
            /* =============== FIN Frases ======== */

            /* =============== INICIO Items ======== */
            $xml->startElementNs($prefix, 'Items', null);

            $totalMontoImpuesto = 0;
            $granTotal = 0;
            foreach ($transaccion->sell_lines as $key => $item) {
                /* =============== INICIO Item ======== */
                $xml->startElementNs($prefix, 'Item', null);
                $xml->startAttribute("NumeroLinea");
                $xml->text($key + 1);
                $xml->startAttribute("BienOServicio");
                $xml->text("B");
                $xml->endAttribute();

                $xml->writeElementNs($prefix, 'Cantidad', null, $item->quantity);
                $xml->writeElementNs($prefix, 'UnidadMedida', null, !empty($item->sub_unit) ? mb_strtoupper($item->sub_unit->short_name) : mb_strtoupper($item->product->unit->short_name));

                $nombre_producto = $item->product->name;
                if ($item->product->type == 'variable') {
                    $nombre_producto .= " - " . $item->variations->product_variation->name ?? '';
                    $nombre_producto .= " - " . $item->variations->name ?? '';
                }
                $nombre_producto .= " " . $item->variations->sub_sku ?? '';
                if (!empty($item->product->brand->name)) {
                    $nombre_producto .= " , " . $item->product->brand->name;
                }

                $precio = ($item->unit_price_before_discount + $item->item_tax);
                $subTotal = ($item->quantity * $precio);
                $descuento = ($item->quantity * $item->get_discount_amount());
                $xml->writeElementNs($prefix, 'Descripcion', null, $nombre_producto);
                $xml->writeElementNs($prefix, 'PrecioUnitario', null, $precio);
                $xml->writeElementNs($prefix, 'Precio', null, $subTotal);
                $xml->writeElementNs($prefix, 'Descuento', null, $descuento);

                /* =============== INICIO Impuestos ======== */
                $xml->startElementNs($prefix, 'Impuestos', null);

                /* =============== INICIO Impuesto ======== */
                $xml->startElementNs($prefix, 'Impuesto', null);
                $xml->writeElementNs($prefix, 'NombreCorto', null, "IVA");
                $xml->writeElementNs($prefix, 'CodigoUnidadGravable', null, "1");

                $total = round(($subTotal - $descuento), 4);
                $granTotal += $total;
                $erc = round(($total / 1.12), 4);
                $iva = (($erc * 12) / 100);
                $totalMontoImpuesto += $iva;

                $xml->writeElementNs($prefix, 'MontoGravable', null, $erc);
                $xml->writeElementNs($prefix, 'MontoImpuesto', null, $iva);
                $xml->endElement();
                /* =============== FIN Impuesto ======== */

                $xml->endElement();
                /* =============== FIN Impuestos ======== */

                $xml->writeElementNs($prefix, 'Total', null, $total);
                $xml->endElement();
                /* =============== FIN Item ======== */
            }

            $xml->endElement();
            /* =============== FIN Items ======== */

            /* =============== INICIO Totales ======== */
            $xml->startElementNs($prefix, 'Totales', null);

            /* =============== INICIO TotalImpuestos ======== */
            $xml->startElementNs($prefix, 'TotalImpuestos', null);

            /* =============== INICIO TotalImpuesto ======== */
            $xml->startElementNs($prefix, 'TotalImpuesto', null);
            $xml->startAttribute("NombreCorto");
            $xml->text("IVA");
            $xml->startAttribute("TotalMontoImpuesto");
            $xml->text($totalMontoImpuesto);
            $xml->endElement();
            /* =============== FIN TotalImpuesto ======== */

            $xml->endElement();
            /* =============== FIN TotalImpuestos ======== */

            $xml->writeElementNs($prefix, 'GranTotal', null, $granTotal);
            $xml->endElement();
            /* =============== FIN Totales ======== */

            $xml->endElement();
            /* =============== FIN DatosEmision ======== */

            $xml->endElement();
            /* =============== FIN DTE ======== */


            /* =============== INICIO Adenda ======== */
            $xml->startElementNs($prefix, 'Adenda', null);

            /* =============== INICIO Informacion_COMERCIAL ======== */
            $xml->startElementNs("{$prefix}comm", 'Informacion_COMERCIAL', null);
            $xml->startAttribute("xmlns:{$prefix}comm");
            $xml->text("https://www.digifact.com.gt/dtecomm");
            $xml->startAttribute("xsi:schemaLocation");
            $xml->text("https://www.digifact.com.gt/dtecomm");
            $xml->endAttribute();

            /* =============== INICIO InformacionAdicional ======== */
            $xml->startElementNs("{$prefix}comm", 'InformacionAdicional', null);
            $xml->startAttribute("Version");
            $xml->text("2020_06_01");
            $xml->endAttribute();

            $xml->writeElementNs("{$prefix}comm", 'REFERENCIA_INTERNA', null, "INVOICE{$transaccion->invoice_no}");
            $xml->writeElementNs("{$prefix}comm", 'FECHA_REFERENCIA', null, "{$fechaTransaccion}T{$horaTransaccion}");
            $xml->writeElementNs("{$prefix}comm", 'VALIDAR_REFERENCIA_INTERNA', null, "VALIDAR");

            $xml->endElement();
            /* =============== FIN InformacionAdicional ======== */

            $xml->endElement();
            /* =============== FIN Informacion_COMERCIAL ======== */

            $xml->endElement();
            /* =============== FIN Adenda ======== */

            $xml->endElement();
            /* =============== FIN SAT ======== */

            $xml->endElement();
            /* =============== FIN GTDocumento ======== */

            //XML GENERADO
            $xml = json_encode($xml->outputMemory(true), JSON_UNESCAPED_UNICODE);
            $xml = str_replace(['"<', '>"', '\\', '>n<'], ['<', '>', '', '><'], $xml);

            return $xml;
        } catch (\Throwable $th) {
            throw new Exception("Ocurrio un error al generar el XML que necesita certificar.");
        }
    }

    private function saveData($data, int $transaction_id, string $error = "error"): Control
    {
        $control = Control::create([
            'codigo' => $data['Codigo'],
            'mensaje' => $data['Mensaje'],
            'acuseSAT' => $data['AcuseReciboSAT'],
            'codigoSAT' => $data['CodigosSAT'],
            'responseXML' => empty($data['ResponseDATA1']) ? $error : $data['ResponseDATA1'],
            'responseHTML' => $data['ResponseDATA2'],
            'responsePDF' => $data['ResponseDATA3'],
            'autorizacionSAT' => $data['Autorizacion'],
            'serieSAT' => $data['Serie'],
            'numeroSAT' => $data['NUMERO'],
            'fechaDTESAT' => $data['Fecha_DTE'],
            'nitEmisor' => $data['NIT_EFACE'],
            'nombreEmisor' => $data['NOMBRE_EFACE'],
            'nitReceptor' => $data['NIT_COMPRADOR'],
            'nombreReceptor' => $data['NOMBRE_COMPRADOR'],
            'proceso' => $data['BACKPROCESOR'],
            'fechaCertificacion' => $data['Fecha_de_certificacion'],
            'transaction_id' => $transaction_id
        ]);

        return $control;
    }

    private function generateTicket(string $xml): string
    {
        $xmlGenerado = simplexml_load_string(base64_decode($xml), "SimpleXMLElement", LIBXML_NOCDATA, "dte", true);

        $DatosGenerales = $xmlGenerado->SAT->DTE->DatosEmision->DatosGenerales->attributes();
        $data['DatosGenerales']['Tipo'] = (string) $DatosGenerales->Tipo;
        $data['DatosGenerales']['FechaHoraEmision'] = date('d/m/Y H:i:s', strtotime((string) $DatosGenerales->FechaHoraEmision));
        $data['DatosGenerales']['CodigoMoneda'] = (string) $DatosGenerales->CodigoMoneda;

        $Emisor = $xmlGenerado->SAT->DTE->DatosEmision->Emisor->attributes();
        $data['Emisor']['NITEmisor'] = (string) $Emisor->NITEmisor;
        $data['Emisor']['NombreEmisor'] = (string) $Emisor->NombreEmisor;
        $data['Emisor']['NombreComercial'] = (string) $Emisor->NombreComercial;
        $data['Emisor']['AfiliacionIVA'] = (string) $Emisor->AfiliacionIVA;
        $DireccionEmisor = $xmlGenerado->SAT->DTE->DatosEmision->Emisor->DireccionEmisor;
        $data['Emisor']['DireccionEmisor']['Direccion'] = (string) $DireccionEmisor->Direccion;
        $data['Emisor']['DireccionEmisor']['CodigoPostal'] = (string) $DireccionEmisor->CodigoPostal;
        $data['Emisor']['DireccionEmisor']['Municipio'] = (string) $DireccionEmisor->Municipio;
        $data['Emisor']['DireccionEmisor']['Departamento'] = (string) $DireccionEmisor->Departamento;
        $DireccionEmisorCompleta = $data['Emisor']['DireccionEmisor']['Direccion'];
        $DireccionEmisorCompleta .= !empty($data['Emisor']['DireccionEmisor']['Municipio']) ?  ", {$data['Emisor']['DireccionEmisor']['Municipio']}" : "";
        $DireccionEmisorCompleta .= !empty($data['Emisor']['DireccionEmisor']['Departamento']) ?  ", {$data['Emisor']['DireccionEmisor']['Departamento']}" : "";
        $data['Emisor']['DireccionEmisor']['Completa'] = mb_strtoupper($DireccionEmisorCompleta);

        $Receptor = $xmlGenerado->SAT->DTE->DatosEmision->Receptor->attributes();
        $data['Receptor']['NombreReceptor'] = mb_strtoupper((string) trim($Receptor->NombreReceptor));
        $data['Receptor']['CorreoReceptor'] = (string) $Receptor->CorreoReceptor;
        $data['Receptor']['IDReceptor'] = (string) $Receptor->IDReceptor;
        $DireccionReceptor = $xmlGenerado->SAT->DTE->DatosEmision->Receptor->DireccionReceptor;
        $data['Receptor']['DireccionReceptor']['Direccion'] = (string) $DireccionReceptor->Direccion;
        $data['Receptor']['DireccionReceptor']['CodigoPostal'] = (string) $DireccionReceptor->CodigoPostal;
        $data['Receptor']['DireccionReceptor']['Municipio'] = (string) $DireccionReceptor->Municipio;
        $data['Receptor']['DireccionReceptor']['Departamento'] = (string) $DireccionReceptor->Departamento;
        $DireccionReceptorCompleta = $data['Receptor']['DireccionReceptor']['Direccion'];
        $DireccionReceptorCompleta .= !empty($data['Receptor']['DireccionReceptor']['Municipio']) ?  ", {$data['Receptor']['DireccionReceptor']['Municipio']}" : "";
        $DireccionReceptorCompleta .= !empty($data['Receptor']['DireccionReceptor']['Departamento']) ?  ", {$data['Receptor']['DireccionReceptor']['Departamento']}" : "";
        $data['Receptor']['DireccionReceptor']['Completa'] = mb_strtoupper($DireccionReceptorCompleta);

        $Items = $xmlGenerado->SAT->DTE->DatosEmision->Items;
        $data['Items']['Productos'] = array();
        $data['Items']['AplicaDescuento'] = false;

        $i = 0;
        foreach ($Items->Item as $key => $value) {
            $item = $value->attributes();
            $info['NumeroLinea'] = (int) $item->NumeroLinea;
            $info['BienOServicio'] = (int) $item->BienOServicio;

            $info['Producto']['Cantidad'] = (int) $value->Cantidad;
            $info['Producto']['UnidadMedida'] = (string) $value->UnidadMedida;
            $info['Producto']['Descripcion'] = mb_strtoupper((string) $value->Descripcion);
            $info['Producto']['PrecioUnitario'] = number_format((string) $value->PrecioUnitario, 2, '.', ',');
            $info['Producto']['Precio'] = number_format((string) $value->Precio, 2, '.', ',');
            $info['Producto']['Descuento'] = number_format((string) $value->Descuento, 2, '.', ',');
            if ((float) $value->Descuento > 0) {
                $data['Items']['AplicaDescuento'] = true;
            }
            $info['Producto']['Total'] = number_format((string) $value->Total, 2, '.', ',');

            array_push($data['Items']['Productos'], $info);
            $i++;
        }

        $data['Items']['FilasImprimir'] = $i;
        $Impuestos = $xmlGenerado->SAT->DTE->DatosEmision->Totales->TotalImpuestos->TotalImpuesto->attributes();
        $data['Totales']['TotalMontoImpuesto'] = number_format((string) $Impuestos->TotalMontoImpuesto, 2, '.', ',');
        $data['Totales']['GranTotal'] = number_format((string) $xmlGenerado->SAT->DTE->DatosEmision->Totales->GranTotal, 2, '.', ',');

        $data['Certificacion']['NITCertificador'] = (string) $xmlGenerado->SAT->DTE->Certificacion->NITCertificador;
        $data['Certificacion']['NombreCertificador'] = (string) $xmlGenerado->SAT->DTE->Certificacion->NombreCertificador;
        $data['Certificacion']['NumeroAutorizacion']['Llave'] = (string) $xmlGenerado->SAT->DTE->Certificacion->NumeroAutorizacion;
        $NumeroAutorizacion = $xmlGenerado->SAT->DTE->Certificacion->NumeroAutorizacion->attributes();
        $data['Certificacion']['NumeroAutorizacion']['Serie'] = (string) $NumeroAutorizacion->Serie;
        $data['Certificacion']['NumeroAutorizacion']['Numero'] = (string) $NumeroAutorizacion->Numero;
        $data['Certificacion']['FechaHoraCertificacion'] = date('d/m/Y H:i:s', strtotime((string) $xmlGenerado->SAT->DTE->Certificacion->FechaHoraCertificacion));

        $alto = $i == 0 ? 600 : 900;
        $pdf = new Dompdf();
        $options = new Options();
        $pdf->setPaper([0, 0, 305, 750]);
        $options->set('isPhpEnabled', true);
        $options->set('isJavascriptEnabled', true);
        $options->setDpi('59');
        $options->setDefaultFont('courier');
        $options->setIsRemoteEnabled(true);
        $options->isHtml5ParserEnabled(true);
        $pdf->setOptions($options);
        $vista = view('dte.template', compact('data'))->render();
        $pdf->loadHtml($vista);
        $pdf->render();

        return $pdf->output();
    }
}
