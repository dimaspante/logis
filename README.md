## OPÇÕES

O Logis aceita inicializar através de um array, com as seguintes opções:

"destino" (Inteiro) - O cep de destino (cliente)

"origem" (Inteiro) - O cep de origem (empresa)

"peso" (Decimal) - O peso somado dos itens (em kg)

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
	'peso' => '120',
	'itens' => '4',
	'dimensoes' => [
		'altura' => '0.4',
		'largura' => '0.5',
		'comprimento' => '0.8'
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
```
