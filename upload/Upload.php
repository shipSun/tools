<?php
class Uploads {
    const UPLOAD_ERR_OK = UPLOAD_ERR_OK;
    const UPLOAD_ERR_INI_SIZE = UPLOAD_ERR_INI_SIZE;
    const UPLOAD_ERR_FORM_SIZE = UPLOAD_ERR_FORM_SIZE;
    const UPLOAD_ERR_PARTIAL = UPLOAD_ERR_PARTIAL;
    const UPLOAD_ERR_NO_FILE = UPLOAD_ERR_NO_FILE;
    const UPLOAD_ERR_NO_TMP_DIR = UPLOAD_ERR_NO_TMP_DIR;
    const UPLOAD_ERR_CANT_WRITE = UPLOAD_ERR_CANT_WRITE;
    const UPLOAD_ERR_EXTENSION = UPLOAD_ERR_EXTENSION;
    const UPLOAD_ERR_FILE_TYPE = 9;
    const UPLOAD_ERR_CREATE_UPLOAD_DIR = 10;
    const UPLOAD_ERR_CREATE_FILE = 11;
    
    protected $file;
    protected $fileName='';
    protected $error = array();
    
    public $allowFileType = array();
    public $filePath = '';
    public $debug = false;
    
    public function __construct($filePath=FALSE, $allowFileType=FALSE, $debug=false){
        if($filePath){
           $this->filePath = $filePath; 
        }
        if($allowFileType){
            $this->allowFileType = $allowFileType;
        }
        if($debug){
            $this->debug = TRUE;
        }
    }
    public function upload($file, $fileName=false){
        try{
            if(isset($file[0])){
                $file['name'] = $file[0]['name'];
                $file['type'] = $file[0]['type'];
                $file['size'] = $file[0]['size'];
                $file['tmp_name'] = $file[0]['tmp_name'];
                $file['error'] = $file[0]['error'];
            }
            $this->file = $file;
            
            if($fileName){
                $this->fileName = $fileName;
            }
            $newfilePath = $this->checkStatus($this->file['error']);
            return $newfilePath;
        }catch (ErrorException $e){
            $this->error($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            if($this->debug){
                $this->debug($e);
            }
        }
    }
    public function batchUpload($files, $fileName = array()){
        $newfilePath = array();
        foreach($files as $key=>$file){
            $newFileName = '';
            if(isset($fileName[$key])){
                $newFileName = $fileName[$key];
            }
            $newfilePath[] = $this->upload($file, $newFileName);
        }
        return $newfilePath;
    }
    public function formatBatchData($files){
        $data = array();
        foreach($files as $key=>$val){
            foreach($val as $k=>$v){
                $data[$k][$key] = $v;
            }
        }
        return $data;
    }
    protected function checkStatus($fileError){
        switch ($fileError){
            case UPLOAD_ERR_INI_SIZE:
                throw new ErrorException('上传文件大小超过服务器允许上传的最大值', self::UPLOAD_ERR_INI_SIZE);
                break;
            case UPLOAD_ERR_FORM_SIZE:
                throw new ErrorException('上传文件大小超过HTML表单中隐藏域MAX_FILE_SIZE选项指定的值', self::UPLOAD_ERR_FORM_SIZE);
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new ErrorException('文件只有部分被上传', self::UPLOAD_ERR_PARTIAL);
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new ErrorException('没有文件上传', self::UPLOAD_ERR_NO_FILE);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new ErrorException('没有找不到临时文件夹', self::UPLOAD_ERR_NO_TMP_DIR);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new ErrorException('文件写入失败', self::UPLOAD_ERR_CANT_WRITE);
                break;
            case UPLOAD_ERR_EXTENSION:
                throw new ErrorException('文件上传扩展没有打开', self::UPLOAD_ERR_EXTENSION);
                break;
        }
        return $this->checkType($this->file['name'], $this->allowFileType);
    }
    protected function checkType($fileName, $allowFileType=array()){
        $fileType = explode('.', $fileName);
        if(!in_array($fileType[1], $allowFileType)){
            throw new ErrorException('不支持'.$fileType[1].'文件格式', self::UPLOAD_ERR_FILE_TYPE);
        }
        $fileType = $fileType[1];
        return $this->moveFile($this->file['tmp_name'], $this->getNewFilePath($this->fileName, $fileType));
    }
    protected function getNewFilePath($fileName, $fileType){
        if(!$fileName){
            $fileName = $this->autoFileName();
        }
        $fileName.= '.'.$fileType;
        return $this->filePath.DIRECTORY_SEPARATOR.$fileName;
    }
    protected function autoFileName(){
        return time();
    }
    protected function moveFile($filePath, $moveFilePath){
        if(!is_dir($this->filePath)){
            mkdir($this->filePath, 0777, true);
        }
        if(!is_dir($this->filePath)){
            throw new ErrorException('创建目录'.$this->filePath.'失败', self::UPLOAD_ERR_CREATE_UPLOAD_DIR);
        }
        $updateStatus = move_uploaded_file($filePath, $moveFilePath);
        if(!$updateStatus){
            throw new ErrorException('文件上传失败', self::UPLOAD_ERR_CREATE_FILE);
        }
        if($this->debug){
            $this->debug();
        }
        $this->error(self::UPLOAD_ERR_OK, '上传成功');
        return $moveFilePath;
    }
    protected function error($errno, $errstr, $errfile='', $errline=0){
        $error['errno'] = $errno;
        $error['errstr'] = $errstr;
        $error['errfile'] = $errfile;
        $error['errline'] = $errline;
        $this->error[] = $error;
    }
    public function getError(){
        if(count($this->error)==1){
            return $this->error[0];
        }
        return $this->error;
    }
    protected function debug($e=false){
        $trace = debug_backtrace(FALSE);
        
        $str = '-------------------------文件上传开始---------------------------'.PHP_EOL;
        if($e){
            $trace = $e->getTrace();
            $str.= $e->getMessage().$e->getFile().'('.$e->getLine().')'.PHP_EOL;
            $str.= '--------------------------------------------------------'.PHP_EOL;
        }
        foreach($trace as $key=>$val){
            $str.= var_export($val,true).PHP_EOL;
        }
        $str.= '--------------------------请求网址------------------------------'.PHP_EOL;
        if(isset($_SERVER['REQUEST_URI'])){
            $str.='REQUEST_URI:'.$_SERVER['REQUEST_URI'].PHP_EOL;
        }
        if(!empty($_SERVER['HTTP_REFERER'])){
            $str.='HTTP_REFERER:'.$_SERVER['HTTP_REFERER'].PHP_EOL;
        }
        $str.= '-------------------------文件上传结束---------------------------'.PHP_EOL;
        $this->log($str);
    }
    protected function log($msg){
        file_put_contents('debug.log', $msg, FILE_APPEND);
    }
}
error_reporting(E_ALL);
header('content-type:text/html;charset=utf-8');
$uploads = new Uploads();
$uploads->debug = true;
$uploads->allowFileType = array('png');
$uploads->filePath = 'uploads';
$data = $uploads->batchUpload($uploads->formatBatchData($_FILES['file']), array('test','test1'));
var_dump($uploads->getError(), $data);