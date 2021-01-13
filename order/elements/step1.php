<h3>お客様情報の入力</h3>
<form>
	<div class="form-area">
		<input type="text" name="order_name" placeholder="お名前" v-model="order_name">
		<input type="text" name="order_tel" placeholder="電話番号" v-model="order_tel">
	</div>
	<input type="submit" class="next-btn" value="ケーキ選択へ進む" v-on:click.prevent="step2">
</form>