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

require_once 'DbConnect.php';

class Logis
{
	public $options;

	function __construct($options = array()) {
		$densidade = 100; //valor padrão da Vipex
		$destino = (int)$options['destino'];
		$origem = (int)$options['origem'];
		$peso = $options['peso'];
		$itens = (int)$options['itens'];
		$dimensoes = (array)$options['dimensoes'];

		/* validação de erros */
		if (!is_array($options)) {
			$this->logError('Opções de inicialização não informadas como array.');
			return false;
		}
		if (!$destino) {
			$this->logError('CEP de destino não informado.');
			return false;
		}
		if (!$origem) {
			$this->logError('CEP de origem não informado.');
			return false;
		}
		if (!$peso) {
			$this->logError('Peso total não informado.');
			return false;
		}
		if (!$dimensoes) {
			$this->logError('Dimensões não informadas.');
			return false;
		}
		if (!is_array($dimensoes) || empty($dimensoes)) {
			$this->logError('Dimensões não informadas ou fora do formato array.');
			return false;
		}

    	$this->destino = $destino;
    	$this->origem = $origem;
    	$this->peso = $peso;
    	$this->itens = $itens;
    	$this->dimensoes = $dimensoes;
    	$this->densidade = $densidade;
    	$this->db = new DbConnect();

    	$cubagem = $this->calcularCubagem();

    	return $cubagem;
	}

	public function logError($message, $log=null) {
		echo '<pre>[ ! ] '.$message.'</pre>';
		if($log) error_log($message." - ".$log);
	}

	public function calcularCubagem() {
		$data = $this->dimensoes;

		if (!$data['altura']) {
			$this->logError('Dimensões: altura não informada.');
			return false;
		}
		if (!$data['largura']) {
			$this->logError('Dimensões: largura não informada.');
			return false;
		}
		if (!$data['comprimento']) {
			$this->logError('Dimensões: comprimento não informado.');
			return false;
		}

		$altura = str_replace(',', '.', $data['altura']);
		$largura = str_replace(',', '.', $data['largura']);
		$comprimento = str_replace(',', '.', $data['comprimento']);

		$cubagem = $altura * $largura * $comprimento;
		if ($this->itens) $cubagem *= $this->itens;

		/**
		 * calcula a densidade do pacote (inativo)
		 * 
		 * $densidade = 0;
		 * 
		 * if ($this->densidade) {
		 * 	$densidade = $cubagem * $this->densidade;
		 * }
		*/

		if ($cubagem) {
			return $this->freteCubado($cubagem);
		} else {
			$this->logError('Peso cubado não pode ser calculado ('.$cubagem.').');
			return false;
		}
	}

	public function freteCubado($peso_cubado) {
		if (!$peso_cubado) return;

		return $peso_cubado;

		$pdo = $this->db;

		$sql = "SELECT x FROM y WHERE z = :p";
		$stmt = $pdo->prepare($sql);

		if (!$stmt->bind_param(":p", $peso_cubado)) {
			$this->logError("Erro ao preparar a consulta: ".$stmt->errorInfo());
			return false;
		}

		if (!$stmt->execute()) {
			$this->logError("Erro ao executar a consulta: ".$stmt->errorInfo());
			return false;
		}

		$row = $stmt->fetchAll();

		$valor = $row['valor'];

		if (version_compare(phpversion(), '7.1', '>=')) {
    		return $valor ?? 0;
    	} else {
    		return (!empty($valor)) ? $valor : 0;
    	}
	}
}
