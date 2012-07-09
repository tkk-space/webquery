<?php
require_once realpath('./Slim/Slim.php');
require_once realpath('./vendor/RedBeanPHP/rb.php');
require_once realpath('./vendor/Slim_Extras/TwigView.php');

ini_set('error_reporting',E_ERROR | E_WARNING | E_PARSE);
//ini_set('display_errors', 0);

TwigView::$twigDirectory = './vendor/template_engine/lib/Twig/lib/Twig';
TwigView::$twigOptions = array('Twig_Extensions_Slim');

$app = new Slim(
	array(
	//'debug' => 'true',
	//'session.flash_key' => 'Keizu.flash_key',
	'view' => 'TwigView',
	//'templates.path' => realpath(__DIR__ . '/views'),
	'cookies.encrypt' => false,
	'session.handler' => null
));

// Grobal Data
$request = array();
$html = array();



$app = new Slim(array('view'=>'TwigView'));

/*
class PDOCSTM extends PDO {
	public function __construct($dsn) {
		parent::__construct($dsn);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('STMT', array($this)));
	}
}

class STMT extends PDOStatement {
	public $dbh;
	protected function __construct($dbh) {
		$this->dbh = $dbh;
	}
	function fetch($option=PDO::FETCH_ASSOC) {
		$row = parent::fetch($option);
		foreach ($row as $key=>$val) {
			$row[$key] = mb_convert_encoding($val, "UTF-8", "EUC-JP");
		}
		return $row;
	}
	function fetchAll($option=PDO::FETCH_ASSOC) {
		$rows = parent::fetchAll($option);
		foreach ($rows as $key => $row) {
			foreach ($row as $crm => $val) {
				$rows[$key][$crm] = mb_convert_encoding($val, "UTF-8", "EUC-JP");
			}
		}
		return $rows;
	}
	function execute($params=array()) {
		if (APPLICATION_ENV !== "production") {
			if (!preg_match("/SELECT/", $this->queryString)) {
				error_log("[SQL] " . $this->queryString . " Array::" . print_r($params,true));
			}
			else {
				error_log("[SQL] " . $this->queryString);
			}
			if (!empty($params)) {
				$params = App_Array::getEncodedData($params, "EUC-JP", "UTF-8");
			}
		}
		return parent::execute($params);
	}
}
*/
/*
// Error handler
$app->error('err');
function err(Exception $e){
	$code = $e->getCode();
	$data = 'HTTPエラー:';
	$data_mes = 'サーバー内部でエラーが発生しました';
	$status = 500;
	if ($e instanceof HttpException) {
		switch ($code) {
		case 403:
			$data_mes = 'お探しのページは表示できません';
			$status = 403;
			break;
		case 404:
			$data_mes = 'お探しのページは見つかりませんでした';
			$status = 404;
			break;
		case 405:
			$data_mes = '不正なアクセスです';
			$status = 405;
			break;
		}
	}
	error_print($data.$data_mes.'('.$status.')','',null);
}
*/

// 初期化作業
function db_init(){
	return create_db(
		array(
			"dbtype"=>$_POST["setting_connect_db"]
			,"ip"=>$_POST["setting_connect_ip"]
			,"port"=>$_POST["setting_connect_port"]
			,"db"=>$_POST["db_select"]
			,"user"=>$_POST["setting_connect_user"]
			,"pass"=>$_POST["setting_connect_pass"]
			,"charset"=>$_POST["setting_connect_char"]
			,"timeout"=>3
		)
	);
}

function ajax_db_option(){
	if($_POST["setting_connect_db"]=='mysql'){
		$db_sqlx="SELECT SCHEMA_NAME as datname FROM INFORMATION_SCHEMA.SCHEMATA";
	}else{
		$db_sqlx="SELECT datname FROM pg_database WHERE datname NOT IN ('template0','template1') ORDER BY datname;";
	}
	
	$pdb=run_sql_query($db_sqlx,__FUNCTION__);
	
	$db_opt_html='<option value=""></option>';
	while($row=$pdb->fetch()){
		$selected=($_POST["db_select"] == $row['datname'])?"selected":"";
		$db_opt_html.='<option value="'.$row['datname'].'" '.$selected.'>'.$row['datname'].'</option>';
	}
	
	result_print('DBに接続しました','',0,$db_opt_html);
}

function ajax_tbl_option(){
	$html=array();
	$tbl_sqlx = get_tbl_query();
	$pdb=run_sql_query($tbl_sqlx,__FUNCTION__);
	
	$type_rows=array();
	$typ_color = array('r'=>'#FFF','v'=>'#AFA','i'=>'#c9c' ,'S'=>'#9cc'	   );
	$typ_name  = array('r'=>'TABLE','v'=>'VIEW','i'=>'INDEX','S'=>'SEQUENCE');
	$form_types=$_POST["setting_tblsel_view_type"];
	
	// テーブルのオプション表示
	if(is_array($form_types)){
		$tbl_opt_html='<option value=""></option>';
		while($db_ary=$pdb->fetch()){
			$disabled='';
			$disp='';
			$type_rows[$db_ary[1]]++;
			if(!in_array($db_ary[1],$form_types)){ $disp='none';$disabled='disabled="disabled";'; }
			$selected='';
			if($_POST["tbl_select"] == $db_ary[0]){ $selected="selected"; }
			// テーブル以外の場合タイプ名を右につける
			if($db_ary['relkind']!='r'){ $db_ary['rows']=$db_ary['relkind']; }
			$tbl_opt_html.='<option '.$disabled.' style="display:'.$disp.'; background:'.$typ_color[$db_ary['relkind']].';"	 type="'.$db_ary['relkind'].'" value="'.$db_ary['relname'].'" '.$selected.'>'.$db_ary['relname'].'	 ('.$db_ary['rows'].')</option>';
		}
	}else{
		$tbl_opt_html='<option type="" value="">設定内のテーブル内容をチェックして下さい</option>';
	}
	
	result_print('DBを選択しました','',0,$tbl_opt_html);
}

function ajax_db_view(){
	if(trim($_POST["tbl_select"]) !== "" && $_POST["tbl_select"] !== "reading..."){
		$_POST["page_select"]=($_POST["page_select"]=='')?1:$_POST["page_select"];
		$_POST["limit_num"]=($_POST["limit_num"]=='')?10:$_POST["limit_num"];
		$sqlx='SELECT * FROM '.$_POST["tbl_select"];
		if($_POST["limit_num"] > 0 && $_POST["page_select"] > 0){
			$sqlx.=' OFFSET '.(int)((int)$_POST["page_select"] - 1)*(int)$_POST["limit_num"].' LIMIT '.$_POST["limit_num"];
		}else if($_POST["limit_num"] > 0){
			$sqlx.=' LIMIT '.$_POST["limit_num"];
		}
		$html=array();
		$html=get_table_html($sqlx);
		result_print('テーブルを表示しました',$sqlx,0,$html);
	}
}

function ajax_query_run(){
	$html=array();
	$sqlx=preg_replace("/\\\'/i","'",$_POST["query"]);
	$sqlxs=preg_split('/;/',$sqlx);
	$html[0]='';
	$html[1]='';
	$html[2]='';
	foreach ($sqlxs as $k => $sql ){
		if(trim($sql)!=''){
			$sql.=';';
			if(preg_match("/^[\s]*(SELECT)/i",$sql)){
				$table_html=get_table_html($sql);
				$html[0].=$table_html[0];
				$html[1].=$table_html[3];
			}else{
				run_sql_query($sql,'query_run');
				$html[0].="<font color=\"#ff0000\">$sql</font><br>";
				//$html[1].='';
				$html[1].='';
				$html[2].='';
			}
		}
	}
	result_print('クエリを実行しました',$sqlx,0,$html);
}

function get_pager(){
	// カウント・ページ数の計算
	if(!$_POST["limit_num"]){ $_POST["limit_num"]=10; }
	
	$rows=getdat("SELECT reltuples FROM pg_class WHERE relname='".$_POST['tbl_select']."'");
	$page_num=ceil($rows['reltuples']/$_POST["limit_num"]);
	$html_option_pages='';
	for($i=1;$i<=$page_num;$i++){
		if($_POST["page_select"] == $i){ $html_option_pages.='<option value="'.$i.'" selected>'.$i.'</option>';}
		else{ $html_option_pages.='<option value="'.$i.'">'.$i.'</option>'; }
	}
	$dat=array("rows"=>$rows['reltuples'],"page_opt_html"=>$html_option_pages,"page_num"=>$page_num);
	
	$start_row=(($_POST["page_select"] * $_POST["limit_num"])-$_POST["limit_num"] + 1);
	$end_row=($_POST["page_select"] * $_POST["limit_num"]);
	$page_info='['. $start_row .' - '.$end_row.' / '.$dat{rows}.']';
	$page_select='<select style="width:40px;" type="text" size="1" name="page_select" id="page_select" onchange="'.pager_ajax().'">'.$dat["page_opt_html"].'</select>/'.$dat["page_num"];
	//$page_button[]=create_input('button','&lt;&lt;','page_first',pager_onclick(-100));
	//$page_button[]=create_input('button','&lt;','page_back',pager_onclick(-1));
	//$page_button[]=create_input('button','&gt;','page_next',pager_onclick(1));
	//$page_button[]=create_input('button','&gt;&gt;','page_last',pager_onclick(100));
	$pager='<div>'.$page_button[0].$page_button[1].$page_info.$page_select.$page_button[2].$page_button[3].'</div>';
	
	return $pager;
}

function get_table_html($sqlx){
	$html=array();
	
	// フィールドの取得
	$col_dat=array();
	$pdb=run_sql_query($sqlx,__FUNCTION__.'col_dat');
	$i = 0;
	while ($column = $pdb->getColumnMeta($i)) {
		$col_dat[$i]['name']=$column['name'];
		$col_dat[$i]['native_type']=$column['native_type'];
		$col_dat[$i]['len']=$column['len'];
		$i++;
	}
	$col_dat['total_num']=$i;
	
	$col_html= "<option></option>";
	for($i=0;$i<$col_dat['total_num'];$i++){
		$selected=($_POST["col_select"] == $col_dat[$i]['name'])?"selected":'';
		$col_html.='<option value="'.$col_dat[$i]['name'].'" '.$selected.'>';
		// 名前 (型) [デフォルト値] <制約>
		$col_html.=$col_dat[$i]['name'];
		$col_html.='('.$col_dat[$i]['native_type'].')';
		//$col_html.='['.$col_dat[$i]['native_type'].']';
		//$col_html.='<'.$col_dat[$i]['native_type'].'>';
		$col_html.='</option>';
	}
	
	// テーブル最初の行 フィールド名
	$table_field='';
	for($i=0;$i<$col_dat['total_num'];$i++){
		$type='';
		$selected=($_POST["col_select"] == $col_dat[$i]['name'])?"selected":'';
		$table_field.='<td><a href="javascript:void(0);" onclick="add_order(\''.$col_dat[$i]['name'].'\');" style="color:#fff" title="">'.$col_dat[$i]['name'].'</td>';
	}
	$table_field='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>'.$table_field.'</tr>';
	
	$table_line='';
	$t=0;
	// テーブル中身 交互に色変え
	$pdb=run_sql_query($sqlx,__FUNCTION__.'table_line');
	while($data=$pdb->fetch(PDO::FETCH_ASSOC)){
		$tr_back=($t%2==0)?"fff":"ddd";
		// オンマウスで色を変える
		$mouse_over_html=' onmouseover="this.style.backgroundColor=\'#9ff\'" onmouseout="this.style.backgroundColor=\'#'.$tr_back.'\'" ';
		$table_line.='<tr id="table_tr_'.$t.'"style="background-color:#'.$tr_back.';" '.$mouse_over_html.'>';
		// チェックボックスで色を変える
		$table_line.='<td><input type="checkbox" onclick="dbview_chk_toggle(\'#table_tr_'.$t.'\');"></td>';
		foreach($data as $dat){
			if((int)$_POST["setting_value_limit"] != 0 && strlen($dat)>(int)$_POST["setting_value_limit"]){
				$dat='<a style="color:#000066;" href="javascript:void(0);" title="'.htmlspecialchars($dat).'">'.substr(htmlspecialchars($dat),0,(int)$_POST["setting_value_limit"]).'...</a>';
			}else{
				$dat=htmlspecialchars($dat);
			}
			if($dat == ''){ $dat='&nbsp;'; }
			$table_line.='<td style="white-space: nowrap;">'.$dat.'</td>';
		}
		$table_line.='</tr>';
		$t++;
		// 200を最大数とする
		if($t>200) break;
	}
	
	$html_table='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">'.$table_field.$table_line.'</table>';
	
	$table_info=$pdb->rowCount().'件<br>';
	
	$html[] = $html_table;
	$html[] = get_pager();
	$html[] = $col_html;
	$html[] = $table_info;
	
	return $html;
}

function pager_onclick($num){
	//return 'onclick="page_sel(\'page_select\',\''.$num.'\');"';
	return 'onclick="'.pager_ajax().'"';
}

function pager_ajax(){
	return 'run_ajax(\'db_view\',\'db_viewer,view_opt\');"';
}

function create_input($type,$value,$name,$other){
	return '<input type="'.$type.'" value="'.$value.'" id="'.$name.'" name="'.$name.'" '.$other.'>';
}

function ajax_get_sql(){
	$html=array();
	$get_sqlx=get_sql_query($_POST["refarence"]);
	$pdb=run_sql_query( $get_sqlx, __FUNCTION__);
	$dat=$pdb->fetch(PDO::FETCH_ASSOC);
	result_print('SQLを取得','',0,$dat["definition"]);
}


// わざとエラーを起こして画面上に表示
function alert($mes){
	print var_dump($mes); 
}

function error_print($mes,$sqlx){
	// 配列を入れて200 OKとかの奴を最後にもっていく
	result_print($mes,$sqlx,1,array('','','','','',''));
}

function result_print($mes,$sqlx_mes,$code,$html=array(),$page_file='',$data=''){
	$app = Slim::getInstance();
	$mes_info.='(';
	$mes_info.=($_POST["setting_connect_ip"])	?' HOST:'.$_POST["setting_connect_ip"]:'';
	$mes_info.=($_POST["db_select"])			?' DB:'.$_POST["db_select"]:'';
	$mes_info.=($_POST["tbl_select"])			?' TABLE:'.$_POST["tbl_select"]:'';
	$mes_info.=($_POST["page_select"]!=''&&$_POST["page_select"]!=1) ?' PAGE:'.$_POST["page_select"]:'';
	$mes_info.=' )';
	
	$mes_tsv='';
	$mes_tsv.=date("Y/m/d(D) H:i:s")."\t";
	$mes_tsv.=$mes."\t";
	$mes_tsv.=$mes_info."\t";
	$mes_tsv.=$sqlx_mes."\t";
	$mes_tsv.=$code;
	
	$html=(!is_array($html))?array($html):$html;
	
	array_unshift($html,$mes_tsv);
	
	// 返す文字列（<##!##>が区切り文字）
	// メッセージTSV<##!##>表示されるHTML1<##!##>HTML2..3..
	$sep='<##!##>';
	$print_str='';
	foreach ($html as $_){
		$print_str .= $_.$sep;
	}
	
	print $print_str;
}

function run_sql_query($sqlx,$err_mes='',$DB=""){
	if($DB==""){
		$DB=db_init();
	}
	
	$pdb=$DB->query($sqlx);
	if(!$pdb){
		error_print("DB実行エラー(".$err_mes.")：".error_disp($DB->errorInfo(),$sqlx),$sqlx);
	} else {
		return $pdb;
	}
}


function getdat($sqlx,$err_mes=''){
	$pdb=run_sql_query($sqlx,$err_mes);
	return $pdb->fetch(PDO::FETCH_ASSOC);
}

function ajax_diff(){
	$html=array();
	$DB=db_init();
	$diff_connect_setting=
	$DB_diff = create_db(
		array(
			"dbtype"=>$_POST["diif_connect_dbtype"]
			,"ip"=>$_POST["diff_connect_ip"]
			,"db"=>$_POST["diff_connect_dbname"]
			,"user"=>$_POST["diff_connect_user"]
			,"pass"=>$_POST["diff_connect_pass"]
			,"timeout"=>3
		)
	);
	
	$dbname1=$_POST["db_select"];
	$dbname2=$_POST["diff_connect_dbname"];
	
	$tables1 = _get_tables($DB);
	$tables2 = _get_tables($DB_diff);
	
	$all_tables = array_unique(array_merge($tables1, $tables2));
	$result=array();
	foreach($all_tables as $all_table){
		if(in_array($all_table,$tables1) && in_array($all_table,$tables2)){
			$columns1 = _get_table_info($DB,$all_table);
			$columns2 = _get_table_info($DB_diff,$all_table);
			$all_columns = array_merge($columns1, $columns2);
			foreach($all_columns as $all_columnKey=>$all_columnVal){
				$rows = array('','&nbsp;','&nbsp;','&nbsp;','&nbsp;');
				if(
					!array_key_exists($all_columnKey, $columns1) ||
					!array_key_exists($all_columnKey, $columns2)
				){
					$rows[0] = $all_table;
					$rows[1] = $all_columnKey;
					
					if(empty($columns1[$all_columnKey])){
						$rows[2] = sql_add_link('カラム無し','ALTER TABLE '.$all_table.' ADD '.$all_columnKey.' '.$all_columnVal.';');
					}
					
					if(empty($columns2[$all_columnKey])){
						$rows[3] = 'カラム無し';
					}
					
					if(trim($rows[2]) != '&nbsp;' && trim($rows[3]) != '&nbsp;'){
						$rows[4] = 'よく分からないから確認して！！';
					}
					$result[] = $rows;
				}else if($columns1[$all_columnKey] != $columns2[$all_columnKey]){
					$rows[0] = $all_table;
					$rows[1] = $all_columnKey;
					$rows[2] = $columns1[$all_columnKey];
					$rows[3] = $columns2[$all_columnKey];
					$rows[4] = sql_add_link('型不一致','');
					$result[] = $rows;
				}
			}
		}else{
			$rows = array('','&nbsp;','&nbsp;','&nbsp;','&nbsp;');
			$rows[0] = $all_table;
			$rows[1] = '-';
			
			if(in_array($all_table,$tables2)){
				$columns1 = _get_table_info($DB,$all_table);
				$columns2 = _get_table_info($DB_diff,$all_table);
				$all_columns = array_merge($columns1, $columns2);
				$rows[2] = sql_add_link('テーブル無し','CREATE TABLE '.$all_table.' ('.create_sql_table($all_columns).');');
			}
			
			if(in_array($all_table,$tables1)){
				$rows[3] = 'テーブル無し';
			}
			$result[] = $rows;
		}
	}
	
	$html='結果：'.count($result).'個の違いがありました';
	$html.='
	<table border="1" style="font-size:small;">
	<tr>
		<th>テーブル名</th>
		<th>カラム名</th>
		<th>'.$dbname1.'<span style="font-size:xx-small;color:red;font-weight:normal;">※押下時SQL追加</span></th>
		<th>'.$dbname2.'</th>
		<th>種別</th>
	</tr>';
	
	foreach($result as $columns){
		$html.="
		<tr>
			<td>$columns[0]</td>
			<td>$columns[1]</td>
			<td>$columns[2]</td>
			<td>$columns[3]</td>
			<td>$columns[4]</td>
		</tr>
		";
	}
	$html.='</table>';
	result_print('比較しました','',0,$html);
}

function sql_add_link($link_name,$sql){
	return '<a href="javascript:void(0);" onclick="$(\'#query\').val($(\'#query\').val()+\'\\n\'+\''.$sql.'\')">'.$link_name.'</a>';
}

function create_sql_table($columns){
	foreach($columns as $key=>$val){
		$sql.=' '.$key.' '.$val.',';
	}
	$sql=substr($sql, 0, (strlen($sql)-1) );
	return $sql;
}

function _get_tables($db){
	$ret = array();
	$str_query=get_tbl_query();
	$tables = gethashs($db, $str_query);
	foreach($tables as $table){
		// テーブルのみを取得
		if($table['relkind'] == 'r'){
			$ret[] = $table['relname'];
		}
	}
	return $ret;
}

function _get_table_info($db, $table_name){
	$ret = array();
	$str_query	= get_tbl_info_query($table_name);
	$table_date = gethashs($db, $str_query);
	foreach($table_date as $table_datum){
		$ret[$table_datum['attname']] = $table_datum['typname'];
	}
	return $ret;
}

function gethashs($db,$sqlx){
	$fd = array();
	$re=run_sql_query($sqlx,"",$db);
	if($re!=''){
		while($rec=$re->fetch()){
			$fd[] = $rec;
		}
	}
	return $fd;
}

function error_disp($errinfo,$sqlx){
	return $errinfo[2].' SQL内容:['.$sqlx.']';
}

function create_db($setting){
	$app = Slim::getInstance();
	$dsn=($setting["dbtype"])?"$setting[dbtype]:":'pgsql:';
	$dsn.=($setting["ip"])?"host=$setting[ip];":"host=localhost;";
	
	if($setting["dbtype"]=='mysql'){
		$dsn.=($setting["port"])?"port=$setting[port];":"port=3306;";
	}else{
		$dsn.=($setting["port"])?"port=$setting[port];":"port=5432;";
	}
	
	$dsn.=(trim($setting["db"])!= '' && $setting["db"] != 'reading...')?" dbname=$setting[db];":"";
	$dsn.=($setting["user"])?"user=$setting[user];":"";
	$dsn.=($setting["pass"])?"password=$setting[pass];":"";
	$dsn.=($setting["timeout"])?" connect_timeout=$setting[timeout];":"";
	//$dsn.=($setting["charset"])?" charset=$setting[char];":"";
	$DB = new PDO($dsn);
	if($DB){
		return $DB;
	}else{
		error_print('DB接続エラー(.'.$_POST["setting_connect_name"].$DB->getMessage().')',$dsn);
	}
}

// SQLを取得するSQLを取得
function get_sql_query($type) {
	if($type == 'refa_tblcre'){
		// viewが定義されているselect文
		$query="SELECT definition FROM pg_views WHERE viewname = '".$_POST["tbl_select"]."'";
	}
	return $query;
}

function get_tbl_query(){
	if($_POST["setting_connect_db"]=='mysql'){
	$query="
		SELECT table_name as relname,table_type as relkind,table_rows as rows,''
		FROM INFORMATION_SCHEMA.TABLES
		ORDER BY relkind, relname;
";
	}else{
	$query="
		SELECT c.relname,c.relkind,reltuples as rows,pg_relation_size(relname::regclass)
		FROM pg_catalog.pg_class c
		JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
		LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
		WHERE n.nspname NOT IN ('pg_catalog', 'pg_toast') AND 
		pg_catalog.pg_table_is_visible(c.oid)
		ORDER BY relkind, relname;
";
	}
	//alert($query);
	return $query;
}

function get_tbl_info_query($table_name){
	if($_POST["setting_connect_db"]=='mysql'){
		$query="
	SELECT
	 pg_attribute.attnum,
	 pg_attribute.attname,
	 pg_type.typname
		";
	}else{
	$query="
	SELECT
	 pg_attribute.attnum,
	 pg_attribute.attname,
	 pg_type.typname
	FROM 
	 pg_class,
	 pg_attribute,
	 pg_type
	WHERE
	 pg_class.oid = pg_attribute.attrelid and
	 pg_attribute.atttypid = pg_type.oid and
	 pg_class.relname='".$table_name."' and
	 pg_attribute.attnum > 0
	ORDER BY 
	 pg_attribute.attnum;";
	}
	return $query;
}

function key_radio_forms($id_name){
	$html='';
	for($i=0;$i<7;$i++){
		$key_code=$i+117;
		$key_name='F'.(6+$i);
		$html .= '<label>
		<input type="radio" id="'.$id_name.'" name="'.$id_name.'" value="'.$key_code.'" onchange="ls_save('.$id_name.');"/>
			'.$key_name.'
		</label>';
	}
	return $html;
}


function accesschk(){
	// アクセスチェック
	$txt=file_get_contents('allow.txt');
	foreach($txt as $line){
		if($line == $_SERVER["REMOTE_ADDR"]){
			return true;
		}
	}
	return false;
}

function main(){
	$app = Slim::getInstance();
	/*if(!accesschk()){
		echo 'アクセスが許可されていません';
		return 0;
	}*/
	
	$key_forms_name=array('実行'=>'setting_key_run','整形'=>'setting_key_crean','更新'=>'setting_key_update','設定'=>'setting_key_conf');
	foreach($key_forms_name as $key=>$value){
		$config_key_list.='<tr><td style="text-align:right;">'.$key.':</td><td>'.key_radio_forms($value).'</td></tr>';
	}
	
	$_POST['limit_num']=($_POST['limit_num']=='')?30:$_POST['limit_num'];
	
	$data["config_limit_num_list"]=array('10','30','50','100','200');
	$data["config_table_type"][0]['key']='r';
	$data["config_table_type"][0]['value']='table';
	
	$data["config_table_type"][1]['key']='v';
	$data["config_table_type"][1]['value']='view';
	
	$data["config_table_type"][2]['key']='S';
	$data["config_table_type"][2]['value']='sequence';
	
	$data["config_table_type"][3]['key']='i';
	$data["config_table_type"][3]['value']='index';
	
	$data["title"]=$_SERVER['SERVER_NAME'];
	$data["version"]='1.2';
	$app->render('main.html',$data);
}

function before(){
	$app = Slim::getInstance();
	return $app->request();
}

$app->hook('slim.before.dispatch', 'before');

$app->get('/', 'main');
$app->post('/ajax', 'ajax');
$app->post('/ajax_db_option', 'ajax_db_option');
$app->post('/ajax_tbl_option', 'ajax_tbl_option');
$app->post('/ajax_db_view', 'ajax_db_view');
$app->post('/ajax_query_run', 'ajax_query_run');
$app->post('/ajax_reload', 'ajax_reload');
$app->post('/ajax_get_sql', 'ajax_get_sql');
$app->post('/ajax_diff', 'ajax_diff');

$app->get('/ajax_db_option', 'ajax_db_option');
$app->get('/ajax_tbl_option', 'ajax_tbl_option');
$app->get('/ajax_db_view', 'ajax_db_view');
$app->get('/ajax_query_run', 'ajax_query_run');
$app->get('/ajax_reload', 'ajax_reload');
$app->get('/ajax_get_sql', 'ajax_get_sql');
$app->get('/ajax_diff', 'ajax_diff');

$app->run();
exit();