<?php

//utiliza o autoloader do composer
require_once __DIR__ . '/vendor/autoload.php';

use Logis\Logis;

//opções de inicialização
$options = [
	'destino' => '38402100',
	'origem' => '97957000',
	'peso' => '120',
	'itens' => '4',
	'dimensoes' => [
		'altura' => '40',
		'largura' => '50',
		'comprimento' => '80'
	]
];

//abertura da API
$logis = new Logis($options);

//calcula o peso cubado a partir dos dados
$peso_cubado = $logis->calcularCubagem();

//retorna o valor final que vem do banco
$valor_final = $logis->freteCubado($peso_cubado);

//debug
echo "<pre>" . $valor_final . "</pre>";
