<?php
require './slim/Slim.php';

ini_set('error_reporting',E_ERROR | E_WARNING | E_PARSE);
//ini_set('display_errors', 0);

$app = new Slim(
	array(
	//'debug' => 'true',
	//'session.flash_key' => 'Keizu.flash_key',
	//'view' => '\Holy\Slim\View\PhpTalView',
	//'templates.path' => realpath(__DIR__ . '/views'),
));

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

// 初期化作業
function db_init(){
	$setting=array(
		"dbtype"=>$_POST["setting_connect_db"]
		,"ip"=>$_POST["setting_connect_ip"]
		,"port"=>$_POST["setting_connect_port"]
		,"db"=>$_POST["db_select"]
		,"user"=>$_POST["setting_connect_user"]
		,"pass"=>$_POST["setting_connect_pass"]
		,"timeout"=>3
	);
	return create_db($setting);
}

function ajax(){
	$DB = db_init();
	$html = array();
	$sqlx='SELECT * FROM '.$_POST["tbl_select"];
	$sqlx_limit = $sqlx.create_query_limit();
	if($_POST["type"] == "db_option"){
		$html[0] = db_option();
		$mes='DBに接続しました';
		
	}else if($_POST["type"] == "tbl_option"){
		$html[0] =tbl_option();
		$mes='DBを選択しました';
		
	}else if($_POST["type"] == "db_view"){
		$col_dat=get_column_data($sqlx_limit);
		
		$html[0] = table_viewer($sqlx_limit,$col_dat);
		$html[1] = pager_view_opt($sqlx);
		$html[2] = col_option($col_dat);
		$mes='テーブルを表示しました';
	}else if($_POST["type"] == 'query_run'){
		$sqlx=preg_replace("/\\\'/i","'",$_POST["query"]);
		$sqlxs=preg_split('/;/',$sqlx);
		$html[0]='';
		$html[1]='';
		
		foreach ($sqlxs as $k => $sql ){
			$sql.=';';
			if(trim($sql)!=';'){
				if(preg_match("/^[\s]*SELECT/i",$sql)){
					$col_dat=get_column_data($sql);
					$html[0].= table_viewer($sql,$col_dat);
					$html[1].= pager_view_opt($sql);
					
				}else{
					run_sql_query($sql,'query_run');
					$html[0].="<font color=\"#ff0000\">$sql</font><br>";
				}
				
			}
			
		}
		
		$mes='クエリを実行しました';
	}else if($_POST["type"] == 'reload'){
		if($_POST["reload_num"] > 1){
			$html[0]=db_option();
		}
		if($_POST["reload_num"] > 2){
			$html[1]=tbl_option($sqlx_limit);
		}
		if($_POST["reload_num"] > 3){
			$col_dat=get_column_data($sqlx_limit);
			$html[2]=col_option($col_dat);
		}
		$mes='更新しました ';
	
	}else if($_POST["type"] == 'get_sql'){
		$html[0]=get_sql($_POST["refarence"]);
		$mes='SQLを取得';
		
	}else if($_POST["type"] == 'diff'){
		$html[0]=diff_viewer();
		$mes='比較しました ';
	}
	
	// 結果表示
	if($_POST["type"] == 'query_run'){
		$sqlx_mes=$sqlx;
	} else if($_POST["type"] == 'db_view'){
		$sqlx_mes=$sqlx_limit;
	} else {
		$sqlx_mes='';
	}
	ajax_end($mes,$sqlx_mes,$html);
}

// 終了作業
function ajax_end($mes,$sqlx_mes,$html){
	$result=array_merge(array(mes_tsv($mes,$sqlx_mes)),$html);
	print result_print($result);
	//$DB->disconnect();
}

function create_query_limit(){
	$qr_limit='';
	if((int)$_POST["limit_num"] > 0 && (int)$_POST["page_select"] > 0){
		$qr_limit=' OFFSET '.(int)((int)$_POST["page_select"]-1)*(int)$_POST["limit_num"].' LIMIT '.$_POST["limit_num"];
	}else if((int)$_POST["limit_num"] > 0){
		$qr_limit=' LIMIT '.$_POST["limit_num"];
	}
	return $qr_limit;
}

function pager_view_opt($tbl_query){
	$dat=page_cnt($tbl_query);
	$start_row=(($_POST["page_select"] * $_POST["limit_num"])-$_POST["limit_num"] + 1);
	$end_row=($_POST["page_select"] * $_POST["limit_num"]);
	$page_info='['. $start_row .' - '.$end_row.' / '.$dat{rows}.']';
	$page_select='<select style="width:40px;" type="text" size="1" name="page_select" id="page_select" onchange="'.pager_ajax().'">'.$dat["page_opt_html"].'</select>/'.$dat["page_num"];
	$page_button[]=create_input('button','&lt;&lt;','page_first',pager_onclick(-100));
	$page_button[]=create_input('button','&lt;','page_back',pager_onclick(-1));
	$page_button[]=create_input('button','&gt;','page_next',pager_onclick(1));
	$page_button[]=create_input('button','&gt;&gt;','page_last',pager_onclick(100));
	$pager='<div>'.$page_button[0].$page_button[1].$page_info.$page_select.$page_button[2].$page_button[3].'</div>';
	return $pager;
}

function pager_onclick($num){
	return 'onclick="page_sel(\'page_select\',\''.$num.'\'); '.pager_ajax().'"';
}

function pager_ajax(){
	return 'run_ajax(\'db_view\',\'db_viewer,view_opt\');"';
}

function create_input($type,$value,$name,$other){
	return '<input type="'.$type.'" value="'.$value.'" id="'.$name.'" name="'.$name.'" '.$other.'>';
}

// カウント・ページ数の計算
function page_cnt($tbl_query){
	if($pdb=run_sql_query($tbl_query,__FUNCTION__)){
		$rows=$pdb->rowcount();
		if($_POST["limit_num"]==0){
			$_POST["limit_num"] = 1;
		}
		$page_num=ceil($rows/$_POST["limit_num"]);
		$html_option_pages='';
		for($i=1;$i<=$page_num;$i++){
			if($_POST["page_select"] == $i){ $html_option_pages.='<option value="'.$i.'" selected>'.$i.'</option>';}
			else{ $html_option_pages.='<option value="'.$i.'">'.$i.'</option>'; }
		}
		if($_POST["page_select"] == ''){$_POST["page_select"]=1;}
		$dat=array("rows"=>$rows,"page_opt_html"=>$html_option_pages,"page_num"=>$page_num);
		return $dat;
	}
}

//SQLを取得
function get_sql($type) {
	$get_sqlx=get_sql_query($type);
	$pdb=run_sql_query( $get_sqlx, __FUNCTION__);
	$dat=$pdb->fetch(PDO::FETCH_ASSOC);
	return $dat["definition"];
}

//DBを表示
function db_option() {
	$db_sqlx=get_db_query();
	$pdb=run_sql_query($db_sqlx,__FUNCTION__);
	$db_opt_html='<option value=""></option>';
	while($row=$pdb->fetch()){
		$selected='';
		if($_POST["db_select"] == $row['datname']){ $selected="selected"; }
		$db_opt_html.='<option value="'.$row['datname'].'" '.$selected.'>'.$row['datname'].'</option>';
	}
	return $db_opt_html;
}

function tbl_option() {
	$tbl_sqlx = get_tbl_query();
	$pdb=run_sql_query($tbl_sqlx,__FUNCTION__);
	
	$type_rows=array();
	$typ_color = array('r'=>'#FFF','v'=>'#AFA','i'=>'#c9c' ,'S'=>'#9cc'	   );
	$typ_name  = array('r'=>'TABLE','v'=>'VIEW','i'=>'INDEX','S'=>'SEQUENCE');
	$form_types=$_POST["setting_tblsel_view_type"];
	
	// テーブルのオプション表示
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
	return $tbl_opt_html;
}

// カラムデータの取得
function get_column_data($col_sqlx){
	$ret=array();
	$pdb=run_sql_query($col_sqlx,__FUNCTION__);
	$i = 0;
	while ($column = $pdb->getColumnMeta($i)) {
		$ret[$i]['name']=$column['name'];
		$ret[$i]['native_type']=$column['native_type'];
		$ret[$i]['len']=$column['len'];
		$i++;
	}
	$ret['total_num']=$i;

	return $ret;
}

//列名セレクトボックスの作成
function col_option($col_dat){
	$col_html.= "<option></option>";
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
	return $col_html;
}

// テーブル内容のテーブルHTML作成
function table_viewer($tbl_naiyo_sqlx,$col_dat){
	$html_table_viewer='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">';
	// テーブル最初の行 フィールド名
	$html_table_viewer.='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>';
	for($i=0;$i<$col_dat['total_num'];$i++){
		$type='';
		$selected=($_POST["col_select"] == $col_dat[$i]['name'])?"selected":'';
		$html_table_viewer.='<td><a	 href="javascript:void(0);" onclick="add_order(\''.$col_dat[$i]['name'].'\');" style="color:#fff" title="">'.$col_dat[$i]['name'].'</td>';
	}
	$html_table_viewer.='</tr>';
	
	$html_table_viewer.=table_viewer_line($tbl_naiyo_sqlx);
	
	$html_table_viewer.='</table>';
	
	return $html_table_viewer;
}

function table_viewer_line($tbl_naiyo_sqlx){
	$html_table_viewer_line='';
	$t=0;
	// テーブル中身 交互に色変え
	$pdb=run_sql_query($tbl_naiyo_sqlx,__FUNCTION__);
	
	while($data=$pdb->fetch(PDO::FETCH_ASSOC)){
		$tr_back=($t%2==0)?"fff":"ddd";
		// オンマウスで色を変える
		$mouse_over_html=' onmouseover="this.style.backgroundColor=\'#9ff\'" onmouseout="this.style.backgroundColor=\'#'.$tr_back.'\'" ';
		$html_table_viewer_line.='<tr id="table_tr_'.$t.'"style="background-color:#'.$tr_back.';" '.$mouse_over_html.'>';
		//$html_table_viewer_line.='<tr>';
		// チェックボックスで色を変える
		$html_table_viewer_line.='<td><input type="checkbox" onclick="dbview_chk_toggle(\'#table_tr_'.$t.'\');"></td>';
		foreach($data as $dat){
			if((int)$_POST["setting_value_limit"] != 0 && strlen($dat)>(int)$_POST["setting_value_limit"]){
				$dat='<a style="color:#000066;" href="javascript:void(0);" title="'.htmlspecialchars($dat).'">'.substr(htmlspecialchars($dat),0,(int)$_POST["setting_value_limit"]).'...</a>';
			}else{
				$dat=htmlspecialchars($dat);
			}
			if($dat == ''){ $dat='&nbsp;'; }
			$html_table_viewer_line.='<td style="white-space: nowrap;">'.$dat.'</td>';
		}
		$html_table_viewer_line.='</tr>';
		$t++;
		
		// 200を最大数とする
		if($t>200){ return $html_table_viewer_line; }
	}

	return $html_table_viewer_line;
}

function mes_info(){
	$mes_info.='(';
	$mes_info.=($_POST["setting_connect_ip"])	?' HOST:'.$_POST["setting_connect_ip"]:'';
	$mes_info.=($_POST["db_select"])			?' DB:'.$_POST["db_select"]:'';
	$mes_info.=($_POST["tbl_select"])			?' TABLE:'.$_POST["tbl_select"]:'';
	$mes_info.=($_POST["page_select"]!=''&&$_POST["page_select"]!=1) ?' PAGE:'.$_POST["page_select"]:'';
	$mes_info.=' )';
	return $mes_info;
}

function mes_tsv($mes,$sqlx_csv,$code=0){
	$mes_tsv='';
	$mes_ary=array();
	$mes_ary[0] = date("Y/m/d(D) H:i:s");
	$mes_ary[1] = $mes;
	$mes_ary[2] = mes_info();
	$mes_ary[3] = $sqlx_csv;	
	$mes_ary[4] = $code;	
	
	for($i=0;$i<count($mes_ary);$i++){
		$mes_tsv.=$mes_ary[$i]."\t";
	}
	return $mes_tsv;
}

// わざとエラーを起こして画面上に表示
function alert($mes){
	print var_dump($mes); 
	
}

function error_print($mes,$sqlx){
	// 区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array(mes_tsv($mes,$sqlx,1),'','','','','');
	if($DB){
		//$DB->disconnect();
	}
	die(result_print($result));
}

// 返す文字列（<##!##>が区切り文字）
// メッセージCSV<##!##>表示されるHTML1<##!##>HTML2..3..
function result_print($ary){
	//$ary = @{shift()};
	$sep='<##!##>';
	$print_str='';
	foreach ($ary as $_){
		$print_str .= $_.$sep;
	}
	return $print_str;
}

function run_sql_query($sqlx,$err_mes='',$DB=""){
	$DB=db_init();
	try {
		//$DB->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		//$DB->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY ,true);
		$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdb=$DB->query($sqlx);
		return $pdb;
	}catch( PDOException $e ) {
		error_print("データベース実行エラー(".$err_mes."".$e->getMessage().")：".error_disp($DB->errorInfo(),$sqlx),$sqlx);
		die();
	}
}

function diff_viewer(){
	$DB=db_init();
	$diff_connect_setting=array(
		"dbtype"=>$_POST["diif_connect_dbtype"]
		,"ip"=>$_POST["diff_connect_ip"]
		,"db"=>$_POST["diff_connect_db"]
		,"user"=>$_POST["diff_connect_user"]
		,"pass"=>$_POST["diff_connect_pass"]
		,"timeout"=>3
	);
	$DB_diff = create_db($diff_connect_setting);
	
	$dbname1=$_POST["db_select"];
	$dbname2=$_POST["diff_connect_db"];
	
	$tables1 = _get_tables($DB);
	$tables2 = _get_tables($DB_diff);
	
	$all_tables = array_unique(array_merge($tables1, $tables2));
	
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
	
	return $html;
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

function gethashs($sqlx){
	$fd = array();
	$re=run_sql_query($sqlx);
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
	try {
		$DB = new PDO($dsn);
		return $DB;
	} catch( PDOException $Exception ) {
		error_print('データベース接続エラー(.'.$_POST["setting_connect_name"].$e->getMessage().')',$dsn);
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

function get_db_query(){
	if($_POST["setting_connect_db"]=='mysql'){
		$query="SELECT SCHEMA_NAME as datname FROM INFORMATION_SCHEMA.SCHEMATA";
	}else{
		$query="SELECT datname FROM pg_database WHERE datname NOT IN ('template0','template1') ORDER BY datname;";
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

function table_set_forms(){
	$html='';
	$types=array('r'=>'table','v'=>'view','S'=>'sequence','i'=>'index');
	foreach ($types as $key => $value) {
		$checked=($key=='r')?'checked':'';
		$id='setting_tblsel_view_type_'.$key;
		$html .= '
		<label>
		<input type="checkbox" name="setting_tblsel_view_type[]" id="'.$id.'" onclick="ls_save(\''.$id.'\'); tbl_type_change(\''.$key.'\')" value="'.$key.'" '.$checked.'/>'.$value.'
		</label>';
	}
	return $html;
}

function limitnum_set_forms(){
	$html='';
	$numlist=array('10','30','50','100','200');
	foreach($numlist as $num){
		$html .= '
		<label>
			<input type="radio" id="limit_num" name="limit_num" value="'.$num.'"  onchange="ls_save(\'limit_num\');" checked />'.$num.'
		</label>';
	}
	$html .='
	<label>
		<input type="radio" id="limit_num" name="limit_num" value=""  onchange="ls_save(\'limit_num\');" />無制限
	</label>';
	return $html;
}

function main(){
		$header = '
<!DOCTYPE html><html><head>
	<meta charset="utf-8"/>
	<title id="title">WebQuery ['.$_SERVER['SERVER_NAME'].']</title>
	<style type="text/css">
	<!--
		.tbl_list {
		overflow: scroll;	/* スクロール表示 */
		width: 150px;
		height: 80%;
		}
	-->
	</style>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<script type="text/javascript" src="jquery-1.6.1.min.js"></script>
	<script type="text/javascript" src="webquery.js"></script>
</head>';

	$html_start = <<<EOT
<body id="body">
	<form id="fm" name="fm" >
EOT;

$connect = <<<EOT
		<div style="background-color:#666;padding:5px;vertical-align:middle;color:#ffffff;">
			<a style='font-size:small;color:white;margin-right:5px;margin-left:5px;' onclick="id_display_toggle('setting');" href="javascript:void(0);">設定</a>
			
			<!-- DBパンくずセレクト -->
			<select id="connect_select" name="connect_select" size="1" type="text" onchange="run_host();">
			</select>
			<span id="" style="font-size: small;"><a style="color:white;" href="javascript:void(0);" onclick="run_host();">></a></span>
			<select id="db_select" name="db_select" size="1" type="text" onchange="run_db();">
				<option value="" selected>DB一覧</option>
			</select>
			<span id="" style="font-size: small;"><a style="color:white;" href="javascript:void(0);" onclick="run_db();">></a></span>
			<select id="tbl_select" title="括弧内のカウント数はバキューム等をしないと正確になりません" name="tbl_select" size="1" type="text" onclick="" onchange="run_tbl();">
			<option value="" selected>テーブル一覧</option>
			</select>
			<span id="" style="font-size: small;"><a style="color:white;" href="javascript:void(0);" onclick="run_tbl();">></a></span>
			<select id="col_select" name="col_select" onchange="create_refa();" size="1" type="text">
				<option value="" selected>列一覧</option>
			</select>
			<span id="" style="font-size: small;">
			[
			<select id="refarence" name="refarence" size="1" style="" title="←接続パネルで選択して下さい" onchange="create_refa();">
				<option value='' style="background:#FFF;">SQL生成</option>
				<option value='refa_tblsel' style="background:#cff;">表表示</option>
				<option value='refa_rowup'	style="background:#fc9;">行更新</option>
				<option value='refa_tblcre' style="background:#9F9;">表作成</option>
				<option value='refa_rowadd' style="background:#9F9;">行作成</option>
				<option value='refa_colcre' style="background:#9F9;">列作成</option>
				<option value='refa_dbcre'	style="background:#9F9;">DB作成</option>
				<option value='refa_tbldel' style="background:#fcc;">表削除</option>
				<option value='refa_rowdel' style="background:#fcc;">行削除</option>
				<option value='refa_coldel' style="background:#fcc;">列削除</option>
				<option value='refa_dbdel'	style="background:#fcc;">DB削除</option>
				<option value='refa_css'	style="background:#FFF;">CSV生成</option>
				<option value='refa_csstbl' style="background:#cff;">表内容CSV</option>
			</select>
			]</span>
			<a style="font-size:small;color:white;margin-right:5px;margin-left:5px;" href="javascript:void(0);" onclick="run_reload();">更新</a>
			<input id="reload_num" name="reload_num" type="hidden" value=""/>
			<!--<span id="ip" style="margin-top:3px;float:right;font-size:small;vertical-align:middle;"></span>-->
		</div>
EOT;
		
		$config_key_list='';
		$key_forms_name=array('実行'=>'setting_key_run','整形'=>'setting_key_crean','更新'=>'setting_key_update','設定'=>'setting_key_conf');
		foreach($key_forms_name as $key=>$value){
			$config_key_list.='<tr><td style="text-align:right;">'.$key.':</td><td>'.key_radio_forms($value).'</td></tr>';
		}
		$config_limit_num='<tr><td style="text-align:right;">制限数：</td><td>'.limitnum_set_forms().'</td></tr>';
		$config_table_type='<tr><td style="text-align:right;">テーブルリスト内容：</td><td>'.table_set_forms().'</td></tr>';
		$config = <<<EOT
		<!-- 設定パネル start -->
		<div id="setting" name="setting" style="display:none;font-size:small;">
			<table style="font-size:small;background-color:#ccc;width:100%;">
				<tr>
					<td style="text-align:right;">接続設定：</td>
					<td>
					<input id="setting_connect_id" name="setting_connect_id" size="1" type="text" value="" placeholder="ID" disabled/>
					
					<input id="setting_connect_name" name="setting_connect_name" size="20" type="text" value="" placeholder="接続名"/>
					<select id="setting_connect_db" name="setting_connect_db" size="1" style="">
						<option value="pgsql" />Postgres</option>
						<option value="mysql" />MySQL</option>
						<!---<option value="oracle" />Oracle</option>--->
					</select>
					<!---
					<select id="setting_connect_char" name="setting_connect_char" size="1" style="" >
						<option name="char_code" onclick="" value="utf-8" checked/>utf-8</option>
						<option name="char_code" onclick="" value="ASCII" checked/>ASCII</option>
						<option name="char_code" onclick="" value="sjis" />Shift-JIS</option>
						<option name="char_code" onclick="" value="euc-jp" />EUC-JP</option>
					</select>
					--->
					<input id="setting_connect_ip" name="setting_connect_ip" size="20" type="text" value="" placeholder="IP"/>
					<input id="setting_connect_user" name="setting_connect_user" size="20" type="text" value="" placeholder="ユーザー名"/>
					<input id="setting_connect_pass" name="setting_connect_pass" size="20" type="password" value="" placeholder="パスワード"/>
					
					<input id="setting_connect_add" name="setting_connect_add" size="10" type="button" onclick="connect_save();" value="保存" />
					<input id="setting_connect_del" name="setting_connect_del" size="10" type="button" onclick="connect_del();" value="削除" />
					</td>
				</tr>
				<tr>
					<td style="text-align:right;">表示：</td>
					<td>
						<label><input type="checkbox" name="debug_panel_toggle" id="debug_panel_toggle" onchange="id_display_toggle('debug_panel');ls_save('debug_panel_toggle');" value="1"/>デバッグパネル</label>
					</td>
				</tr>
				<tr>
					<td style="text-align:right;">省略文字数：</td>
					<td><input id="setting_value_limit" name="setting_value_limit" size="2" type="text" onchange="ls_save('setting_value_limit');" value="100"/></td>
				</tr>
EOT;
				$config.=$config_key_list.$config_limit_num.$config_table_type;
				$config.=<<<EOT
			</table>
		</div>
		<div style="clear:both;" />
		<!-- 設定パネル end -->
EOT;

$debug = <<<EOT
		<!-- デバッグパネル -->
		<div id="debug_panel" style="display:none;font-size:x-small;">
			<textarea type="text" style="width:100%;height:300px;font-size:x-small;" value="" id="postview" ></textarea>
		</div>
EOT;

$run = <<<EOT
		<!-- 実行パネル -->
		<div id="control_panel" name="control_panel" style="background-color:#666;padding:5px;vertical-align: middle;">
			<!-- クエリ入力ボックス -->
			<div id="query_panel" style="">
				<textarea id="query" name="query" style="height:50px;width:99%;font-size:small;resize:vertical;" placeholder='実行クエリ' onkeypress="run_key(event);" readonly="readonly"></textarea>
			</div>
			
			<input type="button" value=" 実行 " id="run_sql" style="width:5%;" onClick="run_query()" />
			<input type="button" value=" 整形 " id="run_clean" style="width:5%;" onClick="run_clean_query()" />
			<!--<input type='button' value=" ↑ " id="run_edit" style="width:5%;" onClick="run_edit()" />--->

			<select id="message" name="message" size="1" style="background-color:#fff;width:89%;"></select>
			<!--<a id="save_link" name="save_link" href="#" style="font-size:small;color:white;margin-right:5px;margin-left:5px;">保存</a>-->
		</div>
EOT;

$result = <<<EOT
		<!-- 実行パネルオプション部分 -->
		<div id="control_opt" name="control_opt"></div>
		
		<div id="result" style="background-color:#ccc;padding:3px;">
			<code class="sql" style="font-size:small;"><span id="syntax"></span></code>
		</div>
		
		<!-- DIFFパネル -->
		<div id="diff" style="display:none;">
			<span style="font-weight:bold;font-size:large;">DIFF</span>
			<select id="diff_connect_select" name="diff_connect_select" size="1" type="text" onchange="connect_load('diff_connect');">
			</select>
			HOST:<input type="text" id="diff_connect_ip" name="diff_connect_ip" value="">
			DB:<input type="text" id="diff_connect_dbname" name="diff_connect_dbname" value="">
			USER:<input type="text" id="diff_connect_user" name="diff_connect_user" value="">
			PASS:<input type="password" id="diff_connect_pass" name="diff_connect_pass" value="">
			<input type="button" value="DIFF" onclick="run_diff()">
		</div>
		
		<!-- sql結果表示 -->
		<div id="sql_panel" name="sql_panel" style="float:left; width:100%;">
			<!-- オプション(ページャー)部分 -->
			<div id="view_opt" name="view_opt"></div>
			
			<!-- sql結果内容テーブル -->
			<div id="db_viewer" style="float:left;"></div>
		</div>
EOT;

$html_end=<<<EOT
	</form>
</body>
</html>
EOT;
	echo $header.$html_start.$connect.$config.$debug.$run.$result.$html_end;
}

$app->get('/', 'main');
$app->post('/ajax', 'ajax');

$app->post('/db_option', 'ajax_db_option');
$app->post('/tbl_option', 'ajax_tbl_option');
$app->post('/db_view', 'ajax_db_view');
$app->post('/query_run', 'ajax_query_run');
$app->post('/reload', 'ajax_reload');
$app->post('/get_sql', 'ajax_get_sql');
$app->post('/diff', 'ajax_diff');

$app->run();
exit();