<?php

require 'Slim/Slim.php';

$app = new Slim();

//GET route
function main(){
	/*
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
				*/	
		$template = <<<EOT
<!DOCTYPE html>
	<html>
		<head>
			<meta charset="utf-8"/>
			<title>Slim Micro PHP 5 Framework</title>
			<style>
				html,body,div,span,object,iframe,
				h1,h2,h3,h4,h5,h6,p,blockquote,pre,
				abbr,address,cite,code,
				del,dfn,em,img,ins,kbd,q,samp,
				small,strong,sub,sup,var,
				b,i,
				dl,dt,dd,ol,ul,li,
				fieldset,form,label,legend,
				table,caption,tbody,tfoot,thead,tr,th,td,
				article,aside,canvas,details,figcaption,figure,
				footer,header,hgroup,menu,nav,section,summary,
				time,mark,audio,video{margin:0;padding:0;border:0;outline:0;font-size:100%;vertical-align:baseline;background:transparent;}
				body{line-height:1;}
				article,aside,details,figcaption,figure,
				footer,header,hgroup,menu,nav,section{display:block;}
				nav ul{list-style:none;}
				blockquote,q{quotes:none;}
				blockquote:before,blockquote:after,
				q:before,q:after{content:'';content:none;}
				a{margin:0;padding:0;font-size:100%;vertical-align:baseline;background:transparent;}
				ins{background-color:#ff9;color:#000;text-decoration:none;}
				mark{background-color:#ff9;color:#000;font-style:italic;font-weight:bold;}
				del{text-decoration:line-through;}
				abbr[title],dfn[title]{border-bottom:1px dotted;cursor:help;}
				table{border-collapse:collapse;border-spacing:0;}
				hr{display:block;height:1px;border:0;border-top:1px solid #cccccc;margin:1em 0;padding:0;}
				input,select{vertical-align:middle;}
				html{ background: #EDEDED; height: 100%; }
				body{background:#FFF;margin:0 auto;min-height:100%;padding:0 30px;color:#666;font:14px/23px Arial,Verdana,sans-serif;}
				h1,h2,h3,p,ul,ol,form,section{margin:0 0 20px 0;}
				h1{color:#333;font-size:20px;}
				h2,h3{color:#333;font-size:14px;}
				h3{margin:0;font-size:12px;font-weight:bold;}
				ul,ol{list-style-position:inside;color:#999;}
				ul{list-style-type:square;}
				code,kbd{background:#EEE;border:1px solid #DDD;border:1px solid #DDD;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;padding:0 4px;color:#666;font-size:12px;}
				pre{background:#EEE;border:1px solid #DDD;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;padding:5px 10px;color:#666;font-size:12px;}
				pre code{background:transparent;border:none;padding:0;}
				a{color:#70a23e;}
				header{padding: 30px 0;text-align:center;}
			</style>
		</head>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style type="text/css">
<!--
	.tbl_list {
	overflow: scroll;   /* スクロール表示 */
	width: 150px;
	height: 80%;
	}
-->
</style>
<script type="text/javascript" src="jquery-1.6.1.min.js"></script>
<script type="text/javascript" src="sqltool.js"></script>

<title id="title">WebQuery</title>
</head>
<body id="body">
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
EOT;
	echo $template;
}

$app->get('/', 'main');

/*
//POST route
$app->post('/post', 'post');
function post() {
	echo 'This is a POST route';
}

//PUT route
$app->put('/put', 'put');
function put() {
	echo 'This is a PUT route';
}

//DELETE route
$app->delete('/delete', 'delete');

function delete() {
	echo 'This is a DELETE route';
}
*/

$app->run();