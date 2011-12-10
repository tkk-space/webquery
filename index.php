﻿<?php
function key_radio_forms($id_name){
	for($i=0;$i<7;$i++){
		$key_code=$i+117;
		$key_name='F'.(6+$i);
		echo '<label><input type="radio" id="'.$id_name.'" name="'.$id_name.'" value="'.$key_code.'"  onchange="ls_save(\''.$id_name.'\');"  />'.$key_name.'</label>';
	}
}

?>
<html>
<head>
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
			<a style='font-size:small;color:white;margin-right:5px;margin-left:5px;' onclick="id_view('setting');" href="javascript:void(0);">設定</a>
			
			<!-- DBパンくずセレクト -->
			<select id="ip_select" name="ip_select" size="1" type="text" onchange="run_host();">
			</select>
			<span id="" style="font-size: small;">></span>
			<select id="db_select" name="db_select" size="1" type="text" onchange="run_db();">
				<option value="">DB一覧</option>
			</select>
			<span id="" style="font-size: small;">></span>
			<select id="tbl_type_select" name="tbl_type_select" size="1" style="display:none;" type="text" onclick="" onchange="">
				<option value="">種別</option>
			</select>
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
				<option value='refa_tblsel' style="background:#f93;">表表示</option>
				<option value='refa_rowup'  style="background:#99f;">行更新</option>
				<option value='refa_tblcre' style="background:#9F9;">表作成</option>
				<option value='refa_rowadd' style="background:#9F9;">行作成</option>
				<option value='refa_colcre' style="background:#9F9;">列作成</option>
				<option value='refa_dbcre'  style="background:#9F9;">DB作成</option>
				<option value='refa_tbldel' style="background:pink;">表削除</option>
				<option value='refa_rowdel' style="background:pink;">行削除</option>
				<option value='refa_coldel' style="background:pink;">列削除</option>
				<option value='refa_dbdel'  style="background:pink;">DB削除</option>
			</select>
			]</span>
			<a style="font-size:small;color:white;margin-right:5px;margin-left:5px;" href="javascript:void(0);" onclick="db_reload();">更新</a>
			<!--<span id="ip" style="margin-top:3px;float:right;font-size:small;vertical-align:middle;"></span>-->
		</div>
		
		<!-- 設定パネル start -->
		<div id="setting" name="setting" style="display:none;background-color:#ccc;width:100%;font-size:small;">
			<table style="font-size:small;">
				<tr>
					<td style="text-align:right;">接続設定：</td>
					<td>
					<select id="setting_connect_db" name="setting_connect_db" size="1" style=''onchange="create_refa();">
						<option value="postgres" />Postgres</option>
						<option value="mysql" />MySQL</option>
						<option value="oracle" />Oracle</option>
					</select>
					
					<select id="setting_connect_char" name="setting_connect_char" size="1" style="" onchange="create_refa();">
						<option name="char_code" onclick="" value="utf-8" checked/>utf-8</option>
						<option name="char_code" onclick="" value="sjis" />Shift-JIS</option>
						<option name="char_code" onclick="" value="euc-jp" />EUC-JP</option>
					</select>

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
						<label><input type="checkbox" name="setting_view_tbl_list" id="setting_view_tbl_list" onchange="id_view('tbl_list');ls_save('setting_view_tbl_list');" value="1"/>テーブルリスト</label>
						<label><input type="checkbox" name="setting_view_typeselect" id="setting_view_typeselect" onchange="id_view('tbl_type_select');ls_save('setting_view_typeselect');" value="1"/>テーブル種別セレクトボックス</label>
						<label><input type="checkbox" name="setting_view_debug" id="setting_view_debug" onchange="id_view('debug_panel');ls_save('setting_view_debug');" value="1"/>デバッグパネル</label>
					</td>
				</tr>
				
				<tr>
					<td style="text-align:right;">テーブルリスト内容：</td>
					<td>
						<label><input type="checkbox" name="setting_tblsel_view_type[]" id="setting_tblsel_view_type_r" onclick="ls_save('setting_tblsel_view_type_r');" value="r" />TABLE</label>
						<label><input type="checkbox" name="setting_tblsel_view_type[]" id="setting_tblsel_view_type_v" onclick="ls_save('setting_tblsel_view_type_v');" value="v" />VIEW</label>
						<label><input type="checkbox" name="setting_tblsel_view_type[]" id="setting_tblsel_view_type_s" onclick="ls_save('setting_tblsel_view_type_s');" value="s" />SEQUENCE</label>
						<label><input type="checkbox" name="setting_tblsel_view_type[]" id="setting_tblsel_view_type_i" onclick="ls_save('setting_tblsel_view_type_i');" value="i" />INDEX</label>
						<label><input type="checkbox" name="setting_tblsel_view_type[]" id="setting_tblsel_view_type_sp" onclick="ls_save('setting_tblsel_view_type_sp');" value="S" />SPECIAL</label>
					</td>
				</tr>
				
				<tr>
					<td style="text-align:right;">接続ロック：</td>
					<td>
						<label><input type="radio" name="lock" onclick="set_lock();" value="none" checked/>なし</label>
						<label><input type="radio" name="lock" onclick="set_lock();" value="host" />host</label>
						<label><input type="radio" name="lock" onclick="set_lock();" value="db" />DB</label>
					</td>
				</tr>
				<tr>
					<td style="text-align:right;">実行：</td>
					<td><?php key_radio_forms('setting_key_run');?></td>
				</tr>
				<tr>
					<td style="text-align:right;">整形：</td>
					<td><?php key_radio_forms('setting_key_crean');?></td>
				</tr>
				<tr>
					<td style="text-align:right;">更新：</td>
					<td><?php key_radio_forms('setting_key_update');?></td>
				</tr>
				<tr>
					<td style="text-align:right;">↑：</td>
					<td><?php key_radio_forms('setting_key_up');?></td>
				</tr>
				<tr>
					<td style="text-align:right;">省略文字数：</td>
					<td><input id="setting_value_limit" name="setting_value_limit" size="2" type="text" onchange="ls_save('setting_value_limit');" value="100"/></td>
				</tr>
				<tr>
					<td style="text-align:right;">制限数：</td>
					<td>
						<label><input type="radio" id="limit_num" name="limit_num" value="30"  onchange="ls_save('limit_num');" checked />30</label>
						<label><input type="radio" id="limit_num" name="limit_num" value="50"  onchange="ls_save('limit_num');" />50</label>
						<label><input type="radio" id="limit_num" name="limit_num" value="100"  onchange="ls_save('limit_num');" />100</label>
						<label><input type="radio" id="limit_num" name="limit_num" value="200"  onchange="ls_save('limit_num');" />200</label>
						<label><input type="radio" id="limit_num" name="limit_num" value=""  onchange="ls_save('limit_num');" />無制限</label>
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
			<!--<input type="button" value=" ↑ " id="run_edit" style="" onclick="query_edit();"/>-->
			<code class="sql" style="font-size:small;">
				<span id="syntax"></span>
			</code>
		</div>
		
		<!-- sql結果表示 -->
		<div id="sql_panel" name="sql_panel" style="float:left; width:100%;">
			<!-- ページャー部分 -->
			<div id="view_opt" name="view_opt"></div>
			
			<!-- sql内容テーブル -->
			<div id="db_viewer" style="float:left;"></div>
		</div>
	</form>
</body>
</html>