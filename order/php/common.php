<?php
session_start();

class common {

	private $mysqli;

	function __construct() {
		$this->restriction();
		$this->setConfigToENV();
		$this->iniMysqli();
	}

	function __destruct() {
		$this->mysqli->close();
	}

	/*
	 * base64文字列をバイナリデータに変換
	 *
	*/
	protected function base64ToBinary($img) {
		$img = preg_replace('/^.*?base64,/','', $img);
		$img = str_replace(' ', '+', $img);
		$img = base64_decode($img);
		return $img;
	}

	// select系値を返す
	protected function query($return_flag, $sql) {
		$data_col = array();
		$data_all = array();
		$result = $this->mysqli->query($sql);
		if(stripos($return_flag,"insert") !== false || stripos($return_flag,"update") !== false){
			return $result;
		}
		while ($row = $result->fetch_array(MYSQLI_BOTH)) {
			$data_col[] = $row[0];
			$data_all[] = $row;
		}
		if (empty($data_all)) {
			$this->response('ng', '');
		}
		if(stripos($return_flag,"single") !== false){
			return $data_col[0];
		}
		else if(stripos($return_flag,"row") !== false){
			return $data_all[0];
		}
		else if(stripos($return_flag,"col") !== false){
			return $data_col;
		}
		else if(stripos($return_flag,"all") !== false){
			return $data_all;
		}
	}

	// update
	protected function updateData($sql) {
		return $this->mysqli->query($sql);
	}

	/*
	 * メール送信
	 *
	*/
	protected function mailSend($mailData) {
		require_once 'mail.php';
		$mail = new mail();
		if( mail::create()
			->name($mailData['name'])
			->from($mailData['from'])
			->to($mailData['to'])
			->title($mailData['title'])
			->body($mailData['body'])
			->header("")
			->param("")
			->cc('')
			->bcc("")
			->files($mailData['files'])
			->send()
		){
			return true;
		}
		return false;
	}

	/*
	* レスポンスを返す
	*
	*/
	protected function response($status, $data){
		$arr = array(
			'status' => $status,
			'response' => $data
		);
		header('X-Content-Type-Options: nosniff');
		header('content-type: application/json; charset=utf-8');
		echo json_encode($arr);
		exit;
	}

	/*
	 * mysqliを初期化
	 *
	*/
	private function iniMysqli(){
		$this->mysqli = new mysqli($_ENV['db_host'], $_ENV['db_user'], $_ENV['db_pass'], $_ENV['db_source']);
		if (!$this->mysqli) {
			$this->response('ok', '失敗');
		}
		$this->mysqli->set_charset("utf8");
	}

	/*
	 * cgi用の設定ファイルをphpの$_ENV変数に配列で格納する
	 *
	*/
	private function setConfigToENV(){
		$config = file_get_contents('../../../config/config.pl');
		preg_match_all('/(\$)(\w*?)( .*?=.*?)("|\')(.*?)("|\')/s', $config, $matches, PREG_SET_ORDER);
		foreach ($matches as $matche) {
			$key = $matche[2];
			$value = $matche[5];
			$_ENV[$key] = $value;
		}
	}

	/*
	* アクセス制限
	*
	*/
	private function restriction(){
		$allow = true;
		// リクエスト許可ドメイン
		$allows = array(
			'debug.picake.jp',
			'picake.jp'
		);
		if (preg_match('/^(http|https)(:\/\/|:\/\/www.)(' . implode("|", $allows) . ')$/', $_SERVER['HTTP_ORIGIN'])) {
			header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
		} else {
			$allow = false;
		}
		// XHR通信のみ許可
		if ($_SERVER["HTTP_X_REQUESTED_WITH"] !== "XMLHttpRequest") {
			$allow = false;
		}
		// getリクエスト禁止
		if ($_GET) {
			$allow = false;
		}
		// トークンチェック
		if (!isset($_SESSION['token']) || (isset($_SESSION['token']) && $_POST['data']['token'] !== $_SESSION['token'] . session_id())) {
			$allow = false;
		}
		// actionは必須
		if (!isset($_POST['action'])) {
			$allow = false;
		}
		if (!$allow) {
			header("HTTP/1.1 403 Forbidden");
			exit;
		}
	
	}

}
?>