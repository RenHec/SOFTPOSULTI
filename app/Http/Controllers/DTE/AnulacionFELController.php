<?php

namespace App\Http\Controllers\DTE;

use Exception;
use XMLWriter;
use App\DTE\Control;
use App\DTE\Anulacion;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class AnulacionFELController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\DTE\Control  $certificado
     * @return \Illuminate\Http\Response
     */
    public function anular(Control $certificado)
    {
        try {
            $base = Config::get("facturaDTE.url.base");
            $version = Config::get("facturaDTE.url.version");

            $token = $this->tokenGenerate($base, $version);
            $documento = $this->certificarDTE($base, $version, $token, $certificado, $this->generateXML($certificado));

            $output = [
                'success' => true,
                'msg' => "El certificado {$documento} fue anulado"
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

    private function certificarDTE(string $base, string $version, string $token, Control $control, string $xml): string
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
                    'TIPO' => 'ANULAR_FEL_TOSIGN',
                    'FORMAT' => 'XML,PDF,HTML',
                    'USERNAME' => $username[2]
                ],
                'body' => $xml
            ]);

            $getBody = json_decode($res->getBody(), true);

            DB::beginTransaction();

            $anulacion = $this->saveData($getBody, $control->id, $control->transaction_id);
            Control::where('autorizacionSAT', $control->autorizacionSAT)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            $control->save();

            $transaction = Transaction::find($control->transaction_id);
            $transaction->invoice_no = "ANULACION-{$anulacion->autorizacionSAT}";
            $transaction->save();

            DB::commit();

            return "{$anulacion->AcuseReciboSAT}";
        } catch (\GuzzleHttp\Exception\BadResponseException  $th) {
            DB::rollBack();
            $error = json_decode($th->getResponse()->getBody()->getContents(), true);
            throw new Exception("Ocurrio un problema al anular el documento, {$error['Mensaje']}");
        } catch (\Throwable $th) {
            throw new Exception("Ocurrio un error en el proceso para obtener el anular DTE, {$th->getMessage()}");
        }
    }

    private function generateXML(Control $control): string
    {
        try {
            $prefix = "dte";

            $xml = new XMLWriter();
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');

            /* =============== INICIO GTAnulacionDocumento ======== */
            $xml->startElementNs($prefix, 'GTAnulacionDocumento', null);
            $xml->startAttribute("xmlns:{$prefix}");
            $xml->text("http://www.sat.gob.gt/dte/fel/0.1.0");
            $xml->startAttribute("xmlns:xsi");
            $xml->text("http://www.w3.org/2001/XMLSchema-instance");
            $xml->startAttribute("Version");
            $xml->text("0.1");
            $xml->endAttribute();

            /* =============== INICIO SAT ======== */
            $xml->startElementNs($prefix, 'SAT', null);

            /* =============== INICIO AnulacionDTE ======== */
            $xml->startElementNs($prefix, 'AnulacionDTE', null);
            $xml->startAttribute("ID");
            $xml->text("DatosCertificados");
            $xml->endAttribute();

            /* =============== INICIO DatosGenerales ======== */
            $xml->startElementNs($prefix, 'DatosGenerales', null);
            $xml->startAttribute("ID");
            $xml->text("DatosAnulacion");
            $xml->startAttribute("NumeroDocumentoAAnular");
            $xml->text($control->autorizacionSAT);
            $xml->startAttribute("NITEmisor");
            $xml->text(Config::get('facturaDTE.DTE.Emisor.NITEmisor'));
            $xml->startAttribute("IDReceptor");
            $xml->text($control->nitReceptor);
            $xml->startAttribute("FechaEmisionDocumentoAnular");
            $xml->text($control->fechaDTESAT);
            $xml->startAttribute("FechaHoraAnulacion");
            $fecha = date("Y-m-d");
            $hora = date("H:i:s");
            $xml->text("{$fecha}T{$hora}");
            $xml->startAttribute("MotivoAnulacion");
            $usuario = Auth::user();
            $xml->text("Anulación de documento por el usuario {$usuario->username}");
            $xml->endAttribute();

            $xml->endElement();
            /* =============== FIN DatosGenerales ======== */

            $xml->endElement();
            /* =============== FIN AnulacionDTE ======== */


            $xml->endElement();
            /* =============== FIN SAT ======== */

            $xml->endElement();
            /* =============== FIN GTAnulacionDocumento ======== */

            //XML GENERADO
            $xml = json_encode($xml->outputMemory(true), JSON_UNESCAPED_UNICODE);
            $xml = str_replace(['"<', '>"', '\\', '>n<'], ['<', '>', '', '><'], $xml);

            return $xml;
        } catch (\Throwable $th) {
            throw new Exception("Ocurrio un error al generar el XML que necesita anular.");
        }
    }

    private function saveData($data, int $control_id, int $transaction_id, string $error = "error"): Anulacion
    {
        $anulacion = Anulacion::create([
            'codigo' => $data['Codigo'],
            'mensaje' => $data['Mensaje'],
            'acuseSAT' => $data['AcuseReciboSAT'],
            'codigoSAT' => $data['CodigosSAT'],
            'responseXML' => empty($data['ResponseDATA1']) ? $error : $data['ResponseDATA1'],
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
            'transaction_id' => $transaction_id,
            'dte_control_id' => $control_id
        ]);

        return $anulacion;
    }
}
