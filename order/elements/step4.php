<h3>イメージの確認</h3>
<form>
	<div class="form-area">

		<div class="sample-div">
			<p>完成イメージ</p>
			<!-- プレビュー -->
			<preview
			  :size="{width:'300px', height:'300px'}"
			  :preview_zindex="preview_zindex"
			  :preview_img="preview_img"
			></preview>
		</div>

		<p class="att-txt">※ディスプレイの環境により色味が多少異なる場合があります。予めご了承ください。</p>

	</div>
	<input type="submit" class="next-btn" value="完了" v-on:click.prevent="step5">
	<input type="submit" class="bak-btn" value="戻る" v-on:click.prevent="changeStep(3)">
</form>