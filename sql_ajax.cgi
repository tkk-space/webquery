#!/usr/bin/perl
use strict;
use warnings;
use Unicode::Japanese;
use DBI;
use CGI;
use bigint;

print "Content-type: text/html\n\n";
	
my @html;
my @sqlx;
my $error;
my $mes;
my @result;

# フォームデータの取得
my $query = new CGI;

my @params = $query->param;
my %in;
my %inh;
my %ind;
my %type_rows;
my $rnum;

for( @params ){
	$in{$_} = $query->param($_);
	$inh{$_}=&esch($in{$_});
	$ind{$_}=&escd($in{$_});
}

if(!defined $in{db_select}){ $in{db_select}='';}
if(!defined $in{tbl_select}){ $in{tbl_select}='';}
if(!defined $in{tbl_select2}){ $in{tbl_select2}='';}

if($in{tbl_select2} ne ''){ $in{tbl_select} = $in{tbl_select2};}

my $dsn;
my $pdb;

if($in{db_select} eq ''||$in{db_select} eq 'reading...'){
	$dsn="DBI:Pg:host=$in{setting_connect_ip};";
}else{
	$dsn="DBI:Pg:dbname=$in{db_select};host=$in{setting_connect_ip};";
}

my $DB=DBI->connect($dsn, $in{setting_connect_user}, 'postgres', {RaiseError=>0, PrintError=>0, AutoCommit=>1}) || &error_print('データベース接続エラー：'.$DBI::errstr);


if($in{type} eq "db_option"){
	$rnum=2;
	$html[0]=&db_option();
	$mes='DBに接続しました。(HOST:'.$in{setting_connect_ip}.')';
}elsif($in{type} eq "tbl_option"){
	$rnum=2;
	($html[0],$html[1],$html[2])=&tbl_option();
	$mes='DBを選択しました(HOST:'.$in{setting_connect_ip}.' DB:'.$in{db_select}.')';
}elsif($in{type} eq 'db_view'){
	$rnum=5;
	$sqlx[0]="SELECT * FROM $in{tbl_select} ".&create_query_limit();
	$html[0]=&table_naiyo($sqlx[0]);
	$html[1]=&pager_create($in{tbl_select});
	$html[2]=&col_option();
	$mes='テーブルを表示しました。(HOST:'.$in{setting_connect_ip}.'  DB:'.$in{db_select}.'  TABLE:'.$in{tbl_select}.'  PAGE:'.$in{page_select}.'  NUM:'.$in{limit_num}.')';
}elsif($in{type} eq 'query_run'){
	my $pager='';
	#if($in{query}=~/(.*);$/){
	#	@sqlx=split(/;/,$in{query});
	#}else{
		$sqlx[0]=$in{query};
	#}
	
	if($sqlx[0]=~/^[\s]*SELECT/i){
		$html[0]=&table_naiyo($sqlx[0]);
		$html[1]=&pager_create('( '.$sqlx[0].' ) as a');
	}else{
		for(my $i=0;$i<@sqlx;$i++){
			&run_sql_query($sqlx[$i]);
			$html[0].="実行しました。<font color=\"#ff0000\">$sqlx[$i]</font><br>";
		}
	}
	$mes='クエリを実行しました('.$in{query}.') (HOST:'.$in{setting_connect_ip}.' DB:'.$in{db_select}.'  )';
}elsif($in{type} eq 'reload'){
	$rnum=2;
	$sqlx[0]="SELECT * FROM $in{tbl_select} ".&create_query_limit();
	$html[0]=&db_option();
	($html[1],$html[5],$html[6])=&tbl_option();
	$html[2]=&col_option();
	$html[3]=&table_naiyo($sqlx[0]);
	$html[4]=&pager_create($in{tbl_select});
	
	$mes=$in{tbl_select}.'更新しました (HOST:'.$in{setting_connect_ip}.' DB:'.$in{db_select}.' TABLE:'.$in{tbl_select}.' )';
}else{
	&error_print("タイプ実行エラー：".$in{type});
}

# 結果表示
if($mes){
	@result=(($rnum,$sqlx[0],$mes),@html);
	&result_print(\@result);
}

$pdb->finish();
$DB->disconnect;


sub create_query_limit{
	my $qr_limit;
	if(int($in{limit_num}) > 0 && int($in{page_select}) > 0){
		$qr_limit='OFFSET '.((int($in{page_select})-1)*$in{limit_num}).' LIMIT '.$in{limit_num};
	}elsif(int($in{limit_num}) > 0){
		$qr_limit=' LIMIT '.$in{limit_num};
	}
	return $qr_limit;
}

sub pager_create{
	my($tbl_query)=@_;
	my %dat=&page_cnt($tbl_query);
	my $pager='
	<div>
	<input type="button" value="&lt;&lt;" onclick="page_sel(\'page_select\',-100); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_first" id="page_first">
	<input type="button" value="&lt;" onclick="page_sel(\'page_select\',-1); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_back" id="page_back">
	['.(($in{page_select} * $in{limit_num})-$in{limit_num} + 1) .' - '.($in{page_select} * $in{limit_num}).' / '.$dat{rows}.']
	<select style="width:40px;" onchange="run_ajax(\'db_view\',\'db_viewer,view_opt\');" type="text" size="1" name="page_select" id="page_select">
	'.$dat{page_opt_html}.'
	</select>
	/'.$dat{page_num}.'
	<input type="button" value="&gt;" onclick="page_sel(\'page_select\',1); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_next" id="page_next">
	<input type="button" value="&gt;&gt;" onclick="page_sel(\'page_select\',100); run_ajax(\'db_view\',\'db_viewer,view_opt\');" name="page_last" id="page_last">
	</div>
	';
	return $pager;
}

# カウント・ページ数の計算
sub page_cnt{
	my($tbl_query)=@_;
	my $sqlx_cnt="SELECT COUNT(*) as cnt FROM $tbl_query ";
	my $pdb=&run_sql_query($sqlx_cnt);
	my ($rows)=$pdb->fetchrow_array();
	my $page_num=(int($rows/$in{limit_num}))+1;
	my $html_option_pages;
	for(my $i=1;$i<=$page_num;$i++){
		if($in{page_select} eq $i){ $html_option_pages.='<option value="'.$i.'" selected>'.$i.'</option>';}
		else{ $html_option_pages.='<option value="'.$i.'">'.$i.'</option>'; }
	}
	if($in{page_select} eq ''){$in{page_select}=1;}
	my %dat=("rows"=>$rows,"page_opt_html"=>$html_option_pages,"page_num"=>$page_num);
	return %dat;
}

# DBを表示
sub db_option{
	my $db_sqlx="SELECT datname FROM pg_database WHERE datname NOT IN ('template0','template1') ORDER BY datname;";
	$pdb = $DB->prepare($db_sqlx)  ||  &error_print("データベース実行エラー：".$DB->errstr());
	$pdb->execute()  ||  &error_print("クエリ実行時エラー：".$pdb->errstr());

	my $db_opt_html='<option value=""></option>';
	while( my($datname)=$pdb->fetchrow_array()){
		my $selected='';
		if($in{db_select} eq $datname){ $selected="selected"; }
		$db_opt_html.='<option value="'.$datname.'" '.$selected.'>'.$datname.'</option>';
	}
	return $db_opt_html;
}

sub tbl_option {
	my @html;
	my $tbl_sqlx = "
SELECT c.relname,c.relkind,
	reltuples as rows
FROM
    pg_catalog.pg_class c
    JOIN
        pg_catalog.pg_roles r ON r.oid = c.relowner
    LEFT JOIN
        pg_catalog.pg_namespace n ON n.oid = c.relnamespace
WHERE
    n.nspname NOT IN ('pg_catalog', 'pg_toast') AND
    pg_catalog.pg_table_is_visible(c.oid)
ORDER BY
	relkind, relname;
	";
	$pdb=&run_sql_query($tbl_sqlx);
	
	# テーブルのオプション表示、テーブル表示
	my $tbl_opt_html='<option value=""></option>';
	my $tbl_tbl_html='<table cellpadding="0" style="font-size:small;background-color:#ddffff;float:left;">';
	while(my($datname,$type,$rows)= $pdb->fetchrow_array()){
		my $opt_color;
		$type_rows{$type}++;
		my @types=($in{setting_tblsel_view_type_r},$in{setting_tblsel_view_type_v},$in{setting_tblsel_view_type_s},$in{setting_tblsel_view_type_i},$in{setting_tblsel_view_type_sp});
		if(in_array($type,@types)){
			my $selected='';
			my $opt_color='';
			if($in{tbl_select} eq $datname){ $selected="selected"; }
			if($type eq 'r' ){
				$opt_color='#FFF';
			}elsif($type eq 'v'){
				$opt_color='#AFA';
				$rows='v';
			}elsif($type eq 'S'){
				$opt_color='#9cc';
				$rows='S';
			}elsif($type eq 'i'){
				$opt_color='#c9c';
				$rows='i';
			}
			$tbl_opt_html.='<option style="background:'.$opt_color.';"value="'.$datname.'" '.$selected.'>'.$datname."   ($rows)</option>";
			$tbl_tbl_html.='<tr bgcolor="#ddffff" onMouseOver="this.style.background=\'#ffcc00\'" onMouseOut="this.style.background=\'#ddffff\'"><td>';
			$tbl_tbl_html.='<a href="javascript:void(0);" onClick="run_ajax(\'db_view\',\'db_viewer,page_select\',\'tbl_select2='.$datname.'\')">'.$datname."  ($rows)</a>";
			$tbl_tbl_html.='</td></tr>';
		}
	}
	$tbl_tbl_html.='</table>';
	
	my $tbl_typ_opt_html='<option></option>';
	foreach my $type (keys %type_rows){
		if($type_rows{$type} > 0){
			if($type eq 'r')	{ $tbl_typ_opt_html.='<option value="r">TABLE('.$type_rows{$type}.')</option>'; }
			elsif($type eq 'v')	{ $tbl_typ_opt_html.='<option value="v">VIEW('.$type_rows{$type}.')</option>'; }
			elsif($type eq 'i')	{ $tbl_typ_opt_html.='<option value="i">INDEX('.$type_rows{$type}.')</option>'; }
			elsif($type eq 'S')	{ $tbl_typ_opt_html.='<option value="S">SEQUENCE('.$type_rows{$type}.')</option>'; }
			elsif($type eq 's')	{ $tbl_typ_opt_html.='<option value="s">SPECIAL('.$type_rows{$type}.')</option>'; }
		}
	}
	
	return ($tbl_opt_html,$tbl_tbl_html,$tbl_typ_opt_html);
}

# 列名の取得
sub col_option {
	my $col_sqlx="
	SELECT
		pg_attribute.attname,
		pg_type.typname
	FROM
		pg_attribute,
		pg_type
	WHERE
		pg_attribute.atttypid = pg_type.oid AND
		( pg_attribute.atttypid < 26 OR pg_attribute.atttypid > 29 ) AND
		attrelid IN (
			SELECT
				pg_class.oid
			FROM
				pg_class,
				pg_namespace
			WHERE
				relname='$in{tbl_select}' AND
				pg_class.relnamespace=pg_namespace.oid AND
				pg_namespace.nspname='public'
		);
	";
	my $pdb=&run_sql_query($col_sqlx);
	my $col_html.= '<option></option>';
	while(my ($datname,$type)= $pdb->fetchrow_array()){
		my $selected='';
		if($in{col_select} eq $datname){ $selected="selected"; }
		$col_html.='<option value="'.$datname.'" '.$selected.'>'.$datname.' ('.$type.')</option>';
	}
	return $col_html;
}

# テーブル内容のテーブルHTML作成
sub table_naiyo{
	my($tbl_naiyo_sqlx)=@_;
	my $pdb=run_sql_query($tbl_naiyo_sqlx);
	my $rows=$pdb->rows;
	my $type_info = $DB->type_info;
	my $html_table_naiyo='<table cellpadding="1" cellspacing="0" border="1" style="border-width:1px;border-color:#ccc; font-size:small">';
	
	# フィールド名
	$html_table_naiyo.='<tr bgcolor="#333" style="color:#fff;"><td><input type="checkbox"></td>';
	for (my $i = 0; $i < $pdb->{NUM_OF_FIELDS} ; $i++){
		# order byを追加する
		$html_table_naiyo.='<td><a  href="javascript:void(0);" onclick="add_order(\''.$pdb->{NAME}->[$i].'\');" style="color:#fff" title="">';
		$html_table_naiyo.=$pdb->{NAME}->[$i];
		#$html_table_naiyo.=$pdb->{TYPE}->[$i];
		$html_table_naiyo.='</td>';
	}
	
	$html_table_naiyo.='</tr>';
	
	# 交互に色変え
	my $t=0;
	while(my @data= $pdb->fetchrow_array()){
		my $tr_back=($t%2==0)?"fff":"ddd";
		# オンマウスで色を変える
		$html_table_naiyo.='<tr id="table_tr_'.$t.'"style="background-color:#'.$tr_back.';" onmouseover="this.style.backgroundColor=\'#9ff\'" onmouseout="this.style.backgroundColor=\'#'.$tr_back.'\'">';
		$html_table_naiyo.='<td><input type="checkbox" onclick="dbview_chk_toggle(\'#table_tr_'.$t.'\');"></td>';
		for(@data){
			if(int($in{setting_value_limit}) != 0 && length($_)>int($in{setting_value_limit})){
				$_='<a style="color:#000066;" href="javascript:void(0);" title="'.esch($_).'">'.substr(esch($_),0,int($in{setting_value_limit})).'...</a>';
			}else{
				$_=esch($_);
			}
			if($_ eq ''){ $_='&nbsp;'; }
			$html_table_naiyo.='<td style="white-space: nowrap;">'.$_.'</td>';
		}
		$html_table_naiyo.='</tr>';
		$t++;
	}

	$html_table_naiyo.='</table>';
	
	return $html_table_naiyo;
}


sub error_print{
	my($mes)=@_;
	#区切り文字を入れて200 OKとかの奴を最後にもっていく
	my @result=('0',$sqlx[0],$mes,'','','','','');
	&result_print(\@result);
	die;
}


# 返す文字列（<##!##>が区切り文字）
# SQL<##!##>結果(数字)<##!##>メッセージ文<##!##>表示されるHTML1<##!##>HTML2..3..
sub result_print{
	my @ary = @{shift()};
	my $sep='<##!##>';
	my $print_str='';
	foreach (@ary){
		$print_str .= $_.$sep;
	}
	print $print_str;
}

# ２バイト構成文字以外のエスケープ対象文字をエスケープ なんでもごじゃ～れ版
sub escd{
	my($pt)=@_;
	if(ref($pt)eq'ARRAY'){
		for(0..$#$pt){
			$$pt[$_] =&n2esc($$pt[$_]);
		}
	}elsif($#_>0){
		my(@pt)=@_;
		for(@pt){
			$_ =&n2esc($_);
		}
		return @pt;
	}elsif(ref($pt)eq'SCALAR'){
		$$pt =&n2esc($$pt);
	}else{
		$pt =&n2esc($pt);
		return $pt;
	}
#	$$pt =~ s/\r\n|\r|\n/<BR>/g;
}


sub in_array {
	my $item = shift;
	my @list = @_;
	
	for (@list) {
		return 1 if $_ eq $item;
	}
	
	return 0;
}


# ２バイト構成文字以外のエスケープ対象文字をエスケープ
sub n2esc{
	my($cstr)=@_;
	my $ch='';
	my $ni=0;
	my $ret_str='';
	
	while( ($ch=substr($cstr,$ni,1)) ne '' ){ # 一文字ずつ切る
		$ni++;
		if( $ch=~/[\x80-\x9f\xe0-\xff]/ ){ # 全角発見  (\xA0-\xDF は半角カナ扱い)
			$ret_str.=$ch;
			$ret_str.=substr($cstr,$ni,1);
			$ni++;
		}elsif($ch=~/[',\\\[\]]/){ # ２バイト構成文字以外でエスケープ対象文字を発見
			$ret_str.="\\$ch";
		}elsif($ch eq '	'){ # タブ
			$ret_str.="\\t";
		}else{ # 他
			$ret_str.=$ch;
		}
	}
	$ret_str =~ s/\r\n|\r|\n/\\n/g;
#	$ret_str =~ s/\t/\\t/g;
	return $ret_str;
}

sub esch{
	my($pt)=@_;
	if($pt){
		if(ref($pt)eq'ARRAY'){
			for(0..$#$pt){
				$$pt[$_] =~ s/&/&amp;/g;
				$$pt[$_] =~ s/</&lt;/g;
				$$pt[$_] =~ s/>/&gt;/g;
				$$pt[$_] =~ s/"/&quot;/g;
			}
		}elsif($#_>0){
			my(@pt)=@_;
			for(@pt){
				$_ =~ s/&/&amp;/g;
				$_ =~ s/</&lt;/g;
				$_ =~ s/>/&gt;/g;
				$_ =~ s/"/&quot;/g;
			}
			return @pt;
		}elsif(ref($pt)eq'SCALAR'){
			$$pt =~ s/&/&amp;/g;
			$$pt =~ s/</&lt;/g;
			$$pt =~ s/>/&gt;/g;
			$$pt =~ s/"/&quot;/g;
		}else{
			$pt =~ s/&/&amp;/g;
			$pt =~ s/</&lt;/g;
			$pt =~ s/>/&gt;/g;
			$pt =~ s/"/&quot;/g;
			return $pt;
		}
	}
#	$$pt =~ s/\r\n|\r|\n/<BR>/g;
}

sub run_sql_query{
	my($sqlx)=@_;
	$pdb = $DB->prepare($sqlx)  ||  &error_print("データベース実行エラー(".$in{type}.")：".$DB->errstr());
	$pdb->execute()  ||  &error_print("クエリ実行時エラー(".$in{type}.")：".$DB->errstr());
	return $pdb;
}

exit;