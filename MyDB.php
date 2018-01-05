<?php
/**
 * mysqliのラッパークラス
 * トランザクションのネストはしない前提で作ってます。
 * 文字コードも全部統一してる前提
 * @author a-hasegawa
 *
 */
Class MyDB {
	private $host = null;
	private $port = null;
	private $dbname = null;
	private $userId = null;
	private $password = null;
	private $charset = null;
	private $mysqli = null;

	/**
	 * インスタンス生成時はDB情報を持つだけ。
	 * @param String $host
	 * @param String $port
	 * @param String $dbname
	 * @param String $userId
	 * @param String $passwd
	 */
	public function  __construct($host, $port, $dbname, $userId, $passwd, $charset = 'utf8'){
		if(!$host || !$port || !$port || !$dbname || !$userId || !$passwd)return null;

		$this->host = $host;
		$this->port = $port;
		$this->dbname = $dbname;
		$this->userId = $userId;
		$this->password = $passwd;
		$this->charset = $charset;
	}

	//インスタンス消滅時にコネクションが残ってたらクローズする。
	public function __destruct(){
		$this->close();
	}

	/**
	 * オートコミットするか否か。
	 * @param bool $bool
	 */
	public function setAutoCommit($bool){
		$this->prepareConnection();
		$this->mysqli->autocommit($bool);
	}

	public function getRowNum($sql, $params=false){
		$countSql = "select count(1) as cnt from (".$sql.") as counttable";
		return $this->getValue($countSql, $params);
	}

	/**
	 * SQLを実行して結果を返却します。<br>
	 * @param String $sql
	 * @param Array $args
	 * @return multitype
	 */
	public  function executeQuery($sql, $params=false, $fetchClass = false){

		//引数やSQLをMySQLi用に変換
		$this->convertSqlParams($sql, $params);

		//パラメータタイプを取得
		$type = $this->getType($params);

		//コネクションが無ければ作る。
		$this->prepareConnection();
		
		$ret = array();

		$stmt = $this->mysqli->stmt_init();

		$tmp = array();
		if($params){
			foreach($params as $key => $value) $tmp[$key] = &$params[$key];
			array_unshift($tmp, $type);
		}

		if($stmt->prepare($sql)){
			//パラメータ割り当て
			if(is_array($tmp) && count($tmp) > 1){
				call_user_func_array(array($stmt, 'bind_param'), $tmp);
			}
			//クエリ実行
			$stmt->execute();
			if(!empty($stmt->error)){
				throw new Exception($stmt->error);
			}
			if($result = $stmt->get_result()){
				if($fetchClass){
					while ($row = $result->fetch_object($fetchClass)){
						array_push($ret, $row);
					}
				}else{
					while ($row = $result->fetch_array(MYSQLI_ASSOC)){
						array_push($ret, $row);
					}
				}
				$result->free(); //結果データ解放
			}
			$stmt->close();
		}else{
			if(!empty($stmt->error)){
				throw new Exception($stmt->error);
			}
		}

		return $ret;
	}

	/**
	 * SQLを実行して結果を返却します。<br>
	 * @param String $sql SQL本文
	 * @param Array $args パラメータ配列
	 * @return 更新件数
	 */
	public  function executeUpdate($sql, $params=false){

		//引数やSQLをMySQLi用に変換
		$this->convertSqlParams($sql, $params);
		//パラメータタイプを取得
		$type = $this->getType($params);

		$this->prepareConnection();

		$ret = false;

		$stmt = $this->mysqli->stmt_init();

		$tmp = array();
		if($params){
			foreach($params as $key => $value) $tmp[$key] = &$params[$key];
			array_unshift($tmp, $type);
		}

		if($stmt->prepare($sql)){
			if(is_array($tmp) && count($tmp) > 1){
				call_user_func_array(array($stmt, 'bind_param'), $tmp);
			};
			//クエリ実行
			$stmt->execute();
			if(!empty($stmt->error)){
				throw new Exception ( "MySQL error {$stmt->error} <br> Query:<br> {$sql}", $stmt->errno );
			}
			$result = $stmt->get_result();

			$ret = $this->mysqli->affected_rows;
			$stmt->close();
		}else{
			if(!empty($stmt->error)){
				throw new Exception($stmt->error);
			}
		}

		return $ret;
	}

	/**
	 * クエリの実行結果(1行)を単一の指定オブジェクトとして取得します。
	 * @param unknown $sql クエリ
	 * @param string $params パラメータ
	 * @param string $fetchClass 取得クラス
	 * @return Ambigous <boolean, multitype>
	 */
	function getObject($sql, $params=false, $fetchClass = false){
		$queryResult = $this->executeQuery($sql,$params,$fetchClass);
		if(count($queryResult) > 1){
		}
		return empty($queryResult)? false : $queryResult[0];
	}

	/**
	 * データを一つだけ取るようなSQLで使用する。
	 * @param String $sql
	 * @param Array $args
	 * @return Ambigous <NULL, unknown>
	 */
	function getValue($sql, $params = false) {
		//引数やSQLをMySQLi用に変換
		$this->convertSqlParams($sql, $params);
		//パラメータタイプを取得
		$type = $this->getType($params);

		//コネクションが無ければ作る。
		$this->prepareConnection();

		$ret = array();

		$stmt = $this->mysqli->stmt_init();

		$tmp = array();
		if($params){
			foreach($params as $key => $value) $tmp[$key] = &$params[$key];
			array_unshift($tmp, $type);
		};

		if($stmt->prepare($sql)){
			if(is_array($tmp) && count($tmp) > 1){
				call_user_func_array(array($stmt, 'bind_param'), $tmp);
			};
			$stmt->execute();
			if(!empty($stmt->error)){
				throw new Exception($stmt->error);
			}
			$result = $stmt->get_result();
			$value = $result->fetch_array(MYSQLI_NUM);
			$stmt->close();
			$result->free(); //結果データ解放
		}

		return is_array($value) ? $value[0] : null;
	}

	/**
	 * コミットします。
	 * @return resource
	 */
	public function commit(){
		$this->mysqli->commit();

	}

	/**
	 * ロールバックします。
	 * @return resource
	 */
	public function rollback(){
		$this->mysqli->rollback();

	}

	/**
	 * コネクションがあったら閉じる
	 * @throws Exception
	 */
	public function close(){
		if(!is_null($this->mysqli)){
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}

	/**
	 * DBを使う準備をする。
	 */
	private function prepareConnection(){
		if(!is_null($this->mysqli))return;
		try{
			$this->mysqli = new mysqli($this->host, $this->userId, $this->password, $this->dbname, $this->port);

			/* 接続状況をチェックします */
			if ($this->mysqli->connect_errno){
				throw new Exception("DB接続に失敗: ".$this->mysqli->connect_error);
			}

			//文字コードのセット
			$this->mysqli->set_charset($this->charset);

		}catch(Exception $e){
			$this->close();
			throw new Exception($e->getMessage());
		}
		return true;
	}

	/**
	 * SQL文字列のパラメータ置換を行う。
	 * 例）
	 *     $sql="select * from hoge where tomato = @tomato";<br>
	 *     $args = array("tomato"=>"akai");<br>
	 *     返却値："select * from hoge where tomato = 'akai'"
	 *
	 * @param String $sql
	 * @param Array $args
	 */
	private function createSQL($sql, $args=false){
		if(is_null($args) || !is_array($args)){
			return $sql;
		}
		foreach ($args as $key => $value) {
			$qVal = $this->quote_smart($value);
			$sql = str_replace("@".$key, $qVal, $sql);
		}
		return $sql;
	}

	/**
	 * パラメータ文字列をシングルクォートでくくる
	 * @param unknown_type $value
	 */
	private function quote_smart($value){
		// 文字列をシングルクォートでくくる
		if(is_null($value)){
			return "null";
		}
		if (is_string($value)) {
		return "'" . mysql_real_escape_string($value) . "'";
		}
		if(!is_numeric($value)){
		return "'" . mysql_real_escape_string(strval($value)) . "'";
		}
		return $value;
	}

	/**
	 * ステートメントで利用するパラメータに対するタイプを取得する。
	 * @param mixed $value
	 * @return string
	 */
	private function getType($params){
		$type = "";
		if(!$params)return "";
		foreach ($params as $key => $value){
			if (is_string($value)) {
				$type .= "s";
			}else if(is_double($value)){
				$type .= "d";
			}else if(is_numeric($value)){
				$type .= "i";
			}else if(is_bool($value)){
				$type .= "s";
			}else{
				$type .= "s";
			}
		}
		return $type;
	}

	/**
	 * パラメータ文字列をmysqliで使えるよう変換
	 * @param array $sql
	 * @param array $params
	 */
	private function convertSqlParams(&$sql, &$params){
		global $logger;
		if(!$params)return;
		$pattern = '/@{([a-zA-Z0-9_.]+)}/';
		$match_num = preg_match_all($pattern, $sql, $matches);
		$repAry = array();
		for($i = 0; $i < $match_num; $i++){
			array_push($repAry, $params[$matches[1][$i]]);
		}
		for($i = 0; $i < $match_num; $i++){
			$sql = str_replace($matches[0][$i], "?", $sql);
		}
		$params = $repAry;
		$logger->debug("変換後SQL:".$sql);
		$logger->debug($params);
	}


	/**
	 * 指定のテーブルデータを切り捨ててAI値を1にする。
	 * @param string $table
	 */
	private function truncate($table){
		$this->mysqli->query("LOCK TABLES {$table} WRITE;");
		$this->mysqli->query("DELETE FROM {$table};");
		$this->mysqli->query("ALTER TABLE {$table} auto_increment=1;");
		$this->mysqli->query("UNLOCK TABLES;");
	}

	/**
	 * 最後に挿入したIDを取得する。
	 */
	public function getLastInsertedId(){
		return $this->mysqli->insert_id;
	}
}
