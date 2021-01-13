<h3>デザインを決めます</h3>
<form>
	<div class="form-area">

		<!-- プレビュー -->
		<preview
			:size="{width:'300px', height:'300px'}"
			:preview_zindex="preview_zindex"
			:preview_img="preview_img"
		></preview>

		<div class="pic-area">

			<input type="file" name="file" v-on:change="uploadImage" accept="image/*">

			<div v-for="img in base_img">
				<img :src="img" width='100'>
			</div>

		</div>

		<h4>パーツを選択</h4>
		<p class="att-txt">ご希望の方は選択してください</p>

		<div class="parts-area">
			<div style="background:#FFF;border:1px solid #CCC;" v-for="(img, index) in parts_img">
				<img :src="img" width='100'>
				<div class="btn btn-primary" v-on:click.prevent="showSelectPartsArea(index)">再編集</div>
				<div class="btn btn-danger" v-on:click.prevent="removeSelectedParts(index)"><i class="fa fa-times-circle-o"></i> 削除する</div>
			</div>
			<div class="btn btn-warning" v-on:click.prevent="showSelectPartsArea(parts_img.length)">パーツを追加する</div>
		</div>

		<h4>メッセージを入力</h4>
		<p class="att-txt">ご希望の方はご入力ください</p>
		<textarea name="order_message" maxlength="20" v-model="order_message"></textarea>

		<p class="bottom-txt">※最大20文字まで。</p>
		<p class="bottom-txt">※パティシエが手書きいたします。</p>

	</div>
	<input type="submit" class="next-btn" value="確認画面へ進む" v-on:click.prevent="step4">
	<input type="submit" class="bak-btn" value="戻る" v-on:click.prevent="changeStep(2)">
</form>