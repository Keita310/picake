<?php
session_start();

new order();

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
		require_once 'php/mail.php';
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
		$config = file_get_contents('../../config/config.pl');
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


class order extends common {

	private $sysId;
	private $orderId;
	private $orderDir;
	private $memberDir = '../member/';

	function __construct() {
		parent::__construct();
		$this->validation();
		$action = $_POST['action'];
		$this->$action();
	}

	/*
	 * 初期データを取得
	 *
	*/
	public function getIniData() {

		// システムIDをセット
		$this->setSysId();
		// 契約店舗情報取得
		$shopMaster = $this->getShopMaster();
		// ケーキテンプレートデータを取得
		$cakeData = $this->getCakeData();

		$arr = array(
			'shop_name' => $shopMaster['shop_name'],
			'shop_image' => $shopMaster['shop_image'],
			// ケーキデータの初期値をセット。必ず1とは限らないためリストの先頭のidを適応
			'cake' => $cakeData[0]['item_id'],
			'cake_template' => $cakeData,
			'parts_template' => $this->getPartsData()
		);
		$this->response('ok', $arr);
	}

	/*
	 * フォーム送信
	 *
	*/
	public function finish() {

//		$this->response('ng', '');


		$data = $_POST;
		// システムIDをセット
		$this->setSysId();
		// オーダーID生成
		$this->setOrderId();
		// ディレクトリ生成
		$this->createOrderDir();

		// 添付ファイルを生成
		$files = $this->createAttachmentFiles($data);
		// 契約店舗情報と紐づくケーキデータを取得
		$shopData = $this->getShopData($data['data']['cake']);
		// メールデータ生成
		$mailData = $this->createMailData($shopData, $data, $files);
		//内容保存
		file_put_contents($this->orderDir . 'mail.txt', $mailData['body']);
		//DB注文データ保存
		$this->addOrderData($data, $shopData);
		// メール送信
		if ($this->mailSend($mailData)) {
			$status = 'ok';
		} else {
			$status = 'ng';
		}
		$this->response($status, '');
	}

	/*
	 * メールの送信データを生成
	 *
	*/
	private function createMailData($shopData, $data, $files) {
		$orderId = $this->orderId;
		// from名があればバラす
		$name = null;
		$from = $_ENV['mail_from'];
		preg_match('/(^.*?)(<)(.*?@.*?)(>)/', $from, $match);
		if (!empty($match)) {
			$name = $match[1];
			$from = $match[3];
		}
		// メールテンプレートを取得
		$title = '[Picake]写真加工の受付（' . $orderId . '）';
		$body = file_get_contents('mail_template.txt');
		// テンプレート置換
		foreach ($data['data'] as $key => $value) {
			$body = str_replace('{{' . $key .'}}', $value, $body);
		}
		$body = str_replace('{{staff_name}}', $shopData['staff_name'], $body);
		$body = str_replace('{{order_id}}', $orderId, $body);
		$body = str_replace('{{create_image}}', $orderId . '.jpg', $body);
		$body = str_replace('{{original_image}}', $orderId . '-original.jpg', $body);
		$body = str_replace('{{item_id}}', $shopData['item_id'], $body);
		$body = str_replace('{{item_name}}', $shopData['item_name'], $body);

		return array(
			'name'  => $name,
			'from'  => $from,
	//		'to'    => $shopData['staff_email'],
			'to'    => $from,
			'title' => $title,
			'body'  => $body,
			'files' => $files
		);
	}

	/*
	 * 添付ファイルを生成
	 *
	*/
	private function createAttachmentFiles($data) {
		$savePath = $this->orderDir;
		$width = $data['data']['img_width'];
		$height = $data['data']['img_height'];
		// 下地を作る
		$dst_im = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($dst_im, 255, 255, 255);
		imagefill($dst_im, 0, 0, $white);
		// すべて合成
		foreach ($data['preview_img'] as $img) {
			if ($img === end($data['preview_img'])) {
				// ここでフレーム無し画像化
				$originPath = $savePath . $this->orderId . "-original.jpg";
				imagejpeg($dst_im, $originPath, 100);
			}
			$this->imgJoin($dst_im, $img['img'], 0, 0, $width, $height);
		}
		// 画像化
		$orderPath = $savePath . $this->orderId . ".jpg";
		imagejpeg($dst_im, $orderPath, 80);
		imagedestroy($dst_im);
	/*
		// 元画像
		$originPath = $savePath . "origin.jpg";
		$baseImg = base64ToBinary($data['data']['origin_img']);
		file_put_contents($originPath, $baseImg);
	*/
		$flies = array(
			basename($orderPath)  => $orderPath,
			basename($originPath) => $originPath
		);
		return $flies;
	}
	
	/*
	 * 画像合成
	 *
	*/
	private function imgJoin($dst_im, $imgPath, $left = 0, $top = 0, $resizeWidth = false, $resizeHeight = false) {
	
		// 画像リソース化
		if (strpos($imgPath,';base64,') !== false) {
			$binary = $this->base64ToBinary($imgPath);
			$src_im = imagecreatefromstring($binary);
		} else {
			$imgInfo = getimagesize($imgPath);
			if (strpos($imgInfo['mime'],"jpeg") !== false) {
				$src_im = imagecreatefromjpeg($imgPath);
			} elseif (strpos($imgInfo['mime'],"png") !== false) {
				$src_im = imagecreatefrompng($imgPath);
			} elseif (strpos($imgInfo['mime'],"gif") !== false) {
				$src_im = imagecreatefromgif($imgPath);
			}
		}
		$width = imagesx($src_im);
		$height = imagesy($src_im);
		if (!$resizeWidth) {
			$resizeWidth = $width;
		}
		if (!$resizeHeight) {
			$resizeHeight = $height;
		}
		imagecopyresampled($dst_im, $src_im, $left, $top, 0, 0, $resizeWidth, $resizeHeight, $width, $height);
		imagedestroy($src_im);
	}

	/*
	 * システムIDをセット
	 *
	*/
	private function setSysId() {
		$this->sysId = preg_replace('/-\d{1,2}$/', '', $_POST['data']['id']);
	}

	/*
	 * オーダーIDを生成
	 *
	*/
	private function setOrderId() {
		$rand = "";
		for($i=0;$i<6;$i++){
			$rand .= mt_rand(0,9);
		}
		$this->orderId = date('Ymd-His') . '-' . $rand;
	}

	/*
	 * オーダーディレクトリを生成
	 *
	*/
	private function createOrderDir() {
		$this->orderDir = $this->memberDir . 'shop/' . $this->sysId . '/order/' . $this->orderId . '/';
		mkdir($this->orderDir, 0777, TRUE);
	}

	/*
	 * 契約店舗情報取得
	 *
	*/
	private function getShopMaster() {
		$sql = "SELECT * FROM shop_master WHERE sys_id = '{$this->sysId}' AND use_flag = 0";
		return $this->query('row', $sql);
	}

	/*
	 * ケーキテンプレートデータを取得
	 *
	*/
	private function getCakeData() {
		$sql = "SELECT * FROM shop_item WHERE sys_id = '{$this->sysId}' AND item_disp = 0";
		return $this->query('all', $sql);
	}

	/*
	 * 契約店舗情報と紐づくケーキデータを取得
	 *
	*/
	private function getShopData($cakeId) {
		$sql = "SELECT * FROM shop_master INNER JOIN shop_item ON shop_master.sys_id = shop_item.sys_id WHERE shop_master.sys_id = '{$this->sysId}' AND shop_item.item_id = '{$cakeId}'";
		return $this->query('row', $sql);
	}

	/*
	 * オーダーデータ書き込み
	 *
	*/
	private function addOrderData($data, $shopData) {
		$orderId = $this->orderId;
		$sys_id = $this->sysId;
		$sql = "INSERT INTO shop_order VALUES (null, '{$orderId}', '{$sys_id}', '{$shopData['item_id']}', '" . date('Y-m-d H:i:s') . "', '{$data['data']['order_name']}', '{$data['data']['order_tel']}', null, '{$data['data']['order_message']}', null, '{$orderId}-original.jpg', '{$orderId}.jpg', 0)";
		$this->query('insert', $sql);
	}

	/*
	 * パーツデータを取得
	 *
	*/
	private function getPartsData() {
		$path = $this->memberDir . "parts/";
		$arr = array();
		$dir = opendir($path);
		while(false !== ($dir_name = readdir( $dir ))){
			if ($dir_name != "." && $dir_name != ".."/* && $dir_name != "Thumbs.db"*/) {
				$arr[] = $path . $dir_name;
			}
		}
		sort($arr);
		closedir($dir);
		return $arr;
	}

	/*
	* バリデーション
	*
	*/
	private function validation() {

		/*
		バリデーションを定義
		　関数ならバリデーション実行
		　trueはバリデーションしない
		　falseはどこにも使われない
		*/
		$config = array(
			'token' => true,
			'loading' => false,
			'step' => function($val, $err = null) {
				if (array_search($val, array(0, 4)) === false) {
					$err = '送信可能ステップではありません';
				}
				return array($val, $err);
			},
			'order_name' => function($val, $err = null) {
				$val = strip_tags($val);
				if (mb_strlen($val) > 100) {
					$err = 'お名前が長すぎます';
				}
				return array($val, $err);
			},
			'order_tel' => function($val, $err = null) {
				$val = preg_replace('/()/', '', $val);
				if (mb_strlen($val) > 20) {
					$err = '電話番号が長すぎます';
				}
				return array($val, $err);
			},
			'cake' => function($val, $err = null) {
				if (!ctype_digit($val)) {
					$err = '数字以外は指定できません';
				}
				return array($val, $err);
			},
			'cake_img' => false,
			'img_width' => function($val, $err = null) {
				if (!ctype_digit($val)) {
					$err = '数字以外は指定できません';
				} else if ($val < 1 && $val > 10000) {
					$err = 'サイズが想定外です';
				}
				return array($val, $err);
			},
			'img_height' => function($val, $err = null) {
				if (!ctype_digit($val)) {
					$err = '数字以外は指定できません';
				} else if ($val < 1 && $val > 10000) {
					$err = 'サイズが想定外です';
				}
				return array($val, $err);
			},
			'origin_img' => function($val, $err = null) {
				$val = strip_tags($val);
				if (!empty($val) && !preg_match('/^data:image\/.*?;base64,/', $val)) {
					$err = 'base64形式以外は指定できません';
				};
				return array($val, $err);
			},
			'preview_zindex' => false,
			'select_parts_area' => false,
			'work_img' => false,
			'work_to' => false,
			'work_to_index' => false,
			'order_message' => function($val, $err = null) {
				$val = strip_tags($val);
				if (mb_strlen($val) > 20) {
					$err = 'メッセージが長すぎます';
				} else if (mb_strlen($val) < 1) {
					$val = 'メッセージなし';
				}
				return array($val, $err);
			},
			'img' => function($val, $err = null) {
				if (strpos($val, 'data:image/png;base64,') === false) {
					$err = 'base64形式以外は指定できません';
				}
				return array($val, $err);
			},
			'id' => function($val, $err = '不正なシステムIDです') {
				if (preg_match('/(\d{8})-(\d{1,2})/', $val, $match)) {
					preg_match_all("([0-9])", $match[1], $m);
					$sum = array_sum($m[0]);
					if ((int)$sum == (int)$match[2]) {
						$err = null;
					}
				}
				return array($val, $err);
			},
			'item_id' => false,
			'sys_id' => false,
			'item_memo' => false,
			'item_name' => false,
			'item_sample' => false,
			'item_price' => false,
			'price_disp' => false,
			'item_disp' => false,
			'shop_name' => true,
			'shop_image' => false,
			'zIndex' => false,
			'action' => function($val, $err = null) {
				if (array_search($val, array('getIniData', 'finish')) === false) {
					$val = '不正なアクションです';
				}
				return array($val, $err);
			}
		);
		$error = array();
		array_walk_recursive($_POST, function(&$value, $key) use ($config, &$error) {
			$err = false;
			if (preg_match('/[^0-9]/', $key)) {
				if (isset($config[$key])) {
					if (is_callable($config[$key])) {
						list($value, $err) = $config[$key]($value);
					} else if ($config[$key] === false){
						$value = false;
					}
				} else {
					$err = '不正な項目は送信できません';
				}
				if ($err) {
					$error[] = '[' . $key . ']' . $err;
				}
			}
		});
		if (!empty($error)) {
			$this->response('ng', $error);
		}
	}

}



?>