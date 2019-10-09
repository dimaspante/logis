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
		if (!is_array($options)) {
			$this->logaErro('Opções de inicialização não informadas como array.');
		}
		if (!$options['destino']) {
			$this->logaErro('CEP de destino não informado.');
		}
		if (!$options['origem']) {
			$this->logaErro('CEP de origem não informado.');
		}
		if (!$options['valor']) {
			$this->logaErro('Valor dos itens não informado.');
		}
		if (!$options['peso']) {
			$this->logaErro('Peso total não informado.');
		}
		if (!is_array($options['dimensoes']) || !$options['dimensoes']) {
			$this->logaErro('Dimensões não informadas ou fora do formato array.');
		}

  	$this->destino = $options['destino'];
  	$this->origem = $options['origem'];
  	$this->valor = $options['valor'];
  	$this->peso = $options['peso'];
  	$this->itens = (int)$options['itens'];
  	$this->dimensoes = (array)$options['dimensoes'];
  	$this->bd = $this->conectaBD();
	}

	/**
	 * Exibição de mensagens de erro e finalização da execução
	 */
	private function logaErro($message, $log=null) {
		if ($log) {
			error_log($message." - ".$log);
			return true;
		}

		echo '<pre>[ ! ] '.$message.'</pre>';
		return false;
	}

	/**
	 * Conexão ao banco com PDO
	 */
	private function conectaBD(){
		require_once 'defines.php';

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

		return array('a'=>$altura, 'l'=>$largura, 'c'=>$comprimento);
	}

	private function calcularFreteVip() {
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
			$raws = $stmt->fetchAll();
		} catch (PDOException $e) {
			$this->logaErro("Erro ao buscar transportadoras: " . $e->getMessage());
		}

		$cubagem = $this->cubagem;

		//o limite minimo que temos na base da VIP é 0.30
		if ($cubagem < "0.30") $cubagem = "0.30";

		$arr = array();

		foreach ($raws as $raw) {
			try {
				$base = substr($this->destino,0,3);
				$base = str_pad($base, 8, 0);

				//busca a praca de destino de acordo com o cep, em cada transportadora
				$stmt = $this->bd->prepare("SELECT p.praca, p.nome_cidade, p.uf_cidade, p.cobra_adicional, p.prazo, v.valor FROM logis_praca p INNER JOIN logis_valor v ON p.praca = v.praca WHERE p.cep_inicial BETWEEN :a AND :c AND v.limite_cubagem <= :x AND p.id_transportadora = :t ORDER BY v.id_valor DESC LIMIT 1");
				//echo "SELECT p.praca, p.nome_cidade, p.uf_cidade, p.cobra_adicional, p.prazo, v.valor FROM logis_praca p INNER JOIN logis_valor v ON p.praca = v.praca WHERE p.cep_inicial BETWEEN $base AND $this->destino AND v.limite_cubagem <= $cubagem AND p.id_transportadora = ".$raw['id_transportadora']." ORDER BY v.id_valor DESC LIMIT 1";
				$stmt->bindParam(":a", $base);
				$stmt->bindParam(":c", $this->destino);
				$stmt->bindParam(":x", $cubagem);
				$stmt->bindParam(":t", $raw['id_transportadora']);
				$stmt->execute();
				$row = $stmt->fetch();

				if ($row['valor']) {
					$valor = $row['valor'];
					/*
					 * Se "cobra_adicional" for true (1), existe uma
					 * Taxa de Restrição de Trânsito (TRT),
					 * que deve adicionar 25% no total do frete
					 */
					if ($row['cobra_adicional']){
						$valor += (0.25 * $valor);
					}

					return array(
		    		'servico' => (string)$raw['nome_transportadora'],
		    		'preco' => (float)round($valor,2),
		    		'prazo' => (int)$row['prazo'],
		    		'mensagem' => 'Frete para ' . $row['nome_cidade'] . '/' . $row['uf_cidade']
		    	);
				}
			} catch (PDOException $e) {
				$this->logaErro("Erro ao buscar valores: " . $e->getMessage());
			}
		}
	}

	private function calcularFreteTNT() {
		/*************
		 *
		 * WS TNT
		 *
		 * Faz a geração do XML necessário e a lê com cURL pois a antiga
		 * leitura com SoapClient gera erros de java no servidor (ns0:Server)
		 * 
		 * cepOrigem deve ser da própria filial da tnt, não da empresa
		 * nrIdentifClienteDest pode ser um CPF default
		 * tpFrete deve ser C ou F (C - CIF, pago à TNT pelo remetente; F - FOB, pago no destino | Padrão C)
		 * tpServico deve ser RNC ou ANC (RNC - Rodoviario Nacional Convencional; ANC - Aéreo Nacional | Padrão RNC)
		 * tpSituacaoTributariaRemetente normalmente CO, ME ou NC (CO - Contribuinte; ME - Microempresa; NC - Não contribuinte | Padrão CO)
		 * tpSituacaoTributariaDestinatario normalmente CO, ME ou NC (CO - Contribuinte; ME - Microempresa; NC - Não contribuinte | Padrão NC)
		 * 
		 *************/

		$url = 'http://ws.tntbrasil.com.br:81/tntws/CalculoFrete?wsdl';

		$xml = '
		<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.calculoFrete.mercurio.com" xmlns:mod="http://model.vendas.lms.mercurio.com">
		   <soapenv:Header/>
		   <soapenv:Body>
		      <ser:calculaFrete>
		         <ser:in0>
		            <mod:login>lojadecorar@gmail.com</mod:login>
		            <mod:senha></mod:senha>
		            <mod:nrIdentifClienteRem>10651777000170</mod:nrIdentifClienteRem>
		            <mod:nrIdentifClienteDest>00433856050</mod:nrIdentifClienteDest>
		            <mod:tpFrete>C</mod:tpFrete>
		            <mod:tpServico>RNC</mod:tpServico>
		            <mod:cepOrigem>96822700</mod:cepOrigem>
		            <mod:cepDestino>'.$this->destino.'</mod:cepDestino>
		            <mod:vlMercadoria>'.$this->valor.'</mod:vlMercadoria>
		            <mod:psReal>'.number_format($this->peso, 3).'</mod:psReal>
		            <mod:nrInscricaoEstadualRemetente>1080161969</mod:nrInscricaoEstadualRemetente>
		            <mod:nrInscricaoEstadualDestinatario></mod:nrInscricaoEstadualDestinatario>
		            <mod:tpSituacaoTributariaRemetente>CO</mod:tpSituacaoTributariaRemetente>
		            <mod:tpSituacaoTributariaDestinatario>NC</mod:tpSituacaoTributariaDestinatario>
		            <mod:cdDivisaoCliente>1</mod:cdDivisaoCliente>
		            <mod:tpPessoaRemetente>J</mod:tpPessoaRemetente>
		            <mod:tpPessoaDestinatario>F</mod:tpPessoaDestinatario>
		         </ser:in0>
		      </ser:calculaFrete>
		   </soapenv:Body>
		</soapenv:Envelope>';

		$headers = array('Content-Type: text/xml; charset=utf-8', 'Content-Length: '.strlen($xml));

		try {
			$soap = curl_init();
			curl_setopt($soap, CURLOPT_URL, $url);
			curl_setopt($soap, CURLOPT_POST, true); 
			curl_setopt($soap, CURLOPT_POSTFIELDS, $xml); 
			curl_setopt($soap, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($soap, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($soap, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($soap, CURLOPT_SSL_VERIFYHOST, false);

			$result = curl_exec($soap);
			curl_close($soap);

			$dom = new \DOMDocument('1.0', 'utf-8');
			$dom->preserveWhiteSpace = false;
			$dom->loadXML($result);

			$xpath = new \DOMXpath($dom);
			$prazos = $xpath->query("//*[name()='prazoEntrega']");
			$valores = $xpath->query("//*[name()='vlTotalFrete']");

			foreach ($prazos as $n) {
			  $prazo = $n->textContent;
			  break;
			}
			foreach ($valores as $v) {
			  $valor = $v->textContent;
			  break;
			}

			return array(
    		'servico' => 'TNT',
    		'preco' => (float)$valor,
    		'prazo' => (int)$prazo,
    		'mensagem' => 'Webservice SOAP 1.1'
    	);
    } catch (\Throwable $e) {
    	$this->logaErro("Erro ao pesquisar webservice: " . $e->getMessage());
    }
	}

	private function calcularFreteCorreios() {
		/*************
		 *
		 * WS CORREIOS
		 *
		 * Somente calcula caso o peso total
		 * seja menor que 30kg, usando XML
		 * 
		 *************/
		if ($this->peso < 30) {
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
		    if ($srv->Erro == 0) {
		    	$arr[] = array(
		    		'servico' => (string)($srv->Codigo == '40010' ? 'SEDEX' : 'PAC'),
		    		'preco' => (float)($srv->Valor+(0.1*$srv->Valor)), //valor mais 10%
		    		'prazo' => (int)$srv->PrazoEntrega,
		    		'mensagem' => null
		    	);
		    }
			}

			return $arr;
		}
	}

	public function calcularFrete() {
		$data = $this->analisaDimensoes();

		$cubagem = $data['a'] * $data['l'] * $data['c'] * $this->itens;
		$this->cubagem = number_format($cubagem, 2);

		//usamos o fator de cubagem rodoviario (300kg)
		$peso_cubado = ($this->cubagem * 300);

		if ($peso_cubado > $this->peso) {
			//$this->peso = $peso_cubado;
		}

		$retorno = array();

		$retorno[] = $this->calcularFreteVip();
		$retorno[] = $this->calcularFreteTNT();
		$retorno[] = $this->calcularFreteCorreios();

		return $retorno;
	}
}
