<?php

/**
 * DbConnect
 * 
 * Inicializa a conexão
 * ao BD usando PDO
 * 
 * @package  Logis
 * @version  0.4
 */

namespace Logis;

use PDO;

class DbConnect
{
	protected $db;

	public function __construct() {
		$host = 'mysql.decorarminhacasa.com.br';
		$name = 'decorarminhaca01';
		$user = 'decorarminhaca01';
		$pass = 'D3C0rar';

		try {
			$db = new PDO("mysql:host=" . $host . ";dbname=" . $name, $user, $pass);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->exec('SET NAMES utf8');

			return $db;
		} catch(PDOException $e) {
			die("Erro ao conectar: " . $e->getMessage());
		}
	}
}
