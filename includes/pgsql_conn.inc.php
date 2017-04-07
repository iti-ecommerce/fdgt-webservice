<?php
    class pgsql_conn extends PDO
    {
        private static $conn;
        private static $host;
        private static $dbT;
        private static $dbN;
        private static $user;
        private static $passwd;
        private $sqlcmd;
        private $dataSet;
        private $errorlog;

        /**
         * Constructor
         */
        public function __construct(){}
        /**
         * @return bool If it does connect, or not.
         * Chose static params and fn bc security issues
         */
        public static function init(){
            self::$host = "localhost";
            self::$dbT = "pgsql";
            self::$dbN = "dgt";
            self::$user  = "dgt";
            self::$passwd = "admin";
            try{
                self::$conn = new PDO(self::$dbT.':dbname='.self::$dbN.';host='.self::$host, self::$user, self::$passwd);
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return true;
            } catch(PDOException $err){
                return false;
            }
        }
        /**
         * @return mixed <b>DataSet</b>
         */
        public function getDataSet(){
            return $this->dataSet;
        }
        /**
         * @return mixed Last error triggered
         */
        public function getLastError(){
            return $this->errorlog;
        }
        /**
         * @param array $params <b>Array</b> that contains the sqlcmd
         * @param string $sqlcmd <b>SQL command</b> to execute
         * @param $select BOOLEAN. If it is a SELECT query, or not
         * @return bool Whether it was successful or not
         */
        public function execSQL($sqlcmd = null, $params = array(), $select = false){
            if (!is_null($sqlcmd)){ $this->sqlcmd = $sqlcmd; }
            try{
                $this->sqlcmd = self::$conn->prepare($this->sqlcmd);
                if ($this->sqlcmd->execute($params)) {
                    if ($select){
                        $this->dataSet = $this->sqlcmd->fetchAll(PDO::FETCH_ASSOC);
                        return $this->dataSet;
                    } else {
                        return true;
                    }
                } else {
                    return false;
                }
            }catch(PDOException $e) {
                $this->errorlog = $e->errorInfo;
                return false;
            }
        }
    }