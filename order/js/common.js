// ajax初期設定
$.ajaxSetup({
  type     : 'POST',
  dataType : 'json',
  url: 'php/order.php',
  contentType: "application/x-www-form-urlencoded",
  headers: {'X-Requested-With': 'XMLHttpRequest'}
});

// コンポーネントを定義
var previewComponent = {
  template: '#preview-component',
  props: ['preview_img','preview_zindex','size']
};
var loadingComponent = {
  template: '#loading-component'
};
var errorComponent = {
  template: '#error-component',
  props: ['errors']
};

// vueインスタンス化
var app = new Vue({
  el: '#app',
  data: {
    token             : null,
    loading           : true,
    errors            : [],
    id                : null,
    shop_name         : null,
    shop_image        : null,
    step              : 0,
    order_name        : null,
    order_tel         : null,
    order_message     : '',
    cake              : 0,
    cake_img          : null,
    cake_template     : [],
    img_width         : 300,
    img_height        : 300,
    crop_data         : [],
    origin_img        : null,
    base_img          : [],
    parts_img         : [],
    preview_zindex    : 0,
    parts_template    : [],
    select_parts_area : false,
    work_img          : 'images/no_img.png',
    work_to           : false,
    work_to_index     : 0
  },
  components: {
    preview: previewComponent,
    loading: loadingComponent,
    error: errorComponent
  },
  created: function () {
    // tokenをセット
    this.token = document.querySelector('input[name="token"]').value;
    // 初期データを取得
    this.getIniData();
  },
  mounted: function() {
    // cropper初期化
    this.iniCropper();
  },
  watch: {

  },
  computed: {
    sys_id: function () {
      return this.id.replace(/-\d{1,2}$/,'');
    },
    preview_img: function () {
      this.preview_zindex = 0;
      var images = [];
      var zIndex = 0;
      // 既存データを再編集だったらプレビューから除いてz-indexを控える
      for(var i in this.base_img) {
        if (this.work_to !== 'base_img' || i != this.work_to_index) {
          images.push({img: this.base_img[i], zIndex: zIndex});
        } else {
          this.preview_zindex = zIndex;
        }
        zIndex++;
      }
      // 新規のとき
      if (this.work_to === 'base_img' && this.base_img.length == this.work_to_index) {
        this.preview_zindex = zIndex;
        zIndex++;
      }
      // 既存データを再編集だったらプレビューから除いてz-indexを控える
      for(var i in this.parts_img) {
        if (this.work_to !== 'parts_img' || i != this.work_to_index) {
          images.push({img: this.parts_img[i], zIndex: zIndex});
        } else {
          this.preview_zindex = zIndex;
        }
        zIndex++;
      }
      // 新規のとき
      if (this.work_to === 'parts_img' && this.parts_img.length == this.work_to_index) {
        this.preview_zindex = zIndex;
        zIndex++;
      }
      // ケーキテンプレート
      images.push({img: this.cake_img, zIndex: zIndex});
      return images;
    },
    showCroppingArea: function () {
      return {
        open: (this.work_to) ? true : false
      }
    }
  },
  methods: {
    step2: function () {
      this.errors = [];
      if (this.order_name && this.order_tel) {
        this.changeStep(2);
        return;
      }
      if (!this.order_name) {
        this.errors.push('お名前が未入力です');
      }
      if (!this.order_tel) {
        this.errors.push('電話番号が未入力です');
      }
      this.smoothScroll('error');
    },
    step3: function () {
      // ケーキのフレームデータをセット
      var img = document.getElementById('cakeid_' + this.cake);
      this.cake_img = this.imageToBase64(img);
      // ここで幅高さを取得してすべての基準にする
      this.img_width = img.naturalWidth;
      this.img_height = img.naturalHeight;
      // ページ移動
      this.changeStep(3);

//      ページ上に読み込まれていない画像を使う場合は下記で対応
//      var _this = this;
//      var img = new Image();
//      img.src = 'images/cake_frame_' + this.cake + '.png';
//      img.onload = function () {
//        _this.cake_img = _this.imageToBase64(img);
//        // ここで幅高さを取得してすべての基準にする
//        _this.img_width = img.width;
//        _this.img_height = img.height;
//        // ページ移動
//        _this.changeStep(3);
//      }
    },
    step4: function () {
      this.errors = [];
      if (this.base_img.length < 1) {
        this.errors.push('写真の登録をしてください');
      }
      if (this.order_message.length > 20) {
        this.errors.push('メッセージは最大20文字までです');
      }
      if (this.errors.length > 0) {
        this.smoothScroll('error');
      } else {
        this.changeStep(4);
      }
    },
    // メールを送信
    step5: function () {
      this.errors = [];
      this.loading = true;
      var _this = this;
      $.ajax({
        data: {
          data: _this._data,
          preview_img: _this.preview_img,
          action: 'finish'
        }
      }).done(function(data) {
        _this.loading = false;
        if (data.status === 'ok') {
          _this.changeStep(5);
        } else {
          _this.errors = data.response;
          _this.smoothScroll('error');
        }
      }).fail(function(data) {
        _this.loading = false;
        alert('受付に失敗しました。');
      });
    },
    // ページを切り替える
    changeStep: function (step) {
      this.step = step;
      this.scrollTop();
    },
    // URLパラメータを取得
    getQuery: function (key) {
      var query = {};
      var queryStr = window.location.search.substring(1);
      var matches = queryStr.match(/[^&.]*=[^&.]*/g);
      for (var i = 0; i < matches.length; i++) {
        var splits = matches[i].split('=');
        query[splits[0]] = splits[1];
      }
      if (key !== undefined) {
        return query[key];
      }
      return query;
    },
    // スクロールをTOPへ戻す
    scrollTop: function () {
      document.body.scrollTop = document.documentElement.scrollTop = 0;
    },
    // スムーズスクロール
    smoothScroll: function (target) {
      var position = $('#' + target).offset().top;
      $("html, body").animate({scrollTop:position}, 500, "swing");
    },
    // ファイルUP
    uploadImage: function (e) {
      this.errors = [];
      var _this = this;
      var files = e.target.files;
      var fileReader = new FileReader();
      fileReader.onload = function() {
        // base64形式のURLを取得
        var url = this.result;
        _this.origin_img = url;
        _this.work_img = url;
        _this.work_to_index = 0;
        _this.work_to = 'base_img';
        cropper.replace(url);
      }
      // 画像ファイルのみ実行
      if (files[0].type.match(/image\/(jpeg|gif|png)/)) {
        fileReader.readAsDataURL(files[0]);
      } else {
        this.errors.push('画像は「jpg」「png」「gif」以外登録できません');
        this.smoothScroll('error');
      }
/*
      //　サーバーへ渡してbase64へ変換する方法
      // jquery.upload読み込み必須
      $(e.target).upload("common.php", {
        action: 'uploadImage',
      }, function(data) {
        var json = JSON.parse(data);
        console.log(data);
        _this.origin_img = json.url;
        _this.work_img = json.url;
        _this.work_to_index = 0;
        _this.work_to = 'base_img';
        _this.errors = [];
        cropper.replace(json.url);
      });
*/
    },
    // 加工決定
    getCropData: function () {
      var size = {
        width:  this.img_width,
        height:  this.img_height
      };
      var result = cropper['getCroppedCanvas'](size, false);
      this[this.work_to][this.work_to_index] = result.toDataURL('image/png');
      this.work_to = false;
      this.scrollTop();
/*
      // サーバーで加工する場合
      var _this = this;
      this.crop_data = {
        getData: cropper['getData'](true, false),
        getContainerData: cropper['getContainerData'](true, false),
        getImageData: cropper['getImageData'](true, false),
        getCanvasData: cropper['getCanvasData'](true, false),
        getCropBoxData: cropper['getCropBoxData'](true, false)
      };
      $.ajax({
        data: {
          data: this._data,
          action: 'crop',
        }
      }).done(function(data) {
        _this.parts_img = data.url;
        cropper.replace(data.url);
      }).fail(function(data) {
        alert('受付に失敗しました。');
      });
*/
    },
    // パーツ選択画面を開く
    showSelectPartsArea: function (index) {
      this.work_to_index = index;
      this.select_parts_area = true;
      this.scrollTop();
    },
    // パーツ選択画面から戻る
    hideSelectPartsArea: function () {
      this.select_parts_area = false;
      this.scrollTop();
    },
    // 選択されたパーツを画像編集画面へ送る
    selectParts: function (index) {
      var img = document.getElementById('parts_' + index);
      this.work_img = this.imageToBase64(img);
      this.work_to = 'parts_img';
      cropper.replace(this.work_img);
      this.hideSelectPartsArea();
    },
    // 指定のパーツを削除
    removeSelectedParts: function (index) {
      this.parts_img.splice(index, 1);
    },
    // 画像をbase64文字列に変換
    imageToBase64: function (img) {
      var canvas = document.createElement('canvas');
      canvas.width  = img.naturalWidth;
      canvas.height = img.naturalHeight;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0);
      return canvas.toDataURL('image/png');
    },
    // 初期データを取得
    getIniData: function () {
      this.id = this.getQuery('q');
      var _this = this;
      $.ajax({
        data: {
          data: _this._data,
          action: 'getIniData'
        }
      }).done(function(data) {
        if (data.status === 'ok') {
          // 初期値をまとめて適応
          $.extend(_this, data.response);
          // ページ遷移
          _this.changeStep(1);
        } else {
          _this.errors = data.response;
        }
        _this.loading = false;
      }).fail(function(data) {
        console.log('初期値取得失敗');
        _this.loading = false;
      });
    },
    // cropper初期化
    iniCropper: function () {
      var image = document.getElementById('work_img');
      var options = {
        aspectRatio: 9 / 9,
        preview: '.cropping'
      };
      window.cropper = new Cropper(image, options);

      // Buttons
      if (typeof document.createElement('cropper').style.transition === 'undefined') {
        $('button[data-method="rotate"]').prop('disabled', true);
        $('button[data-method="scale"]').prop('disabled', true);
      }
  
      // Methods
      document.querySelector('.docs-buttons').onclick = function (event) {
        var e = event || window.event;
        var target = e.target || e.srcElement;
        var cropped;
        var data;
  
        if (!cropper) {
          return;
        }
  
        while (target !== this) {
          if (target.getAttribute('data-method')) {
            break;
          }
  
          target = target.parentNode;
        }
  
        if (target === this || target.disabled || target.className.indexOf('disabled') > -1) {
          return;
        }
  
        data = {
          method: target.getAttribute('data-method'),
          option: target.getAttribute('data-option') || undefined,
          secondOption: target.getAttribute('data-second-option') || undefined
        };
  
        cropped = cropper.cropped;
  
        if (data.method) {
          if (typeof data.target !== 'undefined') {
  
  
            if (!target.hasAttribute('data-option') && data.target) {
              try {
              } catch (e) {
                console.log(e.message);
              }
            }
          }
  
          switch (data.method) {
            case 'rotate':
              if (cropped && options.viewMode > 0) {
                cropper.clear();
              }
              break;

          }

          cropper[data.method](data.option, data.secondOption);

          switch (data.method) {
            case 'rotate':
              if (cropped && options.viewMode > 0) {
                cropper.crop();
              }
              break;

            case 'scaleX':
            case 'scaleY':
              target.setAttribute('data-option', -data.option);
              break;

          }
        }
      };
    }
  }
})

