## OPÇÕES

O Logis aceita inicializar através de um array, com as seguintes opções:

"destino" (Inteiro) - O cep de destino (cliente)

"origem" (Inteiro) - O cep de origem (empresa)

"peso" (Decimal) - O peso somado dos itens (em kg - correios retorna até 30kg)

"itens" (Inteiro) - O número total de itens

"dimensões" (Array):

	"altura" (Decimal) - A altura somada dos itens (em m)
	
	"largura" (Decimal) - A largura/espessura somada dos itens (em m)
	
	"comprimento" (Decimal) - O comprimento/profundidade somado dos itens (em m)

O retorno é o valor em reais.

## EXEMPLO

```php
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
$valores[] = $logis->calcularCubagem();

//debug
echo "<pre>";
print_r($valores);
echo "</pre>";
```
