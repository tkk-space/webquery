<?php
require_once( "../xajax_core/xajax.inc.php" );

function main_ajax(){
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
	
	$result=array_merge(array(mes_tsv($mes,$sqlx_mes)),$html);
	print result_print($result);
	$DB->disconnect;
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
	exit;
}

function error_print($mes,$sqlx,$DB){
	// 区切り文字を入れて200 OKとかの奴を最後にもっていく
	$result=array(mes_tsv($mes,$sqlx,1),'','','','','');
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

function key_radio_forms($id_name){
	for($i=0;$i<7;$i++){
		$key_code=$i+117;
		$key_name='F'.(6+$i);
		?>
		<label>
		<input type="radio" id="<?=$id_name?>" name="<?=$id_name?>" value="<?=$key_code?>"	onchange="ls_save('<?=$id_name?>');"/>
			<?=$key_name?>
		</label>
		<?php
	}
}

function table_set_forms(){
	$types=array('r'=>'table','v'=>'view','S'=>'sequence','i'=>'index');
	foreach ($types as $key => $value) {
		$checked=($key=='r')?'checked':'';
		$id='setting_tblsel_view_type_'.$key;
		?>
		<label>
		<input type="checkbox" name="setting_tblsel_view_type[]" id="<?=$id?>" onclick="ls_save('<?=$id?>'); tbl_type_change('<?=$key?>')" value="<?=$key?>" <?=$checked?>/><?=$value?>
		</label>
		<?php
	}
}

function limitnum_set_forms(){
	$numlist=array('10','30','50','100','200');
	foreach($numlist as $num){
		?>
		<label>
			<input type="radio" id="limit_num" name="limit_num" value="<?=$num?>"  onchange="ls_save('limit_num');" checked /><?=$num?>
		</label>
		<?php
	}
	?>
	<label>
		<input type="radio" id="limit_num" name="limit_num" value=""  onchange="ls_save('limit_num');" />無制限
	</label>
	<?php
}


// tests the select form
function testForm( $formData )
{
	$objResponse=new xajaxResponse();
	$objResponse->alert( "formData: " . print_r( $formData, true ) );
	$objResponse->assign( "submittedDiv", "innerHTML", nl2br( print_r( $formData ) ) );
	return $objResponse;
}

// adds an option to the select
function addInput( $aInputData )
{
	$sId=$aInputData['inputId'];
	$sName=$aInputData['inputName'];
	$sType=$aInputData['inputType'];
	$sValue=$aInputData['inputValue'];

	$objResponse=new xajaxResponse();

	$sParentId="testForm1";

	if ( isset( $aInputData['inputWrapper'] ) )
	{
		$sDivId=$sId . '_div';
		$objResponse->append( $sParentId, "innerHTML", '<div id="' . $sDivId . '"></div>' );
		$sParentId=$sDivId;
	}

	$objResponse->alert( "inputData: " . print_r( $aInputData, true ) );
	$objResponse->createInput( $sParentId, $sType, $sName, $sId );
	$objResponse->assign( $sId, "value", $sValue );
	return $objResponse;
}

// adds an option to the select
function insertInput( $aInputData )
{
	$sId=$aInputData['inputId'];
	$sName=$aInputData['inputName'];
	$sType=$aInputData['inputType'];
	$sValue=$aInputData['inputValue'];
	$sBefore=$aInputData['inputBefore'];

	$objResponse=new xajaxResponse();
	$objResponse->alert( "inputData: " . print_r( $aInputData, true ) );
	$objResponse->insertInput( $sBefore, $sType, $sName, $sId );
	$objResponse->assign( $sId, "value", $sValue );
	return $objResponse;
}

function removeInput( $aInputData )
{
	$sId=$aInputData['inputId'];
	$objResponse=new xajaxResponse();
	$objResponse->remove( $sId );
	return $objResponse;
}

$xajax=new xajax();
//$xajax->configure("debug", true);
$xajax->register(XAJAX_FUNCTION, "testForm");
$xajax->register(XAJAX_FUNCTION, "addInput");
$xajax->register(XAJAX_FUNCTION, "insertInput");
$xajax->register(XAJAX_FUNCTION, "removeInput");

$xajax->processRequest();
$xajax->configure('javascript URI','./xajax/');
$xajax->configure('responseType','html');

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title id="title">WebQuery [<?= $_SERVER["SERVER_NAME"]; ?>]</title>

<?php $xajax->printJavascript( "../" ) ?>
<script type="text/javascript">
"use strict";



// 日時などの桁を合わせる
function conv2deg(val) {
	val = "00" + val;
	return val.substr(val.length - 2, 2);
}


// 現在の日時テキストを取得
function get_date_txt() {
	var date = new Date();
	date = (conv2deg(date.getMonth() + 1)) + "/" + conv2deg(date.getDate()) + " " + conv2deg(date.getHours()) + ":" + conv2deg(date.getMinutes()) + ":" + conv2deg(date.getSeconds());
	return date;
}


//重複を取り除く関数
function unique(array) {
	var storage, uniqueArray, i, value;
	for (i = 0; i < array.length; i++) {
		value = array[i];
		if (!(value in storage)) {
			storage[value] = true;
			uniqueArray.push(value);
		}
	}
	return uniqueArray;
}


// テキスト整形
function text_clean(value) {
	value = value.replace(/^[\s]/g, "");
	value = value.replace(/[\s]$/g, "");
	value = value.replace(/\s,\s/g, ",");
	value = value.replace(/[\s]+/g, " ");
	
	value = value.replace(/\t/g, " ");
	value = value.replace(/\r\n/g, " ");
	value = value.replace(/\n/g, " ");
	// value = value.replace(/,([^ ])/g , ', $1');
	// value = value.replace(/[^ ]\([^ ]/g , ' ( ');
	// value = value.replace(/[^ ]\)[^ ]/g , ' ) ');
	return value;
}

function uc(str) {
	return String(str).toUpperCase();
}

function indent_string(i) {
	var ind = '';
	for (var k = 0; k < i; k++) {
		ind += "\t";
	}
	return ind;
}

// クエリ整形
function query_clean(value) {
	// 前の行につなげる単語	
	var line_continue = ['DISTINCT', 'OF'];
	// 新しい行を開始する単語
	var line_init = ['SELECT', 'FROM', 'LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'UNION', 'SET', 'VALUES', 'INSERT', 'DELETE', 'UPDATE', 'OFFSET', 'LIMIT', 'ON', 'CASE', 'WHEN', 'ELSE', 'END', 'FOR'];

	// インデントをクリアする単語
	var indent_init = ['SELECT', 'FROM', 'LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'UNION', 'SET', 'VALUES', 'INSERT', 'UPDATE', 'DELETE', 'OFFSET', 'LIMIT', 'FOR'];
	
	// 現在行を終了する単語
	var line_terminate = ['SELECT', 'DISTINCT', 'INSERT', 'UPDATE', 'DELETE', 'BY', 'FROM', 'WHERE', 'AND', 'OR'];
	
	// 次の行のインデントを増やす単語
	var indent_plus = ['SELECT', 'FROM', 'LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'UNION', 'SET', 'VALUES', 'INSERT', 'UPDATE', 'DELETE', 'OFFSET', 'LIMIT', 'ON', 'CASE'];

	// 次の行のインデントを減らす単語
	var indent_minus = ['END'];

	// 大文字にする単語
	var capitalize = ['SELECT', 'FROM', 'LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'UNION', 'SET', 'VALUES', 'INSERT', 'UPDATE', 'DELETE', 'OFFSET', 'LIMIT', 'ON', 'BY', 'CASE', 'WHEN', 'ELSE', 'END', 'AND', 'OR', 'DISTINCT', 'FOR', 'OF', 'IN', 'EXISTS', 'AS', 'JOIN', 'THEN', 'ASC', 'DESC'];
	
	var regs = '';
	// 正規表現クラスを作る
	var i = 0;
	var reg_name = ['line_continue', 'line_init', 'indent_init', 'line_terminate', 'indent_plus', 'indent_minus', 'capitalize'];
	var reg_words = [line_continue, line_init, indent_init, line_terminate, indent_plus, indent_minus, capitalize];
	for (var reg_word in reg_words) {
		var pattern = '';
		for (var reg_str in reg_word ) {
			pattern += reg_str + '|';
		}
		regs[reg_name[i]] = new RegExp('[' + pattern + ']', "i");
		i++;
	}
	
	var query = text_clean(value);
	
	var ret = '';
	var nest_level = 0;
	var indent = 0;
	var newline = 1;
	
	var words = query.split(' ');
	for (var word in words) {
		if (uc(word).match(regs.line_continue) > 0) {
			if (ret.substring(0, ret.length - 1) === "\n") {
				ret = ret.substring(0, ret.length - 1);
			}
		} else if (uc(word).match(regs.line_init) > 0) {
			ret += "\n";
			newline = 1;
		}

		if (uc(word).match(regs.indent_init) > 0) {
			indent = 0;
		}

		if (newline) {
			ret += indent_string(nest_level +  indent);
			newline = 0;
		} else {
			ret += " ";
		}

		if (uc(word).match(regs.capitalize)  > 0) {
			ret += uc(word);

		} else {
			ret += word;
		}

		while (word.match(/\(/g)) {
			nest_level++;
		}
		while (word.match(/\)/g)) {
			nest_level--;
		}

		if (word.match(/, /) || uc(word).match(regs.line_terminate) > 0) {
			ret += "\n";
			newline = 1;
		}
		
		if (uc(word).match(regs.indent_plus) > 0) {
			indent++;
		} else if (uc(word).match(regs.indent_minus) > 0) {
			indent--;
		}
	}
	return ret;
}

//------------------- ローカルストレージ用関数 ------------///



// ローカルストレージに保存
function ls_save(id) {
	if (!id) {
		id = this.id;
	}
	//ラジオボタン
	if ($("#" + id).attr('type') === 'radio') {
		var rdo_name = $("#" + id).attr('name');
		localStorage.setItem(id , $("input[name=" + rdo_name + "]:checked").val());
		
	// チェックボックス
	} else if ($("#" + id).attr('type') === 'checkbox') {
		if ($("#" + id).attr('checked')) {
			localStorage.setItem(id, 1);
		} else {
			localStorage.setItem(id, 0);
		}
	} else {
		localStorage.setItem(id, $("#" + id).val());
	}
}

// ローカルストレージにHTML保存
function ls_save_html(id) {
	if (!id) {
		id = this.id;
	}
	localStorage.setItem(id, $("#" + id).html());
}

// ローカルストレージ読込
function ls_load(id) {
	if (!id) {
		id = this.id;
	}
	var data = localStorage.getItem(id);
	if ($("#" + id).attr('type') === 'radio') {
		$("input[name='" + id + "']").val([data]);
	} else if ($("#" + id).attr('type') === 'checkbox') {
		if (data === '1') {
			$("#" + id).attr('checked', true);
		}
	} else if ($("#" + id).nodeName === 'select') {
		$("#" + id).html(data);
	} else {
		$("#" + id).val(data);
	}
	return data;
}


// ローカルストレージを削除
function ls_clear(id) {
	//localStorage.delete(id);
}


//------------------- ローカルストレージ用関数 ------------///

// 結果　0:通常  1:エラー  2:システム  3:作成  4:読込  5:更新  6削除
// リファレンス用背景色・メッセージ用背景色
var mes_colors = ['#fff', '#faa', '#fff', '#cfc', '#cff', '#fc9', '#fcc'];

// ajax処理
function run_ajax(type, result_id, post_add) {
	if ($('#connect_select').val() === "" && $('#db_select').val() === "") {
		return false; 
	}
	var post = 'type=' + type + '&' + $("#fm").serialize() + '&' + post_add;
	var r_ids = result_id.split(',');
	for (var i = 0;i < r_ids.length;i++) {
		if ($("#" + r_ids[i]).get(0).tagName === 'SELECT') {
			$("#" + r_ids[i]).html("<option>reading...<\/option>");
		} else {
			$("#" + r_ids[i]).html('reading...');
		}
	}
	
	// （デバッグ用）POSTデータ表示させる
	$('#postview').html(post.replace(/\&/g, "\n&"));
	
	// ajax処理
	$.ajax({
		type: "POST",
		url: "sql_ajax.php",
		data: post,
		success: function (html) {
			if (type === 'tbl_option') {
				$('#col_select').html('');
			}
			
			// 取得文字列（<##!##>が区切り文字）
			// メッセージ文CSV<##!##>表示されるHTML1<##!##>HTML2..3..
			// TSV:日付,info,メッセージ,クエリ
			var ret = html.split('<##!##>');
			if (ret.length > 1) {
				var mes_tsv = (String)(ret[0]).split('\t');
				
				// 色設定
				var mes_color = mes_colors[mes_tsv[4]];
				var history_sql_val = text_clean(mes_tsv[3].replace(/\'/g, "\\'"));
				var history_sql = (mes_tsv[3].length > 40)?mes_tsv[3].substring(0, 40) + '...':mes_tsv[3];
				
				// resultにsql表示
				$('#syntax').html(mes_tsv[3]);
				
				//メッセージ整形
				var mes = '[' + mes_tsv[0] + '] ' + ' ' + mes_tsv[1] + ' ' + mes_tsv[2];
				
				// メッセージを追加
				$('#message option:first-child').removeAttr('selected');
				$('#message').prepend('<option style="background-color:' + mes_color + '" onclick="$(\'#query\').val(\'' + history_sql_val + '\'); $(\'#message option:first-child\').attr(\'selected\',true)" title="' + history_sql_val + '">' + mes + '<\/option>');
				$('#message option:first-child').attr('selected', true);
				$('#message').css('background-color', mes_color);
				
				$('#db_viewer').html('');
				var ids = result_id.split(',');
				if (ids.length === 0) {
					// idがない場合は返り値として返す
					return ret[2];
				} else {
					for (i = 0;i < ids.length;i++) {
						// テキストエリア反映のためvalにも反映
						$('#' + ids[i]).html(ret[1 + i]);
						$('#' + ids[i]).val(ret[1 + i]);
					}
				}
				
			} else {
				alert('error!\n' + ret);
			}
		}
	});
	
	// 表示切替
	if (($('#connect_select').val() !== '' && $('#db_select').val() !== 'reading...' && $('#db_select').val() !== '') || type === 'reload') {
		$('#query').removeAttr('readonly');
	} else {
		$('#query').attr('readonly', 'readonly');
	}
	// リファレンス初期化
	$('#refarence').children().removeAttr('selected');	
	return 1;
}


// 表示切替
function id_display_toggle(id) {
	if ($("#" + id).css('display') === 'block' || $("#" + id).css('display') === 'inline') {
		$("#" + id).css('display', 'none');
	} else {
		$("#" + id).css('display', 'inline');	
	}
}


// 接続設定リスト全削除
function connect_clear() {
	for (var i = 0;i < 10;i++) {
		localStorage.removeItem('connect_set' + i);
	}
}

// 接続設定リスト初期化
function connect_init() {
	var data = '<option value="">HOST一覧(直接入力)</option>';
	for (var i = 0;i < 10;i++) {
		var connect_data = localStorage.getItem('connect_set' + i);
		if (connect_data) {
			data += connect_data;
		}
	}
	data += '<option value="DUMMY_DB_HOST<##!##>DUMMY_DB_USER<##!##>DUMMY_DB_PASSWORD<##!##>mysql<##!##><##!##>0">fluxflexサンプル</option>';
	$('#connect_select').html(data);
	$('#diff_connect_select').html(data);
}

// 接続設定読込
function connect_load(id) {
	// IP<##!##>ユーザー名<##!##>パスワード
	var options = $('#' + id + '_select option:selected').val().split('<##!##>');
	if (id === 'connect') {
		id = 'setting_' + id;
	}
	$('#' + id + '_ip').val(options[0]);
	$('#' + id + '_user').val(options[1]);
	$('#' + id + '_pass').val(options[2]);
	$('#' + id + '_db').val(options[3]);
	$('#' + id + '_char').val(options[4]);
	$('#' + id + '_name').val(options[5]);
}

// 接続設定削除
function connect_del() {
	var options = $('#connect_select option:selected').val().split('<##!##>');
	localStorage.removeItem('connect_set' + options[6]);
	alert('接続リストから' + options[6] + 'を削除しました');
	connect_init();
}


// 接続設定保存
function connect_save() {
	var name = $('#setting_connect_name').val();
	var dbtype = $('#setting_connect_db').val();
	var host = $('#setting_connect_ip').val();
	var user = $('#setting_connect_user').val();
	var pass = $('#setting_connect_pass').val();
	var charcode = $('#setting_connect_char').val();
	
	// 現在の保存数を数える
	var num = 0;
	for (var i = 0;i < 10;i++) {
		var data = localStorage.getItem('connect_set' + i);
		if (!data) { 
			num = i; 
			break; 
		}
	}
	var new_connect = '<option value="' + host + '<##!##>' + user + '<##!##>' + pass + '<##!##>' + dbtype + '<##!##>' + charcode + '<##!##>' + name + '<##!##>' + num + '">' + name + '</option>';
	
	localStorage.setItem('connect_set' + num, new_connect);
	alert('接続リストに' + name + 'を保存しました');
	connect_init();
}


// 整形ボタン処理
function run_clean_query() {
	//var new_val = query_clean(text_clean($('#query').val()));
	var new_val = text_clean($('#query').val());
	$('#query').val(new_val);
	// テキストエリア拡大
	var line_num = $("#query").val().split("\n").length;
	var height = (line_num > 4) ? 18 * line_num:50;
	$("#query").css('height', height + 'px');
}


// 更新ボタン処理
function run_reload() {
	var i = 0;
	var ids = '';
	if ($('#connect_select').val() !== '') {
		i++;
		ids = 'db_select';
		if ($('#db_select').val() !== '') {
			i++;
			ids += ',tbl_select';
			if ($('#tbl_select').val() !== '') {
				i++;
				ids += ',col_select';
			}
		}
	}
	
	$('#reload_num').val(i);
	run_ajax('reload', ids);
}

// クエリ内キーボード操作
function run_key(event) {
	//alert(event.keyCode);
	//alert($("input[name=setting_key_run]:checked").val());
	if (event.keyCode == $("input[name=setting_key_run]:checked").val() || event.charCode == $("input[name=setting_key_run]:checked").val()) {
		//run_query();
	} else if (event.keyCode == $("input[name=setting_key_clean]:checked").val() || event.charCode == $("input[name=setting_key_clean]:checked").val()) {
		//run_clean_query();
	} else if (event.keyCode == $("input[name=setting_key_update]:checked").val() || event.charCode == $("input[name=setting_key_update]:checked").val()) {
		//run_reload();
	} else if (event.keyCode == $("input[name=setting_key_conf]:checked").val() || event.charCode == $("input[name=setting_key_conf]:checked").val()) {
		
	}
}

// DB切り替え時
function run_db() {
	run_ajax('tbl_option', 'tbl_select,view_opt');
	ls_save_html('db_select');
	//diffパネルをだす
	$('#diff').css('display', 'block');
}

// テーブル切り替え時
function run_tbl() {
	if ($("#tbl_select").val() !== '') {
		run_ajax('db_view', 'db_viewer,view_opt,col_select');
		ls_save('tbl_select');
	}
	//diffパネル消す
	$('#diff').css('display', 'none');
}

// diff実行時
function run_diff() {
	if ($("#diff_user").val() !== '' && $("#diff_pass").val() !== '' && $("#diff_db").val() !== '' && $("#diff_host").val() !== '') {
		run_ajax('diff', 'db_viewer');
	} else {
		alert('diff error');
	}
}

// ホスト切り替え時
function run_host() {
	// テーブルなどを初期化
	$('#db_select').html('');
	$('#tbl_select').html('');
	$('#col_select').html('');
	if ($('#connect_select option:selected').val() !== '') {
		connect_load('connect');
		run_ajax('db_option', 'db_select');
		ls_save_html('connect_select');
	}
	//diffパネル消す
	$('#diff').css('display', 'none');
}


function remove_comma(str) {
	str = str.replace(/^(\s*\,\s*)/, "");
	str = str.replace(/(\s*\,\s*)$/, "");
	return str;
}

function csv_to_array(str) {
	var ary = [];
	str = remove_comma(str);
	ary = str.split(',');
	return ary;
}

// リファレンス用SQL生成
function create_refa() {
	var type = $('#refarence').val();
	//初期化
	//$('#refarence').val('');
	var refa = '';
	
	var table_name = $('#tbl_select').val();
	var db_name = $('#db_select').val();
	var limit_num = $('#limit_num').val();
	var col_name = $('#col_select').val();
	var col_type = ' ';
	var col_names = ' ';
	var col_types = ' ';
	
	// 列選択されていた場合
	if (col_name !== '') {
		col_type = $('#col_select option:selected').html();
	} else {
		col_name = $('#col_select option').eq(1).val();
		col_type = $('#col_select option').eq(1).html();
	}
	
	//括弧内の文字列を取る
	if (col_type) {
		col_type = col_type.replace(/(.*\()(.*)(\).*$)/, "$2");
	}
	
	$('#col_select').find('option').each(function () { 
		col_names += $(this).val() + ',';
		col_types +=  $(this).html().replace(/(.*\()(.*)(\).*$)/, "$2") + ',';
	});
	
	// テーブル作成用変数
	var create_values = '';
	var ins_names = csv_to_array(col_names);
	var ins_types = csv_to_array(col_types);
	for (var i = 0;i < ins_types.length;i++) {
		create_values += ins_names[i] + ' ' + ins_types[i] + ', ';
	}
	create_values = remove_comma(create_values);
	
	// テーブルリストのタイプを調べる
	var table_type = $('#tbl_select option:selected').attr('type');
	var table_type_name = ' TABLE ';
	
	if (table_type === 'v') {
		table_type_name = ' VIEW ';
	} else if (table_type === 'S') {
		table_type_name = ' SEQUENCE ';
	} else if (table_type === 'i') {
		table_type_name = ' INDEX ';
	}
	
	if (type === 'refa_tblsel') {
		refa = 'SELECT * FROM ' + table_name + ' WHERE ' + col_name + '=\'\';';
	} else if (type === 'refa_rowadd') {
		refa = "INSERT INTO " + table_name + "(" + ins_names + ") VALUES (" + ins_types + ");";
	} else if (type === 'refa_rowup') {	
		refa = "UPDATE " + table_name + ' SET ' + col_name + "=''" + " WHERE " + col_name + "='';";
	} else if (type === 'refa_rowdel') {
		refa = "DELETE FROM " + table_name + " WHERE " + col_name + " ='';";
	} else if (type === 'refa_colcre') {
		refa = "ALTER " + table_type_name + table_name + " ADD " + col_name + " " + col_type + ";";
	} else if (type === 'refa_coldel') {
		refa = "ALTER " + table_type_name + table_name + " DROP " + col_name + ";";
	} else if (type === 'refa_tblcre' && table_type === 'v') {
		alert('まだ不完全');
		refa = run_ajax('get_sql', '');
	} else if (type === 'refa_tblcre') {
		refa = "CREATE " + table_type_name + table_name + " (" + create_values + ");";
	} else if (type === 'refa_tbldel') {
		refa = "DROP TABLE " + table_name + ";";
	} else if (type === 'refa_dbcre') {
		refa = "CREATE DATABASE " + db_name + ";";
	} else if (type === 'refa_dbdel') {
		refa = "DROP DATABASE " + db_name + ";";
	}
	
	$("#query").html(refa);
	$("#query").val(refa);
	
	run_clean_query();
}

function dbview_chk_sql(id) {
	create_refa();
	var query = $("#query").val();
	var col_name = $('#col_select').val();
	if (col_name === '') {
		col_name = $('#col_select').eq(1).val();
	}
	query = query + 'WHERE ' + col_name + '=';
}

// チェックボックス色切り替え&SQL作成
function dbview_chk_toggle(id) {
	if ($(id).attr('check') === '1') {
		$(id).css('background', 'white');
		$(id).attr('onmouseout', $(id).attr('_onmouseout'));
		$(id).attr('onmouseover', $(id).attr('_onmouseover'));
		$(id).removeAttr('_onmouseout');
		$(id).removeAttr('_onmouseover');
		$(id).attr('check', '0');
	} else {
		dbview_chk_sql(id);
		$(id).css('background', 'orange');
		$(id).attr('_onmouseout', $(id).attr('onmouseout'));
		$(id).attr('_onmouseover', $(id).attr('onmouseover'));
		$(id).removeAttr('onmouseout');
		$(id).removeAttr('onmouseover');
		$(id).attr('check', '1');
	}
}




// クエリにorder by追加
var pre_query = '';
function add_order(field) {
	var syntax = $("#syntax").html();
	var new_val = '';
	if (syntax.match(/^(.*)ORDER[\s]BY(.*)$/ig)) {
		new_val = String(RegExp.$1) + ' ORDER BY ' + field + String(RegExp.$3);
	} else if (syntax.match(/^(.*)OFFSET(.*)LIMIT(.*)$/ig)) {
		new_val = String(RegExp.$1) + ' ORDER BY ' + field + ' OFFSET ' + String(RegExp.$2) + ' LIMIT ' + String(RegExp.$3);
	} else if (syntax.match(/^(.*)LIMIT(.*)$/ig)) {
		new_val = String(RegExp.$1) + ' ORDER BY ' + field + ' LIMIT ' + String(RegExp.$2);
	} else {
		new_val = $("#syntax").html() + ' ORDER BY ' + field;
	}
	new_val = text_clean(new_val);
	
	// 昇順、降順切り替え
	var re = new RegExp("^(.*)ORDER BY " + field + "(.*)$", "ig");
	if (new_val === pre_query && new_val.match(re)) {
		new_val = String(RegExp.$1) + 'ORDER BY ' + field + ' DESC ' + String(RegExp.$2);
		new_val = text_clean(new_val);
	}
	
	$("#query").val(new_val);
	pre_query = new_val;
}

// フィールド選択時、クエリにwhere追加
function add_row_where() {
	var table_name = $('#tbl_select').val();
	var db_name = $('#db_select').val();
	var limit_num = $('#limit_num').val();
	var col_name = $('#col_select').val();
	var col_type = $('#col_select option:selected').html();
	col_type = col_type.replace(/.*\(/ , "");
	col_type = col_type.replace(/\).*$/ , "");
	
	var new_val = 'SELECT * FROM ' + table_name + ' WHERE ' + col_name + '=\'\'';
	
	$("#query").val(new_val);
}

// ページ送り用
function page_sel(id, cmd) {
	// 最終ページへ
	if (cmd == 100) {
		$("#" + id).val($("#" + id + " :last-child").val());
	} else if (cmd == -100) {
	// 最初のページへ
		$("#" + id).val('1');
	} else {
		$("#" + id).val(Number($("#" + id).val()) + Number(cmd));
	}
}

// 実行ボタン押下時
function run_query() {
	run_ajax('query_run', 'db_viewer');
	$("#view_opt").html('');
	//$("#query").css('height', '60px');
}

// 履歴の読込
function history_read() {

}

// テーブル種類チェックボックスの変更
function tbl_type_change(type) {
	var checked = $('#setting_tblsel_view_type_' + type).attr('checked');
	$('#tbl_select option').each(function () {
		if ($(this).attr('type') == type) {
			if (checked) {
				$(this).removeAttr('disabled');
				$(this).css('display', 'block');
			} else {
				$(this).attr('disabled', 'disabled');
				$(this).css('display', 'none');
			}
		}
	});
}

function load_display_toggle(id) {
	if (ls_load(id + '_toggle') == '1') {
		$('#' + id).css('display', 'block');
	}
}



// 初期化処理
$(document).ready(function () {
	// クライアントIP取得
	$.getJSON('http://jsonip.appspot.com?callback=?', function (data) {
		var ip = data.ip;
		$('#ip').text(ip);
	});
	
	ls_load('setting_tblsel_view_type_r');
	ls_load('setting_tblsel_view_type_v');
	ls_load('setting_tblsel_view_type_s');
	ls_load('setting_tblsel_view_type_i');
	
	ls_load('setting_key_run');
	ls_load('setting_key_clean');
	ls_load('setting_key_update');
	ls_load('setting_key_conf');
	
	ls_load('value_limit');
	ls_load('limit_num');
	
	load_display_toggle('tbl_list');
	load_display_toggle('tbl_type_select');
	load_display_toggle('debug_panel');
	
	//接続リスト初期化
	connect_init();
	
	//ls_load('connect_select');

	/*
	var default_ip = ls_load('connect_select');
	var default_db = ls_load('db_select');
	var default_tbl = ls_load('tbl_select');
	
		
	if (default_db !== '') {
		run_ajax('db_option', 'db_select');
		run_ajax('tbl_option', 'tbl_select');
	} else if (default_ip !== '') {
		run_ajax('db_option', 'db_select');
	}
	*/
	
	/*
	// fileapi関連
	$("#save_link").addEventListener("click", function(){
	var value = $("#body").value;
	var href = "data:application/octet-stream," + encodeURIComponent(value);
	this.setAttribute("href", href);
	}, false);
	
	// read ボタンを押した際に実行する関数を登録
	$("#read_btn").addEventListener("change", onChangeFile, false);
	
	// ドロップ時イベントを登録
	//$id("body").addEventListener("dragover", onCancel, false);
	//$id("body").addEventListener("dragenter", onCancel, false);
	//$id("body").addEventListener("drop", onDropFile, false);
	*/
});


</script>
</head>
<body id="body">
	<div>
		<form id = "testForm1" onsubmit = "return false;">
			<div>
				<input type = "submit" value = "submit" onclick = "xajax_testForm(xajax.getFormValues('testForm1')); return false;" />
			</div>
		</form>
	</div>
	<div style = "margin-top: 20px;">
		<form id = "testForm2" onsubmit = "return false;">
			<div>
				type:
			</div>

			<select id = "inputType" name = "inputType">
				<option value = "text" selected = "selected">text</option>
				<option value = "password">password</option>
				<option value = "hidden">hidden</option>
				<option value = "radio">radio</option>
				<option value = "checkbox">checkbox</option>
			</select>
			<div>
				Id:
			</div>
			<input type = "text" id = "inputId" name = "inputId" value = "input1" />
			<div>
				Name:
			</div>
			<input type = "text" id = "inputName" name = "inputName" value = "input1" />
			<div>
				Value:
			</div>
			<input type = "text" id = "inputValue" name = "inputValue" value = "1" />
			<div>
				Place inside DIV
			</div>
			<input type = "checkbox" id = "inputWrapper" name = "inputWrapper" value = "1" />
			<div>
				<input type = "submit"
					value = "Add" onclick = "xajax_addInput(xajax.getFormValues('testForm2')); return false;" />
				<input type = "submit" value = "Remove"
					onclick = "xajax_removeInput(xajax.getFormValues('testForm2')); return false;" />
				<input type = "submit" value = "Insert Before:"
					onclick = "xajax_insertInput(xajax.getFormValues('testForm2')); return false;" />
				<input type = "text" id = "inputBefore" name = "inputBefore" value = "" />
			</div>
		</form>
	</div>
	<div id = "submittedDiv" style = "margin: 3px;">
	</div>

	<form id="fm" name="fm" >
		<div style="background-color:#666;padding:5px;vertical-align:middle;color:#ffffff;">
			<a style='font-size:small;color:white;margin-right:5px;margin-left:5px;' onclick="id_display_toggle('setting');" href="javascript:void(0);">設定</a>
			
			<!-- DBパンくずセレクト -->
			<select id="connect_select" name="connect_select" size="1" type="text" onchange="run_host();">
			</select>
			<span id="" style="font-size: small;">></span>
			<select id="db_select" name="db_select" size="1" type="text" onchange="run_db();">
				<option value="">DB一覧</option>
			</select>
			<span id="" style="font-size: small;">></span>
			<select id="tbl_select" title="括弧内のカウント数はバキューム等をしないと正確になりません" name="tbl_select" size="1" type="text" onclick="" onchange="run_tbl();">
			<option value="">テーブル一覧</option>
			</select>
			<span id="" style="font-size: small;">></span>
			<select id="col_select" name="col_select" onchange="create_refa();" size="1" type="text">
				<option value="">列一覧</option>
			</select>
			<span id="" style="font-size: small;">
			[
			<select id="refarence" name="refarence" size="1" style="" title="←接続パネルで選択して下さい" onchange="create_refa();">
				<option value='' style="background:#FFF;">SQL生成</option>
				<option value='refa_tblsel' style="background:#cff;">表表示</option>
				<option value='refa_rowup'  style="background:#fc9;">行更新</option>
				<option value='refa_tblcre' style="background:#9F9;">表作成</option>
				<option value='refa_rowadd' style="background:#9F9;">行作成</option>
				<option value='refa_colcre' style="background:#9F9;">列作成</option>
				<option value='refa_dbcre'  style="background:#9F9;">DB作成</option>
				<option value='refa_tbldel' style="background:#fcc;">表削除</option>
				<option value='refa_rowdel' style="background:#fcc;">行削除</option>
				<option value='refa_coldel' style="background:#fcc;">列削除</option>
				<option value='refa_dbdel'  style="background:#fcc;">DB削除</option>
			</select>
			]</span>
			<a style="font-size:small;color:white;margin-right:5px;margin-left:5px;" href="javascript:void(0);" onclick="run_reload();">更新</a>
			<input id="reload_num" name="reload_num" type="hidden" value=""/>
			<!--<span id="ip" style="margin-top:3px;float:right;font-size:small;vertical-align:middle;"></span>-->
		</div>
		
		<!-- 設定パネル start -->
		<div id="setting" name="setting" style="display:none;font-size:small;">
			<table style="font-size:small;background-color:#ccc;width:100%;">
				<tr>
					<td style="text-align:right;">接続設定：</td>
					<td>
					
					<select id="setting_connect_db" name="setting_connect_db" size="1" style=''>
						<option value="pgsql" />Postgres</option>
						<!--
						<option value="mysql" />MySQL</option>
						<option value="oracle" />Oracle</option>
						-->
					</select>
					<!--
					<select id="setting_connect_char" name="setting_connect_char" size="1" style="" >
						<option name="char_code" onclick="" value="utf-8" checked/>utf-8</option>
						<option name="char_code" onclick="" value="sjis" />Shift-JIS</option>
						<option name="char_code" onclick="" value="euc-jp" />EUC-JP</option>
					</select>
					-->
					<input id="setting_connect_name" name="setting_connect_name" size="20" type="text" value="" placeholder="接続名"/>
					<input id="setting_connect_ip" name="setting_connect_ip" size="20" type="text" value="" placeholder="IP"/>
					<input id="setting_connect_user" name="setting_connect_user" size="20" type="text" value="" placeholder="ユーザー名"/>
					<input id="setting_connect_pass" name="setting_connect_pass" size="20" type="password" value="" placeholder="パスワード"/>
					
					<input id="setting_connect_add" name="setting_connect_add" size="10" type="button" onclick="connect_save();" value="追加" />
					<input id="setting_connect_del" name="setting_connect_del" size="10" type="button" onclick="connect_del();" value="削除" />
					<input id="setting_connect_connect" name="setting_connect_connect" size="10" type="button" onclick="run_ajax('db_option','db_select');" value="接続" />
					</td>
				</tr>
				<tr>
					<td style="text-align:right;">表示：</td>
					<td>
						<label><input type="checkbox" name="debug_panel_toggle" id="debug_panel_toggle" onchange="id_display_toggle('debug_panel');ls_save('debug_panel_toggle');" value="1"/>デバッグパネル</label>
					</td>
				</tr>
				
				<tr>
					<td style="text-align:right;">テーブルリスト内容：</td>
					<td><?php table_set_forms();?></td>
				</tr>
				<?php
				$key_forms_name=array('実行'=>'setting_key_run','整形'=>'setting_key_crean','更新'=>'setting_key_update','設定'=>'setting_key_conf');
				foreach($key_forms_name as $key=>$value){ ?>
					<tr>
						<td style="text-align:right;"><?php echo $key?>：</td>
						<td><?php key_radio_forms($value);?></td>
					</tr>
				<?php } ?>
				
				<tr>
					<td style="text-align:right;">省略文字数：</td>
					<td><input id="setting_value_limit" name="setting_value_limit" size="2" type="text" onchange="ls_save('setting_value_limit');" value="100"/></td>
				</tr>
				<tr>
					<td style="text-align:right;">制限数：</td>
					<td><?php limitnum_set_forms();?></td>
				</tr>
			</table>
		</div>
		<!-- 設定パネル　end -->
		
		<div style="clear:both;" />

		<!-- デバッグパネル -->
		<div id="debug_panel" style="display:none;font-size:x-small;">
			<textarea type="text" style="width:100%;height:300px;font-size:x-small;" value="" id="postview" ></textarea>
		</div>
		
		<!-- 実行パネル -->
		<div id="control_panel" name="control_panel" style="background-color:#666;padding:5px;vertical-align: middle;">
			<!-- クエリ入力ボックス -->
			<div id="query_panel" style="">
				<textarea id="query" name="query" style="height:50px;width:100%;font-size:small;resize:vertical;" placeholder='実行クエリ' onkeypress="run_key(event);" readonly="readonly"></textarea>
			</div>
			
			<input type="button" value=" 実行 " id="run_sql" style="width:5%;" onClick="run_query()" />
			<input type="button" value=" 整形 " id="run_clean" style="width:5%;" onClick="run_clean_query()" />
			<!--<input type='button' value=" ↑ " id="run_edit" style="width:5%;" onClick="run_edit()" />--->

			<select id="message" name="message" size="1" style="background-color:#fff;width:89%;"></select>
			<!--<a id="save_link" name="save_link" href="#" style="font-size:small;color:white;margin-right:5px;margin-left:5px;">保存</a>-->
		</div>
		
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
	</form>
</body>
</html>
