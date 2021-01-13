

<div class="img-container">
  <img id="work_img" :src="work_img">
</div>

<!-- プレビュー -->
<preview
  :size="{width:'200px', height:'200px'}"
  :preview_zindex="preview_zindex"
  :preview_img="preview_img"
></preview>


<div class="docs-buttons">
  <button type="button" class="btn btn-primary" data-method="setDragMode" data-option="move" title="Move">
      <span class="fa fa-arrows"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="setDragMode" data-option="crop" title="Crop">
      <span class="fa fa-crop"></span>
  </button>

  <button type="button" class="btn btn-primary" data-method="zoom" data-option="0.1" title="Zoom In">
      <span class="fa fa-search-plus"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="zoom" data-option="-0.1" title="Zoom Out">
      <span class="fa fa-search-minus"></span>
  </button>

  <button type="button" class="btn btn-primary" data-method="move" data-option="-10" data-second-option="0" title="Move Left">
      <span class="fa fa-arrow-left"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="move" data-option="10" data-second-option="0" title="Move Right">
      <span class="fa fa-arrow-right"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="move" data-option="0" data-second-option="-10" title="Move Up">
      <span class="fa fa-arrow-up"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="move" data-option="0" data-second-option="10" title="Move Down">
      <span class="fa fa-arrow-down"></span>
  </button>

  <button type="button" class="btn btn-primary" data-method="rotate" data-option="-45" title="Rotate Left">
      <span class="fa fa-rotate-left"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="rotate" data-option="45" title="Rotate Right">
      <span class="fa fa-rotate-right"></span>
  </button>

  <button type="button" class="btn btn-primary" data-method="scaleX" data-option="-1" title="Flip Horizontal">
      <span class="fa fa-arrows-h"></span>
  </button>
  <button type="button" class="btn btn-primary" data-method="scaleY" data-option="-1" title="Flip Vertical">
      <span class="fa fa-arrows-v"></span>
  </button>
</div>

<div class="btn btn-success" v-on:click.prevent="getCropData()">決定</div>

