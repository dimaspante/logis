<?php

//utiliza o autoloader do composer
require_once __DIR__ . '/vendor/autoload.php';

use Logis\Logis;

//opções de inicialização
$options = [
	'destino' => '38402100',
	'origem' => '97957000',
	'peso' => '20',
	'itens' => '4',
	'dimensoes' => [
		'altura' => '0.4',
		'largura' => '0.5',
		'comprimento' => '0.8'
	]
];

//abertura da API
$logis = new Logis($options);

//incializa o array de valores
$valores = array();

//retorna o valor e o prazo dos Correios (webservice)
$valores[] = $logis->calcularCorreios();

//retorna o valor e o prazo das transportadoras (interno)
$valores[] = $logis->calcularTransportadoras();

//debug
echo "<pre>";
print_r($valores);
echo "</pre>";
