<?php
class Uploads {
    protected $file;
    protected $fileName='';
    
    public $allowFileType = array();
    public $filePath = '';
    public $debug = false;
    public $error = array();
    
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
        $this->error(UPLOAD_ERR_OK, '上传完成');
    }
    public function upload($file, $fileName=false){
        try{
            if(isset($file[0])){
                $file = $file[0];
            }
            $this->file = $file;
            
            if($fileName){
                $this->fileName = $fileName;
            }
            $newfilePath = $this->checkStatus($this->file['error']);
            return $newfilePath;
        }catch (ErrorException $e){
            $this->error($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
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
    protected function checkStatus($fileError){
        switch ($fileError){
            case UPLOAD_ERR_INI_SIZE:
                throw new ErrorException('上传文件大小超过服务器允许上传的最大值', UPLOAD_ERR_INI_SIZE);
                break;
            case UPLOAD_ERR_FORM_SIZE:
                throw new ErrorException('上传文件大小超过HTML表单中隐藏域MAX_FILE_SIZE选项指定的值', UPLOAD_ERR_FORM_SIZE);
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new ErrorException('文件只有部分被上传', UPLOAD_ERR_PARTIAL);
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new ErrorException('没有文件上传', UPLOAD_ERR_NO_FILE);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new ErrorException('没有找不到临时文件夹', UPLOAD_ERR_NO_TMP_DIR);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new ErrorException('文件写入失败', UPLOAD_ERR_CANT_WRITE);
                break;
            case UPLOAD_ERR_EXTENSION:
                throw new ErrorException('文件上传扩展没有打开', UPLOAD_ERR_EXTENSION);
                break;
        }
        return $this->checkType($this->file['name'], $this->allowFileType);
    }
    protected function checkType($fileName, $allowFileType=array()){
        $fileType = explode('.', $fileName);
        if(!in_array($fileType[1], $allowFileType)){
            throw new ErrorException('不支持'.$fileType[1].'文件格式');
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
            throw new ErrorException('创建目录'.$this->filePath.'失败');
        }
        $updateStatus = move_uploaded_file($filePath, $moveFilePath);
        if(!$updateStatus){
            throw new ErrorException('文件上传失败');
        }
        if($this->debug){
            $this->debug();
        }
        return $moveFilePath;
    }
    protected function error($errno, $errstr, $errfile='', $errline=0){
        $this->error['errno'] = $errno;
        $this->error['errstr'] = $errstr;
        $this->error['errfile'] = $errfile;
        $this->error['errline'] = $errline;
        
    }
    public function debug(){
        $trace = debug_backtrace(FALSE);
        $str = '-------------------------文件上传开始---------------------------'.PHP_EOL;
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
$data = $uploads->upload($_FILES['file'], 'test');
var_dump($uploads->error, $data);