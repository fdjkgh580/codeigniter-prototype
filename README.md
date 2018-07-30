# jsnlib/codeigniter-prototype-converter
這是一個 Codeigniter 類別，當利用 Codeigniter 編寫網站後，可將 PHP 轉換為 HTML，且能在沒有伺服器的環境底下運行。這非常適合用來建構 Prototype 原型，或是商務提案的演示。

為什要推薦使用？
- 共同頁面例如 `<header>` `<footer>` 修改的時候，可以不必重複動作。
- 透過 PHP 動態開發模板，可以使用 `if else` `foreach` 加快速度製作原型。

# 安裝
````
composer require jsnlib/codeigniter-prototype-converter
````

# 使用方式
### 必要的
##### 使用 `<base>`
HTML 務必在 `<head>` 中添加 `<base href="<?=site_url()?>">` 作為基本位置，確保整體網站中的超連結、媒體的基本位置，都與根目錄 index.php 同一層。
`site_url()` 可由 `$this->load->helper(url);` 載入，或是從 application/autoload.php 添加 `$autoload['helper'] = array('url');`。

### 建立設定檔
application/config/prototype.php
以下是個範例
- `assets` 可指定複數的靜態資源，可能是 CSS、JavaScript、媒體、字形等等。如果所有的資源都包含在如 `assets` 那麼只要指定一次即可，會很方便。
- `pages` 將 `Codeigniter` 的 `路由` 轉換成靜態 `HTML 名稱`。命名 HTML 時不使用路徑，除了在程式設計上過於繁瑣且效益不高之外，巢狀需要不斷切換上下頁，確實不利於網頁調閱。建議使用下滑線 `_` 命名，因為駝峰式寫法 `camelCase` ，若在 windows 將會不分大小寫，較不嚴僅。當值為 `null` 將使用下滑線自動命名。
- `put` 產生的靜態 html 放在根目錄的資料夾。路徑不存在會自動建立，請注意根目錄的權限。

````php
<?php 
$config = 
[
    'assets' => 
    [
        'assets'
    ],
    'pages' => 
    [
        'product/index' => 'product.html',
        'product/content/001' => 'product_content_001.html',
        'product/content/002' => null, // 自動轉換為 product_content_002.html
    ],
    'put' => 'static-pages'
];
````

### 建立控制器
- 你可以隨意取名，例如 application/controllers/Build.php
````php
class Build extends CI_Controller {

    public function index()
    {
        $this->prototype = new \Jsnlib\Codeigniter\Prototype\Converter;
        
        $result = $this->prototype->build(
        [
            'download' => false
        ]);

        var_dump($result);
    }

}
````
透過網址運行以後，會在根目錄形成一份由 config/prototype.php 的 `put` 所指定的路徑，裡面即是生成的靜態 HTML。若想打包成 zip，可以指定參數 `doewnload => true`。
