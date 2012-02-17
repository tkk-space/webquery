<?php

$html = array();

$setting=array(
	"dbtype"=>$_POST["setting_connect_db"]
	,"ip"=>$_POST["setting_connect_ip"]
	,"port"=>$_POST["setting_connect_port"]
	,"db"=>$_POST["db_select"]
	,"user"=>$_POST["setting_connect_user"]
	,"pass"=>$_POST["setting_connect_pass"]
	,"timeout"=>3
);

$DB = create_db($setting);

$sqlx='SELECT * FROM '.$_POST["tbl_select"];
$sqlx_limit = $sqlx.create_query_limit();

if($_POST["type"] == "db_option"){
	$html[0] = db_option($DB);
	$mes='DBに接続しました';
}else if($_POST["type"] == "tbl_option"){
	$html[0] =tbl_option($DB);
	$html[1] = db_info_html($DB);
	$mes='DBを選択しました';
}else if($_POST["type"] == "db_view"){
	$col_dat=get_column_data($DB,$sqlx_limit);
	
	$html[0] = table_viewer($DB,$sqlx_limit,$col_dat);
	$html[1] = pager_view_opt($DB,$sqlx);
	$html[2] = col_option($col_dat);
	$mes='テーブルを表示しました';
}else if($_POST["type"] == 'query_run'){
	$sqlx=preg_replace("/\\\'/i","'",$_POST["query"]);
	
	if(preg_match("/^[\s]*SELECT/i",$sqlx)){
		$col_dat=get_column_data($DB,$sqlx);
		$html[0] = table_viewer($DB,$sqlx,$col_dat);
		$html[1] = pager_view_opt($DB,$sqlx);
	}else{
		run_sql_query($DB,$sqlx,'query_run');
		$html[0].="実行しました<br><font color=\"#ff0000\">$sqlx</font><br>";
	}
	$mes='クエリを実行しました';
}else if($_POST["type"] == 'reload'){
	if($_POST["reload_num"] > 1){
		$html[0]=db_option($DB);
	}
	if($_POST["reload_num"] > 2){
		$html[1]=tbl_option($DB,$sqlx_limit);
	}
	if($_POST["reload_num"] > 3){
		$col_dat=get_column_data($DB,$sqlx_limit);
		$html[2]=col_option($col_dat);
	}
	$mes='更新しました ';

}else if($_POST["type"] == 'get_sql'){
	$html[0]=get_sql($DB,$_POST["refarence"]);
	$mes='SQLを取得';
	
}else if($_POST["type"] == 'diff'){
	$html[0]=diff_viewer($DB);
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

$result=array_merge(array(mes_csv($mes,$sqlx_mes)),$html);
print result_print($result);

$DB->disconnect;

function create_query_limit(){
	$qr_limit='';
	if((int)$_POST["limit_num"] > 0 && (int)$_POST["page_select"] > 0){
		$qr_limit=' OFFSET '.(int)((int)$_POST["page_select"]-1)*(int)$_POST["limit_num"].' LIMIT '.$_POST["limit_num"];
	}else if((int)$_POST["limit_num"] > 0){
		$qr_limit=' LIMIT '.$_POST["limit_num"];
	}
	return $qr_limit;
}

function pager_view_opt($DB,$tbl_query){
	$dat=page_cnt($DB,$tbl_query);
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
function page_cnt($DB,$tbl_query){
	$pdb=run_sql_query($DB,$tbl_query,__FUNCTION__);
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

//SQLを取得
function get_sql($DB,$type) {
	$get_sqlx=get_sql_query($type);
	$pdb=run_sql_query($DB, $get_sqlx, __FUNCTION__);
	$dat=$pdb->fetch(PDO::FETCH_ASSOC);
	return $dat["definition"];
}

//DBを表示
function db_option($DB) {
	$db_sqlx=get_db_query();
	$pdb=run_sql_query($DB,$db_sqlx,__FUNCTION__);
	$db_opt_html='<option value=""></option>';
	while($row=$pdb->fetch()){
		$selected='';
		if($_POST["db_select"] == $row['datname']){ $selected="selected"; }
		$db_opt_html.='<option value="'.$row['datname'].'" '.$selected.'>'.$row['datname'].'</option>';
	}
	return $db_opt_html;
}

function tbl_option($DB) {
	$tbl_sqlx = get_tbl_query();
	$pdb=run_sql_query($DB,$tbl_sqlx,__FUNCTION__);
	
	$type_rows=array();
	$typ_color = array('r'=>'#FFF','v'=>'#AFA','i'=>'#c9c' ,'S'=>'#9cc'    );
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
		$tbl_opt_html.='<option '.$disabled.' style="display:'.$disp.'; background:'.$typ_color[$db_ary['relkind']].';"  type="'.$db_ary['relkind'].'" value="'.$db_ary['relname'].'" '.$selected.'>'.$db_ary['relname'].'   ('.$db_ary['rows'].')</option>';
	}
	return $tbl_opt_html;
}

// カラムデータの取得
function get_column_data($DB,$col_sqlx){
	$ret=array();
	$pdb=run_sql_query($DB,$col_sqlx,__FUNCTION__);
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
		$col_html.='<option value="'.$col_dat[$i]['name'].'" '.$selected.'>'.$col_dat[$i]['name'].' ('.$col_dat[$i]['native_type'].')</option>';
	}
	return $col_html;
}

// テーブル内容のテーブルHTML作成
function table_viewer($DB,$tbl_naiyo_sqlx,$col_dat){
	$html_table_viewer='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">';
	// テーブル最初の行 フィールド名
	$html_table_viewer.='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>';
	for($i=0;$i<$col_dat['total_num'];$i++){
		$type='';
		$selected=($_POST["col_select"] == $col_dat[$i]['name'])?"selected":'';
		$html_table_viewer.='<td><a  href="javascript:void(0);" onclick="add_order(\''.$col_dat[$i]['name'].'\');" style="color:#fff" title="">'.$col_dat[$i]['name'].'</td>';
	}
	$html_table_viewer.='</tr>';
	
	$html_table_viewer.=table_viewer_line($DB,$tbl_naiyo_sqlx);
	
	$html_table_viewer.='</table>';
	
	return $html_table_viewer;
}


function table_viewer_line($DB,$tbl_naiyo_sqlx){
	$html_table_viewer_line='';
	// テーブル中身 交互に色変え
	$pdb=run_sql_query($DB,$tbl_naiyo_sqlx,__FUNCTION__);
	$t=0;
	while($data=$pdb->fetch(PDO::FETCH_ASSOC)){
		$tr_back=($t%2==0)?"fff":"ddd";
		// オンマウスで色を変える
		$mouse_over_html=' onmouseover="this.style.backgroundColor=\'#9ff\'" onmouseout="this.style.backgroundColor=\'#'.$tr_back.'\'" ';
		$html_table_viewer_line.='<tr id="table_tr_'.$t.'"style="background-color:#'.$tr_back.';" >';
		//$html_table_viewer_line.='<tr>';
		// チェックボックスで色を変える
		$html_table_viewer_line.='<td><input type="checkbox" onclick="dbview_chk_toggle(\'#table_tr_'.$t.'\');"'.$mouse_over_html.'></td>';
		
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

function mes_csv($mes,$sqlx_csv,$code=0){
	$mes_csv='';
	$mes_ary=array();
	$mes_ary[0] = date("Y/m/d(D) H:i:s");
	$mes_ary[1] = $mes;
	$mes_ary[2] = mes_info();
	$mes_ary[3] = $sqlx_csv;	
	$mes_ary[4] = $code;	
	
	for($i=0;$i<count($mes_ary);$i++){
		$mes_csv.=$mes_ary[$i].',';
	}
	return $mes_csv;
}

// わざとエラーを起こして画面上に表示
function alert($mes){
	print var_dump($mes); 
	exit;
}

function error_print($mes,$sqlx,$DB){
	// 区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array(mes_csv($mes,$sqlx,1),'','','','','');
	$DB->disconnect;
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

function run_sql_query($DB,$sqlx,$err_mes=''){
	$pdb=$DB->query($sqlx) or error_print("データベース実行エラー(".$err_mes.")：".error_disp($DB->errorInfo(),$sqlx),$sqlx,$DB);
	return $pdb;
}

function db_info_html(){
	$html='
	<span style="font-weight:bold;font-size:large;">DIFF</span>
	HOST:<input type="text" id="diff_connect_ip" name="diff_connect_ip" value="'.$_POST{'setting_connect_ip'}.'">
	DB:<input type="text" id="diff_connect_db" name="diff_connect_db" value="">
	USER:<input type="text" id="diff_connect_user" name="diff_connect_user" value="'.$_POST{'setting_connect_user'}.'">
	PASS:<input type="password" id="diff_connect_pass" name="diff_connect_pass" value="'.$_POST{'setting_connect_pass'}.'">
	<input type="button" value="DIFF" onclick="run_diff()">
	';
	return $html;
}

function diff_viewer($DB){
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
	$str_query  = get_tbl_info_query($table_name);
	$table_date = gethashs($db, $str_query);
	foreach($table_date as $table_datum){
		$ret[$table_datum['attname']] = $table_datum['typname'];
	}
	return $ret;
}

function gethashs($db,$sqlx){
	$fd = array();
	$re=run_sql_query($db,$sqlx);
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
	if(!$setting["dbtype"]){ $setting["dbtype"]='pgsql'; }
	if(!$setting["port"]){ $setting["port"]='5432'; }
	
	$dsn=$setting["dbtype"].':';
	$dsn.='host='.$setting["ip"];
	$dsn.=($setting["db"] != '' && $setting["db"] != 'reading...')?' dbname='.$setting["db"]:'';
	$dsn.=($setting["user"])?' user='.$setting["user"]:'';
	$dsn.=($setting["pass"])?' password='.$setting["pass"]:'';
	$dsn.=($setting["port"])?' port='.$setting["port"]:'';
	$dsn.=($setting["timeout"])?' connect_timeout='.$setting["timeout"]:'';
	
	$DB = new PDO($dsn) or error_print('データベース接続エラー(.'.$_POST["setting_connect_name"].'.)',$dsn);
	return $DB;
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
	$query="SELECT datname FROM pg_database WHERE datname NOT IN ('template0','template1') ORDER BY datname;";
	return $query;
}



function get_tbl_query(){
	$query="
		SELECT c.relname,c.relkind,reltuples as rows,pg_relation_size(relname::regclass)
		FROM pg_catalog.pg_class c
		JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
		LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
		WHERE n.nspname NOT IN ('pg_catalog', 'pg_toast') AND 
		pg_catalog.pg_table_is_visible(c.oid)
		ORDER BY relkind, relname;
";
	//alert($query);
	return $query;
}

function get_tbl_info_query($table_name){
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

	return $query;
}

?>