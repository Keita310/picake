<?php
  session_start();
?>
<!DOCTYPE HTML>
<html lang="ja">
<head>
  <title>Picake</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
	<meta name="keywords" content="">
	<meta name="description" content="">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="css/cropper.css">
	<link rel="stylesheet" href="css/style.css">

<style>
.cropping-area {
  height: 0;
  overflow: hidden;
}
.cropping-area.open {
  height: auto;
}
.preview {
  position: relative;
  width: 200px;
  height: 200px;
}
.preview div{
  position: absolute;
  display: block;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}
.preview .cropped img {
  width: 100%;
}
.img-container {
  margin-bottom: 1rem;
  max-height: 497px;
  min-height: 200px;
}
.img-container > img {
  max-width: 100%;
}
.cropping {
  overflow: hidden;
}
.cropping > img {
  max-width: 100%;
}
.loading {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 33333;
  background: #FFF;
  opacity: 0.9;
  width: 100%;
  height:100%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 40px;
  pointer-events: none;
}


</style>

</head>
<body>

<div id="app">

<!-- ローディング -->
<loading v-if="loading"></loading>

<header id="header">
	<!--<h2>ケーキハウス幸せの丘</h2>-->
  <h2 v-if="shop_image"><img :src="'../member/shop/' + sys_id + '/' + shop_image" :alt="shop_name"></h2>
  <h2 v-else>{{shop_name}}</h2>
</header>

<div id="container">

  <!-- フォーム入力エラー表示 -->
  <error :errors="errors"></error>

  <!-- パーツ選択画面 -->
  <div v-if="select_parts_area">
    <h3>パーツを選択してください</h3>
    <div v-for="(img, index) in parts_template">
      <img :id="'parts_' + index" :src="img" v-on:click.prevent="selectParts(index)" width="100">
    </div>
    <input type="submit" class="bak-btn" value="戻る" v-on:click.prevent="hideSelectPartsArea()">
  </div>

  <!-- 画像加工画面 -->
  <div class="cropping-area" :class="showCroppingArea">
    <?php include('elements/crop_area.php'); ?>
  </div>

  <!-- 各ステップ画面 -->
  <div v-if="!work_to && !select_parts_area">
    <div v-if="step == 0">
      利用できません
    </div>
    <div v-if="step == 1">
      <?php include('elements/step1.php'); ?>
    </div>
    <div v-if="step == 2">
      <?php include('elements/step2.php'); ?>
    </div>
    <div v-if="step == 3">
      <?php include('elements/step3.php'); ?>
    </div>
    <div v-if="step == 4">
      <?php include('elements/step4.php'); ?>
    </div>
    <div v-if="step == 5">
      <?php include('elements/step5.php'); ?>
    </div>
  </div>

</div>



<footer id="footer">
	<p>supported by</p>
	<div><object type="image/svg+xml" data="images/picake-logo-wh.svg" alt="picake ピッケーキ" class="logo-foot"></object></div>
</footer>

<?php // セキュリティ対策
  $token = md5(uniqid(rand(), TRUE));
  $_SESSION['token'] = $token;
?>
<input type="hidden" name="token" value="<?php echo $token  . session_id(); ?>">
</div><!--//#app-->

<!-- コンポーネント群 -->
<?php include('elements/vue_component.php'); ?>

<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.bundle.min.js"></script>
<script src="js/vue.min.js"></script>
<script src="js/cropper.js"></script>
<script src="js/common.js"></script>

</body>
</html>