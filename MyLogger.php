<?php
/**
 * ログ出力を頑張るクラス。（共通）
 * @author a-hasegawa
 *
 */
Class MyLogger{
	//ログレベル定義
	const LOG_LEVEL_TRACE = 0;
	const LOG_LEVEL_DEBUG = 10;
	const LOG_LEVEL_INFO = 20;
	const LOG_LEVEL_WARN = 30;
	const LOG_LEVEL_ERROR = 40;
	const LOG_LEVEL_FATAL = 50;

	//クラス固有の変数定義
	private $dateFormat;
	private $loglevel;
	private $logDirctory;
	private $prefix;
	private $logEnabled = true;

	/**
	 * ロガーのインスタンスを生成
	 * @param string $logDirctory
	 * @param string $loglevel
	 * @param string $dateFormat
	 */
	public function __construct($logDirctory, $loglevel = 0, $logPrefix="log_", $dateFormat = "Y/m/d H:i:s"){
		$this->logDirctory = $logDirctory;
		$this->loglevel = $loglevel;
		$this->dateFormat = $dateFormat;
		$this->prefix = $logPrefix;
	}

	/**
	 * ログの出力可否を設定
	 * @param bool $bool
	 */
	public function logEnabled($bool){
		if(is_bool($bool))
			$this->logEnabled = $bool;
	}

	/**
	 * 精密調査ログを出力します。
	 * @param string $logMessage
	 */
	public function trace($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_TRACE, "TRACE", $logMessage);
	}

	/**
	 * 開発調査ログを出力します。
	 * @param unknown_type $logMessage
	 */
	public function debug($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_DEBUG, "DEBUG", $logMessage);
	}

	/**
	 * 通常ログを出力します。
	 * @param unknown_type $logMessage
	 */
	public function info($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_INFO, "INFO", $logMessage);
	}

	/**
	 * 警告ログを出力します。
	 * @param string $logMessage
	 */
	public function warn($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_WARN, "WARN", $logMessage);
	}

	/**
	 * エラーログを出力します。
	 * @param string $logMessage
	 */
	public function error($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_ERROR, "ERROR", $logMessage);
	}

	/**
	 * 致命的ログを出力します。
	 * @param string $logMessage
	 */
	public function fatal($logMessage) {
		$this->log(MyLogger::LOG_LEVEL_FATAL, "FATAL", $logMessage);}

	/**
	 * クラスのログレベルが引数のレベル以上なら出力する。
	 * @param int $level 出力するレベル下限
	 * @param string $label ログレベルのラベル
	 * @param string $str 出力する文字列
	 */
	private function log($level,$label, $str){
		//出力可否チェック
		if(!$this->logEnabled || !is_numeric($level) || intval($level) < $this->loglevel)
			return;

		//ログ出力
		$output = sprintf("[%s][%s][%s][%s]%s"
				,date($this->dateFormat)
				,$label
				,isset($_SERVER["REMOTE_ADDR"])?$_SERVER["REMOTE_ADDR"]:""
				,session_id()
				,var_export($str, true)
				);
		$path = $this->logDirctory . DIRECTORY_SEPARATOR . $this->prefix . date("Ymd") . '.log';
		$fp = fopen($path, 'a');
		fwrite($fp, "{$output}\n");
		fclose($fp);
		return;
	}

	/**
	 * デバッグレベルを数値で取得
	 * @return int
	 */
	public function getDebugLevel(){
		return intval($this->loglevel);
	}
}
