<?php

$html = array();

$connect_timeout=3;

$dsn='host='.$_POST["setting_connect_ip"];
$dsn.=($_POST["db_select"] != '' && $_POST["db_select"] != 'reading...')?' dbname='.$_POST["db_select"]:'';
$dsn.=($_POST["setting_connect_user"])?' user='.$_POST["setting_connect_user"]:'';
$dsn.=($_POST["setting_connect_pass"])?' password='.$_POST["setting_connect_pass"]:'';
$dsn.=($_POST["setting_connect_port"])?$dsn.=' port='.$_POST["setting_connect_port"]:' port=5432';
$dsn.=($connect_timeout)?" connect_timeout=$connect_timeout":'';

$DB=pg_connect($dsn) or error_print('データベース接続エラー(.'.$_POST["setting_connect_name"].'.)',$dsn);

if($_POST["type"] == "db_option"){
	$html[0]=db_option($DB);
	$mes='DBに接続しました';
}else if($_POST["type"] == "tbl_option"){
	$html =tbl_option($DB);
	$mes='DBを選択しました';
}else if($_POST["type"] == 'db_view'){
	$sqlx='SELECT * FROM '.$_POST["tbl_select"].' '.create_query_limit();
	$html[0]=table_naiyo($DB,$sqlx);
	$html[1]=pager_create($DB,$sqlx);
	$html[2]=col_option($DB,$sqlx);
	$mes='テーブルを表示しました';
}else if($_POST["type"] == 'query_run'){
	$sqlx=$_POST["query"];
	if(preg_match("/^[\s]*SELECT/i",$sqlx)){
		$html[0].=table_naiyo($DB,$sqlx);
		$html[1].=pager_create($DB,$sqlx);
	}else{
		run_sql_query($DB,$sqlx);
		$html[0].="実行しました<br><font color=\"#ff0000\">$sqlx</font><br>";
	}
	$mes='クエリを実行しました';
}else if($_POST["type"] == 'reload'){
	$sqlx='SELECT * FROM '.$_POST["tbl_select"].' '.create_query_limit();
	$html[0]=db_option($DB);
	$html[1]=tbl_option($DB,$sqlx);
	$html[2]=col_option($DB,$sqlx);
	$html[3]=table_naiyo($DB,$sqlx);
	$html[4]=pager_create($DB,$sqlx);
	$mes='更新しました ';
}else{
	error_print("タイプ実行エラー：".$_POST["type"],'');
}

# 結果表示
if($mes){
	$result=array_merge(array(mes_csv($mes,$sqlx)),$html);
	print result_print($result);
}

//$pdb->finish();
//$DB->disconnect;

function create_query_limit(){
	$qr_limit='';
	if((int)$_POST["limit_num"] > 0 && (int)$_POST["page_select"] > 0){
		$qr_limit='OFFSET '.(int)((int)$_POST["page_select"]-1)*(int)$_POST["limit_num"].' LIMIT '.$_POST["limit_num"];
	}else if((int)$_POST["limit_num"] > 0){
		$qr_limit=' LIMIT '.$_POST["limit_num"];
	}
	return $qr_limit;
}

function pager_create($DB,$tbl_query){
	$dat=page_cnt($DB,$tbl_query);
	$start_row=(($_POST["page_select"] * $_POST["limit_num"])-$_POST["limit_num"] + 1);
	$end_row=($_POST["page_select"] * $_POST["limit_num"]);
	$pager='
	<div>
	<input type="button" value="&lt;&lt;" '.pager_onclick(-100).' name="page_first" id="page_first">
	<input type="button" value="&lt;" '.pager_onclick(-1).' name="page_back" id="page_back">
	['. $start_row .' - '.$end_row.' / '.$dat{rows}.']
	<select style="width:40px;" onchange="'.pager_ajax().'" type="text" size="1" name="page_select" id="page_select">
	'.$dat["page_opt_html"].'
	</select>
	/'.$dat["page_num"].'
	<input type="button" value="&gt;" '.pager_onclick(1).' name="page_next" id="page_next">
	<input type="button" value="&gt;&gt;" '.pager_onclick(100).' name="page_last" id="page_last">
	</div>
	';
	return $pager;
}

function pager_onclick($num){
	return 'onclick="page_sel(\'page_select\',\''.$num.'\'); '.pager_ajax().'"';
}

function pager_ajax($num){
	return 'run_ajax(\'db_view\',\'db_viewer,view_opt\');"';
}

// カウント・ページ数の計算
function page_cnt($DB,$tbl_query){
	$pdb=run_sql_query($DB,$tbl_query);
	$rows=pg_num_rows($pdb);
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
	$datname=pg_query($DB,$db_sqlx) or die(error_print('データベース実行エラー('.$_POST["type"].')：'.pg_last_error($DB),$db_sqlx)); 

	$db_opt_html='<option value=""></option>';
	while($row=pg_fetch_array($datname)){
		$selected='';
		if($_POST["db_select"] == $row['datname']){ $selected="selected"; }
		$db_opt_html.='<option value="'.$row['datname'].'" '.$selected.'>'.$row['datname'].'</option>';
	}
	return $db_opt_html;
}

function tbl_option($DB) {
	$tbl_sqlx = get_tbl_query();
	$pdb=pg_query($DB,$tbl_sqlx) or error_print("データベース実行エラー(".$_POST["type"].")：".pg_last_error($DB),$tbl_sqlx);
	$type_rows=array();
	$typ_color=array('r'=>'#FFF','v'=>'#AFA','s'=>'#9cc','i'=>'#c9c');
	$typ_name=array('r'=>'TABLE','v'=>'VIEW','i'=>'INDEX','s'=>'SEQUENCE','S'=>'SPECIAL');
	
	# テーブルのオプション表示、テーブル表示
	$tbl_opt_html='<option value=""></option>';
	
	while($db_ary=pg_fetch_array($pdb)){
		$type_rows[$db_ary[1]]++;
		$types=$_POST["setting_tblsel_view_type"];
		if(in_array($db_ary[1],$types)){
			$selected='';
			if($_POST["tbl_select"] == $db_ary[0]){ $selected="selected"; }
			// テーブル以外の場合タイプ名を右につける
			if($db_ary['relkind']!='r'){ $db_ary['rows']=$db_ary['relkind']; }
			$tbl_opt_html.='<option style="background:'.$typ_color[$db_ary['relkind']].';"value="'.$db_ary['relname'].'" '.$selected.'>'.$db_ary['relname'].'   ('.$db_ary['rows'].')</option>';
		}
	}
	$tbl_tbl_html.='</table>';
	
	$tbl_typ_opt_html='<option></option>';
	foreach($type_rows as $type){
		if($type_rows[$type] > 0){
			$tbl_typ_opt_html.='<option value="'.$type.'"> '.$tbl_typ_opt_name[$type].' ('.$type_rows[$type].')</option>';
		}
	}
	
	return array($tbl_opt_html,$tbl_typ_opt_html);
}

//列名の取得
function col_option($DB,$col_sqlx){
	$pdb=run_sql_query($DB,$col_sqlx);
	$col_html.= "<option></option>";
	$field_num=pg_num_fields($pdb);
	for ($i = 0; $i < $field_num; $i++){
		$name=pg_field_name($pdb,$i);
		$type=pg_field_type($pdb,$i);
	
		$selected=($_POST["col_select"] == $name)?"selected":'';
		$col_html.='<option value="'.$name.'" '.$selected.'>'.$name.' ('.$type.')</option>';
	}
	return $col_html;
}

// テーブル内容のテーブルHTML作成
function table_naiyo($DB,$tbl_naiyo_sqlx){
	$pdb=run_sql_query($DB,$tbl_naiyo_sqlx);
	$rows=pg_num_rows($pdb);
	$html_table_naiyo='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">';
	
	// フィールド名
	$html_table_naiyo.='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>';
	$field_num=pg_num_fields($pdb);
	for ($i = 0; $i < $field_num; $i++){
		$name=pg_field_name($pdb,$i);
		$type=pg_field_type($pdb,$i);
		
		// order byを追加する
		$html_table_naiyo.='<td><a  href="javascript:void(0);" onclick="add_order(\''.$name.'\');" style="color:#fff" title="">'.$name.'</td>';
	}
	$html_table_naiyo.='</tr>';
	
	// 交互に色変え
	$t=0;
	while($data=pg_fetch_row($pdb)){
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

function error_print($mes,$sqlx){
	#区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array(mes_csv($mes,$sqlx),'','','','','');
	die(result_print($result));
}

# 返す文字列（<##!##>が区切り文字）
# <##!##>メッセージCSV<##!##>表示されるHTML1<##!##>HTML2..3..
function result_print($ary){
	//$ary = @{shift()};
	$sep='<##!##>';
	$print_str='';
	foreach ($ary as $_){
		$print_str .= $_.$sep;
	}
	return $print_str;
}

function run_sql_query($DB,$sqlx){
	$pdb=pg_query($DB,$sqlx) or error_print("データベース実行エラー(".$_POST["type"].")：".pg_last_error($DB),$sqlx);
	return $pdb;
}

function check_db_state($DB1,$DB2){
	$result = doDiffDb($DB1,$DB2);
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

function doDiffDb($DB1,$DB2){
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
						$tmp[4] = sprintf("【%s】",$_SESSION['dbname1']);
					}
					if(!empty($columns2[$all_columnKey])){
						$tmp[3] = '○';
					}else{
						$tmp[4] = sprintf("【%s】",$_SESSION['dbname2']);
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
				$tmp[4] = '【'.$_SESSION['dbname1'].'】に【'.$all_table.'】がありません。';
			}else{

			}
			
			if(in_array($all_table,$tables2)){
				$tmp[3] = '○';
				$tmp[4] = '【'.$_SESSION['dbname2'].'】に【'.$all_table.'】がありません。';
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
	$str_query  = '';
	$str_query .= ' SELECT ';
	$str_query .= '  pg_attribute.attnum, ';
	$str_query .= '  pg_attribute.attname, ';
	$str_query .= '  pg_type.typname ';
	$str_query .= ' FROM ';
	$str_query .= '  pg_class, ';
	$str_query .= '  pg_attribute, ';
	$str_query .= '  pg_type ';
	$str_query .= ' WHERE ';
	$str_query .= '  pg_class.oid = pg_attribute.attrelid and ';
	$str_query .= '  pg_attribute.atttypid = pg_type.oid and ';
	$str_query .= "  pg_class.relname='$table_name' and ";
	$str_query .= '  pg_attribute.attnum > 0 ';
	$str_query .= ' ORDER BY ';
	$str_query .= '  pg_attribute.attnum;  ';

	$table_date = gethashs($db, $str_query);
	foreach($table_date as $table_datum){
		$ret[$table_datum['attname']] = $table_datum['typname'];
	}
	return $ret;
}

function gethashs($db,$sqlx){
	$fd = array();
	$re=pg_query($db,$sqlx);
	if($re!=''){
		while($rec=pg_fetch_assoc($re)){
			$fd[] = $rec;
		}
	}
	return $fd;
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
	$mes_ary[1] = mes_info();
	$mes_ary[2] = $mes;
	$mes_ary[3] = $sqlx_csv;
	
	for($i=0;$i<count($mes_ary);$i++){
		$mes_csv.=$mes_ary[$i].',';
	}
	return $mes_csv;
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
?>