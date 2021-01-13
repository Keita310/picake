<h3>ご希望の商品をお選びください</h3>
<form>
	<div class="form-area">
		<div class="cake-select">

			<div v-for="data in cake_template">
				<input type="radio" name="cake" :value="data.item_id" :id="data.item_id" v-model="cake">
				<label :for="data.item_id">
					<img v-if="data.item_sample" :src="'../../member/shop/' + sys_id + '/template_sample/' + data.item_sample">
					<img :class="{ 'show-sample': data.item_sample }" :src="'../../member/shop/' + sys_id + '/template/' + data.item_id + '.png'" :id="'cakeid_' + data.item_id">
					<p>{{data.item_name}}</p>
				</label>
			</div>

		</div>
	</div>
	<input type="submit" class="next-btn" value="写真選択へ進む" v-on:click.prevent="step3">
	<input type="submit" class="bak-btn" value="戻る" v-on:click.prevent="changeStep(1)">
</form>