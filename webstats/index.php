<?php
if(isset($_GET["piedata"])){
	sqlconnect();
	$conditions=array();
	$conditionstring="";
	if((isset($_GET["channel"]))&&($_GET["channel"]!="all")){
		array_push($conditions,"name=\"" . sqlescape(urldecode($_GET["channel"])) . "\" ");
	}
	if((isset($_GET["nick"]))&&($_GET["nick"]!="all")){
		array_push($conditions,"nick=\"" . sqlescape(urldecode($_GET["nick"])) . "\" ");
	}
	for($i=0;$i<count($conditions);$i++){
		$conditionstring.=$conditions[$i];
		if($i<(count($conditions)-1)){
			$conditionstring.="AND ";
		}
	}
	$sqlresult=sqlquery("SELECT fullname, COUNT(fullname) AS count FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id " . ($conditionstring=="" ? "":"WHERE $conditionstring") . " GROUP BY fullname");
	$response="[";
	for($i=0;$i<sqlnumrows($sqlresult);$i++){
		$response.="{\"label\":\"" . sqlresult($sqlresult,$i,"fullname") . "s" . "\",\"data\":[[1," . sqlresult($sqlresult,$i,"count") . "]]}";
		if($i<(sqlnumrows($sqlresult)-1)){
			$response.=",";
		}
	}
	$response.="]";
	print($response);
}else if(isset($_GET["linedata"])){
	sqlconnect();
	$conditions=array();
	$conditionstring="";
	if((isset($_GET["channel"]))&&($_GET["channel"]!="all")){
		array_push($conditions,"AND name=\"" . sqlescape(urldecode($_GET["channel"])) . "\" ");
	}
	if((isset($_GET["nick"]))&&($_GET["nick"]!="all")){
		array_push($conditions,"AND nick=\"" . sqlescape(urldecode($_GET["nick"])) . "\" ");
	}
	for($i=0;$i<count($conditions);$i++){
		$conditionstring.=$conditions[$i];
	}
	
	$response="[";
	$currentdate=date("Ymd");
	$sqlresult=sqlquery("SELECT shortname FROM categories");
	for($i=0;$i<sqlnumrows($sqlresult);$i++){
		$sqlresult2=sqlquery("SELECT DATE_FORMAT(date,'%Y%m%d') AS date, COUNT(date) AS numwords FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id WHERE date > date_add(CURTIME(), INTERVAL -10 DAY) AND shortname=\"" . sqlresult($sqlresult,$i,"shortname") . "\" $conditionstring GROUP BY DAY(date);");
		$response.="{\"label\":\"" . sqlresult($sqlresult,$i,"shortname") . "\",\"data\":[";
	
		$datapoints=array();
		for($j=0;$j<10;$j++){
			$datapoints[$currentdate-$j]=0;
		}
		
		if(sqlnumrows($sqlresult2)>0){
			for($j=0;$j<sqlnumrows($sqlresult2);$j++){
				$datapoints[sqlresult($sqlresult2,$j,"date")]=sqlresult($sqlresult2,$j,"numwords");
			}
		}
		
		foreach($datapoints as $date => $value){
			$response.="[$date,$value],";
		}
		$response=substr_replace($response,"",-1);
		$response.="]},";
	}
	$response=substr_replace($response,"",-1);
	$response.="]";
	print($response);
}else if(isset($_GET["nicklist"])){
	sqlconnect();
	$conditions="";
	if((isset($_GET["channel"]))&&($_GET["channel"]!="all")){
		$conditions.="name=\"" . sqlescape(urldecode($_GET["channel"])) . "\" ";
	}
	$sqlresult=sqlquery("SELECT DISTINCT nick FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id " . ($conditions=="" ? "":"WHERE $conditions"));
	$response="<option value=\"all\">all</option>";
	for($i=0;$i<sqlnumrows($sqlresult);$i++){
		$response.="<option value=\"" . urlencode(sqlresult($sqlresult,$i,"nick")) . "\">" . sqlresult($sqlresult,$i,"nick") . "</option>";
		if($i<(sqlnumrows($sqlresult)-1)){
			$response.=",";
		}
	}
	print($response);
}else if(isset($_GET["channellist"])){
	sqlconnect();
	$sqlresult=sqlquery("SELECT DISTINCT name FROM uses JOIN words ON uses.wordid=words.id JOIN categories ON words.categoryid=categories.id JOIN channels ON uses.channelid=channels.id");
	$response="<option value=\"all\">all</option>";
	for($i=0;$i<sqlnumrows($sqlresult);$i++){
		$response.="<option value=\"" . urlencode(sqlresult($sqlresult,$i,"name")) . "\">" . sqlresult($sqlresult,$i,"name") . "</option>";
		if($i<(sqlnumrows($sqlresult)-1)){
			$response.=",";
		}
	}
	print($response);
}else{
?>
<!doctype html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>CussBot Statistics</title>
		<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="excanvas.min.js"></script><![endif]-->
		<script type="text/javascript" src="flot.js"></script>
		<script type="text/javascript">
			if(window.XMLHttpRequest){
				menuxmlhttp=new XMLHttpRequest();
				piexmlhttp=new XMLHttpRequest();
				linexmlhttp=new XMLHttpRequest();
			}else if(window.ActiveXObject){
				menuxmlhttp=new ActiveXObject("MSXML2.XMLHTTP.3.0");
				piexmlhttp=new ActiveXObject("MSXML2.XMLHTTP.3.0");
				linexmlhttp=new ActiveXObject("MSXML2.XMLHTTP.3.0");
			}else{
				alert("Browser does not support XMLHttpRequest!");
			}
			
			function initialize(){
				piexmlhttp.onreadystatechange=function(){
					if(piexmlhttp.readyState==4){
						$.plot($("#piegraph"),JSON.parse(piexmlhttp.responseText),{
							series:{
								pie:{ 
									show:true,
									radius:1,
									label:{
										show:true,
										radius:1,
										formatter:function(label,series){
											return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+series.data[0][1]+' ('+Math.round(series.percent)+'%)</div>';
										},
										background:{opacity:0.8}
									}
								}
							},
							legend:{
								show:false
							}
						});
					}
				};
				linexmlhttp.onreadystatechange=function(){
					if(linexmlhttp.readyState==4){
						$.plot("#linegraph",JSON.parse(linexmlhttp.responseText),{
							lines:{
								show:true
							},
							points:{
								show:true
							}
						});
					}
				};
				updatechannels();
				updatenicks();
				redrawgraphs();
			}
			
			function redrawgraphs(){
				redrawpie();
				redrawline();
			}
			
			function redrawpie(){
				piexmlhttp.open("GET","index.php?piedata=true&channel="+document.getElementById("channels").value+"&nick="+document.getElementById("nicks").value,false);
				piexmlhttp.send(null);
			}
			
			function redrawline(){
				linexmlhttp.open("GET","index.php?linedata=true&channel="+document.getElementById("channels").value+"&nick="+document.getElementById("nicks").value,false);
				linexmlhttp.send(null);
			}
			
			function updatechannels(){
				menuxmlhttp.open("GET","index.php?channellist=true",false);
				menuxmlhttp.send(null);
				document.getElementById('channels').innerHTML=menuxmlhttp.responseText;
			}
			
			function updatenicks(){
				menuxmlhttp.open("GET","index.php?nicklist=true&channel="+document.getElementById("channels").value,false);
				menuxmlhttp.send(null);
				document.getElementById('nicks').innerHTML=menuxmlhttp.responseText;
			}
		</script>
	</head>
	<body onLoad="initialize();">
		<h1 style="text-align:center;">CussBot Statistics</h1>
		<div style="width:700px;margin-left:auto;margin-right:auto;text-align:center;">
			<form>
				Channel:<select id="channels" name="channels" onChange="updatenicks();redrawgraphs();"></select>
				<br />
				Nick:<select id="nicks" name="nicks" onChange="redrawgraphs();"></select>
			</form>
			<div id="piegraph" style="width:700px;height:300px;"></div>
			<div id="linegraph" style="width:700px;height:300px;"></div>
		</div>
	</body>
</html>
<?php
}

function sqlconnect(){
	$GLOBALS['mysqlconnection']=mysqli_connect('localhost','you','wish');
	mysqli_select_db($GLOBALS['mysqlconnection'],'cussbot');
}

function sqlquery($query){
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
?>
