<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.1.2
*/namespace
Adminer;if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];const
VERSION="6.1.2";error_reporting(24575);set_error_handler(function($Yc,$ad){return!!preg_match('~^Undefined (array key|offset|index)~',$ad);},E_WARNING|E_NOTICE);$Cd=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($Cd||ini_get("filter.default_flags")){foreach(array('_GET','_POST','_COOKIE','_SERVER')as$X){$Bl=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Bl)$$X=$Bl;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($f=null){return($f?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Nb=adminer()->credentials();$J=Driver::connect($Nb[0],$Nb[1],$Nb[2]);return(is_object($J)?$J:null);}function
idf_unescape($t){if(!preg_match('~^[`\'"[]~',$t))return$t;$Af=substr($t,-1);return
str_replace($Af.$Af,$Af,substr($t,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($za,$w,$i=null){return($za&&array_key_exists($w,$za)?$za[$w]:$i);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
int_type(){return'(tiny|small|medium|big)?int(eger|\d)?';}function
number_type(){return'(^('.int_type().'|decimal|numeric|number|real|(binary_|half_|scaled_)?float\d?|(binary_)?double( precision)?|(small)?money)$)';}function
text_type(){return'char|text'.(JUSH=="sql"?'|enum|set':'');}function
is_user_type($U){return
in_array($U,idx(driver()->structuredTypes(),'User types',array()));}function
full_type_sql(array$k){$U=$k["type"];return(is_user_type($U)?idf_escape($U).substr($k["full_type"],strlen($U)):$k["full_type"]);}function
is_searchable(array$k,array$X){if(!isset($k["privileges"]["where"]))return
false;if(preg_match('~NULL$~',$X["op"]))return
true;$U=$k["type"];$yj=$X["val"];$Pa='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Pa~",$U))return
false;if(preg_match(number_type(),$U)){$ah='-?\d+(\.\d+)?';$Cg='';if(preg_match('~IN$~',$X["op"])){$yj=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$yj);$Cg="( *, *$ah)*";}elseif($X["op"]=="BETWEEN")$Cg="\\s+AND\\s+$ah";return(bool)preg_match('~^'.$ah.$Cg.'$~i',$yj);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$yj);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$yj);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$yj);return
true;}function
remove_slashes(array$Xl,$Cd=false){$J=array();foreach($Xl
as$w=>$X)$J[stripslashes($w)]=(is_array($X)?remove_slashes($X,$Cd):($Cd?$X:stripslashes($X)));return$J;}function
bracket_escape($t,$Ia=false){static$kl=array(':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5');return
strtr($t,($Ia?array_flip($kl):$kl));}function
url_escape($Q){static$kl=array();if(!$kl){$kl=array(' '=>'+');foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$bb)$kl[$bb]=sprintf('%%%02X',ord($bb));for($r=0;$r<256;$r++){if($r<32||$r>126)$kl[chr($r)]=sprintf('%%%02X',$r);}}return
strtr((string)$Q,$kl);}function
min_version($am,$Vf="",$f=null){$f=connection($f);$Mj=$f->server_info;if($Vf&&preg_match('~([\d.]+)-MariaDB~',$Mj,$A)){$Mj=$A[1];$am=$Vf;}return$am&&version_compare($Mj,$am)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($wh,$Y){return(function_exists('ini_set')?\ini_set($wh,$Y):false);}function
ini_bool($Ue){$X=ini_get($Ue);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($Ue){$X=ini_get($Ue);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($K,$Ih){$Yf=(int)ini_get("max_input_vars");return($Yf?(int)floor(($Yf-$Ih)/$K):0);}function
max_input_vars_error(){$Ue="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$Ue = ".ini_get($Ue)."</b>");}function
sid(){static$J;if($J===null)$J=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
set_password($Zl,$O,$V,$F){$_SESSION["pwds"][$Zl][$O][$V]=($_COOKIE["adminer_key"]&&is_string($F)?array(encrypt_string($F,$_COOKIE["adminer_key"])):$F);}function
get_password(){$J=get_session("pwds");if(is_array($J))$J=($_COOKIE["adminer_key"]?decrypt_string($J[0],$_COOKIE["adminer_key"]):false);return$J;}function
get_val($H,$k=0,$_b=null){$_b=connection($_b);$I=$_b->query($H);if(!is_object($I))return
false;$K=$I->fetch_row();return($K?$K[$k]:false);}function
get_vals($H,$c=0){$J=array();$I=connection()->query($H);if(is_object($I)){while($K=$I->fetch_row())$J[]=$K[$c];}return$J;}function
get_key_vals($H,$f=null,$Pj=true){$f=connection($f);$J=array();$I=$f->query($H);if(is_object($I)){while($K=$I->fetch_row()){if($Pj)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$f=null,$j="<p class='error'>"){$_b=connection($f);$J=array();$I=$_b->query($H);if(is_object($I)){while($K=$I->fetch_assoc())$J[]=$K;}elseif(!$I&&!$f&&$j&&(defined('Adminer\PAGE_HEADER')||$j=="-- "))echo$j.adminer()->error()."\n";return$J;}function
unique_array($K,array$v){foreach($v
as$u){if(preg_match("~^(PRIMARY|UNIQUE)$~",$u["type"])&&!$u["partial"]){$J=array();foreach($u["columns"]as$w){if(!isset($K[$w]))continue
2;$J[$w]=$K[$w];}return$J;}}}function
where_function($Ud,$c,array$k){if($Ud=="md5")return
driver()->md5($c,$k)?:$c;return(in_array($Ud,driver()->functions)||in_array($Ud,driver()->grouping)?apply_sql_function($Ud,$c):$c);}function
where(array$Z,array$l=array()){$J=array();foreach((array)$Z["where"]as$w=>$X){$w=bracket_escape($w,true);$c=idf_escape($w);$k=idx($l,$w,array());$xd=$k["type"];$hf=$k&&(is_blob($k)||preg_match('~binary~',$xd));$J[]=$c.($hf&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$xd=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$k["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($xd,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($k,q($X)))))));if(JUSH=="sql"&&preg_match('~char|text~',$xd)&&preg_match("~[^ -@]~",$X))$J[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$w)$J[]=idf_escape($w)." IS NULL";foreach((array)$Z["col"]as$r=>$ob){$X=idx($Z["val"],$r);$J[]=where_function(idx($Z["fun"],$r),idf_escape($ob),idx($l,$ob,array())).($X!==null?" = ".q($X):" IS NULL");}return
implode(" AND ",$J);}function
where_columns(array$l){$J=array();foreach((array)$_GET["null"]as$w)$J[$w]=true;foreach(array_keys((array)$_GET["where"])as$w)$J[bracket_escape($w,true)]=true;foreach((array)$_GET["col"]as$ob)$J[$ob]=true;return
array_intersect_key($J,$l);}function
where_check($X,array$l=array()){parse_str($X,$eb);remove_slashes(array(&$eb));return
where($eb,$l);}function
where_link($r,$c,$Y,$th="="){$qh=($Y!==null?$th:"IS NULL");return"&where[$r][col]=".url_escape($c).($qh!=first(adminer()->operators())?"&where[$r][op]=".url_escape($qh):"")."&where[$r][val]=".url_escape($Y);}function
convert_fields(array$d,array$l,array$N=array()){$J="";foreach($d
as$w=>$X){if($N&&!in_array(idf_escape($w),$N))continue;$_a=convert_field($l[$w]);if($_a)$J
.=", $_a AS ".idf_escape($w);}return$J;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),array(";"=>"%3B",","=>"%2C"));}function
cookie($B,$Y,$Kf=2592000){header("Set-Cookie: $B=".rawurlencode($Y).($Kf?"; expires=".gmdate("D, d M Y H:i:s",time()+$Kf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($B=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
get_url($Kl,$Eb){$http_response_header=null;$Zc=array();set_error_handler(function($Yc,$j)use(&$Zc){$Zc[]=preg_replace('~^file_get_contents\([^)]*\):\s*~','',$j);return
true;});$J=file_get_contents($Kl,false,$Eb);restore_error_handler();$qe=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);return
array($J,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($qe,0,''),$A)?$A[1]:''),(array)$qe,($J===false?implode("\n",$Zc):''),);}function
json_decode_exact($qf){$qf=preg_replace('~"(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','"\\\\u0001$1',$qf);return
json_decode(preg_replace('~"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)|-?\d[-+.\deE]*+~','"\\\\u0001$0"',$qf));}function
json_scalar($X){return(is_string($X)&&substr($X,0,1)=="\1"?substr($X,1):$X);}function
json_encode_exact($X,$Fd=0){return
preg_replace('~"\\\\u0001(-?\d[^"\\\\]*)"|(")\\\\u0001(\\\\u0001(?:[^"\\\\]|\\\\.)*+")|"(?:[^"\\\\]|\\\\.)*+"(*SKIP)(*FAIL)~','$1$2$3',json_encode($X,$Fd));}function
get_settings($Hb){parse_str($_COOKIE[$Hb],$Qj);return$Qj;}function
get_setting($w,$Hb="adminer_settings",$i=null){return
idx(get_settings($Hb),$w,$i);}function
save_settings(array$Qj,$Hb="adminer_settings"){$Y=http_build_query($Qj+get_settings($Hb));cookie($Hb,$Y);$_COOKIE[$Hb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&(!function_exists('session_status')||session_status()==PHP_SESSION_NONE))session_start();}function
stop_session($Id=false){$Nl=ini_bool("session.use_cookies");if(!$Nl||$Id){session_write_close();if($Nl&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($w){return$_SESSION[$w][DRIVER][SERVER][$_GET["username"]];}function
set_session($w,$X){$_SESSION[$w][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($Zl,$O,$V,$h=null){$Jl=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($h!==null?"db|":"").($Zl=='mssql'||$Zl=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Jl,$A);return"$A[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($Zl!="server"||$O!=""?url_escape($Zl)."=".url_escape($O)."&":"")."username=".url_escape($V).($h!=""?"&db=".url_escape($h):"").($A[2]?"&$A[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($_,$og=null){if($og!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($_!==null?$_:$_SERVER["REQUEST_URI"]))][]=$og;}if($_!==null){if($_=="")$_=".";header("Location: $_");exit;}}function
query_redirect($H,$_,$og,$Pi=true,$hd=true,$sd=false,$Xk=""){if($hd){$jk=microtime(true);$sd=!connection()->query($H);$Xk=format_time($jk);}$dk=($H?adminer()->messageQuery($H,$Xk,$sd):"");if($sd){adminer()->error
.=adminer()->error().$dk.script("messagesPrint();")."<br>";return
false;}if($Pi)redirect($_,$og.$dk);return
true;}class
Queries{static$queries=array();static$start=0;}function
remember_query($H){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$H:(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";");}function
queries($H){remember_query($H);return
connection()->query($H);}function
apply_queries($H,array$T,$bd='Adminer\table'){foreach($T
as$R){if(!queries("$H ".$bd($R)))return
false;}return
true;}function
queries_redirect($_,$og,$Pi){$Ii=implode("\n",Queries::$queries);$Xk=format_time(Queries::$start);return
query_redirect($Ii,$_,$og,$Pi,false,!$Pi,$Xk);}function
format_time($jk){return
sprintf('%.3f s',max(0,microtime(true)-$jk));}function
relative_uri($Jl=''){return
preg_replace_callback('~^[^?]*~',function($A){return
str_replace(":","%3A",$A[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($Jl?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($Nh=""){return
substr(preg_replace("~(?<=[?&])($Nh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($B,$cc=false){$zd=$_FILES[$B];if(!$zd)return
null;foreach($zd
as$w=>$X)$zd[$w]=(array)$X;$J=array();foreach($zd["error"]as$w=>$j){if($j)return$j;$m=$zd["name"][$w];$fl=$zd["tmp_name"][$w];$Cb=file_get_contents($cc&&preg_match('~\.gz$~',$m)?"compress.zlib://$fl":$fl);if($cc){$jk=substr($Cb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$jk))$Cb=iconv("utf-16","utf-8",$Cb);elseif($jk=="\xEF\xBB\xBF")$Cb=substr($Cb,3);}$J[]=array($m,$Cb);}return$J;}function
get_file($w,$cc=false,$jc=""){$Bd=get_files($w,$cc);if(!is_array($Bd))return$Bd;$J='';foreach($Bd
as$zd){$Cb=$zd[1];$J
.=$Cb;if($jc)$J
.=(preg_match("($jc\\s*\$)",$Cb)?"":$jc)."\n\n";}return$J;}function
upload_error($j){$gg=($j==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($j?'Unable to upload a file.'.($gg?" ".sprintf('Maximum allowed file size is %sB.',$gg):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
utf8_length($X){return
strlen(preg_replace('~[\x80-\xBF]~','',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$A);$Uj=strlen($A[3]);$J=number_format($X,0,".","");$J=preg_replace('~\B(?=(\d{'.(strlen($A[2])?:$Uj).'})*\d{'.$Uj.'}$)~',$A[1],$J);return
strtr($J,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$w){$X=idx($S,$w,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$wa=($w=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));$X=in_array($w,array("Data_length","Index_length","Data_free"))?adminer()->formatSizeValue($X):format_number($X);return($wa?"~ ":"").$X;}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$td=false){$J=table_status($R,$td);return($J?reset($J):array("Name"=>$R));}function
column_foreign_keys($R){$J=array();foreach(adminer()->foreignKeys($R)as$n){foreach($n["source"]as$X)$J[$X][]=$n;}return$J;}function
fields_from_edit(){$J=array();foreach((array)$_POST["field_keys"]as$w=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$w];$_POST["fields"][$X]=$_POST["field_vals"][$w];}}foreach((array)$_POST["fields"]as$w=>$X){$B=bracket_escape($w,true);$J[$B]=array("field"=>$B,"full_type"=>"","type"=>"","privileges"=>array("insert"=>1,"update"=>1,"where"=>1,"order"=>1),"null"=>true,"auto_increment"=>($B==driver()->primary),);}return$J;}function
dump_headers($De,$Fg=false){$J=adminer()->dumpHeaders($De,$Fg);$Kh=$_POST["output"];if($Kh!="text"||$J=="tar"){$yb=($Kh!="text"&&$Kh!="file"&&preg_match('~^[0-9a-z]+$~',$Kh)?".$Kh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($De).".$J$yb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$J;}function
dump_csv(array$K){$sl=$_POST["format"]=="tsv";foreach($K
as$w=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($sl?'\t':'[,;]|^$').'~',$X))$K[$w]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($sl?"\t":";")),$K)."\r\n";}function
parse_csv($Qb,$Gj){$J=array();preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Qb,$Wf);foreach($Wf[0]as$K){preg_match_all("~((?>\"[^\"]*\")+|[^$Gj]*)$Gj~",$K.$Gj,$Xf);$J[]=$Xf[1];}return$J;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?str_replace('""','"',substr($X,1,-1)):$X);}function
apply_sql_function($p,$c){return($p?($p=="unixepoch"?"DATETIME($c, '$p')":($p=="count distinct"?"COUNT(DISTINCT ":strtoupper("$p("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($m){if(is_link($m))return;$o=@fopen($m,"c+");if(!$o)return;@chmod($m,0660);if(!flock($o,LOCK_EX)){fclose($o);return;}return$o;}function
file_write_unlock($o,$Ub){rewind($o);fwrite($o,$Ub);ftruncate($o,strlen($Ub));file_unlock($o);}function
file_unlock($o){flock($o,LOCK_UN);fclose($o);}function
first(array$za){return
reset($za);}function
password_file($Kb){$m=get_temp_dir()."/adminer.key";if(!$Kb&&!file_exists($m))return'';$o=file_open_lock($m);if(!$o)return'';$J=stream_get_contents($o);if(!$J){$J=rand_string();file_write_unlock($o,$J);}else
file_unlock($o);return$J;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$z,array$k,$Vk,array$gi=array()){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$uf=array();foreach($X
as$W)$uf+=array_fill_keys(array_keys($W),null);foreach(array_keys($uf)as$sf)$J
.="<th>".h($sf);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($uf,$W)as$Tl)$J
.="<td>".select_value($Tl,$z,$k,$Vk,$gi);}}else{foreach($X
as$sf=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($sf):"")."<td>".select_value($W,$z,$k,$Vk,$gi);}return"<table>$J</table>";}if(!$z)$z=adminer()->selectLink($X,$k);if($z===null){if(is_mail($X))$z="mailto:$X";if(is_url($X))$z=$X;}$X=driver()->value($X,$k);$J=adminer()->editVal($X,$k);if($J!==null){if(!is_utf8($J))$J="\0";elseif($Vk!=""&&is_shortable($k))$J=shorten_utf8($J,max(0,+$Vk),"",$gi);else$J=highlight_matches($J,$gi);}return
adminer()->selectVal($J,$z,$k,$X);}function
is_blob(array$k){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$k["type"])&&!in_array($k["type"],idx(driver()->structuredTypes(),'User types',array()));}function
is_identity_always(array$k){return$k["auto_increment"]&&(JUSH=="mssql"||$k["default"]=="GENERATED ALWAYS AS IDENTITY");}function
is_mail($Oc){$Ba='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$Ac='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$fi="$Ba+(\\.$Ba+)*@($Ac?\\.)+$Ac";return
is_string($Oc)&&preg_match("(^$fi(,\\s*$fi)*\$)i",$Oc);}function
is_url($Q){$Ac='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($Ac?\\.)+$Ac(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i",$Q);}function
is_ipv6($ia){$q='[\da-f]{1,4}';$gf='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($q:){7}$q|($q:){6}$gf|(($q:)*$q)?::(($q:)*($q|$gf))?)$~iD",$ia);}function
is_shortable(array$k){return!preg_match('~'.number_type().'|date|time|year~',$k["type"]);}function
url_host($_e){return(strpos($_e,":")!==false?"[$_e]":$_e);}function
server_parts(array$Zh){return
array("scheme"=>(string)$Zh["scheme"],"host"=>(string)$Zh["host"],"port"=>(string)$Zh["port"],"socket"=>(string)$Zh["socket"],"path"=>(string)$Zh["path"],);}function
parse_server($O){if($O=="")return
server_parts(array());if($O[0]==":"&&!is_ipv6($O)){$cj=substr($O,1);if(preg_match('~^\d+$~D',$cj))return
server_parts(array("port"=>$cj));return(preg_match('~^/[-\w.:/]*$~D',$cj)?server_parts(array("socket"=>$cj)):null);}$wj="";if(preg_match('~^([-+.\w]+)://~',$O,$A)){$wj=strtolower($A[1]);$O=substr($O,strlen($A[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$O,$A))return(is_ipv6($A[1])?server_parts(array("scheme"=>$wj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);if(is_ipv6($O))return
server_parts(array("scheme"=>$wj,"host"=>$O));if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$O,$A))return
server_parts(array("scheme"=>$wj,"host"=>$A[1],"port"=>$A[3]));return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$O,$A)?server_parts(array("scheme"=>$wj,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);}function
count_rows($R,array$Z,$if,array$q){$H=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($if&&(JUSH=="sql"||count($q)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$q).")$H":"SELECT COUNT(*)".($if?" FROM (SELECT 1$H GROUP BY ".implode(", ",$q).") x":$H));}function
slow_query($H){$h=adminer()->database();$Yk=adminer()->queryTimeout();$Vj=driver()->slowQuery($H,$Yk);$f=null;if(!$Vj&&support("kill")){$f=connect();if($f&&($h==""||$f->select_db($h))){$vf=number(get_val(connection_id(),0,$f));echo
script("const timeout = setTimeout(() => { ajax('".js_escape(ME)."script=kill', function () {}, 'kill=$vf&token=".get_token()."'); }, 1000 * $Yk);");}}ob_flush();flush();$J=@get_key_vals(($Vj?:$H),$f,false);if($f){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$Mi=rand(1,1e6);return($Mi^$_SESSION["token"]).":$Mi";}function
verify_token(){list($gl,$Mi)=explode(":",$_POST["token"]);return($Mi^$_SESSION["token"])==$gl&&in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"));}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$pc=""){$sa=array_flip(str_split(compress_alphabet()));$x=strlen($Q);$Vl=($x?13*($x-1)/2-$sa[$Q[0]]:0);$Pa="";$cj=0;$dj=0;for($r=1;$r<$x;$r+=2){$cj=($cj<<13)+$sa[$Q[$r]]*93+$sa[$Q[$r+1]];$dj+=13;while($dj>=8&&$Vl>=8){$dj-=8;$Vl-=8;$Pa
.=chr($cj>>$dj);$cj&=(1<<$dj)-1;}}if($Pa=="")return"";if($pc!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,array('dictionary'=>$pc)),$Pa,ZLIB_FINISH);return($pc==""&&function_exists('gzinflate')?gzinflate($Pa):inflate($Pa,$pc));}function
inflate($Pa,$pc=""){$Hf=array(3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258);$If=array(0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0);$tc=array(1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577);$vc=array(0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13);$J=$pc;$G=0;do{$Dd=inflate_bits($Pa,$G,1);$U=inflate_bits($Pa,$G,2);if(!$U){$G=($G+7)&~7;$x=inflate_bits($Pa,$G,16);$G+=16;$J
.=substr($Pa,$G>>3,$x);$G+=$x<<3;}else{if($U==1){$Qf=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$wc=array_fill(0,30,5);}else{$Pf=inflate_bits($Pa,$G,5)+257;$uc=inflate_bits($Pa,$G,5)+1;$D=array(16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15);$ug=array_fill(0,19,0);$tg=inflate_bits($Pa,$G,4)+4;for($r=0;$r<$tg;$r++)$ug[$D[$r]]=inflate_bits($Pa,$G,3);$vg=inflate_table($ug);$Jf=array();while(count($Jf)<$Pf+$uc){$xk=inflate_symbol($Pa,$G,$vg);if($xk==16)$Jf=array_merge($Jf,array_fill(0,inflate_bits($Pa,$G,2)+3,end($Jf)));elseif($xk==17)$Jf=array_merge($Jf,array_fill(0,inflate_bits($Pa,$G,3)+3,0));elseif($xk==18)$Jf=array_merge($Jf,array_fill(0,inflate_bits($Pa,$G,7)+11,0));else$Jf[]=$xk;}$Qf=array_slice($Jf,0,$Pf);$wc=array_slice($Jf,$Pf);}$Rf=inflate_table($Qf);$yc=inflate_table($wc);while(($xk=inflate_symbol($Pa,$G,$Rf))!=256){if($xk<256)$J
.=chr($xk);else{$x=$Hf[$xk-257]+inflate_bits($Pa,$G,$If[$xk-257]);$xc=inflate_symbol($Pa,$G,$yc);$hh=strlen($J)-$tc[$xc]-inflate_bits($Pa,$G,$vc[$xc]);for($r=0;$r<$x;$r++)$J
.=$J[$hh+$r];}}}}while(!$Dd);return($pc==""?$J:substr($J,strlen($pc)));}function
inflate_bits($Pa,&$G,$Jb){$J=0;for($r=0;$r<$Jb;$r++){$J+=((ord($Pa[$G>>3])>>($G&7))&1)<<$r;$G++;}return$J;}function
inflate_table(array$Jf){$R=array();$nb=0;for($Qa=1;$Qa<=max($Jf);$Qa++){foreach($Jf
as$xk=>$x){if($x==$Qa){$R[$Qa][$nb]=$xk;$nb++;}}$nb<<=1;}return$R;}function
inflate_symbol($Pa,&$G,array$R){$nb=0;$Qa=0;do{$nb=($nb<<1)+inflate_bits($Pa,$G,1);$Qa++;}while(!isset($R[$Qa][$nb]));return$R[$Qa][$nb];}function
script($ak,$jl="\n"){return"<script".nonce().">$ak</script>$jl";}function
script_src($Kl,$fc=false){return"<script src='".h($Kl)."'".nonce().($fc?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($cd,$ie,$xa=null){$ya=array();foreach(array_slice(func_get_args(),2)as$X)$ya[]=json_encode($X,256);return" data-on$cd='".str_replace(array('&','<',"'"),array('&amp;','&lt;','&#039;'),"$ie(".implode(", ",$ya).")")."'";}function
input_hidden($B,$Y=""){return"<input type='hidden' name='".h($B)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(array('&','<','"',"'","\0"),array('&amp;','&lt;','&quot;','&#039;','&#0;'),$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($B,$Y,$gb,$xf="",$b="",$mb="",$zf=""){$J="<input type='checkbox' name='$B' value='".h($Y)."'".($gb?" checked":"").($xf==""&&$mb?" class='$mb'":"").($zf?" aria-labelledby='$zf'":"").$b.">";return($xf!=""?"<label".($mb?" class='$mb'":"").">$J".h($xf)."</label>":$J);}function
optionlist($C,$Dj=null,$Ol=false){$J="";foreach($C
as$sf=>$W){$yh=array($sf=>$W);if(is_array($W)){$J
.='<optgroup label="'.h($sf).'">';$yh=$W;}foreach($yh
as$w=>$X)$J
.='<option'.($Ol||is_string($w)?' value="'.h($w).'"':'').($Dj!==null&&($Ol||is_string($w)?(string)$w:$X)===$Dj?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
group_system(array$Ng,$vj=false){$J=array();$yk=array();foreach($Ng
as$B){if($vj?driver()->isSystem(DB,$B):driver()->isSystem($B))$yk[]=$B;else$J[]=$B;}if($yk)$J[sprintf('System%s','')]=$yk;return$J;}function
html_select($B,array$C,$Y="",$b="",$zf=""){static$xf=0;$yf="";if(!$zf&&substr($C[""],0,1)=="("){$xf++;$zf="label-$xf";$yf="<option value='' id='$zf'>".h($C[""]);unset($C[""]);}return"<select name='".h($B)."'".($zf?" aria-labelledby='$zf'":"")."$b>".$yf.optionlist($C,$Y)."</select>";}function
html_radios($B,array$C,$Y="",$Gj=""){$J="";foreach($C
as$w=>$X)$J
.="<label><input type='radio' name='".h($B)."' value='".h($w)."'".($w==$Y?" checked":"").">".h($X)."</label>$Gj";return$J;}function
confirm($og=""){return
on('click','confirmClick',$og?:'Are you sure?');}function
print_fieldset($s,$Gf,$dm=false){echo"<fieldset><legend>","<a href='#fieldset-$s' class='toggle'>$Gf</a>","</legend>","<div id='fieldset-$s'".($dm?"":" class='hidden'").">\n";}function
bold($Ra,$mb=""){return($Ra?" class='active $mb'":($mb?" class='$mb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($E){return
remove_from_uri("page|next").($E?"&page=$E".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($E,$Rb){return" ".($E==$Rb?($E?"<b>".($E+1)."</b>":$E+1):'<a href="'.h(pagination_href($E)).'">'.($E+1)."</a>");}function
hidden_fields(array$Ei,array$He=array(),$wi=''){$J=false;foreach($Ei
as$w=>$X){if(!in_array($w,$He)){if(is_array($X))hidden_fields($X,array(),$w);else{$J=true;echo
input_hidden(($wi?$wi."[$w]":$w),$X);}}}return$J;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$Il){$Il=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($Il?on('submit','uploadProgress',ME."upload=$Il",SESSION_NAME."=$Il"):"");}function
file_input($b,$cj=""){$ag="max_file_uploads";$bg=ini_get($ag);$gg="upload_max_filesize";$hg=ini_bytes($gg);$si=ini_bytes("post_max_size");if($si&&$si<$hg){$gg="post_max_size";$hg=$si;}$ig=ini_get($gg);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$bg,sprintf('Increase %s.',"$ag = $bg"),$hg,sprintf('Increase %s.',"$gg = $ig")).">$cj":'File uploads are disabled.');}function
enum_input($U,$b,array$k,$Y,$Rc=""){preg_match_all("~".driver()->enumLength."~",$k["length"],$Wf);$wi=($k["type"]=="enum"?"val-":"");$gb=(is_array($Y)?in_array("null",$Y):$Y===null);$J=($k["null"]&&$wi?"<label><input type='$U'$b value='null'".($gb?" checked":"")."><i>$Rc</i></label>":"");foreach($Wf[0]as$X){$X=stripcslashes(idf_unescape($X));$gb=(is_array($Y)?in_array($wi.$X,$Y):$Y===$X);$J
.=" <label><input type='$U'$b value='".h($wi.$X)."'".($gb?' checked':'').'>'.h(adminer()->editVal($X,$k)).'</label>';}return$J;}function
input(array$k,$Y,$p,$Ga=false,$Fl=false){$B=h(bracket_escape($k["field"]));echo"<td class='function'>";$Xc=driver()->enumLength($k);if($Xc){$k["type"]="enum";$k["length"]=$Xc;}$C=($k["type"]=="enum"||$k["type"]=="set");if(is_array($Y)&&!$p&&!$C)$p="json";$qf=($p=="json"||preg_match('~^jsonb?$~',$k["full_type"]));if($qf&&$Y!=''&&(JUSH!="pgsql"||$k["type"]!="json")&&(is_array($Y)||!$_POST["save"]))$Y=(is_array($Y)?json_encode($Y,128|64|256):json_encode_exact(json_decode_exact($Y),128|64|256));$bj=($Fl&&is_identity_always($k));if($bj&&!$_POST["save"])$p=null;$Vd=(isset($_GET["select"])||$bj?array("orig"=>'original'):array())+adminer()->editFunctions($k);$b=" name='fields[$B]".($C?"[]":"")."'".($Ga?" autofocus":"");echo
driver()->unconvertFunction($k)." ";$R=$_GET["edit"]?:$_GET["select"];if($k["type"]=="enum")echo
h($Vd[""])."<td>".adminer()->editInput($R,$k,$b,$Y);else{$ke=(in_array($p,$Vd)||isset($Vd[$p]));$Ed=0;foreach($Vd
as$w=>$X){if($w===""||!$X)break;$Ed++;}echo(count($Vd)>1?"<select name='function[$B]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($Vd,$p===null||$ke?$p:"")."</select>":h(reset($Vd)))."<td".($Ed&&count($Vd)>1?on('input','skipOriginal',$Ed):"").">";$We=adminer()->editInput($R,$k,$b,$Y);if($We!="")echo$We;elseif(preg_match('~bool~',$k["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($k["type"]=="set")echo
enum_input("checkbox",$b,$k,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($k)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$B'>";elseif($qf)echo"<textarea$b cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($Uk=preg_match('~text|lob|memo~i',$k["type"]))||preg_match("~\n~",$Y)){if($Uk&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$L'";}echo"<textarea$b>".h($Y).'</textarea>';}else{$wl=driver()->types();$ul=$wl[$k["type"]];$za=preg_match('~\[]~',$k["full_type"]);if($za)$jg=0;elseif(preg_match('~date|time|year~',$k["type"])){$vi=($k["length"]==""&&JUSH=="pgsql"?6:$k["length"]);$Pd=(preg_match('~time~',$k["type"])&&preg_match('~^[1-9]\d*$~',$vi)?$vi+1:0);$jg=($ul?$ul+$Pd:0);}elseif(!preg_match('~int|vector~',$k["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$k["length"],$A))$jg=(preg_match("~binary~",$k["type"])?2:1)*$A[1]+($A[3]?1:0)+($A[2]&&!$k["unsigned"]?1:0);else$jg=($ul?$ul+($k["unsigned"]?0:1):0);echo"<input".((!$ke||$p==="")&&preg_match('~^'.int_type().'$~',$k["type"])&&!$za?" type='number'":"")." value='".h($Y)."'".($jg?" data-maxlength='$jg'":"").(preg_match('~char|binary~',$k["type"])&&$jg>20?" size='".($jg>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$k,$Y),(count($Vd)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$k){$t=bracket_escape($k["field"]);$p=idx($_POST["function"],$t);if($p=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?idf_escape($k["field"]):false);if($p=="NULL")return"NULL";if(is_blob($k)&&ini_bool("file_uploads")){$zd=get_file("fields-$t");if(!is_string($zd))return
false;return
driver()->quoteBinary($zd);}$Y=idx($_POST["fields"],$t);if($Y===null)return
false;if($k["type"]=="enum"||driver()->enumLength($k)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($k["auto_increment"]&&$Y=="")return
null;if($k["type"]=="set")$Y=implode(",",(array)$Y);if($p=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($k,$Y,$p);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$Fj="<ul>\n";foreach(table_status('',true)as$R=>$S){$B=adminer()->tableName($S);if(isset($S["Engine"])&&$B!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$I=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),array(),$S)),1));if(!$I||$I->fetch_row()){$Ai="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$B</a>";echo"$Fj<li>".($I?$Ai:"<p class='error'>$Ai: ".adminer()->error())."\n";$Fj="";}}}echo($Fj?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($Uk,$Tj=0){return
on('mouseover','helpMouseover',$Uk,$Tj).on('mouseout','helpMouseout');}function
on_help_value($Wi="",$aj=""){return
on('mouseover','helpValueMouseover',$Wi,$aj).on('mouseout','helpMouseout');}function
edit_form($R,array$l,$K,$Fl,$j='',$H='',$Xk=''){$Dk=adminer()->tableName(table_status1($R,true));page_header(($Fl?'Edit':'Insert'),$j,array("select"=>array($R,$Dk)),$Dk);adminer()->editRowPrint($R,$l,$K,$Fl,$H,$Xk);if($K===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$Mc=false;$jm=($Fl&&!isset($_GET["select"])?where_columns($l):array());$Fb=(count($jm)!=count($l));if(!$Fb)$jm=array();if(!$l)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Ga=!$_POST;foreach($l
as$B=>$k){echo"<tr".($jm[$B]?on('change','whereChange'):"")."><th>".adminer()->fieldName($k);$i=idx($_GET["set"],bracket_escape($B));if($i===null){$i=$k["default"];if($k["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$i,$Yi))$i=$Yi[1];if(JUSH=="sql"&&preg_match('~binary~',$k["type"]))$i=bin2hex($i);}$Y=($K!==null?($k["type"]=="set"&&is_array($K[$B])?implode(",",$K[$B]):(is_bool($K[$B])?+$K[$B]:$K[$B])):(!$Fl&&$k["auto_increment"]?"":(isset($_GET["select"])?false:$i)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$k);if(($Fl&&!isset($k["privileges"]["update"]))||$k["generated"])echo"<td class='function'><td>".select_value($Y,'',$k,null);else{$Mc=true;$p=($_POST["save"]?idx($_POST["function"],bracket_escape($B),""):($Fl&&preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Fl&&$Y==$k["default"]&&preg_match('~^[\w.]+\(~',$Y))$p="SQL";if(preg_match("~time~",$k["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$p="now";}if($k["type"]=="uuid"&&$Y=="uuid()"){$Y="";$p="uuid";}if($Ga!==false)$Ga=($k["auto_increment"]||$p=="now"||$p=="uuid"?null:true);input($k,$Y,$p,$Ga,$Fl);if($Ga)$Ga=false;}}if(!fields($R)&&driver()->primary!="")echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(array("null"=>isset($_GET["select"]))))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Mc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Fb){$qc=($jm&&($j!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($Fl?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$qc".($Fl?on('click','ajaxForm','Saving…'):"").">\n";}}echo($Fl?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(array("check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]));echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($fi,$x){return
str_repeat("$fi{0,65535}",$x/65535)."$fi{0,".($x%65535)."}";}function
shorten_utf8($Q,$x=80,$tk="",array$gi=array()){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$x).")($)?)u",$Q,$A))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$x).")($)?)",$Q,$A);$x=strlen(isset($A[2])?$A[1]:preg_replace('~\n[^\n]*\z~',"\n",$A[1]));return
highlight_matches($Q,$gi,$x).$tk.(isset($A[2])?"":"<i>…</i>");}function
highlight_matches($Q,array$gi,$x=null){if($x===null)$x=strlen($Q);$J="";$G=0;if($gi&&@preg_match_all("((?|".implode("|",$gi)."))su",$Q,$Wf,PREG_OFFSET_CAPTURE)){foreach($Wf[0]as$A){list($Uk,$jk)=$A;if($Uk!=""&&$jk<$x){$Sc=min($jk+strlen($Uk),$x);$J
.=h(substr($Q,$G,$jk-$G))."<mark>".h(substr($Q,$jk,$Sc-$jk))."</mark>";$G=$Sc;}}}return$J.h(substr($Q,$G,$x-$G));}function
icon($Ce,$B,$Be,$al,$b=""){return"<button ".($B?"type='submit' name='$B'":"draggable='true' tabindex='-1'")." title='".h($al)."' class='icon icon-$Ce".($B?"":" jsonly")."'$b><span>$Be</span></button>";}function
copy_icon(){$Ib='Copy';return"<a href='' class='jsonly icon-copy' title='$Ib'><span>$Ib</span></a>";}if(isset($_GET["file"])){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('*c(<]iDp;+<8]XG-X#ETBP{IAOo`HAD0Z,$t2FfTr3g#Vd(TV$)k.Y.Mza.nf=/#EsSP{RNA{M`c@a0tg&9-nc;72Wy&XvI>dmHD*qF#w3sU4N@=|&JHw3xBmwvnkq<ydKh3e?rW3UW8c6iorbru~-xFWKSUq)z05WLa*7(Z6q_k
>fA~ZVU{Y8%+yFYE(^i;T$03;v)}SxAXNo<2/|a&hF.tk8g=mh`&V[Y+Jg@kV{Sqq*QTq`Pdg~jopx-(Vm;9h^8LO"b79T*xOZYLrw`W*by*"9E8;y3*Q"%:"cM#I?Nm)g"HjH]t<XaA1o(r3Jbt:+q>5y
hEWT0t;QeU+eKbfA)dYUoNbi2I$`BJhLgr:&V-v0b"$EN89Id8>
n)ARt5TpU8NE}!G^/#!6z%BI)xMGGS)M8f+*2HyhUQ+_oMQF2EQi"&/g>W2yT(IE96sGrq+Z`yDc96$8=2UJ+S`$0tt,suyEps:Z-C1+P,@IL[3H3poVy;_1=sg><#+v{"+lM&vrPd{fIErRru1$V5gIRRet>oSi83]?]VZp0C-`G"*EJ`bK6rcS#pSTs^selWAVk+["M;ix#il8A]a52R7R@rGN9BM=C%<x4>Vi4<y<#Xf`)Q382VesHPT84b5.c%I!6vZ+qZK4Yc+3jfA"DS%8zxXru
,Efd5hE+Q&`koWtBokKfC*o<l)_3Mcd!D9H1!M&*SqAXaT]@9yHV!&Lv?owU9lu%,nFFj0q@wfQ!,ovAoG&y+Y$0`?^M,(Z"WKChD*!<qxg5_tzOJ?Y`dNzA:j~qOS7C"AzCc"PX"-xjlFJM5Su[vo/JKyP..nNNxa`-54e<&OCM3<0N~]U"h*kA$HI;N#fiySXADi9muuVRtrYcEs~-YZy"CTBm/dk&lEz2G`mx:X@ii%=3AL"4lv{tN[X)VsoN?:`MMRKfZPX=&q/(0[F%lKJHrT-53p.r{l<1ua
.%*Q/uEk]Z.lo@dy?Rk6Z9y%sk/zDlAqC?s=f2cHx0hd6gEo<))#>1vfZ;xf+!QU<9ls1m4<HR.4.nSkVahA3iWCMDnnDrf{;"&Va_kK-d1_]_<c<w3a*<K]m_OKTH-n0~:}fI>btB8ZG,Vwo*urg6f3OP&-`d*M0ktSkc%{1Nxi)_v#(

V;_RNE>UWBOhds*U0e8t{ZnGaYDJ3>
"e`mW+vNx3)]lct,8m^Mbg9g;pF
6X]}*;u.Gjf*L0ye9GxZfsQ/%)ovdoEhI?)=GvdLMIh:nYyv2+Br9"i|Qc))DM)0ORVRd,ShD#kZk%oa1|y]G/84-$z"q8gn&N-<MjLPUAYyl{o)NKedIl:1_{l}+-Cno1O}"@Er=&<7_XE82CJw$yZnti$xV[8ndG-4@76_KYxqE(Gj:3_i8h_*
n0f:7ki(??jX^=bX(
Nqcn/403Q`G[8()ZxS{"MD/D"RWvrmtX{3uHa]S`8=PqZ"G,0)[G=Kj.n
]r:G+CeI`YBi_58@k.^<(7;<Q#YqF/wN@lFh?8tDtk0HL2gZP_kl+M9ipj>;^xMkz]OO[k3.Z,K_I=f:C61UU
RgNd%Hb:p:,^}u{nEcO6_Nf;-Z/$Pj}_9Yk
9qWn_hqI%K}W(FuK<"T_KQi.Jp]KkYyY|GRy>_Hq.42Vwc.t(4h1;`#-_*6H!D*<Eedoly,F
y"jys2p}"0R$;wx=ls6e,sh<db
uBY9(o*Fdg3Ke0vOUD/)_?!K?4I!#^+<h3J#"&y)-Ul3H%HqQ1]Wj&NJTS2%*VU2EDT+?,]NgNVE<^`aKZ$Rs3P0,qzR.v)4GILUC<|Ud:p[2Q7i:Xl.5%uA3&=La!];v8G_>_B]wfZoU/!lB;cNp_`DJqA[3JubAy:.MMDd>+^a-`-SnxP#EbXIrFth5YEd$/:a_Pr+e$%DC1?<P!5XQ]lb?#??)St=Ztc__DzH{2kDpc_eQH=eNqC-xh[53f$pq<7N2Jn?jLw?/@g=nfRGXV@/~$&Iz%xt.0!*s)naS(fkLwr,)#5.Pixx?K$FstcJFT->afBGQSBu94W`%*Fr4G?lZBU[M0ekQ*M6{E0,D-WLLkL(7bxoyNCI{0_)u(76[ZgDvbeBxvQ*pG3@BT`,NbVey"-Hq-++QiE&v2TW2D~;dX.I7s}Q)_M2d%<2#_mwza(g}ptHZY*%2t9N-G#pibhL0fJ6YmkqW.88^?
On;%1sB%>6YZ//nv81N|?JS=<bNnkVE?<?R/aIeo7Ju9oxbFMl#$+GVX*hpdVvYe5^Aops?Q5ib2WBE+D*T8s#+fk15|m.FEH<2]$r$bBB6$sZu~t/fs^ciBGt0_]3N,D"M{P6iRA[H?t!iFC+p_fa&3NT2&u(R_b}M/5haEWeF@t+n$MHjAY"D~>jy?r|D,"w11?7:Df-I@"MCWry3NxD<G/lT_kUuz4dSAPJ:DL=+nfPkyx6ptdo!BBB0s:?!UV,g<egPzl^Z@40U~n1(7(H8%%H-S
Csxg;]5f>XPi5JE(oV!t"%KpQ?0!?!u+jev]~!T,fN}V(5+m20qhqIiRV,I
HGRt5A.
X#G_})=,Pjv&`Aa`4g(?:l{yPit95/V?`:%,C]2
a]gxe[=F.s3
aU9Pd-$0%tlHg
?sR>~Br$^80iPo2Z$Z=_qJaBu?_^Qy}ChV61:nhk{bm,}7hk,)XDJXv;L/fp"/Lx=cXV/%KkY@sR1gPcJR-KTR0v{P(M76[xTMr1rxcK[cp]NphX7Y~FQHsb}l7l<U55jMp,:cD)Zw95rWHu!H9?2v/?Lu:aVW@I&5*/Jr.6+r
j]xaW<URw9vVx078rA//Y~H1Bm!Pyb*yN0l/4bud;<G0gn*yNNGm(EP*xFJ(5!;S/VDKMBL!uU#wDKvOy.,DxRMqLOXkl7v!#$M.rsHNvZBuR_?J(jhkKsSr`:*iTO^F9CI?6m[gq2ig<5F=!)@Ug}phXQEGGaVe0l8rfcn.SMg)<[rDxKPW%0Hs80yN^>w}O(O;e3Kgw^y.Ud+MpQl_c(2F@-L<XNfSTrL"A,a&O]#(_mC!0q(-`m9uX/.7PW9)&u*~[GuO<jHHh0<P27wI2Aqm]kfqI)KKb_IBE%Y^9kD"]9NO$^<drZ&R#4_fCW8^s9U,uC/Q>+L%Q>k&q1ULx^pctS-T_!%z,Xx5z)t=LiJ<0JIKN}yF+f`SZRBzA39JF`64g"M3#|u*P
$54:q+;xZ@3|*.!]Rrl!QukvN`,=ntjA^8t}tnnDJVBbV0IE=Id>bbn!d!nkq,
MpzBIrIhoo(swnSZg)stY)"l6KR1`bbgRrr_#cp>GHIISPwA.Q)dp%1
~([OkfpfSImq!0>Ph$P%/g
od={Z5;Ob#A3Y~GT&&wElNH"BC.f
&,O@<T7OU@>wlQt:Re78?]/3e-}4XAHR=ea/~;|c/(3&yxeHLKAr=h+H|sclPSCUz1~-!ZOD|oQw</L<
7"J/G)1I
_Wu]5(?:uHtSqJO6-<;0Ctkx.ufZ5NMtw-dY2)#k+xCb-:(Me=1h+Qi21Inqty>RV
YbAKfX7rL4yL;1jlbPb$*8~@=YBQykY,LIXcW#>E]vR-=$
tmyBFQ3"9%ai:W@1<@+-6+;"L]_aD;
C[<w]veXu0z+"[YRq"R$9[(7;m2TH$?;ZbPuV6+hU!b<?0e;8gRJ@dcC^,r8tljexN#K^0}:WDFcfon7#P|!@rJn#BE!Zw&qg08AIw#l9uVpEgW@}hlY`BO!L6?l-bCt$4284Ao@r$4klc3`!y3
F%:HS$,7;B2+rGmC=UcW[&<hh%hBPe%l^mN(>rdbT0e+lu1f=SKEquxI-hg#5rTKn.=*:^;a<ni8.qg9E@"UG
_KGb<@-+6C)cPwS<QaUh(bJF9TaWOF%ZE6ei83sUyt[auyW
%x5JBoBh5:PB5sG#Zn5;mh?@HH**FCNQ
F!+m?p/qpS/wTIq6<J5EChnxwq-vUr<Ng;yG%(#,J.F?AI]X&ImB>/t)@+V@S&ZYbA0UT{2kT_VCZM?7C8GK#Gp]=mWdSy4.*zJUqFVv%?lK$1EK&yB-O)wi%yKZ"%:5ZVg>3[9/F745SQ?3.10DI`t0Ip-zN"kA#f%64{ybdLZ&MAf*ZbQ<fqP6:SWg3^K{ih$gLIT{y@"
v5<(7hjguju)hrhotTyAx3n%_%nxOg@N`{euSPt/e`4L!gq0];4h9r`MhuR"3~XEH^Jq*@<i2_rgX%^?a%W2=xM;r+!h`:"&wfZ-d)$o$8X/1`KtUaZ9p"m{(M<6cplN2O,]Y4cUG*^PZ$nS7:`!I8RA2zk9t4<M$QurHC4v-pges|H1/6gi8;w/Mwd.s8b1eZf=2=1O@-$Q/ZEv-kun+zX]MfJ9GpF8Z1d-/nIQl#Yw&:@I#rj?cRdNBz_&M-

I$#,IpFTje_drQ*0+3DbuW!t@bJ#s_/93e
u@1>G>_.8rYPaNP,;+G2OC;Z"(Y"8R+=<+U](g3i;G$M3y2j}qfwi3"FgQC(<@(Ex3]u0eqmJlGIlHt,%r@HJ7|:2nQ;k?UAKv]LQ_f2G1,&)Mts#<90u"YMcMTK=');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('$Osc2b7V>fj0U7tCw8TNfbT`5e<0!4x49EeL1n%e,E<_WZ?>@wWMzpUD:UubAB^u7,;ZJ.!U!ODBJ$p,I?B8.F[0L@ZP|U.08;>OMB~NvqOKctbeXn{?PG0IyC%OS?)r=jqOH2M?hb}k!R/`..+tcti.<c<RYfU_-K2A
vCxFn<6{L^r&8pu@R.[?Y;Q%DJhl3UE#:f_^Gy.{7^;|fNj6ajaX/-y3:kA
^e)ZU{TubDn#S5p^*>QuANxT;&o~2R.180pfCDp{Af#.9i9,-(*Y&"#eJu7R(uH>BnL38{cb_!.Xt`+GTj["w]mNugo|W%7muWs%-r2M[*vyw`Oi6fN"fuoOa;Cx<jivJ:1;k/yuSo;e7`7Ib+sTqL`5Z!<_Msdu^T1o?NxnfVqUcZ(31mO1mW&?l3hyqkSs<KDz)JOYNX.qcbw.a-hF9!u8),&16yAnEfk#`",*pieSR"^
:QnVS6*:q5["XTfNZdqS`4.wq&WqR$ff;}))8p/))?(NoZ>?`%8E(L)Qbr/mvjYq3odVYIZmt];gErx>%}pw8jHUEMo^U6FzrG6!>r%.Fl,i8GQ7.)96y=aANQAV
I&mM@=8CtkDDN#eQ><"PT&0&"
MqJq.V[OT-W:;AFl}kzYka3jtFJR2
a(@dy4=2dZ`=+P>(`R1E_t2_SsXn^QTobmely7V_<d>rQWl@v,kyf0vur:xH._r2kS"fa0O7^l]f4?(
>1U6U+VK-57o7x8oQWpsfb%dt4*EQPSa_B
R5pEdml@-Xb&>I"-^*scQ1=E/ctCOk5vsYn%D90P*?o@rb?
W?IHCMCI&bJI9sK8]T5bt76}rq_*)G[9K2;FAd)taZI^BtuBX+sK60V2H]NeE|*vCruwDpOK^kn6m6f6&1s^Ucs*]9vP^6+%wutWM/66l4Fj)WO0?_+RGg2|jW.*v?W?ZXyua"T+&(XWj;>M!:kHFApzq<`|]GHW(vkK32!q%[A`AHO1`}O*ZI?@w}[)');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string(',]bcjnsZ323o!;f/.e+fx8n;[f71r0pc]giW`oV!D5mSIO;.F)#>?N&/uKRvUtUkWix^XgjX;sS9{(3
eB-
eJD[z_=IA3FpiUH:=MZ3k@qANy
,pQ[T_3Z)Wvk_gT[Jd2khOG[lbtSJi%Xc~x,$3rQ`e7zs`9hczx~5/)NGI&}cu=mR0U@T!doS{^#,i$18TPKy|Ut2;;<LDrZ*rx?t3j]uV0wBhgRMVF+5rB"o&w`p]@K$P3@jPh+.?b}q$QhMV-w*DRoP)N{L2JTta*c.*>bWJuFy>FjBaYa^O=]J40HjoD4_^T?rw%#V3SaEJ,@haT<%p+hZz%2WiK*x/Ujaht!%qA^VCZ%24?IYc7SxrR/#P0:US@|Fn%2c_mX-0TL`9<sF_kip3+A]LVm4i_B)GKtx"@.D*hh<7$~]-CYZwFt@Oa4x]vbQ|`u+7g+B6IsXQ3H9^K]$9EnvPx3cO;lKlZbjaFHFIy,ncrDLW[Tn<w@a^MaXrb^LUyM7x^;r12)nqu8iDuIM(&I)|_X"Ta}5J+vE8Uf0-m`=4yNV{@ctgiirE-<
?TDx$]G!:iP](g0Z*9Tox-iN*VRy5Z>W|@*CdPh,qgPeFeMj{gObW[)Bvq#<xTo_E9Ma8.QeBgQwGwbq:@%v*h-K?(EG6roY+s6+fSd4qJe8ihH3!oX$R%<=8sjBnS#^E#;AmlT"iBvkm.HK$!dmmiw(<TJiS*Fu)T`tfj)T
@N%}vxH6(]h%)|`r.SEbR&c(LU&vpH"+5`VMV)-ZbIT#<fD?n>8n2J%va!Mv$m.^hRe%-X-j8i^ltxz"+|(/%&CpSH2U#0L{*B3d4
_yY.,rLR)Vu[*cHPfLnsT%2)YL*l"Qrl)l]e?}*q</a6L:)^n}&4kh
$76?e_vQYFJn_$lIesca`%Ewhj%@RVQhWxqp+.jV5;r-HtRBw:=<Sk)v"OJJtc%O%sO:
ZZ&+Q1[:U&fw2;M5@lX{[^,8rZPTbc9_#(EBnTtG;ck;)R4J&Zs.JeIACX?%N(wf_FTvYw0/xQqtaLx0CKViZm]I1+UWX/z$S9P&84YP;E=~j^9RosV!7zfSE#83?W&67RHN5rXJPDCS2Gyv;d!O@S#*/Uo2_|#Mu=br..K!3_K;71(Uy~vxanxZS)w%e;THdO(#U)!#!GJB^&t[rKBocXn!w<n)YgNp!K#5tt=Q,r#DG:?&t8?)!?"%DU_7IMM7ZkymqVXhSO*|CkdoTA3Uncu[!&/UNEkv$d-~Q*Y<41A./WpZv1ekD>a6K,o&o{FA<,hX="<85cJg1"q`ACg$xfbTN^G(18VLq|V^0zEub|d9kf63V>g9m>$"Zen)a{$+a[j><8U7*GC%x99n>"T[6}?,bk*,q-nZ9yH=^Sqtfqs+*[fgu)b>FMLo[`a<HAX%w/b<L.xjMB]jfV,Ar6qJ-IvMed8h$K;tjyg2&/*Iq[?l-Gu<2MghB>KoD{5rWJrFV]bxI9H:=zbb7Td4o7a.Ga2ZLG`$+KgEw@fGI~tV,k-Nd=YwM{)h61ry(c(L/g5$-cWtXw=Q4,CNs`:|aRsFGM+*-V_`%rI/YL@:?n(>E
/n2exjX%P[Dq]yI,HU[R3mauTku
0AA+R`a:Co6f388ML#DT)"c(m-FoQw:%p0
PG&r"LiSMarC.JWx4eaW//o9%OUE[0S6z&C/p4(Bg2oXd8!##.{#w]{VW@hQ7fU7o:tbCrEEJbz:6(cZrU0Xda`4xVx!+Sr@c2&GQ]F):Yb?,oH$K=2j2W%07YqKDDoSheV3Kw.3@aW+w*B#4Z36q<!qp1PyQkpGS9Q<-V(^VFyG;w.H_s5LVHIY&/6]::OcSEM!@>EdFKirB$BjXBihOE=7tX"fY"#JZ?@Rym;[F@lmHfkJOU*h/!MXOdxLq`d<FUof^^.We>/tP!
oZuZPZ8^WdOIo#ty^2XU`|0/.trk[&21Vcp<:iH=NZ0ja;jtf&gI8o]iT]vsA^G)4V5//@E%tfMjh6j$+8wQs:Pc&6-_w^U&]In#`fvwt6#4:&L0:}G^TL=USoe%m{FH;,kcYR@`?q:)8pQ7H?c^Ua;GAk9K%p"fbcHuJ?<=6AT2(R*xu?bcvJKK1j@|g0`v(s$PNT=Z#L,%Q_C/a)6W+41|JQs:,1-J=t%2n.5cAt6l1AiKxX5xDG-iTXMpZBT=_X:=C4WdkI&-`(.GobZFC9!yC%C%/Q(V*k-!uQ?f5>Nt#36)EAcL2%Sn0e]E^+Gd]E4%Ci@ycI9C7j"@kRwz%J5W;$ioW;Co2:4g`/lcCnj@1G*py6%(m?I
>n6Y!F]B-&^2@,;/Y(Rnu%
]vb+wc+CKNq3w+w0D(]GoV^?fNN?,[+?;,"XGP<!b!&aS!S>`Ef:XW`PJXr>)1K&i;)lNuL!uR7/(<lxsGy137GOLb]Fnb{Q0;?J!6cGsEYr"Y?Ub0,cG9p8(2YP]j]KZ&QBDC/"2WjK>"/S&o8b2Fb6wZ]L)+<w7iPj#)yu#L"[sr`cp/__ynn$Cp^DHqvP(DXD^N?;~EeOS.aI-,bRWxnj54PhTJ)tt)hTKpj85%TlGg/JD"Br_)C3*Niig;Pp+XAIn(ilG0HITlWiG-!Giqi#F0nRZt&"Cm:`qUNeim?P-gE#PI/
a"/;T^g$lh(6(/gH;^mRQ/GJh
?ypWph-5Q:YV0biLHp}BJA[;=f#6io7b2@)3<Q8:h[ha$a3u%YhD2pVtC+)Sxldu`/!Dco{+*5mmGR^S[<=QShX]M[{NRMC$p3YYLT:T7)%uAc/"dRz"d%df`,&kOKUrG"u%=Q8G4_+YgWmeiBeyG<RMbn@!=7A.:b<i.=k.4WVYNE&4C"l.8GqVU"^Y]!G=d7hP]C69ADn
Wvsg{r$C!a"K(/onhFLaJ=$R.Omru/!cNR{Ooj)>;Cw:](`AVamlBKN@J8hW@15F-MR>V<FN2r^I9#6t}@=#@acn%tdDD"J
jpU3@l{VIqpQo0V`%op*XE&Lk,D0D

xn"@?(c%ua3"y(U2U=!R[OD9?CRH.z?t>VQgh~gFpv#DLi
rg]W*rmj~c0w(QWr]HbdnZmi(6!ICUX5JR~5cMVF[m#Qtrp5BW#D6_#CyQe+;$$JaCk<"0#RWu?mJ2elz9{U%lCxJ6F"_DDRtG:Jnx}[i,fyXsGHmF8UVDO`@S)o`gCQMbROQmJl=C>a~.lq1gJm;O(U/!3L68.#0p4fp$@"cnV^$=!/?+HnGA0M)H.ssyic]=86rn.GSyW_`->;8v_xbD>+FQ+o|L8"<8-(ZuuE9PW)yN{)X<EngGTo*0.4"3RC*.VZjOz!b=euS;fZE/Uetny/pO2!2%KpoP".NQ"NA`s?Kf|&5CgUEOd=$AZj_B~6KCL<?vOt(+_k9VS;CFPjo`)3~l~N3L0gGDe+kMp$.,mUzFUNp3}u0F!DH+X`WMNZ_Tz*-t<qT.KE|IuL`q+3Cp_lS%3)a89,c,CT{e@6*nB/zH;ZHCa:(!1S%OtQ&R^f{!
^QLkh"pVN&/P1b1>*q7m1}PW4&s3U+]#uBPF@$;OO3T#
sy8Asm^:7
.&2_JP:Zk0,-qW"dl:9lo/(+iR)/|6$-%O1L-_G.71L_1jmYO_G$M<Uk16H8X
=]T<Fo]>#cRYqT{Yr,0,E_L)0Y/QcWT1;!z[;l[6JU5C2MY]wI}iWg^UuP-I@&T10s+$S)aK_0m,[*`;2@C!iZ|MbG!qE@xVbEw,>[8dN^_ARv1jovPT^D6KryM]+`&3:;Rbf"i"4QJV+jD_>b
e+TEhH4xm{QetCK4)U"Wy#P
CF2dl*3MdJ(Y56KS(+A.KD>Hr!S:pYGV8P=q+|FytW]ODoEIY>#Ut6(8d}x6.0%7vdw)PA1XK&P]X">6vF3OG];77oJPUeDZ(^$LaYE/N-.[rgAc-ONe4X]erTeM_0I-R*Mq$DeNl.IQ^bW!nj")IX
4h;YvZ/)vK|4lc1d~Sm<*jR`tl=$0YId.ql+BvBm[^"&u^4XqY%a7!;OWLtD6t
<)3NxSodAe*,C+Jo:+E^@
p:U]-M524?An6KVr<@&{nisr]f4`BRkwr>X
9#6x@&AT07WL-Vfq3ICz)xH3?C,Z8a[.j-Fa@6e2B:9II#kYW+yFy_`4iwrIJ#c>2)A.6sz(47V[I|VWm-H{08eRMvh|Ue&>,k]pMVaN%}IUkC=w0Dn:Gh2j<CB<rnp-XkyNN6*H-k
7Y7.#Q6w5;0K(D;)%SVu[(<("XR=33Er56;X.Vb&9>ffFUlVix_PMv"W!i,V+7O3Lj5%Wr}mX7yE{7m-=:Zlr/|L-%_)IPL%+_&_*)W3p&0cY5Hr%ii0Yg!qrD[>%%zG9-C$ah<?yhwhJ_
4~<G;4wbi#
_?s*bjy*<qq$,$k8WU(82b8U0J+x58D`?X0f:XuGd11."XVNnW:5_u&9`^8wv;Iw_#Rjns
^kTfy?neqpfC&/jGD:J="unu"I;;ADlJX4&_a}V&"x%<#[+0shKLl(f/m]WRKl)
-"gS&
[Tw$DHe!(zWMA8#{umF4
a^F@b
#*Qg34SRcmn^];K,~_/[IK+K^qxFaN]y|@{s=6N+):#KJ<5r0jR.tq{c$0Y$Og^v=rU.V8OuwVheIa:hFu:*Cq
<|/n"h,U,-s.J]5#?;pI-UR%$PFF
e/Oo=*_m(EOAt)f.d#5,XDG(J0I*{LIP5UE8.;V]Yeoag4ja:s1+JEzFj+
k^g)U|EAT[BY3a>|+4Tv[nBD9]60W_?*,a+7"f0(p/>O>n_E0DYVMrMjQS

AXlsf(ihDZ3nm]kUyHmug|B4<22u_PJGZ]Wl)jd0x7&xZuMncLL|?n5kCeI:$Ga}jXL-PP"wW.mDnb3Rj2m{=Q;TouoX(e)t/dgh&l5S9bvQSU&[T`Kk3]`6X"DFCd#k+{A@@]57d^LUSIt"E)A2L^I@"AZ;KJUVTmQk5hpTrKFEs0h?w}2HV8C<]hJMG(Oj&cTNkPqcBK<1+=h-vxsKmQjU-dE8@lEr:5O2Lrpdvdb,fM_Jan-@y+FtWF)WH>!D43b>9Ukf:WA5OR"JO{A(M(AdbR6_lSe8;d@rUuW9VgNybLac57af@mT!Mc<MBvYS],c_
|!F?|k,A#k:Yve$dxkHh))p[M!}+P9lLF,[.*1E*1w:pK.{Uxb~.
5aBOr66GW]$7k>hyrL-!
]o;9[K
"|KK7/x0-#)&;IX6SkmsQb<CX^E(Ma^0K/7oo"6ZYF8Boa8j_rKQ$6jJs3:~/Q%]-Ks#u6o@0^NnD
Vq1va&Zx^KtE-Cc5%&+V*OgReS50z#hzDJEbiL&-b#f}&u5w.MmbvrWe.ceO)WXoQ6%NEAR`NUVXmwC!Fg>xTBYKUd>dl`N?e}d.^NWGpvS/6RNzjrDQGDu5fh="-y&mWk`($&C~WK6U8P[;1mo3KI#I]eV0Y}U(j]*dRz)KZVb0qxDbU^d>t%RuURf:YSWQ(=#@GJ3ukLd1@@xQ9v*GcWSe(55R,x8/Dut5<XI@1g#|gd:STBi3ijXD-<u91iX{/v"{Vxuhs.)-7;fB,E?GE1;d]?&b0]^":*y1/!Md^,1h3~(HnZdLev1jv/KTPkdX-yFp-H3Z2r$X;yBbes%VMJ-O%.;r?"R]->p;>Q8~)o0SQk%=m>_ft4
FQ)Zll{4"8Rc[FYo`6k:$`?u<II$kjeM4T6Upowjv!1^lGd4<;Ndz6~A^;.&`XLFyrfHrPio@u37HqG<
;SX8-8rh#FR>Jk)=3N?`8^:dtICNT7XNVXc@$mg0C`gH[#Zd(M`v*JX6ZyZ+

]|<&^""{+rO,Nm0uCs?7fio02`5740u9Fn.($I2KX*Zl=lHftx4&ISy8(&=DEae43`Y_K!ZA"i5Kv|*_DD[FU=,C3fF*l?Cr/mXRWZ[skp*exF9v1%yoAS@(1l-V8$@JG/07@xb;yH?+H%,Der^jj04a=i?;^L/J@cVb`Z`6V1/*?lv9Js>3Hxa0mYOq-OUK1d1|)/5I#oL!p1Yk>*-PSZdn_Yhw*$24/_kQMX6^U$TgrL%ReDVf[/v$"vq}Hu
:0/%Jt_tsmpo$"7wp[eKL!<^WOfhmYb;NllQ9T9J%vcRRBb0D$,WVQdV5J$d,-6BBbp<7%o:
>(aoK*YZ3P/RQT<|)q.wrCP$cu#Ip!uQl,fxCRYplp2Y]@F,;]*Ce5<yLhT?Kh)nJGPh-TRp)<vSZ]JJZW`1*e)r?WoQHpEHh"#xAE%pq5@@,QpXNGscE^`UQ46UJw+Ti=J=wI_QVVJ<Q|in5gP$_ySg4n7CxXKr2D5o=:(co>k]OEH2.^W6_(GBS:YtT*xo@g<MaogITytp
i+CA>]}XZEL4]wP)?fEtVw!fDR=,(.Z=hazd;e#H+<=Q>YOX@kJs6*1*tudVO^+2a@@4!ZRq6CsqP2}GEBKQ`OSWUCLLdvyk3k.,L_uei^@7NZ*9lUa@+L4).2n(qx$QUWi6PA[X70#H&)PN~pkDbU<3JN,$nFfDqkehqX,LH1kyx*m-emgH<4,(]7!C(`wBb"`gxsWmL`8.1tBJqX`eCR<c24}7#U`%NFoZppTk1$6Of#~%m>q:@vJ%|&8>e_~$AnjlgCdW3VEa]A*wgiOv#W|+r=3`"s*XW+3?rSIPiZ=.%)LRH>iQgL2]sez.Z)((nF88!Y^AN#W#5^@lXDVhot|<6">)|*aLa`dFl*fY(ah5g+8R!^mqyF9*Hi`Z)iI9^m0HBw;y]*63?Z|%nm*>l2Nl-#3
-:x]@=@Xn-Y63EQOBn1[If
$T)tTGsc.vHw:,>*WNMdv*4(o1UwxRaRT"w#3YB;XQF$.`LY]:G([XDFVgZzQgb37ccX8WP5yj>^vcUv<7p-mmHLaYvUvAoFM>k=c[#u]up[p(IN>0a(#(
N:KG6keYjdaj5)4G`"p+SC[K:n&%0.5:bir"++"WW:D0uhDm~lX*Si$YsV"!%?Oe{<Ul$0L5?({_^B&^:Ru?b*oT%=Y*$%R4~@ylBiK?DRo9ZhYMeca^/M&*P(}d}6l5{/i3.nP0}<Qm870XU&Z/o8:pIm>.P^jF/C#,?jPiTu./#yQSf0niY1.`+S39H!4`R"whFE
7z8[?%h]yOR#Rl8s*o&v<pEk8V`GC:^Om[c;Q4!gKX@~:ny7v$%^^h@;eABlu}6YwO:htBw;6jP/=hb#=}=1T0:a8B]prgso1Nd)/2?YKCR2nPV?@-#y^Tr:Mh&
Af4D[@NQmdSjv)k=b1?Iu2JZG%?r1ILY)k4oE9v4AE[/0TeSJe#EAz/36WP$?TXYnkTx]>4&4M(t7V3jY9ikKtVYO."Pm:N.rW1sJfCn4Il(X2VMw(KiRzI._I@SC*d
5Z4T$#B{qGHF5xOTFgoopfBhlVD^duv7hN/^UJ4;iU^gO!En,VvB`=$2cfH}9
.)Rc@u%
E~.|9U#Oq^e8g
o->HMi#n;hW"IvK$.O^7=m"TdVJ0.~SVgE)B5{Z9o[?)tp(h4#!G)yWZ?_M9-[w80"R&bCwa&x)f6_@N#DRruD^lG.!Hl+3.nD84OrYQMj_GmpPu7*1BN5O(%$]GIl1($Ff6]I;&YO>v_TJ=bW#M
DJIUN`s)f4haDO20qv~k<ZxqNX"FeGlYxW$uxUh]Thq8=PFf=bW8<2b6<Q
.;2gWgLdut,!&#trm_f/4=FZ>l`KpK
W1cr}y$[Q
0o8Fq3l&<+h&hm|S6>>fKTVB>!i"}]>.C#D0K52:.nKvThYM/*MjLASiGa5PgmQ@!4#J9[>7i[G6S[7Hf_P*d8
C.]T,,&~0/#ebPNkMg+C[&LU[R9Ru44jr;V|(&RS
N23)sQ4_FE{x<Be<*NW[dG|exK(eO`nfXyCdFMh[:m~94"De~-;+`l5s3w.W82#pt`*A@&%]
QSk5fcMr[A=vUqBV/3!,j[kTZG+55V*p$`ATD?BnBu8zJjEw^_k9kzeBEyAYm]`^ZHj@DL/@4r7wxT>O-H7o]/w&P94&q8lv&KNU
wy;AK.|<+p8F8V&J4#*O%-e1{S}xFfEIEY,_aUmepFrKb/*v&_v^qi~.*=Jvt]N_612+a4uLw6[;uV.V)"E?fVKj@X(o!b)ui)ZIP1q^g]]0l6xG4T])Qm-UI^/E@9bFx"5*EG01O0c>_Ac/?T"v+G;uvd`F%?xoh4fyVK*w|yBMq`sA
K*$N+H8`?L?=o8"E.BQ~xUGZ3O3[=Ty"N^10eE#?Fp&%P9wM_BN)N-RfhD>u82BpUrE3Re,|K=)!#qtmbU:O7W;4B9%hn_"5v^^1UR1|,UcDw]2H)Jl##L[/EG(7QF@B3%1O^!lf[|yh!/&}Etd,5QLd/0fN[m8Iu5=Ai_B9cI#nALItxOg#H<"3-Qm_uZ>@iFj<N>iWWblKq>b&FwX.#I:um!dJY`J>BM-^HNwZMgWF8e-jr,y
K%Kt,amVj#t{OX.X=q`RCS!S]vLs6uV}`v-nX"eS-::<2R[xZ?npZ!&&9xtqSB%E^oWZb}5GZS_!LT0qL-c*e<Aosy
&;w^-O@o]&(skw^]4]2WiXX/#[7g$!M1H$l%)*->;O"3EB..zi8p@VEWKKWD-4%/zz%#L*o5G^F7.sebw+Ert]jcW-e`Z"HNQ%@2+=W)fDMk9aXKx*]QfQI^HdDu;ntGJ_a(.`3eY`Fo]H*Ll&CxX"CFfK%1rmC#d7=<UL;8v3Lh34i#*vl!md+G`@TkI5uf}PS146y-w?;m~9Q5+T}-oe~mXGwnrQk.a]PT~3+5Z_DDYaDeF,46.&MjE?(TN/(<fRW3VPLwg,P
_Fe&d4hTe)BI+`QRHg^t,hJ7|#,[=0w^
d_DF:fo=n+%t!X$z%`V+IcQ~sL([
=FF0:3R=HapXCg3bk^0#DC2Ry5u&1bz#"2gqq?hT
e|:pV+Wt%NCPpDx^"L0Hg8Kdub5$U2&#WL.OU=lkT03i&.bWh4AH+8esD*3H:l.W3*"lPBfRj_(WU6fPJ?
HpriMvg-/#IYa%.>w[LQ&R>JN(a-$jQLUT.+3iikoYDgVxe(z#XIt1yvnxdW>DLMHc:Y>RlPsbSV;kW*m-Ggv-KWfphqdFlEP+~9{n%z"Fz5:w&ylyo2WO4-0BX/Vt[/054!XO4`|=IhCO^-$X6<0y`Q(%Mv%R)W5O:.89a=W47-jVJKpAuL=59OfTcR&4HS>mvu#crlW9TOi;zj6A|<5j%*RAcO)ojgD_1Uj%(DpNfrc8;[oci.,1Cr)(lk"J)_Z2M$FS?b?s[b/?B,@3
if@hL;Hw?jM$;YD
LH;ps~Lz8;M{QyXrX{,c3(U}JrTE#1czA8ws
,.j]zszAsF7i1z"=}!_:A,*wAw}k5+NGXV|fN7>tQZe!RmX1`C.<xy`F[:rBL!`ZQ.i_?4I4l=)IH9a_Cbx5PC{y{ePAJgq/<BOrf0@uk+<xjywQv,qI4:r.uN`0n8^gyn342;P+KB@=e$lo`4pN<4J]0z#*}2;l]8MPrq8j{:(R3a<&xWA64DH@yDl,(ar:|N=-*5T>[7r=-
cgV<J

;1=@/l>)O+qG?iK$Fh5Fqj2.@IvS$$sz$W=&&C/;;I/R;.Cxn=/n`v2#Fe.,<2!P4M-W=s)yHq?GvbX?s$jRuwh`wS
q)?4,Gu
WIplfqzT_.INJ+RYlV6yY/4x:X)*e2NSV7KZ|d
exPg$Mo9$V%(gesw@u.oqhA9bBHdrN":OtmYiO(y$mwyNkc(:qfeY&]Zn.1p&k2dt/AbJ+>eNZp6^}vc`pl&`IXg-Jl.#QF!?H,uRGYE@`JH;l`^<yDvXHPRWA/,/H?-Dq*!^V.^RKH.iyYC[+9//QPQf3#4"lVd!>Xg?FBX#89C=cA;&[8PODUg5HEqIx_
J+_~K$DKo2YnRk-YRoQi-:8UdF?5(dssLbcPkYA~$c3KHf7C-~Lk=cr*@]mA&`+{m|`IMf.Sx842"=!j;D9m@Z*yqT!>sIDo2r,{K>8XF~@?`jkm=2."L6S<9p[sUrobaw3*f?mxLDr^rRvYjbaU&G/.e,gDTl-yI7R{/I=rD=S{F`/2hW3(m!ie+I0uEX;UH,aIM<
+o4(<Q_baVKMI^F&;[pPpI}XEZ-2THzu;h7Xl&*$E)((1p{5H0!XWaP2&MvASJy;>dd6SK}8b2,sI!Kh#@#CXLH]^VaUhE8R][uv4a!`_BiH-Ws+Bmr8w.qqYN?
v=j4XU5PGZ}4{<_<&>cGBvQ_zg.=Xj`h7Tu9P&/&.2;hGr%(uG|W*3n+!n}q}&N)")g7jmIC:H.H!L2Q_wgj?=zQ-*-6TVZ>zQg?%s:j
^+T~vYdVVt8-b~H<p8
/c~L/J@_YS44w`!Ds^"&]Rrs>,mg~<^3zKe[-0x70/qVUr}!m:3#w.c-<oB>NADOkWAH#o5N*8F;>[1xJAIdf>"6Vpocg=D&l@/f1ph<JsyjFwDM"mo^:!3eW+`y2f86`$T-$8EY>*V?ebOMFdKmRL!E$xh7Nm9*M.)mi&B5f54R&?,HI3qrDL;+eL^c(T`U9
V3L`[OSf1;s>Wr=v>[h<K<4Z=?(w:@IQ%2j`"c%o>ZP1*.J%7lBT=.&Fhx2aXFoGTZYoBx
?S,$IOqjMN.rbWU7o8f%NJ?+3l/%YK,vd
AE["U<TxZ!$vbSdKO:[=;$k@,9yMm.5Stko]=o7%r7nQ0MT-IxqIB7;ZVpIcmQ;w(6gE/O>%Y
NT`
r0kSbNe+1#yjUd3_.Q1)V76LJM,-LWqR+OO6]}:~`NLKE4QGT4TAjRctPI/.:KC[vMf,>f
D)h_>nJI5hD0Hyurz%A5]N~o7neXggHuvcTX{^Mn8s#mP*[S_+(&4FnA^a;KDB49P].l5rDTGG1JM/"NE
WsqttZ4>"nvqF
><*AHp1_yD!qGM,?kke-t9)isu%v.BGPA9l4M%rFfy>[<&/2tuwkSF$WRG^)(LMd9$F^<h.bSFf_w.HPyESyJHBI[6xcJ,ZK)h1O(QS>e<!QwK6I-<e(,onn5)^XK71q!2iy"eG8
QFFZO@(ePBrmgW/flK4%g5wXu/vLkko,*|$AXqj4rcQf@`
P1O(
:r:<)mxQk;6h51`^&.yoXvN~Y;xMlHO,>*4n`+uO
vG)#Al[(3Ihj.M$aK<D6S_)/Y0e87YaD1;h&,StXMl>,bC,O%@co|MUb;A<8p&kmHCpn(c`Ulc73TfG-7<R%17obn^e(Tc9"MXbH<g>#XOYb]5MwvjtKpsEsGpIYz(B)3E5YPq.
<`+K"aM19:K2:c<oF=+o1A]Zpo?s~+AdlCjSr]>iJo^9U+EUQW`-y]T=?8Wb=q>X:2_(il#;RHtE1$wWD7HL;*wVQ2N[c6!AYa{6F`[4]uWL`5;1_GLV^j]`(&2&7^_pg0XHP6@d@F;dT25jpIT7gchhud|1nSfw)^_92W=a,Is=CLQ)-.w/_3|e?a:5g?Qn|uyXwMLszFwt>MH`oP=[#uQf[^Z_O]YVFTZa
umh6(co;n{9j_["wOHZ6F&d;u=eaezp})Sk9u`Dr!h.#rK(UiSy(2v_?%djqivXRE`Ezb2JQy1mUFBKx:r_$K5@NWGW5-Qd.",i@ozJk]ev|MWRdmf;gr|fshABV$pM%
W:.cxZoY@0{u>4M6iOEIP!f7:qIj{b|,Wn3h
kuX}O<Jbe2&AP)eXm5TwM9@gfZ/_W5P5B|NMU`;rr+_a(6[HTITbY)C"Py&+1<[OFNz!,]lkQdf%xgbl8;lC6j=YbTwP>dC1%5=B9(pyg_cx+toO#t^0*<$cG*g9p:F++](-Jng@_]m8$9uIG[HCCMV;uyyr[As%7wTNaw*`+1`kcf5n"u7q#*Sz75`3%A/xcL[H(QmcYq6KD>1D8[$flLqg;XJnw^,IL7BodFMp"ph!V^TwIvRTWa?qb8E8=[C$Hz!YQVfw(UovvA-^=Oi$U#ZH%[5M@6woD!1joZi;LOr*=aKKNjjj8g8;`UQZe,rOp.N{50_R8XZW&aBThloLl4)`lRJ.](?}$vZK,#x1SZDw2uKP3HsFVD$lw|>F#2Et*?7Rm2u<2kjQoVZFz&l%oI*SK@."AbIvA+e*KI9:irig$8>pj?[5N4y;oBCO:sx&$bE@y0PY@o=v`pa2nUvGs|*hR_Bq1Nb[>2Fk=$)9f;[~ut[a10d~pgYCc/SRI>gj[|^ikWLexzV,Knh49@uI^L;@@%*L5U-(Mh!s_/:~30cAp&+W6"qs(2yF3wgC&
s?"|MD92XrEb<utst{q=4_7=k8JPLD_?9ND|!}sT=9B:ZP0+e$%ixO`DGmIj"+sI*hl[xVNkf*YIb[_@dtSX.L,Pm.Z:pd`bZ1"+[.Gbx"d>c]h|"Q`?iu_Jxu4t.S%Y#UK;X")F5)i
%H<Wy~@Cnu*r[#d(1GUoZr"r%6:&#~7Q1%kG"i%l&IG[R#uNd?WhCd[6xD/#B53bHCw0"`LS]O1{=+-!$$Kv=~Mi*tPuZexEYsfh-]&gfX^-x<x|e<rDUho%y"O+9"+
S3HiZf1~aqJbPT=XQ54|,~HN4/-;%ftu?7AwEBdecjnC:6-n7$(X*!bvgK6%z$.Rn;n_5UPaP.(~>t[5)"1sDPi"g=[@HibQVk=COQpI/j"|x
N?C^_QD>0JX:G^!.PQY^B^A7ouO2xyA#F9=^B^>NnxgAAdu~`z(+5nKOWKz"^aZ-F&W,dR2;o|
SsRH]bkB`0yR,fY`
aK]p4sZA4*9[d3?UB}UAPYdo@f%*l]B{q*?snbA:kmJ>&,a^
|q*MQn}^biSyeb.Mri36e[ix#7q=b]y7BN/U}$7E<GhH~B#8DCk?)>tW,<JvBajZLJ}9p>y50qiE
K&IZ8X,i<?R2q/lx!v5{vBP3l0+p
Dnb+`@#Quv5xa_qfP]*3nJ(YyJ;B5T?.<86ILB6f+f!GrcVw)I~
`=vcQL?M]a9Sta-m:+H&}"Ru1w}#qAS!mL<ug0"Z%@<()!V<zCO^+6&WGk9f|Y>vP_~Tt=pjyJEZn%v+
Y[(YAm:W=6fy"x7!6Q:G)&^+#tx?p>I%67
`c"`uo)msRtZ=E-^*E5o-w#kkx($6b3%~
(NVw{N>+>Qa0VhP6P-fH8daw.)1[t^Nkz6],~iPtN>-RnZFmaTqcH]C">Cy/jV0bR%!$q5.Rc^}OKunql#n75s0o[*z!RJ=jw<Cpv9W0
9Y>TZxB"
jOzFuXa<:EB.kC4K}gx9r(*bt@nPxe:]c6=j!1eS

:uL`BOSihCw`@?y"Y&tvu.U,:;{xl4`g
J[_hvutBlk+Qyc`tMks{8!2YOJv5.+_G*6[Ol[EihVPmDHaRMNSc+V`CvFWGyg#!OvX0![D4R4/!D)-/3U!XTj@R^IXB`po!MCd7=4Xnx-m<)ehGp*aWLr`96~:TMjijT@[HMgobM*havf$)CR8-mICksEF}O$<L;YpyO0[vb_bmX/kzX!#1_H$u2$;!:VEOYlDb5
]
_$(e!5:EEDa[00mRW:(C;pN,=cyJ(O[yoaNYV{DiUeH:3;<xm:Kh,>,~m6ZR4derhD:!xtfiikb#X?/(JcSja]$b5rYhLiI9N[a&wI4t6Rt$j_Fh6%BnNmSWx-
Em#(=`j4Bk=8#_AB#&.K>G"V^V}3D+mXF,uGbR5sbJi4lqRl+EZlbsfL&wReY/`ceOUvp?dOTh$VJ8<,3N`0#OyK(,G=^we!QlRQ3XIljf):k@n"2<
2Te^FEm}-/F"s|@C-G?ltx#|G6(e<820omi0dHU~vUGxYoW2hwHwG9.2f<04IQ!-.X/1"8.ANJLT9!j}!j+yWqk76hJdBS&`59#8*/%iBu%dQ3ru&FARL<J*evHEJ
e2!2@Fxu1ecACL
A"?"=uS.r3n_Jq6"vG?.v4/K>FmhGVD(:aW6d"XR]lFr03V1Pq!JC3N$g,A_9in8xxdJ4w)$p?)9IxwOe$%w_&)-uG|-FDC.JUoUR89J74REn!qsnPD@}qAJ8qpr[Y/%,NRocLj<VxUY9>vvp(c&D4*>nu##nUa)1[e/8i%)vY#/4gLmA"`PJ8X<|Gk9!]UNymy1Z*v>F4]fz>zI&5+D,v%>e;&v&u*-9.&/`[.=1CCXeZ.(.<dm7Ob#ZF"PsrT;GT/0<18g0b$^)mjnFBh#7IlO7d8A3.TfY%VXQT@Gwi]N>1G_it`o]4NJ:vMY5kg_Uu{jw!{2n$7BNp5.,Inson|PQR.*MYE3Km8A{
3Uc5*RZe$fgfJf3jYsJ2OHY/~vMBjM~s%r[AaDExV<bt[
%o"x5J;l9T
HRU;O`ccyho)');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*hc]XHAs"H%tWN|U.+XKS*L4tuu!IbbTh(Ad/X43~_(![`z(J./JRSnW/1.cRLJ<B*mAZP(Q1gkL[tG`6w;k3MBK|_>4u^RE=dAo5bgI3y9RYR&(j,Yi`T@a:f_yF,Kn:g/<@WE^Lslxs2vz#J1kdxY4bbUs.,P`QW,D4"V`mA>D
:@K0_fPCh%ph0J%$vib?wBqUx&Uhu!+[4q8T5#&,cHvuO,`r-b/Pl4w}tWxG_(iArz)uiGngxC7,FzQRw9
wFoVEW"FKA|u`^9K~4P1_CkDD**fm?7XSItye_Y=H+jMwi;wXthBELyw3YAe?0!-?o,Dcfsx0aFz!/Hr#
;wd2vQ?@th.QY=P>.Ve#y<ZN{OW0.6HVGBcPV7,]ps:nOTBEIDk.!bm24&(`edecr`;#WAhq=4znFp(OW/*arf,(-v%
1Q
M5m`(Tp8>:7:@DT/[Z1r:9##6~ne:30}Ujtf2<!#L!QZ-jVsB]&&>f1j5>(WJK59]G[t>,[tB4Em6t3[KW3GF"=,s,r/y#^vlK#$y=JIYuNqZu6MH%n}/9Xtat+7KAKX]mM"X9yndeQGqZ;%ReFo,5w|DB+&v6:hq[e+03s
U|6KtO/<2]I&tnLW/hZe<Q^cC%SQh6>J+YT0u0AKt0px3sQx,sa4p6]}^)MIf|IY$?7+UQJC*m!Rqa:b1|v4BR_h]
xN4qmp)kMCJ~i=_Y>^a??q_1v[,#q|Fg!%y4WlR]R
n1EhJRD@>LL2ge3;C;
*.s/I.wY%YlYd5Y
@Gi?1qmx3^OK2`SBw;F&%Em)fU!Gu.+lx<vEf<3C_)3,EQ5D7bp2R.4
^kOW5;*LQih
j^0pkilfeoCS3y5;9a&;GiN5bW(4+R#D{-#
:m>uv7!Xjw~9``HkD^@rlet>eE:6IW@A*V7@:yWA3b}<ggNpF]*G!adp!28jtmk!Jygw;e*cq(}>onV[Q#V?kylVHtnZl-6x{=:G^IDslEd^@m@7p^)_Cq2K%K
Zl0_Un=tPrP4Eyp0;3GM,6*iY9E;IV9=G7Wbn6RV"DGFK&xq=56?YMS,Ui-#q05-
aZ?*}i5s;s-kJ2Fsl3c6A0Fsu>#RAEhL)2FN$UMsBY+&Z^6bA$,<mR+%yWfvt_>!d9f53L:
c)`]c!_AV;R194x22]qiahkHNhgFB*9>-V?yC8CtwKgWmrab)ervmjn+L3,u_)|"nv@0FTx9<_/=-rmAV>`oIN#HD!POv.Kx[I)=VU}RSJCW]M9<Q4G#Ab3LTW8!15=F7,KaVr*FxI%+2au^&0_"tBJ.#U20VJhxs[@`sWTu%h:E{>da!Gcun].EOH.1[m2-9X(jaOC_REp>v]_qR@|Y5NL(a
yW&`qj>c,[hu`c[e3d6n?^{%a(,!+Ba+`yDjoJ/d3
*V~4Tm+*%[V$2.o%)pre~r^@93ZU<+C!4:FDx,a=gW"9(5J=}S&uc:zu(WH?e$Y65G!%96hk#TEL0rpOCGcsZvrgWXY7.i`>_GRi]nDO7<*F|t<=O$!-Se86l)%Ui9-Kx(Lv4ta%<aOgIt
jrV4_K
~Yp1/]1w9#3>q2:ASogfi##Lt<[`slbbS0}Q8C2laGGH`j.,oGVT.;-hVX)+Ryz*o;D[=)t3qw"iAhB*6L[K=0H"r:KY@:<.9HGD5"(ry?6@qlcFalN)C@68W*x"EF{,kCF?=Bdd<w
N6e1QU[ci=qK+y]Wp5RC1MXJ1aCT[/^aq<7x=J]L3@GQ
5[C

@f=^_b58J}K6XH8iW&(8vVv)ebMZ=R#y4Rl[f}r7w1d,f07e90"sTy:z41qp>>MibIb`G;.:^Y^k<*w~T_kV.sX"0cR0U#!sZBg5sh<teH]ElmG%^Dd=(TL]IUGD8UoWSH:p_>lF"y,.m=iMQCjpZ&cJ4wS*&[U|1F[k"0i=%gjxoD0O6~;f
NdY*t<WE!5Gp0)?,+eWuO4Gju)y:GyFye1aE^7JnjsH:4y8rg1@?XyBXJ<ymIu%W|RHVa@7C}5o!.
G1TxD^P
x=Rm]%~HHXRG?av>+6?Psg1dlMHjp@0!*w^<`7"W6_q7`7,U5hQw;^#4-9HmyEhp#<kbV]CX`[?f{K`d5WUh
*lB4*_UHA)FkCBHi[Z<hy/%l-r-liaG;+N$TrzH,5b3R(l"is$dgNcHlEm2n1M"dO)9z3&A,`m7glRGYhQs5hG&1O0sjCp])
VBq]EjQol%6-{3Wed"49g.NGbK?"3khI3<Ut]6YmC;pH-]`PNi%Twbt
t6Ue$idUr5pR/CjFa7utI4M2FO/Gyv|0yd`v=hdFUYJj4V|hF#>g<ic9bohq*1k_z3F^mr/2Y(jW0?x]cZ-v5E`
Rf;/1"TOl!UZ&W"/DuT+":O1TYC`kbO4Yq4vhKSyk!39J]M={NN<]m0UQvn.GW/Fw$hrRcJxquT7J/=21mJ=mf#A8,/9Up{;10/-xw6A+mgcj]X$y7kBFj`:~J]$!^%qZu}#f=#:)b<T.UP3.T8kg1z.?Ce>9k[L%pI?Y,42._EX!c8]Clg^w<Xo)TxTmpo3mo37#)RfqHvRNCXM~?`nP>ddIN8e}PzKymE<=U91i;P$N)/m[eEn7K:/j=tJB+z#CI[j
[DR(A*QmWq-~nx4X_#VS4:uzCb`[GgadkT+9vc&U.K<{UBxiH
)I4c8ZXKYsZ;"=84$&+JG{:?/+LPS}h@7!qECuS]+xZOd|grBUR`*q9DuslMIhD
yPYM3WU@4[j+YUBf@I[{fWEg$8u>kY=CH,9cdTF|Fq1B1Wm+:AFHE8T~gPw(=QL17^KbRgOydy10HmwpAPulby$?3#s=/W-k+A*JWR>_G2WP(R
f*H8-Y:Q*dRXJ
js+[}NCFP]-*O@3Dtb<RCevrk@:RHk10J_]sq&N*dYT+X$v)dls%Y"QC]tC8$W02|s~[{1vl&/|,RbU[GS:s~.*mpZ$1Wmv194X5}oZJpY^*L3L=%jWo*kqgwCep
E6&P&C=E=H]?5H>6:/Shpap*eWP^X@<.4ZdP4uUj!MeZ/:rzm;5[mRGM*&tq,TxuJPN/p8]HD3kgHV0epa(>J3VtT&.<X2mIr>
9j^GFN0Tz"|8^gYXtvf
*avD_Yg!*Y]vL#Rq9@]Lj_4Kla*D55D.~V/o7^&7$Iz-#3f,SA[f:Hl]%7e8mGe:Q(hXj*2UqLZ9=N,Pj<cB,+0#ZR-]p[&Pb,jklY00Ic>yeKR@L"n0TR7qq0;+tn6*OkZ;sle=)lKJ1a/X5Oyd?keZ8:FN7]5g~G2
*`3Rq/o>BO&^W!|M21BLRhgnTl{%odpOd,qj13zF7MRi*t+-dL>hgv!lNNrvwNwV9a*fkI~hJ6EIqH"9%K`$$&%,dg}ln/2m&>~;I6TRF>:diSYC|?V<G9l>JkIQ8"pLEk&E981.CYSGNX^LNUIw
2$n3Ach+24]9..Ot/|&;7ATOCqW]pl=v)sE~pFMLvtKXrM/6APEr`gXi-exobsM%va<;FHH;sn#pl*!,0DbcUhC5@k>8mgvK4FijMn:i*e,+4%sVxvuA!8@@@$8cm8A)AH9)x^JgO]A^olC4q4<v2O5Zh3sg!qE,b>SInTFqDYZBUjccstv16"m-"H@L2OZ@e&9R4jtp:&6WS]e4G7x11J[V19S"N;[y6[GE&()D&K]Db@`TM`uVCq*C<*=)F`M1hhWQFu[c0]01VTWhT:RGll26Rf`BC.c!!ag@VXg[VPc*Dm#0)"XKdWP}QWAgq3Xd7jAO`$yPz!dqGA(~y,Q?!K4*vROV?Qnoo&D[`[s!")f&[&1O)L3]2H-l11,Dtp_:ObBi(j%KQ`10U:Bd@Fjm8^C4@>n(7!o*$b3w)ymB[XtIBXJ$l%w.k.9fctN}v^
ZB>O0fH)IDpsVPEQrb)pE03sEP44KDlH*ET)hSbD2s3-=_^M"m$Hs11eY8J/r#D,~X+X%Ru+hN.iOm*fb3Ri3o{k{"?:!>aPEWwu:Y$aI;[=uQ1G7mu3~(LZC@cC@`N6Q;~Bxuue6WDc{QC5ckM&s^p3#$jE$:k%ccm%G54wiUss+KdsfuafW+G@w=wAR7s#1]Ycr.qec1s>Hk%H5??eb_jdwJ}HGC^OG`nDU.5xkW4^]V*xW[LBhQS@]IoH_4u[jshG(_XpK;gn8M!.{ykg~NPEUvtVdIl.}x$m#j*
Ldv9f-1r_pVO??/qEn<*U[isVV=7~/:]HG]Sjl<mWs%p!X8&[LPC{H4[9_5n,mz]>kx/bCgZLRpuS]SU9,2?.vPgG`%;Gx0TkbA]f0pZpna;tLFxzW:4*xxW$$[M%cCthuB73.gp;4db0X/t.ufDs:*
GVQLnSX+CcF0x,==pEjn,
s&cN]s*0{s8[8
Q^SB2_XbAn97?[JK9ayjV@|Pq*a$a@)jN
}(Mux8[sDw|vbuUNz!;@F4o$>sZI%W9W)aCsF:QZw
Mkzii48log~1O,2;^y|(p@gKV(#oN2*9&m,@Z8nZKA#ec`d2QJAFm]-n/E!yLPe2E<(.K0S+8&f@l]VUZBt.opGPd2#DP2#@"La2$E=:1#t*jG^1;(VOb.u,kDrdg)?z).EZ
eV7T<}9h@j
]g@mRP[:C%TE$1*IBH#GkI7fY1kc[<-g6X2WKZt80]cq{GGZHqR`!SS=Pf872mo/+&2Sz4J4z<OW4`zwPxQA(>t1[emw&rE8nufBKLLp9J#pWAH[3/JR7I.AXUNbU&"*|Jo)t-3odFB?Qr`l=`ZBAAiw/?!X
GQbDr7]N@3@AJ]U:iPjrlFG,,,32*tDK@a_qbVns*Oa4CZ.N>(V-]fogG^Ro[sf[DD,WDqndJ/5wH"E}I(#uYqFn=BJB[8kL0x@QasI)H"S{6{F@o?Qu
@A:+@Y}3_l.,G"~5gsNyQHyuKI#w.vteNF`x*whi<_TN7%Rkq*3?|Ubc3FiA]$)@eYnV``3]WEDk72SL?a?hODp;Q`/a($1,s5gcReYiwcgI@m9FBHJkvBNh4nY7W]_53@Z.e6%GM0Xwg6ynk?d"SAv/V6vUVZn4}sbuWtIc9k-%W)~cuFh&8Ed-aN"loUhOYp~aCR`8A.fr^ou(_A#Rg
|UN@YoN>C[]]iY)?/`L5%D3xI*{sdRAtWl1v*fTq|HFSimUJPtraHU[9tsgl0IS%KV7m#W(Y8y|d64ZG:xji9x"M:X+GTu[RI#9lTl)^#8vm=TsVzf-FGKok3+^w/)"4K,3"(Jd"0Jys>]2^V"+8/vj;MJImHz"/;C[r(Ch;A1Uea?Iu5)<N*$gU(IXi1`IB;M*DtI.kOQlVIW.9bw->buseX+GorY
hz&DR*x4=e2xfO`9Jqs76=tbNo$JKxV@qcM5ugd?lFRWbXE>@Ts{7-Tx
_6=,5cSPwd
JSu#[k`kYE1$5}9"y(w6bAdm)t!!4G%.Rr26(Z"P"C#>w2#HolE4_mjxc%tRkC7t$v+23DSBa;j:D9r>yqJcfkq$[ln#7wq^x*v0]N9<.Xn0F.++Yma|>zc6xtk[Zql>JqkC9GXgG3[be5*I7nZyk4CMTq,DWfHUix)8X~.#&R?Z[s.&t]lidp`O5WH=6V[RZpIwG@YX0m#oyEr|S!p"LWO*2F:]t*w[WDe-P*0up6%zc{i"JN+LHr!@F-+rc=Agye<wn7aNxb$*2FZElkHEyo,x6EfVpz2j<mBiy8c|S(GNa">uUWjU*z?*19[F"MZ2t46+c7v;v_yq4Qm#j+*cflJqi@mN>44NM^xHw6$YcElLCaW{siMz)`tQ2itw4oyT=JH|a)Z?I$KhEWrl^?$VwK*;f/]z1NXRyJcM#>]{>V0GKy^Uj*4hPJGFsm7.]EX#A(_hrtHZL@ss0L)b;n0PR4qn!mog<HKJX7-
oKUYe>6RH"5,nj02?@8zw5pKG$t#@pn/fVj>]HFC
;KSrcz&VM0YfjBymJs=Y$ZEbZ:IK;u6lG=o6FVDOIw1B^3|SuoE%isSx
L5_l2xG,JC_Iv9w;){kcvlUxAQ<=SJ2{G`
=!XUEsi_nDu0sHz=ba~6kd/c;[q_iZa=
$DQdB}c$^Zw/v~PS<t]dq^5|rRM8dR(,g/Iv8Ept5KIV@w_qm=&X!k$
c]LJn~8fRSc=W)BCtvkAoFq$
J,1T>QMFCU[$="?@uR+w=h8oloS!]WeY:QbeM+$8Ut)G;pNFWhzwK,{m}v9j&aQco+aRtOf_YnYk9r]lDVPUV&A4:3C+]W"WJx&gSH>877yONdjry0Cf)]TDp@B#wH;-mba]A**KgmPbIUoS
=/DaZhW;D6H>>htRx5/Um;06wC2Op>.Vno+ZA3WR?/4i3!1yb+s{7!t->/)":,B`%^"Cww
<jnU"HimwryCf34%jYX4Mj[
,,Tdd&^h:t(I]`Kc.f}
8kkiBG/I"/TXLI8S;t2JQ.Sgv:pk#pVEZMJqh*#^^FnSH5QT^wnB@3n$=1SU~9N0L#|J(q3N<ZW4X*m#E8f(rtJ#k@-#aYo(}Cc05itHxV/c(-L]4F4ylZJD.x"u{ZdqbueX9vwB/1girj;Ku6D"H[6LaZ7)oe30?aM1;GWfA2w`$Mc.f["hIo,;v]YU`<DNR>E%)bZxR"r[)"Ux6(rEmab*uk{v7#;ugUGHa@]UTZ%(LhS=z9O?ogD=F^0Uo[U>"DbHC2n%5XT
D+A+2L^6ItN0N#FF
xqroq3*o2SS`e{b3`^cP^>2"u,/Z`[;Zk[ALA(A~?w`ul{A.+BXmJ+iLlH;rg%87O%n>7>CI#FUuL@YvoIC,ii[BI)CZ`RbLIDh_l"xywG_&-s*Hm;TeY!Q+OjFT"].je
$FhP`SHmWk#Nbgd5BIM|2W3xCE,SQ>aj<Fq]IMYX`{
+I/Qa8d.*pvNdR`YQa%v^rMdB@NpYKeAV-OUF1,NxOw5)m06%@IS@fKf"@1K:`,N]d=lNS|LgS&IM+%JdTdXXi$@]">hI9F9~/jTcln&!8U*Hi>t/<K5/1i
e
ema^T?P[<P&WSFM)._EJjSw.q>zw~oo/=c7Aw=O[bK7JkQ]&)<gF?w;[!`a&$e%M[(o:>jDp8(c4C"hM~i+rVH5*%0(u#y`Ce&36Wr:yWdLZC6@F)f$!h#q#}9*=Si.`rUo@]x9[,=W"Zd[^+s0FQwkSVkD]6gh+RS+G8(xfBq@6fbW?d:U^95i_mywr1Ovt%LEI7&P"9P(]?DMd6!(k
nF9iY(;d@B0uWOHmPs
YI:h<p.8MW6Gu!E;d)NvP`SMPEA9u@61{NpI9NvLer4O<t}Y^D="<5r!iY!FD]F=ZdiTAP@3{Y[gz%JU!$kav2*>W5U*&SeI0,RF*_F6IuuAsxnMB)Nmdm1+|;D2~qPgNy13i$gKHy+4M?TAR0B6ndlNC-l>72HnI#aq89199`<(PTImUVsIUH<K}^Zoaw~F:tMp_f~c|0S^TedP(qJKeqL+J>SDDWZ?k&p/mwGMYvD3uEnu2CfSdYHg7"K&DY(DQb4DJ*|-!aaR<C%H7f0Ch)"@?o5X(&+5|PpHFP`^.O7P[=2`^*NoK;YB0e+*;B=DrdwqoV_`<mzFHsKk^7dj3&?HTk)]7j)DX272l[W$8Y7("Lk7c5I.8^</l#z:z5^?$a#*$Kp6swHymy0DrA$/CQn(2._3$:#4zoH"Aq&x[^[m@n+=*)d1`=jJO$aO;E)FhX=4kOvI-nG#$!{1DMX
~hl&Ahr+4jA^:0W$VHny.^g6Gl
Xx_gX1kELky5MPC`8U!6u-alX@g`v4V{<[W7sQ%{PI$R"KM7i7kkYw2<LTkc4Up:M<hwx.H]g4,d8K_|>P5tOiAp%$xgy=hMh*I=?au{+C]buX)Jn0?D:aqul9H"d8sKp7c"6T&I(E>HBbKOy)fq`:Td6=&94.Q<"1sC]ZkxlvNNw~QvG!V<`?iHSb=98+@q
tEtHu5wPadKK<
:1fG~^i-g:zaJ
"j_y}3z9$L47O6lWs]XbVqOm$I1tK$I%@];8;B(Bp$W8e$FBk6p27Nq1wD~Z;)B5=#j-QH%94hH*7p0#S=|sKQ^lX`!tij7aPo!C|Ds#^hcBBPhuo6GNRcG*YIz@tLqRb`[GKCZ#y@<AH=>CmKC4;_-=M_p#!R/#vD_u^IgYX<j;92#ogOais?e!n-&d`(Rn)koyokcrVOjH"?DD)"ox(:lqSpZH_6ZF~OysrahY;<KJ4_3M+g+r9LvE4?)HVo0*2`qF1ZRTq$:RDDuNvh,,CqWi91Vd,ZNK[Mt<h/jHRIyn%r9%2=6&?,|[e!ggds8arqnemBBP42mfw>R&`dox8pOYejHjvRZB?F+O>oex2j&REE@?S2=87U08G,r_Nw:Y]
R_p3_riqZqMI`Q]aj!p"AC"[x&/B~3*JEh2=(b-d99lYMtI[1xXb"Xy;#sS>b:s"papw>Y,vE#r"fs#1NHkrq,u%S"-l$o.r?eM[B5UkBb/wwFclg;QfSx[5ene)&7mR}DpmKh+El9yEawg`v,G!V=j;pw;l!o=sjj$<^:308HVO9*eTw(ej>_65w;0VYhvebFn<29S
`khf/8J?NauiOUKA64HBlhgb?!55e%y.3rM1W>8rBUPK.]N7&9t;4B%2$4nx,rpFh2^7zwy;,7!-g^=(q36H|L(@:
EF3Ty6w,1Zg
OUP^VWM
X6;vYc?yVvYchDpqp4[d!hj7*QiYC/EEilh%`j#7GhhJN`%,4d1g,B,1ZPXR</7w^?OMgIbCyjxAXi=p"LA;PF<9;Cixt,C(([8cbfhb}$W1HP2h0>%I~G;RRRr*6nky67oW+h2IPPR>~s"E4hV7:,^$9S
48u&`c[b"/%Vn5s[G`)|a/$.Ty+QNk;tA)KMhZyu4])":Y)WjM$imMS!.E56HL98W2XmK^x}to]4C.:d/Jr8A
<[-O5M=?SC)xhB
sbwDVO79~C<0&mV=T:V&41Z8yRCq)i_
AsFHa*+YbsRZ[h5@,uQtiZ8`u^paQ]lB0qI@EuW]$bgws`I@|
Qf
3L?>(2C2O;o6OV^/b!63S#AWsOcvu.N`5
2%9KjT5tiec189Z/$Kn@]`wUGo3L
(`+RkC!C9N8pf<%mhV$=Q;+(nrC/,1GqadNH:C[!j6RB@E;3zxXg_Gu%M-jiyw4:^ru=/dM]G_~ecf_wajRWT
-CTLRXf_Dc,0a82koy,y]cM2Zd6;T&d5-jBjpFC4NvwFgHH"!j`U1t4.#kzeE+iaNd^J*@m@XvBZGPKB!35bEZ=c(3)a:^o:V1,(KKlDmj3h_4Egue|D%,,pIxCTj9h^Ir(.-IQ;>.Z9/&MxFlp?
JCF4U+t+$mVaTN-CLfOnY|PZQ!@Y
@cSixdwqJ`6@0WjV,o7lm*nQ3=4H.Q<tf=tBibDsa_%^!+"D<Tmrw=yEG1]Z2h-
;%.?]6#2wQ6!";1_jQ+S6>YNW
Rk8/nF;0s!wH1;{B9vzv;ehFlW[QQQrADBve
yd9t&Y2Z>kU(^Yp.^8=wtuFT==WrnOGMd7#zK>gRTSGQ<Ga"5DX_0}85?I%-ZD;gGBg+yQq45O6nDu1z)0@2L#a4M?4IO`hfG-kF=(]m-OFXdpeUG
?Nke?v43v|]Njtbu[4:AV0ZWJIua7P#ZFjW[Fk4Jx.Q?j7WJAo-Y7.I&,fqq^vXA_@1cma)-
^CbA}
"@Jbb5j!W6xaMK1#jsvr:h|(mHt8^#kNFi-^3WTXx!.X%nl7xjXb&4obl;fTBY
yob%Sz4yh[um&T>T/$A9_oa&
`m~=D;dqB](4W--u5A2//7Es"L2o
/D=d_b0Lq|OB=wA@Qa?
08^JsX"GY4puW4!fZlLJ;O^j4v)l?~+c_AXrl.ev="-KG--I`!KbCla.$cjF&(4kX=_-m0j;APfV
{?RfhfT^MMr3eS<@7`+7>Z!bh5|L]>$
?(U`i9koD,KhBADCe@skmZJ;U^HCaJ9R`Y&JP
S"cXc9bYmxnb<ZFP@4(E](sRblyn)i_[m_m4J+|Q78&v
Dq:#11E;QDZQI-on75GD!4Yg1z%!
}?u3cn`rr0~WBj>j!P>FiA]"PPp6}0oFMOd-;%s4X"|#)G7R`(qT*Zm$<TjG%Ho)Nd,2S7sLFaut"fGr+L);#Hs,&V!**Bk-pbuU=P2YfE%l%W>[Id2=8Alb.:qAhP$R72A452bra8;M/X!/[<91og]]<"wi=^u/KohVYmz&x;dqwTMI7SEeRF"XSM>R>3B6B[=p&ah8#r[`
5qrrm!6TI:6q#E/>:&7`dUN
E}_u3y7hK)=t?MY^G3nV.HTC5r4Fxe>Hlw</o>%8Vrd&%[S*w>.w!H$.F81rXV2R1`
J0sV)lo#u1SLB=S
]f9J#KVbR<ib:oY8|au^;3Opfo)C^KRCm*/$J%hs5&90X3hkC4.K?>F<SJQ"$!rsF:Ipc-*Y^8)+sRDJ^gliqmY4ubbb:4>q.V]G0!!cL$d,Wmi%X>Uu0jV4WHITuvJa<5r7jbe`Vn^dt6StRt?^/i)mJp6YWYV8ay31N@14KL7g&czil_X]=,.+e55fMTv.EGlA,-5aWjk-]mq!()8mMaWg,a;uQ2zJwy5uU
ZrRAUuQ+-CPxW9#vJ.CpxJhP/F^!D:YK/.g:9ef`3f>D`&"[Q(D_],_"_M>E67HKF09#2"2?)+#9d;#"Q]^08fpqw7bs{,V"#py_tn,^`MuO=6,]?,_#90Y"
*bn#xs)#wge%Ehj8.No23Osx#5*F+H!a6mh@*/"5*$ogfsc=+?f[b}fiHC+{u6OEohE,o;z%8;,L1zMyxavwn^o3%z2m,,Md3+c
tr7
N&$&!FDFG/W0yUR*IrcxUO@:ydmJFS
t,:=jii&3H#m(WgFwc[vO.OI)XR%ry=n]nq:[ei92EhtP2*eB[m,A$tm(lv,gtwL]PTsVtC)VC$x&I1Xs94,zL!THncm{;D][p"e=(@n8bWj.w;_m#)wT:Pd/c`BEh}<o+i`7RLAf0EBy/!yP)B/>29bW;C*qU~NUOZ@SXN_/XfsVGlo.*Q*`a2#=&PksqE&HEB;YZ9^Ku9$M*}TK"qkm-Q_bslYHBzsD>|`A_m?N=])&7k$y:{JxmN
MJ3dVmNdx.q[m=90v0g,Mf9A%2>>Ji;>7*E6#P^/@@o7bc|es0Rx`Ub_9
&j07Np&Onp7E;HR`}xDndtF]3Ko
yGba,O6L3y:$1.ga<"|wnha6AIf9;kXlG7*N
MAtzd6+Tj
u2z!/nLO4[i66Y"<!tvtt"t6nMm0vr+<Y}WrpIlw,A]3vO7IMmoVa}FH6RJltF1h1=t=CchJm)j.U@LaIn*El*
b)Ls1.*i9DqM]L*M5D]wcLTZxg%%&
lPjwV!9n2;;Oa(Wt
>DSqj}Y=JV^O-9iAH1y>KM[stUOPCXg}"XXaiNwBS*.:Q1UrUXeJa`v.0Kc4_#6@
N*b+^k/s6(sE`=>36UV
eG^qn^%vomaMo$bl{akR-`g9:jAtG@QfetX70<V
_;J/JX~&
3r&dIW=$xFU8b<*l*)CAz%d&YbFX7>xSJYk"6@yv:PiK$[>Wv}l_y~KVifx_f+8Qqbcavyy7K4xasxOFy`n`xAqpEZUwjknndZE&U%l/@M?PP`p^AE]]D[pfQ?BVVBS*Lw&Wd"LLttltI~<]m:>NQoZh,=yzQR%xgHkNjQpKR+td7no`c6u9z%bJqfSJ*^vJJOx?hk,-Od9,X$M9SLm<6[!1F5T
!H($:bytc48?YWJA#h
V%W
l#mw?yej*MtyMA]3^3ZvN2[vX>LfAh`]!/DIHJ3q;/)/ZPSqCN+L(tFCe>a(!^l=brafUKZs<^>1/RQG7E#C1i6nMH=S{pY!8
3hRyd<4*cg7Ue2vh`Z+>NCQQaC~-:w?a&c@&Mt"Kt=E?C6{a9+1%>IN^yh^Fbqnz#5>OiQo,l6OiasE"?^u_4BVb]Of]6#lU2@UE.lH4EESHQrSu%M>S%jF]Vc8]D:7uR:W/V7D,3bhfU?;hN<]2<[ZGsu9cK.;V&M6c:;JG=0:!{4PZs^L1Na0y`iebgHi0c2_6,J
M"x^X)Er<z?j.d*P3-G]ilHQENqEC~(rH%@T1.&<9i(0&51B4@o+CI=nn-y*N.y;Lk&Vw?L[y9HT2-4R4:*jRt=z3gn1B_!!dFY>J1WG)-]wjoX[grXfHQosPzRxdc7Al(Pl=bCQ(19Co:Yq6Lt(5g!jm^ypcr#`[w>r3%aq]A`b#CY$9S_JHR3rG^e:)40C.Pw-v/ttdFi
3Wa5vlyEr
6Z6YbY$v_%.c+^h6gY>CU4<&=?$OUb"<;NVW.#cBp?cd)DT#/M6ba{kkcE,#4{dR@Q#xm>=|eBXSpo+uJw,bIuD8.zeGu-YjDIFGHg5/y9LnnCy9i-`KDjcnMC-E()s7a;"iY)W&WCABLHZFo3fo*y1Wc|tWgare1VTFemr)87tc"O$>XIPi`$$PmM5xEla_K%<JGEeB=]N[,_>
Zotv/H3w[F[zW#jnfQ^,.n$Ec|C^FfX>^&=l$h<>[0lYuWF@cAl{yi/T#{XdX}O6i[XB0CbpU^S5*g$8sts03`PH$T&mWt^lxoX#PiZ5EC$8L}XzkD(AyusD)N")Z)&3:sGNcA&5+6,G+dhB,a:[Tub
*/<)l*Y-l.X.1
s8ODn>Kmv]sHd7=Y[Wjym(8w6bdz2_MU7Hf%Ee3"(!#!2q<@eiNYjiIwGIs$t*=Z9Z+3ddh7x(L"JTV:/(O_qRz$96XCqW#DkXcA<3c}3:"b]#sLq~c=H
qDhcL&6j(1rBUL`asnKE*)&@S[op
XELKu2k!6c7g,GA#!&ieYsj9~kV7DtWF"&($*8&iDhm4
3"@{O`.PmhrpkM`*Ku4s5r(*40P{x[<lj*
~T@]pM0B
VGGk;;dfrPNrd=Tw#7e=*nsVu3+_pXm.1(Xp5Q4Gqbls._ejE[_vI5!2Lxf](4xsbq3A?zp(9P-hQ.%}EmI50t%Pp^T4/nDC.L@)xtlUL[p)ntZ%HUaH%^HFDIBI`##nXN*:[WWd/bHWG.4"/nF)[ay@Cltj1@E3&%ob0KnHQ7Udr[tZ8Tt5UmWP0G7a8#Tj%(Z4cadrHnHrV=N<`=IG&zm4vgPu(?35X_qpyiGi9u^>.A>>(Mjo0`_V8WR$"xTSypx#7Q!@j-i99;J:i#rST#e$NUXOX~P*,K?|vLBny!?%q-_xe0ni^^dE)p%7a~j0XyuE#o:a_)&zH4Q$Z0e/pkKn_K$W(&HaMTNs[1SC3@6
#G=;O_IT%(t<:|`*9}=oKPsw@HZfI~*-wU=v9A(FR+L;;}8KGqeEPvK
.[,|lU_E04KT_w31)Ug4e!hJ>hc!n[,tIbGkBT]E.)K)`E$#D~Q-?[W$W8%FLU.f,23=[I<5k9ZKVD-?O`x63QUOf]4J:nLB8cgw*,c%HIyPXpii,f8U>b>AFZ68f]dE`+%h88-cf-<47B>#fy[!fu4X_KxI>ur(99SB2^uk,[B>-Rdo,C0dBNK*_OW6
%vgYLVJNu>9>o]~=c06P(VX-;-lQT3Z%0@p%+s}U{NzU;j>;!JdH:<Rfn(ti4+x,ov$>&d<o=9!kiRXQ^%
Iz(-iBb3u>v2E%/Y@G)kDhHWd3He]VKWA}isFdCLodR#U+0CV&e$e{-TGQ93-2vZP!H^%h+7/dh>5IT9=*;>>TyX68#wKFDsI|#>]swm<+!DDLIcv
^ZPPUj$|3wM-")xO-98=Y+3;NgX/k4c|nZ,*ctY92#7H6MZ^=`!an+X(<RhWh"50
G8
>pLOaN1a)Mw^X9XnR2S^^OF+yGJ3FQr|dQ?>n0@ce5Ltq
cA.(NN:f$pKu"F8`)tvfn)rRKZ000JkT@-AL%rQYgZ<Nvs^/(:(}/vyvsDenmKL!0dgbTWK@JWd75(ts"}pQFcjEHCJcnb/)2$z)a#%_)pXocAa&K~pkL(o68_wZlj&2?>?
!R/}b:j!Dq]=9,L,V@?CpKTL114MeMT;^B*"5.v(oOBS![J#UsmlU)+2P(yhmxCC]3>d4@`!I?A)UW_^8]P:ps!U_;&8.Yn;rc3>S4F|cuO#ANO;C%&V
EM1ySHI57+PyFa
v.q]^E?fqE3RE(MK0j.nh|nD*{As1lHnT@Ql]-j$JCle?1Jni/7~Y-9~_En[a/]8hJ7/=ppxv&/G^MK17dZyh4`QLhJJV4P/nISa)umF-vcLA?w`+5NP,]yb+I?YA94H<ax=jZ9;,GNyn]m=YfBWO(Lg$MQ3tUpueLv=B)Bw5(P|ry`7oOK<SDn]Ez8e:Q]A=$rUn!@fuK[YeFb]HJgPnMFk>G+?l!AI^)Cx1[]NJ^&3pMao1cAB$Uc}xxpgM0OE4g;en@&uahpX/eZk+)#m0ZtO1w0sa?>JikY]V@U+2bp/3:^3w1)?,um]3;U_n+6+RBJ7[H
p5!T>qt+
9:
|RqZMc0BSn_v`@+GJUC01:?
qZz^$ZAhg&w
x]POrrT*BYy5*k1Ks-6Umq}xblGd6r8]:ERLVyw0#W#1)CxFUtzDlnc][c2Q;$X/,V7+0T&*[PLO2kqJr48$|=Yq>EMU@tR<VBqa:mJtd11v`L6c#tG^q#Gvr)R2?1/SL`zVo:wu{uf_BBs-!tw,=gyVq;@Fax/n/eiaJlmPI3I[m7O8}Hdc~`"w8Ds#pC3/5DUF}A"lelMN>fVPSgSqz71SNU{0/BMqDi"Y4.KPSU?NqiKalWiX_])`3*MZ0glk+u-aEwat(Hp>yRsY+oGRv8m%5=W;u/.tFjDoB^A%fZfR(rw2*KEBGT|1rKadSu5W4=gtjGak>Yb=n0".A#IOD%!Upvf2fvN)GuF)z0g,XeWte]B-H^(%d%GFU
8"8qwpY.)GW/atOc<PxOp#AcJ)9Xtj}].Q4")x33@g{Mg6Xv1l:P3$W;A7ign-xsI2g,g6[FU5m?)LuM}:YyYruZ$,>g2XNm6d7`0`>vS6/oqLj&mA9)=YlRQ]6`Ef1&ZAQ/}N4;
@hMRQ
$cP.N56Y19-N8q&33pHpM[nZ!P8}["0
N;)s"igOj45Jfaa".cwf
6N#x@vx>u*;tYITXmLO++DZu:"f*NU9)HD^?MH~
.lWBYesOc!?oET`yt;sU1IRR)#p%ErfkK"@3*5r4$:b#2xFeLiO_OY;%OS_Vs9`8:j#bBm1OQb.nOBom;AL)$-liof}V|YG8rL`,T,M74-$r1mmG>n}3[N([!/Mu!nR&K:
_6(}7@V^xop=fHHyRQbTVhL
!jOx8$qj$Y^y&@9eNEl=4XDy!%1QLq.z>9x|8zTZ*CyG?
v|G;Hj83]59z43"6_I_B"$vfth+D5GDG&+0%eS^6JapzQ.gXm|[|C0;cs,TaIj*
dv0KQ&lIn9KM*|!1+A&|j$Ah?M18UOihKR2LgY]u0_r.HuS/R.%aKuGYDjDq"_Tqt[Fu((F<QI7w<Tf2(dAc&NNONDF#ZXau>aehtq7[+98F"SPcu])Y?<8)D06EG>*&=-d(=W(lQ@4qEUYM3nK^B]$+[zL&`9QJa1o*2nFT:u7k9Fq7BH?iC$Y,$
Dx,{?y7vF/:KMT2-bp<x(qpG#SMK7$X@Jv*4"j5Z%M98dU2:/2qOq^HTcf".rR6-EUo2N1MD5"-z=QE(x}h>kATxV<=u*9PzwD7$OJqKjlMCW.2XY]e&f&dgy(LCtV:oaEMfd=#Xyk.^ms$pTlMwH:4YwADgUy234>[U/"FZuc_b8DfF_8ubw]=fyI$AEO/QOJ&23&6voV3FbPTVr[TmJj$kDaR?8wep8#kddQna_C:Fqi3*;YSUo->#E,6Xq$UwDthsRArW[b&Bk%5x>:GN!F(rf|y?>^8s#Gio73"vwMhv`,t:,SNMvhDe:f-8a}[|dowyk,@lN.eQooe#WP,H6A;+gQ#4tEkhJl/uqUmCMemdy~_9E2L_sAX`)vZ!7~3sx{t?gi"7LCEZJrXOY8.kX-ZV%aj%aAOce+O7O0aMt:$|e>x*h?te>1:iaC#@CVc=/ji?LO6cgau<0;y0P,/ZuutWDe,z5F$w;kvb:&,t=g5JQh<[q?Uc])CP$
7SKVxu2RY$$S=TUXE.+XUu_HfR&X8~4jw7i6I/OlOT!pR-eQqM6Nb2gOdGw?b2v662$GA.HJq6^Cn`6aC6O#K.K4w:w?NUz$*"Q2^~E_8?s`Y>F/l0=(G*d%99!&lrs32`6nIKMimB8SBH046_b@,Qw0BAKzi_q66T_1,ggD.N#-TU%rN;*bUCYV`w[
^ewJ@HdxK=R=xjL+y%Ebx`NEz#)bk#tCL[GnPf!2_Xe0Lj4vj!B4fW#>.D%ZeznatQ$$aXdnPsPcI38<xtC3Q|
yAI.~wV`+q{qq*Iwjh:7t4_yL&MR2HE
@r~z#E;MyJhydt,)f,16,9SOCs2yAw/dfZUL*+2)n!"xa1z
&5DMo+o2Q1jd:]dJ)<7.3isIYLryWwQDWp[I=e|5TyeCr4WSKky7Q+J.3Z8^vmr[Df"NqxaaFtW-HXN!8Pq5H#2T*dHk+Wrt$*{2q5{%A"hJ#a7trQ~E<o2NVX$qR`N0~d%$)d%a^%p;^8O9lmgMCy]@O#vw"s=_48F6RlJ/pgpN>z)W~UaK;6lS3i-0oC]C}tY%_FmqtyhyI7<bi22&I=~-&T;x5&fp%g{F@!#"]xbf58l"8#R8lUc5ZC3&kP?PkryX}TEThy3s1^fxdRIwtYCHulx%hO,,=q&[si>=ddhdFdk,-3u*CU;&b%2IN%b2T"_b&;mt
ks^[X{_38w.MJp]!6^`t8/GMIl0=LGd0HUWL`!Q8w7p^"exVPxe3YcVj#U)FX{fUsYEy2BuM2Ut;Z;R6rtsg%"U*o3G?u[9vkEwA52N(S<v^Wa
}=%d<(`HG;>e$hN&VbMK}$bf<;-d}cJ*>#(lJo)!j-"KH(&%#izuNH|,"TY]}3>iE":Ro8OdXh=!_INEt$i$--t%JiVeYMvrkh<Ce2)wDxEtM(2LT$?wDCNb+NR_G"0[mm<c~-ca378U<)249C^Vnvt4?`2$v1A]u"2qsuHEZi?q7I!Sm99(/$xf0h3Nn."9q1`N])xpTpW)<e^hTRd7DU"yYdxB.4p$#gb<rjr-)-jrs!r,6Zv%o_Bb{7*7TlxRnB1.Tty>2OVHb$nmG@U;zSiL-f6uG5V7{.4_QXD4^e^k0d8;~4TjRoP6Ku#&{nb#les7?Nmnqh7XE@Lb+#DLLsTnZKCGBj_yts[<|QU#Z0
p^JB6%t?USB[dSFI[e@jZ7wNdZ?AQBwU#z,%>Rg"3K`f/{+AX(Ao97Z|Nz#;%TiL_V,I<T`bbX1OdSb1:zs6A4o:4!rc4_nOZO*RBVB"crfH2EH>V#[AJke!eo]!bsS&I,9LN.&ZIKg)sc8tVg8@n,/Ee#iG+<,K#c6_uG)r3k(|s3^kP/ZNJ{%8.FXUG!0t)A0<oSYcDZ%G,IK^:
fr)8YN?#&7ZMWj6X=sKfN)],Tr.K9njk;Z,rmW-`eS_e)o,&w9<B_%GB"igx[0OkjQ8("=)xH0"4phlBtF.K(^&=,i_?VN(wV>MReY]x<Kk<F.2eArL9/zg:gN_S#alql,FOz!hU23--]uKJ;u&fjT&6:1BVc2a{MRU".q7{XhwduQ_o#X#}Na9$lr.cXhI7E
]dSTrcq39rnpBKu$P87=QjovjI:Q662rx6I]=$s3Hg>MVA4mO,cC!(Vmj/Y@[Me$tv$ZyRS;gLV3VR2@C;3[NJ9U7qU,eIvL%KH7f.3k*yAM
>_iC}>YUg[ZYqSb(8^:lcs04j$p7S^f.)Gr=C98UPqspwEgH7tfe^s.M|1,QYPr9XhKWg5*35@)<`9wDXA:lYo77C9Vd~:(Ys@dugN==M8z
q`ynlNrt"v=Lhc0TFAqH4,y>K<h?L&/0DI%B`v_TI<CCfa|?{!z,k^/*oEUz%ux>`Fz(kZR!S]1)yGtb?<9BrZ%2u?qO:eM#nJ`k0pJCXt{7&c;_u[ZTIy=B2/:l(hz>@D2PB$fh>E?$8
Tb~B2Mf
g1UOr4=-BvG,BHaK*$k^9a)s(j(Zk>0>A]iQe@Bn;"/Fze$9"4(bq
fn#?>5W_g?yy<w:"HyjDccx?K`>35OmG/2VP)*&XOs^^)OPUeRD7o(#W6DIj
Qt]%:A2zT~czmx3|?;>C
e"x,Ae)u;a6Ay/|)s1YvhD9#ZYEnwpb%3?5UMR/1|MTy%2`nWd4_<L{ZMI@p"KT6AbXFAaUAR_OUAXA
,$dMrmfd~[rycAd)HbqHrSC
*cP]d]s`{FJs7FqXdP9:9%f<a`w0l8:]`bB)&]7&R*UM@Yyj1v/u&)Bjm4/NXR2I7KwN]XjLN3_V~q}#HrH,]>,K
"n)^pC-#2]MJ/ohUTKu5FfE!:u900U&f:wpV3v%5P%4uXX>XEN-
w$VGS}.B5eSiZV)3j~5WpYg<VHX/i%:=/"K]3gY.)L?-/Sq^gzrTMZ`4dvY.G0S!2{#rV"U)>I+e3Yox5|28C3yBqMU-,KPoIx#nWASqy6XKPpLxP.]ge^)(09=t2a1Fv,G74yoq.p/70UU"/H7u.a[YormM,T=~O*[>(xLs#6Z4&CamZ;kIT4wVMXnl+^FvlTX.s2+e$1rP=Fm_>)=Bmff52mD/_HsySD[wa|jbZau<+A5/_*(R8"injc*`iUT#Se=Gim4X(FLpB
fI+es%u50KBDDAe-k:
V;H^[
xFI
j;0Zv:^o,Fx+hL1m)6J#Lw2:g7,
<_?<hxN,kN95ji$BwV_Nx&2*lY5OPjN;6c[P}Xd.E_:25p[7VVW5qcZ8<Ytra:T*DQo<d
bc&:=!(k)$e<pR,E#-Kp+(<+i:fIJ,[oY]%
H>.Qd>`j/W1F.9jgBd4iztUy]D?Os=z%DQp%*.nNaS=jO#O10HaoiLBByfG4/A378&NGuSO^{#-<S,Q",ODTwe)eQ>Qa^/R/M"MSk?-f_[uA3WD+nic5Rv^/vX.s~aN:S.<;I]|e6<>I9=5y@)4$QDFRV"JByeu/;wvch&~rBkobPdIKcYv#)Zgi<Fr:$1vp~Pes-C;Z@^?)&d`
2b0,}kb!Au2FyXeP.-r5mZb0?<J9]!]KoxQ.PKkNaE2dG;&)XlnJ,9h@JtsV/uyy%5!V=YaZY,e!D9f?BbdLS7)AUQt2oK0.z:7v][ZgjCY[9`p]SAkM4*NKx4C99@g.4;mJkY%;M<{R{&Ghhfn
s)q2<h&=UTOKE2n`N?hh+1T;IL`Ax?#m5.;!pY:c@#R)}9$_vY5hU&7LBZz8O?A>bK`tS6m4EJCvI#ll+?N9ymGx2p`
n6L*t0ZoY8?J57~$k"h5`j|jk2x5#N>G}hr*
!(vG&<jm`mwBc)ky0(C3k$n)o*FUGAIe]DtRbJ&B*"/,ebleB6P;+TBkoD[fB%)/FS3SK}RrBIdkHK:U&j+Cr=N3r{"$uz?a3<XMP]l>e{S2e%H8Bm3Ys&@8GE1t!6o{?o:UEU=CMqZ159]%1}4#w;_Z9
"y"lXqY?L1M5I)<+I54pnSfw[sCUj}</yrANNS+t8IxbX9cEG&y>yF)wIxx=W68Z&jXA`?4k]
)uz#kxSnUMGys0xH%tOV>vLQGxL|;jZ#2ulwV8QRxiT^Z0$y4Z1|?]7-Qzl8&?=LN&Jet$7|gb@>ik:mQq`OGRn"9O4iaW(2bb+@$q$S%U^r&bz)uKh4G_%mw/Pt<e7b4jYH]35/L`tlN*e.C!XuN>1bMnP}d_&F=u[PA}NQ(>3.yHt+cyV^yyigSd4/"ioe#y1I=<G"enA)
DdZRWiYyp+OC92O8sy*2p@T=t:Y*b"vL(2GMe+f]=5I*]QT,d,Bp4>3L)PEeQ:@eKp01WZ>Zu0Lf:_MDz08Em6!v41n-;IHvO")_3ndD&SO0=l#-&J1Pj=4PmQr+OGrs%G1o85IsS5w_-j)B7J#akHi=P/@3@r2B|YSG/-|S*s=T}Hwi_U"O}koBVv[OPy@*Q`}69F212DiY4at?rw9$DJegoI:+!kB^9)_x%
sqn$#0I-Imhl.Q[59V;wLI%G^/
_Sx9Aj5?dq$TI.4"qybadd3BpC:G!~PAR&)ugCK@$ujrx-13xgvJL/ifF>?6!UEA!5c6R9mf%jIsuzT*JS-lE{kp,bk@0`=y^6W<OkV($^fb5dAE:>(HfA=c%>:%8u.iER$P8HvL(YLvJkm7y{Wn+,-&hL:@j:_F-+jRg[R:g:gcHneFQR"UQ]ZM3J6.%55q]Ihd-LJnD_5qDAixec$YFhq4>z:*GejAu,rvnKV^XHf6EHT|F]/q@kW#BET{3#rw1u/("dZRhy%uNeBs;btZ[dw"9MVR"A"Du|t>uBit)"5E-1%#6c?bI&R8K_D<Rv!r()xVKR"-$tD^=ctLYT!q,>Al){yM+,%cmX+fOi>1IK+oEt:shQO_+Vk?UFg=2N8>HdYY??&5Q`.x0M"b,%XR4!=x<nD1WcI.>Y&~2At/gtiVw)B&c*XSdb>i3B`2e^R/2bE$Q>U.SG9*9R[bqkR"$.w,Eb]R"1V>t^(=WeS[`<fI`R
EdkcX"?-nv
(|&K3M/Oq3-P;U2sXc=}DEuW&>hu5Lj<(}H#_U"$0oI+NqfOlJBm8x"eS6:yn1;#8|^}`7)%E8#@j#g*v*S0"bFBd6#;XV
kgy4Cn:x_Y<+[!i4"0K9(KI6y6x&=)KM6o*Yg^*j%u`o0c|/=$H6X>7Fesb2XPb^V6!X3p,O#iU)~m}UBI"Z1AKi)nqTxO(Uouw-~g}.0C<V_H0/r"N/YfJJ;uwg|g!(Z8<St;V&P38<V8wuT87^2(fiQos"&l#]A(m&6S4PC+sSz!F#AMVY#P~5RF#.WM$TBKQvjA&3kYC7:w+YE*mU(%;,{Rb#,5mQ>&HYaB$[N1"+Z&5j&d^L#HS)~&-?^=5X|u.+<R[19,[Ptat%AJ_:s[p=vJed8CBxXKu;^,ap^j
fvSXRy.kn8.[odQm,=xjkIoC(i=1/MZQ.75R7
YL^,K_e*Ky*M3F4tUbmpUC3Q4/":MY.Z*
*;6sF:T%9^eBqn$qPHb)XQTW=
ckkQ
66|242<1k^0qGJFtqmMjx2-(Q(@#/h]f2?U1/Hl
5R`Q$oz"{_1M9ZnpFq<qX?>O3;oYhy/q3qvTs&vt8tXvX.xWW%~rdEuOw^b(uLE=
B|!w%PpOc}i{T~17J;0&tD.W/+[_7/uwj}FUE6xH/"=?_kDm
6&r9G,[qLaHAl82;
Xmr!DgN@9D;[g]/0>ZY:Z`M
e8K=isEc9N[:F=&fWq1@GLh->Ar[e^QcNr(2Qx@i;[vX,`u`JM5ft>Zra2^K01OMrcr*2:FBenfz-H@h<}Gr*c(Pw|>YR&JzweQ!US9gNR!.olD|R9qwOTa]E)%6UP(g^i<>:U;;Do&>
*==O-QQGX"HaDDB1S"R0EE68,m9:&^69WNz%h?zL5A/N4*k&
<Re[$!8-C^c<#m<]W%I<d)<p^V9-yVT%(93$F&kFAp%5Q_
|){UwT`()Ti+lrj]Y*:>$[3)ApsT-W}(EFzf?oy1&d-k1
z0,N_"BnJ$Hm3Qr"HN#+goLDQ"Vj<vSVL[SQLb_#/vkX<>
Dq<90,WD0DvW+Q7B3`wciYoFJ*:6kt]TKjaBGR[8a~(!s}38T+[d]0#7Z-pU/08Fbhvbwx,Oj{!r!"@mF-kacv#"0epqT|E"&wOo.gJ[G[3LE/)$#J5^IKTQ?
Nc!;C!bWj|>xvT$n@xoQ-xg<6=O-%@62Zch@^kC
2eU5]=:8jA%4+=[b)Qm+-9mfDV5h5w36pYMt4a9%&&6>HursVFK:]Adc<l!mH1c5"G_r9fig)O;a0ngl#Z8pqTQ"kdu"dtKD)2,NB!CiD+)H@d>X]OEFa938T77y[9J`pb/:RR2s)[#PwE.W
[,A+.%-0ISFM{R49P2gw5SjwN/r(ZW3RbMOJrKz,P:MOa;@_DYNWs-T+v8/)R>k4
x!@jW-XLVuq1h>,}R_j#_<2+-fAD&>
Xi4NBf&fAuBQd4M8ub#dqmp"D`vX-Qv%QUP^:%sXIX"[;r"mm-L*ry]2b/Hf!,[/I..Tr${Q5V1[x2=Hkkm81sR!|8l>
!)6nj2$9by.
&w>&L)U|$9`6-5$1TySZECbC1qTJJ%RK5wdz=nYE.4:pGD&^fRK{pC(fWY-aj-r:93[2_j#yXTtP&V_FP%FcCo#KZd%t^aWY]|AZJ[C7.G5jwhikv8t2YJNHbEbTn]:O?E-H(7*G
X>oP0t.ZQ(*s:#Cv0eGw}s/5TuEmEMO@uE:jOpEU.[K/o*nfR0*Po8gt{06;+RRQn`bL8Sk:k=e5o+bE*#-0XWJf?4fyNY:r
)8(#K7m{,MtdFY+o.HdS<@*pyZ4
G3Z42R5ACHpUx2dV5ln8`L`0STgBh,"xC)_l%b4ce(ACB{A`66KOQ[blxN6P-LBGiKJuVSrX:s6eHZ*1u,p"a!Oc?,t/XSL8iYq5e<D?!t=*iti,NWWx)B)}ai%K8snKBU%u*~k~<8VNbOXlF:?_AX,m8=]Z1a"y>^`U.Y>]39/U_j(-T8^f+z#52_[MAk1F`^YgFV(5?!<E5>eibvhrRG;;,91`Y2/4hr]fvpO~">&L(>"YVQp])NVL3!(Iw&;4;uuU#o;?1-d2R.2mm{_iGDVzVVmE#gVYb7+~8-ToUxqMf%LR3%"^9+4l7>_3F1/aRV&vxFHbdnEI=<Yq`
Crt31MU[sL%0f0TTaqZ+h?Uzu"PMLi
/"A+]yTN-o$F%cc32G1EUE*[(+L]0!,&cT?uU1LxJ*)T:6aj%qQK<Fh.g7}fx(VBO1pT4T5huCN;g#_,aBm$5i`-~6:Uo,hJ~JF)l7gDCd;X{kw,tH^Nz/|G*_B(-#QfY1jQjO7S<sVP9-g=5,
bhI;ya_&qr?7XGVj1I(U7(0(_mbHHdsu<m0`).+`#9li@I8jUxGL#Mya!bU,t;@41Gue8>277B:X)S8/#*"(jZtf^o6*2st-oWGx2b$6:3SuI72=Ut>PQQI9Y`?|ur8^kK[9i63[GINYD/fp7a&R*:;]ARXEqQ9t_`&&ZlUhsIAuTM
"jTXI#.J/w`n*#N_Et,#Dy[TD?)mAty>*gs-)ivy0-N)JF7)lhD7[eeQ3v/3J2c_S-m;iuH*
ay<z88E~)h$pxY%Ij&Y|jD;u[=I3V^Xf5@b~0@:B.>df<4_Lv$Fy$~0H@|%B$$88?.nPABYcIP%#y6)g$%cm.~@9;/7C5IH,9zOG%ZmmW7OOo:iYY?*W9b8i+]$*9#X3&|]EdN5ASno.@i"t"PS]).ZV1Y@!s9H,x,qgcXW<r9P7/x8|
1.WnhZifgNi1{j4[;q2;,Vp!:rfO6pGakefB4,?oo(gC|tpBw+atO%Y(x_*MZf0]Eo
YbA.fvK2*h!@bKj(0rfN$uGrq0Ak!:Lm;HVHIGh(#._%DP/Zp}V_`~lO0/a}w!"FW{/dAl4LaK*@$x7wAk#A,<ixJk3zm4k:ngB(5&C(ap6vIJpd#M2V.0.0GQ@.mmG`sgZB>!BRF"Fz!%-;5TOns"d<_Yx[Rp-:B8/k;Omx:E0P$jg7`SgE$!Rub"1DE3GTGJAL"<o.=j6`H2N,Ka8?cwGv)NPoqQVmT,[)N@8;Vu3mt:NhRg^xck,M[PX5toUb5SABsx`s#qKKCS
sJ_%RMInQ,@Q}krwZ*`K`D.h1n.u9/LwN1"@GX&kW/WKk4HS-$WxkAC-M]N;(La;%F<8Pr#%-kKDbh<:s$pITDA.D:qtNWiUHACD-ujsj`c*J4vU;3DYPo/5NoQ?R=j`LOs*qu]efc1S=s]
l3f9|
B?8j+K[K{HANp->$0yS*.$AT&bUA5XTlfS,=M<GTZ7Y`OSk+j;wKpjfhAu
A;+DuNTs+|7<tZNv$o/
Y`BtXN$r-+1%fu$v2AsO_72tHx$Se
H1e]-bQ@<=vF.{2|&xWy
h7(q[
=wV3+N`w:5D&yfT%
-}gr,iPtZ(JZ)Uk6$-8>020/K{>tkJeFDZVK
$u?Elvwft=jP.1fCf?53o"e_L!hU{f7Ca]$1Ao@pN+3fv-k:yA6A8`67]^#E&hrO2-C_u#jT<W@&?Z74#lF
lt*$En}if/bIi>N3PUsmHdrm:badogU7]>`MBlnv]&}
^V%hIET).bCAmtODX0cLs2QLEY6u^.)"h1I#klRbq99CGmEqC)%<U&hP1hfb18UYE?TW7YpAzpN0}lTD[eZ]_Z%I0_QBP79I@X1]i`T_$_%e4Ln`A?NVTIm.&+PB4_Y2-mNsquYF@4,@Kh$lY$l.X(T@Aeg[A]O3W"=!&[AAqn45#(4U~ijq0[D>d+L@6L|g7OoVMT1PuL75/BQ6xOL)Y4:2LB`^p=X3fu(Y~))9l%iJ.:2
1fAU~<rK0.h>TC<w)m-6jJ+[!Ypf/s1M8u3J-%I)`!,5fN}P"^L9o-mT@"Gnqi}3}?DAU$[ZLX8MXvi`6l6Ji51Y2^6Dw3)3*DaFm-Sj{h0gD=}GChYe;c6ndc!dhE0-
[)H12rFX-I*`H>",hO%q+&(9SkPp>&@kR%/{B$d>y@TB6>ED;Y3,BCDq>j*]bm.iK@J]c7vB`h4itNYMKK[(#=b)0<IEgzVEF(wlfwf$40Un@0k}
S*kdnLS&+H&bfHAk#>>B1g,R:WSswY8M"/c"t05I"
Jfp#_9I1W_H4n3M;r@V
:j7C|ER4427E+]BZmT+C&;0@|#?U"oK7x8]-z]{66GKZ%dT&y!j[8u2!Gly%hwkh4%"@_XInGDxp
1K&oUm;.AQc;t03AVr4dT$7/E!:CWaWKDW<"xqio]5)Sna<8P@b_wF#31dHlKO1RQ8O$BU3EDRM6YJIoNCacE>:{$}I#U1B/QUb;])L=oJD_hK@(tzKgK.oS0
M99YRX3M=MoM>rxanh8SWT";SV3P_W^KC@?4JgCS9W5$owtmP<i{yc<%yQw,W=cDYrG}lz=*N@18p*X5gr3c:we.jpiho!o:L^JF/hI94bf+epOe`Msc
+H_nP-92KHia!Uf3qAtiCi.^lm^M>%Dq&-q`r1qbAXM/3h-avEQ.7o:3StQt%_C(=C*
oc^ae8aXw+>5HjUX%j9+W
$FIjt=LoZyB7mKfL#76Ze0WwUvx_9S,G.`E4QtMy96"*"ma&7Yg!-m7ne0*Ws.P:53K2ed/Qv+)w2m0g>Gy?oU>1to(rW?=t4eZO#;`2h+x(Fsrh<M7(*9HN#uGLw!Npc0MT^m`1l+ppSTX?ib+Hb"F`+!`V|2pmW^Pt8Bsj=!$F<=gy)0[!S]S)wRA*F03%SPn!w@d88A7hv7_yH1F(v;3+y.Q%[vh7|ex=}R%0:;~do6Qo?j#L)U{sJVN5.!{,%D9;~%(K~"K%A`{Q-T=g$$#p:;[EbxniJM(%KTj%,Tx$EHfH@Y8B/9+B^T10$Bn=2R!%1/V79#U%D^"5pTC3{2|l<?x.
J.%K"js$Bl!p:~wQ`4[z#!5>sTmtN):>:J85^k:1LDepyiQ?e-.qV|q#sro4CtPRA4ZKIOblX8v1T=SMwgxs-8NAhhtBV?NCVt5DTs9Qd#/?
qa~=5NcZwLe;)5ol]N&,5wi"9^IVJKU;{Ugu=!2p~6A8IB?ccDrI+f3E*g3lbF/8jRN#Pk(gU=X=aqMrIw8Nww=lS&}/#;Z2a`x%Xfxk
.N=D_Xj"^mDhe.#&"%g<!xZ:AApv#]Gq2(^(Kz9b$f_yi~u~et5%[mc/md99:p]$LyPruu?z^Yp]rO0+A5uGS3u{lQ1>L,xM[[m7Gf?P-y
`b~X+"VX-Z.1E*%y%f$<gdQ9PS__X[X,Y3y_v)i<kU%&v3:fF+_obVaE7PcYB9Mjp!ld*Y~/IW-5`:CT{eqTh#WQwynML2XF*P&kL.$)1;)J<>[-4(uA"8<imnSc/4+IYX}:<*tsAsp,k<:OzVY0p>^<h(AOZ/K/vo6,ye<ICwaS~PsGlBjPXSwI)S9D7@b"PEJvMVm[Rc2:~xim(sl$LGnvRWu?VAegPW42}?]?)ILel_e
b]s#"+%ET9IirSx%U&Q<v"h-sgb8Ho486;g1^;/b9Y@9AW&<PQ#_K;pAsVACyfW$)$U5W%{2gXG^wG&F01,`B=uWtN!8`&*C5h"KC!duZ4Y,Ipws8KF$0KQyOIp?y;dpjZqI!yaTk,,221LE#7[iBQFfMxIW3?bD|%Utfp)-EG/wcTU&`&SI$1I<vI8HbNlBPI`!?8nakS^hecS_OogJQ2^6AVT8`;j@ki:9]F9UgxmbM,V
qxdm7YVX*(x0^8r<tOX]]yYWlJ*8nC>(}!hV"-Gml&4wjLTr,Eh1!?#5Q2iQ8bcUBsvvYi%Pl$Qv_;h1+:0ZQKFW5x~>gGu5PM8m}V~LJ!~F7G%.a";^i2HS&#n&#)i;}F[POX}v>?#"qql-Qrc99tu&orpt_l~y-Sm=)/{9jiV`%;XE6#
k!Z3t>e]i8SO1=b90j(c3},v/Q:2!Pswa:>["R3]qQX<$@Nlt#J>uJ$vF}KoQ6f$-9MS*P#NC1bB[=nG.7%rKPrBd>4Ze<sdEN`:d6w%ZkL|s$WYmu>>60hENMNWZ%Q)ZL<K["a[Ldoa2^"sf]Q;FM(u@d3n!0eLaaH-]>D[33Pab7Wl>(qyYr_SV|"f:SJX_hdY*H_3M.^[O8*rgLUm_
^~NLuriB0Jj!3_X=KrIeDiEF#oFb992!Y3A6jA/D+1%D!~>.e!A0hx>JH~qxuUZ>=Rj&!g2w)VKWy:oJv4%(h+&@K;@D69p9#;7.Slj1,%uONCTiP0?"@tn9Vdv7#1xJd[[b%$rU/+?.Mx9gU})74h@FPDwj!!_>ncL|2=[G1TgJmuaeZ(Jpj<.G+nrRhh5<D
JL-TGdxI&Hh35f3b$LU_ie*4sFd;uFPgT*?`Z
:ZZLeM#[_]H%%ht$hXw#($Zdx0d.Xu3[&&;i6sTD:Op{%y%bMiU3km)[W7"P5waM_-2G"11m22EH5d`;f`q&<:@B7K*i,.1rh}5o)!i/2.1"S0%KM@3#>;h`kN4pQ4a`&"owgaf+1^Ps.FNNHDgCczlAjTKT"UnF-|.j2E=U!ki10(nf$]rWk[J$()ceB*8BhUI1M3&18+*zi_O]M>lA5
j4Sm2dk%(^R(K;_88#fJM5j$tY>39+M<Sts3g6<wX8V=G[k%S85cO?:;;l#}Uy(%(CqZh{mQE_z#wODrxN
?_o,c&&@RD-[%0Jd.0Jb.t3jx>B7)CnLd5[idJ#4&"l0s&0^fWFep)1$J2!elo;TXxkT6DK-O+x"<fQsV0[wsSa14(8yPQ%tf6[Hk8pX[C}GWol"rkIOz=xI3ZYN&M9L@@("myD651IHVd.STw4BEgx5f2hG[*[`6[?;NT)$irZUb9~CWIGm!TiyZI!PX7$_*MV2|<il?oZRQ.^+Tm-*Dj+phS(Q%a$[r0Q18J{;>;_k>eGj#f=e^eM$rym[{0SsoUd1z+I;s47O,6&@PGE/rY|U&5n[T<C`xY@Zi7kIe?E3(te$qc0i(
pfaggD(J<(RScd}FSH
6"oc<x
I(&/845o[QE:BPiMKal/5QGr?%emYKp7LT3(ddWe7uac8;jQch#w`eCqA)()jgd(jokM*_!C|,Zm9"px/q}u99AwRo]V(`EOL.,,neCwd3E4}u|U003^.Hs5sit;jBOr=Keg}_|@Hlx[>B]1Y8r3(Wd6r&oC}keso1q2PDF3i)
EN+*#$:Mk9FK]cvjuAF7"`p0CGD]hte"b]B"?qb;*F,%5in"*?ONj:/9$Qav?$x=JV8I[(J[Hix8?Q(DenODE-&/j$-qZEQ*5(c}BeWV/pl{.?<lnaBUU2NJm@n)FY$svKB%iWK.yUk2s.7N[iXm):fUZQ?vS
lw:vMsRx4bdWl5SCNu4rW"j^&`42%U%#E|05=<O.CNi;t"3*2qJ0$;EdYo4H*VqpQqMe!zZ>/5k?o7@9?OR"ICYd
rp;Q3#]a&7
75P*!
b<$jXN"hm>xX!=]h;7qve7s}K>]IJzR./9AY!)]W7=1xEr?Q"xJHIb&D8:F]7R"B6$KrO~ZUp4;=]G=XM|#jb)-DYm&!
BfyC>DGM+=yoUUmKE>=).;9!Bhf
)Mi!Cb.7KkpIp?lfSx)(i#:(=bVI/N;N<fzxg+yyBV:bTJb.k
RS"TXckKC+DLk(~UfWtW?N(gmwh[VbxvCSy;
YDpk-yR90w$fZksrR_qr(x.88&k|>m@dL|[{F<8m)4E~UdV^hr5$%TF#N]KmL_R02,"Sb?d$mq]EHy3h&B6XW_!qIq(>inhh
6%M))EY=mV]8Ag6e5L$&j&Ate/5kD(^#qmnE_]c+*D"2x(*v`7Qu#)55SMFo16/kSpU0/UL#J!
/:S>PN:<4_bTad;,Yx<|vtwY=N
8Kr$`7&qy6mpymzOgABN1$gQwFmQ/;;.KbyJKHal=P!m9Kg5_Ib`@)9uf@87mjrIvC7m&Nj[@[gYR4LbI5=)eegRoT3;W1+=1?EkcIwye
u,M
c4G>U4mmDt|>baz48svV[m
_:uedm+a)tH$=B;J[-d,xAY7R>2Tr+S2t&>:IS&r6:S}YD-I*Ril4;MA5c,`n|DItMZzJ./y*?JPO}]gQi?T]!it%1onwXa?
DgMT6echV-&wk#].r)C19Q*vGDs+MF2FvBFLxuB:8[k?P.KCB%NWy>^i,2-&wf3wxRW0!IK0`7h_9RHq|5o1svk;`@#DHp1yo/uoGCIO8;X:#<.]}",q+:899TE
mRBU%?OexX]g8#d*ESc+UZ(dJh[m_)Iot^$h@Ye4y=#yKU}@{ari`hDeSX|P{#WbIn+:wy2ut8E<xXI++gleoNax+(eI3C@C*I7-;B2:~ZX`e9wa3&8QZb>i[t,t}UqKkD)X:ghCV#+%fP<2:W.Q=rg(~jsk.KtuG8T<4<ZFaO]vxkHQC-79qg{*M[WCF!l#q`rZ;O@W/2K^.UKPD=Ac`"3Pb#V0u=XU^Ul:,p1GHWjptXA1TII(5@!C+JePoQfQ30O_sV]8^xCU:
YWRvfO7BQIssRXh-2mSBuu~bIp
E9
i
Tm.E+3#w/o:a"BmUO_^?dV[`Q?PnT&M_43=:IGc/N1>s"NJRQrj"!nXOp]WF!2!H"3[Qf*xrKUzM,y!&KB/!+NMXgf<>TB@._`zHzZOvp;vPH,Tx
j5!c1a>V;Rm3*^+ko_0eb13loxlUr$f=rn5QWfo
V9<0kYb*L8kc
J_n$/8Pi%]Py}T}2*E?o"^7<4oKdG`nSl8%PZFP^A=,=}CaDZj"&4Dv6Q;#cxf,so/$+oRnRp$7/*@]c/i7S&
@_n)[A#:3v@+JA#n.J48FSjG
"7w7s#5
wHiq+r"+7tK=');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*M-:_crV?&ivhwW00>hyk#FBR?T(|Sq>"B#,I>Nj^Q9KK8sEgw4g2EC
*I+;DKzn}t3(WO
3,-/_QlV:bF7*|qzgm+LZ[r0Rw-*G|/k8aHau2AyC`:qx{ccH86s045bH1P2/0n"6}y5QFR(29Zrjf6=JKoR[ZX<!#*8i<5q.ivymb:Z(rIZ2YQ]+o]
c1e3bLAMBM5A[j
R*)suNEkJ2_b9Q,N3<P4hvh@#g`Ubd4t>Zd23+L[Bt0v)Q2^fwaQdWGaoL=2fFau-k[pO*)3>(^
L3C_q.O7n@ic/h_PH^?3P(D2iqS^rMAuO_swO`)*TiGN,)~(n+#u:.`d2HKi,UCsH@.]A%*j08LgKJ|@kX+bbB8Zu(St;xKfJ=}=9(nou8]":5u&EO~jfR@9f.[9)!m!m>c&!6F6vOL1`]MawSXj)67Xp@ygy7)9*q,X#[h/L6mfb@W@"#ngdi7B#e$3RlPyr[q*H-nfIGG."G="QLE1bbI!fYOm7erh[>m4s7/_nwA');}elseif($_GET["file"]=="logo.svg"){header("Content-Type: image/svg+xml");echo
decompress_string('%s_VkbOV?&!t"do^rQ`p`ZU/yp&Ye3upHt|/H&y4sA3gG1#^TM/psE"F.!L"b-TOg,=_&!?SQvUpK1NG?UVTm6[[a>X*ZfBqY!LKF
fO{QWHay6P%Mxk-@i/qV|wo57>CjpjQuWGGgYH{O@sDx@a=t3J8^4xXkLUkz!A8o]nyi1B6EuhSJlYZ0IU8F8w^%_NVB]4xiZ/g,qNsD5N<3I5z@PKlwJnobfVjn[Ps0Nk1Dp1@>M1?3j7#!a_W13^gLnlX:$#{3
8i+/0=Y3h6/1,{i{28SyT]@Eu=r"Cz(I8et3V~F)%#.@C6^yX&2~ff.YQQ(5WnhDY<DSV20%H2f?Um0n)kC6+)X&<0DhT=GdXfG>W}N
_itFLhYXgQ-?9$q+dW7/s)Vvp*s9<u=9Wu"5]B@h-)l%Z0$vcYCQ:>M#CF$ONU$8f3.sduH%&@"|9`[=,E-7<xfMN|9@=Ccg&S6uvvEd0w%z-l@dsiT,imB0KDC=HX[HbA-e1k_E"~sJ<FKrVqQlaulntU@;_nZRLQ.qyk*ch&y@KSbULF^1JuDW`W+bWA."U,D&Z89.[5Y.EDYJ$A]=t5LNi>n}`Oc
*0;=MJ_8W,XQa$w^=ohbWF!H30]5ctm(Bky7@-Lh7OogMB"*');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$Gi=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$Gi=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($Gi["bytes_processed"])?array($Gi["bytes_processed"],$Gi["content_length"]):array());exit;}if(function_exists('session_status')?session_status()==PHP_SESSION_NONE:!defined("SID")){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(array('lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax'));else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$Cd);$_POST=remove_slashes($_POST,$Cd);$_COOKIE=remove_slashes($_COOKIE,$Cd);}if(function_exists("get_magic_quotes_runtime")&&get_magic_quotes_runtime())set_magic_quotes_runtime(false);if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",PHP_VERSION_ID>=70100?-1:16);function
lang($t,$ah=null){$ya=func_get_args();$ya[0]=$t;return
call_user_func_array('Adminer\lang_format',$ya);}function
lang_format($ll,$ah=null){if(is_array($ll)){$G=($ah==1?0:1);$ll=$ll[$G];}$ll=str_replace("'",'’',$ll);$ya=func_get_args();array_shift($ya);$Md=str_replace("%d","%s",$ll);if($Md!=$ll)$ya[0]=format_number($ah);return
vsprintf($Md,$ya);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$O,$V,$F);abstract
function
quote($Q);abstract
function
select_db($Xb);abstract
function
query($H,$xl=false);function
multi_query($H){return$this->multi=$this->query($H);}function
store_result(){return$this->multi;}function
next_result(){return
false;}function
inTransaction(){return
false;}function
begin(){return!!$this->query("BEGIN");}function
commit(){return!!$this->query("COMMIT");}function
rollback(){return!!$this->query("ROLLBACK");}}if(extension_loaded('pdo')){abstract
class
PdoDb
extends
SqlDb{protected$pdo;function
dsn($Gc,$V,$F,array$C=array(),$mb='PDO'){$C[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$C[\PDO::ATTR_STATEMENT_CLASS]=array('Adminer\PdoResult');try{$this->pdo=new$mb($Gc,$V,$F,$C);}catch(\Exception$fd){return$fd->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($H,$xl=false){$I=$this->pdo->query($H);$this->error="";if(!$I)return$this->store_error(false);$this->store_result($I);return$I;}private
function
store_error($J){if(!$J){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error='Unknown error.';}return$J;}function
store_result($I=null){if(!$I){$I=$this->multi;if(!$I)return
false;}if($I->columnCount()){$I->num_rows=$I->rowCount();return$I;}$this->affected_rows=$I->rowCount();return
true;}function
next_result(){$I=$this->multi;if(!is_object($I))return
false;$I->_offset=0;return@$I->nextRowset();}function
inTransaction(){return$this->pdo->inTransaction();}function
begin(){return$this->store_error($this->pdo->beginTransaction());}function
commit(){return!$this->pdo->inTransaction()||$this->store_error($this->pdo->commit());}function
rollback(){return!$this->pdo->inTransaction()||$this->store_error($this->pdo->rollBack());}}class
PdoResult
extends
\PDOStatement{var$_offset=0,$num_rows;function
fetch_assoc(){return$this->fetch_array(\PDO::FETCH_ASSOC);}function
fetch_row(){return$this->fetch_array(\PDO::FETCH_NUM);}private
function
fetch_array($_g){$J=$this->fetch($_g);return($J?array_map(array($this,'normalize'),$J):$J);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);if(PHP_VERSION_ID<70100&&is_float($X)&&is_finite($X)){for($vi=15;$vi<17;$vi++){$J=sprintf("%.$vi"."G",$X);if((float)$J===$X)return$J;}return
sprintf("%.17G",$X);}return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){return(object)$this->getColumnMeta($this->_offset++);}function
seek($hh){for($r=0;$r<$hh;$r++)$this->fetch();}}}function
add_driver($s,$B){SqlDriver::$drivers[$s]=$B;}function
get_driver($s){return
SqlDriver::$drivers[$s];}abstract
class
SqlDriver{static$instance;static$drivers=array();static$extensions=array();static$jush;static$passwords=true;static$serverSchemes=array();static$serverPorts=array();static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=array();var$delimiter=";";var$insertFunctions=array();var$editFunctions=array();var$unsigned=array();var$fulltextOperator="AGAINST";var$functions=array();var$grouping=array();var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=array();var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=array();var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$lk){$Kk=array();foreach($T
as$R=>$mk){if(!$mk["dependent"])$Kk[$R]=array();}foreach(driver()->allFields()as$R=>$l){foreach($l
as$k)$Kk[$R][]=$k["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($Kk).", ".json_encode($lk).")";}static
function
connect($O,$V,$F){if(static::$serverFile)$Zh=server_parts(array("path"=>$O));else{$Zh=parse_server($O);if(!$Zh||($Zh["scheme"]&&!in_array($Zh["scheme"],static::$serverSchemes))||($Zh["socket"]&&!static::$serverSocket)||($Zh["path"]&&!static::$serverPath)||(substr($Zh["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($Zh["port"]!=""&&($Zh["port"]>65535||($Zh["port"]<1024&&!in_array($Zh["port"],static::$serverPorts))))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($Zh,$V,$F)?:$e);}static
function
disconnect(){}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$k){}function
unconvertFunction(array$k){}function
select($R,array$N,array$Z,array$q,array$D=array(),$y=1,$E=0,$Ai=false){$if=(count($q)<count($N));$H=adminer()->selectQueryBuild($N,$Z,$q,$D,$y,$E);if(!$H)$H="SELECT".limit(($_GET["page"]!="last"&&$y&&$q&&$if&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$N)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($q&&$if?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):""),$y,($E?$y*$E:0),"\n");$this->query=$H;$jk=microtime(true);$J=$this->conn->query($H,(!$y&&!$Ai?1:0));if($Ai)echo
adminer()->selectQuery($H,$jk,!$J);return$J;}function
delete($R,$Ji,$y=0){$H="FROM ".table($R);return
queries("DELETE".($y?limit1($R,$H,$Ji):" $H$Ji"));}function
update($R,array$P,$Ji,$y=0,$Gj="\n"){$Xl=array();foreach($P
as$w=>$X)$Xl[]="$w = $X";$H=table($R)." SET$Gj".implode(",$Gj",$Xl);return
queries("UPDATE".($y?limit1($R,$H,$Ji,$Gj):" $H$Ji"));}function
insert($R,array$P){return
queries("INSERT INTO ".table($R).($P?" (".implode(", ",array_keys($P)).")\nVALUES (".implode(", ",$P).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$L,array$_i){foreach($L
as$P){$Z=array();foreach($P
as$w=>$X){if(isset($_i[idf_unescape($w)]))$Z[]="$w = $X";}if(!($Z&&$this->update($R,$P," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$P))return
false;}return
true;}function
begin(){remember_query("BEGIN");return$this->conn->begin();}function
commit(){remember_query("COMMIT");return$this->conn->commit();}function
rollback(){remember_query("ROLLBACK");return$this->conn->rollback();}function
slowQuery($H,$Yk){}function
operators($_k){return
array();}function
convertSearch($t,array$X,array$k){return$t;}function
value($X,array$k){return(method_exists($this->conn,'value')?$this->conn->value($X,$k):$X);}function
quoteBinary($sj){return
q($sj);}function
md5($c,array$k){}function
typeName(\stdClass$k){return(isset($k->native_type)?$k->native_type:"");}function
warnings(){}function
tableHelp($B,$nf=false){}function
inheritsFrom($R){return
array();}function
inheritedTables($R){return
array();}function
partitionsInfo($R){return
array();}function
hasCStyleEscapes(){return
false;}function
hasEstimatedRows(){return
false;}function
isSystem($h,$M=""){return
information_schema($h,$M);}function
lineComment(){return"--";}function
engines(){return
array();}function
supportsIndex(array$S){return!is_view($S);}function
supportsAlterIndex(array$S){return
true;}function
supportsAlterTable(array$_k){return
true;}function
indexAlgorithms(array$_k){return
array();}function
indexOpclasses(){return
array();}function
shadowTables($R){return
array();}function
routineTriggers($B){return
array();}function
fulltextSql($B,array$u,$H,$Sa){return"MATCH (".implode(", ",array_map('Adminer\idf_escape',$u["columns"])).") AGAINST (".q($H).($Sa?" IN BOOLEAN MODE":"").")";}function
checkConstraints($R){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->conn->flavor=='maria'?" AND c.TABLE_NAME = ".q($R):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($R).(JUSH=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->conn);}function
allFields(){$J=array();if(JUSH!='sql'&&JUSH!='mssql'){foreach(tables_list()as$R=>$U)$J[$R]=array_values(fields($R));}elseif(DB!=""){foreach(get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	".(JUSH=='sql'?"c.COLUMN_KEY = 'PRI'":"k.COLUMN_NAME")." AS ".idf_escape("primary")."
FROM INFORMATION_SCHEMA.COLUMNS c".(JUSH=='sql'?"":"
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME")."
WHERE c.TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",$this->conn)as$K){$K["null"]=($K["nullable"]=="YES");$J[$K["tab"]][]=$K;}}return$J;}}class
Adminer{static$instance;var$error='';function
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.2+9548328c")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
credentials(){return
array(SERVER,$_GET["username"],get_password());}function
connectSsl(){}function
permanentLogin($Kb=false){return
password_file($Kb);}function
bruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
verifyLoginToken(){return
true;}function
serverName($O){return
h($O);}function
database(){return
DB;}function
databases($Hd=true){return
get_databases($Hd);}function
pluginsLinks(){}function
operators($_k=null){return
driver()->operators($_k);}function
schemas(){$J=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$J))array_unshift($J,$_GET["ns"]);return$J;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Ob){return$Ob;}function
verifyVersion(){return
true;}function
serviceWorker(){service_worker();}function
manifest(){$_e=$_SERVER["HTTP_HOST"]?:$_SERVER["SERVER_NAME"];$Ej=preg_replace('~\?.*~','',ME)?:'.';return
array('name'=>"Adminer".($_e!=""?" - $_e":""),'short_name'=>'Adminer','description'=>'Database management in a single PHP file','start_url'=>$Ej,'scope'=>$Ej,'display'=>'minimal-ui','icons'=>array(array('src'=>preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.2+9548328c",'sizes'=>'any','type'=>'image/svg+xml')),);}function
head($Tb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$J=array();foreach(array("","-dark")as$_g){$m="adminer$_g.css";if(file_exists($m)){$zd=file_get_contents($m);$J["$m?v=".crc32($zd)]=($_g?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$zd)?'':'light'));}}return$J;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($B,$re,$Y){return$re.$Y."\n";}function
login($Sf,$F){if($F=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($F);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($F);return
true;}function
tableName(array$_k){return
h($_k["Name"]);}function
fieldName(array$k,$D=0){$U=$k["full_type"].($k["null"]?" NULL":"");$vb=$k["comment"];return'<span title="'.h($U.($vb!=""?($U?": ":"").$vb:'')).'">'.h($k["field"]).'</span>';}function
commentValue($U,$vb){if($vb==""||$U=='SCHEMA'||$U=='TABLE'||$U=='COLUMN')return
h($vb);$ui=function($sj,$Ya='td'){return
preg_replace('~^~m','<tr>',preg_replace('~\|~',"<$Ya>",preg_replace('~\|$~m',"",rtrim($sj))));};$R='(\+--[-+]+\+\n)';$K='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$K$R?($K*)$R?~m",function($A)use($ui){return"<table>\n".($A[1]?"<thead>".$ui($A[2],'th')."<tbody>\n":$ui($A[2])).$ui($A[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($vb))))."</pre>\n";}function
commentInput($U,$b,$vb){$Y=h($vb);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$_k,$P=""){$B=$_k["Name"];echo'<p class="links">';$Of=array();if($B!="")$Of["select"]='Select data';if(support("table")||support("indexes"))$Of["table"]='Show structure';if(support("table")){if(is_view($_k)){if(support("view"))$Of["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$B!="")$Of["create"]='Alter table';}if($P!==null)$Of["edit"]='New item';foreach($Of
as$w=>$X)echo" <a href='".h(ME)."$w=".url_escape($B).($w=="edit"?$P:"")."'".bold(isset($_GET[$w])).">$X</a>";echo"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$zk){return
array();}function
backwardKeysPrint(array$Ja,array$K){}function
selectQuery($H,$jk,$sd=false){$J="\n";if(!$sd&&($fm=driver()->warnings())){$s="warnings";$J=", <a href='#$s' class='toggle'>".'Warnings'."</a>"."$J<div id='$s' class='hidden'>\n$fm</div>\n";}return"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>(".format_time($jk).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($H)."' class='hover'>".'Edit'."</a>":"").$J;}function
sqlCommandQuery($H){return
shorten_utf8(trim($H),1000);}function
sqlPrintAfter(){}function
sqlUrlLength(){return
2000;}function
explain(Db$e,$H,array$Bh){$I=explain($e,$H);if(!$I)return"";ob_start();print_select_result($I,$e,$Bh);return
ob_get_clean();}function
rowDescription($R){return"";}function
rowDescriptions(array$L,array$Kd){return$L;}function
selectLink($X,array$k){}function
selectVal($X,$z,array$k,$Hh){$J=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$k["type"])&&!preg_match("~var~",$k["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$k["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($k)&&!is_utf8($X))$J="<i>".lang_format(array('%d byte','%d bytes'),strlen($Hh))."</i>";return($z?"<a href='".h($z)."'".(is_url($z)?target_blank():"").">$J</a>":$J);}function
editVal($X,array$k){return$X;}function
config(){return
array();}function
tableStructurePrint(array$l,$_k=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<th>".'Type'.(support("comment")?"<th>".'Comment':"")."<tbody>\n";$Rl=(support("type")?types():array());foreach($l
as$k){echo"<tr><th>".h($k["field"]);$U=h($k["full_type"]);$qb=h($k["collation"]);echo"<td><span title='$qb'>".(in_array($U,$Rl)?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($qb&&isset($_k["Collation"])&&$qb!=$_k["Collation"]?" $qb":""))."</span>",($k["null"]?" <i>NULL</i>":""),($k["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($k["default"])?" <span title='".'Default value'."'>[<b>".($k["generated"]?"<code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($k["default"])),80,"</code>"):h($k["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$k["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$v,array$_k){$Rh=false;foreach($v
as$B=>$u)$Rh|=!!$u["partial"];echo"<table>\n";$dc=first(driver()->indexAlgorithms($_k));foreach($v
as$B=>$u){ksort($u["columns"]);$Ai=array();foreach($u["columns"]as$w=>$X)$Ai[]="<i>".h($X)."</i>".($u["lengths"][$w]?"(".h($u["lengths"][$w]).")":"").($u["descs"][$w]?" DESC":"");echo"<tr title='".h($B)."'>","<th>".h($u["type"]).($dc&&$u['algorithm']!=$dc?" (".h($u['algorithm']).")":""),"<td>".implode(", ",$Ai);if($Rh)echo"<td>".($u['partial']?"<code class='jush-".JUSH."'>WHERE ".h($u['partial']):"");echo"\n";}echo"</table>\n";}function
namePattern($U){if($U=="FOREIGN"||$U=="CHECK")return"";if($U=="TRIGGER")return"{table}_{timing}{event}";return(JUSH=="sql"?"":"{table}_")."{columns}";}function
selectColumnInput($b,array$d,$Y="",$ji=""){return
select_input($b,$d,$Y,$ji);}function
selectColumnsPrint(array$N,array$d){print_fieldset("select",'Select',$N);$r=0;$N[""]=array();foreach($N
as$w=>$X){$X=idx($_GET["columns"],$w,array());$c=adminer()->selectColumnInput(" name='columns[$r][col]' data-default=''".on('change',($w!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$r][fun]",array(-1=>"")+array_filter(array('Functions'=>driver()->functions,'Aggregation'=>driver()->grouping)),$X["fun"]," data-default=''".on('change',($w!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$r++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$v,$_k=null){print_fieldset("search",'Search',$Z);foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$u["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$r]' value='".h(idx($_GET["fulltext"],$r))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$r]",1,isset($_GET["boolean"][$r]),"BOOL"):''),"</div>\n";}$uh=adminer()->operators($_k);foreach(array_merge((array)$_GET["where"],array(array()))as$r=>$X){if(!$X||(("$X[col]$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$uh)))echo"<div>".adminer()->selectColumnInput(" name='where[$r][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$r][op]",$uh,$X["op"]," data-default='".h(first($uh))."'".on('change','selectFirstChange')),"<input type='search' name='where[$r][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$D,array$d,array$v){print_fieldset("sort",'Sort',$D);$r=0;foreach((array)$_GET["order"]as$w=>$X){if($X!=""){echo"<div>".adminer()->selectColumnInput(" name='order[$r]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$r]",1,isset($_GET["desc"][$w]),'descending')."</div>\n";$r++;}}echo"<div>".adminer()->selectColumnInput(" name='order[$r]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$r]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($y){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($y?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($Vk){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($Vk)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$v){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=array();foreach($v
as$u){$Sb=reset($u["columns"]);if($u["type"]!="FULLTEXT"&&$Sb)$d[$Sb]=1;}$d[""]=1;foreach($d
as$w=>$X)json_row($w);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Pc,array$d){}function
selectColumnsProcess(array$d,array$v){$N=array();$q=array();foreach((array)$_GET["columns"]as$w=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$N[$w]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$q[]=$N[$w];}}return
array($N,$q);}function
selectSearchProcess(array$l,array$v,$_k=null){$J=array();foreach($v
as$r=>$u){if($u["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$r)!="")$J[]=driver()->fulltextSql($r,$u,$_GET["fulltext"][$r],isset($_GET["boolean"][$r]));}$uh=adminer()->operators($_k);foreach((array)$_GET["where"]as$w=>$X){$X+=array("col"=>"","op"=>first($uh),"val"=>"");$_GET["where"][$w]=$X;$ob=$X["col"];if(("$ob$X[val]"!=""||preg_match('~NULL$~',$X["op"]))&&in_array($X["op"],$uh)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$zb=array();foreach(($ob!=""?array($ob=>idx($l,$ob,array())):$l)as$B=>$k){if($ob!=""||is_searchable($k,$X))$zb[]=adminer()->selectSearchCondition(driver()->convertSearch(idf_escape($B),$X,$k),$X["op"],$X["val"]);}$J[]=(count($zb)==1?$zb[0]:($zb?"(".implode(" OR ",$zb).")":"1 = 0"));}}return$J;}function
selectSearchCondition($c,$qh,$X){if(preg_match('~IN$~',$qh))return"$c $qh ".($X!=""?process_in($X):"(NULL)");if($qh=="BETWEEN")return"$c $qh ".process_between($X);if($qh=="SQL")return"$c $X";if(preg_match('~^(I?LIKE) %%$~',$qh,$A))return"$c $A[1] ".q("%$X%");if($qh=="FIND_IN_SET")return"$qh(".q($X).", $c)";return"$c $qh".(!preg_match('~NULL$~',$qh)?" ".q($X):"");}function
selectOrderProcess(array$l,array$v){$J=array();foreach((array)$_GET["order"]as$w=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$w])?" DESC".(JUSH=='pgsql'&&idx($l[$X],"null")?" NULLS LAST":""):"");}return$J;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$Kd){return
false;}function
selectQueryBuild(array$N,array$Z,array$q,array$D,$y,$E){return"";}function
messageQuery($H,$Xk,$sd=false){restart_session();$xe=&get_session("queries");if(!idx($xe,$_GET["db"]))$xe[$_GET["db"]]=array();if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$xe[$_GET["db"]][]=array($H,time(),$Xk);$fk="sql-".count($xe[$_GET["db"]]);$J="<a href='#$fk' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$sd&&($fm=driver()->warnings())){$s="warnings-".count($xe[$_GET["db"]]);$J="<a href='#$s' class='toggle'>".'Warnings'."</a>, $J<div id='$s' class='hidden'>\n$fm</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $J<div id='$fk' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($H,1e4)."</code></pre>".($Xk?" <span class='time'>($Xk)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($xe[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$l,$K,$Fl,$H='',$Xk=''){echo($H!=""?"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>($Xk)</span>\n":"");}function
editFunctions(array$k){$J=($k["null"]?"NULL/":"");$ne=isset($_GET["select"])||where($_GET);foreach(array(driver()->insertFunctions,driver()->editFunctions)as$w=>$Vd){if(!$w||(!isset($_GET["call"])&&$ne)){foreach($Vd
as$fi=>$X){if(!$fi||preg_match("~$fi~",$k["type"]))$J
.="/$X";}}if($w&&$Vd&&!preg_match('~set|bool~',$k["type"])&&!is_blob($k))$J
.="/SQL";}if($k["auto_increment"]&&!$ne)$J='Auto Increment';return
explode("/",$J);}function
editInput($R,array$k,$b,$Y){if($k["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$k,$Y,"NULL");return"";}function
editHint($R,array$k,$Y){return"";}function
processInput(array$k,$Y,$p=""){if($p=="SQL")return$Y;$B=$k["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$p))$J="$p()";elseif(preg_match('~^current_(date|timestamp)$~',$p))$J=$p;elseif(preg_match('~^([+-]|\|\|)$~',$p))$J=idf_escape($B)." $p $J";elseif(preg_match('~^[+-] interval$~',$p))$J=idf_escape($B)." $p ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$p))$J="$p(".idf_escape($B).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$p))$J="$p($J)";return
unconvert_field($k,$J);}function
dumpOutput(){$J=array('text'=>'open','file'=>'save');if(function_exists('gzencode'))$J['gz']='gzip';return$J;}function
dumpFormat(){return(support("dump")?array('sql'=>'SQL'):array())+array('csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV');}function
dumpPrint(){}function
dumpDatabase($h){}function
dumpTable($R,$rk,$nf=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($rk)dump_csv(array_keys(fields($R)));}else{if($nf==2){$l=array();foreach(fields($R)as$B=>$k)$l[]=idf_escape($B)." ".full_type_sql($k);$Kb="CREATE TABLE ".table($R)." (".implode(", ",$l).")";}else$Kb=create_sql($R,$_POST["auto_increment"],$rk);set_utf8mb4($Kb);if($rk&&$Kb){if(($rk=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$nf==1)echo"DROP ".($nf==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($nf==1)$Kb=remove_definer($Kb);echo"$Kb;\n\n";}}}function
dumpData($R,$rk,$H,array$N=array(),array$Z=array(),array$q=array(),array$D=array()){if($rk){$cg=(JUSH=="sqlite"?0:1048576);$l=array();$Ee=false;if($_POST["format"]=="sql"){if($rk=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$l=fields($R);if(JUSH=="mssql"){foreach($l
as$k){if($k["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$Ee=true;break;}}}}$I=($H!=""?connection()->query($H,1):driver()->select($R,($N?:array("*")),$Z,$q,$D,0));if($I){$Xe="";$Ua="";$uf=array();$Wd=array();$tk="";$vd=($R!=''?'fetch_assoc':'fetch_row');$Jb=0;while($K=$I->$vd()){if(!$uf){$Xl=array();foreach($K
as$X){$k=$I->fetch_field();if(idx($l[$k->name],'generated')){$Wd[$k->name]=true;continue;}$uf[]=$k->name;$w=idf_escape($k->name);$Xl[]="$w = VALUES($w)";}$tk=($rk=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$Xl):"").";\n";}foreach($K
as$w=>$X){if(is_array($X))$K[$w]=json_encode($X,256|64);}if($_POST["format"]!="sql"){if($rk=="table"){dump_csv($uf);$rk="INSERT";}dump_csv($K);}else{if(!$Xe)$Xe="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$uf)).") VALUES";foreach($K
as$w=>$X){if($Wd[$w]){unset($K[$w]);continue;}$k=$l[$w];$K[$w]=($X===null?"NULL":($X===false?0:unconvert_field($k,preg_match(number_type(),$k["type"])&&!preg_match('~\[~',$k["full_type"])&&is_numeric($X)?$X:(!is_blob($k)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$sj=($cg?"\n":" ")."(".implode(",\t",$K).")";if(!$Ua)$Ua=$Xe.$sj;elseif(JUSH=='mssql'?$Jb%1000!=0:strlen($Ua)+4+strlen($sj)+strlen($tk)<$cg)$Ua
.=",$sj";else{echo$Ua.$tk;$Ua=$Xe.$sj;}}$Jb++;}if($Ua)echo$Ua.$tk;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";else
dump_csv(array(connection()->error));if($Ee)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($De){return
friendly_url($De!=""?$De:(SERVER?:"localhost"));}function
dumpHeaders($De,$Fg=false){$Kh=$_POST["output"];$nd=(preg_match('~sql~',$_POST["format"])?"sql":($Fg?"tar":"csv"));header("Content-Type: ".($Kh=="gz"?"application/x-gzip":($nd=="tar"?"application/x-tar":($nd=="sql"||$Kh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($Kh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$nd;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($zg){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$Ug=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$Ug)<0?h($Ug):"").version_iframe()."</a>","</span></h1>\n";if($zg=="auth"){$Kh="";foreach((array)$_SESSION["pwds"]as$Zl=>$Nj){foreach($Nj
as$O=>$Sl){$B=h(get_setting("vendor-$Zl-$O")?:get_driver($Zl));foreach($Sl
as$V=>$F){if($B&&$F!==null){$bc=$_SESSION["db"][$Zl][$O][$V];foreach(($bc?array_keys($bc):array(""))as$h)$Kh
.="<li><a href='".h(auth_url($Zl,$O,$V,$h))."'>($B) ".h("$V@").($O!=""?adminer()->serverName($O):"").h($h!=""?" - $h":"")."</a>\n";}}}}if($Kh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$Kh</ul>\n";}else{$T=array();if($_GET["ns"]!==""&&!$zg&&DB!=""){connection()->select_db(DB);$T=table_status('',true);}adminer()->syntaxHighlighting($T);adminer()->databasesPrint($zg);$ga=array();if(DB==""||!$zg){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$Ke=$_GET["ns"]!==""&&!$zg&&DB!="";if($Ke&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$zg);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");if($Ke){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.1.2+9548328c",true);$Bg=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($Bg?script("addEventListener('DOMContentLoaded', () => {\n$Bg\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$Of=array();foreach($T
as$R=>$U)$Of[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$Of).')(?!\$)\b/g',false);$hk=array("sql","check","event","procedure","trigger","view","type","table","processlist");if(support("routine")&&array_intersect_key($_GET,array_flip($hk))){foreach(routines()as$K)json_row(js_escape(ME).'function='.url_escape($K["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(array("bac","bra","sqlite_quo","mssql_bra")as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(array("sql","check","event","procedure","trigger","view")))){$lk=(isset($_GET["trigger"])?array('INSERT INTO','UPDATE','DELETE FROM'):(isset($_GET["check"])?array():(isset($_GET["view"])?array('SELECT'):null)));$Fa=Driver::jushAutocomplete($T,$lk);echo($Fa?"addEventListener('DOMContentLoaded', () => { autocompleter = $Fa; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($zg){if(support("single_db"))return;$g=adminer()->databases();if(DB&&$g&&!in_array(DB,$g))array_unshift($g,DB);echo"<form action=''>\n<p id='dbs'>\n";hidden_fields_get();$Yb=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($g?html_select("db",array(""=>"")+group_system($g),DB,$Yb):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($g?" class='hidden'":"").">\n";foreach(array("import","sql","schema","dump","privileges")as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$zg){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$mk){$R="$R";$B=adminer()->tableName($mk);if($B!=""&&!$mk["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"])),(is_view($mk)?"view":"structure"))." title='".'Show structure'."'>$B</a>":"<span>$B</span>")."\n";}echo"</ul>\n";}function
showVariables(){return
show_variables();}function
showStatus(){return
show_status();}function
processList(){return
process_list();}function
killProcess($s){return
kill_process($s);}function
formatSizeValue($X){return
format_number($X);}}class
Plugins{private
static$append=array('dumpFormat'=>true,'dumpOutput'=>true,'editRowPrint'=>true,'editFunctions'=>true,'config'=>true);var$plugins;var$drivers=array();var$driverFiles=array();var$error='';private$hooks=array();function
__construct($ni){$Cc=SqlDriver::$drivers;$te=" href='https://www.adminer.org/plugins/#use'".target_blank();if($ni===null){$ni=array();$Na="adminer-plugins";if(is_dir($Na)){foreach(glob("$Na/*.php")as$m){$_d=SqlDriver::$drivers;$this->includeOnce($m);foreach(array_diff_key(SqlDriver::$drivers,$_d)as$s=>$B)$this->driverFiles[$s]=$m;}}if(file_exists("$Na.php")){$Me=$this->includeOnce("$Na.php");if(is_array($Me)){foreach($Me
as$w=>$ki)$ni[is_object($ki)?get_class($ki):$w]=$ki;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Na.php</b>",$te)."<br>";}foreach(get_declared_classes()as$mb){if(!$ni[$mb]&&(preg_match('~^Adminer\w~i',$mb)||is_subclass_of($mb,'Adminer\Plugin'))){$Ui=new
\ReflectionClass($mb);$Bb=$Ui->getConstructor();if($Bb&&$Bb->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$te,"<b>$mb</b>","<b>$Na.php</b>")."<br>";else$ni[$mb]=new$mb;}}}$cf=array_filter($ni,function($ki){return!is_object($ki);});if($cf){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$te)."<br>";$ni=array_diff_key($ni,$cf);}$this->drivers=array_diff_key(SqlDriver::$drivers,$Cc);$this->plugins=$ni;$ja=new
Adminer;$ni[]=$ja;$Ui=new
\ReflectionObject($ja);foreach($Ui->getMethods()as$wg){foreach($ni
as$ki){$B=$wg->getName();if(method_exists($ki,$B))$this->hooks[$B][]=$ki;}}}function
includeOnce($m){return
include_once"./$m";}static
function
checksum($m){$zd=str_replace("\r","",file_get_contents($m));$zd=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$zd);return
dechex(crc32($zd));}function
checksums(){$Ad=array_values($this->driverFiles);foreach($this->plugins
as$ki){$Ui=new
\ReflectionObject($ki);$Ad[]=$Ui->getFileName();}$J=array();foreach($Ad
as$m)$J[basename($m,'.php')]=self::checksum($m);return$J;}static
function
officialChecksums(){return
array('adminer.js'=>'a0599090','backward-keys'=>'5fb34a8b','before-unload'=>'2a613523','config'=>'722eb4af','dark-switcher'=>'3d490dea','database-hide'=>'e304a899','designs'=>'ed7e44e3','dump-alter'=>'896b579e','dump-bz2'=>'f0d0e336','dump-date'=>'adc7f1c7','dump-json'=>'767dd321','dump-xml'=>'4fc3cd60','dump-zip'=>'93817d96','edit-foreign'=>'eb823bb0','edit-textarea'=>'a24c3cc','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'1e24970e','file-upload'=>'10add0e8','foreign-system'=>'ebb4c654','frames'=>'b0e1d11a','highlight-codemirror'=>'c5716555','highlight-monaco'=>'edd1b0af','highlight-prism'=>'267948e5','import-csv'=>'d429c77','login-ip'=>'4d174fea','login-otp'=>'5b5a68af','login-passkey'=>'f69f2f06','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'2b06a5b7','login-table'=>'811f8cef','menu-links'=>'c78461b3','name-patterns'=>'84c10d09','remote-color'=>'ddeecc48','row-numbers'=>'eec8698c','select-email'=>'f84fbd2c','select-expression'=>'4b61d59c','select-foreign'=>'38ac915c','select-image'=>'f55c0231','slugify'=>'dec64713','sql-gemini'=>'c60ab309','sql-log'=>'8e435000','sql-url-length'=>'cd70f1a0','table-indexes-structure'=>'a90cc0c9','table-structure'=>'a8458e02','tables-filter'=>'ec2bcd6e','timeout'=>'97321caf','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'92ca960d','elastic'=>'1582a04d','firebird'=>'1c4309b2','igdb'=>'a6a1750a','imap'=>'3da1022b','mongo'=>'63486492','redis'=>'6a653462','simpledb'=>'b8e2cc7d',);}function
__call($B,array$Ph){$ya=array();foreach($Ph
as$w=>$X)$ya[]=&$Ph[$w];$J=null;foreach($this->hooks[$B]as$ki){$Y=call_user_func_array(array($ki,$B),$ya);if($Y!==null){if(!self::$append[$B])return$Y;$J=$Y+(array)$J;}}return$J;}}abstract
class
Plugin{protected$translations=array();function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($t,$ah=null){$ya=func_get_args();$ya[0]=idx($this->translations[LANG],$t)?:$t;return
call_user_func_array('Adminer\lang_format',$ya);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($bi){$this->password_hash=$bi;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$F=get_password();return
array(SERVER,$_GET["username"],($this->passwordMatches($F)&&!password_required()?"":$F));}function
login($Sf,$F){if($this->passwordMatches($F))return
true;if(!Driver::$passwords||!password_required())return
false;}protected
function
passwordMatches($F){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($F),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=array("server"=>"MySQL / MariaDB")+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$O,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);$oi=$O["port"];$Rc=("$O[host]$oi$O[socket]"=="");$ik=adminer()->connectSsl();$Pl=($ik&&($ik['key']||$ik['cert']||$ik['ca']||isset($ik['verify'])));if($Pl)$this->ssl_set($ik['key'],$ik['cert'],$ik['ca'],'','');$J=@$this->real_connect((!$Rc?$O["host"]:ini_get("mysqli.default_host")),(!$Rc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Rc||$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,($oi!=""?intval($oi):ini_get("mysqli.default_port")),($oi!=""?null:$O["socket"]),($Pl?($ik['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($J?'':$this->error);}function
set_charset($cb){if(parent::set_charset($cb))return
true;parent::set_charset('utf8');return$this->query("SET NAMES $cb");}function
next_result(){return
self::more_results()&&parent::next_result();}function
quote($Q){return"'".$this->escape_string($Q)."'";}function
inTransaction(){return
false;}function
begin(){return$this->begin_transaction();}}}elseif(extension_loaded("mysql")&&!((ini_bool("sql.safe_mode")||ini_bool("mysql.allow_local_infile"))&&extension_loaded("pdo_mysql"))){class
Db
extends
SqlDb{private$link;function
attach(array$O,$V,$F){if(ini_bool("mysql.allow_local_infile"))return
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$oi="$O[port]$O[socket]";$B=$O["host"].($oi!=""?":$oi":"");$this->link=@mysql_connect(($B!=""?$B:ini_get("mysql.default_host")),($B.$V!=""?$V:ini_get("mysql.default_user")),($B.$V.$F!=""?$F:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($cb){return
mysql_set_charset($cb,$this->link)||mysql_set_charset('utf8',$this->link);}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Xb){return
mysql_select_db($Xb,$this->link);}function
query($H,$xl=false){$I=@($xl?mysql_unbuffered_query($H,$this->link):mysql_query($H,$this->link));$this->error="";if(!$I){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
false;}if($I===true){$this->affected_rows=mysql_affected_rows($this->link);$this->info=mysql_info($this->link);return
true;}return
new
Result($I);}}class
Result{var$num_rows;private$result;private$offset=0;function
__construct($I){$this->result=$I;$this->num_rows=mysql_num_rows($I);}function
fetch_assoc(){return
mysql_fetch_assoc($this->result);}function
fetch_row(){return
mysql_fetch_row($this->result);}function
fetch_field(){$J=mysql_fetch_field($this->result,$this->offset++);$J->orgtable=$J->table;$J->native_type=idx(array("string"=>"varchar","real"=>"double"),$J->type,$J->type);return$J;}}}elseif(extension_loaded("pdo_mysql")){class
Db
extends
PdoDb{var$extension="PDO_MySQL";function
attach(array$O,$V,$F){$C=array(\PDO::MYSQL_ATTR_LOCAL_INFILE=>false);if(isset($_GET["select"]))$C[\PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;$ik=adminer()->connectSsl();if($ik){if($ik['key'])$C[\PDO::MYSQL_ATTR_SSL_KEY]=$ik['key'];if($ik['cert'])$C[\PDO::MYSQL_ATTR_SSL_CERT]=$ik['cert'];if($ik['ca'])$C[\PDO::MYSQL_ATTR_SSL_CA]=$ik['ca'];if(isset($ik['verify']))$C[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$ik['verify'];}$_e=$O["host"];$oi=$O["port"];$Wj=$O["socket"];return$this->dsn("mysql:charset=utf8".($_e!=""?";host=$_e":'').($oi!=""?";port=$oi":($Wj!=""?";unix_socket=$Wj":"")),$V,$F,$C);}function
set_charset($cb){return$this->query("SET NAMES $cb");}function
select_db($Xb){return$this->query("USE ".idf_escape($Xb));}function
query($H,$xl=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$xl);return
parent::query($H,$xl);}}}class
Driver
extends
SqlDriver{static$extensions=array("MySQLi","MySQL","PDO_MySQL");static$jush="sql";static$serverSocket=true;var$unsigned=array("unsigned","zerofill","unsigned zerofill");var$functions=array("char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper");var$grouping=array("avg","count","count distinct","group_concat","max","min","sum");var$partitionBy=array("HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST");function
operators($_k){return
array("=","<",">","<=",">=","!=","&","LIKE","LIKE %%","REGEXP","IN","BETWEEN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL");}static
function
connect($O,$V,$F){$e=parent::connect($O,$V,$F);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($sj=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$sj;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=array('Numbers'=>array("tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21),'Date and time'=>array("date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4),'Strings'=>array("char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295),'Lists'=>array("enum"=>65535,"set"=>64),'Binary'=>array("bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295),'Geometry'=>array("geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0),);$this->insertFunctions=array("char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",);$this->editFunctions=array(number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",);if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=array("STORED","VIRTUAL");}function
unconvertFunction(array$k){return(preg_match("~binary~",$k["type"])?"<code class='jush-sql'>UNHEX</code>":($k["type"]=="bit"?doc_link(array('sql'=>'bit-value-literals.html'),"<code>b''</code>"):($k["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$k["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$P){return($P?parent::insert($R,$P):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$L,array$_i){$d=array_keys(reset($L));$wi="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$Xl=array();foreach($d
as$w)$Xl[$w]="$w = VALUES($w)";$tk="\nON DUPLICATE KEY UPDATE ".implode(", ",$Xl);$Xl=array();$x=0;foreach($L
as$P){$Y="(".implode(", ",$P).")";if($Xl&&(strlen($wi)+$x+strlen($Y)+strlen($tk)>1e6)){if(!queries($wi.implode(",\n",$Xl).$tk))return
false;$Xl=array();$x=0;}$Xl[]=$Y;$x+=strlen($Y)+2;}return
queries($wi.implode(",\n",$Xl).$tk);}function
slowQuery($H,$Yk){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$Yk FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$A))return"$A[1] /*+ MAX_EXECUTION_TIME(".($Yk*1000).") */ $A[2]";}}function
convertColumn($t,array$k){if(preg_match("~binary~",$k["type"]))return"HEX($t)";if($k["type"]=="bit")return"BIN($t + 0)";if($k["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($t)";if(preg_match("~geom|point|linestring|polygon~",$k["type"]))return(min_version(8)?"ST_":"")."AsWKT($t)";return"";}function
convertSearch($t,array$X,array$k){return($this->convertColumn($t,$k)?:(preg_match('~'.text_type().'~',$k["type"])&&!preg_match("~^utf8~",$k["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($t USING ".charset($this->conn).")":$t));}function
typeName(\stdClass$k){$B=parent::typeName($k);if($B!=""){$wl=array("TINY"=>"tinyint","SHORT"=>"smallint","LONG"=>"int","INT24"=>"mediumint","LONGLONG"=>"bigint","NEWDECIMAL"=>"decimal","VAR_STRING"=>"varchar","STRING"=>"char",);return
idx($wl,$B,strtolower($B));}$wl=array("decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",);$J=idx($wl,$k->type,"");return($k->charsetnr==63?str_replace(array("text","varchar","char"),array("blob","varbinary","binary"),$J):$J);}function
quoteBinary($sj){return"X".q(bin2hex($sj));}function
md5($c,array$k){if(is_blob($k)||preg_match('~'.text_type().'~',$k["type"]))return"MD5(".(is_blob($k)||preg_match("~^utf8~",$k["collation"])?$c:"CONVERT($c USING ".charset($this->conn).")").")";}function
warnings(){$I=$this->conn->query("SHOW WARNINGS");if($I&&$I->num_rows){ob_start();print_select_result($I);return
ob_get_clean();}}function
tableHelp($B,$nf=false){$Uf=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($Uf?"$B-table/":str_replace("_","-",$B)."-table.html"));if(DB=="sys")return($Uf?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$B)).".html"));if(DB=="mysql")return($Uf?"mysql$B-table/":"system-schema.html");}function
partitionsInfo($R){$Qd="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$I=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Qd ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$K=($I?$I->fetch_row():null);if(!$K)return
array();$J=array();list($J["partition_by"],$J["partition"],$J["partitions"])=$K;$Xh=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Qd AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$J["partition_names"]=array_keys($Xh);$J["partition_values"]=array_values($Xh);return$J;}function
checkConstraints($R){$J=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$J:array_map('stripslashes',$J));}function
hasCStyleEscapes(){static$Va;if($Va===null){$gk=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Va=(strpos($gk,'NO_BACKSLASH_ESCAPES')===false);}return$Va;}function
hasEstimatedRows(){return
true;}function
isSystem($h,$M=""){return
information_schema($h,$M)||in_array($h,array("mysql","sys"));}function
lineComment(){return"#|-- ";}function
engines(){$J=array();foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$J[]=$K["Engine"];}return$J;}function
indexAlgorithms(array$_k){return(preg_match('~^(MEMORY|NDB)$~',$_k["Engine"])?array("HASH","BTREE"):array());}}function
idf_escape($t){return"`".str_replace("`","``",$t)."`";}function
table($t){return
idf_escape($t);}function
get_databases($Hd){$J=get_session("dbs");if($J===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$jk=microtime(true);$J=($Hd?slow_query($H):get_vals($H));if(microtime(true)-$jk>0.1){restart_session();set_session("dbs",$J);stop_session();}}return$J;}function
limit($H,$Z,$y,$hh=0,$Gj=" "){return" $H$Z".($y?$Gj."LIMIT $y".($hh?" OFFSET $hh":""):"");}function
limit1($R,$H,$Z,$Gj="\n"){return
limit($H,$Z,1,0,$Gj);}function
db_collation($h,array$rb){$J=null;$Kb=get_val("SHOW CREATE DATABASE ".idf_escape($h),1);if(preg_match('~ COLLATE ([^ ]+)~',$Kb,$A))$J=$A[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$Kb,$A))$J=$rb[$A[1]][-1];return$J;}function
logged_user(){return
get_val("SELECT CURRENT_USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$g){$J=array();foreach($g
as$h)$J[$h]=count(get_vals("SHOW TABLES IN ".idf_escape($h)));return$J;}function
table_status($B="",$td=false){$J=array();$H="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($B!=""?"AND TABLE_NAME = ".q($B):"ORDER BY Name");$M=array();foreach(($td?array():get_rows($H))as$K)$M[$K["Name"]]=$K;$zi=null;foreach(get_rows($td?$H:"SHOW TABLE STATUS".($B!=""?" LIKE ".q(addcslashes($B,"%_\\")):""))as$K){$Hh=idx($M,$K["Name"]);if($Hh){if($K["Comment"]!==$Hh["Comment"]&&$K["Comment"]!==$zi)$K["Error"]=$K["Comment"];$zi=$K["Comment"];$K["Comment"]=$Hh["Comment"];$K["Engine"]=$Hh["Engine"];}if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($B!="")$K["Name"]=$B;$J[$K["Name"]]=$K;}return$J;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Sd){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Sd,$A);return
array($A[1],$A[2],ltrim($A[3].$A[4]));}function
fields($R){$Uf=(connection()->flavor=='maria');$J=array();foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$K){$k=$K["COLUMN_NAME"];$U=$K["COLUMN_TYPE"];$Xd=$K["GENERATION_EXPRESSION"];$qd=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$qd,$Wd);list($vl,$x,$Dl)=parse_type($U);$i=$K["COLUMN_DEFAULT"];if($i!=""){$lf=preg_match('~text|json~',$vl);if(!$Uf&&$lf)$i=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($i));if($Uf||$lf){$i=($i=="NULL"?null:preg_replace_callback("~^'(.*)'$~",function($A){return
stripslashes(str_replace("''","'",$A[1]));},$i));}if(!$Uf&&preg_match('~binary~',$vl)&&preg_match('~^0x(\w*)$~',$i,$A))$i=pack("H*",$A[1]);}$J[$k]=array("field"=>$k,"full_type"=>$U,"type"=>$vl,"length"=>$x,"unsigned"=>$Dl,"default"=>($Wd?($Uf?$Xd:stripslashes($Xd)):$i),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($qd=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$qd,$A)?$A[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$K[PRIVILEGES],where,order")),"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($Wd[1]=="PERSISTENT"?"STORED":$Wd[1]),);}return$J;}function
indexes($R,$f=null){$J=array();foreach(get_rows("SHOW INDEX FROM ".table($R),$f)as$K){$B=$K["Key_name"];$J[$B]["type"]=($B=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$B]["columns"][]=$K["Column_name"];$J[$B]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$B]["descs"][]=null;$J[$B]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($R){static$fi='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=array();$Lb=get_val("SHOW CREATE TABLE ".table($R),1);if($Lb){preg_match_all("~CONSTRAINT ($fi) FOREIGN KEY ?\\(((?:$fi,? ?)+)\\) REFERENCES ($fi)(?:\\.($fi))? \\(((?:$fi,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Lb,$Wf,PREG_SET_ORDER);foreach($Wf
as$A){preg_match_all("~$fi~",$A[2],$ak);preg_match_all("~$fi~",$A[5],$Ok);$J[idf_unescape($A[1])]=array("db"=>idf_unescape($A[4]!=""?$A[3]:$A[4]),"table"=>idf_unescape($A[4]!=""?$A[4]:$A[3]),"source"=>array_map('Adminer\idf_unescape',$ak[0]),"target"=>array_map('Adminer\idf_unescape',$Ok[0]),"on_delete"=>($A[6]?:"RESTRICT"),"on_update"=>($A[7]?:"RESTRICT"),);}}return$J;}function
view($B){return
array("select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($B),1)));}function
collations(){$J=array();foreach(get_rows("SHOW COLLATION")as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$w=>$X)sort($J[$w]);return$J;}function
information_schema($h,$M=""){return($h=="information_schema")||(min_version(5.5)&&$h=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($h,$qb){return
queries("CREATE DATABASE ".idf_escape($h).($qb?" COLLATE ".q($qb):""));}function
drop_databases(array$g){$J=apply_queries("DROP DATABASE",$g,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($B,$qb){$J=false;if(create_database($B,$qb)){$T=array();$cm=array();foreach(tables_list()as$R=>$U){if($U=='VIEW')$cm[]=$R;else$T[]=$R;}$J=(!$T&&!$cm)||move_tables($T,$cm,$B);drop_databases($J?array(DB):array());}return$J;}function
auto_increment(){$Ea=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$u){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$u["columns"],true)){$Ea="";break;}if($u["type"]=="PRIMARY")$Ea=" UNIQUE";}}return" AUTO_INCREMENT$Ea";}function
alter_table($R,$B,array$l,array$Jd,$vb,$Tc,$qb,$Da,$Wh){$ta=array();foreach($l
as$k){if($k[1]){$i=$k[1][3];if(preg_match('~ GENERATED~',$i)){$k[1][3]=(connection()->flavor=='maria'?"":$k[1][2]);$k[1][2]=$i;}$ta[]=($R!=""?($k[0]!=""?"CHANGE ".idf_escape($k[0]):"ADD"):" ")." ".implode($k[1]).($R!=""?$k[2]:"");}else$ta[]="DROP ".idf_escape($k[0]);}$ta=array_merge($ta,$Jd);$mk=($vb!==null?" COMMENT=".q($vb):"").($Tc?" ENGINE=".q($Tc):"").($qb?" COLLATE ".q($qb):"").($Da!=""?" AUTO_INCREMENT=$Da":"");if($Wh){$Xh=array();if($Wh["partition_by"]=='RANGE'||$Wh["partition_by"]=='LIST'){foreach($Wh["partition_names"]as$w=>$X){$Y=$Wh["partition_values"][$w];$Xh[]="\n  PARTITION ".idf_escape($X)." VALUES ".($Wh["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$mk
.="\nPARTITION BY $Wh[partition_by]($Wh[partition])";if($Xh)$mk
.=" (".implode(",",$Xh)."\n)";elseif($Wh["partitions"])$mk
.=" PARTITIONS ".(+$Wh["partitions"]);}elseif($Wh===null)$mk
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($B)." (\n".implode(",\n",$ta)."\n)$mk");if($R!=$B)$ta[]="RENAME TO ".table($B);if($mk)$ta[]=ltrim($mk);return($ta?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$ta)):true);}function
alter_indexes($R,$ta){$ab=array();foreach($ta
as$X)$ab[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$ab));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$cm){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$cm)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$cm,$Ok){$Zi=array();foreach($T
as$R)$Zi[]=table($R)." TO ".idf_escape($Ok).".".table($R);if(!$Zi||queries("RENAME TABLE ".implode(", ",$Zi))){$ic=array();foreach($cm
as$R)$ic[table($R)]=view($R);connection()->select_db($Ok);$h=idf_escape(DB);foreach($ic
as$B=>$bm){if(!queries("CREATE VIEW $B AS ".str_replace(" $h."," ",$bm["select"]))||!queries("DROP VIEW $h.$B"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$cm,$Ok){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$B=($Ok==DB?table("copy_$R"):idf_escape($Ok).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $B"))||!queries("CREATE TABLE $B LIKE ".table($R))||!queries("INSERT INTO $B SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){$nl=$K["Trigger"];list($cd,$ch)=trigger_event($K);if(!queries("CREATE TRIGGER ".($Ok==DB?idf_escape("copy_$nl"):idf_escape($Ok).".".idf_escape($nl))." $K[Timing] $cd".($ch!=""?" $ch":"")." ON $B FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($cm
as$R){$B=($Ok==DB?table("copy_$R"):idf_escape($Ok).".".table($R));$bm=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $B"))||!queries("CREATE VIEW $B AS $bm[select]"))return
false;}return
true;}function
trigger_event(array$K){$ed=explode(",",$K["Event"]);$J=array();foreach(array("DELETE","INSERT","UPDATE")as$cd){if(in_array($cd,$ed))$J[]=$cd;}$J=implode(" OR ",$J);if(in_array("UPDATE",$ed)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($K["Trigger"]),2),$A)&&preg_match('~\bOF\s+(.+)~is',$A[1],$ch))return
array("$J OF",$ch[1]);return
array($J,"");}function
trigger($B,$R){if($B=="")return
array();$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($B));$J=reset($L);if($J)list($J["Event"],$J["Of"])=trigger_event($J);return($J?:array());}function
triggers($R){$J=array();foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){list($cd)=trigger_event($K);$J[$K["Trigger"]]=array($K["Timing"],$cd);}return$J;}function
trigger_options(){return
array("Timing"=>array("BEFORE","AFTER"),"Event"=>(min_version('','12.0.1')?array("INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",):array("INSERT","UPDATE","DELETE")),"Type"=>array("FOR EACH ROW"),);}function
routine($B,$U){$L=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($B)."
ORDER BY ORDINAL_POSITION");$l=array();foreach($L
as$K){$Sd=$K["DTD_IDENTIFIER"];list($vl,$x,$Dl)=parse_type($Sd);$l[]=array("field"=>$K["PARAMETER_NAME"],"type"=>$vl,"length"=>$x,"unsigned"=>$Dl,"null"=>true,"full_type"=>$Sd,"inout"=>($U=="FUNCTION"?"":$K["PARAMETER_MODE"]),"collation"=>$K["COLLATION_NAME"],);}$J=connection()->query("SELECT
	ROUTINE_COMMENT comment,
	ROUTINE_DEFINITION definition,
	LOWER(EXTERNAL_LANGUAGE) language,
	IF(DEFINER = CURRENT_USER(), '', DEFINER) definer,
	IF(IS_DETERMINISTIC = 'YES', 'DETERMINISTIC', 'NOT DETERMINISTIC') is_deterministic,
	SQL_DATA_ACCESS data_access,
	CONCAT('SQL SECURITY ', SECURITY_TYPE) security
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND ROUTINE_NAME = ".q($B))->fetch_assoc();if(!$J)return
array();$J['options']=array("DEFINER"=>$J['definer'],"DETERMINISTIC"=>$J['is_deterministic'],"SQL_DATA_ACCESS"=>$J['data_access'],"SQL_SECURITY"=>$J['security'],"COMMENT"=>$J['comment'],);if($l&&$l[0]['field']=='')$J['returns']=array_shift($l);$J['fields']=$l;return$J;}function
routines(){return
get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");}function
routine_languages(){return(min_version(9,99)?array("sql"=>"sql","javascript"=>"js"):array());}function
routine_options($kj){return
array("DEFINER"=>array(),"DETERMINISTIC"=>array("NOT DETERMINISTIC","DETERMINISTIC"),"SQL_DATA_ACCESS"=>array("CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"),"SQL_SECURITY"=>array("SQL SECURITY DEFINER","SQL SECURITY INVOKER"),"COMMENT"=>array(),);}function
routine_id($B,array$K){return
idf_escape($B);}function
last_id($I){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$H){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$H);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Da,$rk){$J=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Da)$J=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$J);return$J;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Xb,$rk=""){$B=idf_escape($Xb);$J="";if(preg_match('~CREATE~',$rk)&&($Kb=get_val("SHOW CREATE DATABASE $B",1))){set_utf8mb4($Kb);if($rk=="DROP+CREATE")$J="DROP DATABASE IF EXISTS $B;\n";$J
.="$Kb;\n";}return$J."USE $B";}function
trigger_sql($R){$J="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")),null,"-- ")as$K){list($K["Event"],$K["Of"])=trigger_event($K);$J
.="\n".create_trigger(" ON ".table($K["Table"]),$K+array("Type"=>"FOR EACH ROW")).";\n";}return$J;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$k){return
driver()->convertColumn(idf_escape($k["field"]),$k);}function
unconvert_field(array$k,$J){if(preg_match("~binary~",$k["type"]))$J="UNHEX($J)";if($k["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($k["type"]=="vector")$J=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geom|point|linestring|polygon~",$k["type"])){$wi=(min_version(8)?"ST_":"");$J=$wi."GeomFromText($J, $wi"."SRID($k[field]))";}return$J;}function
support($ud){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$ud);}function
kill_process($s){return
queries("KILL ".number($s));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($pd=false){return
array();}function
type_values($s){return"";}function
type_definition($s){return
array("kind"=>"","definition"=>"");}function
schemas(){return
array();}function
get_schema(){return"";}function
set_schema($M,$f=null){return
true;}function
schema_comment($M){return"";}function
set_schema_comment($M,$vb){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));if(isset($_GET["manifest"])){header("Content-Type: application/manifest+json; charset=utf-8");header("Cache-Control: no-cache");echo
json_encode(adminer()->manifest(),64|256);exit;}function
page_header($al,$j="",$Ta=array(),$bl="",$Xg=false,$zc=""){if($Xg){header("HTTP/1.1 404 Not Found");$j=($j?:'Not found.');}page_headers();if(is_ajax()&&$j){page_messages($j);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$cl=$al.($bl!=""?": $bl":"");$dl=strip_tags($cl.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$dl,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.1.2+9548328c"),'">
';$Pb=adminer()->css();if(is_int(key($Pb)))$Pb=array_fill_keys($Pb,'light');$le=in_array('light',$Pb)||in_array('',$Pb);$je=in_array('dark',$Pb)||in_array('',$Pb);$Tb=($le?($je?null:false):($je?:null));$lg=" media='(prefers-color-scheme: dark)'";if($Tb!==false)echo"<link rel='stylesheet'".($Tb?"":$lg)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.1.2+9548328c")."'>\n";echo"<meta name='color-scheme' content='".($Tb===null?"light dark":($Tb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.1.2+9548328c");if(adminer()->head($Tb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.svg&version=6.1.2+9548328c")."'>\n";if(adminer()->manifest())echo"<link rel='manifest' href='".h(preg_replace('~\?.*~','',ME)."?manifest=")."' crossorigin='use-credentials'>\n";foreach($Pb
as$Kl=>$_g){$b=($_g=='dark'&&!$Tb?$lg:($_g=='light'&&$je?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($Kl)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Ta!==null){$z=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb"><a href="'.h($z?:".").'">'.get_driver(DRIVER).'</a> » ';$z=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$O=adminer()->serverName(SERVER);$O=($O!=""?$O:'Server');if($Ta===false)echo"$O\n";else{echo"<a href='".h($z.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$O</a> » ";$_j="";if(is_string($Ta)){$_j=$Ta;$Ta=array();}if($_GET["ns"]!=""||(DB!=""&&is_array($Ta))){$Zb="$z&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"");echo'<a href="'.h($Zb.($_GET["ns"]==""?$_j:"")).'">'.h(DB).'</a> » ';}if(is_array($Ta)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1).$_j).'">'.h($_GET["ns"]).'</a> » ';foreach($Ta
as$w=>$X){$kc=(is_array($X)?$X[1]:h($X));if($kc!="")echo"<a href='".h(ME."$w=").url_escape(is_array($X)?$X[0]:$X)."'>$kc</a> » ";}}echo"$al\n";}}echo"<h2>$cl$zc</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($j);adminer()->serviceWorker();$g=&get_session("dbs");if(DB!=""&&$g&&!in_array(DB,$g,true))$g=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();if($Xg){page_footer($Xg===true?"":$Xg);exit;}}function
service_worker(){$Xi=has_passwords();$nb=($Xi?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=6.1.2+9548328c")."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$nb\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$Nj){foreach($Nj
as$Sl){foreach($Sl
as$F){if($F!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Ob){$pe=array();foreach($Ob
as$w=>$X)$pe[]="$w $X";header("Content-Security-Policy: ".implode("; ",$pe));}adminer()->headers();}function
csp(){return
array(array("script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",),);}function
design_checksums(){$Ql=array();foreach(array_keys(adminer()->css())as$Kl)$Ql[preg_replace('~\?.*~','',$Kl)]=true;$J=array();foreach(array("adminer.css","adminer-dark.css")as$m){if($Ql[$m]&&file_exists($m)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($m),$A);$J[$m]=array((string)$A[1],Plugins::checksum($m));}}return$J;}function
official_design_checksums(){return
array('adminer-border/adminer.css'=>'ec757f3e','adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'ef0e1948','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'d9169f12','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'1b8ac749','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'a38b3b6a','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',);}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$Wg;if(!$Wg)$Wg=base64_encode(rand_string());return$Wg;}function
page_messages($j){$Jl=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$sg=idx($_SESSION["messages"],$Jl);if($sg){echo"<div class='message'>".implode("</div>\n<div class='message'>",$sg)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$Jl]);}if($j)echo"<div class='error'>$j</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($zg=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($zg);echo"</div>\n";if($zg!="auth")echo'<form action="" method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($Hg){while($Hg>=2147483648)$Hg-=4294967296;while($Hg<=-2147483649)$Hg+=4294967296;return(int)$Hg;}function
long2str(array$W,$em){$sj='';foreach($W
as$X)$sj
.=pack('V',$X);if($em)return
substr($sj,0,end($W));return$sj;}function
str2long($sj,$em){$W=array_values(unpack('V*',str_pad($sj,4*ceil(strlen($sj)/4),"\0")));if($em)$W[]=strlen($sj);return$W;}function
xxtea_mx($om,$nm,$uk,$sf){return
int32((($om>>5&0x7FFFFFF)^$nm<<2)+(($nm>>3&0x1FFFFFFF)^$om<<4))^int32(($uk^$nm)+($sf^$om));}function
encrypt_string($ok,$w){if($ok=="")return"";$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($ok,true);$Hg=count($W)-1;$om=$W[$Hg];$nm=$W[0];$Hi=floor(6+52/($Hg+1));$uk=0;while($Hi-->0){$uk=int32($uk+0x9E3779B9);$Hc=$uk>>2&3;for($Lh=0;$Lh<$Hg;$Lh++){$nm=$W[$Lh+1];$Gg=xxtea_mx($om,$nm,$uk,$w[$Lh&3^$Hc]);$om=int32($W[$Lh]+$Gg);$W[$Lh]=$om;}$nm=$W[0];$Gg=xxtea_mx($om,$nm,$uk,$w[$Lh&3^$Hc]);$om=int32($W[$Hg]+$Gg);$W[$Hg]=$om;}return
long2str($W,false);}function
decrypt_string($ok,$w){if($ok=="")return"";if(!$w)return
false;$w=array_values(unpack("V*",pack("H*",md5($w))));$W=str2long($ok,false);$Hg=count($W)-1;$om=$W[$Hg];$nm=$W[0];$Hi=floor(6+52/($Hg+1));$uk=int32($Hi*0x9E3779B9);while($uk){$Hc=$uk>>2&3;for($Lh=$Hg;$Lh>0;$Lh--){$om=$W[$Lh-1];$Gg=xxtea_mx($om,$nm,$uk,$w[$Lh&3^$Hc]);$nm=int32($W[$Lh]-$Gg);$W[$Lh]=$nm;}$om=$W[$Hg];$Gg=xxtea_mx($om,$nm,$uk,$w[$Lh&3^$Hc]);$nm=int32($W[0]-$Gg);$W[0]=$nm;$uk=int32($uk-0x9E3779B9);}return
long2str($W,true);}$ii=array();if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($w)=explode(":",$X);$ii[$w]=$X;}}function
add_invalid_login(){$La=get_temp_dir()."/adminer-invalid";foreach(glob("$La*")?:array($La)as$m){$o=file_open_lock($m);if($o)break;}if(!$o)$o=file_open_lock("$La-".rand_string());if(!$o)return;$ef=json_decode(stream_get_contents($o),true);$Xk=time();if($ef){foreach($ef
as$ff=>$X){if($X[0]<$Xk)unset($ef[$ff]);}}$cf=&$ef[adminer()->bruteForceKey()];if(!$cf)$cf=array($Xk+30*60,0);$cf[1]++;file_write_unlock($o,json_encode($ef));}function
check_invalid_login(array&$ii){$ef=array();foreach(glob(get_temp_dir()."/adminer-invalid*")as$m){$o=file_open_lock($m);if($o){$ef=json_decode(stream_get_contents($o),true);file_unlock($o);break;}}$w=adminer()->bruteForceKey();$cf=idx($ef,$w,array());$Vg=($cf[1]>29?$cf[0]-time():0);if($Vg>0){$j=lang_format(array('Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'),ceil($Vg/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$w==$_SERVER["REMOTE_ADDR"])$j
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($j,$ii,false);}}function
password_required(){static$J;if($J===null){$J=(bool)get_session("password_required");if(!$J){$Nb=adminer()->credentials();$J=!is_object(Driver::connect($Nb[0],$Nb[1],""));if($J)set_session("password_required",true);}}return$J;}function
require_password_link($F){$Dg="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $Dg";$li=($F!==null?$F:base64_encode(substr(pack("H*",rand_string()),0,12)));$oe=password_hash($li,PASSWORD_DEFAULT);$m="adminer-plugins.php";$jd=file_exists("adminer-plugins.php");if($jd)$af=($F!==null?sprintf('Add this line to %s to require the entered password:',"<b>$m</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$m</b>","<b>$li</b>"));else{$m="<button name='password_less' value='".h($oe)."' class='link'>$m</button>";$af=($F!==null?sprintf('Save %s next to Adminer to require the entered password:',$m):sprintf('Save %s next to Adminer to require the password %s:',$m,"<b>$li</b>"));}$Mf="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($oe)."'</span>),";$J="<p>$af
<pre><code class='jush'>".($jd?$Mf:"&lt;?php\n<a>return</a> <a>array</a>(\n$Mf\n);")."</code></pre>
<p>$Dg
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($jd?$J:"<form action='' method='post'>\n".$J.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$Ca=$_POST["auth"];if($Ca&&(!adminer()->verifyLoginToken()||verify_token())){session_regenerate_id();$Zl=$Ca["driver"];$O=$Ca["server"];$V=$Ca["username"];$F=(string)$Ca["password"];$h=$Ca["db"];set_password($Zl,$O,$V,$F);$_SESSION["db"][$Zl][$O][$V][$h]=true;if($Ca["permanent"]){$w=implode("-",array_map('base64_encode',array($Zl,$O,$V,$h)));$Bi=adminer()->permanentLogin(true);$ii[$w]="$w:".base64_encode($Bi?encrypt_string($F,$Bi):"");cookie("adminer_permanent",implode(" ",$ii));}if(!array_diff(array_keys($_POST),array("auth","token"))||$Zl!=DRIVER||$O!=SERVER||$V!==$_GET["username"]||$h!=DB)redirect(auth_url($Zl,$O,$V,$h));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){Driver::disconnect();foreach(array("pwds","db","dbs","queries")as$w)set_session($w,null);unset_permanent($ii);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.'Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.');}elseif($ii&&!$_SESSION["pwds"]){session_regenerate_id();$Bi=adminer()->permanentLogin();foreach($ii
as$w=>$X){list(,$lb)=explode(":",$X);list($Zl,$O,$V,$h)=array_map('base64_decode',explode("-",$w));set_password($Zl,$O,$V,decrypt_string(base64_decode($lb),$Bi));$_SESSION["db"][$Zl][$O][$V][$h]=true;}}function
unset_permanent(array&$ii){foreach($ii
as$w=>$X){list($Zl,$O,$V,$h)=array_map('base64_decode',explode("-",$w));if($Zl==DRIVER&&$O==SERVER&&$V==$_GET["username"]&&$h==DB)unset($ii[$w]);}cookie("adminer_permanent",implode(" ",$ii));}function
auth_error($j,array&$ii,$df=true){$Oj=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$Oj]||$_GET[$Oj])&&!$_SESSION["token"])$j='Session expired. Please log in again.';elseif($df&&($F=get_password())!==null){restart_session();add_invalid_login();if($F===false)$j
.=($j?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($ii);}}if(!$_COOKIE[$Oj]&&$_GET[$Oj]&&ini_bool("session.use_only_cookies"))$j='Session support must be enabled.';$Ph=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$Ph["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$j,null);echo"<form action='' method='post'>\n","<div>";if(hidden_fields($_POST,array("auth","token")))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($ii);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($ii);$Nb=adminer()->credentials();$e=Driver::connect($Nb[0],$Nb[1],$Nb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(array("vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)));}}$Sf=null;if(!is_object($e)||($Sf=adminer()->login($_GET["username"],get_password()))!==true){$j=(is_string($e)?nl_br(h($e)):(is_string($Sf)?$Sf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($j,$ii);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($Ca&&$_POST["token"])$_POST["token"]=get_token();$j='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$j='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$j=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$j
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($I,$f=null,array$Bh=array(),&$y=0,&$Ic=false){$Of=array();$v=array();$d=array();$T=array();$_i=array();$Mc=array();$wl=array();$J=array();$Ag=$Ic;$Ic=false;for($r=0;(!$y||$r<$y)&&($K=$I->fetch_row());$r++){if(!$r){echo"<div class='scrollable'>\n","<table class='nowrap odds'".($Ag?on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown'):"").">\n","<thead><tr>";for($pf=0;$pf<count($K);$pf++){$k=$I->fetch_field();$B=$k->name;$R=(isset($k->table)?$k->table:"");$Ah=(isset($k->orgtable)?$k->orgtable:"");$_h=(isset($k->orgname)?$k->orgname:$B);$vl=driver()->typeName($k);if($Bh&&JUSH=="sql")$Of[$pf]=($B=="table"?"table=":($B=="possible_keys"?"indexes=":null));elseif($Ah!=""){$pa=($R!=""?$R:$Ah);if($R!="")$J[$R]=$Ah;if(!isset($v[$pa])){if(!isset($_i[$Ah])){$_i[$Ah]=array();foreach(indexes($Ah,$f)as$u){if($u["type"]=="PRIMARY"){$_i[$Ah]=array_flip($u["columns"]);break;}}}$T[$pa]=$Ah;$v[$pa]=$_i[$Ah];$d[$pa]=$_i[$Ah];}if(isset($d[$pa][$_h])){unset($d[$pa][$_h]);$v[$pa][$_h]=$pf;$Of[$pf]=$pa;}elseif($Ag&&isset($k->orgname)&&$k->db==DB&&!is_blob(array("type"=>$vl)))$Mc[$pf]=array($pa,$_h,preg_match('~text|json|lob~',$vl));}$wl[$pf]=$vl;echo"<th title='".h(trim(($Ah!=""?"$Ah.$_h":($k->name!=$_h?$_h:""))." ".$vl))."'>".h($B).($Bh?doc_link(array('sql'=>"explain-output.html#explain_".strtolower($B),'mariadb'=>"explain/#the-columns-in-explain-select",)):"");}foreach($Mc
as$pf=>$Ya){if($d[$Ya[0]])unset($Mc[$pf]);}echo"<tbody>\n";}$Fe=array();foreach($v
as$pa=>$u){if($u&&!$d[$pa]){$t="";foreach($u
as$ob=>$pf){if($K[$pf]===null){$t=null;break;}$t
.="&where[".url_escape(bracket_escape($ob))."]=".url_escape($K[$pf]);}$Fe[$pa]=$t;}}echo"<tr>";foreach($K
as$w=>$X){$z="";if(isset($Of[$w])){if($Bh&&JUSH=="sql"){$R=$K[array_search("table=",$Of)];$z=ME.$Of[$w].url_escape($Bh[$R]!=""?$Bh[$R]:$R);}elseif(idx($Fe,$Of[$w])!==null)$z=ME."edit=".url_escape($T[$Of[$w]]).$Fe[$Of[$w]];}$Be=select_value($X,$z,array('type'=>(preg_match('~binary~',$wl[$w])?'blob':$wl[$w])),null);$b="";$Ya=idx($Mc,$w);if($Ya&&idx($Fe,$Ya[0])!==null&&is_utf8($X)){$Ic=true;$b=" data-name='".h("val[".bracket_escape($T[$Ya[0]])."][".bracket_escape(substr($Fe[$Ya[0]],1))."][".bracket_escape($Ya[1])."]")."' data-text='".($Ya[2]?1:0)."'".(html_entity_decode(strip_tags($Be),ENT_QUOTES,"UTF-8")!==$X?" data-value='".h($X)."'":"");}echo"<td".(preg_match(number_type(),$wl[$w])?" class='number'":"")."$b>$Be";}}$y=$r;echo($r?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$J;}function
textarea($B,$Y,$L=10,$sb=80,$rf=JUSH){echo"<textarea name='".h($B)."' rows='$L' cols='$sb' class='sqlarea jush-".h($rf)."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$C,$Y="",$ji=""){if($C&&$Y!=""&&!isset($C[$Y]))$C=array($Y=>$Y)+$C;$Nk=($C?"select":"input");return"<$Nk$b".($C?"><option value=''>$ji".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$ji'>");}function
json_row($w,$X=null,$bd=true){static$Ed=true;if($Ed)echo"{";if($w!=""){echo($Ed?"":",")."\n\t\"".addcslashes($w,"\r\n\t\"\\/").'": '.($X!==null?($bd?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$Ed=false;}else{echo"\n}\n";$Ed=true;}}function
flat_collations(){$rb=collations();return(is_array(reset($rb))?call_user_func_array('array_merge',array_values($rb)):$rb);}function
edit_type($w,array$k,array$rb,array$Ld=array(),array$rd=array()){$U=(string)$k["type"];echo"<td><select name='".h($w)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($Ld[$U])&&!in_array($U,$rd))$rd[]=$U;$pk=driver()->structuredTypes();if($Ld)$pk['Foreign keys']=$Ld;echo
optionlist(array_merge($rd,$pk),$U),"</select><td>","<input name='".h($w)."[length]' value='".h($k["length"])."' size='3'".(!$k["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($rb?"<input list='collations' name='".h($w)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($k["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($w)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$k["unsigned"]).'</select>':''),(isset($k['on_update'])?"<select name='".h($w)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist(array(""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"),(preg_match('~^CURRENT_TIMESTAMP~i',$k["on_update"])?"CURRENT_TIMESTAMP":$k["on_update"])).'</select>':''),($Ld?"<select name='".h($w)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$k["on_delete"])."</select> ":" ");}function
option_types($U,$wl){return" data-types='".h($wl)."'".(preg_match("~$wl~",$U)?"":" class='hidden'");}function
process_length($x){if(JUSH=="mssql"&&preg_match('~^\s*\(?\s*max\s*\)?\s*$~i',$x))return"(max)";$Wc=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$Wc(?:\\s*,\\s*$Wc)*+\\s*\\)?\\s*\$~",$x)&&preg_match_all("~$Wc~",$x,$Wf)?"(".implode(",",$Wf[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$x)));}function
process_in($X){$X=preg_replace('~^\s*\(\s*(.*?)\s*\)\s*$~s','\1',$X);$Li="'((?:[^']|'')*+)'";$of=(preg_match("~^\\s*$Li(?:\\s*,\\s*$Li)*+\\s*\$~",$X)&&preg_match_all("~$Li~",$X,$Wf)?str_replace("''","'",$Wf[1]):array_map('trim',explode(",",$X)));return"(".implode(", ",array_map('Adminer\q',$of)).")";}function
process_between($X){$Li="'((?:[^']|'')*+)'";$Ni=(preg_match("~^\\s*$Li\\s+AND\\s+$Li\\s*\$~i",$X,$A)?array(str_replace("''","'",$A[1]),str_replace("''","'",$A[2])):preg_split('~\s+AND\s+~i',trim($X)));return(count($Ni)==2?q($Ni[0])." AND ".q($Ni[1]):"NULL AND NULL");}function
process_type(array$k,$pb="COLLATE"){return" ".(is_user_type($k["type"])?idf_escape($k["type"]):$k["type"]).process_length($k["length"]).(preg_match(number_type(),$k["type"])&&in_array($k["unsigned"],driver()->unsigned)?" $k[unsigned]":"").(preg_match('~'.text_type().'~',$k["type"])&&$k["collation"]?" $pb ".(JUSH=="mssql"&&preg_match('~^\w+$~',$k["collation"])?$k["collation"]:q($k["collation"])):"");}function
process_field(array$k,array$tl){if($k["on_update"])$k["on_update"]=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$k["on_update"]);return
array(idf_escape(trim($k["field"])),process_type($tl),($k["null"]?" NULL":" NOT NULL"),default_value($k),(preg_match('~timestamp|datetime~',$k["type"])&&$k["on_update"]?" ON UPDATE $k[on_update]":""),(support("comment")&&$k["comment"]!=""?" COMMENT ".q($k["comment"]):""),($k["auto_increment"]?auto_increment():null),);}function
default_value(array$k){if($k["default"]===null)return"";$i=str_replace("\r","",$k["default"]);$Wd=$k["generated"];$Q=!preg_match('~]$~',$k["length"])&&(preg_match('~char|binary|text|json|enum|set|String~',$k["type"])||driver()->enumLength($k));return(in_array($Wd,driver()->generated)?(JUSH=="mssql"?" AS ($i)".($Wd=="VIRTUAL"?"":" $Wd"):" GENERATED ALWAYS AS ($i) $Wd"):(preg_match('~^GENERATED ~i',$i)?" $i":" DEFAULT ".($Q||preg_match('~^(?![a-z])~i',$i)?(JUSH=="sql"&&preg_match('~text|json~',$k["type"])?"(".q($i).")":q($i)):str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",(JUSH=="sqlite"?"($i)":$i)))));}function
edit_fields(array$l,array$rb,$U="TABLE",array$Ld=array()){$l=array_values($l);$ec=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$wb=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<th id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<th id='label-length'>".'Length',"<th>".'Options';if($U=="TABLE")echo"<th id='label-null'>NULL\n","<th><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>AI</abbr>",doc_link(array('sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",)),"<th id='label-default'$ec>".'Default value',(support("comment")?"<th id='label-comment'$wb>".'Comment':"");$Bf=!support("move_col");echo"<td>".icon("plus","add[".($Bf?count($l):0)."]","+",'Add next',($Bf?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($l
as$r=>$k){$r++;$Ch=$k[($_POST?"orig":"field")];$rc=(isset($_POST["add"][$r-1])||(isset($k["field"])&&!idx($_POST["drop_col"],$r)))&&(support("drop_col")||$Ch=="");echo"<tr".($rc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$r][inout]",explode("|",driver()->inout),$k["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($rc)echo"<input name='fields[$r][field]' value='".h($k["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$r-1])?" autofocus":"").">";echo
input_hidden("fields[$r][orig]",$Ch);edit_type("fields[$r]",$k,$rb,$Ld);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$r][null]",1,$k["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$r'".($k["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$ec>".(driver()->generated?html_select("fields[$r][generated]",array_merge(array("","DEFAULT"),driver()->generated),$k["generated"])." ":checkbox("fields[$r][generated]",1,$k["generated"],"","","","label-default"));$b=" name='fields[$r][default]' aria-labelledby='label-default'";$Y=h($k["default"]);echo(preg_match('~\n~',$k["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$r][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$wb>".adminer()->commentInput('COLUMN',$b,$k["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$r]","+",'Add next')." ":""),($Ch==""||support("drop_col")?icon("cross","drop_col[$r]","x",'Remove'):"");}}function
process_fields(array&$l){if($_POST["add"]){$l=array_values($l);array_splice($l,key($_POST["add"]),0,array(array()));}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($Dc,$Kb,$Ec,$Tk,$Fc,$_,$rg,$pg,$qg,$lh,$Rg){if($_POST["drop"])query_redirect($Dc,$_,$rg);elseif($lh=="")query_redirect($Kb,$_,$qg);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($_,$pg,queries($Dc)&&queries($Kb)&&driver()->commit());driver()->rollback();}elseif($lh!=$Rg){$Mb=queries($Kb);queries_redirect($_,$pg,$Mb&&queries($Dc));if($Mb&&$Ec)queries($Ec);}else
queries_redirect($_,$pg,queries($Tk)&&queries($Fc)&&queries($Dc)&&queries($Kb));}function
create_trigger($oh,array$K){$Zk=" $K[Timing] $K[Event]".(preg_match('~ OF~',$K["Event"])?" $K[Of]":"");return"CREATE TRIGGER ".idf_escape($K["Trigger"]).(JUSH=="mssql"?$oh.$Zk:$Zk.$oh).preg_replace('~[\s;]+$~',''," $K[Type]\n$K[Statement]").";";}function
q_dollar($Q){$jc='$$';while(strpos($Q.$jc,$jc)!=strlen($Q))$jc='$_'.substr($jc,1);return$jc.$Q.$jc;}function
routine_collate($qb){static$db=array();if($qb&&!$db){foreach(collations()as$cb=>$Wl){foreach((array)$Wl
as$X)$db[$X]=$cb;}}return($db[$qb]?"CHARACTER SET ".q($db[$qb])." ":"")."COLLATE";}function
create_routine($kj,array$K){$P=array();$l=$K["fields"];ksort($l);foreach($l
as$k){if($k["field"]!=""){$Ve=(preg_match("~^(".driver()->inout.")\$~",$k["inout"])?$k["inout"]:"");$P[]="\n  ".(JUSH=="mssql"?"@$k[field]".process_type($k).($Ve?" $Ve":""):($Ve?"$Ve ":"").idf_escape($k["field"]).process_type($k,routine_collate($k["collation"])));}}$gc="";$C=array();foreach(routine_options($kj)as$w=>$Xl){$Y=idx($K["options"],$w,"");if($w=="DEFINER")$gc=($Y?" $w=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$Xl){if($Y!="")$C[]="$w ".q($Y);}elseif($Y!=reset($Xl)&&in_array($Y,$Xl))$C[]=$Y;}$_f=$K["language"];$hc=preg_replace('~[\s;]+$~','',$K["definition"]);$_c=(JUSH=="pgsql"||($_f&&$_f!="sql"));$Oh=($P?implode(",",$P)."\n":"");return"CREATE$gc $kj ".table(trim($K["name"])).(JUSH=="mssql"&&$kj=="PROCEDURE"?rtrim($Oh):" ($Oh)").($kj=="FUNCTION"?"\nRETURNS".process_type($K["returns"],routine_collate($K["returns"]["collation"])):"").($_f?" LANGUAGE $_f":"").($C?"\n".implode(" ",$C):"").($_c?" AS ".q_dollar("\n".trim($hc)."\n"):(JUSH=="mssql"?"\nAS":"")."\n$hc;");}function
remove_definer($H){$gc=implode("@",array_map('Adminer\idf_escape',explode("@",logged_user(),2)));return
preg_replace('(^([A-Z =]+) DEFINER='.preg_quote($gc).')','\1',$H);}function
object_name($U,$R,array$d){return
str_replace(array("{table}","{columns}"),array($R,implode("_",$d)),adminer()->namePattern($U));}function
format_foreign_key(array$n,$B=""){$h=$n["db"];$Yg=$n["ns"];return($B!=""?" CONSTRAINT ".idf_escape($B):"")." FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$n["source"])).") REFERENCES ".($h!=""&&$h!=$_GET["db"]?idf_escape($h).".":"").($Yg!=""&&$Yg!=$_GET["ns"]?idf_escape($Yg).".":"").idf_escape($n["table"])." (".implode(", ",array_map('Adminer\idf_escape',$n["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$n["on_delete"])?" ON DELETE $n[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$n["on_update"])?" ON UPDATE $n[on_update]":"").($n["deferrable"]?" $n[deferrable]":"");}function
tar_file($m,$el){$J=pack("a100a8a8a8a12a12",$m,644,0,0,decoct($el->size),decoct(time()));$ib=8*32;for($r=0;$r<strlen($J);$r++)$ib+=ord($J[$r]);$J
.=sprintf("%06o",$ib)."\0 ";echo$J,str_repeat("\0",512-strlen($J));$el->send();echo
str_repeat("\0",511-($el->size+511)%512);}function
doc_version(){$Mj=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$Mj,$A);return($A[1]>=18?$A[1]:"19");}$Wi=(JUSH=='sql'||connection()->flavor=='cockroach'?'~^\d+\.\d+~':'~^\d\.?\d~');$am=(preg_match($Wi,$Mj,$A)?$A[0]:"");if(JUSH=='mssql')return($am>=15?"sql-server-ver$am":($am==12?"azuresqldb-current":"sql-server-2017"));return$am;}function
doc_link(array$ei,$Uk="📖"){$am=doc_version();$Ll=array('sql'=>"https://dev.mysql.com/doc/refman/$am/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$am)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$am/",);if(connection()->flavor=='maria'){$Ll['sql']="https://mariadb.com/kb/en/";$ei['sql']=($ei['mariadb']?:str_replace(".html","/",$ei['sql']));}if(connection()->flavor=='cockroach'&&$ei['cockroach']){$Ll['pgsql']="https://docs.cockroachlabs.com/docs/v$am/";$ei['pgsql']=$ei['cockroach'];}return($ei[JUSH]?" <a href='".h($Ll[JUSH].$ei[JUSH].(JUSH=='mssql'?"?view=$am":""))."'".target_blank()." class='doc' title='".'Documentation'."'>$Uk</a>":"");}function
db_size($h){if(!connection()->select_db($h))return"?";$J=0;foreach(table_status()as$S)$J+=$S["Data_length"]+$S["Index_length"];return
adminer()->formatSizeValue($J);}function
set_utf8mb4($Kb){static$P=false;if(!$P&&preg_match('~\butf8mb4~i',$Kb)){$P=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'));if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!="")page_header('Database'.": ".h(DB),adminer()->error(),true,"","db");else{if(!isset($_GET["db"])&&support("single_db")){$g=adminer()->databases();if($g)redirect(ME."db=".url_escape($g[0]));}if($_POST["db"]&&!$j)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$j,false);echo"<p class='links'>\n";foreach(array('database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',)as$w=>$X){if(support($w))echo"<a href='".h(ME)."$w='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$g=adminer()->databases();if($g){$wj=support("scheme");$rb=collations();echo"<form action='' method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<th>".'Collation'."<th>".'Tables'."<th>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$g=($_GET["dbsize"]?count_tables($g):array_flip($g));foreach($g
as$h=>$T){$jj=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($h);$s=h("Db-".$h);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$h,in_array($h,(array)$_POST["db"]),"","","",$s):""),"<th><a href='$jj' id='$s'>".h($h)."</a>";$qb=h(db_collation($h,$rb));echo"<td>".(support("database")?"<a href='$jj".($wj?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$qb</a>":$qb),"<td align='right'><a href='$jj&amp;schema=' id='tables-".h($h)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($h)."'>".($_GET["dbsize"]?db_size($h):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ja=adminer();$ni=($ja
instanceof
Plugins?$ja->plugins:array());$Cc=($ja
instanceof
Plugins?$ja->drivers:array());$oc=design_checksums();if($ni||$Cc||$oc){$jb=($ja
instanceof
Plugins?$ja->checksums():array());$eh=Plugins::officialChecksums();$Gl=function($Kl){return" (<a href='$Kl'".target_blank()." class='update'>".VERSION."</a>)";};$mi=function($zd)use($jb,$eh,$Gl){return($jb[$zd]&&$eh[$zd]&&$jb[$zd]!==$eh[$zd]?$Gl("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($ni
as$ki){$Ui=new
\ReflectionObject($ki);$lc=(method_exists($ki,'description')?$ki->description():"");if(!$lc){if(preg_match('~^/[\s*]+(.+)~',$Ui->getDocComment(),$A))$lc=$A[1];}$xj=(method_exists($ki,'screenshot')?$ki->screenshot():"");echo"<li><b>".get_class($ki)."</b>".h($lc?": $lc":"").($xj?" (<a href='".h($xj)."'".target_blank().">".'screenshot'."</a>)":"").$mi(basename((string)$Ui->getFileName(),'.php'))."\n";}foreach($Cc
as$s=>$B)echo"<li><b>".h($s)."</b>: ".h($B).$mi(basename((string)$ja->driverFiles[$s],'.php'))."\n";if($oc){$gh=official_design_checksums();foreach($oc
as$m=>$nc){list($B,$ib)=$nc;$fh=$gh["$B/$m"];echo"<li><b>".h($m)."</b>".h($B?": $B":"").($fh&&$fh!==$ib?$Gl("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($Db){$this->size+=strlen($Db);fwrite($this->handler,$Db);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$l=fields($a);header("Content-Type: application/octet-stream");$Xl=array_merge((array)$_GET["where"],(array)$_GET["val"]);header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$Xl)).".".friendly_url($_GET["field"]));$N=array(idf_escape($_GET["field"]));$I=driver()->select($a,$N,array(where($_GET,$l)),$N);$K=($I?$I->fetch_row():array());echo
driver()->value($K[0],$l[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$l=fields($a);if(!$l)$j=adminer()->error();$S=table_status1($a);$B=adminer()->tableName($S);$j=$j?:h($S["Error"]);page_header(($l&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($B!=""?$B:h($a)),$j,array(),"",!$l,($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$ij=array();foreach($l
as$w=>$k)$ij+=$k["privileges"];adminer()->selectLinks($S,(isset($ij["insert"])||!support("table")?"":null));$vb=$S["Comment"];if($vb!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$vb)."\n";if($l)adminer()->tableStructurePrint($l,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$K){$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($K["ns"]),ME);echo"<li><a href='".h($z."table=".url_escape($K["table"]))."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>";}echo"</ul>\n";}$Te=driver()->inheritsFrom($a);if($Te){echo"<h3>".'Inherits from'."</h3>\n";tables_links($Te);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$v=indexes($a);if($v)adminer()->tableIndexesPrint($v,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$Ld=foreign_keys($a);if($Ld){echo"<table>\n","<thead><tr><th>".'Source'."<th>".'Target'."<th>".'ON DELETE'."<th>".'ON UPDATE'."<td class='hover'><tbody>\n";foreach($Ld
as$B=>$n){echo"<tr title='".h($B)."'>","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$n["source"]))."</i>";$z=($n["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($n["db"]),ME):($n["ns"]!=""?preg_replace('~&ns=[^&]*~',"&ns=".url_escape($n["ns"]),ME):ME));echo"<td><a href='".h($z."table=".url_escape($n["table"]))."'>".($n["db"]!=""&&$n["db"]!=DB?"<b>".h($n["db"])."</b>.":"").($n["ns"]!=""&&$n["ns"]!=$_GET["ns"]?"<b>".h($n["ns"])."</b>.":"").h($n["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$n["target"]))."</i>)","<td>".h($n["on_delete"]),"<td>".h($n["on_update"]),'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($B)).'">'.'Alter'.'</a>',"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n","</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$fb=driver()->checkConstraints($a);if($fb){echo"<table>\n";foreach($fb
as$w=>$X)echo"<tr title='".h($w)."'>","<td><code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$ql=triggers($a);if($ql){echo"<table>\n";foreach($ql
as$w=>$X){echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($w)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($w))."'>".'Alter'."</a>";$kj=$X[2];if($kj){$mj=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($kj["ns"]),ME).'function='.url_escape($kj["function"]).'&name='.url_escape($kj["name"]);echo", <a href='".h($mj)."' title='".h($kj["name"])."'>".'Alter function'."</a>";}echo"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$Rj=driver()->shadowTables($a);if($Rj){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($Rj);}$Se=driver()->inheritedTables($a);if($Se){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$Sh=driver()->partitionsInfo($a);if($Sh)echo"<p><code class='jush-".JUSH."'>BY ".h("$Sh[partition_by]($Sh[partition])")."</code>\n";tables_links($Se);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",array(),h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$Ti,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($Ti,$R)as$B=>$Vi){if($B!=$R)$d[$R]=max($d[$R],schema_column($B,$Ti,$d)+1);}}return$d[$R];}function
type_class($U){foreach(array('char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',)as$w=>$X){if(preg_match("~$w|$X~",$U))return" class='$w'";}}$Ek=array();$Gk=array();$Fk=array();$wd=array();$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$Wf,PREG_SET_ORDER);foreach($Wf
as$r=>$A){$Ek[$A[1]]=array((float)$A[2],(float)$A[3]);$Gk[]="\n\t'".js_escape($A[1])."': [ $A[2], $A[3] ]";}$M=array();$Ti=array();$Ld=array();$ra=driver()->allFields();$ue=array();$Hk=array();foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$Hk[$R]=$S;else$ue[$R]=true;}}foreach($Hk
as$R=>$S){$G=0;$M[$R]["fields"]=array();foreach($ra[$R]as$k){$G+=1.25;$wd[$R][$k["field"]]=$G;$M[$R]["fields"][$k["field"]]=$k;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$ue[$X["table"]]){$Ld[$R][]=$X;$Ti[$X["table"]][$R]=array();}}}$d=array();$ae=array();$mm=array();$fe=array();foreach(array_keys($M)as$B)schema_column($B,$Ti,$d);arsort($d);foreach($d
as$B=>$c){$xg=null;foreach((array)idx($Ld,$B)as$X){if($X["table"]!=$B&&$M[$X["table"]])$xg=($xg===null?$d[$X["table"]]:min($xg,$d[$X["table"]]));}$d[$B]=max($c,(int)$xg-1);}foreach($M
as$B=>$R){$c=$d[$B];$ae[$c][]=$B;$Wk=.75*strlen($B);foreach($R["fields"]as$k)$Wk=max($Wk,.65*strlen($k["field"]));$mm[$c]=max(idx($mm,$c,0),ceil($Wk)+1);}foreach($Ld
as$B=>$Wl){foreach($Wl
as$X){$ee=$d[$B]+(idx($d,$X["table"],$d[$B])>$d[$B]?1:0);$fe[$ee]=idx($fe,$ee,0)+1;}}ksort($ae);$se=0;$lm=0;$tb=0;$yi=null;$Ck=array();$Jk=array();foreach($ae
as$c=>$T){if($yi!==null){$tb=round($tb+$mm[$yi]+1.7+idx($fe,$c,0)*.1,1);$D=array();foreach($T
as$B){$uk=0;$Jb=0;$Og=array_keys((array)idx($Ti,$B));foreach((array)idx($Ld,$B)as$X)$Og[]=$X["table"];foreach($Og
as$Ig){if($M[$Ig]&&$d[$Ig]<$c){$uk+=$M[$Ig]["pos"][0];$Jb++;}}$D[$B]=($Jb?$uk/$Jb:$se);}asort($D);$T=array_keys($D);}$hl=0;foreach($T
as$B){$G=1.25*count($M[$B]["fields"]);$M[$B]["pos"]=($Ek[$B]?:array($hl,$tb));$Ck[$B]=$M[$B]["pos"][1];$Jk[$B]=$mm[$c];$hl+=2.5+$G;$se=max($se,$M[$B]["pos"][0]+2.5+$G);$lm=max($lm,round($M[$B]["pos"][1]+$mm[$c],1));if(!$Ek[$B])$Fk[]="\n\t'".js_escape($B)."': [ ".$M[$B]["pos"][0].", ".$M[$B]["pos"][1]." ]";}$yi=$c;}$Ff=array();$Ma=array();foreach($Ld
as$B=>$Wl){foreach($Wl
as$X){$Pk=idx($Ck,$X["table"],$Ck[$B]);$bk=$Ck[$B]+$Jk[$B];$hj=($Pk-1>$bk);$Df=($hj?$bk+1:min($Ck[$B],$Pk)-1);$La=idx($Ma,(string)$Df,0);$Ma[(string)$Df]=$La+1;$Df=round($hj?min($Df+$La*.1,$Pk-1):$Df-$La*.1,1);while($Ff[(string)$Df])$Df-=.0001;$M[$B]["references"][$X["table"]][(string)$Df]=array($X["source"],$X["target"]);$Ti[$X["table"]][$B][(string)$Df]=$X["target"];$Ff[(string)$Df]=true;}}echo'<div id="schema" style="height: ',$se,'em; width: ',$lm,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$Gk)."\n",'};
const tablePosDefault = {',implode(",",$Fk)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$se,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($M
as$B=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$Jk[$B]."em;'>",'<a href="'.h(ME).'table='.url_escape($B).'"><b>'.h($B)."</b></a>";foreach($R["fields"]as$k){$X='<span'.type_class($k["type"]).' title="'.h($k["type"].($k["length"]?"($k[length])":"").($k["null"]?" NULL":'')).'">'.h($k["field"]).'</span>';echo"<br>".($k["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$Qk=>$Vi){foreach($Vi
as$Df=>$Qi){$Ef=$Df-$R["pos"][1];$rk=($Ef>0?"left: 100%; width: calc($Ef"."em - 100%)":"left: $Ef"."em");$lm=($Ef>0?"100%":(-$Ef)."em");$r=0;foreach($Qi[0]as$ak)echo"\n<div class='references' title='".h($Qk)."' id='refs$Df-".($r++)."' style='$rk"."; top: ".$wd[$B][$ak]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $lm;'></div></div>";}}foreach((array)$Ti[$B]as$Qk=>$Vi){foreach($Vi
as$Df=>$Rk){$Ef=$Df-$R["pos"][1];$r=0;foreach($Rk
as$Ok)echo"\n<div class='references arrow' title='".h($Qk)."' id='refd$Df-".($r++)."' style='left: $Ef"."em; top: ".$wd[$B][$Ok]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$Ef)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($M
as$B=>$R){foreach((array)$R["references"]as$Qk=>$Vi){if($M[$Qk]){foreach($Vi
as$Df=>$Qi){$yg=$se;$eg=-10;foreach($Qi[0]as$w=>$ak){$pi=$R["pos"][0]+$wd[$B][$ak];$qi=$M[$Qk]["pos"][0]+$wd[$Qk][$Qi[1][$w]];$yg=min($yg,$pi,$qi);$eg=max($eg,$pi,$qi);}echo"<div class='references' id='refl$Df' style='left: $Df"."em; top: $yg"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($eg-$yg)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$j){$i=array("auto_increment"=>'');foreach(array("type","routine","event","trigger")as$wk){if(support($wk))$i[$wk."s"]='';}save_settings(array_intersect_key($_POST+$i,array_flip(array("output","format","db_style","schema_style","table_style","data_style"))+$i),"adminer_export");$qa=(DB==""||$_GET["ns"]==="");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$nd=dump_headers((count($T)==1?key($T):DB),($qa||count($T)>1));$kf=preg_match('~sql~',$_POST["format"]);if($kf){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$rk=$_POST["db_style"];$g=array(DB);if(DB==""){$g=$_POST["databases"];if(is_string($g))$g=explode("\n",rtrim(str_replace("\r","",$g),"\n"));}foreach((array)$g
as$h){adminer()->dumpDatabase($h);if(connection()->select_db($h)){if($kf&&$rk)echo
use_sql($h,$rk).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?array(""):adminer()->schemas()))as$M){if($M!=""){if(DB==""&&information_schema(DB,$M))continue;set_schema($M);}if($kf&&$_POST["schema_style"]&&function_exists('Adminer\use_schema_sql'))echo
use_schema_sql($_GET["ns"],$_POST["schema_style"]).";\n\n";$nk=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):array());$md=array();$Wb=array();foreach($nk
as$B=>$S){if($qa||in_array($B,(array)$_POST["tables"]))$md[$B]=$S;if($qa||in_array($B,(array)$_POST["data"]))$Wb[$B]=$S;}if($kf){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($md);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$rl=array();foreach($Wb
as$B=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($md[$B])))$rl[]=$B;}echo
truncate_all_sql($rl);}$Jh="";if($_POST["types"]){foreach(types()as$s=>$U){$hc=type_definition($s);$bh=($hc["kind"]=='d'?"DOMAIN":"TYPE");if($hc["definition"])$Jh
.=($rk!='DROP+CREATE'?"DROP $bh IF EXISTS ".table($U).";;\n":"")."CREATE $bh ".table($U)." $hc[definition];\n\n";else$Jh
.="-- Could not export type ".preg_replace('~[\r\n]+~',' ',$U)."\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$B=$K["ROUTINE_NAME"];$kj=$K["ROUTINE_TYPE"];$Re=routine($K["SPECIFIC_NAME"],$kj);if($Re["definition"]!=""){$Kb=create_routine($kj,array("name"=>$B)+$Re);set_utf8mb4($Kb);$Jh
.=($rk!='DROP+CREATE'?"DROP $kj IF EXISTS ".table($B).";;\n":"")."$Kb;\n\n";}else$Jh
.="-- Could not export ".strtolower($kj)." ".preg_replace('~[\r\n]+~',' ',$B)."\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$Kb=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($Kb);$Jh
.=($rk!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$Kb;;\n\n";}}echo($Jh&&JUSH=='sql'?"DELIMITER ;;\n\n$Jh"."DELIMITER ;\n\n":$Jh);}if($_POST["table_style"]||$_POST["data_style"]){$cm=array();foreach($nk
as$B=>$S){$R=array_key_exists($B,$md);$Ub=array_key_exists($B,$Wb);if($R||$Ub){$el=null;if($nd=="tar"){$el=new
TmpFile;ob_start(array($el,'write'),1e5);}adminer()->dumpTable($B,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$cm[]=$B;elseif($Ub){$l=fields($B);$N=array("*");$Gb=convert_fields($l,$l);if($Gb)$N[]=substr($Gb,2);adminer()->dumpData($B,$_POST["data_style"],"",$N);}if($kf&&$_POST["triggers"]&&$R&&($ql=trigger_sql($B)))echo"\nDELIMITER ;;\n$ql\nDELIMITER ;\n";if($nd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$h/")."$B.csv",$el);}elseif($kf)echo"\n";}}if($kf&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($md
as$B=>$S){if(!is_view($S))echo
foreign_keys_sql($B);}}if($kf){foreach($cm
as$bm)adminer()->dumpTable($bm,$_POST["table_style"],1);}if($nd=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$j,($_GET["export"]!=""?array("table"=>$_GET["export"]):array()),h(DB));echo'
<form action="" method="post">
<table class="layout">
';$ac=array('','USE','DROP+CREATE','CREATE');$uj=(JUSH=="mssql"?array('','DROP+CREATE','CREATE'):$ac);$Ik=array('','DROP+CREATE','CREATE');$Vb=array('','TRUNCATE+INSERT','INSERT');if(JUSH=="sql")$Vb[]='INSERT+UPDATE';$K=get_settings("adminer_export");if(!$K)$K=array("output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"schema_style"=>"","table_style"=>"DROP+CREATE","data_style"=>"INSERT");echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$K["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$K["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$ac,$K["db_style"]).(support("type")?checkbox("types",1,$K["types"],'User types'):"").(support("routine")?checkbox("routines",1,$K["routines"],'Routines'):"").(support("event")?checkbox("events",1,$K["events"],'Events'):"")),(function_exists('Adminer\use_schema_sql')?"<tr><th>".'Schema'."<td>".html_select('schema_style',$uj,$K["schema_style"]):""),"<tr><th>".'Tables'."<td>".html_select('table_style',$Ik,$K["table_style"]).checkbox("auto_increment",1,$K["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$K["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Vb,$K["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$xi=array();if($_GET["ns"]===""&&support("scheme")){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$M){if(!information_schema(DB,$M))echo"<tr><td>".checkbox("schemas[]",$M,true,$M,"","block")."\n";}}elseif(DB!=""){$gb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$gb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$gb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$cm="";$Lk=tables_list();foreach($Lk
as$B=>$U){$wi=preg_replace('~_.*~','',$B);$gb=($a==""||$a==(substr($a,-1)=="%"?"$wi%":$B));$Ai="<tr><td>".checkbox("tables[]",$B,$gb,$B,"","block");if($U!==null&&!preg_match('~table~i',$U))$cm
.="$Ai\n";else
echo"$Ai<td align='right'><label class='block'><span id='Rows-".h($B)."'></span>".checkbox("data[]",$B,$gb)."</label>\n";$xi[$wi]++;}echo$cm;if($Lk)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$g=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($g?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($g){foreach($g
as$h){if(!information_schema($h)){$wi=preg_replace('~_.*~','',$h);echo"<tr><td>".checkbox("databases[]",$h,$a==""||$a=="$wi%",$h,"","block")."\n";$xi[$wi]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$Ed=true;foreach($xi
as$w=>$X){if($w!=""&&$X>1){echo($Ed?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$w%")."'>".h($w)."</a>";$Ed=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$I=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$Yd=$I;if(!$I)$I=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($Yd?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($K=$I->fetch_assoc())echo'<tr><td>'.h($K["User"]),"<td>".h($K["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($K["User"]).'&host='.url_escape($K["Host"])).'">'.'Edit'."</a>\n";if(!$Yd||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$j&&$_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}if(!$j&&$_POST["val"]){$ma=0;$sk=true;$Za=array();$rj=0;foreach($_POST["val"]as$L)$rj+=count($L);$Oa=$rj>1&&driver()->begin();foreach($_POST["val"]as$Ak=>$L){$R=bracket_escape($Ak,true);$l=fields($R);$Bk=indexes($R);foreach($L
as$t=>$K){parse_str(bracket_escape($t,true),$Z);$zl=array();foreach($Z["where"]as$w=>$X)$zl[bracket_escape($w,true)]=$X;if(!$l||$Z["null"]||array_diff_key($zl,$l)||!unique_array($zl,$Bk)){$sk=false;break
2;}$P=array();$N=array();foreach($K
as$tf=>$X){$w=bracket_escape($tf,true);$k=idx($l,$w);if(!$k){$sk=false;break
3;}$P[idf_escape($w)]=(preg_match('~char|text~',$k["type"])||$X!=""?adminer()->processInput($k,$X):"NULL");$N[$tf]=$w;}$Ki=where($Z,$l);if(!driver()->update($R,$P," WHERE $Ki",0," ")){$sk=false;break
2;}$ma+=connection()->affected_rows;$d=array();foreach($N
as$w)$d[]=idf_escape($w);$Hl=driver()->select($R,$d,array($Ki),$d);$Sg=($Hl?$Hl->fetch_row():array());$pf=0;foreach($N
as$tf=>$w){$k=$l[$w];$qk=array('type'=>(preg_match('~binary~',$k["type"])?'blob':$k["type"]));$Za["val[$Ak][$t][$tf]"]=select_value(idx($Sg,$pf++),"",$qk,null);}}}if($Oa&&$sk)$sk=driver()->commit();queries_redirect(null,lang_format(array('%d item has been affected.','%d items have been affected.'),$ma),$sk);if($Oa&&!$sk)driver()->rollback();page_headers();page_messages($j);foreach($Za
as$B=>$X)echo"<div data-name='".h($B)."' hidden>$X</div>\n";exit;}restart_session();$ye=&get_session("queries");$xe=&$ye[DB];if(!$j&&$_POST["clear"]){$xe=array();redirect(remove_from_uri("history"));}stop_session();$ka=get_settings("adminer_import");if($_POST&&$ka)save_settings($ka,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$j);$Nf=driver()->lineComment();if(!$j&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$jc=driver()->delimiter;$o=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$ek=adminer()->importServerPath();$o=@fopen((file_exists($ek)?$ek:"compress.zlib://$ek.gz"),"rb");$H=($o?fread($o,1e6):false);}else$H=get_file("sql_file",true,$jc);if(is_string($H)){if(($mg=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($mg,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$Hi=$H.(preg_match("~$jc\\s*\$~",$H)?"":$jc);if(!$xe||first(end($xe))!=$Hi){restart_session();$xe[]=array($Hi,time());set_session("queries",$ye);stop_session();}}$ck="(?:\\s|\xEF\xBB\xBF|/\\*[\s\S]*?\\*/|(?:$Nf)[^\n]*\n?|--\r?\n)";$hh=0;$Rc=true;$Ib=false;$f=connect();if($f&&DB!=""){$f->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$f);}$ub=0;$Zc=array();$Qh='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$Nf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$il=microtime(true);while($H!=""){if(!$hh&&preg_match("~^$ck*+DELIMITER\\s+(\\S+)~i",$H,$A)){$jc=preg_quote($A[1]);$H=substr($H,strlen($A[0]));}elseif(!$hh&&JUSH=='pgsql'&&preg_match("~^($ck*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$A)){$jc="\n\\\\\\.\r?\n";$Ib=true;$hh=strlen($A[0]);}else{preg_match("($jc\\s*|$Qh)",$H,$A,PREG_OFFSET_CAPTURE,$hh);list($Nd,$G)=$A[0];if(!$Nd&&$o&&!feof($o))$H
.=fread($o,1e5);else{if(!$Nd&&rtrim($H)=="")break;$hh=$G+strlen($Nd);if($Nd&&!preg_match("(^$jc)",$Nd)){$Wa=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$fi=($Nd=='/*'?'\*/':($Nd=='['?']':(preg_match("~^(?:$Nf)~",$Nd)?"\n":preg_quote($Nd).($Wa?'|\\\\.':''))));while(preg_match("($fi|\$)s",$H,$A,PREG_OFFSET_CAPTURE,$hh)){$sj=$A[0][0];if(!$sj&&$o&&!feof($o))$H
.=fread($o,1e5);else{$hh=$A[0][1]+strlen($sj);if(!$sj||$sj[0]!="\\")break;}}}else{$Hi=substr($H,0,$G+($Ib?3:0));$H=substr($H,$hh);$hh=0;if($Ib){$jc=driver()->delimiter;$Ib=false;}$nb="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($Hi)."</code>";if(preg_match("~^$ck*+\$~",$Hi)&&!preg_match('~/\*M?!~',$Hi)){echo($_POST["only_errors"]?"":"<pre>$nb</pre>\n");continue;}$Rc=false;$ub++;$Ai="<pre id='sql-$ub'>$nb</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$ck*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$Hi,$A)!==0){echo$Ai,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$A[1])?'ATTACH':'VACUUM INTO')."\n";$Zc[]=" <a href='#sql-$ub'>$ub</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Ai;ob_flush();flush();}$jk=microtime(true);if(connection()->multi_query($Hi)&&$f&&preg_match("~^$ck*+USE\\b~i",$Hi))$f->query($Hi);do{$I=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$Ai:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$Zc[]=" <a href='#sql-$ub'>$ub</a>";if($_POST["error_stops"])break
2;}else{$z=ME."sql=".url_escape(trim($Hi));$Xk=" <span class='time'>(".format_time($jk).")</span>".(strlen($z)<adminer()->sqlUrlLength()-100?" <a href='".h($z)."'>".'Edit'."</a>":"");$ma=connection()->affected_rows;$fm=($_POST["only_errors"]?"":driver()->warnings());$gm="warnings-$ub";if($fm)$Xk
.=", <a href='#$gm' class='toggle'>".'Warnings'."</a>";$kd="";$ld="explain-$ub";if(is_object($I)){$y=$_POST["limit"];$Zg=$y;$Ic=!$_POST["only_errors"];if($Ic)echo"<form action='' method='post'>\n";$Bh=print_select_result($I,$f,array(),$Zg,$Ic);if(!$_POST["only_errors"]){$Zg=max($I->num_rows,$Zg);echo"<p class='sql-footer'>".($Zg?($y&&$Zg>$y?sprintf('%d / ',$y):"").lang_format(array('%d row','%d rows'),$Zg):""),$Xk;if($f&&preg_match("~^($ck|\\()*+SELECT\\b~i",$Hi)&&($kd=adminer()->explain($f,$Hi,$Bh))!="")echo", <a href='#$ld' class='toggle'>Explain</a>";if($Ic)echo", <input type='submit' name='save' value='".'Save'."' class='jsonly' disabled"." title='".'Ctrl+click on a value to modify it.'."'".on('click','sqlSave','Saving…').">";$s="export-$ub";echo", <a href='#$s' class='toggle'>".'Export'."</a><span id='$s' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ka["output"])." ".html_select("format",adminer()->dumpFormat(),$ka["format"]).input_hidden("query",$Hi)."<input type='submit' name='export' value='".'Export'."'".($y?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$ck*+(CREATE|DROP|ALTER)$ck++(DATABASE|SCHEMA)\\b~i",$Hi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(array('Query executed OK, %d row affected.','Query executed OK, %d rows affected.'),$ma)."$Xk\n";}echo($fm?"<div id='$gm' class='hidden'>\n$fm</div>\n":""),($kd!=""?"<div id='$ld' class='hidden explain'>\n$kd</div>\n":"");}$jk=microtime(true);}while(connection()->next_result());}}}}}if($Rc)echo"<p class='message'>".'No commands to execute.'."\n";else{$Le=connection()->inTransaction();driver()->rollback();if($Le)echo"<pre><code class='jush-".JUSH."'>ROLLBACK".(JUSH=="mssql"?" TRANSACTION":"")." -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(array('%d query executed OK.','%d queries executed OK.'),$ub-count($Zc))," <span class='time'>(".format_time($il).")</span>\n";elseif($Zc&&$ub>1)echo"<p class='error'>".'Error in query'.": ".implode("",$Zc)."\n";}}else
echo"<p class='error'>".upload_error($H)."\n";}echo'
<form action="" method="post" enctype="multipart/form-data" id="form"';$Il="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"),adminer()->sqlUrlLength());else
echo
on_upload_progress($Il);echo'>
';$hd="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$Hi=$_GET["sql"];if($_POST)$Hi=$_POST["query"];elseif($_GET["history"]=="all")$Hi=$xe;elseif($_GET["history"]!="")$Hi=idx($xe[$_GET["history"]],0);echo"<p>";textarea("query",$Hi,20);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$hd\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$ge=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($Il?input_hidden(ini_get("session.upload_progress.name"),$Il):""),"SQL$ge: ".file_input(" name='sql_file[]' multiple","\n$hd"),($Il?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$Ie=adminer()->importServerPath();if($Ie)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($Ie)."$ge</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$xe){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($xe);$X;$X=prev($xe)){$w=key($xe);list($Hi,$Xk,$Nc)=$X;echo'<div><a href="'.h(ME."sql=&history=$w").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$Xk)."'>".@date("H:i:s",$Xk)."</span>"." <code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$Nf).*~m",'',$Hi))),80,"</code>").($Nc?" <span class='time'>($Nc)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$l=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$l):""):where($_GET,$l));$Fl=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($l
as$B=>$k){if((!$Fl&&!isset($k["privileges"]["insert"]))||adminer()->fieldName($k)=="")unset($l[$B]);}if($_POST&&!$j&&!isset($_GET["select"])){$_=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$_=($Fl?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$_))$_=ME."select=".url_escape($a);$v=indexes($a);$_l=unique_array($_GET["where"],$v);$Ki="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($_,'Item has been deleted.',driver()->delete($a,$Ki,$_l?0:1));else{$P=array();foreach($l
as$B=>$k){$X=process_input($k);if($X!==false&&$X!==null)$P[idf_escape($B)]=$X;}if($Fl){if(!$P)redirect($_);queries_redirect($_,'Item has been updated.',driver()->update($a,$P,$Ki,$_l?0:1));if(is_ajax()){page_headers();page_messages($j);exit;}}else{$I=driver()->insert($a,$P);$Cf=($I?last_id($I):0);queries_redirect($_,sprintf('Item%s has been inserted.',($Cf?" $Cf":"")),$I);}}}$K=null;$H="";$Xk="";if($Z){$N=array();$Cj=array("*");foreach($l
as$B=>$k){if(isset($k["privileges"]["select"])){$_a=($_POST["clone"]&&$k["auto_increment"]?"''":convert_field($k));$c=($_a?"$_a AS ":"").idf_escape($B);$N[]=$c;if($_a)$Cj[]=$c;}}$K=array();if(!support("table")){$N=array("*");$Cj=$N;}if($N){$jk=microtime(true);$I=driver()->select($a,$N,array($Z),$N,array(),(isset($_GET["select"])?2:1));$H=str_replace("SELECT ".implode(", ",$N),"SELECT ".implode(", ",$Cj),driver()->query);$Xk=format_time($jk);if(!$I)$j=adminer()->error();else{$K=$I->fetch_assoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetch_assoc()))$K=null;}}if(!$l&&driver()->primary!=""){if(!$Z){$I=driver()->select($a,array("*"),array(),array("*"));$K=($I?$I->fetch_assoc():false);if(!$K)$K=array(driver()->primary=>"");}if($K){foreach($K
as$w=>$X){if(!$Z)$K[$w]=null;$l[$w]=array("field"=>$w,"null"=>($w!=driver()->primary),"auto_increment"=>($w==driver()->primary));}}}if($_POST["save"]){$ri=array();foreach((array)$_POST["fields"]as$w=>$X)$ri[bracket_escape($w,true)]=$X;$K=$ri+($K?$K:array());}edit_form($a,$l,$K,$Fl,$j,$H,$Xk);}elseif(isset($_GET["create"])){function
referencable_primary($Ej){$J=array();foreach(table_status('',true)as$Dk=>$R){if($Dk!=$Ej&&!$R["dependent"]&&fk_support($R)){foreach(fields($Dk)as$k){if($k["primary"]){if($J[$Dk]){unset($J[$Dk]);break;}$J[$Dk]=$k;}}}}return$J;}$a=$_GET["create"];$Uh=driver()->partitionBy;$Yh=($Uh&&$a!=""?driver()->partitionsInfo($a):array());$Si=referencable_primary($a);$Ld=array();foreach($Si
as$Dk=>$k)$Ld[str_replace("`","``",$Dk)."`".str_replace("`","``",$k["field"])]=$Dk;$Eh=array();$S=array();$Xg=false;if($a!=""){$Eh=fields($a);$S=table_status1($a);$Xg=(count($S)<2);}$ua=($a==""||driver()->supportsAlterTable($S));$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$j)save_settings(array("comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]));if($_POST&&!process_fields($K["fields"])&&!$j){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables(array($a)));else{$l=array();$ra=array();$Ml=false;$Jd=array();$Dh=reset($Eh);$oa=" FIRST";foreach($K["fields"]as$k){$n=$Ld[$k["type"]];$tl=($n!==null?$Si[$n]:$k);if($k["field"]!=""){if(!$k["generated"])$k["default"]=null;$Fi=process_field($k,$tl);$ra[]=array($k["orig"],$Fi,$oa);if(!$Dh||$Fi!==process_field($Dh,$Dh)){$l[]=array($k["orig"],$Fi,$oa);if($k["orig"]!=""||$oa)$Ml=true;}if($n!==null)$Jd[idf_escape($k["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(array('table'=>$Ld[$k["type"]],'source'=>array($k["field"]),'target'=>array($tl["field"]),'on_delete'=>$k["on_delete"],),object_name("FOREIGN",trim($K["name"]),array($k["field"])));$oa=" AFTER ".idf_escape($k["field"]);}elseif($k["orig"]!=""){$Ml=true;$l[]=array($k["orig"]);}if($k["orig"]!=""){$Dh=next($Eh);if(!$Dh)$oa="";}}$Wh=array();if(in_array($K["partition_by"],$Uh)){foreach($K
as$w=>$X){if(preg_match('~^partition~',$w))$Wh[$w]=$X;}foreach($Wh["partition_names"]as$w=>$B){if($B==""){unset($Wh["partition_names"][$w]);unset($Wh["partition_values"][$w]);}}$Wh["partition_names"]=array_values($Wh["partition_names"]);$Wh["partition_values"]=array_values($Wh["partition_values"]);if($Wh==$Yh)$Wh=array();}elseif(preg_match("~partitioned~",$S["Create_options"]))$Wh=null;$og='Table has been altered.';if($a==""){cookie("adminer_engine",$K["Engine"]);$og='Table has been created.';}$B=trim($K["name"]);$_=ME.(support("table")?"table=":"select=").url_escape($B);$I=alter_table($a,$B,(JUSH=="sqlite"&&($Ml||$Jd)?$ra:$l),$Jd,($K["Comment"]!=$S["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$S["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$S["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$Wh);if($I&&!Queries::$queries&&$a!=""&&!$l&&!$Jd)redirect($_);queries_redirect($_,$og,$I);}}$kk=($a!=""?"alter":"create");page_header(($a!=""?'Alter table':'Create table'),$j,array("table"=>$a),h($a),$Xg,doc_link(array('sql'=>"$kk-table.html",'mariadb'=>($a!=""?"$kk-table":""),)));if(!$_POST){$wl=driver()->types();$K=array("Engine"=>$_COOKIE["adminer_engine"],"fields"=>array(array("field"=>"","type"=>(isset($wl["int"])?"int":(isset($wl["integer"])?"integer":"")),"on_update"=>"")),"partition_names"=>array(""),);if($a!=""){$K=$S;$K["name"]=$a;$K["fields"]=array();if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($Eh
as$k){if($k["generated"])$k["default"]=ltrim($k["default"]);$k["generated"]=$k["generated"]?:(isset($k["default"])?"DEFAULT":"");$K["fields"][]=$k;}if($Uh){$K+=$Yh;$K["partition_names"][]="";$K["partition_values"][]="";}}}$rb=flat_collations();$Uc=driver()->engines();foreach($Uc
as$Tc){if(!strcasecmp($Tc,$K["Engine"])){$K["Engine"]=$Tc;break;}}$Zf=max_input_vars(12,20);if($Zf){$ue=(count($K["fields"])>$Zf?"":" hidden");echo"<p".($ue?" id='max-fields' data-columns='$Zf'":"")." class='error$ue'>".max_input_vars_error()."\n";}echo'
<form action="" method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($K["name"])."' autocapitalize='off'>\n",(!$ua?h($S["Engine"])."\n":($Uc?html_select("Engine",array(""=>"(".'engine'.")")+$Uc,$K["Engine"],on('change','helpClose').on_help_value())."\n":""));if($rb)echo"<datalist id='collations'>".optionlist($rb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($K["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ua){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$rb,"TABLE",$Ld);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($K["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',6),"jsonly");$xb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$xb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($xb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$K["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($Uh&&(JUSH=='sql'||$a=="")){$Vh=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset("partition",'Partition by',$K["partition_by"]);echo"<p>".html_select("partition_by",array_merge(array(""),$Uh),$K["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($K["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($Vh||!$K["partition_by"]?" hidden":"")."' value='".h($K["partitions"])."'>\n","<table id='partition-table'".($Vh?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($K["partition_names"]as$w=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($w==count($K["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($K["partition_values"],$w)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$Qe=array("PRIMARY","UNIQUE","INDEX");$S=table_status1($a,true);$Oe=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$Qe[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$Qe[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$Qe[]="VECTOR";$v=indexes($a);$l=fields($a);$_i=array();if(JUSH=="mongo"){$_i=$v["_id_"];unset($Qe[0]);unset($v["_id_"]);}$K=$_POST;if($K)save_settings(array("index_options"=>$K["options"]));if($_POST&&!$j&&!$_POST["add"]&&!$_POST["drop_col"]){$ta=array();foreach($K["indexes"]as$u){$B=$u["name"];if(in_array($u["type"],$Qe)){$d=array();$Jf=array();$mc=array();$sh=array();$Pe=(support("partial_indexes")?$u["partial"]:"");$Ne=(in_array($u["algorithm"],$Oe)?$u["algorithm"]:"");$P=array();ksort($u["columns"]);foreach($u["columns"]as$w=>$c){if($c!=""){$x=idx($u["lengths"],$w);$kc=idx($u["descs"],$w);$rh=idx($u["opclasses"],$w);$P[]=($l[$c]?idf_escape($c):$c).($x?"(".(+$x).")":"").($rh!=""?" ".idf_escape($rh):"").($kc?" DESC":"");$d[]=$c;$Jf[]=($x?:null);$mc[]=$kc;$sh[]="$rh";}}$id=$v[$B];if($id){ksort($id["columns"]);ksort($id["lengths"]);ksort($id["descs"]);if($u["type"]==$id["type"]&&array_values($id["columns"])===$d&&(!$id["lengths"]||array_values($id["lengths"])===$Jf)&&array_values($id["descs"])===$mc&&(!$id["opclasses"]||array_values($id["opclasses"])===$sh)&&$id["partial"]==$Pe&&(!$Oe||$id["algorithm"]==$Ne)){unset($v[$B]);continue;}}if($d)$ta[]=array($u["type"],$B,$P,$Ne,$Pe);}}foreach($v
as$B=>$id)$ta[]=array($id["type"],$B,"DROP");if(!$ta)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$ta));}page_header('Indexes',$j,array("table"=>$a),h($a),false,doc_link(array('sql'=>"create-index.html",)));$yd=array_keys($l);if($_POST["add"]){foreach($K["indexes"]as$w=>$u){if($u["columns"][count($u["columns"])]!="")$K["indexes"][$w]["columns"][]="";}$u=end($K["indexes"]);if($u["type"]||array_filter($u["columns"],'strlen'))$K["indexes"][]=array("columns"=>array(1=>""));}if(!$K){foreach($v
as$w=>$u){$v[$w]["name"]=$w;$v[$w]["columns"][]="";}$v[]=array("columns"=>array(1=>""));$K["indexes"]=$v;}$Jf=(JUSH=="sql"||JUSH=="mssql");$sh=driver()->indexOpclasses();$Sj=($_POST?$_POST["options"]:get_setting("index_options"));$Kg=array();foreach($Qe
as$U)$Kg[$U]=str_replace("{table}",$a,adminer()->namePattern($U));echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$Ge=" class='idxopts".($Sj?"":" hidden")."'";if($Oe)echo"<th id='label-algorithm'$Ge>".'Algorithm'.doc_link(array('sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',));echo'<th><input type="submit" hidden>','Columns'.($Jf?"<span$Ge> (".'length'.")</span>":"");if($Jf||support("descidx"))echo
checkbox("options",1,$Sj,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$Ge>".'Condition';echo'<td><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($_i){echo"<tr><td>PRIMARY<td>";foreach($_i["columns"]as$w=>$c)echo
select_input(" disabled",array_combine($yd,$yd),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$pf=1;foreach($K["indexes"]as$u){if(!$_POST["drop_col"]||$pf!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$pf][type]",array(-1=>"")+$Qe,$u["type"],on('change','indexesChangeType',$Kg),"label-type");if($Oe)echo"<td$Ge>".html_select("indexes[$pf][algorithm]",array_merge(array(""),$Oe),$u['algorithm'],"","label-algorithm");echo"<td>";ksort($u["columns"]);$r=1;foreach($u["columns"]as$w=>$c){echo"<span>".select_input(" name='indexes[$pf][columns][$r]' title='".'Column'."'".on('change','indexesChangeColumn',$Kg),($l&&($c==""||$l[$c])?array_combine($yd,$yd):array()),$c)," <span$Ge>",($Jf?"<input type='number' name='indexes[$pf][lengths][$r]' class='size' value='".h(idx($u["lengths"],$w))."' title='".'Length'."'>":"");if($sh){$rh=idx($u["opclasses"],$w);echo
html_select("indexes[$pf][opclasses][$r]",array(""=>"(".'operator class'.")")+array_combine($sh,$sh)+($rh!=""?array($rh=>$rh):array()),$rh),'';}echo(support("descidx")?checkbox("indexes[$pf][descs][$r]",1,idx($u["descs"],$w),'descending'):""),"<br>","</span></span>";$r++;}echo"<td><input name='indexes[$pf][name]' value='".h($u["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$Ge><input name='indexes[$pf][partial]' value='".h($u["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$pf]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]'));}$pf++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
',input_token(),'</form>
';}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!$j&&!$_POST["add"]){$B=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases(array(DB)));}elseif($B!==DB){if(DB!=""){$_GET["db"]=$B;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($B),'Database has been renamed.',rename_database($B,(string)$K["collation"]));}else{$g=explode("\n",str_replace("\r","",$B));$sk=true;$Af="";foreach($g
as$h){if(count($g)==1||$h!=""){if(!create_database($h,(string)$K["collation"]))$sk=false;$Af=$h;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($Af),'Database has been created.',$sk);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($B).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),'Database has been altered.');}}$kk=(DB!=""?"alter":"create");page_header(DB!=""?'Alter database':'Create database',$j,array(),h(DB),false,doc_link(array('sql'=>"$kk-database.html",'mariadb'=>(DB!=""?"":"$kk-database"),)));$rb=collations();$B=DB;if($_POST)$B=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,$rb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$Yd){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$Yd,$A)&&$A[1]){$B=stripcslashes(idf_unescape("`$A[2]`"));break;}}}echo'
<form action="" method="post">
<p>
',($_POST["add"]||strpos($B,"\n")?'<textarea autofocus name="name" rows="10" cols="40">'.h($B).'</textarea><br>':'<input name="name" autofocus value="'.h($B).'" data-maxlength="64" autocapitalize="off">')."\n",($rb?html_select("collation",array(""=>"(".'collation'.")")+$rb,$K["collation"]).doc_link(array('sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",)):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);$pj=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$kj=routine($_GET["call"],$pj);$mf=(idx($kj["returns"],"type")=="trigger");page_header(($mf?'Triggers':'Call').": ".h($ba),$j,"#routines","",!$kj,(isset($_GET["callf"])?"":doc_link(array('sql'=>"call.html",))));$Je=array();$Jh=array();foreach($kj["fields"]as$r=>$k){if(substr($k["inout"],-3)=="OUT"&&JUSH=='sql')$Jh[$r]="@".idf_escape($k["field"])." AS ".idf_escape($k["field"]);if(!$k["inout"]||preg_match('~^(IN|OUTPUT)~',$k["inout"]))$Je[]=$r;}if(!$j&&$_POST){$Xa=array();foreach($kj["fields"]as$w=>$k){$X="";if(in_array($w,$Je)){$X=process_input($k);if($X===false)$X="''";if(isset($Jh[$w]))connection()->query("SET @".idf_escape($k["field"])." = $X");}if(isset($Jh[$w]))$Xa[]="@".idf_escape($k["field"]);elseif(in_array($w,$Je))$Xa[]=$X;}$ya=implode(", ",$Xa);$H=(isset($_GET["callf"])||JUSH!="mssql"?(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($kj["returns"],"type")=="record"?"* FROM ":"").table($ba)."($ya)":"EXEC ".table($ba).($ya!=""?" $ya":""));$jk=microtime(true);$I=connection()->multi_query($H);$ma=connection()->affected_rows;echo
adminer()->selectQuery($H,$jk,!$I);if(!$I)echo"<p class='error'>".adminer()->error()."\n";else{$f=connect();if($f)$f->select_db(DB);do{$I=connection()->store_result();if(is_object($I))print_select_result($I,$f);else
echo"<p class='message'>".lang_format(array('Routine has been called, %d row affected.','Routine has been called, %d rows affected.'),$ma)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($Jh)print_select_result(connection()->query("SELECT ".implode(", ",$Jh)));}}if($mf){$ql=driver()->routineTriggers($_GET["call"]);if($ql){echo"<table>\n";foreach($ql
as$K){$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($K["ns"]),ME);echo"<tr><th>".h($K["trigger"])."<td><a href='".h($z."table=".url_escape($K["table"])."#triggers")."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>"."<td class='hover'><a href='".h($z."trigger=".url_escape($K["table"])."&name=".url_escape($K["trigger"]))."'>".'Alter'."</a>\n";}echo"</table>\n";}}else{echo'<form action="" method="post">
';if($Je){echo"<table class='layout'>\n";foreach($Je
as$w){$k=$kj["fields"][$w];$B=$k["field"];echo"<tr><th>".adminer()->fieldName($k);$Y=idx($_POST["fields"],$B);if($Y!=""){if($k["type"]=="set")$Y=implode(",",$Y);}input($k,$Y,idx($_POST["function"],$B,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>
';}echo'
',adminer()->commentValue($pj,$kj['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$B=$_GET["name"];$K=$_POST;if($_POST&&!$j&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$Ok=array();foreach($K["source"]as$w=>$X)$Ok[$w]=$K["target"][$w];$K["target"]=$Ok;}$Ab=object_name("FOREIGN",$a,$K["source"]);$Oa=false;if(JUSH=="sqlite")$I=recreate_table($a,$a,array(),array(),array(" $B"=>($K["drop"]?"":" ".format_foreign_key($K,$Ab))));else{$ta="ALTER TABLE ".table($a);$Dc="$ta DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($B?:'');if($K["drop"])$I=queries($Dc);else{$ha=" ADD".format_foreign_key($K,$Ab);if($B=="")$I=queries($ta.$ha);elseif(JUSH=="pgsql"||(JUSH=="sql"&&$Ab!=$B))$I=queries("$Dc,$ha");else{$Oa=support("transaction_ddl")&&driver()->begin();$I=($Oa||idx(foreign_keys($a),$B)?queries($Dc):true)&&queries($ta.$ha)&&(!$Oa||driver()->commit());}}}queries_redirect(ME."table=".url_escape($a),($K["drop"]?'Foreign key has been dropped.':($B!=""?'Foreign key has been altered.':'Foreign key has been created.')),$I);if($Oa)driver()->rollback();if(!$K["drop"])$j='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}$Xg=false;if(!$_POST&&$B!=""){$Ld=foreign_keys($a);$K=idx($Ld,$B,array());$Xg=!$K;}page_header(($B!=""?'Alter foreign key':'Create foreign key'),$j,array("table"=>$a),h($B!=""?$B:$a),$Xg,doc_link(array('sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",)));if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=array();else$K["source"][]="";}elseif($B!="")$K["source"][]="";else{$K["table"]=$a;$K["source"]=array("");}echo'
<form action="" method="post">
';$ak=array_keys(fields($a));if($K["db"]!="")connection()->select_db($K["db"]);if($K["ns"]!=""){$Fh=get_schema();set_schema($K["ns"]);}$Ri=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$Ok=array_keys(fields(in_array($K["table"],$Ri)?$K["table"]:reset($Ri)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$Ri,$K["table"],$b)."</label>\n";if(JUSH!="sqlite"){$bc=array();foreach(adminer()->databases()as$h){if(!information_schema($h))$bc[]=$h;}echo"<label>".'DB'.": ".html_select("db",$bc,$K["db"]!=""?$K["db"]:$_GET["db"],$b)."</label>";}echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$pf=0;foreach($K["source"]as$w=>$X){echo"<tr>","<td>".html_select("source[".(+$w)."]",array(-1=>"")+$ak,$X,($pf==count($K["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$w)."]",$Ok,idx($K["target"],$w),"","label-target");$pf++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",array(-1=>"")+explode("|",driver()->onActions),$K["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",array(-1=>"")+explode("|",driver()->onActions),$K["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",array('NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'),$K["deferrable"]):''),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$Gh="VIEW";if(JUSH=="pgsql"&&$a!=""){$mk=table_status1($a);$Gh=strtoupper($mk["Engine"]);}if($_POST&&!$j){$B=trim($K["name"]);$_a=" AS\n$K[select]";$_=ME."table=".url_escape($B);$og='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$B&&JUSH!="sqlite"&&$U=="VIEW"&&$Gh=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($B).$_a,$_,$og);else{$Sk="adminer_".uniqid();drop_create("DROP $Gh ".table($a),"CREATE $U ".table($B).$_a,"DROP $U ".table($B),"CREATE $U ".table($Sk).$_a,"DROP $U ".table($Sk),($_POST["drop"]?substr(ME,0,-1):$_),'View has been dropped.',$og,'View has been created.',$a,$B);}}$Xg=false;if(!$_POST&&$a!=""){$K=view($a);$Xg=!$K["select"];$K["name"]=$a;$K["materialized"]=($Gh!="VIEW");if(!$j)$j=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$j,array("table"=>$a),h($a),$Xg,doc_link(array('sql'=>"create-view.html",)));echo'
<form action="" method="post">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$K["materialized"],'Materialized view'):""),'<p>';textarea("select",$K["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$bf=array("YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND");$nk=array("ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE");$K=$_POST;if($_POST&&!$j){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($K["INTERVAL_FIELD"],$bf)&&isset($nk[$K["STATUS"]])){$tj="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$tj.($aa!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$tj)."\n".$nk[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}$Xg=false;if(!$K&&$aa!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$Xg=!$L;$K=reset($L);}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$j,"#events","",$Xg,doc_link(array('sql'=>"create-event.html")));echo'
<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($K["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$K[EXECUTE_AT]$K[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($K["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($K["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$bf,$K["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$nk,$K["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($K["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$kj=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])&&!$j){foreach($K["fields"]as$w=>$k){if($k["field"]=="")unset($K["fields"][$w]);}$mh=routine($_GET["procedure"],$kj);$kh=($mh?routine_id($ba,$mh):"");$Qg=routine_id($K["name"],$K);$Kb=create_routine($kj,$K);$_=substr(ME,0,-1);$og='Routine has been altered.';if(!$_POST["drop"]&&$kh==$Qg&&connection()->flavor!="mysql")queries_redirect($_,$og,queries(substr_replace($Kb,(JUSH=="mssql"?' OR ALTER':' OR REPLACE'),6,0)));else{$Sk="adminer_".uniqid();drop_create("DROP $kj $kh",$Kb,"DROP $kj $Qg",create_routine($kj,array("name"=>$Sk)+$K),"DROP $kj ".routine_id($Sk,$K),$_,'Routine has been dropped.',$og,'Routine has been created.',$ba,$K["name"]);}}$Xg=false;if(!$_POST&&$ba!=""){$K=routine($_GET["procedure"],$kj);$Xg=!$K;$K["name"]=$ba;}$nj=strtolower($kj);page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$j,"#routines","",$Xg,doc_link(array('sql'=>"create-procedure.html",'mariadb'=>"create-$nj/",)));if(!$_POST&&$ba=="")$K["language"]="sql";$rb=(JUSH=="sql"?flat_collations():array());$lj=routine_languages();echo($rb?"<datalist id='collations'>".optionlist($rb)."</datalist>":""),'
<form action="" method="post" id="form">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',($lj?"<label>".'Language'.": ".html_select("language",array_keys($lj),$K["language"],on('change','routineLanguage',$lj))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($K["fields"],$rb,$kj);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$K["returns"],$rb,array(),(JUSH=="pgsql"?array("void","trigger"):array()));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$K["definition"],20,80,($lj[$K["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$oj=routine_options($kj);if($oj){$xh=false;foreach($oj
as$w=>$Xl){$i=($Xl?reset($Xl):"");$K["options"][$w]=idx($K["options"],$w,$i);if($K["options"][$w]!=$i)$xh=true;}print_fieldset("options",'Options',$xh);echo"<table class='layout'>\n";foreach($oj
as$w=>$Xl){$xf="label-option-$w";$al=str_replace("_"," ",$w);$N=array();foreach($Xl
as$Y)$N[$Y]=(strpos($Y,"$al ")===0?substr($Y,strlen($al)+1):$Y);echo"<tr><th id='$xf'>$al<td>".($N?html_select("options[$w]",$N,$K["options"][$w],"",$xf):"<input name='options[$w]' value='".h($K["options"][$w])."' aria-labelledby='$xf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$B="$_GET[name]";$K=$_POST;if($K&&!$j){$_=ME."table=".url_escape($a);$rg='Check has been dropped.';$pg='Check has been altered.';$qg='Check has been created.';if(JUSH=="sqlite")queries_redirect($_,($K["drop"]?$rg:($B!=""?$pg:$qg)),recreate_table($a,$a,array(),array(),array(),"",array(),"$B",($K["drop"]?"":$K["clause"])));else{$ta="ALTER TABLE ".table($a);$eb=" CHECK ($K[clause])";$Sk="adminer_".uniqid();drop_create("$ta DROP CONSTRAINT ".idf_escape($B),"$ta ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"").$eb,"$ta DROP CONSTRAINT ".idf_escape($K["name"]),"$ta ADD CONSTRAINT ".idf_escape($Sk).$eb,"$ta DROP CONSTRAINT ".idf_escape($Sk),$_,$rg,$pg,$qg,$B,$K["name"]);}}$Xg=false;if(!$K){$hb=driver()->checkConstraints($a);$Xg=($B!=""&&!$hb[$B]);$K=array("name"=>($B!=""?$B:object_name("CHECK",$a,array())),"clause"=>$hb[$B]);}page_header(($B!=""?'Alter check':'Create check'),$j,array("table"=>$a),h($B!=""?$B:$a),$Xg,doc_link(array('sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",)));echo'
<form action="" method="post">
';if(JUSH!="sqlite")echo'<p>'.'Name'.': <input name="name" value="'.h($K["name"]).'" data-maxlength="64" autocapitalize="off">';echo'<p>';textarea("clause",$K["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$B="$_GET[name]";$pl=trigger_options();$K=trigger($B,$a);$Xg=($B!=""&&!$K);$Jg=str_replace("{table}",$a,adminer()->namePattern("TRIGGER"));$K+=array("Trigger"=>strtr($Jg,array("{timing}"=>"b","{event}"=>"i","{columns}"=>"","{type}"=>"row")));if($_POST){if(!$j&&in_array($_POST["Timing"],$pl["Timing"])&&in_array($_POST["Event"],$pl["Event"])&&in_array($_POST["Type"],$pl["Type"])){$oh=" ON ".table($a);$Dc="DROP TRIGGER ".idf_escape($B).(JUSH=="pgsql"?$oh:"");$_=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($Dc,$_,'Trigger has been dropped.');else{if($B!="")queries($Dc);queries_redirect($_,($B!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($oh,$_POST)));if($B!="")queries(create_trigger($oh,$K+array("Type"=>reset($pl["Type"]))));}}$K=$_POST;}page_header(($B!=""?'Alter trigger':'Create trigger'),$j,array("table"=>$a),h($B!=""?$B:$a),$Xg,doc_link(array('sql'=>"create-trigger.html",)));$Mg=strtr(preg_quote($Jg),array('\{timing\}'=>'[abi]','\{event\}'=>'[iud]*','\{columns\}'=>'.*','\{type\}'=>'(row|statement)'));$ol=on('change','triggerChange',"^$Mg$",$Jg);$dh=on('input','triggerChange',"^$Mg$",$Jg);echo'
<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$pl["Timing"],$K["Timing"],$ol),'<tr><th>Event<td>',html_select("Event",$pl["Event"],$K["Event"],$ol),(in_array("UPDATE OF",$pl["Event"])?" <input name='Of' value='".h($K["Of"])."' class='hidden'$dh>":""),'<tr><th>Type<td>',html_select("Type",$pl["Type"],$K["Type"],$ol),'<tr><th>Name<td><input name="Trigger" value="',h($K["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$K["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($Yd,array$Di,$d,$oh){if(!$Di)return
true;if($Di==array("ALL PRIVILEGES","GRANT OPTION"))return($Yd=="GRANT"?queries("$Yd ALL PRIVILEGES$oh WITH GRANT OPTION"):queries("$Yd ALL PRIVILEGES$oh")&&queries("$Yd GRANT OPTION$oh"));return
queries("$Yd ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$Di).$d).$oh);}$da=$_GET["user"];$Di=array(""=>array("All privileges"=>""));foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$Eb)$Di[$Eb=="File access on server"?"Server Admin":$Eb][$K["Privilege"]]=$K["Comment"];}unset($Di["Server Admin"]["Usage"]);foreach($Di["Tables"]as$w=>$X)unset($Di["Databases"][$w]);$Pg=array();if($_POST){foreach($_POST["objects"]as$w=>$X)$Pg[$X]=(array)$Pg[$X]+idx($_POST["grants"],$w,array());}$Zd=array();$I=(isset($_GET["host"])?connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])):null);$Xg=(isset($_GET["host"])&&!$I);if($I){while($K=$I->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$A)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$A[1],$Wf,PREG_SET_ORDER)){foreach($Wf
as$X){if($X[1]!="USAGE")$Zd["$A[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$Zd["$A[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$j){$nh=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $nh",ME."privileges=",'User has been dropped.');else{$Tg=q($_POST["user"])."@".q($_POST["host"]);$ai=$_POST["pass"];$Mb=false;$I=true;if($nh!=$Tg){$Mb=queries("CREATE USER $Tg IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($ai));$I=$Mb;}elseif($ai!="")$I=queries("SET PASSWORD FOR $Tg = ".(min_version(8,99)||$_POST["hashed"]?q($ai):"PASSWORD(".q($ai).")"));if($I){$gj=array();foreach($Pg
as$bh=>$Yd){if(isset($_GET["grant"]))$Yd=array_filter($Yd);$Yd=array_keys($Yd);if(isset($_GET["grant"]))$gj=array_diff(array_keys(array_filter($Pg[$bh],'strlen')),$Yd);elseif($nh==$Tg){$jh=array_keys((array)$Zd[$bh]);$gj=array_diff($jh,$Yd);$Yd=array_diff($Yd,$jh);unset($Zd[$bh]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$bh,$A)&&(!grant("REVOKE",$gj,$A[2]," ON $A[1] FROM $Tg")||!grant("GRANT",$Yd,$A[2]," ON $A[1] TO $Tg"))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($nh!=$Tg)queries("DROP USER $nh");elseif(!isset($_GET["grant"])){foreach($Zd
as$bh=>$gj){if(preg_match('~^(.+)(\(.*\))?$~U',$bh,$A))grant("REVOKE",array_keys($gj),$A[2]," ON $A[1] FROM $Tg");}}}if($I&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$I);if($Mb)connection()->query("DROP USER $Tg");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$j,array("privileges"=>array('','Privileges')),"",$Xg,doc_link(array('sql'=>"grant.html",'mariadb'=>"grant")));$K=$_POST;if($K)$Zd=$Pg;else{$K=$_GET+array("host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"));$Zd[(DB==""||$Zd?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=array();}echo'<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($K["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($K["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($K["pass"]),'" autocomplete="new-password">
',($K["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$K["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges';$r=0;foreach($Zd
as$bh=>$Yd){echo'<th>'.($bh!="*.*"?"<input name='objects[$r]' value='".h($bh)."' size='10' autocapitalize='off'>":input_hidden("objects[$r]","*.*")."*.*");$r++;}echo"<tbody>\n";foreach(array(""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',)as$Eb=>$kc){foreach((array)$Di[$Eb]as$Ci=>$vb){echo"<tr><td".($kc?">$kc<td":" colspan='2'").' lang="en" title="'.h($vb).'">'.h($Ci);$r=0;foreach($Zd
as$bh=>$Yd){$B="'grants[$r][".h(strtoupper($Ci))."]'";$Y=$Yd[strtoupper($Ci)];if($Eb=="Server Admin"&&$bh!=(isset($Zd["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$B><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$B value='1'".($Y?" checked":"").($Ci=="All privileges"?" id='grants-$r-all'":($Ci=="Grant option"?"":on('click','grantsClick',"grants-$r-all"))).">","</label>";$r++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$j){$wf=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$wf++;}queries_redirect(ME."processlist=",lang_format(array('%d process has been killed.','%d processes have been killed.'),$wf),$wf||!$_POST["kill"]);}}page_header('Process list',$j);echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds"',on('click','tableClick').on('dblclick','tableClick'),'>
';$r=-1;foreach(adminer()->processList()as$r=>$K){if(!$r){echo"<thead><tr lang='en'>".(support("kill")?"<td class='hover'>":"");foreach($K
as$w=>$X)echo"<th>$w".doc_link(array('sql'=>"show-processlist.html#processlist_".strtolower($w),));echo"<tbody>\n";}echo"<tr>".(support("kill")?"<td class='hover'>".checkbox("kill[]",$K[JUSH=="sql"?"Id":"pid"],0):"");foreach($K
as$w=>$X)echo"<td>".($X!=""&&((JUSH=="sql"&&$w=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(JUSH=="pgsql"&&$w=="query")||(JUSH=="oracle"&&$w=="sql_text"))?"<code class='jush-".JUSH."' data-full='".h($X)."'>".shorten_utf8($X,100,"</code>").' <a href="'.h(($K["db"]!=""?preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($K["db"])."&":ME)."sql=".url_escape($X)).'">'.'Clone'.'</a>'.' '.copy_icon():h($X));echo"\n";}echo'</table>
</div>
<p>
',script("copyCode(qsl('table'));");if(support("kill"))echo
format_number($r+1)."/".sprintf('%d in total',max_connections()),"<p><input type='submit' value='".'Kill'."'>\n";echo
input_token(),'</form>
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$v=indexes($a);$l=fields($a);$Ld=column_foreign_keys($a);$ih=$S["Oid"];$ij=array();$d=array();$zj=array();$zh=array();$Vk=null;foreach($l
as$w=>$k){$B=adminer()->fieldName($k);$Lg=html_entity_decode(strip_tags($B),ENT_QUOTES);if(isset($k["privileges"]["select"])&&$B!=""){$d[$w]=$Lg;if(is_shortable($k))$Vk=adminer()->selectLengthProcess();}if(isset($k["privileges"]["where"])&&$B!="")$zj[$w]=$Lg;if(isset($k["privileges"]["order"])&&$B!="")$zh[$w]=$Lg;$ij+=$k["privileges"];}list($N,$q)=adminer()->selectColumnsProcess($d,$v);$N=array_unique($N);$q=array_unique($q);$if=count($q)<count($N);$Z=adminer()->selectSearchProcess($l,$v,$S);$D=adminer()->selectOrderProcess($l,$v);$y=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Al=>$K){$_a=convert_field($l[key($K)]);$N=array($_a?:idf_escape(key($K)));$Z[]=where_check(bracket_escape($Al,true),$l);$J=driver()->select($a,$N,$Z,$N);if($J)echo
first($J->fetch_row());}exit;}$_i=$Cl=array();foreach($v
as$u){if($u["type"]=="PRIMARY"){$_i=array_flip($u["columns"]);$Cl=($N?$_i:array());foreach($Cl
as$w=>$X){if(in_array(idf_escape($w),$N))unset($Cl[$w]);}break;}}if($ih&&!$_i){$_i=$Cl=array($ih=>0);$v[]=array("type"=>"PRIMARY","columns"=>array($ih));}if($_POST&&!$j){$im=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$hb=array();foreach($_POST["check"]as$eb)$hb[]=where_check($eb,$l);$im[]="((".implode(") OR (",$hb)."))";}$km=$im;$im=($im?"\nWHERE ".implode(" AND ",$im):"");if($_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$Bj=($N?:array("*"));$Gb=convert_fields($d,$l,$N);if($Gb)$Bj[]=substr($Gb,2);$H="";if(is_array($_POST["check"])&&!$_i){$Qd=implode(", ",$Bj)."\nFROM ".table($a);$ce=($q&&$if?"\nGROUP BY ".implode(", ",$q):"").($D?"\nORDER BY ".implode(", ",$D):"");$yl=array();foreach($_POST["check"]as$X)$yl[]="(SELECT".limit($Qd,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l).$ce,1).")";$H=implode(" UNION ALL ",$yl);}adminer()->dumpData($a,"table",$H,$Bj,$km,($if?$q:array()),$D);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$Ld)){if($_POST["save"]||$_POST["delete"]){$I=true;$ma=0;$Oa=false;$P=array();if(!$_POST["delete"]){foreach($l
as$B=>$X){$t=bracket_escape($B);if(isset($_POST["fields"][$t])||$_FILES["fields-$t"]){$X=process_input($l[$B]);if($X!==null&&($_POST["clone"]||$X!==false))$P[idf_escape($B)]=($X!==false?$X:idf_escape($B));}}}if($_POST["delete"]||$P){$H=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($P)).")\nSELECT ".implode(", ",$P)."\nFROM ".table($a):"");if($_POST["all"]||($_i&&is_array($_POST["check"]))||$if){$I=($_POST["delete"]?driver()->delete($a,$im):($_POST["clone"]?queries("INSERT $H$im".driver()->insertReturning($a)):driver()->update($a,$P,$im)));$ma=connection()->affected_rows;if(is_object($I))$ma+=$I->num_rows;}else{$Oa=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$hm="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$l);$I=($_POST["delete"]?driver()->delete($a,$hm,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$hm)):driver()->update($a,$P,$hm,1)));if(!$I)break;$ma+=connection()->affected_rows;}if($Oa&&$I&&!driver()->commit())$I=false;}}$og=lang_format(array('%d item has been affected.','%d items have been affected.'),$ma);if($_POST["clone"]&&$I&&$ma==1){$Cf=last_id($I);if($Cf)$og=sprintf('Item%s has been inserted.'," $Cf");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$og,$I);if($Oa)driver()->rollback();if(!$_POST["delete"]){$ri=(array)$_POST["fields"];edit_form($a,array_intersect_key($l,$ri),$ri,!$_POST["clone"],$j);page_footer();exit;}}elseif(!$_POST["import"]){$I=true;$ma=0;$Oa=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$Al=>$K){$P=array();foreach($K
as$w=>$X){$w=bracket_escape($w,true);$P[idf_escape($w)]=(preg_match('~char|text~',$l[$w]["type"])||$X!=""?adminer()->processInput($l[$w],$X):"NULL");}$I=driver()->update($a,$P," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($Al,true),$l),($if||$_i?0:1)," ");if(!$I)break;$ma+=connection()->affected_rows;}if($Oa)$I=$I&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(array('%d item has been affected.','%d items have been affected.'),$ma),$I);if($Oa)driver()->rollback();}else{save_settings(array("format"=>$_POST["separator"]),"adminer_import");$zd=get_file("csv_file",true);if(!is_string($zd))$j=upload_error($zd);elseif(!preg_match('~~u',$zd))$j='File must be in UTF-8 encoding.';else{$sb=array_keys($l);$Gj=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Qb=parse_csv($zd,$Gj);$ma=count($Qb);driver()->begin();$L=array();foreach($Qb
as$w=>$Xl){if(!$w&&!array_diff($Xl,$sb)){$sb=$Xl;$ma--;}else{$P=array();foreach($Xl
as$r=>$ob)$P[idf_escape($sb[$r])]=($ob==""&&$l[$sb[$r]]["null"]?"NULL":q(csv_value($ob)));$L[]=$P;}}$I=(!$L||driver()->insertUpdate($a,$L,$_i));if($I)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(array('%d row has been imported.','%d rows have been imported.'),$ma),$I);driver()->rollback();}}}}$Dk=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $Dk",$j,array(),"",(!$l&&support("table")),($l?doc_link(array(JUSH=>driver()->tableHelp($a,is_view($S)))):""));$P=null;if(isset($ij["insert"])||!support("table")){$P="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$A)?$A[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$P
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$P);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table.'."\n";else{echo"<form action='' id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($N,$d);adminer()->selectSearchPrint($Z,$zj,$v,$S);adminer()->selectOrderPrint($D,$zh,$v);adminer()->selectLimitPrint($y);if($Vk!==null)adminer()->selectLengthPrint($Vk);adminer()->selectActionPrint($v);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"))){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$E=$_GET["page"];$Od=null;if($E=="last"){$Od=get_val(count_rows($a,$Z,$if,$q));$E=floor(max(0,intval($Od)-1)/$y);}$Aj=$N;$be=$q;if(!$Aj){$Aj[]="*";$Gb=convert_fields($d,$l,$N);if($Gb)$Aj[]=substr($Gb,2);}foreach($N
as$w=>$X){$k=$l[idf_unescape($X)];if($k&&($_a=convert_field($k)))$Aj[$w]="$_a AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$w=>$X){if(isset($Aj[$w])&&$X["fun"])$Aj[$w].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$if&&$Cl){foreach($Cl
as$w=>$X){$Aj[]=idf_escape($w);if($be)$be[]=idf_escape($w);}}$I=driver()->select($a,$Aj,$Z,$be,$D,$y,$E,true);if(!is_object($I))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$E)$I->seek($y*$E);$Qc=array();$L=array();while($K=$I->fetch_assoc()){if($E&&JUSH=="oracle")unset($K["RNUM"]);$L[]=$K;}$me=($y&&(support("cursor")?$_GET["next"]!="":count($L)>=$y));if(is_ajax()&&$me)header("X-Next-Page: ".pagination_href($E+1));if($_GET["modify"]&&$L){$fg=max_input_vars(count($L[0])+1,20);echo($fg&&count($L)>$fg?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form action='' method='post' enctype='multipart/form-data'".on_upload_progress($Il).">\n";if($_GET["page"]!="last"&&$y&&$q&&$if&&JUSH=="sql")$Od=get_val(" SELECT FOUND_ROWS()");if(!$L)echo"<p class='message'>".'No rows.'."\n";else{$Ka=adminer()->backwardKeys($a,$Dk);$fj=array();reset($N);foreach($L[0]as$w=>$X){if(!isset($Cl[$w])){$X=idx($_GET["columns"],key($N))?:array();$fj[$w]=array("fun"=>$X["fun"],"col"=>($N?$X["col"]:$w));next($N);}}echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$q&&$N?"":"<td class='hover check sticky'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$Ng=array();$Oi=1;foreach($fj
as$w=>$X){$k=$l[$X["col"]];$B=($k?adminer()->fieldName($k,$Oi):($X["fun"]?($X["col"]!=""?h($X["col"]):"*"):h($w)));if($B!=""){$Oi++;$Ng[$w]=$B;$c=idf_escape($w);$Ae=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($w);$kc="&desc[0]=1";$Xj=preg_replace('~ DESC( NULLS LAST)?$~','',$D[0]);$Zj=($Xj==$c||$Xj==$w);echo"<th id='th[".h(bracket_escape($w))."]'".($Zj?" aria-sort='".($Xj==$D[0]?"ascending":"descending")."'":"").">";$Ud=apply_sql_function(h($X["fun"]),$B);$Yj=isset($k["privileges"]["order"])||$X["fun"];echo($Yj?"<a href='".h($Ae.($Zj&&$Xj==$D[0]?$kc:''))."'>$Ud</a>":$Ud);$ng=($Yj?"<a href='".h($Ae.$kc)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($k["privileges"]["where"]))$ng
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$w)."> =</a>";echo($ng?"<span class='column'>$ng</span>":"");}}$Jf=array();if($_GET["modify"]){foreach($L
as$K){foreach($K
as$w=>$X)$Jf[$w]=max($Jf[$w],min(40,utf8_length((string)$X)));}}$we=array();$ve=array();foreach((array)$_GET["where"]as$X){$X+=array("col"=>"","op"=>"","val"=>"");$ob=$X["col"];$yj=$X["val"];if(!is_array($yj)&&($yj!=""||preg_match('~NULL$~',$X["op"]))&&(!$X["op"]||in_array($X["op"],adminer()->operators($S)))){$Lf=strtr(preg_quote($yj),array("%"=>".*?","_"=>"."));$gi=array("LIKE %%"=>$Lf,"ILIKE %%"=>$Lf,"REGEXP"=>$yj)+(JUSH=="pgsql"?array("~"=>$yj,"~*"=>$yj):array())+($ob!=""?array():array("="=>'^'.preg_quote($yj).'\z',"IN"=>'^(?:'.implode("|",array_map('preg_quote',array_map('trim',explode(",",$yj)))).')\z',"LIKE"=>"^$Lf\\z","ILIKE"=>"^$Lf\\z","FIND_IN_SET"=>'(?<=^|,)'.preg_quote($yj).'(?=,|\z)',));foreach(($ob!=""?array($ob=>$l[$ob]):$l)as$B=>$k){if($ob!=""||is_searchable($k,$X)){$qh=$X["op"]?:(!preg_match('~'.text_type().'~',$k["type"])?"IN":(preg_match('~%~',$yj)?"LIKE":"LIKE %%"));if(isset($gi[$qh])){$kb=preg_match('~^ILIKE|\*$~',$qh)||($qh!="~"&&preg_match('~^(sql|mssql|sqlite)$~',JUSH));$we[$B][]="(?".($kb?"i":"").":$gi[$qh])";}elseif($qh=="IS NULL"&&$ob=="")$ve[$B]=true;}}}}echo($Ka?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($L,$Ld)as$Hg=>$K){$_l=unique_array($L[$Hg],$v);if(!$_l){$_l=array();foreach($L[$Hg]as$w=>$X){if(!in_array(idx(idx($fj,$w,array()),"fun"),driver()->grouping))$_l[$w]=$X;}}$Al="";$r=0;foreach($_l
as$w=>$X){$ej=idx($fj,$w,array());if($N&&$ej&&!isset($l[$ej["col"]]))continue;$Ud=idx($ej,"fun","");$ob=($Ud?$ej["col"]:$w);$k=(array)$l[$ob];$hf=is_blob($k);if(!$Ud&&strlen($X)>64&&driver()->md5(idf_escape($ob),$k)){$Ud="md5";$X=md5($hf?(string)driver()->value($X,$k):$X);}if($Ud){$Al
.="&fun[$r]=".url_escape($Ud)."&col[$r]=".url_escape($ob).($X!==null?"&val[$r]=".url_escape($X===false?"f":$X):"");$r++;}else$Al
.="&".($X!==null?"where[".url_escape(bracket_escape($ob))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($ob));}echo"<tr>".(!$q&&$N?"":"<td class='hover check sticky'>".($if||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$Al)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($Al,1),in_array(substr($Al,1),(array)$_POST["check"])));foreach($K
as$w=>$X){if(isset($Ng[$w])){$Ud=$fj[$w]["fun"];$ob=$fj[$w]["col"];$k=(array)$l[$w];if($X!=""&&(!isset($Qc[$w])||$Qc[$w]!=""))$Qc[$w]=(is_mail($X)?$Ng[$w]:"");$z="";if(is_blob($k)&&$X!="")$z=ME.'download='.url_escape($a).'&field='.url_escape($w).$Al;if(!$z&&$X!==null){foreach((array)$Ld[$w]as$n){if(count($Ld[$w])==1||end($n["source"])==$w){$z="";foreach($n["source"]as$r=>$ak)$z
.=where_link($r,$n["target"][$r],$L[$Hg][$ak]);$z=($n["db"]!=""?preg_replace('~&db=[^&]*~',"&db=".url_escape($n["db"]),ME):ME).'select='.url_escape($n["table"]).$z;if($n["ns"]!="")$z=preg_replace('~&ns=[^&]*~',"&ns=".url_escape($n["ns"]),$z);if(count($n["source"])==1)break;}}}if($Ud=="count"&&$ob==""){$z=ME."select=".url_escape($a);$r=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$_l))$z
.=where_link($r++,$W["col"],$W["val"],$W["op"]);}foreach($_l
as$sf=>$W){if(idx(idx($fj,$sf,array()),"fun")){$z="";break;}$z
.=where_link($r++,$sf,$W);}}$Be=select_value($X,$z,$k,$Vk,($Ud?array():idx($we,$w,array())));if($X===null&&!$Ud&&isset($ve[$w]))$Be="<mark>$Be</mark>";$t=bracket_escape($Al);$s=h("val[$t][".bracket_escape($w)."]");$ti=idx(idx($_POST["val"],$t),bracket_escape($w));$Fl=idx($k["privileges"],"update")&&!is_identity_always($k);$Lc=$L[$Hg][$w];$Mc=!is_array($K[$w])&&!is_blob($k)&&is_utf8($Lc)&&!$Ud&&!$k["generated"]&&$Fl;$U=($Ud=="min"||$Ud=="max"?$l[$ob]["type"]:$k["type"]);$Uk=preg_match('~text|json|lob~',$U);$jf=preg_match(number_type(),$U)||preg_match('~^(avg|ceil|char_length|count|count distinct|floor|len|length|round|sum|time_to_sec)$~',$Ud);echo"<td id='$s'".($jf&&($X===null||is_numeric(strip_tags($Be))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Mc&&$Lc!==null)||$ti!==null){$he=h($ti!==null?$ti:$Lc);echo">".($Uk?"<textarea name='$s' cols='30' rows='".(substr_count($Lc,"\n")+1)."'>$he</textarea>":"<input name='$s' value='$he' size='$Jf[$w]'>");}else{$Tf=strpos($Be,"<i>…</i>");$Kc=adminer()->editVal($Lc,$k);echo($Fl?" data-text='".($Tf?2:($Uk?1:0))."'".($Mc?"":" data-warning='".'Use the edit link to modify this value.'."'").($Mc&&!$Tf&&html_entity_decode(strip_tags($Be),ENT_QUOTES,"UTF-8")!==$Kc?" data-value='".h($Kc)."'":""):"").">$Be";}}}if($Ka)echo"<td>";adminer()->backwardKeysPrint($Ka,$L[$Hg]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$la=get_settings("adminer_import");if($L||$E||$me){$gd=true;if($_GET["page"]!="last"){if(!$y||(count($L)<$y&&($L||!$E)))$Od=($E?$E*$y:0)+count($L);elseif(JUSH!="sql"||!$if){$Od=($if?null:found_rows($S,$Z));$gd=!driver()->hasEstimatedRows();if($Od===null||(!$gd&&$Od<max(1e4,2*($E+1)*$y))){$Od=first(slow_query(count_rows($a,$Z,$if,$q)));$gd=true;}}}if(!support("cursor"))$me=(($Od===false?count($L)+1:$Od-$E*$y)>$y);$Mh=($y&&($me||$E));if($Mh)echo($me?'<p><a href="'.h(pagination_href($E+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($Mh){$dg=($Od===false?$E+($L?(count($L)>=$y?2:1):0):floor(($Od-1)/$y));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$E).($E>5?" …":"");for($r=max(1,$E-4);$r<min($dg,$E+5);$r++)echo
pagination($r,$E);if($dg>0)echo($E+5<$dg?" …":""),($gd&&$Od!==false?pagination($dg,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$dg'>".'last'."</a>");}else
echo
pagination(0,$E).($E>1?" …":""),($E?pagination($E,$E):""),($me?pagination($E+1,$E)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$sc=($gd?"":"~ ").$Od;$xf=($Od!==false?($gd?"":"~ ").lang_format(array('%d row','%d rows'),$Od):"");echo
checkbox("all",1,0,$xf,on('click','countRows',$sc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]||$_POST["val"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Md=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]||($c["col"]!=""&&!isset($l[$c["col"]]))){unset($Md['sql']);break;}}if($Md){print_fieldset("export",'Export'." <span id='selected2'></span>");$Kh=adminer()->dumpOutput();echo($Kh?html_select("output",$Kh,$la["output"])." ":""),html_select("format",$Md,$la["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Qc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($Il?input_hidden(ini_get("session.upload_progress.name"),$Il):""),file_input(" name='csv_file'"," ".html_select("separator",array("csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"),$la["format"])." <input type='submit' name='import' value='".'Import'."'>".($Il?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$q&&$N?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$mk=isset($_GET["status"]);page_header($mk?'Status':'Variables');$Yl=($mk?adminer()->showStatus():adminer()->showVariables());if(!$Yl)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($Yl
as$K){echo"<tr>";$w=array_shift($K);echo"<th><code class='jush-".JUSH.($mk?"status":"set")."'>".h($w)."</code>";foreach($K
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$vk=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach(table_status()as$B=>$S){json_row("Comment-$B",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(array("Engine","Collation")as$w)json_row("$w-$B",h($S[$w]));foreach(array_keys($vk+array("Auto_increment"=>0,"Rows"=>0))as$w){if(array_key_exists($w,$S))json_row("$w-$B",format_status($S,$w));if($S[$w]!=""&&isset($vk[$w]))$vk[$w]+=($S["Engine"]!="InnoDB"||$w!="Data_free"?$S[$w]:0);}}}if(function_exists('Adminer\db_status'))$vk=db_status();foreach($vk
as$w=>$X)json_row("sum-$w",adminer()->formatSizeValue($X));json_row("");}elseif($_GET["script"]=="kill"){if(!$j)connection()->query("KILL ".number($_POST["kill"]));}else{foreach(count_tables(adminer()->databases(false))as$h=>$X){json_row("tables-$h",format_number($X));json_row("size-$h",db_size($h));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$kg=ME.(isset($_GET["select"])?"select=&":"");$Mk=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($Mk&&!$j&&!$_POST["search"]){$I=true;$og="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$og='Tables have been truncated.';}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$og='Tables have been moved.';}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$og='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$og='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$K)$og
.="<b>".h($R)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$K)$og
.="<b>".h($R)."</b>: ".h($K["MessageText"])."<br>";}}elseif(JUSH!="sql"){$I=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$og='Tables have been optimized.';}elseif(!$_POST["tables"])$og='No tables.';elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($K=$I->fetch_assoc())$og
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect(relative_uri(),$og,$I);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$j,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$D=$_GET["order"];$Rd=($D||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$Lk=($Rd?table_status():tables_list());if(!$Lk)echo"<p class='message'>".'No tables.'."\n";else{echo"<form action='' method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"))," <input type='search' name='query' value='".h($_POST["query"])."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$j&&$_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr>','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th class="sticky"'.(!$D&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($kg,0,-1)).'">'.'Table'.'</a>';$d=array("Engine"=>array('Engine'.doc_link(array('sql'=>'storage-engines.html'))));if(collations())$d["Collation"]=array('Collation'.doc_link(array('sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/')));if(function_exists('Adminer\alter_table'))$d["Data_length"]=array('Data Length'.doc_link(array('sql'=>'show-table-status.html',)),"create",'Alter table',);if(support("indexes"))$d["Index_length"]=array('Index Length'.doc_link(array('sql'=>'show-table-status.html',)),"indexes",'Alter indexes',);$d["Data_free"]=array('Data Free'.doc_link(array('sql'=>'show-table-status.html')),"edit",'New item');if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=array('Auto Increment'.doc_link(array('sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/')),"auto_increment=1&create",'Alter table',);$d["Rows"]=array('Rows'.doc_link(array('sql'=>'show-table-status.html',)),"select",'Select data',);if(support("comment"))$d["Comment"]=array('Comment'.doc_link(array('sql'=>'show-table-status.html',)),);$Aa=array('Engine','Collation','Comment');foreach($d
as$w=>$c)echo"<th".($D==$w?" aria-sort='".(in_array($w,$Aa)?"ascending":"descending")."'":"")."><a href='".h($kg)."order=$w'>$c[0]</a>";echo"<tbody>\n";if($D){uasort($Lk,function($fa,$Ha)use($D,$Aa){$J=($fa[$D]<$Ha[$D]?-1:($fa[$D]>$Ha[$D]?1:0));return(in_array($D,$Aa)?$J:-$J);});}$T=0;$vk=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach($Lk
as$B=>$mk){$bm=($Rd?is_view($mk):$mk!==null&&!preg_match('~table|sequence~i',$mk));$mk=($Rd?$mk:array('Engine'=>$mk));$s=h("Table-".$B);echo'<tr><td class="hover">'.checkbox(($bm?"views[]":"tables[]"),$B,in_array("$B",$Mk,true),"","","",$s),'<th class="sticky">'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($B)."' title='".'Show structure'."' id='$s'>".h($B).'</a>':h($B));if($bm&&!preg_match('~materialized~i',$mk['Engine'])){$al='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($B)."' title='".'Alter view'."'>$al</a>":$al),"<td align='right'><a href='".h(ME)."select=".url_escape($B)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($mk['Comment']);}else{if($Rd){foreach(array_keys($vk)as$w)$vk[$w]+=($mk["Engine"]!="InnoDB"||$w!="Data_free"?idx($mk,$w):0);}foreach($d
as$w=>$c){$s=" id='$w-".h($B)."'";echo($c[1]?"<td align='right'><a href='".h(ME."$c[1]=").url_escape($B)."'$s title='$c[2]'>".format_status($mk,$w)."</a>":"<td$s>".h(idx($mk,$w,'?')).($w=="Comment"&&$mk["Error"]?" <span class='error'>".h($mk["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th class='sticky'>".sprintf('%d in total',count($Lk)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),(collations()?"<td>".h(db_collation(DB,collations())):'');if($Rd&&function_exists('Adminer\db_status'))$vk=db_status();foreach($vk
as$w=>$uk)echo($d[$w]?"<td align='right' id='sum-$w'>".($Rd?adminer()->formatSizeValue($uk):""):"");echo"\n","</table>\n",($Rd?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$Ul="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$vh="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$Ai=(JUSH=="sqlite"?$Ul."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$Ul.$vh:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$vh."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($Ai?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$Ai\n</div></fieldset>\n":"");$g=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($g)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$h=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($g?html_select("target",$g,$h):'<input name="target" value="'.h($h).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$qj=routines();if($qj){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<th>'.'Type'.'<th>'.'Return type'."<td class='hover'><tbody>\n";foreach($qj
as$K){$B=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".url_escape($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($K["SPECIFIC_NAME"]).$B).'"'.' title="'.($K["DTD_IDENTIFIER"]=="trigger"?'Triggers':'Call').'">'.h($K["ROUTINE_NAME"]).'</a>','<td>'.h($K["ROUTINE_TYPE"]),'<td>'.h($K["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($K["SPECIFIC_NAME"]).$B).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".'Name'."<th>".'Schedule'."<th>".'Start'."<th>".'End'."<td class='hover'><tbody>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?'At given time'."<td>".h($K["Execute at"]):'Every'." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($K["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$dd=get_val("SELECT @@event_scheduler");if($dd&&$dd!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($dd)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();