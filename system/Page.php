<?php
namespace Pronto;
/**
 * Pronto Framework
 * @version 1.0
 * @author Juan Caser <caserjan@gmail.com>
 */

 define('ABSPATH', dirname(__DIR__));

// Create custom exception classes
class NotFoundException extends \Exception{}

class Page {

    /**
     * Resource path
     * @var string
     */
    protected $resources_path;

    /**
     * Layout
     * @var string
     */
    protected $layout = 'default';

    /**
     * Field variables
     * @var array
     */
    protected $fields = [];

    /**
     * Content
     * @var string
     */
    protected $content = '';

    /**
     * Mime types
     * @var array
     */
    protected $mime_types = [
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
    
        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
    
        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
    
        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
    
        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
    
        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
    
        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];    

    /**
     * Initialize
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->resources_path = $path;

    }

    /**
     * Set layout
     * @param string $layout
     * @return Pronto\Page
     */
    public function layout(string $layout)
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Getter
     * @param string $key
     * @return string
     */
    public function __get(string $key): string
    {
        if(isset($this->fields[$key])) return $this->fields[$key];
        return '';
    }

    /**
     * Setter
     * @param string $key
     * @param string
     */
    public function __set(string $key, $value)
    {
        $this->fields[$key] = $value;
    }

    /**
     * Check and return default if it doesnt exists
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function has(string $key, $default = null): mixed
    {
        if(isset($this->fields[$key])) $default = $this->fields[$key];
        return $default;
    }

    /**
     * Render page
     * @param string $uri
     * @param string $view
     * @returns string
     */
    public function render()
    {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = dirname($request_uri);
        $filename = pathinfo($request_uri, PATHINFO_FILENAME);
        $ext = pathinfo($request_uri, PATHINFO_EXTENSION);
        $type = empty($ext) ? 'page' : 'post';

        $page_caching = (defined('CACHE_PAGE') ? CACHE_PAGE : true);

        try {
            // Check if we are running cli-server
            if(php_sapi_name() == 'cli-server'){
                // Check if file we are accessing is an actual file in public directory
                // while this is just a work around this will consume resources on your machine
                // consider running pronto on an actual web server like XAMPP or docker
                $public_path = ABSPATH.'/public'.$path.'/'.$filename.'.'.$ext;
                if(!is_dir($public_path) && file_exists($public_path)){
                    $mime_type = (isset($this->mime_types[$ext]) ? $this->mime_types[$ext] : mime_content_type($public_path));
                    header("Content-Type: $mime_type");
                    exit(file_get_contents($public_path));
                }
            }
        
            // Check for cache files and delete those who have already expired
            if($type == 'page'){
                $find = [
                    $this->resources_path.'/pages'.$path.'/'.$filename.'/main.php',
                    $this->resources_path.'/pages'.$path.'/'.$filename.'/index.php',
                ];
            }else{
                $find = [
                    $this->resources_path.'/pages'.$path.'/'.$filename.'.php'
                ];    
            }

            foreach($find as $inc){
                if(file_exists($inc)){
                    header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
                    $cache_file = ABSPATH.'/storage/cache/pages/'.$type.'-'.md5($inc).'.php';
                    

                    if($page_caching && $this->is_cache_expired($cache_file)){
                        @unlink($cache_file);
                    }

                    if($page_caching && file_exists($cache_file)){
                        include($cache_file);
                    }else{
                        ob_start();
                        include($inc);
                        $this->content = ob_get_clean();

                        if($page_caching) ob_start();

                        include($this->resources_path.'/layout/'.$this->layout.'.php');
                        
                        if($page_caching){
                            $cache_content = ob_get_clean();
                            echo $cache_content;
                            // file_put_contents($cache_file, $cache_content);
                        }
                    }
                    exit();
                }
            }    
            throw new NotFoundException('Not Found');
        } catch (NotFoundException $e) {
            header($_SERVER['SERVER_PROTOCOL'].' 404 OK');
            if(file_exists($this->resources_path.'/pages/404.php')){
                include($this->resources_path.'/pages/404.php');
            }
        }
    }

    /**
     * Check if cache had expired
     * @param string $cache_file
     * @return bool
     */
    private function is_cache_expired($cache_file)
    {
        if(file_exists($cache_file)){
            $file_mod_time = filemtime($cache_file);
            $timenow = time();
            $days = 3600*(defined('CACHE_EXPIRATION') ? CACHE_EXPIRATION : 7); // 7 days default
            return ($file_mod_time <= $days);
        }
        return true;
    }

}