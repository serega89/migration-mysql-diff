<?php

define('N', "\n\r");

class MySQLDump {
	var $tables = array();
	var $connected = false;
	var $output;
	var $droptableifexists = false;
	var $mysql_error;
	var $diff1;
	var $diff2;
	var $db;
	
function connect($host,$user,$pass,$db) {	
	$return = true;
	$conn = @mysql_connect($host,$user,$pass);
	if (!$conn) { $this->mysql_error = mysql_error(); $return = false; }
	$seldb = @mysql_select_db($db);
	if (!$conn) { $this->mysql_error = mysql_error();  $return = false; }
	$this->connected = $return;
	$this->db = $db;
	return $return;
}

function list_tables() {
	$return = true;
	if (!$this->connected) { $return = false; }
	$this->tables = array();
	$sql = mysql_query("SHOW TABLES");
	while ($row = mysql_fetch_array($sql)) {
		array_push($this->tables,$row[0]);
	}
	return $return;
}

function list_values($tablename) {
	$sql = mysql_query("SELECT * FROM $tablename");
	$this->output .= "\n\n-- Dumping data for table: $tablename\n\n";
	while ($row = mysql_fetch_array($sql)) {
		$broj_polja = count($row) / 2;
		$this->output .= "INSERT INTO `$tablename` VALUES(";
		$buffer = '';
		for ($i=0;$i < $broj_polja;$i++) {
			$vrednost = $row[$i];
			if (!is_integer($vrednost)) { $vrednost = "'".addslashes($vrednost)."'"; } 
			$buffer .= $vrednost.', ';
		}
		$buffer = substr($buffer,0,count($buffer)-3);
		$this->output .= $buffer . ");\n";
	}	
}

function dump_table($tablename) {
	$this->output = "";
	$this->get_table_structure($tablename);	
	$this->list_values($tablename);
}

function q($tablename) {
	$FOREIGN='';
	$this->output = '';
	$res =mysql_query("SHOW CREATE TABLE $tablename");
	$row = mysql_fetch_row($res);
	$table = $row[1];
	preg_match_all("/(,?\s*CONSTRAINT\s+`(\w+)`\s.*`(\w+)`.*`(\w+)`\)\s*([\s\w]+).*)$/mi", $table, $foo);
	for ($i=0;$i < count($foo[0]);$i++) {
 		$FOREIGN .= N.'ALTER TABLE `'.$tablename.'` ADD FOREIGN KEY (`'.$foo[2][$i].'`) REFERENCES `'.$this->db.'`.`'.$foo[3][$i].'`(`'.$foo[4][$i].'`) '.$foo[5][$i].';';
	}
	$table = preg_replace("/(,?\s*CONSTRAINT\s+`.*)$/mi", '', $table);
	$table = preg_replace("/(AUTO_INCREMENT=\d+)/mi", 'AUTO_INCREMENT=0', $table);
	$this->output .= $FOREIGN;
	$this->output .= N.$table;
	$this->list_values($tablename);
}

function get_table_structure($tablename) {
	$this->output .= "\n\n-- Dumping structure for table: $tablename\n\n";
	if ($this->droptableifexists) { $this->output .= "DROP TABLE IF EXISTS `$tablename`;\nCREATE TABLE `$tablename` (\n"; }
		else { $this->output .= "CREATE TABLE `$tablename` (\n"; }
	$sql = mysql_query("DESCRIBE $tablename");
	$this->fields = array();
	while ($row = mysql_fetch_array($sql)) {
		$name = $row[0];
		$type = $row[1];
		$null = $row[2];
		if (empty($null)) { $null = "NOT NULL"; }
		$key = $row[3];
		if ($key == "PRI") { $primary = $name; }
		$default = $row[4];
		$extra = $row[5];
		if ($extra !== "") { $extra .= ' '; }
		$this->output .= "  `$name` $type $null $extra,\n";
	}
	$this->output .= "  PRIMARY KEY  (`$primary`)\n);\n";
}

}
?>