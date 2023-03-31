<?php

namespace App\DTE;

use Illuminate\Database\Eloquent\Model;

class Control extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dte_control';

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
        'responseHTML',
        'responsePDF',
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
        'transaction_id'
    ];

    public function anulacion()
    {
        return $this->hasOne(Anulacion::class, 'dte_control_id', 'id');
    }
}
