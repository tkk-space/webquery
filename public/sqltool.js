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
	
	// タイトル更新
	$('#title').html('WebQuery [' + location.hostname + ']');
	
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


