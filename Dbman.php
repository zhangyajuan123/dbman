<?php

/**
 * Class Dbman
 */
class Dbman
{

    private static $conf = null;

    public $errMsg;

    public $showup;

    public function __construct()
    {
        $pm = php_sapi_name();
        self::$conf = self::getConfig();
        if(self::$conf['web_access'] == true && $pm != 'cli') exit('Have no right to access !');
        $this->db_lnk = $this->_connect(self::$conf['db_host'],self::$conf['username'],self::$conf['password'],self::$conf['database']);
    }

    /**
     *关闭数据库连接
     */
    public function __destruct()
    {
        $this->close();
    }

    protected static function getConfig(){
        return require_once "config.php";
    }

    private function get_schema_file($schema = '')
    {
        $schema_file_array = array();
        if ($schema) {
            //todo 指定更新某一个表
        } else {
            $dir = self::$conf['file_path'];
            if (is_dir($dir) && $dh = opendir($dir)) {
                //把指定目录下的schema文件信息放到一个数组里面
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                        $schema_file_array[basename($file, ".php")] = require $dir . '/' . $file;
                    }
                }
                closedir($dh);

            }
        }

       return $schema_file_array;
    }

    private function get_create_table_sql($tablename = '',$arr = array())
    {
        //字段信息
        foreach ((array)$arr['fields'] as $k => $v) {
            if ($v && is_array($v)) if ($v['name'] == $k) $rows[] = '`' . $v['name'] . '` ' . $this->get_column_define($v);
        }
        //索引信息
        foreach ((array)$arr['index'] as $k => $v) {
            if ($v && is_array($v)) if ($v['name'] == $k) $rows[] = $this->get_index_define($v);
        }
        $sql = 'CREATE TABLE `'.$tablename."` (\n\t".implode(",\n\t",$rows)."\n)";
        //存储引擎
        $engine = isset($arr['engine'])?$arr['engine']:'InnoDB';
        $sql .= 'ENGINE = '.$engine.' DEFAULT CHARACTER SET utf8 ';
        //表备注
        $sql .= isset($arr['comment'])? ("COMMENT='".$arr['comment']."';"):';';

        return $sql;
    }

    private function add_index_sql($tablename='',$info=array())
    {
        $sql = "ALTER TABLE `{$tablename}` ADD " . $this->get_index_define($info);
        return $sql;
    }

    protected function get_index_define($val=array())
    {
        $_index = ' ';
        if(isset($val['name']) && $val['name'])
        {
            $type = isset($val['type']) && $val['type'] ? $val['type'] : 'normal';
            $_index .= ($type == 'normal' ? '' : $type) . ' KEY `' . $val['name'] .'` ';
        }

        $_index .= ' (';
        if(isset($val['fields']) && $val['fields'] && is_array($val['fields']))
        {
            foreach($val['fields'] as $v)
            {
                $_index .= '`' . $v . '`,';
            }
            $_index = substr($_index,0,-1);
        }
        elseif(isset($val['fields']) && $val['fields'] && !is_array($val['fields']))
        {
            $_index .= '`' . $val['fields'] . '`';
        }
        else
        {
            return false;
        }
        $_index .= ' )';
        return $_index;
    }

    protected function get_column_define($v)
    {
        $str = '';
        if (isset($v['type']) && $v['type']) {
            $str .= ' ' . $v['type'];
        } else {
            $str .= ' varchar(255)';
        }

        if (isset($v['notnull']) && $v['notnull'] == false) {
            $str .= ' not null';
        }

        if (isset($v['primary']) && $v['primary']) {
            $str .= ' PRIMARY KEY';
        }
        if (isset($v['autoinc']) && $v['autoinc']) {
            $str .= ' AUTO_INCREMENT';
        }

        if (isset($v['default'])) {
            if ($v['default'] === null) {
                $str .= ' default null';
            } elseif (is_string($v['default'])) {
                $str .= ' default \'' . $v['default'] . '\'';
            } else {
                $str .= ' default ' . $v['default'];
            }
        }
        if (isset($v['comment'])) {
            $str .= ' comment \'' . $v['comment'] . '\'';
        }
        return $str;
    }

    /**
     * 检查数据是否合法
     * //TODO
     * @param array $data
     * @return bool
     */
    private function __checkRightful($data=array(),&$errMsg=''){
        $err = false;
        foreach((array)$data['fields'] as $k=>$v){
            if($k != $v['name']){
                $errMsg = $k.' Fields are not legal !';
                $err = true;
            }
        }
        foreach((array)$data['index'] as $k=>$v){
            if($k != $v['name']){
                $errMsg = $k.' Index is not legal !';
                $err = true;
            }
        }

        return $err;
    }

    public function update($showup=false){
        if($showup)$this->showup = true;

        $db_arr = $this->get_schema_file();
        if($db_arr && is_array($db_arr)){
            foreach($db_arr as $k=>$v){
                $errMsg = NULL;
                //数据不合法，先跳出去
                if($this->__checkRightful($v,$errMsg))
                {
                    echo $errMsg.PHP_EOL;
                    continue;
                }

                $table_exists = $this->table_exists($k);
                if($table_exists && $v['version'] != '-1'){
                    //更新表
                    $fruit = $this->__updateTable($v,$k);
                    if($fruit)foreach($fruit as $sql){
                        $this->execsql($sql,$errMsg,$k);
                    }
                }elseif($table_exists && $v['version'] == '-1'){
                    //删除表
                    $sql = $this->drop_table($k);
                    $this->execsql($sql,$errMsg,$k);
                }elseif(empty($table_exists) && $v['version'] != '-1'){
                    //创建表
                    $sql = $this->get_create_table_sql($k,$v);
                    $this->execsql($sql,$errMsg,$k);

                }
            }
        }
        echo PHP_EOL.'Update complete.!'.PHP_EOL;
    }

    private function __diff($fileRow=array(),$tabRow=array(),$class='fields')
    {
        $update = false;
        switch($class)
        {
            case 'index':
                if(isset($fileRow['type']) && $fileRow['type'] && $fileRow['type'] != $tabRow['type']){
                    $update = true;
                }
                if($fileRow['fields'] != $tabRow['fields']){
                    $update = true;
                }
                if(isset($fileRow['method']) && $fileRow['method'] && $fileRow['method'] != $tabRow['method']){
                    $update = true;
                }
                break;
            default:
                if($fileRow['type'] != $tabRow['type']){
                    $update = true;
                }
                if($fileRow['notnull'] != $tabRow['notnull']){
                    $update = true;
                }
                if($fileRow['default'] != $tabRow['default']){
                    $update = true;
                }
                if($fileRow['primary'] != $tabRow['primary']){
                    $update = true;
                }
                if($fileRow['autoinc'] != $tabRow['autoinc']){
                    $update = true;
                }
                if(isset($fileRow['comment']) && $fileRow['comment'] != $tabRow['comment']){
                    $update = true;
                }
                break;
        }
        return $update;
    }

    /**
     * 更新表
     * @param array $fileInfo
     * @param string $tabName
     * @return array
     */
    private function __updateTable($fileInfo=array(),$tabName='')
    {
        $tabInfo = $this->getFields($tabName);
        $sqlRows = array();
        foreach((array)$fileInfo as $k=>$v)
        {
            if($k == 'fields')foreach ((array)$v as $ck => $cv)
            {
                if(!isset($tabInfo['fields'][$ck]))
                {
                    $sqlRows[] = $this->add_column($tabName,$cv);
                }
                elseif(isset($tabInfo['fields'][$ck]) && $this->__diff($cv,$tabInfo['fields'][$ck]))
                {
                    $sqlRows[] = $this->update_column($tabName,$cv);
                }
            }
            if($k == 'index')foreach ((array)$v as $ik => $iv)
            {
                if(!isset($tabInfo['index'][$ik]))
                {
                    $sqlRows[] = $this->add_index_sql($tabName,$iv);
                }
                elseif(isset($tabInfo['index'][$ik]) && $this->__diff($iv,$tabInfo['index'][$ik],'index'))
                {
                    //$sqlRows[] = $this->update_column($tabName,$cv);
                    $sqlRows[] = $this->drop_index_sql($tabName,$iv['name']);
                    $sqlRows[] = $this->add_index_sql($tabName,$iv);//add_index_sql
                }
            }
            //是否更新引擎和备注
            /*if($k == 'engine' && $v != $tabInfo['engine'])
            {
                $sqlRows[] = 'alter table `'.$tabName.'` engine='.$v.';';
            }*/
            if($k == 'comment' && $v != $tabInfo['comment'])
            {
                $sqlRows[] = 'ALTER TABLE `'.$tabName.'` COMMENT "'.$v.'";';
            }
        }

        foreach((array)$tabInfo as $k=>$v){
            if($k == 'fields')foreach ((array)$v as $ck => $cv)
            {
                if(!isset($fileInfo['fields'][$ck]))
                {
                    $sqlRows[] = $this->drop_column($tabName,$cv);
                }
            }
            if($k == 'index')foreach ((array)$v as $ik => $iv)
            {
                if(!isset($fileInfo['index'][$ik]))
                {
                    $sqlRows[] = $this->drop_index_sql($tabName,$iv['name']);
                }
            }
        }
        return $sqlRows;
    }

    private function table_exists($tablename=''){
        $database = self::$conf['database'];
        $sql = "select TABLE_NAME AS tablename from INFORMATION_SCHEMA.TABLES where TABLE_SCHEMA='".$database."' and TABLE_NAME='".$tablename."'";
        return $this->getRow($sql);
    }

    private function drop_table($tablename=''){
        $sql = "DROP TABLE IF EXISTS {$tablename}";
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function drop_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` drop column `{$column['name']}`";
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function add_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` add column  ".$column['name'].' '.$this->get_column_define($column);
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function update_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` MODIFY COLUMN `".$column['name'].'` '.$this->get_column_define($column);
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }

    private function drop_index_sql($tablename='',$index=''){
        $sql = "ALTER TABLE `{$tablename}` DROP INDEX `{$index}`";
        return $sql;
    }



    protected function _connect($host,$user,$passwd,$dbname){
        $lnk = @mysql_connect($host,$user,$passwd) or die("Unable to connect to the database");
        mysql_select_db( $dbname, $lnk );
        return $lnk;
    }

    protected function execsql($sql='',&$errMsg='',$table_name=''){
        //return false;
        $db_lnk = $this->db_lnk;
        $this->_debug_log($sql);
        if($this->showup) echo $sql.PHP_EOL;
        if($rs = mysql_query($sql,$db_lnk)){
            return array('rs'=>$rs,'sql'=>$sql);
        }else{
            $this->errMsg[$table_name] = mysql_error($db_lnk);
            return false;
        }

    }

    protected function getRow($sql='',&$errMsg=''){
        $rs = $this->execsql($sql, $errMsg);
        if($rs['rs']){
            $data = array();
            while($row = mysql_fetch_assoc($rs['rs'])){
                $data[]=$row;
            }
            mysql_free_result($rs['rs']);
            return (!empty($data) && $data) ? $data[0] : array();
        }else{
            return false;
        }
    }

    protected function getList($sql='',&$errMsg=''){
        $rs = $this->execsql($sql, $errMsg);
        if($rs['rs']){
            $data = array();
            while($row = mysql_fetch_assoc($rs['rs'])){
                $data[]=$row;
            }
            mysql_free_result($rs['rs']);
            return $data;
        }else{
            return false;
        }
    }

    protected function close(){
        if($this->db_lnk && mysql_close($this->db_lnk)){
            $this->db_lnk = null;
            return true;
        }
        return false;
    }

    protected function _debug_log($data)
    {
        if(self::$conf['debug_log']) error_log(date('Y-m-d H:i:s').' info : '.$data.PHP_EOL,3,self::$conf['log_path'].'dbman.'.date('Y-m-d').'.logs');
    }

    protected function buildDataBaseSchema($tables, $db)
    {

        $backups_path = self::$conf['backups_path'];
        //var_export($backups_path);die;
        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = $this->getFields($table);
            $content .= var_export($info, true) . ';';
            file_put_contents($backups_path . $table . '.php', $content);
        }
    }

    protected function getFields($tableName)
    {
        //$tableName = '`' . $tableName . '`';
        $sql = 'SHOW FULL COLUMNS FROM `' . $tableName.'`;';
        $result = $this->getList($sql);
        $info   = [];
        if ($result && is_array($result)) {
            foreach ($result as $key => $val) {
                $val                 = array_change_key_case($val);
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => $val['null'] === 'NO' ? false : true,
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                    'comment' => $val['comment'],
                ];
            }
        }
        $data['fields'] = $info;
        $data['index'] = $this->getTableIndex($tableName);
        $data['version'] = '1.0';
        $data['engine'] = 'innodb';
        $data['comment'] = $tableName;
        return $data;
    }

    private function __getFieldComment($tableName)
    {
        $sql = 'SHOW FULL COLUMNS  FROM `' . $tableName.'`;';
        $result = $this->getList($sql);
        return $result;
    }

    protected function getTables($dbName = '')
    {

        $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ;';
        $result = $this->getList($sql);
        $info   = [];
        //var_export($result);die;
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * 获取数据表的索引，不包括主键索引
     * @param string $tabName
     * @return array
     */
    public function getTableIndex($tabName = '')
    {

        $sql = "SHOW INDEX FROM `{$tabName}`;";
        $result = $this->getList($sql);
        $return = array();
        //var_dump($result);die;
        foreach((array)$result as $v)
        {
            if($v['Key_name'] != 'PRIMARY') {
                $return[$v['Key_name']]['name'] = $v['Key_name'];
                $idx_type = ($v['Non_unique'] == 1) ? ($v['Index_type'] == 'FULLTEXT' ? 'FULLTEXT' : 'normal') : 'unique';
                $return[$v['Key_name']]['type'] = $idx_type;
                @$return[$v['Key_name']]['fields'] .= $v['Column_name'] . ',';
                $return[$v['Key_name']]['method'] = '';
            }
        }
        #---------------解决历史遗留问题start---------------#
        foreach((array)$return as $k=>$val)
        {
            $fields = substr($val['fields'],0,-1);
            unset($return[$k]['fields']);
            if(substr_count($fields,',') > 0)
            {
                $return[$k]['fields'] = explode(',',$fields);
            }
            else
            {
                $return[$k]['fields'] = $fields;
            }
        }
        #---------------解决历史遗留问题 end---------------#
        //var_dump($return);
        return $return;
    }

    public function backups(){
        $database = self::$conf['database'];
        $tables   = $this->getTables($database);
        $this->buildDataBaseSchema($tables,$database);
        echo PHP_EOL.'The backup data table'.PHP_EOL;
    }
}

