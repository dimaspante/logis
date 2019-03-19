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
		$valor = $options['valor'];
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
		if (!$valor) {
			$this->logaErro('Valor dos itens não informado.');
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
    	$this->valor = $valor;
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
		require_once 'defines.php'

		try {
			$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
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
	 * Faz o cálculo usando as bases/WS
	 * disponíveis atualmente:
	 * 
	 * Correios (WS externo)
	 * TNT (WS externo)
	 * Vipex (base local)
	 */
	public function calcularFrete() {
		$data = $this->analisaDimensoes();

		$cubagem = $data['a'] * $data['l'] * $data['c'];
		if ($this->itens) $cubagem *= $this->itens;

		$retorno = array();

		/*************
		 *
		 * WS CORREIOS
		 *
		 * Somente calcula caso o peso total
		 * seja menor que 30kg, usando XML
		 * 
		 *************/
		if($this->peso < 30){
			$altura 	 = ($data['a'] < 12) ? 12 : $data['a']; //o minimo é 12cm
			$largura 	 = ($data['l'] < 12) ? 12 : $data['l']; //o minimo é 12cm
			$comprimento = ($data['c'] < 16) ? 16 : $data['c']; //o minimo é 16cm

			$servicos = array();

			if ($comprimento <= 105 && $largura <= 105) $servicos[] = 41106; //pac
			if ($comprimento <= 60 && $largura <= 60) $servicos[] = 40010; //sedex

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
		    
		    foreach ($result->cServico as $srv) {
			    if($srv->Erro == 0){
			    	$retorno[] = [
			    		'servico' => (string)($srv->Codigo == '40010' ? 'SEDEX' : 'PAC'),
			    		'preco' => (float)($srv->Valor+(0.1*$srv->Valor)), //valor mais 10%
			    		'prazo' => (int)$srv->PrazoEntrega,
			    		'mensagem' => null
			    	];
			    }
			}
		}

		/*************
		 *
		 * WS TNT
		 *
		 * Utiliza SOAP 1.1
		 * para buscar os dados
		 * 
		 * campo cepOrigem deve ser da própria filial da tnt, não da empresa
		 * tpFrete deve ser C ou F (C - CIF, pago à TNT pelo remetente; F - FOB, pago no destino | Padrão C)
		 * tpServico deve ser RNC ou ANC (RNC - Rodoviario Nacional Convencional; ANC - Aéreo Nacional | Padrão RNC)
		 * tpSituacaoTributariaRemetente normalmente CO, ME ou NC (CO - Contribuinte; ME - Microempresa; NC - Não contribuinte | Padrão CO)
		 * tpSituacaoTributariaDestinatario normalmente CO, ME ou NC (CO - Contribuinte; ME - Microempresa; NC - Não contribuinte | Padrão NC)
		 * 
		 *************/
		$wsdlURL = 'http://ws.tntbrasil.com.br/servicos/CalculoFrete?wsdl';

		$cliente = new \SoapClient($wsdlURL, array(
			'exceptions' => true,
			'cache_wsdl' => WSDL_CACHE_NONE,
			'trace' => true
		));

		$funcao = 'calculaFrete';

		$parametros = array(
			$funcao =>
				array('in0' => 
					array(
						'cdDivisaoCliente'                 => '1',
						'login'                            => 'lojadecorar@gmail.com',
						'senha'                            => '',
						'cepOrigem'                        => '96822700',
						'cepDestino'                       => $this->destino,
						'nrIdentifClienteRem'              => '10651777000170',
						'nrInscricaoEstadualRemetente'     => '1080161969',
						'nrIdentifClienteDest'             => '00433856050',
						'nrInscricaoEstadualDestinatario'  => '',
						'vlMercadoria'                     => $this->valor,
						'psReal'                           => number_format($this->peso, 3, '.', ''),
						'tpFrete'                          => 'C',
						'tpPessoaRemetente'                => 'J',
						'tpSituacaoTributariaRemetente'    => 'CO',
						'tpPessoaDestinatario'             => 'F',
						'tpSituacaoTributariaDestinatario' => 'NC',
						'tpServico'                        => 'RNC'
					)	
				)
		);

		try{
		    $client = $cliente->__soapCall($funcao, $parametros);
		    $result = $client->out;

		    $valor = 0;
		    $prazo = 0;

		    if((array)$result->errorList){
		    	if(isset($result->errorList->string)){
		    		if(is_array($result->errorList->string)) $erro = '<p>Erro: ' . implode('</p><p>Erro: ', $result->errorList->string) . '</p>';
		    		else $erro = $result->errorList->string;

		    		$this->logaErro("Erro na consulta TNT: " . $erro);
		    	}
		    }else{
				foreach($result->parcelas->ParcelasFreteWebService as $parcela){
					$valor += $parcela->vlParcela;
				}

				$valor -= $result->vlDesconto; //se tiver desconto, subtrai

				$prazo = $result->prazoEntrega;

				$cidade = $result->nmMunicipioDestino;

				$retorno[] = [
		    		'servico' => (string)'TNT',
		    		'preco' => (float)$valor,
		    		'prazo' => (int)$prazo,
		    		'mensagem' => 'Frete para ' . $cidade
		    	];
			}
		}catch(SoapFault $fault){
			$this->logaErro("Erro ao conectar TNT: " . $fault->getMessage());
		}

		/*************
		 *
		 * WS VIPex
		 *
		 * Utiliza PDO para buscar
		 * os dados localmente
		 * 
		 *************/
		try {
			$stmt = $this->bd->prepare("SELECT id_transportadora, nome_transportadora FROM logis_transportadora");
			$stmt->execute();
		} catch (PDOException $e) {
			$this->logaErro("Erro ao buscar transportadoras: " . $e->getMessage());
		}

		while ($raw = $stmt->fetch(PDO::FETCH_ASSOC)) {
			try {
				$base = substr($this->destino,0,3);
				$base = (int)str_pad($base, 8, 0);

				//busca a praca de destino de acordo com o cep, em cada transportadora
				$stmt = $this->bd->prepare("SELECT p.praca, p.nome_cidade, p.uf_cidade, p.cobra_adicional, p.prazo, v.valor FROM logis_praca p INNER JOIN logis_valor v ON p.praca = v.praca WHERE p.cep_inicial BETWEEN :a AND :c AND v.limite_cubagem <= :x AND p.id_transportadora = :t LIMIT 1");
				$stmt->bindParam(":a", $base);
				$stmt->bindParam(":c", $this->destino);
				$stmt->bindParam(":x", $cubagem);
				$stmt->bindParam(":t", $raw['id_transportadora']);
				$stmt->execute();
			} catch (PDOException $e) {
				$this->logaErro("Erro ao buscar valores: " . $e->getMessage());
			}

			if ($stmt->rowCount()) {
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
					$valor = $row['valor'];
					/*
					 * Se "cobra_adicional" for true (1), existe uma
					 * Taxa de Restrição de Trânsito (TRT),
					 * que deve adicionar 25% no total do frete
					 */
					if ($row['cobra_adicional']){
						$valor += (0.25 * $valor);
					}

					$retorno[] = [
			    		'servico' => (string)$raw['nome_transportadora'],
			    		'preco' => (float)round($valor,2),
			    		'prazo' => (int)$row['prazo'],
			    		'mensagem' => 'Frete para ' . $row['nome_cidade'] . '/' . $row['uf_cidade']
			    	];
				}
			}
		}

		return $retorno;
	}
}
