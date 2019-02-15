<?php

/**
 * Logis
 * 
 * Cria a chamada ao webservice e
 * retorna os cálculos de peso e valor
 * 
 * @package  Logis
 * @version  0.4
 */

namespace Logis;

use PDO;

class Logis
{
	public $options;

	/**
	 * Construtor da classe de logística
	 */
	function __construct($options = array()) {
		$destino = (int)$options['destino'];
		$origem = (int)$options['origem'];
		$peso = $options['peso'];
		$itens = (int)$options['itens'];
		$dimensoes = (array)$options['dimensoes'];

		if (!is_array($options)) {
			$this->logaErro('Opções de inicialização não informadas como array.');
		}
		if (!$destino) {
			$this->logaErro('CEP de destino não informado.');
		}
		if (!$origem) {
			$this->logaErro('CEP de origem não informado.');
		}
		if (!$peso) {
			$this->logaErro('Peso total não informado.');
		}
		if (!$dimensoes) {
			$this->logaErro('Dimensões não informadas.');
		}
		if (!is_array($dimensoes) || empty($dimensoes)) {
			$this->logaErro('Dimensões não informadas ou fora do formato array.');
		}

    	$this->destino = $destino;
    	$this->origem = $origem;
    	$this->peso = $peso;
    	$this->itens = $itens;
    	$this->dimensoes = $dimensoes;
    	$this->bd = $this->conectaBD();
	}

	/**
	 * Exibição de mensagens de erro e finalização da execução
	 */
	private function logaErro($message, $log=null) {
		echo '<pre>[ ! ] '.$message.'</pre>';
		if($log) error_log($message." - ".$log);

		return false;
	}

	/**
	 * Conexão ao banco com PDO
	 */
	private function conectaBD(){
		$host = 'mysql.decorarminhacasa.com.br';
		$name = 'decorarminhaca';
		$user = 'decorarminhaca';
		$pass = 'D3C0rar';

		try {
			$pdo = new PDO("mysql:host=" . $host . ";dbname=" . $name, $user, $pass);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			return $pdo;
		} catch(PDOException $e) {
			$this->logaErro("Erro ao conectar: " . $e->getMessage());
		}
	}

	/**
	 * Verificação e limpeza das strings de dimensões
	 */
	public function analisaDimensoes(){
		$data = $this->dimensoes;

		if (!$data['altura']) {
			$this->logaErro('Dimensões: altura não informada.');
		}
		if (!$data['largura']) {
			$this->logaErro('Dimensões: largura não informada.');
		}
		if (!$data['comprimento']) {
			$this->logaErro('Dimensões: comprimento não informado.');
		}

		$altura = str_replace(',', '.', $data['altura']);
		$largura = str_replace(',', '.', $data['largura']);
		$comprimento = str_replace(',', '.', $data['comprimento']);

		return ['a'=>$altura,'l'=>$largura,'c'=>$comprimento];
	}

	/**
	 * Cálculo usando a base dos correios (xml)
	 */
	public function calcularCorreios() {
		if ($this->peso > 30) return; //o limite dos correios é 30kg

		$data = $this->analisaDimensoes();

		$altura 	 = ($data['a'] < 12) ? 12 : $data['a']; //o minimo dos correios é 12cm
		$largura 	 = ($data['l'] < 12) ? 12 : $data['l']; //o minimo dos correios é 12cm
		$comprimento = ($data['c'] < 16) ? 16 : $data['c']; //o minimo dos correios é 16cm

		$servicos = array();

		if ($comprimento <= 105 && $largura <= 105) $servicos[] = 41106; //pac
		if ($comprimento <= 60 && $largura <= 60) $servicos[] = 40010; //sedex

		//cria o link dos correios
		$url = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx';

		$qry['nCdServico'] = implode(',',$servicos);
		$qry['nCdFormato'] = '1';
		$qry['nCdEmpresa'] = '';
		$qry['sDsSenha'] = '';
		$qry['sCepOrigem'] = $this->origem;
		$qry['sCepDestino'] = $this->destino;
		$qry['nVlPeso'] = $this->peso;
		$qry['nVlComprimento'] = $comprimento;
		$qry['nVlAltura'] = $altura;
		$qry['nVlLargura'] = $largura;
		$qry['nVlDiametro'] = '0';
		$qry['nVlValorDeclarado'] = '200';
		$qry['sCdAvisoRecebimento'] = 'n';
		$qry['sCdMaoPropria'] = 'n';
		$qry['StrRetorno'] = 'xml';
		$qry = http_build_query($qry);

	    $result = simplexml_load_file($url . '?' . $qry);

	    $retorno = array();
	    
	    foreach ($result->cServico as $srv) {
		    if($srv->Erro == 0){
		    	$retorno[] = [
		    		'servico' => (string)($srv->Codigo == '40010' ? 'SEDEX' : 'PAC'),
		    		'preco' => (float)($srv->Valor+(0.1*$srv->Valor)), //valor mais 10%
		    		'prazo' => (int)$srv->PrazoEntrega,
		    		'mensagem' => ''
		    	];
		    }
		}

		return $retorno;
	}

	/**
	 * Cálculo usando o banco local de transportadoras
	 */
	public function calcularTransportadoras() {
		$data = $this->analisaDimensoes();

		$cubagem = $data['a'] * $data['l'] * $data['c'];
		if ($this->itens) $cubagem *= $this->itens;

		if ($cubagem) {
			$retorno = array();

			//busca as transportadoras existentes
			try {
				$stmt = $this->bd->prepare("SELECT id_transportadora, nome_transportadora FROM logis_transportadora");
				$stmt->execute();
			} catch (PDOException $e) {
				$this->logaErro("Erro ao buscar transportadoras: " . $e->getMessage());
			}

			while ($raw = $stmt->fetch(PDO::FETCH_ASSOC)) {
				try {
					//busca a praca de destino de acordo com o cep, em cada transportadora
					
					$stmt = $this->bd->prepare("SELECT p.id_praca, p.nome_cidade, p.uf_cidade, p.valor_adicional, p.prazo, v.valor FROM logis_praca p INNER JOIN logis_valor v ON p.id_praca = v.id_praca WHERE p.cep_inicial >= :c AND p.cep_final <= :c AND p.id_transportadora = :t LIMIT 1");
					$stmt->bindParam(":c", $this->destino);
					$stmt->bindParam(":t", $raw['id_transportadora']);
					$stmt->execute();
				} catch (PDOException $e) {
					$this->logaErro("Erro ao buscar valores: " . $e->getMessage());
				}

				if ($stmt->fetchColumn() > 0) {
					while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
						$valor = $row['valor'];
						if ($row['valor_adicional']) $valor += $row['valor_adicional'];

						//adiciona 10% no valor
						$valor += (0.1*$valor);

						$retorno[] = [
				    		'servico' => (string)$raw['nome_transportadora'],
				    		'preco' => (float)$valor,
				    		'prazo' => (int)$row['prazo'],
				    		'mensagem' => 'Frete para ' . $row['nome_cidade'] . '/' . $row['uf_cidade']
				    	];
					}
				} else {
					$retorno[] = [
						'servico' => (string)$raw['nome_transportadora'],
						'preco' => 0,
						'prazo' => 0,
						'mensagem' => 'Seu CEP está fora da área de entrega. Solicite seu orçamento por e-mail.',
					];
				}
			}

			return $retorno;
		}

		return;
	}
}
