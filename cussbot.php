#!/usr/bin/php
<?php
/*  cussbot.php - An IRC bot to track how much users cuss.
    Copyright (C) 2013 Michael Marley <michael@michaelmarley.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$ircserver="localhost";
$ircport=6667;
$ircpassword="youwish";
$sqlserver="localhost";
$sqlusername="you";
$sqlpassword="wish";
$sqldb="cussbot";
$botnick="CussBot";
$botname="Cuss Statistics Bot";

sqlconnect();
ircconnect();

$cusswords=array();
$sqlresult=sqlquery("SELECT word FROM words");
for($i=0;$i<sqlnumrows($sqlresult);$i++){
	array_push($cusswords,sqlresult($sqlresult,$i,"word"));
}

while(true){
	if(trim($line=ircreadline())==""){
		checkircconnection();
		continue;
	}
	$linearray=explode(" :",$line,2);
	if(trim($linearray[0])=="PING"){
		irccommand("PONG :$linearray[1]");
		checkmysqlconnection();
		continue;
	}
	$prefixarray=explode(" ",trim($linearray[0]));
	$senderarray=explode("!",$prefixarray[0]);
	if(count($senderarray)==1){
		continue;
	}
	$sender=substr($senderarray[0],1);
	if(count($prefixarray)>=3){
		$target=$prefixarray[2];
	}else{
		continue;
	}
	if($target==$botnick){
		$target=$sender;
	}
	if($prefixarray[1]=="PRIVMSG"){
		if(count($linearray)==2){
			$message=trim($linearray[1]);
			$sender=trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/','',$sender)));
			if(startsWith(trim(strtolower($message)),strtolower($botnick) . ":")){
				$command=str_ireplace("$botnick:","",$message);
			}else if(startsWith(trim(strtolower($message)),strtolower($botnick) . ",")){
				$command=str_ireplace("$botnick,","",$message);
			}else if($target==$sender){
				$command=$message;
			}else{
				$command=null;
			}
			if(isset($command)){
				$commandarray=explode(" ",trim($command));
				if($commandarray[0]=="help"){
					if(count($commandarray)==1){
						ircprivmessage("Valid commands are \"help\", \"search\" (see \"search help\" for arguments), \"stats\", \"stats <nick>\", \"stats <#channel>\", \"webstats\", \"listadmins\", \"addmin <nick>\", \"removemin <nick>\", \"listchannels\", \"join <#channel>\", \"part <#channel>\", \"addword <category> <word>\", \"removeword <word>\", \"listcategories\", \"addcategory <shortname> <fullname>\", \"removecategory <name>\", \"refreshwords\", \"source\".");
					}else{
						ircprivmessage("Invalid command!  Syntax is \"help\"");
					}
				}else if($commandarray[0]=="source"){
					if(count($commandarray)==1){
						ircprivmessage("https://github.com/mamarley/CussBot");
					}else{
						ircprivmessage("Invalid command!  Syntax is \"source\"");
					}
				}else if($commandarray[0]=="search"){
					if(count($commandarray)>=2){
						$conditions=array();
						$conditionstring="";
						for($i=2;$i<count($commandarray);$i++){
							if($commandarray[$i]=="--nick"){
								if(isset($commandarray[$i+1])){
									array_push($conditions,"nick=\"" . sqlescape(trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/','',$commandarray[$i+1])))) . "\" ");
									$i++;
								}else{
									ircprivmessage("Invalid value for argument \"--nick\", ignoring!");
								}
							}else if($commandarray[$i]=="--channel"){
								if(isset($commandarray[$i+1])){
									array_push($conditions,"name=\"" . sqlescape($commandarray[$i+1]) . "\" ");
									$i++;
								}else{
									ircprivmessage("Invalid value for argument \"--channel\", ignoring!");
								}
							}else if($commandarray[$i]=="--word"){
								if(isset($commandarray[$i+1])){
									array_push($conditions,"word=\"" . sqlescape($commandarray[$i+1]) . "\" ");
									$i++;
								}else{
									ircprivmessage("Invalid value for argument \"--word\", ignoring!");
								}
							}else if($commandarray[$i]=="--category"){
								if(isset($commandarray[$i+1])){
									array_push($conditions,"shortname=\"" . sqlescape(trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/','',$commandarray[$i+1])))) . "\" ");
									$i++;
								}else{
									ircprivmessage("Invalid value for argument \"--category\", ignoring!");
								}
							}else{
								ircprivmessage("Invalid argument $commandarray[$i], ignoring!");
							}
						}
						for($i=0;$i<count($conditions);$i++){
							$conditionstring.=$conditions[$i];
							if($i<(count($conditions)-1)){
								$conditionstring.="AND ";
							}
						}
						if($commandarray[1]=="help"){
							ircprivmessage("Valid searches are \"mostcommonword\", \"leastcommonword\", \"mostprolific\", \"leastprolific\", \"mostcommoncategory\", and \"leastcommoncategory\".");
							ircprivmessage("Valid arguments are \"--nick <nick>\", \"--channel <#channel>\", \"--word <word>\", and \"--category <category>\".");
							continue;
						}else if($commandarray[1]=="mostcommonword"){
							$lookup="word";
							$order="DESC";
							$what="most common cuss word";
							$item="use";
							$itemplural="s";
						}else if($commandarray[1]=="leastcommonword"){
							$lookup="word";
							$order="ASC";
							$what="least common cuss word";
							$item="use";
							$itemplural="s";
						}else if($commandarray[1]=="mostprolific"){
							$lookup="nick";
							$order="DESC";
							$what="most prolific cusser";
							$item="cuss";
							$itemplural="es";
						}else if($commandarray[1]=="leastprolific"){
							$lookup="nick";
							$order="ASC";
							$what="least prolific cusser";
							$item="cuss";
							$itemplural="es";
						}else if($commandarray[1]=="mostcommoncategory"){
							$lookup="fullname";
							$order="DESC";
							$what="most common cuss category";
							$item="use";
							$itemplural="s";
						}else if($commandarray[1]=="leastcommoncategory"){
							$lookup="fullname";
							$order="ASC";
							$what="least common cuss category";
							$item="use";
							$itemplural="s";
						}else{
							ircprivmessage("Invalid search!  Try \"search help\" for a list of valid searches.");
							continue;
						}
						$sqlresult=sqlquery("SELECT $lookup, COUNT($lookup) AS count FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id" . ($conditionstring!="" ? " WHERE $conditionstring" : "") . " GROUP BY $lookup ORDER BY count $order LIMIT 1");
						if(sqlnumrows($sqlresult)>0){
							ircprivmessage("The $what was \"" . sqlresult($sqlresult,0,$lookup) . "\" with " . sqlresult($sqlresult,0,"count") . " $item" . ((int)sqlresult($sqlresult,0,"count")!=1 ? $itemplural:"") . ".");
						}else{
							ircprivmessage("Your query returns no results.  Please check any conditions and try again.");
						}
					}else{
						ircprivmessage("Invalid search!  Try \"search help\" for a list of valid searches.");
					}
				}else if($commandarray[0]=="stats"){
					if((count($commandarray)==2)&&(substr($commandarray[1],0,1)=="#")){
						$condition="WHERE name=\"" . sqlescape($commandarray[1]) . "\"";
					}else if(count($commandarray)==2){
						$condition="WHERE nick=\"" . sqlescape(trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/','',$commandarray[1])))) . "\"";
					}else if(count($commandarray)>2){
						ircprivmessage("Invalid command!  Syntax is \"stats\" or \"stats <nick>\" or \"stats <#channel>\"");
						continue;
					}else{
						$condition="";
					}
					$sqlresult=sqlquery("SELECT fullname, COUNT(fullname) AS count FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id $condition GROUP BY fullname");
					if(sqlnumrows($sqlresult)>0){
						$response="";
						for($i=0;$i<sqlnumrows($sqlresult);$i++){
							$response.=sqlresult($sqlresult,$i,"count") . " " . sqlresult($sqlresult,$i,"fullname") . ((int)sqlresult($sqlresult,$i,"count")!=1 ? "s":"");
							if($i<(sqlnumrows($sqlresult)-1)){
								$response.=", ";
							}
						}
						ircprivmessage($response);
					}else{
						ircprivmessage("Your query returns no results.  Please check any conditions and try again.");
					}
				}else if($commandarray[0]=="webstats"){
					if(count($commandarray)==1){
						ircprivmessage("http://michaelmarley.com/cussbotstatistics/index.php");
					}else{
						ircprivmessage("Invalid command!  Syntax is \"webstats\"");
					}
				}else if($commandarray[0]=="listadmins"){
					if(count($commandarray)==1){
						$response="Admins are: ";
						$sqlresult=sqlquery("SELECT nick FROM admins");
						for($i=0;$i<sqlnumrows($sqlresult);$i++){
							$response.=sqlresult($sqlresult,$i,"nick") . " ";
						}
						ircprivmessage($response);
					}else{
						ircprivmessage("Invalid command!  Syntax is \"listadmins\"");
					}
				}else if($commandarray[0]=="addmin"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT nick FROM admins WHERE nick=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)==0){
								sqlquery("INSERT INTO admins (nick) VALUES ('" . sqlescape($commandarray[1]) . "')");
								ircprivmessage("User \"$commandarray[1]\" is now an admin.");
							}else{
								ircprivmessage("User \"$commandarray[1]\" is already an admin.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"addmin <nick>\"");
					}
				}else if($commandarray[0]=="removemin"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT nick, immutable FROM admins WHERE nick=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)>0){
								$immutable=sqlresult($sqlresult,0,"immutable");
								$sqlresult=sqlquery("SELECT nick FROM `admins`");
								if(sqlnumrows($sqlresult)==1){
									ircprivmessage("You are the last admin remaining.  You cannot remove your privileges.");
								}else if($immutable){
									ircprivmessage("$commandarray[1]'s admin privileges cannot be removed!");
								}else{
									sqlquery("DELETE FROM admins WHERE nick='" . sqlescape($commandarray[1]) . "'");
									ircprivmessage("User \"$commandarray[1]\" is no longer an admin.");
								}
							}else{
								ircprivmessage("User \"$commandarray[1]\" is not an admin.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"removemin <nick>\"");
					}
				}else if($commandarray[0]=="listchannels"){
					if(count($commandarray)==1){
						$response="I am currently a member of: ";
							$sqlresult=sqlquery("SELECT name FROM channels");
							for($i=0;$i<sqlnumrows($sqlresult);$i++){
								$response.=sqlresult($sqlresult,$i,"name") . " ";
							}
						ircprivmessage($response);
					}else{
						ircprivmessage("Invalid command!  Syntax is \"listchannels\"");
					}
				}else if($commandarray[0]=="join"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							if(substr($commandarray[1],0,1)=="#"){
								$sqlresult=sqlquery("SELECT name FROM channels WHERE name=\"" . sqlescape($commandarray[1]) . "\"");
								if(sqlnumrows($sqlresult)==0){
									sqlquery("INSERT INTO channels (name) VALUES ('" . sqlescape($commandarray[1]) . "')");
									irccommand("JOIN " . $commandarray[1]);
									ircprivmessage("I will now join $commandarray[1].");
								}else{
									ircprivmessage("I have already joined $commandarray[1].");
								}
							}else{
								ircprivmessage("Invalid channel name!");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"join <#channel>\"");
					}
				}else if($commandarray[0]=="part"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT name FROM channels WHERE name=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)>0){
								$sqlresult=sqlquery("SELECT name FROM channels");
								if(sqlnumrows($sqlresult)==1){
									ircprivmessage("$commandarray[1] is the last remaining channel that I am a member of.  I cannot part.");
								}else{
									sqlquery("DELETE FROM channels WHERE name='" . sqlescape($commandarray[1]) . "'");
									ircprivmessage("I will now part $commandarray[1].");
									irccommand("PART " . $commandarray[1]);
								}
							}else{
								ircprivmessage("I am not a member of $commandarray[1].");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"part <#channel>\"");
					}
				}else if($commandarray[0]=="addword"){
					if(count($commandarray)==3){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT word FROM words WHERE word=\"" . sqlescape($commandarray[2]) . "\"");
							if(sqlnumrows($sqlresult)==0){
								$sqlresult=sqlquery("SELECT id, shortname FROM categories WHERE shortname=\"" . sqlescape($commandarray[1]) . "\"");
								if(sqlnumrows($sqlresult)!=0){
									$categoryid=sqlresult($sqlresult,0,"id");
									sqlquery("INSERT INTO words (categoryid, word) VALUES (" . sqlescape($categoryid) . ", '" . sqlescape($commandarray[2]) . "')");
									array_push($cusswords,$commandarray[2]);
									ircprivmessage("I now recognize \"$commandarray[2]\" as a cuss word.");
								}else{
									ircprivmessage("Error!  The category \"$commandarray[1]\" does not exist.");
								}
							}else{
								ircprivmessage("I already recognize \"$commandarray[2]\" as a cuss word.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"addword <category> <word>\"");
					}
				}else if($commandarray[0]=="removeword"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT word FROM words WHERE word=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)>0){
								sqlquery("DELETE FROM words WHERE word='" . sqlescape($commandarray[1]) . "'");
								foreach ($cusswords as $key => $value) {
									if($value==$commandarray[1]) {
										unset($cusswords[$key]);
									}
								}
								ircprivmessage("I no longer recognize \"$commandarray[1]\" as a cuss word.");
							}else{
								ircprivmessage("I do not recognize \"$commandarray[1]\" as a cuss word.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"removeword <word>\"");
					}
				}else if($commandarray[0]=="refreshwords"){
					if(count($commandarray)==1){
						$cusswords=array();
						$sqlresult=sqlquery("SELECT word FROM words");
						for($i=0;$i<sqlnumrows($sqlresult);$i++){
							array_push($cusswords,sqlresult($sqlresult,$i,"word"));
						}
						ircprivmessage("I have refreshed my word list successfully!");
					}else{
						ircprivmessage("Invalid command!  Syntax is \"refreshwords\"");
					}
				}else if($commandarray[0]=="listcategories"){
					if(count($commandarray)==1){
						$response="The cuss categories I know are: ";
							$sqlresult=sqlquery("SELECT shortname FROM categories");
							for($i=0;$i<sqlnumrows($sqlresult);$i++){
								$response.="\"" . sqlresult($sqlresult,$i,"shortname") . "\" ";
							}
						ircprivmessage($response);
					}else{
						ircprivmessage("Invalid command!  Syntax is \"listcategories\"");
					}
				}else if($commandarray[0]=="addcategory"){
					if(count($commandarray)>=3){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT shortname FROM categories WHERE shortname=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)==0){
								$fullname="";
								for($i=2;$i<count($commandarray);$i++){
									$fullname.=$commandarray[$i];
									if($i<(count($commandarray)-1)){
										$fullname.=" ";
									}
								}
								sqlquery("INSERT INTO categories (shortname, fullname) VALUES ('" . sqlescape($commandarray[1]) . "', '" . sqlescape($fullname) . "')");
								ircprivmessage("I now recognize \"$commandarray[1]\" as a cuss category.");
							}else{
								ircprivmessage("I already recognize \"$commandarray[1]\" as a cuss category.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"addcategory <shortname> <fullname>\"");
					}
				}else if($commandarray[0]=="removecategory"){
					if(count($commandarray)==2){
						if(senderisadmin($sender)){
							$sqlresult=sqlquery("SELECT shortname FROM categories WHERE shortname=\"" . sqlescape($commandarray[1]) . "\"");
							if(sqlnumrows($sqlresult)>0){
								sqlquery("DELETE FROM categories WHERE shortname='" . sqlescape($commandarray[1]) . "'");
								ircprivmessage("I no longer recognize \"$commandarray[1]\" as a cuss category.");
							}else{
								ircprivmessage("I do not recognize \"$commandarray[1]\" as a cuss category.");
							}
						}
					}else{
						ircprivmessage("Invalid command!  Syntax is \"removecategory <shortname>\"");
					}
				}else{
					ircprivmessage("Invalid command!  Try \"help\"");
				}
			}else{
				if(($sender!="MCLUGBridge")&&($sender!="TeeBridge")){
					foreach($cusswords as $cussword){
						if(stristr($message,$cussword)){
							sqlquery("INSERT INTO uses (nick,channelid,wordid,message) VALUES ('" . sqlescape(trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/','',$sender)))) . "',(SELECT id FROM channels WHERE name='" . sqlescape($target) . "'),(SELECT id FROM words WHERE word='" . sqlescape($cussword) . "'),'" . sqlescape($message) . "');");
						}
					}
				}
			}
		}
	}
}

function ircconnect($host){
	do{
		$GLOBALS['ircconnection']=fsockopen($GLOBALS['ircserver'],$GLOBALS['ircport']);
	}while(($GLOBALS['ircconnection']==false)&&(sleep(2)||true));
	
	irccommand("NICK " . $GLOBALS['botnick']);
	irccommand("USER " . $GLOBALS['botnick'] . " 8 * : " . $GLOBALS['botname']);
	irccommand("PASS " . $GLOBALS['botnick'] . ":" . $GLOBALS['ircpassword']);
	
	$sqlresult=sqlquery("SELECT * FROM channels",$GLOBALS['mysqlconnection']);
	for($i=0;$i<sqlnumrows($sqlresult);$i++){
		irccommand("JOIN " . sqlresult($sqlresult,$i,"name"));
	}
	if(sqlnumrows($sqlresult)==0){
		print("WARNING: Channels list is empty. Joining ##bottest.\n");
		irccommand("JOIN ##bottest");
	}
}

function checkircconnection(){
	fwrite($GLOBALS['ircconnection'],"\n");
	if(fwrite($GLOBALS['ircconnection'],"\n")==false){
		fclose($GLOBALS['ircconnection']);
		ircconnect($GLOBALS['ircserver']);
	}
}

function irccommand($commandarray){
	print($commandarray . "\n");
	fwrite($GLOBALS['ircconnection'],"$commandarray\n");
}

function ircprivmessage($message){
	if($message!=""){
		irccommand("PRIVMSG " . $GLOBALS['target'] ." :$message");
	}
}

function ircreadline(){
	$line=fgets($GLOBALS['ircconnection']);
	print($line);
	return $line;
}

function sqlconnect(){
	do{
		$GLOBALS['mysqlconnection']=mysqli_connect($GLOBALS['sqlserver'],$GLOBALS['sqlusername'],$GLOBALS['sqlpassword']);
	}while((!$GLOBALS['mysqlconnection'])&&(sleep(2)||TRUE));
	mysqli_select_db($GLOBALS['mysqlconnection'],$GLOBALS['sqldb']);
}

function checkmysqlconnection(){
	if(!mysqli_ping($GLOBALS['mysqlconnection'])){
		mysqli_close($GLOBALS['mysqlconnection']);
		sqlconnect();
	}
}

function sqlquery($query){
	checkmysqlconnection();
	return mysqli_query($GLOBALS['mysqlconnection'],$query);
}

function sqlresult($res,$row,$field=0){
	$res->data_seek($row);
	$datarow=$res->fetch_array();
	return $datarow[$field];
}

function sqlescape($string){
	return mysqli_real_escape_string($GLOBALS['mysqlconnection'],$string);
}

function sqlnumrows($result){
	return mysqli_num_rows($result);
}

function senderisadmin($sender){
	if(sqlnumrows(sqlquery("SELECT * FROM admins WHERE nick=\"" . sqlescape($sender) . "\""))>0){
		return true;
	}else{
		ircprivmessage("Insufficient privileges!");
		return false;
	}
}

function startsWith($haystack,$needle){
	return !strncmp($haystack,$needle,strlen($needle));
}

?>
