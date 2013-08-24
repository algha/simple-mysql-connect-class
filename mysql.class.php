<?php 
  class mysql{
	  private $db_host;
	  private $db_user;
	  private $db_pass;
	  private $db_name;
	  private $db_pre;
	  public  $link;
	  public function __construct($db_host,$db_user,$db_pass,$db_name,$db_pre){
		  $this->db_host = $db_host;
		  $this->db_user = $db_user;
		  $this->db_pass = $db_pass;
		  $this->db_name = $db_name;
		  $this->db_pre  = $db_pre;
		  $this->connect_to_server();
	 }
	private function connect_to_server(){
		if(!$this->link = mysql_connect($this->db_host,$this->db_user,$this->db_pass)){
			$this->halt("can not connect to the server : ".$this->db_host);
		}
		if(!mysql_select_db($this->db_name,$this->link)){
			$this->halt("cat not found database name: ".$this->db_name);
			return false;
			}	
		mysql_query("SET NAMES UTF8");	
		return $this->link;
		}
	private function execute($sql){
		if(!is_resource($this->link)){
			$this->connect_to_server();
			}
		$sql = str_replace("#@_",$this->db_pre,$sql);	
		$query =  mysql_query($sql,$this->link);
		if(!$query){ $this->halt("SQL error : ".$sql);}
		return $query;
		}
	public function _execute($sql){
		return mysql_query($sql);	
		}	
	public function query($sql){
		return $this->execute($sql);
		}
	public function insert($table,$data,$return_insert_id = false,$replace=false){
		if($table == "" || !is_array($data) || count($data) == 0){
			return false;
		}
		$keysdata   = array_keys($data);
		$valuesdata = array_values($data);	
		array_walk($keysdata,array($this,"add_special_char"));
		array_walk($valuesdata,array($this,"escape_string"));
		$keys   = implode(",",$keysdata);
		$values = implode(",",$valuesdata);
		$cmd = $replace ? 'REPLACE INTO ' : 'INSERT INTO ';
		$sql = $cmd.$table.' ('.$keys.')VALUES('.$values.')';
		$query = $this->execute($sql);
		return $return_insert_id ? $this->insert_id() : $query;
		}
	public function update($table,$data,$where=''){
		  if($table == '' or $where == '') {
			return false;
		  }
		  $where  = ' WHERE '.$where;
		  $field = "";
		  if(is_string($data) && $data != ""){
			  $field = $data;
			  }else if(is_array($data) && count($data) > 0){
				$fields = array();
				foreach($data as $k=>$v){
					$fields[] = $this->add_special_char($k)."=".$this->escape_string($v,'',1);
					} 
					$field = implode(",",$fields); 
			  }else{
				return false;  
			  }
			  $sql = "UPDATE ".$table." SET ".$field.$where;
			return $this->execute($sql);  
	    }				
	public function select($data, $table, $where = '',  $order = '', $group = '', $limit = '',$key = '') {
		$where = $where == '' ? '' : ' WHERE '.$where;
		$order = $order == '' ? '' : ' ORDER BY '.$order;
		$group = $group == '' ? '' : ' GROUP BY '.$group;
		$limit = $limit == '' ? '' : ' LIMIT '.$limit;
		$field = explode(',', $data);
		array_walk($field, array($this, 'add_special_char'));
		$data = implode(',', $field);

		$sql = 'SELECT '.$data.' FROM '.$table.' '.$where.$group.$order.$limit;
		$lists = $this->execute($sql);	
		$datalist = array();
		while(($rs = $this->fetch_array($lists)) != false) {
			if($key) {
				$datalist[$rs[$key]] = $rs;
			} else {
				$datalist[] = $rs;
			}
		}
		$this->free_result();
		return $datalist;
		}	
	public function get_one($data, $table, $where = '', $order = '', $group = '') {
		$where = $where == '' ? '' : ' WHERE '.$where;
		$order = $order == '' ? '' : ' ORDER BY '.$order;
		$group = $group == '' ? '' : ' GROUP BY '.$group;
		$limit = ' LIMIT 1';
		$field = explode( ',', $data);
		array_walk($field, array($this, 'add_special_char'));
		$data = implode(',', $field);
		$sql = 'SELECT '.$data.' FROM '.$table.' '.$where.$group.$order.$limit;
		$listone = $this->execute($sql);
		$res = $this->fetch_array($listone);
		$this->free_result();
		return $res;
	}
	private function fetch_array($sql,$type=MYSQL_ASSOC){
		return mysql_fetch_array($sql,$type);
		}
	public function delete($table,$where){
		if($table == "" || $where == ""){
			return false;
			}
		$where = " WHERE ".$where;	
		$sql = "DELETE FROM ".$table.$where;
		return $this->execute($sql);
		}	
	public function insert_id(){
		return mysql_insert_id($this->link);
		}	
	public function affected_rows() {
		return mysql_affected_rows($this->link);
	}
	public function get_primary($table){
		$pri = $this->execute("SHOW COLUMNS FROM $table");
		return false;
		while ($r = $pri){
			if($r['Key'] == 'PRI') break;
			}
			return $r['Field'];
		}	
	public function get_fields($table){
		$fields = array();
		$f = $this->execute("SHOW COLUMNS FROM $table");
		while($f) {
			$fields[$f['Field']] = $f['Type'];
		}
		return $f;
		}	
   public function list_tables() {
		$tables = array();
		$t = $this->execute("SHOW TABLES");
		while($r = $t) {
			$tables[] = $r['Tables_in_'.$t];
		}
		return $tables;
	}			
	public function free_result(){
		return @mysql_free_result($this->link);
		}	
	public function num_rows($table,$sql=""){
		$where = $sql ==""?"":"WHERE ";
		$num =  $this->execute("SELECT * FROM $table $where $sql");
		return mysql_num_rows($num);
		}	
	public function close() {
		if (is_resource($this->link)) {
			mysql_close($this->link);
		}
	}	
	public function halt($message="",$sql=""){
		$msg = "<p style='border:1px solid #f00; padding:5px;'>".$message;
		if($sql != ""){
			$msg .="<br />";
			$msg .="sql info : ".$sql."<br />";
			$msg .="sql error : ".mysql_error($this->link);
			}
		$msg .="</p>";
		echo $msg;
		exit();
		}	
    public function add_special_char(&$value) {
		if('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.') || false !== strpos ( $value, '`')) {

		} else {
			$value = '`'.trim($value).'`';
		}
		if (preg_match("/\b(select|insert|update|delete)\b/i", $value)) {
			$value = preg_replace("/\b(select|insert|update|delete)\b/i", '', $value);
		}
		return $value;
	}	
	public function escape_string(&$value, $key='', $quotation = 1) {
		if ($quotation) {
			$q = '\'';
		} else {
			$q = '';
		}
		$value = $q.$value.$q;
		return $value;
	}			  	  
 }
?>
