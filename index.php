<?php

//utiliza o autoloader do composer
require_once __DIR__ . '/vendor/autoload.php';

use Logis\Logis;

//opções de inicialização
$options = [
	'destino' => '96810178',
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

//retorna os dados em formato de array (serviço, valor, prazo, mensagem)
$valores = $logis->calcularFrete();

//debug
echo "<pre>";
print_r($valores);
echo "</pre>";
