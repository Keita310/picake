
<!-- プレビューのコンポーネント -->
<script type="text/x-template" id="preview-component">
  <div class="preview" :style="size">
    <div class="cropped" v-for="data in preview_img" :style="{ zIndex: data.zIndex }"><img :src="data.img"></div>
    <div class="cropping" :style="{ zIndex: preview_zindex }"></div>
  </div>
</script>

<!-- ローディングのコンポーネント -->
<script type="text/x-template" id="loading-component">
  <div class="loading">
    <i class="fa fa-spinner fa-spin"></i>
  </div>
</script>

<!-- エラー表示のコンポーネント -->
<script type="text/x-template" id="error-component">
  <div>
    <div id="error"></div>
    <div v-if="errors.length">
      <ul>
        <li v-for="error in errors">{{ error }}</li>
      </ul>
    </div>
  </div>
</script>
