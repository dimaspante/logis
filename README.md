## OPÇÕES

O Logis aceita inicializar através de um array, com as seguintes opções:

**destino** (int) O cep de destino (cliente)

**origem** (int) O cep de origem (empresa)

**valor** (float) O valor total dos itens

**peso** (float) O peso somado dos itens (em kg - correios retorna até 30kg)

**itens** (int) O número total de itens, opcional

**dimensões** (array):

- altura (float) A altura somada dos itens (em m)
- largura (float) A largura/espessura somada dos itens (em m)
- comprimento (float) O comprimento/profundidade somado dos itens (em m)

O retorno é um (array) com os dados:

- servico (string) O nome da transportadora ou da modalidade (no caso de Correios)
- preco (float) O valor total para cada serviço
- prazo (int) O prazo em dias previsto para a entrega
- mensagem (string) Uma mensagem referente ao resultado (em caso de erro, retorna null)

## EXEMPLO

```php
<?php

//utiliza o autoloader do composer
require_once __DIR__ . '/vendor/autoload.php';

use Logis\Logis;

//opções de inicialização
$options = [
	'destino' => '96810178',
	'origem' => '97957000',
    'valor' => '700.90',
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
```

## RETORNO

```php
Array
(
    [0] => Array
        (
            [servico] => CORREIOS - PAC
            [preco] => 102.3
            [prazo] => 5
            [mensagem] => 
        )

    [1] => Array
        (
            [servico] => CORREIOS - SEDEX
            [preco] => 126.5
            [prazo] => 2
            [mensagem] => 
        )

    [2] => Array
        (
            [servico] => Vipex
            [preco] => 37.14
            [prazo] => 3
            [mensagem] => Frete para Santa Cruz do Sul/RS
        )

    [3] => Array
        (
            [servico] => TNT
            [preco] => 61.14
            [prazo] => 4
            [mensagem] => Frete para Santa Cruz do Sul
        )

)
```
