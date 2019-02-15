## OPÇÕES

O Logis aceita inicializar através de um array, com as seguintes opções:

**destino** (int) O cep de destino (cliente)

**origem** (int) O cep de origem (empresa)

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
- mensagem (string) Uma mensagem referente ao resultado (em caso de erro, retorna nulo - Correios idem)

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

## RETORNO

```php
Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [servico] => PAC
                    [preco] => 217.8
                    [prazo] => 13
                    [mensagem] => null
                )

            [1] => Array
                (
                    [servico] => SEDEX
                    [preco] => 441.1
                    [prazo] => 7
                    [mensagem] => null
                )

        )

    [1] => Array
        (
            [0] => Array
                (
                    [servico] => Vipex
                    [preco] => 0
                    [prazo] => 0
                    [mensagem] => null
                )

        )

)
```
