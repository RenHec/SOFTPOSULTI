<?php

namespace App\DTE;

use Illuminate\Database\Eloquent\Model;

class Anulacion extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dte_anulacion';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'codigo',
        'mensaje',
        'acuseSAT',
        'codigoSAT',
        'responseXML',
        'autorizacionSAT',
        'serieSAT',
        'numeroSAT',
        'fechaDTESAT',
        'nitEmisor',
        'nombreEmisor',
        'nitReceptor',
        'nombreReceptor',
        'proceso',
        'fechaCertificacion',
        'transaction_id',
        'dte_control_id'
    ];
}
