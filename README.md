# jsnlib/codeigniter-prototype-converter
Prototype Converter. Conver your php  to html. 

可以將 Codeigniter 開發的網頁，轉換成靜態 HTML 格式，在沒有伺服器的環境底下運行。這非常適合用來建構 Prototype 原型，或是商務提案的演示。
為什要推薦使用？
- 共同頁面例如 `<header>` `<footer>` 修改的時候，可以不必重複動作。
- 透過 PHP 動態開發模板，可以使用 `if else` `foreach` 加快速度製作原型。

# 安裝
````
composer require jsnlib/codeigniter-prototype-converter
````

# 使用方式

### 建立設定檔
application/config/prototype.php
- `assets` 可指定複數的靜態資源，可能是 CSS、JavaScript、媒體、字形等等。如果所有的資源都包含在如 `assets` 那麼只要指定一次即可，會很方便。
- `pages` 將 `Codeigniter` 的 `路由` 轉換成靜態 `HTML 名稱`。命名 HTML 時不使用路徑，除了在程式設計上過於繁瑣且效益不高之外，巢狀需要不斷切換上下頁，確實不利於網頁調閱。建議使用下滑線 `_` 命名，因為駝峰式寫法 `camelCase` ，若在 windows 將會不分大小寫，較不嚴僅。
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
        'product' => 'product.html',
        'product/detail/1001' => 'product_detail_1001.html',
        'product/detail/2003' => 'product_detail_2003.html',
    ],
    'put' => 'static-pages'
];
````

### 建立控制器
- 你可以隨意取名，例如 application/controllers/Build.php
````php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Build extends CI_Controller {

    function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->prototype = new \Jsnlib\Codeigniter\Prototype\Converter;
        
        $result = $this->prototype->build([
            'download' => false
        ]);

        var_dump($result);
    }

}
````
透過網址運行以後，會在根目錄形成一份由 config/prototype.php 的 `put` 所指定的路徑，裡面即是生成的靜態 HTML。若想打包成 zip，可以指定參數 `doewnload => true`。
