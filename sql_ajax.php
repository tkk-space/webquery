<?php

$html = array();

$setting=array(
	"dbtype"=>$_POST["setting_connect_db"]
	,"ip"=>$_POST["setting_connect_ip"]
	,"port"=>$_POST["setting_connect_ip"]
	,"db"=>$_POST["db_select"]
	,"user"=>$_POST["setting_connect_user"]
	,"pass"=>$_POST["setting_connect_pass"]
	,"timeout"=>3
);

$dsn=create_dsn($setting);

$DB = new PDO($dsn) or error_print('データベース接続エラー(.'.$_POST["setting_connect_name"].'.)',$dsn);

$sqlx='SELECT * FROM '.$_POST["tbl_select"];
$sqlx_limit = $sqlx.create_query_limit();

if($_POST["type"] == "db_option"){
	$html[0] = db_option($DB);
	$mes='DBに接続しました';
}else if($_POST["type"] == "tbl_option"){
	$html[0] =tbl_option($DB);
	$mes='DBを選択しました';
}else if($_POST["type"] == "db_view"){
	$col_dat=get_column_data($DB,$sqlx_limit);
	
	$html[0] = table_naiyo($DB,$sqlx_limit,$col_dat);
	$html[1] = pager_create($DB,$sqlx);
	$html[2] = col_option($col_dat);
	$mes='テーブルを表示しました';
}else if($_POST["type"] == 'query_run'){
	$sqlx=preg_replace("/\\\'/i","'",$_POST["query"]);
	
	if(preg_match("/^[\s]*SELECT/i",$sqlx)){
		$html[0] = table_naiyo($DB,$sqlx);
		$html[1] = pager_create($DB,$sqlx);
	}else{
		run_sql_query($DB,$sqlx,'query_run');
		$html[0].="実行しました<br><font color=\"#ff0000\">$sqlx</font><br>";
	}
	$mes='クエリを実行しました';
}else if($_POST["type"] == 'reload'){
	if ($_POST["reload_num"] == 1){
		$html[0]=db_option($DB);
	}else if($_POST["reload_num"] == 2){
		$html[0]=db_option($DB);
		$html[1]=tbl_option($DB,$sqlx_limit);
		break;
	}else if($_POST["reload_num"] == 3){
		$col_dat=get_column_data($DB,$sqlx_limit);
		$html[0]=db_option($DB);
		$html[1]=tbl_option($DB,$sqlx_limit);
		$html[2]=col_option($col_dat);
	}
	
	$mes='更新しました ';
}else if($_POST["type"] == 'diff'){
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

function pager_create($DB,$tbl_query){
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
	$pdb=run_sql_query($DB,$tbl_query,'page_cnt');
	$rows=$pdb->rowcount();
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

//DBを表示
function db_option($DB) {
	$db_sqlx="SELECT datname FROM pg_database WHERE datname NOT IN ('template0','template1') ORDER BY datname;";
	$pdb=run_sql_query($DB,$db_sqlx,'db_option');
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
	$pdb=run_sql_query($DB,$tbl_sqlx,'tbl_option');
	
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
	$pdb=run_sql_query($DB,$col_sqlx,'get_column_data');
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
function table_naiyo($DB,$tbl_naiyo_sqlx,$col_dat){
	$html_table_naiyo='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">';
	// テーブル最初の行 フィールド名
	$html_table_naiyo.='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>';
	for($i=0;$i<$col_dat['total_num'];$i++){
		$type='';
		$selected=($_POST["col_select"] == $col_dat[$i]['name'])?"selected":'';
		$html_table_naiyo.='<td><a  href="javascript:void(0);" onclick="add_order(\''.$col_dat[$i]['name'].'\');" style="color:#fff" title="">'.$col_dat[$i]['name'].'</td>';
	}
	$html_table_naiyo.='</tr>';
	
	// テーブル中身 交互に色変え
	$pdb=run_sql_query($DB,$tbl_naiyo_sqlx,'table_naiyo');
	$t=0;
	while($data=$pdb->fetch(PDO::FETCH_ASSOC)){
		$tr_back=($t%2==0)?"fff":"ddd";
		// オンマウスで色を変える
		$html_table_naiyo.='<tr id="table_tr_'.$t.'"style="background-color:#'.$tr_back.';" onmouseover="this.style.backgroundColor=\'#9ff\'" onmouseout="this.style.backgroundColor=\'#'.$tr_back.'\'">';
		$html_table_naiyo.='<td><input type="checkbox" onclick="dbview_chk_toggle(\'#table_tr_'.$t.'\');"></td>';
		
		foreach($data as $dat){
			if((int)$_POST["setting_value_limit"] != 0 && strlen($dat)>(int)$_POST["setting_value_limit"]){
				$dat='<a style="color:#000066;" href="javascript:void(0);" title="'.htmlspecialchars($dat).'">'.substr(htmlspecialchars($dat),0,(int)$_POST["setting_value_limit"]).'...</a>';
			}else{
				$dat=htmlspecialchars($dat);
			}
			if($dat == ''){ $dat='&nbsp;'; }
			$html_table_naiyo.='<td style="white-space: nowrap;">'.$dat.'</td>';
		}
		$html_table_naiyo.='</tr>';
		$t++;
	}
	
	$html_table_naiyo.='</table>';
	
	return $html_table_naiyo;
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

function mes_csv($mes,$sqlx_csv){
	$mes_csv='';
	$mes_ary=array();
	$mes_ary[0] = date("Y/m/d(D) H:i:s");
	$mes_ary[1] = $mes;
	$mes_ary[2] = mes_info();
	$mes_ary[3] = $sqlx_csv;
	
	for($i=0;$i<count($mes_ary);$i++){
		$mes_csv.=$mes_ary[$i].',';
	}
	return $mes_csv;
}

// わざとエラーを起こして画面上に表示
function error_alert($mes){
	print $mes; 
	exit;
}

function error_print($mes,$sqlx){
	// 区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array(mes_csv($mes,$sqlx),'','','','','');
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
	$pdb=$DB->query($sqlx) or error_print("データベース実行エラー(".$err_mes.")：".error_disp($DB->errorInfo(),$sqlx),$sqlx);
	return $pdb;
}

function check_db_state($DB1,$DB2){
	$dbname1='';
	$dbname2='';
	$result = doDiffDb($DB1,$DB2,$dbname1,$dbname2);
	$ret='
	<h1>Diff</h1>
	<table border="1">
	<tr>
		<th>No</th>
		<th>テーブル名</th>
		<th>カラム名</th>
		<th>'.$dbname1.'</th>
		<th>'.$dbname2.'</th>
		<th>備考</th>
	</tr>';
	$no = 0;
	foreach($result as $columns){
		$no++;
		$ret.="<tr><td>$no</td>";
		foreach($columns as $cell){
			$ret.="<td>$cell</td>";
		}
		$ret.="</tr>";
	}
	$ret.='</table>';
}

function doDiffDb($DB1,$DB2,$dbname1,$dbname2){
	$tables1 = _get_tables($DB1);
	$tables2 = _get_tables($DB2);

	$all_tables = array_unique(array_merge($tables1, $tables2));

	foreach($all_tables as $all_table){
		if(in_array($all_table,$tables1) && in_array($all_table,$tables2)){
			$columns1    = _get_table_info($DB1,$all_table);
			$columns2    = _get_table_info($DB2,$all_table);
			$all_columns = array_merge($columns1, $columns2);
			foreach($all_columns as $all_columnKey=>$all_columnVal){
				$tmp = array('','&nbsp;','&nbsp;','&nbsp;','&nbsp;');
				if(
					!array_key_exists($all_columnKey, $columns1) ||
					!array_key_exists($all_columnKey, $columns2)
				){
					$tmp[0] = $all_table;
					$tmp[1] = $all_columnKey;
					
					if(!empty($columns1[$all_columnKey])){
						$tmp[2] = '○';
					}else{
						$tmp[4] = sprintf("【%s】",$dbname1);
					}
					if(!empty($columns2[$all_columnKey])){
						$tmp[3] = '○';
					}else{
						$tmp[4] = sprintf("【%s】",$dbname2);
					}
					
					$tmp[4] .= 'に【'.$all_columnKey.'】が存在しません';
					if($tmp[2] == '○' && $tmp[3] == '○'){
						$tmp[4] = 'よく分からないから確認して！！';
					}
					
				}else if($columns1[$all_columnKey] != $columns2[$all_columnKey]){
					$tmp[0] = $all_table;
					$tmp[1] = $all_columnKey;
					$tmp[2] = $columns1[$all_columnKey];
					$tmp[3] = $columns2[$all_columnKey];
					$tmp[4] = '型が一致しません';
				}
				if($tmp[0] != ''){$ret[] = $tmp;}
			}
		}else{
			$tmp = array('','&nbsp;','&nbsp;','&nbsp;','&nbsp;');
			$tmp[0] = $all_table;
			$tmp[1] = '-';
			if(in_array($all_table,$tables1)){
				$tmp[2] = '○';
				$tmp[4] = '【'.$dbname1.'】に【'.$all_table.'】がありません。';
			}else{

			}
			
			if(in_array($all_table,$tables2)){
				$tmp[3] = '○';
				$tmp[4] = '【'.$dbname2.'】に【'.$all_table.'】がありません。';
			}else{

			}
			if($tmp[0] != ''){$ret[] = $tmp;}
		}

	}
	return $ret;
}

function _get_tables($db){
	$ret = array();
	$str_query=get_tbl_query();
	$tables = gethashs($db, $str_query);
	foreach($tables as $table){
		$ret[] = $table['relname'];
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
	$re=$db->query($db,$sqlx);
	if($re!=''){
		while($rec=pg_fetch_assoc($re)){
			$fd[] = $rec;
		}
	}
	return $fd;
}

function get_tbl_query(){
	$query="
		SELECT c.relname,c.relkind,reltuples as rows
		FROM pg_catalog.pg_class c
		JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
		LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
		WHERE n.nspname NOT IN ('pg_catalog', 'pg_toast') 
		AND pg_catalog.pg_table_is_visible(c.oid)
		ORDER BY relkind, relname;
";
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

function error_disp($errinfo,$sqlx){
	return $errinfo[2].' SQL内容:['.$sqlx.']';
}

function create_dsn($setting){
	$dsn=$setting["dbtype"].':';
	$dsn.='host='.$setting["ip"];
	$dsn.=($setting["db"] != '' && $setting["db"] != 'reading...')?' dbname='.$setting["db"]:'';
	$dsn.=($setting["user"])?' user='.$setting["user"]:'';
	$dsn.=($setting["pass"])?' password='.$setting["pass"]:'';
	//$dsn.=($setting["port"])?$dsn.=' port='.$setting["port"]:' port=5432';
	$dsn.=($setting["timeout"])?' connect_timeout='.$setting["timeout"]:'';
	return $dsn;
}


?>