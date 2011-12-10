<?php

$html = array();

$connect_timeout=3;

$dsn='host='.$_POST["setting_connect_ip"];
$dsn.=($_POST["db_select"] != '' && $_POST["db_select"] != 'reading...')?' dbname='.$_POST["db_select"]:'';
$dsn.=($_POST["setting_connect_user"])?' user='.$_POST["setting_connect_user"]:'';
$dsn.=($_POST["setting_connect_pass"])?' password='.$_POST["setting_connect_pass"]:'';
$dsn.=($_POST["setting_connect_port"])?$dsn.=' port='.$_POST["setting_connect_port"]:' port=5432';
$dsn.=($connect_timeout)?" connect_timeout=$connect_timeout":'';

$DB=pg_connect($dsn) or error_print('データベース接続エラー(.'.$_POST["setting_connect_name"].'.)：'.$dsn);

if($_POST["type"] == "db_option"){
	$rnum=2;
	$html[0]=db_option($DB);
	$mes=' DBに接続しました。'.mes_info();
}else if($_POST["type"] == "tbl_option"){
	$rnum=2;
	$html =tbl_option($DB);
	$mes='DBを選択しました '.mes_info();
}else if($_POST["type"] == 'db_view'){
	$rnum=5;
	$sqlx[0]='SELECT * FROM '.$_POST["tbl_select"].' '.create_query_limit();
	$html[0]=table_naiyo($DB,$sqlx[0]);
	$html[1]=pager_create($DB,$sqlx[0]);
	$html[2]=col_option($DB,$sqlx[0]);
	$mes='テーブルを表示しました。'.mes_info();
}else if($_POST["type"] == 'query_run'){
	$pager='';
	$sqlx[0]=$_POST["query"];
	
	if(preg_match("/^[\s]*SELECT/i",$sqlx[0])){
		$html[0].=table_naiyo($DB,$sqlx[0]);
		$html[1].=pager_create($DB,$sqlx[0]);
	}else{
		run_sql_query($DB,$sqlx[0]);
		$html[0].="実行しました。<br><font color=\"#ff0000\">$sqlx[0]</font><br>";
	}
	
	$mes='クエリを実行しました('.$_POST["query"].') '.mes_info();
}else if($_POST["type"] == 'reload'){
	$rnum=2;
	$sqlx[0]='SELECT * FROM '.$_POST["tbl_select"].' '.create_query_limit();
	$html[0]=db_option($DB);
	$html[1]=tbl_option($DB,$sqlx[0]);
	$html[2]=col_option($DB,$sqlx[0]);
	$html[3]=table_naiyo($DB,$sqlx[0]);
	$html[4]=pager_create($DB,$sqlx[0]);
	
	$mes=$_POST["tbl_select"].'更新しました '.mes_info();
}else{
	error_print("タイプ実行エラー：".$_POST["type"]);
}

# 結果表示
if($mes){
	$result=array_merge(array($rnum,$sqlx[0],$mes),$html);
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
	$pager='
	<div>
	<input type="button" value="&lt;&lt;" onclick="page_sel(\'page_select\',-100); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_first" id="page_first">
	<input type="button" value="&lt;" onclick="page_sel(\'page_select\',-1); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_back" id="page_back">
	['.(($_POST["page_select"] * $_POST["limit_num"])-$_POST["limit_num"] + 1) .' - '.($_POST["page_select"] * $_POST["limit_num"]).' / '.$dat{rows}.']
	<select style="width:40px;" onchange="run_ajax(\'db_view\',\'db_viewer,view_opt\');" type="text" size="1" name="page_select" id="page_select">
	'.$dat["page_opt_html"].'
	</select>
	/'.$dat["page_num"].'
	<input type="button" value="&gt;" onclick="page_sel(\'page_select\',1); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_next" id="page_next">
	<input type="button" value="&gt;&gt;" onclick="page_sel(\'page_select\',100); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_last" id="page_last">
	</div>
	';
	return $pager;
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
	$datname=pg_query($DB,$db_sqlx) or die(error_print('データベース実行エラー('.$_POST["type"].')：'.pg_last_error($DB))); 

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
	$pdb=pg_query($DB,$tbl_sqlx) or error_print("データベース実行エラー(".$_POST["type"].")：".pg_last_error($DB));
	
	# テーブルのオプション表示、テーブル表示
	$tbl_opt_html='<option value=""></option>';
	$tbl_tbl_html='<table cellpadding="0" style="font-size:small;background-color:#ddffff;float:left;">';
	while($db_ary=pg_fetch_array($pdb)){
		$type_rows[$db_ary[1]]++;
		$types=array($_POST["setting_tblsel_view_type_r"],$_POST["setting_tblsel_view_type_v"],$_POST["setting_tblsel_view_type_s"],$_POST["setting_tblsel_view_type_i"],$_POST["setting_tblsel_view_type_sp"]);
		if(in_array($db_ary[1],$types)){
			$selected='';
			$opt_color='';
			if($_POST["tbl_select"] == $db_ary[0]){ $selected="selected"; }
			if($db_ary[1] == 'r' ){
				$opt_color='#FFF';
			}else if($db_ary[1] == 'v'){
				$opt_color='#AFA';
				$db_ary[2]='v';
			}else if($db_ary[1] == 'S'){
				$opt_color='#9cc';
				$db_ary[2]='S';
			}else if($db_ary[1] == 'i'){
				$opt_color='#c9c';
				$db_ary[2]='i';
			}
			$tbl_opt_html.='<option style="background:'.$opt_color.';"value="'.$db_ary['relname'].'" '.$selected.'>'.$db_ary['relname']."   ($db_ary[2])</option>";
			$tbl_tbl_html.='<tr bgcolor="#ddffff" onMouseOver="this.style.background=\'#ffcc00\'" onMouseOut="this.style.background=\'#ddffff\'"><td>';
			$tbl_tbl_html.='<a href="javascript:void(0);" onClick="run_ajax(\'db_view\',\'db_viewer,page_select\',\'tbl_select2='.$db_ary['relname'].'\')">'.$db_ary['relname']."  ($db_ary[2])</a>";
			$tbl_tbl_html.='</td></tr>';
		}
	}
	$tbl_tbl_html.='</table>';
	
	$tbl_typ_opt_html='<option></option>';
	foreach($type_rows as $type){
		if($type_rows[$type] > 0){
			if		($type == 'r'){ $tbl_typ_opt_html.='<option value="r">TABLE('.$type_rows[$type].')</option>'; }
			else if	($type == 'v'){ $tbl_typ_opt_html.='<option value="v">VIEW('.$type_rows[$type].')</option>'; }
			else if	($type == 'i'){ $tbl_typ_opt_html.='<option value="i">INDEX('.$type_rows[$type].')</option>'; }
			else if	($type == 'S'){ $tbl_typ_opt_html.='<option value="S">SEQUENCE('.$type_rows[$type].')</option>'; }
			else if	($type == 's'){ $tbl_typ_opt_html.='<option value="s">SPECIAL('.$type_rows[$type].')</option>'; }
		}
	}
	
	return array($tbl_opt_html,$tbl_tbl_html,$tbl_typ_opt_html);
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

function error_print($mes){
	#区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array('0',$sqlx[0],$mes,'','','','','');
	die(result_print($result));
}

# 返す文字列（<##!##>が区切り文字）
# SQL<##!##>結果(数字)<##!##>メッセージ文<##!##>表示されるHTML1<##!##>HTML2..3..
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
	$pdb=pg_query($DB,$sqlx) or error_print("データベース実行エラー(".$_POST["type"].")：".pg_last_error($DB).'['.$sqlx.']');
	return $pdb;
}

function check_db_state(){
	$result = doDiffDb();
?>
	<h1>Diff</h1>
	<form method="POST" action=" echo $_SERVER['SCRIPT_NAME']; ?>">
		<input type="hidden" name="chg_db" value="1" />
		<input type="submit" value="DB変更" />
	</form>
	<table border="1">
	<tr>
		<th>No</th>
		<th>テーブル名</th>
		<th>カラム名</th>
		<th><?php echo $_SESSION['dbname1'];?></th>
		<th><?php echo $_SESSION['dbname2'];?></th>
		<th>備考</th>
	</tr>

	<?php
	$no = 0;
	foreach($result as $columns){
		echo "<tr>";
			$no++;
			echo "<td>$no</td>";
		foreach($columns as $cell){
			echo "<td>$cell</td>";
		}
		echo "</tr>";
	}
	?>
	</table>

<?php
}

function doDiffDb(){
	global $db1,$db2;
	$tables1 = _get_tables($db1);
	$tables2 = _get_tables($db2);

	$all_tables = array_unique(array_merge($tables1, $tables2));

	foreach($all_tables as $all_table){
		if(in_array($all_table,$tables1) && in_array($all_table,$tables2)){
			$columns1    = _get_table_info($db1,$all_table);
			$columns2    = _get_table_info($db2,$all_table);
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