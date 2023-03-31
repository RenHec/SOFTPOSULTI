<?php

return [
    'activar'   => (bool) env('DTE_ACTIVE', false),
    'generar_ticket'  => (bool) env('DTE_GENERAR_TICKET', false),
    'url'       => [
        'base'  => (string) env('DTE_URL_BASE', 'https://felgttestaws.digifact.com.gt'),
        'version'  => (string) env('DTE_URL_VERSION', 'gt.com.fel.api.v3/api')
    ],
    'login'     => [
        'username'      => (string) env('DTE_USERNAME', 'GT.000044653948.PRUEBAS56'),
        'password'      => (string) env('DTE_PASSWORD', 'w&LWv8h_')
    ],
    'get_token' => [
        'token'         => (string) '',
        'expira_en'     => (string) '',
        'otorgado_a'    => (string) ''
    ],

    'DTE'       => [
        'EnviarEmail'           => (bool) env('DTE_EnviarEmail', false),
        'DatosCertificados'     => [
            'ID'            => 'DatosEmision',
            'Tipo'          => 'FACT',
            'CodigoMoneda'  => 'GTQ'
        ],
        'Emisor'                => [
            'NITEmisor'                 => (string) env('DTE_NITEmisor', '44653948'),
            'NombreEmisor'              => (string) env('DTE_NombreEmisor', 'COMPAÑIA DE AGUA DE LAS TERRAZAS, S.A.'),
            'CodigoEstablecimiento'     => (int) env('DTE_CodigoEstablecimiento', 1),
            'NombreComercial'           => (string) env('DTE_NombreComercial', "COMPAÑIA DE AGUA DE LAS TERRAZAS"),
            'AfiliacionIVA'             => (string) env('DTE_AfiliacionIVA', 'GEN'),
            'DireccionEmisor'           => [
                'Direccion'         =>  (string) env('DTE_DireccionEmisor_Direccion', 'Via Granada, Zona 8'),
                'CodigoPostal'      =>  (string) env('DTE_DireccionEmisor_CodigoPostal', '01006'),
                'Municipio'         =>  (string) env('DTE_DireccionEmisor_Municipio', 'VILLA NUEVA'),
                'Departamento'      =>  (string) env('DTE_DireccionEmisor_Departamento', 'GUATEMALA'),
                'Pais'              =>  (string) env('DTE_DireccionEmisor_Pais', 'GT')
            ]
        ],
        'Frases'                => [
            'TipoFrase'         => (string) env('DTE_Frases_TipoFrase', '1'),
            'CodigoEscenario'   => (string) env('DTE_Frases_CodigoEscenario', '1')
        ]
    ]
];
