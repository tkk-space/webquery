"use strict";


// 結果　0:通常  1:エラー  2:システム  3:作成  4:読込  5:更新  6削除
// リファレンス用背景色・メッセージ用背景色
var mes_colors = ['#fff', '#f00', '#fff', '#cfc', '#cff', '#fc9', '#fcc'];

// 日時などの桁を合わせる
function conv2deg(val) {
	val = "00" + val;
	return val.substr(val.length - 2, 2);
}


// 現在の日時テキストを取得
function get_date_txt() {
	var date = new Date();
	date = (conv2deg(date.getMonth() + 1)) + "/" + conv2deg(date.getDate()) + " " + conv2deg(date.getHours()) + ":" + conv2deg(date.getMinutes());
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
	value = value.replace(/\t/g, " ");
	value = value.replace(/\r\n/g, " ");
	value = value.replace(/\n/g, " ");
	value = value.replace(/[\s]+/g, " ");
	value = value.replace(/^[\s]/g, "");
	value = value.replace(/[\s]$/g, "");
	return value;
}

// クエリ整形
function query_clean(value){
	var indent=0;
	value = value.replace(/\r\n/gi,"");
	value = value.replace(/\n/gi,"");
	value = value.replace(/\t/gi," ");
	value = value.replace(/\s+/gi," ");
	value = value.replace(/\(\s+[SELECT]/gi,"\n(\n\tSELECT");
	value = value.replace(/^[count|using]\(/gi,"\n(");
	value = value.replace(/\)\s+([^as])/gi,")\n $1");
	value = value.replace(/(\s+SELECT|^SELECT)\s+/gi,"SELECT \n\t");
	value = value.replace(/(\s+UPDATE|^UPDATE)\s+/gi,"UPDATE \n\t");
	value = value.replace(/(\s+INSERT|^INSERT)\s+/gi,"INSERT \n\t");
	value = value.replace(/(\s+DELETE|^DELETE)\s+/gi,"DELETE \n\t");
	value = value.replace(/\s+FROM\s+/gi," \nFROM \n\t");
	value = value.replace(/\s+WHERE\s+/gi," \nWHERE \n\t");
	value = value.replace(/\s+ORDER\s+BY\s+/gi," \nORDER BY \n\t");
	value = value.replace(/\s+GROUP\s+BY\s+/gi," \nGROUP BY \n\t");
	value = value.replace(/\s+LEFT\s+OUTER\s+JOIN\s+/gi," \nLEFT OUTER JOIN \n\t");
	value = value.replace(/\s+LEFT\s+JOIN\s+/gi," \nLEFT JOIN \n\t");
	value = value.replace(/\s+RIGHT\s+OUTER\s+JOIN\s+/gi," \nRIGHT OUTER JOIN \n\t");
	value = value.replace(/\s+RIGHT\s+JOIN\s+/gi," \nRIGHT JOIN \n\t");
	value = value.replace(/\s+AND\s+/gi," \nAND\t");
	value = value.replace(/\s+OR\s+/gi," \nOR\t");
	value = value.replace(/^\s*\n$^/gi,"");
	
	//value = value.replace(/\s*,\s*/g," \n\t, ");
	//value = value.replace(/\s+AS\s+/g,"\t\tAS ");
	return value;
}


// ローカルストレージに保存
function ls_save(id) {
	
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
	localStorage.setItem(id, $("#" + id).html());
}

// ローカルストレージ読込
function ls_load(id) {
	var data = localStorage.getItem(id);
	if ($("#" + id).attr('type') === 'radio') {
		$("input[name='" + id + "']").attr('checked', true);
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


// セレクトボックスにテキストデータを入れる
function readselect(id, target) {
	var textdata = $('#' + id).val();
	var sel = document.getElementById(target);
	sel.length = 0;
	var textline = String(textdata).split('\n');
	textline = unique(textline);

	for (var i = 0;i < textline.length; i++) {
		$("#" + target).append("<option id='textline" + i + "' value='" + textline[i] + "'>" + textline[i] + "<\/option>");
	}
	ls_save(id);
	//$("#textdata").val('');
}



// ajax処理
function run_ajax(type, result_id, post_add) {
	if ($('#ip_select').val() === "" && $('#db_select').val() === "") {
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
		url: "sql_ajax.cgi",
		data: post,
		success: function (html) {
			if (type === 'tbl_option') {
				$('#col_select').html('');
			}
			
			//alert(html);
			
			// 取得文字列（<##!##>が区切り文字）
			// 結果(数字)<##!##>SQL<##!##>メッセージ文<##!##>表示されるHTML1<##!##>HTML2..3..			
			var ret = html.split('<##!##>');
			
			// クエリを表示
			if(ret[1]!='-1'){ $('#syntax').html(ret[1]); }
			
			// 色設定
			var mes_color = mes_colors[ret[0]];
			
			//SQLを整形
			var history_sql_val = text_clean(ret[1].replace(/\'/g, "\\'"));
			var history_sql = (ret[1].length > 40)?ret[1].substring(0, 40) + '...':ret[1];
						
			//メッセージに現在の日時
			ret[2] = '[' + get_date_txt() + '] ' + ret[2];

			// メッセージを追加
			$('#message option:first-child').removeAttr('selected');
			$('#message').prepend('<option style="background-color:' + mes_color + '" onclick="$(\'#query\').val(\'' + history_sql_val + '\'); $(\'#message option:first-child\').attr(\'selected\',true)" title="' + history_sql_val + '">' + ret[2] + '<\/option>');
			$('#message option:first-child').attr('selected', true);
			$('#message').css('background-color', mes_color);
			
			//失敗時 色々初期化させる
			//if (ret[0] === '1') {
				//$('#db_select').html('');
				//$('#tbl_select').html('');
				//$('#col_select').html('');
				//$('#tbl_list').html('');
				
			// 成功時
			//} else {
				$('#db_viewer').html('');
				var ids = result_id.split(',');
				for (i = 0;i < ids.length;i++) {
					$('#' + ids[i]).html(ret[3 + i]);
				}
			//}
			
			//$('#ip_select').removeAttr('disabled','');
		}
	});
	
	// 表示切替
	if (($('#ip_select').val() !== '' && $('#db_select').val() !== 'reading...' && $('#db_select').val() !== '') || type === 'reload') {
		$('#query').removeAttr('readonly');
	} else {
		$('#query').attr('readonly', 'readonly');
	}
	// リファレンス初期化
	$('#refarence').children().removeAttr('selected');	
	return 1;
}


function set_lock() {
	var lock_type = $("input[name=lock]:checked").val();
	if (lock_type === 'none') {
		$('#ip_select').removeAttr("disabled", "");
		$('#db_select').removeAttr("disabled", "");
	} else if (lock_type === 'host') {
		$('#ip_select').attr("disabled", "disabled");
		$('#db_select').removeAttr("disabled", "");
	} else if (lock_type === 'db') {
		$('#ip_select').attr("disabled", "disabled");
		$('#db_select').attr("disabled", "disabled");
	}
}

// 表示切り替えトグル
function id_view(id) {
	if($('#' + id).css('display') === 'none') {
		$('#' + id).css('display', '');
	} else {
		$('#' + id).css('display', 'none');
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
		data = data + localStorage.getItem('connect_set' + i);
	}
	$('#ip_select').html(data);
}

// 接続設定読込
function connect_load() {
	// IP<##!##>ユーザー名<##!##>パスワード
	var options = $('#ip_select option:selected').val().split('<##!##>');
	$('#setting_connect_ip').val(options[0]);
	$('#setting_connect_user').val(options[1]);
	$('#setting_connect_pass').val(options[2]);
	$('#setting_connect_db').val(options[3]);
	$('#setting_connect_char').val(options[4]);
	$('#setting_connect_name').val(options[5]);
}

// 接続設定削除
function connect_del(){
	var options = $('#ip_select option:selected').val().split('<##!##>');
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
		if (!data) { num = i; break; }
	}
	var new_connect = '<option value="' + host + '<##!##>' + user + '<##!##>' + pass + '<##!##>' + dbtype + '<##!##>' + charcode + '<##!##>' + name + '<##!##>' + num + '">' + name + '</option>';
	
	localStorage.setItem('connect_set' + num, new_connect);
	alert('接続リストに' + name + 'を保存しました');
	connect_init();
}


// syntaxからqueryへ
function run_edit() {
	var query_value = $('#syntax').html();
	query_value = query_value.replace(/&amp;/g, "&");
	query_value = query_value.replace(/&quot;/g, "\"");
	query_value = query_value.replace(/&#039;/g, "'");
	query_value = query_value.replace(/&lt;/g, "<");
	query_value = query_value.replace(/&gt;/g, ">");
	query_value = text_clean(query_value);
	$('#query').val(query_value);
	query_clean();
}


// クエリ内キーボード操作
function run_key(event) {
	if (event.keyCode === $("input[name=setting_key_run]:checked").val() || event.charCode === $("input[name=setting_key_run]:checked").val()) {
		
	} else if (event.keyCode === $("input[name=setting_key_clean]:checked").val() || event.charCode === $("input[name=setting_key_clean]:checked").val()) {
		
	} else if (event.keyCode === $("input[name=setting_key_update]:checked").val() || event.charCode === $("input[name=setting_key_update]:checked").val()) {
		
	} else if (event.keyCode === $("input[name=setting_key_up]:checked").val() || event.charCode === $("input[name=setting_key_up]:checked").val()) {
		
	}
}



// 整形ボタン処理
function run_clean_query() {
	var new_val = query_clean(text_clean($('#query').val()));
	new_val = new_val.replace(/;\s*/g, ";\r\n");
	$('#query').val(new_val);
	
	// テキストエリア拡大
	var line_num = $("#query").val().split("\n").length;
	var height = (line_num > 4) ? 18 * line_num:50;
	$("#query").css('height', height + 'px');
}


// 更新ボタン処理
function db_reload() {
	run_ajax('reload', 'db_select,tbl_select,col_select,db_viewer,view_opt');
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
	var col_type = '';
	
	// 列選択されていた場合
	if (col_name !== '') {
		col_type = $('#col_select option:selected').html();
		col_type = col_type.replace(/.*\(/, "");
		col_type = col_type.replace(/\).*$/, "");
	} else {
		col_name = $('#col_select option').eq(1).val();
		col_type = $('#col_select option').eq(1).html();
		col_type = col_type.replace(/.*\(/, "");
		col_type = col_type.replace(/\).*$/, "");
	}
	
	if (type === 'refa_tblsel') {
		refa = 'SELECT * FROM ' + table_name + ' WHERE ' + col_name + '=\'\';';
	} else if (type === 'refa_rowadd') {
		var ins_col = '';	
		var ins_val = '';
		var ins_val_tmp = '';
		$('#col_select').find('option').each(function() { 
			ins_col += $(this).val() + ',';
			ins_val_tmp = $(this).html();
			ins_val_tmp = ins_val_tmp.replace(/.*\(/, "");
			ins_val_tmp = ins_val_tmp.replace(/\).*$/, "");
			ins_val += ins_val_tmp + ',';
		});
		ins_col = ins_col.replace(/,$/, "");
		ins_col = ins_col.replace(/^[ ]*\,/, "");
		ins_val = ins_val.replace(/,$/, "");
		ins_val = ins_val.replace(/^[ ]*\,/, "");
		refa = "INSERT INTO " + table_name + "(" + ins_col + ") VALUES (" + ins_val + ");";
	} else if (type === 'refa_rowup') {	
		refa = "UPDATE " + table_name + ' SET ' + col_name + "=''" + " WHERE " + col_name + "='';";
	} else if (type === 'refa_rowdel') {
		refa = "DELETE FROM " + table_name + " WHERE " + col_name + " ='';";
	} else if (type === 'refa_colcre') {
		refa = "ALTER TABLE " + table_name + " ADD " + col_name + " " + col_type + ";";
	} else if (type === 'refa_coldel') {
		refa = "ALTER TABLE " + table_name + " DROP " + col_name + ";";
	} else if (type === 'refa_tblcre') {
		var cre_col = '';	
		var cre_val = '';
		var cre_val_tmp = '';
		$('#col_select').find('option').each(function() { 
			cre_col = $(this).val();
			cre_val_tmp = $(this).html();
			cre_val_tmp = cre_val_tmp.replace(/.*\(/, "");
			cre_val_tmp = cre_val_tmp.replace(/\).*$/, "");
			cre_val += cre_col + ' ' + cre_val_tmp + ',';
		});
		cre_val = cre_val.replace(/^[ ]*\,/, "");
		cre_val = cre_val.replace(/\,$/, "");
		refa = "CREATE TABLE " + table_name + " (" + cre_val + ");";
	} else if (type === 'refa_tbldel') {
		refa = "DROP TABLE " + table_name + ";";
	} else if (type === 'refa_dbcre') {
		refa = "CREATE DATABASE " + db_name + ";";
	} else if (type === 'refa_dbdel') {
		refa = "DROP DATABASE " + db_name + ";";
	}
	var query = $("#query").val().replace(/(^\s+)|(\s+$)/g, "") + '\n' + refa;
	
	$("#query").html(refa);
	$("#query").val(refa);
	
	//$("#query").html(query);
	//$("#query").val(query);
	run_clean_query();
}

// クエリにorder by追加
var pre_query='';
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
		new_val=$("#syntax").html() + ' ORDER BY ' + field;
	}
	new_val=text_clean(new_val);
	
	// 昇順、降順切り替え
	var re = new RegExp("^(.*)ORDER BY " + field + "(.*)$", "ig");
	if (new_val === pre_query && new_val.match(re)) {
		new_val = String(RegExp.$1) + 'ORDER BY ' + field + ' DESC ' + String(RegExp.$2);
		new_val = text_clean(new_val);
	}
	
	$("#query").val(new_val);
	pre_query=new_val;
}

/*
// フィールド選択時、クエリにwhere追加
function add_row_where(){
	var table_name=$('#tbl_select').val();
	var db_name=$('#db_select').val();
	var limit_num=$('#limit_num').val();
	var col_name=$('#col_select').val();
	var col_type=$('#col_select option:selected').html();
	col_type=col_type.replace(/.*\(/,"");
	col_type=col_type.replace(/\).*$/,"");
	
	var new_val='SELECT * FROM ' + table_name + ' WHERE ' + col_name + '=\'\'';
	
	$("#query").val(new_val);
}
*/


// ページ送り用
function page_sel(id, cmd) {
	// 最終ページへ
	if (cmd === 100) {
		$("#" + id).val($("#" + id + " :last-child").val());
	} else if(cmd === -100) {
	// 最初のページへ
		$("#" + id).val('1');
	} else {
		var nowval = $("#"+id).val();
		$("#" + id).val(Number(nowval) + Number(cmd));
	}
}

// 実行ボタン押下時
function run_query() {
	run_ajax('query_run', 'db_viewer,view_opt');
	//$("#query").val('');
	$("#query").css('height', '60px');
}

// ホスト切り替え時
function run_host() {
	// テーブルなどを初期化
	$('#db_select').html('');
	$('#tbl_select').html('');
	$('#col_select').html('');
	if($('#ip_select option:selected').val() !== ''){
		connect_load();
		run_ajax('db_option', 'db_select');
		ls_save_html('ip_select');
	}
}

// DB切り替え時
function run_db() {
	run_ajax('tbl_option', 'tbl_select,tbl_list,tbl_type_select');
	ls_save_html('db_select');
}

// テーブル切り替え時
function run_tbl() {
	if($("#tbl_select").val()!==''){
		run_ajax('db_view', 'db_viewer,view_opt,col_select');
		ls_save('tbl_select');
	}
}

// 履歴の読込
function history_read(){

}

function dbview_chk_sql(id) {
	create_refa();
	var query = $("#query").val();
	var col_name = $('#col_select').val();
	if(col_name === ''){
		col_name=$('#col_select').eq(1).val();
	}
	query=query + 'WHERE ' + col_name + '=';
}

// チェックボックス色切り替え&SQL作成
function dbview_chk_toggle(id) {
	if($(id).attr('check') === '1'){
		$(id).css('background', 'white');
		$(id).attr('onmouseout', $(id).attr('_onmouseout'));
		$(id).attr('onmouseover', $(id).attr('_onmouseover'));
		$(id).removeAttr('_onmouseout');
		$(id).removeAttr('_onmouseover');
		$(id).attr('check', '0');
	}else{
		dbview_chk_sql(id);
		$(id).css('background', 'orange');
		$(id).attr('_onmouseout', $(id).attr('onmouseout'));
		$(id).attr('_onmouseover', $(id).attr('onmouseover'));
		$(id).removeAttr('onmouseout');
		$(id).removeAttr('onmouseover');
		$(id).attr('check', '1');
	}
}


/*
sub sqlformat {
	my($in) = @_;

	# 前の行につなげる単語
	my(@line_continue) = qw(
DISTINCT OF
);

	# 新しい行を開始する単語
	my(@line_init) = qw(
SELECT FROM LEFT RIGHT INNER FULL CROSS WHERE
GROUP HAVING ORDER UNION SET VALUES
INSERT DELETE UPDATE
OFFSET LIMIT
ON
CASE WHEN ELSE END
FOR
);

	# インデントをクリアする単語
	my(@indent_init) = qw(
SELECT FROM LEFT RIGHT INNER FULL CROSS WHERE
GROUP HAVING ORDER UNION SET VALUES
INSERT UPDATE DELETE
OFFSET LIMIT
FOR
);

	# 現在行を終了する単語
	my(@line_terminate) = qw(
SELECT DISTINCT INSERT UPDATE DELETE BY FROM
WHERE
AND OR
);

	# 次の行のインデントを増やす単語
	my(@indent_plus) = qw(
SELECT FROM LEFT RIGHT INNER FULL CROSS WHERE
GROUP HAVING ORDER UNION SET VALUES
INSERT UPDATE DELETE
OFFSET LIMIT
ON
CASE
);

	# 次の行のインデントを減らす単語
	my(@indent_minus) = qw(
END
);

	# 大文字にする単語
	my(@capitalize) = qw(
SELECT FROM LEFT RIGHT INNER FULL CROSS WHERE
GROUP HAVING ORDER UNION SET VALUES
INSERT UPDATE DELETE
OFFSET LIMIT
ON BY
CASE WHEN ELSE END AND OR
DISTINCT FOR OF IN EXISTS
AS JOIN THEN ASC DESC
);

	# 改行コードの統一
	$in =~ s/\r\n/\n/g;

	# 空白の統一
	$in =~ s/\t/ /g;
	$in =~ s/ +/ /g;

	# カンマの後に空白を入れて見やすくする
	$in =~ s/,([^ ])/, $1/g;

	# キーワードとカッコがつながっている場合は分離する
	foreach (@capitalize) {
		$in =~ s/\)($_)/\) $1/gi;
		$in =~ s/\(($_)/\( $1/gi;
		$in =~ s/($_)\(/$1 \(/gi;
		$in =~ s/($_)\)/$1 \)/gi;
	}

	my ($out) = '';
	my ($nest_level) = 0;
	my ($indent) = 0;
	my ($newline) = 1;

	foreach (split(/\n/, $in)) {
		for my $word (split) {
			if (scalar(grep {uc($_) eq uc($word)} @line_continue) > 0) {
				if (substr($out, length($out) - 1) eq "\n") {
					$out = substr($out, 0, length($out) - 1);
				}

			} elsif (scalar(grep {uc($_) eq uc($word)} @line_init) > 0) {
				$out .= "\n" if (! $newline);
				$newline = 1;
			}

			if (scalar(grep {uc($_) eq uc($word)} @indent_init) > 0) {
				$indent = 0;
			}

			if ($newline) {
				$out .= indent_string($nest_level + $indent);
				$newline = 0;

			} else {
				$out .= " ";
			}

			if (scalar(grep {uc($_) eq uc($word)} @capitalize) > 0) {
				$out .= uc($word);

			} else {
				$out .= $word;
			}

			while ($word =~ /\(/g) {
				$nest_level++;
			}
			while ($word =~ /\)/g) {
				$nest_level--;
			}

			if ($word =~ /,$/ || scalar(grep {uc($_) eq uc($word)} @line_terminate) > 0) {
				$out .= "\n";
				$newline = 1;
			}

			if (scalar(grep {uc($_) eq uc($word)} @indent_plus) > 0) {
				$indent++;

			} elsif (scalar(grep {uc($_) eq uc($word)} @indent_minus) > 0) {
				$indent--;
			}
		}
	}
	return $out;
}

sub indent_string {
	my($i) = @_;

	my $ind = '';
	for (1..$i) {
		$ind .= "\t";
	}
	return $ind;
}
*/


// 初期化処理
$(document).ready( function() {
	// クライアントIP取得
	$.getJSON('http://jsonip.appspot.com?callback=?', function (data) {
		var ip = data.ip;
		$('#ip').text(ip);
	});
	
	// タイトル更新
	$('#title').html('SQL Tool [' + location.hostname + ']');
	
	var default_ip = ls_load('ip_select');
	var default_db = ls_load('db_select');
	var default_tbl = ls_load('tbl_select');
	
	var default_value_limit = (ls_load('value_limit') === '') ? 100 : ls_load('value_limit');
	
	$('#value_limit').val(default_value_limit);
	$('input[name=run_key]').val(ls_load('run_key'));
	$('input[name=clean_key]').val(ls_load('clean_key'));
	
		
	if (ls_load('setting_view_tbl_list') === '1') {
		id_view('tbl_list');
	}
	
	if (ls_load('setting_view_typeselect') === '1') {
		id_view('tbl_type_select');
	}
	
	if (ls_load('setting_view_debug') === '1') {
		id_view('debug_panel');
	}
	
	ls_load('setting_tblsel_view_type_r');
	ls_load('setting_tblsel_view_type_v');
	ls_load('setting_tblsel_view_type_s');
	ls_load('setting_tblsel_view_type_i');
	
	//接続リスト初期化
	connect_init();
	
	ls_load('ip_select');
	
	if(default_db !== '') {
		run_ajax('db_option', 'db_select');
		run_ajax('tbl_option', 'tbl_select');
	} else if(default_ip !== '') {
		run_ajax('db_option', 'db_select');
	}
	
	// 保存ボタンの処理
	/*
	$("#save_link").addEventListener("click", function(){
	var value = $("#body").value;
	var href = "data:application/octet-stream," + encodeURIComponent(value);
	this.setAttribute("href", href);
	}, false);
	
	// read ボタンを押した際に実行する関数を登録
	$("#read_btn").addEventListener("change", onChangeFile, false);
	*/
	// ドロップ時イベントを登録
	//$id("body").addEventListener("dragover", onCancel, false);
	//$id("body").addEventListener("dragenter", onCancel, false);
	//$id("body").addEventListener("drop", onDropFile, false);
});


