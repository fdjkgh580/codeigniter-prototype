<?php
namespace Jsnlib\Codeigniter\Prototype;

class Converter
{
    protected $ci;
    protected $config;

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->helper('url');
        $this->ci->load->helper('file');

        // 讀取設定檔
        $this->loadConfig();
    }

    // 讀取設定檔並檢查參數
    protected function loadConfig()
    {
        $this->ci->config->load('prototype', true);
        $itemPrototype = $this->ci->config->item('prototype');

        if (!isset($itemPrototype['assets']) or !is_array($itemPrototype['assets']))
        {
            show_error("Config prototype.php need array 'assets'.");
        }
        else if (!isset($itemPrototype['pages']) or !is_array($itemPrototype['pages']))
        {
            show_error("Config prototype.php need array 'pages'.");
        }
        else if (!isset($itemPrototype['put']) or !is_string($itemPrototype['put']))
        {
            show_error("Config prototype.php need string 'put'.");
        }
        $this->config = new \Jsnlib\Ao($itemPrototype);
    }

    /**
     * 建造
     * @param *$param['download'] 是否下載 true | *false
     */
    public function build(array $param): bool
    {
        $param +=
            [
            'download' => false,
        ];

        // 路徑清空
        $this->cleanDir();

        // 路徑創建
        $this->createDir();

        // 取得 HTML
        $this->putSource();

        // 複製所有資源
        $this->putAssets();

        // 封裝
        if ($param['download'] === true)
        {
            $this->download();
        }

        return true;
    }

    // 下載
    public function download()
    {
        $zipName = 'ci-prototype.zip';

        $this->ci->load->library('zip');

        $this->ci->zip->read_dir($this->config->put);

        $this->ci->zip->download($zipName);
    }

    // 放置網頁編碼
    public function putSource()
    {
        $pages = $this->config->pages;

        $this->views = new \Jsnlib\Ao([]);

        try
        {
            foreach ($pages as $path => $fileName)
            {
                // 取得視圖
                $source = $this->getSourceView($path, $fileName, function ($dirFileName, $source) use ($fileName)
                {
                    // 超連結改為 html
                    $source = $this->replaceUrl($source, $fileName);

                    // 自動檔案之前的路徑
                    $this->createDeepFileDir($dirFileName);

                    if (\write_file($dirFileName, $source) === false)
                    {
                        throw new \Exception("write_file() Not Success.");
                    }
                });
            }
        }
        catch (\Exception $e)
        {
            die($e->getMessage());
        }

        return true;
    }

    public function replaceUrl(string $source, string $fileName)
    {
        $dom = \Sunra\PhpSimple\HtmlDomParser::str_get_html($source);

        $this->replaceEleBase($dom);

        $this->replaceAttrHref($dom, $fileName);
        // $this->replaceAttrSrc($dom, $fileName);
        // $this->replaceEleImg($dom, $fileName);
        // $this->replaceEleScript($dom, $fileName);

        $source = (string) $dom;

        return $source;
    }

    // protected function replaceAttrSrc(&$dom, string $fileName): object
    // {
    //     foreach ($dom->find('[src]') as $element)
    //     {
    //         if ($this->isHttp($element->src))
    //         {
    //             continue;
    //         }

    //         if ($this->isIncludeBasePath($element->src))
    //         {
    //             continue;
    //         }

    //         //相對路徑的轉換
    //         $from = '.'; // 根目錄
    //         $to = $fileName;

    //         $converter = new Converter($from, $to);
    //         $resultConvert = $converter->convert($element->src);

    //         // print_r([
    //         //     'from' => $from,
    //         //     'to' => $to,
    //         //     'file' => $element->src,
    //         //     'resultConvert' => $resultConvert,
    //         // ]);

    //         $element->src = $resultConvert;
    //     }

    //     return $dom;
    // }

    // 放置資源
    public function putAssets()
    {
        $assets = $this->config->assets;

        foreach ($assets as $key => $path)
        {
            $path = trim($path, "\ /");
            $to   = $this->config->put . "\\" . $path;
            $this->createDeepDir($to);

            $this->smartCopy($path, $to,
                [
                    'folderPermission' => '0755',
                    'filePermission'   => '0644',
                ]);
        }
    }

    // 自動建立深度路徑
    public function createDeepDir($dir)
    {
        $levelBox = $this->levelPath($dir);
        foreach ($levelBox as $dirName)
        {
            $this->smart_create_dir_file('dir', $dirName, '0755');
        }
    }

    // 自動建立檔案之前的路徑
    public function createDeepFileDir($dirFileName)
    {
        $levelBox = $this->levelPath($dirFileName);

        // 陣列倒數第二個是最後的路徑
        $endPrev = $levelBox[count($levelBox) - 2];

        $this->createDeepDir($endPrev);
    }

    // 取得視圖 HTML
    private function getSourceView(string $path, string $fileName, callable $callback): string
    {
        $fileName = trim($fileName, "\ /");
        $this->replaceSysteSeparator($fileName);

        // CURL 利用路由取得試圖
        $dirFileName = $this->config->put . DIRECTORY_SEPARATOR . $fileName;
        $url         = site_url($path);
        $source      = \file_get_contents($url);

        $callback($dirFileName, $source);
        return $source;
    }

    // 轉換成系統路徑斜線
    private function replaceSysteSeparator(string &$str)
    {
        $str = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $str);
    }

    // 把路徑轉成階層陣列，由小到大代表路徑越來越深
    private function levelPath(string $dir): array
    {
        $levelBox = [];
        $levelMix = '';

        $this->replaceSysteSeparator($dir);

        $box = \explode(DIRECTORY_SEPARATOR, $dir);
        foreach ($box as $segment)
        {
            $levelMix .= $segment . DIRECTORY_SEPARATOR;
            $levelBox[] = trim($levelMix, DIRECTORY_SEPARATOR);
        }

        return $levelBox;
    }

    // 替換標籤 <base>
    protected function replaceEleBase(&$dom): object
    {
        foreach ($dom->find('base') as $element)
        {
            $element->href = "";
        }

        return $dom;
    }

    // 替換網址
    protected function replaceAttrHref(&$dom, string $fileName): object
    {
        foreach ($dom->find('[href]') as $element)
        {
            // 不轉換 http:// or https://
            if ($this->isHttp($element->href))
            {
                continue;
            }

            // 轉換 'welcome/index' => 'http://localhost/ci-prototype/welcome/index'
            if ($this->isIncludeBasePath($element->href))
            {
                continue;
            }

            // 沒有 href 屬性
            if (!isset($element->href) or empty($element->href))
            {
                continue;
            }

            // 若不是超連結
            if (!in_array($element->tag, ['a']))
            {
                continue;
            }

            // 若是 Hash
            if (in_array($element->href, ['#']))
            {
                continue;
            }

            // 尋找匹配的轉換檔案
            if (!isset($this->config->pages[$element->href]))
            {
                throw new \Exception("Error！replaceAttrHref: '{$element->href}' not in config prototype.php");
            }

            $element->href = $this->config->pages[$element->href];
        }

        return $dom;
    }

    private function isIncludeBasePath($href)
    {
        $count = substr_count($href, site_url());

        return ($count > 0) ? true : false;
    }

    private function isHttp($href)
    {
        $countHttp  = substr_count($href, "http://");
        $countHttps = substr_count($href, "https://");
        return ($countHttp > 0 or $countHttps > 0) ? true : false;
    }

    // 刪除已經存在的放置路徑
    public function cleanDir(): bool
    {
        $base = $this->config->put;

        $del = new \Jsnlib\Del();
        $del->get($base);
        $deleteResult = $del->all();

        return $deleteResult;
    }

    public function createDir(): bool
    {
        $base = $this->config->put;

        $trimBase = trim($base, "\ /");

        try
        {
            $this->smart_create_dir_file('dir', $trimBase, '0755');
        }
        catch (Exception $e)
        {
            echo $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * 聰明的複製檔案或是目錄
     * 例如 smartCopy('test.php', 'test2.php'); 或   smartCopy('dirA', 'dirB');
     * 來自
     * http://php.net/manual/en/function.copy.php
     *
     * Copy file or folder from source to destination, it can do 
     * recursive copy as well and is very smart 
     * It recursively creates the dest file or directory path if there weren't exists 
     * Situtaions : 
     * - Src:/home/test/file.txt ,Dst:/home/test/b ,Result:/home/test/b -> If source was file copy file.txt name with b as name to destination 
     * - Src:/home/test/file.txt ,Dst:/home/test/b/ ,Result:/home/test/b/file.txt -> If source was file Creates b directory if does not exsits and copy file.txt into it 
     * - Src:/home/test ,Dst:/home/ ,Result:/home/test/** -> If source was directory copy test directory and all of its content into dest      
     * - Src:/home/test/ ,Dst:/home/ ,Result:/home/**-> if source was direcotry copy its content to dest 
     * - Src:/home/test ,Dst:/home/test2 ,Result:/home/test2/** -> if source was directoy copy it and its content to dest with test2 as name 
     * - Src:/home/test/ ,Dst:/home/test2 ,Result:->/home/test2/** if source was directoy copy it and its content to dest with test2 as name 
     * @todo 
     *     - Should have rollback technique so it can undo the copy when it wasn't successful 
     *  - Auto destination technique should be possible to turn off 
     *  - Supporting callback function 
     *  - May prevent some issues on shared enviroments : http://us3.php.net/umask 
     * @param $source //file or folder 
     * @param $dest ///file or folder 
     * @param $options //folderPermission,filePermission 
     * @return boolean 
     */ 
    public function smartCopy($source, $dest, $options=array('folderPermission'=>0755,'filePermission'=>0755)) 
        { 
            $result=false; 
            
            if (is_file($source)) { 
                if ($dest[strlen($dest)-1]=='/') { 
                    if (!file_exists($dest)) { 
                        cmfcDirectory::makeAll($dest,$options['folderPermission'],true); 
                    } 
                    $__dest=$dest."/".basename($source); 
                } else { 
                    $__dest=$dest; 
                } 
                $result=copy($source, $__dest); 
                chmod($__dest,$options['filePermission']); 
                
            } elseif(is_dir($source)) { 
                if ($dest[strlen($dest)-1]=='/') { 
                    if ($source[strlen($source)-1]=='/') { 
                        //Copy only contents 
                    } else { 
                        //Change parent itself and its contents 
                        $dest=$dest.basename($source); 
                        @mkdir($dest); 
                        chmod($dest,$options['filePermission']); 
                    } 
                } else { 
                    if ($source[strlen($source)-1]=='/') { 
                        //Copy parent directory with new name and all its content 
                        @mkdir($dest,$options['folderPermission']); 
                        chmod($dest,$options['filePermission']); 
                    } else { 
                        //Copy parent directory with new name and all its content 
                        @mkdir($dest,$options['folderPermission']); 
                        chmod($dest,$options['filePermission']); 
                    } 
                } 

                $dirHandle=opendir($source); 
                while($file=readdir($dirHandle)) 
                { 
                    if($file!="." && $file!="..") 
                    { 
                         if(!is_dir($source."/".$file)) { 
                            $__dest=$dest."/".$file; 
                        } else { 
                            $__dest=$dest."/".$file; 
                        } 
                        //echo "$source/$file ||| $__dest<br />"; 
                        $result = $this->smartCopy($source."/".$file, $__dest, $options); 
                    } 
                } 
                closedir($dirHandle); 
                
            } else { 
                $result=false; 
            } 
            return $result; 
        } 

    /**
     * 自動建立連續的路徑或路徑+檔案
     * @param    $type     檔案路徑 file | 路徑 dir
     * @param    $dir_name  路徑位置
     * @param    $mode      權限
     * @return   bool
     */
    public function smart_create_dir_file($type = "file", $dir_name, $mode = 0777)
    {
        $dirs  = explode(DIRECTORY_SEPARATOR, $dir_name);
        $dir   = '';
        $total = count($dirs);

        foreach ($dirs as $key => $part) 
        {

            // 最後一個？
            if ($key + 1 === $total)
            {
                $file = $dir . $part;

                if ($type == "file")
                {
                    $result = file_put_contents($file, NULL);
                    return $result === false ? false : true;
                }
                else if ($type == "dir")
                {
                    // (A錨點)
                    $dir .= $part . DIRECTORY_SEPARATOR;

                    if (!is_dir($dir) && strlen($dir) > 0)
                    {
                        return mkdir($dir, $mode);
                    }
                }
            }
            else
            {
                // 同 (A錨點)

                $dir .= $part . DIRECTORY_SEPARATOR;

                if (!is_dir($dir) && strlen($dir) > 0)
                {
                    mkdir($dir, $mode);
                }
            }


            
        }
    }
}

/* End of file Prototype.php */
/* Location: ./application/libraries/Prototype.php */
