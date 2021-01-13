<?php
session_start();
//ini_set('error_reporting', E_ALL);

require_once 'common.php';

class order extends common {

	private $sysId;
	private $orderId;
	private $orderDir;
	private $memberDir = '../../member/';

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
		$body = file_get_contents('../mail_template.txt');
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

new order();

//function uploadImage() {
//
//	if (isset($_FILES['file']['tmp_name'])) {
//		$aa = $_FILES['file']['tmp_name'];
//		$img = file_get_contents($aa);
//		$img = base64_encode($img);
//		$url = 'data:image/jpeg;base64,' . $img;
//		// pngとか対応する
//		$array = array(
//			'url' => $url
//		);
//		echo json_encode($array);
//		exit;
//	}
//}


//function crop() {
//
//	$validated = $_POST;
//	print_r($validated);exit;
//	/*
//	$validated['data']['parts_img'];
//	$validated['data']['crop_data']['getData']['width'];
//	$validated['data']['crop_data']['getData']['height'];
//	$validated['data']['crop_data']['getData']['rotate'];
//	$validated['data']['crop_data']['getData']['x'];
//	$validated['data']['crop_data']['getData']['y'];
//	$validated['data']['crop_data']['getData']['scaleX'];
//	$validated['data']['crop_data']['getData']['scaleY'];
//	*/
//
//	// これで画像文字列をGDで扱えるようになるかも
//	$img = $validated['data']['parts_img'];
//
//	$img = preg_replace('/^.*?base64,/','', $img);
////	$img = str_replace(' ', '+', $img);
//	$img = base64_decode($img);
//	$dst_im = imagecreatefromstring($img);
//
//
//
//
//// 外枠を作る サイズ指定
//$wrapImgRsrc = imagecreatetruecolor();
//
//
//imagealphablending($wrapImgRsrc, false);
//imagesavealpha($wrapImgRsrc, true);
//
//header ('Content-Type: image/png');
//imagepng($im);
//
//exit;
//
////	$imgPath = 'images/picture.jpg';
//
//
////	imgJoin($dst_im, $imgPath, 50, 50, 100, 50);
//
//	$dst_im = imagerotate($dst_im, 45, 255);
//
//
//	$source = imagecreatefrompng($srcPath);
//
//
//	$imgInfo = getimagesize($imgPath);
//	$width = $imgInfo[0];
//	$height = $imgInfo[1];
//	if (!$resizeWidth) {
//		$resizeWidth = $width;
//	}
//	if (!$resizeHeight) {
//		$resizeHeight = $height;
//	}
//	if (strpos($imgInfo['mime'],"jpeg") !== false) {
//		$src_im = imagecreatefromjpeg($imgPath);
//	} elseif (strpos($imgInfo['mime'],"png") !== false) {
//		$src_im = imagecreatefrompng($imgPath);
//	} elseif (strpos($imgInfo['mime'],"gif") !== false) {
//		$src_im = imagecreatefromgif($imgPath);
//	}
//
//	imagecopyresampled($dst_im, $src_im, $left, $top, 0, 0, $resizeWidth, $resizeHeight, $width, $height);
//	imagedestroy($src_im);
//
//
//
//	imagealphablending($dst_im, false);
//	imagesavealpha($dst_im, true);
//
////	imagejpeg($dst_im, 'images/test.jpg', 100);
////	imagedestroy($dst_im);
////	exit;
//
//	// バイナリデータに戻す
//	ob_start();
//	imagejpeg($dst_im, null, 100);
//	$img = ob_get_clean();
//
//
//
//
//	imagedestroy($dst_im);
//
//	$img = base64_encode($img);
//	$url = 'data:image/jpeg;base64,' . $img;
////	$url = 'aaa';
//	// pngとか対応する
//
//	$array = array(
//		'url' => $url
//	);
//	header('content-type: application/json; charset=utf-8');
//	echo json_encode($array);
//	exit;
//
//	//ベース画像生成
//	$imgInfo = getimagesize($baseImg);
//	$width = $imgInfo[0];
//	$height = $imgInfo[1];
//	$dst_im = imagecreatetruecolor($width, $height);
//	$white = imagecolorallocate($dst_im, 255, 255, 255);
//	imagefill($dst_im, 0, 0, $white);
//
//	imagedestroy($this->dst_im);
//
//
//	header('content-type: application/json; charset=utf-8');
//	echo json_encode($_POST);
//	exit;
//
//}





//$request_body = file_get_contents('php://input');


//if (isset($_GET["gd"])) {
//
///*
//これベースとなる画像
//元バナーサイズの大きい辺かける1.414
//回転に対応できる対角線の大きさにする
//
//494 * 1.414
//
//*/
//
//// 外枠を作る サイズ指定
//$wrapImgRsrc = imagecreatetruecolor(800, 371);
//// 指定した色を透明化
//$transparent = imagecolorallocate($wrapImgRsrc, 0, 0, 0);
//imagecolortransparent($wrapImgRsrc, $transparent);
//
//imageAlphaBlending($wrapImgRsrc, false);
//imageSaveAlpha($wrapImgRsrc, true);
///*
//これ外枠　ポジションは必要なさそう
//getCropBoxData
//width
//height
//*/
//
///*
//外枠の大きさに対して
//画像のサイズgetImageDataの
//wdith
//height
//になる
//*/
//
//	//画像サイズ / 外枠　でバナーのサイズ比を取得
//	$ratio = 389 / 263; // 1.47
//
//
//
//
//// 加工画像
//$source = imagecreatefromjpeg('images/hbnr_1.jpg');
//$source = imagerotate($source, 20, 0, 0);
//
//imageAlphaBlending($source, false);
//imageSaveAlpha($source, true);
//
////出力画像大きさを予め定義する
////ケーキによって変動する可能性があるから
////ケーキの画像のサイズを測る
////
//$previewWidth = 300;
//$previewHeight = 300;
//
//
//
//// 元画像サイズ
///*
//
//[getImageData] => Array
//[naturalWidth] => 494
//[naturalHeight] => 180
//*/
//$width = 494;
//$height = 180;
//// リサイズ
///*
//プレビューサイズに対する
//画像サイズを計算
//プレビューサイズ　かける　サイズ比を取得
//
//$resizeWidth / 高さはアスペクト比から計算
//
//*/
//$resizeWidth = 300 * 1.27; //443
//$resizeHeight = $resizeWidth / 2.7; // 164
//echo $resizeWidth . "<BR>";
//echo $resizeHeight;
//$left = 0;
//$top = 0;
//imagecopyresampled($wrapImgRsrc, $source, $left, $top, 0, 0, $resizeWidth, $resizeHeight, $width, $height);
//
//imageAlphaBlending($wrapImgRsrc, false);
//imageSaveAlpha($wrapImgRsrc, true);
//
//
//
//// 空pngを最後に混ぜることによって透過png情報を持たせることができる
//$clear = imagecreatefrompng('images/clear.png');
//imageCopy($clear, $wrapImgRsrc, 0, 0, 0, 0, 800, 800);
//imageAlphaBlending($clear, false);
//imageSaveAlpha($clear, true);
//
//
//imagepng($clear,'aaagf.png', 0);
//
//imagedestroy($wrapImgRsrc);
//imagedestroy($clear);
//imagedestroy($source);
//
//echo '<img src="aaagf.png" style="border:1px solid #CCC;">';
//exit;
//
//}





?>